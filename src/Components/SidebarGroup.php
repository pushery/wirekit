<?php

declare(strict_types=1);

namespace Pushery\WireKit\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * The class behind `sidebar.group`, registered under the same tag as the anonymous file.
 *
 * Everything the group renders stays in `sidebar/group.blade.php`, and so does its `@props`
 * block, which is what every catalog, manifest and guard reads. The class exists for one prop.
 * `collapsible` on a group means "this group folds its rows"; on the sidebar it means "this
 * column can become an icon rail", and each row reads the sidebar's through `@aware`. `@aware`
 * answers with the nearest ancestor that was called with the name, and an anonymous component
 * hands every attribute of its tag to that search. A class component hands its descendants
 * only its public properties, so taking `collapsible` as a constructor parameter keeps the
 * group's meaning to the group, and the rows reach the sidebar's value past it.
 */
final class SidebarGroup extends Component
{
    /**
     * Every scalar a tag can pass, because the view reads the value through BooleanProp: a bare
     * attribute arrives as `true`, a quoted one as a string, and a bound one as whatever the
     * expression gives.
     */
    public function __construct(
        private readonly bool|int|float|string|null $collapsible = false,
        private readonly bool|int|float|string|null $forceOpen = false,
    ) {}

    public function render(): View
    {
        /** @var view-string $view */
        $view = 'wirekit::components.sidebar.group';

        // Handed to the view as data rather than held in a public property: a public property is
        // part of what `@aware` searches, and this setting belongs to the group alone.
        //
        // `forceOpen` goes the same way, for a different reason. A class component hands the view
        // its attributes as they were written, and `@props` binds a kebab-case attribute to a
        // variable of that exact name, so `force-open` would never reach `$forceOpen`; an
        // anonymous component receives its attributes camel-cased. The constructor maps the
        // attribute to the parameter, and it is the view's one prop of more than one word.
        return view($view, [
            'collapsible' => $this->collapsible,
            'forceOpen' => $this->forceOpen,
        ]);
    }
}
