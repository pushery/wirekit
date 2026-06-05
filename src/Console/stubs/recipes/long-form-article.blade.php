{{-- Recipe: Long-Form Article — readable column with metadata header + prose body.
     Full reference: https://docs.wirekit.app/blueprints/recipes/long-form-article --}}
<div>
    <x-wirekit::container max="md">
        <x-wirekit::reading-meta
            author="Jane Doe"
            published="2026-05-21"
            reading-time="6 min read"
        />

        <article class="prose">
            <h1>The case for declarative components</h1>
            <p class="lede">Declarative APIs let your application's intent show through the markup. Imperative wiring buries that intent in event handlers and side effects.</p>

            <h2>What this looks like in practice</h2>
            <p>Consider a card with a header, body, and footer. The declarative shape uses three composable sub-components; the imperative shape requires you to know which class names to attach to which DOM elements.</p>

            <h2>The tradeoff</h2>
            <p>Declarative components are more verbose at the call site, but they're also more legible to readers and easier to refactor. Most teams find the tradeoff favors legibility once a codebase grows past a few thousand lines.</p>
        </article>
    </x-wirekit::container>
</div>
