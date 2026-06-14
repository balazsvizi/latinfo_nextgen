<?php
declare(strict_types=1);

/**
 * Google OAuth visszahívás – token mentése munkamenetbe, opcionálisan a felhasználóhoz (DB).
 */
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/google_drive_backup.php';
require_once __DIR__ . '/../../includes/google_drive_account.php';

requireSuperadmin();

$returnInfo = alatinfo_gdrive_oauth_consume_return();
$returnUrl = alatinfo_gdrive_oauth_return_url($returnInfo['target']);

$oauthError = trim((string)($_GET['error'] ?? ''));
if ($oauthError !== '') {
	if ($oauthError === 'access_denied') {
		flash('error', 'Google hozzáférés megtagadva. A bejelentkező Gmail cím legyen tesztfelhasználó a Cloud Console-ban.');
	} else {
		$desc = trim((string)($_GET['error_description'] ?? ''));
		flash('error', 'Google OAuth hiba: ' . $oauthError . ($desc !== '' ? ' – ' . $desc : ''));
	}
	redirect($returnUrl);
}

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
	redirect($returnUrl);
}

$cfg = alatinfo_backup_drive_load_config();
if ($cfg === null || ($cfg['auth'] ?? '') !== 'oauth_session') {
	flash('error', 'Hiányzik az OAuth alkalmazás konfiguráció (config.local.php).');
	redirect($returnUrl);
}

$clientId = trim((string) ($cfg['oauth_client_id'] ?? ''));
$clientSecret = trim((string) ($cfg['oauth_client_secret'] ?? ''));
$redirectUri = alatinfo_gdrive_oauth_redirect_uri();

$exchange = alatinfo_gdrive_oauth_exchange_code($clientId, $clientSecret, $redirectUri, $code);
if (!$exchange['ok']) {
	flash('error', 'Token csere sikertelen: ' . $exchange['error']);
	redirect($returnUrl);
}

if ($exchange['access_token'] === '') {
	flash('error', 'Nem érkezett access token a Google-tól.');
	redirect($returnUrl);
}

alatinfo_gdrive_user_session_store_tokens($exchange, $clientId, $clientSecret);
$email = alatinfo_gdrive_user_session_email();
$refreshToken = trim((string) ($exchange['refresh_token'] ?? ''));

if ($returnInfo['remember']) {
	$adminId = alatinfo_gdrive_current_admin_id();
	if ($adminId > 0) {
		if ($refreshToken === '') {
			$existing = alatinfo_gdrive_account_load($adminId);
			if ($existing !== null) {
				$refreshToken = (string) $existing['refresh_token'];
			}
		}
		if ($refreshToken !== '' && $email !== '') {
			if (alatinfo_gdrive_account_save($adminId, $email, $refreshToken)) {
				flash('success', 'Google fiók mentve: ' . $email);
				redirect($returnUrl);
			}
			flash('error', 'A Google fiók mentése sikertelen. Futtasd le a migration_admin_gdrive.sql fájlt.');
			redirect($returnUrl);
		}
	}
}

$msg = $email !== ''
	? 'Google bejelentkezés sikeres: ' . $email
	: 'Google bejelentkezés sikeres.';
flash('success', $msg);
redirect($returnUrl);
