{{-- Shared close button logic for Modal and Drawer.
     Included via @include('wirekit::components.partials.overlay-close', ['component' => 'modal'])
     from modal/close.blade.php and drawer/close.blade.php.
     The $component variable controls the personalization key. --}}

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses("{$component}.close", 'base', '', $scope ?? null);
@endphp

{{-- Close wrapper — triggers overlay close on click.
     Uses <div> instead of <button> to avoid invalid nested <button> HTML
     when the slot contains <x-wirekit::button>. The inner button provides
     keyboard accessibility (Enter/Space activates, click bubbles up). --}}
<div
    x-on:click="close()"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
