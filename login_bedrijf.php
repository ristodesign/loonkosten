<?php
// login_bedrijf.php - Login voor bedrijf (werkgever)
session_start();
require_once 'config.php';
require_once 'functions.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$email = strtolower(validate_input($_POST['email']));
	$password = $_POST['password'];

	$bedrijf = db_getRow("SELECT * FROM bedrijven WHERE email = ?", [$email], 's');

	if ($bedrijf && password_verify($password, $bedrijf['password'])) {
		$_SESSION['bedrijf_id'] = $bedrijf['id'];
		header('Location: dashboard_bedrijf.php');
		exit;
	} else {
		$message = '<div class="alert alert-danger" role="alert">Ongeldige email of wachtwoord</div>';
	}
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Bedrijf Login - Loonkosten.nl</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<style>
		body { background: linear-gradient(135deg, #007bff 0%, #00bfff 100%); height: 100vh; display: flex; align-items: center; }
		.login-card { max-width: 400px; margin: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
		.btn-login { background-color: #007bff; border: none; }
		.btn-login:hover { background-color: #0056b3; }
	</style>
</head>
<body>
	<div class="container">
		<div class="card login-card">
			<div class="card-header bg-primary text-white text-center">
				<h3>Bedrijf Login</h3>
			</div>
			<div class="card-body">
				<?= $message ?>
				<form method="POST">
					<div class="mb-3">
						<label for="email" class="form-label">Email</label>
						<input type="email" name="email" id="email" class="form-control" required placeholder="jouwbedrijf@voorbeeld.nl" title="Voer het emailadres in dat je gebruikte bij registratie">
					</div>
					<div class="mb-3">
						<label for="password" class="form-label">Wachtwoord</label>
						<input type="password" name="password" id="password" class="form-control" required title="Voer je wachtwoord in">
					</div>
					<button type="submit" class="btn btn-login text-white w-100" title="Log in op je bedrijfsdashboard">
						<i class="fas fa-sign-in-alt me-2"></i>Inloggen
					</button>
				</form>
				<div class="text-center mt-3">
					<p>Nog geen account? <a href="register_bedrijf.php" title="Registreer je bedrijf om te starten">Registreer hier</a></p>
				</div>
			</div>
		</div>
	</div>
</body>
</html>