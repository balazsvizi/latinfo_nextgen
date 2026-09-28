<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/init.php';
require_once dirname(__DIR__, 2) . '/lib/user/users.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('error', 'Érvénytelen kérés.');
    redirect(nextgen_url('admin/users/'));
}
csrf_require('admin_users_toggle', '_csrf', nextgen_url('admin/users/'));

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('error', 'Hiányzó azonosító.');
    redirect(nextgen_url('admin/users/'));
}

$db = getDb();
latinfo_users_ensure_schema($db);
if (latinfo_user_set_active($db, $id, false)) {
    flash('success', 'Felhasználó letiltva.');
} else {
    flash('error', 'A letiltás sikertelen.');
}
redirect(nextgen_url('admin/users/'));
