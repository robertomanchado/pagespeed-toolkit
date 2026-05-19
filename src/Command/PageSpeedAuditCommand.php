<?php

namespace Inforob\PageSpeedToolkit\Command;

use Inforob\PageSpeedToolkit\Service\PageSpeedService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pagespeed:audit',
    description: 'Audit project pages with Google PageSpeed Insights and save a JSON report',
)]
class PageSpeedAuditCommand extends Command
{
    public function __construct(
        private readonly PageSpeedService $pageSpeed,
        private readonly string $siteUrl,
        private readonly string $projectDir,
        private readonly string $reportPath = 'var/pagespeed-report.json',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('strategy', 's', InputOption::VALUE_REQUIRED, 'mobile|desktop|both', 'both')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Path to JSON report (relative to project root)', $this->reportPath)
            ->addOption('url', null, InputOption::VALUE_OPTIONAL, 'Audit only this relative path, e.g. /blog')
            ->addOption('urls', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of paths, e.g. /,/blog,/about')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Google PageSpeed Insights — Full Audit');

        $strategy   = strtolower($input->getOption('strategy') ?? 'both');
        $strategies = match ($strategy) {
            'mobile'  => ['mobile'],
            'desktop' => ['desktop'],
            default   => ['mobile', 'desktop'],
        };

        $pages = $this->resolvePages($input);

        $io->text(sprintf(
            'Auditing <info>%d pages</info> · strategy: <info>%s</info> · site: <info>%s</info>',
            count($pages),
            implode(' + ', $strategies),
            $this->siteUrl,
        ));
        $io->newLine();

        $report     = [];
        $outputFile = $this->projectDir . '/' . ltrim((string) $input->getOption('output'), '/');

        foreach ($pages as $path => $label) {
            $fullUrl = rtrim($this->siteUrl, '/') . $path;
            $io->section("$label — $path");

            foreach ($strategies as $strat) {
                $io->write("  <comment>$strat</comment> → auditing...");

                try {
                    $data    = $this->pageSpeed->audit($fullUrl, $strat);
                    $scores  = $this->pageSpeed->getCategoryScores($data);
                    $failing = $this->pageSpeed->getFailingAudits($data);

                    $report[$path][$strat] = [
                        'url'       => $fullUrl,
                        'scores'    => $scores,
                        'failing'   => $failing,
                        'timestamp' => date('c'),
                    ];

                    $output->writeln(' <info>OK</info>');

                    $catNames = [
                        'performance'    => 'Performance',
                        'seo'            => 'SEO',
                        'accessibility'  => 'Accessibility',
                        'best-practices' => 'Best Practices',
                    ];

                    $rows = [];
                    foreach ($scores as $cat => $score) {
                        $color  = $this->pageSpeed->getScoreColor($score);
                        $bar    = str_repeat('█', (int) round($score / 10)) . str_repeat('░', 10 - (int) round($score / 10));
                        $rows[] = [$catNames[$cat] ?? $cat, "<fg=$color>$bar $score</>"];
                    }
                    $io->table(['Category', 'Score'], $rows);

                    if ($failing) {
                        $worst = array_slice($failing, 0, 8, true);
                        $io->text('<fg=red>Audits that need fixing:</>');
                        foreach ($worst as $id => $audit) {
                            $display = $audit['displayValue'] ? " [{$audit['displayValue']}]" : '';
                            $io->text(sprintf('    <fg=red>✗</> <comment>%s</comment>%s', $audit['title'], $display));
                        }
                        if (count($failing) > 8) {
                            $io->text(sprintf('    <fg=yellow>… and %d more audits in the JSON report</>', count($failing) - 8));
                        }
                        $io->newLine();
                    }
                } catch (\Throwable $e) {
                    $output->writeln(' <error>ERROR</error>');
                    $io->warning("Failed to audit $fullUrl ($strat): " . $e->getMessage());
                    $report[$path][$strat] = ['error' => $e->getMessage()];
                }

                usleep(500_000); // respect free API rate limit
            }
        }

        file_put_contents($outputFile, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        $io->success([
            "Report saved to: $outputFile",
            'Run /pagespeed-fix in Claude Code to analyze and apply fixes interactively',
        ]);

        return Command::SUCCESS;
    }

    private function resolvePages(InputInterface $input): array
    {
        if ($singleUrl = $input->getOption('url')) {
            return [$singleUrl => $singleUrl];
        }

        if ($urlsOption = $input->getOption('urls')) {
            $pages = [];
            foreach (explode(',', $urlsOption) as $path) {
                $path = trim($path);
                $pages[$path] = $path;
            }

            return $pages;
        }

        $envUrls = $_ENV['PAGESPEED_URLS'] ?? getenv('PAGESPEED_URLS') ?: '';
        if ($envUrls) {
            $pages = [];
            foreach (explode(',', $envUrls) as $path) {
                $path = trim($path);
                $pages[$path] = $path;
            }

            return $pages;
        }

        return [
            '/'        => 'Home',
            '/blog'    => 'Blog',
            '/contact' => 'Contact',
            '/login'   => 'Login',
        ];
    }
}
