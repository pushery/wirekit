{{-- Recipe: Marketing Landing Page — navbar, hero, features, stats, pricing, call to action and footer.
     Full reference: https://docs.wirekit.app/blueprints/recipes/marketing-landing-page

     These are the seven sections the reference names as the smallest complete landing page, in
     the markup of its full composition. The other four (two detail blocks, testimonials and an
     FAQ) need material only you have, performance figures, quotes and real questions, and the
     reference carries them for when you do. Every section spans the full width and centers its
     content on the same max="xl" column, so the left edges line up from the navbar down to the
     footer. --}}
<div>
    <x-wirekit::stack gap="none" class="w-full">
        <x-wirekit::navbar container max="xl">
            <x-slot:brand>
                <x-wirekit::brand :name="config('app.name')" href="/" />
            </x-slot:brand>
            <x-wirekit::navbar.item href="#features">Features</x-wirekit::navbar.item>
            <x-wirekit::navbar.item href="#pricing">Pricing</x-wirekit::navbar.item>
            <x-wirekit::navbar.item href="/docs">Docs</x-wirekit::navbar.item>
            <x-slot:actions>
                <x-wirekit::button intent="neutral" surface="ghost" href="/login">Sign in</x-wirekit::button>
                <x-wirekit::button href="/register">Get started</x-wirekit::button>
            </x-slot:actions>
        </x-wirekit::navbar>

        <x-wirekit::hero size="lg" layout="centered" gradient animateIn="fade">
            <x-slot:eyebrow>
                <x-wirekit::badge intent="accent">New: shared workspaces</x-wirekit::badge>
            </x-slot:eyebrow>
            <x-slot:title>Ship faster, in less time.</x-slot:title>
            <x-slot:lede>
                Push to Git, and the build, the rollout and the rollback happen on their own. A release takes minutes instead of an afternoon.
            </x-slot:lede>
            <x-slot:actions>
                <x-wirekit::button size="lg" href="/register">Get started for free</x-wirekit::button>
                <x-wirekit::button size="lg" intent="neutral" surface="outline" href="#features">See how it works</x-wirekit::button>
            </x-slot:actions>
        </x-wirekit::hero>

        <x-wirekit::section id="features" padding="2xl" background="muted">
            <x-wirekit::container max="xl">
                <x-wirekit::stack gap="xl" align="center">
                    <x-wirekit::stack gap="sm" align="center">
                        <x-wirekit::heading level="2" size="3xl">Everything a release needs.</x-wirekit::heading>
                        <x-wirekit::text intent="muted" align="center">Three parts of one platform, with no plugins to keep in step.</x-wirekit::text>
                    </x-wirekit::stack>
                    <x-wirekit::feature-grid cols="1 sm:2 lg:3" gap="lg" stagger>
                        <x-wirekit::feature animateIn="slide-up" icon="bolt" title="Instant deploys" class="items-center text-center">
                            A push is live in under a second, and only what changed is rebuilt.
                        </x-wirekit::feature>
                        <x-wirekit::feature animateIn="slide-up" icon="shield-check" title="Global failover" class="items-center text-center">
                            When a region goes down, traffic moves to the next healthy one.
                        </x-wirekit::feature>
                        <x-wirekit::feature animateIn="slide-up" icon="lock-closed" title="Security and compliance" class="items-center text-center">
                            Single sign-on, audit logs and encryption with your own keys.
                        </x-wirekit::feature>
                    </x-wirekit::feature-grid>
                </x-wirekit::stack>
            </x-wirekit::container>
        </x-wirekit::section>

        {{-- The band paints the inverse surface in both themes. The sparklines are decorative,
             since each stat states its trend in words as well, so they are hidden from assistive
             technology. --}}
        <x-wirekit::section padding="2xl" style="background: linear-gradient(135deg, var(--color-wk-bg-inverse) 0%, color-mix(in srgb, var(--color-wk-bg-inverse) 90%, var(--color-wk-text-inverse)) 100%); color: var(--color-wk-text-inverse);">
            <x-wirekit::container max="lg">
                <x-wirekit::stats cols="3" stagger>
                    <x-wirekit::stat animateIn="slide-up" animate label="Average deploy time" value="781" description="ms" trend="down" change="-12">
                        <x-slot:sparkline>
                            <svg viewBox="0 0 80 24" width="80" height="24" aria-hidden="true" style="display: block">
                                <polyline points="0,4 10,8 20,6 30,12 40,10 50,14 60,16 70,18 80,20" fill="none" stroke="var(--color-wk-danger)" stroke-width="1.5" />
                            </svg>
                        </x-slot:sparkline>
                    </x-wirekit::stat>
                    <x-wirekit::stat animateIn="slide-up" animate label="Uptime" value="99.99" description="%" trend="up" change="+0.03">
                        <x-slot:sparkline>
                            <svg viewBox="0 0 80 24" width="80" height="24" aria-hidden="true" style="display: block">
                                <polyline points="0,6 10,5 20,7 30,4 40,3 50,5 60,3 70,2 80,2" fill="none" stroke="var(--color-wk-success)" stroke-width="1.5" />
                            </svg>
                        </x-slot:sparkline>
                    </x-wirekit::stat>
                    <x-wirekit::stat animateIn="slide-up" animate label="Deploys per day" value="12400" change="+8" trend="up">
                        <x-slot:sparkline>
                            <svg viewBox="0 0 80 24" width="80" height="24" aria-hidden="true" style="display: block">
                                <polyline points="0,20 10,18 20,16 30,12 40,10 50,8 60,6 70,4 80,2" fill="none" stroke="var(--color-wk-accent)" stroke-width="1.5" />
                            </svg>
                        </x-slot:sparkline>
                    </x-wirekit::stat>
                </x-wirekit::stats>
            </x-wirekit::container>
        </x-wirekit::section>

        <x-wirekit::section id="pricing" padding="2xl" background="muted">
            <x-wirekit::container max="xl">
                <x-wirekit::stack gap="xl" align="center">
                    <x-wirekit::stack gap="sm" align="center">
                        <x-wirekit::heading level="2" size="3xl">Pay for what you use.</x-wirekit::heading>
                        <x-wirekit::text intent="muted" align="center">Every plan includes every feature; the plans differ in capacity and support.</x-wirekit::text>
                    </x-wirekit::stack>
                    <x-wirekit::grid cols="1 md:3" gap="md">
                        <x-wirekit::card>
                            <x-wirekit::card.body>
                                <x-wirekit::stack gap="md">
                                    <x-wirekit::heading level="3" size="lg">Hobby</x-wirekit::heading>
                                    <x-wirekit::text size="sm" intent="muted">For personal projects and prototypes.</x-wirekit::text>
                                    <x-wirekit::price :amount="0" currency="USD" size="lg">
                                        <x-slot:suffix>/forever</x-slot:suffix>
                                    </x-wirekit::price>
                                    <x-wirekit::list type="none" spacing="sm">
                                        <x-wirekit::list.item>100 GB bandwidth</x-wirekit::list.item>
                                        <x-wirekit::list.item>1 concurrent build</x-wirekit::list.item>
                                        <x-wirekit::list.item>Community support</x-wirekit::list.item>
                                    </x-wirekit::list>
                                    <x-wirekit::button intent="neutral" surface="outline" href="/register">Start free</x-wirekit::button>
                                </x-wirekit::stack>
                            </x-wirekit::card.body>
                        </x-wirekit::card>
                        <x-wirekit::card>
                            <x-wirekit::card.body>
                                <x-wirekit::stack gap="md">
                                    <x-wirekit::row justify="between" wrap align="center">
                                        <x-wirekit::heading level="3" size="lg">Pro</x-wirekit::heading>
                                        <x-wirekit::badge intent="accent">Most popular</x-wirekit::badge>
                                    </x-wirekit::row>
                                    <x-wirekit::text size="sm" intent="muted">For teams that release every day.</x-wirekit::text>
                                    <x-wirekit::price :amount="20" currency="USD" size="lg">
                                        <x-slot:suffix>/user/month</x-slot:suffix>
                                    </x-wirekit::price>
                                    <x-wirekit::list type="none" spacing="sm">
                                        <x-wirekit::list.item>1 TB bandwidth</x-wirekit::list.item>
                                        <x-wirekit::list.item>10 concurrent builds</x-wirekit::list.item>
                                        <x-wirekit::list.item>Email and chat support</x-wirekit::list.item>
                                    </x-wirekit::list>
                                    <x-wirekit::button href="/register">Start a 14-day trial</x-wirekit::button>
                                </x-wirekit::stack>
                            </x-wirekit::card.body>
                        </x-wirekit::card>
                        <x-wirekit::card>
                            <x-wirekit::card.body>
                                <x-wirekit::stack gap="md">
                                    <x-wirekit::heading level="3" size="lg">Enterprise</x-wirekit::heading>
                                    <x-wirekit::text size="sm" intent="muted">For regulated industries and large teams.</x-wirekit::text>
                                    <x-wirekit::heading level="3" size="2xl">Custom</x-wirekit::heading>
                                    <x-wirekit::list type="none" spacing="sm">
                                        <x-wirekit::list.item>Unlimited bandwidth</x-wirekit::list.item>
                                        <x-wirekit::list.item>Single sign-on and provisioning</x-wirekit::list.item>
                                        <x-wirekit::list.item>99.99% uptime commitment</x-wirekit::list.item>
                                    </x-wirekit::list>
                                    <x-wirekit::button intent="neutral" surface="outline" href="/contact">Talk to sales</x-wirekit::button>
                                </x-wirekit::stack>
                            </x-wirekit::card.body>
                        </x-wirekit::card>
                    </x-wirekit::grid>
                </x-wirekit::stack>
            </x-wirekit::container>
        </x-wirekit::section>

        {{-- `variant="dark"` paints the inverse surface, so the button takes the neutral filled
             look: a default button there would be the accent color on a background close to it. --}}
        <x-wirekit::cta variant="dark" size="lg" animateIn="fade">
            <x-slot:title>Ship faster today.</x-slot:title>
            <x-slot:description>
                Start on the free plan and move up when your team does. No credit card needed.
            </x-slot:description>
            <x-slot:actions>
                <x-wirekit::button intent="neutral" surface="filled" href="/register">Start for free &rarr;</x-wirekit::button>
            </x-slot:actions>
        </x-wirekit::cta>

        <x-wirekit::footer max="xl">
            <x-slot:columns>
                <x-wirekit::stack gap="sm">
                    <x-wirekit::heading level="3" size="sm">Product</x-wirekit::heading>
                    <x-wirekit::list type="none" spacing="sm">
                        <x-wirekit::list.item><x-wirekit::link href="#features" variant="muted" underline="hover">Features</x-wirekit::link></x-wirekit::list.item>
                        <x-wirekit::list.item><x-wirekit::link href="#pricing" variant="muted" underline="hover">Pricing</x-wirekit::link></x-wirekit::list.item>
                        <x-wirekit::list.item><x-wirekit::link href="/changelog" variant="muted" underline="hover">Changelog</x-wirekit::link></x-wirekit::list.item>
                    </x-wirekit::list>
                </x-wirekit::stack>
                <x-wirekit::stack gap="sm">
                    <x-wirekit::heading level="3" size="sm">Company</x-wirekit::heading>
                    <x-wirekit::list type="none" spacing="sm">
                        <x-wirekit::list.item><x-wirekit::link href="/about" variant="muted" underline="hover">About</x-wirekit::link></x-wirekit::list.item>
                        <x-wirekit::list.item><x-wirekit::link href="/blog" variant="muted" underline="hover">Blog</x-wirekit::link></x-wirekit::list.item>
                        <x-wirekit::list.item><x-wirekit::link href="/contact" variant="muted" underline="hover">Contact</x-wirekit::link></x-wirekit::list.item>
                    </x-wirekit::list>
                </x-wirekit::stack>
                <x-wirekit::stack gap="sm">
                    <x-wirekit::heading level="3" size="sm">Resources</x-wirekit::heading>
                    <x-wirekit::list type="none" spacing="sm">
                        <x-wirekit::list.item><x-wirekit::link href="/docs" variant="muted" underline="hover">Documentation</x-wirekit::link></x-wirekit::list.item>
                        <x-wirekit::list.item><x-wirekit::link href="/status" variant="muted" underline="hover">Status</x-wirekit::link></x-wirekit::list.item>
                        <x-wirekit::list.item><x-wirekit::link href="/security" variant="muted" underline="hover">Security</x-wirekit::link></x-wirekit::list.item>
                    </x-wirekit::list>
                </x-wirekit::stack>
            </x-slot:columns>
            <x-slot:brand>
                <x-wirekit::text size="xs" intent="muted">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</x-wirekit::text>
            </x-slot:brand>
            <x-slot:legal>
                <x-wirekit::link href="/privacy" variant="muted" underline="hover">Privacy</x-wirekit::link>
                <x-wirekit::link href="/terms" variant="muted" underline="hover">Terms</x-wirekit::link>
            </x-slot:legal>
        </x-wirekit::footer>
    </x-wirekit::stack>
</div>
