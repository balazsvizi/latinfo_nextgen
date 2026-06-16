<?php
declare(strict_types=1);

/**
 * Google Drive mentés napló (nextgen_gdrive_backup_log).
 */

if (!function_exists('alatinfo_gdrive_backup_log_table_exists')) {
	function alatinfo_gdrive_backup_log_table_exists(): bool
	{
		static $exists = null;
		if ($exists !== null) {
			return $exists;
		}
		try {
			getDb()->query('SELECT 1 FROM nextgen_gdrive_backup_log LIMIT 1');
			$exists = true;
		} catch (Throwable $e) {
			$exists = false;
		}
		return $exists;
	}
}

if (!function_exists('alatinfo_backup_parse_options_from_post')) {
	/**
	 * @return array{
	 *   include_db:bool,
	 *   include_files:bool,
	 *   date_filter:string,
	 *   date_from:?int,
	 *   date_from_label:string,
	 *   error:string
	 * }
	 */
	function alatinfo_backup_parse_options_from_post(array $post): array
	{
		$includeDb = !empty($post['backup_include_db']);
		$includeFiles = !empty($post['backup_include_files']);
		if (!$includeDb && !$includeFiles) {
			return array(
				'include_db' => false,
				'include_files' => false,
				'date_filter' => 'all',
				'date_from' => null,
				'date_from_label' => 'Mind',
				'error' => 'Legalább az adatbázist vagy a fájlokat válaszd ki.',
			);
		}
		$filter = trim((string) ($post['backup_date_filter'] ?? 'all'));
		$allowed = array('all', '1month', '1week', '1day', 'custom');
		if (!in_array($filter, $allowed, true)) {
			$filter = 'all';
		}
		$customDate = trim((string) ($post['backup_date_custom'] ?? ''));
		$dateFrom = alatinfo_backup_resolve_date_from($filter, $customDate);
		if ($filter === 'custom' && $dateFrom === null) {
			return array(
				'include_db' => $includeDb,
				'include_files' => $includeFiles,
				'date_filter' => $filter,
				'date_from' => null,
				'date_from_label' => 'Egyedi',
				'error' => 'Érvényes egyedi dátumot adj meg (ÉÉÉÉ-HH-NN).',
			);
		}
		return array(
			'include_db' => $includeDb,
			'include_files' => $includeFiles,
			'date_filter' => $filter,
			'date_from' => $dateFrom,
			'date_from_label' => alatinfo_backup_date_filter_label($filter, $customDate, $dateFrom),
			'error' => '',
		);
	}
}

if (!function_exists('alatinfo_backup_resolve_date_from')) {
	function alatinfo_backup_resolve_date_from(string $filter, string $customDate): ?int
	{
		$tz = date_default_timezone_get();
		if ($tz === '' || $tz === 'UTC') {
			@date_default_timezone_set('Europe/Budapest');
		}
		return match ($filter) {
			'all' => null,
			'1day' => strtotime('today -1 day'),
			'1week' => strtotime('today -7 days'),
			'1month' => strtotime('today -1 month'),
			'custom' => alatinfo_backup_parse_custom_date($customDate),
			default => null,
		};
	}
}

if (!function_exists('alatinfo_backup_parse_custom_date')) {
	function alatinfo_backup_parse_custom_date(string $date): ?int
	{
		$date = trim($date);
		if ($date === '') {
			return null;
		}
		$dt = DateTimeImmutable::createFromFormat('Y-m-d', $date);
		if ($dt === false) {
			return null;
		}
		$ts = $dt->setTime(0, 0, 0)->getTimestamp();
		return $ts > 0 ? $ts : null;
	}
}

if (!function_exists('alatinfo_backup_date_filter_label')) {
	function alatinfo_backup_date_filter_label(string $filter, string $customDate, ?int $dateFrom): string
	{
		return match ($filter) {
			'all' => 'Mind',
			'1day' => 'Utolsó 1 nap',
			'1week' => 'Utolsó 1 hét',
			'1month' => 'Utolsó 1 hónap',
			'custom' => $dateFrom !== null
				? ('Ettől: ' . date('Y-m-d', $dateFrom))
				: ('Egyedi: ' . ($customDate !== '' ? $customDate : '—')),
			default => 'Mind',
		};
	}
}

if (!function_exists('alatinfo_gdrive_backup_log_create')) {
	function alatinfo_gdrive_backup_log_create(array $options, string $googleEmail = ''): ?int
	{
		if (!alatinfo_gdrive_backup_log_table_exists()) {
			return null;
		}
		$adminId = (int) ($_SESSION['admin_id'] ?? 0);
		$dateFromSql = null;
		if ($options['date_from'] !== null) {
			$dateFromSql = date('Y-m-d', (int) $options['date_from']);
		}
		try {
			$db = getDb();
			$stmt = $db->prepare(
				'INSERT INTO nextgen_gdrive_backup_log
				(admin_id, google_email, status, include_db, include_files, date_filter, date_from, log_text)
				VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
			);
			$stmt->execute([
				$adminId > 0 ? $adminId : null,
				$googleEmail !== '' ? $googleEmail : null,
				'running',
				$options['include_db'] ? 1 : 0,
				$options['include_files'] ? 1 : 0,
				$options['date_filter'],
				$dateFromSql,
				'Mentés indítása…',
			]);
			return (int) $db->lastInsertId();
		} catch (Throwable $e) {
			return null;
		}
	}
}

if (!function_exists('alatinfo_gdrive_backup_log_append')) {
	function alatinfo_gdrive_backup_log_append(?int $logId, string $line): void
	{
		if ($logId === null || $logId <= 0 || !alatinfo_gdrive_backup_log_table_exists()) {
			return;
		}
		try {
			$db = getDb();
			$stmt = $db->prepare('SELECT log_text FROM nextgen_gdrive_backup_log WHERE id = ? LIMIT 1');
			$stmt->execute([$logId]);
			$row = $stmt->fetch(PDO::FETCH_ASSOC);
			$prev = is_array($row) ? trim((string) ($row['log_text'] ?? '')) : '';
			$new = $prev === '' ? $line : ($prev . "\n" . $line);
			$db->prepare('UPDATE nextgen_gdrive_backup_log SET log_text = ? WHERE id = ?')->execute([$new, $logId]);
		} catch (Throwable $e) {
			// ignore
		}
	}
}

if (!function_exists('alatinfo_gdrive_backup_log_finish')) {
	/**
	 * @param list<string> $messages
	 */
	function alatinfo_gdrive_backup_log_finish(
		?int $logId,
		bool $ok,
		array $messages,
		?string $sqlDriveName = null,
		?string $zipDriveName = null
	): void {
		if ($logId === null || $logId <= 0 || !alatinfo_gdrive_backup_log_table_exists()) {
			return;
		}
		$logText = implode("\n", $messages);
		try {
			$db = getDb();
			$stmt = $db->prepare(
				'UPDATE nextgen_gdrive_backup_log SET
				status = ?, log_text = ?, sql_drive_name = ?, zip_drive_name = ?, finished_at = NOW()
				WHERE id = ?'
			);
			$stmt->execute([
				$ok ? 'ok' : 'error',
				$logText !== '' ? $logText : null,
				$sqlDriveName,
				$zipDriveName,
				$logId,
			]);
		} catch (Throwable $e) {
			return;
		}
		if (function_exists('rendszer_log')) {
			$summary = ($ok ? 'Sikeres' : 'Sikertelen') . ' Drive mentés';
			if ($sqlDriveName !== null) {
				$summary .= ' SQL: ' . $sqlDriveName;
			}
			if ($zipDriveName !== null) {
				$summary .= ' ZIP: ' . $zipDriveName;
			}
			rendszer_log('gdrive_backup', $logId, $ok ? 'Mentés kész' : 'Mentés hiba', $summary);
		}
	}
}

if (!function_exists('alatinfo_gdrive_backup_log_list')) {
	/**
	 * @return list<array<string,mixed>>
	 */
	function alatinfo_gdrive_backup_log_list(int $limit = 25): array
	{
		if (!alatinfo_gdrive_backup_log_table_exists()) {
			return array();
		}
		$limit = max(1, min(100, $limit));
		try {
			$db = getDb();
			$stmt = $db->query(
				'SELECT l.*, a.név AS admin_nev
				FROM nextgen_gdrive_backup_log l
				LEFT JOIN nextgen_admins a ON a.id = l.admin_id
				ORDER BY l.started_at DESC
				LIMIT ' . $limit
			);
			$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
			return is_array($rows) ? $rows : array();
		} catch (Throwable $e) {
			return array();
		}
	}
}

if (!function_exists('alatinfo_gdrive_backup_log_format_targets')) {
	function alatinfo_gdrive_backup_log_format_targets(array $row): string
	{
		$parts = array();
		if (!empty($row['include_db'])) {
			$parts[] = 'DB';
		}
		if (!empty($row['include_files'])) {
			$parts[] = 'Fájlok';
		}
		return $parts !== array() ? implode(' + ', $parts) : '—';
	}
}
