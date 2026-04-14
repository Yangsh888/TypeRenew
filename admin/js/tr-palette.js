(function () {
    'use strict';

    const body = document.body;
    if (!body || !body.classList.contains('tr-admin')) return;

    const wrap = document.getElementById('trCmd');
    const overlay = document.getElementById('trCmdOverlay');
    const input = document.getElementById('trCmdInput');
    const list = document.getElementById('trCmdList');
    const hint = document.getElementById('trCmdHint');
    const btn = document.getElementById('trCmdBtn');
    if (!wrap || !overlay || !input || !list || !hint) return;

    const STORAGE_KEY_RECENT = 'trCmdRecent';
    const STORAGE_KEY_HISTORY = 'trCmdHistory';
    const store = window.TypechoStore || null;
    const MAX_RECENT = 10;
    const MAX_HISTORY = 20;
    const MAX_RESULTS = 16;
    const DEBOUNCE_MS = 80;

    let categories = new Map();
    let commands = new Map();
    let results = [];
    let activeIndex = -1;
    let isOpen = false;
    let debounceTimer = null;
    let iconsUrl = '';
    let initialized = false;

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function textOf(el) {
        return (el && el.textContent ? el.textContent : '').replace(/\s+/g, ' ').trim();
    }

    function storageGet(key, defaultValue) {
        return store ? store.getJson(key, defaultValue) : defaultValue;
    }

    function storageSet(key, value) {
        if (store) {
            store.setJson(key, value);
        }
    }

    function readRecent() {
        const arr = storageGet(STORAGE_KEY_RECENT, []);
        if (!Array.isArray(arr)) return [];
        return arr.filter(x => typeof x === 'string' && x.length > 0).slice(0, MAX_RECENT);
    }

    function writeRecent(id) {
        const cur = readRecent().filter(x => x !== id);
        cur.unshift(id);
        storageSet(STORAGE_KEY_RECENT, cur.slice(0, MAX_RECENT));
    }

    function readHistory() {
        const arr = storageGet(STORAGE_KEY_HISTORY, []);
        if (!Array.isArray(arr)) return [];
        return arr.filter(x => x && typeof x === 'object' && x.id).slice(0, MAX_HISTORY);
    }

    function writeHistory(cmd) {
        if (!cmd || !cmd.id) return;
        const cur = readHistory().filter(x => x.id !== cmd.id);
        cur.unshift({ id: cmd.id, title: cmd.title, category: cmd.category, time: Date.now() });
        storageSet(STORAGE_KEY_HISTORY, cur.slice(0, MAX_HISTORY));
    }

    function registerCommand(cmd) {
        if (!cmd || !cmd.id) return;
        const fullCmd = {
            type: 'action',
            category: 'tools',
            icon: null,
            shortcut: null,
            keywords: [],
            access: 'subscriber',
            confirm: null,
            url: null,
            target: null,
            action: null,
            ...cmd
        };
        commands.set(cmd.id, fullCmd);
    }

    function registerCommands(cmdList) {
        if (!Array.isArray(cmdList)) return;
        cmdList.forEach(registerCommand);
    }

    function initFromConfig() {
        if (initialized) return;

        const config = window.__trPaletteConfig;
        if (!config) {
            initFallback();
            return;
        }

        if (config.iconsUrl) {
            iconsUrl = config.iconsUrl;
        }

        if (config.categories) {
            Object.values(config.categories).forEach(cat => {
                categories.set(cat.id, cat);
            });
        }

        if (config.commands) {
            Object.values(config.commands).forEach(cmd => {
                registerCommand(cmd);
            });
        }

        buildNavCommands();
        initialized = true;
    }

    function initFallback() {
        const fallbackCategories = {
            nav: { id: 'nav', name: '导航', order: 10, icon: 'i-external' },
            create: { id: 'create', name: '创建', order: 20, icon: 'i-plus' },
            manage: { id: 'manage', name: '管理', order: 30, icon: 'i-folder' },
            settings: { id: 'settings', name: '设置', order: 40, icon: 'i-gear' },
            appearance: { id: 'appearance', name: '外观', order: 50, icon: 'i-palette' },
            tools: { id: 'tools', name: '工具', order: 60, icon: 'i-zap' },
            interface: { id: 'interface', name: '界面', order: 70, icon: 'i-monitor' },
            help: { id: 'help', name: '帮助', order: 80, icon: 'i-info' }
        };

        Object.values(fallbackCategories).forEach(cat => {
            categories.set(cat.id, cat);
        });

        const use = document.querySelector('.tr-nav .tr-ico use');
        if (use) {
            const href = use.getAttribute('href') || '';
            iconsUrl = href.split('#')[0] || '';
        }

        buildNavCommands();
        initialized = true;
    }

    function toggleSidebar() {
        const key = 'trSidebarCollapsed';
        const next = !body.classList.contains('tr-sidebar-collapsed');
        body.classList.toggle('tr-sidebar-collapsed', next);
        storageSet(key, next ? '1' : '0');
        window.dispatchEvent(new Event('resize'));
    }

    function setThemePref(pref) {
        storageSet('trTheme', pref);
        const evt = new CustomEvent('tr-theme-change', { detail: { pref: pref } });
        window.dispatchEvent(evt);
        if (window.TypechoTheme && typeof window.TypechoTheme.apply === 'function') {
            window.TypechoTheme.apply();
        }
    }

    function toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(() => {});
        } else {
            document.exitFullscreen().catch(() => {});
        }
    }

    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function refreshPage() {
        window.location.reload();
    }

    function clearCache() {
        if (!store) {
            showNotice('error', '清除缓存失败');
            return;
        }

        ['trTheme', 'trSidebarCollapsed', 'trCmdRecent', 'trCmdHistory'].forEach(k => {
            store.remove(k);
        });
        showNotice('success', '前端缓存已清除');
    }

    function showNotice(type, message) {
        if (window.TypechoNotice && window.TypechoNotice.show) {
            window.TypechoNotice.show(type, [message]);
        } else {
            alert(message);
        }
    }

    function executeAction(actionStr) {
        if (!actionStr) return false;

        const parts = actionStr.split(':');
        if (parts.length !== 2) return false;

        const [type, value] = parts;

        switch (type) {
            case 'theme':
                setThemePref(value);
                return true;
            case 'sidebar':
                if (value === 'toggle') {
                    toggleSidebar();
                    return true;
                }
                break;
            case 'fullscreen':
                if (value === 'toggle') {
                    toggleFullscreen();
                    return true;
                }
                break;
            case 'scroll':
                if (value === 'top') {
                    scrollToTop();
                    return true;
                }
                break;
            case 'page':
                if (value === 'refresh') {
                    refreshPage();
                    return true;
                }
                break;
            case 'cache':
                if (value === 'clear') {
                    clearCache();
                    return true;
                }
                break;
        }

        return false;
    }

    function buildNavCommands() {
        const navElements = document.querySelectorAll('.tr-nav a[href]');
        const seen = new Set();

        navElements.forEach(a => {
            const name = textOf(a);
            const href = a.getAttribute('href') || '';
            if (!name || !href || seen.has(href)) return;
            seen.add(href);

            // 检查是否已经存在相同 URL 的命令，避免重复
            let exists = false;
            commands.forEach(cmd => {
                if (cmd.url === href) {
                    exists = true;
                }
            });
            if (exists) return;

            if (commands.has('nav:' + href)) return;

            registerCommand({
                id: 'nav:' + href,
                title: name,
                category: 'nav',
                icon: null,
                action: function () {
                    writeRecent('nav:' + href);
                    window.location.href = href;
                }
            });
        });
    }

    function scoreCommand(cmd, query) {
        if (!query) return 1;

        const q = query.toLowerCase().trim();
        if (!q) return 1;

        const title = (cmd.title || '').toLowerCase();
        const keywords = cmd.keywords || [];

        let bestScore = 0;

        const idx = title.indexOf(q);
        if (idx === 0) {
            bestScore = Math.max(bestScore, 100 - q.length);
        } else if (idx > 0) {
            bestScore = Math.max(bestScore, 60 - idx);
        } else {
            let ti = 0, qi = 0;
            while (ti < title.length && qi < q.length) {
                if (title[ti] === q[qi]) qi++;
                ti++;
            }
            if (qi === q.length) {
                bestScore = Math.max(bestScore, 20 - (title.length - q.length));
            }
        }

        for (const kw of keywords) {
            const kwLower = kw.toLowerCase();
            if (kwLower === q) {
                bestScore = Math.max(bestScore, 90);
            } else if (kwLower.startsWith(q)) {
                bestScore = Math.max(bestScore, 70);
            } else if (kwLower.includes(q)) {
                bestScore = Math.max(bestScore, 40);
            }
        }

        if (cmd.shortcut) {
            const shortcut = cmd.shortcut.toLowerCase().replace(/\s+/g, '');
            if (shortcut.includes(q.replace(/\s+/g, ''))) {
                bestScore = Math.max(bestScore, 30);
            }
        }

        return bestScore;
    }

    function highlightMatch(text, query) {
        if (!query) return escapeHtml(text);

        const q = query.toLowerCase().trim();
        if (!q) return escapeHtml(text);

        const lowerText = text.toLowerCase();
        const idx = lowerText.indexOf(q);

        if (idx === -1) return escapeHtml(text);

        const before = text.substring(0, idx);
        const match = text.substring(idx, idx + q.length);
        const after = text.substring(idx + q.length);

        return escapeHtml(before) + '<mark class="tr-cmd-mark">' + escapeHtml(match) + '</mark>' + escapeHtml(after);
    }

    function createIcon(iconId) {
        if (!iconId || !iconsUrl) return null;
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'tr-ico tr-cmd-ico');
        svg.setAttribute('aria-hidden', 'true');
        const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        use.setAttributeNS('http://www.w3.org/1999/xlink', 'href', iconsUrl + '#' + iconId);
        svg.appendChild(use);
        return svg;
    }

    function search(query) {
        results.length = 0;
        activeIndex = -1;

        const q = (query || '').trim();

        if (!q) {
            const recent = readRecent();
            const history = readHistory();
            const actionCmds = [];

            commands.forEach(cmd => {
                if (cmd.category !== 'nav') {
                    actionCmds.push(cmd);
                }
            });

            actionCmds.sort((a, b) => {
                const catA = categories.get(a.category) || { order: 100 };
                const catB = categories.get(b.category) || { order: 100 };
                return catA.order - catB.order;
            });

            const recentCmds = [];
            recent.forEach(id => {
                const cmd = commands.get(id);
                if (cmd) recentCmds.push(cmd);
            });

            history.slice(0, 4).forEach(h => {
                const cmd = commands.get(h.id);
                if (cmd && !recentCmds.includes(cmd)) {
                    recentCmds.push(cmd);
                }
            });

            results.push(...recentCmds.slice(0, 6));
            results.push(...actionCmds.slice(0, MAX_RESULTS - results.length));

            hint.textContent = recent.length > 0 ? '最近访问' : '输入关键词搜索命令';
            render(q);
            return;
        }

        const scored = [];
        commands.forEach(cmd => {
            const s = scoreCommand(cmd, q);
            if (s > 0) {
                scored.push({ cmd, score: s });
            }
        });

        scored.sort((a, b) => b.score - a.score);

        scored.slice(0, MAX_RESULTS).forEach(item => {
            results.push(item.cmd);
        });

        activeIndex = results.length > 0 ? 0 : -1;
        hint.textContent = results.length > 0 ? '' : '未找到匹配的命令';
        render(q);
    }

    function render(query) {
        list.innerHTML = '';

        if (results.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'tr-cmd-empty';
            empty.textContent = query ? '未找到匹配的命令' : '暂无可用命令';
            list.appendChild(empty);
            return;
        }

        let currentCategory = null;

        results.forEach((cmd, i) => {
            const cat = categories.get(cmd.category) || { name: cmd.category || '其他' };

            if (currentCategory !== cmd.category && !query) {
                const groupHeader = document.createElement('div');
                groupHeader.className = 'tr-cmd-group';
                groupHeader.textContent = cat.name;
                list.appendChild(groupHeader);
                currentCategory = cmd.category;
            }

            const row = document.createElement('button');
            row.type = 'button';
            row.className = 'tr-cmd-item' + (i === activeIndex ? ' is-active' : '');
            row.setAttribute('role', 'option');
            row.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false');

            const main = document.createElement('div');
            main.className = 'tr-cmd-main';

            if (cmd.icon) {
                const icon = createIcon(cmd.icon);
                if (icon) {
                    main.appendChild(icon);
                }
            }

            const titleSpan = document.createElement('span');
            titleSpan.className = 'tr-cmd-title';
            titleSpan.innerHTML = highlightMatch(cmd.title, query);
            main.appendChild(titleSpan);

            const meta = document.createElement('div');
            meta.className = 'tr-cmd-meta';

            if (cmd.shortcut) {
                const kbd = document.createElement('span');
                kbd.className = 'tr-kbd tr-kbd-sm';
                kbd.textContent = cmd.shortcut;
                meta.appendChild(kbd);
            }

            const sub = document.createElement('span');
            sub.className = 'tr-cmd-sub';
            sub.textContent = cat.name;
            meta.appendChild(sub);

            row.appendChild(main);
            row.appendChild(meta);

            row.addEventListener('mouseenter', () => {
                activeIndex = i;
                syncActive();
            });

            row.addEventListener('click', () => {
                execute(i);
            });

            list.appendChild(row);
        });
    }

    function syncActive() {
        const items = list.querySelectorAll('.tr-cmd-item');
        items.forEach((el, i) => {
            const on = i === activeIndex;
            el.classList.toggle('is-active', on);
            el.setAttribute('aria-selected', on ? 'true' : 'false');
        });
    }

    function execute(index) {
        const cmd = results[index];
        if (!cmd) return;

        if (cmd.confirm) {
            if (!window.confirm(cmd.confirm)) {
                return;
            }
        }

        close();
        writeHistory(cmd);

        try {
            if (cmd.url) {
                writeRecent(cmd.id);
                if (cmd.target === '_blank') {
                    window.open(cmd.url, '_blank', 'noopener,noreferrer');
                } else {
                    window.location.href = cmd.url;
                }
            } else if (cmd.action) {
                if (typeof cmd.action === 'string') {
                    executeAction(cmd.action);
                } else if (typeof cmd.action === 'function') {
                    cmd.action();
                }
            }
        } catch (e) {
            showNotice('error', '命令执行失败');
        }
    }

    function open() {
        if (isOpen) return;
        isOpen = true;

        initFromConfig();

        wrap.style.display = 'block';
        wrap.setAttribute('aria-hidden', 'false');
        body.classList.add('tr-cmd-open');

        input.value = '';
        results.length = 0;

        search('');

        setTimeout(() => input.focus(), 0);
    }

    function close() {
        if (!isOpen) return;
        isOpen = false;

        wrap.style.display = 'none';
        wrap.setAttribute('aria-hidden', 'true');
        body.classList.remove('tr-cmd-open');

        results.length = 0;
        activeIndex = -1;
    }

    function toggle() {
        if (isOpen) close();
        else open();
    }

    overlay.addEventListener('click', close);
    if (btn) btn.addEventListener('click', toggle);

    input.addEventListener('input', () => {
        if (debounceTimer) clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            search(input.value);
        }, DEBOUNCE_MS);
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            close();
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (results.length === 0) return;
            activeIndex = Math.min(results.length - 1, activeIndex + 1);
            syncActive();
            scrollToActive();
            return;
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (results.length === 0) return;
            activeIndex = Math.max(0, activeIndex - 1);
            syncActive();
            scrollToActive();
            return;
        }

        if (e.key === 'Enter') {
            e.preventDefault();
            execute(activeIndex);
        }
    });

    function scrollToActive() {
        const items = list.querySelectorAll('.tr-cmd-item');
        if (items[activeIndex]) {
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        }
    }

    document.addEventListener('keydown', (e) => {
        const k = e.key.toLowerCase();
        const mod = e.metaKey || e.ctrlKey;

        if (mod && k === 'k') {
            e.preventDefault();
            e.stopPropagation();
            toggle();
            return;
        }

        if (isOpen && e.key === 'Escape') {
            e.preventDefault();
            e.stopPropagation();
            close();
        }
    }, true);

    window.TypechoCommandPalette = {
        register: registerCommand,
        registerAll: registerCommands,
        open: open,
        close: close,
        toggle: toggle,
        getCommands: () => Array.from(commands.values()),
        getCategories: () => Array.from(categories.values()),
        refresh: () => {
            initialized = false;
            commands.clear();
            categories.clear();
            initFromConfig();
        }
    };
})();
