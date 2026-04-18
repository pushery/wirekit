@props([
    'type' => 'text', // text | avatar | card | custom
    'lines' => 3,     // for type=text
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Shared shimmer base: .wk-skeleton applies bg color + shimmer keyframes (see dist/wirekit.css).
    // role="status" + aria-label announce loading state to screen readers.
    $baseShimmer = 'wk-skeleton bg-[var(--color-wk-bg-muted)] rounded-[var(--radius-wk-md)]';

    // Pre-compute classes for each skeleton type
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
    {{ $attributes->class([$wrapperClasses]) }}
>
    @if($type === 'text')
        {{-- Text: N lines of decreasing/varied width for realistic placeholder.
             Uses inline styles for height/width/spacing to guarantee rendering
             in environments where Tailwind JIT may not scan these templates. --}}
        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            @for($i = 0; $i < $lines; $i++)
                {{-- Vary widths so the stack doesn't look perfectly aligned --}}
                <div class="{{ $baseShimmer }}" style="height: 0.75rem; width: {{ $i === $lines - 1 ? '66%' : '100%' }}; background: var(--color-wk-bg-muted); border-radius: var(--radius-wk-md);"></div>
            @endfor
        </div>

    @elseif($type === 'avatar')
        {{-- Avatar: circular placeholder + two short text lines (name + subtitle) --}}
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <div class="{{ $baseShimmer }}" style="height: 2.5rem; width: 2.5rem; flex-shrink: 0; background: var(--color-wk-bg-muted); border-radius: var(--radius-wk-full);"></div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; flex: 1;">
                <div class="{{ $baseShimmer }}" style="height: 0.75rem; width: 33%; background: var(--color-wk-bg-muted); border-radius: var(--radius-wk-md);"></div>
                <div class="{{ $baseShimmer }}" style="height: 0.5rem; width: 25%; background: var(--color-wk-bg-muted); border-radius: var(--radius-wk-md);"></div>
            </div>
        </div>

    @elseif($type === 'card')
        {{-- Card: image area + title + body text mimicking a content card --}}
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div class="{{ $baseShimmer }}" style="height: 8rem; width: 100%; background: var(--color-wk-bg-muted); border-radius: var(--radius-wk-md);"></div>
            <div class="{{ $baseShimmer }}" style="height: 1rem; width: 75%; background: var(--color-wk-bg-muted); border-radius: var(--radius-wk-md);"></div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <div class="{{ $baseShimmer }}" style="height: 0.75rem; width: 100%; background: var(--color-wk-bg-muted); border-radius: var(--radius-wk-md);"></div>
                <div class="{{ $baseShimmer }}" style="height: 0.75rem; width: 83%; background: var(--color-wk-bg-muted); border-radius: var(--radius-wk-md);"></div>
            </div>
        </div>

    @else
        {{-- custom: caller provides their own shape via slot --}}
        {{ $slot }}
    @endif

    {{-- Visible-only-to-AT text so screen readers announce the loading state --}}
    <span class="sr-only">Loading content</span>
</div>
