<?php
// config.php - Centrale configuratie
session_start();

// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'loonstroken_db');

// Mappen
define('LOGO_DIR', __DIR__ . '/logos/');
define('PDF_DIR', __DIR__ . '/payslips/');
define('CONTRACT_DIR', __DIR__ . '/contracts/');
define('SEPA_DIR', __DIR__ . '/sepa/');

// SMTP voor emails/2FA
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USER', 'jouwemail@gmail.com');
define('SMTP_PASS', 'app-wachtwoord');
define('SMTP_FROM', 'noreply@loonkosten.nl');

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
define('CSRF_TOKEN', $_SESSION['csrf_token']);

function getDb() {
    static $conn = null;
    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('Database verbinding mislukt');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}
?>
