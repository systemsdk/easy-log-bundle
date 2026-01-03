<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Systemsdk\Bundle\EasyLogBundle\Formatter\EasyLogFormatter;
use Systemsdk\Bundle\EasyLogBundle\Handler\EasyLogHandler;

return static function (ContainerConfigurator $container): void {
    $parameters = $container->parameters();
    $parameters
        ->set('easy_log.handler.class', EasyLogHandler::class)
        ->set('easy_log.formatter.class', EasyLogFormatter::class)
    ;

    $services = $container->services();
    $services
        ->set('easy_log.handler', EasyLogHandler::class)
        ->args([
            param('easy_log.log_path'),
        ])
    ;
    $services
        ->set('easy_log.formatter', EasyLogFormatter::class)
        ->args([
            param('easy_log.max_line_length'),
            param('easy_log.prefix_length'),
            param('easy_log.ignored_routes'),
        ])
    ;
};
