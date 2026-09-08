/* ============================================================
   CITY PLANNER — API CLIENT
   AJAX wrapper with CSRF and error handling
   ============================================================ */
'use strict';

const API = {
    baseUrl: ((typeof window !== 'undefined' && window.BASE_URL) || document.querySelector('meta[name="base-url"]')?.content || './') + 'api/',

    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.content ||
               document.querySelector('[name="_csrf_token"]')?.value || '';
    },

    async request(endpoint, options = {}) {
        const url = this.baseUrl + endpoint;
        const headers = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': this.getCsrfToken(),
            ...(options.headers || {}),
        };

        try {
            const res = await fetch(url, {
                method: options.method || 'GET',
                headers,
                body: options.body ? JSON.stringify(options.body) : undefined,
                credentials: 'same-origin',
            });

            if (res.status === 401) {
                const base = (typeof window !== 'undefined' && window.BASE_URL) || document.querySelector('meta[name="base-url"]')?.content || './';
                window.location.href = base + 'index.php';
                return null;
            }

            if (!res.ok && res.status !== 422) {
                throw new Error(`HTTP ${res.status}: ${res.statusText}`);
            }

            const text = await res.text();
            try {
                return JSON.parse(text);
            } catch {
                console.error('Non-JSON response:', text.substring(0, 200));
                throw new Error('Invalid JSON response from server.');
            }
        } catch (err) {
            console.error(`API Error [${endpoint}]:`, err.message);
            throw err;
        }
    },

    get(endpoint, params = {}) {
        const qs = Object.keys(params).length
            ? '?' + new URLSearchParams(params).toString()
            : '';
        return this.request(endpoint + qs);
    },

    post(endpoint, data = {}) {
        return this.request(endpoint, { method: 'POST', body: data });
    },

    put(endpoint, data = {}) {
        return this.request(endpoint, { method: 'PUT', body: data });
    },

    delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    },
};
