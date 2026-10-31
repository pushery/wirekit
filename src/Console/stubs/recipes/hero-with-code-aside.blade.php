{{-- Recipe: Hero with Code Aside — the copy on one side and the install commands in a code block on the other.
     Full reference: https://docs.wirekit.app/blueprints/recipes/hero-with-code-aside

     The default layout gives the copy and the aside half the width each and stacks them on a
     narrow screen. Switch to layout="lead" when the headline carries the message and the code
     supports it. --}}
<div>
    <x-wirekit::hero>
        <x-slot:eyebrow>
            <x-wirekit::badge intent="info">New</x-wirekit::badge>
        </x-slot:eyebrow>
        <x-slot:title>Laravel UI components, two commands away.</x-slot:title>
        <x-slot:lede>Components themed by your design tokens and fonts served from your own domain.</x-slot:lede>
        <x-slot:actions>
            <x-wirekit::button href="/get-started">Get started</x-wirekit::button>
            <x-wirekit::button href="/docs" intent="neutral" surface="outline">Read the docs</x-wirekit::button>
        </x-slot:actions>
        <x-slot:aside>
            <x-wirekit::code-block language="bash" filename="Install" :copy="true">composer require pushery/wirekit
php artisan wirekit:install</x-wirekit::code-block>
        </x-slot:aside>
    </x-wirekit::hero>
</div>
