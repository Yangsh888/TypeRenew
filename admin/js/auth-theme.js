(function () {
    'use strict';

    var body = document.body;
    if (!body || !body.classList.contains('body-100')) return;

    var themes = [
        { id: 'forest', name: '森林' },
        { id: 'slate', name: '石板' },
        { id: 'ember', name: '余烬' },
        { id: 'moss', name: '苔绿' },
        { id: 'sand', name: '砂岩' },
        { id: 'rose', name: '蔷薇' },
        { id: 'ocean', name: '海雾' },
        { id: 'ink', name: '墨影' },
        { id: 'gold', name: '鎏金' },
        { id: 'coral', name: '珊瑚' },
        { id: 'cypress', name: '柏影' },
        { id: 'lilac', name: '丁香' }
    ];

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
        if (open) renderMenu();
    }

    btn.addEventListener('click', function () { toggle(); });
    document.addEventListener('click', function (e) {
        if (!open) return;
        if (e.target === btn || btn.contains(e.target) || e.target === menu || menu.contains(e.target)) return;
        toggle(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') toggle(false);
    });
})();
