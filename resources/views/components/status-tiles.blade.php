@props([
    // The fleet: a list of entities to render as status tiles. Each entry:
    //   ['key' => 'app-1', 'label' => __('shop.example'), 'intent' => 'danger', 'href' => '…', 'meta' => '2 issues']
    // intent ∈ success | warning | danger | info | neutral (anything else → neutral).
    // 'href' makes the tile a keyboard-operable link; 'meta' is an optional caption.
    'items' => [],
    // Show a count-per-intent legend above the grid (only intents present are listed).
    'legend' => false,
    // Responsive grid column spec, forwarded verbatim to the grid component (never
    // interpolated here — the literal-class safelist lives in grid).
    'columns' => '2 sm:3 md:4 lg:6',
    'gap' => 'sm',
    // Promote the status word from the screen-reader-only span into a visible,
    // intent-tinted caption on every tile. Off by default so existing tiles are
    // unchanged.
    'showStatus' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $legend = BooleanProp::from($legend, false);
    $showStatus = BooleanProp::from($showStatus, false);

    // Status-Tiles — N entities as colored status tiles, one glance (a fleet light).
    // Each tile is colored by its intent AND carries a distinct icon SHAPE plus a
    // spoken status word, so status never depends on color alone — WCAG 1.4.1. The
    // soft tinted surface + 25%-tinted border mirror the badge component's soft
    // intents so the whole library reads with one visual grammar.

    $intents = ['success', 'warning', 'danger', 'info', 'neutral'];

    // Soft tile surface (background + border) per intent — the badge-soft treatment.
    $intentSurface = fn (string $intent): string => match ($intent) {
        'success' => 'bg-[color-mix(in_srgb,var(--color-wk-success)_12%,var(--color-wk-bg))] border-[color-mix(in_srgb,var(--color-wk-success)_25%,transparent)]',
        'warning' => 'bg-[color-mix(in_srgb,var(--color-wk-warning)_12%,var(--color-wk-bg))] border-[color-mix(in_srgb,var(--color-wk-warning)_25%,transparent)]',
        'danger' => 'bg-[color-mix(in_srgb,var(--color-wk-danger)_12%,var(--color-wk-bg))] border-[color-mix(in_srgb,var(--color-wk-danger)_25%,transparent)]',
        'info' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_8%,var(--color-wk-bg))] border-[color-mix(in_srgb,var(--color-wk-accent)_20%,transparent)]',
        default => 'bg-[var(--color-wk-bg-muted)] border-[var(--color-wk-border-subtle)]',
    };

    // Icon-tint color per intent (the -text variants clear ≥4.5:1 on the soft bg).
    $intentColor = fn (string $intent): string => match ($intent) {
        'success' => 'var(--color-wk-success-text)',
        'warning' => 'var(--color-wk-warning-text)',
        'danger' => 'var(--color-wk-danger-text)',
        'info' => 'var(--color-wk-info-text)',
        default => 'var(--color-wk-text-muted)',
    };

    // A DISTINCT icon shape per intent — the shape (not the color) is what survives a
    // monochrome (color-stripped) render, so a colorblind reader still tells the states apart.
    $intentIcon = fn (string $intent): string => match ($intent) {
        'success' => '<svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.7a1 1 0 00-1.4-1.4L9 10.2 7.7 8.9a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>',
        'warning' => '<svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.5 2.9c.7-1.2 2.4-1.2 3 0l6 10.4c.7 1.1-.2 2.6-1.5 2.6H4c-1.3 0-2.2-1.5-1.5-2.6L8.5 2.9zM10 6a1 1 0 00-1 1v3a1 1 0 002 0V7a1 1 0 00-1-1zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>',
        'danger' => '<svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.7 7.3a1 1 0 00-1.4 1.4L8.6 10l-1.3 1.3a1 1 0 101.4 1.4L10 11.4l1.3 1.3a1 1 0 001.4-1.4L11.4 10l1.3-1.3a1 1 0 10-1.4-1.4L10 8.6 8.7 7.3z" clip-rule="evenodd"/></svg>',
        'info' => '<svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9 8a1 1 0 012 0v5a1 1 0 01-2 0V8zm1-4a1 1 0 100 2 1 1 0 000-2z" clip-rule="evenodd"/></svg>',
        default => '<svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM6 9a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"/></svg>',
    };

    // The spoken status word (screen readers + legend). Translatable.
    $intentWord = fn (string $intent): string => match ($intent) {
        'success' => __('OK'),
        'warning' => __('Warning'),
        'danger' => __('Critical'),
        'info' => __('Info'),
        default => __('Unknown'),
    };

    // Shared tile-box classes; link tiles additionally get hover elevation + a focus ring.
    // justify-center: the grid stretches every tile to the row's tallest (the ones with
    // a `meta` caption), so a tile WITHOUT meta would otherwise top-align its single line
    // and leave a lopsided gap below. Centering the content balances the vertical spacing
    // in every tile regardless of whether it has a caption.
    // The tile is a BLOCK outer carrying the real padding, wrapping an inner flex
    // centerer ($tileInner). This is deliberate: a flex column used directly as a
    // stretched grid item sizes the auto row to its CONTENT height and then border-box
    // squeezes the vertical padding to zero, so a two-line tile jammed its label against
    // the top edge (a magic min-height "fixed" it only for one font size and broke at
    // another). A block outer's padding IS counted in the grid row, so it always applies
    // and adapts to the real content; the inner flex then vertically centers, balancing
    // both the two-line tiles and the single-line (no-caption) ones at the same row height.
    // h-full so the tile fills the listitem wrapper the grid now stretches (the
    // wrapper, not the tile, is the grid item since role="listitem" moved
    // off the <a> to preserve its native link role).
    $tileBase = 'block h-full rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] px-[var(--padding-wk-x-sm)] py-1';
    $tileInner = 'flex h-full flex-col justify-center gap-[var(--gap-wk-xs)]';
    $tileLink = ' transition-shadow hover:shadow-[var(--shadow-wk-sm)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]';

    // Normalize + pre-resolve every tile so the markup below stays pure data (no
    // closures / dynamic element tags — those break Blade's slot compilation), and
    // tally each intent for the legend.
    $tiles = [];
    $counts = array_fill_keys($intents, 0);
    foreach ($items as $item) {
        $intent = in_array($item['intent'] ?? 'neutral', $intents, true) ? $item['intent'] : 'neutral';
        $href = ($item['href'] ?? null) ?: null;
        $tiles[] = [
            'key' => (string) ($item['key'] ?? ''),
            'label' => (string) ($item['label'] ?? ''),
            'href' => $href,
            'meta' => isset($item['meta']) ? (string) $item['meta'] : null,
            'color' => $intentColor($intent),
            'icon' => $intentIcon($intent),
            'statusText' => __('Status: :status', ['status' => $intentWord($intent)]),
            // The blank status word (no "Status:" prefix) for the visible caption.
            'statusWord' => $intentWord($intent),
            // NB the space before $intentSurface: without it the last $tileBase class
            // (py-1) glued onto the first surface class (bg-…), silently killing the
            // tile's vertical padding — the real cause of the "no padding" bug.
            'class' => $tileBase.' '.$intentSurface($intent).($href !== null ? $tileLink : ''),
        ];
        $counts[$intent]++;
    }

    $wrapperClasses = WireKit::resolveClasses('status-tiles', 'base', 'flex flex-col gap-[var(--gap-wk-md)]', $scope);
@endphp

<div {{ $attributes->only('class')->class([$wrapperClasses]) }}>
    @if($legend && count($tiles) > 0)
        {{-- Legend: intent word + count + shaped icon (never color alone). --}}
        <div class="flex flex-wrap gap-x-[var(--gap-wk-md)] gap-y-[var(--gap-wk-xs)]">
            @foreach($counts as $intent => $n)
                @if($n > 0)
                    <span class="inline-flex items-center gap-[var(--gap-wk-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">
                        <span style="color: {{ $intentColor($intent) }}">{!! $intentIcon($intent) !!}</span>
                        {{ $intentWord($intent) }} ({{ $n }})
                    </span>
                @endif
            @endforeach
        </div>
    @endif

    {{-- The tile grid IS the ARIA list (role on the grid element itself, so the
         tiles are its direct listitem children). The caller's aria-label + other
         attributes forward onto it. --}}
    <x-wirekit::grid role="list" :cols="$columns" :gap="$gap" {{ $attributes->except('class') }}>
        @foreach($tiles as $tile)
            {{-- The listitem role sits on the WRAPPER, never on the <a> — an explicit
                 role on a link REPLACES its implicit `link` role, so the tile would
                 vanish from the link list and from "step through all links" navigation. Both branches share this wrapper so the linked and
                 non-linked tiles are structurally identical grid items. --}}
            <div role="listitem" @if($tile['key'] !== '') data-key="{{ $tile['key'] }}" @endif>
                @if($tile['href'] !== null)
                    <a href="{{ $tile['href'] }}" title="{{ $tile['label'] }}" class="{{ $tile['class'] }}">
                        <span class="{{ $tileInner }}">
                            <span class="flex items-center gap-[var(--gap-wk-xs)] min-w-0">
                                <span class="shrink-0" style="color: {{ $tile['color'] }}">{!! $tile['icon'] !!}</span>
                                <span class="truncate text-[length:var(--text-wk-sm)] font-[family-name:var(--font-wk-sans)] text-[color:var(--color-wk-text)]">{{ $tile['label'] }}</span>
                            </span>
                            @if($tile['meta'] !== null)
                                <span class="truncate text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $tile['meta'] }}</span>
                            @endif
                            @if($showStatus)
                                <span class="truncate text-[length:var(--text-wk-xs)] font-[family-name:var(--font-wk-sans)]" style="color: {{ $tile['color'] }}"><span class="sr-only">{{ __('Status:') }}</span>{{ $tile['statusWord'] }}</span>
                            @else
                                <span class="sr-only">{{ $tile['statusText'] }}</span>
                            @endif
                        </span>
                    </a>
                @else
                    <div title="{{ $tile['label'] }}" class="{{ $tile['class'] }}">
                        <span class="{{ $tileInner }}">
                            <span class="flex items-center gap-[var(--gap-wk-xs)] min-w-0">
                                <span class="shrink-0" style="color: {{ $tile['color'] }}">{!! $tile['icon'] !!}</span>
                                <span class="truncate text-[length:var(--text-wk-sm)] font-[family-name:var(--font-wk-sans)] text-[color:var(--color-wk-text)]">{{ $tile['label'] }}</span>
                            </span>
                            @if($tile['meta'] !== null)
                                <span class="truncate text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $tile['meta'] }}</span>
                            @endif
                            @if($showStatus)
                                <span class="truncate text-[length:var(--text-wk-xs)] font-[family-name:var(--font-wk-sans)]" style="color: {{ $tile['color'] }}"><span class="sr-only">{{ __('Status:') }}</span>{{ $tile['statusWord'] }}</span>
                            @else
                                <span class="sr-only">{{ $tile['statusText'] }}</span>
                            @endif
                        </span>
                    </div>
                @endif
            </div>
        @endforeach
    </x-wirekit::grid>
</div>
