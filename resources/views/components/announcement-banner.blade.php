@props([
    // Where the bar lives. Sticky is opt-in — a bar that follows the reader is
    // a stronger claim on the viewport than most announcements deserve.
    'position' => 'top',
    'sticky' => false,
    // Semantic tint. `promo` is the marketing default (accent), the state
    // intents carry the usual meaning.
    'intent' => 'promo',
    // A stable key makes the dismissal STICK across page loads. Without one the
    // bar is not dismissible unless `persist` is explicitly turned off (below) —
    // a close button that silently forgets is worse than no close button, so
    // forgetfulness has to be a deliberate choice, never an accident.
    'dismissKey' => null,
    // Whether a dismissal is remembered across page loads (localStorage). On by
    // default. Turn it OFF for a session-scoped notice that should reappear next
    // visit — and it is what makes a live demo resettable, since a re-mount then
    // brings the bar back instead of reading a stored "dismissed" flag.
    'persist' => true,
    // Accessible name for the region.
    'label' => 'Announcement',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $positionValue = in_array($position, ['top', 'bottom'], true)
        ? $position
        : WireKit::validateProp('announcement-banner', 'position', $position, ['top', 'bottom']);

    $intentValue = in_array($intent, ['promo', 'info', 'success', 'warning', 'danger'], true)
        ? $intent
        : WireKit::validateProp('announcement-banner', 'intent', $intent, ['promo', 'info', 'success', 'warning', 'danger']);

    $hasKey = $dismissKey !== null && $dismissKey !== '';
    $persistValue = filter_var($persist, FILTER_VALIDATE_BOOLEAN);

    // The bar is dismissible when it can be dismissed: either it has a key (and
    // remembers) OR persistence was deliberately turned off (session-only). It
    // only PERSISTS when there is a key AND persist is on.
    $isDismissible = $hasKey || ! $persistValue;
    $persistsDismissal = $hasKey && $persistValue;
    $isSticky = filter_var($sticky, FILTER_VALIDATE_BOOLEAN);

    // Full literal class strings via match so the drift auditor can harvest them.
    $intentClasses = match ($intentValue) {
        'info' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_12%,var(--color-wk-bg-elevated))] text-[color:var(--color-wk-text)]',
        'success' => 'bg-[color-mix(in_srgb,var(--color-wk-success)_12%,var(--color-wk-bg-elevated))] text-[color:var(--color-wk-text)]',
        'warning' => 'bg-[color-mix(in_srgb,var(--color-wk-warning)_12%,var(--color-wk-bg-elevated))] text-[color:var(--color-wk-text)]',
        'danger' => 'bg-[color-mix(in_srgb,var(--color-wk-danger)_12%,var(--color-wk-bg-elevated))] text-[color:var(--color-wk-text)]',
        default => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
    };

    $stickyClasses = match (true) {
        $isSticky && $positionValue === 'bottom' => 'sticky bottom-0 z-30',
        $isSticky => 'sticky top-0 z-30',
        default => '',
    };

    // Page-edge component: the inline padding is the content-edge spine
    // (--padding-wk-x-lg), so the banner's text edge lines up with every other
    // page-chrome component instead of floating on its own margin.
    $classes = WireKit::resolveClasses('announcement-banner', 'base', implode(' ', array_filter([
        'wk-announcement-banner',
        'flex w-full items-center justify-center gap-[var(--gap-wk-sm)]',
        'px-[var(--padding-wk-x-lg)] py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-sm)] font-[family-name:var(--font-wk-sans)]',
        $intentClasses,
        $stickyClasses,
    ])), $scope);
@endphp

{{-- Not role="alert": an announcement is ambient, and an alert interrupts a
     screen-reader user mid-sentence. A labeled region lets them find it when
     they want it and ignore it when they do not.

     x-cloak keeps a previously-dismissed bar from flashing on every page load
     before Alpine reads localStorage. --}}
<div
    @if($isDismissible)
        x-data="{
            shown: true,
            init() {
                @if($persistsDismissal)
                    try { this.shown = localStorage.getItem('wk-banner:{{ $dismissKey }}') !== '1'; } catch (e) { this.shown = true; }
                @else
                    {{-- Session-only ($persist=false): nothing to read — a fresh
                         mount (page load OR a docs replay re-mount) always starts
                         shown. Assign explicitly so re-running init() resets it. --}}
                    this.shown = true;
                @endif
            },
            dismiss() {
                this.shown = false;
                @if($persistsDismissal)
                    try { localStorage.setItem('wk-banner:{{ $dismissKey }}', '1'); } catch (e) {}
                @endif
            },
        }"
        x-show="shown"
        x-cloak
        {{-- Opts the dismissed-then-empty preview into the docs preview frame's
             replay/reset affordance (same contract as alert / badge). Without it
             a dismissible demo stays gone with no way to bring it back. --}}
        data-replayable="true"
    @endif
    role="region"
    aria-label="{{ $label }}"
    data-wk-announcement-banner
    data-position="{{ $positionValue }}"
    data-intent="{{ $intentValue }}"
    {{ $attributes->class([$classes]) }}
>
    <span data-wk-announcement-content class="min-w-0">{{ $slot }}</span>

    @isset($action)
        <span data-wk-announcement-action class="shrink-0">{{ $action }}</span>
    @endisset

    @if($isDismissible)
        {{-- A real button with a real name — never a bare glyph. --}}
        <button
            type="button"
            @click="dismiss()"
            data-wk-announcement-dismiss
            aria-label="{{ __('Dismiss') }} {{ $label }}"
            class="ms-auto shrink-0 cursor-pointer rounded-[var(--radius-wk-sm)] p-[var(--padding-wk-x-xs)] opacity-70 transition-opacity duration-[var(--transition-wk-duration)] hover:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[color:var(--color-wk-ring)]"
        >
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>
