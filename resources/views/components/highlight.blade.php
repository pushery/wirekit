{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'query' => null,
    'as' => 'span',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('highlight', $attributes->getAttributes());

    $classes = WireKit::resolveClasses('highlight', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    $markClasses = implode(' ', [
        'bg-[var(--color-wk-warning-bg,oklch(0.905_0.093_102.1))]',
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
        'px-0.5',
    ]);
@endphp

@if($query)
    <{{ $as }} {{ $attributes->class([$classes]) }}>
        @php
            $content = (string) $slot;
            // Escape the query for regex, then split the content around matches
            $escaped = preg_quote($query, '/');
            $parts = preg_split("/({$escaped})/i", $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        @endphp
        @foreach($parts as $part)
            @if(mb_strtolower($part) === mb_strtolower($query))
                <mark class="{{ $markClasses }}">{{ $part }}</mark>
            @else
                {{ $part }}
            @endif
        @endforeach
    </{{ $as }}>
@else
    <{{ $as }} {{ $attributes->class([$classes]) }}>{{ $slot }}</{{ $as }}>
@endif