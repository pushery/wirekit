<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Support\SuggestSimilar;
use Pushery\WireKit\WireKit;

/**
 * Scaffold a custom component derived from a WireKit base.
 *
 * Copies the base component's Blade file to
 * `resources/views/components/custom/{name}.blade.php` so the developer can
 * override classes, variants, slots without publishing the whole package
 * vendor:publish --tag=wirekit-views (which would copy ~109 files).
 *
 * `--base` defaults:
 *   1. Explicit --base= wins (today's behaviour).
 *   2. Self-derivation: if `name` IS itself a valid component (e.g.
 *      `wirekit:component button`), use it. Most common case.
 *   3. Right-segment derivation: `my-button` → `button`,
 *      `custom-card` → `card`. Verified against ComponentRegistry::has()
 *      before use.
 *   4. If derivation produces no real component, fall back to a
 *      Levenshtein suggestion against ComponentRegistry::names().
 *   5. If TTY-detected, --no-interaction is NOT set, AND we are not in a
 *      unit test (Pest), prompt the user with the suggestion list.
 *
 * Usage:
 *   php artisan wirekit:component my-button
 *     -> derives --base=button, copies button.blade.php
 *
 *   php artisan wirekit:component button --force
 *     -> overwrite existing file (safe-by-default refuses without --force)
 */
class ComponentMakeCommand extends Command
{
    protected $signature = 'wirekit:component
        {name : Component slug for the new file (kebab-case)}
        {--base= : Source component to copy from. Defaults to the rightmost dash-segment of {name} (e.g. "my-button" → "button"). Falls back to a Levenshtein suggestion when no real component matches.}
        {--force : Overwrite an existing custom component}
        {--interactive : Prompt for --base from the component catalogue when derivation has no clear match (defaults to on in a TTY).}';

    protected $description = 'Scaffold a custom Blade component derived from a WireKit base';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        if (! preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
            $this->error("Invalid component name '{$name}'. Use kebab-case (e.g. 'my-button').");

            return self::FAILURE;
        }

        $base = $this->resolveBase($name);
        if ($base === null) {
            // resolveBase() already printed the actionable error including
            // a Did-you-mean line; return INVALID semantics (FAILURE) here.
            return self::FAILURE;
        }

        $baseSourcePath = $this->resolveBladeSource($base);
        if ($baseSourcePath === null || ! file_exists($baseSourcePath)) {
            $this->error("Unknown base component '{$base}'.");
            $hint = SuggestSimilar::format(
                SuggestSimilar::byLevenshtein($base, $this->knownComponentNames())
            );
            if ($hint !== null) {
                $this->line('  '.$hint);
            }
            $this->line('  Run `php artisan wirekit:list` to see all top-level components.');
            $this->line("  Sub-components use dotted names (e.g. 'card.header').");

            return self::FAILURE;
        }

        $targetPath = resource_path("views/components/custom/{$name}.blade.php");

        if (file_exists($targetPath) && ! $this->option('force')) {
            $this->error("Component already exists at {$targetPath}.");
            $this->line('  Re-run with --force to overwrite.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($targetPath));
        File::copy($baseSourcePath, $targetPath);

        $this->info("Scaffolded custom component → {$targetPath}");
        $this->line('  Source: '.str_replace(base_path().'/', '', $baseSourcePath));
        $this->line('');
        $this->line('  Usage:');
        $this->line('    <x-custom::'.$name.' ... />');
        $this->line('');
        $this->line('  To register a personalization scope instead of copying the file,');
        $this->line('  see '.WireKit::DOCS_URL.'/customization');

        return self::SUCCESS;
    }

    /**
     * Resolve the `--base` value for the new component.
     *
     * Order of precedence:
     *   1. Explicit `--base=…` flag wins.
     *   2. Right-segment derivation: `my-button` → `button`. Verified
     *      against the package's blade-component directory.
     *   3. Levenshtein suggestion against the component registry.
     *      In a TTY (and unless --no-interaction is set), prompt the
     *      user to pick from the top suggestions.
     *
     * Returns null when no base can be derived AND the user declined
     * the interactive prompt (or non-interactive mode rejects every
     * candidate). The caller treats null as "exit with failure" — the
     * actionable error message is already printed here.
     */
    private function resolveBase(string $name): ?string
    {
        $explicit = $this->option('base');
        if ($explicit !== null && $explicit !== '') {
            return (string) $explicit;
        }

        // 1. Self-derivation: name itself IS a valid component. Common case:
        //    `wirekit:component button` to scaffold a custom variant of the
        //    button component. Unambiguous — no prompt needed.
        if ($this->resolveBladeSource($name) !== null) {
            return $name;
        }

        // 2. Right-segment derivation.
        if (str_contains($name, '-')) {
            $segments = explode('-', $name);
            $rightmost = (string) end($segments);
            if ($rightmost !== '' && $this->resolveBladeSource($rightmost) !== null) {
                $this->line("  <fg=blue>i</> Derived --base={$rightmost} from '{$name}'. Pass --base= explicitly to override.");

                return $rightmost;
            }
        }

        // 3. Levenshtein suggestion.
        $candidates = $this->knownComponentNames();
        $suggestions = SuggestSimilar::byLevenshtein($name, $candidates, max: 5);

        // 4. Interactive prompt — guarded behind TTY detection + the
        //    --no-interaction flag (Symfony provides this option on every
        //    command via NoInteraction). Skip silently when stdin isn't a
        //    TTY (CI, piped invocations).
        $interactiveFlag = (bool) $this->option('interactive');
        $isInteractive = $this->input->isInteractive() || $interactiveFlag;

        // Skip in unit tests — Laravel's Artisan::call() test runner
        // considers itself interactive (ArrayInput defaults to interactive=true)
        // but doesn't forward real stdin. On a TTY-equipped local `vendor/bin/pest`
        // run, Symfony's QuestionHelper falls through to a blocking
        // `fgets(STDIN)` here. Mirrors the same guard in
        // InstallCommand::maybeRunInteractivePrompts (line 666).
        if ($isInteractive && $suggestions !== [] && ! app()->runningUnitTests()) {
            $picked = $this->choice(
                "No --base provided and '{$name}' does not derive cleanly. Pick a base component:",
                array_merge($suggestions, ['<cancel>']),
                $suggestions[0]
            );
            if ($picked !== '<cancel>') {
                return (string) $picked;
            }
        }

        $this->error("Could not derive a --base from '{$name}'. Pass --base= explicitly.");
        $hint = SuggestSimilar::format($suggestions);
        if ($hint !== null) {
            $this->line('  '.$hint);
        }

        return null;
    }

    /**
     * Component names suitable for `--base` derivation: ComponentRegistry's
     * top-level catalogue. Sub-components like `card.header` are reachable
     * via dotted names but are not common base templates.
     *
     * @return list<string>
     */
    private function knownComponentNames(): array
    {
        return array_values(array_keys(ComponentRegistry::all()));
    }

    /**
     * Resolve a component name to its Blade source path inside the package.
     * Handles both flat (`button.blade.php`) and dotted
     * (`card.header` -> `card/header.blade.php`) forms.
     */
    private function resolveBladeSource(string $name): ?string
    {
        $base = __DIR__.'/../../resources/views/components/';

        $flat = $base.$name.'.blade.php';
        if (file_exists($flat)) {
            return $flat;
        }

        $dotted = $base.str_replace('.', '/', $name).'.blade.php';
        if (file_exists($dotted)) {
            return $dotted;
        }

        return null;
    }
}
