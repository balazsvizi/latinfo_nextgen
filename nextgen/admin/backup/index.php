<?php
declare(strict_types=1);

require_once __DIR__ . '/../../init.php';

$pageTitle = 'Mentés Google Drive-ra';
$extraHead = '<link rel="stylesheet" href="' . h(nextgen_url('assets/css/backup-drive.css')) . '">';
require_once __DIR__ . '/../../partials/header.php';

requireSuperadmin();
require_once __DIR__ . '/../../includes/google_drive_backup.php';

$testResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['backup_test'])) {
	if (!csrf_validate('backup_drive')) {
		$testResult = array('ok' => false, 'messages' => array('Biztonsági token érvénytelen. Frissítsd az oldalt, és próbáld újra.'));
	} else {
		$testResult = alatinfo_backup_test_drive_connection();
	}
}

$cfg = alatinfo_backup_drive_load_config();
$sa_email = '';
if ($cfg !== null && $cfg['auth'] === 'service_account') {
	$sa = alatinfo_gdrive_load_service_account($cfg['json_path']);
	if ($sa !== null) {
		$sa_email = $sa['client_email'];
	}
}

$urlIndex = nextgen_url('admin/backup/');
$urlOauth = nextgen_url('admin/backup/oauth.php');
$urlStep = nextgen_url('admin/backup/step.php');
$urlPoll = nextgen_url('admin/backup/poll.php');

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
		<p class="backup-drive-intro">SQL (adatbázis) + ZIP (projekt fájlok) a VibeBackup Drive mappába.</p>
	</header>

	<?php if ($cfg === null) { ?>
	<div class="backup-drive-card backup-drive-card--warn">
		<h3 class="backup-drive-card__title">Konfiguráció hiányzik</h3>
		<ul class="backup-drive-list">
			<li>Környezeti változók vagy <code>secret_folder/google_drive_backup_config.php</code></li>
			<li>Minta: <code>nextgen/includes/google_drive_backup_config.sample.php</code></li>
			<li>Gmail mappa: <a href="<?= h($urlOauth) ?>">OAuth beállítás</a> (automatikusan menti a secret_folder mappába)</li>
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
					<dt>Hitelesítés</dt>
					<dd><?= h($cfg['auth'] === 'oauth_refresh' ? 'OAuth (Gmail)' : 'Service account') ?></dd>
				</div>
				<?php if ($cfg['auth'] === 'service_account') { ?>
				<div class="backup-drive-dl__row">
					<dt>Kulcs fájl</dt>
					<dd><code><?= h($cfg['json_path']) ?></code></dd>
				</div>
				<?php if ($sa_email !== '') { ?>
				<div class="backup-drive-dl__row">
					<dt>Service account</dt>
					<dd><code><?= h($sa_email) ?></code></dd>
				</div>
				<?php } ?>
				<?php } ?>
			</dl>
			<?php if ($cfg['auth'] === 'oauth_refresh') { ?>
			<p class="backup-drive-note">Mentés a Gmail fiók tárhelyére. Token: <a href="<?= h($urlOauth) ?>">OAuth beállítás</a>.</p>
			<?php } elseif ($sa_email !== '') { ?>
			<p class="backup-drive-note">Személyes Gmail mappán a service account csak olvasni tud – íráshoz <a href="<?= h($urlOauth) ?>">OAuth</a>.</p>
			<?php } ?>
		</section>

		<section class="backup-drive-card">
			<h3 class="backup-drive-card__title">Műveletek</h3>
			<form method="post" action="<?= h($urlIndex) ?>" class="backup-drive-form" id="backup-drive-form">
				<?= csrf_input('backup_drive') ?>
				<div class="backup-drive-actions">
					<button type="submit" name="backup_test" value="1" class="btn btn-secondary" id="backup-drive-test-btn">Kapcsolat tesztelése</button>
					<button type="button" class="btn btn-primary" id="backup-drive-run-btn">Mentés indítása (Drive)</button>
					<a href="<?= h($urlOauth) ?>" class="btn btn-secondary backup-drive-actions__link">OAuth beállítás</a>
				</div>
				<p class="backup-drive-hint">A kapcsolatteszt nem készít exportot – token, mappa, <code>teszt.txt</code> olvasás, írási jog.</p>
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
			<li>SQL: adatbázis export (<code>mysqldump</code> vagy PHP)</li>
			<li>ZIP: projekt fájlok, kivéve <code>.git</code>, <code>secret_folder</code>, <code>nextgen/vendor</code></li>
			<li>Fájlnevek: <code>alatinfo_db_*.sql</code>, <code>alatinfo_site_*.zip</code></li>
		</ul>
	</details>
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
	var backupStepsOrder = ['start', 'sql', 'zip', 'upload_sql', 'upload_zip', 'cleanup'];

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
		setBusy(true);
		resetSteps();
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
