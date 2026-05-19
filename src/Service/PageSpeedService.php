<?php

namespace Inforob\PageSpeedToolkit\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PageSpeedService
{
    private const API_ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    private const CATEGORIES = ['performance', 'seo', 'accessibility', 'best-practices'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
    ) {}

    public function audit(string $url, string $strategy = 'mobile'): array
    {
        // PageSpeed API requires repeated params (category=a&category=b),
        // not array notation, so we build the query string manually.
        $params = http_build_query([
            'url'      => $url,
            'strategy' => strtoupper($strategy),
            'key'      => $this->apiKey ?: '',
        ]);

        foreach (self::CATEGORIES as $cat) {
            $params .= '&category=' . $cat;
        }

        $response = $this->httpClient->request('GET', self::API_ENDPOINT . '?' . $params, [
            'timeout' => 90,
        ]);

        return $response->toArray();
    }

    public function getCategoryScores(array $data): array
    {
        $scores = [];
        foreach ($data['lighthouseResult']['categories'] ?? [] as $key => $cat) {
            $scores[$key] = (int) round(($cat['score'] ?? 0) * 100);
        }

        return $scores;
    }

    public function getFailingAudits(array $data): array
    {
        $failing = [];
        foreach ($data['lighthouseResult']['audits'] ?? [] as $id => $audit) {
            $score = $audit['score'] ?? null;
            $mode  = $audit['scoreDisplayMode'] ?? '';
            if (
                $score !== null
                && $score < 1.0
                && !in_array($mode, ['informative', 'notApplicable', 'manual'], true)
            ) {
                $failing[$id] = [
                    'title'        => $audit['title'],
                    'description'  => strip_tags($audit['description'] ?? ''),
                    'score'        => $score,
                    'displayValue' => $audit['displayValue'] ?? null,
                ];
            }
        }
        uasort($failing, static fn($a, $b) => $a['score'] <=> $b['score']);

        return $failing;
    }

    public function getScoreColor(int $score): string
    {
        if ($score >= 90) {
            return 'green';
        }
        if ($score >= 50) {
            return 'yellow';
        }

        return 'red';
    }
}
