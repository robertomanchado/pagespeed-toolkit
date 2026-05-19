<?php

namespace Inforob\PageSpeedToolkit\DependencyInjection;

use Inforob\PageSpeedToolkit\Command\PageSpeedAuditCommand;
use Inforob\PageSpeedToolkit\Service\PageSpeedService;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class PageSpeedExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->register(PageSpeedService::class, PageSpeedService::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArgument('$apiKey', $config['api_key']);

        $container->register(PageSpeedAuditCommand::class, PageSpeedAuditCommand::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setArgument('$siteUrl', $config['site_url'])
            ->setArgument('$projectDir', '%kernel.project_dir%')
            ->setArgument('$reportPath', $config['report_path']);
    }

    public function getAlias(): string
    {
        return 'pagespeed';
    }
}
