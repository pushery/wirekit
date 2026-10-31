<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

/**
 * The address of a video, read as the kind of player it needs.
 *
 * `<x-wirekit::video>` takes one `src` and plays four kinds of it: a file in a `<video>`, a stream
 * (HLS, a playlist ending in `.m3u8`) in a `<video>` through the browser or an engine, and a video
 * on YouTube or Vimeo in the provider's own player. The provider's player is an iframe that loads
 * from the provider, so the view needs the kind before it draws anything: a provider's video
 * waits for consent, and the address it loads is built here, never fetched before.
 *
 * YouTube plays from `www.youtube-nocookie.com`, the address on which YouTube sets no cookie until
 * the video plays, and Vimeo with `dnt=1`, which stops it from tracking the session. Both start
 * with `autoplay=1`: the iframe is created by the reader's own press, which the browser counts as
 * the gesture that allows sound.
 *
 * @internal Read by the video component; not a developer-facing API.
 */
final class VideoSource
{
    public const FILE = 'file';

    public const HLS = 'hls';

    public const YOUTUBE = 'youtube';

    public const VIMEO = 'vimeo';

    /** The name each provider is shown under. */
    public const PROVIDER_NAMES = [self::YOUTUBE => 'YouTube', self::VIMEO => 'Vimeo'];

    /**
     * The kind of `$src`, the provider's video id where it has one, and the address its player
     * loads. A provider's address without a recognizable id comes back with no `embed`.
     *
     * @return array{kind: string, id: string|null, embed: string|null}
     */
    public static function of(string $src): array
    {
        $src = trim($src);
        $parts = parse_url($src);
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $path = is_array($parts) ? (string) ($parts['path'] ?? '') : '';
        $query = [];

        if (is_array($parts) && isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        if (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'youtu.be'], true)) {
            $id = self::youtubeId($host, $path, $query);

            return [
                'kind' => self::YOUTUBE,
                'id' => $id,
                'embed' => $id === null ? null : 'https://www.youtube-nocookie.com/embed/'.$id.'?autoplay=1&playsinline=1&rel=0',
            ];
        }

        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
            [$id, $hash] = self::vimeoId($host, $path, $query);

            return [
                'kind' => self::VIMEO,
                'id' => $id,
                'embed' => $id === null ? null : 'https://player.vimeo.com/video/'.$id.'?autoplay=1&dnt=1'.($hash !== null ? '&h='.$hash : ''),
            ];
        }

        $kind = preg_match('/\.m3u8$/i', $path !== '' ? $path : (string) strtok($src, '?#')) === 1 ? self::HLS : self::FILE;

        return ['kind' => $kind, 'id' => null, 'embed' => null];
    }

    /**
     * The 11-character id of a YouTube address: `watch?v=`, `youtu.be/`, `/embed/`, `/shorts/`
     * and `/live/`.
     *
     * @param  array<array-key, mixed>  $query
     */
    private static function youtubeId(string $host, string $path, array $query): ?string
    {
        $candidate = '';

        if ($host === 'youtu.be') {
            $candidate = explode('/', ltrim($path, '/'))[0];
        } elseif ($path === '/watch') {
            $candidate = is_string($query['v'] ?? null) ? $query['v'] : '';
        } elseif (preg_match('#^/(?:embed|shorts|live|v)/([^/?]+)#', $path, $m) === 1) {
            $candidate = $m[1];
        }

        return preg_match('/^[A-Za-z0-9_-]{11}$/', $candidate) === 1 ? $candidate : null;
    }

    /**
     * The numeric id of a Vimeo address and the hash an unlisted video carries, from
     * `vimeo.com/{id}`, `vimeo.com/{id}/{hash}` and `player.vimeo.com/video/{id}?h={hash}`.
     *
     * @param  array<array-key, mixed>  $query
     * @return array{0: string|null, 1: string|null}
     */
    private static function vimeoId(string $host, string $path, array $query): array
    {
        $pattern = $host === 'player.vimeo.com' ? '#^/video/(\d+)(?:/([A-Za-z0-9]+))?/?$#' : '#^/(\d+)(?:/([A-Za-z0-9]+))?/?$#';

        if (preg_match($pattern, $path, $m) !== 1) {
            return [null, null];
        }

        $hash = ($m[2] ?? '') !== '' ? $m[2] : (is_string($query['h'] ?? null) && preg_match('/^[A-Za-z0-9]+$/', $query['h']) === 1 ? $query['h'] : null);

        return [$m[1], $hash];
    }
}
