{{-- optimistic-ui: n/a — passthrough
     The token reaches the server through the caller's `wire:model` once the provider gives it,
     and there is nothing to show before it.
     A captcha whose token reaches a Livewire property: the provider's widget, and beside it a
     field of the kit's own that carries the caller's `wire:model` and `name`. The kit checks
     nothing itself; the server verifies the token with the provider. --}}
@props([
    // `turnstile` (Cloudflare) or `altcha`, a proof of work against a challenge the application
    // serves.
    'provider' => config('wirekit.components.captcha.provider', 'turnstile'),
    // Turnstile: the site key of the widget, from the Cloudflare dashboard.
    'siteKey' => null,
    // ALTCHA: the address that hands out a challenge, or the challenge itself as JSON.
    'challenge' => null,
    // The field the token travels in. Without a name, the property `wire:model` binds names it.
    'name' => null,
    'label' => null,
    'hint' => null,
    // Explained in a tooltip from a question mark beside the label, and read as the group's
    // description (partials/field-help).
    'help' => null,
    'error' => null,
    // The error stands in a polite live region, so a message that arrives after a Livewire round
    // trip is announced. Set false where the application announces its errors itself.
    'announceError' => null,
    'required' => false,
    // Turnstile's width: `normal`, `flexible` (the width of its column) or `compact`.
    'size' => 'normal',
    // The widget's language, such as `de`; null leaves it to the provider, which reads the browser.
    'language' => null,
    // Turnstile: a name for the check in Cloudflare's analytics.
    'action' => null,
    // Loads nothing from the provider until the reader uses the form around the captcha, or the
    // captcha itself outside a form. Null takes `components.captcha.defer`.
    'defer' => null,
    'scope' => null,
])

@aware(['announceErrors' => null, 'wkField' => null, 'wkFieldSet' => null])

@php
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\AttributeText;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\CallerModel;
    use Pushery\WireKit\Support\DomId;
    use Pushery\WireKit\Support\FieldControl;
    use Pushery\WireKit\Support\FieldError;
    use Pushery\WireKit\Support\FieldGroup;
    use Pushery\WireKit\Support\StrictnessGate;
    use Pushery\WireKit\Support\UtilityClasses;
    use Pushery\WireKit\WireKit;

    // `@aware` reads a value from the parent component and leaves its key in the attribute bag,
    // so a key also written on the tag would render as a stray attribute. Both spellings go.
    $attributes = $attributes->except(['announceErrors', 'announce-errors', 'wkField', 'wk-field', 'wkFieldSet', 'wk-field-set']);

    // Dev-only: flags an unknown prop in debug, silent in production.
    WireKit::warnUnknownProps('captcha', $attributes->getAttributes());

    // The prop first, then a form around the captcha, then the configuration.
    $announceError = BooleanProp::from($announceError ?? $announceErrors ?? config('wirekit.a11y.announce_error', true), true);
    $required = BooleanProp::from($required, false, 'captcha', 'required');
    $defer = BooleanProp::from($defer ?? config('wirekit.components.captcha.defer', false), false, 'captcha', 'defer');

    $provider = WireKit::validateProp('captcha', 'provider', is_string($provider) ? strtolower(trim($provider)) : '', ['turnstile', 'altcha']);
    $size = WireKit::validateProp('captcha', 'size', is_string($size) ? strtolower(trim($size)) : '', ['normal', 'flexible', 'compact']);

    $siteKey = is_string($siteKey) ? trim($siteKey) : '';
    $challenge = is_string($challenge) ? trim($challenge) : '';

    // Without its site key or its challenge a provider gives no token, so the gate names the one
    // that is missing.
    if ($provider === 'turnstile' && $siteKey === '') {
        StrictnessGate::reject('captcha', 'siteKey', '', 'the site key of a Turnstile widget', '');
    }

    if ($provider === 'altcha' && $challenge === '') {
        StrictnessGate::reject('captcha', 'challenge', '', 'the address of an ALTCHA challenge, or the challenge as JSON', '');
    }

    // The bag files the error under `name`, or under the property `wire:model` binds
    // (FieldError::nameOf()), and the field is sent under the same name by a plain form post.
    $rawName = is_string($name) && trim($name) !== '' ? trim($name) : null;
    $fieldName = FieldError::nameOf($rawName, $attributes) ?? 'captcha';
    $id = DomId::unique(is_string($attributes->get('id')) ? $attributes->get('id') : $fieldName, 'captcha-');

    // A field around the captcha with its error off turns it off here too (FieldControl::errorOf()),
    // and inside a field set an entry under the set's own key is the set's message, which the set
    // shows once under its legend (FieldGroup::entryMessage()).
    $error = FieldControl::errorOf($wkField instanceof FieldControl ? $wkField : null, $error);
    $errorMessage = FieldGroup::entryMessage($wkFieldSet, $errors ?? null, $fieldName, $error);
    $hasError = filled($errorMessage);
    $showsHint = ! $hasError && filled($hint);

    $hasLabel = filled($label);
    // A caller's name names the group, in place of the label's.
    $namedByCaller = filled(AttributeText::get($attributes, 'aria-label')) || $attributes->has('aria-labelledby');
    // Inside a labeled field and without a label of its own, the field's label names the group by
    // reference. It gets no `for`: the provider's widget is no element a label can name.
    $fieldLabelId = ! $hasLabel && ! $namedByCaller && $wkField instanceof FieldControl
        ? $wkField->takeLabel(null)
        : null;

    // The group's description: its own message, then the caller's, then a hint of a field around
    // it, then its help, which only stands beside a label.
    $helpId = filled($help) && $hasLabel ? $id.'-help' : null;
    $describedBy = trim(implode(' ', array_filter([
        $hasError ? $id.'-error' : ($showsHint ? $id.'-hint' : ''),
        trim((string) AttributeText::get($attributes, 'aria-describedby', '')),
    ], static fn (string $part): bool => $part !== '')));
    $describedBy = FieldControl::describe($wkField instanceof FieldControl ? $wkField : null, $describedBy, $id, $hint ? $id.'-hint' : null, $helpId);
    $describedBy = trim($describedBy.' '.($helpId ?? ''));

    // A caller's `x-model` binds the token's field, which stands inside the captcha's own Alpine
    // root, so it is read in the scope around the captcha (Support\CallerModel).
    $modelScope = CallerModel::binds($attributes);
    $attributes = $modelScope ? CallerModel::onField($attributes) : $attributes;

    $config = [
        'provider' => $provider,
        'script' => (string) ($provider === 'altcha'
            ? config('wirekit.components.captcha.altcha.script', '')
            : config('wirekit.components.captcha.turnstile.script', '')),
        'name' => $fieldName,
        'siteKey' => $siteKey,
        'challenge' => $challenge,
        'size' => $size,
        'language' => is_string($language) ? trim($language) : '',
        'action' => is_string($action) ? trim($action) : '',
        'defer' => $defer,
    ];

    $classes = WireKit::resolveClasses('captcha', 'base', 'flex min-w-0 flex-col gap-[var(--gap-wk-xs)] [overflow-wrap:break-word]', $scope);
@endphp
{{-- A group once something names it, described by its hint or error. The widget inside names
     itself: Turnstile's frame and ALTCHA's checkbox each carry their own label. A caller's role
     and name replace the group's own. --}}
<div
    data-wk-captcha
    id="{{ $id }}"
    @if($hasLabel || $fieldLabelId !== null || $namedByCaller) @unless($attributes->has('role')) role="group" @endunless @endif
    @if($hasLabel) @unless($namedByCaller) aria-labelledby="{{ $id }}-label" @endunless @elseif($fieldLabelId !== null) aria-labelledby="{{ $fieldLabelId }}" @endif
    @if($hasError) aria-invalid="true" @endif
    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    @if($modelScope) data-wk-ref-scope @endif
    {{ $attributes->except(['id', 'aria-describedby'])->whereDoesntStartWith(['wire:model', 'x-wk-model'])->class(UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>
    @if($hasLabel)
        <x-wirekit::label :help="$help" :help-id="$helpId" :help-field="$fieldName" id="{{ $id }}-label" :required="$required" :scope="$scope">{{ $label }}<x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after></x-wirekit::label>
    @endif
    {{-- `data-wk-captcha-defer` says the captcha waits for the reader before it loads anything
         from its provider, so a host page can tell it from one that loads with the page. --}}
    <div x-data="wirekitCaptcha({{ AlpinePayload::from($config) }})" data-wk-captcha-provider="{{ $provider }}" @if($defer) data-wk-captcha-defer @endif>
        {{-- The provider draws into this element, and a Livewire render leaves it alone. --}}
        <div wire:ignore data-wk-captcha-widget x-ref="widget"></div>
        {{-- The token's field, outside `wire:ignore`, so the server can empty it. It is empty until
             the provider's check in the browser gives a token, which the server never holds first. --}}
        <input type="hidden" name="{{ $fieldName }}" value="" data-wk-captcha-field x-ref="field" {{ $attributes->whereStartsWith(['wire:model', 'x-wk-model']) }}>
    </div>
    @wirekitLast($hasError)
        <p data-wk-prose-skip id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($showsHint)
        <p data-wk-prose-skip id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endwirekitLast
</div>
