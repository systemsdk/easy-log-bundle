<?php
declare(strict_types = 1);

namespace Systemsdk\Bundle\EasyLogBundle\DependencyInjection\Compiler;

use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class ValidateConfigurationPass
 */
class ValidateConfigurationPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function process(ContainerBuilder $container): void
    {
        /* @phpstan-ignore-next-line */
        $container->getExtension('easy_log')->configValidate($container);

        $definition = $container->getDefinition('easy_log.handler');
        $definition->addMethodCall('setFormatter', [new Reference('easy_log.formatter')]);
    }
}
