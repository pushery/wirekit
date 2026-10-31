{{-- optimistic-ui: n/a — client-only
     It opens the action stack. --}}
@props([
    // The accessible name when the button carries no words of its own.
    //
    // Null rather than the default string, so the component can tell "the caller
    // said nothing" from "the caller asked for this" — a distinction the name
    // resolution below needs and could not otherwise make. The default is applied
    // there.
    'label' => null,
    // Where it floats — the inline corner, following the writing direction.
    'position' => config('wirekit.components.fab.position', 'end'),
    // Which block edge it sits on. `block-end` (the default) is the canonical
    // bottom-corner FAB and is unchanged; `block-start` moves it to the top.
    'placement' => 'block-end',
    // What the button opens, so assistive tech announces it correctly. A
    // single-action FAB is NOT a menu. Not given, a button opens a dialog (the
    // canonical use is a feedback / compose modal) and a link with `href`
    // navigates, so it announces nothing. Pass 'dialog' / 'menu' / 'listbox' /
    // 'grid' / 'tree' as needed, or a falsy value to omit aria-haspopup entirely
    // (a plain action that fires an event rather than opening a popup).
    'haspopup' => null,
    // Render an <a> instead of a <button> when the action is a navigation.
    'href' => null,
    // Optional icon name (resolved via the WireKit icon system). A slot overrides it.
    'icon' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('fab.button', $attributes->getAttributes());

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables and
    // `autofocus="false"` focuses — the opposite of what the call site says, with no error
    // either way. Strip such flags when their value reads as false, before the bag is printed.
    $attributes = \Pushery\WireKit\Support\BooleanProp::stripFalseHtmlFlags($attributes);
    // Echoed into the tag or bound, the URL is written escaped once (Support\UrlProp).
    $href = \Pushery\WireKit\Support\UrlProp::text($href);

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    $position = WireKit::validateProp('fab.button', 'position', $position, ['end', 'start', 'center']);
    $placement = WireKit::validateProp('fab.button', 'placement', $placement, ['block-end', 'block-start']);

    // The accessible name.
    //
    // An unconditional `aria-label` would make a FAB that shows "Send feedback"
    // announce "Action". Visible name and accessible name have to agree — WCAG
    // 2.5.3, Label in Name — and someone using voice control cannot activate a
    // control by the words they can see if it answers to something else.
    //
    // Suppressing the label whenever the slot is FILLED would be wrong too: a
    // slot usually carries custom icon markup, which is non-empty and has nothing
    // to read, so those buttons would lose their name entirely. What decides is
    // visible TEXT.
    $slotText = \Pushery\WireKit\Support\SlotContent::plain($slot);
    $ariaLabel = match (true) {
        // No words of its own — the canonical icon FAB. The label is the name.
        $slotText === '' => \Pushery\WireKit\Support\BlankName::first($label, __('wirekit::Action')),
        // Words plus a label that CONTAINS them: allowed by 2.5.3 and useful,
        // since the name may add context as long as it starts from what is written.
        $label !== null && str_contains($label, $slotText) => $label,
        // Words plus a label that contradicts them: that is the failure itself.
        default => null,
    };

    // Whether the icon wrapper below may be hidden from assistive tech.
    //
    // `aria-hidden` is a claim that the content is DECORATIVE, and text is never
    // decorative. Carried unconditionally it would break the branch above:
    // `default => null` means "the visible text is the name", and text inside a
    // hidden subtree leaves a FAB with words in it with NO accessible name at all,
    // which voice control cannot reach and a screen reader announces as nothing.
    //
    // Same value the name resolution keys off, so the two cannot disagree: the
    // wrapper is hidden exactly when there is nothing in it to read.
    $slotIsDecorative = $slotText === '';

    // Positioning mirrors the speed-dial <x-wirekit::fab>: the shared `.wk-fab`
    // class carries the block-end offset INCLUDING env(safe-area-inset-bottom) (so
    // the iOS home indicator never overlaps it), and the inline offset folds in the
    // matching horizontal safe-area inset for a landscape notch on that side.
    // `--wk-scrollbar-inset` is on the END arm ONLY, and that is not an oversight.
    //
    // The document's own scrollbar is outside the viewport box a `position: fixed` box is laid
    // out against, and needs nothing. A scrollbar drawn by an inner scroll container is inside
    // it, and so is the gutter the scroll lock takes away while an overlay is open: that gutter
    // eats into the inline-end gap while the block-end gap loses nothing. The two axes read as
    // symmetric and are not: at a 17px gutter the declared 16px inline offset would render as
    // about -1px and the button would touch the scrollbar. `--wk-scrollbar-inset` carries it.
    //
    // `env(safe-area-inset-right)` does not cover it: it compensates a device notch and is 0 on
    // every desktop, in both engines.
    //
    // The scrollbar sits on the inline-END edge in BOTH directions: LTR puts it right, which is
    // inline-end, and an RTL document puts it left, which is also inline-end. So the START arm
    // is genuinely unaffected and folding the term in there would push it away from a gutter
    // that is not on that side.
    //
    // Host-filled with a `0px` fallback, exactly like `--wk-fab-lift` one axis over: the kit
    // ships no JavaScript for it, and with overlay scrollbars — the macOS default and every
    // touch device — the gutter is 0 and nothing moves.
    $positionClass = match ($position) {
        'start' => 'start-[calc(var(--padding-wk-x-lg)_+_env(safe-area-inset-left))]',
        'center' => 'left-1/2 -translate-x-1/2',
        default => 'end-[calc(var(--padding-wk-x-lg)_+_env(safe-area-inset-right)_+_var(--wk-scrollbar-inset,0px))]',
    };

    // Resolved OUT of the class list, next to $positionClass, rather than as a
    // ternary inside it. The drift guard reads bare quoted strings in that array
    // as emitted classes, so a comparison value written there is reported as a
    // class Tailwind never generated — a false positive that would have been
    // silenced with an allowlist entry instead of removed.
    $placementClass = $placement === 'block-start' ? 'wk-fab-block-start' : '';

    $tag = $href !== null ? 'a' : 'button';

    // A falsy value means "this button does not open a popup" — omit the attribute
    // rather than emit aria-haspopup="false" (which some ATs still announce). Any other
    // value is one of the popups ARIA names, `true` among them, which it reads as `menu`;
    // anything else is reported and announces a dialog.
    $haspopupValue = match (true) {
        $haspopup === null => $href !== null ? null : 'dialog',
        BooleanProp::isFalse($haspopup) => null,
        default => WireKit::validateProp('fab.button', 'haspopup', $haspopup, ['dialog', 'menu', 'listbox', 'grid', 'tree', 'true']),
    };

    // Auto-inject rel="noopener noreferrer" when target="_blank" on an <a>, and
    // force-override any caller rel so the tabnabbing guard can't be defeated —
    // same pattern as sidebar.item / link / button.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href !== null && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);

    $classes = 'wk-fab '.WireKit::resolveClasses('fab.button', 'base', implode(' ', [
        $placementClass,
        // The layer TOKEN, as `fab` and the speed dial use: a number here stayed put when a theme
        // moved the layer, and the FAB ended up above or below scroll-to-top.
        'fixed z-[var(--z-wk-sticky)]',
        // The box reads --size-wk-fab rather than a literal, because a developer laying out
        // AROUND a fixed control needs to be able to read its size. `wk-fab-clearance` does
        // exactly that; before the token the only way was to copy 3.5rem out of this line.
        'flex h-[var(--size-wk-fab)] w-[var(--size-wk-fab)] cursor-pointer items-center justify-center',
        $positionClass,
        'rounded-[var(--radius-wk-full)]',
        'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
        'shadow-[var(--shadow-wk-lg)]',
        'transition-transform duration-[var(--transition-wk-duration)]',
        'hover:brightness-110',
        'focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // The named glyph, looked up only when no slot wins over it. Null where nothing can be drawn
    // (no icon package, or an unknown name in a request): the neutral plus stands in then, so the
    // button never shows an empty circle.
    $iconSvg = ! (isset($slot) && $slot->hasActualContent()) && is_string($icon) && $icon !== '' && ! str_contains($icon, '<')
        ? \Pushery\WireKit\Support\IconSvg::html($icon, ['class' => 'h-6 w-6'])
        : null;

    // A button's type is the caller's when it is one a button has, so a form can submit through it;
    // otherwise it stays a plain button. It leaves the attributes below, since a second `type`
    // would lose to the first.
    $wkButtonType = in_array($attributes->get('type'), ['submit', 'reset', 'button'], true) ? $attributes->get('type') : 'button';
@endphp

<{{ $tag }} data-wk-prose-skip
    @if($href !== null) href="{{ $href }}" @else type="{{ $wkButtonType }}" @endif
    @if($haspopupValue !== null) aria-haspopup="{{ $haspopupValue }}" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    @if($ariaLabel !== null && ! \Pushery\WireKit\Support\CallerAttribute::writes($attributes, 'aria-label') && ! \Pushery\WireKit\Support\CallerAttribute::writes($attributes, 'aria-labelledby')) aria-label="{{ $ariaLabel }}" @endif
    data-wk-fab-button
    data-position="{{ $position }}"
    data-placement="{{ $placement }}"
    {{-- Fixed at the bottom: the page keeps an element that takes the focus out from under it
         (WCAG 2.4.11, resources/js/utils/scroll-inset.js). It stands on `--wk-fab-lift`, so the
         band a page may lift it by leaves it out (`--wk-scroll-inset-below-fab`). --}}
    data-wk-scroll-inset="bottom"
    data-wk-fab-lifted
    {{ $attributes->except($href !== null ? ['rel'] : ['rel', 'type'])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>
    {{-- Icon — decorative ONLY when there is nothing to read. A slot wins; then a
         bare icon name resolves via the WireKit icon system (consistent with
         sidebar.item / fab.action); otherwise a neutral plus stands in. The
         hidden-ness is conditional for the reason spelled out at $slotIsDecorative:
         hiding visible text is what left this button nameless. --}}
    <span class="inline-grid place-items-center" @if($slotIsDecorative) aria-hidden="true" @endif>
        @if(isset($slot) && $slot->hasActualContent())
            {{ $slot }}
        @elseif($iconSvg !== null)
            {{ $iconSvg }}
        @else
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
        @endif
    </span>
    {{-- The other half of the `target="_blank"` rule, and the half that was missing here.
         `rel="noopener noreferrer"` protects the OPENER; this tells the reader what is about
         to happen. A link that silently opens a new tab leaves a screen-reader user in a
         document they did not ask for, with Back doing nothing — and nothing in the page
         explains why. `link` has carried this hint all along; these did not. --}}
    @if($opensNewTab)<span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>@endif
</{{ $tag }}>
