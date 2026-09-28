<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Detects the Tailwind CSS major version the host app declares.
 *
 * WireKit's CSS is built on the Tailwind v4 engine (`@theme`, `@source`,
 * `color-mix()`, `@property`) and cannot run on v3 — but Tailwind is an npm
 * dependency of the app, so Composer (which resolves WireKit's PHP deps and
 * already enforces PHP / Laravel / Livewire via `require`) has no visibility
 * into it, and WireKit is not an npm package so npm peer-deps can't fire either.
 * The earliest WireKit-controlled checkpoint is therefore the artisan layer
 * (`wirekit:install` / `wirekit:doctor`), which CAN read the app's package.json.
 *
 * Detection is deliberately CONSERVATIVE: {@see self::isPreV4()} returns true
 * only on POSITIVE pre-v4 evidence, so a valid v4 install is never blocked.
 */
final class TailwindVersion
{
    /**
     * The Tailwind major version the host app runs, or null when it can't be determined.
     *
     * The installed package decides first: `node_modules/tailwindcss/package.json` names the
     * version npm resolved, which is the one the app's build uses. Without it (nothing installed
     * yet, or a Plug'n'Play tree with no `node_modules`) the constraint in `devDependencies`,
     * then `dependencies`, is read, and only in a form that names ONE major: "^4.0.0", "~3.4",
     * "4.x", "4.1.13". A range such as ">=3.4" or "^3.4 || ^4.0" starts with its lower bound,
     * while npm installs the highest version the range allows, so its first number says nothing
     * about the version that runs. Such a constraint is undetermined, and {@see self::isPreV4()}
     * does not block on undetermined.
     */
    public static function detectMajor(string $basePath): ?int
    {
        $base = rtrim($basePath, '/');

        return self::installedMajor($base) ?? self::declaredMajor($base);
    }

    /**
     * The major of the installed `tailwindcss` package, read from its own manifest.
     */
    private static function installedMajor(string $base): ?int
    {
        $version = self::readJson($base.'/node_modules/tailwindcss/package.json')['version'] ?? null;

        if (! is_string($version) || preg_match('/^v?(\d+)\.\d+\.\d+/', $version, $m) !== 1) {
            return null;
        }

        return (int) $m[1];
    }

    /**
     * The major a package.json constraint names, when it names exactly one.
     */
    private static function declaredMajor(string $base): ?int
    {
        $pkg = self::readJson($base.'/package.json');

        if ($pkg === null) {
            return null;
        }

        foreach (['devDependencies', 'dependencies'] as $section) {
            $constraint = $pkg[$section]['tailwindcss'] ?? null;

            if (! is_string($constraint)) {
                continue;
            }

            // The first section that declares the package decides, readable or not.
            //
            // An optional `^`, `~`, `=` or `v`, then ONE version whose minor and patch may be
            // wildcards, and nothing after it but a pre-release tag. The major is group 1.
            if (preg_match('/^\s*[\^~=v]?\s*(\d+)(?:\.(?:\d+|[xX*])){0,2}(?:-[0-9A-Za-z.-]+)?\s*$/', $constraint, $m) !== 1) {
                return null;
            }

            return (int) $m[1];
        }

        return null;
    }

    /**
     * A JSON object from disk, or null when the file is missing, unreadable or not an object.
     *
     * @return array<mixed>|null
     */
    private static function readJson(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        // `@` and an explicit false check, for the reason given in appCssUsesV3Directives().
        $content = @file_get_contents($path);
        $decoded = $content === false ? null : json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * True when the app's CSS entry still uses Tailwind v3 directives
     * (`@tailwind base|components|utilities`) instead of v4's
     * `@import "tailwindcss"`. A corroborating signal when package.json has no
     * `tailwindcss` entry.
     */
    public static function appCssUsesV3Directives(string $basePath): bool
    {
        $cssFiles = glob(rtrim($basePath, '/').'/resources/css/*.css') ?: [];

        foreach ($cssFiles as $file) {
            // `@` and an explicit false check, not just a `(string)` cast.
            //
            // The cast catches the RETURN value; it does not stop the warning, and
            // Laravel's error handler promotes a warning to an ErrorException — so an
            // unreadable file (a dangling symlink, an artifact owned by another user, a
            // file mid-write) killed the caller either way. glob() lists such a file
            // happily, which is what makes it reachable at all.
            $content = @file_get_contents($file);

            if ($content === false) {
                continue;
            }

            if (preg_match('/@tailwind\s+(base|components|utilities)\b/', $content) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * True when the app is positively on a pre-v4 Tailwind. Conservative — the
     * package.json major wins when present; only when it is absent do we fall
     * back to the CSS-directive signal. Anything we can't positively classify
     * as pre-v4 (including "undetermined") returns false, so a valid v4 (or an
     * unusual-but-modern) setup is never blocked.
     */
    public static function isPreV4(string $basePath): bool
    {
        $major = self::detectMajor($basePath);

        if ($major !== null) {
            return $major < 4;
        }

        return self::appCssUsesV3Directives($basePath);
    }
}
