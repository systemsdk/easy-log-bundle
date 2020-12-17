<?php
declare(strict_types = 1);

namespace Systemsdk\Bundle\EasyLogBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration
 *
 * FrameworkExtraBundle configuration structure.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('easy_log');
        $rootNode = $treeBuilder->getRootNode();
        /* @phpstan-ignore-next-line */
        $rootNode
            ->children()
                ->scalarNode('log_path')
                    ->info('Path where readable log file will be located')
                    ->cannotBeEmpty()
                    ->defaultValue('%kernel.logs_dir%/%kernel.environment%-readable.log')
                ->end()
                ->integerNode('max_line_length')
                    ->info('Max line length in log file')
                    ->min(1)
                    ->defaultValue(120)
                ->end()
                ->integerNode('prefix_length')
                    ->info('Prefix length in log file')
                    ->min(0)
                    ->defaultValue(2)
                ->end()
                ->append($this->getIgnoredRoutes())
            ->end();

        return $treeBuilder;
    }

    private function getIgnoredRoutes(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition('ignored_routes');
        $node->info('Ignored routes list');
        $node->prototype('scalar')->end();
        $node->defaultValue(['_wdt', '_profiler']);

        return $node;
    }
}
