<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Finds the files that close a page's `<head>` and `<body>`, starting from the layout it names.
 *
 * A layout is not always the document. The Livewire starter kit's `layouts/app.blade.php` is
 * five lines that render `<x-layouts::app.sidebar>`, and it is THAT file which carries the
 * `<html>`, the `<head>` and the `</body>`. An installer that edits the layout finds nowhere to
 * put a stylesheet; a check that reads it answers for a file no page's head lives in.
 *
 * So a layout is followed — the component it renders, a view it extends or includes — until
 * the files that close `<head>` and `<body>` turn up. Those are what a directive has to reach.
 *
 * ⚠️ WHAT THIS CANNOT SEE, so that silence is never read as a verdict: a directive pushed
 * through a stack, a section a child view fills, a view composer, a class component whose view
 * is chosen in PHP. An empty answer means "not found from here", never "absent".
 */
final class LayoutShells
{
    /** The app layout, in the order `wirekit:install` probes it. */
    public const APP_LAYOUTS = [
        'components/layouts/app.blade.php',
        'layouts/app.blade.php',
        'components/layout.blade.php',
    ];

    /**
     * The auth layout, in both places the Livewire starter kit has kept it: `layouts/` since
     * Livewire 4, `components/layouts/` before.
     */
    public const AUTH_LAYOUTS = [
        'layouts/auth.blade.php',
        'components/layouts/auth.blade.php',
    ];

    /** The starter kit needs one step. Four leaves room without inviting a runaway walk. */
    private const MAX_DEPTH = 4;

    /**
     * @param  array<string, string>  $namespaces  a `prefix::` and the directory it resolves to
     */
    public function __construct(
        private readonly string $viewsPath,
        private readonly array $namespaces = [],
    ) {}

    /**
     * The running application's views, with the anonymous-component prefixes Blade knows —
     * which is where Livewire 4 registers `layouts::`.
     */
    public static function forApplication(): self
    {
        $namespaces = [];

        foreach (app('blade.compiler')->getAnonymousComponentPaths() as $registered) {
            if (is_string($registered['prefix'] ?? null) && is_string($registered['path'] ?? null)) {
                $namespaces[$registered['prefix']] = $registered['path'];
            }
        }

        return new self(resource_path('views'), $namespaces);
    }

    /** The first app layout that exists, or null. */
    public function appLayout(): ?string
    {
        return $this->firstExisting(self::APP_LAYOUTS);
    }

    /** The first auth layout that exists, or null. */
    public function authLayout(): ?string
    {
        return $this->firstExisting(self::AUTH_LAYOUTS);
    }

    /**
     * The files that close `<head>` and `<body>` for the documents `$layout` renders.
     *
     * A layout that closes both is its own answer, and nothing inside it is followed. Otherwise
     * the walk follows what it renders until it reaches the files that do.
     *
     * With `$alternatives`, a shell the walk had to FOLLOW to also brings the other shells in
     * its directory. The starter kit keeps `sidebar` and `header` side by side and switches
     * between them with a one-word edit — wire only the current one, and the other is broken
     * the day somebody switches. Only inside a `layouts` directory, because next to
     * `views/welcome.blade.php` the other documents in the folder are pages, not alternatives.
     *
     * @return array{head: list<string>, body: list<string>}
     */
    public function shellsOf(string $layout, bool $alternatives = false): array
    {
        $found = ['head' => [], 'body' => []];

        $this->walk($layout, static function (string $file, string $live) use (&$found): bool {
            $closesHead = str_contains($live, '</head>');
            $closesBody = str_contains($live, '</body>');

            if ($closesHead) {
                $found['head'][] = $file;
            }

            if ($closesBody) {
                $found['body'][] = $file;
            }

            // A file that closes both IS the document: what it renders inside is page content.
            return ! ($closesHead && $closesBody);
        });

        if ($alternatives) {
            foreach (['head' => '</head>', 'body' => '</body>'] as $part => $closing) {
                foreach ($found[$part] as $shell) {
                    if ($shell === $layout || ! $this->insideALayoutsDirectory($shell)) {
                        continue;
                    }

                    foreach (glob(dirname($shell).'/*.blade.php') ?: [] as $sibling) {
                        if (str_contains(BladeParser::liveText((string) file_get_contents($sibling)), $closing)) {
                            $found[$part][] = $sibling;
                        }
                    }
                }
            }
        }

        return [
            'head' => array_values(array_unique($found['head'])),
            'body' => array_values(array_unique($found['body'])),
        ];
    }

    /**
     * Does `$file` — or anything it renders or includes — use `@$directive`?
     *
     * The starter kit's shells hand their whole `<head>` to `partials/head`, so a stylesheet a
     * developer put there IS in every head, and a check of the shell alone would add a second.
     */
    public function carries(string $file, string $directive): bool
    {
        $carries = false;
        $pattern = '/@'.preg_quote($directive, '/').'\b/';

        $this->walk($file, static function (string $reached, string $live) use (&$carries, $pattern): bool {
            $carries = $carries || preg_match($pattern, $live) === 1;

            return ! $carries;
        });

        return $carries;
    }

    /**
     * Breadth-first over what a file renders, handing each file's live text to `$visit`, which
     * answers whether that file's own references are worth following.
     *
     * @param  callable(string, string): bool  $visit
     */
    private function walk(string $start, callable $visit): void
    {
        $queue = [[$start, 0]];
        $seen = [];
        $next = 0;

        // A cursor rather than array_shift(): the queue grows while it is being read.
        while (isset($queue[$next])) {
            [$file, $depth] = $queue[$next];
            $next++;

            if (isset($seen[$file]) || ! is_file($file)) {
                continue;
            }

            $seen[$file] = true;
            $live = BladeParser::liveText((string) file_get_contents($file));

            if (! $visit($file, $live) || $depth >= self::MAX_DEPTH) {
                continue;
            }

            foreach ($this->referencesIn($live) as $reference) {
                $queue[] = [$reference, $depth + 1];
            }
        }
    }

    /**
     * The files a template hands its output to: the components it renders and the views it
     * extends or includes. A name that resolves to no file here — a package's `wirekit::`, a
     * class component — is skipped rather than guessed at.
     *
     * @return list<string>
     */
    private function referencesIn(string $live): array
    {
        $files = [];

        foreach (BladeParser::tagsFromSource($live) as $tag) {
            if ($tag['isComponent'] && preg_match('/^x[-:](.+)$/i', $tag['name'], $name) === 1) {
                $files[] = $this->resolve($name[1], asComponent: true);
            }
        }

        preg_match_all('/@(?:extends|include|includeIf)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $live, $views);

        foreach ($views[1] as $view) {
            $files[] = $this->resolve($view, asComponent: false);
        }

        return array_values(array_filter($files, static fn (?string $file): bool => $file !== null));
    }

    /**
     * `layouts::app.sidebar` is `app/sidebar` under wherever `layouts::` points; as a component,
     * `layouts.app.sidebar` is `components/layouts/app/sidebar`; as a view, `partials.head` is
     * `partials/head`. A prefix nobody registered falls back to the directory of the same name,
     * which is where Livewire 4 puts `layouts::` by default — and Livewire only registers the
     * prefix when that directory already existed as the application booted.
     */
    private function resolve(string $name, bool $asComponent): ?string
    {
        [$prefix, $path] = str_contains($name, '::') ? explode('::', $name, 2) : [null, $name];

        $root = match (true) {
            $prefix !== null => $this->namespaces[$prefix] ?? $this->viewsPath.'/'.$prefix,
            $asComponent => $this->viewsPath.'/components',
            default => $this->viewsPath,
        };

        $relative = str_replace('.', '/', $path);

        foreach ([$relative.'.blade.php', $relative.'/index.blade.php', $relative.'/'.basename($relative).'.blade.php'] as $candidate) {
            if (is_file($root.'/'.$candidate)) {
                return $root.'/'.$candidate;
            }
        }

        return null;
    }

    private function insideALayoutsDirectory(string $file): bool
    {
        $relative = str_replace('\\', '/', substr(dirname($file), strlen($this->viewsPath)));

        return in_array('layouts', explode('/', $relative), true);
    }

    /**
     * @param  list<string>  $candidates
     */
    private function firstExisting(array $candidates): ?string
    {
        foreach ($candidates as $relative) {
            if (is_file($this->viewsPath.'/'.$relative)) {
                return $this->viewsPath.'/'.$relative;
            }
        }

        return null;
    }
}
