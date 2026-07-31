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
 * on docs.wirekit.app and any developer's deployment.
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
     * The newest version the package has actually RELEASED, from CHANGELOG.md.
     *
     * A different question from `resolve()`, and the difference is the whole point.
     * `resolve()` answers "which build is installed here", and on a deployment that
     * pins `dev-develop` — which the documentation site does — that is literally the
     * string `'dev-develop'`. So it cannot be compared against a version a page
     * claims to be showing, and a downstream check built on it would compare
     * `dev-develop` to `2.22.0` and have nothing to say.
     *
     * This answers "what is the newest release this package claims", which is the
     * comparable fact. It exists because a documentation page served a changelog
     * frozen four minors back and NOTHING anywhere disagreed with it out loud: the
     * page said 2.21.1, the package said 2.22.0, and no single artifact carried both
     * halves of a comparison. A version rather than a date or a count, deliberately
     * — a count is a fact about a moment and goes stale within the hour, a version
     * is a fact about a release.
     *
     * The newest CLOSED section, never a staging bucket. Two shapes stage a section:
     * `## [Unreleased]`, which carries no number to match, and `## [x.y.z] —
     * Unreleased`, whose number is decided while its content is still being written.
     * Publishing the second as "released" is the failure this method must not have:
     * it would announce a version that has no tag, which is worse than announcing a
     * stale one, because a developer can act on it.
     *
     * @param  string|null  $packageRoot  defaults to this package's own root
     * @return string|null null when no released section exists — the caller decides
     *                     whether that is fatal, because for a fresh checkout it is not
     */
    public static function released(?string $packageRoot = null): ?string
    {
        $path = ($packageRoot ?? dirname(__DIR__, 2)).'/CHANGELOG.md';

        if (! file_exists($path)) {
            return null;
        }

        $changelog = (string) file_get_contents($path);

        preg_match_all('~^## \[(\d+\.\d+\.\d+)\](.*)$~m', $changelog, $all, PREG_SET_ORDER);

        foreach ($all as $m) {
            if (stripos($m[2], 'unreleased') !== false) {
                continue;
            }

            return $m[1];
        }

        return null;
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
