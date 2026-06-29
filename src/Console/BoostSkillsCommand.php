<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pushery\WireKit\Boost\BoostManifest;
use Pushery\WireKit\Support\VersionResolver;

/**
 * `wirekit:boost-skills` — publish a Laravel Boost skill manifest.
 *
 * Writes `.boost/wirekit.json` into the developer's project: a typed bundle of
 * component / prop / preset / command data an AI-augmented editor loads for
 * WireKit-aware autocomplete. The sibling of `wirekit:cursor-rules` for the
 * Laravel Boost surface; the manifest is auto-generated from source (see
 * {@see BoostManifest}) so it never drifts. Re-running is the documented refresh
 * path after a WireKit upgrade.
 */
final class BoostSkillsCommand extends Command
{
    protected $signature = 'wirekit:boost-skills {--force : Overwrite an existing .boost/wirekit.json}';

    protected $description = 'Publish a Laravel Boost skill manifest (.boost/wirekit.json) so AI editors autocomplete WireKit components, props, presets, and commands';

    public function handle(): int
    {
        $target = base_path('.boost/wirekit.json');

        if (File::exists($target) && ! $this->option('force')) {
            $this->warn('.boost/wirekit.json already exists. Re-run with --force to refresh it.');

            return self::FAILURE;
        }

        $manifest = (new BoostManifest)->build(VersionResolver::resolve());

        File::ensureDirectoryExists(\dirname($target));
        File::put(
            $target,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n",
        );

        $componentCount = \count($manifest['skills'][0]['components'] ?? []);
        $this->info(".boost/wirekit.json written — {$componentCount} components across ".\count($manifest['skills']).' skills.');

        return self::SUCCESS;
    }
}
