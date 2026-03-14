(function () {
    var cfg = window.RenewGoPanel || {};

    function notice(type, text) {
        if (window.TypechoNotice && typeof window.TypechoNotice.show === 'function') {
            window.TypechoNotice.show(type, [text]);
            return;
        }
        window.alert(text);
    }

    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data || {})
        }).then(function (res) {
            if (!res.ok) {
                return res.text().then(function (txt) {
                    throw new Error(txt || ('HTTP ' + res.status));
                });
            }
            return res.json();
        }).catch(function (err) {
            if (err instanceof SyntaxError) {
                throw new Error('响应格式无效');
            }
            throw err;
        });
    }

    function text(id) {
        var node = document.getElementById(id);
        return node ? String(node.value || '').trim() : '';
    }

    function setHtml(id, html) {
        var node = document.getElementById(id);
        if (node) {
            node.innerHTML = html;
        }
    }

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    var testBtn = document.getElementById('renewgoTestBtn');
    if (testBtn) {
        testBtn.addEventListener('click', function () {
            var url = text('renewgoTestUrl');
            if (!url) {
                notice('notice', '请先输入 URL');
                return;
            }
            post(cfg.test, { url: url }).then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && json.error) || '测试失败');
                }
                var result = [];
                result.push('<div><strong>标准化 URL：</strong>' + esc(json.url || '') + '</div>');
                result.push('<div><strong>白名单命中：</strong>' + (json.whitelisted ? '是' : '否') + '</div>');
                result.push('<div><strong>是否改写：</strong>' + (json.rewrite ? '是' : '否') + '</div>');
                result.push('<div><strong>改写结果：</strong>' + esc(json.go || '') + '</div>');
                setHtml('renewgoTestResult', result.join(''));
            }).catch(function (err) {
                notice('error', err && err.message ? err.message : '测试失败');
            });
        });
    }

    var exportBtn = document.getElementById('renewgoExportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            post(cfg.export, {}).then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && json.error) || '导出失败');
                }
                var rules = (json.data && json.data.whitelist) ? String(json.data.whitelist) : '';
                var node = document.getElementById('renewgoRules');
                if (node) {
                    node.value = rules;
                }
                notice('success', '已导出当前白名单');
            }).catch(function (err) {
                notice('error', err && err.message ? err.message : '导出失败');
            });
        });
    }

    var importBtn = document.getElementById('renewgoImportBtn');
    if (importBtn) {
        importBtn.addEventListener('click', function () {
            var rules = text('renewgoRules');
            post(cfg.import, { rules: rules }).then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && json.error) || '导入失败');
                }
                notice('success', '白名单已导入');
            }).catch(function (err) {
                notice('error', err && err.message ? err.message : '导入失败');
            });
        });
    }

    var purgeBtn = document.getElementById('renewgoPurgeBtn');
    if (purgeBtn) {
        purgeBtn.addEventListener('click', function () {
            if (!window.confirm('确认清空所有 RenewGo 日志？')) {
                return;
            }
            post(cfg.purge, {}).then(function (json) {
                if (!json || !json.success) {
                    throw new Error((json && json.error) || '清理失败');
                }
                notice('success', '日志已清空');
                window.location.reload();
            }).catch(function (err) {
                notice('error', err && err.message ? err.message : '清理失败');
            });
        });
    }
})();
