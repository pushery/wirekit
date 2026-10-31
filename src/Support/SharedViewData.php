<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\View\View;
use WeakMap;

/**
 * A WireKit view renders what its caller wrote, whatever an application shares with every view or
 * composes into it.
 *
 * A value shared with every view through `View::share('title', $page)` reaches a component's view
 * as data too. The code Blade compiles `@props` to keeps a value the data already holds for a prop
 * of that name, and a view that asks whether its caller gave a named slot, `isset($actions)`, finds
 * the shared value under the slot's name. Without this, a page title an application shares would
 * be the heading of every alert written without one, a status it shares would stand under every
 * filter builder, and a text shared under the name of a slot whose content a view inspects would
 * end the page. Each shared name the caller did not write is given to the view as null before it
 * runs, so a prop takes its default and a slot reads as not given. The few shared values the views
 * read on purpose are left alone (KEPT).
 *
 * The view's own data takes precedence over shared data, so the shared value stays available to
 * every other view, the content of a slot included, which renders in its caller's view. A prop or
 * slot the caller wrote is left alone, and so is `@aware`, which reads the parent's value without
 * looking at the data. A partial a component includes is handed the component's variables, so it
 * reads the same null.
 *
 * A composer works on the view's own data instead, and one the application registers runs after
 * the component composer: remember() keeps the data that composer leaves, and restore(), which the
 * service provider registers once the application booted, gives it back after the application's
 * composers ran.
 *
 * @internal Called from the view composers the service provider registers; not a developer-facing API.
 */
final class SharedViewData
{
    /**
     * What a WireKit view reads of the data shared with every view: the view factory and the
     * application, which every compiled view uses, the error bag Laravel shares with every view of
     * a web request and Livewire with every view of a component it renders, which the fields read,
     * and the component Livewire renders, under its name and its older one, which its directives
     * read.
     */
    private const KEPT = ['__env', 'app', 'errors', '__livewire', '_instance'];

    /**
     * The data each rendering WireKit view held when the component composer was done with it,
     * until the view is gone.
     *
     * @var WeakMap<View, array<array-key, mixed>>|null
     */
    private static ?WeakMap $written = null;

    public static function shadow(View $view): void
    {
        $data = $view->getData();

        foreach (array_keys(app('view')->getShared()) as $key) {
            if (is_string($key) && ! in_array($key, self::KEPT, true) && ! array_key_exists($key, $data)) {
                $view->with($key, null);
            }
        }
    }

    /**
     * Keep the data the view holds now, as the caller wrote it and the component composer read it,
     * for restore().
     */
    public static function remember(View $view): void
    {
        self::$written ??= new WeakMap;
        self::$written[$view] = $view->getData();
    }

    /**
     * Give the view back the data remember() kept: what a later composer changed is changed back,
     * and what it added reads as none.
     *
     * A composer the application registers for every view runs after the component composer and
     * writes the view's own data, a prop the caller wrote included: without this, a composer that
     * gives every view a `title` would replace the title of every alert, and a partial would read
     * the composer's value for a variable its component hands it. This runs after every composer
     * the application registered while it booted; one it registers later still runs after it.
     */
    public static function restore(View $view): void
    {
        if (self::$written === null || ! isset(self::$written[$view])) {
            return;
        }

        $written = self::$written[$view];

        foreach ($view->getData() as $key => $value) {
            if (! array_key_exists($key, $written)) {
                if ($value !== null) {
                    $view->with((string) $key, null);
                }
            } elseif ($written[$key] !== $value) {
                $view->with((string) $key, $written[$key]);
            }
        }
    }
}
