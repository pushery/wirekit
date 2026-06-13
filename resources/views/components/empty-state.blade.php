@props([
    'icon' => null,
    'title' => null,
    'description' => null,
    // Container chrome: default (no border/bg — v1.5.0-identical) · outline (dashed
    // border, reads as a placeholder drop-zone) · muted (filled muted surface).
    'variant' => config('wirekit.components.empty-state.variant', 'default'),
    // Optional reveal animation. Null = no animation (default, v1.5.0-identical).
    'animateIn' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // outline ↔ outlined spelling-pair alias (house rule — EnumValueAliasConsistencyTest).
    $variantAliases = ['outlined' => 'outline'];
    $variant = $variantAliases[$variant] ?? $variant;

    $variantValue = match ($variant) {
        'default', 'outline', 'muted' => $variant,
        default => WireKit::validateProp('empty-state', 'variant', $variant, ['default', 'outline', 'muted']),
    };

    // Container chrome per variant. `default` adds nothing (back-compat); the others
    // wrap the centered content in a rounded surface for use as a standalone card.
    $variantClasses = match ($variantValue) {
        'outline' => 'rounded-[var(--radius-wk-lg)] border-[length:var(--border-wk-width)] border-dashed border-[var(--color-wk-border)]',
        'muted' => 'rounded-[var(--radius-wk-lg)] bg-[var(--color-wk-bg-muted)]',
        default => '',
    };

    // Centered container with muted colors and generous vertical padding
    $classes = WireKit::resolveClasses('empty-state', 'base', implode(' ', [
        'flex flex-col items-center justify-center text-center',
        'px-[var(--padding-wk-x-xl)] py-[var(--padding-wk-y-xl)]',
        'font-[family-name:var(--font-wk-sans)]',
        $variantClasses,
    ]), $scope);

    $animateAttr = WireKit::resolveAnimateIn($animateIn, 'empty-state');
@endphp

@php
    // — iconSlot symmetry: slot wins over icon prop when
    // both supplied. Matches feature pattern + stat behavior.
    $hasIconSlot = isset($iconSlot) && $iconSlot->isNotEmpty();
@endphp

<div {{ $attributes->class([$classes]) }} @if($animateAttr) {!! $animateAttr !!} @endif>
    {{-- Icon: iconSlot (if provided) takes priority; else string $icon prop. --}}
    @if($hasIconSlot)
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text-muted)]">
            {{ $iconSlot }}
        </div>
    @elseif($icon)
        <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text-muted)]">
            <x-wirekit::icon :name="$icon" size="lg" />
        </div>
    @endif

    @if($title)
        <h3 class="mb-1 text-[length:var(--text-wk-lg)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">
            {{ $title }}
        </h3>
    @endif

    @if($description)
        <p class="mb-4 max-w-md text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
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
