@props([
    'steps' => [],
    'current' => 1,
    'orientation' => config('wirekit.components.stepper.orientation', 'horizontal'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // A stepper shows progress through a multi-step flow. Each step is either
    // completed (index < current), current (index == current), or upcoming
    // (index > current). The visual treatment and ARIA semantics differ per state.
    $isVertical = $orientation === 'vertical';

    // Outer list — <ol> since steps are ordered. role="list" is redundant but
    // some styling removes list-style so we keep the semantic element.
    // Horizontal: no gap — connectors span the full distance between circles.
    // Vertical: gap between items provides visual spacing between steps.
    $listClasses = WireKit::resolveClasses('stepper', 'list', implode(' ', [
        // list-none + m-0 + p-0 strip the browser-default <ol> decimal markers
        // and marker indent. Stepper renders its own numbered circles per step,
        // so the UA "1. 2. 3." prefixes would visually duplicate the step number.
        'list-none m-0 p-0',
        $isVertical ? 'flex flex-col gap-[var(--padding-wk-y-md)]' : 'flex flex-row items-start gap-2',
        'w-full',
    ]), $scope);

    // Each step wrapper.
    $itemClasses = WireKit::resolveClasses('stepper', 'item', implode(' ', [
        'flex',
        // Horizontal: `flex-1 min-w-0` so each step takes an equal share AND
        // can shrink below its label's intrinsic width. Without `min-w-0` the
        // default `min-width: auto` pins each item to its longest-word width,
        // so a multi-word step label ("Supporting documents") pushed the item
        // past its share and overflowed the row on a phone. With min-w-0 the
        // centered label wraps within its column instead of overflowing.
        $isVertical ? 'flex-row items-start gap-[var(--padding-wk-x-sm)]' : 'flex-col items-center flex-1 min-w-0',
        'relative',
    ]), $scope);

    // Circle indicator that shows step number or a check for completed steps.
    $circleBase = WireKit::resolveClasses('stepper', 'circle', implode(' ', [
        'flex items-center justify-center',
        'w-8 h-8 shrink-0',
        'rounded-full',
        'text-[length:var(--text-wk-sm)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'border-[length:var(--border-wk-width)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
    ]), $scope);

    // Connector line between steps.
    $connectorClasses = implode(' ', [
        $isVertical
            ? 'absolute left-4 top-8 w-[1px] h-[calc(100%-0.5rem)] -translate-x-[0.5px]'
            : 'absolute top-4 left-[calc(50%+1rem)] right-[calc(-50%+0.5rem)] h-[1px]',
        'bg-[var(--color-wk-border)]',
    ]);

    // Label classes.
    $labelClasses = WireKit::resolveClasses('stepper', 'label', implode(' ', [
        $isVertical ? '' : 'mt-[var(--padding-wk-y-xs)] text-center',
        // Long step labels must wrap (and break a too-long single token)
        // within their min-w-0 column on a phone instead of overflowing the
        // horizontal row.
        $isVertical ? '' : 'max-w-full [overflow-wrap:anywhere]',
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

{{--
    aria-label names the progress indicator; role=list is implicit on ol.
    Inline style strips the UA decimal markers + indent because the docs
    sandbox iframe runs WITHOUT Tailwind preflight; the `list-none m-0 p-0`
    classes in $listClasses are decorative only and don't apply there.
--}}
<ol aria-label="{{ __('Progress') }}" {{ $attributes->class([$listClasses]) }} style="list-style: none; margin: 0; padding: 0;">
    @foreach($steps as $i => $step)
        @php
            // Normalize: accept a string (label only) or ['label' => .., 'description' => ..].
            $label = is_array($step) ? ($step['label'] ?? '') : (string) $step;
            $description = is_array($step) ? ($step['description'] ?? null) : null;
            $stepNumber = $i + 1;

            $isCompleted = $stepNumber < $current;
            $isCurrent = $stepNumber === $current;
            $isLast = $i === array_key_last($steps);

            // Visual treatment per state. Completed: filled accent. Current:
            // outlined accent (active ring). Upcoming: muted outline.
            $stateClasses = $isCompleted
                ? 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)] border-[var(--color-wk-accent)]'
                : ($isCurrent
                    ? 'bg-[var(--color-wk-bg)] text-[color:var(--color-wk-accent-text)] border-[var(--color-wk-accent)]'
                    : 'bg-[var(--color-wk-bg)] text-[color:var(--color-wk-text-muted)] border-[var(--color-wk-border)]');
        @endphp

        <li
            class="{{ $itemClasses }}"
            @if($isCurrent) aria-current="step" @endif
        >
            {{-- Connector: drawn for all but the last step. Lives inside <li>
                 as absolutely positioned element so it never breaks flow. --}}
            @unless($isLast)
                <span class="{{ $connectorClasses }}" aria-hidden="true"></span>
            @endunless

            <div class="flex {{ $isVertical ? 'flex-row items-start gap-[var(--padding-wk-x-sm)]' : 'flex-col items-center' }} relative">
                <div class="{{ $circleBase }} {{ $stateClasses }}">
                    @if($isCompleted)
                        {{-- Check mark — decorative; state is communicated via aria-current / visually-hidden text. --}}
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 5.29a.75.75 0 010 1.06l-7.5 7.5a.75.75 0 01-1.06 0l-3.5-3.5a.75.75 0 111.06-1.06L8.674 12.23l6.97-6.94a.75.75 0 011.06 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="sr-only">Completed:</span>
                    @else
                        <span aria-hidden="true">{{ $stepNumber }}</span>
                    @endif
                </div>
                <div class="{{ $labelClasses }}">
                    <div>{{ $label }}</div>
                    @if($description)
                        {{-- Optional helper text, small and muted. --}}
                        <div class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $description }}</div>
                    @endif
                </div>
            </div>
        </li>
    @endforeach
</ol>
