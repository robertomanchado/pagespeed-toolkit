<?php

namespace Inforob\PageSpeedToolkit\Tests\DependencyInjection;

use Inforob\PageSpeedToolkit\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase
{
    private Processor $processor;
    private Configuration $config;

    protected function setUp(): void
    {
        $this->processor = new Processor();
        $this->config    = new Configuration();
    }

    public function testDefaultValues(): void
    {
        $result = $this->processor->processConfiguration($this->config, [[]]);

        $this->assertSame('', $result['api_key']);
        $this->assertSame('', $result['site_url']);
        $this->assertSame('var/pagespeed-report.json', $result['report_path']);
    }

    public function testCustomValuesAreAccepted(): void
    {
        $result = $this->processor->processConfiguration($this->config, [[
            'api_key'     => 'my-api-key',
            'site_url'    => 'https://example.com',
            'report_path' => 'var/custom-report.json',
        ]]);

        $this->assertSame('my-api-key', $result['api_key']);
        $this->assertSame('https://example.com', $result['site_url']);
        $this->assertSame('var/custom-report.json', $result['report_path']);
    }

    public function testUnknownKeyThrowsException(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->processor->processConfiguration($this->config, [[
            'unknown_key' => 'value',
        ]]);
    }

    public function testMultipleConfigsAreMerged(): void
    {
        $result = $this->processor->processConfiguration($this->config, [
            ['api_key' => 'first-key'],
            ['api_key' => 'second-key', 'site_url' => 'https://example.com'],
        ]);

        $this->assertSame('second-key', $result['api_key']);
        $this->assertSame('https://example.com', $result['site_url']);
    }
}
