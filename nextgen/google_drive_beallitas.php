<?php
declare(strict_types=1);

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/google_drive_backup.php';
require_once __DIR__ . '/includes/google_drive_account.php';

requireSuperadmin();

$urlSettings = nextgen_url('google_drive_beallitas.php');
$urlOauthStart = nextgen_url('admin/backup/oauth.php');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['google_disconnect'])) {
	if (csrf_validate('gdrive_settings')) {
		$adminId = alatinfo_gdrive_current_admin_id();
		alatinfo_gdrive_account_clear($adminId);
		alatinfo_gdrive_user_session_clear();
		flash('success', 'Google Drive fiók leválasztva.');
	}
	redirect($urlSettings);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['google_connect'])) {
	if (!csrf_validate('gdrive_settings')) {
		flash('error', 'Biztonsági token érvénytelen. Frissítsd az oldalt, és próbáld újra.');
		redirect($urlSettings);
	}
	$cfg = alatinfo_backup_drive_load_config();
	if ($cfg === null || ($cfg['auth'] ?? '') !== 'oauth_session') {
		flash('error', 'Hiányzik az OAuth alkalmazás konfiguráció (GOOGLE_DRIVE_OAUTH_CLIENT_ID / SECRET a config.local.php-ben).');
		redirect($urlSettings);
	}
	alatinfo_gdrive_oauth_set_return('settings', true);
	$authUrl = alatinfo_gdrive_oauth_authorize_url(
		(string) $cfg['oauth_client_id'],
		alatinfo_gdrive_oauth_redirect_uri()
	);
	header('Location: ' . $authUrl);
	exit;
}

$pageTitle = 'Google Drive fiók';
require_once __DIR__ . '/partials/header.php';

$adminId = alatinfo_gdrive_current_admin_id();
$account = alatinfo_gdrive_account_load($adminId);
$cfg = alatinfo_backup_drive_load_config();
$redirectUri = alatinfo_gdrive_oauth_redirect_uri();
$tableReady = alatinfo_gdrive_account_table_exists();

$flashSuccess = flash('success');
$flashError = flash('error');
?>
<div class="card card-narrow">
	<h2>Google Drive fiók</h2>
	<p>Bejelentkezve: <strong><?= h($_SESSION['admin_nev']) ?></strong></p>
	<p>Itt kapcsolhatod össze a Google fiókodat a Drive mentéshez. A mentés a te fiókod jogosultságait használja – a token titkosítva, a felhasználódhoz kötve kerül mentésre.</p>

	<?php if ($flashSuccess): ?>
	<p class="msg msg-success"><?= h($flashSuccess) ?></p>
	<?php endif; ?>
	<?php if ($flashError): ?>
	<p class="msg msg-error"><?= h($flashError) ?></p>
	<?php endif; ?>

	<?php if (!$tableReady): ?>
	<p class="msg msg-error">Hiányzik az adatbázis tábla. Futtasd: <code>nextgen/database/migration_admin_gdrive.sql</code></p>
	<?php endif; ?>

	<?php if ($cfg === null): ?>
	<p class="msg msg-error">Az OAuth alkalmazás nincs beállítva. Add meg a <code>config.local.php</code>-ben a <code>GOOGLE_DRIVE_OAUTH_CLIENT_ID</code> és <code>GOOGLE_DRIVE_OAUTH_CLIENT_SECRET</code> értékeket.</p>
	<?php else: ?>
	<dl style="margin:1rem 0;">
		<dt style="font-weight:600;color:#64748b;">Drive mappa</dt>
		<dd><code><?= h($cfg['folder_id']) ?></code></dd>
		<dt style="font-weight:600;color:#64748b;margin-top:0.75rem;">Összekapcsolt Google fiók</dt>
		<dd>
			<?php if ($account !== null): ?>
				<strong><?= h($account['email']) ?></strong>
				<?php if ($account['updated_at'] !== ''): ?>
				<br><span style="font-size:0.88rem;color:#64748b;">Frissítve: <?= h($account['updated_at']) ?></span>
				<?php endif; ?>
			<?php else: ?>
				<span style="color:#b45309;">Nincs mentett fiók</span>
			<?php endif; ?>
		</dd>
	</dl>

	<div class="form-actions">
		<form method="post" style="display:inline;">
			<?= csrf_input('gdrive_settings') ?>
			<button type="submit" name="google_connect" value="1" class="btn btn-primary"<?= $tableReady ? '' : ' disabled' ?>>
				<?= $account !== null ? 'Google fiók újracsatlakoztatása' : 'Google fiók összekapcsolása' ?>
			</button>
		</form>
		<?php if ($account !== null): ?>
		<form method="post" style="display:inline;margin-left:0.5rem;">
			<?= csrf_input('gdrive_settings') ?>
			<button type="submit" name="google_disconnect" value="1" class="btn btn-secondary" onclick="return confirm('Biztosan leválasztod a Google fiókot?');">Leválasztás</button>
		</form>
		<?php endif; ?>
		<a href="<?= h(nextgen_url('admin/backup/')) ?>" class="btn btn-secondary">Mentés Google Drive-ra</a>
	</div>

	<details style="margin-top:1.25rem;font-size:0.88rem;color:#64748b;">
		<summary>Cloud Console beállítás</summary>
		<p style="margin:0.5rem 0 0;">Engedélyezett átirányítási URI:</p>
		<p><code><?= h($redirectUri) ?></code></p>
	</details>
	<?php endif; ?>
</div>
<?php require_once __DIR__ . '/partials/footer.php'; ?>
