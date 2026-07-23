@props([
    'variant' => config('wirekit.components.callout.variant', 'info'),
    'icon' => true,
    'bordered' => true,
    // Opt-in one-sided accent stripe (default OFF). The plain callout is the
    // alert-style 4-sided tinted border; a one-sided colored stripe reads as
    // generic dashboard chrome, so it is no longer the default. Pass `stripe`
    // to restore the accent bar deliberately.
    'stripe' => false,
    // Optional reveal animation. Null = no animation (default, v1.5.0-identical).
    'animateIn' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $icon = BooleanProp::from($icon, true);
    $bordered = BooleanProp::from($bordered, true);
    $stripe = BooleanProp::from($stripe, false);

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('callout', $attributes->getAttributes());

    $animateAttr = WireKit::resolveAnimateIn($animateIn, 'callout');

    // Validate variant against the canonical intent set. 'primary' and 'info'
    // are visual synonyms on callout (both use --color-wk-accent — there is
    // no separate --color-wk-info token in the WireKit token surface).
    $variantValue = match ($variant) {
        'primary', 'neutral', 'info', 'success', 'warning', 'danger' => $variant,
        default => WireKit::validateProp('callout', 'variant', $variant, ['primary', 'neutral', 'info', 'success', 'warning', 'danger']),
    };

    // Callout is visually denser than Alert (15% vs 10% background tint), designed
    // for inline documentation notices. It is persistent (no dismiss), supports
    // title+body+action slots. With bordered=false the tinted background carries the
    // surface; pass `stripe` to add an opt-in one-sided accent bar.
    $baseClasses = WireKit::resolveClasses('callout', 'base', implode(' ', [
        'relative flex items-start gap-3',
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-lg)]',
        'rounded-[var(--radius-wk-lg)]',
        $bordered ? 'border-[length:var(--border-wk-width)]' : 'border-0',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    // Variant colors with stronger background tint than Alert (15% vs 10%)
    // for visual density distinction
    $variantColors = match ($variantValue) {
        'success' => [
            'border' => 'border-[color-mix(in_srgb,var(--color-wk-success)_40%,var(--color-wk-border))]',
            'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-success)_15%,var(--color-wk-bg-elevated))]',
            'icon' => 'text-[color:var(--color-wk-success)]',
            'stripe' => 'bg-[var(--color-wk-success)]',
        ],
        'warning' => [
            'border' => 'border-[color-mix(in_srgb,var(--color-wk-warning)_40%,var(--color-wk-border))]',
            'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-warning)_15%,var(--color-wk-bg-elevated))]',
            'icon' => 'text-[color:var(--color-wk-warning)]',
            'stripe' => 'bg-[var(--color-wk-warning)]',
        ],
        'danger' => [
            'border' => 'border-[color-mix(in_srgb,var(--color-wk-danger)_40%,var(--color-wk-border))]',
            'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-danger)_15%,var(--color-wk-bg-elevated))]',
            'icon' => 'text-[color:var(--color-wk-danger)]',
            'stripe' => 'bg-[var(--color-wk-danger)]',
        ],
        'neutral' => [
            'border' => 'border-[var(--color-wk-border)]',
            'bg' => 'bg-[var(--color-wk-bg-subtle)]',
            'icon' => 'text-[color:var(--color-wk-text-muted)]',
            'stripe' => 'bg-[var(--color-wk-text-muted)]',
        ],
        default => [ // primary, info
            'border' => 'border-[color-mix(in_srgb,var(--color-wk-accent)_40%,var(--color-wk-border))]',
            'bg' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_15%,var(--color-wk-bg-elevated))]',
            'icon' => 'text-[color:var(--color-wk-accent-text)]',
            'stripe' => 'bg-[var(--color-wk-accent)]',
        ],
    };

    // Default inline SVG icons per variant (reused from Alert)
    $defaultIcon = match ($variantValue) {
        'success' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />',
        'warning' => '<path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />',
        'danger' => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />',
        'neutral' => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />',
        default => '<path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />',
    };
@endphp

{{-- Callout: persistent inline notice for documentation-style content.
     Uses <aside> for semantic landmark (complementary content).
     Visually denser than Alert; opt-in one-sided accent stripe via `stripe`. --}}
<aside {{ $attributes->class([$baseClasses, $variantColors['border'], $variantColors['bg'], 'overflow-hidden']) }} @if($animateAttr) {!! $animateAttr !!} @endif>
    @if($stripe)
        {{-- Opt-in accent stripe: a one-sided colored bar, OFF by default. The
             plain callout is the alert-style 4-sided tinted border; the bar is
             added only when `stripe` is set deliberately. --}}
        <div class="absolute left-0 top-0 bottom-0 w-1 rounded-l-[var(--radius-wk-lg)] {{ $variantColors['stripe'] }}" aria-hidden="true"></div>
    @endif

    {{-- Variant icon —: iconSlot named slot wins over the
         variant-derived auto icon. The bool $icon prop continues to toggle
         the auto-icon path off when explicitly set false. --}}
    @php $hasIconSlot = isset($iconSlot) && $iconSlot->isNotEmpty(); @endphp
    @if($hasIconSlot)
        <div class="shrink-0 mt-0.5">{{ $iconSlot }}</div>
    @elseif($icon !== false)
        <div @class(['shrink-0 mt-0.5', $variantColors['icon']]) aria-hidden="true">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">{!! $defaultIcon !!}</svg>
        </div>
    @endif

    {{-- Body: title (named slot) + message (default slot) + actions (named slot) --}}
    <div class="flex-1 min-w-0">
        @isset($title)
            <div class="font-[number:var(--font-wk-heading-weight)] mb-1 text-[color:var(--color-wk-text)]">{{ $title }}</div>
        @endisset
        <div class="text-[color:var(--color-wk-text-muted)]">{{ $slot }}</div>
        @isset($actions)
            {{-- flex-wrap so a callout with two buttons (or a long single
                 button label) wraps to a second row on a phone instead of
                 overflowing the callout body. --}}
            <div class="mt-3 flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</aside>
