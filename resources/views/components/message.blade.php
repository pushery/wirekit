@props([
    'author' => [],
    'timestamp' => null,
    'side' => 'left',
    'intent' => 'neutral',
    'edited' => false,
    // Delivery ladder for an outgoing message: sending | sent | delivered |
    // read | failed. Null (default) renders nothing — most threads only track
    // status on the messages the current user sent.
    'status' => null,
    // When the status was reached (Carbon|string). Given → the status glyph
    // carries a tooltip AND its accessible text reads "Read at 9:15 AM" instead
    // of just "Read". Formatted with the same locale-aware short time as the
    // message timestamp (or `timeFormat`).
    'statusTime' => null,
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
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;
    use Carbon\Carbon;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $edited = BooleanProp::from($edited, false);

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

    // Delivery status. Each rung carries its OWN text — the color and glyph are
    // support, never the only signal (WCAG 1.4.1). `failed` shows its text
    // VISIBLY because it needs the user to act; the rest are visually a glyph
    // and announce their wording to assistive technology.
    $statusValue = ($status === null || $status === '')
        ? null
        : (in_array($status, ['sending', 'sent', 'delivered', 'read', 'failed'], true)
            ? $status
            : WireKit::validateProp('message', 'status', $status, ['sending', 'sent', 'delivered', 'read', 'failed']));

    $statusText = match ($statusValue) {
        'sending' => __('Sending'),
        'sent' => __('Sent'),
        'delivered' => __('Delivered'),
        'read' => __('Read'),
        'failed' => __('Failed to send'),
        default => null,
    };

    $statusIcon = match ($statusValue) {
        'sending' => 'refresh',
        'sent', 'delivered', 'read' => 'check',
        'failed' => 'danger',
        default => null,
    };

    // Delivery convention shared by every messaging app (WhatsApp / Telegram /
    // iMessage): ONE check = sent to the server, TWO checks = delivered to the
    // recipient, two ACCENT checks = read. delivered + read render the
    // double-check glyph; the rung color ($statusClass) is what separates read
    // (accent) from delivered (muted). sent keeps the single check.
    $statusDouble = in_array($statusValue, ['delivered', 'read'], true);

    $statusClass = match ($statusValue) {
        'sending', 'sent' => 'text-[color:var(--color-wk-text-subtle)]',
        'delivered' => 'text-[color:var(--color-wk-text-muted)]',
        'read' => 'text-[color:var(--color-wk-accent-text)]',
        'failed' => 'text-[color:var(--color-wk-danger-text)]',
        default => '',
    };

    // Status time. When given, the glyph's accessible text and tooltip read
    // "Read at 9:15 AM" — the WHEN behind the rung. Same locale-aware format as
    // the message timestamp; an explicit timeFormat overrides.
    $statusTimeText = '';
    if ($statusValue !== null && $statusTime !== null && $statusTime !== '') {
        $statusCarbon = $statusTime instanceof Carbon ? $statusTime : Carbon::parse($statusTime);
        $statusTimeText = $timeFormat !== null
            ? $statusCarbon->format($timeFormat)
            : $statusCarbon->isoFormat('LT');
    }
    $statusHasTime = $statusTimeText !== '';
    // "Read at 9:15 AM" when a time is present, else just "Read".
    $statusFullText = $statusHasTime && $statusText !== null
        ? $statusText.' '.__('at').' '.$statusTimeText
        : $statusText;

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

        {{-- Delivery status. Glyph + color are support; the wording is always
             present (visible for `failed`, announced otherwise), so the rung is
             never conveyed by color alone. --}}
        @if($statusValue)
            {{-- The glyph. Double-check ✓✓ (delivered / read) is inline, not via
                 the icon preset: the default heroicons set has no double-check
                 glyph, and the rung must render regardless of which preset the
                 developer installed. Decorative — the wording beside it carries
                 the meaning; currentColor inherits the rung color. --}}
            @php
                $statusGlyph = $statusDouble
                    ? '<svg data-wk-check-double xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 15" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" class="h-3 w-auto shrink-0" aria-hidden="true"><path d="M1 8.2 4.4 11.6 10.8 4.2" /><path d="M7 8.2 10.4 11.6 16.8 4.2" /></svg>'
                    : null;
            @endphp
            @if($statusHasTime)
                {{-- With a status time, the glyph gets a tooltip ("Read at 9:15
                     AM"). The time is ALSO in the sr-only / visible text, so
                     assistive technology gets it without the (mouse-only) hover —
                     the tooltip is pure visual enhancement, no keyboard trap. --}}
                <x-wirekit::tooltip :text="$statusFullText" :placement="$sideValue === 'right' ? 'left' : 'right'">
                    <span
                        data-wk-message-status
                        data-status="{{ $statusValue }}"
                        class="flex items-center gap-[var(--space-wk-xs,0.25rem)] text-[length:var(--text-wk-xs)] {{ $statusClass }}"
                    >
                        @if($statusGlyph){!! $statusGlyph !!}@else<x-wirekit::icon :name="$statusIcon" size="xs" aria-hidden="true" />@endif
                        @if($statusValue === 'failed')
                            <span>{{ $statusFullText }}</span>
                        @else
                            <span class="sr-only">{{ $statusFullText }}</span>
                        @endif
                    </span>
                </x-wirekit::tooltip>
            @else
                <span
                    data-wk-message-status
                    data-status="{{ $statusValue }}"
                    class="flex items-center gap-[var(--space-wk-xs,0.25rem)] text-[length:var(--text-wk-xs)] {{ $statusClass }}"
                >
                    @if($statusGlyph){!! $statusGlyph !!}@else<x-wirekit::icon :name="$statusIcon" size="xs" aria-hidden="true" />@endif
                    @if($statusValue === 'failed')
                        <span>{{ $statusText }}</span>
                    @else
                        <span class="sr-only">{{ $statusText }}</span>
                    @endif
                </span>
            @endif
        @endif

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
