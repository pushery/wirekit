<?php

declare(strict_types=1);

/*
 * The files in dist/ an application loads: what `vendor:publish --tag=wirekit-assets` copies,
 * what `--tag=wirekit-scripts` copies of them (the scripts), and what the asset routes serve when
 * nothing is published, each with the content type the route answers with. One list for the
 * three, in the order the publish groups name the files, so a file added here reaches all three
 * at once. Read by WireKitServiceProvider; this file is data and holds no logic.
 *
 * `; charset=utf-8` is not decoration on a text type. Without it a browser opening the URL on its
 * own falls back to a legacy single-byte encoding, and every em-dash in the file renders as
 * mojibake. Only the direct hit is affected: a stylesheet or script loaded from a page inherits the
 * document's encoding.
 */

$css = 'text/css; charset=utf-8';
$js = 'application/javascript; charset=utf-8';

return [
    'wirekit.css' => $css,
    'wirekit.js' => $js,
    'wirekit.core.js' => $js,
    'wirekit.esm.js' => $js,
    'wirekit-apex.js' => $js,
    'wirekit-tiptap.js' => $js,
    'wirekit-optimistic.js' => $js,
    'wirekit-theme.js' => $js,
    'wirekit-alpine.js' => $js,
    'wirekit-alpine.csp.js' => $js,
    'wirekit.min.css' => $css,
    'wirekit-compact.css' => $css,
];
