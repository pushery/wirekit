<?php

declare(strict_types=1);

namespace Pushery\WireKit\Icons;

use Illuminate\Support\Str;

/**
 * Which Composer package ships the icon set behind a resolved icon name.
 *
 * An alias resolves through a preset table into a prefixed name — `inbox` becomes
 * `heroicon-m-inbox` — and that name only renders if the package providing the
 * `heroicon` set is installed. The preset tables are static: they never check, because
 * checking every glyph at resolve time would mean touching the filesystem for an
 * answer that is the same for the whole set.
 *
 * So when the set is absent the failure surfaces at render, and "why are my icons
 * gone" has no answer in the message unless something knows which package to name.
 * That is all this class is for.
 *
 * The map is deliberately NOT a method on the preset contract: adding one would be a
 * breaking change for anyone who has implemented `IconPreset` themselves, and this is
 * a minor. `IconSetPackagesMatchComposerSuggestTest` holds it against the `suggest`
 * block in composer.json, which is the list a developer actually reads.
 */
final class IconSetPackages
{
    /**
     * Set prefix => the Composer package that registers it.
     *
     * @var array<string, string>
     */
    private const PACKAGES = [
        'heroicon' => 'blade-ui-kit/blade-heroicons',
        'lucide' => 'mallardduck/blade-lucide-icons',
        'phosphor' => 'codeat3/blade-phosphor-icons',
        'tabler' => 'secondnetwork/blade-tabler-icons',
    ];

    /**
     * The package for a resolved icon name, or null when the prefix is not one of ours.
     *
     * A developer's own set is the common case for null — they registered it themselves
     * and know where it came from, so naming a package would be a guess.
     */
    public static function forResolvedName(string $resolved): ?string
    {
        return self::PACKAGES[Str::before($resolved, '-')] ?? null;
    }

    /**
     * The diagnostic for a name whose set could not be found.
     *
     * It names the resolved identifier rather than the alias the developer wrote,
     * because the alias is fine — `inbox` resolved correctly, and the gap is one layer
     * further down. Saying "unknown icon 'inbox'" would send them to fix a table that
     * has nothing wrong with it.
     */
    public static function missingSetMessage(string $alias, string $resolved): string
    {
        $prefix = Str::before($resolved, '-');
        $package = self::forResolvedName($resolved);

        $message = "WireKit: the icon alias '{$alias}' resolved to '{$resolved}', but no icon set "
            ."with the prefix '{$prefix}' is registered.";

        $message .= $package !== null
            ? " Install {$package} to provide it."
            : ' Register that set with blade-icons, or point WireKit at a preset whose set you have installed.';

        return $message.' Rendering an empty placeholder.';
    }

    /**
     * Prefixes already reported this process.
     *
     * @var array<string, true>
     */
    private static array $reported = [];

    /**
     * Report a missing set once per prefix, and say whether this call did the reporting.
     *
     * Once per PREFIX rather than once per process: a page can legitimately mix two
     * presets, and a developer who installed one of the two packages needs to hear about
     * the other. Once per RENDER would be worse than useless — a single page draws icons
     * through buttons, dropdowns and modals, so the same sentence would arrive fifty
     * times and the log would be the thing the developer stops reading.
     *
     * Resettable because the state is static and a test that cannot clear it can only
     * ever assert the first case in the process; every later one would read as "already
     * reported" and pass for the wrong reason.
     */
    public static function reportMissingSetOnce(string $alias, string $resolved): bool
    {
        $prefix = Str::before($resolved, '-');

        if (isset(self::$reported[$prefix])) {
            return false;
        }

        self::$reported[$prefix] = true;

        // Guarded the same way IconResolver guards its own degradation log: this view
        // renders in contexts with no container behind it (the sandbox renderer, a bare
        // Blade::render in a test), and a diagnostic that throws is worse than silence.
        if (function_exists('logger')) {
            logger()->error(self::missingSetMessage($alias, $resolved));
        }

        return true;
    }

    /**
     * Forget what has been reported. For tests only.
     */
    public static function flushReported(): void
    {
        self::$reported = [];
    }

    /**
     * Every prefix this class claims to know.
     *
     * @return array<string>
     */
    public static function knownPrefixes(): array
    {
        return array_keys(self::PACKAGES);
    }

    /**
     * Every package this class names, for the drift guard.
     *
     * @return array<string>
     */
    public static function knownPackages(): array
    {
        return array_values(self::PACKAGES);
    }
}
