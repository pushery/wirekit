<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Support\SuggestSimilar;

class ListComponentsCommand extends Command
{
    protected $signature = 'wirekit:list
        {--category= : Filter by category. Accepts comma-separated list (e.g. --category=Marketing,Display) for multi-category filter.}
        {--as= : Output format: count|json|slugs|categories. Default: human-readable table.}
        {--format= : Alias for --as. Symfony-Console-style spelling for developers accustomed to the `--format=json` idiom common in other Laravel commands.}';

    protected $description = 'List all WireKit components grouped by category';

    /**
     * Output formats supported by --as=…. Each value below maps to a
     * handler method `outputAs<Format>($components, $grouped, $total)`
     * dispatched in handle(). Adding a new format = adding an entry
     * here PLUS the corresponding method.
     */
    private const FORMATS = ['count', 'json', 'slugs', 'categories'];

    public function handle(): int
    {
        $categoryFilter = $this->option('category');
        // --as is canonical; --format is the Laravel-ecosystem-conventional
        // alias (Extension #4). Reject the combination — passing both with
        // different values is ambiguous and likely a copy-paste typo.
        $asValue = $this->option('as');
        $formatValue = $this->option('format');
        if ($asValue !== null && $asValue !== '' && $formatValue !== null && $formatValue !== '' && $asValue !== $formatValue) {
            $this->error('--as and --format are aliases and must not be passed with different values.');
            $this->line('  Pass one OR the other, not both.');

            return self::FAILURE;
        }
        $format = $asValue ?? $formatValue;

        $components = ComponentRegistry::all();

        if ($categoryFilter) {
            // Extension #3 — multi-category filter: comma-separated list
            // unions the per-category sets. `--category=Marketing,Display`
            // returns every component in EITHER category.
            $requested = array_values(array_filter(array_map('trim', explode(',', (string) $categoryFilter))));
            $available = ComponentRegistry::categories();
            $unknown = array_values(array_diff($requested, $available));

            if ($unknown !== []) {
                $this->error('Unknown category: '.implode(', ', $unknown));
                $this->line('  Available: '.implode(', ', $available));

                $suggestionLines = [];
                foreach ($unknown as $bad) {
                    $hint = SuggestSimilar::format(
                        SuggestSimilar::byLevenshtein($bad, $available)
                    );
                    if ($hint !== null) {
                        $suggestionLines[] = "  {$bad}: {$hint}";
                    }
                }
                foreach ($suggestionLines as $line) {
                    $this->line($line);
                }

                return self::FAILURE;
            }

            $components = [];
            foreach ($requested as $cat) {
                $components += ComponentRegistry::category($cat);
            }
            if ($components === []) {
                return self::FAILURE;
            }
        }

        if ($format !== null && $format !== '') {
            if (! in_array($format, self::FORMATS, true)) {
                $this->error("Unknown --as format: {$format}");
                $this->line('  Available: '.implode(', ', self::FORMATS));

                $hint = SuggestSimilar::format(
                    SuggestSimilar::byLevenshtein($format, self::FORMATS)
                );
                if ($hint !== null) {
                    $this->line('  '.$hint);
                }

                return self::FAILURE;
            }

            return $this->emitMachineFormat($format, $components);
        }

        return $this->emitHumanReadable($components);
    }

    /**
     * Emit one of the machine-readable formats. Output is plain
     * stdout text (no decoration) so developers can pipe into Bash
     * variables / jq / awk.
     *
     * @param  array<string, array{category: string, description: string}>  $components
     */
    private function emitMachineFormat(string $format, array $components): int
    {
        switch ($format) {
            case 'count':
                $this->output->write((string) count($components));

                return self::SUCCESS;

            case 'slugs':
                foreach (array_keys($components) as $name) {
                    $this->output->writeln($name);
                }

                return self::SUCCESS;

            case 'categories':
                $byCategory = [];
                foreach ($components as $meta) {
                    $byCategory[$meta['category']] = ($byCategory[$meta['category']] ?? 0) + 1;
                }
                ksort($byCategory);
                $this->output->write((string) json_encode($byCategory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                return self::SUCCESS;

            case 'json':
                $payload = [];
                foreach ($components as $name => $meta) {
                    $payload[] = [
                        'name' => $name,
                        'category' => $meta['category'],
                        'description' => $meta['description'],
                        'tag' => ComponentRegistry::tag($name),
                    ];
                }
                // JSON_HEX_TAG protects against `</script>` breakouts when
                // the output is embedded in a `<script type="application/ld+json">`
                // block. Same XSS-safety contract as `wirekit:export-json`.
                $this->output->write(
                    (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG)
                );

                return self::SUCCESS;

            default:
                // Unreachable — validated in handle().
                return self::FAILURE;
        }
    }

    /**
     * Emit the human-readable table (default mode).
     *
     * @param  array<string, array{category: string, description: string}>  $components
     */
    private function emitHumanReadable(array $components): int
    {
        $grouped = [];
        foreach ($components as $name => $meta) {
            $grouped[$meta['category']][$name] = $meta;
        }

        ksort($grouped);

        $total = count($components);
        $this->info("WireKit Components ({$total})");
        $this->line('');

        foreach ($grouped as $category => $items) {
            ksort($items);
            $this->line("  <fg=yellow>{$category}</> (".count($items).')');

            foreach ($items as $name => $meta) {
                $this->line("    <fg=green>{$name}</> — {$meta['description']}");
            }

            $this->line('');
        }

        $this->line('  Use <fg=cyan>php artisan wirekit:show {name}</> for details.');
        $this->line('  For scripted use, pass <fg=cyan>--as=count|json|slugs|categories</>.');

        return self::SUCCESS;
    }
}
