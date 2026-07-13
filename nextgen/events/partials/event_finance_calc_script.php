<script>
(function () {
    var calcRoot = document.getElementById('events-edit-finance-calc');
    var btn = document.getElementById('events-edit-finance-calc-btn');
    var feeInput = document.getElementById('finance_organizer_fee');
    var costFrom = document.getElementById('event_cost_from');
    var costTo = document.getElementById('event_cost_to');
    if (!calcRoot || !btn || !feeInput) {
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
        var f = from !== null ? from : 0;
        var t = to !== null ? to : f;
        if (f <= 0 && t <= 0) {
            return null;
        }
        var effectivePercent = (percent !== null && percent >= 1 && percent <= 500) ? percent : 200;
        return Math.round(((f + t) / 2) * effectivePercent / 100 * 100) / 100;
    }

    function feeForOrganizer(orgId, from, to) {
        var meta = financeMap[String(orgId)] || financeMap[orgId] || {};
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
        return calculateFee(fixAmt, pct, from, to);
    }

    btn.addEventListener('click', function () {
        var payerIds = getOrganizerIdsFromToken('event-finance-payer');
        var eventOrgIds = getOrganizerIdsFromToken('event-organizers');
        var targetId = payerIds.length > 0 ? payerIds[0] : (eventOrgIds.length > 0 ? eventOrgIds[0] : 0);

        if (targetId <= 0) {
            btn.title = 'Nincs szervező kiválasztva.';
            return;
        }

        var from = parseCostInput(costFrom);
        var to = parseCostInput(costTo);
        var fee = feeForOrganizer(targetId, from, to);
        if (fee === null) {
            btn.title = 'Nincs belépő megadva a kalkulációhoz.';
            return;
        }

        feeInput.value = String(fee);
        btn.title = 'Szervezői díj kalkulálása';
        feeInput.dispatchEvent(new Event('input', { bubbles: true }));
    });
})();
</script>
