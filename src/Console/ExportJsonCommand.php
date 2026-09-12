<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Support\ComponentManifest;

/**
 * components.json export.
 *
 * Emits a machine-readable manifest of every WireKit component, including
 * category, description, props (parsed from @props([...]) blocks in the
 * Blade file), and slot names (parsed from $slot / @isset($slotName)
 * references). Designed to be consumed by the docs site's
 * /components.json endpoint, AI tooling, and design-system audits.
 *
 * The docs site's build wrapper calls us twice: once with --pretty, once without. We support both by accepting --pretty
 * and pretty-printing whenever it's set; without the flag we emit
 * minified JSON. Either way: stdout-only, exit 0 on success, JSON
 * decodable.
 *
 * --public restricts the manifest to components whose dedicated docs
 * page is publicly rendered — the variant docs.wirekit.app serves at
 * /components.json. A component whose page is not publicly rendered is
 * omitted entirely. Components with NO dedicated page (the sub-component
 * pattern, documented on a parent page) always stay. The flagless full
 * manifest is the build input for docs.wirekit.app and internal tooling.
 *
 * Usage:
 *   php artisan wirekit:export-json --pretty
 *   php artisan wirekit:export-json --public
 *   php artisan wirekit:export-json
 *
 * Output: full JSON document on stdout. Exit code 0 = success.
 */
class ExportJsonCommand extends Command
{
    protected $signature = 'wirekit:export-json
        {--pretty : Pretty-print the JSON output}
        {--public : Emit the manifest docs.wirekit.app serves at its /components.json endpoint (the default emits the full inventory).}';

    protected $description = 'Emit machine-readable JSON manifest of every WireKit component (props + slots + category)';

    public function handle(): int
    {
        /*
         * The manifest is built in `ComponentManifest` rather than here, because it is
         * published by two artifacts and used to be built by two loops. `wirekit:install`
         * writes the same document to `.wirekit-schema.json`, three documented places call the
         * two "the same manifest", and the second loop had quietly lost `component_kind`,
         * `tag_alias` and `released_version` and was emitting `sub_components` as bare strings.
         */
        $document = ComponentManifest::document((bool) $this->option('public'));

        // JSON_HEX_TAG is non-negotiable — `/components.json` is consumed by
        // AI tooling and may be embedded in a <script type="application/ld+json">
        // block on a docs page. Without HEX_TAG, a description containing
        // `</script>` would break out of the surrounding script block. Same
        // contract as `wirekit:export-api-map` and `wirekit:export-blocks`.
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($document, $flags);
        if ($json === false) {
            $this->error('Failed to encode component manifest as JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        // Write to stdout — the docs site captures whatever we emit and
        // serves it from /components.json. Use $this->line() with empty
        // verbosity guard so the JSON stays the only thing on stdout.
        $this->output->write($json);
        $this->output->writeln('');

        return self::SUCCESS;
    }
}
