<?php
// backend/auth.php - Authenticatie (login, registratie, 2FA)
require_once __DIR__ . '/../config.php';
require_once 'api_db.php';

$action = $_POST['action'] ?? '';

if ($action === 'register_bedrijf') {
	$naam = validate_input($_POST['naam']);
	$adres = validate_input($_POST['adres']);
	$loonheffingsnummer = validate_input($_POST['loonheffingsnummer']);
	$email = strtolower(validate_input($_POST['email']));
	$password = password_hash($_POST['password'], PASSWORD_DEFAULT);
	$land = $_POST['land'] ?? 'NL';

	// Check of email al bestaat
	if (db_getRow("SELECT id FROM bedrijven WHERE email = ?", [$email], 's')) {
		json_response([], false, 'Email bestaat al');
	}

	$result = db_execute(
		"INSERT INTO bedrijven (naam, adres, loonheffingsnummer, email, password, land) VALUES (?, ?, ?, ?, ?, ?)",
		[$naam, $adres, $loonheffingsnummer, $email, $password, $land],
		'ssssss'
	);

	if ($result['success']) {
		json_response(['bedrijf_id' => getLastInsertId()], true, 'Bedrijf succesvol geregistreerd');
	} else {
		json_response([], false, 'Registratie mislukt');
	}
}

if ($action === 'login_werknemer') {
	$email = strtolower(validate_input($_POST['email']));
	$password = $_POST['password'];

	$user = db_getRow("SELECT * FROM werknemers WHERE email = ?", [$email], 's');

	if ($user && password_verify($password, $user['password'])) {
		$ip = $_SERVER['REMOTE_ADDR'];
		if ($ip !== $user['last_ip']) {
			$code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
			$expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));
			db_execute("UPDATE werknemers SET 2fa_code = ?, 2fa_expiry = ?, last_ip = ? WHERE id = ?", [$code, $expiry, $ip, $user['id']], 'sssi');
			sendEmail($email, '2FA Code - Loonkosten.nl', "Je verificatiecode is: <strong>$code</strong><br>Geldig voor 5 minuten.");
			json_response(['needs_2fa' => true], true, '2FA code verzonden');
		} else {
			$_SESSION['user_id'] = $user['id'];
			db_execute("UPDATE werknemers SET last_ip = ? WHERE id = ?", [$ip, $user['id']], 'si');
			json_response(['user_id' => $user['id']], true, 'Login succesvol');
		}
	} else {
		json_response([], false, 'Ongeldige email of wachtwoord');
	}
}

if ($action === 'verify_2fa') {
	$code = $_POST['code'];
	$user_id = $_SESSION['2fa_pending'] ?? 0;

	$user = db_getRow("SELECT id FROM werknemers WHERE id = ? AND 2fa_code = ? AND 2fa_expiry > NOW()", [$user_id, $code], 'is');

	if ($user) {
		$ip = $_SERVER['REMOTE_ADDR'];
		db_execute("UPDATE werknemers SET last_ip = ?, 2fa_code = NULL, 2fa_expiry = NULL WHERE id = ?", [$ip, $user_id], 'si');
		$_SESSION['user_id'] = $user_id;
		unset($_SESSION['2fa_pending']);
		json_response(['success' => true], true, '2FA geverifieerd');
	} else {
		json_response([], false, 'Ongeldige of verlopen code');
	}
}

// Fallback
json_response([], false, 'Ongeldige actie');
?>