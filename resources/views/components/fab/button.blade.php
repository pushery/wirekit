@props([
    // The trigger is an icon, so this is its whole accessible name.
    'label' => __('Action'),
    // Where it floats — the inline corner, following the writing direction.
    'position' => config('wirekit.components.fab.position', 'end'),
    // What the button opens, so assistive tech announces it correctly. A
    // single-action FAB is NOT a menu — the default is 'dialog' (the canonical use
    // is a feedback / compose modal). Pass 'menu' / 'true' / 'listbox' / 'grid' /
    // 'tree' as needed, or a falsy value to omit aria-haspopup entirely (a plain
    // action that navigates or fires an event rather than opening a popup).
    'haspopup' => 'dialog',
    // Render an <a> instead of a <button> when the action is a navigation.
    'href' => null,
    // Optional icon name (resolved via the WireKit icon system). A slot overrides it.
    'icon' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    $position = WireKit::validateProp('fab.button', 'position', $position, ['end', 'start', 'center']);

    // Positioning mirrors the speed-dial <x-wirekit::fab>: the shared `.wk-fab`
    // class carries the block-end offset INCLUDING env(safe-area-inset-bottom) (so
    // the iOS home indicator never overlaps it), and the inline offset folds in the
    // matching horizontal safe-area inset for a landscape notch on that side.
    $positionClass = match ($position) {
        'start' => 'start-[calc(var(--padding-wk-x-lg)_+_env(safe-area-inset-left))]',
        'center' => 'left-1/2 -translate-x-1/2',
        default => 'end-[calc(var(--padding-wk-x-lg)_+_env(safe-area-inset-right))]',
    };

    $tag = $href ? 'a' : 'button';

    // A falsy value means "this button does not open a popup" — omit the attribute
    // rather than emit aria-haspopup="false" (which some ATs still announce).
    $haspopupValue = BooleanProp::isFalse($haspopup) ? null : $haspopup;

    // Auto-inject rel="noopener noreferrer" when target="_blank" on an <a>, and
    // force-override any caller rel so the tabnabbing guard can't be defeated —
    // same pattern as sidebar.item / link / button.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);

    $classes = WireKit::resolveClasses('fab.button', 'base', implode(' ', [
        'wk-fab',
        'fixed z-40',
        'flex h-14 w-14 cursor-pointer items-center justify-center',
        $positionClass,
        'rounded-[var(--radius-wk-full)]',
        'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
        'shadow-[var(--shadow-wk-lg)]',
        'transition-transform duration-[var(--transition-wk-duration)]',
        'hover:brightness-110',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-2',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @else type="button" @endif
    @if($haspopupValue !== null) aria-haspopup="{{ $haspopupValue }}" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    aria-label="{{ $label }}"
    data-wk-fab-button
    data-position="{{ $position }}"
    {{ $attributes->except('rel')->class([$classes]) }}
>
    {{-- Icon — decorative; the label is the accessible name. A slot wins; then a
         bare icon name resolves via the WireKit icon system (consistent with
         sidebar.item / fab.action); otherwise a neutral plus stands in. --}}
    <span class="inline-grid place-items-center" aria-hidden="true">
        @if(isset($slot) && $slot->isNotEmpty())
            {{ $slot }}
        @elseif(is_string($icon) && $icon !== '' && ! str_contains($icon, '<') && function_exists('svg'))
            {{ svg(WireKit::icon($icon), ['class' => 'h-6 w-6']) }}
        @else
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
        @endif
    </span>
</{{ $tag }}>
