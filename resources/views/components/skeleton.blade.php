{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'type' => 'text',     // text | avatar | card | custom
    'lines' => 3,         // for type=text
    // Animation mode: shimmer (gradient sweep, default) · pulse (opacity fade,
    // lighter on the GPU) · none (static placeholder, no animation).
    'animation' => config('wirekit.components.skeleton.animation', 'shimmer'),
    'shimmer' => true,    // legacy bool — false → pulse (kept for back-compat)
    'scope' => null,
])

{{-- Spacing reads TOKENS, not literals.

     These inline styles mixed the two in one declaration — `gap: 0.5rem` beside
     `background: var(--color-wk-bg-skeleton)` — so half of a skeleton's geometry followed a
     retheme and half did not, and nothing reported the half that stayed behind. The values
     were matched against the declared ones rather than by name: `--gap-wk-sm` is 0.5rem and
     `--gap-wk-md` is 0.75rem, which is not what the names suggest at a glance.

     The remaining literals are DELIBERATE and are the placeholder's own proportions — a bar
     0.75rem tall at 66% width is what a line of text looks like at this scale, and there is no
     token for "the height of a fake line". Those are the shape of the drawing, not the
     theme. --}}
@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('skeleton', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $shimmer = BooleanProp::from($shimmer, true);

    // Shared shimmer base: .wk-skeleton applies bg color + shimmer keyframes (see dist/wirekit.css).
    // role="status" + aria-label announce loading state to screen readers.
    $baseShimmer = 'wk-skeleton bg-[var(--color-wk-bg-skeleton)] rounded-[var(--radius-wk-md)]';

    $animationValue = match ($animation) {
        'shimmer', 'pulse', 'none' => $animation,
        default => WireKit::validateProp('skeleton', 'animation', $animation, ['shimmer', 'pulse', 'none']),
    };
    // Legacy `:shimmer="false"` maps to pulse, but only when the caller did NOT
    // explicitly pick an animation (animation still at its 'shimmer' default) —
    // the new `animation` prop always wins when set.
    if ($animationValue === 'shimmer' && ! filter_var($shimmer, FILTER_VALIDATE_BOOL)) {
        $animationValue = 'pulse';
    }

    // Each .wk-skeleton element below carries this attribute. The CSS rules
    // `.wk-skeleton[data-pulse="true"]` (opacity pulse) and
    // `.wk-skeleton[data-animation="none"]` (static) switch off the gradient
    // ::after layer; shimmer (default) needs no attribute.
    $animAttr = match ($animationValue) {
        'pulse' => 'data-pulse="true"',
        'none' => 'data-animation="none"',
        default => '',
    };

    // The intrinsic-size hint for the off-screen skip, handed to CSS as a custom property.
    //
    // `content-visibility: auto` lets the browser skip style, layout, paint and composite for
    // a skeleton that is off screen, and the hint keeps the page from jumping when one scrolls
    // in.
    //
    // ⚠️ It first shipped in Safari 18.0 — ABOVE this library's floor of 16.4 — so the
    // declaration itself lives behind an `@supports` block in the stylesheet rather than as an
    // inline style here. Nothing depends on it: without support every skeleton renders
    // normally, which is what they all did before the optimization existed.
    //
    // Per-type defaults are tuned for the variant shape; developers override them by setting
    // `--wk-skeleton-intrinsic-size` in their own CSS or via the `style` attribute.
    $intrinsicSize = match ($type) {
        'avatar' => 'auto 60px',
        'card' => 'auto 200px',
        'text' => 'auto 80px',
        default => 'auto 100px',
    };

    $wrapperClasses = WireKit::resolveClasses('skeleton', 'base', implode(' ', [
        'block',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

<div
    role="status"
    aria-live="polite"
    aria-label="{{ __('wirekit::Loading') }}"
    {{-- No aria-busy here, deliberately. This element IS the live region, and
         WAI-ARIA defines aria-busy on a live region as "wait before exposing
         this to the user" — set to true and never flipped back, it tells
         assistive technology to withhold the very announcement the region
         exists to make, and the skeleton is removed from the page rather than
         marked done, so the flip never comes. The busy signal belongs on the
         container whose content is still missing, which is the developer's
         element; leaving the attribute off is also what lets them put it there
         and have the shimmer's own pause rule see it. --}}
    {{ $attributes->merge(['style' => 'width: 100%; min-width: 12rem; --wk-skeleton-intrinsic-size: '.($intrinsicSize).';'])->class([$wrapperClasses, 'wk-skeleton-skip-offscreen']) }}
>
    @if($type === 'text')
        {{-- Text: N lines of decreasing/varied width for realistic placeholder.
             Uses inline styles for height/width/spacing to guarantee rendering
             in environments where Tailwind JIT may not scan these templates. --}}
        <div style="display: flex; flex-direction: column; gap: var(--gap-wk-sm);">
            @for($i = 0; $i < $lines; $i++)
                {{-- Vary widths so the stack doesn't look perfectly aligned --}}
                <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 0.75rem; width: {{ $i === $lines - 1 ? '66%' : '100%' }}; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            @endfor
        </div>

    @elseif($type === 'avatar')
        {{-- Avatar: circular placeholder + two short text lines (name + subtitle) --}}
        <div style="display: flex; align-items: center; gap: var(--gap-wk-md);">
            <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: var(--size-wk-md); width: var(--size-wk-md); flex-shrink: 0; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-full);"></div>
            <div style="display: flex; flex-direction: column; gap: var(--gap-wk-sm); flex: 1;">
                <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 0.75rem; width: 33%; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
                <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 0.5rem; width: 25%; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            </div>
        </div>

    @elseif($type === 'card')
        {{-- Card: image area + title + body text mimicking a content card --}}
        <div style="display: flex; flex-direction: column; gap: var(--gap-wk-md);">
            <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 8rem; width: 100%; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 1rem; width: 75%; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            <div style="display: flex; flex-direction: column; gap: var(--gap-wk-sm);">
                <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 0.75rem; width: 100%; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
                <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 0.75rem; width: 83%; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            </div>
        </div>

    @else
        {{-- custom: caller provides their own shape via slot --}}
        {{ $slot }}
    @endif

    {{-- Visible-only-to-AT text so screen readers announce the loading state --}}
    <span class="sr-only">{{ __('wirekit::Loading content') }}</span>
</div>
