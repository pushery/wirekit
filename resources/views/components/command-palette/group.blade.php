@props([
    'heading' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Command group — optional heading + grouped items.
    $headingClasses = WireKit::resolveClasses('command-palette.group', 'heading', implode(' ', [
        'px-[var(--padding-wk-x-md)]',
        'py-[var(--padding-wk-y-xs)]',
        'text-[length:var(--text-wk-xs)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[var(--color-wk-text-muted)]',
        'uppercase tracking-wider',
    ]), $scope);
@endphp

<div role="group" @if($heading) aria-label="{{ $heading }}" @endif {{ $attributes }}>
    @if($heading)
        <div class="{{ $headingClasses }}" aria-hidden="true">{{ $heading }}</div>
    @endif

    {{ $slot }}
</div>
