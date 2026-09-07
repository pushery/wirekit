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
 *
 * `--check` answers the same question without writing: does the published file
 * still describe the installed package? A published manifest freezes at the
 * version that generated it, so an upgrade leaves it behind silently — nothing
 * in the upgrade touches the file, and the editor keeps autocompleting props
 * that have since changed. Without a reporting mode the only way to find out
 * was to regenerate and read the diff, which destroys the artifact you were
 * asking about.
 */
final class BoostSkillsCommand extends Command
{
    /**
     * How many names a single report line prints before it counts the rest.
     *
     * A regeneration after a major moves every component, and a report that
     * scrolls past the terminal is read by nobody.
     */
    private const LIST_CAP = 8;

    protected $signature = 'wirekit:boost-skills
        {--force : Overwrite an existing .boost/wirekit.json}
        {--check : Report whether .boost/wirekit.json still matches the installed package, and write nothing}';

    protected $description = 'Publish a Laravel Boost skill manifest (.boost/wirekit.json) so AI editors autocomplete WireKit components, props, presets, and commands';

    public function handle(): int
    {
        $target = base_path('.boost/wirekit.json');
        $manifest = (new BoostManifest)->build(VersionResolver::resolve());

        if ($this->option('check')) {
            // Rejected rather than resolved in favor of one of them: a scripted
            // `--check --force` reads as "check", and silently writing instead
            // would destroy the very file the step was asking about.
            if ($this->option('force')) {
                $this->error('--check and --force contradict: one writes the manifest, the other refuses to.');

                return self::FAILURE;
            }

            return $this->check($target, $manifest);
        }

        if (File::exists($target) && ! $this->option('force')) {
            $this->warn('.boost/wirekit.json already exists. Re-run with --force to refresh it, or --check to see whether it is still current.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(\dirname($target));
        File::put($target, self::encode($manifest));

        $componentCount = \count($manifest['skills'][0]['components'] ?? []);
        $this->info(".boost/wirekit.json written — {$componentCount} components across ".\count($manifest['skills']).' skills.');

        return self::SUCCESS;
    }

    /**
     * The one place the manifest becomes bytes, so `--check` and the write path
     * cannot disagree about formatting.
     *
     * @param  array<string, mixed>  $manifest
     */
    private static function encode(array $manifest): string
    {
        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * Compare the published manifest against the installed package and say what differs.
     *
     * ⚠️ The comparison is over the WHOLE structure, never the version stamp
     * alone. A stamp-only check is green over a manifest that describes
     * components wrongly — a minor release that adds a prop changes the catalog
     * and the stamp together, so an agreeing stamp proves only that both sides
     * were written by the same release, which is exactly what is in doubt.
     *
     * @param  array<string, mixed>  $current
     */
    private function check(string $target, array $current): int
    {
        if (! File::exists($target)) {
            $this->error('.boost/wirekit.json is missing — no manifest has been published in this project.');
            $this->line('  Run: php artisan wirekit:boost-skills');

            return self::FAILURE;
        }

        $published = json_decode((string) File::get($target), true);

        if (! \is_array($published)) {
            $this->error('.boost/wirekit.json is not readable as JSON.');
            $this->line('  Run: php artisan wirekit:boost-skills --force');

            return self::FAILURE;
        }

        if ($published === $current) {
            $version = \is_scalar($current['version'] ?? null) ? (string) $current['version'] : 'the installed package';
            $this->info(".boost/wirekit.json is current — it matches WireKit {$version}.");

            return self::SUCCESS;
        }

        $this->error('.boost/wirekit.json no longer describes the installed package.');

        foreach ($this->differences($published, $current) as $line) {
            $this->line('  '.$line);
        }

        $this->line('  Run: php artisan wirekit:boost-skills --force');

        return self::FAILURE;
    }

    /**
     * The difference report — what makes `--check` worth running instead of
     * regenerating and reading a diff.
     *
     * Names are compared as sets and entries as structures, so the three
     * things that actually change between releases each get their own line:
     * components appearing or disappearing, a component describing different
     * props, and the CLI or preset lists moving.
     *
     * @param  array<string, mixed>  $published
     * @param  array<string, mixed>  $current
     * @return list<string>
     */
    private function differences(array $published, array $current): array
    {
        $lines = [];

        foreach (['version', 'format-version'] as $key) {
            if (($published[$key] ?? null) !== ($current[$key] ?? null)) {
                $lines[] = "{$key}: ".self::scalar($published, $key).' → '.self::scalar($current, $key);
            }
        }

        $publishedComponents = self::componentsOf($published);
        $currentComponents = self::componentsOf($current);

        $lines = array_merge(
            $lines,
            $this->nameDelta('skill', self::skillIdsOf($published), self::skillIdsOf($current)),
            $this->nameDelta('component', array_keys($publishedComponents), array_keys($currentComponents)),
            $this->changedComponents($publishedComponents, $currentComponents),
            $this->nameDelta('theme preset', self::presetsOf($published), self::presetsOf($current)),
            $this->nameDelta('command', self::commandsOf($published), self::commandsOf($current)),
        );

        if ($lines === []) {
            // Reached when every name matches and something outside the named
            // surfaces moved — a prompt fragment, the decision tree, an
            // instruction string. Saying "stale, and here is nothing" would
            // read as a defect in this reporter.
            $lines[] = 'the catalog carries the same names; the difference is in the prompt fragments or instruction text.';
        }

        return $lines;
    }

    /**
     * @param  list<string>  $published
     * @param  list<string>  $current
     * @return list<string>
     */
    private function nameDelta(string $noun, array $published, array $current): array
    {
        $lines = [];

        $added = array_values(array_diff($current, $published));
        $removed = array_values(array_diff($published, $current));

        if ($added !== []) {
            $lines[] = "{$noun}(s) the installed package has and the file does not: ".self::names($added);
        }

        if ($removed !== []) {
            $lines[] = "{$noun}(s) the file still lists and the package no longer has: ".self::names($removed);
        }

        return $lines;
    }

    /**
     * @param  array<string, array<string, mixed>>  $published
     * @param  array<string, array<string, mixed>>  $current
     * @return list<string>
     */
    private function changedComponents(array $published, array $current): array
    {
        $changed = [];

        foreach ($current as $name => $entry) {
            if (isset($published[$name]) && $published[$name] !== $entry) {
                $changed[$name] = self::propDelta($published[$name], $entry);
            }
        }

        if ($changed === []) {
            return [];
        }

        $lines = [\count($changed).' component(s) are described differently now:'];

        foreach (\array_slice($changed, 0, self::LIST_CAP, true) as $name => $detail) {
            $lines[] = "  - {$name}{$detail}";
        }

        $rest = \count($changed) - self::LIST_CAP;

        if ($rest > 0) {
            $lines[] = "  - (and {$rest} more)";
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $was
     * @param  array<string, mixed>  $now
     */
    private static function propDelta(array $was, array $now): string
    {
        $wasProps = self::propNames($was);
        $nowProps = self::propNames($now);

        $parts = [];

        if ($added = array_values(array_diff($nowProps, $wasProps))) {
            $parts[] = 'props added: '.self::names($added);
        }

        if ($removed = array_values(array_diff($wasProps, $nowProps))) {
            $parts[] = 'props gone: '.self::names($removed);
        }

        // Same prop names on both sides means the change is in a default, a
        // hint, the description, the tag or the sub-components. A bare name
        // with no explanation would send the reader looking for a prop that
        // did not move.
        return $parts === [] ? ' — same props, different detail' : ' — '.implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $component
     * @return list<string>
     */
    private static function propNames(array $component): array
    {
        $names = [];

        foreach ($component['props'] ?? [] as $prop) {
            if (\is_array($prop) && \is_string($prop['name'] ?? null)) {
                $names[] = $prop['name'];
            }
        }

        return $names;
    }

    /**
     * The component skill, keyed by name.
     *
     * @param  array<string, mixed>  $manifest
     * @return array<string, array<string, mixed>>
     */
    private static function componentsOf(array $manifest): array
    {
        $components = [];

        foreach (self::skill($manifest, 'wirekit-component')['components'] ?? [] as $component) {
            if (\is_array($component) && \is_string($component['name'] ?? null)) {
                $components[$component['name']] = $component;
            }
        }

        return $components;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    private static function presetsOf(array $manifest): array
    {
        return array_values(array_filter(
            self::skill($manifest, 'wirekit-theme-switch')['presets'] ?? [],
            'is_string',
        ));
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    private static function commandsOf(array $manifest): array
    {
        $names = [];

        foreach (self::skill($manifest, 'wirekit-cli')['commands'] ?? [] as $command) {
            if (\is_array($command) && \is_string($command['name'] ?? null)) {
                $names[] = $command['name'];
            }
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return list<string>
     */
    private static function skillIdsOf(array $manifest): array
    {
        $ids = [];

        foreach (\is_array($manifest['skills'] ?? null) ? $manifest['skills'] : [] as $skill) {
            if (\is_array($skill) && \is_string($skill['id'] ?? null)) {
                $ids[] = $skill['id'];
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private static function skill(array $manifest, string $id): array
    {
        foreach (\is_array($manifest['skills'] ?? null) ? $manifest['skills'] : [] as $skill) {
            if (\is_array($skill) && ($skill['id'] ?? null) === $id) {
                return $skill;
            }
        }

        return [];
    }

    /**
     * @param  list<string>  $names
     */
    private static function names(array $names): string
    {
        $shown = \array_slice($names, 0, self::LIST_CAP);
        $rest = \count($names) - \count($shown);

        // The count is printed rather than the list silently truncated: a
        // wholesale regeneration moves every component, and a report that
        // stops at eight without saying so reads as a small change.
        return implode(', ', $shown).($rest > 0 ? " (and {$rest} more)" : '');
    }

    /**
     * Print a top-level manifest field, or say it is absent.
     *
     * Takes the array and the key rather than the value, so the parameter can be
     * typed: reading `$manifest[$key]` at the call site would hand this a `mixed`,
     * and `MixedTypeRatchetTest` counts every one of those as a deliberate edit —
     * correctly, since the narrowing was available here for the asking.
     *
     * @param  array<string, mixed>  $manifest
     */
    private static function scalar(array $manifest, string $key): string
    {
        $value = $manifest[$key] ?? null;

        return \is_scalar($value) ? (string) $value : '(absent)';
    }
}
