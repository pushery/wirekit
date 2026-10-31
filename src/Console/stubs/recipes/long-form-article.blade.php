{{-- Recipe: Long-Form Article — reading-shell around a prose article with its byline and the time to read.
     Full reference: https://docs.wirekit.app/blueprints/recipes/long-form-article

     The shell adds the progress bar, the section spine and the prompt to resume reading; the spine
     builds its links from the article's headings. Give each article its own bookmarkKey, from its
     slug for example, so a reader's position is kept per article. --}}
<div>
    <x-wirekit::reading-shell bookmarkKey="article-declarative-components">
        <x-wirekit::prose>
            <header>
                <h1>The case for declarative components</h1>
                {{-- The byline is ordinary markup: author and date are yours to render however your
                     model spells them. reading-meta measures the article's own text and shows the
                     time to read from it. --}}
                <x-wirekit::text intent="muted" as="p">By Jane Doe · Published 2026-05-21</x-wirekit::text>
                <x-wirekit::reading-meta :showRemaining="true" />
            </header>

            <article>
                <h2 id="introduction">Introduction</h2>
                <p>Declarative APIs let your application's intent show through the markup. Imperative wiring buries that intent in event handlers and side effects.</p>

                <h2 id="in-practice">What this looks like in practice</h2>
                <p>Consider a card with a header, a body and a footer. The declarative shape uses three composable parts; the imperative shape asks you to know which class names go on which elements.</p>

                <h3 id="refactoring">Refactoring</h3>
                <p>A part that names what it is can be moved, wrapped or replaced without hunting for the script that expected it somewhere else.</p>

                <h2 id="tradeoff">The tradeoff</h2>
                <p>Declarative components are longer at the call site and easier to read. Most teams find that legibility wins once a codebase grows past a few thousand lines.</p>
            </article>
        </x-wirekit::prose>
    </x-wirekit::reading-shell>
</div>
