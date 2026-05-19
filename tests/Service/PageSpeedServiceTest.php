<?php

namespace Inforob\PageSpeedToolkit\Tests\Service;

use Inforob\PageSpeedToolkit\Service\PageSpeedService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class PageSpeedServiceTest extends TestCase
{
    private PageSpeedService $service;
    private HttpClientInterface $client;

    protected function setUp(): void
    {
        $this->client  = $this->createMock(HttpClientInterface::class);
        $this->service = new PageSpeedService($this->client, 'test-api-key');
    }

    // --- getScoreColor ---

    public function testScoreColorGreen(): void
    {
        $this->assertSame('green', $this->service->getScoreColor(90));
        $this->assertSame('green', $this->service->getScoreColor(100));
    }

    public function testScoreColorYellow(): void
    {
        $this->assertSame('yellow', $this->service->getScoreColor(50));
        $this->assertSame('yellow', $this->service->getScoreColor(89));
    }

    public function testScoreColorRed(): void
    {
        $this->assertSame('red', $this->service->getScoreColor(0));
        $this->assertSame('red', $this->service->getScoreColor(49));
    }

    // --- getCategoryScores ---

    public function testGetCategoryScoresReturnsRoundedIntegers(): void
    {
        $data = [
            'lighthouseResult' => [
                'categories' => [
                    'performance'    => ['score' => 0.92],
                    'seo'            => ['score' => 0.854],
                    'accessibility'  => ['score' => 0.005],
                    'best-practices' => ['score' => 1.0],
                ],
            ],
        ];

        $scores = $this->service->getCategoryScores($data);

        $this->assertSame(92, $scores['performance']);
        $this->assertSame(85, $scores['seo']);
        $this->assertSame(1, $scores['accessibility']);
        $this->assertSame(100, $scores['best-practices']);
    }

    public function testGetCategoryScoresReturnsEmptyArrayWhenNoData(): void
    {
        $this->assertSame([], $this->service->getCategoryScores([]));
        $this->assertSame([], $this->service->getCategoryScores(['lighthouseResult' => []]));
    }

    // --- getFailingAudits ---

    public function testGetFailingAuditsFiltersPassingAudits(): void
    {
        $data = $this->makeAuditData([
            'meta-description' => ['score' => 1.0, 'scoreDisplayMode' => 'binary'],
        ]);

        $this->assertSame([], $this->service->getFailingAudits($data));
    }

    public function testGetFailingAuditsFiltersNullScore(): void
    {
        $data = $this->makeAuditData([
            'structured-data' => ['score' => null, 'scoreDisplayMode' => 'notApplicable'],
        ]);

        $this->assertSame([], $this->service->getFailingAudits($data));
    }

    public function testGetFailingAuditsFiltersExcludedModes(): void
    {
        $data = $this->makeAuditData([
            'informative-audit' => ['score' => 0.3, 'scoreDisplayMode' => 'informative'],
            'manual-audit'      => ['score' => 0.3, 'scoreDisplayMode' => 'manual'],
            'na-audit'          => ['score' => 0.3, 'scoreDisplayMode' => 'notApplicable'],
        ]);

        $this->assertSame([], $this->service->getFailingAudits($data));
    }

    public function testGetFailingAuditsIncludesScoreBelowOne(): void
    {
        $data = $this->makeAuditData([
            'render-blocking-resources' => ['score' => 0.5, 'scoreDisplayMode' => 'numeric'],
            'uses-text-compression'     => ['score' => 0.0, 'scoreDisplayMode' => 'numeric'],
        ]);

        $failing = $this->service->getFailingAudits($data);

        $this->assertArrayHasKey('render-blocking-resources', $failing);
        $this->assertArrayHasKey('uses-text-compression', $failing);
        $this->assertSame(0.5, $failing['render-blocking-resources']['score']);
    }

    public function testGetFailingAuditsSortsByScoreAscending(): void
    {
        $data = $this->makeAuditData([
            'audit-high'   => ['score' => 0.8, 'scoreDisplayMode' => 'numeric'],
            'audit-low'    => ['score' => 0.1, 'scoreDisplayMode' => 'numeric'],
            'audit-medium' => ['score' => 0.4, 'scoreDisplayMode' => 'numeric'],
        ]);

        $failing = $this->service->getFailingAudits($data);
        $scores  = array_column($failing, 'score');

        $this->assertSame([0.1, 0.4, 0.8], $scores);
    }

    public function testGetFailingAuditsStripsHtmlFromDescription(): void
    {
        $data = $this->makeAuditData([
            'test-audit' => [
                'score'           => 0.5,
                'scoreDisplayMode' => 'numeric',
                'description'     => 'Fix <a href="#">this issue</a> now.',
            ],
        ]);

        $failing = $this->service->getFailingAudits($data);

        $this->assertSame('Fix this issue now.', $failing['test-audit']['description']);
    }

    // --- audit ---

    public function testAuditCallsApiWithCorrectMethod(): void
    {
        $fakeData = ['lighthouseResult' => ['categories' => [], 'audits' => []]];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($fakeData);

        $this->client->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('pagespeedonline/v5/runPagespeed'), $this->anything())
            ->willReturn($response);

        $result = $this->service->audit('https://example.com', 'mobile');

        $this->assertSame($fakeData, $result);
    }

    public function testAuditUppercasesStrategy(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);

        $this->client->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->stringContains('strategy=DESKTOP'),
                $this->anything()
            )
            ->willReturn($response);

        $this->service->audit('https://example.com', 'desktop');
    }

    public function testAuditIncludesAllFourCategories(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn([]);

        $this->client->expects($this->once())
            ->method('request')
            ->with(
                'GET',
                $this->logicalAnd(
                    $this->stringContains('category=performance'),
                    $this->stringContains('category=seo'),
                    $this->stringContains('category=accessibility'),
                    $this->stringContains('category=best-practices')
                ),
                $this->anything()
            )
            ->willReturn($response);

        $this->service->audit('https://example.com', 'mobile');
    }

    // --- helpers ---

    private function makeAuditData(array $audits): array
    {
        $normalized = [];
        foreach ($audits as $id => $audit) {
            $normalized[$id] = array_merge([
                'title'           => ucfirst(str_replace('-', ' ', $id)),
                'description'     => 'Description for ' . $id,
                'score'           => 0.5,
                'scoreDisplayMode' => 'numeric',
                'displayValue'    => null,
            ], $audit);
        }

        return ['lighthouseResult' => ['audits' => $normalized]];
    }
}
