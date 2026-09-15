/**
 * WireKit theme script: applies the reader's stored theme before the first paint.
 *
 * `@wirekitThemeScript` renders this file, written into the page by default or loaded as a file when
 * `wirekit.theme.script` is `external`. Either way it runs as a plain script at the top of <head>:
 * the browser holds the page until such a script has run, so the theme is set before anything
 * paints. A deferred, async or module script would run after the first paint.
 *
 * It reads its configuration from its own tag, which is what lets one file serve both deliveries:
 * `data-wk-theme-storage` is `local` or `cookie`, and `data-wk-theme-key` is the key the theme
 * controller writes the choice under.
 */
(function () {
    try {
        var tag = document.currentScript;
        var key = (tag && tag.getAttribute('data-wk-theme-key')) || 'wirekit-theme';
        var stored = null;

        if (tag && tag.getAttribute('data-wk-theme-storage') === 'cookie') {
            // By exact name rather than by a pattern, so a key with pattern characters in it cannot
            // break the match. The theme controller reads its cookie the same way.
            var pairs = (document.cookie || '').split('; ');

            for (var i = 0; i < pairs.length; i++) {
                var eq = pairs[i].indexOf('=');
                var name = eq < 0 ? pairs[i] : pairs[i].slice(0, eq);

                if (name === key) {
                    stored = decodeURIComponent(pairs[i].slice(eq + 1));
                    break;
                }
            }
        } else {
            stored = localStorage.getItem(key);
        }

        // Nothing stored follows the operating system, and an explicit choice always wins over it.
        var dark = stored === 'dark' || (stored !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);

        document.documentElement.classList.toggle('dark', dark);
    } catch {
        // Storage throws in private mode and when it is disabled. Swallowing that leaves the page on
        // its default theme, which is the right fallback, and never a page that fails to render.
    }
})();
