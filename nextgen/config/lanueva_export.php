<?php
declare(strict_types=1);
/**
 * LaNueva – visszajelzések / értesítések export (Excel által megnyitható .xls, SpreadsheetML).
 */
require_once __DIR__ . '/../init.php';
requireLogin();

require_once __DIR__ . '/../includes/landingpage_table.php';

$db = getDb();
ensure_landingpage_table($db);

$tipus = $_GET['tipus'] ?? '';
if (!in_array($tipus, ['', 'visszajelzes', 'ertesites'], true)) {
    $tipus = '';
}

$where_sql = '';
$params = [];
if ($tipus === 'visszajelzes') {
    $where_sql = 'WHERE (email IS NULL OR email = \'\')';
} elseif ($tipus === 'ertesites') {
    $where_sql = 'WHERE email IS NOT NULL AND email != \'\'';
}

$stmt = $db->prepare("
    SELECT id, ilyen_legyen, ilyen_ne_legyen, email, ip, user_agent, létrehozva
    FROM landingpage
    $where_sql
    ORDER BY létrehozva DESC
");
$stmt->execute($params);
$sorok = $stmt->fetchAll(PDO::FETCH_ASSOC);

function landing_export_tipus_cimke(array $r): string {
    if (isset($r['email']) && trim((string) $r['email']) !== '') {
        return 'Értesítés (e-mail)';
    }
    return 'Visszajelzés';
}

function landing_export_xml_cell(string $value): string {
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    return '<Cell><Data ss:Type="String">' . htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8') . '</Data></Cell>';
}

$suffix = $tipus === '' ? 'mind' : $tipus;
$filename = 'lanueva-' . $suffix . '-' . date('Y-m-d') . '.xls';
$filenameSafe = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $filename) ?: 'export.xls';

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filenameSafe . '"');
header('X-Content-Type-Options: nosniff');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:html="http://www.w3.org/TR/REC-html40">
<Worksheet ss:Name="LaNueva">
<Table>
<Row>
<?php
$headers = ['ID', 'Időbélyeg', 'Típus', 'Ilyen legyen', 'Ilyen ne legyen', 'E-mail (értesítés)', 'IP', 'User-Agent'];
foreach ($headers as $h) {
    echo landing_export_xml_cell($h);
}
echo "</Row>\n";

foreach ($sorok as $r) {
    echo '<Row>';
    echo landing_export_xml_cell((string) ((int) ($r['id'] ?? 0)));
    echo landing_export_xml_cell((string) ($r['létrehozva'] ?? ''));
    echo landing_export_xml_cell(landing_export_tipus_cimke($r));
    echo landing_export_xml_cell((string) ($r['ilyen_legyen'] ?? ''));
    echo landing_export_xml_cell((string) ($r['ilyen_ne_legyen'] ?? ''));
    echo landing_export_xml_cell((string) ($r['email'] ?? ''));
    echo landing_export_xml_cell((string) ($r['ip'] ?? ''));
    echo landing_export_xml_cell((string) ($r['user_agent'] ?? ''));
    echo "</Row>\n";
}
?>
</Table>
</Worksheet>
</Workbook>
<?php
exit;
