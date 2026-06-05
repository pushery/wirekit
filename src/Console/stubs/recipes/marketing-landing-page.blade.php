{{-- Recipe: Marketing Landing Page — composes brand-bar + hero + feature-grid + cta + footer.
     Full reference: https://docs.wirekit.app/blueprints/recipes/marketing-landing-page
     This scaffold ships the skeleton; expand each section with your copy + assets. --}}
<div>
    <x-wirekit::brand-bar :container="true">
        <x-slot:brand>
            <x-wirekit::brand name="Acme" href="/" />
        </x-slot:brand>
        <x-slot:actions>
            <x-wirekit::button href="/login" intent="neutral" surface="filled">Log in</x-wirekit::button>
            <x-wirekit::button href="/signup">Get started</x-wirekit::button>
        </x-slot:actions>
    </x-wirekit::brand-bar>

    <x-wirekit::hero variant="default" size="lg">
        <x-slot:title>Build production-grade Laravel apps faster</x-slot:title>
        <x-slot:lede>Drop in beautifully designed, accessible components and ship your next idea this week.</x-slot:lede>
        <x-slot:actions>
            <x-wirekit::button href="/get-started" size="lg">Start building</x-wirekit::button>
            <x-wirekit::button href="/docs" intent="neutral" surface="filled" size="lg">Read the docs</x-wirekit::button>
        </x-slot:actions>
    </x-wirekit::hero>

    <x-wirekit::feature-grid cols="3">
        <x-wirekit::feature title="Production-ready">Battle-tested across hundreds of components.</x-wirekit::feature>
        <x-wirekit::feature title="Accessible by default">WCAG 2.2 AA — every interactive component.</x-wirekit::feature>
        <x-wirekit::feature title="Dark mode included">Token-based theming with one toggle.</x-wirekit::feature>
    </x-wirekit::feature-grid>

    <x-wirekit::cta variant="accent">
        <x-slot:title>Ready to ship?</x-slot:title>
        <x-slot:lede>Install WireKit in two minutes — no credit card required.</x-slot:lede>
        <x-slot:actions>
            <x-wirekit::button href="/signup" intent="neutral" surface="filled" size="lg">Get started free</x-wirekit::button>
        </x-slot:actions>
    </x-wirekit::cta>

    <x-wirekit::footer />
</div>
