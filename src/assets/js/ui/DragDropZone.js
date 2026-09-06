import { notifier } from '../core/Notifier.js';

/**
 * Universelle UI-Komponente für Datei-Uploads per Drag & Drop.
 * Unterstützt direkte Formular-Submits (z.B. Profilbilder) sowie reine
 * lokale Vorschauen (z.B. Formular-Avatare) oder Preview + Auto-Submit (Bank CSV).
 */
export class DragDropZone {
    constructor(zone) {
        this.zone = zone;
        this.input = this.zone.querySelector('input[type="file"]');
        this.form = this.zone.closest('form') || this.zone.querySelector('form');
        this.isPreviewOnly = this.zone.classList.contains('js-preview-only');
        // Steuert, ob nach einem Preview direkt abgesendet werden soll
        this.isAutoSubmit = this.zone.classList.contains('js-auto-submit');

        if (this.input) {
            this.init();
        }
    }

    init() {
        // Standard-Browser-Aktionen (wie Bild im Tab öffnen) verhindern
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
            this.zone.addEventListener(
                eventName,
                (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                },
                false
            );
        });

        // Visuelles Feedback beim Drüberziehen
        ['dragenter', 'dragover'].forEach((eventName) => {
            this.zone.addEventListener(
                eventName,
                () => this.zone.classList.add('is-dragover'),
                false
            );
        });

        // Visuelles Feedback entfernen, wenn man die Zone verlässt
        ['dragleave', 'drop'].forEach((eventName) => {
            this.zone.addEventListener(
                eventName,
                () => this.zone.classList.remove('is-dragover'),
                false
            );
        });

        // Die Magie beim Loslassen (Drop)
        this.zone.addEventListener('drop', (e) => {
            if (e.dataTransfer.files.length > 0) {
                this.input.files = e.dataTransfer.files;
                this.handleFile(this.input.files[0]);
            }
        });

        // Fallback: Normaler Klick auf das Input-Feld
        this.input.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                this.handleFile(e.target.files[0]);
            }
        });

        // Klick auf die Zone öffnet den Datei-Dialog (wenn Preview-Modus)
        if (this.isPreviewOnly) {
            this.zone.addEventListener('click', () => this.input.click());
        }
    }

    handleFile(file) {
        if (!file) return;

        // Validierung: Wenn es die Avatar-Zone ist, erlaube nur Bilder
        if (!this.isPreviewOnly && !file.type.startsWith('image/')) {
            notifier.show('Bitte nur Bilder hochladen!', 'error');
            return;
        }

        if (this.isPreviewOnly) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const img = this.zone.querySelector('.js-preview-img');
                const txt = this.zone.querySelector('.js-preview-text');

                if (img && file.type.startsWith('image/')) {
                    img.src = e.target.result;
                    img.classList.remove('u-hidden');
                }
                if (txt) {
                    // Für Dateien wie CSVs ändern wir einfach den Text
                    txt.innerText = `Ausgewählt: ${file.name}`;
                    txt.style.fontSize = '1.2rem';
                    txt.style.opacity = '1';
                    txt.style.color = 'var(--primary-color)';
                }

                // Wenn es eine Auto-Submit Zone ist (z.B. Bank CSV), direkt hochladen!
                if (this.isAutoSubmit && this.form) {
                    if (typeof this.form.requestSubmit === 'function') {
                        this.form.requestSubmit();
                    } else {
                        this.form.submit();
                    }
                }
            };
            reader.readAsDataURL(file);

            // Direktes Speichern (Klassischer Avatar-Upload in Profil/Benutzer)
        } else if (this.form) {
            // requestSubmit feuert das native Submit-Event im Gegensatz zu .submit()
            if (typeof this.form.requestSubmit === 'function') {
                this.form.requestSubmit();
            } else {
                this.form.submit();
            }
        }
    }
}
