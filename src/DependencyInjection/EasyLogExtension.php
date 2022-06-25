<?php

declare(strict_types=1);

namespace Systemsdk\Bundle\EasyLogBundle\DependencyInjection;

use InvalidArgumentException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * Class EasyLogExtension
 */
class EasyLogExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('easy_log.log_path', $config['log_path']);
        $container->setParameter('easy_log.max_line_length', $config['max_line_length']);
        $container->setParameter('easy_log.prefix_length', $config['prefix_length']);
        $container->setParameter('easy_log.ignored_routes', $config['ignored_routes']);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.xml');
    }

    /**
     * @throws InvalidArgumentException
     */
    public function configValidate(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('easy_log.handler') || !$container->hasDefinition('easy_log.formatter')) {
            throw new InvalidArgumentException('Wrong services definition for EasyLog bundle.');
        }
    }
}
