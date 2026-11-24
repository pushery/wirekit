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
 *
 * A page that fixes the mode says so on its root element, `data-wk-theme-fixed="light|dark"`, and
 * that mode wins over the reader's choice, which stays stored for the pages that fix nothing.
 *
 * A page that names the mode a reader without a choice gets says so on the same element,
 * `data-wk-theme-default="light|dark|system"`. A stored choice wins over it, and without it, or
 * with `system`, a reader without a choice follows the operating system.
 *
 * A `wire:navigate` hop keeps the document and gives its root element the attributes of the new
 * page, which takes the class away wherever the layout does not write it, and it does not run a
 * head script the new page carries unchanged. So the script applies the theme again in the
 * `onSwap` of `livewire:navigating`, which runs in the task that swaps the page in, before the new
 * page paints, after a link and after the back button alike. It reads the new page's root, so a
 * mode that page fixes or a default it names counts there.
 */
(function () {
    // The tag is only known while the script first runs, so its configuration is read once here.
    var tag = document.currentScript;
    var key = (tag && tag.getAttribute('data-wk-theme-key')) || 'wirekit-theme';
    var cookie = !!tag && tag.getAttribute('data-wk-theme-storage') === 'cookie';

    function storedChoice() {
        if (!cookie) {
            return localStorage.getItem(key);
        }

        // By exact name rather than by a pattern, so a key with pattern characters in it cannot
        // break the match. The theme controller reads its cookie the same way.
        //
        // The loop and the test for a missing `=` are written without `<`. The directive writes
        // this script into the page, and `strip_tags()`, which `assertSeeText()` reads a response
        // through, takes a `<` before anything but a space for the start of a tag: the text after
        // the script would drop out of that reading. The built file holds no such `<`.
        var pairs = (document.cookie || '').split('; ');

        for (var pair of pairs) {
            var eq = pair.indexOf('=');
            var name = eq === -1 ? pair : pair.slice(0, eq);

            if (name === key) {
                return decodeURIComponent(pair.slice(eq + 1));
            }
        }

        return null;
    }

    function apply() {
        try {
            var root = document.documentElement;
            var fixed = root.getAttribute('data-wk-theme-fixed');

            if (fixed === 'light' || fixed === 'dark') {
                root.classList.toggle('dark', fixed === 'dark');

                return;
            }

            var stored = storedChoice();

            // An explicit choice always wins. Without one the page's default decides, and without
            // that the operating system.
            var fallback = root.getAttribute('data-wk-theme-default');
            var dark = stored === 'dark' || (stored !== 'light' && (fallback === 'dark'
                || (fallback !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches)));

            root.classList.toggle('dark', dark);
        } catch {
            // Storage throws in private mode and when it is disabled. Swallowing that leaves the page
            // on its default theme, which is the right fallback, and never a page that fails to
            // render.
        }
    }

    apply();

    if (typeof document.addEventListener === 'function') {
        document.addEventListener('livewire:navigating', function (event) {
            var detail = event && event.detail;

            if (detail && typeof detail.onSwap === 'function') {
                detail.onSwap(apply);
            }
        });
    }
})();
