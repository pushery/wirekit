{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include of `sidebar`. It renders no UI and accepts nothing a server
     could refuse. --}}
{{-- The first paint of a width the reader dragged a sidebar to, with the `local` driver.

     The same correction `nav-persist-seed` makes for the folded state, for the width: no server
     can read localStorage, so without it the column paints at its declared width and takes the
     stored one a frame later, moving the content beside it. A script that runs while the parser
     is still at work sets the width before anything is painted. It stands after the column, past
     the folded state's own seed when there is one, and reaches the column through its previous
     siblings, so it needs no id and cannot reach a second column on the same page.

     The stored pixels go into a CSS `clamp()` between the bounds, so a width kept from before the
     bounds were narrowed holds inside them. Every value arrives as a data attribute and the body
     is a constant, for the reason the folded state's seed gives. Blocked or never reached, it
     leaves the column as it is without it: Alpine applies the same width a frame later. --}}
@php
    $seedNonce = \Pushery\WireKit\WireKit::cspNonce();
@endphp
<script
    @if($seedNonce)nonce="{{ $seedNonce }}"@endif
    data-wk-seed-width-key="{{ $persist }}-width"
    data-wk-seed-width-min="{{ $minWidth }}"
    data-wk-seed-width-max="{{ $maxWidth }}"
>
(function () {
    var self = document.currentScript;
    var el = self && self.previousElementSibling;
    while (el && el.tagName === 'SCRIPT') { el = el.previousElementSibling; }
    if (!el) { return; }

    var cfg = self.dataset;
    var stored;
    try { stored = window.localStorage.getItem(cfg.wkSeedWidthKey); }
    catch (e) { return; }
    if (stored === null || !/^\d{1,5}$/.test(stored)) { return; }

    el.style.setProperty('--wk-sidebar-w', 'clamp(' + cfg.wkSeedWidthMin + ', ' + stored + 'px, ' + cfg.wkSeedWidthMax + ')');
})();
</script>
