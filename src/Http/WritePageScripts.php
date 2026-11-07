<?php

declare(strict_types=1);

namespace Pushery\WireKit\Http;

use Closure;
use Pushery\WireKit\Support\PageScripts;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Writes the scripts of a page under `'scripts' => ['bundle' => 'auto']` into an HTML response
 * (Support\PageScripts). The service provider puts it on the `web` group while that setting is on.
 * A response that is not HTML, is streamed, or carries no region of `@wirekitScripts` passes as
 * it came.
 */
final class WritePageScripts
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        $type = (string) $response->headers->get('Content-Type', '');

        if ($type !== '' && ! str_contains($type, 'text/html')) {
            return $response;
        }

        $content = $response->getContent();

        if (! is_string($content) || ! str_contains($content, PageScripts::START)) {
            return $response;
        }

        // Laravel's response keeps what `setContent()` is given as its `original`, where the view a
        // route returned stands for `assertViewIs()`, `assertViewHas()` and any middleware that
        // reads it. The rewrite changes the markup, not what the response was made from, so a
        // view or another object it held is put back.
        $original = property_exists($response, 'original') ? $response->original : null;

        $response->setContent(PageScripts::rewrite($content));

        if (is_object($original) && property_exists($response, 'original')) {
            $response->original = $original;
        }

        return $response;
    }
}
