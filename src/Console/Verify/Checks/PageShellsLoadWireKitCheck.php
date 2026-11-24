<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\Support\CompactClasses;
use Pushery\WireKit\Support\KitIcons;
use Pushery\WireKit\Support\LayoutShells;
use Pushery\WireKit\WireKit;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class PageShellsLoadWireKitCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkPageShellsLoadWireKit();
    }

    /**
     * Does every page shell the app and auth layouts render actually load WireKit?
     *
     * The check above answers "is the directive somewhere?", and a directive can be somewhere
     * and still reach no page. The Livewire starter kit is such a case: its
     * `layouts/app.blade.php` hands the page to `layouts/app/sidebar.blade.php`, keeps
     * `layouts/app/header.blade.php` beside it, and switching between the two is a one-word
     * edit. Wired into the sidebar alone, a project passes the check above on every page and
     * loses WireKit on all of them the day somebody makes that edit.
     *
     * A WARN rather than a FAIL: a directive pushed through a stack, a section a child view
     * fills, or a view composer is invisible to the walk, so "not found from here" is not
     * "absent".
     */
    private function checkPageShellsLoadWireKit(): void
    {
        // Where the check above found no directive at all, its FAIL already said everything —
        // repeating it once per shell would bury the one line that matters.
        if (! $this->context->stylesFoundAnywhere && ! $this->context->scriptsFoundAnywhere) {
            return;
        }

        $shells = LayoutShells::forApplication();
        $stylesCovered = ! $this->context->stylesFoundAnywhere || $this->hasWirekitCssImportInAppCss();

        // The scripts directive writes the symbols of the icon sprite, so while a sprite is on
        // every shell needs it, or @wirekitIconSprite in an application that loads the components
        // through a module of its own: without either the glyphs of a checkbox or a select, and
        // the icons of a sidebar's rows, point at nothing.
        $sprite = KitIcons::sheetNeeded();
        $scriptsNeeded = $this->context->scriptsFoundAnywhere || $sprite;
        $missing = [];
        $inspected = 0;

        // With compact classes on, the kit's short classes sit in `@layer utilities` beside the
        // application's own utilities, and within one layer the later stylesheet wins a tie: a
        // head that loads the application's stylesheet first hands those ties to the kit.
        $compact = CompactClasses::enabled();
        $late = [];

        // Both directives write the glyphs, so a page with the two carries the id of every glyph
        // twice. The icons of navigation rows are written once, by whichever of them comes first.
        $glyphs = KitIcons::sprite();
        $twice = [];

        foreach (array_filter([$shells->appLayout(), $shells->authLayout()]) as $layout) {
            $found = $shells->shellsOf($layout);

            foreach ($found['head'] as $file) {
                $inspected++;

                if (! $stylesCovered && ! $shells->carries($file, 'wirekitStyles')) {
                    $missing[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).' — no @wirekitStyles in its <head>';
                }

                if ($compact && $shells->loadsApplicationStylesheetFirst($file)) {
                    $late[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
                }
            }

            foreach ($found['body'] as $file) {
                $inspected++;

                if ($glyphs && $shells->carries($file, 'wirekitScripts') && $shells->carries($file, 'wirekitIconSprite')) {
                    $twice[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
                }

                if (! $scriptsNeeded || $shells->carries($file, 'wirekitScripts')) {
                    continue;
                }

                // Without the directive anywhere, the application loads the components itself, and
                // only the glyphs are asked for: @wirekitIconSprite writes them without the scripts.
                if ($this->context->scriptsFoundAnywhere) {
                    $missing[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).' — no @wirekitScripts before its </body>'
                        .($sprite ? ', which writes the glyphs of the icon sprite' : '');
                } elseif (! $shells->carries($file, 'wirekitIconSprite')) {
                    $missing[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file).' — no @wirekitScripts or @wirekitIconSprite before its </body>, one of which writes the glyphs of the icon sprite';
                }
            }
        }

        // No layout it could follow: nothing to report either way, and a PASS would claim a
        // result for a check that never ran.
        if ($inspected === 0) {
            return;
        }

        $this->reportStylesheetOrder($compact, array_values(array_unique($late)));
        $this->reportSpriteWrittenTwice(array_values(array_unique($twice)));

        if ($missing === []) {
            $this->reportPass('Every page shell your layouts render loads WireKit');

            return;
        }

        $this->reportWarn('A page shell your layouts render does not load WireKit');

        foreach (array_values(array_unique($missing)) as $line) {
            $this->line('  '.$line);
        }

        $this->line('  Fix: php artisan wirekit:install — it follows each layout to these files. Loading WireKit through a @stack or a section instead is fine; this check cannot see those.');
    }

    /**
     * A page shell that carries @wirekitScripts and @wirekitIconSprite while the sprite is on
     * writes its sheet twice, and every symbol id stands twice in the page. Said only where it
     * happens: one directive is the setup.
     *
     * @param  list<string>  $twice  the page shells that carry both directives
     */
    private function reportSpriteWrittenTwice(array $twice): void
    {
        if ($twice === []) {
            return;
        }

        $this->reportWarn('A page shell writes the glyphs of the icon sprite twice: @wirekitScripts writes them, and so does @wirekitIconSprite');

        foreach ($twice as $file) {
            $this->line('  '.$file);
        }

        $this->line('  Fix: remove @wirekitIconSprite where @wirekitScripts stands');
    }

    /**
     * The order of the two stylesheets, said only while compact classes are on: with them off,
     * nothing of the kit's shares a layer with the application's utilities.
     *
     * @param  list<string>  $late  the page shells whose head loads the application's stylesheet first
     */
    private function reportStylesheetOrder(bool $compact, array $late): void
    {
        if (! $compact) {
            return;
        }

        if ($late === []) {
            $this->reportPass('Compact classes are on, and every page shell loads @wirekitStyles before your stylesheet');

            return;
        }

        $this->reportWarn('Compact classes are on, and a page shell loads your stylesheet before @wirekitStyles: a class of the kit\'s wins a tie with a utility of yours');

        foreach ($late as $file) {
            $this->line('  '.$file);
        }

        $this->line('  Fix: move @wirekitStyles above @vite(...) in the <head>');
    }
}
