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
    use Pushery\WireKit\WireKit;

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
        if ($b < 1024) {
            return $b.' B';
        }
        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $b / 1024;
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        // One decimal below 10 (2.4 MB), none above (24 MB) — the convention
        // every file manager uses.
        return ($value < 10 ? number_format($value, 1) : (string) round($value)).' '.$units[$i];
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

    $sizeText = $formatBytes($bytes !== null ? (int) $bytes : null);
    $label = $typeLabel($type, (string) $name);
    $isImage = $thumbnail !== null && $thumbnail !== '';

    // Description line: "PDF · 2.4 MB". Only the parts we actually have.
    $descriptionParts = array_values(array_filter([$label, $sizeText]));

    // The state is never color-only: each carries its own text/icon.
    [$stateText, $stateIntent] = match ($stateValue) {
        'uploading' => ['Uploading', 'info'],
        'done' => ['Uploaded', 'success'],
        'error' => ['Upload failed', 'danger'],
        default => [null, null],
    };

    $rootClasses = WireKit::resolveClasses('attachment', 'base', implode(' ', [
        'flex items-center gap-[var(--gap-wk-sm)]',
        'rounded-[var(--radius-wk-md)] border border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'p-[var(--padding-wk-x-sm)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    $isLink = $href !== null && $href !== '';
    $hasActions = isset($actions);

    // When the card is a link AND carries an actions slot, the action controls
    // cannot nest inside the anchor: a <button>/<a> inside an <a> is invalid HTML
    // and a WCAG 4.1.1 nested-interactive failure (the inner control is not
    // reliably operable by assistive tech). In that one case the root stays a
    // <div>, only the media+name region becomes the link, and the actions sit
    // outside it. A link with no actions keeps the whole-card-clickable <a>.
    $splitLink = $isLink && $hasActions;
    $tag = ($isLink && ! $hasActions) ? 'a' : 'div';
@endphp

@php
    $accessibleName = $name
        .($descriptionParts ? ', '.implode(', ', $descriptionParts) : '')
        .($stateText ? ', '.$stateText : '');
@endphp

<{{ $tag }}
    @if($tag === 'a') href="{{ $href }}" @endif
    data-wk-attachment
    data-state="{{ $stateValue }}"
    @unless($splitLink) aria-label="{{ $accessibleName }}" @endunless
    {{ $attributes->class([$rootClasses]) }}
>
    {{-- Link over the media+name only when there are also actions, so the action
         controls can live outside the anchor. Without actions the whole card is
         the link (the $tag === 'a' root above). --}}
    @if($splitLink)
        <a href="{{ $href }}" aria-label="{{ $accessibleName }}" data-wk-attachment-link class="flex min-w-0 flex-1 items-center gap-[var(--gap-wk-sm)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]">
    @endif

    {{-- Media tile: a real thumbnail when we have one, else the file glyph.
         Decorative either way — the card's aria-label carries the meaning. --}}
    <span
        data-wk-attachment-media
        class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-muted)]"
    >
        @if($isImage)
            <img src="{{ $thumbnail }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover" />
        @else
            <x-wirekit::icon :name="$icon ?? 'file-text'" size="md" class="text-[color:var(--color-wk-text-muted)]" aria-hidden="true" />
        @endif
    </span>

    <span class="min-w-0 flex-1">
        <span data-wk-attachment-name class="block truncate text-[length:var(--text-wk-sm)] font-medium">{{ $name }}</span>

        @if($descriptionParts || $stateText)
            <span data-wk-attachment-meta class="block truncate text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">
                {{ implode(' · ', $descriptionParts) }}
                @if($stateText)
                    @if($descriptionParts) · @endif<span data-wk-attachment-state>{{ $stateText }}</span>
                @endif
            </span>
        @endif

        {{-- Upload progress. role=progressbar + aria-valuenow live on the
             progress component itself; aria-label (NOT the visible `label`
             prop) names it for assistive technology without duplicating the
             file name on screen. --}}
        @if($stateValue === 'uploading' && $progress !== null)
            <x-wirekit::progress
                :value="(int) $progress"
                size="sm"
                :animation="$animate ? 'shimmer' : 'none'"
                class="mt-[var(--space-wk-xs)]"
                aria-label="Uploading {{ $name }}"
            />
        @endif
    </span>

    @if($splitLink)
        </a>
    @endif

    @isset($actions)
        <span data-wk-attachment-actions class="flex shrink-0 items-center gap-[var(--gap-wk-sm)]">
            {{ $actions }}
        </span>
    @endisset
</{{ $tag }}>
