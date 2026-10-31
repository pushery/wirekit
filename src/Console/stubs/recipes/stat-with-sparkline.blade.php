{{-- Recipe: Stat with Sparkline — a KPI with its change, an inline trend line and a citation.
     Full reference: https://docs.wirekit.app/blueprints/recipes/stat-with-sparkline

     The trend line is a static SVG polyline, so it needs no chart library. To draw it from your
     data, build the points string in your Livewire class and bind it with :points. The citation
     says where the number comes from. --}}
<div>
    <x-wirekit::stat
        label="Total Events"
        value="2.4M"
        change="+12.3%"
        trend="up"
        citation="Source: events table (snapshot {{ now()->format('Y-m-d') }})"
    >
        <x-slot:sparkline>
            <svg viewBox="0 0 80 24" width="80" height="24" aria-hidden="true" style="display: block">
                <polyline
                    points="0,18 10,16 20,14 30,10 40,12 50,8 60,6 70,4 80,2"
                    fill="none"
                    stroke="var(--color-wk-accent)"
                    stroke-width="1.5"
                />
            </svg>
        </x-slot:sparkline>
    </x-wirekit::stat>
</div>
