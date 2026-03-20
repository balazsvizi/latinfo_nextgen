<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$szervezo_id = (int)($_GET['szervezo_id'] ?? 0);
$kontakt_id = (int)($_GET['kontakt_id'] ?? 0);
if (!$szervezo_id || !$kontakt_id) {
    flash('error', 'Hiányzó adat.');
    redirect(BASE_URL . '/organizers/');
}
$db = getDb();
$db->prepare('DELETE FROM szervező_kontakt WHERE szervező_id = ? AND kontakt_id = ?')->execute([$szervezo_id, $kontakt_id]);
rendszer_log('szervező_kontakt', null, 'Kapcsolat törölve', "szervező_id=$szervezo_id, kontakt_id=$kontakt_id");
flash('success', 'Kontakt lecsatolva.');
redirect(BASE_URL . '/organizers/megtekint.php?id=' . $szervezo_id . '#kontaktok');
