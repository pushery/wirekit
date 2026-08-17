{{-- optimistic-ui: n/a — client-only
     Its state is reading time and progress. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'target' => null,
    'wpm' => 225,
    'showRemaining' => false,
    'perParagraph' => false,
    'totalLabel' => 'min read',
    'remainingLabel' => 'min remaining',
    'paragraphLabelTemplate' => '{n} min',
    'paragraphMinWords' => 30,
    'cjkCharsPerMinute' => 500,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('reading-meta', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $showRemaining = BooleanProp::from($showRemaining, false);
    $perParagraph = BooleanProp::from($perParagraph, false);

    // Reading-meta — small text element showing "~12 min read" (initial) and
    // optionally "~5 min remaining" (after scroll). On mount, measures the
    // target's textContent word count; for articles in CJK languages where
    // whitespace tokenization underestimates, falls back to a character-based
    // estimate (--cjk-chars-per-minute / default 500).
    //
    // Skips text inside <pre>, <code>, <figure>, <figcaption>, [data-language],
    // and <img>/<picture>/<svg> — code blocks and figure captions don't read
    // at prose pace.
    //
    // perParagraph mode (Medium-style): when enabled, the component injects
    // a small `<span class="wk-reading-meta-paragraph">N min</span>`
    // annotation immediately before each <p> in the target with at least
    // `paragraphMinWords` words. Annotations show estimated remaining-time
    // FROM that paragraph onward (re-computed on scroll). Default off; opt-in.
    // aria-hidden — total/remaining display is the canonical SR reading-time.

    $wpmInt = max(50, (int) $wpm);
    $cjkCpm = max(100, (int) $cjkCharsPerMinute);
    $showRemainingBool = filter_var($showRemaining, FILTER_VALIDATE_BOOL);
    $perParagraphBool = filter_var($perParagraph, FILTER_VALIDATE_BOOL);
    $paragraphMinWordsInt = max(1, (int) $paragraphMinWords);

    // target=null resolves to the family default 'main, article' (first-match
    // wins). Same convention as reading-spine + reading-bookmark.
    $resolvedTarget = $target ?? 'main, article';

    $rootClass = WireKit::resolveClasses('reading-meta', 'base', implode(' ', [
        'wk-reading-meta',
        'inline-flex items-center gap-1',
    ]), $scope);
@endphp

<div
    {{-- The word count, the reading estimate and the per-paragraph annotations
         live in the factory (resources/js/components/reading-meta.js). Method
         shorthand does not parse under Alpine's CSP build, so the object failed
         to build and the estimate stayed at the static "~0" this markup ships. --}}
    x-data="wirekitReadingMeta({
        target: {{ \Pushery\WireKit\Support\AlpinePayload::from($resolvedTarget) }},
        wpm: {{ \Pushery\WireKit\Support\AlpinePayload::from($wpmInt) }},
        cjkCpm: {{ \Pushery\WireKit\Support\AlpinePayload::from($cjkCpm) }},
        showRemaining: {{ \Pushery\WireKit\Support\AlpinePayload::from($showRemainingBool) }},
        perParagraph: {{ \Pushery\WireKit\Support\AlpinePayload::from($perParagraphBool) }},
        paragraphMinWords: {{ \Pushery\WireKit\Support\AlpinePayload::from($paragraphMinWordsInt) }},
        paragraphTemplate: {{ \Pushery\WireKit\Support\AlpinePayload::from($paragraphLabelTemplate) }},
    })"
    role="status"
    aria-live="polite"
    {{ $attributes->merge(['style' => 'font-size: var(--reading-meta-text-size); color: var(--reading-meta-color);'])->class([$rootClass]) }}
>
    <span class="wk-reading-meta__total">
        ~<span x-text="totalMinutes"></span> {{ $totalLabel }}
    </span>
    @if ($showRemainingBool)
        <span class="wk-reading-meta__separator" aria-hidden="true">·</span>
        <span class="wk-reading-meta__remaining">
            ~<span x-text="remainingMinutes"></span> {{ $remainingLabel }}
        </span>
    @endif
</div>
