<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use BladeUI\Icons\Factory;
use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\Icons\IconResolver;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class IconPresetPackagesCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkIconPresetPackages();
    }

    /**
     * Does every CONFIGURED icon preset have its composer package installed?
     *
     * The published config carries a commented line offering the stacked shape:
     *
     *     // 'presets' => ['heroicons', 'heroicons-marketing'],
     *
     * Uncommenting it on a phosphor, lucide or tabler installation trades a missing
     * alias for a RESOLVING alias onto a glyph that is not installed. By default a
     * request then renders the icon placeholder and logs the package to install,
     * and a console or test run throws. Either way the finding surfaces in whichever
     * view happened to use the word, and says nothing about the config line that
     * caused it.
     *
     * A WARNING rather than a failure, and rather than a boot-time abort. An abort
     * would be a behavior change on a shipped configuration; this is purely additive
     * and moves the discovery to a command a developer chose to run. The stricter
     * form stays available afterwards if it turns out somebody needs it — the reverse
     * is not as easy.
     */
    private function checkIconPresetPackages(): void
    {
        if (! class_exists(Factory::class)) {
            // Nothing measured is NOT the same as everything fine, and must not print like it.
            $this->reportInfo('Icon presets not checked (Blade Icons is unavailable)');

            return;
        }

        $resolver = app(IconResolver::class);
        $samples = $resolver->sampleIdentifiers();

        if ($samples === []) {
            $this->reportPass('No icon preset configured');

            return;
        }

        // Whether Composer installed the package is a different question from the one that
        // decides whether a page renders, so this asks the second one.
        //
        // An application may ship the glyphs itself: derive the subset its tree actually uses,
        // drop them under the preset's prefix, register that set in its own `blade-icons` config
        // and deliberately not depend on the upstream package. Composer then says "not installed"
        // and every icon on every page resolves. Advising `composer require` there would bring a
        // second set claiming the same prefix, which Blade Icons refuses outright, and the
        // starter-kit baseline builds exactly that arrangement.
        //
        // Resolving one real identifier per preset answers the question the message is about,
        // through the supported API rather than the factory's `@internal` set list.
        $unresolved = [];

        foreach ($samples as $preset => $identifier) {
            try {
                app(Factory::class)->svg($identifier);
            } catch (\Throwable) {
                $unresolved[$preset] = $identifier;
            }
        }

        if ($unresolved === []) {
            $this->reportPass('Every configured icon preset resolves');

            return;
        }

        $packages = $resolver->requiredPackages();

        foreach ($unresolved as $preset => $identifier) {
            $package = $packages[$preset] ?? null;

            $this->reportWarn(sprintf(
                "Icon preset '%s' does not resolve: '%s' is not registered with Blade Icons, so "
                .'the aliases pointing at it fail when a page renders. Either install %s, or '
                .'register a set of your own under that prefix — shipping the glyphs yourself is '
                .'a supported arrangement, and it is why this check asks whether the identifier '
                .'resolves rather than whether the package is present.',
                $preset,
                $identifier,
                $package ?? 'the preset\'s package'
            ));
        }
    }
}
