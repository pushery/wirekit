@props([
    'icon' => null,
    'title' => null,
    'description' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Centered container with muted colors and generous vertical padding
    $classes = WireKit::resolveClasses('empty-state', 'base', implode(' ', [
        'flex flex-col items-center justify-center text-center',
        'px-[var(--padding-wk-x-xl)] py-[var(--padding-wk-y-xl)]',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

<div {{ $attributes->class([$classes]) }}>
    {{-- Icon: either passed via "icon" prop (semantic alias) OR rendered via the icon slot --}}
    @if($icon)
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-[var(--color-wk-bg-muted)] text-[var(--color-wk-text-muted)]">
            <x-wirekit::icon :name="$icon" class="h-6 w-6" />
        </div>
    @elseif(isset($iconSlot))
        {{-- Named slot allows callers to supply custom SVG / illustration --}}
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-[var(--color-wk-bg-muted)] text-[var(--color-wk-text-muted)]">
            {{ $iconSlot }}
        </div>
    @endif

    @if($title)
        <h3 class="mb-1 text-[length:var(--text-wk-lg)] font-[number:var(--font-wk-heading-weight)] text-[var(--color-wk-text)]">
            {{ $title }}
        </h3>
    @endif

    @if($description)
        <p class="mb-4 max-w-md text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)]">
            {{ $description }}
        </p>
    @endif

    {{-- Default slot holds the call-to-action (e.g. button) --}}
    @if(trim($slot->toHtml()) !== '')
        <div class="mt-2">
            {{ $slot }}
        </div>
    @endif
</div>
