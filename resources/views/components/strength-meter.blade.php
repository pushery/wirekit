{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // The lit steps, from 0 (nothing to judge yet) to `max`. Whole steps: a fraction rounds.
    'value' => 0,
    // How many bars, and so how many steps.
    'max' => 4,
    // The meter's accessible name, and the subject of what it says when the step changes:
    // "Code strength: Strong". A step's word alone would not say what it judges.
    'label' => null,
    // What each step is called, weakest first, one per bar. Four bars default to Weak, Fair,
    // Good and Strong; any other count defaults to "2 of 5".
    'levels' => null,
    // Show the current step's word beside the bars as well.
    'showLevel' => false,
    // Speak a changed step through a polite status region. Off where the page already says it.
    'announce' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('strength-meter', $attributes->getAttributes());

    $showLevel = BooleanProp::from($showLevel, false);
    $announce = BooleanProp::from($announce, true);

    $max = max(1, (int) $max);
    $steps = is_numeric($value) ? min(max((int) round((float) $value), 0), $max) : 0;
    $label ??= __('wirekit::Strength');

    // Translated here, since a word chosen in JavaScript cannot be. Four bars have a word for
    // each step; any other count says where it stands instead of borrowing four words for it.
    if (is_array($levels) && $levels !== []) {
        $levels = array_values(array_map('strval', $levels));
    } elseif ($max === 4) {
        $levels = [__('wirekit::Weak'), __('wirekit::Fair'), __('wirekit::Good'), __('wirekit::Strong')];
    } else {
        $levels = array_map(fn (int $step): string => __('wirekit:::current of :total', ['current' => $step, 'total' => $max]), range(1, $max));
    }

    $level = $steps === 0 ? '' : ($levels[min($steps, count($levels)) - 1] ?? '');

    // The longest word any step can show. `show-level` reserves its width once, so the bars
    // keep one length whatever the current word is: sized to the word on screen, "Weak" and
    // "Strong" drew bars of two lengths in one stack of meters.
    $widestLevel = array_reduce($levels, fn (string $widest, string $word): string => mb_strlen($word) > mb_strlen($widest) ? $word : $widest, '');

    // The status sentence with the meter's name in it, and `:value` left for the factory to
    // fill with the step's word.
    $template = __('wirekit:::label: :value', ['label' => $label]);

    // The ladder of utils/strength.js, for the bars as the server draws them before Alpine
    // starts: one bar lit is danger up to a quarter of the steps, below the top is warning,
    // the top step is success, and an unlit bar is muted.
    $barColor = function (int $index) use ($steps, $max): string {
        if ($index >= $steps) {
            return 'var(--color-wk-bg-muted)';
        }

        if ($steps >= $max) {
            return 'var(--color-wk-success)';
        }

        return $steps <= max(1, intdiv($max, 4)) ? 'var(--color-wk-danger)' : 'var(--color-wk-warning)';
    };

    $classes = WireKit::resolveClasses('strength-meter', 'base', implode(' ', [
        'flex items-center gap-[var(--gap-wk-sm)]',
        'w-full min-w-0',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    $levelClasses = WireKit::resolveClasses('strength-meter', 'level', implode(' ', [
        'grid shrink-0 tabular-nums',
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
    ]), $scope);
@endphp

{{-- The step travels on `data-wk-server-value` and NOT in the `x-data` seed, which stays the same
     across renders: a seed that changed would be rewritten by the Livewire morph, and Alpine
     re-initializes a component whose `x-data` changes. The factory reads the attribute at init
     and follows it after that. --}}
<div
    {{ $attributes->class([$classes]) }}
    x-data="wirekitStrengthMeter({ max: {{ $max }}, levels: {{ AlpinePayload::from($levels) }}, template: {{ AlpinePayload::string($template) }} })"
    x-modelable="value"
    data-wk-server-value="{{ $steps }}"
>
    {{-- role="meter" carries the value, and aria-valuetext the step's word: four bars whose
         only difference is a tint and a length say nothing to a reader. The static values are
         the pre-hydration state, so a scan before Alpine has built the scope sees a complete
         meter. --}}
    <div
        role="meter"
        aria-label="{{ $label }}"
        aria-valuemin="0"
        aria-valuemax="{{ $max }}"
        aria-valuenow="{{ $steps }}"
        @if($level !== '') aria-valuetext="{{ $level }}" @endif
        x-bind:aria-valuenow="steps"
        x-bind:aria-valuetext="valueText"
        class="flex flex-1 gap-1"
    >
        @include('wirekit::components.partials.strength-bars', [
            'count' => $max,
            'colors' => array_map($barColor, range(0, $max - 1)),
        ])
    </div>

    @if($showLevel)
        {{-- The word on screen as well. aria-hidden, since the meter already says it. A one-cell
             grid: an invisible twin of the longest word shares the cell, so the cell is as wide
             as that word and never as narrow as whichever is showing. --}}
        <span aria-hidden="true" class="{{ $levelClasses }}">
            <span class="col-start-1 row-start-1 invisible whitespace-nowrap">{{ $widestLevel }}</span>
            <span class="col-start-1 row-start-1 whitespace-nowrap" x-text="level">{{ $level }}</span>
        </span>
    @endif

    @if($announce)
        {{-- Rendered empty and kept in the DOM: a live region that arrives together with its
             text is a new node, and a new node announces nothing. It is filled on a CHANGE of
             step only, so a page does not announce its meters on load. --}}
        <span class="sr-only" role="status" aria-live="polite" x-text="spoken"></span>
    @endif
</div>
