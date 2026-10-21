<?php
declare(strict_types=1);

/** @var array<string, mixed> $partner */
/** @var int $id */
/** @var list<array{id: int, nev: string, kod: string, targy: string, html_tartalom: string}> $loginInviteTemplates */
/** @var array{id: int, nev: string, kod: string, targy: string, html_tartalom: string}|null $loginInviteSelected */
/** @var string $loginInviteTempPassword */
/** @var string $loginInviteSubject */
/** @var string $loginInviteHtml */
/** @var list<array<string, mixed>> $loginInviteSmtpAccounts */
/** @var int $loginInviteSmtpId */
/** @var bool $loginInviteCanEmail */
/** @var string $loginInviteBcc */

$portalAbsoluteUrl = ng_absolute_url(partner_url(''));
$partnerEmail = trim((string) ($partner['email'] ?? ''));
$selectedTplId = (int) ($loginInviteSelected['id'] ?? 0);
$loginInviteBcc = (string) ($loginInviteBcc ?? '');
$loginInvitePlaceholders = nextgen_partner_login_invite_placeholders($partner, (string) $loginInviteTempPassword);
$loginInvitePlaceholdersJson = json_encode(
    $loginInvitePlaceholders,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_INVALID_UTF8_SUBSTITUTE
);
if ($loginInvitePlaceholdersJson === false) {
    $loginInvitePlaceholdersJson = '{}';
}
?>
<div class="card" id="partner-login-invite">
    <h3>Partner login létrehozása</h3>
    <p class="help">
        Ideiglenes jelszót állít be, kötelező jelszócserét kér az első belépéskor, és kiértesítő e-mailt küld.
        Portál: <a href="<?= h($portalAbsoluteUrl) ?>" target="_blank" rel="noopener"><?= h($portalAbsoluteUrl) ?></a>
    </p>

    <?php if (!$loginInviteCanEmail): ?>
        <p class="alert alert-warning">A partner e-mail címe nem alkalmas kiértesítésre (hiányzik, érvénytelen, vagy szintetikus).</p>
    <?php endif; ?>

    <form method="post" class="venue-form" id="partner-login-invite-form">
        <?= csrf_input('partner_admin_edit') ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="_action" value="login_invite">
        <input type="hidden" name="login_invite_load_template" id="login_invite_load_template" value="">

        <div class="form-row form-row-2">
            <div class="form-group">
                <label for="login_invite_password">Ideiglenes jelszó *</label>
                <div class="teszt-email-sor">
                    <input type="text" id="login_invite_password" name="login_invite_password" value="<?= h($loginInviteTempPassword) ?>" required minlength="8" autocomplete="off" spellcheck="false">
                    <button type="submit" name="login_invite_regen" value="1" class="btn btn-secondary" formnovalidate>Új jelszó</button>
                </div>
                <p class="help">Legalább 8 karakter. Az első belépéskor kötelező lesz megváltoztatni.</p>
            </div>
            <div class="form-group">
                <label for="login_invite_smtp">Feladó (SMTP)</label>
                <select id="login_invite_smtp" name="login_invite_smtp" <?= $loginInviteSmtpAccounts === [] ? 'disabled' : '' ?>>
                    <?php if ($loginInviteSmtpAccounts === []): ?>
                        <option value="0">Nincs beállított SMTP fiók</option>
                    <?php else: ?>
                        <?php foreach ($loginInviteSmtpAccounts as $acc): ?>
                            <?php
                            $accId = (int) ($acc['id'] ?? 0);
                            $accLabel = trim((string) ($acc['név'] ?? 'Feladó'));
                            $from = trim((string) ($acc['from_name'] ?? ''));
                            $fromEmail = trim((string) ($acc['from_email'] ?? ''));
                            if ($from !== '' && $fromEmail !== '') {
                                $accLabel .= ' – ' . $from . ' <' . $fromEmail . '>';
                            } elseif ($fromEmail !== '') {
                                $accLabel .= ' – ' . $fromEmail;
                            }
                            ?>
                            <option value="<?= $accId ?>"<?= $accId === $loginInviteSmtpId ? ' selected' : '' ?>><?= h($accLabel) ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <?php
        $loginInviteActivateChecked = true;
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && (string) ($_POST['_action'] ?? '') === 'login_invite'
        ) {
            $loginInviteActivateChecked = !empty($_POST['login_invite_activate']);
        }
        ?>
        <div class="form-group">
            <label>
                <input type="checkbox" name="login_invite_activate" value="1"<?= $loginInviteActivateChecked ? ' checked' : '' ?>>
                Inaktív partner aktiválása a küldéskor
            </label>
        </div>

        <div class="form-group">
            <label for="login_invite_template_id">Levélsablon</label>
            <?php if ($loginInviteTemplates === []): ?>
                <p class="help">Nincs elérhető levélsablon; az alapértelmezett szöveg jelenik meg. <a href="<?= h(nextgen_url('config/levelsablonok/letrehoz.php')) ?>" target="_blank" rel="noopener">Új sablon</a></p>
                <input type="hidden" name="login_invite_template_id" value="0">
            <?php else: ?>
                <select id="login_invite_template_id" name="login_invite_template_id">
                    <?php foreach ($loginInviteTemplates as $tpl): ?>
                        <option value="<?= (int) $tpl['id'] ?>"<?= (int) $tpl['id'] === $selectedTplId ? ' selected' : '' ?>>
                            <?= h($tpl['nev'] . ' (' . $tpl['kod'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="help">
                    Alap sablon: <code><?= h(NEXTGEN_PARTNER_LOGIN_INVITE_TEMPLATE_CODE) ?></code>.
                    Választáskor a tárgy és a tartalom betöltődik, utána szabadon szerkeszthető.
                    <a href="<?= h(nextgen_url('config/levelsablonok/')) ?>" target="_blank" rel="noopener">Levélsablonok kezelése</a>
                </p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="login_invite_subject">Levél tárgya *</label>
            <input type="text" id="login_invite_subject" name="login_invite_subject" value="<?= h($loginInviteSubject) ?>" required maxlength="255">
            <p class="help">Változók: <code>{{partner_nev}}</code>, <code>{{email}}</code>, <code>{{jelszo}}</code>, <code>{{portal_url}}</code>, <code>{{site_name}}</code>, <code>{{szerkeszto_becenev}}</code></p>
        </div>

        <div class="form-group">
            <label for="login_invite_html">Levél tartalma (HTML) *</label>
            <textarea id="login_invite_html" name="login_invite_html" class="js-html-editor-source" rows="12" required><?= h($loginInviteHtml) ?></textarea>
            <p class="help">Ugyanazok a változók használhatók, mint a tárgynál. A küldés előtt a behelyettesített, végleges levél jelenik meg.</p>
        </div>

        <div class="form-group">
            <label for="login_invite_bcc">BCC</label>
            <input
                type="text"
                id="login_invite_bcc"
                name="login_invite_bcc"
                value="<?= h($loginInviteBcc) ?>"
                maxlength="2000"
                placeholder="masolat@pelda.hu, masik@pelda.hu"
                autocomplete="off"
                spellcheck="false"
            >
            <p class="help">Rejtett másolat. Több cím vesszővel, pontosvesszővel vagy szóközzel elválasztva. A címzett nem látja, és nem kell ide beírni. Üresen hagyható.</p>
        </div>

        <p class="toolbar">
            <button type="submit" class="btn btn-primary" id="login_invite_send"<?= !$loginInviteCanEmail || $loginInviteSmtpAccounts === [] ? ' disabled' : '' ?>>
                Végleges levél megtekintése
            </button>
        </p>
        <p class="help">Címzett: <strong><?= h($partnerEmail !== '' ? $partnerEmail : '–') ?></strong>. A jelszó és a partner csak a megerősítés után változik.</p>
    </form>

    <dialog class="partner-login-preview-dialog" id="partner-login-preview-dialog" aria-labelledby="partner-login-preview-title">
        <div class="partner-login-preview-dialog__inner">
            <header class="partner-login-preview-dialog__header">
                <h3 id="partner-login-preview-title">Végleges levél</h3>
                <button type="button" class="partner-login-preview-dialog__x" data-login-preview-close aria-label="Bezárás">×</button>
            </header>
            <div class="partner-login-preview-dialog__body">
                <p class="help">Ezt a levelet küldjük ki. A változók már be vannak helyettesítve.</p>
                <dl class="partner-login-preview-dialog__meta">
                    <dt>Címzett</dt>
                    <dd id="partner-login-preview-to"><?= h($partnerEmail !== '' ? $partnerEmail : '–') ?></dd>
                    <dt>BCC</dt>
                    <dd id="partner-login-preview-bcc">nincs</dd>
                    <dt>Feladó</dt>
                    <dd id="partner-login-preview-from">–</dd>
                    <dt>Tárgy</dt>
                    <dd id="partner-login-preview-subject">–</dd>
                </dl>
                <p class="alert alert-error" id="partner-login-preview-error" hidden></p>
                <p class="alert alert-warning" id="partner-login-preview-warning" hidden></p>
                <iframe class="partner-login-preview-dialog__frame" id="partner-login-preview-frame" sandbox="" title="Végleges levél előnézete"></iframe>
            </div>
            <div class="partner-login-preview-dialog__toolbar">
                <button type="button" class="btn btn-primary" id="partner-login-preview-confirm">Login létrehozása és kiértesítés küldése</button>
                <button type="button" class="btn btn-secondary" data-login-preview-close>Vissza a szerkesztéshez</button>
            </div>
        </div>
    </dialog>
    <script type="application/json" id="partner-login-placeholders-json"><?= $loginInvitePlaceholdersJson ?></script>
</div>

<script>
(function () {
    var form = document.getElementById('partner-login-invite-form');
    var select = document.getElementById('login_invite_template_id');
    var flag = document.getElementById('login_invite_load_template');
    if (!form || !select || !flag) {
        return;
    }
    var lastValue = select.value;
    select.addEventListener('change', function () {
        if (select.value === lastValue) {
            return;
        }
        flag.value = '1';
        form.submit();
    });
})();
(function () {
    var form = document.getElementById('partner-login-invite-form');
    var dialog = document.getElementById('partner-login-preview-dialog');
    var sendBtn = document.getElementById('login_invite_send');
    var confirmBtn = document.getElementById('partner-login-preview-confirm');
    var frame = document.getElementById('partner-login-preview-frame');
    var subjectEl = document.getElementById('login_invite_subject');
    var htmlEl = document.getElementById('login_invite_html');
    var passwordEl = document.getElementById('login_invite_password');
    var bccEl = document.getElementById('login_invite_bcc');
    var smtpEl = document.getElementById('login_invite_smtp');
    var raw = document.getElementById('partner-login-placeholders-json');
    var bccOut = document.getElementById('partner-login-preview-bcc');
    var fromOut = document.getElementById('partner-login-preview-from');
    var subjectOut = document.getElementById('partner-login-preview-subject');
    var errorEl = document.getElementById('partner-login-preview-error');
    var warningEl = document.getElementById('partner-login-preview-warning');
    if (!form || !dialog || !sendBtn || !confirmBtn || !frame || !htmlEl || !subjectEl) {
        return;
    }

    var baseMap = {};
    try {
        baseMap = raw ? JSON.parse(raw.textContent || '{}') : {};
    } catch (e) {
        baseMap = {};
    }

    function escapeRegExp(value) {
        return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function applyPlaceholders(text, map) {
        var keys = Object.keys(map).sort(function (a, b) {
            return b.length - a.length;
        });
        if (!keys.length) {
            return text;
        }
        var pattern = new RegExp(keys.map(escapeRegExp).join('|'), 'g');
        return text.replace(pattern, function (token) {
            return Object.prototype.hasOwnProperty.call(map, token) ? map[token] : token;
        });
    }

    function currentHtml() {
        var api = htmlEl.__eventsHtmlEditor;
        if (api && typeof api.getData === 'function') {
            return api.getData();
        }
        return htmlEl.value || '';
    }

    function currentMap() {
        var map = {};
        Object.keys(baseMap).forEach(function (key) {
            map[key] = baseMap[key];
        });
        var password = passwordEl ? passwordEl.value.trim() : '';
        map['{{jelszo}}'] = password !== '' ? password : '{{jelszo}}';
        return map;
    }

    function parseBcc(rawValue) {
        var recipient = String(baseMap['{{email}}'] || '').trim().toLowerCase();
        var emails = [];
        var invalid = [];
        var skippedRecipient = false;
        String(rawValue || '').split(/[\s,;]+/).forEach(function (part) {
            var trimmed = part.trim();
            if (trimmed === '') {
                return;
            }
            var normalized = trimmed.toLowerCase();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(normalized)) {
                invalid.push(trimmed);
                return;
            }
            if (recipient !== '' && normalized === recipient) {
                skippedRecipient = true;
                return;
            }
            if (emails.indexOf(normalized) === -1) {
                emails.push(normalized);
            }
        });
        return { emails: emails, invalid: invalid, skippedRecipient: skippedRecipient };
    }

    function setNotice(el, text) {
        if (!el) {
            return;
        }
        el.hidden = text === '';
        el.textContent = text;
    }

    function openPreview() {
        var map = currentMap();
        var subject = applyPlaceholders(subjectEl.value || '', map);
        var html = applyPlaceholders(currentHtml(), map);
        var bcc = parseBcc(bccEl ? bccEl.value : '');
        var errors = [];
        var warnings = [];

        if (subject.trim() === '' || html.trim() === '') {
            errors.push('A levél tárgya és tartalma kötelező.');
        }
        if (passwordEl && passwordEl.value.trim().length < 8) {
            errors.push('Az ideiglenes jelszónak legalább 8 karakter hosszúnak kell lennie.');
        }
        if (bcc.invalid.length) {
            errors.push('Érvénytelen BCC cím: ' + bcc.invalid.join(', '));
        }
        if (bcc.emails.length > 20) {
            errors.push('Legfeljebb 20 BCC cím adható meg.');
        }
        if (bcc.skippedRecipient) {
            warnings.push('A címzett címe kimarad a BCC-ből.');
        }
        var leftover = (subject + '\n' + html).match(/\{\{[a-z0-9_]+\}\}/gi);
        if (leftover && leftover.length) {
            var unique = leftover.filter(function (token, index) {
                return leftover.indexOf(token) === index;
            });
            warnings.push('Ismeretlen változó maradt a levélben: ' + unique.join(', '));
        }

        if (bccOut) {
            bccOut.textContent = bcc.emails.length ? bcc.emails.join(', ') : 'nincs';
        }
        if (fromOut && smtpEl) {
            var option = smtpEl.options[smtpEl.selectedIndex];
            fromOut.textContent = option ? option.textContent.trim() : '–';
        }
        if (subjectOut) {
            subjectOut.textContent = subject.trim() !== '' ? subject : '–';
        }
        setNotice(errorEl, errors.join(' '));
        setNotice(warningEl, warnings.join(' '));
        confirmBtn.disabled = errors.length > 0;

        frame.srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8">'
            + '<style>body{margin:16px;font:15px/1.5 system-ui,Segoe UI,sans-serif;color:#1c1c1c;}a{color:#0b57d0;}img{max-width:100%;height:auto;}</style>'
            + '</head><body>' + html + '</body></html>';

        if (typeof dialog.showModal === 'function' && !dialog.open) {
            dialog.showModal();
        }
    }

    function closePreview() {
        form.dataset.loginInviteConfirmed = '';
        if (dialog.open) {
            dialog.close();
        }
    }

    form.addEventListener('submit', function (e) {
        var submitter = e.submitter || null;
        if (!submitter || submitter.id !== 'login_invite_send') {
            return;
        }
        if (form.dataset.loginInviteConfirmed === '1') {
            htmlEl.value = currentHtml();
            form.dataset.loginInviteConfirmed = '';
            return;
        }
        e.preventDefault();
        openPreview();
    });
    form.addEventListener('invalid', function () {
        form.dataset.loginInviteConfirmed = '';
    }, true);

    confirmBtn.addEventListener('click', function () {
        if (confirmBtn.disabled) {
            return;
        }
        form.dataset.loginInviteConfirmed = '1';
        htmlEl.value = currentHtml();
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(sendBtn);
            return;
        }
        form.submit();
    });

    dialog.querySelectorAll('[data-login-preview-close]').forEach(function (btn) {
        btn.addEventListener('click', closePreview);
    });
    dialog.addEventListener('cancel', function () {
        form.dataset.loginInviteConfirmed = '';
    });
})();
</script>
