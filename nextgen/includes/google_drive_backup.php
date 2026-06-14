<?php
declare(strict_types=1);

/**
 * Google Drive mentés: service account JWT vagy OAuth refresh token + resumable upload.
 * Személyes Gmail Saját meghajtó: OAuth refresh (a mappa tulajdonosa). Csapatmeghajtó: service account.
 */
if (!function_exists('alatinfo_backup_drive_default_folder_id')) {
	function alatinfo_backup_drive_default_folder_id(): string
	{
		return '1BOBSMtZDB10LWKNcJxDWtq6AnFx4W9mJ';
	}
}

if (!function_exists('alatinfo_backup_drive_secret_folder')) {
	function alatinfo_backup_drive_secret_folder(): string
	{
		if (defined('BASE_PATH')) {
			return rtrim((string) BASE_PATH, '/\\') . DIRECTORY_SEPARATOR . 'secret_folder';
		}
		return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'secret_folder';
	}
}

if (!function_exists('alatinfo_backup_drive_config_path')) {
	function alatinfo_backup_drive_config_path(): string
	{
		return alatinfo_backup_drive_secret_folder() . DIRECTORY_SEPARATOR . 'google_drive_backup_config.php';
	}
}

if (!function_exists('alatinfo_backup_project_root')) {
	function alatinfo_backup_project_root(): string
	{
		if (defined('BASE_PATH')) {
			$root = realpath((string) BASE_PATH);
			if ($root !== false) {
				return $root;
			}
		}
		$fallback = realpath(dirname(__DIR__, 2));
		return $fallback !== false ? $fallback : dirname(__DIR__, 2);
	}
}

if (!function_exists('alatinfo_backup_drive_cfg')) {
	function alatinfo_backup_drive_cfg(string $key, string $default = ''): string
	{
		$env = getenv($key);
		if ($env !== false && $env !== '') {
			return (string) $env;
		}
		static $local = null;
		if ($local === null) {
			$local = array();
			$path = dirname(__DIR__) . '/core/config.local.php';
			if (is_file($path)) {
				$tmp = require $path;
				if (is_array($tmp)) {
					$local = $tmp;
				}
			}
		}
		$val = $local[$key] ?? $default;
		return is_string($val) ? $val : (string) $val;
	}
}

if (!function_exists('alatinfo_gdrive_user_session_key')) {
	function alatinfo_gdrive_user_session_key(): string
	{
		return 'alatinfo_gdrive_user_auth';
	}
}

if (!function_exists('alatinfo_gdrive_user_session_get')) {
	/** @return array<string,mixed>|null */
	function alatinfo_gdrive_user_session_get(): ?array
	{
		if (session_status() !== PHP_SESSION_ACTIVE) {
			return null;
		}
		$data = $_SESSION[alatinfo_gdrive_user_session_key()] ?? null;
		return is_array($data) ? $data : null;
	}
}

if (!function_exists('alatinfo_gdrive_user_session_set')) {
	/** @param array<string,mixed> $auth */
	function alatinfo_gdrive_user_session_set(array $auth): void
	{
		alatinfo_backup_drive_session_ensure();
		$_SESSION[alatinfo_gdrive_user_session_key()] = $auth;
	}
}

if (!function_exists('alatinfo_gdrive_user_session_clear')) {
	function alatinfo_gdrive_user_session_clear(): void
	{
		alatinfo_backup_drive_session_ensure();
		unset($_SESSION[alatinfo_gdrive_user_session_key()]);
	}
}

if (!function_exists('alatinfo_gdrive_user_is_logged_in')) {
	function alatinfo_gdrive_user_is_logged_in(): bool
	{
		return alatinfo_gdrive_user_get_access_token() !== null;
	}
}

if (!function_exists('alatinfo_gdrive_user_has_saved_account')) {
	function alatinfo_gdrive_user_has_saved_account(): bool
	{
		if (alatinfo_gdrive_user_session_get() !== null) {
			return true;
		}
		require_once __DIR__ . '/google_drive_account.php';
		return alatinfo_gdrive_account_has(alatinfo_gdrive_current_admin_id());
	}
}

if (!function_exists('alatinfo_gdrive_user_display_email')) {
	function alatinfo_gdrive_user_display_email(): string
	{
		$email = alatinfo_gdrive_user_session_email();
		if ($email !== '') {
			return $email;
		}
		require_once __DIR__ . '/google_drive_account.php';
		$account = alatinfo_gdrive_account_load(alatinfo_gdrive_current_admin_id());
		return $account !== null ? trim((string) ($account['email'] ?? '')) : '';
	}
}

if (!function_exists('alatinfo_gdrive_user_get_access_token_from_db')) {
	function alatinfo_gdrive_user_get_access_token_from_db(): ?string
	{
		require_once __DIR__ . '/google_drive_account.php';
		$adminId = alatinfo_gdrive_current_admin_id();
		if ($adminId <= 0) {
			return null;
		}
		$account = alatinfo_gdrive_account_load($adminId);
		if ($account === null) {
			return null;
		}
		$cfg = alatinfo_backup_drive_load_config();
		if ($cfg === null) {
			return null;
		}
		$res = alatinfo_gdrive_access_token_oauth_refresh_with_detail(array(
			'oauth_client_id' => (string) $cfg['oauth_client_id'],
			'oauth_client_secret' => (string) $cfg['oauth_client_secret'],
			'oauth_refresh_token' => (string) $account['refresh_token'],
		));
		if (!$res['ok'] || $res['token'] === null) {
			return null;
		}
		alatinfo_gdrive_user_session_set(array(
			'access_token' => $res['token'],
			'refresh_token' => (string) $account['refresh_token'],
			'expires_at' => time() + 3600,
			'client_id' => (string) $cfg['oauth_client_id'],
			'client_secret' => (string) $cfg['oauth_client_secret'],
			'email' => (string) $account['email'],
		));
		return $res['token'];
	}
}

if (!function_exists('alatinfo_gdrive_user_get_access_token')) {
	function alatinfo_gdrive_user_get_access_token(): ?string
	{
		$sessionToken = alatinfo_gdrive_user_session_get_access_token();
		if ($sessionToken !== null) {
			return $sessionToken;
		}
		return alatinfo_gdrive_user_get_access_token_from_db();
	}
}

if (!function_exists('alatinfo_gdrive_user_session_email')) {
	function alatinfo_gdrive_user_session_email(): string
	{
		$auth = alatinfo_gdrive_user_session_get();
		return trim((string) ($auth['email'] ?? ''));
	}
}

if (!function_exists('alatinfo_gdrive_fetch_user_email')) {
	function alatinfo_gdrive_fetch_user_email(string $accessToken): string
	{
		if (!function_exists('curl_init')) {
			return '';
		}
		$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $accessToken),
			CURLOPT_TIMEOUT => 15,
		));
		$body = curl_exec($ch);
		curl_close($ch);
		if (!is_string($body)) {
			return '';
		}
		$j = json_decode($body, true);
		if (!is_array($j)) {
			return '';
		}
		return trim((string) ($j['email'] ?? ''));
	}
}

if (!function_exists('alatinfo_gdrive_user_session_store_tokens')) {
	/**
	 * @param array{ok:bool,refresh_token:string,access_token:string,expires_in:int,error:string} $exchange
	 */
	function alatinfo_gdrive_user_session_store_tokens(array $exchange, string $clientId, string $clientSecret): void
	{
		$expiresIn = max(60, (int) ($exchange['expires_in'] ?? 3600));
		$accessToken = trim((string) ($exchange['access_token'] ?? ''));
		$email = $accessToken !== '' ? alatinfo_gdrive_fetch_user_email($accessToken) : '';
		$existing = alatinfo_gdrive_user_session_get();
		$refreshToken = trim((string) ($exchange['refresh_token'] ?? ''));
		if ($refreshToken === '' && is_array($existing)) {
			$refreshToken = trim((string) ($existing['refresh_token'] ?? ''));
		}
		alatinfo_gdrive_user_session_set(array(
			'access_token' => $accessToken,
			'refresh_token' => $refreshToken,
			'expires_at' => time() + $expiresIn,
			'client_id' => $clientId,
			'client_secret' => $clientSecret,
			'email' => $email,
		));
	}
}

if (!function_exists('alatinfo_gdrive_user_session_refresh_access_token')) {
	function alatinfo_gdrive_user_session_refresh_access_token(): ?string
	{
		$auth = alatinfo_gdrive_user_session_get();
		if ($auth === null) {
			return null;
		}
		$refreshToken = trim((string) ($auth['refresh_token'] ?? ''));
		$clientId = trim((string) ($auth['client_id'] ?? ''));
		$clientSecret = trim((string) ($auth['client_secret'] ?? ''));
		if ($refreshToken === '' || $clientId === '' || $clientSecret === '') {
			return null;
		}
		$cfg = array(
			'oauth_client_id' => $clientId,
			'oauth_client_secret' => $clientSecret,
			'oauth_refresh_token' => $refreshToken,
		);
		$res = alatinfo_gdrive_access_token_oauth_refresh_with_detail($cfg);
		if (!$res['ok'] || $res['token'] === null) {
			return null;
		}
		$expiresIn = 3600;
		$auth['access_token'] = $res['token'];
		$auth['expires_at'] = time() + $expiresIn;
		if ($auth['email'] === '') {
			$auth['email'] = alatinfo_gdrive_fetch_user_email($res['token']);
		}
		alatinfo_gdrive_user_session_set($auth);
		return $res['token'];
	}
}

if (!function_exists('alatinfo_gdrive_user_session_get_access_token')) {
	function alatinfo_gdrive_user_session_get_access_token(): ?string
	{
		$auth = alatinfo_gdrive_user_session_get();
		if ($auth === null) {
			return null;
		}
		$token = trim((string) ($auth['access_token'] ?? ''));
		$expiresAt = (int) ($auth['expires_at'] ?? 0);
		if ($token !== '' && ($expiresAt === 0 || time() < $expiresAt - 60)) {
			return $token;
		}
		return alatinfo_gdrive_user_session_refresh_access_token();
	}
}

if (!function_exists('alatinfo_gdrive_base64url_encode')) {
	function alatinfo_gdrive_base64url_encode(string $bin): string {
		return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
	}
}

if (!function_exists('alatinfo_gdrive_load_service_account')) {
	/**
	 * @return array{client_email:string,private_key:string}|null
	 */
	function alatinfo_gdrive_load_service_account(string $jsonPath): ?array {
		if ($jsonPath === '' || !is_readable($jsonPath)) {
			return null;
		}
		$raw = file_get_contents($jsonPath);
		if ($raw === false) {
			return null;
		}
		$j = json_decode($raw, true);
		if (!is_array($j)) {
			return null;
		}
		$email = trim((string)($j['client_email'] ?? ''));
		$key = (string)($j['private_key'] ?? '');
		if ($email === '' || $key === '') {
			return null;
		}
		return array('client_email' => $email, 'private_key' => $key);
	}
}

if (!function_exists('alatinfo_gdrive_oauth_scopes')) {
	/**
	 * Megosztott mappa eléréséhez (service account) a drive.file scope nem elég.
	 */
	function alatinfo_gdrive_oauth_scopes(): string
	{
		return 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/userinfo.email';
	}
}

if (!function_exists('alatinfo_gdrive_jwt_assertion')) {
	function alatinfo_gdrive_jwt_assertion(array $sa, ?string $impersonateUser = null): ?string {
		$now = time();
		$hdr = alatinfo_gdrive_base64url_encode(json_encode(array('typ' => 'JWT', 'alg' => 'RS256')));
		$claims = array(
			'iss' => $sa['client_email'],
			'scope' => alatinfo_gdrive_oauth_scopes(),
			'aud' => 'https://oauth2.googleapis.com/token',
			'iat' => $now,
			'exp' => $now + 3600,
		);
		$impersonateUser = $impersonateUser !== null ? trim($impersonateUser) : '';
		if ($impersonateUser !== '') {
			$claims['sub'] = $impersonateUser;
		}
		$clm = alatinfo_gdrive_base64url_encode(json_encode($claims));
		$unsigned = $hdr . '.' . $clm;
		$pkey = openssl_pkey_get_private($sa['private_key']);
		if ($pkey === false) {
			return null;
		}
		$sig = '';
		if (!openssl_sign($unsigned, $sig, $pkey, OPENSSL_ALGO_SHA256)) {
			return null;
		}
		return $unsigned . '.' . alatinfo_gdrive_base64url_encode($sig);
	}
}

if (!function_exists('alatinfo_gdrive_access_token')) {
	function alatinfo_gdrive_access_token(array $sa, ?string $impersonateUser = null): ?string {
		$jwt = alatinfo_gdrive_jwt_assertion($sa, $impersonateUser);
		if ($jwt === null) {
			return null;
		}
		if (!function_exists('curl_init')) {
			return null;
		}
		$ch = curl_init('https://oauth2.googleapis.com/token');
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
			CURLOPT_POSTFIELDS => http_build_query(array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion' => $jwt,
			)),
			CURLOPT_TIMEOUT => 30,
		));
		$body = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($code !== 200 || !is_string($body)) {
			return null;
		}
		$j = json_decode($body, true);
		if (!is_array($j) || empty($j['access_token'])) {
			return null;
		}
		return (string)$j['access_token'];
	}
}

if (!function_exists('alatinfo_gdrive_upload_resumable')) {
	/**
	 * @param callable(int $uploadedBytes, int $totalBytes): void|null $onUploadProgress
	 * @return array{ok:bool,message:string,storage_quota:bool}
	 */
	function alatinfo_gdrive_upload_resumable(
		string $accessToken,
		string $folderId,
		string $localPath,
		string $driveName,
		string $mime,
		?callable $onUploadProgress = null
	): array {
		if (!is_readable($localPath) || !function_exists('curl_init')) {
			return array('ok' => false, 'message' => 'A fájl nem olvasható, vagy nincs cURL.', 'storage_quota' => false);
		}
		$size = filesize($localPath);
		if ($size === false) {
			return array('ok' => false, 'message' => 'Fájlméret olvasása sikertelen.', 'storage_quota' => false);
		}
		$meta = json_encode(array(
			'name' => $driveName,
			'parents' => array($folderId),
		));
		$url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true';
		$session = '';
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . $accessToken,
				'Content-Type: application/json; charset=UTF-8',
				'X-Upload-Content-Type: ' . $mime,
				'X-Upload-Content-Length: ' . (string)$size,
			),
			CURLOPT_POSTFIELDS => $meta,
			CURLOPT_TIMEOUT => 60,
		));
		$resp = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if (!is_string($resp) || ($code !== 200 && $code !== 201)) {
			$errBody = is_string($resp) ? $resp : '';
			if (preg_match('/\{[\s\S]*\}/', $errBody, $m)) {
				$errBody = $m[0];
			}
			$errMsg = alatinfo_gdrive_google_error_message($errBody);
			return array(
				'ok' => false,
				'message' => 'Drive feltöltés indítása sikertelen (HTTP ' . $code . '): ' . $errMsg,
				'storage_quota' => alatinfo_gdrive_is_storage_quota_error($errMsg),
			);
		}
		if (preg_match('/^Location:\s*(.+)$/mi', $resp, $m)) {
			$session = trim($m[1]);
		}
		if ($session === '') {
			return array('ok' => false, 'message' => 'Nincs Location fejléc a resumable válaszban.', 'storage_quota' => false);
		}
		$fp = fopen($localPath, 'rb');
		if ($fp === false) {
			return array('ok' => false, 'message' => 'Fájl megnyitása sikertelen.', 'storage_quota' => false);
		}
		$ch2 = curl_init($session);
		$curlOpts = array(
			CURLOPT_PUT => true,
			CURLOPT_INFILE => $fp,
			CURLOPT_INFILESIZE => $size,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . $accessToken,
				'Content-Type: ' . $mime,
				'Content-Length: ' . (string)$size,
			),
			CURLOPT_TIMEOUT => 0,
		);
		if ($onUploadProgress !== null) {
			$curlOpts[CURLOPT_NOPROGRESS] = false;
			if (defined('CURLOPT_XFERINFOFUNCTION')) {
				$curlOpts[CURLOPT_XFERINFOFUNCTION] = static function (
					$resource,
					$downloadTotal,
					$downloaded,
					$uploadTotal,
					$uploaded
				) use ($onUploadProgress) {
					unset($resource, $downloadTotal, $downloaded);
					if ((int)$uploadTotal > 0) {
						$onUploadProgress((int)$uploaded, (int)$uploadTotal);
					}
					return 0;
				};
			} else {
				$curlOpts[CURLOPT_PROGRESSFUNCTION] = static function (
					$resource,
					$downloadTotal,
					$downloaded,
					$uploadTotal,
					$uploaded
				) use ($onUploadProgress) {
					unset($resource, $downloadTotal, $downloaded);
					if ((int)$uploadTotal > 0) {
						$onUploadProgress((int)$uploaded, (int)$uploadTotal);
					}
					return 0;
				};
			}
		}
		curl_setopt_array($ch2, $curlOpts);
		$body2 = curl_exec($ch2);
		$code2 = (int)curl_getinfo($ch2, CURLINFO_HTTP_CODE);
		curl_close($ch2);
		fclose($fp);
		if ($code2 !== 200 && $code2 !== 201) {
			$errMsg = alatinfo_gdrive_google_error_message(is_string($body2) ? $body2 : '');
			return array(
				'ok' => false,
				'message' => 'Fájl feltöltése sikertelen (HTTP ' . $code2 . '): ' . $errMsg,
				'storage_quota' => alatinfo_gdrive_is_storage_quota_error($errMsg),
			);
		}
		return array('ok' => true, 'message' => 'Feltöltve: ' . $driveName, 'storage_quota' => false);
	}
}

if (!function_exists('alatinfo_gdrive_oauth_sanitize_credential')) {
	function alatinfo_gdrive_oauth_sanitize_credential(string $value): string
	{
		$value = trim($value);
		if ($value === '') {
			return '';
		}
		if (
			(strlen($value) >= 2)
			&& (($value[0] === "'" && substr($value, -1) === "'") || ($value[0] === '"' && substr($value, -1) === '"'))
		) {
			$value = trim(substr($value, 1, -1));
		}
		return trim($value, " \t\n\r\0\x0B,;");
	}
}

if (!function_exists('alatinfo_backup_drive_normalize_config')) {
	/**
	 * @param array<string,mixed> $raw
	 * @return array{
	 *   auth:string,
	 *   folder_id:string,
	 *   json_path:string,
	 *   impersonate_user:string,
	 *   oauth_client_id:string,
	 *   oauth_client_secret:string,
	 *   oauth_refresh_token:string
	 * }|null
	 */
	function alatinfo_backup_drive_normalize_config(array $raw): ?array
	{
		$folderId = trim((string)($raw['folder_id'] ?? ''));
		if ($folderId === '') {
			return null;
		}
		$cfg = array(
			'auth' => trim((string)($raw['auth'] ?? '')),
			'folder_id' => $folderId,
			'json_path' => trim((string)($raw['json_path'] ?? '')),
			'impersonate_user' => trim((string)($raw['impersonate_user'] ?? '')),
			'oauth_client_id' => alatinfo_gdrive_oauth_sanitize_credential((string)($raw['oauth_client_id'] ?? '')),
			'oauth_client_secret' => alatinfo_gdrive_oauth_sanitize_credential((string)($raw['oauth_client_secret'] ?? '')),
			'oauth_refresh_token' => alatinfo_gdrive_oauth_sanitize_credential((string)($raw['oauth_refresh_token'] ?? '')),
		);
		if ($cfg['auth'] === '') {
			if ($cfg['oauth_client_id'] !== '' && $cfg['oauth_client_secret'] !== '') {
				$cfg['auth'] = 'oauth_session';
			} elseif ($cfg['oauth_refresh_token'] !== '' && $cfg['oauth_client_id'] !== '' && $cfg['oauth_client_secret'] !== '') {
				$cfg['auth'] = 'oauth_refresh';
			} else {
				$cfg['auth'] = 'service_account';
			}
		}
		if ($cfg['auth'] === 'oauth_session') {
			if ($cfg['oauth_client_id'] === '' || $cfg['oauth_client_secret'] === '') {
				return null;
			}
			return $cfg;
		}
		if ($cfg['auth'] === 'oauth_refresh') {
			if ($cfg['oauth_client_id'] === '' || $cfg['oauth_client_secret'] === '' || $cfg['oauth_refresh_token'] === '') {
				return null;
			}
			return $cfg;
		}
		if ($cfg['json_path'] === '') {
			return null;
		}
		$cfg['auth'] = 'service_account';
		return $cfg;
	}
}

if (!function_exists('alatinfo_backup_drive_load_config')) {
	/**
	 * @return array{
	 *   auth:string,
	 *   folder_id:string,
	 *   json_path:string,
	 *   impersonate_user:string,
	 *   oauth_client_id:string,
	 *   oauth_client_secret:string,
	 *   oauth_refresh_token:string
	 * }|null
	 */
	function alatinfo_backup_drive_load_config(): ?array {
		$raw = array(
			'folder_id' => alatinfo_backup_drive_cfg('GOOGLE_DRIVE_BACKUP_FOLDER_ID', alatinfo_backup_drive_default_folder_id()),
			'oauth_client_id' => alatinfo_backup_drive_cfg('GOOGLE_DRIVE_OAUTH_CLIENT_ID'),
			'oauth_client_secret' => alatinfo_backup_drive_cfg('GOOGLE_DRIVE_OAUTH_CLIENT_SECRET'),
		);
		$j = getenv('GOOGLE_DRIVE_BACKUP_SERVICE_ACCOUNT_JSON');
		$f = getenv('GOOGLE_DRIVE_BACKUP_FOLDER_ID');
		if (is_string($f) && trim($f) !== '') {
			$raw['folder_id'] = trim($f);
			if (is_string($j) && trim($j) !== '') {
				$raw['json_path'] = trim($j);
			}
			$auth = getenv('GOOGLE_DRIVE_BACKUP_AUTH');
			if (is_string($auth) && trim($auth) !== '') {
				$raw['auth'] = trim($auth);
			}
			foreach (array(
				'GOOGLE_DRIVE_BACKUP_IMPERSONATE_USER' => 'impersonate_user',
				'GOOGLE_DRIVE_OAUTH_CLIENT_ID' => 'oauth_client_id',
				'GOOGLE_DRIVE_OAUTH_CLIENT_SECRET' => 'oauth_client_secret',
				'GOOGLE_DRIVE_OAUTH_REFRESH_TOKEN' => 'oauth_refresh_token',
			) as $envKey => $cfgKey) {
				$v = getenv($envKey);
				if (is_string($v) && trim($v) !== '') {
					$raw[$cfgKey] = alatinfo_gdrive_oauth_sanitize_credential($v);
				}
			}
		}
		$cfgFile = alatinfo_backup_drive_config_path();
		if (is_readable($cfgFile)) {
			/** @noinspection PhpIncludeInspection */
			$a = include $cfgFile;
			if (is_array($a)) {
				foreach ($a as $k => $v) {
					if (!array_key_exists($k, $raw) || $raw[$k] === '' || $raw[$k] === alatinfo_backup_drive_default_folder_id()) {
						$raw[$k] = $v;
					}
				}
			}
		}
		return alatinfo_backup_drive_normalize_config($raw);
	}
}

if (!function_exists('alatinfo_gdrive_google_error_message')) {
	function alatinfo_gdrive_google_error_message(string $body): string
	{
		$j = json_decode($body, true);
		if (!is_array($j)) {
			$trim = trim($body);
			return $trim !== '' ? substr($trim, 0, 400) : 'Ismeretlen hiba.';
		}
		$msg = trim((string)($j['error']['message'] ?? $j['error_description'] ?? ''));
		if ($msg === '') {
			$msg = trim((string)($j['error'] ?? ''));
		}
		return $msg !== '' ? $msg : 'Ismeretlen Google API hiba.';
	}
}

if (!function_exists('alatinfo_gdrive_access_token_with_detail')) {
	/**
	 * @return array{ok:bool,token:?string,http_code:int,error:string}
	 */
	function alatinfo_gdrive_access_token_with_detail(array $sa, ?string $impersonateUser = null): array
	{
		if (!function_exists('curl_init')) {
			return array('ok' => false, 'token' => null, 'http_code' => 0, 'error' => 'A PHP cURL kiterjesztés nincs engedélyezve.');
		}
		$jwt = alatinfo_gdrive_jwt_assertion($sa, $impersonateUser);
		if ($jwt === null) {
			return array('ok' => false, 'token' => null, 'http_code' => 0, 'error' => 'JWT aláírás sikertelen (ellenőrizd a JSON kulcsot és az OpenSSL-t).');
		}
		$ch = curl_init('https://oauth2.googleapis.com/token');
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
			CURLOPT_POSTFIELDS => http_build_query(array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion' => $jwt,
			)),
			CURLOPT_TIMEOUT => 30,
		));
		$body = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($code !== 200 || !is_string($body)) {
			return array(
				'ok' => false,
				'token' => null,
				'http_code' => $code,
				'error' => alatinfo_gdrive_google_error_message(is_string($body) ? $body : ''),
			);
		}
		$j = json_decode($body, true);
		if (!is_array($j) || empty($j['access_token'])) {
			return array('ok' => false, 'token' => null, 'http_code' => $code, 'error' => 'A válaszban nincs access_token.');
		}
		return array('ok' => true, 'token' => (string)$j['access_token'], 'http_code' => $code, 'error' => '');
	}
}

if (!function_exists('alatinfo_gdrive_access_token_oauth_refresh_with_detail')) {
	/**
	 * @return array{ok:bool,token:?string,http_code:int,error:string}
	 */
	function alatinfo_gdrive_access_token_oauth_refresh_with_detail(array $cfg): array
	{
		if (!function_exists('curl_init')) {
			return array('ok' => false, 'token' => null, 'http_code' => 0, 'error' => 'A PHP cURL kiterjesztés nincs engedélyezve.');
		}
		$ch = curl_init('https://oauth2.googleapis.com/token');
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
			CURLOPT_POSTFIELDS => http_build_query(array(
				'grant_type' => 'refresh_token',
				'client_id' => $cfg['oauth_client_id'],
				'client_secret' => $cfg['oauth_client_secret'],
				'refresh_token' => $cfg['oauth_refresh_token'],
			)),
			CURLOPT_TIMEOUT => 30,
		));
		$body = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($code !== 200 || !is_string($body)) {
			return array(
				'ok' => false,
				'token' => null,
				'http_code' => $code,
				'error' => alatinfo_gdrive_google_error_message(is_string($body) ? $body : ''),
			);
		}
		$j = json_decode($body, true);
		if (!is_array($j) || empty($j['access_token'])) {
			return array('ok' => false, 'token' => null, 'http_code' => $code, 'error' => 'A válaszban nincs access_token.');
		}
		return array('ok' => true, 'token' => (string)$j['access_token'], 'http_code' => $code, 'error' => '');
	}
}

if (!function_exists('alatinfo_gdrive_oauth_token_error_hints')) {
	/** @return list<string> */
	function alatinfo_gdrive_oauth_token_error_hints(string $error): array
	{
		$e = strtolower($error);
		$hints = array();
		if (strpos($e, 'client secret') !== false || strpos($e, 'invalid_client') !== false) {
			$hints[] = 'A Client Secret hibás, elavult, vagy nem egyezik a Client ID-val.';
			$hints[] = 'Cloud Console → Google Auth Platform → Clients → Web kliens → Client secret (GOCSPX-…). Másold újra, ne legyen szóköz/idézőjel a configban.';
			$hints[] = 'Jelentkezz ki, majd lépj be újra Google-lel a mentés oldalon.';
		} elseif (strpos($e, 'invalid_grant') !== false || strpos($e, 'token has been expired') !== false || strpos($e, 'revoked') !== false || strpos($e, 'bad request') !== false) {
			$hints[] = 'A Google munkamenet lejárt – jelentkezz be újra a mappa tulajdonosának fiókjával.';
		}
		return $hints;
	}
}

if (!function_exists('alatinfo_gdrive_access_token_for_backup')) {
	/**
	 * @param array{
	 *   auth:string,
	 *   folder_id:string,
	 *   json_path:string,
	 *   impersonate_user:string,
	 *   oauth_client_id:string,
	 *   oauth_client_secret:string,
	 *   oauth_refresh_token:string
	 * } $cfg
	 * @return array{ok:bool,token:?string,http_code:int,error:string,auth_label:string}
	 */
	function alatinfo_gdrive_access_token_for_backup(array $cfg): array
	{
		if (($cfg['auth'] ?? '') === 'oauth_session') {
			$token = alatinfo_gdrive_user_get_access_token();
			if ($token === null) {
				return array(
					'ok' => false,
					'token' => null,
					'http_code' => 401,
					'error' => 'Nincs összekapcsolt Google fiók. Kapcsold össze a felhasználói beállításoknál, vagy jelentkezz be Google-lel.',
					'auth_label' => 'Google (nincs bejelentkezve)',
				);
			}
			$email = alatinfo_gdrive_user_display_email();
			return array(
				'ok' => true,
				'token' => $token,
				'http_code' => 200,
				'error' => '',
				'auth_label' => $email !== '' ? ('Google: ' . $email) : 'Google (bejelentkezve)',
			);
		}
		if (($cfg['auth'] ?? '') === 'oauth_refresh') {
			$res = alatinfo_gdrive_access_token_oauth_refresh_with_detail($cfg);
			$res['auth_label'] = 'OAuth (felhasználói fiók)';
			return $res;
		}
		$sa = alatinfo_gdrive_load_service_account($cfg['json_path']);
		if ($sa === null) {
			return array(
				'ok' => false,
				'token' => null,
				'http_code' => 0,
				'error' => 'Service account JSON nem olvasható: ' . $cfg['json_path'],
				'auth_label' => 'Service account',
			);
		}
		$impersonate = $cfg['impersonate_user'] !== '' ? $cfg['impersonate_user'] : null;
		$res = alatinfo_gdrive_access_token_with_detail($sa, $impersonate);
		$label = 'Service account: ' . $sa['client_email'];
		if ($impersonate !== null) {
			$label .= ' (delegálás: ' . $impersonate . ')';
		}
		$res['auth_label'] = $label;
		return $res;
	}
}

if (!function_exists('alatinfo_gdrive_storage_quota_hints')) {
	/** @return list<string> */
	function alatinfo_gdrive_storage_quota_hints(): array
	{
		return array(
			'Google korlátozás: a service account nem írhat személyes Gmail Saját meghajtó mappába (nincs tárhelye), még Szerkesztő megosztással sem.',
			'Megoldás (Gmail mappa, pl. VibeBackup): jelentkezz be Google-lel a mappa tulajdonosának fiókjával a mentés oldalon.',
			'Megoldás (Google Workspace): hozz létre Csapatmeghajtót (Shared Drive), tedd oda a mentési mappát, add hozzá a service accountot Tartalomkezelőként.',
		);
	}
}

if (!function_exists('alatinfo_gdrive_is_storage_quota_error')) {
	function alatinfo_gdrive_is_storage_quota_error(string $error): bool
	{
		$e = strtolower($error);
		return strpos($e, 'storage quota') !== false || strpos($e, 'storagequotaexceeded') !== false;
	}
}

if (!function_exists('alatinfo_gdrive_oauth_redirect_uri')) {
	function alatinfo_gdrive_oauth_redirect_uri(): string
	{
		$override = getenv('GOOGLE_DRIVE_OAUTH_REDIRECT_URI');
		if (is_string($override) && trim($override) !== '') {
			return trim($override);
		}
		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
		if (function_exists('nextgen_url')) {
			$path = nextgen_url('admin/backup/oauth.php');
			if ($path !== '') {
				if ($path[0] !== '/') {
					$path = '/' . $path;
				}
				return $scheme . '://' . $host . $path;
			}
		}
		$script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/nextgen/admin/backup/oauth.php'));
		return $scheme . '://' . $host . $script;
	}
}

if (!function_exists('alatinfo_gdrive_oauth_validate_client_id')) {
	/**
	 * @return array{ok:bool,error:string}
	 */
	function alatinfo_gdrive_oauth_validate_client_id(string $clientId): array
	{
		$clientId = trim($clientId);
		if ($clientId === '') {
			return array('ok' => false, 'error' => 'Üres Client ID.');
		}
		if (strpos($clientId, '@') !== false) {
			return array(
				'ok' => false,
				'error' => 'Ez service account e-mail címnek tűnik – OAuth Web kliens Client ID kell (….apps.googleusercontent.com).',
			);
		}
		if (preg_match('#^https?://#i', $clientId)) {
			return array('ok' => false, 'error' => 'A Client ID elejéről vedd le a http:// vagy https:// részt.');
		}
		if (!preg_match('/\.apps\.googleusercontent\.com$/i', $clientId)) {
			return array(
				'ok' => false,
				'error' => 'A Client ID formátuma hibás – a végén .apps.googleusercontent.com legyen (Cloud Console → Hitelesítő adatok → OAuth 2.0 Web kliens).',
			);
		}
		return array('ok' => true, 'error' => '');
	}
}

if (!function_exists('alatinfo_gdrive_oauth_validate_client_secret')) {
	/**
	 * @return array{ok:bool,error:string}
	 */
	function alatinfo_gdrive_oauth_validate_client_secret(string $clientSecret): array
	{
		$clientSecret = trim($clientSecret);
		if ($clientSecret === '') {
			return array('ok' => false, 'error' => 'Üres Client Secret.');
		}
		if (strlen($clientSecret) < 20) {
			return array('ok' => false, 'error' => 'A Client Secret túl rövid – másold újra a Cloud Console-ból.');
		}
		return array('ok' => true, 'error' => '');
	}
}

if (!function_exists('alatinfo_gdrive_oauth_authorize_url')) {
	function alatinfo_gdrive_oauth_authorize_url(string $clientId, string $redirectUri): string
	{
		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(array(
			'client_id' => $clientId,
			'redirect_uri' => $redirectUri,
			'response_type' => 'code',
			'scope' => alatinfo_gdrive_oauth_scopes(),
			'access_type' => 'offline',
			'prompt' => 'consent',
		));
	}
}

if (!function_exists('alatinfo_gdrive_oauth_exchange_code')) {
	/**
	 * @return array{ok:bool,refresh_token:string,access_token:string,error:string}
	 */
	function alatinfo_gdrive_oauth_exchange_code(string $clientId, string $clientSecret, string $redirectUri, string $code): array
	{
		if (!function_exists('curl_init')) {
			return array('ok' => false, 'refresh_token' => '', 'access_token' => '', 'expires_in' => 0, 'error' => 'cURL nincs engedélyezve.');
		}
		$ch = curl_init('https://oauth2.googleapis.com/token');
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
			CURLOPT_POSTFIELDS => http_build_query(array(
				'code' => $code,
				'client_id' => $clientId,
				'client_secret' => $clientSecret,
				'redirect_uri' => $redirectUri,
				'grant_type' => 'authorization_code',
			)),
			CURLOPT_TIMEOUT => 30,
		));
		$body = curl_exec($ch);
		$codeHttp = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($codeHttp !== 200 || !is_string($body)) {
			return array(
				'ok' => false,
				'refresh_token' => '',
				'access_token' => '',
				'expires_in' => 0,
				'error' => alatinfo_gdrive_google_error_message(is_string($body) ? $body : ''),
			);
		}
		$j = json_decode($body, true);
		if (!is_array($j)) {
			return array('ok' => false, 'refresh_token' => '', 'access_token' => '', 'expires_in' => 0, 'error' => 'Érvénytelen OAuth válasz.');
		}
		return array(
			'ok' => true,
			'refresh_token' => trim((string)($j['refresh_token'] ?? '')),
			'access_token' => trim((string)($j['access_token'] ?? '')),
			'expires_in' => (int) ($j['expires_in'] ?? 3600),
			'error' => '',
		);
	}
}

if (!function_exists('alatinfo_gdrive_drive_get')) {
	/**
	 * @return array{ok:bool,http_code:int,name:string,error:string}
	 */
	function alatinfo_gdrive_drive_get(string $accessToken, string $fileId): array
	{
		$url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId)
			. '?fields=id,name,mimeType,driveId&supportsAllDrives=true';
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $accessToken),
			CURLOPT_TIMEOUT => 30,
		));
		$body = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($code === 200 && is_string($body)) {
			$j = json_decode($body, true);
			if (is_array($j)) {
				return array(
					'ok' => true,
					'http_code' => $code,
					'name' => trim((string)($j['name'] ?? '')),
					'error' => '',
				);
			}
		}
		return array(
			'ok' => false,
			'http_code' => $code,
			'name' => '',
			'error' => alatinfo_gdrive_google_error_message(is_string($body) ? $body : ''),
		);
	}
}

if (!function_exists('alatinfo_gdrive_curl_request')) {
	/**
	 * @return array{ok:bool,http_code:int,body:string,error:string}
	 */
	function alatinfo_gdrive_curl_request(
		string $url,
		string $accessToken,
		string $method = 'GET',
		?string $body = null,
		array $extraHeaders = array()
	): array {
		if (!function_exists('curl_init')) {
			return array('ok' => false, 'http_code' => 0, 'body' => '', 'error' => 'cURL nincs engedélyezve.');
		}
		$headers = array_merge(array('Authorization: Bearer ' . $accessToken), $extraHeaders);
		$ch = curl_init($url);
		$opts = array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_TIMEOUT => 60,
		);
		if ($method !== 'GET') {
			$opts[CURLOPT_CUSTOMREQUEST] = $method;
		}
		if ($body !== null) {
			$opts[CURLOPT_POSTFIELDS] = $body;
		}
		curl_setopt_array($ch, $opts);
		$respBody = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($code >= 200 && $code < 300 && is_string($respBody)) {
			return array('ok' => true, 'http_code' => $code, 'body' => $respBody, 'error' => '');
		}
		return array(
			'ok' => false,
			'http_code' => $code,
			'body' => is_string($respBody) ? $respBody : '',
			'error' => alatinfo_gdrive_google_error_message(is_string($respBody) ? $respBody : ''),
		);
	}
}

if (!function_exists('alatinfo_gdrive_drive_list_files')) {
	/**
	 * @return array{ok:bool,http_code:int,files:array<int,array{id:string,name:string,mimeType:string,parents:array<int,string>}>,error:string}
	 */
	function alatinfo_gdrive_drive_list_files(string $accessToken, string $q, int $pageSize = 50): array
	{
		$url = 'https://www.googleapis.com/drive/v3/files?'
			. http_build_query(array(
				'q' => $q,
				'pageSize' => max(1, min(100, $pageSize)),
				'fields' => 'files(id,name,mimeType,parents)',
				'supportsAllDrives' => 'true',
				'includeItemsFromAllDrives' => 'true',
				'corpora' => 'allDrives',
			));
		$res = alatinfo_gdrive_curl_request($url, $accessToken);
		if (!$res['ok']) {
			return array('ok' => false, 'http_code' => $res['http_code'], 'files' => array(), 'error' => $res['error']);
		}
		$j = json_decode($res['body'], true);
		$files = array();
		if (is_array($j) && !empty($j['files']) && is_array($j['files'])) {
			foreach ($j['files'] as $f) {
				if (!is_array($f) || empty($f['id'])) {
					continue;
				}
				$parents = array();
				if (!empty($f['parents']) && is_array($f['parents'])) {
					foreach ($f['parents'] as $p) {
						$parents[] = (string)$p;
					}
				}
				$files[] = array(
					'id' => (string)$f['id'],
					'name' => trim((string)($f['name'] ?? '')),
					'mimeType' => trim((string)($f['mimeType'] ?? '')),
					'parents' => $parents,
				);
			}
		}
		return array('ok' => true, 'http_code' => $res['http_code'], 'files' => $files, 'error' => '');
	}
}

if (!function_exists('alatinfo_gdrive_drive_list_folder_children')) {
	/**
	 * @return array{ok:bool,http_code:int,files:array<int,array{id:string,name:string,mimeType:string,parents:array<int,string>}>,error:string}
	 */
	function alatinfo_gdrive_drive_list_folder_children(string $accessToken, string $folderId): array
	{
		$q = "'" . str_replace("'", "\\'", $folderId) . "' in parents and trashed=false";
		return alatinfo_gdrive_drive_list_files($accessToken, $q, 100);
	}
}

if (!function_exists('alatinfo_gdrive_drive_download_text')) {
	/**
	 * @return array{ok:bool,http_code:int,content:string,error:string}
	 */
	function alatinfo_gdrive_drive_download_text(string $accessToken, string $fileId): array
	{
		$url = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId)
			. '?alt=media&supportsAllDrives=true';
		$res = alatinfo_gdrive_curl_request($url, $accessToken);
		if (!$res['ok']) {
			return array('ok' => false, 'http_code' => $res['http_code'], 'content' => '', 'error' => $res['error']);
		}
		return array('ok' => true, 'http_code' => $res['http_code'], 'content' => $res['body'], 'error' => '');
	}
}

if (!function_exists('alatinfo_gdrive_drive_probe_read_file')) {
	/**
	 * Megosztott mappában lévő fájl keresése és tartalmának olvasása (kapcsolatteszt).
	 *
	 * @return array{ok:bool,messages:array<int,string>,file_id:string,content_preview:string}
	 */
	function alatinfo_gdrive_drive_probe_read_file(
		string $accessToken,
		string $folderId,
		string $fileName = 'teszt.txt'
	): array {
		$messages = array();
		$fileName = trim($fileName);
		if ($fileName === '') {
			return array('ok' => false, 'messages' => array('Üres fájlnév.'), 'file_id' => '', 'content_preview' => '');
		}

		$children = alatinfo_gdrive_drive_list_folder_children($accessToken, $folderId);
		if (!$children['ok']) {
			$messages[] = 'HIBA: Mappa tartalom listázása sikertelen (HTTP ' . $children['http_code'] . '): ' . $children['error'];
			return array('ok' => false, 'messages' => $messages, 'file_id' => '', 'content_preview' => '');
		}

		if ($children['files'] === array()) {
			$messages[] = 'A konfigurált mappa (ID: ' . $folderId . ') üresnek látszik a service account számára – vagy rossz a mappa ID.';
		} else {
			$names = array();
			foreach ($children['files'] as $f) {
				$names[] = $f['name'] !== '' ? $f['name'] : ('(id:' . $f['id'] . ')');
			}
			$messages[] = 'Mappa tartalma (' . count($children['files']) . ' elem): ' . implode(', ', $names);
		}

		$fileId = '';
		foreach ($children['files'] as $f) {
			if (strcasecmp($f['name'], $fileName) === 0) {
				$fileId = $f['id'];
				break;
			}
		}

		if ($fileId === '') {
			$escaped = str_replace("'", "\\'", $fileName);
			$global = alatinfo_gdrive_drive_list_files(
				$accessToken,
				"name='{$escaped}' and trashed=false",
				10
			);
			if ($global['ok'] && $global['files'] !== array()) {
				foreach ($global['files'] as $f) {
					if (strcasecmp($f['name'], $fileName) === 0) {
						$fileId = $f['id'];
						$parentHint = $f['parents'] !== array() ? implode(', ', $f['parents']) : '(nincs parent)';
						$messages[] = 'A „' . $fileName . '” megtalálható máshol (ID: ' . $fileId . ', szülő: ' . $parentHint . ') – a konfigurált mappa ID valószínűleg nem egyezik.';
						break;
					}
				}
			}
		}

		if ($fileId === '') {
			$messages[] = 'HIBA: A „' . $fileName . '” fájl nem található a service account számára ebben a mappában.';
			$visible = alatinfo_gdrive_drive_list_files($accessToken, 'trashed=false', 15);
			if ($visible['ok'] && $visible['files'] !== array()) {
				$sample = array();
				foreach ($visible['files'] as $f) {
					$sample[] = $f['name'] . ' [' . $f['id'] . ']';
				}
				$messages[] = 'A service account által elérhető fájlok (max. 15): ' . implode('; ', $sample);
			} elseif ($visible['ok']) {
				$messages[] = 'A service account egyetlen Drive fájlt sem lát – a megosztás valószínűleg nem érvényes.';
			}
			return array('ok' => false, 'messages' => $messages, 'file_id' => '', 'content_preview' => '');
		}

		$dl = alatinfo_gdrive_drive_download_text($accessToken, $fileId);
		if (!$dl['ok']) {
			$messages[] = 'HIBA: „' . $fileName . '” letöltése sikertelen (HTTP ' . $dl['http_code'] . '): ' . $dl['error'];
			return array('ok' => false, 'messages' => $messages, 'file_id' => $fileId, 'content_preview' => '');
		}

		$preview = trim($dl['content']);
		if (strlen($preview) > 300) {
			$preview = substr($preview, 0, 300) . '…';
		}
		$messages[] = 'Olvasási teszt sikeres: „' . $fileName . '” (ID: ' . $fileId . ').';
		if ($preview !== '') {
			$messages[] = 'Tartalom (előnézet): ' . $preview;
		} else {
			$messages[] = 'A fájl üres.';
		}

		return array(
			'ok' => true,
			'messages' => $messages,
			'file_id' => $fileId,
			'content_preview' => $preview,
		);
	}
}

if (!function_exists('alatinfo_gdrive_drive_write_probe')) {
	/**
	 * Kis tesztfájl feltöltése és törlése – írási jog ellenőrzése.
	 *
	 * @return array{ok:bool,http_code:int,error:string}
	 */
	function alatinfo_gdrive_drive_write_probe(string $accessToken, string $folderId): array
	{
		$boundary = 'alatinfo_' . bin2hex(random_bytes(8));
		$meta = json_encode(array(
			'name' => '_alatinfo_kapcsolat_teszt.txt',
			'parents' => array($folderId),
		));
		$content = 'Civil Sziget Drive kapcsolat teszt – ' . gmdate('c') . "\n";
		$body = "--{$boundary}\r\n"
			. "Content-Type: application/json; charset=UTF-8\r\n\r\n"
			. $meta . "\r\n"
			. "--{$boundary}\r\n"
			. "Content-Type: text/plain; charset=UTF-8\r\n\r\n"
			. $content . "\r\n"
			. "--{$boundary}--\r\n";

		$url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true';
		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array(
				'Authorization: Bearer ' . $accessToken,
				'Content-Type: multipart/related; boundary=' . $boundary,
			),
			CURLOPT_POSTFIELDS => $body,
			CURLOPT_TIMEOUT => 60,
		));
		$respBody = curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($code !== 200 && $code !== 201) {
			return array(
				'ok' => false,
				'http_code' => $code,
				'error' => alatinfo_gdrive_google_error_message(is_string($respBody) ? $respBody : ''),
			);
		}

		$fileId = '';
		if (is_string($respBody)) {
			$j = json_decode($respBody, true);
			if (is_array($j) && !empty($j['id'])) {
				$fileId = (string)$j['id'];
			}
		}
		if ($fileId === '') {
			return array('ok' => true, 'http_code' => $code, 'error' => '');
		}

		$delUrl = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($fileId) . '?supportsAllDrives=true';
		$ch2 = curl_init($delUrl);
		curl_setopt_array($ch2, array(
			CURLOPT_CUSTOMREQUEST => 'DELETE',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $accessToken),
			CURLOPT_TIMEOUT => 30,
		));
		curl_exec($ch2);
		curl_close($ch2);

		return array('ok' => true, 'http_code' => $code, 'error' => '');
	}
}

if (!function_exists('alatinfo_backup_test_drive_connection')) {
	/**
	 * @return array{ok:bool,messages:array<int,string>}
	 */
	function alatinfo_backup_test_drive_connection(): array
	{
		$messages = array();

		$cfg = alatinfo_backup_drive_load_config();
		if ($cfg === null) {
			return array('ok' => false, 'messages' => array('Hiányzik a Google Drive mentés konfigurációja.'));
		}
		$messages[] = 'Konfiguráció betöltve (mappa ID + hitelesítés).';

		if (!function_exists('curl_init')) {
			$messages[] = 'HIBA: PHP cURL nincs engedélyezve.';
			return array('ok' => false, 'messages' => $messages);
		}

		if ($cfg['auth'] === 'service_account' && !function_exists('openssl_sign')) {
			$messages[] = 'HIBA: OpenSSL nincs elérhető (JWT aláíráshoz kell).';
			return array('ok' => false, 'messages' => $messages);
		}

		$tokenRes = alatinfo_gdrive_access_token_for_backup($cfg);
		if (!$tokenRes['ok'] || $tokenRes['token'] === null) {
			$messages[] = 'HIBA: Google hitelesítés sikertelen (HTTP ' . $tokenRes['http_code'] . '): ' . $tokenRes['error'];
			if ($cfg['auth'] === 'oauth_session' || $cfg['auth'] === 'oauth_refresh') {
				foreach (alatinfo_gdrive_oauth_token_error_hints($tokenRes['error']) as $hint) {
					$messages[] = $hint;
				}
				if ($cfg['auth'] === 'oauth_session') {
					$messages[] = 'Kapcsold össze a Google fiókodat: Beállítások → Google Drive fiók, vagy jelentkezz be a mentés oldalon.';
				}
			} else {
				$messages[] = 'Ellenőrizd: Google Drive API engedélyezve a Cloud projektben, érvényes JSON kulcs.';
			}
			return array('ok' => false, 'messages' => $messages);
		}
		$messages[] = 'Hitelesítés: ' . $tokenRes['auth_label'];
		$messages[] = 'OAuth token rendben (Google API elérhető).';

		$token = $tokenRes['token'];

		$readProbe = alatinfo_gdrive_drive_probe_read_file($token, $cfg['folder_id'], 'teszt.txt');
		foreach ($readProbe['messages'] as $rm) {
			$messages[] = $rm;
		}
		$readOk = $readProbe['ok'];

		$folder = alatinfo_gdrive_drive_get($token, $cfg['folder_id']);
		$folderMetaOk = $folder['ok'];
		if ($folderMetaOk) {
			$folderLabel = $folder['name'] !== '' ? ('„' . $folder['name'] . '”') : '(névtelen)';
			$messages[] = 'Mappa metaadat: ' . $folderLabel . ' (ID: ' . $cfg['folder_id'] . ').';
		} elseif (!$readOk) {
			$messages[] = 'Mappa metaadat nem olvasható (HTTP ' . $folder['http_code'] . '): ' . $folder['error'];
		}

		$write = alatinfo_gdrive_drive_write_probe($token, $cfg['folder_id']);
		if (!$write['ok']) {
			$messages[] = 'HIBA: Írási teszt sikertelen (HTTP ' . $write['http_code'] . '): ' . $write['error'];
			if (alatinfo_gdrive_is_storage_quota_error($write['error'])) {
				foreach (alatinfo_gdrive_storage_quota_hints() as $hint) {
					$messages[] = $hint;
				}
			} else {
				$messages[] = 'Ellenőrizd a mappa ID-t: https://drive.google.com/drive/folders/' . $cfg['folder_id'];
				if ($cfg['auth'] === 'service_account') {
					$sa = alatinfo_gdrive_load_service_account($cfg['json_path']);
					if ($sa !== null) {
						$messages[] = 'Service account megosztás (Csapatmeghajtó): ' . $sa['client_email'];
					}
				}
			}
			if (!$readOk && (int)$folder['http_code'] === 404) {
				$messages[] = '404: rossz mappa ID, vagy a mappa nincs megosztva.';
			}
			return array('ok' => false, 'messages' => $messages);
		}
		$messages[] = 'Írási teszt sikeres (tesztfájl feltöltve és törölve).';

		if (!$readOk) {
			return array('ok' => false, 'messages' => $messages);
		}

		if ($folderMetaOk) {
			$messages[] = 'Olvasás + írás rendben – a teljes mentés indítható.';
		} else {
			$messages[] = 'Olvasás + írás rendben (mappa metaadat hibás volt) – a mentés indítható.';
		}

		return array('ok' => true, 'messages' => $messages);
	}
}

if (!function_exists('alatinfo_backup_mysqldump')) {
	/**
	 * @return array{ok:bool,path:?string,message:string}
	 */
	function alatinfo_backup_mysqldump(string $host, string $user, string $pass, string $db, string $outSql): array {
		$bin = getenv('MYSQLDUMP_PATH') ?: 'mysqldump';
		$cmd = $bin
			. ' --single-transaction --quick --skip-comments'
			. ' -h' . escapeshellarg($host)
			. ' -u' . escapeshellarg($user)
			. ' -p' . escapeshellarg($pass)
			. ' ' . escapeshellarg($db)
			. ' > ' . escapeshellarg($outSql) . ' 2>&1';
		$out = array();
		$ret = 0;
		@exec($cmd, $out, $ret);
		if ($ret === 0 && is_file($outSql) && filesize($outSql) > 32) {
			return array('ok' => true, 'path' => $outSql, 'message' => 'mysqldump kész.');
		}
		$msg = $out ? implode("\n", $out) : ('exit ' . $ret);
		return array('ok' => false, 'path' => null, 'message' => $msg);
	}
}

if (!function_exists('alatinfo_backup_pdo_dump')) {
	/**
	 * Egyszerű SQL export (nagy tábláknál lassú / memória).
	 * @return array{ok:bool,path:?string,message:string}
	 */
	function alatinfo_backup_pdo_dump(PDO $db, string $outSql): array {
		$f = fopen($outSql, 'wb');
		if ($f === false) {
			return array('ok' => false, 'path' => null, 'message' => 'SQL fájl nem írható.');
		}
		fwrite($f, "-- Alatinfo backup (PDO)\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
		try {
			$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
		} catch (Throwable $e) {
			fclose($f);
			return array('ok' => false, 'path' => null, 'message' => 'SHOW TABLES hiba.');
		}
		foreach ($tables as $tr) {
			$t = (string) ($tr[0] ?? '');
			if ($t === '') {
				continue;
			}
			$t_esc = '`' . str_replace('`', '``', $t) . '`';
			try {
				$cr = $db->query('SHOW CREATE TABLE ' . $t_esc)->fetch(PDO::FETCH_ASSOC);
			} catch (Throwable $e) {
				continue;
			}
			$create = (string)($cr['Create Table'] ?? '');
			if ($create !== '') {
				fwrite($f, "DROP TABLE IF EXISTS $t_esc;\n$create;\n\n");
			}
			try {
				$dq = $db->query('SELECT * FROM ' . $t_esc);
			} catch (Throwable $e) {
				continue;
			}
			$colCount = $dq->columnCount();
			$cols = array();
			for ($i = 0; $i < $colCount; $i++) {
				$meta = $dq->getColumnMeta($i);
				$name = (string) ($meta['name'] ?? '');
				if ($name !== '') {
					$cols[] = '`' . str_replace('`', '``', $name) . '`';
				}
			}
			$colList = implode(',', $cols);
			$n = 0;
			while ($row = $dq->fetch(PDO::FETCH_ASSOC)) {
				$vals = array();
				foreach ($row as $v) {
					if ($v === null) {
						$vals[] = 'NULL';
					} else {
						$vals[] = $db->quote((string) $v);
					}
				}
				fwrite($f, 'INSERT INTO ' . $t_esc . ' (' . $colList . ') VALUES (' . implode(',', $vals) . ");\n");
				$n++;
				if ($n % 200 === 0) {
					fwrite($f, "\n");
				}
			}
			fwrite($f, "\n");
		}
		fwrite($f, "SET FOREIGN_KEY_CHECKS=1;\n");
		fclose($f);
		if (!is_file($outSql) || filesize($outSql) < 32) {
			return array('ok' => false, 'path' => null, 'message' => 'Üres vagy túl kicsi export.');
		}
		return array('ok' => true, 'path' => $outSql, 'message' => 'PDO export kész.');
	}
}

if (!function_exists('alatinfo_backup_default_exclude_paths')) {
	/** @return list<string> */
	function alatinfo_backup_default_exclude_paths(): array
	{
		return array(
			'.git',
			'node_modules',
			'.svn',
			'__MACOSX',
			'secret_folder',
			'nextgen/vendor',
			'nextgen/node_modules',
			'nextgen/database/backups',
			'nextgen/database/dumps',
		);
	}
}

if (!function_exists('alatinfo_backup_rel_excluded')) {
	/** @param array<string, true> $excludeMap */
	function alatinfo_backup_rel_excluded(string $rel, array $excludeMap): bool
	{
		foreach ($excludeMap as $prefix => $_) {
			if ($rel === $prefix || strpos($rel, $prefix . '/') === 0) {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('alatinfo_backup_export_sql')) {
	/**
	 * @return array{ok:bool,path:?string,message:string}
	 */
	function alatinfo_backup_export_sql(PDO $db, string $outSql): array
	{
		$host = defined('DB_HOST') ? (string) DB_HOST : (getenv('DB_HOST') ?: 'localhost');
		$user = defined('DB_USER') ? (string) DB_USER : (getenv('DB_USER') ?: '');
		$pass = defined('DB_PASS') ? (string) DB_PASS : (getenv('DB_PASSWORD') ?: '');
		$dbName = defined('DB_NAME') ? (string) DB_NAME : (getenv('DB_NAME') ?: '');
		$dump = null;
		if ($user !== '' && $dbName !== '') {
			$dump = alatinfo_backup_mysqldump($host, $user, $pass, $dbName, $outSql);
			if ($dump['ok']) {
				return $dump;
			}
		}
		if ($dump !== null && !$dump['ok']) {
			$pdoDump = alatinfo_backup_pdo_dump($db, $outSql);
			if ($pdoDump['ok']) {
				return array(
					'ok' => true,
					'path' => $outSql,
					'message' => 'mysqldump nem elérhető, PHP export kész.',
				);
			}
			return array(
				'ok' => false,
				'path' => null,
				'message' => 'mysqldump: ' . $dump['message'] . ' | PHP: ' . $pdoDump['message'],
			);
		}
		return alatinfo_backup_pdo_dump($db, $outSql);
	}
}

if (!function_exists('alatinfo_backup_create_full_zip')) {
	/**
	 * Egy ZIP: database.sql + site/ fájlfa.
	 *
	 * @return array{ok:bool,path:?string,filename:string,tmpdir:string,messages:list<string>}
	 */
	function alatinfo_backup_create_full_zip(PDO $db, ?array $excludeRel = null): array
	{
		$messages = array();
		if (!class_exists('ZipArchive')) {
			return array(
				'ok' => false,
				'path' => null,
				'filename' => '',
				'tmpdir' => '',
				'messages' => array('Nincs ZipArchive kiterjesztés – a teljes mentés nem készíthető.'),
			);
		}

		$excludeRel = $excludeRel ?? alatinfo_backup_default_exclude_paths();
		$excludeMap = array();
		foreach ($excludeRel as $r) {
			$r = str_replace('\\', '/', trim((string)$r, "/\\"));
			if ($r !== '') {
				$excludeMap[$r] = true;
			}
		}

		$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alatinfo_bk_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
		if (!@mkdir($tmp, 0700, true)) {
			return array(
				'ok' => false,
				'path' => null,
				'filename' => '',
				'tmpdir' => '',
				'messages' => array('Átmeneti mappa létrehozása sikertelen.'),
			);
		}

		$sqlPath = $tmp . DIRECTORY_SEPARATOR . 'database.sql';
		$dump = alatinfo_backup_export_sql($db, $sqlPath);
		if (!$dump['ok']) {
			$messages[] = 'Adatbázis export sikertelen: ' . $dump['message'];
			alatinfo_backup_rrmdir($tmp);
			return array('ok' => false, 'path' => null, 'filename' => '', 'tmpdir' => $tmp, 'messages' => $messages);
		}
		$messages[] = $dump['message'];

		$root = alatinfo_backup_project_root();
		if ($root === false) {
			$messages[] = 'Projekt gyökér nem található.';
			alatinfo_backup_rrmdir($tmp);
			return array('ok' => false, 'path' => null, 'filename' => '', 'tmpdir' => $tmp, 'messages' => $messages);
		}

		$tz = date_default_timezone_get();
		if ($tz === '' || $tz === 'UTC') {
			@date_default_timezone_set('Europe/Budapest');
		}
		$stamp = date('Y-m-d_His');
		$filename = 'alatinfo_' . $stamp . '.zip';
		$zipPath = $tmp . DIRECTORY_SEPARATOR . $filename;

		$zip = new ZipArchive();
		if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			$messages[] = 'ZIP megnyitása sikertelen.';
			alatinfo_backup_rrmdir($tmp);
			return array('ok' => false, 'path' => null, 'filename' => $filename, 'tmpdir' => $tmp, 'messages' => $messages);
		}

		$zip->addFile($sqlPath, 'database.sql');

		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		$fileCount = 0;
		foreach ($it as $file) {
			/** @var SplFileInfo $file */
			if (!$file->isFile()) {
				continue;
			}
			$full = $file->getRealPath();
			if ($full === false) {
				continue;
			}
			$rel = ltrim(str_replace('\\', '/', substr($full, strlen($root))), '/');
			if (alatinfo_backup_rel_excluded($rel, $excludeMap)) {
				continue;
			}
			$zip->addFile($full, 'site/' . $rel);
			$fileCount++;
		}
		$zip->close();

		if (!is_file($zipPath) || filesize($zipPath) < 64) {
			$messages[] = 'ZIP üres vagy túl kicsi.';
			alatinfo_backup_rrmdir($tmp);
			return array('ok' => false, 'path' => null, 'filename' => $filename, 'tmpdir' => $tmp, 'messages' => $messages);
		}

		$messages[] = 'ZIP kész: database.sql + ' . $fileCount . ' fájl a site/ mappában.';
		return array(
			'ok' => true,
			'path' => $zipPath,
			'filename' => $filename,
			'tmpdir' => $tmp,
			'messages' => $messages,
		);
	}
}

if (!function_exists('alatinfo_backup_zip_dir')) {
	/**
	 * @param array<int,string> $excludeRel
	 * @param callable(int $fileCount): void|null $onFileProgress
	 * @return array{ok:bool,path:?string,message:string,file_count:int}
	 */
	function alatinfo_backup_zip_dir(
		string $rootDir,
		string $outZip,
		array $excludeRel = array(),
		?callable $onFileProgress = null
	): array {
		$fileCount = 0;
		if (!class_exists('ZipArchive')) {
			return array('ok' => false, 'path' => null, 'message' => 'Nincs ZipArchive kiterjesztés.', 'file_count' => 0);
		}
		$root = realpath($rootDir);
		if ($root === false) {
			return array('ok' => false, 'path' => null, 'message' => 'Gyökérkönyvtár nem található.', 'file_count' => 0);
		}
		$ex = array();
		foreach ($excludeRel as $r) {
			$r = str_replace('\\', '/', trim((string)$r, "/\\"));
			if ($r !== '') {
				$ex[$r] = true;
			}
		}
		$zip = new ZipArchive();
		if ($zip->open($outZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			return array('ok' => false, 'path' => null, 'message' => 'ZIP megnyitása sikertelen.', 'file_count' => 0);
		}
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ($it as $file) {
			/** @var SplFileInfo $file */
			if (!$file->isFile()) {
				continue;
			}
			$full = $file->getRealPath();
			if ($full === false) {
				continue;
			}
			$rel = ltrim(str_replace('\\', '/', substr($full, strlen($root))), '/');
			if (alatinfo_backup_rel_excluded($rel, $ex)) {
				continue;
			}
			$zip->addFile($full, 'site/' . $rel);
			$fileCount++;
			if ($onFileProgress !== null) {
				$onFileProgress($fileCount);
			}
		}
		$zip->close();
		if (!is_file($outZip) || filesize($outZip) < 64) {
			return array('ok' => false, 'path' => null, 'message' => 'ZIP üres vagy túl kicsi.', 'file_count' => $fileCount);
		}
		return array(
			'ok' => true,
			'path' => $outZip,
			'message' => 'ZIP kész (' . $fileCount . ' fájl).',
			'file_count' => $fileCount,
		);
	}
}

if (!function_exists('alatinfo_backup_format_bytes')) {
	function alatinfo_backup_format_bytes(int $bytes): string
	{
		if ($bytes < 1024) {
			return $bytes . ' B';
		}
		if ($bytes < 1024 * 1024) {
			return round($bytes / 1024, 1) . ' KB';
		}
		if ($bytes < 1024 * 1024 * 1024) {
			return round($bytes / (1024 * 1024), 1) . ' MB';
		}
		return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
	}
}

if (!function_exists('alatinfo_backup_drive_job_session_key')) {
	function alatinfo_backup_drive_job_session_key(): string
	{
		return 'alatinfo_gdrive_backup_job';
	}
}

if (!function_exists('alatinfo_backup_drive_job_get')) {
	/** @return array<string,mixed>|null */
	function alatinfo_backup_drive_job_get(): ?array
	{
		if (session_status() !== PHP_SESSION_ACTIVE) {
			return null;
		}
		$key = alatinfo_backup_drive_job_session_key();
		$job = $_SESSION[$key] ?? null;
		return is_array($job) ? $job : null;
	}
}

if (!function_exists('alatinfo_backup_drive_session_release')) {
	function alatinfo_backup_drive_session_release(): void
	{
		if (session_status() === PHP_SESSION_ACTIVE) {
			session_write_close();
		}
	}
}

if (!function_exists('alatinfo_backup_drive_session_ensure')) {
	function alatinfo_backup_drive_session_ensure(): void
	{
		if (session_status() !== PHP_SESSION_ACTIVE) {
			@session_start();
		}
	}
}

if (!function_exists('alatinfo_backup_drive_job_set')) {
	/** @param array<string,mixed> $job */
	function alatinfo_backup_drive_job_set(array $job): void
	{
		alatinfo_backup_drive_session_ensure();
		$_SESSION[alatinfo_backup_drive_job_session_key()] = $job;
	}
}

if (!function_exists('alatinfo_backup_drive_job_clear')) {
	function alatinfo_backup_drive_job_clear(): void
	{
		alatinfo_backup_drive_session_ensure();
		$job = alatinfo_backup_drive_job_get();
		if ($job !== null && !empty($job['job_id'])) {
			$path = alatinfo_backup_drive_job_progress_path((string)$job['job_id']);
			if (is_file($path)) {
				@unlink($path);
			}
		}
		unset($_SESSION[alatinfo_backup_drive_job_session_key()]);
	}
}

if (!function_exists('alatinfo_backup_drive_job_progress_path')) {
	function alatinfo_backup_drive_job_progress_path(string $jobId): string
	{
		$safe = preg_replace('/[^a-f0-9]/', '', strtolower($jobId));
		return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alatinfo_bk_prog_' . $safe . '.json';
	}
}

if (!function_exists('alatinfo_backup_drive_job_progress_write')) {
	/** @param array<string,mixed> $data */
	function alatinfo_backup_drive_job_progress_write(string $jobId, array $data): void
	{
		if ($jobId === '') {
			return;
		}
		$data['updated_at'] = time();
		@file_put_contents(
			alatinfo_backup_drive_job_progress_path($jobId),
			json_encode($data, JSON_UNESCAPED_UNICODE),
			LOCK_EX
		);
	}
}

if (!function_exists('alatinfo_backup_drive_job_progress_read')) {
	/** @return array<string,mixed>|null */
	function alatinfo_backup_drive_job_progress_read(string $jobId): ?array
	{
		if ($jobId === '') {
			return null;
		}
		$path = alatinfo_backup_drive_job_progress_path($jobId);
		if (!is_readable($path)) {
			return null;
		}
		$raw = @file_get_contents($path);
		if ($raw === false || $raw === '') {
			return null;
		}
		$j = json_decode($raw, true);
		return is_array($j) ? $j : null;
	}
}

if (!function_exists('alatinfo_backup_drive_upload_progress_callback')) {
	/**
	 * @return callable(int,int):void
	 */
	function alatinfo_backup_drive_upload_progress_callback(
		string $jobId,
		string $step,
		string $label,
		int $rangeStart,
		int $rangeEnd
	): callable {
		$lastUploadPct = -1;
		$lastEmitAt = 0.0;
		return static function (int $uploaded, int $total) use (
			$jobId,
			$step,
			$label,
			$rangeStart,
			$rangeEnd,
			&$lastUploadPct,
			&$lastEmitAt
		): void {
			if ($total <= 0) {
				return;
			}
			$uploadPct = (int)floor($uploaded * 100 / $total);
			$now = microtime(true);
			if ($uploadPct <= $lastUploadPct && ($now - $lastEmitAt) < 0.3) {
				return;
			}
			$lastUploadPct = $uploadPct;
			$lastEmitAt = $now;
			$overall = $rangeStart + (int)floor(($rangeEnd - $rangeStart) * $uploaded / $total);
			alatinfo_backup_drive_job_progress_write($jobId, array(
				'step' => $step,
				'message' => $label . ' feltöltése…',
				'percent' => $overall,
				'upload_bytes' => $uploaded,
				'upload_total' => $total,
				'upload_percent' => $uploadPct,
				'detail' => alatinfo_backup_format_bytes($uploaded)
					. ' / ' . alatinfo_backup_format_bytes($total)
					. ' (' . $uploadPct . '%)',
			));
		};
	}
}

if (!function_exists('alatinfo_backup_drive_json_response')) {
	/** @param array<string,mixed> $data */
	function alatinfo_backup_drive_json_response(array $data, int $code = 200): void
	{
		http_response_code($code);
		header('Content-Type: application/json; charset=UTF-8');
		header('Cache-Control: no-cache');
		echo json_encode($data, JSON_UNESCAPED_UNICODE);
	}
}

if (!function_exists('alatinfo_backup_run_to_google_drive')) {
	/**
	 * @param callable(array{step:string,message:string,percent:int,upload_bytes?:int,upload_total?:int,upload_percent?:int,detail?:string}):void|null $onProgress
	 * @return array{ok:bool,messages:array<int,string>}
	 */
	function alatinfo_backup_run_to_google_drive(PDO $db, ?callable $onProgress = null): array {
		$messages = array();
		$emit = static function (array $data) use ($onProgress): void {
			if ($onProgress === null) {
				return;
			}
			$payload = array(
				'step' => (string)($data['step'] ?? ''),
				'message' => (string)($data['message'] ?? ''),
				'percent' => max(0, min(100, (int)($data['percent'] ?? 0))),
			);
			foreach (array('upload_bytes', 'upload_total', 'upload_percent', 'detail') as $key) {
				if (array_key_exists($key, $data)) {
					$payload[$key] = $data[$key];
				}
			}
			$onProgress($payload);
		};

		$uploadProgressFactory = static function (
			string $step,
			string $label,
			int $rangeStart,
			int $rangeEnd
		) use ($emit): callable {
			$lastUploadPct = -1;
			$lastEmitAt = 0.0;
			return static function (int $uploaded, int $total) use (
				$emit,
				$step,
				$label,
				$rangeStart,
				$rangeEnd,
				&$lastUploadPct,
				&$lastEmitAt
			): void {
				if ($total <= 0) {
					return;
				}
				$uploadPct = (int)floor($uploaded * 100 / $total);
				$now = microtime(true);
				if ($uploadPct <= $lastUploadPct && ($now - $lastEmitAt) < 0.35) {
					return;
				}
				$lastUploadPct = $uploadPct;
				$lastEmitAt = $now;
				$overall = $rangeStart + (int)floor(($rangeEnd - $rangeStart) * $uploaded / $total);
				$emit(array(
					'step' => $step,
					'message' => $label . ' feltöltése…',
					'percent' => $overall,
					'upload_bytes' => $uploaded,
					'upload_total' => $total,
					'upload_percent' => $uploadPct,
					'detail' => alatinfo_backup_format_bytes($uploaded)
						. ' / ' . alatinfo_backup_format_bytes($total)
						. ' (' . $uploadPct . '%)',
				));
			};
		};

		$emit(array('step' => 'auth', 'message' => 'Konfiguráció és OAuth token…', 'percent' => 2));
		$cfg = alatinfo_backup_drive_load_config();
		if ($cfg === null) {
			return array('ok' => false, 'messages' => array('Hiányzik a Google Drive mentés konfigurációja (lásd minta fájl).'));
		}
		$tokenRes = alatinfo_gdrive_access_token_for_backup($cfg);
		if (!$tokenRes['ok'] || $tokenRes['token'] === null) {
			return array('ok' => false, 'messages' => array('OAuth token kérés sikertelen: ' . $tokenRes['error']));
		}
		$messages[] = 'Hitelesítés rendben (' . $tokenRes['auth_label'] . ').';
		$token = $tokenRes['token'];
		$emit(array('step' => 'auth', 'message' => 'Hitelesítés rendben.', 'percent' => 8));

		$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alatinfo_bk_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
		if (!@mkdir($tmp, 0700, true)) {
			return array('ok' => false, 'messages' => array('Átmeneti mappa létrehozása sikertelen: ' . $tmp));
		}
		$sqlPath = $tmp . DIRECTORY_SEPARATOR . 'database.sql';
		$zipPath = $tmp . DIRECTORY_SEPARATOR . 'site.zip';

		$emit(array('step' => 'sql', 'message' => 'Adatbázis export (SQL)…', 'percent' => 10));
		$dump = alatinfo_backup_export_sql($db, $sqlPath);
		if (!$dump['ok']) {
			$messages[] = 'Adatbázis export sikertelen: ' . $dump['message'];
			alatinfo_backup_rrmdir($tmp);
			return array('ok' => false, 'messages' => $messages);
		}
		$messages[] = $dump['message'];
		$sqlSize = is_file($sqlPath) ? (int)filesize($sqlPath) : 0;
		$emit(array(
			'step' => 'sql',
			'message' => 'SQL export kész.',
			'percent' => 24,
			'detail' => $sqlSize > 0 ? ('Fájlméret: ' . alatinfo_backup_format_bytes($sqlSize)) : '',
		));

		$emit(array('step' => 'zip', 'message' => 'Projekt fájlok csomagolása (ZIP)…', 'percent' => 26));
		$root = alatinfo_backup_project_root();
		if ($root === false) {
			alatinfo_backup_rrmdir($tmp);
			return array('ok' => false, 'messages' => array('Projekt gyökér nem található.'));
		}
		$zipFileCount = 0;
		$zip = alatinfo_backup_zip_dir(
			$root,
			$zipPath,
			alatinfo_backup_default_exclude_paths(),
			static function (int $count) use ($emit): void {
				if ($count % 40 !== 0) {
					return;
				}
				$zipPct = min(44, 26 + (int)floor(min(1.0, $count / 800) * 18));
				$emit(array(
					'step' => 'zip',
					'message' => 'ZIP készítése…',
					'percent' => $zipPct,
					'detail' => $count . ' fájl hozzáadva',
				));
			}
		);
		if (!$zip['ok']) {
			$messages[] = $zip['message'];
			alatinfo_backup_rrmdir($tmp);
			return array('ok' => false, 'messages' => $messages);
		}
		$zipFileCount = (int)($zip['file_count'] ?? 0);
		$messages[] = $zip['message'];
		$zipSize = is_file($zipPath) ? (int)filesize($zipPath) : 0;
		$emit(array(
			'step' => 'zip',
			'message' => 'ZIP kész.',
			'percent' => 46,
			'detail' => ($zipFileCount > 0 ? ($zipFileCount . ' fájl, ') : '')
				. ($zipSize > 0 ? alatinfo_backup_format_bytes($zipSize) : ''),
		));

		$stamp = gmdate('Y-m-d_His');
		$sqlDriveName = 'alatinfo_db_' . $stamp . '.sql';
		$emit(array(
			'step' => 'upload_sql',
			'message' => 'SQL feltöltés indítása…',
			'percent' => 48,
			'detail' => $sqlSize > 0 ? alatinfo_backup_format_bytes($sqlSize) : '',
		));
		$r1 = alatinfo_gdrive_upload_resumable(
			$token,
			$cfg['folder_id'],
			$sqlPath,
			$sqlDriveName,
			'application/sql',
			$uploadProgressFactory('upload_sql', 'SQL', 48, 72)
		);
		$messages[] = $r1['message'];
		if (!$r1['ok']) {
			if (!empty($r1['storage_quota'])) {
				foreach (alatinfo_gdrive_storage_quota_hints() as $hint) {
					$messages[] = $hint;
				}
			}
			alatinfo_backup_rrmdir($tmp);
			return array('ok' => false, 'messages' => $messages);
		}
		$emit(array('step' => 'upload_sql', 'message' => 'SQL feltöltve.', 'percent' => 72, 'detail' => $sqlDriveName));

		$zipDriveName = 'alatinfo_site_' . $stamp . '.zip';
		$emit(array(
			'step' => 'upload_zip',
			'message' => 'ZIP feltöltés indítása…',
			'percent' => 74,
			'detail' => $zipSize > 0 ? alatinfo_backup_format_bytes($zipSize) : '',
		));
		$r2 = alatinfo_gdrive_upload_resumable(
			$token,
			$cfg['folder_id'],
			$zipPath,
			$zipDriveName,
			'application/zip',
			$uploadProgressFactory('upload_zip', 'ZIP', 74, 97)
		);
		$messages[] = $r2['message'];

		$emit(array('step' => 'cleanup', 'message' => 'Ideiglenes fájlok törlése…', 'percent' => 98));
		alatinfo_backup_rrmdir($tmp);
		$emit(array(
			'step' => 'cleanup',
			'message' => $r2['ok'] ? 'Mentés kész.' : 'Mentés hibával végződött.',
			'percent' => 100,
			'detail' => '',
		));

		return array('ok' => $r2['ok'], 'messages' => $messages);
	}
}

if (!function_exists('alatinfo_backup_rrmdir')) {
	function alatinfo_backup_rrmdir(string $dir): void {
		if (!is_dir($dir)) {
			return;
		}
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ($it as $f) {
			$p = $f->getRealPath();
			if ($p === false) {
				continue;
			}
			if ($f->isDir()) {
				@rmdir($p);
			} else {
				@unlink($p);
			}
		}
		@rmdir($dir);
	}
}
