/**
 * CIF module: eligibility
 * Citizenship eligibility UI.
 */
(function (Cif) {
    'use strict';

Cif.getUserForms = function () {
        var wrap = document.querySelector('.cif-progress-wrap');
        if (!wrap || !wrap.dataset.userForms) return [];
        try {
            return JSON.parse(wrap.dataset.userForms);
        } catch (e) {
            return [];
        }
    }

    Cif.getClientCareMessage = function () {
        var wrap = document.querySelector('.cif-progress-wrap');
        if (wrap && wrap.dataset.clientCareMessage) {
            return wrap.dataset.clientCareMessage;
        }
        return 'Please contact our Client Care department as it seems that you are not eligible to proceed with the application. Client Care Department contact info: Phone: 1-800-543-2137 Ext: 823 Email at clientcare@pardonsandwaivers.com';
    }

    Cif.hasWaiverForms = function (forms) {
        return forms.indexOf('waiver') !== -1 || forms.indexOf('waiver-renewal') !== -1;
    }

    Cif.hasCrOrTrpForms = function (forms) {
        return (
            forms.indexOf('criminal-rehab') !== -1 || forms.indexOf('trp') !== -1
        );
    }

    Cif.clearEligibilityErrors = function () {
        document.querySelectorAll('.cif-field-error[data-eligibility-for]').forEach(function (el) {
            el.hidden = true;
            el.textContent = '';
        });
    }

    /**
     * Show Client Care text under eligibility fields; scroll to first.
     * @param {string[]} keys
     */
    Cif.showEligibilityErrors = function (keys) {
        var msg = Cif.getClientCareMessage();
        Cif.clearEligibilityErrors();
        if (!keys || !keys.length) return;

        var firstQ = null;
        keys.forEach(function (key) {
            var slot = document.querySelector(
                '.cif-field-error[data-eligibility-for="' + key + '"]'
            );
            if (slot) {
                slot.hidden = false;
                slot.textContent = msg;
            }
            var q = document.querySelector('.cif-question[data-key="' + key + '"]');
            if (q && !firstQ) firstQ = q;
        });

        if (firstQ) {
            var sec = firstQ.closest('.cif-section');
            if (sec) {
                var secIdx = Array.from(sections).indexOf(sec);
                if (secIdx >= 0) Cif.showSection(secIdx);
            }
            firstQ.scrollIntoView({ behavior: 'smooth', block: 'center' });
            firstQ.style.outline = '2px solid #dc3545';
            setTimeout(function () {
                firstQ.style.outline = '';
            }, 4000);
        }
    }

    /**
     * Finish-time citizenship eligibility (Phase 3). Returns offending field keys.
     * @returns {string[]}
     */
    Cif.validateCitizenshipEligibility = function () {
        var forms = Cif.getUserForms();
        var keys = [];
        var us = Cif.getFieldValue('us_citizenship');
        var cad = Cif.getFieldValue('canadian_citizenship');
        var gc = Cif.getFieldValue('green_card');

        if (Cif.hasCrOrTrpForms(forms)) {
            if (us === 'No') keys.push('us_citizenship');
            if (cad === 'Yes') keys.push('canadian_citizenship');
        }
        if (Cif.hasWaiverForms(forms)) {
            if (us === 'Yes') keys.push('us_citizenship');
            if (gc === 'Yes') keys.push('green_card');
            if (cad === 'No') keys.push('canadian_citizenship');
        }

        // unique
        return keys.filter(function (k, i, a) {
            return a.indexOf(k) === i;
        });
    }

})(window.Cif = window.Cif || {});
