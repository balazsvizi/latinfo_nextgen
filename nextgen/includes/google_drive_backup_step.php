<?php
declare(strict_types=1);

require_once __DIR__ . '/google_drive_backup.php';
require_once __DIR__ . '/google_drive_backup_log.php';

if (!function_exists('alatinfo_backup_google_drive_abort_job')) {
	function alatinfo_backup_google_drive_abort_job(array $job, string $reason): void
	{
		$logId = isset($job['log_id']) ? (int) $job['log_id'] : null;
		$messages = is_array($job['messages'] ?? null) ? $job['messages'] : array();
		$messages[] = $reason;
		alatinfo_gdrive_backup_log_append($logId, $reason);
		alatinfo_gdrive_backup_log_finish($logId, false, $messages);
		if (!empty($job['tmp']) && is_dir((string) $job['tmp'])) {
			alatinfo_backup_rrmdir((string) $job['tmp']);
		}
		$jobId = (string) ($job['job_id'] ?? '');
		if ($jobId !== '') {
			alatinfo_backup_drive_job_clear_cancel_flag($jobId);
		}
		alatinfo_backup_drive_job_clear();
	}
}

if (!function_exists('alatinfo_backup_google_drive_cancelled_response')) {
	/** @param list<string> $messages */
	function alatinfo_backup_google_drive_cancelled_response(array $job, string $reason, array $messages = array()): void
	{
		alatinfo_backup_google_drive_abort_job($job, $reason);
		alatinfo_backup_drive_json_response(array(
			'ok' => false,
			'cancelled' => true,
			'job_id' => (string) ($job['job_id'] ?? ''),
			'messages' => $messages !== array() ? $messages : array($reason),
			'progress' => array(
				'step' => 'cleanup',
				'message' => $reason,
				'percent' => 0,
			),
		));
	}
}

if (!function_exists('alatinfo_backup_google_drive_step_maybe_cancelled')) {
	function alatinfo_backup_google_drive_step_maybe_cancelled(array $job, string $jobId): bool
	{
		if (!alatinfo_backup_drive_job_is_cancelled($jobId, $job)) {
			return false;
		}
		$messages = is_array($job['messages'] ?? null) ? $job['messages'] : array();
		$messages[] = 'Megszakítva a felhasználó által.';
		alatinfo_backup_google_drive_cancelled_response($job, 'Megszakítva a felhasználó által.', $messages);
		return true;
	}
}

if (!function_exists('alatinfo_backup_google_drive_handle_cancel')) {
	function alatinfo_backup_google_drive_handle_cancel(): void
	{
		$guard = alatinfo_backup_google_drive_step_guard();
		if (!$guard['ok']) {
			alatinfo_backup_drive_json_response(array(
				'ok' => false,
				'messages' => array($guard['message']),
			), $guard['message'] === 'Nincs belépve.' ? 403 : 400);
			return;
		}
		$job = alatinfo_backup_drive_job_get();
		if ($job === null || empty($job['job_id'])) {
			alatinfo_backup_drive_json_response(array(
				'ok' => true,
				'messages' => array('Nincs futó mentés.'),
			));
			return;
		}
		$jobId = (string) $job['job_id'];
		alatinfo_backup_drive_job_request_cancel($jobId);
		$reason = 'Megszakítva a felhasználó által.';
		$messages = is_array($job['messages'] ?? null) ? $job['messages'] : array();
		$messages[] = $reason;
		alatinfo_backup_google_drive_cancelled_response($job, $reason, $messages);
	}
}

if (!function_exists('alatinfo_backup_google_drive_step_guard')) {
	/** @return array{ok:bool,message:string} */
	function alatinfo_backup_google_drive_step_guard(): array
	{
		if (!function_exists('isLoggedIn') || !isLoggedIn()) {
			return array('ok' => false, 'message' => 'Nincs belépve.');
		}
		if (!function_exists('isSuperadmin') || !isSuperadmin()) {
			return array('ok' => false, 'message' => 'Nincs jogosultság.');
		}
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
			return array('ok' => false, 'message' => 'Érvénytelen kérés.');
		}
		if (!function_exists('csrf_validate') || !csrf_validate('backup_drive')) {
			return array('ok' => false, 'message' => 'Biztonsági token érvénytelen.');
		}
		return array('ok' => true, 'message' => '');
	}
}

if (!function_exists('alatinfo_backup_google_drive_step_progress')) {
	/** @return array<string,mixed> */
	function alatinfo_backup_google_drive_step_progress(
		string $jobId,
		string $step,
		string $message,
		int $percent,
		string $detail = '',
		?int $uploadPercent = null
	): array {
		$data = array(
			'step' => $step,
			'message' => $message,
			'percent' => max(0, min(100, $percent)),
			'detail' => $detail,
		);
		if ($uploadPercent !== null) {
			$data['upload_percent'] = $uploadPercent;
		}
		alatinfo_backup_drive_job_progress_write($jobId, $data);
		return $data;
	}
}

if (!function_exists('alatinfo_backup_google_drive_handle_poll')) {
	function alatinfo_backup_google_drive_handle_poll(): void
	{
		if (!function_exists('isLoggedIn') || !isLoggedIn() || !function_exists('isSuperadmin') || !isSuperadmin()) {
			alatinfo_backup_drive_json_response(array('ok' => false, 'progress' => null), 403);
			return;
		}
		$jobId = trim((string)($_GET['job_id'] ?? ''));
		$job = alatinfo_backup_drive_job_get();
		if ($jobId === '' && $job !== null) {
			$jobId = (string)($job['job_id'] ?? '');
		}
		$sessionJobId = ($job !== null) ? (string)($job['job_id'] ?? '') : '';
		alatinfo_backup_drive_session_release();
		if ($jobId === '' || $sessionJobId === '' || $sessionJobId !== $jobId) {
			alatinfo_backup_drive_json_response(array('ok' => true, 'progress' => null));
			return;
		}
		$progress = alatinfo_backup_drive_job_progress_read($jobId);
		alatinfo_backup_drive_json_response(array('ok' => true, 'progress' => $progress));
	}
}

if (!function_exists('alatinfo_backup_google_drive_handle_step')) {
	function alatinfo_backup_google_drive_handle_step(PDO $db): void
	{
		$guard = alatinfo_backup_google_drive_step_guard();
		if (!$guard['ok']) {
			alatinfo_backup_drive_json_response(array(
				'ok' => false,
				'messages' => array($guard['message']),
				'progress' => null,
			), $guard['message'] === 'Nincs belépve.' ? 403 : 400);
			return;
		}

		@set_time_limit(0);
		if (function_exists('ignore_user_abort')) {
			ignore_user_abort(true);
		}

		$step = trim((string)($_POST['backup_step'] ?? ''));
		$allowed = array('start', 'sql', 'zip', 'upload_sql', 'upload_zip', 'cleanup');
		if (!in_array($step, $allowed, true)) {
			alatinfo_backup_drive_json_response(array(
				'ok' => false,
				'messages' => array('Ismeretlen lépés.'),
				'progress' => null,
			), 400);
			return;
		}

		$job = alatinfo_backup_drive_job_get();
		$stepMessages = array();

		if ($step === 'start') {
			alatinfo_backup_drive_job_clear();
			$options = alatinfo_backup_parse_options_from_post($_POST);
			if ($options['error'] !== '') {
				alatinfo_backup_drive_json_response(array(
					'ok' => false,
					'messages' => array($options['error']),
					'progress' => null,
				), 400);
				return;
			}
			$cfg = alatinfo_backup_drive_load_config();
			if ($cfg === null) {
				alatinfo_backup_drive_json_response(array(
					'ok' => false,
					'messages' => array('Hiányzik a Google Drive mentés konfigurációja.'),
					'progress' => null,
				));
				return;
			}
			$jobId = bin2hex(random_bytes(16));
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'auth',
				'Konfiguráció és OAuth token…',
				2
			);
			$tokenRes = alatinfo_gdrive_access_token_for_backup($cfg);
			if (!$tokenRes['ok'] || $tokenRes['token'] === null) {
				alatinfo_backup_drive_job_clear();
				alatinfo_backup_drive_json_response(array(
					'ok' => false,
					'job_id' => $jobId,
					'messages' => array('Google hitelesítés sikertelen: ' . $tokenRes['error'] . ' Jelentkezz be a mappa tulajdonosának fiókjával.'),
					'progress' => $progress,
				));
				return;
			}
			$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'alatinfo_bk_' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4));
			if (!@mkdir($tmp, 0700, true)) {
				alatinfo_backup_drive_job_clear();
				alatinfo_backup_drive_json_response(array(
					'ok' => false,
					'job_id' => $jobId,
					'messages' => array('Átmeneti mappa létrehozása sikertelen.'),
					'progress' => $progress,
				));
				return;
			}
			$stamp = gmdate('Y-m-d_His');
			$googleEmail = function_exists('alatinfo_gdrive_user_display_email')
				? alatinfo_gdrive_user_display_email()
				: '';
			$logId = alatinfo_gdrive_backup_log_create($options, $googleEmail);
			$targetParts = array();
			if ($options['include_db']) {
				$targetParts[] = 'adatbázis';
			}
			if ($options['include_files']) {
				$targetParts[] = 'fájlok';
			}
			$initMessages = array(
				'Hitelesítés rendben (' . $tokenRes['auth_label'] . ').',
				'Cél: ' . implode(' + ', $targetParts) . '.',
				'Dátumszűrő (fájlok): ' . $options['date_from_label'] . '.',
			);
			foreach ($initMessages as $m) {
				alatinfo_gdrive_backup_log_append($logId, $m);
			}
			$job = array(
				'job_id' => $jobId,
				'tmp' => $tmp,
				'folder_id' => $cfg['folder_id'],
				'sql_path' => $tmp . DIRECTORY_SEPARATOR . 'database.sql',
				'zip_path' => $tmp . DIRECTORY_SEPARATOR . 'site.zip',
				'stamp' => $stamp,
				'messages' => $initMessages,
				'include_db' => $options['include_db'],
				'include_files' => $options['include_files'],
				'date_from' => $options['date_from'],
				'date_from_label' => $options['date_from_label'],
				'log_id' => $logId,
				'sql_uploaded' => false,
				'zip_uploaded' => false,
				'sql_drive_name' => null,
				'zip_drive_name' => null,
			);
			alatinfo_backup_drive_job_set($job);
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'auth',
				'Hitelesítés rendben.',
				8
			);
			alatinfo_backup_drive_session_release();
			alatinfo_backup_drive_json_response(array(
				'ok' => true,
				'job_id' => $jobId,
				'messages' => $job['messages'],
				'progress' => $progress,
				'done' => false,
			));
			return;
		}

		if ($job === null || empty($job['job_id'])) {
			alatinfo_backup_drive_json_response(array(
				'ok' => false,
				'messages' => array('Nincs aktív mentés – indítsd újra.'),
				'progress' => null,
			));
			return;
		}
		$jobId = (string)$job['job_id'];

		if (alatinfo_backup_google_drive_step_maybe_cancelled($job, $jobId)) {
			return;
		}

		if ($step === 'sql') {
			$logId = isset($job['log_id']) ? (int) $job['log_id'] : null;
			if (empty($job['include_db'])) {
				$stepMessages[] = 'Adatbázis export kihagyva (nincs kiválasztva).';
				alatinfo_gdrive_backup_log_append($logId, $stepMessages[0]);
				$job['messages'] = array_merge($job['messages'], $stepMessages);
				alatinfo_backup_drive_job_set($job);
				$progress = alatinfo_backup_google_drive_step_progress($jobId, 'sql', 'SQL kihagyva.', 24);
				alatinfo_backup_drive_json_response(array(
					'ok' => true,
					'job_id' => $jobId,
					'messages' => $stepMessages,
					'progress' => $progress,
					'done' => false,
				));
				return;
			}
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'sql',
				'Adatbázis export (SQL)…',
				12
			);
			alatinfo_backup_drive_session_release();
			$dump = alatinfo_backup_export_sql($db, (string)$job['sql_path']);
			if (alatinfo_backup_google_drive_step_maybe_cancelled($job, $jobId)) {
				return;
			}
			if (!$dump['ok']) {
				alatinfo_gdrive_backup_log_append($logId, 'HIBA: ' . $dump['message']);
				alatinfo_gdrive_backup_log_finish($logId, false, array_merge($job['messages'], array('Adatbázis export sikertelen: ' . $dump['message'])));
				alatinfo_backup_rrmdir((string)$job['tmp']);
				alatinfo_backup_drive_job_clear();
				alatinfo_backup_drive_json_response(array(
					'ok' => false,
					'job_id' => $jobId,
					'messages' => array('Adatbázis export sikertelen: ' . $dump['message']),
					'progress' => $progress,
				));
				return;
			}
			$sqlSize = is_file((string)$job['sql_path']) ? (int)filesize((string)$job['sql_path']) : 0;
			$stepMessages[] = $dump['message'];
			alatinfo_gdrive_backup_log_append($logId, $dump['message']);
			$job['messages'] = array_merge($job['messages'], $stepMessages);
			alatinfo_backup_drive_job_set($job);
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'sql',
				'SQL export kész.',
				24,
				$sqlSize > 0 ? ('Fájlméret: ' . alatinfo_backup_format_bytes($sqlSize)) : ''
			);
			alatinfo_backup_drive_json_response(array(
				'ok' => true,
				'job_id' => $jobId,
				'messages' => $stepMessages,
				'progress' => $progress,
				'done' => false,
			));
			return;
		}

		if ($step === 'zip') {
			$logId = isset($job['log_id']) ? (int) $job['log_id'] : null;
			if (empty($job['include_files'])) {
				$stepMessages[] = 'Fájlok csomagolása kihagyva (nincs kiválasztva).';
				alatinfo_gdrive_backup_log_append($logId, $stepMessages[0]);
				$job['messages'] = array_merge($job['messages'], $stepMessages);
				alatinfo_backup_drive_job_set($job);
				$progress = alatinfo_backup_google_drive_step_progress($jobId, 'zip', 'ZIP kihagyva.', 46);
				alatinfo_backup_drive_json_response(array(
					'ok' => true,
					'job_id' => $jobId,
					'messages' => $stepMessages,
					'progress' => $progress,
					'done' => false,
				));
				return;
			}
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'zip',
				'Projekt fájlok csomagolása (ZIP)…',
				28
			);
			$root = alatinfo_backup_project_root();
			if (!is_dir($root)) {
				alatinfo_gdrive_backup_log_finish($logId, false, array_merge($job['messages'], array('Projekt gyökér nem található.')));
				alatinfo_backup_rrmdir((string)$job['tmp']);
				alatinfo_backup_drive_job_clear();
				alatinfo_backup_drive_json_response(array(
					'ok' => false,
					'job_id' => $jobId,
					'messages' => array('Projekt gyökér nem található.'),
					'progress' => $progress,
				));
				return;
			}
			$minMtime = isset($job['date_from']) && $job['date_from'] !== null ? (int) $job['date_from'] : null;
			alatinfo_backup_drive_session_release();
			$zip = alatinfo_backup_zip_dir(
				$root,
				(string)$job['zip_path'],
				alatinfo_backup_default_exclude_paths(),
				static function (int $count) use ($jobId): void {
					if ($count % 25 !== 0) {
						return;
					}
					$zipPct = min(44, 28 + (int)floor(min(1.0, $count / 800) * 16));
					alatinfo_backup_drive_job_progress_write($jobId, array(
						'step' => 'zip',
						'message' => 'ZIP készítése…',
						'percent' => $zipPct,
						'detail' => $count . ' fájl hozzáadva',
					));
				},
				$minMtime
			);
			if (alatinfo_backup_google_drive_step_maybe_cancelled($job, $jobId)) {
				return;
			}
			if (!$zip['ok']) {
				alatinfo_gdrive_backup_log_append($logId, 'HIBA: ' . $zip['message']);
				alatinfo_gdrive_backup_log_finish($logId, false, array_merge($job['messages'], array($zip['message'])));
				alatinfo_backup_rrmdir((string)$job['tmp']);
				alatinfo_backup_drive_job_clear();
				alatinfo_backup_drive_json_response(array(
					'ok' => false,
					'job_id' => $jobId,
					'messages' => array($zip['message']),
					'progress' => $progress,
				));
				return;
			}
			$zipSize = is_file((string)$job['zip_path']) ? (int)filesize((string)$job['zip_path']) : 0;
			$fileCount = (int)($zip['file_count'] ?? 0);
			$stepMessages[] = $zip['message'];
			alatinfo_gdrive_backup_log_append($logId, $zip['message']);
			if (!empty($zip['skipped'])) {
				$job['zip_skipped'] = true;
			}
			$job['messages'] = array_merge($job['messages'], $stepMessages);
			alatinfo_backup_drive_job_set($job);
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'zip',
				'ZIP kész.',
				46,
				($fileCount > 0 ? ($fileCount . ' fájl, ') : '') . ($zipSize > 0 ? alatinfo_backup_format_bytes($zipSize) : '')
			);
			alatinfo_backup_drive_json_response(array(
				'ok' => true,
				'job_id' => $jobId,
				'messages' => $stepMessages,
				'progress' => $progress,
				'done' => false,
			));
			return;
		}

		$cfg = alatinfo_backup_drive_load_config();
		if ($cfg === null) {
			alatinfo_backup_drive_job_clear();
			alatinfo_backup_drive_json_response(array(
				'ok' => false,
				'messages' => array('Konfiguráció hiányzik.'),
				'progress' => null,
			));
			return;
		}
		$tokenRes = alatinfo_gdrive_access_token_for_backup($cfg);
		if (!$tokenRes['ok'] || $tokenRes['token'] === null) {
			alatinfo_backup_rrmdir((string)$job['tmp']);
			alatinfo_backup_drive_job_clear();
			alatinfo_backup_drive_json_response(array(
				'ok' => false,
				'job_id' => $jobId,
				'messages' => array('Google hitelesítés sikertelen: ' . $tokenRes['error']),
				'progress' => null,
			));
			return;
		}
		$token = $tokenRes['token'];

		if ($step === 'upload_sql') {
			$logId = isset($job['log_id']) ? (int) $job['log_id'] : null;
			if (empty($job['include_db'])) {
				$stepMessages[] = 'SQL feltöltés kihagyva.';
				alatinfo_gdrive_backup_log_append($logId, $stepMessages[0]);
				$job['messages'] = array_merge($job['messages'], $stepMessages);
				alatinfo_backup_drive_job_set($job);
				$progress = alatinfo_backup_google_drive_step_progress($jobId, 'upload_sql', 'SQL feltöltés kihagyva.', 72);
				alatinfo_backup_drive_json_response(array(
					'ok' => true,
					'job_id' => $jobId,
					'messages' => $stepMessages,
					'progress' => $progress,
					'done' => false,
				));
				return;
			}
			$sqlSize = is_file((string)$job['sql_path']) ? (int)filesize((string)$job['sql_path']) : 0;
			$sqlDriveName = 'alatinfo_db_' . (string)$job['stamp'] . '.sql';
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'upload_sql',
				'SQL feltöltés indítása…',
				48,
				$sqlSize > 0 ? alatinfo_backup_format_bytes($sqlSize) : ''
			);
			alatinfo_backup_drive_session_release();
			$r1 = alatinfo_gdrive_upload_resumable(
				$token,
				(string)$job['folder_id'],
				(string)$job['sql_path'],
				$sqlDriveName,
				'application/sql',
				alatinfo_backup_drive_upload_progress_callback($jobId, 'upload_sql', 'SQL', 48, 72)
			);
			if (alatinfo_backup_google_drive_step_maybe_cancelled($job, $jobId)) {
				return;
			}
			$stepMessages[] = $r1['message'];
			alatinfo_gdrive_backup_log_append($logId, $r1['message']);
			if (!$r1['ok']) {
				if (!empty($r1['storage_quota'])) {
					$stepMessages = array_merge($stepMessages, alatinfo_gdrive_storage_quota_hints());
				}
				alatinfo_gdrive_backup_log_finish($logId, false, array_merge($job['messages'], $stepMessages));
				alatinfo_backup_rrmdir((string)$job['tmp']);
				alatinfo_backup_drive_job_clear();
				alatinfo_backup_drive_json_response(array(
					'ok' => false,
					'job_id' => $jobId,
					'messages' => $stepMessages,
					'progress' => $progress,
				));
				return;
			}
			$job['sql_uploaded'] = true;
			$job['sql_drive_name'] = $sqlDriveName;
			$job['messages'] = array_merge($job['messages'], $stepMessages);
			alatinfo_backup_drive_job_set($job);
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'upload_sql',
				'SQL feltöltve.',
				72,
				$sqlDriveName
			);
			alatinfo_backup_drive_json_response(array(
				'ok' => true,
				'job_id' => $jobId,
				'messages' => $stepMessages,
				'progress' => $progress,
				'done' => false,
			));
			return;
		}

		if ($step === 'upload_zip') {
			$logId = isset($job['log_id']) ? (int) $job['log_id'] : null;
			if (empty($job['include_files']) || !empty($job['zip_skipped'])) {
				$msg = empty($job['include_files'])
					? 'ZIP feltöltés kihagyva (nincs kiválasztva).'
					: 'ZIP feltöltés kihagyva (nincs fájl a szűrőnek megfelelően).';
				$stepMessages[] = $msg;
				alatinfo_gdrive_backup_log_append($logId, $msg);
				$job['messages'] = array_merge($job['messages'], $stepMessages);
				alatinfo_backup_drive_job_set($job);
				$progress = alatinfo_backup_google_drive_step_progress($jobId, 'upload_zip', 'ZIP feltöltés kihagyva.', 97);
				alatinfo_backup_drive_json_response(array(
					'ok' => true,
					'job_id' => $jobId,
					'messages' => $stepMessages,
					'progress' => $progress,
					'done' => false,
				));
				return;
			}
			$zipSize = is_file((string)$job['zip_path']) ? (int)filesize((string)$job['zip_path']) : 0;
			$zipDriveName = 'alatinfo_site_' . (string)$job['stamp'] . '.zip';
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'upload_zip',
				'ZIP feltöltés indítása…',
				74,
				$zipSize > 0 ? alatinfo_backup_format_bytes($zipSize) : ''
			);
			alatinfo_backup_drive_session_release();
			$r2 = alatinfo_gdrive_upload_resumable(
				$token,
				(string)$job['folder_id'],
				(string)$job['zip_path'],
				$zipDriveName,
				'application/zip',
				alatinfo_backup_drive_upload_progress_callback($jobId, 'upload_zip', 'ZIP', 74, 97)
			);
			if (alatinfo_backup_google_drive_step_maybe_cancelled($job, $jobId)) {
				return;
			}
			$stepMessages[] = $r2['message'];
			alatinfo_gdrive_backup_log_append($logId, $r2['message']);
			$job['messages'] = array_merge($job['messages'], $stepMessages);
			if (!$r2['ok']) {
				alatinfo_gdrive_backup_log_finish($logId, false, $job['messages']);
				alatinfo_backup_rrmdir((string)$job['tmp']);
				alatinfo_backup_drive_job_clear();
				alatinfo_backup_drive_json_response(array(
					'ok' => false,
					'job_id' => $jobId,
					'messages' => $stepMessages,
					'progress' => $progress,
				));
				return;
			}
			$job['zip_uploaded'] = true;
			$job['zip_drive_name'] = $zipDriveName;
			alatinfo_backup_drive_job_set($job);
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'upload_zip',
				'ZIP feltöltve.',
				97,
				$zipDriveName
			);
			alatinfo_backup_drive_json_response(array(
				'ok' => true,
				'job_id' => $jobId,
				'messages' => $stepMessages,
				'progress' => $progress,
				'done' => false,
			));
			return;
		}

		if ($step === 'cleanup') {
			$logId = isset($job['log_id']) ? (int) $job['log_id'] : null;
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'cleanup',
				'Ideiglenes fájlok törlése…',
				98
			);
			alatinfo_backup_rrmdir((string)$job['tmp']);
			$allMessages = is_array($job['messages']) ? $job['messages'] : array();
			$ok = true;
			foreach ($allMessages as $m) {
				if (stripos((string)$m, 'sikertelen') !== false || stripos((string)$m, 'HIBA') !== false) {
					$ok = false;
					break;
				}
			}
			if ($ok && !empty($job['include_db']) && empty($job['sql_uploaded'])) {
				$ok = false;
				$allMessages[] = 'Az adatbázis export nem került feltöltésre.';
			}
			if ($ok && !empty($job['include_files']) && empty($job['zip_uploaded']) && empty($job['zip_skipped'])) {
				$ok = false;
				$allMessages[] = 'A fájlok ZIP exportja nem került feltöltésre.';
			}
			$finishMsg = $ok ? 'Mentés kész.' : 'Mentés hibával végződött.';
			alatinfo_gdrive_backup_log_append($logId, $finishMsg);
			alatinfo_gdrive_backup_log_finish(
				$logId,
				$ok,
				$allMessages,
				!empty($job['sql_drive_name']) ? (string) $job['sql_drive_name'] : null,
				!empty($job['zip_drive_name']) ? (string) $job['zip_drive_name'] : null
			);
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'cleanup',
				$ok ? 'Mentés kész.' : 'Mentés hibával végződött.',
				100
			);
			alatinfo_backup_drive_job_clear();
			alatinfo_backup_drive_json_response(array(
				'ok' => $ok,
				'job_id' => $jobId,
				'messages' => $allMessages,
				'progress' => $progress,
				'done' => true,
			));
		}
	}
}
