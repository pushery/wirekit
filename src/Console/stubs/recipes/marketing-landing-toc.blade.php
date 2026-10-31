{{-- Recipe: Marketing Landing Page with TOC — a brand bar, a sticky strip of links to the h2 sections, and the sections.
     Full reference: https://docs.wirekit.app/blueprints/recipes/marketing-landing-toc

     The strip scans the article for its h2 headings and renders one link per section, so a section
     you add shows up in it. The brand bar scrolls away with the page, and the strip then pins to
     the top of the viewport. --}}
<div>
    <x-wirekit::brand-bar padding="sm">
        <x-slot:brand>
            <x-wirekit::brand :name="config('app.name')" />
        </x-slot:brand>
        <x-slot:tagline>Ship faster, in less time.</x-slot:tagline>
    </x-wirekit::brand-bar>

    {{-- With `flush`, the strip runs from edge to edge, with no horizontal padding. --}}
    <x-wirekit::reading-toc flush />

    {{-- The compact density tightens the margins around the headings for a marketing page. --}}
    <x-wirekit::prose density="compact">
        <article>
            <section id="features">
                <h2>Features</h2>
                <p>Lead with what the product does for the reader. Selecting "Features" in the strip jumps here, and the link stays marked while this section is in view.</p>
            </section>

            <section id="pricing">
                <h2>Pricing</h2>
                <p>Lay out the plans. A link that points at #pricing opens the page at this section, with its link marked in the strip.</p>
            </section>

            <section id="faq">
                <h2>FAQ</h2>
                <p>Answer the questions that come up before a purchase. The strip stays in reach down to the end of the page.</p>
            </section>
        </article>
    </x-wirekit::prose>
</div>
