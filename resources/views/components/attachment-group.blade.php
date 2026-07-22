@props([
    // Accessible name for the group (e.g. "3 attachments").
    'label' => config('wirekit.components.attachment-group.label') ?? __('Attachments'),
    // 'row' scroll-snaps horizontally (chat bubbles, tight rows);
    // 'stack' lists them vertically (mail, detail panels).
    'orientation' => 'stack',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

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
        'row' => 'wk-scrollbar snap-x snap-mandatory overflow-x-auto overflow-y-hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--color-wk-ring)]',
        default => 'flex-col',
    };

    $classes = WireKit::resolveClasses('attachment-group', 'base', implode(' ', [
        'flex gap-[var(--gap-wk-sm)]',
        $orientationClasses,
    ]), $scope);
@endphp

<div
    role="group"
    aria-label="{{ $label }}"
    @if($isRow) tabindex="0" @endif
    data-wk-attachment-group
    data-orientation="{{ $orientationValue }}"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
