@props([
    'date' => null,
    'variant' => 'inline',
    'format' => null,
    'now' => null,
    'timezone' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;
    use Carbon\Carbon;

    $variantValue = match ($variant) {
        'inline', 'sticky' => $variant,
        default => WireKit::validateProp('date-separator', 'variant', $variant, ['inline', 'sticky']),
    };

    // Parse date with timezone support
    $tz = $timezone ?? config('app.timezone', 'UTC');
    $carbonDate = $date instanceof Carbon ? $date->timezone($tz) : Carbon::parse($date, $tz);
    $reference = $now instanceof Carbon ? $now->timezone($tz) : ($now ? Carbon::parse($now, $tz) : Carbon::now($tz));

    // Human-friendly relative label
    if ($format !== null) {
        $label = $carbonDate->format($format);
    } elseif ($carbonDate->isSameDay($reference)) {
        $label = __('Today');
    } elseif ($carbonDate->isSameDay($reference->copy()->subDay())) {
        $label = __('Yesterday');
    } elseif ($carbonDate->diffInDays($reference) < 7) {
        $label = $carbonDate->translatedFormat('l');
    } else {
        $label = $carbonDate->translatedFormat('M j');
    }

    // Full date for screen readers
    $fullDate = $carbonDate->translatedFormat('l, F j, Y');

    $stickyClasses = $variantValue === 'sticky'
        ? 'sticky top-0 z-[var(--z-wk-sticky,10)] py-[var(--space-wk-xs,0.25rem)]'
        : 'py-[var(--space-wk-xs,0.25rem)]';
@endphp

<div
    role="separator"
    aria-label="{{ $fullDate }}"
    {{ $attributes->class([
        WireKit::resolveClasses('date-separator', 'base', implode(' ', [
            'flex items-center',
            'text-[length:var(--text-wk-xs)]',
            'text-[var(--color-wk-text-muted)]',
            'font-[family-name:var(--font-wk-sans)]',
            $stickyClasses,
        ]), $scope),
    ]) }}
>
    <span aria-hidden="true" class="grow border-t border-[var(--color-wk-border)]"></span>
    <time
        datetime="{{ $carbonDate->toDateString() }}"
        class="shrink-0 px-[var(--space-wk-sm,0.5rem)] font-[number:var(--font-wk-heading-weight)]"
    >
        {{ $slot->isEmpty() ? $label : $slot }}
    </time>
    <span aria-hidden="true" class="grow border-t border-[var(--color-wk-border)]"></span>
</div>
