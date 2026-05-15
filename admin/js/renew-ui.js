(function () {
    'use strict';

    const body = document.body;
    if (!body || !body.classList.contains('tr-admin')) {
        return;
    }

    const notices = (() => {
        let host = null;

        const findMount = () => {
            return document.querySelector('.main > .body.container')
                || document.querySelector('.body.container')
                || document.querySelector('main.main')
                || document.body;
        };

        const ensureHost = () => {
            if (host && host.isConnected) {
                return host;
            }
            const mount = findMount();
            const existing = mount.querySelector('.tr-notice-host');
            if (existing) {
                host = existing;
                return host;
            }
            host = document.createElement('div');
            host.className = 'tr-notice-host';
            host.setAttribute('aria-live', 'polite');
            host.setAttribute('aria-relevant', 'additions text');

            if (mount === document.body) {
                const topbar = document.querySelector('.tr-topbar');
                if (topbar && topbar.parentNode) {
                    topbar.parentNode.insertBefore(host, topbar.nextSibling);
                    return host;
                }
            }

            mount.insertBefore(host, mount.firstChild);
            return host;
        };

        const renderText = (messages) => {
            const items = Array.isArray(messages) ? messages : [messages];
            const list = items
                .filter((m) => m != null && String(m).trim() !== '')
                .map((m) => String(m)
                    .replace(/\r\n?/g, '\n')
                    .split('\n')
                    .map((line) => line.trim())
                    .filter((line) => line !== '')
                    .join('\n'));
            return list.join('\n');
        };

        const renderHtml = (messages) => {
            const items = Array.isArray(messages) ? messages : [messages];
            const html = items
                .filter((m) => m != null && String(m).trim() !== '')
                .map((m) => String(m))
                .join('<br>');
            return html;
        };

        const removeWithAnim = (el) => {
            if (!el || el.classList.contains('tr-leaving')) {
                return;
            }
            el.classList.add('tr-leaving');
            window.setTimeout(() => {
                if (el.parentNode) {
                    el.parentNode.removeChild(el);
                }
            }, 180);
        };

        const show = (type, messages, options) => {
            const opts = options && typeof options === 'object' ? options : {};
            const duration = Number.isFinite(opts.duration) ? opts.duration : 5200;
            const el = document.createElement('div');
            el.className = 'tr-notice tr-' + String(type || 'notice');
            el.setAttribute('role', 'status');

            const content = document.createElement('div');
            content.className = 'tr-notice-content';

            const close = document.createElement('button');
            close.type = 'button';
            close.className = 'tr-notice-close';
            close.setAttribute('aria-label', '关闭');
            close.textContent = '×';

            const badge = document.createElement('div');
            badge.className = 'tr-notice-badge';
            badge.textContent = opts.title
                ? String(opts.title)
                : (type === 'success' ? '成功' : (type === 'error' ? '错误' : '提示'));

            const text = document.createElement('div');
            text.className = 'tr-notice-text';
            if (opts.allowHtml) {
                text.innerHTML = renderHtml(messages);
            } else {
                text.textContent = renderText(messages);
            }

            content.appendChild(badge);
            content.appendChild(text);

            el.appendChild(content);
            el.appendChild(close);

            const h = ensureHost();
            h.appendChild(el);

            close.addEventListener('click', () => removeWithAnim(el));
            if (duration > 0) {
                window.setTimeout(() => removeWithAnim(el), duration);
            }

            return el;
        };

        return { show };
    })();

    const highlight = (id) => {
        if (!id) {
            return;
        }
        const el = document.getElementById(String(id));
        if (!el) {
            return;
        }
        el.classList.remove('tr-highlight');
        void el.offsetWidth;
        el.classList.add('tr-highlight');
        window.setTimeout(() => {
            el.classList.remove('tr-highlight');
        }, 1200);
    };

    if (!window.TypechoNotice) {
        window.TypechoNotice = {};
    }
    window.TypechoNotice.show = (type, messages, options) => notices.show(type, messages, options);
    window.TypechoNotice.highlight = highlight;

    const sidebar = document.querySelector('.tr-shell');
    const overlay = document.querySelector('.tr-overlay');
    const btnNav = document.querySelector('[data-tr-nav]');
    const mq = window.matchMedia('(max-width: 768px)');

    const store = window.TypechoStore || null;

    const setCollapsed = (collapsed, persist = true) => {
        if (mq.matches) {
            body.classList.remove('tr-sidebar-collapsed');
            body.classList.toggle('tr-sidebar-open', !collapsed);
            body.classList.toggle('tr-scroll-lock', body.classList.contains('tr-sidebar-open'));
            if (btnNav) {
                btnNav.setAttribute('aria-expanded', body.classList.contains('tr-sidebar-open') ? 'true' : 'false');
            }
            return;
        }
        body.classList.remove('tr-sidebar-open');
        body.classList.toggle('tr-sidebar-collapsed', collapsed);
        body.classList.remove('tr-scroll-lock');
        if (btnNav) {
            btnNav.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
        if (persist && store) {
            store.set('trSidebarCollapsed', collapsed ? '1' : '0');
        }
    };

    const initState = () => {
        if (mq.matches) {
            setCollapsed(true, false);
            return;
        }
        const saved = store ? store.get('trSidebarCollapsed', null) : null;
        setCollapsed(saved === '1', false);
    };

    const openMobile = () => {
        if (!mq.matches) {
            return;
        }
        body.classList.add('tr-sidebar-open');
        body.classList.add('tr-scroll-lock');
        if (btnNav) {
            btnNav.setAttribute('aria-expanded', 'true');
        }
    };

    const closeMobile = () => {
        body.classList.remove('tr-sidebar-open');
        body.classList.remove('tr-scroll-lock');
        if (btnNav) {
            btnNav.setAttribute('aria-expanded', 'false');
        }
    };

    initState();
    if (btnNav) {
        btnNav.setAttribute('aria-expanded', mq.matches
            ? (body.classList.contains('tr-sidebar-open') ? 'true' : 'false')
            : (body.classList.contains('tr-sidebar-collapsed') ? 'false' : 'true'));
    }

    if (btnNav) {
        btnNav.addEventListener('click', () => {
            if (mq.matches) {
                const open = body.classList.contains('tr-sidebar-open');
                if (open) {
                    closeMobile();
                } else {
                    openMobile();
                }
                return;
            }
            const now = body.classList.contains('tr-sidebar-collapsed');
            setCollapsed(!now);
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobile);
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeMobile();
        }
    });

    if (mq.addEventListener) {
        mq.addEventListener('change', initState);
    } else if (mq.addListener) {
        mq.addListener(initState);
    }

    const initProfileCards = () => {
        if (!body.classList.contains('tr-page-profile')) {
            return;
        }
        const cards = document.querySelectorAll('[data-tr-form-card]');
        if (!cards.length) {
            return;
        }
        cards.forEach((card) => {
            const actions = card.querySelector('[data-tr-form-actions]');
            const form = card.querySelector('form');
            if (!actions || !form) {
                return;
            }
            const formId = form.getAttribute('id');
            if (!formId) {
                return;
            }
            if (actions.dataset.trActionsReady === '1') {
                return;
            }
            const submit = form.querySelector('ul.typecho-option-submit button[type="submit"], ul.typecho-option-submit input[type="submit"], button[type="submit"], input[type="submit"]');
            if (!submit) {
                return;
            }
            if (!actions.querySelector('[data-tr-submit]')) {
                const btn = document.createElement('button');
                btn.type = 'submit';
                btn.setAttribute('form', formId);
                btn.setAttribute('data-tr-submit', '1');
                btn.className = submit.className && String(submit.className).trim() ? submit.className : 'btn primary';
                const label = submit.tagName.toLowerCase() === 'input' ? submit.value : submit.textContent;
                btn.textContent = label && String(label).trim() ? String(label).trim() : '保存';
                actions.appendChild(btn);
            }
            const wrap = submit.closest('ul.typecho-option-submit, p.typecho-option-submit');
            if (wrap) {
                wrap.classList.add('tr-submit-hidden');
            }
            actions.dataset.trActionsReady = '1';
        });
    };

    initProfileCards();

    if (sidebar) {
        sidebar.addEventListener('click', (e) => {
            if (!mq.matches) {
                return;
            }
            const a = e.target && e.target.closest ? e.target.closest('a') : null;
            if (a) {
                closeMobile();
            }
        });
    }
})();
