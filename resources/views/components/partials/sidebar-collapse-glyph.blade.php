{{-- optimistic-ui: n/a — presentational
     A glyph drawn inside the sidebar's collapse controls; it holds no value a server could
     accept or refuse. --}}
{{-- The collapse glyph, drawn by the sidebar's own control and by `sidebar.collapse-toggle`, so the
     two read as one button wherever it stands.

     A panel rather than a double chevron: the app rail's toggle draws the double chevron, and in
     one shell the two stood at the foot of neighboring columns reading as two versions of one
     button while they fold different things. The frame is the column, the rule inside it stands on
     the column's own side, and only the arrow turns. It points the way the column is about to move,
     so on the trailing side the two states swap, and the edge stays where the column is in both.

     Reads `$side` ('start' or 'end') from the including scope. The arrow turns with `collapsed`,
     which both including Alpine scopes carry. --}}
@php
    $glyphOnEnd = ($side ?? 'start') === 'end';

    // Written out per side. The arrow sits in the half away from the rule, drawn pointing left:
    // the direction the turn below has always assumed, so its states swap on the trailing side.
    $glyphEdge = $glyphOnEnd ? 'M15 3.75v16.5' : 'M9 3.75v16.5';
    $glyphArrow = $glyphOnEnd ? 'M10.5 9.75 8.25 12l2.25 2.25' : 'M15.75 9.75 13.5 12l2.25 2.25';

    // Computed here rather than assembled inside the attribute: a ternary built from string
    // fragments in a Blade echo is one missing quote away from emitting `collapsed ?  :`, a
    // JavaScript syntax error that only shows up in the reader's console.
    $glyphTurn = $glyphOnEnd
        ? "collapsed ? '' : 'rotate-180'"
        : "collapsed ? 'rotate-180' : ''";
@endphp
<svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6Z" />
    <path stroke-linecap="round" d="{{ $glyphEdge }}" />
    <path stroke-linecap="round" stroke-linejoin="round" class="origin-center [transform-box:fill-box] transition-transform duration-[var(--transition-wk-duration)]" :class="{{ $glyphTurn }}" d="{{ $glyphArrow }}" />
</svg>
