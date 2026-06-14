<?php
declare(strict_types=1);

/**
 * Google OAuth visszahívás – a token csak a PHP munkamenetben marad, fájlba nem kerül.
 */
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/google_drive_backup.php';

requireSuperadmin();

$urlIndex = nextgen_url('admin/backup/');

$oauthError = trim((string)($_GET['error'] ?? ''));
if ($oauthError !== '') {
	if ($oauthError === 'access_denied') {
		flash('error', 'Google hozzáférés megtagadva. A bejelentkező Gmail cím legyen tesztfelhasználó a Cloud Console-ban.');
	} else {
		$desc = trim((string)($_GET['error_description'] ?? ''));
		flash('error', 'Google OAuth hiba: ' . $oauthError . ($desc !== '' ? ' – ' . $desc : ''));
	}
	redirect($urlIndex);
}

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
	redirect($urlIndex);
}

$cfg = alatinfo_backup_drive_load_config();
if ($cfg === null || ($cfg['auth'] ?? '') !== 'oauth_session') {
	flash('error', 'Hiányzik az OAuth alkalmazás konfiguráció (config.local.php).');
	redirect($urlIndex);
}

$clientId = trim((string) ($cfg['oauth_client_id'] ?? ''));
$clientSecret = trim((string) ($cfg['oauth_client_secret'] ?? ''));
$redirectUri = alatinfo_gdrive_oauth_redirect_uri();

$exchange = alatinfo_gdrive_oauth_exchange_code($clientId, $clientSecret, $redirectUri, $code);
if (!$exchange['ok']) {
	flash('error', 'Token csere sikertelen: ' . $exchange['error']);
	redirect($urlIndex);
}

if ($exchange['access_token'] === '') {
	flash('error', 'Nem érkezett access token a Google-tól.');
	redirect($urlIndex);
}

alatinfo_gdrive_user_session_store_tokens($exchange, $clientId, $clientSecret);
$email = alatinfo_gdrive_user_session_email();
$msg = $email !== ''
	? 'Google bejelentkezés sikeres: ' . $email
	: 'Google bejelentkezés sikeres.';
flash('success', $msg);
redirect($urlIndex);
