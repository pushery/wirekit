<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Support\BladeParser;
use Pushery\WireKit\Support\StrictnessGate;
use Pushery\WireKit\Support\SuggestSimilar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Find misspelled props before a page renders.
 *
 * An unknown prop on a WireKit component is not an error at runtime. Blade passes it
 * through to the attribute bag, so `<x-wirekit::button intnet="danger">` renders a
 * perfectly good button in the WRONG intent, and the only trace is a log line the
 * developer has to already suspect exists in order to look for it.
 *
 * The strictness gate has known how to detect this since it shipped. What was missing is a
 * way to ASK: the knowledge only fired while a page was being rendered, so finding a typo
 * required visiting the page that carried it — which is exactly the visit somebody skips
 * when a page looks right.
 *
 * Static, so it covers a template nobody has opened yet, and modeled on
 * `wirekit:doctor:a11y` down to the `--fail-on` contract, because two doctors that behave
 * differently are two things to learn.
 */
class DoctorPropsCommand extends Command
{
    protected $signature = 'wirekit:doctor:props
        {path? : Path to scan (defaults to resources/views in the host app)}
        {--fail-on= : Treat findings as a non-zero exit. One of `error` (default) or `none`.}';

    protected $description = 'Static-analysis prop linter — finds unknown or misspelled props on WireKit components';

    public function handle(): int
    {
        $path = $this->argument('path') ?: base_path('resources/views');

        if (! is_dir($path)) {
            $this->error("Path not found or not a directory: {$path}");

            return self::FAILURE;
        }

        $failOn = (string) ($this->option('fail-on') ?: 'error');

        if (! in_array($failOn, ['error', 'none'], true)) {
            $this->error("Invalid --fail-on value: {$failOn}. Allowed: error / none.");

            // FAILURE, never INVALID. Every wirekit:* command exits 1 on every error path.
            return self::FAILURE;
        }

        $this->info("Scanning {$path} for unknown props...");
        $this->line('');

        $findings = [];
        $scanned = 0;

        foreach ($this->collectBladeFiles($path) as $file) {
            $contents = (string) file_get_contents($file);

            if (! str_contains($contents, '<x-wirekit::')) {
                continue;
            }

            $scanned++;

            foreach (BladeParser::extractWireKitComponentUsagesFromSource($contents) as $usage) {
                // A flat LIST of names, and getting this shape wrong is silent in both
                // directions. `extractProps` returns prop RECORDS (name, default, type_hint
                // …), while `unknownPropNames` compares with `in_array($key, $declared)` —
                // against the VALUES. Hand it the records and every attribute is unknown;
                // hand it a name-keyed map and every attribute is unknown again, because the
                // names are then keys. Neither mistake throws; both just report a correct
                // template as full of typos.
                $declared = collect(ComponentRegistry::extractProps($usage['name']))
                    ->pluck('name')
                    ->filter()
                    ->values()
                    ->all();

                // An empty declared list means the component's `@props` could not be
                // resolved (`glass`, `fonts`), and against an empty list EVERY attribute
                // reads as unknown. The gate returns early on exactly this, so the wave of
                // phantom findings cannot happen here — but say so, because a reader
                // wondering why a file is quiet deserves the reason in the code.
                foreach (StrictnessGate::unknownPropNames(array_fill_keys($usage['attributes'], true), $declared) as $unknown) {
                    $suggestions = SuggestSimilar::byLevenshtein($unknown, $declared);

                    $findings[] = [
                        'file' => str_replace(base_path().'/', '', $file),
                        'component' => $usage['name'],
                        'prop' => $unknown,
                        'suggestions' => $suggestions,
                    ];
                }
            }
        }

        if ($findings === []) {
            $this->info(sprintf('No unknown props found across %d template(s) using WireKit components.', $scanned));

            return self::SUCCESS;
        }

        foreach ($findings as $finding) {
            $this->line(sprintf(
                '  <fg=yellow>%s</> — <x-wirekit::%s> has no prop <fg=red>%s</>%s',
                $finding['file'],
                $finding['component'],
                $finding['prop'],
                $finding['suggestions'] === []
                    ? ''
                    : ' — did you mean '.implode(' or ', array_map(fn ($s) => "`{$s}`", $finding['suggestions'])).'?'
            ));
        }

        $this->line('');
        $this->warn(sprintf('%d unknown prop(s) across %d template(s).', count($findings), $scanned));
        $this->line('An unknown prop is not an error at render time — it lands in the attribute bag and');
        $this->line('the component renders with its default instead. That is why these are worth finding');
        $this->line('here rather than by noticing a page looks subtly wrong.');

        return $failOn === 'none' ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function collectBladeFiles(string $root): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), '.blade.php')) {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
