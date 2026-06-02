@props([
    // Activity kind — drives the leading dot colour. The built-in set is
    // commit / merge / deploy / comment / system / user; extend or recolour
    // via `config('wirekit.components.activity-row.kinds')`. Unknown kinds
    // fall back to the muted dot (no error — the map is intentionally open).
    'kind' => 'system',
    // Optional actor name, rendered bold at the start of the line.
    'actor' => null,
    // Optional relative timestamp ("2 hours ago"), right-aligned + muted.
    'timestamp' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // kind → dot-colour token. Developer config is merged over the defaults,
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
         distinguishable without colour perception (WCAG 1.4.1). --}}
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

        {{-- Accessible kind label — the textual equivalent of the dot colour. --}}
        <span class="sr-only">{{ $kind }}</span>
    </div>
</div>
