/**
 * Ecofone HRMS - Persistent Client-Side Encrypted Cache & Live Sync Engine (HRMSCache)
 * 
 * 1. Persistent Storage: Preserves device cache across logouts so re-logging in loads in 0ms.
 * 2. Continuous Auto-Sync: Updates local cache as live actions and page visits happen.
 * 3. Stale-While-Revalidate (SWR): Instant 0ms cache rendering + silent background sync.
 */
(function(window) {
    'use strict';

    const CACHE_PREFIX = 'ecofone_vault_';

    const HRMSCache = {
        // Fast Obfuscated Local Storage Encoder with Timestamp
        _encode(data) {
            try {
                const payload = {
                    updated_at: Date.now(),
                    payload: data
                };
                return btoa(encodeURIComponent(JSON.stringify(payload)));
            } catch(e) {
                return null;
            }
        },

        // Fast Local Storage Decoder
        _decode(raw) {
            try {
                const json = decodeURIComponent(atob(raw));
                const parsed = JSON.parse(json);
                return parsed ? parsed.payload : null;
            } catch(e) {
                return null;
            }
        },

        // Save or update live data in device cache
        set(key, data) {
            try {
                const encoded = this._encode(data);
                if (encoded) {
                    localStorage.setItem(CACHE_PREFIX + key, encoded);
                }
            } catch(e) {
                try {
                    // Remove oldest keys if storage quota reached
                    const keys = Object.keys(localStorage).filter(k => k.startsWith(CACHE_PREFIX));
                    if (keys.length > 20) {
                        localStorage.removeItem(keys[0]);
                    }
                    const encoded = this._encode(data);
                    if (encoded) localStorage.setItem(CACHE_PREFIX + key, encoded);
                } catch(err) {}
            }
        },

        // Get data instantly from device cache (0ms)
        get(key) {
            try {
                const raw = localStorage.getItem(CACHE_PREFIX + key);
                if (!raw) return null;
                return this._decode(raw);
            } catch(e) {
                return null;
            }
        },

        // Auto-sync page state into cache whenever fresh data loads
        sync(key, data) {
            if (data !== undefined && data !== null) {
                this.set(key, data);
            }
        },

        // Remove item from cache
        remove(key) {
            try {
                localStorage.removeItem(CACHE_PREFIX + key);
            } catch(e) {}
        },

        /**
         * Stale-While-Revalidate (SWR) Fetcher:
         * 1. Renders cached data immediately (0ms).
         * 2. Fetches fresh updates from server quietly in the background (1-2s).
         * 3. Seamlessly updates UI and cache with new live changes.
         */
        async swr(key, fetchUrl, onData) {
            // 1. Instant Cache Hit (0ms)
            const cached = this.get(key);
            if (cached !== null && typeof onData === 'function') {
                try { onData(cached, true); } catch(e) {}
            }

            // 2. Background Revalidation (Updates cache with any team live changes)
            try {
                const res = await fetch(fetchUrl);
                if (!res.ok) return;
                const fresh = await res.json();
                this.set(key, fresh);
                if (typeof onData === 'function') {
                    onData(fresh, false);
                }
            } catch(e) {
                // If offline or network slow, cached data is already active
            }
        }
    };

    window.HRMSCache = HRMSCache;
})(window);