<?php
declare(strict_types=1);

require_once __DIR__ . '/google_drive_backup.php';

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
			$job = array(
				'job_id' => $jobId,
				'tmp' => $tmp,
				'folder_id' => $cfg['folder_id'],
				'sql_path' => $tmp . DIRECTORY_SEPARATOR . 'database.sql',
				'zip_path' => $tmp . DIRECTORY_SEPARATOR . 'site.zip',
				'stamp' => $stamp,
				'messages' => array('Hitelesítés rendben (' . $tokenRes['auth_label'] . ').'),
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

		if ($step === 'sql') {
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'sql',
				'Adatbázis export (SQL)…',
				12
			);
			alatinfo_backup_drive_session_release();
			$dump = alatinfo_backup_export_sql($db, (string)$job['sql_path']);
			if (!$dump['ok']) {
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
			$progress = alatinfo_backup_google_drive_step_progress(
				$jobId,
				'zip',
				'Projekt fájlok csomagolása (ZIP)…',
				28
			);
			$root = alatinfo_backup_project_root();
			if (!is_dir($root)) {
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
				}
			);
			if (!$zip['ok']) {
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
			$stepMessages[] = $r1['message'];
			if (!$r1['ok']) {
				if (!empty($r1['storage_quota'])) {
					$stepMessages = array_merge($stepMessages, alatinfo_gdrive_storage_quota_hints());
				}
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
			$stepMessages[] = $r2['message'];
			$job['messages'] = array_merge($job['messages'], $stepMessages);
			alatinfo_backup_drive_job_set($job);
			if (!$r2['ok']) {
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
