@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Header section — title area with bottom border separator
    $classes = WireKit::resolveClasses('card.header', 'base', implode(' ', [
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-md)]',
        'border-b-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-subtle)]',
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[length:var(--text-wk-lg)]',
        'text-[var(--color-wk-text)]',
    ]), $scope);
@endphp

@php
    // — iconSlot symmetry: optional named slot for a header
    // icon/avatar/illustration. Renders before the default slot content.
    $hasIconSlot = isset($iconSlot) && $iconSlot->isNotEmpty();
@endphp

<div {{ $attributes->class([$classes, 'flex items-center gap-[var(--gap-wk-sm)]']) }}>
    @if($hasIconSlot)
        <div class="shrink-0">{{ $iconSlot }}</div>
    @endif
    <div class="flex-1 min-w-0">{{ $slot }}</div>
</div>
