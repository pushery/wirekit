{{-- optimistic-ui: n/a — passthrough
     A file chip. Its download link and its remove action are both the developer's. --}}
@props([
    // File name. Also the accessible name of the card.
    'name' => '',
    // Raw byte count — formatted for display ("2.4 MB"). Pass the raw number;
    // formatting is ours, so developers never hand-roll it.
    'bytes' => null,
    // MIME type (e.g. "application/pdf"). Rendered as a short label ("PDF").
    'type' => null,
    // Image source for a visual preview. Given → the media tile shows the
    // thumbnail instead of the file glyph.
    'thumbnail' => null,
    // Lifecycle: idle | uploading | done | error.
    'state' => 'idle',
    // 0–100 while uploading. Drives the progress bar.
    'progress' => null,
    // Makes the whole card a link (download / open).
    'href' => null,
    // Override the media glyph with any icon name from the active preset.
    'icon' => null,
    // Animate the upload progress bar while `state="uploading"` — a shimmer
    // sweep that reads as "actively uploading". Opt-in (off by default), gated
    // by prefers-reduced-motion at the CSS layer.
    'animate' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;
    use Pushery\WireKit\Support\LocalizedNumber;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('attachment', $attributes->getAttributes());
    // Echoed into the tag or bound, the URL is written escaped once (Support\UrlProp).
    $href = \Pushery\WireKit\Support\UrlProp::text($href);

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $animate = BooleanProp::from($animate, false, 'attachment', 'animate');

    $stateValue = in_array($state, ['idle', 'uploading', 'done', 'error'], true)
        ? $state
        : WireKit::validateProp('attachment', 'state', $state, ['idle', 'uploading', 'done', 'error']);

    // ── Metadata formatting (PHP-side on purpose) ───────────────────────────
    // Developers pass raw file metadata; turning bytes into "2.4 MB" and a MIME
    // type into "PDF" is the component's job, not theirs.
    $formatBytes = static function (?int $b): ?string {
        if ($b === null || $b < 0) {
            return null;
        }
        // The unit symbols come from the catalog: French counts in octets (`Ko`, `Mo`). Every
        // number goes through LocalizedNumber, which writes it as the locale or the application's
        // own formatter does (`WireKit::formatNumbersUsing()`), so one card never mixes two ways.
        if ($b < 1024) {
            return LocalizedNumber::format((float) $b, precision: 0).' '.__('wirekit::B');
        }
        $units = [__('wirekit::KB'), __('wirekit::MB'), __('wirekit::GB'), __('wirekit::TB')];
        $value = $b / 1024;
        $i = 0;
        // Each boundary is judged on the number as it will be shown: 1023.7 KB rounds to
        // 1024 KB, so it is shown as 1.0 MB instead.
        while (round($value) >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        // One decimal below 10 (2.4 MB), none above (24 MB) — the convention
        // every file manager uses. Rounded before the comparison, so 9.96 MB is
        // shown as 10 MB rather than 10.0 MB.
        $shown = round($value, 1);

        return LocalizedNumber::format($shown < 10 ? $shown : round($value), precision: $shown < 10 ? 1 : 0).' '.$units[$i];
    };

    // Short, human label. Prefer the file extension (PDF, DOCX) — it is more
    // informative than a generic glyph — and fall back to the MIME subtype.
    $typeLabel = static function (?string $mime, string $fileName): ?string {
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        if ($ext !== '') {
            return mb_strtoupper($ext);
        }
        if ($mime === null || $mime === '') {
            return null;
        }
        $sub = mb_substr($mime, (int) mb_strpos($mime, '/') + 1);

        return mb_strtoupper($sub === '' ? $mime : $sub);
    };

    $sizeText = $formatBytes(\Pushery\WireKit\Support\NumericProp::intOrNull($bytes, 'attachment', 'bytes'));
    // The progress reaches the bar as PHP casts it, so a text with a unit keeps the number it
    // starts with, and it is reported. A number no integer holds reaches it as 0.
    \Pushery\WireKit\Support\NumericProp::check($progress, 'attachment', 'progress', whole: true);
    $label = $typeLabel($type, \Pushery\WireKit\Support\SlotContent::textOf($name));
    // An empty thumbnail address, `false` or one of white space is no thumbnail, so the file shows
    // its type rather than the sign of a broken image (Support\UrlProp::source()).
    $thumbnail = \Pushery\WireKit\Support\UrlProp::source($thumbnail);
    $isImage = $thumbnail !== null;

    // Description line: "PDF · 2.4 MB". Only the parts we actually have.
    $descriptionParts = array_values(array_filter([$label, $sizeText]));

    /*
     * The state is never color-only: each carries its own text, which is the whole
     * accessibility position here (WCAG 1.4.1 — color is not the only carrier of meaning).
     *
     * One value per state, and no intent beside it. An intent per state would read as a
     * half-wired feature, and the obvious way to "finish" it is to tint the row by state,
     * which is exactly what the line above rules out.
     */
    $stateText = match ($stateValue) {
        'uploading' => __('wirekit::Uploading'),
        'done' => __('wirekit::Uploaded'),
        'error' => __('wirekit::Upload failed'),
        default => null,
    };

    $rootClasses = WireKit::resolveClasses('attachment', 'base', implode(' ', [
        'flex items-center gap-[var(--gap-wk-sm)]',
        'rounded-[var(--radius-wk-md)] border border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'p-[var(--padding-wk-x-sm)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    $isLink = $href !== null && $href !== '';
    $hasActions = \Pushery\WireKit\Support\SlotContent::given($actions ?? null);

    // When the card is a link AND carries an actions slot, the action controls
    // cannot nest inside the anchor: a <button>/<a> inside an <a> is invalid HTML
    // and a WCAG 4.1.1 nested-interactive failure (the inner control is not
    // reliably operable by assistive tech). In that one case the root stays a
    // <div>, only the media+name region becomes the link, and the actions sit
    // outside it. A link with no actions keeps the whole-card-clickable <a>.
    $splitLink = $isLink && $hasActions;
    $tag = ($isLink && ! $hasActions) ? 'a' : 'div';
    // An attachment that is a link as a whole draws the kit's focus ring, standing off it like a
    // button's, rather than the browser's, which follows neither the ring tokens nor a preset. The
    // split link below draws its own inside the card.
    $rootRingClass = match ($tag) {
        'a' => 'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        default => '',
    };
    // Auto-inject rel="noopener noreferrer" when target="_blank". This component
    // takes an href and echoes the caller's bag onto the element that carries it,
    // so a caller's target would otherwise reach a bare anchor; for that shape the
    // injection is unconditional. Rendered explicitly with the bag echoed via
    // except('rel'), because
    // $attributes->merge() treats rel as a DEFAULT and a caller-supplied rel
    // would replace the computed value.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $tag === 'a' && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp

@php
    // A name, an attribute, so markup is read as its text.
    $accessibleName = \Pushery\WireKit\Support\SlotContent::textOf($name)
        .($descriptionParts ? ', '.implode(', ', $descriptionParts) : '')
        .($stateText ? ', '.$stateText : '');

    // The other half of the target="_blank" rule: rel protects the opener, this
    // warns the person who cannot see the new tab appear. WHERE it goes depends
    // on how this card is named, and the card has both shapes. With a composed
    // name it carries aria-label, and name computation stops at the label — a
    // span inside would sit in the DOM and outside the announcement, so the hint
    // rides the label. With no name at all (every naming part empty) no label is
    // emitted, the anchor falls back to its own content, and the house pattern's
    // sr-only span is what reaches the reader. The flag below records which of
    // the two took it so the second cannot fire as well and say it twice.
    $hintsNewTabInLabel = $opensNewTab && filled($accessibleName);

    if ($hintsNewTabInLabel) {
        $accessibleName = trim($accessibleName.' '.__('wirekit::(opens in new tab)'));
    }
@endphp

<{{ $tag }} data-wk-prose-skip
    @if($tag === 'a') href="{{ $href }}" @endif
    data-wk-attachment
    data-state="{{ $stateValue }}"
    {{-- WAI-ARIA 1.2 forbids author naming on an element with an implicit
         role="generic", so the composed name ("contract.pdf, PDF, 2.4 MB,
         Uploaded") was not reliably exposed whenever the card was NOT a link —
         the anchor branch carries its own role, the div branch carried none and
         axe reports aria-prohibited-attr on it. `role="group"` names the card
         without promoting it to a landmark; it rides with the label rather than
         being emitted on its own, because a group with no accessible name is
         noise in the tree instead of structure. Same shape as accordion. --}}
    @unless($splitLink)
        @if(filled($accessibleName))
            @if($tag === 'div') @unless($attributes->has('role')) role="group" @endunless @endif
            @unless($attributes->has('aria-label') || $attributes->has('aria-labelledby')) aria-label="{{ $accessibleName }}" @endunless
        @endif
    @endunless
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$rootClasses, $rootRingClass])) }} data-wk-sr-host
>
    {{-- Link over the media+name only when there are also actions, so the action
         controls can live outside the anchor. Without actions the whole card is
         the link (the $tag === 'a' root above). --}}
    @if($splitLink)
        <a data-wk-prose-skip href="{{ $href }}" aria-label="{{ $accessibleName }}" data-wk-attachment-link class="flex min-w-0 flex-1 items-center gap-[var(--gap-wk-sm)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]">
    @endif

    {{-- Media tile: a real thumbnail when we have one, else the file glyph.
         Decorative either way — the card's aria-label carries the meaning. --}}
    <span
        data-wk-attachment-media
        class="flex h-[var(--size-wk-md)] w-[var(--size-wk-md)] shrink-0 items-center justify-center overflow-hidden rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-muted)]"
    >
        @if($isImage)
            <img data-wk-prose-skip src="{{ $thumbnail }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover" />
        @else
            <x-wirekit::icon :name="$icon ?? 'file-text'" size="md" class="text-[color:var(--color-wk-text-muted)]" aria-hidden="true" />
        @endif
    </span>

    <span class="min-w-0 flex-1">
        {{-- The name is not cut: two files a version apart differ at their end, and a name is read
             whole to tell them apart. `break-word` breaks it inside the card where it has to and
             leaves the card's min-content width alone, so a row of attachments scrolls rather than
             squeezing its cards. --}}
        <span data-wk-attachment-name class="block [overflow-wrap:break-word] text-[length:var(--text-wk-sm)] font-medium">{{ $name }}</span>

        @if($descriptionParts || $stateText)
            <span data-wk-attachment-meta class="block truncate text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">
                {{ implode(' · ', $descriptionParts) }}
                @if($stateText)
                    {{-- Hidden from assistive technology: the status region at the end of the
                         card says it, and browse mode would otherwise read it twice. --}}
                    @if($descriptionParts) · @endif<span data-wk-attachment-state aria-hidden="true">{{ $stateText }}</span>
                @endif
            </span>
        @endif

        {{-- Upload progress. role=progressbar + aria-valuenow live on the
             progress component itself; aria-label (NOT the visible `label`
             prop) names it for assistive technology without duplicating the
             file name on screen. --}}
        @if($stateValue === 'uploading' && $progress !== null)
            <x-wirekit::progress
                :value="\Pushery\WireKit\Support\NumericProp::cast($progress) ?? 0"
                size="sm"
                :animation="$animate ? 'shimmer' : 'none'"
                class="mt-[var(--space-wk-xs)]"
                aria-label="{{ __('wirekit::Uploading :name', ['name' => \Pushery\WireKit\Support\SlotContent::textOf($name)]) }}"
            />
        @endif
    </span>

    @if($splitLink)
        </a>
    @endif

    @if(\Pushery\WireKit\Support\SlotContent::given($actions ?? null))
        <span data-wk-attachment-actions class="flex shrink-0 items-center gap-[var(--gap-wk-sm)]">
            {{ $actions }}
        </span>
    @endif

    @if($opensNewTab && ! $hintsNewTabInLabel)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif

    {{-- The upload state as a status message (WCAG 4.1.3). The application sets `state`, and a
         Livewire update changes it in the card without moving the focus, so the change has to
         reach a screen reader on its own. A live region announces a change of its CONTENT, not
         its arrival with text already inside, so this one is rendered in every state, empty
         while idle, and an update only swaps its text. --}}
    <span role="status" class="sr-only" data-wk-attachment-status>{{ $stateText }}</span>
</{{ $tag }}>
