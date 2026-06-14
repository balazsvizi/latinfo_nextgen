<?php
declare(strict_types=1);

/**
 * Google Drive fiók mentése admin felhasználónként (titkosított refresh token).
 */

if (!function_exists('alatinfo_gdrive_current_admin_id')) {
	function alatinfo_gdrive_current_admin_id(): int
	{
		return (int) ($_SESSION['admin_id'] ?? 0);
	}
}

if (!function_exists('alatinfo_gdrive_account_table_exists')) {
	function alatinfo_gdrive_account_table_exists(): bool
	{
		static $exists = null;
		if ($exists !== null) {
			return $exists;
		}
		try {
			$db = getDb();
			$db->query('SELECT 1 FROM nextgen_admin_gdrive LIMIT 1');
			$exists = true;
		} catch (Throwable $e) {
			$exists = false;
		}
		return $exists;
	}
}

if (!function_exists('alatinfo_gdrive_account_load')) {
	/**
	 * @return array{email:string,refresh_token:string,updated_at:string}|null
	 */
	function alatinfo_gdrive_account_load(int $adminId): ?array
	{
		if ($adminId <= 0 || !alatinfo_gdrive_account_table_exists()) {
			return null;
		}
		require_once __DIR__ . '/email.php';
		try {
			$db = getDb();
			$stmt = $db->prepare(
				'SELECT google_email, refresh_token_encrypted, updated_at FROM nextgen_admin_gdrive WHERE admin_id = ? LIMIT 1'
			);
			$stmt->execute([$adminId]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
		} catch (Throwable $e) {
			return null;
		}
		if (!$row) {
			return null;
		}
		$refresh = email_jelszo_visszafejt((string) ($row['refresh_token_encrypted'] ?? ''));
		if ($refresh === '') {
			return null;
		}
		return array(
			'email' => trim((string) ($row['google_email'] ?? '')),
			'refresh_token' => $refresh,
			'updated_at' => (string) ($row['updated_at'] ?? ''),
		);
	}
}

if (!function_exists('alatinfo_gdrive_account_save')) {
	function alatinfo_gdrive_account_save(int $adminId, string $googleEmail, string $refreshToken): bool
	{
		if ($adminId <= 0 || !alatinfo_gdrive_account_table_exists()) {
			return false;
		}
		$googleEmail = trim($googleEmail);
		$refreshToken = trim($refreshToken);
		if ($googleEmail === '' || $refreshToken === '') {
			return false;
		}
		require_once __DIR__ . '/email.php';
		$encrypted = email_jelszo_titkosit($refreshToken);
		try {
			$db = getDb();
			$stmt = $db->prepare(
				'INSERT INTO nextgen_admin_gdrive (admin_id, google_email, refresh_token_encrypted)
				 VALUES (?, ?, ?)
				 ON DUPLICATE KEY UPDATE google_email = VALUES(google_email),
				 refresh_token_encrypted = VALUES(refresh_token_encrypted),
				 updated_at = CURRENT_TIMESTAMP'
			);
			$stmt->execute([$adminId, $googleEmail, $encrypted]);
			return true;
		} catch (Throwable $e) {
			return false;
		}
	}
}

if (!function_exists('alatinfo_gdrive_account_clear')) {
	function alatinfo_gdrive_account_clear(int $adminId): bool
	{
		if ($adminId <= 0 || !alatinfo_gdrive_account_table_exists()) {
			return false;
		}
		try {
			$db = getDb();
			$stmt = $db->prepare('DELETE FROM nextgen_admin_gdrive WHERE admin_id = ?');
			$stmt->execute([$adminId]);
			return true;
		} catch (Throwable $e) {
			return false;
		}
	}
}

if (!function_exists('alatinfo_gdrive_account_has')) {
	function alatinfo_gdrive_account_has(int $adminId): bool
	{
		return alatinfo_gdrive_account_load($adminId) !== null;
	}
}

if (!function_exists('alatinfo_gdrive_oauth_return_key')) {
	function alatinfo_gdrive_oauth_return_key(): string
	{
		return 'alatinfo_gdrive_oauth_return';
	}
}

if (!function_exists('alatinfo_gdrive_oauth_set_return')) {
	function alatinfo_gdrive_oauth_set_return(string $target, bool $remember = true): void
	{
		alatinfo_backup_drive_session_ensure();
		$_SESSION[alatinfo_gdrive_oauth_return_key()] = $target;
		$_SESSION['alatinfo_gdrive_oauth_remember'] = $remember;
	}
}

if (!function_exists('alatinfo_gdrive_oauth_consume_return')) {
	/** @return array{target:string,remember:bool} */
	function alatinfo_gdrive_oauth_consume_return(): array
	{
		$target = trim((string) ($_SESSION[alatinfo_gdrive_oauth_return_key()] ?? 'backup'));
		$remember = !empty($_SESSION['alatinfo_gdrive_oauth_remember']);
		unset($_SESSION[alatinfo_gdrive_oauth_return_key()], $_SESSION['alatinfo_gdrive_oauth_remember']);
		if ($target !== 'settings' && $target !== 'backup') {
			$target = 'backup';
		}
		return array('target' => $target, 'remember' => $remember);
	}
}

if (!function_exists('alatinfo_gdrive_oauth_return_url')) {
	function alatinfo_gdrive_oauth_return_url(string $target): string
	{
		if ($target === 'settings') {
			return nextgen_url('google_drive_beallitas.php');
		}
		return nextgen_url('admin/backup/');
	}
}
