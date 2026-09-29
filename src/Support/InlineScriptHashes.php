<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Pushery\WireKit\WireKit;
use Pushery\WireKit\WireKitServiceProvider;

/**
 * The CSP hash sources (`sha256-…`) of the inline scripts WireKit writes into a page.
 *
 * A policy that admits inline scripts by hash, without a nonce, has to list each script's hash in
 * `script-src`, or the browser refuses it without a word. Three bodies qualify: the first-paint
 * seed after a remembered sidebar column, the seed after a remembered folding section, and the
 * `@wirekitThemeScript` code while `wirekit.theme.script` writes it into the page. Every value the
 * seeds need arrives as a data attribute, so each body is a constant and its hash holds for every
 * page. `application/ld+json` from `structured-data` is a data block that `script-src` does not
 * govern, so it has no entry.
 *
 * The bodies are read from what the views render rather than from the package's files, so a
 * published override of a seed view is hashed as it stands. They are read once per process.
 */
final class InlineScriptHashes
{
    /** @var list<string>|null */
    private static ?array $hashes = null;

    /** @return list<string> */
    public static function all(): array
    {
        return self::$hashes ??= array_values(array_unique(array_map(
            static fn (string $body): string => 'sha256-'.base64_encode(hash('sha256', $body, true)),
            self::bodies(),
        )));
    }

    /**
     * The text of each inline script, exactly as it stands between its tags.
     *
     * @return list<string>
     */
    public static function bodies(): array
    {
        /** @var view-string $navSeed */
        $navSeed = 'wirekit::components.partials.nav-persist-seed';
        /** @var view-string $disclosureSeed */
        $disclosureSeed = 'wirekit::components.partials.disclosure-persist-seed';

        $bodies = [
            self::body(view($navSeed, [
                'seedKey' => 'wk',
                'seedOn' => false,
                'seedClassOn' => '',
                'seedClassOff' => '',
                'seedMinWidth' => null,
            ])->render()),
            self::body(view($disclosureSeed, [
                'seedKey' => 'wk',
                'seedOn' => false,
            ])->render()),
            // Empty while the theme script loads as a file, which a `'self'` source already admits.
            self::body(WireKitServiceProvider::themeScriptTag()),
        ];

        return array_values(array_filter($bodies, static fn (string $body): bool => $body !== ''));
    }

    /** Forget the bodies read so far; called by {@see WireKit::flush()}. */
    public static function reset(): void
    {
        self::$hashes = null;
    }

    private static function body(string $html): string
    {
        return preg_match('/<script\b[^>]*>(.*?)<\/script>/s', $html, $match) === 1 ? $match[1] : '';
    }
}
