/* Profile address helpers — province, postal, timezone suggest */
/* ── Province/State dynamic update ─────────────────────────────── */
var CA_PROVINCES =
    (window.PROFILE_DATA && window.PROFILE_DATA.CA_PROVINCES) || [];
var US_STATES = (window.PROFILE_DATA && window.PROFILE_DATA.US_STATES) || [];

function updateProvinceSelect(type) {
    var country = document.getElementById('country_' + type).value;
    var select = document.getElementById('province_' + type);
    var provWrap = document.getElementById('provinceWrap_' + type);
    var otherWrap = document.getElementById('otherCountryWrap_' + type);
    var current = select ? select.value : '';

    if (country === 'Other') {
        /* Hide province select, show other country text field */
        if (provWrap) provWrap.style.display = 'none';
        if (otherWrap) otherWrap.style.display = '';
        if (select) select.removeAttribute('required');
        return;
    }

    /* Show province select, hide other country field */
    if (provWrap) provWrap.style.display = '';
    if (otherWrap) otherWrap.style.display = 'none';
    if (select) select.setAttribute('required', '');

    var options = country === 'United States' ? US_STATES : CA_PROVINCES;
    select.innerHTML = '';
    options.forEach(function (name) {
        var opt = document.createElement('option');
        opt.value = name;
        opt.text = name;
        if (name === current) opt.selected = true;
        select.appendChild(opt);
    });
}

/* ── State / Province → Timezone suggestion ────────────────────── */
var STATE_TZ = {
    /* Canada */
    'Newfoundland & Labrador': 'NST',
    'New Brunswick': 'AST',
    'Nova Scotia': 'AST',
    'Prince Edward Island': 'AST',
    Ontario: 'EST',
    Quebec: 'EST',
    Manitoba: 'CST',
    Saskatchewan: 'CST',
    Alberta: 'MST',
    'Northwest Territories': 'MST',
    'British Columbia': 'PST',
    Yukon: 'PST',
    Nunavut: 'EST',
    /* US states */
    Connecticut: 'EST',
    Delaware: 'EST',
    Florida: 'EST',
    Georgia: 'EST',
    Indiana: 'EST',
    Maine: 'EST',
    Maryland: 'EST',
    Massachusetts: 'EST',
    Michigan: 'EST',
    'New Hampshire': 'EST',
    'New Jersey': 'EST',
    'New York': 'EST',
    'North Carolina': 'EST',
    Ohio: 'EST',
    Pennsylvania: 'EST',
    'Rhode Island': 'EST',
    'South Carolina': 'EST',
    Tennessee: 'EST',
    Vermont: 'EST',
    Virginia: 'EST',
    'West Virginia': 'EST',
    'District of Columbia': 'EST',
    Alabama: 'CST',
    Arkansas: 'CST',
    Illinois: 'CST',
    Iowa: 'CST',
    Kansas: 'CST',
    Kentucky: 'CST',
    Louisiana: 'CST',
    Minnesota: 'CST',
    Mississippi: 'CST',
    Missouri: 'CST',
    Nebraska: 'CST',
    'North Dakota': 'CST',
    Oklahoma: 'CST',
    'South Dakota': 'CST',
    Texas: 'CST',
    Wisconsin: 'CST',
    Arizona: 'MST',
    Colorado: 'MST',
    Idaho: 'MST',
    Montana: 'MST',
    Nevada: 'MST',
    'New Mexico': 'MST',
    Utah: 'MST',
    Wyoming: 'MST',
    California: 'PST',
    Oregon: 'PST',
    Washington: 'PST',
    Alaska: 'AKST',
    Hawaii: 'HAST',
    Jamaica: 'EST',
};

function suggestTimezoneFromProvince(provinceName) {
    var tz = STATE_TZ[provinceName];
    if (!tz) return;
    var sel = document.getElementById('timezoneSelect');
    if (!sel) return;
    if (!sel.value) {
        sel.value = tz;
    } else if (sel.value !== tz) {
        showTzSuggestion(tz, provinceName);
    }
}

function showTzSuggestion(tz, province) {
    var sel = document.getElementById('timezoneSelect');
    if (!sel) return;
    sel.value = tz;
    if (window.PortalToast) {
        PortalToast.show(
            'Timezone set to ' + tz + ' based on ' + province + '.',
            'info'
        );
    }
}

/* ── Postal code → city auto-fill ──────────────────────────────── */
function autoFillFromPostal(type) {
    var postalEl = document.getElementById('postal_' + type);
    var cityEl = document.getElementById('city_' + type);
    var countryEl = document.getElementById('country_' + type);
    if (!postalEl || !cityEl || !countryEl) return;

    var postal = postalEl.value.trim().replace(/\s+/g, '');
    var country = countryEl.value;

    if (!postal) return;

    var url = '';
    if (country === 'United States' && /^\d{5}$/.test(postal)) {
        url = 'https://api.zippopotam.us/us/' + postal;
    } else if (country === 'Canada' && /^[A-Za-z]\d[A-Za-z]/i.test(postal)) {
        /* Zippopotam.us only supports the 3-char FSA for Canada (e.g. "M4C"),
           NOT the full 6-char postal code. Extract first 3 characters. */
        var fsa = postal.substring(0, 3).toUpperCase();
        url = 'https://api.zippopotam.us/ca/' + fsa;
    } else {
        return;
    }

    fetch(url)
        .then(function (r) {
            return r.ok ? r.json() : null;
        })
        .then(function (data) {
            if (!data || !data.places || !data.places[0]) return;
            var place = data.places[0];
            if (!cityEl.value.trim()) {
                cityEl.value = place['place name'] || '';
            }
            var stateName = place['state'] || '';
            if (stateName) {
                var provSel = document.getElementById('province_' + type);
                if (provSel) {
                    var oldValue = provSel.value;
                    for (var i = 0; i < provSel.options.length; i++) {
                        if (provSel.options[i].value === stateName) {
                            provSel.value = stateName;
                            suggestTimezoneFromProvince(stateName);
                            /* Show notification whenever auto-fill changed the field
                               (including switching from an empty/default selection) */
                            if (oldValue !== stateName) {
                                showPostalChangeMsg(type, oldValue, stateName);
                            }
                            break;
                        }
                    }
                }
            }
        })
        .catch(function () {
            /* silent fail — postal lookup is best-effort */
        });
}

/**
 * Shows a temporary alert when postal code auto-fill changed the province/state.
 */
function showPostalChangeMsg(type, oldVal, newVal) {
    var wrap = document.getElementById('provinceWrap_' + type);
    if (!wrap) {
        wrap = document.getElementById('province_' + type);
        if (wrap) wrap = wrap.parentElement;
    }
    if (!wrap) return;
    var existing = wrap.querySelector('.postal-change-alert');
    if (existing) existing.remove();
    var div = document.createElement('div');
    div.className =
        'alert alert-info alert-dismissible fade show mt-2 py-2 px-3 small postal-change-alert';
    div.setAttribute('role', 'alert');

    var msg = oldVal
        ? 'Province / State changed from <strong>' +
          oldVal +
          '</strong> to <strong>' +
          newVal +
          '</strong> based on postal code.'
        : 'Province / State set to <strong>' +
          newVal +
          '</strong> based on postal code.';

    div.innerHTML =
        '<i class="bi bi-info-circle-fill me-1"></i>' +
        msg +
        '<button type="button" class="btn-close btn-close-sm" data-bs-dismiss="alert" style="padding:0.5rem;"></button>';
    wrap.appendChild(div);
    /* Auto-dismiss after 6 seconds */
    setTimeout(function () {
        if (div.parentNode) div.remove();
    }, 6000);
}


/* Bind country / province / postal (no inline handlers) */
(function bindAddressListeners() {
    ['living', 'mail'].forEach(function (type) {
        var country = document.getElementById('country_' + type);
        if (country && !country._profileBound) {
            country._profileBound = true;
            country.addEventListener('change', function () {
                updateProvinceSelect(type);
            });
        }
        var prov = document.getElementById('province_' + type);
        if (prov && !prov._profileTzBound) {
            prov._profileTzBound = true;
            prov.addEventListener('change', function () {
                suggestTimezoneFromProvince(this.value);
            });
        }
        var postal = document.getElementById('postal_' + type);
        if (postal && !postal._profilePostalBound) {
            postal._profilePostalBound = true;
            postal.addEventListener('blur', function () {
                autoFillFromPostal(type);
            });
        }
    });
})();
