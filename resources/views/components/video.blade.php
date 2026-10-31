{{-- optimistic-ui: n/a — client-only
     A player plays what the page gives it and sends nothing to the server, so there is nothing to
     anticipate and nothing to roll back. --}}
@props([
    // A file, a stream (HLS, a playlist ending in .m3u8), or the address of a video on YouTube or
    // Vimeo (Support\VideoSource).
    'src' => null,
    // The video's name: the play button's, the provider player's and the notice's.
    'title' => null,
    // The page's own still, shown until the video plays. A provider's thumbnail would be a request
    // to the provider, so it is never fetched.
    'poster' => null,
    'ratio' => '16/9',
    // Captions and subtitles for a file or a stream: arrays with `src`, and `srclang`, `label`,
    // `kind` (captions, subtitles, descriptions) and `default` as a <track> takes them.
    'tracks' => [],
    // A muted loop with a pause button, which starts when it is seen.
    'background' => false,
    // Whether the page holds the reader's consent for the provider, and the name of that consent.
    'consented' => false,
    'consent' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\SafeUrl;
    use Pushery\WireKit\Support\SlotContent;
    use Pushery\WireKit\Support\StrictnessGate;
    use Pushery\WireKit\Support\UrlProp;
    use Pushery\WireKit\Support\UtilityClasses;
    use Pushery\WireKit\Support\VideoSource;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list auto-derived from
    // this component's @props. Fully qualified: this view's imports may live in a later @php block.
    \Pushery\WireKit\WireKit::warnUnknownProps('video', $attributes->getAttributes());

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);

    $src = UrlProp::source(is_string($src) || $src instanceof \Stringable || is_bool($src) ? $src : null);
    $poster = UrlProp::source(is_string($poster) || $poster instanceof \Stringable || is_bool($poster) ? $poster : null);
    $title = SlotContent::textOf($title);
    $title = $title !== '' ? $title : __('wirekit::Video');
    $ratio = WireKit::validateProp('video', 'ratio', is_string($ratio) ? $ratio : '16/9', ['16/9', '4/3', '1/1', '9/16', '21/9']);
    $background = BooleanProp::from($background, false, 'video', 'background');
    $consented = BooleanProp::from($consented, false, 'video', 'consented');

    $source = $src !== null ? VideoSource::of($src) : null;
    $kind = $source['kind'] ?? null;
    $provider = in_array($kind, [VideoSource::YOUTUBE, VideoSource::VIMEO], true);

    // A provider's address without the id of a video plays nothing.
    if ($provider && $source['embed'] === null) {
        StrictnessGate::reject('video', 'src', (string) $src, 'the address of one video on YouTube or Vimeo', 'nothing rendered');
        $source = null;
    }

    // A loop plays a file or a stream; a provider's player is not one.
    if ($background && $provider && $source !== null) {
        $background = StrictnessGate::reject('video', 'background', 'true', 'a file or a stream for a loop', 'a video that plays on a click') === 'true';
    }

    $consentName = is_string($consent) && trim($consent) !== '' ? trim($consent) : (string) $kind;

    // Each track as a <track> takes it, its address read as an image's would be.
    $trackList = [];

    foreach (is_iterable($tracks) ? $tracks : [] as $track) {
        if (! is_array($track)) {
            continue;
        }

        $trackSrc = SafeUrl::href(UrlProp::text(is_string($track['src'] ?? null) || ($track['src'] ?? null) instanceof \Stringable ? $track['src'] : null), SafeUrl::LOAD_SCHEMES);

        if ($trackSrc === '') {
            continue;
        }

        $trackKind = is_string($track['kind'] ?? null) && in_array($track['kind'], ['captions', 'subtitles', 'descriptions'], true) ? $track['kind'] : 'captions';

        $trackList[] = [
            'src' => $trackSrc,
            'kind' => $trackKind,
            'srclang' => is_string($track['srclang'] ?? null) ? $track['srclang'] : null,
            'label' => SlotContent::textOf($track['label'] ?? null),
            'default' => BooleanProp::from($track['default'] ?? false),
        ];
    }

    $state = $provider && ! $consented ? 'consent' : 'ready';

    $config = [
        'kind' => $kind,
        'src' => $provider ? null : $src,
        'embed' => $provider ? ($source['embed'] ?? null) : null,
        'title' => $title,
        'consent' => $consentName,
        'consented' => $consented,
        'background' => $background,
        'state' => $state,
    ];

    $ratioClass = match ($ratio) {
        '4/3' => 'aspect-[4/3]',
        '1/1' => 'aspect-square',
        '9/16' => 'aspect-[9/16]',
        '21/9' => 'aspect-[21/9]',
        default => 'aspect-video',
    };

    $rootClasses = WireKit::resolveClasses('video', 'base', 'relative block w-full overflow-hidden rounded-[var(--radius-wk-lg)] bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)] font-[family-name:var(--font-wk-sans)] '.$ratioClass, $scope);

    $focusRing = 'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]';

    // The play button in the middle of the box: placed by its four zero insets and `m-auto`.
    $playClasses = WireKit::resolveClasses('video', 'play', implode(' ', [
        'absolute inset-0 m-auto flex items-center justify-center size-[var(--size-wk-lg)] rounded-full',
        'bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-md)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'cursor-pointer hover:bg-[var(--color-wk-bg-hover)]',
        $focusRing,
    ]), $scope);

    $noticeClasses = WireKit::resolveClasses('video', 'notice', 'absolute inset-0 flex flex-col items-center justify-center gap-[var(--gap-wk-sm)] p-[var(--padding-wk-x-md)] text-center text-[length:var(--text-wk-sm)] bg-[var(--color-wk-bg-muted)]', $scope);

    $toggleClasses = 'wk-touch-target absolute end-[var(--padding-wk-x-sm)] bottom-[var(--padding-wk-y-sm)] flex items-center justify-center size-[var(--size-wk-sm)] rounded-full bg-[var(--color-wk-bg-elevated)] text-[color:var(--color-wk-text)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] cursor-pointer hover:bg-[var(--color-wk-bg-hover)] '.$focusRing;

    $providerName = VideoSource::PROVIDER_NAMES[$kind] ?? '';
@endphp

@if($source !== null)
<div
    data-wk-video
    data-wk-video-kind="{{ $kind }}"
    x-data="wirekitVideo({{ AlpinePayload::from($config) }})"
    {{ $attributes->class(UtilityClasses::yieldToCaller($attributes, [$rootClasses])) }}
>
    @if($poster !== null)
    <img data-wk-prose-skip src="{{ $poster }}" alt="" loading="lazy" decoding="async" data-wk-video-poster class="absolute inset-0 size-full object-cover" x-show="! isPlaying()">
    @endif

    @if($provider)
    {{-- The provider's player goes here once the reader asks for it. Livewire leaves the element
         to the script. --}}
    <div x-ref="stage" wire:ignore class="absolute inset-0 [&>iframe]:absolute [&>iframe]:inset-0 [&>iframe]:size-full [&>iframe]:border-0" x-show="isPlaying()" x-cloak></div>

    <div data-wk-video-notice class="{{ $noticeClasses }}" x-show="state === 'consent'" @if($state !== 'consent') x-cloak @endif>
        <p data-wk-prose-skip class="m-0 max-w-[var(--size-wk-modal-sm)] text-[color:var(--color-wk-text-muted)]">{{ __('wirekit::This video is on :provider. Loading it sends data to :provider.', ['provider' => $providerName]) }}</p>
        <x-wirekit::button intent="neutral" surface="filled" size="sm" data-wk-video-load x-on:click="requestConsent()">{{ __('wirekit::Load the video') }}</x-wirekit::button>
    </div>
    @else
    {{-- No address before the first play: the browser fetches nothing until the reader asks, or
         until a loop is seen. Livewire leaves the element to the script. --}}
    <video
        x-ref="video"
        wire:ignore
        preload="none"
        playsinline
        @if($background) muted loop @else controls @endif
        class="absolute inset-0 size-full {{ $background ? 'object-cover' : 'object-contain' }}"
        x-show="isPlaying() || background"
        @if(! $background) x-cloak @endif
    >
        @foreach($trackList as $track)
        <track kind="{{ $track['kind'] }}" src="{{ $track['src'] }}" @if($track['srclang'] !== null) srclang="{{ $track['srclang'] }}" @endif @if($track['label'] !== '') label="{{ $track['label'] }}" @endif @if($track['default']) default @endif>
        @endforeach
    </video>

    <p data-wk-prose-skip data-wk-video-unsupported class="{{ $noticeClasses }} m-0" x-show="state === 'unsupported'" x-cloak>{{ __('wirekit::This video cannot play in this browser.') }}</p>
    @endif

    @if(! $background)
    <button
        type="button"
        data-wk-video-play
        class="wk-touch-row {{ $playClasses }}"
        aria-label="{{ __('wirekit::Play :title', ['title' => $title]) }}"
        x-show="state === 'ready'"
        @if($state !== 'ready') x-cloak @endif
        x-on:click="play()"
    >
        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
    </button>
    @else
    {{-- Named after what it does now, as the carousel's button is, and static as well as bound:
         before the scripts run the loop is still, so the button plays it. --}}
    <button
        type="button"
        data-wk-video-toggle
        class="{{ $toggleClasses }}"
        aria-label="{{ __('wirekit::Play the video') }}"
        x-bind:aria-label="looping ? {{ AlpinePayload::from(__('wirekit::Pause the video')) }} : {{ AlpinePayload::from(__('wirekit::Play the video')) }}"
        x-on:click="toggle()"
    >
        <svg x-show="looping" x-cloak class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z" /></svg>
        <svg x-show="! looping" class="h-4 w-4" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z" /></svg>
    </button>
    @endif
</div>
@elseif($src === null)
{{-- A player without an address has nothing to play. --}}
@php(StrictnessGate::reject('video', 'src', '', 'the address of a video file, a stream, or a video on YouTube or Vimeo', 'nothing rendered'))
@endif
