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

        function activate(tab, focusTab) {
            if (!tab) {
                return;
            }

            var target = tab.getAttribute('data-target') || '';
            for (var i = 0; i < tabs.length; i++) {
                var isActive = tabs[i] === tab;
                tabs[i].classList.toggle('is-active', isActive);
                tabs[i].setAttribute('aria-selected', isActive ? 'true' : 'false');
                tabs[i].setAttribute('tabindex', isActive ? '0' : '-1');
            }

            for (var j = 0; j < panes.length; j++) {
                var paneActive = panes[j].getAttribute('data-tab') === target;
                panes[j].classList.toggle('is-active', paneActive);
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
