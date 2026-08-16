{{-- optimistic-ui: n/a — client-only
     It opens the modal. The interaction never leaves the page. --}}
@props([
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('modal.trigger', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('modal.trigger', 'base', '', $scope);
@endphp

{{-- Modal trigger — opens the parent modal when clicked --}}
<div
    x-on:click="show()"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
