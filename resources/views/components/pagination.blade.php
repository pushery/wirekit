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
    // `flex-wrap` lets the summary + button-row wrap onto multiple lines
    // on narrow viewports instead of overflowing the page horizontally.
    $navClasses = WireKit::resolveClasses('pagination', 'base', implode(' ', [
        'flex flex-wrap items-center w-full gap-2',
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
        'text-[color:var(--color-wk-text)]',
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
        'text-[color:var(--color-wk-text-subtle)]',
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
        'text-[color:var(--color-wk-accent-fg)]',
        'font-[number:var(--font-wk-heading-weight)]',
    ]);

    // Absolute-ize paginator URLs. Under Livewire's WithPagination the current-path
    // resolver returns a RELATIVE path, so previousPageUrl()/nextPageUrl()/link urls
    // come out relative and 404 on a nested route. url()->to() host-qualifies them and
    // is idempotent on already-absolute URLs (WIRE-122).
    $abs = static fn (?string $u): ?string => $u === null ? null : url()->to($u);

    // The nav's accessible name. __('Pagination') is intended as a JSON string key, but
    // on a case-insensitive filesystem it ALSO matches Laravel's own pagination.php GROUP
    // lang file and resolves to that ARRAY — so guard it, or a bare (untranslated) render
    // echoes an array into aria-label and crashes. A real JSON translation still wins
    // (the translator checks JSON before the group), so localization is unaffected.
    $navLabel = __('Pagination');
    $navLabel = is_string($navLabel) ? $navLabel : 'Pagination';
@endphp

<nav role="navigation" aria-label="{{ $navLabel }}" {{ $attributes->class([$navClasses]) }}>
    @if($variant === 'simple' || $variant === 'mini')
        {{-- Simple: prev + next only (optionally with a "page X of Y" label) --}}
        <div class="flex items-center gap-2">
            @if($paginator->onFirstPage())
                <span class="{{ $buttonDisabled }}" aria-hidden="true">&larr; {{ __('Previous') }}</span>
            @else
                <a href="{{ $abs($paginator->previousPageUrl()) }}" rel="prev" class="{{ $buttonBase }}">&larr; {{ __('Previous') }}</a>
            @endif
        </div>

        @if($variant === 'simple')
            {{-- Centered "Page X of Y" label — hidden on mini variant for tighter footprint --}}
            <span class="text-[color:var(--color-wk-text-muted)]">
                {{ __('Page :current of :last', [
                    'current' => $paginator->currentPage(),
                    'last' => $paginator->lastPage(),
                ]) }}
            </span>
        @endif

        <div class="flex items-center gap-2">
            @if($paginator->hasMorePages())
                <a href="{{ $abs($paginator->nextPageUrl()) }}" rel="next" class="{{ $buttonBase }}">{{ __('Next') }} &rarr;</a>
            @else
                <span class="{{ $buttonDisabled }}" aria-hidden="true">{{ __('Next') }} &rarr;</span>
            @endif
        </div>
    @else
        {{-- Full: prev + numbered pages + next (standard Laravel paginator links) --}}
        {{-- ONE translatable sentence, not four fragments (WIRE-177). The earlier
             form concatenated __('Showing') / __('to') / __('of') / __('results')
             around the numbers, which handed a translator four context-free words
             ("to" is untranslatable without knowing it sits between two numbers)
             and locked the output into English word order — a locale that puts the
             total first simply could not be expressed.

             The numbers keep their emphasis by passing pre-built markup as the
             placeholder values, so the translator moves the placeholders freely
             and the styling travels with them. {!! !!} is required for that, and
             is safe here: every value is an integer straight off the paginator,
             never developer input, and each is escaped before being wrapped. --}}
        @php
            $emphasize = fn (int $value): string => '<span class="font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">'.e((string) $value).'</span>';

            // The markup is substituted AFTER translation, never passed through
            // it. Laravel's translator also honors :Placeholder and :PLACEHOLDER
            // as case variants, applying ucfirst / strtoupper to the value — and
            // an uppercased value here would wreck the markup, since Tailwind
            // classes and CSS custom-property names are case-sensitive. A
            // translator writing ":TOTAL" for emphasis would silently lose the
            // number styling. All-caps sentinels are immune: ucfirst and
            // strtoupper both leave them unchanged, whichever case the
            // translation uses.
            $summary = __('Showing :first to :last of :total results', [
                'first' => 'WKPAGEFIRST',
                'last' => 'WKPAGELAST',
                'total' => 'WKPAGETOTAL',
            ]);

            $summary = str_replace(
                ['WKPAGEFIRST', 'WKPAGELAST', 'WKPAGETOTAL'],
                [
                    $emphasize($paginator->firstItem() ?? 0),
                    $emphasize($paginator->lastItem() ?? 0),
                    $emphasize($paginator->total()),
                ],
                $summary,
            );
        @endphp
        <div class="text-[color:var(--color-wk-text-muted)]">
            {!! $summary !!}
        </div>

        <div class="flex flex-wrap items-center gap-1">
            @if($paginator->onFirstPage())
                <span class="{{ $buttonDisabled }}" aria-hidden="true" aria-label="{{ __('Previous') }}">&larr;</span>
            @else
                <a href="{{ $abs($paginator->previousPageUrl()) }}" rel="prev" class="{{ $buttonBase }}" aria-label="{{ __('Previous') }}">&larr;</a>
            @endif

            {{-- Numbered links: linkCollection() returns {url, label, active} per entry.
                 We skip the framework-generated prev/next (we render our own above) by
                 filtering out entries whose label matches &laquo; / &raquo; glyphs.

                 The `label` field is unescaped via {!! !!} below — safe because
                 Laravel's paginator constructs `label` as a numeric page-index
                 string OR a pre-encoded HTML entity (`&laquo;` / `&raquo;` /
                 `…`). The value never originates from user input or query
                 parameters — it's generated entirely from `$paginator->currentPage()`
                 and the framework's internal range calculation. If a downstream
                 developer ever extends the paginator to produce user-influenced
                 labels, the unescaped render becomes an XSS vector and this
                 emission must be wrapped in `e($link['label'])` or equivalent. --}}
            @foreach($paginator->linkCollection()->slice(1, -1) as $link)
                @if($link['url'] === null)
                    {{-- null url = separator (ellipsis) --}}
                    <span class="{{ $buttonDisabled }}" aria-hidden="true">{!! $link['label'] !!}</span>
                @elseif($link['active'])
                    <span class="{{ $buttonActive }}" aria-current="page">{!! $link['label'] !!}</span>
                @else
                    <a href="{{ $abs($link['url']) }}" class="{{ $buttonBase }}" aria-label="{{ __('Go to page :page', ['page' => $link['label']]) }}">{!! $link['label'] !!}</a>
                @endif
            @endforeach

            @if($paginator->hasMorePages())
                <a href="{{ $abs($paginator->nextPageUrl()) }}" rel="next" class="{{ $buttonBase }}" aria-label="{{ __('Next') }}">&rarr;</a>
            @else
                <span class="{{ $buttonDisabled }}" aria-hidden="true" aria-label="{{ __('Next') }}">&rarr;</span>
            @endif
        </div>
    @endif
</nav>
