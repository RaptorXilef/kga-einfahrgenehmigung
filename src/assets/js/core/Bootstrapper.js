/**
 * Zentraler Component-Bootstrapper mit Garbage Collection Protection.
 * Bindet JS-Klassen an DOM-Elemente und verhindert Zombie-Instanzen.
 */

// Sichert die Zuweisung, ohne den Garbage Collector zu blockieren
const componentRegistry = new WeakMap();

export function mount(selector, ComponentClass, ...args) {
    let elements = [];
    try {
        elements = document.querySelectorAll(selector);
    } catch (error) {
        console.error(`[Bootstrapper] Ungültiger Selektor blockiert: ${selector}`, error);
        return [];
    }

    if (elements.length === 0) return [];

    const instances = [];
    for (const el of elements) {
        // Verhindert doppeltes Mounting desselben Elements
        if (componentRegistry.has(el)) continue;

        try {
            const instance = new ComponentClass(el, ...args);
            componentRegistry.set(el, instance);
            instances.push(instance);
        } catch (error) {
            console.error(
                `[Bootstrapper] Kritischer Fehler beim Mounten von ${ComponentClass.name} an ${selector}:`,
                error
            );
        }
    }
    return instances;
}

export function mountSingle(selector, ComponentClass, ...args) {
    const instances = mount(selector, ComponentClass, ...args);
    return instances.length > 0 ? instances[0] : null;
}

export function lazyMount(selector, importPromise, className, ...args) {
    let elements = [];
    try {
        elements = document.querySelectorAll(selector);
    } catch (error) {
        return [];
    }

    if (elements.length === 0) return [];

    importPromise()
        .then((module) => {
            const ComponentClass = module[className];
            for (const el of elements) {
                if (componentRegistry.has(el)) continue;
                try {
                    const instance = new ComponentClass(el, ...args);
                    componentRegistry.set(el, instance);
                } catch (error) {
                    console.error(
                        `[Bootstrapper] Fehler beim asynchronen Mounten von ${className}:`,
                        error
                    );
                }
            }
        })
        .catch((err) =>
            console.error(`[Bootstrapper] Netzwerk-Fehler beim Laden von ${className}:`, err)
        );
}

export function lazyMountSingle(selector, importPromise, className, ...args) {
    lazyMount(selector, importPromise, className, ...args);
}
