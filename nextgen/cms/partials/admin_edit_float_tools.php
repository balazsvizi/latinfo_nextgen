<?php
declare(strict_types=1);

/**
 * Lebegő mini eszköztár — CMS cikk szerkesztő (bal felső).
 *
 * @var string $cmsEditCopyUrl
 * @var string|null $cmsEditPublicUrl
 */
$cmsEditPublicUrl = $cmsEditPublicUrl ?? null;

$adminFloatTools = [
    [
        'submit_form' => 'cms-edit-form',
        'title' => 'Mentés',
        'aria' => 'Mentés',
        'icon' => 'save',
        'name' => 'form_action',
        'value' => 'save',
    ],
];
if ($cmsEditPublicUrl !== null && $cmsEditPublicUrl !== '') {
    $adminFloatTools[] = [
        'href' => $cmsEditPublicUrl,
        'title' => 'Nyilvános megtekintés',
        'aria' => 'Nyilvános megtekintés',
        'icon' => 'eye',
        'target' => '_blank',
    ];
}
$adminFloatTools[] = [
    'href' => $cmsEditCopyUrl,
    'title' => 'Cikk másolása',
    'aria' => 'Cikk másolása',
    'icon' => 'copy',
];
$adminFloatTools[] = [
    'href' => cms_url('posts.php'),
    'title' => 'Vissza a listához',
    'aria' => 'Vissza a listához',
    'icon' => 'list',
];

$adminFloatToolsRequireLogin = false;
require dirname(__DIR__, 2) . '/events/partials/admin_float_tools.php';
