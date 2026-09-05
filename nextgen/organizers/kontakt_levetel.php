<?php
require_once __DIR__ . '/../../nextgen/core/database.php';
require_once __DIR__ . '/../../nextgen/includes/auth.php';
require_once __DIR__ . '/../../nextgen/includes/functions.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Érvénytelen kérés.');
    redirect(nextgen_url('organizers/'));
}

$szervezo_id = (int) ($_POST['szervezo_id'] ?? 0);
$kontakt_id = (int) ($_POST['kontakt_id'] ?? 0);
$redirectTo = nextgen_url('organizers/megtekint.php?id=') . $szervezo_id . '#kontaktok';
csrf_require('organizer_kontakt_levetel', '_csrf', $szervezo_id > 0 ? $redirectTo : nextgen_url('organizers/'));

if (!$szervezo_id || !$kontakt_id) {
    flash('error', 'Hiányzó adat.');
    redirect(nextgen_url('organizers/'));
}
$db = getDb();
$db->prepare('DELETE FROM finance_organizer_contacts WHERE szervező_id = ? AND kontakt_id = ?')->execute([$szervezo_id, $kontakt_id]);
rendszer_log('szervező_kontakt', null, 'Kapcsolat törölve', "szervező_id=$szervezo_id, kontakt_id=$kontakt_id");
flash('success', 'Kontakt lecsatolva.');
redirect($redirectTo);
