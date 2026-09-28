<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * Which pages under `docs/blueprints/` are page layouts rather than blueprints.
 *
 * The five page-layout sections moved under `docs/blueprints/` when the two documentation
 * sections merged, and they are a different thing all the same: a page layout is a generic
 * shape (a shell, a dashboard, a sign-in screen) that the product verticals are built out of.
 * The API map gives them a group of their own and the blocks manifest labels them `layout`,
 * both from this one list, so the two artifacts cannot disagree about which page is which.
 */
final class BlueprintSections
{
    /** The page-layout sections, by the directory they sit in under `docs/blueprints/`. */
    public const PAGE_LAYOUT_SECTIONS = ['application-shells', 'auth', 'dashboards', 'errors', 'marketing'];

    /**
     * Whether a page, named by its path under `docs/blueprints/` without the extension, is a
     * page layout. `page-layouts` is the introduction to the five sections.
     */
    public static function isPageLayout(string $path): bool
    {
        foreach (self::PAGE_LAYOUT_SECTIONS as $section) {
            if (str_starts_with($path, $section.'/')) {
                return true;
            }
        }

        return $path === 'page-layouts';
    }
}
