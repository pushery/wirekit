<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\Support\BaseLocaleJsonLoader;
use Pushery\WireKit\WireKit;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class TranslationKeyCollisionsCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkTranslationKeyCollisions();
    }

    /**
     * Shared translation keys whose meaning diverges between the app and WireKit.
     *
     * The catalog is one FLAT JSON namespace and the loader puts the app on top, deliberately —
     * a translation someone wrote on purpose should win. That rule cannot resolve one case: an
     * ordinary English word with more than one meaning. An app using "Map" as a verb and WireKit
     * using it as a noun collide, the app wins, and it wins INSIDE a WireKit component — which
     * then shows a word that is wrong in its own context. Nothing errors; only the text is wrong.
     *
     * A collision on a component that renders on many pages shows the wrong word on all of them,
     * and one on a component not in use yet lands on its first use, with nothing turning red.
     *
     * This does not resolve the design question (a namespaced catalog, `wirekit::Map`). It
     * spares every application building this detection itself.
     */
    private function checkTranslationKeyCollisions(): void
    {
        $packageLang = dirname(__DIR__, 4).'/lang';
        $appLang = function_exists('lang_path') ? lang_path() : base_path('lang');

        if (! is_dir($packageLang) || ! is_dir($appLang)) {
            $this->reportInfo('Translation collisions not checked (no published language directory)');

            return;
        }

        /** @var array<string, list<string>> $collisions namespaced key => locales it diverges in */
        $collisions = [];

        /** @var array<string, array<string, bool>> $ambiguous namespaced key => locale => the two spellings hold the SAME string */
        $ambiguous = [];

        foreach ((array) glob($packageLang.'/*.json') as $ours) {
            $locale = basename((string) $ours, '.json');
            $theirs = $appLang.'/'.$locale.'.json';

            if (! is_file($theirs)) {
                continue;
            }

            $mine = json_decode((string) file_get_contents((string) $ours), true);
            $app = json_decode((string) file_get_contents($theirs), true);

            if (! is_array($mine) || ! is_array($app)) {
                continue;
            }

            foreach ($mine as $key => $value) {
                // THE PREFIX COMES OFF BEFORE THE COMPARISON, and this line is the whole check.
                //
                // Our keys are `wirekit::Dismiss`; an application that predates the prefix wrote
                // `Dismiss`. Comparing the two as stored finds nothing in common, so the check
                // would report a clean bill of health over precisely the collision it exists to
                // name — and it is the flat-namespace problem itself that makes that silence
                // expensive, because the legacy-key bridge keeps that application's wording
                // flowing into the component exactly as it did before.
                $plain = str_starts_with((string) $key, BaseLocaleJsonLoader::NAMESPACE)
                    ? substr((string) $key, strlen(BaseLocaleJsonLoader::NAMESPACE))
                    : (string) $key;

                $hasPlain = array_key_exists($plain, $app);
                $hasNamespaced = $plain !== $key && array_key_exists($key, $app);

                // BOTH spellings in one catalog. Not a collision — a fork. The namespaced entry
                // is the one the bridge steps aside for, so the plain one has quietly stopped
                // reaching WireKit while still looking like it governs the component. Whoever
                // adopted the new key almost certainly meant to retire the old one, and nothing
                // else in the system will ever mention that they did not.
                if ($hasPlain && $hasNamespaced) {
                    // Whether the two spellings hold the same string decides how this reads. Two
                    // copies of one wording is a fork waiting to drift, which is the case worth a
                    // warning. Two different wordings is the state the divergence message a few
                    // lines down asks for ("translate THAT key ... and keep 'Home' for your own
                    // use"), so reporting it at the same severity would make the two checks
                    // contradict each other: follow the first one's advice and the second one
                    // fails your gate.
                    $ambiguous[(string) $key][$locale] = $app[$plain] === $app[$key];

                    continue;
                }

                if ($hasPlain && $app[$plain] !== $value) {
                    $collisions[(string) $key][] = $locale;
                }
            }
        }

        $this->reportTranslationKeyForks($ambiguous);

        if ($collisions === []) {
            $this->reportPass('No translation key means something different in your catalog than in WireKit');

            return;
        }

        // A divergence only matters while the legacy-key bridge is on. With the bridge off, a
        // plain `Home` in the application's catalog does not reach the component at all —
        // WireKit renders its own `wirekit::Home` — so the wording difference has no effect on
        // anything the component shows, and the warning's own last sentence ("the legacy-key
        // bridge keeps applying your wording inside the component") would be untrue.
        $bridgeIsOn = (bool) config('wirekit.translations.legacy_key_bridge', true);

        if (! $bridgeIsOn) {
            $this->reportInfo(sprintf(
                'Your catalog re-words %d shared key(s), and the legacy-key bridge is off — so those '
                .'wordings stay on your own strings and the components render their own.',
                count($collisions)
            ));

            return;
        }

        // A divergence only bites where a COMPONENT renders the key — that is the difference
        // between "you translated a word differently" (fine, and the whole point of app-wins)
        // and "a WireKit component now shows your wording in its context" (the defect).
        $rendered = $this->componentsRenderingTranslationKeys(array_keys($collisions));

        $reportable = array_intersect_key($collisions, $rendered);

        if ($reportable === []) {
            $this->reportPass(sprintf(
                'Your catalog re-words %d shared key(s), none of them rendered by a WireKit component',
                count($collisions)
            ));

            return;
        }

        // Which of these the application renders ITSELF decides what to advise. Leading with
        // "write the `wirekit::` twin and keep your own key" would copy a string this package
        // already ships into a second file that then drifts from the first — and the fork
        // reporter above names that copy as a finding of its own, so following that advice
        // would fail the second check.
        //
        // A colliding key is often an orphan, left over from before this package namespaced its
        // own strings, and deleting it is then the whole fix, so the message names that route.
        $plainKeys = [];
        foreach (array_keys($reportable) as $key) {
            $plainKeys[] = substr((string) $key, strlen(BaseLocaleJsonLoader::NAMESPACE));
        }

        $appUses = $this->applicationUsesTranslationKeys($plainKeys);

        foreach ($reportable as $key => $locales) {
            $plain = substr((string) $key, strlen(BaseLocaleJsonLoader::NAMESPACE));

            // The ways out, most-likely first. The last one is last because it is the one
            // that creates a second copy, and it carries the step that is easy to miss:
            // the plain key has to go with it, or this command reports a fork next run.
            $ways = isset($appUses[$plain])
                ? sprintf(
                    "Your own templates DO render '%s', so it is not stale. Either rename your key to "
                    .'something that says what it means in your interface, or — if the component is '
                    .'being handed the string as a prop — let it derive that string itself. As a last '
                    ."resort translate '%s' as well and delete '%s'; keeping both is a fork, and this "
                    .'command reports it as one.',
                    $plain,
                    $key,
                    $plain
                )
                : sprintf(
                    "Nothing in your own views or app code appears to render '%s', so it is most likely "
                    .'left over from before this package namespaced its own strings — deleting it gives '
                    .'the component its wording back and costs you nothing. If you do use it somewhere '
                    ."this scan cannot see, rename it instead. As a last resort translate '%s' as well "
                    ."and delete '%s'; keeping both is a fork, and this command reports it as one.",
                    $plain,
                    $key,
                    $plain
                );

            $this->reportWarn(sprintf(
                "Translation key '%s' means something different in your catalog than in WireKit (%s), "
                .'and %s renders it — so that component will show your wording in a context it was not '
                .'written for. %s Until then, the legacy-key bridge keeps applying your wording inside '
                .'the component, exactly as before.',
                $plain,
                implode(', ', $locales),
                $rendered[$key],
                $ways
            ));
        }
    }

    /**
     * Report a catalog that carries BOTH spellings of the same string.
     *
     * Its own reporter rather than a branch inside the collision report, because it is a
     * different finding with a different remedy — and it is NOT gated on whether a component
     * renders the key. A fork is worth naming wherever it sits: the plain entry has stopped
     * governing WireKit, so the next reader who edits it to change a component's wording
     * changes nothing, and nothing tells them why.
     *
     * Both spellings of one key in a catalog — and only one of the two shapes is a defect.
     *
     * Holding `Home` and `wirekit::Home` with DIFFERENT wordings is the state the divergence
     * message a few methods up explicitly asks for: translate the namespaced key to give the
     * component its wording back, keep the plain one for your own pages. Reporting that at
     * warning severity made the two checks contradict each other — follow the first one's
     * advice and the second one fails your gate, with no third state that satisfies both.
     *
     * Holding both with the SAME wording is the case worth a warning, and it is the one this
     * check was written for: somebody copied the string across and now maintains two of it.
     * Nothing else in the system will ever mention the day they drift apart.
     *
     * @param  array<string, array<string, bool>>  $ambiguous  namespaced key => locale => same string
     */
    private function reportTranslationKeyForks(array $ambiguous): void
    {
        $intentional = 0;

        foreach ($ambiguous as $key => $locales) {
            $plain = substr((string) $key, strlen(BaseLocaleJsonLoader::NAMESPACE));
            $duplicated = array_keys(array_filter($locales));

            if ($duplicated === []) {
                $intentional++;

                continue;
            }

            $this->reportWarn(sprintf(
                "Your catalog holds '%s' and '%s' with the SAME wording (%s). Only the second one "
                ."reaches WireKit, so they are one string in two places now: change '%s' and the "
                .'component keeps the old text, with nothing to tell you they came apart. Keep the '
                .'copy only if the two are meant to say different things.',
                $plain,
                $key,
                implode(', ', $duplicated),
                $plain
            ));
        }

        if ($intentional > 0) {
            $this->reportInfo(sprintf(
                '%d key(s) exist in both spellings with different wordings — the split this command '
                .'recommends, so your pages and the components each keep their own text.',
                $intentional
            ));
        }
    }

    /**
     * Which shipped component renders each of the given translation keys.
     *
     * The keys arrive NAMESPACED (`wirekit::Dismiss`), because that is what the call sites
     * contain — the prefix is part of the literal a component passes to `__()`, not a
     * decoration on top of it. Handing this the plain key would match nothing and report
     * that no component renders any of them, which reads as the good news.
     *
     * @param  list<string>  $keys  namespaced keys, as they appear at the call site
     * @return array<string, string> key => the component that renders it
     */
    private function componentsRenderingTranslationKeys(array $keys): array
    {
        $root = dirname(__DIR__, 4).'/resources/views/components';

        if (! is_dir($root) || $keys === []) {
            return [];
        }

        $found = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $body = (string) file_get_contents($file->getPathname());

            foreach ($keys as $key) {
                if (isset($found[$key])) {
                    continue;
                }

                // A regex rather than a handful of str_contains calls, for two reasons that
                // happen to point the same way. It tolerates whitespace after the call and
                // matches either quoting in one pass — the catalog holds keys containing
                // apostrophes. And the pattern spells each call with an escaped parenthesis, so
                // this file never contains the literal sequence a translation-key scanner looks
                // for: written inline, the needle reads to TranslationKeyDriftTest as this
                // command emitting a key of its own.
                //
                // ALL THREE call forms, because a key reachable by only one of the other two
                // would be reported as rendered by nothing — and this check's quiet answer is
                // the one that reads as good news. `trans_choice()` and `PluralPhrases::from()`
                // carry the plural strings, which are ordinary catalog keys and collide the
                // same way.
                $call = '(?:__|trans_choice|PluralPhrases::from)';

                if (preg_match('/'.$call.'\(\s*'.$this->translationKeyNeedle($key).'/', $body) === 1) {
                    $found[$key] = str_replace($root.'/', '', $file->getPathname());
                }
            }
        }

        return $found;
    }

    /**
     * Which of these keys the APPLICATION renders itself.
     *
     * The sibling above asks which WireKit component renders a key. This asks the other
     * half, and without it the advice cannot tell an orphan from a word the application
     * genuinely uses — which is the difference between "delete it" and "rename it".
     *
     * It matters more than it looks: in an application older than this package's namespaced
     * strings, most colliding keys are orphans. Nothing renders them, and without this half the
     * advice would send their maintainer to write a `wirekit::` twin for every one.
     *
     * Scanning the application is bounded on purpose — its views and its PHP, which is
     * where a translation call lives. A miss here is safe in the direction that matters:
     * an unseen usage reports as an orphan candidate, and the wording says "if nothing
     * renders it" rather than asserting it.
     *
     * @param  list<string>  $keys
     * @return array<string, true>
     */
    private function applicationUsesTranslationKeys(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $roots = array_values(array_filter([
            function_exists('resource_path') ? resource_path('views') : null,
            function_exists('app_path') ? app_path() : null,
        ], static fn ($dir) => is_string($dir) && is_dir($dir)));

        if ($roots === []) {
            return [];
        }

        $used = [];

        // Same three call forms as the sibling, and the same reason for spelling the
        // parenthesis as an escape: written inline, the needle reads to the
        // translation-key drift guard as this command emitting a key of its own.
        $call = '(?:__|trans|trans_choice|@lang)';

        foreach ($roots as $root) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.php')) {
                    continue;
                }

                $body = (string) file_get_contents($file->getPathname());

                foreach ($keys as $key) {
                    if (isset($used[$key])) {
                        continue;
                    }

                    if (preg_match('/'.$call.'\(\s*'.$this->translationKeyNeedle($key).'/', $body) === 1) {
                        $used[$key] = true;
                    }
                }
            }
        }

        return $used;
    }

    /**
     * A key as PHP SOURCE spells it, in either quoting, as one regex fragment.
     *
     * `preg_quote()` escapes a key for the regular expression and says nothing about how PHP
     * writes it. Between single quotes an apostrophe is spelled `\'`, and single quotes are what a
     * Blade template ordinarily writes, so a scan for the bare character would miss the key there.
     *
     * That fails in the quiet direction: a key nothing appears to render is reported as an orphan,
     * and the advice then tells the maintainer to delete a string their own page shows, leaving a
     * translated page with the English sentence and nothing going red.
     */
    private function translationKeyNeedle(string $key): string
    {
        // Between single quotes, PHP requires an escape for exactly two characters.
        $single = preg_quote(str_replace(['\\', "'"], ['\\\\', "\\'"], $key), '/');

        // Between double quotes the set is a different one, and `$` belongs to it: a key carrying
        // one would otherwise be written as an interpolation rather than as itself.
        $double = preg_quote(str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $key), '/');

        return '(?:\''.$single.'\'|"'.$double.'")';
    }
}
