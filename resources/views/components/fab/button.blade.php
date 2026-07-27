@props([
    // The accessible name when the button carries no words of its own.
    //
    // Null rather than the default string, so the component can tell "the caller
    // said nothing" from "the caller asked for this" — a distinction the name
    // resolution below needs and could not otherwise make. The default is applied
    // there, so an existing icon-only call renders exactly as before.
    'label' => null,
    // Where it floats — the inline corner, following the writing direction.
    'position' => config('wirekit.components.fab.position', 'end'),
    // Which block edge it sits on. `block-end` (the default) is the canonical
    // bottom-corner FAB and is unchanged; `block-start` moves it to the top.
    'placement' => 'block-end',
    // What the button opens, so assistive tech announces it correctly. A
    // single-action FAB is NOT a menu — the default is 'dialog' (the canonical use
    // is a feedback / compose modal). Pass 'menu' / 'true' / 'listbox' / 'grid' /
    // 'tree' as needed, or a falsy value to omit aria-haspopup entirely (a plain
    // action that navigates or fires an event rather than opening a popup).
    'haspopup' => 'dialog',
    // Render an <a> instead of a <button> when the action is a navigation.
    'href' => null,
    // Optional icon name (resolved via the WireKit icon system). A slot overrides it.
    'icon' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    $position = WireKit::validateProp('fab.button', 'position', $position, ['end', 'start', 'center']);
    $placement = WireKit::validateProp('fab.button', 'placement', $placement, ['block-end', 'block-start']);

    // The accessible name.
    //
    // `aria-label` used to be emitted unconditionally, so a FAB with words in it
    // showed "Send feedback" and announced "Action". Visible name and accessible
    // name have to agree — WCAG 2.5.3, Label in Name — and someone using voice
    // control cannot activate a control by the words they can see if it answers
    // to something else.
    //
    // Suppressing the label whenever the slot is FILLED would have been the
    // obvious fix and the wrong one: a slot usually carries custom icon markup,
    // which is non-empty and has nothing to read, so those buttons would have
    // lost their name entirely. What decides is visible TEXT.
    $slotText = trim(strip_tags((string) $slot));
    $ariaLabel = match (true) {
        // No words of its own — the canonical icon FAB. The label is the name.
        $slotText === '' => $label ?? __('Action'),
        // Words plus a label that CONTAINS them: allowed by 2.5.3 and useful,
        // since the name may add context as long as it starts from what is written.
        $label !== null && str_contains($label, $slotText) => $label,
        // Words plus a label that contradicts them: that is the failure itself.
        default => null,
    };

    // Whether the icon wrapper below may be hidden from assistive tech.
    //
    // `aria-hidden` is a claim that the content is DECORATIVE, and text is never
    // decorative. The wrapper used to carry it unconditionally, which turned the
    // branch above into a promise the next line broke: `default => null` means
    // "the visible text is the name", and the text sat inside a hidden subtree,
    // so a FAB with words in it shipped with NO accessible name at all — worse
    // than the wrong name it replaced, because a control without a name cannot be
    // reached by voice control and is announced as nothing at all.
    //
    // Same value the name resolution keys off, so the two cannot disagree: the
    // wrapper is hidden exactly when there is nothing in it to read.
    $slotIsDecorative = $slotText === '';

    // Positioning mirrors the speed-dial <x-wirekit::fab>: the shared `.wk-fab`
    // class carries the block-end offset INCLUDING env(safe-area-inset-bottom) (so
    // the iOS home indicator never overlaps it), and the inline offset folds in the
    // matching horizontal safe-area inset for a landscape notch on that side.
    $positionClass = match ($position) {
        'start' => 'start-[calc(var(--padding-wk-x-lg)_+_env(safe-area-inset-left))]',
        'center' => 'left-1/2 -translate-x-1/2',
        default => 'end-[calc(var(--padding-wk-x-lg)_+_env(safe-area-inset-right))]',
    };

    // Resolved OUT of the class list, next to $positionClass, rather than as a
    // ternary inside it. The drift guard reads bare quoted strings in that array
    // as emitted classes, so a comparison value written there is reported as a
    // class Tailwind never generated — a false positive that would have been
    // silenced with an allowlist entry instead of removed.
    $placementClass = $placement === 'block-start' ? 'wk-fab-block-start' : '';

    $tag = $href ? 'a' : 'button';

    // A falsy value means "this button does not open a popup" — omit the attribute
    // rather than emit aria-haspopup="false" (which some ATs still announce).
    $haspopupValue = BooleanProp::isFalse($haspopup) ? null : $haspopup;

    // Auto-inject rel="noopener noreferrer" when target="_blank" on an <a>, and
    // force-override any caller rel so the tabnabbing guard can't be defeated —
    // same pattern as sidebar.item / link / button.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);

    $classes = WireKit::resolveClasses('fab.button', 'base', implode(' ', [
        'wk-fab',
        $placementClass,
        'fixed z-40',
        'flex h-14 w-14 cursor-pointer items-center justify-center',
        $positionClass,
        'rounded-[var(--radius-wk-full)]',
        'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
        'shadow-[var(--shadow-wk-lg)]',
        'transition-transform duration-[var(--transition-wk-duration)]',
        'hover:brightness-110',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-2',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @else type="button" @endif
    @if($haspopupValue !== null) aria-haspopup="{{ $haspopupValue }}" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    @if($ariaLabel !== null) aria-label="{{ $ariaLabel }}" @endif
    data-wk-fab-button
    data-position="{{ $position }}"
    data-placement="{{ $placement }}"
    {{ $attributes->except('rel')->class([$classes]) }}
>
    {{-- Icon — decorative ONLY when there is nothing to read. A slot wins; then a
         bare icon name resolves via the WireKit icon system (consistent with
         sidebar.item / fab.action); otherwise a neutral plus stands in. The
         hidden-ness is conditional for the reason spelled out at $slotIsDecorative:
         hiding visible text is what left this button nameless. --}}
    <span class="inline-grid place-items-center" @if($slotIsDecorative) aria-hidden="true" @endif>
        @if(isset($slot) && $slot->isNotEmpty())
            {{ $slot }}
        @elseif(is_string($icon) && $icon !== '' && ! str_contains($icon, '<') && function_exists('svg'))
            {{ svg(WireKit::icon($icon), ['class' => 'h-6 w-6']) }}
        @else
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
        @endif
    </span>
</{{ $tag }}>
