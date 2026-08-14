{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason
     for any file that renders one. --}}
@props([
    // Color role. Same vocabulary as badge / button, so a call site that knows
    // one knows this.
    'intent' => config('wirekit.components.step-marker.intent', 'neutral'),
    'size' => config('wirekit.components.step-marker.size', 'md'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('step-marker', $attributes->getAttributes());

    $intent = WireKit::validateProp(
        'step-marker',
        'intent',
        $intent,
        ['primary', 'accent', 'success', 'warning', 'danger', 'info', 'neutral'],
    );

    $size = WireKit::validateProp('step-marker', 'size', $size, ['sm', 'md', 'lg']);

    // A FILLED chip, always — that is what distinguishes a step marker from a
    // badge. The number is the whole content, so it has to read at a glance
    // against a surface, not sit in a tint.
    //
    // Literal class strings rather than interpolation, because Tailwind's
    // scanner reads this file as text: a class assembled from a variable is a
    // class that never reaches the stylesheet, and the failure is an unstyled
    // chip rather than an error.
    //
    // `info` borrows the accent fill and `neutral` inverts the text/background
    // pair — both mirror badge's solid map exactly, so the two components cannot
    // drift into meaning different things by the same name. Every pair here is
    // an intent base against its own `-fg`, which is what carries the contrast.
    $intentClasses = match ($intent) {
        'primary', 'accent', 'info' => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
        'success' => 'bg-[var(--color-wk-success)] text-[color:var(--color-wk-success-fg)]',
        'warning' => 'bg-[var(--color-wk-warning)] text-[color:var(--color-wk-warning-fg)]',
        'danger' => 'bg-[var(--color-wk-danger)] text-[color:var(--color-wk-danger-fg)]',
        default => 'bg-[var(--color-wk-text)] text-[color:var(--color-wk-bg)]',
    };

    // Square-with-soft-corners, not a pill: `01` in a pill reads as a badge, and
    // a badge is a label about something else. A step marker IS the step.
    $sizeClasses = match ($size) {
        'sm' => 'h-8 w-8 text-[length:var(--text-wk-xs)] rounded-[var(--radius-wk-sm)]',
        'lg' => 'h-12 w-12 text-[length:var(--text-wk-md)] rounded-[var(--radius-wk-lg)]',
        default => 'h-10 w-10 text-[length:var(--text-wk-sm)] rounded-[var(--radius-wk-md)]',
    };

    $classes = WireKit::resolveClasses('step-marker', 'base', implode(' ', [
        'inline-flex items-center justify-center',
        'shrink-0',
        'font-[number:var(--font-wk-heading-weight)]',
        'font-[family-name:var(--font-wk-sans)]',
        'leading-none',
        $sizeClasses,
        $intentClasses,
    ]), $scope);
@endphp

{{-- No `role`, deliberately.
     The marker repeats what the step's own heading already says — its number
     and its order. Given a role it becomes a second announcement of the same
     fact, and a screen-reader user hears "01" before every heading with no way
     to know it adds nothing. Presentational by omission is the correct shape
     here; a decorative element that stays quiet is not an accessibility gap. --}}
<span {{ $attributes->class([$classes]) }}>{{ $slot }}</span>