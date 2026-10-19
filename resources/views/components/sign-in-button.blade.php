{{-- optimistic-ui: n/a — navigation
     A link the browser follows to the service, or a button whose action the application owns;
     there is no result to show before it. --}}
@props([
    // The service: `google`, `apple`, `facebook`, `paypal` or `amazon`.
    'service' => null,
    // Where the sign-in with the service starts, the route that redirects to it. Without it the
    // component is a button, for a `wire:click` of the application's own.
    'href' => null,
    // What the button offers: `continue` ("Continue with Google"), `sign-in` or `sign-up`. Meta and
    // PayPal name no words for signing up, so there it continues.
    'action' => 'continue',
    // The look within what the service allows, its first by default. Google: `auto`, `light`,
    // `dark`, `neutral`. Apple: `auto`, `black`, `white`, `outline`. Facebook: `white`, `blue`.
    // PayPal: `blue`, `gray`. `auto` follows the page's mode. Amazon's image is its only look.
    'variant' => null,
    // The service's logo, a URL. Unset, `wirekit.sign_in.logos.<service>` from the config. For
    // Amazon, the whole button image.
    'logo' => null,
    // The words on the button, in place of the ones `action` gives.
    'label' => null,
    // A pill instead of the kit's corners, a shape Google and PayPal offer.
    'pill' => false,
    // As wide as its container, the way a sign-in page stacks them. `false` keeps it as wide as
    // its words.
    'block' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list auto-derived from
    // this component's @props.
    \Pushery\WireKit\WireKit::warnUnknownProps('sign-in-button', $attributes->getAttributes());

    // Each service with the name its guidelines write in the label.
    $wkServiceNames = ['google' => 'Google', 'apple' => 'Apple', 'facebook' => 'Facebook', 'paypal' => 'PayPal', 'amazon' => 'Amazon'];
    $service = WireKit::validateProp('sign-in-button', 'service', strtolower(trim((string) $service)), array_keys($wkServiceNames));
    $wkName = $wkServiceNames[$service];

    // Every look a service offers, the list a call is checked against first.
    $variant = $variant === null ? null : WireKit::validateProp('sign-in-button', 'variant', (string) $variant, ['auto', 'light', 'dark', 'neutral', 'black', 'white', 'outline', 'blue', 'gray']);

    // The looks this service allows, its default first, and a look of another service is refused
    // the same way. Google and Apple follow the page's mode with `auto`. Meta's blue reads at 4.23:1
    // under white text, short of the 4.5:1 a label of this size needs, so its white button comes
    // first and the blue is there for a page that takes that trade. Amazon allows its own button
    // images only, so it has no look to choose.
    $wkVariants = match ($service) {
        'google' => ['auto', 'light', 'dark', 'neutral'],
        'apple' => ['auto', 'black', 'white', 'outline'],
        'facebook' => ['white', 'blue'],
        'paypal' => ['blue', 'gray'],
        default => [],
    };
    $variant = $wkVariants === []
        ? null
        : \Pushery\WireKit\Support\StrictnessGate::enforce('sign-in-button', 'variant', (string) ($variant ?? $wkVariants[0]), $wkVariants);

    $action = WireKit::validateProp('sign-in-button', 'action', (string) $action, ['continue', 'sign-in', 'sign-up']);

    // The words each service's guidelines give. Amazon's button image says "Login with Amazon"
    // whatever the action, so its label, which a screen reader reads for the image, does too.
    $wkLabel = match (true) {
        filled($label) => (string) $label,
        $service === 'amazon' => __('wirekit::Login with Amazon'),
        $action === 'sign-in' && in_array($service, ['facebook', 'paypal'], true) => __('wirekit::Log in with :service', ['service' => $wkName]),
        $action === 'sign-in' => __('wirekit::Sign in with :service', ['service' => $wkName]),
        $action === 'sign-up' && in_array($service, ['google', 'apple'], true) => __('wirekit::Sign up with :service', ['service' => $wkName]),
        default => __('wirekit::Continue with :service', ['service' => $wkName]),
    };

    // Echoed into the tag, a URL is written escaped once (Support\UrlProp).
    $href = \Pushery\WireKit\Support\UrlProp::text($href);
    $logo = \Pushery\WireKit\Support\UrlProp::text($logo ?? config('wirekit.sign_in.logos.'.$service));

    $pill = BooleanProp::from($pill, false);
    $block = BooleanProp::from($block, true);

    // Amazon allows its own button images only, so with one the image is the button, at its own
    // size: stretched, its artwork would no longer be Amazon's.
    $wkImageButton = $service === 'amazon' && $logo !== null;

    // Each look's colors, from the tokens the services prescribe. Whole class strings: Tailwind
    // reads this file as text. Amazon without its image takes Facebook's white, a neutral button.
    $wkLook = match ($wkImageButton ? 'image' : $service.'-'.$variant) {
        'google-auto' => 'bg-[var(--color-wk-sign-in-google-fill)] text-[color:var(--color-wk-sign-in-google-text)] border-[var(--color-wk-sign-in-google-stroke)]',
        'google-light' => 'bg-[var(--color-wk-sign-in-google-light-fill)] text-[color:var(--color-wk-sign-in-google-light-text)] border-[var(--color-wk-sign-in-google-light-stroke)]',
        'google-dark' => 'bg-[var(--color-wk-sign-in-google-dark-fill)] text-[color:var(--color-wk-sign-in-google-dark-text)] border-[var(--color-wk-sign-in-google-dark-stroke)]',
        'google-neutral' => 'bg-[var(--color-wk-sign-in-google-neutral-fill)] text-[color:var(--color-wk-sign-in-google-neutral-text)] border-[var(--color-wk-sign-in-google-neutral-fill)]',
        'apple-auto' => 'bg-[var(--color-wk-sign-in-apple-fill)] text-[color:var(--color-wk-sign-in-apple-text)] border-[var(--color-wk-sign-in-apple-fill)]',
        'apple-black' => 'bg-[var(--color-wk-sign-in-black)] text-[color:var(--color-wk-sign-in-white)] border-[var(--color-wk-sign-in-black)]',
        'apple-white' => 'bg-[var(--color-wk-sign-in-white)] text-[color:var(--color-wk-sign-in-black)] border-[var(--color-wk-sign-in-white)]',
        'apple-outline' => 'bg-[var(--color-wk-sign-in-white)] text-[color:var(--color-wk-sign-in-black)] border-[var(--color-wk-sign-in-black)]',
        'facebook-blue' => 'bg-[var(--color-wk-sign-in-facebook-blue)] text-[color:var(--color-wk-sign-in-white)] border-[var(--color-wk-sign-in-facebook-blue)]',
        'paypal-blue' => 'bg-[var(--color-wk-sign-in-paypal-blue)] text-[color:var(--color-wk-sign-in-white)] border-[var(--color-wk-sign-in-paypal-blue)]',
        'paypal-gray' => 'bg-[var(--color-wk-sign-in-paypal-gray)] text-[color:var(--color-wk-sign-in-black)] border-[var(--color-wk-sign-in-paypal-gray)]',
        'image' => '',
        default => 'bg-[var(--color-wk-sign-in-white)] text-[color:var(--color-wk-sign-in-black)] border-[var(--color-wk-border-strong)]',
    };

    // The measures. Google sets 12px before its G, 10px after it and 12px after the words, which
    // the kit's padding and a quarter more of its small gap give. Apple's logo file carries its own
    // padding and is as tall as the button, and its title is 43% of the button's height.
    $wkLayout = match (true) {
        // `self-start`: in a stack, a column of flex items, it would be stretched to the stack's width.
        $wkImageButton => 'inline-flex max-w-full self-start',
        $service === 'apple' => 'h-[var(--size-wk-md)] pe-[var(--padding-wk-x-md)] text-[length:calc(var(--size-wk-md)*0.43)] font-[family-name:-apple-system,system-ui,sans-serif]',
        $service === 'google' => 'h-[var(--size-wk-md)] px-[var(--padding-wk-x-md)] gap-[calc(var(--gap-wk-sm)*1.25)] text-[length:var(--text-wk-md)] font-[family-name:var(--font-wk-sans)]',
        default => 'h-[var(--size-wk-md)] px-[var(--padding-wk-x-md)] gap-[var(--gap-wk-sm)] text-[length:var(--text-wk-md)] font-[family-name:var(--font-wk-sans)]',
    };

    $wkLogoClasses = $service === 'apple'
        ? 'h-[var(--size-wk-md)] w-auto shrink-0'
        : 'h-[calc(var(--size-wk-md)*0.5)] w-auto shrink-0';

    $wkClasses = WireKit::resolveClasses('sign-in-button', 'base', implode(' ', array_filter([
        'items-center justify-center no-underline select-none cursor-pointer',
        $wkImageButton ? '' : 'inline-flex border font-medium leading-none whitespace-nowrap',
        $wkImageButton ? '' : ($pill ? 'rounded-full' : 'rounded-[var(--radius-wk-md)]'),
        $block && ! $wkImageButton ? 'w-full' : '',
        $wkLayout,
        $wkLook,
        'focus:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
    ])), $scope);

    $wkTag = $href !== null ? 'a' : 'button';

    // A link the caller opens in a new tab gets `noopener noreferrer`, and the reader is told it
    // opens one. Rendered here rather than merged: a merge treats a caller's `rel` as the default
    // and would drop the protection.
    $opensNewTab = $href !== null && str_contains((string) $attributes->get('target', ''), '_blank');
    $relAttr = (string) $attributes->get('rel', '');
    $computedRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : ($relAttr !== '' ? $relAttr : null);
@endphp

<{{ $wkTag }} data-wk-prose-skip
    @if($href !== null) href="{{ $href }}" @else type="button" @endif
    data-wk-sign-in="{{ $service }}"
    @if($variant !== null && ! $wkImageButton) data-wk-variant="{{ $variant }}" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$wkClasses]) }}
>
    @if($wkImageButton)
        {{-- The service's image is the button, and its words are the image's text. --}}
        <img data-wk-prose-skip src="{{ $logo }}" alt="{{ $wkLabel }}" class="block h-auto max-w-full" />
    @else
        @if($logo !== null)
            {{-- The logo is the service's mark beside its name in the words, so it is not read twice. --}}
            <img data-wk-prose-skip src="{{ $logo }}" alt="" aria-hidden="true" class="{{ $wkLogoClasses }}" />
        @endif
        <span>{{ $wkLabel }}</span>
    @endif
    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $wkTag }}>
