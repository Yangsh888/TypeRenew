(function () {
    'use strict';

    var body = document.body;
    if (!body || !body.classList.contains('body-100')) return;

    var themes = Array.isArray(window.__trAuthThemes) ? window.__trAuthThemes : [];
    if (!themes.length) return;

    var key = 'trAuthTheme';
    var btn = document.getElementById('trAuthThemeBtn');
    var menu = document.getElementById('trAuthThemeMenu');
    var store = window.TypechoStore || null;
    if (!btn || !menu) return;

    var allClasses = themes.map(function (t) { return 'tr-auth-' + t.id; });

    function setTheme(id) {
        allClasses.forEach(function (c) { body.classList.remove(c); });
        body.classList.add('tr-auth-' + id);
        if (store) {
            store.set(key, id);
        }
        var found = themes.find(function (t) { return t.id === id; });
        btn.textContent = found ? found.name : id;
    }

    function getSaved() {
        return store ? store.get(key, null) : null;
    }

    var saved = getSaved();
    if (saved && allClasses.indexOf('tr-auth-' + saved) !== -1) {
        setTheme(saved);
    } else {
        var current = themes.find(function (t) { return body.classList.contains('tr-auth-' + t.id); });
        setTheme(current ? current.id : 'forest');
    }

    var open = false;

    function renderMenu() {
        menu.innerHTML = '';
        themes.forEach(function (t) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'tr-auth-switch-item';
            b.setAttribute('role', 'menuitem');
            b.textContent = t.name;
            b.addEventListener('click', function () {
                setTheme(t.id);
                toggle(false);
            });
            menu.appendChild(b);
        });
    }

    function toggle(next) {
        open = typeof next === 'boolean' ? next : !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        menu.style.display = open ? 'grid' : 'none';
        if (open) {
            renderMenu();
            var items = menu.querySelectorAll('[role="menuitem"]');
            if (items.length) items[0].focus();
        }
    }

    btn.addEventListener('click', function () { toggle(); });
    document.addEventListener('click', function (e) {
        if (!open) return;
        if (e.target === btn || btn.contains(e.target) || e.target === menu || menu.contains(e.target)) return;
        toggle(false);
    });
    document.addEventListener('keydown', function (e) {
        if (!open) return;
        var items = Array.prototype.slice.call(menu.querySelectorAll('[role="menuitem"]'));
        var index = items.indexOf(document.activeElement);
        if (e.key === 'Escape') {
            e.preventDefault();
            toggle(false);
            btn.focus();
        } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            var step = e.key === 'ArrowDown' ? 1 : -1;
            items[(index + step + items.length) % items.length].focus();
        } else if (e.key === 'Home' && items.length) {
            e.preventDefault();
            items[0].focus();
        } else if (e.key === 'End' && items.length) {
            e.preventDefault();
            items[items.length - 1].focus();
        }
    });
})();
