<?php
declare(strict_types=1);

/**
 * CLI telepítő: php hostbase/install_cli.php
 */

$_SERVER['REQUEST_METHOD'] = 'POST';

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/lib/SchemaInstaller.php';

try {
    $info = HbSchemaInstaller::install(hb_get_db());
    echo "HostBase telepítés kész.\n";
    foreach ($info as $line) {
        echo $line . "\n";
    }
    exit(0);
} catch (Throwable $ex) {
    fwrite(STDERR, 'Hiba: ' . $ex->getMessage() . "\n");
    exit(1);
}
