<?php
declare(strict_types=1);

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/google_drive_backup.php';
require_once __DIR__ . '/../../includes/google_drive_account.php';
require_once __DIR__ . '/../../includes/google_drive_backup_log.php';

requireSuperadmin();

$urlIndex = nextgen_url('admin/backup/');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['google_logout'])) {
	if (csrf_validate('backup_drive')) {
		alatinfo_gdrive_user_session_clear();
		flash('success', 'Ideiglenes Google munkamenet törölve. A mentett fiók megmaradt.');
	}
	redirect($urlIndex);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['google_login_start'])) {
	if (!csrf_validate('backup_drive')) {
		flash('error', 'Biztonsági token érvénytelen. Frissítsd az oldalt, és próbáld újra.');
		redirect($urlIndex);
	}
	$cfgLogin = alatinfo_backup_drive_load_config();
	if ($cfgLogin === null || ($cfgLogin['auth'] ?? '') !== 'oauth_session') {
		flash('error', 'Hiányzik az OAuth alkalmazás konfiguráció (GOOGLE_DRIVE_OAUTH_CLIENT_ID / SECRET a config.local.php-ben).');
		redirect($urlIndex);
	}
	require_once __DIR__ . '/../../includes/google_drive_account.php';
	$remember = !empty($_POST['google_remember']);
	alatinfo_gdrive_oauth_set_return('backup', $remember);
	$authUrl = alatinfo_gdrive_oauth_authorize_url(
		(string) $cfgLogin['oauth_client_id'],
		alatinfo_gdrive_oauth_redirect_uri()
	);
	header('Location: ' . $authUrl);
	exit;
}

$pageTitle = 'Mentés Google Drive-ra';
$extraHead = '<link rel="stylesheet" href="' . h(nextgen_url('assets/css/backup-drive.css')) . '">';
require_once __DIR__ . '/../../partials/header.php';

$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['backup_test'])) {
	if (!csrf_validate('backup_drive')) {
		$testResult = array('ok' => false, 'messages' => array('Biztonsági token érvénytelen. Frissítsd az oldalt, és próbáld újra.'));
	} else {
		$testResult = alatinfo_backup_test_drive_connection();
	}
}

$cfg = alatinfo_backup_drive_load_config();
$googleLoggedIn = alatinfo_gdrive_user_is_logged_in();
$googleEmail = alatinfo_gdrive_user_display_email();
$savedAccount = alatinfo_gdrive_account_load(alatinfo_gdrive_current_admin_id());
$redirectUri = alatinfo_gdrive_oauth_redirect_uri();
$urlSettings = nextgen_url('google_drive_beallitas.php');
$backupLogs = alatinfo_gdrive_backup_log_list(25);
$logTableReady = alatinfo_gdrive_backup_log_table_exists();

$urlStep = nextgen_url('admin/backup/step.php');
$urlPoll = nextgen_url('admin/backup/poll.php');

$flashSuccess = flash('success');
$flashError = flash('error');

$backupSteps = array(
	array('id' => 'auth', 'label' => 'Hitelesítés'),
	array('id' => 'sql', 'label' => 'Adatbázis export'),
	array('id' => 'zip', 'label' => 'Fájlok csomagolása'),
	array('id' => 'upload_sql', 'label' => 'SQL feltöltés'),
	array('id' => 'upload_zip', 'label' => 'ZIP feltöltés'),
	array('id' => 'cleanup', 'label' => 'Befejezés'),
);
?>
<div class="backup-drive-wrap card">
	<header class="backup-drive-header">
		<h2>Mentés Google Drive-ra</h2>
		<p class="backup-drive-intro">SQL (adatbázis) + ZIP (projekt fájlok) a VibeBackup Drive mappába. A mentéshez a mappához jogosult Google fiók kell – <a href="<?= h($urlSettings) ?>">állítsd be a felhasználói beállításoknál</a>.</p>
	</header>

	<?php if ($flashSuccess): ?>
	<p class="msg msg-success"><?= h($flashSuccess) ?></p>
	<?php endif; ?>
	<?php if ($flashError): ?>
	<p class="msg msg-error"><?= h($flashError) ?></p>
	<?php endif; ?>

	<?php if ($cfg === null) { ?>
	<div class="backup-drive-card backup-drive-card--warn">
		<h3 class="backup-drive-card__title">Alkalmazás konfiguráció hiányzik</h3>
		<ul class="backup-drive-list">
			<li>Állítsd be a <code>nextgen/core/config.local.php</code> fájlban:</li>
			<li><code>GOOGLE_DRIVE_OAUTH_CLIENT_ID</code> és <code>GOOGLE_DRIVE_OAUTH_CLIENT_SECRET</code></li>
			<li>Opcionális: <code>GOOGLE_DRIVE_BACKUP_FOLDER_ID</code> (alapértelmezés: VibeBackup)</li>
			<li>Minta: <code>nextgen/includes/google_drive_backup_config.sample.php</code></li>
		</ul>
	</div>
	<?php } else { ?>
	<div class="backup-drive-grid">
		<section class="backup-drive-card">
			<h3 class="backup-drive-card__title">Beállítások</h3>
			<dl class="backup-drive-dl">
				<div class="backup-drive-dl__row">
					<dt>Mappa ID</dt>
					<dd><code><?= h($cfg['folder_id']) ?></code></dd>
				</div>
				<div class="backup-drive-dl__row">
					<dt>Google fiók</dt>
					<dd>
						<?php if ($savedAccount !== null): ?>
							<strong><?= h($savedAccount['email']) ?></strong>
							<span style="font-size:0.85rem;color:#166534;"> (mentve)</span>
						<?php elseif ($googleEmail !== ''): ?>
							<strong><?= h($googleEmail) ?></strong>
							<span style="font-size:0.85rem;color:#64748b;"> (ideiglenes)</span>
						<?php else: ?>
							<span style="color:#b45309;">Nincs összekapcsolva</span>
						<?php endif; ?>
					</dd>
				</div>
			</dl>
			<p class="backup-drive-note">
				<a href="<?= h($urlSettings) ?>">Google Drive fiók beállítások</a>
				<?php if ($savedAccount === null): ?> – itt mentheted el a fiókodat<?php endif; ?>
			</p>
			<?php if ($googleLoggedIn && alatinfo_gdrive_user_session_get() !== null): ?>
			<form method="post" action="<?= h($urlIndex) ?>" style="margin-top:8px;">
				<?= csrf_input('backup_drive') ?>
				<button type="submit" name="google_logout" value="1" class="btn btn-secondary btn-sm">Ideiglenes munkamenet törlése</button>
			</form>
			<?php elseif ($savedAccount === null): ?>
			<form method="post" action="<?= h($urlIndex) ?>" style="margin-top:10px;">
				<?= csrf_input('backup_drive') ?>
				<label style="display:block;margin-bottom:0.5rem;font-size:0.9rem;">
					<input type="checkbox" name="google_remember" value="1" checked> Megjegyzés a felhasználómhoz
				</label>
				<button type="submit" name="google_login_start" value="1" class="btn btn-primary">Bejelentkezés Google-lel</button>
			</form>
			<?php endif; ?>
		</section>

		<section class="backup-drive-card backup-drive-card--wide">
			<h3 class="backup-drive-card__title">Mentés beállítások</h3>
			<form method="post" action="<?= h($urlIndex) ?>" class="backup-drive-form" id="backup-drive-form">
				<?= csrf_input('backup_drive') ?>
				<fieldset class="backup-drive-fieldset">
					<legend>Mit mentsek?</legend>
					<label class="backup-drive-check"><input type="checkbox" name="backup_include_db" value="1" checked> Adatbázis (SQL)</label>
					<label class="backup-drive-check"><input type="checkbox" name="backup_include_files" value="1" checked> Fájlok (ZIP)</label>
				</fieldset>
				<fieldset class="backup-drive-fieldset">
					<legend>Fájlok dátuma (módosítva ettől)</legend>
					<p class="backup-drive-hint" style="margin-top:0;">A dátumszűrő a fájlokra vonatkozik. Az adatbázis mindig teljes export.</p>
					<div class="backup-drive-date-options">
						<label class="backup-drive-radio"><input type="radio" name="backup_date_filter" value="all" checked> Mind</label>
						<label class="backup-drive-radio"><input type="radio" name="backup_date_filter" value="1month"> Utolsó 1 hónap</label>
						<label class="backup-drive-radio"><input type="radio" name="backup_date_filter" value="1week"> Utolsó 1 hét</label>
						<label class="backup-drive-radio"><input type="radio" name="backup_date_filter" value="1day"> Utolsó 1 nap</label>
						<label class="backup-drive-radio"><input type="radio" name="backup_date_filter" value="custom" id="backup-date-custom-radio"> Egyedi dátum</label>
					</div>
					<p id="backup-date-custom-wrap" class="backup-drive-custom-date" hidden>
						<label>Ettől a naptól: <input type="date" name="backup_date_custom" id="backup-date-custom-input"></label>
					</p>
				</fieldset>
				<div class="backup-drive-actions">
					<button type="submit" name="backup_test" value="1" class="btn btn-secondary" id="backup-drive-test-btn"<?= $googleLoggedIn ? '' : ' disabled' ?>>Kapcsolat tesztelése</button>
					<button type="button" class="btn btn-primary" id="backup-drive-run-btn"<?= $googleLoggedIn ? '' : ' disabled' ?>>Mentés indítása (Drive)</button>
				</div>
				<?php if (!$googleLoggedIn): ?>
				<p class="backup-drive-hint" style="color:#b45309;">Kapcsold össze a Google fiókodat a <a href="<?= h($urlSettings) ?>">beállításoknál</a>, vagy jelentkezz be ideiglenesen.</p>
				<?php else: ?>
				<p class="backup-drive-hint">A kapcsolatteszt nem készít exportot – token, mappa, <code>teszt.txt</code> olvasás, írási jog.</p>
				<?php endif; ?>
			</form>
		</section>
	</div>

	<section class="backup-drive-card backup-drive-progress" id="backup-drive-progress" hidden>
		<h3 class="backup-drive-card__title">Mentés folyamatban…</h3>
		<div class="backup-drive-progress__bar-wrap" aria-hidden="true">
			<div class="backup-drive-progress__bar" id="backup-drive-progress-bar" style="width:0%"></div>
		</div>
		<p class="backup-drive-progress__pct" id="backup-drive-progress-pct">0%</p>
		<p class="backup-drive-progress__status" id="backup-drive-progress-status">Indítás…</p>
		<p class="backup-drive-progress__detail" id="backup-drive-progress-detail" hidden></p>
		<ol class="backup-drive-steps" id="backup-drive-steps">
			<?php foreach ($backupSteps as $step) { ?>
			<li class="backup-drive-steps__item" data-step="<?= h($step['id']) ?>" data-default-label="<?= h($step['label']) ?>">
				<span class="backup-drive-steps__icon" aria-hidden="true"></span>
				<span class="backup-drive-steps__main">
					<span class="backup-drive-steps__label"><?= h($step['label']) ?></span>
					<span class="backup-drive-steps__subbar" hidden><span class="backup-drive-steps__subbar-fill"></span></span>
				</span>
			</li>
			<?php } ?>
		</ol>
	</section>
	<?php } ?>

	<?php if ($testResult !== null) { ?>
	<section class="backup-drive-card backup-drive-result <?= $testResult['ok'] ? 'backup-drive-result--ok' : 'backup-drive-result--err' ?>">
		<h3 class="backup-drive-card__title"><?= $testResult['ok'] ? 'Kapcsolatteszt sikeres' : 'Kapcsolatteszt sikertelen' ?></h3>
		<ul class="backup-drive-log">
			<?php foreach ($testResult['messages'] as $m) { ?>
			<li><?= h((string) $m) ?></li>
			<?php } ?>
		</ul>
	</section>
	<?php } ?>

	<section class="backup-drive-card backup-drive-result" id="backup-drive-result" hidden>
		<h3 class="backup-drive-card__title" id="backup-drive-result-title">Mentés eredménye</h3>
		<ul class="backup-drive-log" id="backup-drive-result-log"></ul>
	</section>

	<details class="backup-drive-details">
		<summary>Részletek (mit tartalmaz a mentés?)</summary>
		<ul class="backup-drive-list">
			<li>SQL: adatbázis export (<code>mysqldump</code> vagy PHP) – mindig teljes, ha kiválasztod</li>
			<li>ZIP: projekt fájlok, kivéve <code>.git</code>, <code>secret_folder</code>, <code>nextgen/vendor</code></li>
			<li>Dátumszűrő: csak a megadott időpont után módosított fájlok kerülnek a ZIP-be</li>
			<li>Fájlnevek: <code>alatinfo_db_*.sql</code>, <code>alatinfo_site_*.zip</code></li>
			<li>OAuth redirect URI (Cloud Console): <code><?= h($redirectUri) ?></code></li>
		</ul>
	</details>

	<section class="backup-drive-card backup-drive-log-section">
		<h3 class="backup-drive-card__title">Mentés napló</h3>
		<?php if (!$logTableReady): ?>
		<p class="backup-drive-hint" style="color:#b45309;">A napló tábla hiányzik. Futtasd: <code>nextgen/database/migration_gdrive_backup_log.sql</code></p>
		<?php elseif ($backupLogs === array()): ?>
		<p class="backup-drive-hint">Még nincs mentés naplóbejegyzés.</p>
		<?php else: ?>
		<div class="backup-drive-log-table-wrap">
			<table class="backup-drive-log-table">
				<thead>
					<tr>
						<th>Idő</th>
						<th>Admin</th>
						<th>Státusz</th>
						<th>Cél</th>
						<th>Szűrő</th>
						<th>Fájlok</th>
						<th>Részletek</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($backupLogs as $logRow): ?>
					<?php
					$status = (string) ($logRow['status'] ?? '');
					$statusClass = $status === 'ok' ? 'ok' : ($status === 'error' ? 'err' : 'run');
					$statusLabel = match ($status) {
						'ok' => 'OK',
						'error' => 'Hiba',
						'running' => 'Fut',
						default => $status,
					};
					$filterKey = (string) ($logRow['date_filter'] ?? 'all');
					$filterLabel = alatinfo_backup_date_filter_label(
						$filterKey,
						'',
						!empty($logRow['date_from']) ? strtotime((string) $logRow['date_from']) : null
					);
					$fileParts = array();
					if (!empty($logRow['sql_drive_name'])) {
						$fileParts[] = (string) $logRow['sql_drive_name'];
					}
					if (!empty($logRow['zip_drive_name'])) {
						$fileParts[] = (string) $logRow['zip_drive_name'];
					}
					?>
					<tr>
						<td><?= h((string) ($logRow['started_at'] ?? '')) ?></td>
						<td><?= h((string) ($logRow['admin_nev'] ?? '—')) ?></td>
						<td><span class="backup-drive-status backup-drive-status--<?= h($statusClass) ?>"><?= h($statusLabel) ?></span></td>
						<td><?= h(alatinfo_gdrive_backup_log_format_targets($logRow)) ?></td>
						<td><?= h($filterLabel) ?></td>
						<td><?= h($fileParts !== array() ? implode(', ', $fileParts) : '—') ?></td>
						<td>
							<?php if (!empty($logRow['log_text'])): ?>
							<details><summary>Log</summary><pre class="backup-drive-log-pre"><?= h((string) $logRow['log_text']) ?></pre></details>
							<?php else: ?>—<?php endif; ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php endif; ?>
	</section>
</div>
<script>
(function () {
	var runBtn = document.getElementById('backup-drive-run-btn');
	var testBtn = document.getElementById('backup-drive-test-btn');
	var form = document.getElementById('backup-drive-form');
	var progressBox = document.getElementById('backup-drive-progress');
	var progressBar = document.getElementById('backup-drive-progress-bar');
	var progressPct = document.getElementById('backup-drive-progress-pct');
	var progressStatus = document.getElementById('backup-drive-progress-status');
	var progressDetail = document.getElementById('backup-drive-progress-detail');
	var stepsRoot = document.getElementById('backup-drive-steps');
	var resultBox = document.getElementById('backup-drive-result');
	var resultTitle = document.getElementById('backup-drive-result-title');
	var resultLog = document.getElementById('backup-drive-result-log');
	var stepUrl = <?= json_encode($urlStep, JSON_UNESCAPED_UNICODE) ?>;
	var pollUrl = <?= json_encode($urlPoll, JSON_UNESCAPED_UNICODE) ?>;
	var customDateWrap = document.getElementById('backup-date-custom-wrap');
	var customDateRadio = document.getElementById('backup-date-custom-radio');
	var includeDbInput = form ? form.querySelector('[name=backup_include_db]') : null;
	var includeFilesInput = form ? form.querySelector('[name=backup_include_files]') : null;

	function toggleCustomDate() {
		if (!customDateWrap || !customDateRadio) {
			return;
		}
		customDateWrap.hidden = !customDateRadio.checked;
	}

	function buildStepsOrder() {
		var steps = ['start'];
		if (includeDbInput && includeDbInput.checked) {
			steps.push('sql');
		}
		if (includeFilesInput && includeFilesInput.checked) {
			steps.push('zip');
		}
		if (includeDbInput && includeDbInput.checked) {
			steps.push('upload_sql');
		}
		if (includeFilesInput && includeFilesInput.checked) {
			steps.push('upload_zip');
		}
		steps.push('cleanup');
		return steps;
	}

	function updateStepVisibility() {
		if (!stepsRoot) {
			return;
		}
		var incDb = includeDbInput && includeDbInput.checked;
		var incFiles = includeFilesInput && includeFilesInput.checked;
		stepsRoot.querySelectorAll('.backup-drive-steps__item').forEach(function (el) {
			var step = el.getAttribute('data-step');
			var show = step === 'auth' || step === 'cleanup';
			if (step === 'sql' || step === 'upload_sql') {
				show = incDb;
			}
			if (step === 'zip' || step === 'upload_zip') {
				show = incFiles;
			}
			el.hidden = !show;
		});
	}

	if (form) {
		form.querySelectorAll('[name=backup_date_filter]').forEach(function (el) {
			el.addEventListener('change', toggleCustomDate);
		});
		if (includeDbInput) {
			includeDbInput.addEventListener('change', updateStepVisibility);
		}
		if (includeFilesInput) {
			includeFilesInput.addEventListener('change', updateStepVisibility);
		}
		toggleCustomDate();
		updateStepVisibility();
	}

	if (!runBtn || !form) {
		return;
	}

	function setBusy(busy) {
		runBtn.disabled = busy;
		if (testBtn) {
			testBtn.disabled = busy;
		}
		runBtn.textContent = busy ? 'Mentés folyamatban…' : 'Mentés indítása (Drive)';
	}

	function resetSteps() {
		if (!stepsRoot) {
			return;
		}
		stepsRoot.querySelectorAll('.backup-drive-steps__item').forEach(function (el) {
			el.classList.remove('is-active', 'is-done', 'is-error');
			var labelEl = el.querySelector('.backup-drive-steps__label');
			var base = el.getAttribute('data-default-label') || '';
			if (labelEl && base) {
				labelEl.textContent = base;
			}
			var subbar = el.querySelector('.backup-drive-steps__subbar');
			if (subbar) {
				subbar.hidden = true;
			}
			var subfill = el.querySelector('.backup-drive-steps__subbar-fill');
			if (subfill) {
				subfill.style.width = '0%';
			}
		});
		if (progressDetail) {
			progressDetail.hidden = true;
			progressDetail.textContent = '';
		}
	}

	function updateStepUploadUi(stepId, uploadPercent) {
		if (!stepsRoot || !stepId) {
			return;
		}
		stepsRoot.querySelectorAll('.backup-drive-steps__item').forEach(function (el) {
			var subbar = el.querySelector('.backup-drive-steps__subbar');
			var subfill = el.querySelector('.backup-drive-steps__subbar-fill');
			var labelEl = el.querySelector('.backup-drive-steps__label');
			var base = el.getAttribute('data-default-label') || '';
			if (el.getAttribute('data-step') !== stepId) {
				if (subbar) {
					subbar.hidden = true;
				}
				if (labelEl && base && el.classList.contains('is-active')) {
					labelEl.textContent = base;
				}
				return;
			}
			if (typeof uploadPercent === 'number') {
				if (labelEl && base) {
					labelEl.textContent = base + ' (' + uploadPercent + '%)';
				}
				if (subbar && subfill) {
					subbar.hidden = false;
					subfill.style.width = uploadPercent + '%';
				}
			} else if (labelEl && base) {
				labelEl.textContent = base;
				if (subbar) {
					subbar.hidden = true;
				}
			}
		});
	}

	function markStep(stepId, state) {
		if (!stepsRoot || !stepId) {
			return;
		}
		var el = stepsRoot.querySelector('[data-step="' + stepId + '"]');
		if (!el) {
			return;
		}
		el.classList.remove('is-active', 'is-done', 'is-error');
		if (state) {
			el.classList.add(state);
		}
	}

	function updateProgress(data) {
		if (!progressBox) {
			return;
		}
		progressBox.hidden = false;
		var pct = typeof data.percent === 'number' ? data.percent : 0;
		if (progressBar) {
			progressBar.style.width = pct + '%';
		}
		if (progressPct) {
			progressPct.textContent = pct + '%';
		}
		if (progressStatus && data.message) {
			progressStatus.textContent = data.message;
		}
		if (progressDetail) {
			if (data.detail) {
				progressDetail.textContent = data.detail;
				progressDetail.hidden = false;
			} else if (typeof data.upload_percent !== 'number') {
				progressDetail.hidden = true;
				progressDetail.textContent = '';
			}
		}
		if (data.step) {
			stepsRoot.querySelectorAll('.backup-drive-steps__item.is-active').forEach(function (el) {
				if (el.getAttribute('data-step') !== data.step) {
					el.classList.remove('is-active');
					if (!el.classList.contains('is-error')) {
						el.classList.add('is-done');
					}
				}
			});
			markStep(data.step, pct >= 100 ? 'is-done' : 'is-active');
			if (typeof data.upload_percent === 'number') {
				updateStepUploadUi(data.step, data.upload_percent);
			} else {
				updateStepUploadUi(data.step, null);
			}
		}
	}

	function showResult(ok, messages) {
		if (!resultBox || !resultLog || !resultTitle) {
			return;
		}
		resultBox.hidden = false;
		resultBox.classList.remove('backup-drive-result--ok', 'backup-drive-result--err');
		resultBox.classList.add(ok ? 'backup-drive-result--ok' : 'backup-drive-result--err');
		resultTitle.textContent = ok ? 'Mentés sikeres' : 'Mentés sikertelen';
		resultLog.innerHTML = '';
		(messages || []).forEach(function (msg) {
			var li = document.createElement('li');
			li.textContent = msg;
			resultLog.appendChild(li);
		});
		resultBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
	}

	runBtn.addEventListener('click', function () {
		if (includeDbInput && includeFilesInput && !includeDbInput.checked && !includeFilesInput.checked) {
			showResult(false, ['Legalább az adatbázist vagy a fájlokat válaszd ki.']);
			return;
		}
		var customRadio = document.getElementById('backup-date-custom-radio');
		var customInput = document.getElementById('backup-date-custom-input');
		if (customRadio && customRadio.checked && customInput && !customInput.value) {
			showResult(false, ['Egyedi dátum esetén add meg a dátumot.']);
			return;
		}
		setBusy(true);
		resetSteps();
		updateStepVisibility();
		if (resultBox) {
			resultBox.hidden = true;
		}
		updateProgress({ percent: 0, message: 'Mentés indítása…', step: 'auth' });

		var jobId = '';
		var allMessages = [];
		var pollTimer = null;

		function stopPoll() {
			if (pollTimer !== null) {
				clearInterval(pollTimer);
				pollTimer = null;
			}
		}

		function startPoll() {
			stopPoll();
			if (!jobId) {
				return;
			}
			function pollOnce() {
				fetch(pollUrl + '?job_id=' + encodeURIComponent(jobId), { credentials: 'same-origin' })
					.then(function (r) { return r.json(); })
					.then(function (j) {
						if (j && j.progress) {
							updateProgress(j.progress);
						}
					})
					.catch(function () {});
			}
			pollOnce();
			pollTimer = setInterval(pollOnce, 400);
		}

		function runStep(stepName) {
			var fd = new FormData(form);
			fd.set('backup_step', stepName);
			fd.delete('backup_test');
			if (stepName === 'sql' || stepName === 'zip' || stepName === 'upload_sql' || stepName === 'upload_zip') {
				startPoll();
			}
			return fetch(stepUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (resp) {
					return resp.json().then(function (j) {
						if (!resp.ok && !j) {
							throw new Error('HTTP ' + resp.status);
						}
						return j;
					});
				})
				.finally(function () {
					stopPoll();
				});
		}

		var backupStepsOrder = buildStepsOrder();

		backupStepsOrder.reduce(function (chain, stepName) {
			return chain.then(function () {
				return runStep(stepName).then(function (j) {
					if (!j || !j.ok) {
						if (j && j.progress) {
							updateProgress(j.progress);
						}
						var msgs = (j && j.messages) ? j.messages : ['Ismeretlen hiba.'];
						showResult(false, allMessages.concat(msgs));
						throw new Error('step_failed');
					}
					if (j.job_id) {
						jobId = j.job_id;
					}
					if (j.progress) {
						updateProgress(j.progress);
					}
					if (Array.isArray(j.messages)) {
						allMessages = allMessages.concat(j.messages);
					}
					if (j.done) {
						updateProgress({ percent: 100, message: 'Kész.', step: 'cleanup' });
						showResult(true, allMessages);
						setTimeout(function () { window.location.reload(); }, 1500);
					}
				});
			});
		}, Promise.resolve())
			.catch(function (err) {
				if (err && err.message === 'step_failed') {
					return;
				}
				showResult(false, allMessages.concat(['Hálózati vagy szerver hiba: ' + err.message]));
				updateProgress({ percent: 0, message: 'Megszakadt.' });
			})
			.finally(function () {
				stopPoll();
				setBusy(false);
			});
	});
})();
</script>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
