<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use BackedEnum;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Support\Stringable;
use Illuminate\View\ComponentAttributeBag;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use Traversable;
use UnitEnum;

/**
 * A prop a caller wrote on a component's tag, read as the text it stands for.
 *
 * Blade escapes a value echoed into a component tag before the component receives it:
 * `label="{{ $name }}"` arrives as `Tom&#039;s &amp; Jerry`. So does every value a wrapper passes
 * on through its attribute bag, which holds each value escaped, even one its own caller bound. A
 * bound value, `:label="$name"`, arrives as written. A view that writes the first with `{{ }}` or
 * hands it to a script's configuration escapes it a second time: the reader sees the references,
 * a screen reader reads them out, and a form submits them as the value. Read here, every way of
 * writing the prop arrives as the same text, which the view escapes once.
 *
 * Only the five characters Blade's escape writes are restored, as in UrlProp, in any spelling PHP's
 * decoder reads: `&amp;`, `&lt;`, `&gt;`, `&quot;` and `&#039;`, and their numeric forms such as
 * `&#34;` or `&#x26;`. A reference to any other character, `&#106;` or `&mdash;`, stays text, so a
 * URL prop keeps the scheme it was read with. The price is a bound value that holds one of the five
 * as literal text, in any of those spellings: it reads as the character it names.
 *
 * @internal Called from the view composer the service provider registers and from the class
 *           components; not a developer-facing API.
 */
final class PropText
{
    /**
     * Props whose value is HTML the view writes as it is. Undoing Blade's escape on a bound value
     * would turn an escaped `&lt;script&gt;` in its text into a tag, so these are read only where
     * Blade escaped them on the way in, which is when the value equals the bag's copy of it.
     *
     * @var array<string, list<string>>
     */
    private const HTML = [
        'editor' => ['value'],
    ];

    /**
     * Props that are text in every component that declares one: a label, a help, a heading, the
     * language of a text. A boolean given to one reads as none, as `null` does, and so does a string
     * of nothing but whitespace. A condition at the call site writes a boolean,
     * `:help="$isNew && __('Optional')"`, and a record writes an empty string, `:value-text="$row->label"`.
     * A view that asks `filled()`, which is true for `false`, or `??`, which keeps an empty string,
     * would draw the element around no text: an empty heading, a help button with nothing to say, a
     * landmark or a button with an empty name, or no price where the label stood in for it. Read as
     * none, the component writes its own text where it has one. `legend` is not here, since it is
     * a switch on a status matrix and a status tile, and neither is `tooltip`, a switch on a slider
     * and a status dot. A stream's `eventName` is the name of the event it listens for, not a text.
     *
     * @var list<string>
     */
    public const TEXT = [
        'label', 'help', 'hint', 'title', 'description', 'heading', 'eyebrow', 'tagline', 'caption',
        'placeholder', 'emptyText', 'attribution', 'author', 'discardQuestion', 'ariaLabel', 'unit',
        'listLabel', 'weekLabel', 'bulkActionsLabel', 'clearLabel', 'onLabel', 'offLabel', 'trendLabel', 'tableLabel', 'badgeLabel',
        'decreaseLabel', 'increaseLabel', 'addLabel', 'removeLabel', 'kindLabel', 'unlimitedLabel', 'priceLabel', 'totalLabel',
        'remainingLabel', 'beforeLabel', 'afterLabel', 'featuredLabel', 'seeAllLabel', 'intervalLabel', 'dismissLabel',
        'moreLabel', 'menuLabel', 'stripLabel', 'valueText', 'copiedText', 'expiredText', 'initialText',
        'unknownMessage', 'startMessage', 'readyMessage', 'stoppedMessage', 'failedMessage',
        'altLang', 'captionLang', 'titleLang', 'triggerLang', 'nameLang',
    ];

    /**
     * Text props where an empty string is a choice rather than no text: `placeholder=""` means no
     * placeholder, as it does in HTML, and switches off a component's own. A boolean on one still
     * reads as none.
     *
     * @var list<string>
     */
    public const BLANK_KEPT = ['placeholder'];

    /**
     * Text props that take a list as well, as their pages say: the author of a message, a record
     * with a name and a picture, and the sentences of a rail item's badge. A list given to one of
     * these is read by its component.
     *
     * @var array<string, list<string>>
     */
    private const LISTS_KEPT = [
        'message' => ['author'],
        'message-group' => ['author'],
        'app-rail.item' => ['badgeLabel'],
    ];

    /**
     * Props that are text on one component although the name is something else elsewhere: the
     * legend of a field set, which is a switch on a status matrix.
     *
     * @var array<string, list<string>>
     */
    private const TEXT_ON = [
        'field.set' => ['legend'],
    ];

    /**
     * Props that take one value and never a list, in every component that declares one, and that
     * are neither a text nor a word from a fixed list: the `scope` every component takes, an
     * address, an id, a key, a time zone. A list or something iterated as one given to one of
     * them is reported, and the prop reads as not given. An object passes, unlike on a text or a
     * word prop: a time zone takes a `DateTimeZone`, a moment a date, an icon a slot.
     *
     * @var list<string>
     */
    public const SINGLE = [
        'accessibleLabel', 'animateIn', 'change', 'citation', 'cite', 'column', 'darkLogo', 'datetime',
        'dismissKey', 'displayValue', 'entryMaxWidth', 'filename', 'follow', 'headingId', 'imageAlt',
        'language', 'leadingIcon', 'loadingTarget', 'mobileLogo', 'nonce', 'now', 'offIcon', 'onIcon',
        'prefix', 'primary', 'quantityName', 'realtimeEvent', 'replyAuthor', 'replyDate', 'resetAt',
        'sans', 'schemaAnswer', 'scope', 'search', 'seeAllHref', 'serif', 'shippingNote', 'sizes',
        'sortKey', 'srcset', 'styleUrl', 'suffix', 'template', 'thumbnail', 'timezone', 'trailingIcon',
    ];

    /**
     * Props that take one value on these components, though the same name takes something else
     * on another: a list of keys in the `shortcut` of a menubar item, a map of variants in the
     * `logo` of a sign-in button, a record in the `avatar` of a profile, a list that a calendar's
     * `min` reports on its own terms. A list given to one of these is reported as on the props of
     * SINGLE, and an object passes.
     *
     * @var array<string, list<string>>
     */
    public const SINGLE_ON = [
        'app-rail.brand' => ['href'],
        'app-rail.item' => ['badge'],
        'assistant-message' => ['avatar', 'name'],
        'attachment' => ['href', 'icon'],
        'avatar' => ['alt', 'src'],
        'badge' => ['tooltip'],
        'bottom-nav.item' => ['badge'],
        'brand' => ['logo'],
        'button' => ['href'],
        'calendar' => ['locale'],
        'card' => ['href', 'name', 'open'],
        'cart-item' => ['locale'],
        'color-picker' => ['id'],
        'combobox' => ['id'],
        'command-palette.item' => ['href', 'icon', 'id'],
        'context-menu.item' => ['href'],
        'context-menu.submenu' => ['icon'],
        'countdown' => ['locale'],
        'data-table' => ['name'],
        'date-separator' => ['format'],
        'datetime-picker' => ['locale'],
        'drawer.trigger' => ['intent', 'size', 'surface'],
        'dropdown' => ['name'],
        'dropdown.item' => ['badge', 'icon'],
        'dropdown.submenu' => ['icon'],
        'editor' => ['id'],
        'event-calendar' => ['locale'],
        'fab.action' => ['href'],
        'fab.button' => ['href'],
        'faq-item' => ['id'],
        'feature' => ['icon'],
        'filter-builder' => ['name'],
        'flag' => ['alt', 'src'],
        'fonts' => ['mono'],
        'grid' => ['min'],
        'image-compare' => ['name'],
        'image-editor' => ['alt', 'src'],
        'image-hotspots' => ['alt', 'icon', 'phoneSrc', 'phoneSrcset', 'src'],
        'image-hotspots-editor' => ['alt', 'phoneSrc', 'src'],
        'video' => ['consent', 'poster', 'src'],
        'input' => ['autocomplete'],
        'main' => ['id'],
        'menubar.item' => ['href'],
        'menubar.submenu' => ['icon'],
        'message' => ['timeFormat'],
        'modal' => ['describedby'],
        'modal.trigger' => ['size'],
        'navigation-menu.item' => ['href'],
        'notification-center' => ['name'],
        'pricing-table' => ['interval'],
        'reading-progress' => ['target'],
        'scope-switcher' => ['placement', 'searchPlaceholder'],
        'shortcuts.trigger' => ['size'],
        'sidebar.collapsible' => ['icon'],
        'sidebar.item' => ['badge', 'icon'],
        'slider' => ['id'],
        'stage-card' => ['count'],
        'stat' => ['href', 'icon'],
        'status-matrix' => ['name'],
        'swatch' => ['image'],
        'tabs.tab' => ['icon'],
        'team-member' => ['avatar', 'initials'],
        'ticker' => ['delta'],
        'time-picker' => ['max', 'min', 'step'],
        'timeline.item' => ['icon'],
        'toggle-button' => ['icon', 'tooltip'],
        'translatable-input' => ['name'],
        'tree-view.node' => ['icon'],
        'unsaved-hint' => ['question', 'text'],
    ];

    /** What a report says a text prop expected. */
    private const EXPECTED = 'a text, not a list or an object';

    /** What a report says a prop expected that takes one value from a fixed list. */
    private const EXPECTED_WORD = 'one of its values, not a list or an object';

    /**
     * The word props of each component, read once per process from resources/catalog/word-props.php.
     *
     * @var array<string, list<string>>|null
     */
    private static ?array $words = null;

    /** What a report says a parameter of a class component expected that takes no list. */
    private const EXPECTED_VALUE = 'one value, not a list or an object';

    /** What a report says a prop expected that takes one value, an object included. */
    private const EXPECTED_SINGLE = 'one value, not a list';

    /**
     * The constructor parameters of each class component, read once per process: the names of the
     * types each takes, null for an untyped one, and whether it has a default.
     *
     * @var array<class-string, array<string, array{types: list<string>|null, optional: bool}>>
     */
    private static array $parameters = [];

    /**
     * Read every prop the caller wrote on the tag of the component this view renders, in place,
     * before the view runs. A partial a component includes is left alone: what reaches it was
     * read by the component that includes it.
     *
     * A fluent string, what `str()` and `Str::of()` return, is read as its text as well. Blade's
     * `{{ }}` writes one as the string it holds, and the views read a text prop as a string: as an
     * object it would be left out where a view asks `is_string()`, written as a JSON string into a
     * document, or used as an array key. An `HtmlString` stays as it is, since it is markup.
     *
     * An enum is read as the value of a field is (Support\FieldValue): a backed enum as its value,
     * which is how `e()` writes one, and a pure one, which `e()` cannot write, as its name. A model
     * attribute cast to an enum hands one over, and the views compare a prop with strings and write
     * it as text, where an enum does not cast.
     *
     * A list, something iterated as one, or an object without a text of its own given to a text
     * prop cannot be written as text, and a view that writes it ends the page with a type error. It
     * is reported through the strictness gate, and the prop reads as none, as a boolean does. A
     * prop that takes a list as well keeps one.
     *
     * The same value given to a prop that takes one value from a fixed list, a size or a variant
     * (isWord()), cannot be one of its values, and a view that compares it or looks it up ends the
     * page the same way. It is reported as well, and the prop reads as not given, so the component
     * takes the default its `@props` declares.
     */
    public static function normalize(View $view): void
    {
        $component = UnboundModel::componentOf($view->name());

        if ($component === null || str_starts_with($component, 'partials.')) {
            return;
        }

        $data = $view->getData();
        $escaped = [];
        $fluent = [];
        $none = [];
        $lists = [];

        // Only a value with an ampersand can hold one of the references, and only an object can be
        // a fluent string or an enum, so most renders stop here.
        foreach ($data as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $shown = is_array($value) || is_object($value) ? self::unwritable($component, $key, $value) : null;

            if (self::isNoText($key, $value)) {
                $none[] = $key;
            } elseif ($shown !== null) {
                $lists[$key] = $shown;
            } elseif ($value instanceof Stringable) {
                $fluent[$key] = $value->toString();
            } elseif ($value instanceof UnitEnum) {
                $fluent[$key] = self::enum($value);
            } elseif (is_string($value) && str_contains($value, '&')) {
                $escaped[$key] = $value;
            }
        }

        $attributes = $data['attributes'] ?? null;

        // A component with a class behind it holds the class's properties as its data, and every
        // other attribute its caller wrote stays in the bag alone, where the view writes it or
        // reads it as its name. A text given there as a list is reported and dropped, as it is
        // from an anonymous component, whose data carries a copy of every attribute.
        $bagLists = [];

        if ($attributes instanceof ComponentAttributeBag) {
            foreach ($attributes->getAttributes() as $name => $value) {
                $key = Str::camel((string) $name);

                if ((is_array($value) || is_object($value)) && ! array_key_exists($key, $data)) {
                    $shown = self::unwritable($component, $key, $value);

                    if ($shown !== null) {
                        $bagLists[(string) $name] = [$key, $shown];
                    }
                }
            }
        }

        if (($escaped === [] && $fluent === [] && $none === [] && $lists === [] && $bagLists === []) || ! $attributes instanceof ComponentAttributeBag) {
            return;
        }

        foreach ($bagLists as [$key, $shown]) {
            StrictnessGate::reject($component, $key, $shown, self::expected($component, $key), '');
        }

        if ($bagLists !== []) {
            $attributes->setAttributes(array_diff_key($attributes->getAttributes(), $bagLists));
        }

        // Blade hands an anonymous component every attribute twice: in the bag under the name it
        // was written with, and as data under that name in camel case, which `@props` then keeps
        // for a prop. A value the caller did not write on this tag has no copy in the bag.
        $written = [];

        foreach (array_keys($attributes->getAttributes()) as $name) {
            $written[Str::camel((string) $name)] = (string) $name;
        }

        // A fluent string or an enum was bound in PHP, so Blade's escape never reached it: its text
        // is the prop.
        foreach ($fluent as $key => $value) {
            if (isset($written[$key])) {
                $view->with($key, $value);
            }
        }

        // A list or an object without a text cannot be written as one: it is reported, and the
        // prop is not given, as a boolean is not.
        foreach ($lists as $key => $shown) {
            if (isset($written[$key])) {
                StrictnessGate::reject($component, $key, $shown, self::expected($component, $key), '');
                $none[] = $key;
            }
        }

        // A boolean or a blank string is no text, so a text prop that holds one is not given.
        // `@props` takes a prop from the bag wherever the data holds null, so the copy in the bag
        // goes as well.
        $given = array_values(array_filter($none, static fn (string $key): bool => isset($written[$key])));

        if ($given !== []) {
            foreach ($given as $key) {
                $view->with($key, null);
            }

            $attributes->setAttributes(array_diff_key($attributes->getAttributes(), array_flip(array_map(static fn (string $key): string => $written[$key], $given))));
        }

        $html = self::HTML[$component] ?? [];

        foreach ($escaped as $key => $value) {
            if (! isset($written[$key])) {
                continue;
            }

            if (in_array($key, $html, true) && $attributes->get($written[$key]) !== $value) {
                continue;
            }

            $view->with($key, self::text($value));
        }
    }

    /**
     * The values a class component's constructor takes, read as `normalize()` reads the props of
     * the other components: every enum as its value or name, since the parameters are typed for
     * text and an enum there is an error before the component runs, and a boolean or a blank
     * string on a text prop as none, since a parameter typed for text and numbers takes `false` as
     * `0`. Given the component's name, a list or an object without a text on a text prop is
     * reported and read as none as well, since the parameter's type refuses it before the
     * component runs. On a word prop it is reported and left out, so the parameter takes its own
     * default. Given the class as well, a list or an object on any other parameter whose type
     * refuses it is reported and left out the same way, a switch or a number given a list.
     *
     * @param  array<array-key, mixed>  $data
     * @param  class-string|null  $class  the component's class, whose constructor takes the values
     * @return array<array-key, mixed>
     */
    public static function forConstructor(array $data, ?string $component = null, ?string $class = null): array
    {
        // A tag carries whatever the caller wrote or bound, a string, a list, a model or a closure,
        // and everything else goes to the constructor as it came, so the reader is `mixed` on both
        // sides, as text() is.
        $values = [];

        foreach ($data as $key => $value) {
            if ($value instanceof UnitEnum) {
                $values[$key] = self::enum($value);

                continue;
            }

            if (is_string($key)) {
                $prop = Str::camel($key);
                $shown = $component !== null && (is_array($value) || is_object($value)) ? self::unwritable($component, $prop, $value) : null;

                if ($component !== null && $shown !== null) {
                    StrictnessGate::reject($component, $prop, $shown, self::expected($component, $prop), '');
                }

                // A word prop is left out rather than set to null: its parameter may take no null,
                // and left out it takes its own default, as `@props` gives one to the other views.
                if ($shown !== null && $component !== null && (self::isWord($component, $prop) || self::isSingle($component, $prop))) {
                    continue;
                }

                if ($shown !== null || self::isNoText($prop, $value)) {
                    $values[$key] = null;

                    continue;
                }

                // A list or an object the parameter's type refuses ends the component before it
                // runs: reported, and left out for the parameter's default.
                if ($component !== null && $class !== null && (is_array($value) || is_object($value)) && ! self::parameterTakes($class, $prop, $value)) {
                    StrictnessGate::reject($component, $prop, is_array($value) ? 'array' : class_basename($value), self::EXPECTED_VALUE, '');

                    continue;
                }
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * The value with Blade's escape undone.
     *
     * A value that is not a string comes back as it is: a slot or an `HtmlString` is HTML the
     * caller means as written, and a number, an array or an object never passed through Blade's
     * escape. A prop is whatever the caller wrote or bound, which is why the parameter and the
     * return are `mixed`.
     */
    public static function text(mixed $value): mixed
    {
        // Every one of the five references begins with an ampersand.
        return is_string($value) && str_contains($value, '&')
            ? htmlspecialchars_decode($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401)
            : $value;
    }

    /**
     * Whether a value given to a text prop is no text: a boolean, or a string, plain or fluent, of
     * nothing but whitespace, except on a prop where an empty string is a choice. The value is
     * whatever the tag carried, as in text(), so it is `mixed`.
     */
    private static function isNoText(string $key, mixed $value): bool
    {
        if (! in_array($key, self::TEXT, true)) {
            return false;
        }

        if (is_bool($value)) {
            return true;
        }

        $text = $value instanceof Stringable ? $value->toString() : $value;

        return is_string($text) && trim($text) === '' && ! in_array($key, self::BLANK_KEPT, true);
    }

    /**
     * What a report shows for a value a text prop cannot write as text, or a word prop cannot hold
     * as one of its values, or null when it can: a list, something iterated as one, or an object
     * without a text of its own, such as a record read without its name. An enum is read as its
     * value elsewhere, and a text prop that takes a list as well keeps a list, though not another
     * object. The value is whatever the tag carried, as in text(), so it is `mixed`.
     */
    private static function unwritable(string $component, string $key, mixed $value): ?string
    {
        $text = in_array($key, self::TEXT, true) || in_array($key, self::TEXT_ON[$component] ?? [], true);
        $word = ! $text && self::isWord($component, $key);

        if (! $text && ! $word && ! self::isSingle($component, $key)) {
            return null;
        }

        $takesLists = $text && in_array($key, self::LISTS_KEPT[$component] ?? [], true);

        if (is_array($value)) {
            return $takesLists ? null : 'array';
        }

        if ($value instanceof Traversable) {
            return $takesLists ? null : class_basename($value);
        }

        // A prop that takes one value takes an object, a date or a time zone among them.
        if (! $text && ! $word) {
            return null;
        }

        // An object writes itself as text when it has a `__toString()`, as a date or a model does.
        if (is_object($value) && ! method_exists($value, '__toString') && ! $value instanceof Htmlable && ! $value instanceof UnitEnum) {
            return class_basename($value);
        }

        return null;
    }

    /**
     * Whether a prop of a component takes one value from a fixed list: a size, a variant, an intent.
     * None of them takes a list, so one given to them is reported and the prop reads as not given,
     * and the component takes its default. Which props they are is data, resources/catalog/
     * word-props.php, written from the components' `@props`: per component, since a name that is a
     * word on one component is a date or a number on another.
     */
    private static function isWord(string $component, string $key): bool
    {
        if (self::$words === null) {
            /** @var array<string, list<string>> $entries */
            $entries = require \dirname(__DIR__, 2).'/resources/catalog/word-props.php';
            self::$words = $entries;
        }

        return in_array($key, self::$words[$component] ?? [], true);
    }

    /** What a report says the prop expected: a text, one of the values of a word prop, or one value. */
    private static function expected(string $component, string $key): string
    {
        if (in_array($key, self::TEXT, true) || in_array($key, self::TEXT_ON[$component] ?? [], true)) {
            return self::EXPECTED;
        }

        if (self::isWord($component, $key)) {
            return self::EXPECTED_WORD;
        }

        return self::isSingle($component, $key) ? self::EXPECTED_SINGLE : self::EXPECTED;
    }

    /** Whether a prop of a component takes one value and never a list, an object included. */
    private static function isSingle(string $component, string $key): bool
    {
        return in_array($key, self::SINGLE, true) || in_array($key, self::SINGLE_ON[$component] ?? [], true);
    }

    /**
     * Whether a class component's constructor takes a list or an object under this name. A name
     * with no parameter goes to the attribute bag, and an untyped parameter or one without a
     * default is left to the constructor, which decides what it accepts: only a parameter that
     * has a default to fall back to is spared the value.
     *
     * @param  class-string  $class
     * @param  array<array-key, mixed>|object  $value  whatever the tag carried
     */
    private static function parameterTakes(string $class, string $prop, array|object $value): bool
    {
        if (! isset(self::$parameters[$class])) {
            $parameters = [];

            foreach ((new ReflectionClass($class))->getConstructor()?->getParameters() ?? [] as $parameter) {
                $type = $parameter->getType();

                $parameters[$parameter->getName()] = [
                    'types' => match (true) {
                        $type instanceof ReflectionNamedType => [$type->getName()],
                        // A part of a union that is itself an intersection takes what it takes: read as any.
                        $type instanceof ReflectionUnionType => array_map(static fn (ReflectionType $part): string => $part instanceof ReflectionNamedType ? $part->getName() : 'mixed', $type->getTypes()),
                        default => null,
                    },
                    'optional' => $parameter->isDefaultValueAvailable(),
                ];
            }

            self::$parameters[$class] = $parameters;
        }

        $parameter = self::$parameters[$class][$prop] ?? null;

        if ($parameter === null || $parameter['types'] === null || ! $parameter['optional']) {
            return true;
        }

        foreach ($parameter['types'] as $type) {
            $takes = match ($type) {
                'mixed' => true,
                'array' => is_array($value),
                'iterable' => is_iterable($value),
                'object' => is_object($value),
                'callable' => is_callable($value),
                // A text parameter takes an object that writes itself as text.
                'string' => is_object($value) && method_exists($value, '__toString'),
                default => is_object($value) && $value instanceof $type,
            };

            if ($takes) {
                return true;
            }
        }

        return false;
    }

    /** An enum as text: a backed one as its value, a pure one as its name. */
    private static function enum(UnitEnum $enum): string|int
    {
        return $enum instanceof BackedEnum ? $enum->value : $enum->name;
    }
}
