{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    // Activity kind — drives the leading dot color. The built-in set is
    // commit / merge / deploy / comment / system / user; extend or recolor
    // via `config('wirekit.components.activity-row.kinds')`. Unknown kinds
    // fall back to the muted dot (no error — the map is intentionally open).
    'kind' => 'system',
    // What a screen reader hears for the kind, in place of the dot's color.
    // The six built-in kinds are announced from the translation catalog; any
    // other kind is announced by its key unless this names it.
    'kindLabel' => null,
    // Optional actor name, rendered bold at the start of the line.
    'actor' => null,
    // Optional relative timestamp ("2 hours ago"), right-aligned + muted.
    'timestamp' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('activity-row', $attributes->getAttributes());

    // kind → dot-color token. Developer config is merged over the defaults,
    // so an app can add `release => var(--color-wk-warning)` etc. without a
    // component override.
    $kinds = array_merge([
        'commit' => 'var(--color-wk-accent)',
        'merge' => 'var(--color-wk-accent)',
        'deploy' => 'var(--color-wk-success)',
        'comment' => 'var(--color-wk-accent)',
        'system' => 'var(--color-wk-text-muted)',
        'user' => 'var(--color-wk-accent)',
    ], (array) config('wirekit.components.activity-row.kinds', []));
    $dotColor = $kinds[$kind] ?? 'var(--color-wk-text-muted)';

    // The kind's spoken name — the text the dot's color stands in for (WCAG 1.4.1). A caller's
    // `kindLabel` wins. The built-in kinds come from the catalog, so a page in another language
    // announces its own word instead of the key, which used to be the one untranslated word in
    // the row. Any other kind falls back to its key: the one name the developer gave it. The keys
    // are written out literally, so a search for a catalog entry finds where it is used.
    $kindText = $kindLabel ?? match ($kind) {
        'commit' => __('wirekit::Commit'),
        'merge' => __('wirekit::Merge'),
        'deploy' => __('wirekit::Deployment'),
        'comment' => __('wirekit::Comment'),
        'system' => __('wirekit::System'),
        'user' => __('wirekit::User'),
        default => $kind,
    };

    $classes = WireKit::resolveClasses('activity-row', 'base', implode(' ', [
        'flex items-start gap-3',
        'py-[var(--padding-wk-y-sm)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-sm)]',
    ]), $scope);
@endphp

<div {{ $attributes->class([$classes]) }}>
    {{-- Leading kind dot. Decorative (aria-hidden) — the kind is conveyed
         textually by the sr-only span below, so the activity is
         distinguishable without color perception (WCAG 1.4.1). --}}
    <span
        aria-hidden="true"
        class="mt-1.5 inline-block h-2 w-2 shrink-0 rounded-full"
        style="background-color: {{ $dotColor }};"
    ></span>

    <div class="min-w-0 flex-1">
        <div class="flex items-baseline justify-between gap-2">
            <span class="min-w-0 text-[color:var(--color-wk-text)]">
                @if($actor)<span class="font-[number:var(--font-wk-heading-weight)]">{{ $actor }}</span> @endif{{ $slot }}
            </span>
            @if($timestamp)
                <span class="shrink-0 text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)] tabular-nums">
                    {{ $timestamp }}
                </span>
            @endif
        </div>

        @isset($badge)
            <div class="mt-1 flex flex-wrap items-center gap-1">
                {{ $badge }}
            </div>
        @endisset

        {{-- Accessible kind label — the textual equivalent of the dot color. --}}
        <span class="sr-only">{{ $kindText }}</span>

        {{-- Block content under the line: a list of changed fields, a quoted comment. The default
             slot sits in an inline <span> beside the actor and cannot hold a block, so this is a
             <div> in the content column, flush with the text above it. --}}
        @isset($detail)
            <div class="mt-1 min-w-0">
                {{ $detail }}
            </div>
        @endisset
    </div>
</div>
