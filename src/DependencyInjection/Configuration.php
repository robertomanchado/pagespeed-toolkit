<?php

namespace Inforob\PageSpeedToolkit\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('pagespeed');

        $tree->getRootNode()
            ->children()
                ->scalarNode('api_key')
                    ->defaultValue('')
                    ->info('Google PageSpeed Insights API key (env: PAGESPEED_API_KEY)')
                ->end()
                ->scalarNode('site_url')
                    ->defaultValue('')
                    ->info('Base URL of the site to audit, e.g. https://example.com (env: SITE_URL)')
                ->end()
                ->scalarNode('report_path')
                    ->defaultValue('var/pagespeed-report.json')
                    ->info('Path relative to kernel.project_dir where the JSON report is saved')
                ->end()
            ->end()
        ;

        return $tree;
    }
}
