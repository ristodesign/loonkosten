<?php
// functions.php - Alle herbruikbare functies en berekeningen
require_once 'config.php';

// ==================== DATABASE HELPERS ====================
function db_query($sql, $params = [], $types = '') {
	$conn = getDb();
	$stmt = $conn->prepare($sql);
	if (!$stmt) {
		error_log("Prepare failed: " . $conn->error);
		return false;
	}
	if ($params && $types) {
		$stmt->bind_param($types, ...$params);
	}
	$stmt->execute();
	return $stmt;
}

function db_getRow($sql, $params = [], $types = '') {
	$stmt = db_query($sql, $params, $types);
	if ($stmt) {
		$result = $stmt->get_result();
		return $result->fetch_assoc();
	}
	return null;
}

function db_getAll($sql, $params = [], $types = '') {
	$stmt = db_query($sql, $params, $types);
	if ($stmt) {
		$result = $stmt->get_result();
		return $result->fetch_all(MYSQLI_ASSOC);
	}
	return [];
}

function db_execute($sql, $params = [], $types = '') {
	$stmt = db_query($sql, $params, $types);
	return $stmt ? $stmt->affected_rows > 0 : false;
}

function getLastInsertId() {
	return getDb()->insert_id;
}

function json_response($data = [], $success = true, $message = '') {
	http_response_code($success ? 200 : 400);
	echo json_encode([
		'success' => $success,
		'data' => $data,
		'message' => $message
	]);
	exit;
}

// ==================== EMAIL ====================
function sendEmail($to, $subject, $body) {
	require_once 'vendor/autoload.php';
	$mail = new PHPMailer\PHPMailer\PHPMailer(true);
	try {
		$mail->isSMTP();
		$mail->Host       = SMTP_HOST;
		$mail->SMTPAuth   = true;
		$mail->Username   = SMTP_USER;
		$mail->Password   = SMTP_PASS;
		$mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
		$mail->Port       = 587;

		$mail->setFrom(SMTP_FROM, 'Loonkosten.nl');
		$mail->addAddress($to);
		$mail->isHTML(true);
		$mail->Subject = $subject;
		$mail->Body    = $body;
		$mail->send();
		return true;
	} catch (Exception $e) {
		error_log("Email error: " . $e->getMessage());
		return false;
	}
}

// ==================== ALGEMENE BEREKENINGEN ====================
function getUurloon($werknemer_id) {
	$c = db_getRow("SELECT bruto_salaris, is_uurloon FROM contracten WHERE werknemer_id = ?", [$werknemer_id], 'i');
	if (!$c) return 0;
	if ($c['is_uurloon']) return $c['bruto_salaris'];
	return round($c['bruto_salaris'] / 173.33, 2); // Maand → uur
}

function berekenOverurentoeslag($uurloon, $overuren, $percentage = 50) {
	return round($overuren * $uurloon * ($percentage / 100), 2);
}

function berekenVakantieOpbouw($uren, $percentage = 8.00) {
	return round($uren * ($percentage / 100), 2);
}

function updateVakantieSaldo($werknemer_id, $jaar, $opbouw = 0, $gebruikt = 0) {
	$saldo = db_getRow("SELECT id FROM vakantie_saldo WHERE werknemer_id = ? AND jaar = ?", [$werknemer_id, $jaar], 'ii');
	if ($saldo) {
		db_execute("UPDATE vakantie_saldo SET opbouw = opbouw + ?, gebruikt = gebruikt + ? WHERE id = ?", [$opbouw, $gebruikt, $saldo['id']], 'ddi');
	} else {
		db_execute("INSERT INTO vakantie_saldo (werknemer_id, jaar, opbouw, gebruikt) VALUES (?, ?, ?, ?)", [$werknemer_id, $jaar, $opbouw, $gebruikt], 'iidd');
	}
}

function getVakantieSaldo($werknemer_id, $jaar = 2026) {
	$r = db_getRow("SELECT opbouw, gebruikt, (opbouw - gebruikt) AS restant FROM vakantie_saldo WHERE werknemer_id = ? AND jaar = ?", [$werknemer_id, $jaar], 'ii');
	return $r ?: ['opbouw' => 0, 'gebruikt' => 0, 'restant' => 0];
}

function berekenPensioenBijdrage($bruto_maand, $totaal_pct = 25.00, $werknemer_pct = 8.00, $franchise_jaar = 19172.00) {
	$franchise_maand = $franchise_jaar / 12;
	$grondslag = max(0, $bruto_maand - $franchise_maand);
	$totaal_premie = round($grondslag * ($totaal_pct / 100), 2);
	$werknemer_bijdrage = round($totaal_premie * ($werknemer_pct / $totaal_pct), 2);
	return ['grondslag' => $grondslag, 'werknemer_bijdrage' => $werknemer_bijdrage];
}

function berekenHeffing($bruto_maand, $land, $jaarloon_geschat, $leeftijd_aow = false) {
	$regels = getRegels($land);
	if ($land === 'NL') {
		$tarief = $leeftijd_aow ? $regels['tarief1_aow'] : $regels['tarief1'];
		$heffing = $jaarloon_geschat <= $regels['schijf1_grens'] 
			? $jaarloon_geschat * ($tarief / 100)
			: $regels['schijf1_grens'] * ($tarief / 100) + ($jaarloon_geschat - $regels['schijf1_grens']) * ($regels['tarief2'] / 100);
		return round($heffing / 12, 2);
	}
	// BE logica hier (uit eerdere berichten)
	return 0;
}

function getRegels($land, $jaar = 2026) {
	$tabel = $land === 'NL' ? 'regels_nl' : 'regels_be';
	return db_getRow("SELECT * FROM $tabel WHERE jaar = ?", [$jaar], 'i') ?: [];
}

function validate_input($data) {
	$data = trim($data);
	$data = stripslashes($data);
	$data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
	return $data;
}

function getZiekteverzuimRegels($land, $jaar = 2026) {
	return db_getRow("SELECT * FROM ziekteverzuim_regels WHERE jaar = ? AND land = ?", [$jaar, $land], 'is');
}

function berekenBijtellingAuto($cataloguswaarde, $elektrisch = false, $bijdrage = 0, $land = 'NL') {
	if ($land === 'NL') {
		$bijtelling_pct = $elektrisch ? 18 : 22;  // 2026: EV 18% tot €30.000, daarna 22%
		$grens_ev = 30000;
		if ($elektrisch) {
			$deel_ev = min($cataloguswaarde, $grens_ev);
			$deel_normaal = max(0, $cataloguswaarde - $grens_ev);
			$bijtelling = ($deel_ev * 0.18) + ($deel_normaal * 0.22);
		} else {
			$bijtelling = $cataloguswaarde * ($bijtelling_pct / 100);
		}
		return max(0, round($bijtelling - $bijdrage, 2));  // Eigen bijdrage aftrekbaar
	} elseif ($land === 'BE') {
		// VAA België 2026 (forfaitair)
		$basis_pct = 6.00 / 100;
		$ref_co2_benzine = 70;
		$ref_co2_diesel = 58;
		$co2 = 100;  // Default, gebruik veld als beschikbaar
		$co2_factor = $elektrisch ? 0.95 : (1 + max(-0.5, min(0.5, ($co2 - ($co2 < 60 ? $ref_co2_diesel : $ref_co2_benzine)) / 10 * 0.04)));
		$vaa_bruto = $cataloguswaarde * $basis_pct * $co2_factor;

		// Leeftijdscorrectie
		$jaar_inschrijving = date('Y');  // Placeholder
		$leeftijd = date('Y') - $jaar_inschrijving;
		$leeftijd_correctie = max(0.70, 1 - ($leeftijd * 0.06));  // Max -30%

		$vaa = $vaa_bruto * $leeftijd_correctie;
		$vaa_min = 1690;  // Minimum VAA 2026
		$vaa = max($vaa, $vaa_min);

		return round($vaa - $bijdrage, 2);
	}
	return 0;
}

function berekenBijtellingMaand($bijtelling_jaar) {
	return round($bijtelling_jaar / 12, 2);
}

function berekenVakantiegeld($bruto_bedrag, $land, $periode_id = null, $regels = null) {
	if ($regels === null) {
		$regels = getRegels($land);
	}

	if ($land === 'NL') {
		// Nederland: 8% opbouw over bruto (standaard)
		$pct = $regels['vakantie_pct'] ?? 8.00;
		return round($bruto_bedrag * ($pct / 100), 2);
	} elseif ($land === 'BE') {
		// België: Enkel vakantiegeld (92% voor bedienden, 91.67% voor arbeiders)
		$pct = $regels['vakantiegeld_enkel_pct'] ?? 92.00;
		$enkel = round($bruto_bedrag * ($pct / 100), 2);

		// Dubbel vakantiegeld in juni (extra maandloon)
		$dubbel = ($periode_id == 6) ? $bruto_bedrag : 0; // Periode 6 = juni

		return $enkel + $dubbel;
	}

	return 0;
}

// Optioneel: Maandelijkse opbouw updaten (wordt al aangeroepen in track_hours)
function updateVakantiegeld($werknemer_id, $jaar, $bedrag, $land = 'NL', $periode_id = null) {
	$vg = db_getRow("SELECT id FROM vakantiegeld_reservering WHERE werknemer_id = ? AND jaar = ?", [$werknemer_id, $jaar], 'ii');
	if ($vg) {
		if ($land === 'BE' && $periode_id == 6) {
			db_execute("UPDATE vakantiegeld_reservering SET dubbel = dubbel + ? WHERE id = ?", [$bedrag, $vg['id']], 'di');
		} else {
			db_execute("UPDATE vakantiegeld_reservering SET opgebouwd = opgebouwd + ? WHERE id = ?", [$bedrag, $vg['id']], 'di');
		}
	} else {
		$columns = $land === 'BE' ? 'opgebouwd, enkel, dubbel' : 'opgebouwd';
		$values = $land === 'BE' ? '?, ?, ?' : '?';
		$params = $land === 'BE' ? [$werknemer_id, $jaar, $bedrag, ($periode_id == 6 ? $bedrag : 0), ($periode_id == 6 ? $bedrag : 0)] : [$werknemer_id, $jaar, $bedrag];
		$types = $land === 'BE' ? 'iiddd' : 'iid';
		db_execute("INSERT INTO vakantiegeld_reservering (werknemer_id, jaar, $columns) VALUES (?, ?, $values)", $params, $types);
	}
}

// Placeholder voor andere berekeningen (bijtelling, verzuim, etc.)
// Deze worden uitgebreid in latere bestanden
?>