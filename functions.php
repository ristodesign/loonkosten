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

// Placeholder voor andere berekeningen (bijtelling, verzuim, etc.)
// Deze worden uitgebreid in latere bestanden
?>