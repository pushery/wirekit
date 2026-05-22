{{-- Recipe: Stat with Sparkline — KPI tile with inline trend visualisation.
     Full reference: https://docs.wirekit.app/recipes/stat-with-sparkline --}}
<div>
    <x-wirekit::row>
        <x-wirekit::stat
            label="Revenue"
            value="$12,340"
            delta="+12%"
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
            delta="+8%"
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
