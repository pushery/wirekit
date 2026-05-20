<?php

declare(strict_types=1);

namespace Pushery\WireKit\Drift;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Static-source inventory of class strings + design-token references emitted
 * by every layer of the WireKit codebase.
 *
 * Pure data extraction — no assertions. Reused by build-output diff and
 * theme-symmetry validation in the drift-audit test suite.
 *
 * @phpstan-type Occurrence array{file: string, line: int}
 * @phpstan-type Inventory array<string, list<Occurrence>>
 */
final class ClassInventory
{
    /**
     * Single-word Tailwind utility classes that the strict filter
     * (used for PHP and JS source extraction) must accept. Without
     * this allowlist, common static utilities like `flex` or `hidden`
     * would be filtered out alongside identifier-style strings like
     * `'primary'` because both shapes are indistinguishable by regex.
     *
     * Order: display, position, visibility, type, isolation.
     */
    private const KNOWN_SINGLE_WORD_CLASSES = [
        'block', 'inline', 'flex', 'grid', 'table', 'contents', 'hidden',
        'static', 'fixed', 'absolute', 'relative', 'sticky',
        'visible', 'invisible', 'collapse',
        'italic', 'antialiased', 'truncate',
        'capitalize', 'uppercase', 'lowercase',
        'underline', 'overline',
        'isolate',
        // Variant-scope markers + container utility
        'group', 'peer', 'container', 'dark',
    ];

    /**
     * Path prefixes skipped for CLASS extraction. Matched via
     * str_starts_with() — a value ending in `/` matches every file under
     * that directory; a non-slash value matches the literal path only.
     *
     * Use when a file's literal class candidates are placeholders /
     * domain identifiers / metadata but its `var(--…)` references (if
     * any) are real runtime usages and should still be counted by
     * tokenReferences().
     *
     * Empirical reality of the WireKit src/ layout:
     *   - Only src/VariantResolver.php emits Tailwind-class string
     *     literals from PHP. Every other file in src/ stores domain
     *     identifiers (icon names, chart-kind labels, slot keys,
     *     publish-tag names, version labels, slug constants).
     *   - The strict filter cannot tell `'color-picker'` (component
     *     slug) apart from `text-blue-500` (Tailwind class) by shape
     *     alone — both are hyphenated, both pass the regex.
     *   - Hence: skip every non-class-emitting source dir wholesale.
     *     If a future file needs to emit classes, REMOVE its prefix
     *     here in the same commit and add a regression test.
     *
     *   resources/views/_safelist.blade.php → Tailwind safelist payload
     *   src/Charts/                         → chart-kind labels + adapter glue
     *   src/Components/                     → class-component glue (no class strings)
     *   src/ComponentRegistry.php           → component-slug constants
     *   src/Console/                        → artisan command surfaces
     *   src/Contracts/                      → interfaces + DTOs
     *   src/Drift/                          → audit-tooling itself
     *   src/Fonts/                          → font preset / registry
     *   src/Icons/                          → icon resolver + heroicon / lucide presets
     *   src/Sandbox/                        → preview-renderer wiring
     *   src/Support/                        → version metadata + utilities
     *   src/WireKit.php                     → animation / icon registrations
     *   src/WireKitServiceProvider.php      → publish-tag names + boot wiring
     *   resources/js/                       → Alpine factory string literals
     *                                         (Floating-UI placements, ARIA
     *                                         attribute names, CSS-property
     *                                         strings inside chart options,
     *                                         custom event names) — no JS
     *                                         file in the codebase emits
     *                                         Tailwind arbitrary-value
     *                                         classes today. If a future
     *                                         factory does emit them,
     *                                         REMOVE the prefix here in the
     *                                         same commit + add a regression
     *                                         test that fails without it.
     */
    public const DEFAULT_SKIP_PATH_PREFIXES_FOR_CLASS_EXTRACTION = [
        'resources/views/_safelist.blade.php',
        'resources/js/',
        'src/Charts/',
        'src/Components/',
        'src/ComponentRegistry.php',
        'src/Console/',
        'src/Contracts/',
        'src/Drift/',
        'src/Fonts/',
        'src/Icons/',
        'src/Sandbox/',
        'src/Support/',
        'src/Theming/',
        'src/WireKit.php',
        'src/WireKitServiceProvider.php',
    ];

    /**
     * Files skipped for token-reference scanning. Use when a file
     * contains illustrative `var(--…)` examples in prose that DO NOT
     * correspond to real runtime references.
     *
     *   _safelist.blade.php → the safelist's PROSE portion (lines
     *                         after the documentation header) describes
     *                         token chains in plain English with example
     *                         shapes like `var(--color-wk-X)`. The
     *                         actual class strings Tailwind extracts
     *                         from this file already reach compiled CSS
     *                         and are validated by Tier 2's compiled-
     *                         CSS scan; this file's `var(--…)` mentions
     *                         are documentation only.
     *
     * Kept as a distinct constant from SKIP_PATH_PREFIXES_FOR_CLASS_EXTRACTION
     * so the two semantics never collide silently.
     */
    public const DEFAULT_SKIP_PATH_PREFIXES_FOR_TOKEN_REFERENCES = [
        'resources/views/_safelist.blade.php',
    ];

    /**
     * Project-relative roots that house additional Laravel-style source trees
     * to scan ON TOP of the package's own resources/views, src/, and
     * resources/js/. The driving use case is the in-repo `sample/` Laravel app
     * whose Blade templates and Livewire page classes feed Tailwind v4's
     * automatic source scan during the sample's Vite build — meaning sample-
     * only classes legitimately land in the compiled CSS that Tier-2 diffs.
     * Without scanning the sample's source, every welcome-page / showcase-
     * page class shows up as "reverse-dead" even though it has a real
     * source emission.
     *
     * For each extra root, the convention is:
     *   {root}/resources/views/**\/*.blade.php  → bladeClasses()
     *   {root}/resources/js/**\/*.js            → jsEmittedClasses()
     *
     * PHP files under `{root}/app` or `{root}/src` are intentionally NOT
     * scanned — developer Laravel apps store domain identifiers, route
     * names, and seeder strings in those files, not Tailwind class
     * literals. The strict class-shape filter cannot tell `'support-team'`
     * (a team-slug seeder string) apart from `'support-team-50'` (a
     * hypothetical Tailwind utility), so scanning developer PHP would
     * round-trip every hyphenated identifier back as a forward-drift
     * false positive. Developer apps that DO emit Tailwind classes from
     * PHP (via `match` arms or `implode(' ', [...])`) carry that emission
     * in the Blade layer for WireKit's Livewire-page convention; they
     * already get scanned via {root}/resources/views.
     *
     * Callers pass roots without a trailing slash. The `is_dir()` check
     * inside each scan method silently ignores extra roots whose subpaths
     * don't exist (e.g. a sample with no resources/js).
     *
     * @param  list<string>  $skipPathPrefixesForClassExtraction
     * @param  list<string>  $skipPathPrefixesForTokenReferences
     * @param  list<string>  $extraSourceRoots
     */
    public function __construct(
        private readonly string $projectRoot,
        private readonly array $skipPathPrefixesForClassExtraction = self::DEFAULT_SKIP_PATH_PREFIXES_FOR_CLASS_EXTRACTION,
        private readonly array $skipPathPrefixesForTokenReferences = self::DEFAULT_SKIP_PATH_PREFIXES_FOR_TOKEN_REFERENCES,
        private readonly array $extraSourceRoots = [],
    ) {}

    /**
     * Project-relative subpaths to scan for each extra root, indexed by
     * inventory layer. Blade + JS only — see the constructor docblock
     * for why developer PHP scanning is intentionally skipped.
     *
     * @return array{blade: list<string>, js: list<string>}
     */
    private function extraScanSubpaths(): array
    {
        return [
            'blade' => array_map(fn ($root) => $root.'/resources/views', $this->extraSourceRoots),
            'js' => array_map(fn ($root) => $root.'/resources/js', $this->extraSourceRoots),
        ];
    }

    /**
     * Class strings emitted from Blade templates under resources/views.
     *
     * Captures two Blade authoring shapes:
     *   1. Static `class="…"` and `class='…'` attributes
     *   2. `@class([…])` directive arrays
     *
     * Alpine `:class="…"` bindings are intentionally NOT scanned —
     * their JS expressions can construct class names dynamically,
     * which the static scanner cannot predict. Such cases must be
     * opt-in safelisted via the `data-drift-skip` attribute.
     *
     * Each captured string is split on whitespace; only candidates that
     * pass the Tailwind v4 class-shape filter are kept. Empty strings,
     * Blade interpolation tokens (`{{ … }}`), and Alpine expressions
     * containing operators (`?`, `&&`, `===`) are rejected.
     *
     * @return Inventory
     */
    public function bladeClasses(): array
    {
        $inventory = [];

        $roots = array_merge(['resources/views'], $this->extraScanSubpaths()['blade']);

        foreach ($roots as $root) {
            foreach ($this->filesUnder($root, ['blade.php'], $this->skipPathPrefixesForClassExtraction) as $file) {
                $contents = (string) file_get_contents($file->getPathname());
                $relative = $this->relativePath($file->getPathname());

                /*
                 * Strip Blade comments before scanning so prose mentions of
                 * class shapes inside `{{-- … --}}` don't surface as candidates.
                 */
                $strippedContents = $this->stripComments($contents);

                $this->harvestAttributeClasses($strippedContents, $relative, $inventory);
                $this->harvestAtClassDirectives($strippedContents, $relative, $inventory);
                $this->harvestMatchArmClassStrings($strippedContents, $relative, $inventory);
                $this->harvestImplodeArrayClassStrings($strippedContents, $relative, $inventory);
                $this->harvestPhpBlockClassStrings($strippedContents, $relative, $inventory);
            }
        }

        return $inventory;
    }

    /**
     * Class strings emitted from PHP source under src/.
     *
     * Scans every quoted string literal in src/**\/*.php files. The
     * candidate filter is stricter than for Blade: a candidate must
     * either contain an arbitrary-value bracket (`[…]`) or be a
     * hyphenated multi-segment class (`text-foo-500`, `hover:bg-red-200`).
     * One-word identifiers like `'primary'` or `'success'` are filtered
     * out — they would otherwise be flagged as drift on every PR.
     *
     * @return Inventory
     */
    public function phpEmittedClasses(): array
    {
        $inventory = [];

        foreach ($this->filesUnder('src', ['php'], $this->skipPathPrefixesForClassExtraction) as $file) {
            $contents = (string) file_get_contents($file->getPathname());
            $relative = $this->relativePath($file->getPathname());

            $this->harvestQuotedStringClasses($contents, $relative, $inventory, strict: true);
        }

        return $inventory;
    }

    /**
     * Class strings emitted from JS factories under resources/js/.
     *
     * Scans every quoted string literal (single, double, or backtick)
     * in resources/js/**\/*.js. Uses the same strict filter as PHP
     * extraction — candidate must contain a `[…]` bracket or be a
     * hyphenated multi-segment class. Template-literal interpolation
     * segments (`${expr}`) are stripped before filtering, so static
     * portions of a template literal are still captured.
     *
     * @return Inventory
     */
    public function jsEmittedClasses(): array
    {
        $inventory = [];

        $roots = array_merge(['resources/js'], $this->extraScanSubpaths()['js']);

        foreach ($roots as $root) {
            foreach ($this->filesUnder($root, ['js'], $this->skipPathPrefixesForClassExtraction) as $file) {
                $contents = (string) file_get_contents($file->getPathname());
                $relative = $this->relativePath($file->getPathname());

                $this->harvestQuotedStringClasses($contents, $relative, $inventory, strict: true);
                $this->harvestTemplateLiteralClasses($contents, $relative, $inventory);
            }
        }

        return $inventory;
    }

    /**
     * Design-token references (var(--…)) discovered in any source layer.
     *
     * Scans Blade templates, PHP source, JS factories, and the compiled
     * stylesheet for `var(--name)` references. The token name (e.g.
     * `--color-wk-danger-fg`) becomes the inventory key.
     *
     * @return Inventory
     */
    public function tokenReferences(): array
    {
        $inventory = [];

        $sources = [
            ['resources/views', ['blade.php']],
            ['src', ['php']],
            ['resources/js', ['js']],
            ['dist', ['css']],
        ];

        foreach ($sources as [$root, $extensions]) {
            foreach ($this->filesUnder($root, $extensions, $this->skipPathPrefixesForTokenReferences) as $file) {
                $contents = (string) file_get_contents($file->getPathname());
                $relative = $this->relativePath($file->getPathname());
                $stripped = $this->stripComments($contents);

                $this->harvestTokenReferences($stripped, $relative, $inventory);
            }
        }

        return $inventory;
    }

    /**
     * Strip block- and line-comments before scanning for token references.
     * Without this, an example like `var(--color-wk-X)` inside a docblock
     * surfaces as a phantom reference and produces a false-positive
     * Tier-1 violation. Conservative — handles `/* … *\/`, `//…`, `<!-- … -->`,
     * and Blade's `{{-- … --}}` shapes; CSS only uses block comments, the
     * line-comment regex is a no-op there.
     */
    private function stripComments(string $contents): string
    {
        $stripped = preg_replace('!/\*.*?\*/!s', '', $contents) ?? $contents;
        /*
         * Line-comment stripper: must NOT match `//` inside URL schemes
         * (`https://`, `http://`, protocol-relative `//cdn.example.com`).
         * The negative lookbehind for `:` excludes `https://`-style URLs;
         * the lookbehind for `/` excludes the second `/` of an already-
         * consumed `//`. Without these guards a `class="… https://x.io …"`
         * attribute on a Blade line gets truncated at the colon, losing
         * every Tailwind class to the right of the URL — the silent bug
         * class that drove sample/resources/views/welcome.blade.php's
         * line-115 anchor (40+ classes) to surface as reverse-dead.
         */
        $stripped = preg_replace('~(?<![:/])//[^\n]*~', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/<!--.*?-->/s', '', $stripped) ?? $stripped;
        $stripped = preg_replace('/\{\{--.*?--\}\}/s', '', $stripped) ?? $stripped;

        return $stripped;
    }

    /**
     * Token names declared in dist/wirekit.css.
     *
     * Captures every `--name: value;` declaration (token definition).
     * Distinct from `tokenReferences()` which captures `var(--name)`
     * usage sites. The intersection of the two answers "is every
     * referenced token actually declared?".
     *
     * @return list<string>
     */
    public function declaredTokens(): array
    {
        $declared = [];

        foreach ($this->filesUnder('dist', ['css'], []) as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            if (preg_match_all('/(--[a-zA-Z0-9_-]+)\s*:/u', $contents, $matches) !== false) {
                foreach ($matches[1] as $name) {
                    $declared[$name] = true;
                }
            }
        }

        return array_keys($declared);
    }

    /**
     * @param  list<string>  $extensions  filename suffixes to include (e.g. ['blade.php', 'php'])
     * @param  list<string>  $skipPaths  project-relative paths to exclude
     * @return iterable<SplFileInfo>
     */
    private function filesUnder(string $relativeRoot, array $extensions, array $skipPaths = []): iterable
    {
        $absoluteRoot = $this->projectRoot.DIRECTORY_SEPARATOR.$relativeRoot;

        if (! is_dir($absoluteRoot)) {
            return;
        }

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($absoluteRoot, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iter as $file) {
            assert($file instanceof SplFileInfo);

            if (! $file->isFile()) {
                continue;
            }

            $relativePath = $this->relativePath($file->getPathname());

            $skip = false;
            foreach ($skipPaths as $prefix) {
                if (str_starts_with($relativePath, $prefix)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) {
                continue;
            }

            foreach ($extensions as $ext) {
                if (str_ends_with($file->getFilename(), '.'.$ext)) {
                    yield $file;
                    break;
                }
            }
        }
    }

    /**
     * Convert an absolute path to a project-relative one with forward slashes.
     */
    private function relativePath(string $absolute): string
    {
        $rel = str_replace($this->projectRoot.DIRECTORY_SEPARATOR, '', $absolute);

        return str_replace(DIRECTORY_SEPARATOR, '/', $rel);
    }

    /**
     * Match static `class="…"` / `class='…'`. Records each whitespace-split
     * candidate that passes the class-shape filter. Alpine `:class=` is
     * deliberately excluded — see bladeClasses() docblock.
     *
     * @param  Inventory  $inventory
     */
    private function harvestAttributeClasses(string $contents, string $file, array &$inventory): void
    {
        $pattern = '/(?<![:\w])class\s*=\s*(["\'])(?P<value>[^"\']*)\1/u';

        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return;
        }

        foreach ($matches['value'] as $match) {
            [$value, $offset] = $match;
            $line = $this->offsetToLine($contents, (int) $offset);

            foreach (preg_split('/\s+/', $value) ?: [] as $candidate) {
                $candidate = trim($candidate);

                if ($candidate === '' || ! $this->looksLikeTailwindClass($candidate)) {
                    continue;
                }

                $inventory[$candidate][] = ['file' => $file, 'line' => $line];
            }
        }
    }

    /**
     * Match `@class([…])` directive arrays. Captures string keys + boolean
     * branches' string values. Numeric keys (i.e. `'foo bar baz'` without
     * a `=>` arrow) are split on whitespace like attribute values.
     *
     * @param  Inventory  $inventory
     */
    private function harvestAtClassDirectives(string $contents, string $file, array &$inventory): void
    {
        $pattern = '/@class\(\s*\[(?P<body>.*?)\]\s*\)/su';

        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return;
        }

        foreach ($matches['body'] as $match) {
            [$body, $offset] = $match;
            $line = $this->offsetToLine($contents, (int) $offset);

            /*
             * Capture string literals AND remember whether each is followed
             * by a `=>` arrow — those are array keys, not class values.
             *
             *   @class([
             *     'flex items-center',          // value (numeric key)
             *     'sm' => 'rounded-md h-8',     // 'sm' is KEY, 'rounded-md h-8' is VALUE
             *     'opacity-50' => $disabled,    // 'opacity-50' is BOTH a key AND a class
             *                                   // (its presence is conditional on $disabled)
             *   ])
             *
             * The third shape is real Tailwind — when the condition is true,
             * the key string IS the emitted class. So we keep keys IF their
             * value (after =>) is a PHP variable / method call (not a string
             * literal). When the value IS a string literal, the key is a
             * dispatcher index and skipped.
             */
            /*
             * Capture string literals + a non-consuming lookahead that
             * detects whether the string is the KEY of an `=>` mapping
             * to ANOTHER string literal (in which case it's a dispatcher
             * key, not an emitted class). Using a lookahead instead of
             * a capturing group keeps the match position right after
             * the closing quote, so the NEXT string in the array still
             * matches cleanly.
             */
            $stringPattern = '/(["\'])(?P<value>(?:\\\\.|(?!\1).)*)\1(?P<keyArrow>(?=\s*=>\s*["\']))?/u';
            preg_match_all($stringPattern, $body, $stringMatches);

            foreach ($stringMatches['value'] as $i => $value) {
                $isMappingKey = ($stringMatches['keyArrow'][$i] ?? '') !== '';

                /*
                 * Skip dispatcher keys whose value is a string literal —
                 * they're size/intent indices like `'sm' => 'rounded-md'`.
                 * Keep keys whose value is a variable (`'opacity-50' =>
                 * $disabled`) — those ARE emitted classes when the
                 * condition is true.
                 */
                if ($isMappingKey) {
                    continue;
                }

                foreach (preg_split('/\s+/', $value) ?: [] as $candidate) {
                    $candidate = trim($candidate);

                    if ($candidate === '' || ! $this->looksLikeTailwindClass($candidate)) {
                        continue;
                    }

                    $inventory[$candidate][] = ['file' => $file, 'line' => $line];
                }
            }
        }
    }

    /**
     * Extract class strings from PHP `match()` arms inside Blade files.
     * Focused on the most common shape where dispatcher keys map to
     * Tailwind class strings:
     *
     *   $position = match ($p) {
     *     'left' => 'inset-y-0 left-0',
     *     'top'  => 'inset-x-0 top-0',
     *   };
     *
     * Captures ONLY the right-hand-side string-literal values (keys
     * and any non-string values like variable references are skipped).
     * Each captured value is whitespace-split and run through the
     * strict class-shape filter; non-Tailwind hyphenated identifiers
     * (component names, slot names, animation presets) are rejected
     * by the looksLikeTailwindClass() filters.
     *
     * Without this scan Tailwind v4's raw-text source scan still reads
     * the @php-block content and generates rules — but the audit's
     * reverse-diff would flag them as "compiled classes without
     * source" because the static analyser couldn't reach them. This
     * focused scan closes that gap for the most common shape (match
     * arms) without re-introducing the false-positive flood that a
     * blanket "scan every quoted string" produced.
     *
     * @param  Inventory  $inventory
     */
    /**
     * Extract class strings from `implode(' ', [...])` calls inside Blade
     * files. The canonical shape for class-string concatenation in WireKit
     * components:
     *
     *   $knobClasses = implode(' ', [
     *       'absolute left-0.5 top-1/2 -translate-y-1/2',
     *       'rounded-full',
     *       'bg-[var(--color-wk-bg-elevated)]',
     *       'shadow-[var(--shadow-wk-sm)]' => $shouldShowShadow,
     *       'transition-transform',
     *   ]);
     *
     * Restricted to the `' '` (single-space) joiner. Other joiners
     * (`', '`, `','`) are used for sentence enumeration / ARIA labels and
     * NOT class concatenation; including them would round-trip ARIA
     * fragments back through the class-shape filter and produce false
     * positives.
     *
     * Each captured string is whitespace-split and run through the
     * strict class-shape filter — non-Tailwind hyphenated identifiers
     * are rejected. Conditional entries (`'class' => $bool`) extract
     * the class side only; the boolean expression is skipped.
     *
     * @param  Inventory  $inventory
     */
    private function harvestImplodeArrayClassStrings(string $contents, string $file, array &$inventory): void
    {
        /*
         * Find every `implode(' ', [` opening. Single OR double quotes
         * around the joiner are accepted because PHP allows both.
         * After the opening bracket we walk char-by-char tracking
         * `[` / `(` depth so the closing `]` is matched correctly
         * even when array elements contain nested calls or arrays.
         */
        $openPattern = '/implode\s*\(\s*(["\'])\s\1\s*,\s*\[/u';

        if (preg_match_all($openPattern, $contents, $opens, PREG_OFFSET_CAPTURE) === false) {
            return;
        }

        $len = strlen($contents);
        foreach ($opens[0] as $openMatch) {
            [$matchText, $matchStart] = $openMatch;
            $bodyStart = (int) $matchStart + strlen($matchText);

            // Walk to the closing `]` of the array (depth = 1 at start).
            $depth = 1;
            $i = $bodyStart;
            while ($i < $len && $depth > 0) {
                $c = $contents[$i];

                if ($c === '"' || $c === "'") {
                    // Skip the entire string literal — its contents
                    // can contain `[` / `]` without affecting depth.
                    $quote = $c;
                    $i++;
                    while ($i < $len) {
                        if ($contents[$i] === '\\') {
                            $i += 2;

                            continue;
                        }
                        if ($contents[$i] === $quote) {
                            break;
                        }
                        $i++;
                    }
                    $i++;

                    continue;
                }

                if ($c === '[' || $c === '(') {
                    $depth++;
                }
                if ($c === ']' || $c === ')') {
                    $depth--;
                }
                $i++;
            }

            if ($depth !== 0) {
                continue;
            }

            $body = substr($contents, $bodyStart, $i - 1 - $bodyStart);

            /*
             * Inside the body, every quoted string is a class-string
             * candidate. We do NOT distinguish between numeric-keyed
             * positional entries and `'class' => $bool` conditional
             * entries — the LEFT side of `=>` (which is the class
             * string) is the one we want regardless. Variable-on-left
             * shapes (`$dynamic => 'foo'`) are skipped because the
             * left side isn't a string literal.
             */
            $stringPattern = '/(?P<quote>["\'])(?P<value>(?:\\\\.|(?!\1).)*)\1/u';
            preg_match_all($stringPattern, $body, $strings, PREG_OFFSET_CAPTURE);

            foreach ($strings['value'] as $stringMatch) {
                [$value, $localOffset] = $stringMatch;
                $line = $this->offsetToLine($contents, $bodyStart + (int) $localOffset);

                foreach (preg_split('/\s+/', $value) ?: [] as $candidate) {
                    $candidate = trim($candidate);

                    if ($candidate === '' || ! $this->looksLikeTailwindClass($candidate, strict: true)) {
                        continue;
                    }

                    $inventory[$candidate][] = ['file' => $file, 'line' => $line];
                }
            }
        }
    }

    /**
     * Extract every quoted string literal that lives inside an `@php … @endphp`
     * block (or `<?php … ?>` block) of a Blade file, run each through the
     * strict class-shape filter, and add the survivors to the inventory.
     *
     * The earlier per-shape harvesters (`harvestAtClassDirectives`,
     * `harvestMatchArmClassStrings`, `harvestImplodeArrayClassStrings`)
     * each cover one specific PHP authoring pattern. They miss the
     * fourth canonical pattern: a plain associative-array literal whose
     * VALUES are class strings — the `$colsMap = ['1' => 'grid-cols-1', …]`
     * lookup table in `resources/views/components/grid.blade.php`, the
     * nested `'sm' => ['translate' => 'peer-checked:translate-x-4']`
     * shape inside a match arm in `toggle.blade.php`, and any future
     * variation (function default arg, `static::CLASS_MAP` constant, …).
     *
     * Tailwind v4's source scanner reads the raw Blade text without
     * understanding PHP scope, so it picks up these strings regardless
     * of whether they're inside an array, match arm, or function call.
     * The drift inventory must mirror that reality — otherwise every
     * `grid-cols-N` / `sm:grid-cols-N` / `lg:grid-cols-N` lookup-table
     * value surfaces as reverse-dead even though it has a real source
     * emission point.
     *
     * The strict filter (same one used by `phpEmittedClasses()`) rejects
     * identifier-style strings (`'sm'`, `'compact'`, `'auto'`) so the
     * dispatcher KEYS of these arrays stay out of the inventory; only
     * the Tailwind-shaped VALUES survive.
     *
     * @param  Inventory  $inventory
     */
    private function harvestPhpBlockClassStrings(string $contents, string $file, array &$inventory): void
    {
        /*
         * Two PHP-context delimiters in Blade:
         *   @php … @endphp  (Blade-native)
         *   <?php … ?>      (raw PHP)
         *
         * The match is non-greedy across multi-line bodies; nested
         * delimiters don't appear in practice.
         */
        $patterns = [
            '/@php\b(?P<body>.*?)@endphp\b/su',
            '/<\?php\b(?P<body>.*?)\?>/su',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
                continue;
            }

            foreach ($matches['body'] as $bodyMatch) {
                [$body, $bodyOffset] = $bodyMatch;

                $stringPattern = '/(?P<quote>["\'])(?P<value>(?:\\\\.|(?!(?P=quote)).)*)(?P=quote)/u';
                preg_match_all($stringPattern, $body, $strings, PREG_OFFSET_CAPTURE);

                foreach ($strings['value'] as $stringMatch) {
                    [$value, $localOffset] = $stringMatch;
                    $line = $this->offsetToLine($contents, (int) $bodyOffset + (int) $localOffset);

                    /*
                     * Skip strings containing inline HTML / SVG markup —
                     * they're @php-block-side string concatenations like
                     *   $extLink = '<svg class="..."><path d="M13.5 6H..."/></svg>';
                     * Whitespace-splitting an SVG path data attribute
                     * surfaces fragments like `0h-5.25M21` and `0zm-7-4a1`
                     * that pass the digit-leading Tailwind shape filter
                     * but are NOT real class candidates. Any class= attr
                     * inside the markup is independently caught by the
                     * file-wide harvestAttributeClasses() scan, so this
                     * skip costs no real coverage.
                     */
                    if (preg_match('/<[a-z]/i', $value) === 1) {
                        continue;
                    }

                    foreach (preg_split('/\s+/', $value) ?: [] as $candidate) {
                        $candidate = trim($candidate);

                        if ($candidate === '' || ! $this->looksLikeTailwindClass($candidate, strict: true)) {
                            continue;
                        }

                        /*
                         * Extra-strict pass for @php-block context: require
                         * at least one Tailwind-specific shape marker — a
                         * digit, an arbitrary-value bracket, or a colon
                         * (variant or arbitrary-value type prefix). This
                         * filters out the long tail of component-slug
                         * strings (`reading-progress`, `tree-view`,
                         * `aria-label`, `data-wk-striped`, `lower-roman`,
                         * `top-left`) that live inside @php blocks for
                         * runtime dispatch but are NOT Tailwind utilities.
                         *
                         * The dispatcher KEYS of class lookup tables —
                         * `'sm'`, `'compact'`, `'auto'` — are already
                         * dropped by the strict class-shape filter above
                         * (no hyphen, no bracket); this extra pass catches
                         * the hyphenated identifier-style false positives
                         * that the strict filter cannot tell from real
                         * Tailwind classes by shape alone.
                         *
                         * Real Tailwind class strings emitted from @php
                         * blocks always carry one of these markers:
                         *   - digit:  `grid-cols-12`, `gap-4`, `text-2xl`
                         *   - bracket: `bg-[var(--…)]`, `[&_h1]:mt-0`
                         *   - colon:   `peer-checked:translate-x-4`, `sm:flex`
                         *
                         * Pure word-only Tailwind classes (`bg-white`,
                         * `text-white`, `flex-row`, `items-center`) very
                         * rarely live in lookup-table values; when they do,
                         * the same class virtually always appears in a
                         * `class="…"` attribute elsewhere in the same
                         * Blade template and is caught by
                         * `harvestAttributeClasses`. The trade-off favours
                         * zero false positives in @php-block context.
                         */
                        $hasShapeMarker = preg_match('/\d|\[|:/', $candidate) === 1;
                        if (! $hasShapeMarker) {
                            continue;
                        }

                        $inventory[$candidate][] = ['file' => $file, 'line' => $line];
                    }
                }
            }
        }
    }

    private function harvestMatchArmClassStrings(string $contents, string $file, array &$inventory): void
    {
        /*
         * Two-pass approach:
         *   1. Find every `match (…) { … }` body (non-greedy across
         *      multi-line bodies; nested matches are rare in Blade
         *      and not worth the complexity).
         *   2. Inside each body, capture every `=> 'STRING'` arm
         *      (single OR double quoted). Skip arms whose right-hand-
         *      side is a variable / method call / non-string-literal.
         */
        if (preg_match_all('/match\s*\([^)]+\)\s*\{(?P<body>.*?)\}/su', $contents, $matchBodies, PREG_OFFSET_CAPTURE) === false) {
            return;
        }

        foreach ($matchBodies['body'] as $bodyMatch) {
            [$body, $bodyOffset] = $bodyMatch;

            /*
             * Only capture arrows at the TOP level of the match body
             * — i.e. NOT inside nested arrays / function calls. A
             * shape like
             *
             *   'compact' => [
             *     'spineExpand' => 'always-md',  // inner arrow, skip
             *   ],
             *
             * has TWO arrows; only the outer one points at the arm
             * value. The inner arrow's right-side ('always-md') is a
             * config-preset name, not a class string.
             *
             * Walk the body char-by-char tracking `[` / `(` depth.
             * For each `=>` at depth 0, peek the next non-whitespace
             * char — if it's `'` or `"`, capture the string-literal
             * value; otherwise the arm's value is an array / variable
             * / function call and we skip it.
             */
            $len = strlen($body);
            $depth = 0;
            for ($i = 0; $i < $len; $i++) {
                $c = $body[$i];

                if ($c === '[' || $c === '(') {
                    $depth++;

                    continue;
                }
                if ($c === ']' || $c === ')') {
                    $depth--;

                    continue;
                }

                if ($depth !== 0 || $c !== '=' || ($body[$i + 1] ?? '') !== '>') {
                    continue;
                }

                // Skip the `=>` and any whitespace
                $j = $i + 2;
                while ($j < $len && ($body[$j] === ' ' || $body[$j] === "\t" || $body[$j] === "\n" || $body[$j] === "\r")) {
                    $j++;
                }

                if ($j >= $len) {
                    break;
                }

                $quote = $body[$j];
                if ($quote !== '"' && $quote !== "'") {
                    // Arm value is not a string literal — skip
                    continue;
                }

                // Walk to the closing quote, honouring backslash escapes
                $valueStart = $j + 1;
                $k = $valueStart;
                while ($k < $len) {
                    if ($body[$k] === '\\') {
                        $k += 2;

                        continue;
                    }
                    if ($body[$k] === $quote) {
                        break;
                    }
                    $k++;
                }
                if ($k >= $len) {
                    break;
                }

                $value = substr($body, $valueStart, $k - $valueStart);
                $line = $this->offsetToLine($contents, (int) $bodyOffset + $valueStart);

                /*
                 * Skip match-arm string-literals containing inline HTML/SVG
                 * markup (e.g. the per-variant default-icon paths in
                 * alert.blade.php). Whitespace-splitting an SVG `d="…"`
                 * attribute extracts fragments like `0v-3.5A.75.75` that
                 * pass the digit-leading Tailwind shape filter but are
                 * NOT classes. The classes inside any class= attribute of
                 * the markup are caught by harvestAttributeClasses().
                 */
                if (preg_match('/<[a-z]/i', $value) === 1) {
                    $i = $k;

                    continue;
                }

                foreach (preg_split('/\s+/', $value) ?: [] as $candidate) {
                    $candidate = trim($candidate);

                    if ($candidate === '' || ! $this->looksLikeTailwindClass($candidate, strict: true)) {
                        continue;
                    }

                    $inventory[$candidate][] = ['file' => $file, 'line' => $line];
                }

                $i = $k;
            }
        }
    }

    /**
     * Match every `var(--name)` (with optional fallback) in arbitrary content.
     *
     * @param  Inventory  $inventory
     */
    private function harvestTokenReferences(string $contents, string $file, array &$inventory): void
    {
        $pattern = '/var\(\s*(?P<name>--[a-zA-Z0-9_-]+)/u';

        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return;
        }

        foreach ($matches['name'] as $match) {
            [$name, $offset] = $match;
            $line = $this->offsetToLine($contents, (int) $offset);

            $inventory[$name][] = ['file' => $file, 'line' => $line];
        }
    }

    /**
     * Match every double- or single-quoted string literal in the contents.
     * Each captured string is whitespace-split into candidates and run
     * through the (optionally strict) class-shape filter.
     *
     * @param  Inventory  $inventory
     */
    private function harvestQuotedStringClasses(
        string $contents,
        string $file,
        array &$inventory,
        bool $strict = false,
    ): void {
        $pattern = '/(["\'])(?P<value>(?:\\\\.|(?!\1).)*)\1/u';

        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return;
        }

        foreach ($matches['value'] as $match) {
            [$value, $offset] = $match;
            $line = $this->offsetToLine($contents, (int) $offset);

            foreach (preg_split('/\s+/', $value) ?: [] as $candidate) {
                $candidate = trim($candidate);

                if ($candidate === '' || ! $this->looksLikeTailwindClass($candidate, strict: $strict)) {
                    continue;
                }

                $inventory[$candidate][] = ['file' => $file, 'line' => $line];
            }
        }
    }

    /**
     * Match every backtick-delimited template literal. Interpolation
     * segments (`${expr}`) are blanked out so the static text around
     * them is still captured — `bg-${color}-500` becomes `bg- -500`,
     * which the filter then rejects, but `flex gap-2 ${dynamic}` keeps
     * `flex` and `gap-2` intact.
     *
     * @param  Inventory  $inventory
     */
    private function harvestTemplateLiteralClasses(string $contents, string $file, array &$inventory): void
    {
        $pattern = '/`(?P<value>(?:\\\\.|[^`])*)`/u';

        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return;
        }

        foreach ($matches['value'] as $match) {
            [$value, $offset] = $match;
            $line = $this->offsetToLine($contents, (int) $offset);
            $cleaned = preg_replace('/\$\{[^}]*\}/u', ' ', $value) ?? '';

            foreach (preg_split('/\s+/', $cleaned) ?: [] as $candidate) {
                $candidate = trim($candidate);

                if ($candidate === '' || ! $this->looksLikeTailwindClass($candidate, strict: true)) {
                    continue;
                }

                $inventory[$candidate][] = ['file' => $file, 'line' => $line];
            }
        }
    }

    /**
     * Lightweight class-shape filter that mirrors Tailwind v4's heuristic:
     * candidates start with [a-z], are at least 2 chars long, and contain
     * only Tailwind-legal characters. Operators, interpolation, and prose
     * are rejected.
     *
     * Strict mode (used for PHP/JS source) additionally requires the
     * candidate to either contain a `[…]` arbitrary-value bracket OR be
     * a hyphenated multi-segment class. This rejects identifier-style
     * strings like 'primary', 'neutral', 'success' which would otherwise
     * be flagged as drift on every PR.
     */
    private function looksLikeTailwindClass(string $candidate, bool $strict = false): bool
    {
        if (strlen($candidate) < 2) {
            return false;
        }

        if (str_contains($candidate, '{{') || str_contains($candidate, '}}')) {
            return false;
        }

        /*
         * Reject Alpine / Blade expression strings (operators in template-
         * binding shapes like `disabled && readonly` or `state === 'open'`).
         *
         * Single `&` is INTENTIONALLY excluded from the rejection set —
         * Tailwind v4 uses `&` as the parent-selector marker inside
         * arbitrary-variant brackets like `[&_h1]:mt-0` or `[&::-webkit-…]`.
         * The compound `&&` (logical AND) is still rejected via the
         * trailing alternation. Same logic applies to `|` vs `||`: bitwise
         * `|` doesn't appear in Tailwind syntax in practice, but the
         * single-char form is a real false-positive risk on a class-string
         * that ends up containing `pipe-separated-options` data — keep it
         * on the strict reject list and rely on `||` for the OR case.
         *
         * The `[&...]:..." silent-failure was the root cause of all the
         * `[&_h1]:mt-0`, `[&_blockquote]:`, `[&_th]:text-left` strings in
         * `resources/views/components/prose.blade.php` surfacing as
         * reverse-dead Tier-2 entries.
         */
        if (preg_match('/[?|=!<>;]|===|&&|\|\|/', $candidate) === 1) {
            return false;
        }

        /*
         * Tailwind v4 class shape: starts with a lowercase letter (utility
         * prefix like `text-`, `bg-`) OR with `[` (raw arbitrary property
         * like `[appearance:textfield]` or nested-selector variant like
         * `[&_a]:text-foo`, `[&::-webkit-outer-spin-button]:appearance-none`)
         * OR with a single digit IMMEDIATELY followed by a lowercase letter
         * (the `2xl:` breakpoint variant is the canonical case — every
         * `2xl:grid-cols-N` and `2xl:flex` shape begins with `2`).
         * Body chars are alphanumeric + the structural delimiters Tailwind
         * uses inside arbitrary values + `&` (the v4 nesting marker).
         */
        if (preg_match('/^(?:[a-z]|\[|\d[a-z])[a-zA-Z0-9:_\-\[\]\(\)\/.,#%*&]+$/', $candidate) !== 1) {
            return false;
        }

        /*
         * Reject WireKit's own custom-CSS class prefixes — these are
         * defined in dist/wirekit.css (e.g. `.wk-reading-progress__fill`,
         * `.wk-scrollbar`) and shipped as static class strings, NOT as
         * Tailwind utilities. The compiled-CSS-diff would correctly note
         * they're not in the developer's Tailwind output, but that's
         * because they live in dist/wirekit.css, not because they drifted.
         */
        if (str_starts_with($candidate, 'wk-') || str_starts_with($candidate, 'wirekit-')) {
            return false;
        }

        /*
         * Trailing CSS-grammar markers indicate the candidate is a CSS
         * property name (`background-image:`), a comma-separated value
         * fragment (`ui-monospace,`), a function-call fragment
         * (`linear-gradient(to`), or a statement terminator. Tailwind
         * utility names never end with these characters.
         */
        if (preg_match('/[:,;(\s]$/', $candidate) === 1) {
            return false;
        }

        /*
         * Blade sub-component reference shape — `command-palette.empty`,
         * `alert-dialog.actions`. The `<x-wirekit::name.sub>` tag has
         * the dotted form passed as a string in some PHP-side helpers.
         * Real Tailwind utilities don't use this shape outside arbitrary
         * value brackets.
         */
        if (preg_match('/^[a-z][\w-]*\.[a-z]/', $candidate) === 1
            && ! str_contains($candidate, '[')) {
            return false;
        }

        /*
         * Wirekit custom-event prefix — `wirekit:foo:bar` is an event
         * channel identifier, not a Tailwind variant.
         */
        if (str_starts_with($candidate, 'wirekit:')) {
            return false;
        }

        /*
         * Reject one-word identifiers (no hyphen, colon, or bracket) that
         * aren't on the KNOWN_SINGLE_WORD_CLASSES list. Tailwind utilities
         * use composite shapes with at least one structural delimiter;
         * single bare words (`icon`, `disabled`, `primary`) are domain
         * identifiers — most often PHP array keys captured from the
         * `$variantColors['icon']` shape inside @class([…]) bodies.
         */
        $hasTailwindStructuralChar = preg_match('/[-:\[]/', $candidate) === 1;
        if (! $hasTailwindStructuralChar && ! in_array($candidate, self::KNOWN_SINGLE_WORD_CLASSES, true)) {
            return false;
        }

        if ($strict) {
            if (in_array($candidate, self::KNOWN_SINGLE_WORD_CLASSES, true)) {
                return true;
            }

            $hasArbitraryBracket = str_contains($candidate, '[') && str_contains($candidate, ']');
            // Allow optional digit prefix on the first segment so digit-leading
            // breakpoint variants like `2xl:grid-cols-12` pass strict mode.
            $isHyphenated = preg_match('/^\d?[a-z][a-z0-9]*(?::[a-z0-9-]+)*-[a-z0-9]/', $candidate) === 1;

            if (! $hasArbitraryBracket && ! $isHyphenated) {
                return false;
            }
        }

        return true;
    }

    /**
     * Convert a byte-offset into a 1-indexed line number.
     */
    private function offsetToLine(string $contents, int $offset): int
    {
        return substr_count($contents, "\n", 0, $offset) + 1;
    }
}
