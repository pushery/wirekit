{{-- Recipe: Reading Sidebar — one article, one bounded scroll container.
     Full reference: https://docs.wirekit.app/blueprints/recipes/reading-sidebar
     Reach for this over the on-page-toc recipe when the reading surface is a
     bounded region rather than the document: the progress fills as the reader
     reaches the end of THIS article, not the end of the page. --}}
<div>
    {{-- The scroll container carries its own height and overflow, so it needs a
         name and a tab stop of its own (WCAG 2.1.1) to be operable by keyboard. --}}
    <div role="region" aria-label="Article" tabindex="0" style="height: 100vh; overflow: auto;">
        {{-- reading-shell composes the progress bar, the sidebar TOC and the
             bookmark in one tag. `boundary="container"` scopes all three to this
             container instead of the viewport — that scoping IS the recipe. --}}
        <x-wirekit::reading-shell boundary="container" bookmarkKey="article-1">
            <article>
                <h2 id="introduction">Introduction</h2>
                <p>Open with what the article is about. The sidebar highlights this section while it is in view.</p>

                <h2 id="setup">Setup</h2>
                <p>Swap this body for your own prose; the sidebar rebuilds from whatever headings it finds.</p>

                <h3 id="prerequisites">Prerequisites</h3>
                <p>Give every heading you want in the sidebar an id — that is what its links point at.</p>

                <h2 id="usage">Usage</h2>
                <p>Give each article its own bookmarkKey so a reader's position is remembered per article.</p>
            </article>
        </x-wirekit::reading-shell>
    </div>
</div>
