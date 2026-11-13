<?php
declare(strict_types=1);

/**
 * Tánciskola címkék. Nyilvánosan megjelennek, a név kereshető.
 *
 * @var list<array{id:int,name:string}> $schoolTagCatalog
 * @var list<int> $schoolTagIds
 */
$schoolTagCatalog = $schoolTagCatalog ?? [];
$schoolTagIds = $schoolTagIds ?? [];

$wpTokenId = 'school-tags';
$wpTokenLabel = 'Címkék';
$wpTokenFieldName = 'tag_ids[]';
$wpTokenPlaceholder = 'Címke hozzáadása…';
$wpTokenHelp = 'Nyilvánosan is megjelenik, és a tánciskolák keresője a címke nevére is rátalál. Pl. Angolul oktatnak.';
$wpTokenAll = $schoolTagCatalog;
$wpTokenSelected = $schoolTagIds;
$wpTokenAllowCreate = true;
$wpTokenEntityType = 'dance_school_tag';
$wpTokenShowPopular = true;
$wpTokenManageUrl = null;
require __DIR__ . '/wp_token_field.php';
