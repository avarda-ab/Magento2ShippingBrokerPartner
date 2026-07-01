define([], function () {
    'use strict';

    return function (config, element) {
        element.addEventListener('click', function () {
            var field = document.querySelector(config.field),
                bytes = new Uint8Array(24);

            if (!field) {
                return;
            }

            (window.crypto || window.msCrypto).getRandomValues(bytes);

            field.value = Array.prototype.map.call(bytes, function (byte) {
                return ('0' + byte.toString(16)).slice(-2);
            }).join('');

            // Reveal the generated value so it can be copied into the Avarda portal.
            field.type = 'text';
            field.dispatchEvent(new Event('change'));
        });
    };
});
