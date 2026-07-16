@props([
    'author' => [],
    'timestamp' => null,
    'side' => 'left',
    'intent' => 'neutral',
    'edited' => false,
    // How the actions slot is revealed. 'hover' (default) shows it on hover OR
    // keyboard focus; 'always' keeps it visible — the accessible path for touch
    // (no hover) and for surfaces where the actions should always be reachable.
    'actionsReveal' => config('wirekit.components.message.actions-reveal', 'hover'),
    // Carbon format string for the timestamp. Null (default) renders the
    // locale-aware short time via isoFormat('LT') — "9:15 PM" for en (identical
    // to the old hardcoded format), "21:15" for de and other 24h locales. Pass an
    // explicit format (e.g. 'H:i') to override.
    'timeFormat' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;
    use Carbon\Carbon;

    $sideValue = match ($side) {
        'left', 'right' => $side,
        default => WireKit::validateProp('message', 'side', $side, ['left', 'right']),
    };

    // 'primary' aligns the intent enum with <x-wirekit::button> and the
    // canonical VariantResolver::INTENTS set. Visually equivalent to 'info'
    // on message bubbles (both tint with --color-wk-accent) — pick whichever
    // expresses your intent semantically.
    $intentValue = match ($intent) {
        'neutral', 'primary', 'info', 'success', 'warning', 'danger' => $intent,
        default => WireKit::validateProp('message', 'intent', $intent, ['neutral', 'primary', 'info', 'success', 'warning', 'danger']),
    };

    // Actions reveal. 'hover' reveals on hover OR keyboard focus (focus-within),
    // so a keyboard user tabbing to the action control makes it visible — the
    // old group-hover-only reveal was invisible to touch AND keyboard (and never
    // fired at all, since the <article> carried no `group` class). 'always' keeps
    // the actions visible for touch surfaces.
    $actionsRevealValue = match ($actionsReveal) {
        'hover', 'always' => $actionsReveal,
        default => WireKit::validateProp('message', 'actionsReveal', $actionsReveal, ['hover', 'always']),
    };
    $actionsRevealClasses = $actionsRevealValue === 'always'
        ? ''
        : 'opacity-0 focus-within:opacity-100 group-hover:opacity-100 transition-opacity duration-[var(--transition-wk-duration)]';

    // Parse author data
    $authorName = is_array($author) ? ($author['name'] ?? '') : (string) $author;
    $authorAvatar = is_array($author) ? ($author['avatar'] ?? null) : null;
    $authorRole = is_array($author) ? ($author['role'] ?? null) : null;

    // Format timestamp
    $carbonTimestamp = null;
    $formattedTime = '';
    if ($timestamp !== null) {
        $carbonTimestamp = $timestamp instanceof Carbon ? $timestamp : Carbon::parse($timestamp);
        // Locale-aware short time by default (isoFormat('LT') honors the Carbon
        // locale): "9:15 PM" for en — byte-identical to the old hardcoded
        // 'g:i A' — but "21:15" for de and other 24h locales. An explicit
        // timeFormat overrides with a raw Carbon format string.
        $formattedTime = $timeFormat !== null
            ? $carbonTimestamp->format($timeFormat)
            : $carbonTimestamp->isoFormat('LT');
    }

    // Alignment
    $alignClass = $sideValue === 'right' ? 'flex-row-reverse' : 'flex-row';
    $textAlign = $sideValue === 'right' ? 'items-end' : 'items-start';

    // Bubble background + border based on side and intent.
    // 'primary' and 'info' both tint with --color-wk-accent — they are visual
    // synonyms here, distinguished only by the prop value the developer passed.
    // Non-neutral intents tint BOTH the background AND the border (mirrors the
    // callout palette) so a system message reads as a colored callout rather
    // than a tinted bubble inside a generic gray frame.
    $bubbleClasses = match (true) {
        $intentValue !== 'neutral' => match ($intentValue) {
            'primary', 'info' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_8%,var(--color-wk-bg-elevated))] border-[color-mix(in_srgb,var(--color-wk-accent)_40%,var(--color-wk-border))]',
            'success' => 'bg-[color-mix(in_srgb,var(--color-wk-success)_8%,var(--color-wk-bg-elevated))] border-[color-mix(in_srgb,var(--color-wk-success)_40%,var(--color-wk-border))]',
            'warning' => 'bg-[color-mix(in_srgb,var(--color-wk-warning)_8%,var(--color-wk-bg-elevated))] border-[color-mix(in_srgb,var(--color-wk-warning)_40%,var(--color-wk-border))]',
            'danger' => 'bg-[color-mix(in_srgb,var(--color-wk-danger)_8%,var(--color-wk-bg-elevated))] border-[color-mix(in_srgb,var(--color-wk-danger)_40%,var(--color-wk-border))]',
        },
        $sideValue === 'right' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_10%,var(--color-wk-bg-elevated))] border-[var(--color-wk-border-subtle)]',
        default => 'bg-[var(--color-wk-bg-elevated)] border-[var(--color-wk-border-subtle)]',
    };

    $baseClasses = WireKit::resolveClasses('message', 'base', implode(' ', [
        // `group` enables the actions reveal on hover/focus of the whole message
        // (the old markup referenced group-hover with no group ancestor, so the
        // actions never appeared at all).
        'group',
        'flex gap-[var(--space-wk-sm,0.5rem)]',
        $alignClass,
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    $messageId = 'message-' . md5($authorName . ($timestamp ?? uniqid()));
@endphp

<article
    role="article"
    aria-labelledby="{{ $messageId }}-header"
    {{ $attributes->class([$baseClasses]) }}
>
    {{-- Avatar --}}
    @if($authorAvatar || $authorName)
        <div class="shrink-0">
            <x-wirekit::avatar
                :src="$authorAvatar"
                :alt="$authorName"
                size="sm"
            />
        </div>
    @endif

    {{-- Message content --}}
    <div class="flex flex-col {{ $textAlign }} gap-[var(--space-wk-xs,0.25rem)] min-w-0 max-w-[42ch]">
        {{-- Header: name + timestamp --}}
        <span id="{{ $messageId }}-header" class="flex items-center gap-[var(--space-wk-sm,0.5rem)] text-[length:var(--text-wk-sm)]">
            <span class="font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)] truncate">
                {{ $authorName }}
            </span>
            @if($authorRole)
                <x-wirekit::badge size="sm" intent="neutral">{{ $authorRole }}</x-wirekit::badge>
            @endif
            @if($carbonTimestamp)
                <time
                    datetime="{{ $carbonTimestamp->toIso8601String() }}"
                    class="text-[color:var(--color-wk-text-muted)] whitespace-nowrap"
                >
                    {{ $formattedTime }}
                </time>
            @endif
            @if($edited)
                <span class="text-[color:var(--color-wk-text-muted)] italic">{{ __('(edited)') }}</span>
            @endif
        </span>

        {{-- Body bubble — border color comes from $bubbleClasses (intent-tinted for
             non-neutral, neutral border for plain chat bubbles). --}}
        <div class="{{ $bubbleClasses }} rounded-[var(--radius-wk-lg)] px-[var(--space-wk-md,1rem)] py-[var(--space-wk-sm,0.5rem)] text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] border-[length:var(--border-wk-width)]">
            {{ $slot }}
        </div>

        {{-- Reactions slot --}}
        @if(isset($reactions))
            <div class="flex flex-wrap gap-1 mt-[var(--space-wk-xs,0.25rem)]">
                {{ $reactions }}
            </div>
        @endif

        {{-- Attachments slot --}}
        @if(isset($attachments))
            <div class="flex flex-wrap gap-[var(--space-wk-sm,0.5rem)] mt-[var(--space-wk-xs,0.25rem)]">
                {{ $attachments }}
            </div>
        @endif
    </div>

    {{-- Actions slot (dropdown menu). Revealed on hover OR keyboard focus by
         default; actions-reveal="always" keeps it visible (touch surfaces). --}}
    @if(isset($actions))
        <div class="shrink-0 {{ $actionsRevealClasses }}">
            {{ $actions }}
        </div>
    @endif
</article>
