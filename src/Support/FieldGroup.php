<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Stringable;

/**
 * What a `field.set` tells the controls inside it.
 *
 * The error-bag key the group answers for, the message it was handed, its hint, and the ids of
 * the two paragraphs it renders. A slot renders before the view of the component that holds it,
 * so a control cannot read an id that view computes; the set's class builds this object in its
 * constructor instead, and `@aware` hands it to every control in the slot.
 *
 * The set and the checkboxes, radios and toggles ask the same two questions through it: is the
 * group invalid, and what describes this control. Answering them here keeps the key matching and
 * the order of a description in one place rather than in four templates that would drift apart.
 * The controls with an entry of their own read their message through it ({@see entryMessage()}).
 *
 * Plumbing for the shipped views, not a documented API.
 */
final class FieldGroup
{
    private function __construct(
        public readonly ?string $key,
        public readonly string|Stringable|null $error,
        public readonly string|Stringable|null $hint,
        public readonly ?string $errorId,
        public readonly ?string $hintId,
        private readonly bool $readsBag = true,
        public readonly ?string $name = null,
    ) {}

    /**
     * An `error` of `false` turns the group's message off: the group reads nothing from the bag,
     * and it keeps its key, so the controls inside still leave the entries it answers for alone
     * rather than each printing one under itself (FieldError::isOff).
     *
     * The name may be written as a form writes it (`address[country]`, `roles[]`) or as the bag
     * files it (`address.country`). The key is kept in the bag's spelling (FieldError::key()), the
     * one each control inside is compared in, so a set named like its controls answers for them.
     * `name` keeps the name as written, for what the page shows of it: the `<fieldset>`'s own
     * `name` and the field a click on the set's help reports.
     */
    public static function open(string|Stringable|null $name, string|Stringable|false|null $error, string|Stringable|null $hint): self
    {
        $readsBag = ! FieldError::isOff($error);
        $written = filled($name) ? (string) $name : null;
        $key = $written === null ? null : FieldError::key($written);
        $error = $readsBag && $error !== false && filled($error) ? $error : null;
        $hint = filled($hint) ? $hint : null;

        if ($key === null && $error === null && $hint === null) {
            return new self(null, null, null, null, null, true, $written);
        }

        // Registered once the group can render something under an id. A key alone qualifies:
        // it renders nothing today and a message after the next failed submit, and a control
        // that points at the message needs the same id both times.
        $stem = $key === null ? '' : trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $key), '-');
        $base = DomId::unique($stem === '' ? null : $stem.'-group', 'field-set-');

        return new self($key, $error, $hint, $base.'-error', $base.'-hint', $readsBag, $written);
    }

    /**
     * The entries below the group's key that a control with an entry of its own shows under
     * itself ({@see entryMessage()}), which the group's message passes over.
     *
     * @var array<string, true>
     */
    private array $shownByTheirControl = [];

    /**
     * The message for the group, or null when there is none.
     *
     * An explicit message wins. Otherwise the key answers for itself and for every entry below
     * it, because Laravel files a rejected entry of an array field under its index: a checkbox
     * bound to `roles` fails as `roles.1`. An entry that a text field, a select or a picker in the
     * set shows under itself is that control's and not the group's. A key written with a wildcard
     * is taken as written. A group whose error is off answers nothing.
     */
    public function message(?object $errors): string|Stringable|null
    {
        if ($this->error !== null) {
            return $this->error;
        }

        if (! $this->readsBag || $this->key === null || ! ($errors instanceof ViewErrorBag || $errors instanceof MessageBag)) {
            return null;
        }

        $message = (string) $errors->first($this->key);

        if ($message === '' && ! str_contains($this->key, '*')) {
            foreach ($errors->get($this->key.'.*') as $entry => $messages) {
                if (! isset($this->shownByTheirControl[(string) $entry]) && is_array($messages) && $messages !== []) {
                    $message = (string) reset($messages);

                    break;
                }
            }
        }

        return $message === '' ? null : $message;
    }

    /**
     * The message a control with an entry of its own shows inside the set, in place of
     * FieldError::message().
     *
     * A checkbox, a radio or a toggle chooses a value of the group, and the group answers for
     * every entry under its key ({@see covers()}). A text field, a select or a picker holds an
     * entry of its own. Its entry under the group's own key is the group's message, which the set
     * shows once, under its legend, and the control does not repeat; the `<fieldset>` points at
     * it, as it does for a group of anything else. An entry below the key (`emails.1`) is the
     * control's, which shows it, and the group no longer takes it for its own message: the slot
     * renders before the set's view, so the group knows every such entry by the time it reads one.
     * A message the caller gives the control is the control's alone.
     *
     * `$group` is what `@aware` hands the control's view under `wkFieldSet`: this set, null outside
     * one, or whatever a component around the caller's carries under that name. So it is `mixed`,
     * and anything but a set is read as no set rather than ending the render.
     *
     * @param  iterable<array-key, mixed>|string|Stringable|false|null  $error
     * @param  list<string>  $parts
     */
    public static function entryMessage(mixed $group, ?object $errors, ?string $name, iterable|string|Stringable|false|null $error = null, array $parts = []): string|Stringable|null
    {
        $message = FieldError::message($errors, $name, $error, $parts);
        $given = FieldError::one($error);

        if (! $group instanceof self || $group->key === null || $name === null || $name === '' || ($given !== null && $given !== false && (string) $given !== '')) {
            return $message;
        }

        $control = (string) FieldError::key($name);

        if (str_contains($group->key, '*') ? Str::is($group->key, $control) : $control === $group->key) {
            return null;
        }

        if ($message !== null && ! str_contains($group->key, '*') && Str::is($group->key.'.*', $control)) {
            $group->shownByTheirControl[$control] = true;

            foreach ($parts as $part) {
                $group->shownByTheirControl[$control.'.'.$part] = true;
            }
        }

        return $message;
    }

    public function isInvalid(?object $errors): bool
    {
        return $this->message($errors) !== null;
    }

    /**
     * Whether a control's own bag entry is this group's message.
     *
     * A control would otherwise render that entry under itself, and a group of radios sharing the
     * key repeats it once per radio. The form spelling of an array (`roles[]`, `roles[0]`) is
     * compared in the bag's spelling (`roles`, `roles.0`).
     */
    public function covers(?string $controlName): bool
    {
        if ($this->key === null || $controlName === null || $controlName === '') {
            return false;
        }

        // The bag key the control's errors arrive under, the same one the control itself reads.
        $control = (string) FieldError::key($controlName);

        if (str_contains($this->key, '*')) {
            return Str::is($this->key, $control);
        }

        return $control === $this->key || Str::is($this->key.'.*', $control);
    }

    /**
     * One `aria-describedby` value for a control, inside a group or not.
     *
     * Its own error, the group's error, its own hint, the group's hint, then what the caller
     * wrote. Errors come before hints because they are what the reader has to act on, and the
     * control's own before the group's because it is the more specific. A hint is left out while
     * an error renders in its place, since only one of the two is on the page. One attribute:
     * written twice, the parser keeps the first and the other description is lost.
     *
     * `$takesGroupError` is a control declining the group's message — a checkbox that belongs to
     * the group, does not cause the rejection and cannot resolve it. It drops the reference to
     * the group error and nothing else: the control keeps its own error, its own hint, and the
     * group HINT, which is a different sentence and is on the page whenever the group is valid.
     * It is deliberately not expressed as "pass null for the group": that would also stop
     * {@see covers()} from suppressing the group's bag entry, and the control would print the
     * group's message under itself — the confusion this is here to prevent, one step over.
     */
    public static function describedBy(?self $group, ?object $errors, ?string $ownErrorId, ?string $ownHintId, ?string $callerIds, bool $takesGroupError = true): ?string
    {
        $groupInvalid = $group !== null && $group->isInvalid($errors);

        $ids = array_filter([
            $ownErrorId,
            $groupInvalid && $takesGroupError ? $group->errorId : null,
            $ownErrorId === null ? $ownHintId : null,
            ! $groupInvalid && $group?->hint !== null ? $group->hintId : null,
            $callerIds,
        ], static fn (?string $id): bool => filled($id));

        return $ids === [] ? null : implode(' ', $ids);
    }

    /**
     * Whether a set's rendered default slot holds a `<legend>` of its own.
     *
     * The slot renders inside the set's spacing `<div>`, so a legend written there is a
     * grandchild of the `<fieldset>` and not its caption. A set nested in the slot is different:
     * its legend is the first child of its own `<fieldset>`, which is right. Only a legend outside
     * every fieldset of the slot counts, read by tag depth in one pass from left to right.
     *
     * Comments go first. A legend inside one renders nothing, and Livewire's morph markers around
     * a caller's `@if` are comments too.
     */
    public static function hasLooseLegend(string $html): bool
    {
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? $html;
        $depth = 0;
        $offset = 0;

        while (preg_match('~<(/?)(fieldset|legend)\b~i', $html, $tag, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $offset = $tag[0][1] + strlen($tag[0][0]);
            $closing = $tag[1][0] === '/';

            if (strtolower($tag[2][0]) === 'fieldset') {
                $depth = max(0, $depth + ($closing ? -1 : 1));
            } elseif (! $closing && $depth === 0) {
                return true;
            }
        }

        return false;
    }
}
