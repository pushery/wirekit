{{-- optimistic-ui: n/a — sub-component
     The tour owns which step is current. --}}
@props([
    'target' => null,
    'placement' => 'bottom',
    // When null (default), the index is auto-assigned via
    // TourStepCounter::next() based on document order under the
    // enclosing tour parent. Explicit :index="N" bypasses the
    // auto-assignment — useful when steps render conditionally
    // and the developer needs control over the numbering.
    'index' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('tour.step', $attributes->getAttributes());

    use Pushery\WireKit\Support\TourStepCounter;
    use Pushery\WireKit\WireKit;

    // Auto-assign the index from the per-render counter when the
    // developer didn't supply one. The parent tour component
    // reset the counter to 0; sibling steps each pick the next
    // integer in document-render order.
    $resolvedIndex = $index ?? TourStepCounter::next();

    // Id for aria-labelledby — links the step dialog to its own heading.
    //
    // The index alone is not unique: several tours may sit on one page and each
    // numbers its steps from zero. DomId dedupes that — the first sight of a base
    // keeps it verbatim, a second gets `-2` — and it does so with a per-request
    // counter rather than a random suffix. That difference is the whole point
    // here: this dialog's aria-labelledby and its heading's id are two halves of
    // one pairing, and a value re-minted per render leaves them well-formed while
    // naming different things after any partial re-render. Nothing in the markup
    // looks wrong; the only witness is a reader who is told the dialog has no name.
    $titleId = \Pushery\WireKit\Support\DomId::unique(
        'wk-tour-step-title-'.$resolvedIndex,
        'wk-tour-step-title-'
    );

    // HOW THE STEP DIALOG GETS ITS NAME — three sources, one winner, and the
    // caller's is the winner whenever there is one.
    //
    // The panel used to name itself and then let the attribute bag land on the
    // same element, so `aria-label="…"` from the call site lost in both branches
    // and lost SILENTLY, by two different mechanisms:
    //
    //   * with a title slot, the element carried `aria-labelledby` as well, and
    //     ARIA resolves the reference in preference to the label;
    //   * without one, the element carried `aria-label` TWICE, and the HTML parser
    //     keeps the first — which was the generated "Tour step N".
    //
    // The second is the quieter of the two: it is not an ARIA precedence rule a
    // developer might know to look up, just a parser rule, and the DOM inspector
    // shows one attribute with the wrong value. No error, no warning, no lint hit.
    //
    // The order is the one `modal.blade.php` sets out and for the same reason: a
    // name written at the call site is the most specific instruction available, so
    // it wins over anything the component can derive. A tour step is where that
    // matters most — the visible heading is a page heading, and the announcement
    // often wants to be shorter and more telling than it.
    //
    // `filled()` rather than a null check: an interpolated caller value over a
    // record with no title yields `""`, and an empty `aria-label` names nothing at
    // all. Honoring it would trade the heading for no name — worse than the defect.
    $callerAriaLabel = $attributes->get('aria-label');
    $hasCallerName = filled($callerAriaLabel);

    // Out of the bag, so the winner is emitted once rather than joined by a loser
    // further along the tag.
    $attributes = $attributes->except('aria-label');

    // Tour step — individual tooltip-like popup positioned near a target element.
    // The initial off-screen position (left/top: -9999px) is set via a CSS rule
    // in dist/wirekit.css scoped to [data-wk-tour-step] — it prevents a visible
    // flicker at (0, 0) between Alpine's x-show flip and Floating UI's async
    // positioning. Floating UI's Object.assign(floating.style, { left, top })
    // writes inline style values that outrank the stylesheet rule once
    // computePosition() resolves, so the panel jumps straight to its final spot.
    $panelClasses = WireKit::resolveClasses('tour.step', 'base', implode(' ', [
        'fixed z-[var(--z-wk-modal)]',
        'w-80',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-lg)]',
        'shadow-[var(--shadow-wk-lg)]',
        'p-[var(--padding-wk-x-md)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

<div
    x-show="currentStep === {{ (int) $resolvedIndex }}"
    data-wk-tour-step="{{ (int) $resolvedIndex }}"
    data-wk-target="{{ $target }}"
    data-wk-placement="{{ $placement }}"
    role="dialog"
    {{-- The tour lays a full-viewport scrim over the page, so the step IS modal
         in the sense ARIA means: everything behind it is unavailable while it is
         open. The name follows the dialog rule — point at the visible heading
         when there is one, and fall back to a generated name only when the step
         ships without a title slot. --}}
    aria-modal="true"
    @if($hasCallerName)
        aria-label="{{ $callerAriaLabel }}"
    @elseif(isset($title))
        aria-labelledby="{{ $titleId }}"
    @else
        aria-label="{{ __('wirekit::Tour step :number', ['number' => $resolvedIndex + 1]) }}"
    @endif
    {{-- Focus lands here when the step opens, ahead of its Back/Next controls, so
         the step's name and body are read before the buttons. `-1` keeps the panel
         out of the page's own tab order — it is a focus destination, not a stop. --}}
    tabindex="-1"
    {{ $attributes->class([$panelClasses]) }}
    x-cloak
>
    {{-- Step title --}}
    @isset($title)
        <h3 id="{{ $titleId }}" class="font-[number:var(--font-wk-heading-weight)] text-[length:var(--text-wk-lg)] mb-[var(--padding-wk-y-xs)]">{{ $title }}</h3>
    @endisset

    {{-- Step body --}}
    <div class="text-[color:var(--color-wk-text-muted)] mb-[var(--padding-wk-y-md)]">
        {{ $slot }}
    </div>

    {{-- Step footer — navigation + progress --}}
    <div class="flex items-center justify-between">
        <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] tabular-nums" x-text="progressText"></span>
        <div class="flex items-center gap-[var(--gap-wk-sm)]">
            <button
                type="button"
                x-show="currentStep > 0"
                x-on:click="prev()"
                class="p-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-sm)] cursor-pointer text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] rounded-[var(--radius-wk-sm)] hover:bg-[var(--color-wk-bg-subtle)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
            >{{ __('wirekit::Back') }}</button>
            <button
                type="button"
                x-on:click="next()"
                class="p-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-sm)] cursor-pointer bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)] rounded-[var(--radius-wk-md)] hover:bg-[var(--color-wk-accent-hover)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                x-text="currentStep === totalSteps - 1 ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Finish')) }} : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Next')) }}"
            {{-- A literal opening label, because x-text is the button's ONLY content: until
                 Alpine evaluates it the element is empty, so the control has no accessible
                 name at all — and a server-side accessibility check sees nothing else, ever.
                 x-text overwrites this text node on its first evaluation.
                 "Next" rather than "Finish": which one is right depends on the step index,
                 which lives in Alpine and not on the server, and every step but the last
                 opens on "Next". --}}
            >{{ __('wirekit::Next') }}</button>
        </div>
    </div>
</div>
