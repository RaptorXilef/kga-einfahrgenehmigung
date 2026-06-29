# Externe Abhängigkeiten & APIs

Das System ist so konzipiert, dass es serverseitig autark arbeiten kann (Zero-Dependency-Architektur via PHP). Es gibt jedoch einige externe APIs und Frontend-Ressourcen, die über CDNs geladen werden.

## 1. Frontend-Bibliotheken (via CDN)

* **Chart.js:** `https://cdnjs.cloudflare.com/...` (Rendert die Dashboard-Statistiken).
* **DOMPurify:** `https://cdnjs.cloudflare.com/...` (Verhindert XSS beim Parsen von Markdown).
* **Marked.js:** `https://cdnjs.cloudflare.com/...` (Wandelt die `CHANGELOG.md` für Updates in HTML um).

## 2. Externe APIs (Server & Client)

* **QR-Code Generierung:** `https://api.qrserver.com` (Generiert die QR-Codes für Druckansichten und EPC-Bank-GiroCodes on-the-fly).
* **Zahlungsabwicklung (PayPal):** `https://www.paypal.com`, `https://api-m.paypal.com` (REST API für Order-Capturing) sowie `https://www.paypalobjects.com` für UI-Elemente.
* **Google Analytics 4:** `https://www.googletagmanager.com` und Serverseitiges Event-Tracking an `https://www.google-analytics.com`. *Hinweis:* Wird streng über die `AnalyticsMiddleware` und das Consent-Banner blockiert, sofern keine Zustimmung vorliegt.
* **GitHub API:** `https://api.github.com` (Wird vom `GitHubUpdaterService` verwendet, um Release-Zips für Over-The-Air Systemupdates herunterzuladen).
