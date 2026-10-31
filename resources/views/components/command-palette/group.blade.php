{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'heading' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('command-palette.group', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // A group shows while one of its entries does: on its own level, and on a page the palette
    // filters, while an entry matches the search. A caller's own `x-show` still applies.
    $callerShow = \Pushery\WireKit\Support\AttributeText::get($attributes, 'x-show');
    $show = is_string($callerShow) && trim($callerShow) !== ''
        ? 'groupShows($el) && ('.$callerShow.')'
        : 'groupShows($el)';
    $attributes = $attributes->except(['x-show']);

    // Command group — optional heading + grouped items.
    $headingClasses = WireKit::resolveClasses('command-palette.group', 'heading', implode(' ', [
        'px-[var(--padding-wk-x-md)]',
        'py-[var(--padding-wk-y-xs)]',
        'text-[length:var(--text-wk-xs)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[color:var(--color-wk-text-muted)]',
        'uppercase tracking-wider',
    ]), $scope);
@endphp

<div role="group" x-show="{{ $show }}" @if($heading && ! \Pushery\WireKit\Support\CallerAttribute::writes($attributes, 'aria-label') && ! \Pushery\WireKit\Support\CallerAttribute::writes($attributes, 'aria-labelledby')) aria-label="{{ \Pushery\WireKit\Support\SlotContent::textOf($heading) }}" @endif {{ $attributes->except('role') }}>
    @if($heading)
        <div class="{{ $headingClasses }}" aria-hidden="true">{{ $heading }}</div>
    @endif

    {{ $slot }}
</div>
