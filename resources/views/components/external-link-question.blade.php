{{-- optimistic-ui: n/a — client-only
     A question before a link takes the reader to another site. It sends nothing to a server: Visit
     goes on with the link, Stay closes the dialog. --}}
@props([
    // Hosts that never ask, beside the configuration's: an instance's own addresses, known per
    // request. A host as it is, or `*.example.com` for every host under a domain and the domain
    // itself. The page's own host never asks.
    'trusted' => [],
    // Where a host the reader chose not to be warned about again is kept: `browser`, the browser's
    // local storage, or `none`. Either way `wirekit:external-link-trusted` carries the host to the
    // page, which may keep it per user and pass it back in `trusted`.
    'remember' => config('wirekit.components.external-link-question.remember', 'browser'),
    'scope' => null,
])
@php
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\DomId;
    use Pushery\WireKit\Support\StrictnessGate;
    use Pushery\WireKit\WireKit;

    // Dev-only: flags an unknown prop in debug, silent in production.
    WireKit::warnUnknownProps('external-link-question', $attributes->getAttributes());

    // The configuration's hosts and the prop's, each a string; anything else names no host. The
    // prop is read as a list whatever it arrives as: an array, a Collection of an instance's
    // addresses, a paginator.
    $hosts = [];
    $given = (array) \Pushery\WireKit\Support\ListProp::renumbered($trusted, 'external-link-question', 'trusted');

    foreach ([...(array) config('wirekit.components.external-link-question.trusted', []), ...$given] as $host) {
        if (is_string($host) && trim($host) !== '') {
            $hosts[] = strtolower(trim($host));
        }
    }

    $remember = is_string($remember) ? strtolower(trim($remember)) : 'browser';

    if (! in_array($remember, ['browser', 'none'], true)) {
        $remember = StrictnessGate::reject('external-link-question', 'remember', $remember, 'browser or none', 'browser');
    }

    // A name of its own, so two pages in one document, a page and its preview, never answer for
    // each other's dialog.
    $name = DomId::distinct('wk-external-link-question');

    $config = [
        'name' => $name,
        'trusted' => array_values(array_unique($hosts)),
        'remember' => $remember,
        'titleText' => __('wirekit::Go to :host?'),
        'forgetText' => __("wirekit::Don't warn me about links to :host again"),
        'writtenText' => __('wirekit::The link writes its host as :host.'),
    ];

    $classes = WireKit::resolveClasses('external-link-question', 'base', 'contents', $scope);

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
@endphp
{{-- One element around the dialog, in which the factory listens for the page's link clicks. It
     takes no room: `contents`, unless the caller gives it a display of its own. --}}
<div data-wk-external-link-question x-data="wirekitExternalLinkQuestion({{ AlpinePayload::from($config) }})" {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    <x-wirekit::alert-dialog :name="$name" :close-on-confirm="false" :scope="$scope">
        <x-wirekit::alert-dialog.title><span x-text="title"></span></x-wirekit::alert-dialog.title>
        <x-wirekit::alert-dialog.description>
            {{ __('wirekit::This link takes you to another site. An address can be disguised, so check it before you go on.') }}
        </x-wirekit::alert-dialog.description>
        {{-- The address as the browser calls it, so a host in disguise, a user name before the `@`
             and a host in punycode all stand in plain sight. It breaks at the seams of an address,
             as `break="address"` sets them, which the factory writes into the element from
             `x-effect` whenever the address changes, and anywhere only inside a part too long for
             the line. The sentence
             under it wraps as text, its host anywhere where it is too long. --}}
        <p data-wk-prose-skip class="mt-[var(--space-wk-sm)]">
            <code data-wk-prose-skip data-wk-external-link-address x-effect="paintAddress($el)" class="block [overflow-wrap:anywhere] rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-soft)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] font-[family-name:var(--font-wk-mono)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]"></code>
        </p>
        <p data-wk-prose-skip data-wk-external-link-written x-show="written" x-text="writtenLine" class="mt-[var(--space-wk-xs)] [overflow-wrap:anywhere] font-[family-name:var(--font-wk-sans)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]"></p>
        {{-- No `name`: the choice is the factory's, and a form around the page sends nothing of it. --}}
        <div class="mt-[var(--space-wk-md)]">
            <x-wirekit::checkbox x-model="forget" :align-fields="false"><span x-text="forgetLabel"></span></x-wirekit::checkbox>
        </div>
        <x-wirekit::alert-dialog.actions>
            <x-wirekit::alert-dialog.cancel>
                <x-wirekit::button intent="neutral" size="sm" x-on:click="stay()">{{ __('wirekit::Stay here') }}</x-wirekit::button>
            </x-wirekit::alert-dialog.cancel>
            <x-wirekit::button size="sm" data-wk-dialog-primary data-wk-external-link-visit x-on:click="visit()">{{ __('wirekit::Visit site') }}</x-wirekit::button>
        </x-wirekit::alert-dialog.actions>
    </x-wirekit::alert-dialog>
</div>
