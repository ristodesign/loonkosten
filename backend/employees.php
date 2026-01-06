<?php
// backend/employees.php - Volledige medewerkerbeheer (AJAX API)
require_once __DIR__ . '/../config.php';

// Alleen ingelogde bedrijven mogen dit gebruiken
if (!isset($_SESSION['bedrijf_id'])) {
	json_response([], false, 'Niet ingelogd');
}

$bedrijf_id = $_SESSION['bedrijf_id'];
$action = $_POST['action'] ?? '';

if ($action === 'get_employees') {
	$employees = db_getAll("
		SELECT w.id, w.naam, w.email, w.geboortedatum, w.statuur,
			   c.type AS contract_type, c.bruto_salaris, c.contract_uren_per_week,
			   c.reiskosten_recht, c.verzuimverzekering_actief,
			   c.heeft_auto_van_de_zaak, c.heeft_13e_maand
		FROM werknemers w
		JOIN contracten c ON w.id = c.werknemer_id
		WHERE w.bedrijf_id = ?
		ORDER BY w.naam
	", [$bedrijf_id], 'i');

	json_response($employees);
}

if ($action === 'add_employee') {
	// Basis werknemer gegevens
	$naam = validate_input($_POST['naam']);
	$adres = validate_input($_POST['adres']);
	$geboortedatum = $_POST['geboortedatum'] ?: null;
	$bsn = validate_input($_POST['bsn']);
	$email = strtolower(validate_input($_POST['email']));
	$rijksregisternummer = validate_input($_POST['rijksregisternummer'] ?? '');
	$statuut = validate_input($_POST['statuut'] ?? 'bediende');

	// Wachtwoord (leeg = automatisch)
	$password_input = $_POST['password'] ?? '';
	$password = $password_input ?: bin2hex(random_bytes(8)); // Random wachtwoord
	$password_hash = password_hash($password, PASSWORD_DEFAULT);

	// Check unieke email
	if (db_getRow("SELECT id FROM werknemers WHERE email = ?", [$email], 's')) {
		json_response([], false, 'E-mailadres bestaat al');
	}

	// Werknemer toevoegen
	$result = db_execute(
		"INSERT INTO werknemers 
		 (bedrijf_id, naam, adres, geboortedatum, bsn, email, password, rijksregisternummer, statuut)
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
		[$bedrijf_id, $naam, $adres, $geboortedatum, $bsn, $email, $password_hash, $rijksregisternummer, $statuut],
		'issssssss'
	);

	if (!$result['success']) {
		json_response([], false, 'Fout bij toevoegen werknemer');
	}

	$werknemer_id = getLastInsertId();

	// Contractgegevens
	$contract = [
		'werknemer_id' => $werknemer_id,
		'type' => validate_input($_POST['type']),
		'bruto_salaris' => (float)$_POST['bruto_salaris'],
		'is_uurloon' => (int)($_POST['is_uurloon'] ?? 0),
		'contract_uren_per_week' => (float)$_POST['contract_uren_per_week'],
		'pensioen_totaal_percentage' => (float)($_POST['pensioen_totaal_percentage'] ?? 25.00),
		'pensioen_werknemer_percentage' => (float)($_POST['pensioen_werknemer_percentage'] ?? 8.00),
		'vakantie_percentage' => (float)($_POST['vakantie_percentage'] ?? 8.00),
		'reiskosten_recht' => (int)($_POST['reiskosten_recht'] ?? 0),
		'reiskosten_km_per_dag' => (float)($_POST['reiskosten_km_per_dag'] ?? 0.00),
		'cao' => validate_input($_POST['cao'] ?? ''),
		'paritair_comite' => validate_input($_POST['paritair_comite'] ?? ''),
		'heeft_13e_maand' => (int)($_POST['heeft_13e_maand'] ?? 0),
		'proeftijd_maanden' => (int)($_POST['proeftijd_maanden'] ?? 0),
		'duur_type' => validate_input($_POST['duur_type'] ?? 'onbepaald'),
		'einddatum' => $_POST['einddatum'] ?: null,
		'verzuimverzekering_actief' => (int)($_POST['verzuimverzekering_actief'] ?? 0),
		'verzuimverzekering_premie_pct' => (float)($_POST['verzuimverzekering_premie_pct'] ?? 2.00),
		'verzuim_wachttijd_dagen' => (int)($_POST['verzuim_wachttijd_dagen'] ?? 14),
		'heeft_auto_van_de_zaak' => (int)($_POST['heeft_auto_van_de_zaak'] ?? 0),
		'auto_cataloguswaarde' => (float)($_POST['auto_cataloguswaarde'] ?? 0.00),
		'auto_co2_uitstoot' => (int)($_POST['auto_co2_uitstoot'] ?? 0),
		'auto_elektrisch' => (int)($_POST['auto_elektrisch'] ?? 0),
		'auto_eerste_inschrijving_jaar' => $_POST['auto_eerste_inschrijving_jaar'] ? (int)$_POST['auto_eerste_inschrijving_jaar'] : null,
		'auto_bijdrage_werknemer' => (float)($_POST['auto_bijdrage_werknemer'] ?? 0.00)
	];

	// Contract insert
	$columns = implode(', ', array_keys($contract));
	$placeholders = str_repeat('?,', count($contract) - 1) . '?';
	$types = 'i' . str_repeat('s', 4) . str_repeat('d', 12) . str_repeat('i', 7) . 's' . str_repeat('d', 2); // Aanpassen aan aantal velden

	$contract_result = db_execute("INSERT INTO contracten ($columns) VALUES ($placeholders)", array_values($contract), $types);

	if (!$contract_result['success']) {
		// Optioneel: rollback werknemer – voor nu log
		error_log("Contract fout na werknemer toevoegen ID $werknemer_id");
	}

	// E-mail met inloggegevens (als wachtwoord automatisch)
	if (!$password_input) {
		sendEmail($email, 'Welkom bij Loonkosten.nl', "Beste $naam,<br><br>Je account is aangemaakt.<br>
			E-mail: $email<br>Wachtwoord: $password<br><br>Log in op " . APP_URL . "/login_werknemer.php");
	}

	json_response(['werknemer_id' => $werknemer_id], true, 'Medewerker succesvol toegevoegd');
}

if ($action === 'update_employee') {
	// Vergelijkbare logica als add_employee, maar met UPDATE
	// Uit te breiden indien nodig
	json_response([], false, 'Update nog niet geïmplementeerd');
}

if ($action === 'delete_employee') {
	$werknemer_id = (int)$_POST['werknemer_id'];
	// Cascade delete via FOREIGN KEY ON DELETE CASCADE
	$result = db_execute("DELETE FROM werknemers WHERE id = ? AND bedrijf_id = ?", [$werknemer_id, $bedrijf_id], 'ii');
	json_response(['success' => $result], $result, $result ? 'Medewerker verwijderd' : 'Verwijderen mislukt');
}

// Fallback
json_response([], false, 'Ongeldige actie');
?>