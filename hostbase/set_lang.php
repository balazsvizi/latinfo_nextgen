<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

hb_require_login();

$locale = hb_get_string('lang');
$return = hb_get_string('return');

if ($locale !== '') {
    hb_set_locale($locale);
    HbActivityLog::log(
        hb_get_db(),
        hb_subscriber_id(),
        hb_user_id(),
        'locale_change',
        null,
        null,
        $locale
    );
}

if ($return !== '' && str_starts_with($return, '/')) {
    hb_redirect($return);
}

hb_redirect(hb_url('index.php'));
