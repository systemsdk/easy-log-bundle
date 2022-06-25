<?php

declare(strict_types=1);

namespace Systemsdk\Bundle\EasyLogBundle\Formatter;

use DateTimeImmutable;
use DateTimeInterface;
use Monolog\Formatter\FormatterInterface;
use Monolog\Level;
use Monolog\LogRecord;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

use function array_key_exists;
use function get_class;
use function in_array;
use function is_bool;
use function is_object;
use function is_string;
use function strlen;

/**
 * Class EasyLogFormatter
 *
 * This formatter is specially designed to make logs more human-friendly in the development environment.
 * It takes all the log records and processed them in batch to perform advanced tasks
 * (such as combining similar consecutive logs).
 */
class EasyLogFormatter implements FormatterInterface
{
    private const DISPLAY_LOG_INFO_FIELD = 'display_log_info';
    private const TITLE_ASSETIC_REQUEST = 'Assetic request';
    private const TITLE_DATE_FORMAT = 'd/M/Y H:i:s';
    private const PHP_SERIALIZED_OBJECT_PREFIX = '- !php/object:';

    public function __construct(
        private readonly int $maxLineLength,
        private readonly int $prefixLength,
        private readonly array $ignoredRoutes
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException
     */
    public function format(LogRecord $record): mixed
    {
        // The method "format()" should never be called (call "formatBatch()" instead).
        throw new RuntimeException(
            'Wrong "monolog" configuration. Please read EasyLogHandler README configuration instructions.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function formatBatch(array $records): LogRecord
    {
        $logBatch = new LogRecord(
            datetime: new DateTimeImmutable('now'),
            channel: 'php',
            level: Level::Debug,
            message: '',
            formatted: ''
        );

        if ($this->isInIgnoreList($records)) {
            return $logBatch;
        }

        $logBatch->offsetSet('formatted', $logBatch->formatted . $this->formatLogBatchHeader($records));

        foreach ($records as $key => $record) {
            $key = (int)$key;

            if ($this->isDeprecationLog($record)) {
                $records[$key] = $this->processDeprecationLogRecord($record);
            }

            if ($this->isEventStopLog($record)) {
                $records[$key] = $this->processEventStopLogRecord($record);
            }

            if ($this->isEventNotificationLog($record)) {
                $records[$key] = $this->processEventNotificationLogRecord($records, $key);
            }

            if ($this->isTranslationLog($record)) {
                $records[$key] = $this->processTranslationLogRecord($records, $key);
            }

            if ($this->isRouteMatchLog($record)) {
                $records[$key] = $this->processRouteMatchLogRecord($record);
            }

            if ($this->isDoctrineLog($record)) {
                $records[$key] = $this->processDoctrineLogRecord($record);
            }

            $logBatch->offsetSet(
                'formatted',
                $logBatch->formatted . rtrim($this->formatRecord($records, $key), PHP_EOL) . PHP_EOL
            );
        }

        $logBatch->offsetSet('formatted', $logBatch->formatted . PHP_EOL . PHP_EOL);

        return $logBatch;
    }

    private function isInIgnoreList(array $records): bool
    {
        foreach ($records as $record) {
            if ($this->ignoredRoutes
                && isset($record->context['route'])
                && in_array($record->context['route'], $this->ignoredRoutes, true)
            ) {
                return true;
            }
        }

        return false;
    }

    private function isAsseticLog(LogRecord $record): bool
    {
        return isset($record->context['route']) && strpos((string)$record->context['route'], '_assetic_') === 0;
    }

    private function isDeprecationLog(LogRecord $record): bool
    {
        $isPhpChannel = $record->channel === 'php';
        $isDeprecationError = isset($record->context['type']) && $record->context['type'] === E_USER_DEPRECATED;
        $looksLikeDeprecationMessage = strpos($record->message, 'deprecated since') !== false;

        return $isPhpChannel && ($isDeprecationError || $looksLikeDeprecationMessage);
    }

    private function isEventStopLog(LogRecord $record): bool
    {
        return $record->message === 'Listener "{listener}" stopped propagation of the event "{event}".';
    }

    private function isEventNotificationLog(LogRecord $record): bool
    {
        $isEventNotifyChannel = $record->channel === '_event_notify';
        $isEventChannel = $record->channel === 'event';
        $context = $record->context;

        $contextWithEventNotification = isset($context['event'], $context['listener']);

        return $isEventNotifyChannel || ($isEventChannel && $contextWithEventNotification);
    }

    private function isTranslationLog(LogRecord $record): bool
    {
        return $record->channel === 'translation';
    }

    private function isRouteMatchLog(LogRecord $record): bool
    {
        return $record->message === 'Matched route "{route}".';
    }

    private function isDoctrineLog(LogRecord $record): bool
    {
        return $record->channel === 'doctrine';
    }

    private function formatLogBatchHeader(array $records): string
    {
        $firstRecord = isset($records[0]) && $records[0] instanceof LogRecord ? $records[0] : null;

        if ($firstRecord && $this->isAsseticLog($firstRecord)) {
            return $this->formatAsSubtitle(self::TITLE_ASSETIC_REQUEST);
        }

        $logDate = 'unknown_date';

        if ($firstRecord) {
            $logDate = $firstRecord->datetime;
        }

        $logDateAsString = is_object($logDate) ? $logDate->format(self::TITLE_DATE_FORMAT) : (string)$logDate;

        return $this->formatAsTitle($logDateAsString);
    }

    private function formatAsSubtitle(string $title): string
    {
        $subtitle = str_pad('###  ' . $title . '  ', $this->maxLineLength, '#', STR_PAD_BOTH);

        return $subtitle . PHP_EOL;
    }

    private function formatAsTitle(string $title): string
    {
        $titleLines = [
            str_repeat('#', $this->maxLineLength),
            rtrim($this->formatAsSubtitle($title), PHP_EOL),
            str_repeat('#', $this->maxLineLength),
        ];

        return implode(PHP_EOL, $titleLines) . PHP_EOL;
    }

    private function formatRecord(array $records, int $currentRecordIndex): string
    {
        $recordAsString = '';

        if (!isset($records[$currentRecordIndex]) || !($records[$currentRecordIndex] instanceof LogRecord)) {
            return $recordAsString;
        }

        $record = $records[$currentRecordIndex];

        if ($this->isLogInfoDisplayed($record)) {
            $logInfo = $this->formatLogInfo($record);
            $recordAsString .= $this->formatAsSection($logInfo);
        }

        if (!empty($record->message)) {
            $recordAsString .= $this->formatMessage($record) . PHP_EOL;
        }

        $context = $record->context;

        if (!empty($context)) {
            // if the context contains an error stack trace, remove it to display it separately
            $stack = null;

            if (isset($context['stack'])) {
                $stack = $context['stack'];
                unset($context['stack']);
            }

            $recordAsString .= $this->formatContext($record) . PHP_EOL;

            if ($stack !== null) {
                $recordAsString .= '--> Stack Trace:' . PHP_EOL;
                $recordAsString .= $this->formatStackTrace($stack, '    | ');
            }
        }

        if (!empty($record->extra)) {
            // don't display the extra information when it's identical to the previous log record
            $previousRecord = null;

            if (isset($records[$currentRecordIndex - 1]) && $records[$currentRecordIndex - 1] instanceof LogRecord) {
                $previousRecord = $records[$currentRecordIndex - 1];
            }

            $previousRecordExtra = $previousRecord?->extra;

            if ($record->extra !== $previousRecordExtra) {
                $recordAsString .= $this->formatExtra($record) . PHP_EOL;
            }
        }

        return $recordAsString;
    }

    private function processDeprecationLogRecord(LogRecord $record): LogRecord
    {
        $context = $record->context;
        if (isset($context['type'], $context['level'])) {
            unset($context['type'], $context['level']);
            $record = new LogRecord(
                $record->datetime,
                $record->channel,
                $record->level,
                $record->message,
                $context,
                $record->extra,
                $record->formatted
            );
        }

        return $record;
    }

    private function processEventStopLogRecord(LogRecord $record): LogRecord
    {
        return new LogRecord(
            $record->datetime,
            '_event_stop',
            $record->level,
            'Event "{event}" stopped by:',
            $record->context,
            $record->extra,
            $record->formatted
        );
    }

    /**
     * In Symfony applications is common to have lots of consecutive "event notify" log messages.
     * This method combines them all to generate a more compact output.
     */
    private function processEventNotificationLogRecord(array $records, int $currentRecordIndex): LogRecord
    {
        /** @var LogRecord $record */
        $record = $records[$currentRecordIndex];
        $context = $record->context;
        $contextNew = [];

        if (array_key_exists('event', $context) && array_key_exists('listener', $context)) {
            $contextNew = [$context['event'] => $context['listener']];
        }

        // if the previous record is also an event notification, combine them
        if (isset($records[$currentRecordIndex - 1])
            && $this->isEventNotificationLog($records[$currentRecordIndex - 1])
        ) {
            $record->extra[self::DISPLAY_LOG_INFO_FIELD] = false;
        }

        return new LogRecord(
            $record->datetime,
            '_event_notify',
            $record->level,
            '',
            $contextNew,
            $record->extra,
            $record->formatted
        );
    }

    /**
     * In Symfony applications is common to have lots of consecutive "translation not found" log messages.
     * This method combines them all to generate a more compact output.
     */
    private function processTranslationLogRecord(array $records, int $currentRecordIndex): LogRecord
    {
        /** @var LogRecord $record */
        $record = $records[$currentRecordIndex];

        if (isset($records[$currentRecordIndex - 1]) && $this->isTranslationLog($records[$currentRecordIndex - 1])) {
            $record->extra[self::DISPLAY_LOG_INFO_FIELD] = false;
            $record = new LogRecord(
                $record->datetime,
                $record->channel,
                $record->level,
                '',
                $record->context,
                $record->extra,
                $record->formatted
            );
        }

        return $record;
    }

    private function processRouteMatchLogRecord(LogRecord $record): LogRecord
    {
        if ($this->isAsseticLog($record)) {
            return new LogRecord(
                $record->datetime,
                $record->channel,
                $record->level,
                '{method}: {request_uri}',
                $record->context,
                $record->extra,
                $record->formatted
            );
        }

        if (array_key_exists('method', $record->context) && array_key_exists('request_uri', $record->context)) {
            $context = $record->context;
            unset($context['method'], $context['request_uri']);
            $record = new LogRecord(
                $record->datetime,
                $record->channel,
                $record->level,
                $record->message,
                array_merge(
                    [$record->context['method'] => $record->context['request_uri']],
                    $context
                ),
                $record->extra,
                $record->formatted
            );
        }

        return $record;
    }

    private function processDoctrineLogRecord(LogRecord $record): LogRecord
    {
        $isDatabaseQueryContext = $this->arrayContainsOnlyNumericKeys($record->context);

        if ($isDatabaseQueryContext) {
            $record = new LogRecord(
                $record->datetime,
                $record->channel,
                $record->level,
                $record->message,
                ['query params' => $record['context']],
                $record->extra,
                $record->formatted
            );
        }

        return $record;
    }

    /**
     * Interpolates the given string replacing its placeholders with the values defined in the given variables array.
     */
    private function processStringPlaceholders(string $string, array $variables): string
    {
        foreach ($variables as $key => $value) {
            if (!is_string($value) && !is_numeric($value) && !is_bool($value)) {
                continue;
            }

            $string = (string)str_replace('{' . $key . '}', (string)$value, $string);
        }

        return $string;
    }

    private function formatLogChannel(LogRecord $record): string
    {
        if ($this->isDeprecationLog($record)) {
            return '** DEPRECATION **';
        }

        if ($this->isEventNotificationLog($record)) {
            return 'NOTIFIED EVENTS';
        }

        $channelIcons = ['_event_stop' => '[!] ', 'security' => '(!) '];
        $channelIcon = array_key_exists($record->channel, $channelIcons) ? $channelIcons[$record->channel] : '';

        return sprintf('%s%s', $channelIcon, strtoupper($record->channel));
    }

    private function formatContext(LogRecord $record): string
    {
        $context = $this->filterVariablesUsedAsPlaceholders($record->message, $record->context);
        $context = $this->formatDateTimeObjects($context);
        $context = $this->formatThrowableObjects($context);

        $contextAsString = Yaml::dump(
            $context,
            $this->getInlineLevel($record),
            $this->prefixLength,
            Yaml::DUMP_OBJECT
        );

        if (substr(
            $contextAsString,
            (int)strpos($contextAsString, self::PHP_SERIALIZED_OBJECT_PREFIX),
            strlen(self::PHP_SERIALIZED_OBJECT_PREFIX)
        ) === self::PHP_SERIALIZED_OBJECT_PREFIX) {
            $contextAsString = $this->formatSerializedObject($contextAsString);
        }

        $contextAsString = $this->formatTextBlock($contextAsString, '--> ');

        return rtrim($contextAsString, PHP_EOL);
    }

    /**
     * Turns any Throwable object present in the given array into a string representation.
     * If the object cannot be serialized, an approximative representation of the object is given instead.
     */
    private function formatThrowableObjects(array $array): array
    {
        array_walk_recursive($array, function (&$value): void {
            if ($value instanceof Throwable) {
                try {
                    $value = serialize($value);
                } catch (Throwable $throwable) {
                    /* @phpstan-ignore-next-line */
                    $value = $this->formatThrowable($value);
                }
            }
        });

        return $array;
    }

    private function formatThrowable(Throwable $throwable): array
    {
        $previous = $throwable->getPrevious();

        return [
            'class' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => $throwable->getTraceAsString(),
            'previous' => $previous ? $this->formatThrowable($previous) : null,
        ];
    }

    private function formatSerializedObject(string $objectString): string
    {
        $objectPrefixLength = strlen(self::PHP_SERIALIZED_OBJECT_PREFIX);
        $objectStart = (int)strpos($objectString, self::PHP_SERIALIZED_OBJECT_PREFIX) + $objectPrefixLength;
        $beforePrefix = substr($objectString, 0, $objectStart - $objectPrefixLength);
        $objectAsString = print_r(unserialize(substr($objectString, $objectStart), ['allowed_classes' => true]), true);

        return $beforePrefix . $objectAsString;
    }

    private function formatExtra(LogRecord $record): string
    {
        $extra = $this->formatDateTimeObjects($record->extra);
        $extraAsString = Yaml::dump(['extra' => $extra], 1, $this->prefixLength);

        return $this->formatTextBlock($extraAsString, '--> ');
    }

    private function formatLogInfo(LogRecord $record): string
    {
        return sprintf('%s%s', $this->formatLogLevel($record), $this->formatLogChannel($record));
    }

    private function formatLogLevel(LogRecord $record): string
    {
        $level = $record->offsetGet('level_name');
        $levelLabels = [
            'DEBUG' => '',
            'INFO' => '',
            'WARNING' => '** WARNING ** ==> ',
            'ERROR' => '*** ERROR *** ==> ',
            'CRITICAL' => '*** CRITICAL ERROR *** ==> ',
        ];

        return array_key_exists($level, $levelLabels) ? $levelLabels[$level] : $level . ' ';
    }

    private function formatMessage(LogRecord $record): string
    {
        $message = $this->processStringPlaceholders($record->message, $record->context);

        return $this->formatStringAsTextBlock($message);
    }

    private function formatStackTrace(array $trace, string $prefix = ''): string
    {
        $traceAsString = '';
        foreach ($trace as $line) {
            if (isset($line['class'], $line['type'], $line['function'])) {
                $traceAsString .= sprintf('%s%s%s()', $line['class'], $line['type'], $line['function']) . PHP_EOL;
            } elseif (isset($line['class'])) {
                $traceAsString .= sprintf('%s', $line['class']) . PHP_EOL;
            } elseif (isset($line['function'])) {
                $traceAsString .= sprintf('%s()', $line['function']) . PHP_EOL;
            }

            if (isset($line['file'], $line['line'])) {
                $traceAsString .= sprintf(
                    '  > %s:%d',
                    $this->makePathRelative((string)$line['file']),
                    $line['line']
                ) . PHP_EOL;
            }
        }

        return $this->formatTextBlock($traceAsString, $prefix, true);
    }

    private function formatAsSection(string $text): string
    {
        $section = str_pad(
            str_repeat('_', 3) . ' ' . $text . ' ',
            $this->maxLineLength,
            '_'
        );

        return $section . PHP_EOL;
    }

    private function formatStringAsTextBlock(string $string): string
    {
        $string = wordwrap($string, $this->maxLineLength - $this->prefixLength);
        $stringLines = explode(PHP_EOL, $string);
        foreach ($stringLines as &$line) {
            $line = str_repeat(' ', $this->prefixLength) . $line;
        }
        unset($line);

        $string = implode(PHP_EOL, $stringLines);

        return trim($string);
    }

    /**
     * Prepends the prefix to every line of the given string.
     * If $prefixAllLines false, prefix is only added to lines that don't start with white spaces
     */
    private function formatTextBlock(string $text, string $prefix = '', bool $prefixAllLines = false): string
    {
        if (empty($text)) {
            return $text;
        }

        $textLines = explode(PHP_EOL, $text);
        // remove the trailing PHP_EOL (and add it back afterwards) to avoid formatting issues
        $addTrailingNewline = false;

        if ($textLines && $textLines[(int)count($textLines) - 1] === '') {
            array_pop($textLines);
            $addTrailingNewline = true;
        }

        $newTextLines = [];
        foreach ($textLines as $line) {
            if ($prefixAllLines) {
                $newTextLines[] = $prefix . $line;
            } elseif (isset($line[0]) && $line[0] !== ' ') {
                $newTextLines[] = $prefix . $line;
            } else {
                $newTextLines[] = str_repeat(' ', strlen($prefix)) . $line;
            }
        }

        return implode(PHP_EOL, $newTextLines) . ($addTrailingNewline ? PHP_EOL : '');
    }

    /**
     * Turns any DateTime object present in the given array into a string representation of that date and time.
     */
    private function formatDateTimeObjects(array $array): array
    {
        array_walk_recursive($array, static function (&$value): void {
            if ($value instanceof DateTimeInterface) {
                $value = date_format($value, 'c');
            }
        });

        return $array;
    }

    /**
     * It scans the given string for placeholders and removes from $variables
     * any element whose key matches the name of a placeholder.
     */
    private function filterVariablesUsedAsPlaceholders(string $string, array $variables): array
    {
        if (empty($string)) {
            return $variables;
        }

        return array_filter($variables, static function ($key) use ($string) {
            return strpos($string, '{' . $key . '}') === false;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * It returns the level at which YAML component inlines the values, which
     * determines how compact or readable the information is displayed.
     */
    private function getInlineLevel(LogRecord $record): int
    {
        if ($this->isTranslationLog($record)) {
            return 0;
        }

        if ($this->isDoctrineLog($record) || $this->isAsseticLog($record)) {
            return 1;
        }

        return 2;
    }

    /**
     * It returns true when the general information related to the record log should be displayed.
     * It returns false when a log is displayed in a compact way to combine it with a similar previous record.
     */
    private function isLogInfoDisplayed(LogRecord $record): bool
    {
        return $record->extra[self::DISPLAY_LOG_INFO_FIELD] ?? true;
    }

    private function arrayContainsOnlyNumericKeys(array $array): bool
    {
        return count(array_filter(array_keys($array), 'is_string')) === 0;
    }

    private function makePathRelative(string $filePath): string
    {
        $thisFilePathParts = explode('/src/', __FILE__);
        $projectRootDir = $thisFilePathParts[0] . DIRECTORY_SEPARATOR;

        return str_replace($projectRootDir, '', $filePath);
    }
}
