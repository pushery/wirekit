{{-- Recipe: Documentation Reader — long-form article + reading-spine + reading-progress.
     Full reference: https://docs.wirekit.app/blueprints/recipes/documentation-reader
     Drop your prose into <main>; the reading widgets auto-detect headings. --}}
<div>
    <x-wirekit::reading-progress />

    <x-wirekit::app-shell>
        <x-slot:sidebar>
            <x-wirekit::reading-spine />
        </x-slot:sidebar>

        <main>
            <article class="prose">
                <h1>Getting started</h1>
                <p>Open the reader on any modern browser. The spine on the left auto-builds from your headings.</p>

                <h2>Installation</h2>
                <p>Install via Composer:</p>
                <x-wirekit::code-block language="bash">composer require pushery/wirekit</x-wirekit::code-block>

                <h2>Usage</h2>
                <p>Drop the reading-spine into your sidebar; it will populate from <code>h1</code> / <code>h2</code> elements inside <code>main</code> automatically.</p>

                <h2>Customization</h2>
                <p>Override the target selector via <code>target="#article-body"</code> to scope the spine to a specific region.</p>
            </article>
        </main>
    </x-wirekit::app-shell>
</div>
