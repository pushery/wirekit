{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include shared by `sidebar.group` and `sidebar.collapsible`. It
     renders no UI and accepts nothing a server could refuse. --}}
{{-- THE FIRST PAINT OF A REMEMBERED SECTION.

     A folding section with `persist` keeps its open state in the reader's browser, and no server
     can read localStorage. So the markup carries the state the server chose, and Alpine applies
     the remembered one when it starts. Until then the section showed the server's state: a group
     the reader had opened stayed hidden and then appeared, and one they had folded appeared open
     and then collapsed, on every page load.

     This script runs while the parser is still working, before anything is painted. It sits
     immediately after the section's panel and reaches it through
     `document.currentScript.previousElementSibling`, so it needs no id. It changes two things:
     whether the panel carries `x-cloak`, the attribute that keeps it hidden until Alpine starts,
     and whether the section's arrow is turned. Alpine removes `x-cloak` when it starts, and
     `x-show` and the arrow's binding take over from the same stored value, so neither disagrees
     with it for a frame. The arrow is the first `[data-wk-disclosure-arrow]` in the section,
     which is its own: the header comes before the panel, and a nested section's arrow is inside
     the panel.

     Every value arrives as a data attribute on this tag and the body is a constant, for the
     reason `nav-persist-seed` gives: a script body is not escaped the way an attribute is.

     It is a correction and never a requirement. Blocked by a policy, thrown by a browser with
     storage switched off, or never reached, the section keeps the behavior it has without it.

     Params: $seedKey (the `persist` key) and $seedOn (the state the server rendered). --}}
@php
    $seedNonce = \Pushery\WireKit\WireKit::cspNonce();
@endphp
<script
    @if($seedNonce)nonce="{{ $seedNonce }}"@endif
    data-wk-disclosure-seed-key="{{ $seedKey }}"
    data-wk-disclosure-seed-on="{{ $seedOn ? '1' : '0' }}"
>
(function () {
    var self = document.currentScript;
    var panel = self && self.previousElementSibling;
    if (!panel) { return; }

    var cfg = self.dataset;
    var stored;
    try { stored = window.localStorage.getItem(cfg.wkDisclosureSeedKey); }
    catch (e) { return; }
    if (stored === null) { return; }

    var on = stored === '1';
    if (on === (cfg.wkDisclosureSeedOn === '1')) { return; }

    // A section the reader opened shows at once, like one the server rendered open; one they
    // folded stays hidden until Alpine starts, like one the server rendered closed.
    if (on) { panel.removeAttribute('x-cloak'); } else { panel.setAttribute('x-cloak', ''); }

    var arrow = panel.parentElement && panel.parentElement.querySelector('[data-wk-disclosure-arrow]');
    if (arrow) { arrow.classList.toggle('rotate-90', on); }
})();
</script>
