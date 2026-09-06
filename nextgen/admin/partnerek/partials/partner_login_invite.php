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

$portalAbsoluteUrl = ng_absolute_url(partner_url(''));
$partnerEmail = trim((string) ($partner['email'] ?? ''));
$selectedTplId = (int) ($loginInviteSelected['id'] ?? 0);
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
            <p class="help">Változók: <code>{{partner_nev}}</code>, <code>{{email}}</code>, <code>{{jelszo}}</code>, <code>{{portal_url}}</code>, <code>{{site_name}}</code></p>
        </div>

        <div class="form-group">
            <label for="login_invite_html">Levél tartalma (HTML) *</label>
            <textarea id="login_invite_html" name="login_invite_html" class="js-html-editor-source" rows="12" required><?= h($loginInviteHtml) ?></textarea>
            <p class="help">Ugyanazok a változók használhatók, mint a tárgynál. Címzett: <strong><?= h($partnerEmail !== '' ? $partnerEmail : '–') ?></strong></p>
        </div>

        <p class="toolbar">
            <button type="submit" class="btn btn-primary"<?= !$loginInviteCanEmail || $loginInviteSmtpAccounts === [] ? ' disabled' : '' ?>>
                Login létrehozása és kiértesítés küldése
            </button>
        </p>
    </form>
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
</script>
