/**
 * @copyright Copyright © Avarda. All rights reserved.
 * @package   Avarda_ShippingBrokerPartner
 *
 * Implements the global window.avardaShipping contract Avarda Checkout3 expects
 * for partner shipping. Renders shipping options from the session `modules`
 * payload our backend produces, and re-fetches via the public state endpoint
 * when sessionHasUpdated() is invoked by the host checkout.
 *
 * Loaded as a plain <script> (not an AMD module) so the global is in place
 * before Avarda's checkout JS calls into it.
 */
(function () {
    'use strict';

    var config = window.avardaShippingPartnerConfig || {};
    var listeners = new Map();
    var state = {
        element: null,
        sessionId: null,
        language: 'en',
        locale: 'en-US',
        styles: null,
        modules: null,
        suspended: false
    };

    var FONT_DEFAULT = '-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif';
    var STYLE_SHEET = '\
.avarda-shipping-widget{font-family:var(--font-family,' + FONT_DEFAULT + ');color:var(--unselected-label-color,#202020)}\
.avarda-shipping-fieldset{margin:0;padding:0;border:0}\
.avarda-shipping-legend{height:0;overflow:hidden;font-size:0}\
.avarda-shipping-options{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}\
.avarda-shipping-option{background:var(--background-color,#fff);border:1px solid var(--border-color,#d9d9d9);border-radius:var(--border-radius,8px);transition:border-color .15s ease,box-shadow .15s ease}\
.avarda-shipping-option:hover{border-color:var(--selected-radio-button-color,#000)}\
.avarda-shipping-option.is-selected{border-color:var(--selected-radio-button-color,#000);box-shadow:0 0 0 1px var(--selected-radio-button-color,#000) inset}\
.avarda-shipping-option-label{display:block;padding:12px 16px;cursor:pointer;margin:0}\
.avarda-shipping-option-header{display:flex;align-items:center;gap:12px}\
.avarda-shipping-option-header-col1{flex:1 1 auto;min-width:0;display:flex;flex-direction:column}\
.avarda-shipping-option-header-col2{flex:0 0 auto}\
.avarda-shipping-option-title{font-weight:500;color:var(--unselected-label-color,#202020);line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}\
.avarda-shipping-option.is-selected .avarda-shipping-option-title{color:var(--selected-label-color,#000)}\
.avarda-shipping-option-text{color:var(--unselected-label-color,#202020);opacity:.7;font-size:.85em;margin-top:2px;line-height:1.3}\
.avarda-shipping-option-price{font-weight:600;color:var(--amount-to-pay-color,#000);font-variant-numeric:tabular-nums}\
.avarda-shipping-input-radio{position:relative;width:20px;height:20px;flex:0 0 auto;display:inline-block}\
.avarda-shipping-input-radio-control{position:absolute;inset:0;margin:0;opacity:0;cursor:pointer;width:100%;height:100%}\
.avarda-shipping-input-radio-overlay1{position:absolute;inset:0;border:2px solid var(--unselected-radio-button-color,#cecece);border-radius:50%;background:var(--background-color,#fff);display:flex;align-items:center;justify-content:center;pointer-events:none;box-sizing:border-box}\
.avarda-shipping-option.is-selected .avarda-shipping-input-radio-overlay1{border-color:var(--selected-radio-button-color,#000)}\
.avarda-shipping-input-radio-overlay2{width:10px;height:10px;border-radius:50%;background:transparent;transition:background-color .15s ease}\
.avarda-shipping-option.is-selected .avarda-shipping-input-radio-overlay2{background:var(--selected-radio-button-color,#000)}\
.avarda-shipping-empty{padding:14px;text-align:center;font-style:italic;color:var(--unselected-label-color,#202020);opacity:.7;font-family:var(--font-family,' + FONT_DEFAULT + ')}\
';

    function on(type, listener) {
        if (!listeners.has(type)) {
            listeners.set(type, new Set());
        }
        listeners.get(type).add(listener);
    }

    function dispatchEvent(event) {
        var set = listeners.get(event.type);
        if (set) {
            set.forEach(function (listener) {
                try {
                    if (typeof listener === 'function') {
                        listener(event);
                    } else if (listener && typeof listener.handleEvent === 'function') {
                        listener.handleEvent(event);
                    }
                } catch (err) {
                    if (window.console && console.error) {
                        console.error('avardaShipping listener error', err);
                    }
                }
            });
        }
        return true;
    }

    function parseModules(modules) {
        if (!modules) {
            return { options: [] };
        }
        if (typeof modules === 'object') {
            return modules;
        }
        try {
            return JSON.parse(modules);
        } catch (err) {
            return { options: [] };
        }
    }

    function applyStyles(rootEl, styles) {
        if (!styles || typeof styles !== 'object') {
            return;
        }
        Object.keys(styles).forEach(function (key) {
            var value = styles[key];
            if (typeof value !== 'string' && typeof value !== 'number') {
                return;
            }
            var prop = key.indexOf('--') === 0 ? key : '--' + key;
            try {
                rootEl.style.setProperty(prop, String(value));
            } catch (err) {
                /* invalid property name, ignore */
            }
        });
    }

    function formatPrice(value, currency) {
        var amount = typeof value === 'number' ? value : parseFloat(value);
        if (!isFinite(amount)) {
            amount = 0;
        }
        try {
            return new Intl.NumberFormat(state.locale || 'en-US', {
                style: 'currency',
                currency: currency || 'EUR',
                currencyDisplay: 'narrowSymbol'
            }).format(amount);
        } catch (e) {
            return amount.toFixed(2) + (currency ? ' ' + currency : '');
        }
    }

    function buildOption(option, list) {
        var item = document.createElement('li');
        item.className = 'avarda-shipping-option' + (option.selected ? ' is-selected' : '');

        var label = document.createElement('label');
        label.className = 'avarda-shipping-option-label';

        var header = document.createElement('div');
        header.className = 'avarda-shipping-option-header';

        var radio = document.createElement('span');
        radio.className = 'avarda-shipping-input-radio';

        var input = document.createElement('input');
        input.type = 'radio';
        input.name = 'avarda-shipping-option';
        input.value = option.id;
        input.className = 'avarda-shipping-input-radio-control';
        if (option.selected) {
            input.checked = true;
        }
        input.addEventListener('change', function () {
            Array.prototype.forEach.call(
                list.querySelectorAll('.avarda-shipping-option'),
                function (li) { li.classList.remove('is-selected'); }
            );
            item.classList.add('is-selected');
            pushSelection(option.id);
            var detail = {
                shippingMethod: option.id,
                carrier: option.carrier,
                product: option.product,
                price: option.price,
                currency: option.currency
            };
            var changeEvent;
            try {
                changeEvent = new CustomEvent('shipping_option_changed', { detail: detail });
            } catch (e) {
                changeEvent = document.createEvent('CustomEvent');
                changeEvent.initCustomEvent('shipping_option_changed', false, false, detail);
            }
            dispatchEvent(changeEvent);
        });

        var overlay1 = document.createElement('span');
        overlay1.className = 'avarda-shipping-input-radio-overlay1';
        var overlay2 = document.createElement('span');
        overlay2.className = 'avarda-shipping-input-radio-overlay2';
        overlay1.appendChild(overlay2);

        radio.appendChild(input);
        radio.appendChild(overlay1);

        var col1 = document.createElement('div');
        col1.className = 'avarda-shipping-option-header-col1';

        var title = document.createElement('div');
        title.className = 'avarda-shipping-option-title';
        title.textContent = option.product || option.title || option.id;
        col1.appendChild(title);

        if (option.carrier && option.carrier !== option.product) {
            var meta = document.createElement('div');
            meta.className = 'avarda-shipping-option-text';
            meta.textContent = option.carrier;
            col1.appendChild(meta);
        }

        var col2 = document.createElement('div');
        col2.className = 'avarda-shipping-option-header-col2';

        var price = document.createElement('div');
        price.className = 'avarda-shipping-option-price';
        price.textContent = formatPrice(option.price, option.currency);
        col2.appendChild(price);

        header.appendChild(radio);
        header.appendChild(col1);
        header.appendChild(col2);
        label.appendChild(header);
        item.appendChild(label);
        return item;
    }

    function render() {
        if (!state.element) {
            return;
        }
        if (state.suspended) {
            state.element.style.display = 'none';
            return;
        }
        state.element.style.display = '';
        state.element.innerHTML = '';

        var style = document.createElement('style');
        style.textContent = STYLE_SHEET;
        state.element.appendChild(style);

        var widget = document.createElement('div');
        widget.className = 'avarda-shipping-widget';
        applyStyles(widget, state.styles);
        state.element.appendChild(widget);

        var data = parseModules(state.modules);
        var options = data && Array.isArray(data.options) ? data.options : [];
        if (!options.length) {
            var empty = document.createElement('p');
            empty.className = 'avarda-shipping-empty';
            empty.textContent = 'No shipping options available.';
            widget.appendChild(empty);
            return;
        }

        var fieldset = document.createElement('fieldset');
        fieldset.className = 'avarda-shipping-fieldset';

        var legend = document.createElement('legend');
        legend.className = 'avarda-shipping-legend';
        legend.textContent = 'Shipping options';
        fieldset.appendChild(legend);

        var list = document.createElement('ul');
        list.className = 'avarda-shipping-options';
        options.forEach(function (option) {
            list.appendChild(buildOption(option, list));
        });
        fieldset.appendChild(list);
        widget.appendChild(fieldset);
    }

    function fetchState() {
        if (!config.stateBaseUrl || !state.sessionId) {
            return Promise.resolve();
        }
        return fetch(config.stateBaseUrl + '/' + encodeURIComponent(state.sessionId), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(function (resp) {
            if (!resp.ok) {
                throw new Error('state ' + resp.status);
            }
            return resp.json();
        }).then(function (data) {
            if (data && typeof data.modules !== 'undefined') {
                state.modules = data.modules;
                render();
            }
        }).catch(function (err) {
            if (window.console && console.warn) {
                console.warn('avardaShipping: state refresh failed', err);
            }
        });
    }

    function pushSelection(shippingMethod) {
        if (!config.selectBaseUrl || !state.sessionId || !shippingMethod) {
            return;
        }
        try {
            fetch(config.selectBaseUrl + '/' + encodeURIComponent(state.sessionId), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ shippingMethod: shippingMethod })
            }).catch(function (err) {
                if (window.console && console.warn) {
                    console.warn('avardaShipping: selection push failed', err);
                }
            });
        } catch (err) {
            if (window.console && console.warn) {
                console.warn('avardaShipping: selection push threw', err);
            }
        }
    }

    var avardaShipping = {
        init: function (initObject) {
            // Avarda re-attaches listeners on every init() call; clearing here
            // prevents identical handlers from stacking across session updates.
            listeners.clear();
            initObject = initObject || {};
            state.element = initObject.element || null;
            state.sessionId = initObject.session_id || null;
            var cfg = initObject.config || {};
            state.modules = cfg.modules || null;
            state.styles = cfg.styles || null;
            state.language = cfg.language || state.language;
            state.locale = cfg.locale || state.locale;
            state.suspended = false;
            render();
            try {
                dispatchEvent(new Event('loaded'));
            } catch (e) {
                var ev = document.createEvent('Event');
                ev.initEvent('loaded', false, false);
                dispatchEvent(ev);
            }
        },
        suspend: function () {
            state.suspended = true;
            render();
        },
        resume: function () {
            state.suspended = false;
            render();
        },
        unmount: function () {
            if (state.element) {
                state.element.innerHTML = '';
            }
            listeners.clear();
            state.element = null;
            state.sessionId = null;
            state.modules = null;
        },
        setLanguage: function (language) {
            state.language = language;
            render();
        },
        sessionHasUpdated: function () {
            fetchState();
        },
        on: on,
        addEventListener: on,
        removeEventListener: function (type, listener) {
            var set = listeners.get(type);
            if (set) {
                set.delete(listener);
            }
        },
        dispatchEvent: dispatchEvent
    };

    window.avardaShipping = avardaShipping;
}());
