<?php
declare(strict_types=1);

// Ideiglenes smoke teszt: partner blokkok szolgáltatás rétege SQLite-on.
define('BASE_PATH', __DIR__);

function h(?string $s): string {
    return $s === null ? '' : htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/nextgen/events/lib/partner_blocks.php';

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('
    CREATE TABLE events_partner_blocks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        block_type TEXT NOT NULL DEFAULT "partner",
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_visible INTEGER NOT NULL DEFAULT 1,
        title TEXT NOT NULL DEFAULT "",
        title_en TEXT NOT NULL DEFAULT "",
        heading_level INTEGER NOT NULL DEFAULT 2,
        link_url TEXT NOT NULL DEFAULT "",
        logo_url TEXT NOT NULL DEFAULT "",
        body TEXT NOT NULL DEFAULT "",
        body_en TEXT NOT NULL DEFAULT "",
        note_before TEXT NOT NULL DEFAULT "",
        note_before_en TEXT NOT NULL DEFAULT "",
        note_after TEXT NOT NULL DEFAULT "",
        note_after_en TEXT NOT NULL DEFAULT "",
        created TEXT,
        modified TEXT
    )
');

$fail = 0;
$check = static function (string $label, bool $ok, string $extra = '') use (&$fail): void {
    if (!$ok) {
        $fail++;
    }
    echo ($ok ? '[OK]   ' : '[FAIL] '), $label, ($extra !== '' ? ' -> ' . $extra : ''), PHP_EOL;
};

$headingId = events_partner_blocks_create($db, 'heading');
$partnerId = events_partner_blocks_create($db, 'partner');
$htmlId = events_partner_blocks_create($db, 'html');
$check('3 blokk letrejott', count(events_partner_blocks_all($db)) === 3);
$check('tipus normalizalas', events_partner_blocks_normalize_type('hacked') === 'partner');

events_partner_blocks_save($db, $headingId, [
    'is_visible' => true,
    'title' => 'Kiemelt partnerek',
    'title_en' => '',
    'heading_level' => 3,
]);
events_partner_blocks_save($db, $partnerId, [
    'is_visible' => true,
    'title' => 'Salsa Klub',
    'title_en' => 'Salsa Club',
    'link_url' => 'www.salsaklub.hu',
    'logo_url' => '/nextgen/events/partnerlogos/salsa.png',
    'note_before' => '<p onclick="alert(1)">Elotte <script>bad()</script><b>fontos</b></p>',
    'note_after' => '<p>Utana</p>',
]);
events_partner_blocks_save($db, $htmlId, [
    'is_visible' => false,
    'body' => '<p>Magyar HTML</p>',
    'body_en' => '<p>English HTML</p>',
]);

$rows = events_partner_blocks_all($db);
$byId = [];
foreach ($rows as $r) {
    $byId[(int) $r['id']] = $r;
}

$check('www link https-re normalizalva', $byId[$partnerId]['link_url'] === 'https://www.salsaklub.hu', (string) $byId[$partnerId]['link_url']);
$check('relativ logo path megmaradt', $byId[$partnerId]['logo_url'] === '/nextgen/events/partnerlogos/salsa.png');
$check('script es onclick kiszurve', !str_contains((string) $byId[$partnerId]['note_before'], 'script') && !str_contains((string) $byId[$partnerId]['note_before'], 'onclick'), (string) $byId[$partnerId]['note_before']);
$check('engedelyezett tag megmaradt', str_contains((string) $byId[$partnerId]['note_before'], '<b>fontos</b>'));
$check('heading szint mentve', (int) $byId[$headingId]['heading_level'] === 3);
$check('rejtett blokk nem latszik publikusan', count(events_partner_blocks_all($db, true)) === 2);

// EN fallback
$huPartner = events_partner_block_localized($byId[$partnerId], 'hu');
$enPartner = events_partner_block_localized($byId[$partnerId], 'en');
$enHeading = events_partner_block_localized($byId[$headingId], 'en');
$enHtml = events_partner_block_localized($byId[$htmlId], 'en');
$check('HU nev', $huPartner['title'] === 'Salsa Klub');
$check('EN nev', $enPartner['title'] === 'Salsa Club');
$check('EN fallback ures EN cimnel', $enHeading['title'] === 'Kiemelt partnerek');
$check('EN body sajat tartalom', $enHtml['body'] === '<p>English HTML</p>');

// Sorrend: heading, partner, html
$ids = array_map(static fn(array $r): int => (int) $r['id'], events_partner_blocks_all($db));
$check('kezdo sorrend', $ids === [$headingId, $partnerId, $htmlId], implode(',', $ids));
$check('html felmozgatas sikeres', events_partner_blocks_move($db, $htmlId, -1));
$ids = array_map(static fn(array $r): int => (int) $r['id'], events_partner_blocks_all($db));
$check('sorrend csere utan', $ids === [$headingId, $htmlId, $partnerId], implode(',', $ids));
$check('elso blokk nem mozgathato feljebb', events_partner_blocks_move($db, $headingId, -1) === false);
$check('utolso blokk nem mozgathato lejjebb', events_partner_blocks_move($db, $partnerId, 1) === false);

// Validacio
try {
    events_partner_blocks_save($db, $partnerId, ['title' => '', 'is_visible' => true]);
    $check('ures partner nev elutasitva', false);
} catch (InvalidArgumentException $e) {
    $check('ures partner nev elutasitva', true, $e->getMessage());
}
try {
    events_partner_blocks_save($db, $partnerId, ['title' => 'X', 'link_url' => 'javascript:alert(1)', 'is_visible' => true]);
    $check('javascript: link elutasitva', false);
} catch (InvalidArgumentException $e) {
    $check('javascript: link elutasitva', true, $e->getMessage());
}
try {
    events_partner_blocks_save($db, $htmlId, ['body' => '', 'body_en' => '', 'is_visible' => true]);
    $check('ures HTML blokk elutasitva', false);
} catch (InvalidArgumentException $e) {
    $check('ures HTML blokk elutasitva', true, $e->getMessage());
}

events_partner_blocks_delete($db, $htmlId);
$check('torles', count(events_partner_blocks_all($db)) === 2);

echo PHP_EOL, ($fail === 0 ? 'MIND OK' : $fail . ' HIBA'), PHP_EOL;
exit($fail === 0 ? 0 : 1);
