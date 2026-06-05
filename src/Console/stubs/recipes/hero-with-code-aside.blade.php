{{-- Recipe: Hero with Code Aside — copy on the left, code block on the right (balanced layout).
     Full reference: https://docs.wirekit.app/blueprints/recipes/hero-with-code-aside --}}
<div>
    <x-wirekit::hero layout="balanced" variant="default">
        <x-slot:title>Ship your dashboard in minutes</x-slot:title>
        <x-slot:lede>One command. Zero config. Production-grade Livewire components ready for your app.</x-slot:lede>
        <x-slot:actions>
            <x-wirekit::button href="/get-started" size="lg">Get started</x-wirekit::button>
            <x-wirekit::button href="/docs" intent="neutral" surface="filled" size="lg">Read the docs</x-wirekit::button>
        </x-slot:actions>
        <x-slot:aside>
            <x-wirekit::code-block language="bash">composer require pushery/wirekit
php artisan wirekit:install
npm install && npm run build</x-wirekit::code-block>
        </x-slot:aside>
    </x-wirekit::hero>
</div>
