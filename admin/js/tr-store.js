(function () {
    'use strict';

    const cache = {
        local: null,
        session: null
    };
    const resolved = {
        local: false,
        session: false
    };

    function warn(action, error) {
        if (window.console && typeof window.console.warn === 'function') {
            window.console.warn('[tr-store] ' + action, error);
        }
    }

    function getStore(type) {
        const key = type === 'session' ? 'session' : 'local';
        if (resolved[key]) {
            return cache[key];
        }

        resolved[key] = true;

        try {
            cache[key] = key === 'session' ? window.sessionStorage : window.localStorage;
        } catch (error) {
            cache[key] = null;
            warn(key + ':access', error);
        }

        return cache[key];
    }

    function get(key, defaultValue, type) {
        const target = getStore(type);
        if (!target) {
            return defaultValue;
        }

        try {
            const value = target.getItem(key);
            return value !== null ? value : defaultValue;
        } catch (error) {
            warn((type || 'local') + ':get:' + key, error);
            return defaultValue;
        }
    }

    function set(key, value, type) {
        const target = getStore(type);
        if (!target) {
            return false;
        }

        try {
            target.setItem(key, value);
            return true;
        } catch (error) {
            warn((type || 'local') + ':set:' + key, error);
            return false;
        }
    }

    function remove(key, type) {
        const target = getStore(type);
        if (!target) {
            return false;
        }

        try {
            target.removeItem(key);
            return true;
        } catch (error) {
            warn((type || 'local') + ':remove:' + key, error);
            return false;
        }
    }

    function getJson(key, defaultValue, type) {
        const raw = get(key, null, type);
        if (raw === null || raw === '') {
            return defaultValue;
        }

        try {
            const parsed = JSON.parse(raw);
            return parsed !== null ? parsed : defaultValue;
        } catch (error) {
            warn((type || 'local') + ':json:get:' + key, error);
            return defaultValue;
        }
    }

    function setJson(key, value, type) {
        try {
            return set(key, JSON.stringify(value), type);
        } catch (error) {
            warn((type || 'local') + ':json:set:' + key, error);
            return false;
        }
    }

    window.TypechoStore = {
        get: (key, defaultValue) => get(key, defaultValue, 'local'),
        set: (key, value) => set(key, value, 'local'),
        remove: (key) => remove(key, 'local'),
        getJson: (key, defaultValue) => getJson(key, defaultValue, 'local'),
        setJson: (key, value) => setJson(key, value, 'local'),
        sessionGet: (key, defaultValue) => get(key, defaultValue, 'session'),
        sessionSet: (key, value) => set(key, value, 'session'),
        sessionRemove: (key) => remove(key, 'session'),
        sessionGetJson: (key, defaultValue) => getJson(key, defaultValue, 'session'),
        sessionSetJson: (key, value) => setJson(key, value, 'session')
    };
})();
