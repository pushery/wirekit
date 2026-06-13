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
     * The declared Tailwind major version (from package.json), or null when it
     * can't be determined. Reads `devDependencies` then `dependencies` and
     * parses the first integer run of the constraint, mirroring the version
     * parsing used elsewhere ("^4.0.0" → 4, "~3.4" → 3, "4.x" → 4).
     */
    public static function detectMajor(string $basePath): ?int
    {
        $pkgPath = rtrim($basePath, '/').'/package.json';
        if (! is_file($pkgPath)) {
            return null;
        }

        $pkg = json_decode((string) file_get_contents($pkgPath), true);
        if (! is_array($pkg)) {
            return null;
        }

        foreach (['devDependencies', 'dependencies'] as $section) {
            $constraint = $pkg[$section]['tailwindcss'] ?? null;
            if (is_string($constraint) && preg_match('/(\d+)/', $constraint, $m)) {
                return (int) $m[1];
            }
        }

        return null;
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
            $content = (string) file_get_contents($file);
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
