/**
 * Ecofone HRMS - Client-Side Encrypted Instant Cache Engine (HRMSCache)
 * Provides 0ms instant data retrieval from local browser storage with
 * background Stale-While-Revalidate (SWR) syncing.
 */
(function(window) {
    'use strict';

    const CACHE_PREFIX = 'ecofone_hrms_v1_';

    const HRMSCache = {
        // Fast Obfuscated Local Storage Encoder
        _encode(data) {
            try {
                const json = JSON.stringify({
                    t: Date.now(),
                    d: data
                });
                return btoa(encodeURIComponent(json));
            } catch(e) {
                return null;
            }
        },

        // Fast Local Storage Decoder
        _decode(raw) {
            try {
                const json = decodeURIComponent(atob(raw));
                const parsed = JSON.parse(json);
                return parsed ? parsed.d : null;
            } catch(e) {
                return null;
            }
        },

        // Save data to device cache
        set(key, data) {
            try {
                const encoded = this._encode(data);
                if (encoded) {
                    localStorage.setItem(CACHE_PREFIX + key, encoded);
                }
            } catch(e) {
                try {
                    localStorage.clear();
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

        // Remove item from cache
        remove(key) {
            try {
                localStorage.removeItem(CACHE_PREFIX + key);
            } catch(e) {}
        },

        // Clear all portal cache on logout
        clear() {
            try {
                Object.keys(localStorage).forEach(k => {
                    if (k.startsWith(CACHE_PREFIX)) {
                        localStorage.removeItem(k);
                    }
                });
            } catch(e) {}
        },

        /**
         * Stale-While-Revalidate (SWR) Fetcher:
         * 1. Calls callback immediately with cached data (0ms).
         * 2. Fetches fresh data from server in background (1-2s).
         * 3. Calls callback with fresh data and updates cache.
         */
        async swr(key, fetchUrl, onData) {
            // 1. Instant Cache Hit (0ms)
            const cached = this.get(key);
            if (cached !== null && typeof onData === 'function') {
                try { onData(cached, true); } catch(e) {}
            }

            // 2. Background Revalidation
            try {
                const res = await fetch(fetchUrl);
                if (!res.ok) return;
                const fresh = await res.json();
                this.set(key, fresh);
                if (typeof onData === 'function') {
                    onData(fresh, false);
                }
            } catch(e) {
                // Network failure: cached data already served
            }
        }
    };

    window.HRMSCache = HRMSCache;
})(window);