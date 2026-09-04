{{-- Recipe: On-Page TOC — page-scoped reading progress + a right-edge sticky spine.
     Full reference: https://docs.wirekit.app/blueprints/recipes/on-page-toc
     The spine builds its link list from the headings inside <main>; give every
     heading you want surfaced an id, or the scroll-to-anchor links land nowhere. --}}
<div>
    {{-- Page-scoped: this bar tracks the whole document, not the article. --}}
    <x-wirekit::reading-progress />

    {{-- A sibling of <main>, never a descendant of it. `expand="hover"` keeps the
         spine narrow at rest and opens it on hover; `expand="always"` suits a
         dedicated docs layout. --}}
    <x-wirekit::reading-spine
        target="main"
        levels="2,3"
        expand="hover"
    />

    <main>
        <article>
            <h2 id="introduction">Introduction</h2>
            <p>Open with what the page is for. The spine highlights this section while it is in view.</p>

            <h2 id="setup">Setup</h2>
            <p>The three primitives compose: a progress bar, a spine, and your own content in a <code>main</code> element.</p>

            <h3 id="prerequisites">Prerequisites</h3>
            <p>Heading ids are the anchors the spine links to. Most Markdown renderers add them; hand-written Blade does not.</p>

            <h2 id="usage">Usage</h2>
            <p>Replace these sections with your own. Add or remove headings freely — the spine rebuilds from whatever is there.</p>
        </article>
    </main>
</div>
