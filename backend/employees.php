<?php
// backend/employees.php - Medewerkers beheren (toevoegen, ophalen, bewerken, verwijderen)
require_once 'api_db.php';

$action = $_POST['action'] ?? '';

if ($action === 'get_employees') {
	$bedrijf_id = (int)$_POST['bedrijf_id'];

	$employees = db_getAll("
		SELECT w.id, w.naam, w.email, w.geboortedatum, w.statuur,
			   c.type AS contract_type, c.bruto_salaris, c.contract_uren_per_week,
			   c.reiskosten_recht, c.verzuimverzekering_actief,
			   c.heeft_auto_van_de_zaak
		FROM werknemers w
		JOIN contracten c ON w.id = c.werknemer_id
		WHERE w.bedrijf_id = ?
		ORDER BY w.naam
	", [$bedrijf_id], 'i');

	json_response($employees);
}

if ($action === 'add_employee') {
	$bedrijf_id = (int)$_POST['bedrijf_id'];
	$naam = validate_input($_POST['naam']);
	$adres = validate_input($_POST['adres']);
	$geboortedatum = $_POST['geboortedatum'] ?: null;
	$bsn = validate_input($_POST['bsn']);
	$email = strtolower(validate_input($_POST['email']));
	$password = password_hash($_POST['password'] ?? bin2hex(random_bytes(8)), PASSWORD_DEFAULT); // Random als leeg
	$rijksregisternummer = validate_input($_POST['rijksregisternummer'] ?? '');
	$statuut = validate_input($_POST['statuut'] ?? 'bediende');

	// Voeg werknemer toe
	$result = db_execute(
		"INSERT INTO werknemers (bedrijf_id, naam, adres, geboortedatum, bsn, email, password, rijksregisternummer, statuut) 
		 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
		[$bedrijf_id, $naam, $adres, $geboortedatum, $bsn, $email, $password, $rijksregisternummer, $statuut],
		'issssssss'
	);

	if (!$result['success']) {
		json_response([], false, 'Fout bij toevoegen werknemer');
	}

	$werknemer_id = getLastInsertId();

	// Voeg contract toe
	$contract_data = [
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
		'auto_eerste_inschrijving_jaar' => (int)($_POST['auto_eerste_inschrijving_jaar'] ?? 0),
		'auto_bijdrage_werknemer' => (float)($_POST['auto_bijdrage_werknemer'] ?? 0.00)
	];

	$fields = implode(', ', array_keys($contract_data));
	$placeholders = str_repeat('?,', count($contract_data) - 1) . '?';
	$types = str_repeat('i', 2) . str_repeat('d', 10) . str_repeat('s', 3) . str_repeat('i', 9); // Aanpassen aan aantal

	db_execute("INSERT INTO contracten ($fields) VALUES ($placeholders)", array_values($contract_data), $types);

	json_response(['werknemer_id' => $werknemer_id], true, 'Medewerker toegevoegd');
}

if ($action === 'update_employee') {
	// Vergelijkbare logica als add, maar met UPDATE
	// ... (uit te breiden)
}

if ($action === 'delete_employee') {
	$werknemer_id = (int)$_POST['werknemer_id'];
	db_execute("DELETE FROM werknemers WHERE id = ? AND bedrijf_id = ?", [$werknemer_id, $_SESSION['bedrijf_id']], 'ii');
	json_response(['success' => true]);
}

// Fallback
json_response([], false, 'Ongeldige actie');
?>