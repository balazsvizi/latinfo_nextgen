<?php
/**
 * SMTP e-mail küldés – központi függvény és titkosítási segédek
 * PHPMailer használata (composer require phpmailer/phpmailer)
 */

if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/../../nextgen/core/config.php';
}
require_once __DIR__ . '/../../nextgen/core/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Jelszó titkosítás (SMTP jelszó tárolásához)
 */
function email_jelszo_titkosit(string $jelszo): string {
    $key = defined('EMAIL_ENCRYPT_KEY') ? EMAIL_ENCRYPT_KEY : '';
    if (strlen($key) < 32) {
        return base64_encode($jelszo); // gyenge fallback – állíts be EMAIL_ENCRYPT_KEY-et
    }
    $key = hash('sha256', $key, true);
    $iv = random_bytes(16);
    $enc = openssl_encrypt($jelszo, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return 'v2.' . base64_encode($iv . $enc);
}

/**
 * Jelszó visszafejtés
 */
function email_jelszo_visszafejt(string $titkosított): string {
    if (strpos($titkosított, 'v2.') === 0) {
        $raw = base64_decode(substr($titkosított, 3), true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }
        $key = defined('EMAIL_ENCRYPT_KEY') ? EMAIL_ENCRYPT_KEY : '';
        if (strlen($key) < 32) {
            return '';
        }
        $key = hash('sha256', $key, true);
        $iv = substr($raw, 0, 16);
        $enc = substr($raw, 16);
        $dec = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $dec !== false ? $dec : '';
    }
    return (string) base64_decode($titkosított, true) ?: '';
}

/**
 * E-mail küldés SMTP-val a megadott (vagy alapértelmezett) fiókkal.
 *
 * @param string|string[] $cimzett   E-mail cím vagy címek tömbje
 * @param string          $targy    Tárgy
 * @param string          $szoveg   Szöveg vagy HTML tartalom
 * @param array           $opciok   reply_to, cc, bcc, html (bool, default true), config_id (int, null = alapértelmezett), attachments (array)
 * @return array { ok: bool, hiba: string }
 */
function email_kuld($cimzett, string $targy, string $szoveg, array $opciok = []): array {
    $config_id = $opciok['config_id'] ?? null;
    $html = $opciok['html'] ?? true;
    $reply_to = $opciok['reply_to'] ?? null;
    $cc = $opciok['cc'] ?? [];
    $bcc = $opciok['bcc'] ?? [];
    $attachments = $opciok['attachments'] ?? [];

    $db = getDb();
    $sql = 'SELECT id, név, host, port, titkosítás, felhasználó, jelszó_titkosított, from_email, from_name
            FROM finance_email_accounts WHERE 1=1';
    $params = [];
    if ($config_id !== null) {
        $sql .= ' AND id = ?';
        $params[] = (int) $config_id;
    } else {
        $sql .= ' AND alapértelmezett = 1';
    }
    $sql .= ' LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $cfg = $stmt->fetch();
    if (!$cfg) {
        return ['ok' => false, 'hiba' => 'Nincs SMTP konfiguráció kiválasztva vagy alapértelmezett fiók.'];
    }

    $jelszo = '';
    if (!empty($cfg['jelszó_titkosított'])) {
        $jelszo = email_jelszo_visszafejt($cfg['jelszó_titkosított']);
    }

    $phpmailer_loaded = false;
    if (is_file(BASE_PATH . '/nextgen/vendor/autoload.php')) {
        require_once BASE_PATH . '/nextgen/vendor/autoload.php';
        $phpmailer_loaded = true;
    }
    $pmDir = __DIR__ . '/phpmailer';
    if (!$phpmailer_loaded && is_file($pmDir . '/PHPMailer.php') && is_file($pmDir . '/SMTP.php') && is_file($pmDir . '/Exception.php')) {
        require_once $pmDir . '/Exception.php';
        require_once $pmDir . '/SMTP.php';
        require_once $pmDir . '/PHPMailer.php';
        $phpmailer_loaded = true;
    }
    if (!$phpmailer_loaded) {
        return ['ok' => false, 'hiba' => 'PHPMailer nincs telepítve. Nyisd meg egyszer böngészőből: ' . (isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] . '/' : '') . 'install_phpmailer.php – vagy másold az includes/phpmailer/ mappába a PHPMailer Exception.php, SMTP.php, PHPMailer.php fájlokat (GitHub: PHPMailer/PHPMailer, src/ mappa).'];
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
        $mail->Encoding = 'base64';
        $mail->isSMTP();
        $mail->Host = $cfg['host'];
        $mail->Port = (int) $cfg['port'];
        $mail->Timeout = 15;
        if (property_exists($mail, 'Timelimit')) {
            $mail->Timelimit = 15;
        }
        $mail->SMTPAuth = !empty($cfg['felhasználó']);
        if ($mail->SMTPAuth) {
            $mail->Username = $cfg['felhasználó'];
            $mail->Password = $jelszo;
        }
        $enc = strtolower((string) $cfg['titkosítás']);
        if ($enc === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($enc === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        }
        $mail->setFrom($cfg['from_email'], $cfg['from_name'] ?: '');
        $mail->Subject = $targy;

        if ($html) {
            $mail->isHTML(true);
            $mail->Body = $szoveg;
            $mail->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $szoveg));
        } else {
            $mail->isHTML(false);
            $mail->Body = $szoveg;
        }

        // Spam/elérhetőség barát beállítások
        $domain = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[:\\/].*$/', '', $_SERVER['HTTP_HOST']) : 'localhost';
        $mail->MessageID = sprintf('<%s.%s@%s>', date('YmdHis'), bin2hex(random_bytes(8)), $domain);
        $mail->XMailer = ' ';
        $mail->addCustomHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');

        $cimzettek = is_array($cimzett) ? $cimzett : [$cimzett];
        foreach ($cimzettek as $c) {
            $c = trim((string) $c);
            if ($c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($c);
            }
        }
        if (count($mail->getToAddresses()) === 0) {
            return ['ok' => false, 'hiba' => 'Nincs érvényes címzett.'];
        }
        if ($reply_to && filter_var($reply_to, FILTER_VALIDATE_EMAIL)) {
            $mail->addReplyTo($reply_to);
        }
        foreach ((array) $cc as $c) {
            $c = trim((string) $c);
            if ($c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($c);
            }
        }
        foreach ((array) $bcc as $c) {
            $c = trim((string) $c);
            if ($c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)) {
                $mail->addBCC($c);
            }
        }
        foreach ((array) $attachments as $att) {
            $path = is_array($att) ? ($att['path'] ?? '') : (string) $att;
            $name = is_array($att) ? ($att['name'] ?? '') : '';
            if ($path && is_file($path)) {
                $mail->addAttachment($path, $name ?: '');
            }
        }

        @set_time_limit(25);
        try {
            $smtp = $mail->getSMTPInstance();
            $smtp->Timeout = 10;
            $smtp->Timelimit = 10;
        } catch (\Throwable $e) {
            // SMTP instance might not exist yet
        }
        $mail->send();
        return ['ok' => true, 'hiba' => ''];
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        return ['ok' => false, 'hiba' => $mail->ErrorInfo ?: $e->getMessage()];
    }
}
