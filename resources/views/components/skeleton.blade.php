@props([
    'type' => 'text',     // text | avatar | card | custom
    'lines' => 3,         // for type=text
    // Animation mode: shimmer (gradient sweep, default) · pulse (opacity fade,
    // lighter on the GPU) · none (static placeholder, no animation).
    'animation' => config('wirekit.components.skeleton.animation', 'shimmer'),
    'shimmer' => true,    // legacy bool — false → pulse (kept for back-compat)
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

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

    // content-visibility: auto + contain-intrinsic-size give the
    // browser permission to SKIP rendering work for off-screen
    // skeletons entirely (no style, no layout, no paint, no composite).
    // Massive CPU/GPU savings on long lists. The intrinsic-size hint
    // prevents the page from jumping when a skeleton scrolls into view.
    // Per-type intrinsic-size defaults are tuned for the variant shape;
    // developers can override via the `style` attribute on the wrapper.
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
    aria-label="Loading"
    aria-busy="true"
    style="width: 100%; min-width: 12rem; content-visibility: auto; contain-intrinsic-size: {{ $intrinsicSize }};"
    {{ $attributes->class([$wrapperClasses]) }}
>
    @if($type === 'text')
        {{-- Text: N lines of decreasing/varied width for realistic placeholder.
             Uses inline styles for height/width/spacing to guarantee rendering
             in environments where Tailwind JIT may not scan these templates. --}}
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            @for($i = 0; $i < $lines; $i++)
                {{-- Vary widths so the stack doesn't look perfectly aligned --}}
                <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 0.75rem; width: {{ $i === $lines - 1 ? '66%' : '100%' }}; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            @endfor
        </div>

    @elseif($type === 'avatar')
        {{-- Avatar: circular placeholder + two short text lines (name + subtitle) --}}
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 2.5rem; width: 2.5rem; flex-shrink: 0; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-full);"></div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; flex: 1;">
                <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 0.75rem; width: 33%; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
                <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 0.5rem; width: 25%; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            </div>
        </div>

    @elseif($type === 'card')
        {{-- Card: image area + title + body text mimicking a content card --}}
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 8rem; width: 100%; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 1rem; width: 75%; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 0.75rem; width: 100%; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
                <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 0.75rem; width: 83%; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            </div>
        </div>

    @else
        {{-- custom: caller provides their own shape via slot --}}
        {{ $slot }}
    @endif

    {{-- Visible-only-to-AT text so screen readers announce the loading state --}}
    <span class="sr-only">Loading content</span>
</div>
