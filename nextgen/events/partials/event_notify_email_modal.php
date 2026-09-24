<?php
declare(strict_types=1);

/**
 * Esemény szervezői értesítő e-mail előugró (<dialog>).
 *
 * @var int $id
 * @var list<array{id: int, nev: string, kod: string, targy: string, html_tartalom: string}> $notifyEmailTemplatesRendered
 * @var array{id: int, nev: string, kod: string, targy: string, html_tartalom: string}|null $notifyEmailSelected
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
$toDefault = implode(', ', $notifyEmailRecipients);
$canSend = $notifyEmailSmtpAccounts !== [];
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
                        <label for="notify_template_id">Levélsablon</label>
                        <?php if ($notifyEmailTemplatesRendered === []): ?>
                            <p class="help">Nincs sablon. <a href="<?= h(nextgen_url('config/levelsablonok/letrehoz.php')) ?>" target="_blank" rel="noopener">Új sablon</a></p>
                            <input type="hidden" name="notify_template_id" value="0">
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
                            <p class="help">
                                Megnyitáskor a default sablonból generálódik a levél.
                                <a href="<?= h(nextgen_url('config/levelsablonok/')) ?>" target="_blank" rel="noopener">Sablonok / default beállítás</a>
                            </p>
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
                        <p class="help">Preferált: <code><?= h(EVENTS_NOTIFY_EMAIL_PREFERRED_FROM) ?></code></p>
                    </div>
                </div>

                <div class="form-row form-row-2">
                    <div class="form-group">
                        <label for="notify_to">Címzett *</label>
                        <input type="text" id="notify_to" name="notify_to" value="<?= h($toDefault) ?>" required placeholder="email@pelda.hu, masik@pelda.hu">
                        <p class="help">Több cím vesszővel. Default: szervező(k) partner / portál e-mailjei.</p>
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
                    <label for="notify_html">Levél szövege (HTML) *</label>
                    <textarea id="notify_html" name="notify_html" class="js-notify-html-source" rows="14" required><?= h($initialHtml) ?></textarea>
                    <p class="help">
                        Változók a sablonban: <code>{{organizer_names}}</code>, <code>{{event_name}}</code>,
                        <code>{{event_id}}</code>, <code>{{event_start}}</code>, <code>{{event_end}}</code>,
                        <code>{{venue_name}}</code>, <code>{{event_url}}</code>, <code>{{site_name}}</code>
                    </p>
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
    var raw = document.getElementById('event-notify-templates-json');
    var templates = [];
    try {
        templates = raw ? JSON.parse(raw.textContent || '[]') : [];
    } catch (e) {
        templates = [];
    }

    var mode = 'html'; // html | source
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
            + '<button type="button" data-cmd="createLink" title="Link">Link</button>'
            + '<button type="button" data-cmd="formatBlock" data-value="p" title="Bekezdés">P</button>';

        visualEl = document.createElement('div');
        visualEl.className = 'html-editor-area';
        visualEl.contentEditable = 'true';
        visualEl.setAttribute('role', 'textbox');
        visualEl.setAttribute('aria-multiline', 'true');
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
        textarea.addEventListener('input', function () {
            // forráskód szerkesztés közben a mező az igazság
        });

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

        var form = textarea.closest('form');
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

    function openDialog() {
        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', 'open');
        }
        document.body.classList.add('event-notify-modal-open');
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

    if (regenBtn) {
        regenBtn.addEventListener('click', function () {
            if (select) applyTemplate(select.value);
        });
    }
})();
</script>
