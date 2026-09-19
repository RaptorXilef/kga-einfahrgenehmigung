/**
 * Zentraler Component-Bootstrapper mit automatischer Garbage Collection.
 * Bindet JS-Klassen an DOM-Elemente und verhindert Zombie-Instanzen durch einen MutationObserver.
 */

// Sichert die Zuweisung, ohne den Garbage Collector zu blockieren
// Struktur: WeakMap<Element, Map<ComponentClass, Instance>>
const componentRegistry = new WeakMap();

// Zentraler DOM-Observer: Erkennt gelöschte Elemente und triggert deren destroy() Methode
const gcObserver = new MutationObserver((mutations) => {
    for (const mutation of mutations) {
        for (const removedNode of mutation.removedNodes) {
            if (removedNode.nodeType !== Node.ELEMENT_NODE) continue;

            // Rekursive Prüfung: Löscht ein Parent, müssen auch die Kinder aufgeräumt werden
            const cleanupNode = (node) => {
                const classMap = componentRegistry.get(node);
                if (classMap) {
                    for (const instance of classMap.values()) {
                        if (typeof instance.destroy === 'function') {
                            try {
                                instance.destroy();
                            } catch (err) {
                                console.error(
                                    `[Bootstrapper] Fehler beim Zerstören von Component:`,
                                    err
                                );
                            }
                        }
                    }
                    componentRegistry.delete(node);
                }

                // Rekursiv alle Kinder durchlaufen (O(n) - aber notwendig für GC)
                if (node.children && node.children.length > 0) {
                    Array.from(node.children).forEach(cleanupNode);
                }
            };

            cleanupNode(removedNode);
        }
    }
});

// Observer sicher starten
if (document.body) {
    gcObserver.observe(document.body, { childList: true, subtree: true });
} else {
    document.addEventListener('DOMContentLoaded', () => {
        gcObserver.observe(document.body, { childList: true, subtree: true });
    });
}

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
        // 1. Hole oder erstelle die Map für dieses spezifische DOM-Element
        let classMap = componentRegistry.get(el);
        if (!classMap) {
            classMap = new Map();
            componentRegistry.set(el, classMap);
        }

        // 2. Verhindert doppeltes Mounting DIESER spezifischen Klasse am selben Element
        if (classMap.has(ComponentClass)) continue;

        try {
            const instance = new ComponentClass(el, ...args);
            classMap.set(ComponentClass, instance);
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
        // LINTER-FIX: Fehler nicht verschlucken, sondern dokumentieren!
        console.warn(
            `[Bootstrapper] Ungültiger Selektor für LazyLoad blockiert: ${selector}`,
            error
        );
        return [];
    }

    if (elements.length === 0) return [];

    importPromise()
        .then((module) => {
            const ComponentClass = module[className];
            for (const el of elements) {
                let classMap = componentRegistry.get(el);
                if (!classMap) {
                    classMap = new Map();
                    componentRegistry.set(el, classMap);
                }

                if (classMap.has(ComponentClass)) continue;

                try {
                    const instance = new ComponentClass(el, ...args);
                    classMap.set(ComponentClass, instance);
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
