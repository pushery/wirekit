<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Container\Container;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Blaze\BlazeManager;

/**
 * A component view of the kit that livewire/blaze compiled into a PHP function.
 *
 * Blaze renders an anonymous component an application opts in as a function rather than as a
 * Blade view, and hands it a bag of its own class. Such a render passes no view through the view
 * factory, so the composers WireKit registers for its views do not run, and the component stands
 * on no stack of the factory. A view of the kit that may render under Blaze asks here, and does
 * the composers' work on its bag itself.
 *
 * Which views an application may compile is decided here as well, in families, and a family holds
 * only views that render the same under Blaze as under Blade. A long table is mostly its cells, so
 * the cells and their stand-ins are the family `table`. The table and its rows stay Blade
 * components on the factory's stack, which is where a cell finds the table it belongs to, in a
 * table inside a cell too: Blaze's own `@aware` reads the attributes the callers wrote, and passes
 * a table that did not write the key on to a table further out.
 *
 * @internal Called from the component views and the service provider; not a developer-facing API.
 */
final class BlazeRender
{
    /** The bag Blaze hands a component it compiled, named as text so the kit runs without Blaze. */
    private const BAG = 'Livewire\\Blaze\\Runtime\\BlazeAttributeBag';

    /**
     * The families an application can compile with Blaze, under the key of `wirekit.blaze` that
     * opts each in, as the paths of their views below `resources/views/components`.
     */
    public const FAMILIES = [
        'table' => ['table/td', 'table/stand-in'],
        'text' => ['text'],
    ];

    /** Whether the view this bag was handed to runs as a function Blaze compiled. */
    public static function active(ComponentAttributeBag $attributes): bool
    {
        return is_a($attributes, self::BAG);
    }

    /**
     * What the composers of the kit's views do to a caller's attributes, for a render that passes
     * no view through the factory: an attribute bound to `null`, which the bag prints as nothing,
     * leaves the bag, and an Alpine directive written without a value gets an empty one.
     */
    public static function normalize(ComponentAttributeBag $attributes): void
    {
        CallerAttribute::normalize($attributes);
        ValuelessDirectives::normalize($attributes);
    }

    /**
     * The view files of the families the application opts in, for Blaze to compile.
     *
     * @param  array<array-key, mixed>  $choice  the `wirekit.blaze` configuration, a switch per family
     * @return list<string>
     */
    public static function files(array $choice, string $componentsPath): array
    {
        $files = [];

        foreach (self::FAMILIES as $family => $views) {
            if (($choice[$family] ?? false) !== true) {
                continue;
            }

            foreach ($views as $view) {
                $files[] = rtrim($componentsPath, '/').'/'.$view.'.blade.php';
            }
        }

        return $files;
    }

    /**
     * Hands Blaze the views of the families `wirekit.blaze` switches on, where Blaze is installed.
     * The service provider calls it once every provider has booted, so the configuration is final
     * and Blaze's own provider has registered its manager.
     */
    public static function register(Container $app, string $componentsPath): void
    {
        $choice = config('wirekit.blaze');

        if (! is_array($choice) || ! $app->bound(BlazeManager::class)) {
            return;
        }

        $files = self::files($choice, $componentsPath);

        if ($files === []) {
            return;
        }

        $optimize = $app->make(BlazeManager::class)->optimize();

        foreach ($files as $file) {
            $optimize->in($file);
        }
    }
}
