{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include of `reorder` and `table.reorder`, which own the element the
     controls sit in. The arrows call the developer's own action, as those two describe. --}}
{{-- The handle a pointer drags and the two arrows everyone else moves a row with, shared by
     `reorder` and `table.reorder` so both draw and call the same things.

     Params: $reorderComponent (the name a message and personalization use), $item, $position, $count,
     $action, $label, $arrowSurface, $arrowSize, $scope. --}}
@php
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\WireKit;

    $position = max(0, (int) $position);
    $count = $count === null || $count === '' ? null : max(0, (int) $count);

    // The arrows call the method by name inside an attribute Livewire evaluates, so it has to be
    // a name. Anything else is reported through the strictness gate, and where the gate does not
    // throw, the arrows render without one.
    //
    // Each component is named literally rather than through `$reorderComponent`: the prop
    // validation parser reads the call's first argument as written, and every catalog built on
    // it would otherwise lose the `action` of both components.
    if (! is_string($action) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $action) !== 1) {
        if ($reorderComponent === 'reorder') {
            WireKit::validateProp('reorder', 'action', is_string($action) ? $action : '', [
                'the name of the Livewire method wire:sort calls',
            ]);
        } else {
            WireKit::validateProp('table.reorder', 'action', is_string($action) ? $action : '', [
                'the name of the Livewire method wire:sort calls',
            ]);
        }

        $action = null;
    }

    $isFirst = $position === 0;
    $isLast = $count !== null && $position >= $count - 1;

    // The key the drop would pass, as a JavaScript literal, so a string key arrives as a string.
    $key = AlpinePayload::from($item);
    $upCall = $action !== null && ! $isFirst ? $action.'('.$key.', '.($position - 1).')' : null;
    $downCall = $action !== null && ! $isLast ? $action.'('.$key.', '.($position + 1).')' : null;

    $upText = filled($label) ? __('wirekit::Move :item up', ['item' => $label]) : __('wirekit::Move up');
    $downText = filled($label) ? __('wirekit::Move :item down', ['item' => $label]) : __('wirekit::Move down');

    $handleClasses = WireKit::resolveClasses($reorderComponent, 'handle', implode(' ', [
        'inline-flex items-center justify-center',
        'cursor-grab active:cursor-grabbing touch-none',
        'text-[color:var(--color-wk-text-subtle)] hover:text-[color:var(--color-wk-text-muted)]',
    ]), $scope);
@endphp
{{-- The handle a pointer drags. Hidden from assistive technology on purpose: a drag is not
     something a keyboard or a screen reader can do, and the two buttons beside it are how they
     move a row. --}}
<span wire:sort:handle aria-hidden="true" title="{{ __('wirekit::Drag to reorder') }}" class="{{ $handleClasses }}">
    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
        <circle cx="6" cy="3.5" r="1.25" /><circle cx="10" cy="3.5" r="1.25" />
        <circle cx="6" cy="8" r="1.25" /><circle cx="10" cy="8" r="1.25" />
        <circle cx="6" cy="12.5" r="1.25" /><circle cx="10" cy="12.5" r="1.25" />
    </svg>
</span>

<x-wirekit::tooltip :text="$upText" :focusable-trigger="false" :describes="false">
    <x-wirekit::button :size="$arrowSize" intent="neutral" :surface="$arrowSurface" icon-only :disabled="$isFirst" :wire:click="$upCall" data-wk-reorder-arrow="up" x-on:click="remember('up', $el)">
        <x-slot:iconLeft><x-wirekit::icon name="arrow-up" size="sm" aria-hidden="true" /></x-slot:iconLeft>
        {{ $upText }}
    </x-wirekit::button>
</x-wirekit::tooltip>

<x-wirekit::tooltip :text="$downText" :focusable-trigger="false" :describes="false">
    <x-wirekit::button :size="$arrowSize" intent="neutral" :surface="$arrowSurface" icon-only :disabled="$isLast" :wire:click="$downCall" data-wk-reorder-arrow="down" x-on:click="remember('down', $el)">
        <x-slot:iconLeft><x-wirekit::icon name="arrow-down" size="sm" aria-hidden="true" /></x-slot:iconLeft>
        {{ $downText }}
    </x-wirekit::button>
</x-wirekit::tooltip>
