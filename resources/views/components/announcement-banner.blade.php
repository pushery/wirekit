{{-- optimistic-ui: n/a — passthrough
     Same as alert. --}}
@props([
    // Where the bar lives. Sticky is opt-in — a bar that follows the reader is
    // a stronger claim on the viewport than most announcements deserve.
    'position' => 'top',
    'sticky' => false,
    // A strip ABOVE the page: pinned to the top of the viewport, one line tall, never
    // dismissible — for the line that says "this is staging", which has to be visible on every
    // screen and must not change the application underneath it.
    //
    // It sets `--wk-strip-inset` on :root, and every surface that pins itself to the top of the
    // viewport or takes the viewport's height folds that in — `app-shell viewport` gives the
    // strip its height back, sticky page bars stick BELOW it. So the application moves down by
    // exactly the strip and changes nothing else, whichever shell it uses.
    //
    // ⚠ ONE LINE ON PURPOSE, and it is what makes "changes nothing" true from the first frame.
    // A strip whose height a script had to measure would let the page jump once it ran. A fixed
    // height lets the stylesheet know it before anything paints. Text that does not fit is cut
    // on screen and stays whole in the accessible name; an announcement that needs several
    // lines belongs in an ordinary banner, which flows with the page.
    'strip' => false,
    // Semantic tint. `promo` is the marketing default (accent), the state
    // intents carry the usual meaning.
    'intent' => 'promo',
    // How strongly the intent is applied: `soft` tints the bar (what it has always done),
    // `solid` fills it with the intent's color and its own contrast-paired text. The same two
    // treatments `badge` has, with the same names — a strip saying "staging" wants `solid`.
    'surface' => 'soft',
    // A HUE instead of an intent, 0-360: `250` is blue, `300` violet. For the environment line,
    // where the color identifies the instance rather than a state — `warning` on staging would
    // read as a warning about something that is not one. Independent of the theme's accent,
    // which is monochrome by default, so `intent="info"` is not blue out of the box.
    //
    // Lightness and chroma come from `--wk-banner-hue-l` / `--wk-banner-hue-c`, measured to
    // keep AA against the text at every hue. The hue itself can also come from the stylesheet
    // — `.env-staging { --wk-banner-hue: 300 }` — which survives a Content-Security-Policy that
    // strips inline styles; without either, it follows `--theme-hue` where a preset sets one.
    'hue' => null,
    // A stable key makes the dismissal STICK across page loads. Without one the
    // bar is not dismissible unless `persist` is explicitly turned off (below) —
    // a close button that silently forgets is worse than no close button, so
    // forgetfulness has to be a deliberate choice, never an accident.
    'dismissKey' => null,
    // Whether a dismissal is remembered across page loads (localStorage). On by
    // default. Turn it OFF for a session-scoped notice that should reappear next
    // visit — and it is what makes a live demo resettable, since a re-mount then
    // brings the bar back instead of reading a stored "dismissed" flag.
    'persist' => true,
    // Whether a close button exists at all, stated rather than derived. Left null, it is derived
    // as it always was — and that derivation has a trap: `persist="false"` WITHOUT a key makes a
    // bar dismissible (session-only), so a developer turning persistence off to be safe gets a
    // close button they never asked for. `false` means never, whatever the other two props say.
    // A strip is never dismissible regardless.
    'dismissible' => null,
    // Accessible name for the region — and the LANDMARK waits for it.
    //
    // ⚠️ THE DEFAULT USED TO BE `__('wirekit::Announcement')`, WHICH MADE EVERY BANNER THE
    // SAME LANDMARK. `position` validates against top AND bottom, so a page with a promo bar
    // above and a notice below is a first-class composition — and it produced two
    // `role="region"` landmarks both called "Announcement". Somebody paging through landmarks
    // meets two regions with one name and cannot tell them apart, which is the failure the
    // house rule on named landmarks was written against — a component may not invent the name
    // that turns itself into one. Eight siblings in this catalog already gate the role on
    // `filled()`; this component was the outlier.
    //
    // Null, so the role is gated on the CALLER having supplied a name rather than on this file
    // being able to invent one. The dismiss button still gets a composed name below, because a
    // control needs one whether or not the banner is a landmark.
    'label' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('announcement-banner', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $sticky = BooleanProp::from($sticky, false);
    $persist = BooleanProp::from($persist, true);
    $strip = BooleanProp::from($strip, false);

    // Null keeps the derivation below; anything else is a statement and is normalized like the
    // other booleans, so `dismissible="false"` means false rather than the truthy string.
    $dismissibleStated = $dismissible === null ? null : BooleanProp::from($dismissible, true);

    $surfaceValue = in_array($surface, ['soft', 'solid'], true)
        ? $surface
        : WireKit::validateProp('announcement-banner', 'surface', (string) $surface, ['soft', 'solid']);

    // A number of degrees and nothing else, because it is interpolated into a style attribute.
    // Stated positively rather than by stripping, the same way `grid` guards `min`.
    // `theme` switches the hue tone on WITHOUT writing a value, so the hue comes from the
    // stylesheet (`--wk-banner-hue`) or the theme (`--theme-hue`) — the form that survives a
    // policy stripping inline styles, and the one a design system sets once for all banners.
    $hueMode = false;
    $hueValue = null;

    if ($hue !== null && $hue !== '') {
        if ($hue === 'theme') {
            $hueMode = true;
        } elseif (is_numeric($hue) && (float) $hue >= 0 && (float) $hue <= 360) {
            $hueMode = true;
            $hueValue = rtrim(rtrim(number_format((float) $hue, 2, '.', ''), '0'), '.');
        } else {
            WireKit::validateProp('announcement-banner', 'hue', (string) $hue, ['a number of degrees from 0 to 360 such as 250, or theme']);
        }
    }

    $positionValue = in_array($position, ['top', 'bottom'], true)
        ? $position
        : WireKit::validateProp('announcement-banner', 'position', $position, ['top', 'bottom']);

    $intentValue = in_array($intent, ['promo', 'info', 'success', 'warning', 'danger'], true)
        ? $intent
        : WireKit::validateProp('announcement-banner', 'intent', $intent, ['promo', 'info', 'success', 'warning', 'danger']);

    $hasKey = $dismissKey !== null && $dismissKey !== '';
    $persistValue = filter_var($persist, FILTER_VALIDATE_BOOLEAN);

    // The bar is dismissible when it can be dismissed: either it has a key (and
    // remembers) OR persistence was deliberately turned off (session-only). It
    // only PERSISTS when there is a key AND persist is on.
    //
    // A strip is never dismissible. A stated `dismissible` beats the derivation. Only when
    // neither applies is it derived, exactly as it always was.
    //
    // A boolean expression rather than a `match (true)`: this is a precedence between three
    // booleans, not a switch over an enum, and written as a match its last arm read as an enum
    // default returning a raw value — EnumPropsFallThroughTheValidatorTest cannot tell the two
    // shapes apart, and exempting the file would have unguarded every real enum arm in it.
    $isDismissible = ! $strip && ($dismissibleStated ?? ($hasKey || ! $persistValue));
    $persistsDismissal = $isDismissible && $hasKey && $persistValue;

    // A strip lives at the top and follows the reader; those are what it is, not options on it.
    $isSticky = $strip || filter_var($sticky, FILTER_VALIDATE_BOOLEAN);

    if ($strip) {
        $positionValue = 'top';
    }

    // Full literal class strings via match so the drift auditor can harvest them.
    //
    // A hue beats an intent, because it is the more specific statement. The formula sits on the
    // ELEMENT rather than in a :root token, and that is load-bearing: a custom property is
    // computed where it is declared, so a token holding `oklch(… var(--wk-banner-hue))` would be
    // resolved on :root, where no hue exists, and inherit as invalid. Only the constants are
    // tokens. The fallback chain ends at 264 (indigo) so a strip never paints transparent.
    $intentClasses = match (true) {
        $hueMode && $surfaceValue === 'solid' => 'bg-[oklch(var(--wk-banner-hue-l)_var(--wk-banner-hue-c)_var(--wk-banner-hue,var(--theme-hue,264)))] text-[color:var(--color-wk-banner-hue-fg)]',
        $hueMode => 'bg-[color-mix(in_srgb,oklch(var(--wk-banner-hue-l)_var(--wk-banner-hue-c)_var(--wk-banner-hue,var(--theme-hue,264)))_12%,var(--color-wk-bg-elevated))] text-[color:var(--color-wk-text)]',
        // `solid` is the badge's treatment, pair for pair: the intent's color as the fill and
        // its own contrast-paired foreground. `promo` was always a solid accent fill, so both
        // surfaces render it the same way and nothing about an existing promo bar changes.
        $surfaceValue === 'solid' => match ($intentValue) {
            'info' => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
            'success' => 'bg-[var(--color-wk-success)] text-[color:var(--color-wk-success-fg)]',
            'warning' => 'bg-[var(--color-wk-warning)] text-[color:var(--color-wk-warning-fg)]',
            'danger' => 'bg-[var(--color-wk-danger)] text-[color:var(--color-wk-danger-fg)]',
            default => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
        },
        default => match ($intentValue) {
            'info' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_12%,var(--color-wk-bg-elevated))] text-[color:var(--color-wk-text)]',
            'success' => 'bg-[color-mix(in_srgb,var(--color-wk-success)_12%,var(--color-wk-bg-elevated))] text-[color:var(--color-wk-text)]',
            'warning' => 'bg-[color-mix(in_srgb,var(--color-wk-warning)_12%,var(--color-wk-bg-elevated))] text-[color:var(--color-wk-text)]',
            'danger' => 'bg-[color-mix(in_srgb,var(--color-wk-danger)_12%,var(--color-wk-bg-elevated))] text-[color:var(--color-wk-text)]',
            default => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
        },
    };

    $stickyClasses = match (true) {
        // The strip is browser chrome, so it takes chrome's stacking level: above sticky page
        // content, below every dialog. A strip that stayed clickable over a modal backdrop
        // would break the modality the backdrop exists to enforce.
        $strip => 'sticky top-0 z-[var(--z-wk-chrome)] h-[var(--wk-strip-height)] py-0 font-[number:var(--font-wk-heading-weight)]',
        $isSticky && $positionValue === 'bottom' => 'sticky bottom-0 z-30',
        // An ordinary sticky bar sits below a strip; only the strip itself owns the edge.
        $isSticky => 'sticky top-[var(--wk-strip-inset,0px)] z-30',
        default => '',
    };

    // Page-edge component: the inline padding is the content-edge spine
    // (--padding-wk-x-lg), so the banner's text edge lines up with every other
    // page-chrome component instead of floating on its own margin.
    $classes = WireKit::resolveClasses('announcement-banner', 'base', implode(' ', array_filter([
        'wk-announcement-banner',
        'flex w-full items-center justify-center gap-[var(--gap-wk-sm)]',
        // A strip's height is a token and its padding would only fight it, so it is left out
        // there (`py-0` in the sticky classes) and the text is centered on the fixed line instead.
        'px-[var(--padding-wk-x-lg)] py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-sm)] font-[family-name:var(--font-wk-sans)]',
        $intentClasses,
        $stickyClasses,
    ])), $scope);
@endphp

{{-- Not role="alert": an announcement is ambient, and an alert interrupts a
     screen-reader user mid-sentence. A labeled region lets them find it when
     they want it and ignore it when they do not.

     x-cloak keeps a previously-dismissed bar from flashing on every page load
     before Alpine reads localStorage. --}}
<div
    @if($isDismissible)
        x-data="wirekitDismissible({@if($persistsDismissal) persistKey: {{ \Pushery\WireKit\Support\AlpinePayload::string('wk-banner:'.$dismissKey) }} @endif })"
        x-show="shown"
        x-cloak
        {{-- Opts the dismissed-then-empty preview into the docs preview frame's
             replay/reset affordance (same contract as alert / badge). Without it
             a dismissible demo stays gone with no way to bring it back. --}}
        data-replayable="true"
    @endif
    @if(filled($label)) role="region" aria-label="{{ $label }}" @endif
    data-wk-announcement-banner
    @if($strip) data-wk-viewport-strip @endif
    data-position="{{ $positionValue }}"
    data-intent="{{ $intentValue }}"
    {{ $hueValue !== null ? $attributes->class([$classes])->merge(['style' => '--wk-banner-hue: '.$hueValue.';']) : $attributes->class([$classes]) }}
>
    {{-- In a strip the line is fixed, so text that does not fit is cut on screen — and only
         there: the characters stay in the DOM, so the accessible name is the whole sentence. --}}
    <span data-wk-announcement-content class="min-w-0 {{ $strip ? 'truncate' : '' }}">{{ $slot }}</span>

    @isset($action)
        <span data-wk-announcement-action class="shrink-0">{{ $action }}</span>
    @endisset

    @if($isDismissible)
        {{-- A real button with a real name — never a bare glyph. --}}
        <button
            type="button"
            @click="dismiss()"
            data-wk-announcement-dismiss
            {{-- Composed from the caller's name where there is one, and from the generic word
                 where there is not: a control always needs a name, even when the banner
                 deliberately is not a landmark. --}}
            aria-label="{{ __('wirekit::Dismiss') }} {{ filled($label) ? $label : __('wirekit::Announcement') }}"
            class="ms-auto shrink-0 cursor-pointer rounded-[var(--radius-wk-sm)] p-[var(--padding-wk-x-xs)] opacity-70 transition-opacity duration-[var(--transition-wk-duration)] hover:opacity-100 focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-[color:var(--color-wk-ring)]"
        >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>
