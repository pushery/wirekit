{{-- Recipe: Stat with Sparkline — KPI tile with inline trend visualization.
     Full reference: https://docs.wirekit.app/blueprints/recipes/stat-with-sparkline --}}
<div>
    <x-wirekit::row>
        <x-wirekit::stat
            label="Revenue"
            value="$12,340"
            change="+12%"
            trend="up"
        >
            <x-slot:sparkline>
                <x-wirekit::sparkline
                    :data="[12, 14, 13, 16, 18, 17, 20, 22, 21, 24]"
                    height="32px"
                />
            </x-slot:sparkline>
        </x-wirekit::stat>

        <x-wirekit::stat
            label="Active Users"
            value="2,840"
            change="+8%"
            trend="up"
        >
            <x-slot:sparkline>
                <x-wirekit::sparkline
                    :data="[2700, 2750, 2720, 2780, 2810, 2820, 2840]"
                    height="32px"
                />
            </x-slot:sparkline>
        </x-wirekit::stat>
    </x-wirekit::row>
</div>
