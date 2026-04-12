(function () {
    'use strict';

    const cfg = window.VditorRenewConfig || null;
    if (!cfg || typeof window.Vditor === 'undefined') {
        return;
    }

    const resolveFullStrategy = (value) => {
        if (value === true) {
            return 'compat';
        }
        if (value === false) {
            return 'off';
        }

        value = String(value || 'compat');
        return ['compat', 'native', 'off'].includes(value) ? value : 'compat';
    };

    cfg.fullStrategy = resolveFullStrategy(cfg.fullStrategy);

    const textarea = document.getElementById('text');
    if (!textarea) {
        return;
    }

    const form = textarea.form || document.querySelector('form[name=write_post],form[name=write_page]');
    if (!form) {
        return;
    }

    const createHiddenInput = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = String(value || '');
        form.appendChild(input);
        return input;
    };

    const attachmentCidsInput = createHiddenInput('attachment_cids', '');

    const card = textarea.closest('.tr-editor-card') || textarea.parentNode;
    const host = document.createElement('div');
    host.id = 'tr-vditor';
    host.className = 'tr-vditor-host';
    textarea.parentNode.insertBefore(host, textarea);
    textarea.classList.add('tr-vditor-hidden');

    const storage = (() => {
        try {
            return window.localStorage;
        } catch (e) {
            return null;
        }
    })();

    const modeKey = 'trVditorMode';
    let editor = null;
    let currentMode = cfg.mode || 'ir';
    let fullObserver = null;
    const editorHeight = Math.max(420, parseInt(cfg.editorHeight || 520, 10));
    let cacheId = String(cfg.cacheId || '');
    let draftCacheSessionKey = '';
    
    const draftDataKey = () => 'trVditorDraftData:' + cacheId;
    const draftTsKey = () => 'trVditorDraftTs:' + cacheId;
    const draftAttachmentsKey = () => 'trVditorDraftAttachments:' + cacheId;
    const draftServerTsKey = () => 'trVditorDraftServerTs:' + cacheId;
    
    let draftSaveTimer = null;
    let allowDraftWrite = false;
    let scrubLock = false;
    let cidEnsuring = false;
    const cidWaiters = [];
    const uploadQueue = [];
    let uploadWaitTimer = null;
    const uploadingFiles = new Set();
    const dataImagePendingByName = new Map();
    const dataImageSeen = new Set();
    const uploadedAttachmentCids = new Set();
    let serverLastModified = cfg.lastModified || 0;

    const initDraftCacheKey = () => {
        if (!cfg.isNew || !storage || !cfg.localCache) {
            return;
        }
        const userHash = cfg.userHash || '';
        const baseScope = String(cfg.draftScope || 'vditorrenew:post');
        const scope = userHash && baseScope.indexOf(':' + userHash) === -1 ? (baseScope + ':' + userHash) : baseScope;
        draftCacheSessionKey = 'trVditorDraft:' + scope;
        let seed = storage.getItem(draftCacheSessionKey);
        if (!seed) {
            seed = Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
            storage.setItem(draftCacheSessionKey, seed);
        }
        cacheId = scope + ':' + seed;
    };
    initDraftCacheKey();

    if (cfg.modeSwitch && storage) {
        const saved = storage.getItem(modeKey);
        if (saved === 'wysiwyg' || saved === 'ir' || saved === 'sv') {
            currentMode = saved;
        }
    }

    const openConfirmDialog = ({ title, message, okText = '确定', cancelText = '取消', onOk, onCancel }) => {
        const mask = document.createElement('div');
        mask.className = 'tr-vditor-mask';

        const dialog = document.createElement('div');
        dialog.className = 'tr-vditor-dialog';

        const hd = document.createElement('div');
        hd.className = 'tr-vditor-dialog-hd';
        hd.textContent = String(title || '');

        const bd = document.createElement('div');
        bd.className = 'tr-vditor-dialog-bd';
        const p = document.createElement('div');
        p.style.fontSize = '13px';
        p.style.lineHeight = '1.6';
        p.textContent = String(message || '');
        bd.appendChild(p);

        const ft = document.createElement('div');
        ft.className = 'tr-vditor-dialog-ft';
        const btnCancel = document.createElement('button');
        btnCancel.type = 'button';
        btnCancel.className = 'btn';
        btnCancel.textContent = String(cancelText || '取消');
        const btnOk = document.createElement('button');
        btnOk.type = 'button';
        btnOk.className = 'btn primary';
        btnOk.textContent = String(okText || '确定');

        ft.appendChild(btnCancel);
        ft.appendChild(btnOk);
        dialog.appendChild(hd);
        dialog.appendChild(bd);
        dialog.appendChild(ft);
        mask.appendChild(dialog);
        document.body.appendChild(mask);

        const close = () => mask.remove();
        const ok = () => {
            close();
            onOk && onOk();
        };
        const cancel = () => {
            close();
            onCancel && onCancel();
        };

        btnOk.addEventListener('click', ok);
        btnCancel.addEventListener('click', cancel, { passive: true });
        mask.addEventListener('click', (e) => {
            if (e.target === mask) {
                cancel();
            }
        });
    };

    const ensureMarkdownFlag = () => {
        let input = form.querySelector('input[name="markdown"]');
        if (cfg.allowMarkdown && cfg.forceMarkdown) {
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'markdown';
                form.appendChild(input);
            }
            input.value = '1';
        } else if (input) {
            input.parentNode.removeChild(input);
        }
    };

    const emitWrite = () => {
        if (window.jQuery) {
            window.jQuery(form).trigger('write');
            return;
        }
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    const syncValue = (value) => {
        textarea.value = String(value || '');
        ensureMarkdownFlag();
        emitWrite();
    };

    const showNotice = (text, type = 'error') => {
        if (window.TypechoNotice && typeof window.TypechoNotice.show === 'function') {
            window.TypechoNotice.show(type, [text]);
            return;
        }
        window.alert(text);
    };

    const updateAttachmentCidsInput = () => {
        attachmentCidsInput.value = Array.from(uploadedAttachmentCids).join(',');
    };

    const saveDraftWithAttachments = () => {
        if (!storage || !cfg.localCache) {
            return;
        }
        try {
            const content = editor ? editor.getValue() : textarea.value;
            const attachments = Array.from(uploadedAttachmentCids);
            const ts = Date.now();
            
            storage.setItem(draftDataKey(), content);
            storage.setItem(draftTsKey(), String(ts));
            storage.setItem(draftAttachmentsKey(), JSON.stringify(attachments));
        } catch (e) {
        }
    };

    const loadDraftAttachments = () => {
        if (!storage || !cfg.localCache) {
            return [];
        }
        try {
            const json = storage.getItem(draftAttachmentsKey()) || '[]';
            const attachments = JSON.parse(json);
            if (Array.isArray(attachments)) {
                attachments.forEach(cid => {
                    if (typeof cid === 'number' && cid > 0) {
                        uploadedAttachmentCids.add(cid);
                    }
                });
                updateAttachmentCidsInput();
            }
            return attachments;
        } catch (e) {
            return [];
        }
    };

    const clearDraftCache = () => {
        if (!storage) {
            return;
        }
        try {
            storage.removeItem(draftDataKey());
            storage.removeItem(draftTsKey());
            storage.removeItem(draftAttachmentsKey());
            if (cfg.isNew && draftCacheSessionKey) {
                storage.removeItem(draftCacheSessionKey);
            }
        } catch (e) {
        }
    };

    const ensureCid = (cb) => {
        const idInput = form.querySelector('input[name="cid"]');
        const cidNow = parseInt((idInput && idInput.value) ? idInput.value : '0', 10) || 0;
        if (cidNow > 0) {
            cb && cb(cidNow);
            return;
        }
        if (!window.Typecho || typeof window.Typecho.ensureCid !== 'function') {
            cb && cb(0);
            return;
        }
        if (cidEnsuring) {
            cb && cidWaiters.push(cb);
            return;
        }
        cidEnsuring = true;
        cb && cidWaiters.push(cb);
        window.Typecho.ensureCid((cid) => {
            cidEnsuring = false;
            const list = cidWaiters.splice(0);
            if (idInput) {
                idInput.value = String(cid);
            }
            list.forEach((fn) => {
                try {
                    fn && fn(cid);
                } catch (e) {
                }
            });
        });
    };

    const safeUploadFile = (file) => {
        if (!file) {
            return;
        }
        if (!window.Typecho || typeof window.Typecho.uploadFile !== 'function') {
            uploadQueue.push(file);
            if (!uploadWaitTimer) {
                let attempts = 0;
                uploadWaitTimer = window.setInterval(() => {
                    attempts += 1;
                    if (window.Typecho && typeof window.Typecho.uploadFile === 'function') {
                        window.clearInterval(uploadWaitTimer);
                        uploadWaitTimer = null;
                        const items = uploadQueue.splice(0);
                        ensureCid(() => {
                            items.forEach((f) => window.Typecho.uploadFile(f));
                        });
                        return;
                    }
                    if (attempts >= 100) {
                        window.clearInterval(uploadWaitTimer);
                        uploadWaitTimer = null;
                        uploadQueue.length = 0;
                        showNotice('上传能力不可用，请刷新页面或改用附件面板上传', 'error');
                    }
                }, 80);
            }
            return;
        }
        ensureCid(() => window.Typecho.uploadFile(file));
    };

    const hasInlineDataUri = (text) => {
        return /\!\[[^\]]*\]\(\s*data:image\/[a-z0-9.+-]+;base64,[^)]+\)/i.test(String(text || ''));
    };

    const nextRefId = (markdown) => {
        const text = String(markdown || '');
        const re = /\[(\d+)\]:\s*/g;
        let m;
        let max = 0;
        while ((m = re.exec(text))) {
            const n = parseInt(m[1], 10);
            if (n > max) {
                max = n;
            }
        }
        return max + 1;
    };

    const mdInsertInline = (file, url, isImage) => {
        const safeFile = String(file || '').replace(/[\[\]]/g, '');
        const safeUrl = String(url || '');
        if (isImage) {
            return '![' + safeFile + '](' + safeUrl + ')';
        }
        return '[' + safeFile + '](' + safeUrl + ')';
    };

    const mdInsertRef = (file, url, isImage, markdown) => {
        const safeFile = String(file || '').replace(/[\[\]]/g, '');
        const safeUrl = String(url || '');
        const id = nextRefId(markdown);
        const head = (isImage ? '![' + safeFile + ']' : '[' + safeFile + ']') + '[' + id + ']';
        const def = '[' + id + ']: ' + safeUrl;
        return { head, def, id };
    };

    const openInsertDialog = ({ title, url, isImage, prefer = 'ref' }) => {
        const mask = document.createElement('div');
        mask.className = 'tr-vditor-mask';

        const dialog = document.createElement('div');
        dialog.className = 'tr-vditor-dialog';

        const hd = document.createElement('div');
        hd.className = 'tr-vditor-dialog-hd';
        hd.textContent = isImage ? '插入图片' : '插入链接';

        const bd = document.createElement('div');
        bd.className = 'tr-vditor-dialog-bd';

        const labelUrl = document.createElement('label');
        labelUrl.textContent = '链接';
        const inputUrl = document.createElement('input');
        inputUrl.type = 'text';
        inputUrl.value = String(url || '');

        const labelAlt = document.createElement('label');
        labelAlt.textContent = isImage ? '图片描述' : '链接文本';
        const inputAlt = document.createElement('input');
        inputAlt.type = 'text';
        inputAlt.value = String(title || '');

        const opt = document.createElement('div');
        opt.className = 'tr-vditor-dialog-opt';
        const name = 'trVditorInsMode';
        const optRef = document.createElement('label');
        const radioRef = document.createElement('input');
        radioRef.type = 'radio';
        radioRef.name = name;
        radioRef.value = 'ref';
        radioRef.checked = prefer === 'ref';
        optRef.appendChild(radioRef);
        optRef.appendChild(document.createTextNode('引用式'));

        const optInline = document.createElement('label');
        const radioInline = document.createElement('input');
        radioInline.type = 'radio';
        radioInline.name = name;
        radioInline.value = 'inline';
        radioInline.checked = prefer === 'inline';
        optInline.appendChild(radioInline);
        optInline.appendChild(document.createTextNode('行内'));

        opt.appendChild(optRef);
        opt.appendChild(optInline);

        bd.appendChild(labelUrl);
        bd.appendChild(inputUrl);
        bd.appendChild(labelAlt);
        bd.appendChild(inputAlt);
        bd.appendChild(opt);

        const ft = document.createElement('div');
        ft.className = 'tr-vditor-dialog-ft';
        const btnCancel = document.createElement('button');
        btnCancel.type = 'button';
        btnCancel.className = 'btn';
        btnCancel.textContent = '取消';
        const btnOk = document.createElement('button');
        btnOk.type = 'button';
        btnOk.className = 'btn primary';
        btnOk.textContent = '确定';

        ft.appendChild(btnCancel);
        ft.appendChild(btnOk);

        dialog.appendChild(hd);
        dialog.appendChild(bd);
        dialog.appendChild(ft);
        mask.appendChild(dialog);
        document.body.appendChild(mask);

        const close = () => {
            mask.remove();
        };

        const submit = () => {
            if (!editor) {
                close();
                return;
            }
            const finalUrl = String(inputUrl.value || '').trim();
            if (!finalUrl) {
                showNotice('链接不能为空', 'error');
                return;
            }
            const finalAlt = String(inputAlt.value || '').trim() || (isImage ? 'image' : 'link');
            const mode = (mask.querySelector('input[name="' + name + '"]:checked') || {}).value || 'ref';
            if (mode === 'inline') {
                editor.insertValue(mdInsertInline(finalAlt, finalUrl, !!isImage) + '\n');
                syncValue(editor.getValue());
                close();
                return;
            }

            const current = editor.getValue();
            const ref = mdInsertRef(finalAlt, finalUrl, !!isImage, current);
            editor.insertValue(ref.head + '\n');
            let next = editor.getValue();
            const exists = new RegExp('^\\[' + ref.id + '\\]:\\s*', 'm').test(next);
            if (!exists) {
                next = next.replace(/\s*$/, '');
                next += '\n\n' + ref.def + '\n';
                editor.setValue(next);
            }
            syncValue(editor.getValue());
            close();
        };

        btnCancel.addEventListener('click', close, { passive: true });
        btnOk.addEventListener('click', submit);
        mask.addEventListener('click', (e) => {
            if (e.target === mask) {
                close();
            }
        });
        inputUrl.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                submit();
            } else if (e.key === 'Escape') {
                close();
            }
        });
        inputAlt.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                submit();
            } else if (e.key === 'Escape') {
                close();
            }
        });

        setTimeout(() => {
            inputUrl.focus();
            inputUrl.select();
        }, 0);
    };

    const extractDataImage = (text) => {
        const raw = String(text || '');
        const m = raw.match(/data:image\/([a-z0-9.+-]+);base64,([a-z0-9+/=\s]+)/i);
        if (!m) {
            return null;
        }
        return { ext: m[1], base64: m[2].replace(/\s+/g, '') };
    };

    const extractDataImageMarkdownAll = (markdown) => {
        const text = String(markdown || '');
        const re = /!\[([^\]]*)\]\(\s*data:image\/([a-z0-9.+-]+);base64,([\s\S]+?)\s*\)/ig;
        const out = [];
        let m;
        while ((m = re.exec(text))) {
            out.push({
                full: m[0],
                alt: m[1] || 'image',
                ext: m[2],
                base64: String(m[3] || '').replace(/\s+/g, '')
            });
        }
        return out;
    };

    const dataImageToFile = (ext, base64, name) => {
        try {
            const bin = atob(base64);
            const len = bin.length;
            const bytes = new Uint8Array(len);
            for (let i = 0; i < len; i++) {
                bytes[i] = bin.charCodeAt(i);
            }
            const mime = 'image/' + ext.toLowerCase();
            const blob = new Blob([bytes], { type: mime });
            const fileName = String(name || (Date.now().toString() + '.' + ext.toLowerCase().replace('jpeg', 'jpg')));
            return new File([blob], fileName, { type: mime });
        } catch (e) {
            return null;
        }
    };

    const applyTheme = () => {
        if (!editor || !cfg.followTheme) {
            return;
        }
        const dark = document.documentElement.classList.contains('tr-theme-dark');
        editor.setTheme(dark ? 'dark' : 'classic', dark ? 'dark' : 'light', 'github');
    };

    const setFullscreen = (on) => {
        if (cfg.fullStrategy !== 'compat') {
            document.body.classList.remove('fullscreen');
            document.body.classList.remove('tr-vditor-fullscreen');
            document.body.style.overflow = '';
            return;
        }
        document.body.classList.toggle('fullscreen', !!on);
        document.body.classList.toggle('tr-vditor-fullscreen', !!on);
        document.body.style.overflow = on ? 'hidden' : '';
    };

    const bindFullscreen = (attempt = 0) => {
        if (cfg.fullStrategy !== 'compat' || !window.MutationObserver) {
            return;
        }
        const root = host.classList.contains('vditor') ? host : host.querySelector('.vditor');
        if (!root) {
            if (attempt < 10) {
                window.setTimeout(() => bindFullscreen(attempt + 1), 60);
            }
            return;
        }
        if (fullObserver) {
            fullObserver.disconnect();
            fullObserver = null;
        }
        const sync = () => {
            setFullscreen(root.classList.contains('vditor--fullscreen'));
        };
        sync();
        fullObserver = new MutationObserver(sync);
        fullObserver.observe(root, { attributes: true, attributeFilter: ['class'] });
    };

    const initModeSwitch = () => {
        if (!cfg.modeSwitch || !card) {
            return;
        }

        const wrap = document.createElement('div');
        wrap.className = 'tr-vditor-mode';
        wrap.innerHTML = '<label for="tr-vditor-mode">编辑模式</label>'
            + '<select id="tr-vditor-mode">'
            + '<option value="wysiwyg">所见即所得</option>'
            + '<option value="ir">即时渲染</option>'
            + '<option value="sv">分屏预览</option>'
            + '</select>';

        const select = wrap.querySelector('select');
        select.value = currentMode;
        select.addEventListener('change', () => {
            const next = select.value;
            if (next !== 'wysiwyg' && next !== 'ir' && next !== 'sv') {
                return;
            }
            currentMode = next;
            if (storage) {
                storage.setItem(modeKey, currentMode);
            }
            const latest = editor ? editor.getValue() : textarea.value;
            renderEditor(latest, false);
        });

        card.insertBefore(wrap, card.firstChild);
    };

    const resolveEmoji = () => {
        if (!cfg.emoji) {
            return {};
        }
        return {
            smile: '😄',
            joy: '😂',
            thumbsup: '👍',
            heart: '❤️',
            fire: '🔥',
            clap: '👏',
            thinking: '🤔'
        };
    };

    const getOptions = (value, convertLegacy) => ({
        mode: currentMode,
        value: value,
        height: editorHeight,
        minHeight: editorHeight,
        cdn: cfg.cdn,
        lang: cfg.lang,
        icon: cfg.icon,
        toolbar: Array.isArray(cfg.toolbar) ? cfg.toolbar : undefined,
        toolbarConfig: { pin: true },
        cache: {
            enable: false,
            id: cacheId
        },
        outline: {
            enable: !!cfg.outline,
            position: 'right'
        },
        counter: {
            enable: !!cfg.counter,
            type: 'markdown'
        },
        hint: {
            emoji: resolveEmoji()
        },
        preview: {
            mode: 'both',
            hljs: {
                enable: !!cfg.hljs,
                style: 'github',
                lineNumber: false
            },
            markdown: {
                toc: !!cfg.outline
            },
            math: {
                engine: 'KaTeX'
            }
        },
        input: (md) => {
            syncValue(md);
            
            if (!scrubLock) {
                const list = extractDataImageMarkdownAll(md);
                if (list.length > 0) {
                    uploadDataImages(list);
                }
            }
            
            if (allowDraftWrite && cfg.isNew && storage && cfg.localCache) {
                if (draftSaveTimer) {
                    clearTimeout(draftSaveTimer);
                }
                draftSaveTimer = setTimeout(() => {
                    saveDraftWithAttachments();
                }, 500);
            }
        },
        after: () => {
            if (!editor) {
                return;
            }
            if (convertLegacy && cfg.legacy === 'convert' && !cfg.isMarkdown) {
                const raw = textarea.value;
                if (raw.trim() !== '') {
                    const md = editor.html2md(raw);
                    editor.setValue(md);
                    syncValue(md);
                }
            } else {
                syncValue(editor.getValue());
            }
            applyTheme();
        }
    });

    const renderEditor = (value, convertLegacy) => {
        setFullscreen(false);
        if (fullObserver) {
            fullObserver.disconnect();
            fullObserver = null;
        }
        if (editor) {
            editor.destroy();
            editor = null;
        }
        host.innerHTML = '';
        editor = new window.Vditor(host, getOptions(value, convertLegacy));
        bindFullscreen();
    };

    const bindTheme = () => {
        if (!cfg.followTheme) {
            return;
        }
        window.addEventListener('tr-theme-change', applyTheme);
        if (window.MutationObserver) {
            const observer = new MutationObserver(applyTheme);
            observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        }
    };

    const uploadDataImages = (list) => {
        if (!list || list.length === 0) {
            return;
        }
        let queued = 0;
        list.forEach((it) => {
            const rawExt = String(it.ext || 'png').toLowerCase();
            const mimeExt = rawExt;
            const fileExt = rawExt.split('+')[0].replace('jpeg', 'jpg').replace(/[^a-z0-9]+/g, '') || 'png';
            const key = mimeExt + ':' + it.base64.slice(0, 256);
            if (dataImageSeen.has(key)) {
                return;
            }
            dataImageSeen.add(key);
            const stamp = Date.now().toString(36) + Math.random().toString(36).slice(2, 6);
            const name = 'clip-' + stamp + '.' + fileExt;
            const file = dataImageToFile(mimeExt, it.base64, name);
            if (!file) {
                showNotice('剪贴板图片解析失败，请改用附件上传', 'error');
                return;
            }
            dataImagePendingByName.set(name, { full: it.full, alt: it.alt || 'image' });
            queued++;
            safeUploadFile(file);
        });
        if (queued > 0) {
            showNotice('检测到剪贴板图片，已自动上传为附件；上传成功后将自动替换为外链', 'notice');
        }
    };

    const bindSubmitGuard = () => {
        form.addEventListener('submit', (event) => {
            if (editor) {
                textarea.value = editor.getValue();
            }
            ensureMarkdownFlag();
            
            const markdown = editor ? editor.getValue() : textarea.value;
            if (hasInlineDataUri(markdown)) {
                event.preventDefault();
                showNotice('检测到未上传的剪贴板图片，请等待上传完成后再发布', 'error');
                return;
            }
            if (window.Typecho && typeof window.Typecho.getUploadPending === 'function') {
                const pending = parseInt(window.Typecho.getUploadPending() || '0', 10) || 0;
                if (pending > 0) {
                    event.preventDefault();
                    showNotice('正在上传附件，请等待上传完成后再提交', 'notice');
                    return;
                }
            }

            let action = '';
            const submitter = event.submitter;
            if (submitter && submitter.name === 'do') {
                action = String(submitter.value || '');
            } else {
                const hidden = form.querySelector('input[name="do"]');
                if (hidden) {
                    action = String(hidden.value || '');
                }
            }
            if (action === 'publish' && storage) {
                clearDraftCache();
            }
        });
    };

    const bindUploadBridge = () => {
        window.Typecho = window.Typecho || {};
        
        window.Typecho.insertFileToEditor = (file, url, isImage) => {
            if (!editor) {
                return;
            }
            const md = isImage ? '![' + file + '](' + url + ')' : '[' + file + '](' + url + ')';
            editor.insertValue(md + '\n');
            syncValue(editor.getValue());
        };
        
        window.Typecho.uploadComplete = (attachment) => {
            if (!attachment || !editor) {
                return;
            }
            
            const name = String(attachment.title || '');
            const url = String(attachment.url || '');
            const cid = attachment.cid || 0;
            
            if (cid > 0) {
                uploadedAttachmentCids.add(cid);
                updateAttachmentCidsInput();
            }
            
            let foundName = null;
            let foundPending = null;
            for (const [pendingName, pending] of dataImagePendingByName.entries()) {
                if (pendingName === name || 
                    pendingName.replace(/\.[^.]+$/, '') === name.replace(/\.[^.]+$/, '')) {
                    foundName = pendingName;
                    foundPending = pending;
                    break;
                }
            }
            
            if (!foundPending) {
                const base64Pattern = /!\[([^\]]*)\]\(\s*data:image\/[a-z0-9.+-]+;base64,[^)]+\s*\)/gi;
                const matches = [...editor.getValue().matchAll(base64Pattern)];
                if (matches.length > 0) {
                    const match = matches[0];
                    foundPending = { full: match[0], alt: match[1] || 'image' };
                }
            }
            
            if (foundPending && foundPending.full) {
                const latest = editor.getValue();
                let replaced = latest.replace(foundPending.full, '![' + foundPending.alt + '](' + url + ')');
                
                if (replaced === latest && foundPending.full.includes('data:image')) {
                    const base64Pattern = /!\[([^\]]*)\]\(\s*data:image\/[a-z0-9.+-]+;base64,[^)]+\s*\)/gi;
                    const matches = [...latest.matchAll(base64Pattern)];
                    for (const match of matches) {
                        const alt = match[1] || 'image';
                        if (alt === foundPending.alt || alt.includes(foundPending.alt) || foundPending.alt.includes(alt)) {
                            replaced = latest.replace(match[0], '![' + alt + '](' + url + ')');
                            if (replaced !== latest) {
                                break;
                            }
                        }
                    }
                }
                
                if (foundName) {
                    dataImagePendingByName.delete(foundName);
                }
                
                if (replaced !== latest) {
                    editor.setValue(replaced);
                    syncValue(replaced);
                    
                    if (draftSaveTimer) {
                        clearTimeout(draftSaveTimer);
                    }
                    saveDraftWithAttachments();
                    return;
                }
            }
        };
        
        window.Typecho.attachmentDeleted = (cid) => {
            if (uploadedAttachmentCids.has(cid)) {
                uploadedAttachmentCids.delete(cid);
                updateAttachmentCidsInput();
                saveDraftWithAttachments();
            }
        };
    };

    const checkDraftConflict = () => {
        if (!storage || !cfg.localCache) {
            return false;
        }
        
        const localTs = parseInt(storage.getItem(draftTsKey()) || '0', 10);
        const serverTs = serverLastModified * 1000;
        
        if (localTs > 0 && serverTs > 0 && localTs > serverTs) {
            return true;
        }
        return false;
    };

    const initDraftRestore = () => {
        if (!cfg.isNew || !storage || !cfg.localCache) {
            allowDraftWrite = true;
            renderEditorAndContinue();
            return;
        }

        let saved = '';
        try {
            saved = String(storage.getItem(draftDataKey()) || '');
        } catch (e) {
            saved = '';
        }

        if (!saved || saved.trim() === '') {
            allowDraftWrite = true;
            renderEditorAndContinue();
            return;
        }

        const raw = String(textarea.value || '').trim();
        if (raw !== '') {
            allowDraftWrite = true;
            renderEditorAndContinue();
            return;
        }

        const hasConflict = checkDraftConflict();
        const message = hasConflict 
            ? '检测到本地草稿比服务器版本更新，是否恢复本地草稿？'
            : '检测到上次未发布的本地草稿，是否恢复？';

        openConfirmDialog({
            title: '恢复草稿',
            message: message,
            okText: '恢复',
            cancelText: '放弃',
            onOk: () => {
                allowDraftWrite = true;
                textarea.value = saved;
                loadDraftAttachments();
                renderEditorAndContinue();
            },
            onCancel: () => {
                clearDraftCache();
                uploadedAttachmentCids.clear();
                updateAttachmentCidsInput();
                allowDraftWrite = true;
                renderEditorAndContinue();
            }
        });
    };

    const renderEditorAndContinue = () => {
        renderEditor(textarea.value, cfg.legacy === 'convert');
    };

    const bindAttachmentEvents = () => {
        if (!window.jQuery) {
            return;
        }
        
        const $ = window.jQuery;
        
        $(document).on('click', '#file-list .delete', function(e) {
            const $li = $(this).closest('li');
            const cid = $li.data('cid');
            if (cid && window.Typecho && typeof window.Typecho.attachmentDeleted === 'function') {
                window.Typecho.attachmentDeleted(cid);
            }
        });
    };

    initModeSwitch();
    bindTheme();
    bindUploadBridge();
    bindSubmitGuard();
    bindAttachmentEvents();
    ensureMarkdownFlag();
    initDraftRestore();

    if (cfg.legacy === 'raw' && !cfg.isNew && !cfg.isMarkdown) {
        showNotice('当前内容为旧文且未标记 Markdown，按原样编辑可能破坏排版，建议在插件设置中切换为“旧文自动转换为 Markdown”。', 'notice');
    }
})();
