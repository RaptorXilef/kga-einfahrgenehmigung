/**
 * @file form-handler.js
 * Steuert das interaktive Verhalten des Antragsformulars (v0.4.0).
 */

document.addEventListener('DOMContentLoaded', () => {
    const typSelect = document.getElementById('typ');
    const groupFirma = document.getElementById('group_firma');
    const kennzeichenInput = document.getElementById('kennzeichen');
    const labelKennzeichen = document.getElementById('label_kennzeichen');

    // 1. Toggle PKW / LKW
    typSelect.addEventListener('change', (e) => {
        const isLkw = e.target.value === 'lkw';
        groupFirma.classList.toggle('u-hidden', !isLkw);
        labelKennzeichen.innerText = isLkw ? 'Amtl. Kennzeichen (Optional)' : '* Amtl. Kennzeichen';
        kennzeichenInput.required = !isLkw;
    });

    // 2. Echtzeit Kennzeichen-Formatierung (BHD7398 -> B-HD 7398)
    kennzeichenInput.addEventListener('blur', (e) => {
        let val = e.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        // Grobe Formatierungshilfe
        if (val.length > 3) {
            e.target.value = val.replace(/^([A-Z]{1,3})([A-Z]{0,2})([0-9]{1,4})$/, '$1-$2 $3').trim();
        }
    });

    // 3. Parzellen-Padding Vorschau (20 -> 0020)
    document.getElementById('parzelle').addEventListener('blur', (e) => {
        if (e.target.value) {
            e.target.value = e.target.value.toString().padStart(4, '0');
        }
    });
});
