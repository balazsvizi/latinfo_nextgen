<?php
declare(strict_types=1);

/**
 * Esemény szervezői értesítő e-mail előugró (<dialog>).
 *
 * @var int $id
 * @var list<array{id: int, nev: string, kod: string, targy: string, cc_emails?: string, html_tartalom: string}> $notifyEmailTemplatesRendered
 * @var array{id: int, nev: string, kod: string, targy: string, cc_emails?: string, html_tartalom: string}|null $notifyEmailSelected
 * @var list<array{id: int, nev: string, from_email: string, from_name: string, alapertelmezett: int}> $notifyEmailSmtpAccounts
 * @var int $notifyEmailSmtpId
 * @var list<string> $notifyEmailRecipients
 * @var string $notifyEmailBcc
 * @var list<array<string, mixed>> $notifyEmailSentLogs
 */

$notifyEmailTemplatesRendered = $notifyEmailTemplatesRendered ?? [];
$notifyEmailSelected = $notifyEmailSelected ?? null;
$notifyEmailSmtpAccounts = $notifyEmailSmtpAccounts ?? [];
$notifyEmailSmtpId = (int) ($notifyEmailSmtpId ?? 0);
$notifyEmailRecipients = $notifyEmailRecipients ?? [];
$notifyEmailBcc = (string) ($notifyEmailBcc ?? EVENTS_NOTIFY_EMAIL_DEFAULT_BCC);
$notifyEmailSentLogs = $notifyEmailSentLogs ?? [];
$selectedTplId = (int) ($notifyEmailSelected['id'] ?? 0);
$initialSubject = (string) ($notifyEmailSelected['targy'] ?? '');
$initialHtml = (string) ($notifyEmailSelected['html_tartalom'] ?? '');
$initialCc = trim((string) ($notifyEmailSelected['cc_emails'] ?? ''));
$toDefault = implode(', ', $notifyEmailRecipients);
$canSend = $notifyEmailSmtpAccounts !== [];
$templatesListUrl = nextgen_url('config/levelsablonok/');
$templatesJson = json_encode(
    $notifyEmailTemplatesRendered,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP
);
?>
<dialog class="event-notify-modal" id="event-notify-email-dialog" aria-labelledby="event-notify-email-title">
    <div class="event-notify-modal__inner">
        <header class="event-notify-modal__header">
            <h2 class="event-notify-modal__title" id="event-notify-email-title">E-mail a szervezőnek</h2>
            <button type="button" class="event-notify-modal__x" data-event-notify-close aria-label="Bezárás">×</button>
        </header>

        <form
            method="post"
            action="<?= h(events_url('event_notify_send.php')) ?>"
            class="event-notify-modal__form"
            id="event-notify-email-form"
        >
            <?= csrf_input('events_notify_email') ?>
            <input type="hidden" name="event_id" value="<?= (int) $id ?>">

            <div class="event-notify-modal__toolbar">
                <button type="submit" class="btn btn-primary"<?= !$canSend ? ' disabled' : '' ?>>Küldés</button>
                <button type="button" class="btn btn-secondary" data-event-notify-close>Mégse</button>
            </div>

            <div class="event-notify-modal__body">
                <?php if ($notifyEmailRecipients === []): ?>
                    <p class="alert alert-warning">
                        Nincs ismert e-mail cím a hozzárendelt szervező(k)höz.
                        Partner vagy portál fiók e-mail cím alapján töltjük fel automatikusan; itt kézzel is megadhatod.
                    </p>
                <?php endif; ?>
                <?php if ($notifyEmailSmtpAccounts === []): ?>
                    <p class="alert alert-warning">
                        Nincs SMTP fiók.
                        <a href="<?= h(nextgen_url('admin/email/')) ?>" target="_blank" rel="noopener">SMTP beállítás</a>
                    </p>
                <?php endif; ?>

                <div class="form-row form-row-2">
                    <div class="form-group">
                        <div class="event-notify-modal__label-row">
                            <label for="notify_template_id">Sablon</label>
                            <a
                                class="event-notify-modal__tpl-link"
                                href="<?= h($templatesListUrl) ?>"
                                target="_blank"
                                rel="noopener"
                                title="Levélsablonok"
                                aria-label="Levélsablonok"
                            >
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 4h7l3 3v13H8z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 4v3h3"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 11h6M10 15h6"/>
                                </svg>
                            </a>
                        </div>
                        <?php if ($notifyEmailTemplatesRendered === []): ?>
                            <p class="help">Nincs sablon. <a href="<?= h(nextgen_url('config/levelsablonok/letrehoz.php')) ?>" target="_blank" rel="noopener">Új sablon</a></p>
                            <input type="hidden" name="notify_template_id" id="notify_template_id" value="0">
                        <?php else: ?>
                            <div class="teszt-email-sor">
                                <select id="notify_template_id" name="notify_template_id">
                                    <?php foreach ($notifyEmailTemplatesRendered as $tpl): ?>
                                        <option value="<?= (int) $tpl['id'] ?>"<?= (int) $tpl['id'] === $selectedTplId ? ' selected' : '' ?>>
                                            <?= h($tpl['nev'] . ' (' . $tpl['kod'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-secondary" id="event-notify-regen" title="Sablon újragenerálása">Újragenerálás</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="notify_smtp">Feladó (SMTP)</label>
                        <select id="notify_smtp" name="notify_smtp"<?= $notifyEmailSmtpAccounts === [] ? ' disabled' : '' ?>>
                            <?php if ($notifyEmailSmtpAccounts === []): ?>
                                <option value="0">Nincs SMTP fiók</option>
                            <?php else: ?>
                                <?php foreach ($notifyEmailSmtpAccounts as $acc): ?>
                                    <?php
                                    $accId = (int) $acc['id'];
                                    $accLabel = trim($acc['nev'] !== '' ? $acc['nev'] : 'Feladó');
                                    $from = trim($acc['from_name']);
                                    $fromEmail = trim($acc['from_email']);
                                    if ($from !== '' && $fromEmail !== '') {
                                        $accLabel .= ' – ' . $from . ' <' . $fromEmail . '>';
                                    } elseif ($fromEmail !== '') {
                                        $accLabel .= ' – ' . $fromEmail;
                                    }
                                    ?>
                                    <option value="<?= $accId ?>"<?= $accId === $notifyEmailSmtpId ? ' selected' : '' ?>><?= h($accLabel) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notify_to">Címzett *</label>
                    <input
                        type="text"
                        id="notify_to"
                        name="notify_to"
                        value="<?= h($toDefault) ?>"
                        required
                        autocomplete="off"
                        data-lpignore="true"
                        data-1p-ignore="true"
                        data-form-type="other"
                        data-event-notify-recipients-url="<?= h(events_url('ajax_event_notify_recipients.php?event_id=' . (int) $id)) ?>"
                        placeholder="email@pelda.hu, masik@pelda.hu"
                    >
                    <p class="help event-notify-blocked" id="event-notify-blocked" hidden></p>
                </div>

                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label for="notify_cc">CC</label>
                        <input
                            type="text"
                            id="notify_cc"
                            name="notify_cc"
                            value="<?= h($initialCc) ?>"
                            placeholder="masolat@pelda.hu"
                            autocomplete="off"
                        >
                        <p class="help">Alapértelmezés a kiválasztott sablonból (Újragenerálás frissíti).</p>
                    </div>
                    <div class="form-group">
                        <label for="notify_bcc">BCC</label>
                        <input type="text" id="notify_bcc" name="notify_bcc" value="<?= h($notifyEmailBcc) ?>" placeholder="<?= h(EVENTS_NOTIFY_EMAIL_DEFAULT_BCC) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="notify_subject">Tárgy *</label>
                    <input type="text" id="notify_subject" name="notify_subject" value="<?= h($initialSubject) ?>" required maxlength="255">
                </div>

                <div class="form-group">
                    <textarea id="notify_html" name="notify_html" class="js-notify-html-source" rows="14" required aria-label="Levél szövege"><?= h($initialHtml) ?></textarea>
                </div>

                <?php if ($notifyEmailSentLogs !== []): ?>
                    <div class="event-notify-modal__log">
                        <h3 class="event-notify-modal__log-title">Korábbi küldések</h3>
                        <ul class="event-notify-modal__log-list">
                            <?php foreach ($notifyEmailSentLogs as $log): ?>
                                <li>
                                    <strong><?= h((string) ($log['sent_at'] ?? '')) ?></strong>
                                    – <?= h((string) ($log['to_emails'] ?? '')) ?>
                                    · <?= h((string) ($log['subject'] ?? '')) ?>
                                    · megnyitás: <?= (int) ($log['open_count'] ?? 0) ?>
                                    <?php if (!empty($log['first_opened_at'])): ?>
                                        (első: <?= h((string) $log['first_opened_at']) ?>)
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <div class="event-notify-modal__toolbar event-notify-modal__toolbar--footer">
                <button type="submit" class="btn btn-primary"<?= !$canSend ? ' disabled' : '' ?>>Küldés</button>
                <button type="button" class="btn btn-secondary" data-event-notify-close>Mégse</button>
            </div>
        </form>
    </div>
</dialog>
<script type="application/json" id="event-notify-templates-json"><?= $templatesJson !== false ? $templatesJson : '[]' ?></script>
<script>
(function () {
    var dialog = document.getElementById('event-notify-email-dialog');
    if (!dialog) return;

    var openBtns = document.querySelectorAll('[data-event-notify-open]');
    var closeBtns = dialog.querySelectorAll('[data-event-notify-close]');
    var select = document.getElementById('notify_template_id');
    var regenBtn = document.getElementById('event-notify-regen');
    var subjectEl = document.getElementById('notify_subject');
    var htmlEl = document.getElementById('notify_html');
    var ccEl = document.getElementById('notify_cc');
    var form = document.getElementById('event-notify-email-form');
    var raw = document.getElementById('event-notify-templates-json');
    var templates = [];
    try {
        templates = raw ? JSON.parse(raw.textContent || '[]') : [];
    } catch (e) {
        templates = [];
    }

    var mode = 'html';
    var visualEl = null;
    var btnHtml = null;
    var btnSource = null;
    var formatBar = null;

    function buildHtmlSourceEditor(textarea) {
        if (!textarea || textarea.dataset.htmlSourceEditor === '1') {
            return;
        }
        textarea.dataset.htmlSourceEditor = '1';

        var wrapper = document.createElement('div');
        wrapper.className = 'html-editor event-notify-html-editor';

        var toolbar = document.createElement('div');
        toolbar.className = 'html-editor-toolbar event-notify-html-editor__modes';

        btnHtml = document.createElement('button');
        btnHtml.type = 'button';
        btnHtml.textContent = 'HTML';
        btnHtml.className = 'is-active';
        btnHtml.setAttribute('aria-pressed', 'true');

        btnSource = document.createElement('button');
        btnSource.type = 'button';
        btnSource.textContent = 'Forráskód';
        btnSource.setAttribute('aria-pressed', 'false');

        toolbar.appendChild(btnHtml);
        toolbar.appendChild(btnSource);

        formatBar = document.createElement('div');
        formatBar.className = 'html-editor-toolbar event-notify-html-editor__format';
        formatBar.innerHTML = ''
            + '<button type="button" data-cmd="bold" title="Félkövér"><strong>B</strong></button>'
            + '<button type="button" data-cmd="italic" title="Dőlt"><em>I</em></button>'
            + '<button type="button" data-cmd="underline" title="Aláhúzott"><u>U</u></button>'
            + '<button type="button" data-cmd="insertUnorderedList" title="Lista">Lista</button>'
            + '<button type="button" data-cmd="insertOrderedList" title="Számozás">Számozás</button>'
            + '<button type="button" data-cmd="createLink" title="Link">Link</button>'
            + '<button type="button" data-cmd="formatBlock" data-value="h2" title="Címsor">Címsor</button>'
            + '<button type="button" data-cmd="formatBlock" data-value="p" title="Bekezdés">Bekezdés</button>';

        visualEl = document.createElement('div');
        visualEl.className = 'html-editor-area';
        visualEl.contentEditable = 'true';
        visualEl.setAttribute('role', 'textbox');
        visualEl.setAttribute('aria-multiline', 'true');
        visualEl.setAttribute('aria-label', 'Levél szövege');
        visualEl.innerHTML = textarea.value || '';

        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(toolbar);
        wrapper.appendChild(formatBar);
        wrapper.appendChild(visualEl);
        wrapper.appendChild(textarea);
        textarea.classList.add('html-editor-source');
        textarea.hidden = true;

        function syncHtmlToField() {
            textarea.value = visualEl.innerHTML;
        }

        function syncFieldToHtml() {
            visualEl.innerHTML = textarea.value || '';
        }

        function setMode(next) {
            if (next === mode) {
                return;
            }
            if (next === 'source') {
                syncHtmlToField();
                visualEl.hidden = true;
                formatBar.hidden = true;
                textarea.hidden = false;
                btnHtml.classList.remove('is-active');
                btnSource.classList.add('is-active');
                btnHtml.setAttribute('aria-pressed', 'false');
                btnSource.setAttribute('aria-pressed', 'true');
            } else {
                syncFieldToHtml();
                textarea.hidden = true;
                visualEl.hidden = false;
                formatBar.hidden = false;
                btnSource.classList.remove('is-active');
                btnHtml.classList.add('is-active');
                btnSource.setAttribute('aria-pressed', 'false');
                btnHtml.setAttribute('aria-pressed', 'true');
            }
            mode = next;
        }

        visualEl.addEventListener('input', syncHtmlToField);

        btnHtml.addEventListener('click', function () {
            setMode('html');
        });
        btnSource.addEventListener('click', function () {
            setMode('source');
        });

        formatBar.addEventListener('click', function (e) {
            var btn = e.target.closest('button[data-cmd]');
            if (!btn || mode !== 'html') {
                return;
            }
            e.preventDefault();
            visualEl.focus();
            var cmd = btn.getAttribute('data-cmd');
            if (cmd === 'createLink') {
                var url = window.prompt('Link URL:', 'https://');
                if (url) {
                    document.execCommand('createLink', false, url);
                }
            } else if (cmd === 'formatBlock') {
                document.execCommand('formatBlock', false, btn.getAttribute('data-value') || 'p');
            } else {
                document.execCommand(cmd, false, null);
            }
            syncHtmlToField();
        });

        if (form) {
            form.addEventListener('submit', function () {
                if (mode === 'html') {
                    syncHtmlToField();
                }
            });
        }

        window.eventNotifyHtmlEditor = {
            setHtml: function (html) {
                textarea.value = html || '';
                if (mode === 'html') {
                    visualEl.innerHTML = html || '';
                }
            },
            setMode: setMode
        };
    }

    if (htmlEl) {
        buildHtmlSourceEditor(htmlEl);
    }

    function refreshRecipients() {
        var toEl = document.getElementById('notify_to');
        var blockedEl = document.getElementById('event-notify-blocked');
        if (!toEl) return Promise.resolve();
        var url = toEl.getAttribute('data-event-notify-recipients-url') || '';
        if (!url) return Promise.resolve();

        // Autofill / elavult érték ne maradjon a friss lista előtt.
        toEl.value = '';
        toEl.setAttribute('readonly', 'readonly');
        if (blockedEl) {
            blockedEl.hidden = true;
            blockedEl.textContent = '';
        }

        return fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
            cache: 'no-store'
        }).then(function (res) {
            return res.json();
        }).then(function (data) {
            if (!data || !data.ok) {
                return;
            }
            toEl.value = typeof data.to === 'string' ? data.to : '';
            if (blockedEl && Array.isArray(data.blocked) && data.blocked.length) {
                blockedEl.textContent = 'Kihagyva (Event bekerült ki): ' + data.blocked.join(', ');
                blockedEl.hidden = false;
            }
        }).catch(function () {
            // marad üres / kézi megadás
        }).finally(function () {
            toEl.removeAttribute('readonly');
        });
    }

    function openDialog() {
        refreshRecipients().finally(function () {
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', 'open');
            }
            document.body.classList.add('event-notify-modal-open');
        });
    }

    function closeDialog() {
        if (typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
        }
        document.body.classList.remove('event-notify-modal-open');
    }

    function applyTemplate(id) {
        var tid = String(id || '');
        var found = null;
        for (var i = 0; i < templates.length; i++) {
            if (String(templates[i].id) === tid) {
                found = templates[i];
                break;
            }
        }
        if (!found) return;
        if (subjectEl) subjectEl.value = found.targy || '';
        if (ccEl) ccEl.value = found.cc_emails || '';
        var html = found.html_tartalom || '';
        if (window.eventNotifyHtmlEditor) {
            window.eventNotifyHtmlEditor.setHtml(html);
        } else if (htmlEl) {
            htmlEl.value = html;
        }
    }

    openBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            openDialog();
        });
    });
    closeBtns.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            closeDialog();
        });
    });
    dialog.addEventListener('click', function (e) {
        if (e.target === dialog) closeDialog();
    });
    dialog.addEventListener('close', function () {
        document.body.classList.remove('event-notify-modal-open');
    });

    if (select) {
        select.addEventListener('change', function () {
            applyTemplate(select.value);
        });
    }
    if (regenBtn) {
        regenBtn.addEventListener('click', function () {
            if (select) applyTemplate(select.value);
        });
    }
})();
</script>
