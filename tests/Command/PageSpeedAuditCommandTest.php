<?php

namespace Inforob\PageSpeedToolkit\Tests\Command;

use Inforob\PageSpeedToolkit\Command\PageSpeedAuditCommand;
use Inforob\PageSpeedToolkit\Service\PageSpeedService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class PageSpeedAuditCommandTest extends TestCase
{
    private PageSpeedService $service;
    private string $projectDir;

    protected function setUp(): void
    {
        $this->service    = $this->createMock(PageSpeedService::class);
        $this->projectDir = sys_get_temp_dir() . '/pst-test-' . uniqid();
        mkdir($this->projectDir . '/var', 0755, true);

        $this->service->method('audit')->willReturn([]);
        $this->service->method('getCategoryScores')->willReturn(['performance' => 85, 'seo' => 90]);
        $this->service->method('getFailingAudits')->willReturn([]);
        $this->service->method('getScoreColor')->willReturn('yellow');
    }

    protected function tearDown(): void
    {
        $report = $this->projectDir . '/var/pagespeed-report.json';
        if (file_exists($report)) {
            unlink($report);
        }
        if (is_dir($this->projectDir . '/var')) {
            rmdir($this->projectDir . '/var');
        }
        if (is_dir($this->projectDir)) {
            rmdir($this->projectDir);
        }
    }

    private function makeCommand(): PageSpeedAuditCommand
    {
        return new PageSpeedAuditCommand(
            $this->service,
            'https://example.com',
            $this->projectDir,
        );
    }

    public function testCommandReturnsSuccess(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $tester->execute(['--url' => '/', '--strategy' => 'mobile']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testCommandWritesJsonReport(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $tester->execute(['--url' => '/', '--strategy' => 'mobile']);

        $reportFile = $this->projectDir . '/var/pagespeed-report.json';
        $this->assertFileExists($reportFile);

        $report = json_decode(file_get_contents($reportFile), true);
        $this->assertIsArray($report);
        $this->assertArrayHasKey('/', $report);
    }

    public function testReportContainsStrategyKey(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $tester->execute(['--url' => '/', '--strategy' => 'mobile']);

        $report = json_decode(file_get_contents($this->projectDir . '/var/pagespeed-report.json'), true);
        $this->assertArrayHasKey('mobile', $report['/']);
    }

    public function testSingleUrlOptionAuditsOneUrl(): void
    {
        $this->service->expects($this->once())
            ->method('audit')
            ->with('https://example.com/blog', 'mobile')
            ->willReturn([]);

        $tester = new CommandTester($this->makeCommand());
        $tester->execute(['--url' => '/blog', '--strategy' => 'mobile']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testUrlsOptionAuditsMultipleUrls(): void
    {
        $this->service->expects($this->exactly(3))
            ->method('audit')
            ->willReturn([]);

        $tester = new CommandTester($this->makeCommand());
        $tester->execute(['--urls' => '/,/blog,/about', '--strategy' => 'mobile']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testEnvVarPagspeedUrlsIsUsed(): void
    {
        $_ENV['PAGESPEED_URLS'] = '/env-page,/env-page2';

        try {
            $this->service->expects($this->exactly(2))
                ->method('audit')
                ->willReturn([]);

            $tester = new CommandTester($this->makeCommand());
            $tester->execute(['--strategy' => 'mobile']);

            $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        } finally {
            unset($_ENV['PAGESPEED_URLS']);
        }
    }

    public function testFallbackPageListHasFourPages(): void
    {
        unset($_ENV['PAGESPEED_URLS']);

        $this->service->expects($this->exactly(4))
            ->method('audit')
            ->willReturn([]);

        $tester = new CommandTester($this->makeCommand());
        $tester->execute(['--strategy' => 'mobile']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testBothStrategiesAuditedByDefault(): void
    {
        $this->service->expects($this->exactly(2)) // 1 URL × 2 strategies
            ->method('audit')
            ->willReturn([]);

        $tester = new CommandTester($this->makeCommand());
        $tester->execute(['--url' => '/']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function testCustomOutputPathIsUsed(): void
    {
        $tester = new CommandTester($this->makeCommand());
        $tester->execute([
            '--url'      => '/',
            '--strategy' => 'mobile',
            '--output'   => 'var/custom-report.json',
        ]);

        $this->assertFileExists($this->projectDir . '/var/custom-report.json');
        unlink($this->projectDir . '/var/custom-report.json');
    }

    public function testServiceErrorIsHandledGracefully(): void
    {
        $this->service->method('audit')
            ->willThrowException(new \RuntimeException('API unreachable'));

        $tester = new CommandTester($this->makeCommand());
        $tester->execute(['--url' => '/', '--strategy' => 'mobile']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $report = json_decode(file_get_contents($this->projectDir . '/var/pagespeed-report.json'), true);
        $this->assertArrayHasKey('error', $report['/']['mobile']);
    }
}
