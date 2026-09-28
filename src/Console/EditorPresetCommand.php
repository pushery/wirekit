<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pushery\WireKit\Support\SuggestSimilar;

/**
 * Scaffold the `window.wirekitEditor(config)` factory snippet that
 * `<x-wirekit::editor>` calls at Alpine init, pre-wired for a chosen
 * toolbar preset. (The legacy `window.tiptapEditor` name still works as a
 * deprecated alias — the emitted snippet uses the canonical name.)
 *
 * The editor ships ZERO Tiptap code — Tiptap is the developer's peer
 * dependency, exposed through this factory. Writing the factory by hand is
 * the one fiddly setup step (forwarding every `config.*` callback, the
 * security-correct Link config, the right extension set per preset), so this
 * command emits a ready-to-paste, preset-specific version straight from the
 * same contract documented on the editor docs page.
 *
 * Usage:
 *   php artisan wirekit:editor-preset            # prints the `basic` factory
 *   php artisan wirekit:editor-preset full       # prints the `full` factory
 *   php artisan wirekit:editor-preset full --write=resources/js/editor.js
 *   php artisan wirekit:editor-preset --write=resources/js/editor.js --force
 *
 * The `basic` preset matches `toolbar="basic"` (bold / italic / strike / link
 * / lists); `full` matches `toolbar="full"` (adds underline, headings, quote,
 * code block, history).
 *
 * The snippet is written for Tiptap 3, which an unversioned `npm install` gets.
 * Its StarterKit carries Link and Underline itself, so Link is configured through
 * StarterKit rather than registered beside it: a second Link is the extension
 * twice, with a warning and two click handlers, and the one StarterKit adds
 * opens a link on click inside the editor. `basic` switches Underline off, as its
 * toolbar has no button for it. The snippet says what differs on Tiptap 2.
 */
class EditorPresetCommand extends Command
{
    protected $signature = 'wirekit:editor-preset
        {preset=basic : Toolbar preset to scaffold the factory for (basic or full)}
        {--write= : Write the snippet to this file instead of printing it to stdout}
        {--force : Overwrite the --write target if it already exists}';

    protected $description = 'Scaffold the window.wirekitEditor() factory snippet for the editor component';

    /**
     * The presets this command can scaffold. Mirrors the toolbar presets that
     * carry a preset command vocabulary in editor.blade.php (`basic` / `full`);
     * `custom` and `false` need no preset factory (the developer composes the
     * toolbar / hides it). Keep this list in lockstep with that template.
     *
     * @var list<string>
     */
    private const PRESETS = ['basic', 'full'];

    public function handle(): int
    {
        // Normalize so `Basic` / `FULL` work; the preset names are ASCII-lower.
        $preset = strtolower((string) $this->argument('preset'));

        if (! in_array($preset, self::PRESETS, true)) {
            $this->error("Unknown preset '{$preset}'.");
            $this->line('  Valid presets: '.implode(', ', self::PRESETS).'.');

            // The same hint every other enumerating rejection in the catalog prints.
            $hint = SuggestSimilar::format(SuggestSimilar::byLevenshtein($preset, self::PRESETS));
            if ($hint !== null) {
                $this->line('  '.$hint);
            }

            // Invalid input is still FAILURE (exit 1), never INVALID (exit 2) —
            // strict Laravel/Artisan convention (see CliUniformityAuditTest).
            return self::FAILURE;
        }

        $snippet = $this->buildSnippet($preset);

        $writeTarget = $this->option('write');
        if (is_string($writeTarget) && $writeTarget !== '') {
            return $this->writeToFile($writeTarget, $snippet, $preset);
        }

        // Default: print to stdout for copy-paste into the developer's app.js.
        $this->line($snippet);
        $this->newLine();
        $this->components->info("Scaffolded the '{$preset}' editor factory. Paste it into your app.js (or use --write).");
        $this->printLoadHint();

        return self::SUCCESS;
    }

    /**
     * Build the npm-install line + the JS factory body for a preset.
     */
    private function buildSnippet(string $preset): string
    {
        $isFull = $preset === 'full';

        // npm packages: in Tiptap 3 StarterKit covers bold/italic/strike/underline/link/lists/
        // headings/quote/code-block/history; Placeholder lives in @tiptap/extensions.
        $packages = ['@tiptap/core', '@tiptap/starter-kit', '@tiptap/extensions'];

        $imports = [
            "import { Editor } from '@tiptap/core';",
            "import StarterKit from '@tiptap/starter-kit';",
            "import { Placeholder } from '@tiptap/extensions';",
        ];

        // Link is configured INSIDE StarterKit: protocols restricted (blocks javascript:
        // URLs) and no open-on-click, the security-correct shape the editor docs require.
        // A Link registered beside it would be a second one, and StarterKit's own opens
        // links on click. `basic` has no underline button, so it leaves Underline out.
        $starterKit = $isFull
            ? "StarterKit.configure({ link: { protocols: ['http', 'https', 'mailto'], openOnClick: false } }),"
            : "StarterKit.configure({ underline: false, link: { protocols: ['http', 'https', 'mailto'], openOnClick: false } }),";
        $extensions = [
            $starterKit,
            "Placeholder.configure({ placeholder: config.placeholder ?? 'Write something...' }),",
        ];

        $importBlock = implode("\n", $imports);
        $extensionBlock = implode("\n        ", $extensions);

        return <<<JS
        // WireKit editor factory ({$preset} preset) — paste into your app.js.
        // 1. Install the Tiptap peer dependencies first (Tiptap 3):
        //    npm install {$this->joinPackages($packages)}
        //    On Tiptap 2, StarterKit carries neither Link nor Underline: install and list
        //    @tiptap/extension-link (and @tiptap/extension-underline for `full`) beside it,
        //    and import Placeholder from @tiptap/extension-placeholder.
        {$importBlock}

        // 2. Expose the factory WireKit calls at Alpine init(). It receives
        //    element / content / editable / editorProps / lifecycle callbacks
        //    and MUST forward editorProps verbatim (carries role + styling).
        //    (The legacy name window.tiptapEditor still works as a deprecated alias.)
        window.wirekitEditor = (config) => new Editor({
            element: config.element,
            content: config.content,
            editable: config.editable,
            editorProps: config.editorProps,
            onCreate: config.onCreate,
            onUpdate: config.onUpdate,
            onSelectionUpdate: config.onSelectionUpdate,
            onTransaction: config.onTransaction,
            // 3. YOU own the extension set. config.extensions is an optional
            //    list of string name-hints; never spread it raw into Tiptap.
            extensions: [
                {$extensionBlock}
            ],
        });
        JS;
    }

    /**
     * Join npm package names for the install line.
     *
     * @param  list<string>  $packages
     */
    private function joinPackages(array $packages): string
    {
        return implode(' ', $packages);
    }

    /**
     * Write the snippet to a file, honoring --force.
     */
    private function writeToFile(string $relativePath, string $snippet, string $preset): int
    {
        $targetPath = $this->resolvePath($relativePath);

        if (file_exists($targetPath) && ! $this->option('force')) {
            $this->error("File already exists at {$targetPath}.");
            $this->line('  Re-run with --force to overwrite.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($targetPath));

        // Trim the heredoc's leading indentation only at the block level — the
        // snippet body is already left-aligned for a file, so write it as-is
        // with a trailing newline.
        if (File::put($targetPath, $snippet."\n") === false) {
            $this->error("Could not write to {$targetPath}.");

            return self::FAILURE;
        }

        $this->components->info("Wrote the '{$preset}' editor factory → {$targetPath}");
        $this->printLoadHint();

        return self::SUCCESS;
    }

    /**
     * Resolve a write target relative to the project root (absolute paths pass
     * through unchanged).
     */
    private function resolvePath(string $path): string
    {
        // An absolute path (POSIX `/...` or Windows `C:\...`) is used verbatim;
        // anything else is taken relative to the application base path.
        $isAbsolute = str_starts_with($path, '/') || (bool) preg_match('/^[A-Za-z]:[\\\\\/]/', $path);

        return $isAbsolute ? $path : base_path($path);
    }

    /**
     * Remind the developer which JS bundle registers the wirekitEditor glue.
     */
    private function printLoadHint(): void
    {
        $this->line('  Load the editor glue via wirekit.js / wirekit-alpine.js,');
        $this->line('  or wirekit-tiptap.js alongside wirekit.core.js.');
    }
}
