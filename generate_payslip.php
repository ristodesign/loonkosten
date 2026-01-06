<?php
// generate_payslip.php - Volledige loonstrook genereren + SEPA-export
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'vendor/autoload.php'; // mPDF
require_once 'backend/sepa.php'; // SEPA-generatie

if (!isset($_SESSION['bedrijf_id'])) {
	header('Location: login_bedrijf.php');
	exit;
}

$bedrijf_id = $_SESSION['bedrijf_id'];
$bedrijf = db_getRow("SELECT * FROM bedrijven WHERE id = ?", [$bedrijf_id], 'i');
$land = $bedrijf['land'];

if (!$bedrijf) {
	die('Bedrijf niet gevonden');
}

// Periode (dynamisch of via GET, hier voorbeeld januari 2026)
$periode_id = $_GET['periode_id'] ?? 1;
$periode = db_getRow("SELECT * FROM loonperiodes WHERE id = ?", [$periode_id], 'i');

if (!$periode) {
	die('Periode niet gevonden');
}

// Medewerkers ophalen
$medewerkers = db_getAll("
	SELECT w.id, w.naam, w.email, w.iban, w.bic, w.geboortedatum,
		   c.*
	FROM werknemers w
	JOIN contracten c ON w.id = c.werknemer_id
	WHERE w.bedrijf_id = ?
", [$bedrijf_id], 'i');

$regels = getRegels($land, date('Y'));

$payments = []; // Voor SEPA
$total_amount = 0;
$pdf_files = [];

foreach ($medewerkers as $m) {
	$werknemer_id = $m['id'];

	// Tijdsblokken ophalen
	$tijdsblokken = db_getAll("SELECT * FROM tijdsblokken WHERE werknemer_id = ? AND periode_id = ?", [$werknemer_id, $periode_id], 'ii');

	$bruto_maand = 0;
	$toeslag_totaal = 0;
	$reiskosten = 0;
	$vakantiegeld_opbouw = 0;
	$bijtelling_auto = 0;

	$uurloon = getUurloon($werknemer_id);

	foreach ($tijdsblokken as $tb) {
		$dag_bruto = $tb['berekende_uren'] * $uurloon;
		$bruto_maand += $dag_bruto;

		// Overurentoeslag
		if ($tb['is_overuur'] > 0 && $m['type'] !== 'AllInVastSalaris') {
			$toeslag = berekenOverurentoeslag($uurloon, $tb['is_overuur'], 50);
			$toeslag_totaal += $toeslag;
			$bruto_maand += $toeslag;
		}
	}

	// Reiskosten
	$gew_dagen = count(array_unique(array_column($tijdsblokken, 'datum')));
	if ($m['reiskosten_recht']) {
		$reiskosten = $gew_dagen * $m['reiskosten_km_per_dag'] * $regels['reiskosten_tarief'];
		$bruto_maand += $reiskosten;
	}

	// Pensioen
	$pensioen = berekenPensioenBijdrage($bruto_maand, $m['pensioen_totaal_percentage'], $m['pensioen_werknemer_percentage']);

	// Auto van de zaak
	if ($m['heeft_auto_van_de_zaak']) {
		$bijtelling_jaar = berekenBijtellingAuto(
			$m['auto_cataloguswaarde'],
			$m['auto_elektrisch'],
			$m['auto_bijdrage_werknemer'] * 12,
			$land
		);
		$bijtelling_maand = round($bijtelling_jaar / 12, 2);
		$bruto_maand += $bijtelling_maand;
	}

	// Vakantiegeld opbouw
	$vakantiegeld_opbouw = berekenVakantiegeld($bruto_maand, $land, $periode_id, $regels);
	updateVakantiegeld($werknemer_id, date('Y'), $vakantiegeld_opbouw, $land, $periode_id);

	// Heffing
	$jaarloon_geschat = $bruto_maand * 12;
	$leeftijd_aow = (date('Y') - date('Y', strtotime($m['geboortedatum'] ?? '2000-01-01'))) >= 67;
	$heffing = berekenHeffing($bruto_maand, $land, $jaarloon_geschat, $leeftijd_aow);

	// Nettoloon
	$nettoloon = $bruto_maand - $heffing - $pensioen['werknemer_bijdrage'];

	// SEPA betaling toevoegen
	if (!empty($m['iban'])) {
		$payments[] = [
			'name' => $m['naam'],
			'iban' => $m['iban'],
			'bic' => $m['bic'] ?? '',
			'amount' => $nettoloon,
			'description' => "Salaris " . date('F Y', strtotime($periode['start_datum']))
		];
		$total_amount += $nettoloon;
	}

	// PDF loonstrook
	$mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
	$html = '<h1 style="text-align:center;">Loonstrook ' . date('F Y', strtotime($periode['start_datum'])) . '</h1>';
	if ($bedrijf['logo_path'] && file_exists($bedrijf['logo_path'])) {
		$html .= '<img src="' . $bedrijf['logo_path'] . '" width="200" style="float:right;margin-bottom:20px;">';
	}
	$html .= '<p><strong>Werknemer:</strong> ' . htmlspecialchars($m['naam']) . '<br>
			  <strong>Brutoloon:</strong> € ' . number_format($bruto_maand, 2) . '<br>
			  <strong>Reiskosten (onbelast):</strong> € ' . number_format($reiskosten, 2) . '<br>
			  <strong>Pensioenbijdrage werknemer:</strong> - € ' . number_format($pensioen['werknemer_bijdrage'], 2) . '<br>';
	if ($m['heeft_auto_van_de_zaak']) {
		$html .= '<strong>Bijtelling auto van de zaak:</strong> € ' . number_format($bijtelling_maand, 2) . '<br>';
	}
	$html .= '<strong>Heffing:</strong> - € ' . number_format($heffing, 2) . '<br>
			  <strong>Nettoloon:</strong> € ' . number_format($nettoloon, 2) . '<br>
			  <strong>Vakantiegeld opbouw:</strong> € ' . number_format($vakantiegeld_opbouw, 2) . '</p>';

	$mpdf->WriteHTML($html);
	$pdf_path = PDF_DIR . "loonstrook_{$werknemer_id}_{$periode_id}.pdf";
	$mpdf->Output($pdf_path, 'F');
	$pdf_files[] = $pdf_path;

	// DB opslaan
	db_execute("INSERT INTO loonstroken 
				(werknemer_id, periode_id, brutoloon, reiskosten, pensioen_werknemer, loonheffing, nettoloon, vakantiegeld_opbouw, pdf_path) 
				VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", 
				[$werknemer_id, $periode_id, $bruto_maand, $reiskosten, $pensioen['werknemer_bijdrage'], $heffing, $nettoloon, $vakantiegeld_opbouw, $pdf_path],
				'iiddddddd');
}

// SEPA genereren
$sepa_path = generate_sepa_xml($payments, $bedrijf, $periode);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="UTF-8">
	<title>Loonstroken gegenereerd</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
	<div class="alert alert-success">
		<h4>Loonstroken succesvol gegenereerd voor <?= count($medewerkers) ?> medewerkers!</h4>
		<p>Totale netto uitbetaling: € <?= number_format($total_amount, 2) ?></p>
	</div>

	<div class="row">
		<div class="col-md-6">
			<h5>Individuele loonstroken</h5>
			<ul class="list-group">
				<?php foreach ($pdf_files as $pdf): ?>
				<li class="list-group-item">
					<a href="<?= $pdf ?>" target="_blank" class="btn btn-outline-primary btn-sm">
						<?= basename($pdf) ?>
					</a>
				</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<div class="col-md-6">
			<h5>SEPA Betaalbestand</h5>
			<?php if ($sepa_path): ?>
				<a href="<?= $sepa_path ?>" class="btn btn-success btn-lg" download>
					<i class="fas fa-download me-2"></i>SEPA-bestand downloaden (<?= count($payments) ?> betalingen)
				</a>
				<p class="mt-3"><small>Upload dit bestand in je bank voor automatische salarisbetaling (werkt in NL en BE).</small></p>
			<?php else: ?>
				<p class="text-muted">Geen IBAN's gevonden – geen SEPA gegenereerd.</p>
			<?php endif; ?>
		</div>
	</div>

	<div class="text-center mt-5">
		<a href="dashboard_bedrijf.php" class="btn btn-primary btn-lg">
			<i class="fas fa-arrow-left me-2"></i>Terug naar dashboard
		</a>
	</div>
</div>
</body>
</html>