{{-- optimistic-ui: n/a — navigation
     A completed step may carry `href` or `wire:click`, so this file CAN render an interactive
     element. What it never renders is an action with a result to show early: the step either
     navigates or hands the click to the application, and the stepper's own state comes from
     `current` on the next render. --}}
@props([
    'steps' => [],
    'current' => 1,
    'orientation' => config('wirekit.components.stepper.orientation', 'horizontal'),
    // The name of an Alpine property that holds the current step, 1-based, for a flow that
    // changes step in the browser: the stepper then follows it. `current` still draws the first
    // paint. `wizard` passes its own `current` here.
    'follow' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('stepper', $attributes->getAttributes());

    // A stepper shows progress through a multi-step flow. Each step is either
    // completed (index < current), current (index == current), or upcoming
    // (index > current). The visual treatment and ARIA semantics differ per state.
    $isVertical = $orientation === 'vertical';

    // `follow` is written into Alpine expressions below, so it has to be a plain identifier:
    // anything else would be code in an attribute. A name that is not one is dropped, and said
    // so where a developer is looking.
    if ($follow !== null && (! is_string($follow) || preg_match('/^[A-Za-z_$][\w$]*$/', $follow) !== 1)) {
        if (config('app.debug')) {
            throw new \InvalidArgumentException('[wirekit] stepper: `follow` takes the name of an Alpine property, such as "current"; '
                .var_export($follow, true).' is not one.');
        }

        $follow = null;
    }

    // How many steps there are, for the line the compact form draws under the row: it names
    // the current step as "Step 3 of 7", and the shipped stylesheet stretches it over every
    // column by this count (`--wk-stepper-count` on the list, `--wk-stepper-index` per step).
    $count = is_countable($steps) ? count($steps) : 0;

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
        // horizontal row. A long word breaks at a syllable, with a hyphen, where the
        // document's `lang` says how; the arbitrary break stays the fallback for a word the
        // browser cannot hyphenate. A column narrower than a word does not get this far: the
        // shipped stylesheet draws the compact form there (see the caption below).
        $isVertical ? '' : 'max-w-full [overflow-wrap:anywhere] hyphens-auto',
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
<ol data-wk-prose-skip data-wk-stepper="{{ $isVertical ? 'vertical' : 'horizontal' }}" role="list" aria-label="{{ __('wirekit::Progress') }}" {{ $attributes->merge(['style' => 'list-style: none; margin: 0; padding: 0; --wk-stepper-count: '.$count.';'])->class([$listClasses]) }}>
    @foreach($steps as $i => $step)
        @php
            // Normalize: accept a string (label only) or ['label' => .., 'description' => ..].
            $label = is_array($step) ? ($step['label'] ?? '') : (string) $step;
            $description = is_array($step) ? ($step['description'] ?? null) : null;
            $stepNumber = $i + 1;

            // A step may carry a destination or a Livewire action. Both are third keys on a
            // shape that already takes `label` and `description`, which is why this is the
            // small variant: no new concept, and a plain string step keeps working untouched.
            $stepHref = is_array($step) ? ($step['href'] ?? null) : null;
            $stepAction = is_array($step) ? ($step['wire:click'] ?? $step['action'] ?? null) : null;

            $isCompleted = $stepNumber < $current;
            $isCurrent = $stepNumber === $current;

            // ONLY a completed step becomes operable, and the restriction is the point rather
            // than caution. A stepper looks like the way back, so a finished step that does not
            // answer a click costs the reader a click and a guess about the state of the page.
            // A FUTURE step is the opposite case: making it operable would offer a jump the
            // application never said was allowed, and the component cannot know whether it is.
            // The current step is already where the reader is.
            // A stepper that follows a flow in the browser has no operable steps: which steps
            // are completed changes after this render, and a link cannot become a div later.
            $stepIsOperable = $follow === null && $isCompleted && ($stepHref !== null || $stepAction !== null);
            $stepTag = ! $stepIsOperable ? 'div' : ($stepHref !== null ? 'a' : 'button');
            $isLast = $i === array_key_last($steps);

            // Visual treatment per state. Completed: filled accent. Current:
            // outlined accent (active ring). Upcoming: muted outline.
            $completedClasses = 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)] border-[var(--color-wk-accent)]';
            $currentClasses = 'bg-[var(--color-wk-bg)] text-[color:var(--color-wk-accent-text)] border-[var(--color-wk-accent)]';
            $upcomingClasses = 'bg-[var(--color-wk-bg)] text-[color:var(--color-wk-text-muted)] border-[var(--color-wk-border)]';
            $stateClasses = $isCompleted ? $completedClasses : ($isCurrent ? $currentClasses : $upcomingClasses);
        @endphp

        <li data-wk-prose-skip
            data-wk-stepper-step
            class="{{ $itemClasses }}"
            @unless($isVertical) style="--wk-stepper-index: {{ $stepNumber - 1 }};" @endunless
            @if($isCurrent) aria-current="step" @endif
            @if($follow !== null) x-bind:aria-current="{{ $follow }} === {{ $stepNumber }} ? 'step' : null" @endif
        >
            {{-- Connector: drawn for all but the last step. Lives inside <li>
                 as absolutely positioned element so it never breaks flow. --}}
            @unless($isLast)
                <span class="{{ $connectorClasses }}" aria-hidden="true"></span>
            @endunless

            {{-- The tag is chosen above, and it is a PLAIN HTML tag rather than a component, so
                 building it from a variable is safe here — Blade's component scanner is what
                 cannot cope with that, and there is no component in this line.

                 The interactive element wraps the circle AND the label, so its accessible name
                 is computed from everything inside it — which is more than the label. The
                 circle contributes the visually hidden "Completed:" (the check is aria-hidden,
                 and the number never appears here at all, since only a completed step is ever
                 operable), then the label, then the description when the step carries one. A
                 completed "Details" step described as "Sent yesterday" is therefore announced
                 as "Completed: Details Sent yesterday", not as "Details". That reads well as
                 long as the description is written to be heard: on an operable step it is part
                 of the link's name rather than a caption beside it.
                 `cursor-pointer` because Tailwind v4 sets `cursor: default` on `<button>` — the
                 reverse of v3 — so every button in this package puts the affordance back. --}}
            <{{ $stepTag }} data-wk-prose-skip
                @if($stepTag === 'a') href="{{ $stepHref }}" @endif
                @if($stepTag === 'button') type="button" wire:click="{{ $stepAction }}" @endif
                @class([
                    'flex relative',
                    $isVertical ? 'flex-row items-start gap-[var(--padding-wk-x-sm)]' : 'flex-col items-center',
                    'cursor-pointer text-start' => $stepIsOperable,
                    'rounded-[var(--radius-wk-md)]' => $stepIsOperable,
                    'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]' => $stepIsOperable,
                    'transition-opacity duration-[var(--transition-wk-duration)] hover:opacity-80' => $stepIsOperable,
                ])
            >
                @if($follow === null)
                    <div class="{{ $circleBase }} {{ $stateClasses }}">
                        @if($isCompleted)
                            {{-- Check mark — decorative; state is communicated via aria-current / visually-hidden text. --}}
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M16.704 5.29a.75.75 0 010 1.06l-7.5 7.5a.75.75 0 01-1.06 0l-3.5-3.5a.75.75 0 111.06-1.06L8.674 12.23l6.97-6.94a.75.75 0 011.06 0z" clip-rule="evenodd"/>
                            </svg>
                            {{-- The colon stays outside `__()`, the way alert prefixes its variant
                                 word: the catalog keys a plain label, and the punctuation that
                                 joins it to what follows belongs to this template. --}}
                            <span class="sr-only">{{ __('wirekit::Completed') }}:</span>
                        @else
                            <span aria-hidden="true">{{ $stepNumber }}</span>
                        @endif
                    </div>
                @else
                    {{-- Following a flow in the browser: the circle is drawn in all three states and
                         the one matching the property is shown. A class binding cannot switch
                         between the states, because Alpine never removes a class the markup was
                         sent with. The hidden ones ship as `display: none` so the first paint is
                         right before Alpine runs, and `display: none` keeps the "Completed:" of a
                         step that is not completed out of what a screen reader hears. --}}
                    <div class="{{ $circleBase }} {{ $completedClasses }}" @unless($isCompleted) style="display: none;" @endunless x-show="{{ $follow }} > {{ $stepNumber }}">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M16.704 5.29a.75.75 0 010 1.06l-7.5 7.5a.75.75 0 01-1.06 0l-3.5-3.5a.75.75 0 111.06-1.06L8.674 12.23l6.97-6.94a.75.75 0 011.06 0z" clip-rule="evenodd"/>
                        </svg>
                        <span class="sr-only">{{ __('wirekit::Completed') }}:</span>
                    </div>
                    <div class="{{ $circleBase }} {{ $currentClasses }}" @unless($isCurrent) style="display: none;" @endunless x-show="{{ $follow }} === {{ $stepNumber }}">
                        <span aria-hidden="true">{{ $stepNumber }}</span>
                    </div>
                    <div class="{{ $circleBase }} {{ $upcomingClasses }}" @if($isCompleted || $isCurrent) style="display: none;" @endif x-show="{{ $follow }} < {{ $stepNumber }}">
                        <span aria-hidden="true">{{ $stepNumber }}</span>
                    </div>
                @endif
                <div data-wk-stepper-label class="{{ $labelClasses }}">
                    <div>{{ $label }}</div>
                    @if($description)
                        {{-- Optional helper text, small and muted. --}}
                        <div class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $description }}</div>
                    @endif
                </div>
            </{{ $stepTag }}>

            {{-- The compact form's line under the row. Every horizontal step carries its own,
                 and the stylesheet shows the current step's only when the columns have become
                 narrower than a word, so the line follows `aria-current` wherever it moves.
                 `aria-hidden`: a screen reader already hears the step's label, which the
                 compact form hides only visually, and `aria-current` on the step. --}}
            @unless($isVertical)
                <div data-wk-stepper-caption aria-hidden="true">{{ trim($label) !== ''
                    ? __('wirekit::Step :current of :total: :label', ['current' => $stepNumber, 'total' => $count, 'label' => $label])
                    : __('wirekit::Step :current of :total', ['current' => $stepNumber, 'total' => $count]) }}</div>
            @endunless
        </li>
    @endforeach
</ol>
