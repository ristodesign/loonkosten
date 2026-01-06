<?php
// login_werknemer.php - Login voor werknemers (medewerkers)
session_start();
require_once 'config.php';
require_once 'functions.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = strtolower(validate_input($_POST['email']));
	$password = $_POST['password'];

	$user = db_getRow("SELECT * FROM werknemers WHERE email = ?", [$email], 's');

	if ($user && password_verify($password, $user['password'])) {
		$ip = $_SERVER['REMOTE_ADDR'];

		// 2FA controle bij nieuw IP
		if ($ip !== $user['last_ip']) {
			$code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
			$expiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));

			db_execute("UPDATE werknemers SET 2fa_code = ?, 2fa_expiry = ? WHERE id = ?", [$code, $expiry, $user['id']], 'ssi');

			sendEmail($email, 'Jouw 2FA-code - Loonkosten.nl', "Beste {$user['naam']},<br><br>Je inlogcode is: <strong>$code</strong><br>Geldig voor 5 minuten.<br><br>Loonkosten.nl");

			$_SESSION['2fa_pending'] = $user['id'];
			$message = '<div class="alert alert-info">Een 2FA-code is naar je e-mail gestuurd. Voer deze hieronder in.</div>';
		} else {
			// Direct inloggen
			$_SESSION['user_id'] = $user['id'];
			db_execute("UPDATE werknemers SET last_ip = ? WHERE id = ?", [$ip, $user['id']], 'si');
			header('Location: track_hours.php');
			exit;
		}
	} else {
		$message = '<div class="alert alert-danger">Ongeldige e-mail of wachtwoord.</div>';
	}
}

// 2FA verificatie
if (isset($_SESSION['2fa_pending']) && isset($_POST['2fa_code'])) {
	$code = $_POST['2fa_code'];
	$user_id = $_SESSION['2fa_pending'];

	$user = db_getRow("SELECT id FROM werknemers WHERE id = ? AND 2fa_code = ? AND 2fa_expiry > NOW()", [$user_id, $code], 'is');

	if ($user) {
		$ip = $_SERVER['REMOTE_ADDR'];
		db_execute("UPDATE werknemers SET last_ip = ?, 2fa_code = NULL, 2fa_expiry = NULL WHERE id = ?", [$ip, $user_id], 'si');
		$_SESSION['user_id'] = $user_id;
		unset($_SESSION['2fa_pending']);
		header('Location: track_hours.php');
		exit;
	} else {
		$message = '<div class="alert alert-danger">Ongeldige of verlopen 2FA-code.</div>';
	}
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Medewerker Login - Loonkosten.nl</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		body { background: linear-gradient(135deg, #007bff 0%, #00bfff 100%); height: 100vh; display: flex; align-items: center; }
		.login-card { max-width: 420px; margin: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3); border-radius: 15px; }
		.btn-login { background-color: #007bff; border: none; }
		.btn-login:hover { background-color: #0056b3; }
	</style>
</head>
<body>
	<div class="container">
		<div class="card login-card">
			<div class="card-header bg-primary text-white text-center rounded-top">
				<h3>Medewerker Login</h3>
			</div>
			<div class="card-body p-4">
				<?= $message ?>

				<?php if (!isset($_SESSION['2fa_pending'])): ?>
				<form method="POST">
					<div class="mb-3">
						<label for="email" class="form-label">E-mailadres</label>
						<input type="email" name="email" id="email" class="form-control" required placeholder="jouw@email.nl" title="Voer het e-mailadres in dat bij je account hoort">
					</div>
					<div class="mb-3">
						<label for="password" class="form-label">Wachtwoord</label>
						<input type="password" name="password" id="password" class="form-control" required title="Voer je wachtwoord in">
					</div>
					<button type="submit" class="btn btn-login text-white w-100" title="Log in om je uren te registreren en loonstroken te bekijken">
						<i class="fas fa-sign-in-alt me-2"></i>Inloggen
					</button>
				</form>
				<?php else: ?>
				<form method="POST">
					<div class="mb-3">
						<label for="2fa_code" class="form-label">2FA-code (check je e-mail)</label>
						<input type="text" name="2fa_code" id="2fa_code" class="form-control text-center fs-3" maxlength="6" required title="Voer de 6-cijferige code in die je per e-mail hebt ontvangen">
					</div>
					<button type="submit" class="btn btn-success w-100" title="Verifieer de code om in te loggen">
						<i class="fas fa-check me-2"></i>Verifiëren
					</button>
				</form>
				<?php endif; ?>

				<div class="text-center mt-4">
					<small class="text-muted">Problemen met inloggen? Neem contact op met je werkgever.</small>
				</div>
			</div>
		</div>
	</div>
</body>
</html>