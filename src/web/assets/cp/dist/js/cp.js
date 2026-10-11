/**
 * SEO Redirect Manager - Control Panel Scripts
 */

(function () {
    'use strict';

    // Clipboard copy helper
    window.srmCopy = function (text, btnElement) {
        if (!navigator.clipboard) {
            const input = document.createElement('input');
            input.value = text;
            document.body.appendChild(input);
            input.select();
            document.execCommand('copy');
            document.body.removeChild(input);
        } else {
            navigator.clipboard.writeText(text);
        }

        if (btnElement) {
            const originalText = btnElement.innerText;
            btnElement.innerText = 'Copied!';
            setTimeout(function () {
                btnElement.innerText = originalText;
            }, 2000);
        }
    };

    // Bulk selection handler
    document.addEventListener('DOMContentLoaded', function () {
        const selectAllCheckbox = document.getElementById('srm-select-all');
        if (selectAllCheckbox) {
            const rowCheckboxes = document.querySelectorAll('.srm-row-select');
            selectAllCheckbox.addEventListener('change', function () {
                rowCheckboxes.forEach(function (cb) {
                    cb.checked = selectAllCheckbox.checked;
                });
            });
        }
    });
})();
