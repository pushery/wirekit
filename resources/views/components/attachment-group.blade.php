{{-- optimistic-ui: n/a — client-only
     A keyboard-reachable scroll region. Scrolling never reaches the server; the tabindex is there for WCAG 2.1.1, not for an action. --}}
@props([
    // Accessible name for the group (e.g. "3 attachments").
    'label' => config('wirekit.components.attachment-group.label') ?? __('wirekit::Attachments'),
    // 'row' scroll-snaps horizontally (chat bubbles, tight rows);
    // 'stack' lists them vertically (mail, detail panels).
    'orientation' => 'stack',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('attachment-group', $attributes->getAttributes());

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables and
    // `autofocus="false"` focuses — the opposite of what the call site says, with no error
    // either way. Strip such flags when their value reads as false, before the bag is printed.
    $attributes = \Pushery\WireKit\Support\BooleanProp::stripFalseHtmlFlags($attributes);

    $orientationValue = in_array($orientation, ['row', 'stack'], true)
        ? $orientation
        : WireKit::validateProp('attachment-group', 'orientation', $orientation, ['row', 'stack']);

    $isRow = $orientationValue === 'row';

    // The row variant is a scroll container, so it carries the keyboard
    // contract: tabindex + role + aria-label make it reachable and operable
    // (WCAG 2.1.1), with a visible focus ring. The stack variant does not
    // scroll — it is a plain labeled group.
    // A `match` (not a ternary) on purpose: the drift auditor statically
    // harvests match-arm class strings, so every class here stays traceable to
    // its source emission. A ternary hides them from the scanner and would need
    // an allowlist entry instead — same reason scroll-area resolves its
    // overflow classes this way.
    $orientationClasses = match ($orientationValue) {
        'row' => 'wk-scrollbar relative snap-x snap-mandatory overflow-x-auto overflow-y-hidden focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[color:var(--color-wk-ring)]',
        default => 'flex-col',
    };

    $classes = WireKit::resolveClasses('attachment-group', 'base', implode(' ', [
        'flex gap-[var(--gap-wk-sm)]',
        $orientationClasses,
    ]), $scope);
@endphp

<div
    @unless($attributes->has('role')) role="group" @endunless
    @unless($attributes->has('aria-label') || $attributes->has('aria-labelledby')) aria-label="{{ \Pushery\WireKit\Support\SlotContent::textOf($label) }}" @endunless
    {{-- The condition lives in the VALUE, not around the attribute. A `tabindex` behind
         `@if` reads as a keyboard model and is not one: the guard that holds this contract
         strips Blade conditionals before it looks at the wiring, so it was passing this
         element through on its role alone — delete the line and nothing would have said so,
         while a named `role="group"` with no `tabindex` is not keyboard-reachable at all,
         which is the failure the guard exists for. `-1` for the stack, which does not
         scroll: an element that scrolls nothing must not become an invisible tab stop. --}}
    tabindex="{{ $isRow ? '0' : '-1' }}"
    data-wk-attachment-group
    data-orientation="{{ $orientationValue }}"
    {{-- A sheet does not scroll sideways: on paper a row of attachments flows onto the next
         line. --}}
    @if($isRow) data-wk-print-wrap @endif
    {{ $attributes->except('tabindex')->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>
    {{ $slot }}
</div>
