<?php

declare(strict_types=1);

namespace Pushery\WireKit\Support;

use Illuminate\Contracts\Support\Htmlable;
use Stringable;

/**
 * What a `field` tells the control inside it.
 *
 * A field labels its control by `for`, and the id it points at is its `name`, the id a plain input
 * takes, with each run of whitespace a hyphen. A composite control gives its own field an id of its own (`wk-date-…`, `…-input`), and
 * without a label of its own it names itself: with a placeholder sentence, a hidden label made
 * from its name, or not at all. Inside a labeled field that leaves the visible label naming
 * nothing, a click on it focusing nothing, and the control announced by its fallback.
 *
 * A slot renders before the view of the component that holds it, so the field cannot read the
 * control's id. The class behind `field` builds this object in its constructor, and `@aware`
 * hands it to the control. The control takes the label here, records the element a label can
 * name, and leaves its fallback name out; the field's view, which renders after the slot, points
 * its label at what was recorded.
 *
 * Plumbing for the shipped views, not a documented API.
 */
final class FieldControl
{
    private bool $taken = false;

    private ?string $controlId = null;

    private ?string $labelId = null;

    /** The id a control that keeps its own name reported, see follow(). */
    private ?string $followed = null;

    /** The id the field's hint paragraph carries, once a control linked it, see hintId(). */
    private ?string $hintId = null;

    /** The id the hidden copy of the field's help carries, once a control linked it, see helpId(). */
    private ?string $helpId = null;

    private function __construct(
        private readonly ?string $target,
        private readonly bool $offers,
        private readonly string $labelText,
        private readonly bool $hint = false,
        private readonly bool $ownError = false,
        private readonly bool $required = false,
        private readonly bool $help = false,
        private readonly bool $errorOff = false,
    ) {}

    /**
     * @param  bool  $hint  whether the field shows a hint below its control
     * @param  bool  $ownError  whether the field has an error of its own, which takes the hint's place
     * @param  bool  $required  whether the field is marked required, see requiredBy()
     * @param  bool  $help  whether the field has a help, see helpId()
     * @param  bool  $errorOff  whether the field's error is turned off, see errorOf()
     */
    public static function open(string|Stringable|null $for, string|Stringable|null $name, Htmlable|string|int|float|null $label, bool $hint = false, bool $ownError = false, bool $required = false, bool $help = false, bool $errorOff = false): self
    {
        $for = filled($for) ? (string) $for : null;
        // An id holds no whitespace (DomId::valid()), and a list of ids splits on it: the name
        // `first name` is the id `first-name`, the one a plain input takes, so the hint the field
        // hands its control as `first-name-hint` is the one its paragraph carries.
        $target = DomId::valid($for ?? (filled($name) ? (string) $name : null));
        // Text, for the names a control builds from it; an Htmlable label is read as markup.
        $text = SlotContent::textOf($label);

        // A field offers its label when it has one, and when it chose its target itself: a caller
        // who points `for` somewhere has wired the label by hand, and that wiring stays theirs.
        // The help's hidden copy stands beside the label, so a field without a label has none to link.
        return new self($target, $text !== '' && $for === null, $text, $hint, $ownError, $required, $help && $text !== '', $errorOff);
    }

    /**
     * Whether a control takes `aria-required` from the field around it.
     *
     * A required field marks its label with an asterisk hidden from assistive technology, so the
     * control has to say it. It says it with `aria-required` and never the native attribute, which
     * would turn on the browser's own check and stop a submit the page let through until now. A
     * control required by its own `required` already says it, and takes nothing.
     */
    public static function requiredBy(?self $field, bool $ownRequired): bool
    {
        return ! $ownRequired && $field !== null && $field->required;
    }

    /**
     * The `error` a control goes by inside the field around it.
     *
     * A field and its control read the bag for the same failure: the field to show the message
     * below, the control to show it under itself and mark itself invalid. A field whose error is
     * off (FieldError::isOff) leaves the bag alone for the control as well, as an `:error="false"`
     * of the control's own would, whatever name the control reads it under. A message the control
     * was handed itself still shows, since it comes from the caller and not from the bag.
     *
     * @param  iterable<array-key, mixed>|string|Stringable|false|null  $error
     * @return iterable<array-key, mixed>|string|Stringable|false|null
     */
    public static function errorOf(?self $field, iterable|string|Stringable|false|null $error): iterable|string|Stringable|false|null
    {
        if ($field === null || ! $field->errorOff) {
            return $error;
        }

        // Read as FieldError::message() reads it: a list by its first message (FieldError::one()),
        // and what would fall through to the bag there is what the field turns off here.
        $message = FieldError::one($error);

        return $message === null || $message === false || (string) $message === '' || FieldError::isOff($message) ? false : $error;
    }

    /**
     * The id of the field's hint, for the `aria-describedby` of the control inside it.
     *
     * Null when the field shows no hint: it has none, or its own error takes the hint's place. A
     * hint the field shows is linked whatever the control shows, an error of the control's own
     * included, since the field renders it below the control either way.
     *
     * The hint takes `{target}-hint`, the id the field offers for markup of the caller's own. A
     * field with no target names it after the control, the way the control names its own parts. A
     * control whose own hint already carries that id moves the field's to `…-field-hint`, so no id
     * is written twice. The first control to ask decides.
     *
     * @param  string  $controlId  the id the control builds its own ids from
     * @param  string|null  $ownHintId  the id of the control's own hint, when it has one
     */
    public function hintId(string $controlId, ?string $ownHintId): ?string
    {
        $base = $this->target ?? $controlId;

        if (! $this->hint || $this->ownError || $base === '') {
            return null;
        }

        return $this->hintId ??= $ownHintId === $base.'-hint' ? $base.'-field-hint' : $base.'-hint';
    }

    /** The id the field's hint paragraph carries: the one a control linked, else the one it offers. */
    public function hintParagraphId(): ?string
    {
        return $this->hintId ?? ($this->target === null ? null : $this->target.'-hint');
    }

    /**
     * The id of the hidden copy of the field's help, for the `aria-describedby` of the control.
     *
     * The help is explained in a tooltip beside the label, and its hidden copy describes the control
     * as a control's own help does. Null when the field has no help or no label to show it beside.
     * The ids follow hintId(): `{target}-help`, `…-field-help` beside a control's own help of that
     * id, the control's id as the base when the field has no target, and the first control decides.
     *
     * @param  string  $controlId  the id the control builds its own ids from
     * @param  string|null  $ownHelpId  the id of the hidden copy of the control's own help, when it has one
     */
    public function helpId(string $controlId, ?string $ownHelpId): ?string
    {
        $base = $this->target ?? $controlId;

        if (! $this->help || $base === '') {
            return null;
        }

        return $this->helpId ??= $ownHelpId === $base.'-help' ? $base.'-field-help' : $base.'-help';
    }

    /** The id the hidden copy of the field's help carries: the one a control linked, else the one it offers. */
    public function helpParagraphId(): ?string
    {
        return $this->helpId ?? ($this->target === null ? null : $this->target.'-help');
    }

    /**
     * A control's `aria-describedby` with the hint and the help of the field around it added, see
     * hintId() and helpId(). Outside a field, when the field shows neither, or when a caller's own
     * `aria-describedby` names one already, the value keeps what it had.
     */
    public static function describe(?self $field, ?string $describedBy, string $controlId, ?string $ownHintId, ?string $ownHelpId = null): string
    {
        $describedBy = trim((string) $describedBy);

        foreach ([$field?->hintId($controlId, $ownHintId), $field?->helpId($controlId, $ownHelpId)] as $id) {
            if ($id !== null && ! in_array($id, preg_split('/\s+/', $describedBy) ?: [], true)) {
                $describedBy = trim($describedBy.' '.$id);
            }
        }

        return $describedBy;
    }

    /**
     * The control takes the field's label as its name, and gets the label's id back.
     *
     * The id is for a part that names itself by reference: a listbox, a slider thumb, an editing
     * surface the browser builds. Null when the field has no label to give, its caller pointed
     * `for` elsewhere, or a control in the same field took the label already; the control then
     * names itself as it would anywhere else.
     *
     * @param  string|null  $controlId  the element a label can name, or null when the control has none
     */
    public function takeLabel(?string $controlId): ?string
    {
        if (! $this->offers || $this->taken) {
            return null;
        }

        $this->taken = true;
        $this->controlId = filled($controlId) ? $controlId : null;

        return $this->labelId = DomId::unique(($this->target ?? 'field').'-label', 'field-label-');
    }

    /**
     * A control that does not take the label reports the id it got, and the label points there.
     *
     * A plain field takes its id from its `name`, which is the field's target, but a second one
     * with the same name on the page gets `-2` from DomId: pointed at the name, the second field's
     * label named the first one's control. The first control to report wins, and a caller who
     * pointed `for` somewhere keeps that wiring, as with takeLabel().
     */
    public function follow(string $controlId): void
    {
        if ($this->offers && ! $this->taken && $this->followed === null && $controlId !== '') {
            $this->followed = $controlId;
        }
    }

    /** The visible label's text, for a control that names its parts with it. */
    public function labelText(): string
    {
        return $this->labelText;
    }

    /**
     * What the field's label points its `for` at.
     *
     * The control that took the label, or nothing when that control has no element a label can
     * name and is named by reference instead. Before any control took it, the id a plain control
     * reported through follow(), else the target the field was given: its `for`, or its `name`,
     * which is the id a plain input carries.
     */
    public function forId(): ?string
    {
        return $this->taken ? $this->controlId : ($this->followed ?? $this->target);
    }

    /** The label's id, once a control took the label; the label carries no id before that. */
    public function labelId(): ?string
    {
        return $this->labelId;
    }
}
