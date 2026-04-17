@props([
    'paginator' => null,
    'variant' => config('wirekit.components.pagination.variant', 'full'), // full | simple | mini
    'justify' => config('wirekit.components.pagination.justify', 'between'), // between | center | end | start
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Bail early if the paginator is empty or missing — avoids rendering an empty nav
    if (! $paginator || ! method_exists($paginator, 'hasPages') || ! $paginator->hasPages()) {
        return;
    }

    // Flex justification — controls how summary text and page buttons are spread.
    // 'between' (default) pushes summary left + buttons right.
    // 'center'/'start'/'end' align all controls together.
    $justifyClass = match ($justify) {
        'center' => 'justify-center',
        'end'    => 'justify-end',
        'start'  => 'justify-start',
        default  => 'justify-between',
    };

    // Base container classes — w-full ensures justify-* has room to spread.
    $navClasses = WireKit::resolveClasses('pagination', 'base', implode(' ', [
        'flex items-center w-full gap-2',
        $justifyClass,
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-sm)]',
    ]), $scope);

    // Shared control button styles (used for prev/next/numbered page links)
    $buttonBase = implode(' ', [
        'inline-flex items-center justify-center',
        'h-[var(--size-wk-sm)] min-w-[var(--size-wk-sm)]',
        'px-[var(--padding-wk-x-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'text-[var(--color-wk-text)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'hover:border-[var(--color-wk-border-hover)]',
        'focus:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
    ]);

    // Disabled state for prev/next at list boundaries
    $buttonDisabled = implode(' ', [
        'inline-flex items-center justify-center',
        'h-[var(--size-wk-sm)] min-w-[var(--size-wk-sm)]',
        'px-[var(--padding-wk-x-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-subtle)]',
        'bg-[var(--color-wk-bg-subtle)]',
        'text-[var(--color-wk-text-subtle)]',
        'cursor-not-allowed',
        'opacity-[var(--opacity-wk-disabled)]',
    ]);

    // Active page highlight — uses accent color for strong visual anchor
    $buttonActive = implode(' ', [
        'inline-flex items-center justify-center',
        'h-[var(--size-wk-sm)] min-w-[var(--size-wk-sm)]',
        'px-[var(--padding-wk-x-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-accent)]',
        'bg-[var(--color-wk-accent)]',
        'text-[var(--color-wk-accent-fg)]',
        'font-[number:var(--font-wk-heading-weight)]',
    ]);
@endphp

<nav role="navigation" aria-label="Pagination" {{ $attributes->class([$navClasses]) }}>
    @if($variant === 'simple' || $variant === 'mini')
        {{-- Simple: prev + next only (optionally with a "page X of Y" label) --}}
        <div class="flex items-center gap-2">
            @if($paginator->onFirstPage())
                <span class="{{ $buttonDisabled }}" aria-hidden="true">&larr; Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $buttonBase }}">&larr; Previous</a>
            @endif
        </div>

        @if($variant === 'simple')
            {{-- Centered "Page X of Y" label — hidden on mini variant for tighter footprint --}}
            <span class="text-[var(--color-wk-text-muted)]">
                Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}
            </span>
        @endif

        <div class="flex items-center gap-2">
            @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $buttonBase }}">Next &rarr;</a>
            @else
                <span class="{{ $buttonDisabled }}" aria-hidden="true">Next &rarr;</span>
            @endif
        </div>
    @else
        {{-- Full: prev + numbered pages + next (standard Laravel paginator links) --}}
        <div class="text-[var(--color-wk-text-muted)]">
            Showing
            <span class="font-[number:var(--font-wk-heading-weight)] text-[var(--color-wk-text)]">{{ $paginator->firstItem() ?? 0 }}</span>
            to
            <span class="font-[number:var(--font-wk-heading-weight)] text-[var(--color-wk-text)]">{{ $paginator->lastItem() ?? 0 }}</span>
            of
            <span class="font-[number:var(--font-wk-heading-weight)] text-[var(--color-wk-text)]">{{ $paginator->total() }}</span>
            results
        </div>

        <div class="flex items-center gap-1">
            @if($paginator->onFirstPage())
                <span class="{{ $buttonDisabled }}" aria-hidden="true" aria-label="Previous">&larr;</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $buttonBase }}" aria-label="Previous">&larr;</a>
            @endif

            {{-- Numbered links: linkCollection() returns {url, label, active} per entry.
                 We skip the framework-generated prev/next (we render our own above) by
                 filtering out entries whose label matches &laquo; / &raquo; glyphs. --}}
            @foreach($paginator->linkCollection()->slice(1, -1) as $link)
                @if($link['url'] === null)
                    {{-- null url = separator (ellipsis) --}}
                    <span class="{{ $buttonDisabled }}" aria-hidden="true">{!! $link['label'] !!}</span>
                @elseif($link['active'])
                    <span class="{{ $buttonActive }}" aria-current="page">{!! $link['label'] !!}</span>
                @else
                    <a href="{{ $link['url'] }}" class="{{ $buttonBase }}" aria-label="Go to page {{ $link['label'] }}">{!! $link['label'] !!}</a>
                @endif
            @endforeach

            @if($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $buttonBase }}" aria-label="Next">&rarr;</a>
            @else
                <span class="{{ $buttonDisabled }}" aria-hidden="true" aria-label="Next">&rarr;</span>
            @endif
        </div>
    @endif
</nav>
