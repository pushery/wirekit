{{-- optimistic-ui: n/a — client-only
     Its state is which step is showing. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. Whatever the STEPS ask of the server
     is theirs to declare. --}}
@props([
    // Step names, in order. They drive the indicator and the announcement, so a flow with
    // no names still works — it announces "Step 2 of 4" instead of naming the place.
    'steps' => [],
    // Which step shows first, 1-based.
    'current' => 1,
    // Draw the progress indicator above the panel. `false` when the surrounding page
    // already shows progress its own way — two indicators for one flow is worse than one.
    'indicator' => true,
    'orientation' => config('wirekit.components.wizard.orientation', 'horizontal'),
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('wizard', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `indicator="false"` would draw the stepper it was asked to suppress.
    $indicator = BooleanProp::from($indicator, true);

    $stepNames = array_values(array_map(
        fn ($step) => is_array($step) ? ($step['label'] ?? '') : (string) $step,
        is_array($steps) ? $steps : []
    ));

    $total = max(1, count($stepNames));
    $current = max(1, min($total, (int) $current));

    $classes = WireKit::resolveClasses('wizard', 'base', implode(' ', [
        // w-full because the stepper inside already carries it, and a percentage
        // resolves against a parent that has to have a width of its own. Without it
        // the wizard is a flex column with no width, so it takes the intrinsic width
        // of whichever step is showing -- and the step indicator, which should be a
        // stable frame around the flow, changed size as the reader moved through it.
        // On a short step it shrank far enough that a two-word label wrapped mid-word.
        'flex flex-col w-full gap-[var(--padding-wk-y-lg)]',
    ]), $scope);

    $panelClasses = WireKit::resolveClasses('wizard', 'panel', '', $scope);

    // The announcement is a TEMPLATE rather than a sentence built here, because it has to
    // be translatable — a sentence assembled from fragments in JavaScript cannot be, and
    // word order is not the same in every language.
    $announcementTemplate = $stepNames !== [] && trim($stepNames[0] ?? '') !== ''
        ? __('wirekit::Step :current of :total: :label')
        : __('wirekit::Step :current of :total');
@endphp

<div
    x-data="wirekitWizard({
        current: {{ $current }},
        total: {{ $total }},
        labels: {{ \Pushery\WireKit\Support\AlpinePayload::from($stepNames) }},
        announcement: {{ \Pushery\WireKit\Support\AlpinePayload::from($announcementTemplate) }}
    })"
    {{ $attributes->class([$classes]) }}
>
    @if($indicator && $stepNames !== [])
        {{-- The existing indicator, USED rather than reimplemented. It stays presentational:
             it reflects `current` and owns no navigation, which is the position
             docs/components/stepper.md takes and this component does not overturn. --}}
        <div>
            <x-wirekit::stepper
                :steps="$stepNames"
                :current="$current"
                :orientation="$orientation"
                :scope="$scope"
               
            />
        </div>
    @endif

    {{-- The announcement region, and it is present from the first render rather than
         created when the first change happens. A live region added to the document at the
         moment it has something to say is frequently not announced at all — assistive
         technology has to be observing it beforehand. It stays empty until a step
         changes, so nothing is read on load. --}}
    <div class="sr-only" role="status" aria-live="polite" x-text="announcement"></div>

    <div class="{{ $panelClasses }}">
        {{ $slot }}
    </div>

    @isset($controls)
        {{ $controls }}
    @else
        {{-- Default controls. Ordinary buttons, so the keyboard model is the browser's and
             there is nothing bespoke to get wrong. `aria-disabled` rather than `disabled`
             on Next for the same reason the alert-dialog uses it: a disabled button leaves
             the tab order, so the reason it will not advance is never announced. --}}
        <x-wirekit::row justify="between">
            <x-wirekit::button
                intent="neutral"
                surface="ghost"
                type="button"
                x-on:click="prev()"
                x-bind:aria-disabled="isFirst ? 'true' : null"
                x-bind:hidden="isFirst ? true : null"
            >{{ __('wirekit::Back') }}</x-wirekit::button>

            <x-wirekit::button
                intent="primary"
                type="button"
                x-on:click="next()"
                x-bind:aria-disabled="canAdvance ? null : 'true'"
                x-bind:hidden="isLast ? true : null"
            >{{ __('wirekit::Next') }}</x-wirekit::button>
        </x-wirekit::row>
    @endisset
</div>
