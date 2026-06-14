<?php
declare(strict_types=1);

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/google_drive_backup.php';

if (!function_exists('alatinfo_backup_google_drive_oauth_load_clients')) {
	/**
	 * @return array{client_id:string,client_secret:string}
	 */
	function alatinfo_backup_google_drive_oauth_load_clients(): array
	{
		$clientId = '';
		$clientSecret = '';
		$partialCfg = alatinfo_backup_drive_config_path();
		if (is_readable($partialCfg)) {
			/** @noinspection PhpIncludeInspection */
			$a = include $partialCfg;
			if (is_array($a)) {
				$clientId = trim((string)($a['oauth_client_id'] ?? ''));
				$clientSecret = trim((string)($a['oauth_client_secret'] ?? ''));
			}
		}
		if ($clientId === '') {
			$clientId = trim((string)(getenv('GOOGLE_DRIVE_OAUTH_CLIENT_ID') ?: ''));
		}
		if ($clientSecret === '') {
			$clientSecret = trim((string)(getenv('GOOGLE_DRIVE_OAUTH_CLIENT_SECRET') ?: ''));
		}
		return array('client_id' => $clientId, 'client_secret' => $clientSecret);
	}
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && !empty($_POST['oauth_start'])) {
	requireSuperadmin();
	if (!csrf_validate('backup_oauth')) {
		$_SESSION['alatinfo_gdrive_oauth_error'] = 'Biztonsági token érvénytelen. Frissítsd az oldalt, és próbáld újra.';
		redirect(nextgen_url('admin/backup/oauth.php'));
	}

	$clients = alatinfo_backup_google_drive_oauth_load_clients();
	$postClientId = trim((string)($_POST['oauth_client_id'] ?? ''));
	$postClientSecret = trim((string)($_POST['oauth_client_secret'] ?? ''));
	if ($postClientId !== '') {
		$clients['client_id'] = $postClientId;
	}
	if ($postClientSecret !== '') {
		$clients['client_secret'] = $postClientSecret;
	}

	if ($clients['client_id'] === '' || $clients['client_secret'] === '') {
		$_SESSION['alatinfo_gdrive_oauth_error'] = 'Add meg az OAuth Client ID-t és Client Secret-et.';
		redirect(nextgen_url('admin/backup/oauth.php'));
	}

	$idCheck = alatinfo_gdrive_oauth_validate_client_id($clients['client_id']);
	if (!$idCheck['ok']) {
		$_SESSION['alatinfo_gdrive_oauth_error'] = $idCheck['error'];
		redirect(nextgen_url('admin/backup/oauth.php'));
	}
	$secretCheck = alatinfo_gdrive_oauth_validate_client_secret($clients['client_secret']);
	if (!$secretCheck['ok']) {
		$_SESSION['alatinfo_gdrive_oauth_error'] = $secretCheck['error'];
		redirect(nextgen_url('admin/backup/oauth.php'));
	}

	$_SESSION['alatinfo_gdrive_oauth_client_id'] = $clients['client_id'];
	$_SESSION['alatinfo_gdrive_oauth_client_secret'] = $clients['client_secret'];

	$authUrl = alatinfo_gdrive_oauth_authorize_url(
		$clients['client_id'],
		alatinfo_gdrive_oauth_redirect_uri()
	);
	header('Location: ' . $authUrl);
	exit;
}

$pageTitle = 'Drive OAuth beállítás';
require_once __DIR__ . '/../../partials/header.php';

requireSuperadmin();

$redirectUri = alatinfo_gdrive_oauth_redirect_uri();
$oauthResult = null;
$clients = alatinfo_backup_google_drive_oauth_load_clients();
$clientId = $clients['client_id'];
$clientSecret = $clients['client_secret'];

if (!empty($_SESSION['alatinfo_gdrive_oauth_error'])) {
	$oauthResult = array('ok' => false, 'message' => (string) $_SESSION['alatinfo_gdrive_oauth_error']);
	unset($_SESSION['alatinfo_gdrive_oauth_error']);
}

$oauthError = trim((string)($_GET['error'] ?? ''));
if ($oauthResult === null && $oauthError !== '') {
	$oauthErrorDesc = trim((string)($_GET['error_description'] ?? ''));
	if ($oauthError === 'access_denied') {
		$oauthResult = array(
			'ok' => false,
			'message' => 'Google hozzáférés megtagadva (403 access_denied). Az OAuth app „Tesztelés” módban van – add hozzá a bejelentkező Gmail címet tesztfelhasználóként a Cloud Console-ban.',
		);
	} else {
		$msg = 'Google OAuth hiba: ' . $oauthError;
		if ($oauthErrorDesc !== '') {
			$msg .= ' – ' . $oauthErrorDesc;
		}
		$oauthResult = array('ok' => false, 'message' => $msg);
	}
}

$code = trim((string)($_GET['code'] ?? ''));
if ($code !== '') {
	if ($clientId === '' && !empty($_SESSION['alatinfo_gdrive_oauth_client_id'])) {
		$clientId = trim((string) $_SESSION['alatinfo_gdrive_oauth_client_id']);
	}
	if ($clientSecret === '' && !empty($_SESSION['alatinfo_gdrive_oauth_client_secret'])) {
		$clientSecret = trim((string) $_SESSION['alatinfo_gdrive_oauth_client_secret']);
	}
}
if ($code !== '' && $clientId !== '' && $clientSecret !== '') {
	$exchange = alatinfo_gdrive_oauth_exchange_code($clientId, $clientSecret, $redirectUri, $code);
	if (!$exchange['ok']) {
		$oauthResult = array('ok' => false, 'message' => 'Token csere sikertelen: ' . $exchange['error']);
	} elseif ($exchange['refresh_token'] === '') {
		$oauthResult = array(
			'ok' => false,
			'message' => 'Nem érkezett refresh token. Töröld a Google fiókodnál az app hozzáférését, majd próbáld újra (prompt=consent).',
		);
	} else {
		$saved = alatinfo_backup_drive_save_oauth_config($clientId, $clientSecret, $exchange['refresh_token']);
		if ($saved['ok']) {
			$oauthResult = array(
				'ok' => true,
				'message' => $saved['message'] . ' A VibeBackup mappa (ID: ' . alatinfo_backup_drive_default_folder_id() . ') használatban.',
			);
		} else {
			$oauthResult = array(
				'ok' => false,
				'message' => $saved['message'],
			);
		}
		unset($_SESSION['alatinfo_gdrive_oauth_client_id'], $_SESSION['alatinfo_gdrive_oauth_client_secret']);
	}
}

$urlBack = nextgen_url('admin/backup/');
$urlOauth = nextgen_url('admin/backup/oauth.php');
?>
<div class="card" style="max-width:52rem;">
	<h2>Google Drive OAuth beállítás</h2>
	<p>Személyes Gmail Saját meghajtó mappába (VibeBackup) a service account <strong>nem tud írni</strong>. Itt a mappa tulajdonos Gmail fiókjával kérsz refresh tokent – a beállítások automatikusan a <code>secret_folder/google_drive_backup_config.php</code> fájlba kerülnek.</p>
	<p><a href="<?= h($urlBack) ?>">← Vissza: Mentés Google Drive-ra</a></p>

	<h3>1. Google Cloud Console</h3>
	<ol style="margin:0 0 1rem 1.2rem;">
		<li><strong>OAuth beleegyező képernyő</strong>: típus <em>Külső</em>, alkalmazásnév és fejlesztői e-mail kötelező.<br>
			<strong style="color:#b45309;">Tesztelés mód:</strong> <strong>Tesztfelhasználók</strong> → add hozzá a VibeBackup mappa tulajdonosának Gmail címét.</li>
		<li><strong>Hitelesítő adatok → OAuth kliens</strong> – típus: <em>Webalkalmazás</em>.</li>
		<li>Engedélyezett átirányítási URI (pontosan):<br>
			<code><?= h($redirectUri) ?></code></li>
	</ol>

	<h3>2. Bejelentkezés</h3>
	<form method="post" action="<?= h($urlOauth) ?>">
		<?= csrf_input('backup_oauth') ?>
		<p><label>OAuth Client ID<br>
			<input type="text" name="oauth_client_id" value="<?= h($clientId) ?>" placeholder="123456789-xxxxx.apps.googleusercontent.com" style="width:100%;max-width:36rem;" autocomplete="off"></label></p>
		<p><label>OAuth Client Secret<br>
			<input type="password" name="oauth_client_secret" value="<?= h($clientSecret) ?>" placeholder="GOCSPX-..." style="width:100%;max-width:36rem;" autocomplete="off"></label></p>
		<p><button type="submit" name="oauth_start" value="1" class="btn btn-primary">Google-bejelentkezés (mappa tulajdonosa)</button></p>
		<p style="font-size:0.88rem;color:#64748b;">A bejelentkezés után a refresh token automatikusan mentésre kerül. Drive mappa: VibeBackup (<code><?= h(alatinfo_backup_drive_default_folder_id()) ?></code>).</p>
	</form>

	<?php if ($oauthResult !== null) { ?>
	<div style="margin-top:1rem;">
		<?php if ($oauthResult['ok']) { ?>
		<p class="msg msg-success"><strong><?= h((string) $oauthResult['message']) ?></strong></p>
		<p><a href="<?= h($urlBack) ?>" class="btn btn-primary">Mentés Google Drive-ra →</a></p>
		<?php } else { ?>
		<p class="msg msg-error"><strong><?= h((string) $oauthResult['message']) ?></strong></p>
		<?php } ?>
	</div>
	<?php } ?>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
