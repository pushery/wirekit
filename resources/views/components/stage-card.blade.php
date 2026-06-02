@props([
    // Visible stage title (e.g. "In progress", "Q3 2026", "Backlog").
    'label' => null,
    // Semantic colour for the left stripe + tinted body.
    // primary | success | warning | danger | info | neutral.
    'intent' => 'neutral',
    // Optional item count rendered as a pill in the header.
    'count' => null,
    // Optional 0..100 completion percentage rendered as a thin progress bar.
    'progress' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Validate the intent first (throws in debug / falls back in prod), then
    // map to its colour token. Mirrors stat / badge: info+primary share
    // accent, neutral uses the muted text token.
    $validIntent = match ($intent) {
        'primary', 'accent', 'success', 'warning', 'danger', 'info', 'neutral' => $intent,
        default => WireKit::validateProp('stage-card', 'intent', $intent, ['primary', 'accent', 'success', 'warning', 'danger', 'info', 'neutral']),
    };
    $intentToken = match ($validIntent) {
        'success' => 'var(--color-wk-success)',
        'warning' => 'var(--color-wk-warning)',
        'danger' => 'var(--color-wk-danger)',
        'neutral' => 'var(--color-wk-text-muted)',
        default => 'var(--color-wk-accent)', // primary + accent + info
    };

    $classes = WireKit::resolveClasses('stage-card', 'base', implode(' ', [
        'flex flex-col gap-3',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)]',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // 3px intent stripe + 6%-tinted body via inline style (overrides the base
    // border-left + bg without a per-intent class explosion). color-mix and
    // the colour tokens are in the Tailwind baseline.
    $stripeStyle = "border-left-width: 3px; border-left-color: {$intentToken}; background-color: color-mix(in srgb, {$intentToken} 6%, var(--color-wk-bg-elevated));";

    // Clamp the optional progress to [0, 100].
    $progressValue = $progress === null ? null : max(0, min(100, (int) $progress));
@endphp

{{-- role="group" + aria-label makes a screen reader announce the stage's
     title as the purpose of the grouped items (kanban column / pipeline
     stage / roadmap quarter). --}}
<div
    {{ $attributes->class([$classes]) }}
    style="{{ $stripeStyle }}"
    @if($label) role="group" aria-label="{{ $label }}" @endif
>
    @if($label || $count !== null)
        <div class="flex items-center justify-between gap-2">
            @if($label)
                <span class="text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">
                    {{ $label }}
                </span>
            @endif
            @if($count !== null)
                {{-- aria-label spells out "N items" (not a bare number) so the
                     count is announced with its unit. --}}
                <span
                    aria-label="{{ $count }} items"
                    class="inline-flex items-center justify-center rounded-[var(--radius-wk-full)] bg-[var(--color-wk-bg-muted)] px-[var(--padding-wk-x-sm)] text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text-muted)] tabular-nums"
                >
                    {{ $count }}
                </span>
            @endif
        </div>
    @endif

    @if($progressValue !== null)
        <x-wirekit::progress :value="$progressValue" :intent="$validIntent" size="sm" aria-label="{{ $label ? $label.' progress' : 'Stage progress' }}" />
    @endif

    @if(trim($slot->toHtml()) !== '')
        <div class="flex flex-col gap-2">
            {{ $slot }}
        </div>
    @endif
</div>
