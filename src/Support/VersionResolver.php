<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Resolve the running WireKit version. Single source of truth so the three
 * export commands (`wirekit:export-json`, `wirekit:export-api-map`,
 * `wirekit:export-blocks`) and any future surface that needs to advertise the
 * package version stay in lockstep.
 *
 * Priority order:
 *
 *   1. Composer's `vendor/composer/installed.json` in the consuming app
 *      — the canonical source when WireKit is installed via Composer (the
 *      normal case on docs.wirekit.app and in any Laravel app that pulled
 *      the package via `composer require pushery/wirekit`). Returns the
 *      exact tag the app pulled.
 *
 *   2. The package's own `composer.json` `version` field if present
 *      — only set during local-development checkouts that pin a value.
 *      Best practice for Composer packages is to NOT carry this field
 *      (versions live in git tags) — so this branch rarely fires.
 *
 *   3. `'dev-develop'` — terminal fallback. Used when the package is
 *      checked out raw (no Composer install yet, no installed.json) and
 *      the package's own composer.json doesn't pin a version.
 *
 * Background: previously each export command had its own `detectVersion()`
 * / `packageVersion()` helper that read ONLY the package's own
 * `composer.json`. Since the package intentionally carries no `version`
 * field, every helper fell straight to `'dev'` / `'dev-develop'` even on
 * tagged releases consumed via Composer — visible on `/components.json`
 * and `/api-map.json` as `version: "dev"` instead of `version: "1.x.y"`
 * on docs.wirekit.app and any consumer's deployment.
 */
final class VersionResolver
{
    public static function resolve(): string
    {
        // Path 1 — consuming app's installed.json
        if (function_exists('base_path')) {
            $installedPath = base_path('vendor/composer/installed.json');
            if (file_exists($installedPath)) {
                $installed = json_decode((string) file_get_contents($installedPath), true);
                $packages = $installed['packages'] ?? $installed ?? [];
                if (is_array($packages)) {
                    foreach ($packages as $package) {
                        if (! is_array($package)) {
                            continue;
                        }
                        if (($package['name'] ?? null) === 'pushery/wirekit') {
                            $version = (string) ($package['version'] ?? '');
                            if ($version !== '' && $version !== 'dev') {
                                return $version;
                            }
                        }
                    }
                }
            }
        }

        // Path 2 — package's own composer.json
        $composerPath = self::packageComposerJsonPath();
        if (file_exists($composerPath)) {
            $composer = json_decode((string) file_get_contents($composerPath), true);
            if (is_array($composer) && isset($composer['version']) && $composer['version'] !== '') {
                return (string) $composer['version'];
            }
        }

        // Path 3 — terminal fallback
        return 'dev-develop';
    }

    /**
     * Resolve the path to the wirekit package's own composer.json — works
     * whether the package is consumed via Composer (`vendor/pushery/wirekit/`)
     * or checked out at the repo root.
     */
    private static function packageComposerJsonPath(): string
    {
        return dirname(__DIR__, 2).'/composer.json';
    }
}
