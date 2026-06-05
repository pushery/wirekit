@props([
    'scope' => null,
])

{{-- Alert dialog cancel — pre-wired to close the parent alert-dialog
     via x-on:click="close()". The parent provides the close() method
     via x-data="wirekitAlertDialog(...)".

     Use this instead of a bare <x-wirekit::button> when you want the
     Cancel control to actually close the dialog without manually
     wiring `$dispatch('wirekit-alert-dialog-close', { name })`.

     Defaults: renders a neutral filled button reading "Cancel". Pass
     a default slot to override the button label, OR pass any inner
     <x-wirekit::button> to customize variant / size / icon.

     Example:
       <x-wirekit::alert-dialog.cancel />
       <x-wirekit::alert-dialog.cancel>Back</x-wirekit::alert-dialog.cancel>
       <x-wirekit::alert-dialog.cancel>
           <x-wirekit::button intent="neutral" surface="ghost">Discard</x-wirekit::button>
       </x-wirekit::alert-dialog.cancel> --}}
@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('alert-dialog.cancel', 'base', '', $scope);
@endphp

<div
    x-on:click="close()"
    {{ $attributes->class([$classes]) }}
>
    @if(trim((string) $slot) === '')
        <x-wirekit::button intent="neutral" surface="filled">Cancel</x-wirekit::button>
    @elseif(str_contains((string) $slot, '<x-wirekit'))
        {{-- Caller supplied a full WireKit component (typically a
             button) — use it verbatim. The x-on:click on the parent
             <div> handles the close event so the caller's button
             doesn't need its own wire:click. --}}
        {{ $slot }}
    @else
        {{-- Plain text slot — wrap it in the default Cancel button so
             keyboard + screen-reader semantics stay correct. --}}
        <x-wirekit::button intent="neutral" surface="filled">{{ $slot }}</x-wirekit::button>
    @endif
</div>
