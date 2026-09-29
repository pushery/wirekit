import { withOwnScope } from './own-scope.js';

/**
 * The component object an alias belongs to, stored under a key no markup can spell.
 *
 * Alpine evaluates an expression against the MERGED scope of the element it is on, and hands
 * that merged scope to an accessor as `this`. `this.isOpen` inside the alias would therefore
 * resolve `isOpen` against the whole stack, and the first scope that declares one wins: markup
 * reading `open` inside a WireKit dropdown, with a scope of its own that declares `isOpen` in
 * between, would read and write that inner value instead of the dropdown's. Asking the merged
 * scope for this key finds the scope that owns the alias, because every scope carrying the
 * alias carries the key too.
 *
 * The stored value is the plain object. Read through Alpine's reactive scope it comes back as
 * that scope's own reactive proxy, so the alias stays reactive.
 */
const OWNER = Symbol('wirekit.openAliasOwner');

/**
 * `open` as an alias of `isOpen`, deprecated since 2.57.0 and removed in 3.0.0, a major version
 * that has no fixed date.
 *
 * A component's open state is `isOpen`, not `open`: `open` is also the name of a function on
 * `window`, so an expression evaluated against a scope that no longer carries the component finds
 * `window.open` instead of failing, and Alpine calls it — an `Illegal invocation` from the
 * framework's bundle, with no component named. `isOpen` fails with the component's own name
 * instead.
 *
 * `open` stays readable and writable for markup written against it, such as a developer's own slot
 * content or a view published with `vendor:publish`. The accessor is defined on the finished object
 * rather than in the returned literal because an object spread copies a getter's current VALUE, and
 * a component built as `{ ...overlay }` would then carry a frozen `open`.
 *
 * The component's methods are bound to its own scope on the way out (see `own-scope.js`). `isOpen`
 * is a name other scopes use as well, an accordion's `isOpen(id)` among them, and a method called
 * from inside one of them must not read or write theirs. That binding stays when the alias goes:
 * each caller then passes its component to `withOwnScope()` directly.
 *
 * @template {object} T
 * @param {T} component
 * @returns {T}
 */
export function withOpenAlias(component) {
    Object.defineProperty(component, OWNER, {
        configurable: true,
        enumerable: false,
        writable: false,
        value: component,
    });

    Object.defineProperty(component, 'open', {
        configurable: true,
        enumerable: true,
        get() {
            return this[OWNER].isOpen;
        },
        set(value) {
            this[OWNER].isOpen = value;
        },
    });

    return withOwnScope(component);
}

export default withOpenAlias;
