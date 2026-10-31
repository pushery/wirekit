{{-- optimistic-ui: n/a — client-only
     It draws the parts it is given in an order, and showing a hidden part never reaches the
     server. --}}
{{-- Named parts, such as the cards of a record page, drawn in an order the application remembers,
     with the parts a reader hid carried along hidden.

     A hidden part is drawn with `hidden` rather than left out: a slot is rendered before the
     component places it, and a Livewire component inside one would otherwise lose its element.
     Each part stands in a wrapper with no box of its own (`display: contents`) and a `wire:key`,
     so a new order moves the parts instead of drawing them again, and the order in the document is
     the order on screen, for the keyboard and for a screen reader. A hidden part shows for the
     visit on `wirekit:card-stack-show`, and when a link's fragment leads into it. See
     resources/js/components/card-stack.js. --}}
@php
    // A part arrives as a slot of its name, and Blade sets each slot as a variable of that name
    // before the props below are read, where it takes the place of the attribute: a part named
    // `order` would replace the order. The parts are read from `$__laravel_slots`
    // (Support\KeyedSlot), so the slot variables are let go first.
    foreach (array_keys($__laravel_slots ?? []) as $__wkSlotName) {
        if (is_string($__wkSlotName) && ! in_array($__wkSlotName, ['__default', 'slot', 'attributes', '__laravel_slots'], true)) {
            unset($$__wkSlotName);
        }
    }
    unset($__wkSlotName);
@endphp
@props([
    // The parts in the order they are drawn: a list of slot names, or of
    // `['key' => name, 'shown' => bool]`. A name without a slot is skipped; a slot the order does
    // not name, a part added after the order was remembered, comes last and shows.
    'order' => [],
    'gap' => config('wirekit.components.stack.gap', 'md'),
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\ListProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list auto-derived from
    // this component's @props.
    WireKit::warnUnknownProps('card-stack', $attributes->getAttributes());

    // Every named slot the call site wrote, in the order it wrote them.
    $slots = $__laravel_slots ?? [];
    $named = array_values(array_filter(
        array_keys($slots),
        static fn ($name): bool => is_string($name) && $name !== '__default',
    ));

    // Each part under the name the order gives it, with the slot it draws: an entry names its slot
    // as written, or in the camel form Blade gives a hyphenated name written as `<x-slot:api-keys>`.
    $parts = [];
    $placed = [];
    // An entry may be a slot key alone, so a single key written as text is an order of one
    // through the cast, and a text is no error here.
    foreach ((array) (ListProp::from($order) ?? []) as $entry) {
        $key = is_array($entry) ? (string) ($entry['key'] ?? '') : (string) $entry;
        $slotName = \Pushery\WireKit\Support\KeyedSlot::nameFor($slots, $key);

        if ($slotName === null || isset($placed[$slotName]) || array_key_exists($key, $parts)) {
            continue;
        }

        $placed[$slotName] = true;
        $parts[$key] = ['slot' => $slotName, 'shown' => is_array($entry) ? BooleanProp::from($entry['shown'] ?? true, true) : true];
    }

    foreach ($named as $slotName) {
        if (! isset($placed[$slotName]) && ! array_key_exists($slotName, $parts)) {
            $parts[$slotName] = ['slot' => $slotName, 'shown' => true];
        }
    }

    // The keys of the wrappers, stable across renders: a caller's id, or one derived from the
    // parts, which a second stack of the same parts on the page keeps apart with `-2`.
    $stackId = $attributes->get('id') ?: \Pushery\WireKit\Support\DomId::unique(
        WireKit::stableId('wk-card-stack', implode('-', array_keys($parts))),
        'wk-card-stack-'
    );

    // The caller's attributes land on the stack's own Alpine root: a caller's `x-ref` there would
    // be filed inside the component, so CallerRef::onRoot() hands it to the root above, and a
    // caller's listener for the show event moves to the other spelling so both run.
    $stackAttributes = \Pushery\WireKit\Support\CallerListeners::beside(
        \Pushery\WireKit\Support\CallerRef::onRoot($attributes->except('id')),
        ['x-on:wirekit:card-stack-show.window']
    );
@endphp

<x-wirekit::stack
    :gap="$gap"
    :scope="$scope"
    id="{{ $stackId }}"
    data-wk-card-stack
    x-data="wirekitCardStack()"
    x-on:wirekit:card-stack-show.window="show($event.detail)"
    :attributes="$stackAttributes"
>
    @foreach ($parts as $key => $part)
        <div data-wk-card-stack-part="{{ $key }}" wire:key="{{ $stackId }}-{{ $key }}" class="contents" @unless ($part['shown']) hidden data-wk-card-stack-hidden @endunless>
            {{ $slots[$part['slot']] ?? '' }}
        </div>
    @endforeach
</x-wirekit::stack>
