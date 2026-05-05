# Erweitern der Logik so, dass ein Gutschein nicht mehr nur "da" ist, sondern Regeln hat

``` PHP
// Beispiel der neuen Datenstruktur in vouchers.json
"GUT-AB12-CD34": {
    "code": "GUT-AB12-CD34",
    "type": "percent", // 'free', 'fixed', 'percent'
    "value": 50.00,    // 50% Rabatt oder 5.00€ Festpreis
    "multi_use": true, // Kann öfter verwendet werden
    "max_uses": 10,    // Maximal 10 Mal
    "uses_count": 2,   // Bisher 2 Mal genutzt
    "expires_at": "2026-12-31", // Gültigkeitsdatum
    "data": { ... prefill ... }
}
```
