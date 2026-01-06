<?php
// backend/times.php - Tijdsblokken beheren (in-/uitklokken)
require_once __DIR__ . '/../config.php';
require_once 'api_db.php';

$action = $_POST['action'] ?? '';

if ($action === 'add_timeslot') {
	$werknemer_id = (int)$_POST['werknemer_id'];
	$periode_id = (int)$_POST['periode_id'] ?? 1; // Default huidige periode
	$datum = validate_input($_POST['datum']);
	$in_tijd = validate_input($_POST['in_tijd']);
	$uit_tijd = validate_input($_POST['uit_tijd']);
	$pauze_min = (int)($_POST['pauze_min'] ?? 30);
	$type = validate_input($_POST['type'] ?? 'normaal');

	// Valideer tijden
	if (strtotime($uit_tijd) <= strtotime($in_tijd)) {
		json_response([], false, 'Uitkloktijd moet na inkloktijd zijn');
	}

	// Bereken totale uren en overuren
	$uren_totaal = (strtotime("$datum $uit_tijd") - strtotime("$datum $in_tijd")) / 3600 - $pauze_min / 60;
	$uren_totaal = max(0, round($uren_totaal, 2));

	$contract = db_getRow("SELECT contract_uren_per_week, type AS contract_type FROM contracten WHERE werknemer_id = ?", [$werknemer_id], 'i');
	if (!$contract) {
		json_response([], false, 'Contract niet gevonden');
	}

	$dag_max = $contract['contract_uren_per_week'] / 5; // Gemiddeld per werkdag
	$normaal = min($uren_totaal, $dag_max);
	$over = max(0, $uren_totaal - $dag_max);

	// Sla tijdsblok op
	$result = db_execute(
		"INSERT INTO tijdsblokken (werknemer_id, periode_id, datum, in_tijd, uit_tijd, pauze_min, is_overuur) 
		 VALUES (?, ?, ?, ?, ?, ?, ?)",
		[$werknemer_id, $periode_id, $datum, $in_tijd, $uit_tijd, $pauze_min, $over],
		'iisssii'
	);

	if (!$result['success']) {
		json_response([], false, 'Fout bij opslaan tijdsblok');
	}

	// Sla normale uren op
	db_execute(
		"INSERT INTO uren (werknemer_id, periode_id, datum, uren, type) VALUES (?, ?, ?, ?, ?)",
		[$werknemer_id, $periode_id, $datum, $normaal, $type],
		'iisds'
	);

	// Overuren + toeslag (alleen als niet AllInVastSalaris)
	if ($over > 0 && $contract['contract_type'] !== 'AllInVastSalaris') {
		$toeslag_pct = 50; // Default, kan uit regels halen
		$uurloon = getUurloon($werknemer_id);
		$toeslag_bedrag = berekenOverurentoeslag($uurloon, $over, $toeslag_pct);

		db_execute(
			"INSERT INTO uren (werknemer_id, periode_id, datum, uren, toeslag, is_overuur, toeslag_percentage) 
			 VALUES (?, ?, ?, ?, ?, 1, ?)",
			[$werknemer_id, $periode_id, $datum, $over, $toeslag_bedrag, $toeslag_pct],
			'iisdsi'
		);
	}

	// Vakantieopbouw bijwerken (alleen normale uren)
	if ($type === 'normaal') {
		updateVakantieSaldo($werknemer_id, date('Y'), berekenVakantieOpbouw($normaal));
	} elseif ($type === 'vakantie') {
		updateVakantieSaldo($werknemer_id, date('Y'), 0, $uren_totaal);
	}

	json_response(['timeslot_id' => getLastInsertId()], true, 'Tijdsblok opgeslagen');
}

if ($action === 'get_timeslots') {
	$werknemer_id = (int)$_POST['werknemer_id'];
	$periode_id = (int)$_POST['periode_id'] ?? 1;

	$timeslots = db_getAll(
		"SELECT t.*, (t.berekende_uren - t.is_overuur) AS normale_uren 
		 FROM tijdsblokken t 
		 WHERE t.werknemer_id = ? AND t.periode_id = ? 
		 ORDER BY t.datum DESC, t.in_tijd",
		[$werknemer_id, $periode_id],
		'ii'
	);

	json_response($timeslots);
}

// Fallback
json_response([], false, 'Ongeldige actie');
?>