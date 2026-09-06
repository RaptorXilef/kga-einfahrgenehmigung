/**
 * Zentraler Component-Bootstrapper.
 * Sucht nach DOM-Elementen und instanziiert die dazugehörigen Klassen.
 * Beinhaltet eine Error-Boundary, um zu verhindern, dass ein defektes Modul
 * die Ausführung anderer, wichtiger Skripte (wie den Session-Timer) blockiert.
 */

export function mount(selector, ComponentClass, ...args) {
    const elements = document.querySelectorAll(selector);
    if (elements.length === 0) return [];

    const instances = [];
    for (const el of elements) {
        try {
            instances.push(new ComponentClass(el, ...args));
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
    const el = document.querySelector(selector);
    if (!el) return null;

    try {
        return new ComponentClass(el, ...args);
    } catch (error) {
        console.error(
            `[Bootstrapper] Kritischer Fehler beim Mounten des Singletons ${ComponentClass.name} an ${selector}:`,
            error
        );
        return null;
    }
}
