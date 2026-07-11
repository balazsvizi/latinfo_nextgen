<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

hb_require_login();

$propertyId = hb_post_int('property_id');
if ($propertyId <= 0) {
    $propertyId = hb_get_int('property_id');
}
if ($propertyId > 0) {
    $property = HbPropertyRepository::findForSubscriber(hb_get_db(), $propertyId, hb_subscriber_id());
    if ($property !== null) {
        hb_set_current_property_id($propertyId);
    }
}

$return = hb_get_string('return');
if ($return !== '' && str_starts_with($return, '/')) {
    hb_redirect($return);
}

$referer = $_SERVER['HTTP_REFERER'] ?? '';
if ($referer !== '') {
    hb_redirect($referer);
}

hb_redirect(hb_url('index.php'));
