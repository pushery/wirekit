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
 * constructor instead, and `@aware` hands it to every checkbox, radio and toggle in the slot.
 *
 * The set and all three controls ask the same two questions through it: is the group invalid,
 * and what describes this control. Answering them here keeps the key matching and the order of a
 * description in one place rather than in four templates that would drift apart.
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
    ) {}

    public static function open(string|Stringable|null $name, string|Stringable|null $error, string|Stringable|null $hint): self
    {
        $key = filled($name) ? (string) $name : null;
        $error = filled($error) ? $error : null;
        $hint = filled($hint) ? $hint : null;

        if ($key === null && $error === null && $hint === null) {
            return new self(null, null, null, null, null);
        }

        // Registered once the group can render something under an id. A key alone qualifies:
        // it renders nothing today and a message after the next failed submit, and a control
        // that points at the message needs the same id both times.
        $stem = $key === null ? '' : trim((string) preg_replace('/[^A-Za-z0-9_-]+/', '-', $key), '-');
        $base = DomId::unique($stem === '' ? null : $stem.'-group', 'field-set-');

        return new self($key, $error, $hint, $base.'-error', $base.'-hint');
    }

    /**
     * The message for the group, or null when there is none.
     *
     * An explicit message wins. Otherwise the key answers for itself and for every entry below
     * it, because Laravel files a rejected entry of an array field under its index: a checkbox
     * bound to `roles` fails as `roles.1`. A key written with a wildcard is taken as written.
     */
    public function message(?object $errors): string|Stringable|null
    {
        if ($this->error !== null) {
            return $this->error;
        }

        if ($this->key === null || ! ($errors instanceof ViewErrorBag || $errors instanceof MessageBag)) {
            return null;
        }

        $message = (string) $errors->first($this->key);

        if ($message === '' && ! str_contains($this->key, '*')) {
            $message = (string) $errors->first($this->key.'.*');
        }

        return $message === '' ? null : $message;
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

        $control = rtrim((string) preg_replace('/\[([^\]]*)\]/', '.$1', $controlName), '.');

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
     */
    public static function describedBy(?self $group, ?object $errors, ?string $ownErrorId, ?string $ownHintId, ?string $callerIds): ?string
    {
        $groupInvalid = $group !== null && $group->isInvalid($errors);

        $ids = array_filter([
            $ownErrorId,
            $groupInvalid ? $group->errorId : null,
            $ownErrorId === null ? $ownHintId : null,
            ! $groupInvalid && $group?->hint !== null ? $group->hintId : null,
            $callerIds,
        ], static fn (?string $id): bool => filled($id));

        return $ids === [] ? null : implode(' ', $ids);
    }
}
