<?php

namespace Inforob\PageSpeedToolkit\Tests\DependencyInjection;

use Inforob\PageSpeedToolkit\Command\PageSpeedAuditCommand;
use Inforob\PageSpeedToolkit\DependencyInjection\PageSpeedExtension;
use Inforob\PageSpeedToolkit\Service\PageSpeedService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PageSpeedExtensionTest extends TestCase
{
    private PageSpeedExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new PageSpeedExtension();
        $this->container = new ContainerBuilder();
        $this->container->setParameter('kernel.project_dir', '/tmp');
    }

    public function testAliasIsPagespeed(): void
    {
        $this->assertSame('pagespeed', $this->extension->getAlias());
    }

    public function testPageSpeedServiceIsRegistered(): void
    {
        $this->extension->load([[]], $this->container);

        $this->assertTrue($this->container->hasDefinition(PageSpeedService::class));
    }

    public function testPageSpeedAuditCommandIsRegistered(): void
    {
        $this->extension->load([[]], $this->container);

        $this->assertTrue($this->container->hasDefinition(PageSpeedAuditCommand::class));
    }

    public function testApiKeyIsInjectedIntoService(): void
    {
        $this->extension->load([['api_key' => 'my-key']], $this->container);

        $definition = $this->container->getDefinition(PageSpeedService::class);
        $this->assertSame('my-key', $definition->getArgument('$apiKey'));
    }

    public function testSiteUrlIsInjectedIntoCommand(): void
    {
        $this->extension->load([['site_url' => 'https://example.com']], $this->container);

        $definition = $this->container->getDefinition(PageSpeedAuditCommand::class);
        $this->assertSame('https://example.com', $definition->getArgument('$siteUrl'));
    }

    public function testReportPathIsInjectedIntoCommand(): void
    {
        $this->extension->load([['report_path' => 'var/custom.json']], $this->container);

        $definition = $this->container->getDefinition(PageSpeedAuditCommand::class);
        $this->assertSame('var/custom.json', $definition->getArgument('$reportPath'));
    }

    public function testDefaultReportPathIsUsedWhenNotConfigured(): void
    {
        $this->extension->load([[]], $this->container);

        $definition = $this->container->getDefinition(PageSpeedAuditCommand::class);
        $this->assertSame('var/pagespeed-report.json', $definition->getArgument('$reportPath'));
    }

    public function testProjectDirParameterIsInjectedIntoCommand(): void
    {
        $this->extension->load([[]], $this->container);

        $definition = $this->container->getDefinition(PageSpeedAuditCommand::class);
        $this->assertSame('%kernel.project_dir%', $definition->getArgument('$projectDir'));
    }
}
