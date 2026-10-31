{{-- Recipe: Feature with Numbered Marker — a numbered step-marker in the place of each feature's icon.
     Full reference: https://docs.wirekit.app/blueprints/recipes/feature-numbered-marker

     The steps are spelled out here so the scaffolded view renders the moment it is written; swap
     the array for the list your Livewire class builds. Each number comes from the step's position,
     so reordering the steps renumbers them. --}}
<div>
    <x-wirekit::feature-grid cols="1 sm:2 lg:3" gap="lg">
        @foreach ([
            ['title' => 'Connect your data', 'description' => 'Pull rows from your warehouse, with no export step in between.'],
            ['title' => 'Define dashboards', 'description' => 'Compose KPI cards from the stat and ticker components.'],
            ['title' => 'Share with your team', 'description' => 'Invite people in one click, with roles and an audit log.'],
        ] as $i => $step)
            <x-wirekit::feature :title="$step['title']">
                {{-- iconSlot is the place above the title where the feature's icon would stand. --}}
                <x-slot:iconSlot>
                    <x-wirekit::step-marker intent="primary">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</x-wirekit::step-marker>
                </x-slot:iconSlot>
                {{ $step['description'] }}
            </x-wirekit::feature>
        @endforeach
    </x-wirekit::feature-grid>
</div>
