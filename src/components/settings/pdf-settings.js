(function () {
    'use strict';

    function normalizeHex(value) {
        if (!value) {
            return '';
        }

        var hex = value.trim().replace(/^#+/, '');

        if (hex.length === 3) {
            hex = hex.split('').map(function (char) {
                return char + char;
            }).join('');
        }

        if (!/^([0-9a-f]{6})$/i.test(hex)) {
            return '';
        }

        return '#' + hex.toUpperCase();
    }

    function initColorControls(form) {
        var pairs = [];
        var controls = form.querySelectorAll('.spv-color-control');

        Array.prototype.forEach.call(controls, function (control) {
            var colorInput = control.querySelector('input[type="color"]');
            var hexInput = control.querySelector('.spv-color-code');

            if (!colorInput || !hexInput) {
                return;
            }

            pairs.push({ color: colorInput, hex: hexInput });

            colorInput.addEventListener('input', function () {
                hexInput.value = colorInput.value.toUpperCase();
                hexInput.classList.remove('is-invalid');
            });

            hexInput.addEventListener('input', function () {
                var normalized = normalizeHex(hexInput.value);
                if (normalized) {
                    colorInput.value = normalized;
                    hexInput.classList.remove('is-invalid');
                } else if (hexInput.value.trim().length > 0) {
                    hexInput.classList.add('is-invalid');
                } else {
                    hexInput.classList.remove('is-invalid');
                }
            });

            hexInput.addEventListener('blur', function () {
                var normalized = normalizeHex(hexInput.value) || colorInput.value;
                colorInput.value = normalized;
                hexInput.value = normalized.toUpperCase();
                hexInput.classList.remove('is-invalid');
            });
        });

        form.addEventListener('reset', function () {
            window.requestAnimationFrame(function () {
                pairs.forEach(function (pair) {
                    pair.hex.value = pair.color.value.toUpperCase();
                    pair.hex.classList.remove('is-invalid');
                });
            });
        });
    }

    function initTokenButtons(form) {
        var buttons = form.querySelectorAll('[data-insert-token]');

        Array.prototype.forEach.call(buttons, function (button) {
            button.addEventListener('click', function () {
                var token = button.getAttribute('data-insert-token');
                var targetId = button.getAttribute('data-token-target');
                var textarea = targetId ? document.getElementById(targetId) : null;

                if (!token || !textarea) {
                    return;
                }

                var start = textarea.selectionStart || 0;
                var end = textarea.selectionEnd || 0;
                var value = textarea.value || '';
                textarea.value = value.slice(0, start) + token + value.slice(end);

                var cursor = start + token.length;
                textarea.focus();
                if (typeof textarea.setSelectionRange === 'function') {
                    textarea.setSelectionRange(cursor, cursor);
                }
            });
        });
    }

    function boot() {
        var form = document.getElementById('spv-pdf-settings-form');
        if (!form) {
            return;
        }

        initColorControls(form);
        initTokenButtons(form);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
