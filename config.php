<?php
// config.php - Laad .env en definieer constants
session_start();

require_once 'vendor/autoload.php'; // Voor phpdotenv

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Database
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);
define('DB_NAME', $_ENV['DB_NAME']);

// App
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_DEBUG', $_ENV['APP_DEBUG'] === 'true');
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');

// Mappen
define('LOGO_DIR', __DIR__ . '/' . $_ENV['LOGO_DIR']);
define('PDF_DIR', __DIR__ . '/' . $_ENV['PDF_DIR']);
define('CONTRACT_DIR', __DIR__ . '/' . $_ENV['CONTRACT_DIR']);
define('SEPA_DIR', __DIR__ . '/' . $_ENV['SEPA_DIR']);

// SMTP
define('SMTP_HOST', $_ENV['SMTP_HOST']);
define('SMTP_PORT', (int)$_ENV['SMTP_PORT']);
define('SMTP_USER', $_ENV['SMTP_USER']);
define('SMTP_PASS', $_ENV['SMTP_PASS']);
define('SMTP_FROM', $_ENV['SMTP_FROM']);
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'Loonkosten.nl');

// Debug modus
if (APP_DEBUG) {
	error_reporting(E_ALL);
	ini_set('display_errors', 1);
} else {
	error_reporting(0);
	ini_set('display_errors', 0);
}

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
			if (APP_DEBUG) {
				die('Database verbinding mislukt: ' . $conn->connect_error);
			} else {
				die('Er is een fout opgetreden. Probeer later opnieuw.');
			}
		}
		$conn->set_charset('utf8mb4');
	}
	return $conn;
}
?>