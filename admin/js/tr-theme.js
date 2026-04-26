(function () {
    'use strict';

    const warn = (scope, error) => {
        if (window.console && typeof window.console.warn === 'function') {
            window.console.warn('[tr-theme] ' + scope, error);
        }
    };

    const body = document.body;
    if (!body || !body.classList.contains('tr-admin')) return;

    const root = document.documentElement;
    const STORAGE_KEY = 'trTheme';
    const store = window.TypechoStore || null;

    let pref = 'system';

    const getPref = () => {
        const saved = store ? store.get(STORAGE_KEY, 'system') : 'system';
        if (saved === 'light' || saved === 'dark' || saved === 'system') {
            return saved;
        }

        return 'system';
    };

    const setPref = (next) => {
        pref = next;
        if (store) {
            store.set(STORAGE_KEY, next);
        }
    };

    const isSystemDark = () => {
        try {
            return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
        } catch (e) {
            warn('matchMedia', e);
            return false;
        }
    };

    const apply = () => {
        let dark = false;
        if (pref === 'dark') {
            dark = true;
        } else if (pref === 'system') {
            dark = isSystemDark();
        }

        if (dark) {
            root.classList.add('tr-theme-dark');
        } else {
            root.classList.remove('tr-theme-dark');
        }

        updateUi();
    };

    const themeBtn = document.getElementById('trThemeBtn');
    const themePop = document.getElementById('trThemePop');
    const themeItems = themePop
        ? Array.from(themePop.querySelectorAll('[data-tr-theme]'))
        : [];

    let isOpen = false;

    const getIconHref = (p) => {
        if (!themeBtn) return null;
        const use = themeBtn.querySelector('use');
        if (!use) return null;
        const href = use.getAttribute('href') || '';
        const base = href.split('#')[0] || '';

        if (p === 'light') return base + '#i-sun';
        if (p === 'dark') return base + '#i-moon';
        return base + '#i-monitor';
    };

    const getLabel = (p) => {
        if (p === 'light') return '浅色主题';
        if (p === 'dark') return '深色主题';
        return '跟随系统';
    };

    const updateUi = () => {
        if (themeBtn) {
            const use = themeBtn.querySelector('use');
            if (use) {
                use.setAttribute('href', getIconHref(pref));
            }
            themeBtn.setAttribute('aria-label', getLabel(pref));
        }

        themeItems.forEach((el) => {
            const v = el.getAttribute('data-tr-theme');
            el.setAttribute('aria-checked', v === pref ? 'true' : 'false');
        });
    };

    const togglePop = (next) => {
        if (!themeBtn || !themePop) return;

        isOpen = typeof next === 'boolean' ? next : !isOpen;

        themeBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        themePop.style.display = isOpen ? 'grid' : 'none';

        if (isOpen) {
            updateUi();
        }
    };

    pref = getPref();
    apply();

    if (window.matchMedia) {
        try {
            const media = window.matchMedia('(prefers-color-scheme: dark)');
            if (media.addEventListener) {
                media.addEventListener('change', () => {
                    if (pref === 'system') {
                        apply();
                    }
                });
            } else if (media.addListener) {
                media.addListener(() => {
                    if (pref === 'system') {
                        apply();
                    }
                });
            }
        } catch (e) {
            warn('watchSystemTheme', e);
        }
    }

    window.addEventListener('tr-theme-change', (e) => {
        const detail = e && e.detail ? e.detail : {};
        const next = detail.pref || getPref();
        pref = next;
        apply();
    });

    if (themeBtn && themePop) {
        themePop.style.display = 'none';

        themeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            togglePop();
        });

        themeItems.forEach((el) => {
            el.addEventListener('click', () => {
                const v = el.getAttribute('data-tr-theme') || 'system';
                setPref(v);
                apply();
                togglePop(false);
            });
        });

        document.addEventListener('click', (e) => {
            if (!isOpen) return;
            if (e.target === themeBtn || themeBtn.contains(e.target)) return;
            if (e.target === themePop || themePop.contains(e.target)) return;
            togglePop(false);
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                togglePop(false);
            }
        });
    }

})();
