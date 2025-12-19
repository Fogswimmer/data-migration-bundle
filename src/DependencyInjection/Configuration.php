<?php

namespace Fogswimmer\DataMigration\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('data_migration');

        $root = $treeBuilder->getRootNode();

        $root
            ->children()
                ->arrayNode('data_source')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('type')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('connection')
                            ->defaultValue('default')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('tables')
                    ->useAttributeAsKey('entity')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('source')->end()
                            ->scalarNode('source_path')->defaultNull()->end()
                            ->arrayNode('map')
                                ->variablePrototype()->end()
                            ->end()
                            ->arrayNode('transform')
                                ->useAttributeAsKey('field')
                                ->arrayPrototype()
                                    ->variablePrototype()->end()
                                ->end()
                                ->defaultValue([])
                            ->end()
                            ->arrayNode('post_process')
                                ->scalarPrototype()->end()
                                ->defaultValue([])
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
