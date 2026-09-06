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
    }

    /**
     * Zentraler Response-Handler, der Abstürze bei 500er HTML-Seiten verhindert.
     */
    async #handleResponse(response) {
        const isJson = response.headers.get('content-type')?.includes('application/json');

        if (!response.ok) {
            if (isJson) {
                const errData = await response.json();
                return { success: false, error: errData.error || `HTTP Fehler ${response.status}` };
            }
            return { success: false, error: `Server-Verbindungsfehler (HTTP ${response.status}).` };
        }

        if (isJson) {
            return await response.json();
        }

        return { success: false, error: 'Ungültige Server-Antwort (Kein JSON).' };
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

        try {
            // Endpoint-Pfade bereinigen (verhindert doppelte Slashes)
            const cleanEndpoint = endpoint.startsWith('/') ? endpoint.substring(1) : endpoint;
            const response = await fetch(`${this.baseUrl}${cleanEndpoint}`, {
                method: 'POST',
                headers: headers,
                body: body,
            });
            return await this.#handleResponse(response);
        } catch (error) {
            if (
                error.name !== 'TypeError' &&
                error.message !== 'NetworkError when attempting to fetch resource.'
            ) {
                console.error(`[API Error] POST /${endpoint} failed:`, error);
            }
            return { success: false, error: 'Netzwerkfehler. Server nicht erreichbar.' };
        }
    }

    async get(endpoint, params = '') {
        const query = params ? `?${params.toString()}` : '';
        const cleanEndpoint = endpoint.startsWith('/') ? endpoint.substring(1) : endpoint;

        try {
            const response = await fetch(`${this.baseUrl}${cleanEndpoint}${query}`, {
                headers: {
                    Accept: 'application/json',
                },
            });
            return await this.#handleResponse(response);
        } catch (error) {
            if (
                error.name !== 'TypeError' &&
                error.message !== 'NetworkError when attempting to fetch resource.'
            ) {
                console.error(`[API Error] GET /${endpoint} failed:`, error);
            }
            return { success: false, error: 'Netzwerkfehler. Server nicht erreichbar.' };
        }
    }
}

// Als Singleton exportieren
export const api = new ApiService();
