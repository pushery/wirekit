<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Scaffold the `window.tiptapEditor(config)` factory snippet that
 * `<x-wirekit::editor>` calls at Alpine init, pre-wired for a chosen
 * toolbar preset.
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
 * code block, history). `full` additionally needs `@tiptap/extension-underline`,
 * which StarterKit does not bundle — the emitted npm line includes it.
 */
class EditorPresetCommand extends Command
{
    protected $signature = 'wirekit:editor-preset
        {preset=basic : Toolbar preset to scaffold the factory for (basic or full)}
        {--write= : Write the snippet to this file instead of printing it to stdout}
        {--force : Overwrite the --write target if it already exists}';

    protected $description = 'Scaffold the window.tiptapEditor() factory snippet for the editor component';

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

        // npm packages: StarterKit covers bold/italic/strike/lists/headings/
        // quote/code-block/history; Link + Placeholder are always wired; the
        // `full` preset's underline button needs the extra underline extension.
        $packages = ['@tiptap/core', '@tiptap/starter-kit', '@tiptap/extension-link', '@tiptap/extension-placeholder'];
        if ($isFull) {
            $packages[] = '@tiptap/extension-underline';
        }

        $imports = [
            "import { Editor } from '@tiptap/core';",
            "import StarterKit from '@tiptap/starter-kit';",
            "import Link from '@tiptap/extension-link';",
            "import Placeholder from '@tiptap/extension-placeholder';",
        ];
        if ($isFull) {
            $imports[] = "import Underline from '@tiptap/extension-underline';";
        }

        // Extension list inside the factory. The Link config restricts protocols
        // (blocks javascript: URLs) and disables open-on-click — the same
        // security-correct shape the editor docs require.
        $extensions = [
            'StarterKit,',
        ];
        if ($isFull) {
            $extensions[] = 'Underline,';
        }
        $extensions[] = "Link.configure({ protocols: ['http', 'https', 'mailto'], openOnClick: false }),";
        $extensions[] = "Placeholder.configure({ placeholder: config.placeholder ?? 'Write something...' }),";

        $importBlock = implode("\n", $imports);
        $extensionBlock = implode("\n        ", $extensions);

        return <<<JS
        // WireKit editor factory ({$preset} preset) — paste into your app.js.
        // 1. Install the Tiptap peer dependencies first:
        //    npm install {$this->joinPackages($packages)}
        {$importBlock}

        // 2. Expose the factory WireKit calls at Alpine init(). It receives
        //    element / content / editable / editorProps / lifecycle callbacks
        //    and MUST forward editorProps verbatim (carries role + styling).
        window.tiptapEditor = (config) => new Editor({
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
