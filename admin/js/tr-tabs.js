(function (window, document) {
    'use strict';

    function init(options) {
        var opts = options && typeof options === 'object' ? options : {};
        var root = opts.root || document;
        var tabs = Array.prototype.slice.call(root.querySelectorAll(opts.tabSelector || '.tr-panel-tab'));
        var panes = Array.prototype.slice.call(root.querySelectorAll(opts.paneSelector || '.tr-panel-pane'));
        var onChange = typeof opts.onChange === 'function' ? opts.onChange : null;
        var manageHidden = opts.manageHidden !== false;
        var keyboard = opts.keyboard !== false;

        if (!tabs.length || !panes.length) {
            return null;
        }

        var tabList = tabs[0].parentNode;
        var idPrefix = root === document ? 'tr-tab' : 'tr-tab-' + Math.random().toString(36).slice(2, 8);

        if (tabList && !tabList.getAttribute('role')) {
            tabList.setAttribute('role', 'tablist');
        }

        for (var a = 0; a < tabs.length; a++) {
            var tabTarget = tabs[a].getAttribute('data-target') || '';
            var pane = null;
            for (var b = 0; b < panes.length; b++) {
                if (panes[b].getAttribute('data-tab') === tabTarget) {
                    pane = panes[b];
                    break;
                }
            }

            if (!tabs[a].id) {
                tabs[a].id = idPrefix + '-' + a;
            }
            tabs[a].setAttribute('role', 'tab');
            if (pane) {
                if (!pane.id) {
                    pane.id = idPrefix + '-panel-' + a;
                }
                tabs[a].setAttribute('aria-controls', pane.id);
                pane.setAttribute('role', 'tabpanel');
                pane.setAttribute('aria-labelledby', tabs[a].id);
                pane.setAttribute('tabindex', '0');
            }
        }

        function activate(tab, focusTab) {
            if (!tab) {
                return;
            }

            var target = tab.getAttribute('data-target') || '';
            var targetPane = null;
            for (var n = 0; n < panes.length; n++) {
                if (panes[n].getAttribute('data-tab') === target) {
                    targetPane = panes[n];
                    break;
                }
            }

            if (!targetPane) {
                return;
            }

            for (var i = 0; i < tabs.length; i++) {
                var isActive = tabs[i] === tab;
                tabs[i].classList.toggle('is-active', isActive);
                tabs[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
                tabs[i].setAttribute('tabindex', isActive ? '0' : '-1');
            }

            for (var j = 0; j < panes.length; j++) {
                var paneActive = panes[j].getAttribute('data-tab') === target;
                panes[j].classList.toggle('is-active', paneActive);
                panes[j].setAttribute('aria-hidden', paneActive ? 'false' : 'true');
                if (manageHidden) {
                    panes[j].hidden = !paneActive;
                }
            }

            if (onChange) {
                onChange(target, tab);
            }

            if (focusTab) {
                tab.focus();
            }
        }

        function move(step) {
            var current = 0;
            for (var i = 0; i < tabs.length; i++) {
                if (tabs[i].classList.contains('is-active')) {
                    current = i;
                    break;
                }
            }

            var next = (current + step + tabs.length) % tabs.length;
            activate(tabs[next], true);
        }

        for (var k = 0; k < tabs.length; k++) {
            tabs[k].addEventListener('click', function () {
                activate(this, false);
            });

            if (!keyboard) {
                continue;
            }

            tabs[k].addEventListener('keydown', function (event) {
                if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                    event.preventDefault();
                    move(1);
                    return;
                }

                if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    move(-1);
                    return;
                }

                if (event.key === 'Home') {
                    event.preventDefault();
                    activate(tabs[0], true);
                    return;
                }

                if (event.key === 'End') {
                    event.preventDefault();
                    activate(tabs[tabs.length - 1], true);
                }
            });
        }

        var initial = tabs[0];
        for (var m = 0; m < tabs.length; m++) {
            if (tabs[m].classList.contains('is-active')) {
                initial = tabs[m];
                break;
            }
        }

        activate(initial, false);

        var controls = root.querySelectorAll('input, select, textarea');
        for (var c = 0; c < controls.length; c++) {
            if (controls[c].hasAttribute('aria-label') || controls[c].hasAttribute('aria-labelledby')) {
                continue;
            }

            var label = controls[c].closest('label');
            if (label && label.textContent.trim()) {
                continue;
            }

            var heading = controls[c].closest('.renewseo-list-item, .renewseo-block-item, .shield-list-item, .shield-block-item, .renewseo-field, .shield-field');
            var headingNode = heading && heading.querySelector('.renewseo-list-item-title, .shield-list-item-title, .renewseo-field > span, .shield-field > span');
            if (headingNode) {
                if (!headingNode.id) {
                    headingNode.id = idPrefix + '-label-' + c;
                }
                controls[c].setAttribute('aria-labelledby', headingNode.id);
            }
        }

        return {
            activate: activate,
            tabs: tabs,
            panes: panes
        };
    }

    window.TypechoTabs = {
        init: init
    };
})(window, document);
