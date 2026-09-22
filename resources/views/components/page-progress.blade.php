{{-- optimistic-ui: n/a — client-only
     Its state is how long the page has been waiting for a request. No server owns
     that, so there is nothing to anticipate and nothing to roll back. --}}
@props([
    'height' => 'md',
    'intent' => 'primary', // primary | neutral | success | warning | danger | info | auto
    // Milliseconds of waiting before the bar appears at all. Most requests finish
    // inside this window and show nothing, which is the point: a bar that blinks on
    // every click is noise, and noise is what stops the reader noticing the one
    // request that really is slow.
    'showAfter' => 180,
    // The percentage the easing approaches while it waits. It is a ceiling rather
    // than a destination — a round trip has no percentage, and 100 has to keep
    // meaning "finished" instead of "the animation ran out".
    'ceiling' => 92,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('page-progress', $attributes->getAttributes());

    // Page-progress — a viewport-pinned bar that reports the request the reader is
    // waiting on. Mount it ONCE per layout. It takes no per-screen opt-in, which is
    // the whole design: the factory hooks Livewire's message lifecycle and the
    // navigate events, so a screen built tomorrow is covered without anyone
    // remembering to add anything to it.
    //
    // It is NOT `progress`, and the difference is the axis rather than the look:
    // `progress` shows a value somebody knows, this shows waiting that nobody has
    // measured. It is NOT `reading-progress` either — that one is driven by scroll
    // position and says where you are, not that something is in flight.

    $heightToken = match ($height) {
        'sm' => 'var(--page-progress-height-sm)',
        'lg' => 'var(--page-progress-height-lg)',
        'md' => 'var(--page-progress-height-md)',
        default => WireKit::validateProp('page-progress', 'height', (string) $height, ['sm', 'md', 'lg']),
    };

    // Every intent honors the --page-progress-fill override, which is what a
    // developer sets in :root {} to retint the bar theme-wide without touching a
    // call site. 'info' aliases 'primary', the same visual-synonym the alert family
    // uses. 'auto' falls back to currentColor for an embedded context where the bar
    // should take the color of the text around it.
    $fillColor = match ($intent) {
        'success' => 'var(--page-progress-fill, var(--color-wk-success))',
        'warning' => 'var(--page-progress-fill, var(--color-wk-warning))',
        'danger' => 'var(--page-progress-fill, var(--color-wk-danger))',
        'neutral' => 'var(--page-progress-fill, var(--color-wk-text-muted))',
        'auto' => 'var(--page-progress-fill, currentColor)',
        'primary', 'info' => 'var(--page-progress-fill, var(--color-wk-accent))',
        default => WireKit::validateProp(
            'page-progress',
            'intent',
            (string) $intent,
            ['primary', 'neutral', 'success', 'warning', 'danger', 'info', 'auto']
        ),
    };

    // Marker class — reduced-motion and print rules in dist/wirekit.css scope to it,
    // and so does the RTL mirror of the fill's anchor.
    $rootClass = WireKit::resolveClasses('page-progress', 'base', 'wk-page-progress', $scope);
@endphp

{{-- `aria-hidden` is a decision, not an omission. The bar carries no text, a
     navigation announces its own new page on arrival, and a number creeping
     towards a ceiling is not something anyone can act on. Saying "loading" on
     every keystroke would be noise in the one channel that cannot be skimmed. --}}
<div
    x-data="wirekitPageProgress({
        showAfter: {{ (int) $showAfter }},
        ceiling: {{ (int) $ceiling }},
    })"
    aria-hidden="true"
    {{-- The positioning is inline as well as in the class list, for the same reason the
         reading bar states: in a context where the developer's Tailwind compile never
         sees these arbitrary values — a sandboxed iframe, a standalone HTML page, an
         extension surface — the wrapper would otherwise fall back into document flow
         and start affecting the height it is supposed to float over. The tokens stay
         theme-aware either way.

         ⚠️ It does NOT carry `--wk-scrollbar-inset`, and that is deliberate rather than
         forgotten. That token exists for a fixed surface that keeps a GAP from the inline
         edge, where a classic scrollbar eats into the gap. This bar keeps no gap — it is
         full bleed, so running under the gutter is where it belongs. The five components
         that do take the inset all sit inset from an edge. 

         It DOES take `--wk-strip-inset`, which is the other edge and a different question: a
         strip above the page is browser chrome, and the bar reports the application's request,
         so it runs along the top of the application — directly under the strip, not across it. --}}
    {{ $attributes
        ->merge(['style' => 'position: fixed; inset-block-start: var(--wk-strip-inset, 0px); inset-inline: 0; z-index: var(--z-wk-tooltip); pointer-events: none; height: '.$heightToken.';'])
        ->class([$rootClass]) }}
>
    {{-- ⚠️ THE ANCHOR IS DECLARED TWICE AND MIRRORED ONCE, AND BOTH HALVES ARE LOAD-BEARING.
         A bar that grows from the start edge is direction-encoded: under `dir="rtl"` the
         reader travels the other way while a scaleX anchored at the physical start still
         grows rightward. `transform-origin` has no logical keyword in any shipped browser,
         so the mirror is a `[dir="rtl"]` rule in the stylesheet — the same shape the reading
         bar's anchor uses, and it needs `!important` there because this inline declaration
         outranks a stylesheet rule.
         The inline copy is unavoidable: the fill color varies per render and has to be
         inline anyway, and Alpine's object-form style binding MERGES with static declarations
         rather than replacing them, so the anchor survives every reactive update. --}}
    <div
        x-bind:style="fillStyle()"
        class="wk-page-progress__fill h-full"
        style="height: 100%; width: 100%; transform: scaleX(0); transform-origin: left center; opacity: 0; background-color: {{ $fillColor }}; transition: transform var(--transition-wk-duration) ease-out, opacity calc(var(--transition-wk-duration) / 2) linear;"
    ></div>
</div>
