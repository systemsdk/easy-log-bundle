<?php

declare(strict_types=1);

namespace Systemsdk\Bundle\EasyLogBundle\Handler;

use Monolog\Handler\StreamHandler;
use RuntimeException;
use Monolog\LogRecord;

/**
 * Class EasyLogHandler
 */
class EasyLogHandler extends StreamHandler
{
    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException
     */
    public function handle(LogRecord $record): bool
    {
        // The method "handle()" should never be called (call "handleBatch()" instead).
        throw new RuntimeException(
            'Wrong "monolog" configuration. Please read EasyLogHandler README configuration instructions.'
        );
    }

    /**
     * {@inheritdoc}
     */
    public function handleBatch(array $records): void
    {
        // if the log records were filtered (by channel, level, etc.) the array
        // no longer contains consecutive numeric keys. Make them consecutive again
        // before the log processing (this eases getting the next/previous record)
        $records = array_values($records);

        if ($records) {
            $this->write($this->getFormatter()->formatBatch($records));
        }
    }
}
