/**
 * Zentraler Component-Bootstrapper.
 * Sucht nach DOM-Elementen und instanziiert die dazugehörigen Klassen.
 * Beinhaltet eine Error-Boundary, um zu verhindern, dass ein defektes Modul
 * die Ausführung anderer, wichtiger Skripte (wie den Session-Timer) blockiert.
 */

export function mount(selector, ComponentClass, ...args) {
    let elements = [];
    try {
        // FIX: DOM-Zugriff gegen DOMException (SyntaxError) absichern
        elements = document.querySelectorAll(selector);
    } catch (error) {
        console.error(`[Bootstrapper] Ungültiger Selektor blockiert: ${selector}`, error);
        return [];
    }

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
    let el = null;
    try {
        el = document.querySelector(selector);
    } catch (error) {
        console.error(`[Bootstrapper] Ungültiger Singleton-Selektor blockiert: ${selector}`, error);
        return null;
    }

    if (!el) return null;

    try {
        return new ComponentClass(el, ...args);
    } catch (error) {
        console.error(
            `[Bootstrapper] Kritischer Fehler beim Mounten des Singletons ${ComponentClass.name} an${selector}:`,
            error
        );
        return null;
    }
}
