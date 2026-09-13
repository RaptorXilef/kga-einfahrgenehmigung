/**
 * Zentraler API-Service als Singleton.
 * Greift sicher auf window.KGA_CONFIG zu und wickelt alle fetch-Requests ab.
 * Fängt Netzwerkfehler und fehlerhaftes JSON zentral ab.
 */

class ApiService {
    constructor() {
        // Fallback, falls KGA_CONFIG mal fehlen sollte (Ausfallsicherheit)
        this.config = window.KGA_CONFIG || { baseUrl: '/', csrfToken: '' };
        this.baseUrl = this.config.baseUrl.endsWith('/')
            ? this.config.baseUrl
            : `${this.config.baseUrl}/`;
        this.csrfToken = this.config.csrfToken;
        // Standard-Timeout für alle Anfragen: 15 Sekunden
        this.timeoutMs = 15000;
    }

    /**
     * Zentraler Response-Handler, der Abstürze bei 500er HTML-Seiten verhindert.
     */
    async #handleResponse(response) {
        const isJson = response.headers.get('content-type')?.includes('application/json');

        if (!response.ok) {
            if (isJson) {
                try {
                    const errData = await response.json();
                    return {
                        success: false,
                        error: errData.error || `HTTP Fehler ${response.status}`,
                    };
                } catch {
                    return {
                        success: false,
                        error: `JSON Parse-Fehler (HTTP ${response.status}).`,
                    };
                }
            }
            return { success: false, error: `Server-Verbindungsfehler (HTTP ${response.status}).` };
        }

        if (isJson) {
            try {
                return await response.json();
            } catch {
                return { success: false, error: 'Ungültige Server-Antwort (Defektes JSON).' };
            }
        }

        return { success: false, error: 'Ungültige Server-Antwort (Kein JSON).' };
    }

    #normalizeEndpoint(endpoint) {
        // Endpoint-Pfade bereinigen (verhindert doppelte Slashes)
        return endpoint.startsWith('/') ? endpoint.substring(1) : endpoint;
    }

    async post(endpoint, bodyData = null) {
        let body = bodyData;
        const headers = {
            Accept: 'application/json',
            'X-CSRF-Token': this.csrfToken,
        };

        // Wenn FormData übergeben wird, fügen wir den Token sicherheitshalber auch dort ein
        if (body instanceof FormData) {
            if (!body.has('csrf_token')) {
                body.append('csrf_token', this.csrfToken);
            }
        } else if (body !== null && !(body instanceof URLSearchParams)) {
            // Wenn es ein normales Objekt ist, senden wir es als JSON
            headers['Content-Type'] = 'application/json';
            if (typeof body === 'object') {
                body = JSON.stringify(body);
            }
        }

        // Modernes Timeout-Handling ohne Memory-Leak-Gefahr
        const signal = AbortSignal.timeout(this.timeoutMs);
        const cleanEndpoint = this.#normalizeEndpoint(endpoint);

        try {
            const response = await fetch(`${this.baseUrl}${cleanEndpoint}`, {
                method: 'POST',
                headers: headers,
                body: body,
                signal: signal,
            });
            return await this.#handleResponse(response);
        } catch (error) {
            // Wenn der Abbruch durch unseren Timeout ausgelöst wurde
            if (error.name === 'TimeoutError') {
                return {
                    success: false,
                    error: 'Zeitüberschreitung. Die Verbindung war zu langsam.',
                };
            }

            // Fehler, die nicht von Abbrüchen stammen, im Log hinterlassen
            if (error.name !== 'TypeError' && error.name !== 'AbortError') {
                console.error(`[API Error] POST /${cleanEndpoint} failed:`, error);
            }

            return {
                success: false,
                error: 'Netzwerkfehler. Server nicht erreichbar oder Verbindung abgebrochen.',
            };
        }
    }

    async get(endpoint, params = '') {
        const query = params ? `?${params.toString()}` : '';
        const cleanEndpoint = this.#normalizeEndpoint(endpoint);
        const signal = AbortSignal.timeout(this.timeoutMs);

        try {
            const response = await fetch(`${this.baseUrl}${cleanEndpoint}${query}`, {
                headers: { Accept: 'application/json' },
                signal: signal,
            });
            return await this.#handleResponse(response);
        } catch (error) {
            if (error.name === 'TimeoutError') {
                return {
                    success: false,
                    error: 'Zeitüberschreitung. Die Verbindung war zu langsam.',
                };
            }

            if (error.name !== 'TypeError' && error.name !== 'AbortError') {
                console.error(`[API Error] GET /${cleanEndpoint} failed:`, error);
            }

            return {
                success: false,
                error: 'Netzwerkfehler. Server nicht erreichbar oder Verbindung abgebrochen.',
            };
        }
    }
}

// Als Singleton exportieren
export const api = new ApiService();
