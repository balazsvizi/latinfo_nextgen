<script>
(function () {
    var calcRoot = document.getElementById('events-edit-finance-calc');
    var btn = document.getElementById('events-edit-finance-calc-btn');
    var result = document.getElementById('events-edit-finance-calc-result');
    var costFrom = document.getElementById('event_cost_from');
    var costTo = document.getElementById('event_cost_to');
    if (!calcRoot || !btn || !result) {
        return;
    }

    var financeMap = {};
    try {
        financeMap = JSON.parse(calcRoot.getAttribute('data-organizer-finance') || '{}');
    } catch (e0) {
        financeMap = {};
    }

    function parseCostInput(inp) {
        if (!inp) {
            return null;
        }
        var t = (inp.value || '').trim().replace(',', '.');
        if (t === '') {
            return null;
        }
        var n = parseFloat(t);
        return isNaN(n) ? null : n;
    }

    function getOrganizerIdsFromToken(tokenId) {
        var root = document.getElementById(tokenId);
        if (!root) {
            return [];
        }
        var ids = [];
        root.querySelectorAll('.wp-token-input__chip[data-id]').forEach(function (chip) {
            var v = parseInt(chip.getAttribute('data-id') || '', 10);
            if (v > 0 && ids.indexOf(v) === -1) {
                ids.push(v);
            }
        });
        if (ids.length === 0) {
            root.querySelectorAll('.wp-token-input__hiddens input[type="hidden"]').forEach(function (inp) {
                var hv = parseInt(inp.value || '', 10);
                if (hv > 0 && ids.indexOf(hv) === -1) {
                    ids.push(hv);
                }
            });
        }
        return ids;
    }

    function calculateFee(fixAmount, percent, from, to) {
        if (fixAmount !== null && fixAmount > 0) {
            return Math.round(fixAmount * 100) / 100;
        }
        if (percent !== null && percent >= 1 && percent <= 100) {
            var f = from !== null ? from : 0;
            var t = to !== null ? to : f;
            if (f <= 0 && t <= 0) {
                return null;
            }
            return Math.round(((f + t) / 2) * percent / 100 * 100) / 100;
        }
        return null;
    }

    function formatFt(amount) {
        try {
            return new Intl.NumberFormat('hu-HU', { maximumFractionDigits: 2 }).format(amount) + ' Ft';
        } catch (e1) {
            return String(amount) + ' Ft';
        }
    }

    btn.addEventListener('click', function () {
        var payerIds = getOrganizerIdsFromToken('event-finance-payer');
        var eventOrgIds = getOrganizerIdsFromToken('event-organizers');
        var targetIds = payerIds.length > 0 ? payerIds : eventOrgIds;

        if (targetIds.length === 0) {
            result.textContent = 'Nincs szervező kiválasztva.';
            return;
        }
        if (payerIds.length > 1) {
            result.textContent = 'A „Ki fizeti” mezőben több szervező van — mentéskor hiba keletkezik. A kalkuláció az összes kijelöltre vonatkozik.';
        }

        var from = parseCostInput(costFrom);
        var to = parseCostInput(costTo);
        var lines = [];

        targetIds.forEach(function (orgId) {
            var meta = financeMap[String(orgId)] || financeMap[orgId] || {};
            var name = meta.name || ('Szervező #' + orgId);
            var fixAmt = meta.finance_fix_amount !== null && meta.finance_fix_amount !== undefined && meta.finance_fix_amount !== ''
                ? parseFloat(meta.finance_fix_amount)
                : null;
            var pct = meta.finance_ticket_percent !== null && meta.finance_ticket_percent !== undefined && meta.finance_ticket_percent !== ''
                ? parseInt(meta.finance_ticket_percent, 10)
                : null;
            if (fixAmt !== null && isNaN(fixAmt)) {
                fixAmt = null;
            }
            if (pct !== null && isNaN(pct)) {
                pct = null;
            }
            var fee = calculateFee(fixAmt, pct, from, to);
            if (fee === null) {
                lines.push(name + ': nincs finance beállítás vagy belépő');
            } else {
                lines.push(name + ': ' + formatFt(fee));
            }
        });

        if (payerIds.length <= 1) {
            result.textContent = lines.join(' · ');
        } else {
            result.textContent = lines.join(' · ');
        }
    });
})();
</script>
