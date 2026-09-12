<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\WireKit;

/**
 * The machine-readable component manifest, built once for every artifact that publishes it.
 *
 * ⚠️ THIS EXISTS BECAUSE THE SAME MANIFEST WAS BUILT TWICE AND THE TWO DRIFTED APART.
 * `wirekit:export-json` emits it, and `wirekit:install` writes `.wirekit-schema.json` at the
 * developer's project root — three documented places call the second one "the same JSON
 * manifest" as the first. It was not: the feeder dropped `component_kind` and `tag_alias` from
 * every entry and `released_version` from the document, and its `sub_components` were bare
 * dotted strings against the export's `{name, tag, props}` records. A tool written against the
 * documentation and pointed at the feeder — which is precisely what the documentation tells an
 * integrator to do — got a different shape and no notice.
 *
 * Neither copy was wrong on its own; they were written months apart, and the second one's own
 * docblock claimed "same output shape, single source of truth" while re-deriving slots and
 * sub-components through a private mirror of the first. That is the shape of this defect: the
 * comment asserting the property is what stops anyone checking it.
 */
final class ComponentManifest
{
    /**
     * Every component, in the manifest shape.
     *
     * `$publicOnly` drops a component whose page exists but is not publicly rendered —
     * ENTIRELY, never merely with `docs_url` nulled. A page-less sub-component (`toast-region`,
     * `glass`, the `reading-*` parts) is a different case and stays: it is documented on a
     * parent page, so it is public, it just has no page of its own.
     *
     * @return list<array<string, mixed>>
     */
    public static function components(bool $publicOnly = false): array
    {
        $components = [];

        foreach (ComponentRegistry::all() as $name => $meta) {
            $pageStatus = DocsVisibility::componentPageStatus($name);

            if ($publicOnly && $pageStatus === DocsVisibility::STATUS_STAGED) {
                continue;
            }

            $entry = [
                'name' => $name,
                'tag' => ComponentRegistry::tag($name),
            ];

            /*
             * For a class-based component whose canonical tag uses the single-hyphen form
             * (`<x-wirekit-chart>`), the double-colon alias (`<x-wirekit::chart>`) is emitted
             * too, so an integrator grepping the historical shape still matches. An anonymous
             * component has no alias and the field is omitted rather than nulled.
             */
            $tagAlias = ComponentRegistry::tagAlias($name);

            if ($tagAlias !== null) {
                $entry['tag_alias'] = $tagAlias;
            }

            $entry['category'] = $meta['category'];
            $entry['description'] = $meta['description'];

            /*
             * Null for a component with no publicly rendered page of its own. This document
             * lands in a developer's project root as an editor/AI feed, and a URL that 404s
             * there is worse than no URL, because the tool follows it.
             */
            $entry['docs_url'] = $pageStatus === DocsVisibility::STATUS_PUBLIC
                ? WireKit::DOCS_URL."/components/{$name}"
                : null;

            // The registry routes anonymous components through PropsParser and class-based ones
            // through ClassPropsExtractor, and returns one shape, so this caller does not branch.
            $entry['props'] = ComponentRegistry::extractProps($name);

            /*
             * How the component exposes its API. An anonymous Blade component carries props via
             * an `@props([...])` block AND accepts named template slots; a class-based one
             * carries them via its constructor signature and typically has no developer-facing
             * slots. A tool that knows which it is generates the right wrapping shape instead of
             * guessing from prop names.
             */
            $entry['component_kind'] = ComponentRegistry::componentClass($name) !== null ? 'class' : 'anonymous';
            $entry['slots'] = ComponentRegistry::slotsOf($name);
            $entry['sub_components'] = ComponentRegistry::describeSubComponentsOf($name);

            $components[] = $entry;
        }

        return $components;
    }

    /**
     * The whole document, version header included.
     *
     * @return array{version: string, released_version: string, generated_at: string, components: list<array<string, mixed>>}
     */
    public static function document(bool $publicOnly = false): array
    {
        return [
            'version' => VersionResolver::resolve(),
            /*
             * The newest RELEASED version, which is a different question from `version` above:
             * that one is the build installed here, and on a deployment pinned to a development
             * branch it is literally that branch name, so it cannot be compared against a
             * version a page claims to show. This field is the comparable half — it exists
             * because a documentation page served a changelog frozen four minors back and no
             * artifact anywhere carried both sides of the comparison that would have said so.
             */
            'released_version' => VersionResolver::released(),
            'generated_at' => date('c'),
            'components' => self::components($publicOnly),
        ];
    }
}
