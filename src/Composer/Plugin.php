<?php

namespace Inforob\PageSpeedToolkit\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    private IOInterface $io;

    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->io = $io;
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}

    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'run',
            ScriptEvents::POST_UPDATE_CMD  => 'run',
        ];
    }

    public function run(Event $event): void
    {
        $projectDir = dirname($event->getComposer()->getConfig()->get('vendor-dir'));
        $bundleRoot = realpath(__DIR__ . '/../../');

        if (!$bundleRoot) {
            return;
        }

        $this->copyClaude($projectDir, $bundleRoot);
        $this->registerBundle($projectDir);
        $this->createPackageConfig($projectDir);
        $this->appendEnvVars($projectDir);
    }

    private function copyClaude(string $projectDir, string $bundleRoot): void
    {
        $files = [
            '.claude/commands/pagespeed-fix.md'     => 'Slash command /pagespeed-fix',
            '.claude/agents/pagespeed-optimizer.md' => 'Agent pagespeed-optimizer',
        ];

        foreach ($files as $relative => $label) {
            $src  = $bundleRoot . '/' . $relative;
            $dest = $projectDir . '/' . $relative;

            if (!file_exists($src) || file_exists($dest)) {
                continue;
            }

            $dir = dirname($dest);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            copy($src, $dest);
            $this->io->write("  <info>[pagespeed-toolkit]</info> Installed $label → $relative");
        }
    }

    private function registerBundle(string $projectDir): void
    {
        $bundlesFile  = $projectDir . '/config/bundles.php';
        $bundleClass  = 'Inforob\\PageSpeedToolkit\\PageSpeedBundle';
        $bundleLine   = "    $bundleClass::class => ['all' => true],";

        if (!file_exists($bundlesFile)) {
            return;
        }

        $contents = file_get_contents($bundlesFile);

        if (str_contains($contents, $bundleClass)) {
            return;
        }

        // Insert before the closing bracket of the return array
        $pos = strrpos($contents, '];');
        if ($pos === false) {
            return;
        }
        $contents = substr($contents, 0, $pos) . "$bundleLine\n" . substr($contents, $pos);
        file_put_contents($bundlesFile, $contents);

        $this->io->write('  <info>[pagespeed-toolkit]</info> Bundle registered in config/bundles.php');
    }

    private function createPackageConfig(string $projectDir): void
    {
        $configFile = $projectDir . '/config/packages/pagespeed.yaml';

        if (file_exists($configFile)) {
            return;
        }

        $configDir = dirname($configFile);
        if (!is_dir($configDir)) {
            return;
        }

        $yaml = <<<YAML
pagespeed:
    api_key: '%env(PAGESPEED_API_KEY)%'
    site_url: '%env(SITE_URL)%'
    # report_path: 'var/pagespeed-report.json'  # optional
YAML;

        file_put_contents($configFile, $yaml . "\n");
        $this->io->write('  <info>[pagespeed-toolkit]</info> Created config/packages/pagespeed.yaml');
    }

    private function appendEnvVars(string $projectDir): void
    {
        $envFile = $projectDir . '/.env';

        if (!file_exists($envFile)) {
            return;
        }

        $contents = file_get_contents($envFile);

        if (str_contains($contents, 'PAGESPEED_API_KEY')) {
            return;
        }

        $block = <<<ENV

###> inforob/pagespeed-toolkit ###
PAGESPEED_API_KEY=
SITE_URL=https://your-site.com
# PAGESPEED_URLS=/,/blog,/about,/contact
###< inforob/pagespeed-toolkit ###
ENV;

        file_put_contents($envFile, $contents . $block . "\n");
        $this->io->write('  <info>[pagespeed-toolkit]</info> Added env vars to .env — set PAGESPEED_API_KEY');
    }
}
