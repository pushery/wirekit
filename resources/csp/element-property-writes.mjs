/**
 * Assignments the CSP evaluator refuses whatever their value: a write to a property of an element.
 *
 * The evaluator looks at the object of every assignment to a member, and of every `++` and `--`
 * on one, and throws "Property assignments on DOM objects are prohibited in the CSP build" when
 * that object is a DOM object: a `Node`, a `CSSStyleDeclaration`, a `DOMStringMap`, a
 * `DOMTokenList`, a `NamedNodeMap`, or an object whose class name starts with `SVG`, `CSS` or
 * `DOM`. The expression parses, so a grammar check passes it, and the directive throws each time
 * it runs.
 *
 * Which object a member expression reaches is a question about values at runtime. Decided here
 * is the part the source answers: an object that is one of Alpine's element magics (`$el`,
 * `$root`, a `$refs` entry), the element of an event (`$event.target`, `$event.currentTarget`,
 * `$event.relatedTarget`), an element a method of one of those returns (`closest()`,
 * `querySelector()`), or a member of an element that is itself a DOM object (`style`, `dataset`,
 * `classList`, `attributes`, `parentElement` and the other element links below). A member of the
 * component's own state is none of these, so `form.name = ''` stays clean, and so does `$refs.x =
 * 1`, an assignment to the object that holds the references rather than to an element.
 *
 * This module ships (`resources/` is not export-ignored) for the reason the globals module gives:
 * the audit a developer runs is the one that has to know.
 */

/** The magics whose value is an element. */
const ELEMENT_MAGICS = new Set(['$el', '$root']);

/** The members of an event whose value is an element. */
const EVENT_ELEMENTS = new Set(['target', 'currentTarget', 'relatedTarget', 'srcElement']);

/**
 * The members of an element whose value is a DOM object as well. A property such as `value`,
 * `checked` or `scrollTop` is a string, a boolean or a number, and a write to a property of one
 * of those is no write to a DOM object.
 */
const DOM_MEMBERS = new Set([
    'style', 'dataset', 'classList', 'attributes',
    'parentElement', 'parentNode', 'offsetParent', 'form',
    'firstElementChild', 'lastElementChild', 'nextElementSibling', 'previousElementSibling',
    'firstChild', 'lastChild', 'nextSibling', 'previousSibling',
]);

/** The methods of an element that return an element. */
const ELEMENT_METHODS = new Set(['closest', 'querySelector']);

const memberName = (node) => (node && ! node.computed && node.property && node.property.type === 'Identifier'
    ? node.property.name
    : null);

/**
 * Whether this node's value is a DOM object, as far as the source says.
 *
 * @param {object} node - a node of the parsed AST
 * @returns {boolean}
 */
function isDomObject(node) {
    if (! node || typeof node !== 'object') {
        return false;
    }

    if (node.type === 'Identifier') {
        return ELEMENT_MAGICS.has(node.name);
    }

    if (node.type === 'MemberExpression') {
        const object = node.object;

        if (object && object.type === 'Identifier' && object.name === '$refs') {
            return true;
        }

        if (object && object.type === 'Identifier' && object.name === '$event') {
            return EVENT_ELEMENTS.has(memberName(node));
        }

        return DOM_MEMBERS.has(memberName(node)) && isDomObject(object);
    }

    if (node.type === 'CallExpression' && node.callee && node.callee.type === 'MemberExpression') {
        return ELEMENT_METHODS.has(memberName(node.callee)) && isDomObject(node.callee.object);
    }

    return false;
}

/** The written target as it reads in the expression, `$refs.list.scrollTop`, or `…` for a computed part. */
function targetText(node) {
    if (! node || typeof node !== 'object') {
        return '…';
    }

    if (node.type === 'Identifier') {
        return node.name;
    }

    if (node.type === 'MemberExpression') {
        const name = memberName(node);

        return targetText(node.object) + (name !== null ? '.' + name : '[…]');
    }

    if (node.type === 'CallExpression') {
        return targetText(node.callee) + '(…)';
    }

    return '…';
}

/**
 * The targets in this expression that write a property of a DOM object, which the CSP evaluator
 * refuses: an assignment whose left side is a member of one, and a `++` or `--` on such a member.
 *
 * @param {object} node - the parsed AST
 * @returns {string[]} the written targets, sorted, empty when there are none
 */
function elementPropertyWritesIn(node, found = new Set()) {
    if (! node || typeof node !== 'object') {
        return [...found].sort();
    }

    if (Array.isArray(node)) {
        for (const child of node) {
            elementPropertyWritesIn(child, found);
        }

        return [...found].sort();
    }

    const target = node.type === 'AssignmentExpression' ? node.left
        : (node.type === 'UpdateExpression' ? node.argument : null);

    if (target && target.type === 'MemberExpression' && isDomObject(target.object)) {
        found.add(targetText(target));
    }

    for (const value of Object.values(node)) {
        if (value && typeof value === 'object') {
            elementPropertyWritesIn(value, found);
        }
    }

    return [...found].sort();
}

export { DOM_MEMBERS, ELEMENT_MAGICS, EVENT_ELEMENTS, ELEMENT_METHODS, isDomObject, elementPropertyWritesIn };
