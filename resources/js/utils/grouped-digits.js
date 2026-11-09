/**
 * `x-wk-grouped-digits`: an amount field that groups its digits while it is typed.
 *
 * The field shows the number with the marks of the page's language, `1.500.000,5` in German and
 * `1,500,000.5` in English, and the caret stays where it was as a mark appears or goes. Its
 * `value` is the plain number all the same: digits, a `.` before the decimals and a leading `-`.
 * The directive gives the element an accessor of its own for `value`: reading it returns the
 * plain number, and writing it shows the number grouped. Alpine's `x-model`, and Livewire's
 * `wire:model` through it, read and write `value`, so the model holds the plain number with every
 * modifier, and a number the server writes comes back grouped. A plain form sends the plain number
 * as well, because the form's `formdata` event replaces the field's entry. Without JavaScript the
 * field is an ordinary text field and sends what was typed.
 *
 * The marks come from the field: `data-wk-grouped-marks` (`{"group": ".", "decimal": ","}`) when
 * the application formats numbers itself, else `Intl.NumberFormat` for `data-wk-grouped-locale`.
 *
 * Typing is filtered, not refused: a character that is no digit, no decimal mark and no leading
 * minus is dropped, and so is a second decimal mark. A point or a comma that is not the page's
 * group mark counts as a decimal mark, so `.` from a French numeric keypad writes `,`. A group mark
 * deleted with Backspace or Delete takes the digit beside it, or the deletion would change nothing
 * and the field would look stuck.
 *
 * A number written into the field that the field shows already keeps its text as typed:
 * `x-model.number` writes the number back after every keystroke, and its digits would drop a
 * decimal mark just typed and a trailing zero. A minus or a decimal mark typed before any digit
 * stays the same way while the field has the focus.
 *
 * Pasted text is read by its own marks, since it was written for some other page: where a point
 * and a comma both stand, the last one is the decimal mark; a mark that stands more than once
 * groups; a mark that stands once is the decimal mark unless it is the page's group mark followed
 * by exactly three digits. `1,500,000` pasted into a German field is 1.500.000, not 1,5.
 *
 * Cleanup contract: the listeners on the field and on its form go with the element, and so do
 * the accessor, which leaves the element's own one in place, and a pending regroup after a reset.
 */

const MINUS = String.fromCharCode(0x2212);

const isDigit = (ch) => ch >= '0' && ch <= '9';

/**
 * The group mark and the decimal mark the field writes: the ones given, when the application
 * formats numbers itself, else the ones `Intl.NumberFormat` writes for the locale.
 *
 * @param {string|null|undefined} locale
 * @param {{group?: unknown, decimal?: unknown}|null} [given]
 * @returns {{group: string, decimal: string}}
 */
export function groupedMarksFor(locale, given = null) {
    if (given && typeof given.group === 'string' && typeof given.decimal === 'string' && given.decimal !== '' && given.decimal !== given.group) {
        return { group: given.group, decimal: given.decimal };
    }

    // The field writes the digits 0 to 9, so the marks are the ones the locale writes beside them.
    const options = { numberingSystem: 'latn' };
    let parts;

    try {
        parts = new Intl.NumberFormat(locale || undefined, options).formatToParts(1234567.5);
    } catch {
        // An unknown tag throws a RangeError; the browser's own language is the closest answer.
        parts = new Intl.NumberFormat(undefined, options).formatToParts(1234567.5);
    }

    const group = parts.find((part) => part.type === 'group')?.value ?? ',';
    const decimal = parts.find((part) => part.type === 'decimal')?.value ?? '.';

    return { group, decimal: decimal === group ? '.' : decimal };
}

/**
 * Whether a typed character is a decimal mark: the page's own, or a point or a comma that is not
 * the page's group mark.
 *
 * @param {string} ch
 * @param {{group: string, decimal: string}} marks
 * @returns {boolean}
 */
export function isDecimalMark(ch, marks) {
    return ch === marks.decimal || ((ch === '.' || ch === ',') && ch !== marks.group);
}

/**
 * The plain number in a text the field shows: its digits, its first decimal mark as `.`, and a
 * minus that stands before any digit.
 *
 * @param {unknown} text
 * @param {{group: string, decimal: string}} marks
 * @returns {string}
 */
export function plainOfGrouped(text, marks) {
    let plain = '';
    let pointed = false;

    for (const ch of String(text ?? '')) {
        if (isDigit(ch)) {
            plain += ch;
        } else if (isDecimalMark(ch, marks) && ! pointed) {
            pointed = true;
            plain += '.';
        } else if ((ch === '-' || ch === MINUS) && plain === '') {
            plain = '-';
        }
    }

    return plain;
}

/**
 * A number the application writes, read as plain: a JavaScript number, or a text with `.` as its
 * decimal mark, as a model and a database hold it.
 *
 * @param {unknown} value
 * @returns {string}
 */
export function plainOfModel(value) {
    if (typeof value === 'number') {
        return Number.isFinite(value) ? String(value) : '';
    }

    let plain = '';
    let pointed = false;

    for (const ch of String(value ?? '')) {
        if (isDigit(ch)) {
            plain += ch;
        } else if (ch === '.' && ! pointed) {
            pointed = true;
            plain += '.';
        } else if ((ch === '-' || ch === MINUS) && plain === '') {
            plain = '-';
        }
    }

    return plain;
}

/**
 * A plain number as the field gives it away: empty while it holds no digit, without a decimal
 * mark that ends it, and with a zero before one that starts it. `-` is empty, `1234.` is `1234`
 * and `-.5` is `-0.5`, each a number PHP's `is_numeric()` and JavaScript's `Number()` read alike.
 *
 * @param {unknown} plain
 * @returns {string}
 */
export function numberText(plain) {
    let text = String(plain ?? '');

    if (! [...text].some(isDigit)) {
        return '';
    }

    if (text.endsWith('.')) {
        text = text.slice(0, -1);
    }

    return text.replace(/^(-?)\./, '$10.');
}

/**
 * Whether two plain numbers are the same number: `1234` and `1234.`, `1.5` and `1.50`. A text
 * without a digit is no number, and the same as nothing.
 *
 * @param {unknown} a
 * @param {unknown} b
 * @returns {boolean}
 */
export function sameNumber(a, b) {
    const left = numberText(a);
    const right = numberText(b);

    return left !== '' && right !== '' && Number(left) === Number(right);
}

/**
 * The plain number in pasted text, which was written for some other page: by the marks it
 * carries, not by the page's alone. Where a point and a comma both stand, the last one is the
 * decimal mark. A mark that stands more than once groups. A mark that stands once is the decimal
 * mark, unless it is the page's group mark and exactly three digits follow it. Every other
 * character but a leading minus is dropped.
 *
 * @param {unknown} text
 * @param {{group: string, decimal: string}} marks
 * @returns {string}
 */
export function plainOfPasted(text, marks) {
    const source = String(text ?? '');
    const lastPoint = source.lastIndexOf('.');
    const lastComma = source.lastIndexOf(',');
    let decimal = null;

    if (lastPoint !== -1 && lastComma !== -1) {
        decimal = lastPoint > lastComma ? '.' : ',';
    } else if (lastPoint !== -1 || lastComma !== -1) {
        const mark = lastPoint !== -1 ? '.' : ',';
        const at = source.indexOf(mark);

        if (at === source.lastIndexOf(mark)) {
            const digitsAfter = /^\d*/.exec(source.slice(at + 1))[0].length;

            decimal = mark === marks.group && digitsAfter === 3 ? null : mark;
        }
    } else if (marks.decimal !== '.' && marks.decimal !== ',' && source.includes(marks.decimal)) {
        decimal = marks.decimal;
    }

    let plain = '';
    let pointed = false;

    for (const ch of source) {
        if (isDigit(ch)) {
            plain += ch;
        } else if (ch === decimal && ! pointed) {
            pointed = true;
            plain += '.';
        } else if ((ch === '-' || ch === MINUS) && plain === '') {
            plain = '-';
        }
    }

    return numberText(plain);
}

/**
 * A plain number as the field shows it: the whole part in groups of three, the decimals after the
 * page's decimal mark, as many as were typed.
 *
 * @param {string} plain
 * @param {{group: string, decimal: string}} marks
 * @returns {string}
 */
export function groupedOf(plain, marks) {
    const text = String(plain ?? '');
    const negative = text.startsWith('-');
    const body = negative ? text.slice(1) : text;
    const point = body.indexOf('.');
    const whole = point === -1 ? body : body.slice(0, point);
    const fraction = point === -1 ? null : body.slice(point + 1);
    let grouped = '';

    for (let i = 0; i < whole.length; i++) {
        if (i > 0 && (whole.length - i) % 3 === 0) {
            grouped += marks.group;
        }

        grouped += whole[i];
    }

    return (negative ? '-' : '') + grouped + (fraction === null ? '' : marks.decimal + fraction);
}

/**
 * The text grouped anew, with the caret after as many characters of the plain number as stood
 * before it. The plain number of the text before the caret is the start of the plain number of the
 * whole, since the filter reads from left to right, and in a grouped text every character but a
 * group mark is one character of the plain number.
 *
 * @param {string} text
 * @param {number} caret
 * @param {{group: string, decimal: string}} marks
 * @returns {{text: string, caret: number}}
 */
export function regroup(text, caret, marks) {
    const source = String(text ?? '');
    const before = plainOfGrouped(source.slice(0, Math.max(0, caret)), marks).length;
    const grouped = groupedOf(plainOfGrouped(source, marks), marks);
    let at = 0;
    let seen = 0;

    while (at < grouped.length && seen < before) {
        if (grouped[at] !== marks.group) {
            seen++;
        }

        at++;
    }

    return { text: grouped, caret: at };
}

/** The element's own accessor for `value`, from its prototype chain. */
function nativeValueOf(el) {
    for (let proto = Object.getPrototypeOf(el); proto; proto = Object.getPrototypeOf(proto)) {
        const descriptor = Object.getOwnPropertyDescriptor(proto, 'value');

        if (descriptor && typeof descriptor.get === 'function' && typeof descriptor.set === 'function') {
            return descriptor;
        }
    }

    return null;
}

/** The marks the server wrote on the field, read as JSON, or null. */
function givenMarksOf(el) {
    try {
        return JSON.parse(el.getAttribute('data-wk-grouped-marks') ?? 'null');
    } catch {
        return null;
    }
}

/**
 * Register the `x-wk-grouped-digits` directive.
 *
 * @param {object} Alpine
 */
export function registerGroupedDigitsDirective(Alpine) {
    Alpine.directive('wk-grouped-digits', (el, _directive, { cleanup }) => {
        const native = nativeValueOf(el);

        if (! native) {
            return;
        }

        const marks = groupedMarksFor(el.getAttribute('data-wk-grouped-locale'), givenMarksOf(el));
        const shown = () => native.get.call(el);
        const focused = () => el.ownerDocument?.activeElement === el;
        const show = (text, caret = null) => {
            native.set.call(el, text);

            if (caret !== null && typeof el.setSelectionRange === 'function' && focused()) {
                el.setSelectionRange(caret, caret);
            }
        };

        // What stands in the field when the directive starts is a number as the application wrote
        // it: the server's `value`, or what `x-model` wrote, which initializes before this does.
        show(groupedOf(plainOfModel(shown()), marks));

        Object.defineProperty(el, 'value', {
            configurable: true,
            enumerable: true,
            get: () => numberText(plainOfGrouped(shown(), marks)),
            set: (value) => {
                const next = plainOfModel(value);
                const current = plainOfGrouped(shown(), marks);

                // The field shows this number already, as it was typed, or a start of one while
                // it has the focus: `x-model.number` writes back what it read after every keystroke.
                if (sameNumber(next, current) || (numberText(next) === '' && numberText(current) === '' && focused())) {
                    return;
                }

                show(groupedOf(next, marks));
            },
        });

        const onInput = (event) => {
            if (event.isComposing) {
                return;
            }

            const text = shown();
            const next = regroup(text, el.selectionStart ?? text.length, marks);

            if (next.text !== text) {
                show(next.text, next.caret);
            }
        };

        const onBeforeInput = (event) => {
            const backward = event.inputType === 'deleteContentBackward';

            if ((! backward && event.inputType !== 'deleteContentForward') || el.selectionStart === null || el.selectionStart !== el.selectionEnd) {
                return;
            }

            const text = shown();
            const mark = backward ? el.selectionStart - 1 : el.selectionStart;
            const digit = backward ? mark - 1 : mark + 1;

            if (marks.group === '' || text[mark] !== marks.group || digit < 0 || digit >= text.length) {
                return;
            }

            event.preventDefault();

            const next = regroup(text.slice(0, digit) + text.slice(digit + 1), backward ? digit : mark, marks);
            show(next.text, next.caret);
            el.dispatchEvent(new Event('input', { bubbles: true }));
        };

        // A plain form sends what the form data holds, which the browser reads from what the field
        // shows. The entry of this field becomes the plain number, among the fields of its name.
        const form = el.form ?? null;
        const onFormData = (event) => {
            const name = el.getAttribute('name');

            if (! name || el.disabled || ! event.formData) {
                return;
            }

            const fields = [...form.elements].filter((field) => field.getAttribute?.('name') === name && ! field.disabled);
            const values = event.formData.getAll(name);
            const index = fields.indexOf(el);

            if (index === -1 || index >= values.length) {
                return;
            }

            values[index] = el.value;
            event.formData.delete(name);
            values.forEach((value) => event.formData.append(name, value));
        };

        // Pasted text was written for some other page, so it is read by its own marks and goes in
        // as the page writes it, in place of what was selected.
        const onPaste = (event) => {
            const pasted = event.clipboardData?.getData('text/plain') ?? '';

            if (pasted === '' || el.readOnly || el.disabled) {
                return;
            }

            event.preventDefault();

            const text = shown();
            const start = el.selectionStart ?? text.length;
            const end = el.selectionEnd ?? start;
            const number = plainOfPasted(pasted, marks).replace('.', marks.decimal);
            const next = regroup(text.slice(0, start) + number + text.slice(end), start + number.length, marks);

            show(next.text, next.caret);
            el.dispatchEvent(new Event('input', { bubbles: true }));
        };

        // A form resets its fields after its `reset` event, to the text of their `value`
        // attribute, which is the plain number the server wrote.
        let resetTimer = null;
        const onReset = () => {
            clearTimeout(resetTimer);
            resetTimer = setTimeout(() => show(groupedOf(plainOfModel(shown()), marks)), 0);
        };

        // Capture: the field regroups before `x-model`, whose listener bubbles, reads it.
        el.addEventListener('input', onInput, true);
        el.addEventListener('beforeinput', onBeforeInput);
        el.addEventListener('paste', onPaste);
        form?.addEventListener('formdata', onFormData);
        form?.addEventListener('reset', onReset);

        cleanup(() => {
            el.removeEventListener('input', onInput, true);
            el.removeEventListener('beforeinput', onBeforeInput);
            el.removeEventListener('paste', onPaste);
            form?.removeEventListener('formdata', onFormData);
            form?.removeEventListener('reset', onReset);
            clearTimeout(resetTimer);
            delete el.value;
        });
    });

    // A directive only runs inside a tree Alpine initializes, and an amount field in a plain form
    // has none. Registered as an init selector it starts on its own, as `x-wk-indeterminate` does.
    if (typeof Alpine.addInitSelector === 'function') {
        Alpine.addInitSelector(() => '[x-wk-grouped-digits]');
    }
}
