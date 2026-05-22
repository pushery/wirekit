<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Fonts\FontPreset;
use Pushery\WireKit\Fonts\FontRegistry;
use Pushery\WireKit\Support\SuggestSimilar;

class ListFontsCommand extends Command
{
    protected $signature = 'wirekit:fonts
        {--category= : Filter by category. Values: sans, serif, mono.}
        {--as= : Output format: count|json|slugs|categories. Default: human-readable table.}
        {--format= : Alias for --as. Symfony-Console-style spelling for developers accustomed to the `--format=json` idiom common in other Laravel commands.}';

    protected $description = 'List every font preset shipped with WireKit, grouped by category';

    /**
     * Output formats supported by --as=…. Each value below maps to a
     * handler method `outputAs<Format>($fonts, $grouped, $total)`
     * dispatched in handle(). Mirrors ListComponentsCommand's API.
     */
    private const FORMATS = ['count', 'json', 'slugs', 'categories'];

    /**
     * Canonical font categories. Same enum FontPreset's `category`
     * field accepts. Validating against this list lets us produce a
     * Levenshtein hint on a typo (`--category=sanss` → suggest `sans`).
     */
    private const CATEGORIES = ['sans', 'serif', 'mono'];

    public function handle(): int
    {
        $categoryFilter = $this->option('category');
        $asValue = $this->option('as');
        $formatValue = $this->option('format');

        if ($asValue !== null && $asValue !== '' && $formatValue !== null && $formatValue !== '' && $asValue !== $formatValue) {
            $this->error('--as and --format are aliases and must not be passed with different values.');
            $this->line('  Pass one OR the other, not both.');

            return self::FAILURE;
        }
        $format = $asValue ?? $formatValue;

        $fonts = FontRegistry::all();

        if ($categoryFilter) {
            $requested = (string) $categoryFilter;

            if (! in_array($requested, self::CATEGORIES, true)) {
                $this->error("Unknown category: {$requested}");
                $this->line('  Available: '.implode(', ', self::CATEGORIES));

                $hint = SuggestSimilar::format(
                    SuggestSimilar::byLevenshtein($requested, self::CATEGORIES)
                );
                if ($hint !== null) {
                    $this->line('  '.$hint);
                }

                return self::FAILURE;
            }

            $fonts = FontRegistry::category($requested);
            if ($fonts === []) {
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

            return $this->dispatch($format, $fonts);
        }

        $this->renderHumanReadable($fonts);

        return self::SUCCESS;
    }

    /**
     * Human-readable, category-grouped listing. Mirrors `wirekit:list`'s
     * default output shape so developers don't have to learn a second
     * convention.
     *
     * @param  array<string, FontPreset>  $fonts
     */
    private function renderHumanReadable(array $fonts): void
    {
        $grouped = [];
        foreach ($fonts as $key => $preset) {
            $grouped[$preset->category][$key] = $preset;
        }

        foreach (self::CATEGORIES as $category) {
            if (! isset($grouped[$category])) {
                continue;
            }
            $this->info(ucfirst($category));
            foreach ($grouped[$category] as $key => $preset) {
                $this->line("  <fg=green>{$key}</>  {$preset->label}  <fg=gray>({$preset->family})</>");
            }
            $this->line('');
        }

        $total = count($fonts);
        $this->info("Total: {$total} font preset".($total === 1 ? '' : 's'));
        $this->line('  Use any key with `wirekit:install --font={key}`.');
    }

    /**
     * Route to the requested --as= handler. Returns the handler's
     * exit code so the caller can early-return.
     *
     * @param  array<string, FontPreset>  $fonts
     */
    private function dispatch(string $format, array $fonts): int
    {
        switch ($format) {
            case 'count':
                $this->line((string) count($fonts));

                return self::SUCCESS;

            case 'slugs':
                foreach (array_keys($fonts) as $key) {
                    $this->line($key);
                }

                return self::SUCCESS;

            case 'categories':
                $categories = [];
                foreach ($fonts as $preset) {
                    $categories[$preset->category] = true;
                }
                foreach (array_keys($categories) as $cat) {
                    $this->line($cat);
                }

                return self::SUCCESS;

            case 'json':
                $payload = [];
                foreach ($fonts as $key => $preset) {
                    $payload[] = [
                        'key' => $key,
                        'label' => $preset->label,
                        'family' => $preset->family,
                        'category' => $preset->category,
                        'install' => "php artisan wirekit:install --font={$key}",
                    ];
                }
                // JSON_HEX_TAG: same XSS-safety contract as every other
                // wirekit:* JSON-emitting command.
                $this->output->write(
                    (string) json_encode(
                        $payload,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG
                    )
                );

                return self::SUCCESS;
        }

        // Unreachable — validated in handle().
        return self::FAILURE;
    }
}
