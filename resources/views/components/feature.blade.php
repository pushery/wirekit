@props([
    'icon' => null,
    'title' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Feature — individual feature card for feature-grid.
    $classes = WireKit::resolveClasses('feature', 'base', implode(' ', [
        'flex flex-col',
        'gap-[var(--gap-wk-sm)]',
    ]), $scope);
@endphp

<div {{ $attributes->class([$classes]) }}>
    @if($icon)
        <div class="flex items-center justify-center w-10 h-10 rounded-[var(--radius-wk-md)] bg-[var(--color-wk-accent)] text-white mb-[var(--space-wk-xs,0.25rem)]">
            <x-wirekit::icon :name="$icon" class="h-5 w-5" />
        </div>
    @endif

    @if($title)
        <h3 class="text-[length:var(--text-wk-lg)] font-[number:var(--font-wk-heading-weight)] text-[var(--color-wk-text)]">
            {{ $title }}
        </h3>
    @endif

    @if($slot->isNotEmpty())
        <p class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)] leading-relaxed">
            {{ $slot }}
        </p>
    @endif
</div>
