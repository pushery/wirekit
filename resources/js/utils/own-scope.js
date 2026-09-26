/**
 * Methods that act on their own component, whichever scope calls them.
 *
 * Alpine runs a method with `this` set to the MERGED scope of the element whose expression called
 * it, innermost scope first. For a component whose markup can hold other scopes, that is the wrong
 * object. Take `close()` on a modal, called from a button inside an accordion in the modal's body:
 * `this.isOpen` resolves against the accordion first, and the accordion has an `isOpen(id)` method
 * of its own, so `this.isOpen = false` would replace that method and leave the modal open. `this.$el`
 * and `this.$refs` would be the inner scope's too.
 *
 * Every method and getter of a component passed through here therefore runs against the
 * component's own scope, bound when Alpine starts the component:
 *
 * - A read finds the component's own property first and falls back to the scopes around the
 *   component's root. That fallback is how a disclosure reads the folding state of the sidebar it
 *   sits in, and how a control finds the optimistic layer that wraps it.
 * - A method found through that fallback belongs to the scope it was found in, and runs against
 *   that scope. Called as `this.run(value)`, it would otherwise get this component as `this`, and
 *   the optimistic layer's `run()` would write the new value onto the control that handed it up
 *   instead of onto the layer, which never flips.
 * - A write lands on the component. Alpine stores a property that no scope declares on the
 *   OUTERMOST scope of the stack, so a handle assigned in `init()` would otherwise end up on
 *   whatever wraps the page, shared by every component under it. Changing another component's
 *   state is that component's job: call its method or dispatch an event.
 *
 * - A magic resolves against the component's root too: `this.$el` is the root and `this.$refs` holds
 *   the refs registered in the component's own scope. An `x-ref` registers into the CLOSEST
 *   `x-data`, so a ref inside a sub-component that declares a scope of its own is not among them,
 *   whichever element called the method. Such an element is found through the DOM, with the ref
 *   as the first try: `this.$refs.trigger ?? this.$root.querySelector('[data-wk-dropdown-trigger]')`.
 *
 * Until `init()` has run, and for a caller that is not Alpine, `this` stays what the caller set.
 *
 * @template {object} T
 * @param {T} component
 * @returns {T}
 */
export function withOwnScope(component) {
    // One key per instance, so asking any merged scope for it finds exactly this component.
    const self = Symbol('wirekit.ownScope');
    let host = null;

    Object.defineProperty(component, self, {
        configurable: true,
        enumerable: false,
        writable: false,
        value: component,
    });

    for (const [key, descriptor] of Object.entries(Object.getOwnPropertyDescriptors(component))) {
        if (key === 'init') {
            continue;
        }

        if (typeof descriptor.value === 'function') {
            const method = descriptor.value;

            descriptor.value = function ownScopeMethod(...args) {
                return method.apply(host || this, args);
            };
            Object.defineProperty(component, key, descriptor);
        } else if (typeof descriptor.get === 'function') {
            const { get, set } = descriptor;

            descriptor.get = function ownScopeGetter() {
                return get.call(host || this);
            };

            if (typeof set === 'function') {
                descriptor.set = function ownScopeSetter(value) {
                    set.call(host || this, value);
                };
            }

            Object.defineProperty(component, key, descriptor);
        }
    }

    const init = component.init;
    const initDescriptor = Object.getOwnPropertyDescriptor(component, 'init');

    Object.defineProperty(component, 'init', {
        configurable: true,
        enumerable: initDescriptor ? initDescriptor.enumerable : false,
        writable: true,
        value: function ownScopeInit(...args) {
            host = scopeOf(this, self, component);

            return typeof init === 'function' ? init.apply(host, args) : undefined;
        },
    });

    return component;
}

/**
 * The scope a component's methods run against: its own properties first, then the scopes around
 * its root, whose methods run against those scopes.
 *
 * `scope` is what Alpine hands `init()`, the merged scope of the component's root. Read through it,
 * the per-instance key comes back as the component's own REACTIVE proxy, so every read and write
 * below is tracked like any other. Outside Alpine it is the plain object.
 *
 * The proxy's target is an empty object rather than the component, because the component carries
 * properties Alpine defines as non-configurable, and a proxy may not report those differently from
 * its target.
 */
function scopeOf(scope, self, component) {
    const own = (scope && scope[self]) || component;
    const around = scope || own;

    return new Proxy(Object.create(null), {
        get(_target, key) {
            if (Reflect.has(own, key)) {
                return Reflect.get(own, key);
            }

            const found = Reflect.get(around, key);

            // Bound to the merged scope it came from, which is how Alpine runs the same method for
            // markup at this component's root: its reads and writes reach the scope that declares
            // them, the layer's `value` on the layer rather than on the component that called it.
            return typeof found === 'function' ? found.bind(around) : found;
        },
        has(_target, key) {
            return Reflect.has(own, key) || Reflect.has(around, key);
        },
        set(_target, key, value) {
            return Reflect.set(own, key, value);
        },
        deleteProperty(_target, key) {
            return Reflect.deleteProperty(own, key);
        },
    });
}

export default withOwnScope;
