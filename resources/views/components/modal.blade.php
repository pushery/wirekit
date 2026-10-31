{{-- optimistic-ui: n/a — client-only
     Its state is open state. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'name' => null,
    'size' => config('wirekit.components.modal.size', 'md'),
    'dismissible' => config('wirekit.components.modal.dismissible', true),
    // Whether the reader is asked before closing the dialog by Escape, a click beside it or its
    // close button while its fields hold changes. On by default: a click beside a filled form
    // would otherwise throw away what has been typed into it. A close the page makes and a
    // Cancel control never ask. `discardQuestion` replaces the question, which is in the
    // reader's language otherwise.
    'confirmDiscard' => config('wirekit.components.modal.confirm-discard', true),
    'discardQuestion' => null,
    // Whether ⌘↵ or Ctrl+Enter presses the dialog's primary control while it is open, from a field
    // as well. A modal has no confirm of its own: the control a page marks with
    // `data-wk-dialog-primary` is the one, and without a mark nothing is pressed.
    'confirmShortcut' => config('wirekit.components.modal.confirm-shortcut', true),
    // Whether opening locks the page's scroll. False for an overlay that lives inside a page
    // region, such as a preview, where the page around it has to keep scrolling.
    'lockScroll' => true,
    'describedby' => null,
    // Names the dialog when it has no header sub-component. A dialog with no
    // accessible name is announced as just "dialog", and the failure is silent —
    // see the aria-labelledby block below.
    // (No component tag in this comment: Blade compiles tags inside comments too,
    // and one here breaks the @props array it sits in.)
    'ariaLabel' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `dismissible="false"` would otherwise mean the opposite of what the call site reads
    // as, on the one switch a confirmation dialog exists to set. All four reads below are
    // truth tests (the x-data seed, the Escape listener and the two backdrop handlers),
    // and modal.header's close-X hangs off the seeded Alpine value, so the string would
    // turn every one of them back ON: Escape would close the dialog, the overlay would
    // close it, and the close-X the prop suppresses would render.
    //
    // Normalized against the prop's own default so a cast never turns dismissal off on
    // a modal that never asked for it.
    $dismissible = BooleanProp::from($dismissible, true, 'modal', 'dismissible');
    $confirmDiscard = BooleanProp::from($confirmDiscard, true, 'modal', 'confirmDiscard');
    $confirmShortcut = BooleanProp::from($confirmShortcut, true, 'modal', 'confirmShortcut');
    $lockScroll = BooleanProp::from($lockScroll, true, 'modal', 'lockScroll');

    // Only a dialog the reader can close asks before it closes.
    $discardQuestionText = $dismissible && $confirmDiscard
        ? (filled($discardQuestion) ? $discardQuestion : __('wirekit::Discard your changes?'))
        : null;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('modal', $attributes->getAttributes());

    // An unknown `size` is reported (Support\StrictnessGate) and renders as `md`; a bound `null` or
    // empty string is `md` without a word.
    $size = \Pushery\WireKit\WireKit::validateProp('modal', 'size', \Pushery\WireKit\Support\NumericProp::text($size ?: 'md'), ['md', 'sm', 'lg', 'xl', 'full']);

    // A caller's listener for an event this view listens to on the element the bag lands on
    // goes in the other spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:keydown.escape.window']);

    // Title ID for aria-labelledby — links dialog to its header
    // Counted, not random, when there is no name. The same dialog renders the same id on the next
    // round trip, so Livewire's morph keeps the heading instead of replacing it, and a heading
    // re-rendered on its own still carries the id the dialog's aria-labelledby names.
    $titleId = $name !== null ? 'wk-modal-title-' . $name : \Pushery\WireKit\Support\DomId::unique(null, 'wk-modal-title-');

    // The dialog's id, which a `modal.trigger` naming this modal points `aria-controls` at while the
    // modal is open. Only a named modal has one, since a trigger can address a modal only by name.
    // A second modal of the same name on the page takes a suffix and keeps its own id.
    $dialogId = filled($name) ? \Pushery\WireKit\Support\DomId::distinct('wk-modal-dialog-'.$name) : null;

    // How the dialog gets its name, and why this is not just `aria-labelledby`.
    //
    // Only the header sub-component ever puts the labeling id on an element, so an
    // unconditional aria-labelledby would, in a modal built without a header — a
    // confirmation, a media lightbox, anything whose heading is its own markup —
    // point at an id nothing carries. Per ARIA that resolves to no name at all: the
    // dialog would announce as bare "dialog", with the markup looking complete and
    // axe unable to see a reference that is merely unfulfilled at runtime.
    //
    // Three sources, in priority order, and only one is ever emitted:
    //   1. a caller `aria-label` attribute,
    //   2. the `ariaLabel` prop,
    //   3. the header, via aria-labelledby.
    // A caller attribute wins over the prop because it is the more specific
    // instruction; both win over the header because if someone named the dialog
    // explicitly, that is the name they meant.
    $callerAriaLabel = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-label');
    $resolvedAriaLabel = \Pushery\WireKit\Support\BlankName::first($callerAriaLabel, $ariaLabel);

    // Is there a header to point at? The header's own id is bound by Alpine at
    // runtime and so is invisible here; the marker attribute is what makes the
    // question answerable at render time. The slot is already rendered by now.
    $hasHeader = str_contains((string) $slot, 'data-wk-modal-header');

    if ($resolvedAriaLabel === null && ! $hasHeader && ! $attributes->has('aria-labelledby')) {
        // Say it where the developer will see it: through the house strictness gate, as
        // an exception or a log line depending on its settings. Nothing downstream can
        // recover a name that was never given, and a nameless dialog is a WCAG 4.1.2
        // failure that no automated scan of the page will catch.
        WireKit::validateProp('modal', 'ariaLabel', '', [
            'a non-empty label, or an <x-wirekit::modal.header> to name the dialog',
        ]);
    }

    // aria-* belongs on the element that carries role="dialog", not on the
    // x-data wrapper. `aria-label` on a roleless <div> is prohibited by ARIA and
    // simply does not reach assistive tech.
    $ariaAttributes = collect($attributes->getAttributes())
        ->filter(fn ($value, string $key): bool => str_starts_with($key, 'aria-'))
        ->all();
    // The caller's description joins the one `describedby` names in one attribute: written as two,
    // the parser would keep the first and drop the caller's.
    $dialogDescribedBy = trim(((string) $describedby).' '.trim((string) \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-describedby')));
    $attributes = $attributes->except(array_keys($ariaAttributes));
    // Written through a bag of their own: a bag holds each value as the caller's markup, escaped
    // once, and writes it as it holds it, where `{{ }}` would escape it a second time.
    $dialogAriaAttributes = new \Illuminate\View\ComponentAttributeBag(array_diff_key($ariaAttributes, ['aria-label' => true, 'aria-describedby' => true]));

    // Backdrop classes — semi-transparent overlay behind the dialog
    $backdropClasses = 'wk-overlay-fixed wk-overlay-layer-modal '.WireKit::resolveClasses('modal', 'backdrop', implode(' ', [
        'fixed inset-0',
        'z-[var(--z-wk-modal)]',
        'bg-[var(--color-wk-overlay)]',
    ]), $scope);

    // Container classes — positions the dialog on screen.
    //
    // `items-start` here and `my-auto` on the panel, not `items-center`. A flex item taller
    // than its scroll container is pushed past the START of the scrollable area by
    // `items-center`: `scrollTop: 0` already sits below the panel's top edge and there is no
    // negative scroll, so the header and the first lines of a long dialog would be
    // unreachable. Auto margins center the panel while it fits and resolve to zero once it
    // does not, which keeps its top inside the scrollable
    // area. The panel's own cap below makes that case rare; this keeps it reachable when a
    // caller overrides the cap.
    $containerClasses = 'wk-overlay-fixed wk-overlay-layer-modal wk-scrollbar '.WireKit::resolveClasses('modal', 'container', implode(' ', [
        'fixed inset-0',
        'z-[var(--z-wk-modal)]',
        'flex items-start justify-center',
        'p-[var(--padding-wk-y-xl)]',
        'overflow-y-auto',
    ]), $scope);

    // Panel classes — the dialog surface with shadow and rounded corners.
    //
    // Capped to the viewport, and a column. Without a height limit the panel would grow with
    // its content, and `overflow-hidden` would limit nothing: it only clips what overflows a
    // box whose height the content itself decides. The cap is the viewport minus the container's
    // padding on both edges, in `dvh` so a mobile browser's toolbars cannot eat the bottom
    // edge — the drawer caps itself the same way. `flex-col` lets the header and footer keep
    // their height while modal.body, which may shrink, scrolls between them.
    //
    // The panel scrolls too, as the fallback for a dialog built WITHOUT modal.body: clipped,
    // its overflow would simply be gone. With a body the body absorbs the overflow and the
    // panel never needs its own scrollbar. It carries `role="dialog"`, so the scroll region
    // already has an owner for its keyboard model.
    $panelClasses = 'wk-scrollbar '.WireKit::resolveClasses('modal', 'panel', implode(' ', [
        'relative w-full',
        'my-auto',
        'flex flex-col',
        'max-h-[calc(100dvh-2*var(--padding-wk-y-xl))]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-xl)]',
        'shadow-[var(--shadow-wk-lg)]',
        'overflow-x-hidden overflow-y-auto',
        // A typed token with no break point, an email address or a URL, breaks rather than being
        // cut off at the panel's edge. Inherited by everything inside; `break-word` leaves every
        // min-content width alone, so a table or a flex row in the content keeps the layout it had.
        '[overflow-wrap:break-word]',
    ]), $scope);

    // Size mapping to modal width tokens
    $sizeClass = match ($size) {
        'sm' => 'max-w-[var(--size-wk-modal-sm)]',
        'md' => 'max-w-[var(--size-wk-modal-md)]',
        'lg' => 'max-w-[var(--size-wk-modal-lg)]',
        'xl' => 'max-w-[var(--size-wk-modal-xl)]',
        'full' => 'max-w-[var(--size-wk-modal-full)]',
        default => 'max-w-[var(--size-wk-modal-md)]',
    };
    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);

    // The dialog is teleported, so where the component is written only its trigger shows.
    // Without one the root would be an empty box, which a flex or grid container still counts as
    // an item with a gap of its own beside it, so it generates no box. With a trigger it keeps its
    // box, and the trigger its place: its own width in a grid cell, the margin a
    // stack gives its children. `class="contents"` on the component hands the trigger over.
    $rootAttributes = \Pushery\WireKit\Support\SlotContent::given($trigger ?? null) ? $attributes : $attributes->class(\Pushery\WireKit\Support\CompactClasses::list(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['contents'])));
@endphp

{{-- Modal component — teleported to the overlay root for proper stacking context.

     ESC handling: the JS component uses `focus-trap` with `escapeDeactivates`,
     which listens on the document once the trap is active. The trap is armed
     after the panel has settled, so a window-level ESC listener covers the time
     before that: it catches the event regardless of focus location and closes
     through `dismissByReader('escape')` (which closes, deactivates the focus trap
     and announces `wirekit:modal-dismissed`). It is guarded against re-entry, so the extra
     call is safe when focus-trap catches the key too, and the announcement is
     made once. Both leave alone an Escape that an element inside the panel
     already handled, such as a menu or a list that closed on it, and so does
     the listener of a modal another overlay covers. Only registered when the
     modal is dismissible — non-dismissible modals must never close on ESC. --}}
<div
    x-data="wirekitModal({ name: {{ \Pushery\WireKit\Support\AlpinePayload::string($name) }}, dismissible: {{ $dismissible ? 'true' : 'false' }}, lockScroll: {{ $lockScroll ? 'true' : 'false' }}, discardQuestion: {{ $discardQuestionText !== null ? \Pushery\WireKit\Support\AlpinePayload::string(\Pushery\WireKit\Support\SlotContent::textOf($discardQuestionText)) : 'null' }}, confirmShortcut: {{ $confirmShortcut ? 'true' : 'false' }} })"
    @if($dismissible) x-on:keydown.escape.window="onWindowEscape($event)" @endif
    {{ $rootAttributes }}
>
    {{-- Trigger slot — always visible, clicking opens the modal.
         The wrapper only carries the click listener, so it generates no box. As a block it would
         keep a button in the slot at its own width inside a container that stretches its
         children, where a caller cannot reach it with a class. On its own `contents` changes nothing a page shows;
         `class="contents"` on the component root is what hands the trigger to the container. --}}
    @if(\Pushery\WireKit\Support\SlotContent::given($trigger ?? null))
        <div class="contents" x-on:click="show($event)">
            {{ $trigger }}
        </div>
    @endif

    {{-- Modal overlay and dialog — rendered when open --}}
    <template x-teleport="#wk-overlay-root">
        <div x-show="isOpen" x-cloak>
            {{-- Backdrop.
                 Leave transition intentionally omitted: an instant close matches the
                 platform dialog convention, and whatever checks right after a close
                 that the dialog is gone does not race a fade-out. --}}
            <div
                x-show="isOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="{{ $backdropClasses }}"
                @if($dismissible) x-on:click="handleBackdropClick()" @endif
                aria-hidden="true"
            ></div>

            {{-- Dialog container — centers the panel.
                 Click handler here (not on backdrop) because this div is layered
                 on top and intercepts pointer events. `.self` closes only on a click
                 on the container itself, beside the panel, so a click inside the
                 dialog is not stopped: it reaches the document, where a listener the
                 page delegates and the `click.outside` of a menu inside the panel
                 hear it. --}}
            <div
                class="{{ $containerClasses }}"
                @if($dismissible) x-on:click.self="handleBackdropClick()" @endif
            >
                {{-- Dialog panel — the actual modal content --}}
                {{-- Dialog panel — leave transition intentionally omitted
                     for the same reason as the backdrop above. --}}
                <div
                    x-ref="panel"
                    x-show="isOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    @if($dialogId !== null) id="{{ $dialogId }}" @endif
                    role="dialog"
                    {{-- Dynamic aria-modal so a stacked modal-over-modal pair only
                         marks the topmost as modal — ARIA spec compliance. --}}
                    :aria-modal="isTopmost ? 'true' : 'false'"
                    aria-modal="true"
                    @if($resolvedAriaLabel !== null)
                        aria-label="{{ $resolvedAriaLabel }}"
                    @elseif(! array_key_exists('aria-labelledby', $ariaAttributes))
                        {{-- A caller's own aria-labelledby is forwarded below and names the
                             dialog; the header's would stand beside it and the parser keeps
                             the first. --}}
                        aria-labelledby="{{ $titleId }}"
                    @endif
                    @if($dialogDescribedBy !== '') aria-describedby="{{ $dialogDescribedBy }}" @endif
                    {{-- aria-* the caller passed, on the dialog rather than the roleless wrapper.
                         aria-label is excluded: it is resolved above so an explicit name
                         and the header can never both be emitted. --}}
                    {{ $dialogAriaAttributes }}
                    class="{{ $panelClasses }} {{ $sizeClass }}"
                    wire:ignore.self
                    {{-- The theme marker for the panel itself, the surface that paints the modal's
                         background. A theme reaching only the body inside it dresses a layer the panel
                         already covers. --}}
                    data-wk-modal-panel
                    data-wk-title-id="{{ $titleId }}"
                >
                    {{ $slot }}
                </div>
            </div>
        </div>
    </template>

    {{-- The dialog the question about discarded changes is asked in, when the modal asks one. --}}
    @if($discardQuestionText !== null)
        @include('wirekit::components.partials.discard-question', ['question' => $discardQuestionText, 'lockScroll' => $lockScroll, 'scope' => $scope])
    @endif
</div>
