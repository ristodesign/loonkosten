<?php
// backend/payslips.php - Loonstrook genereren + SEPA-export
require_once 'api_db.php';
require_once '../vendor/autoload.php'; // mPDF

$action = $_POST['action'] ?? '';

if ($action === 'generate_batch') {
	$periode_id = (int)$_POST['periode_id'];
	$bedrijf_id = (int)$_POST['bedrijf_id'];

	$periode = db_getRow("SELECT * FROM loonperiodes WHERE id = ?", [$periode_id], 'i');
	if (!$periode) {
		json_response([], false, 'Periode niet gevonden');
	}

	$medewerkers = db_getAll("
		SELECT w.id, w.naam, w.email, w.iban, w.bic, w.geboortedatum,
			   c.*
		FROM werknemers w
		JOIN contracten c ON w.id = c.werknemer_id
		WHERE w.bedrijf_id = ?
	", [$bedrijf_id], 'i');

	$bedrijf = db_getRow("SELECT * FROM bedrijven WHERE id = ?", [$bedrijf_id], 'i');
	$land = $bedrijf['land'];

	$regels = getRegels($land);

	$payments = []; // Voor SEPA
	$total_amount = 0;
	$pdf_files = [];

	foreach ($medewerkers as $m) {
		$werknemer_id = $m['id'];

		// Haal tijdsblokken + uren
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
			$toeslag_totaal += berekenOverurentoeslag($uurloon, $tb['is_overuur'], 50);
			$bruto_maand += $toeslag_totaal;
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
		updateVakantiegeld($werknemer_id, date('Y'), $vakantiegeld_opbouw);

		// Heffing
		$jaarloon_geschat = $bruto_maand * 12;
		$leeftijd_aow = (2026 - date('Y', strtotime($m['geboortedatum'] ?? '2000-01-01'))) >= 67;
		$heffing = berekenHeffing($bruto_maand, $land, $jaarloon_geschat, $leeftijd_aow);

		// Nettoloon
		$nettoloon = $bruto_maand - $heffing - $pensioen['werknemer_bijdrage'];

		// SEPA betaling toevoegen
		if ($m['iban']) {
			$payments[] = [
				'name' => $m['naam'],
				'iban' => $m['iban'],
				'bic' => $m['bic'] ?? '',
				'amount' => $nettoloon,
				'description' => "Salaris {$periode['start_datum']} - {$periode['eind_datum']}"
			];
			$total_amount += $nettoloon;
		}

		// PDF loonstrook
		$mpdf = new \Mpdf\Mpdf();
		$html = "<h1>Loonstrook {$periode['start_datum']}</h1>
				 <p>Werknemer: {$m['naam']}</p>
				 <table border='1' cellpadding='5'>
					 <tr><td>Brutoloon</td><td>€ " . number_format($bruto_maand, 2) . "</td></tr>
					 <tr><td>Reiskosten</td><td>€ " . number_format($reiskosten, 2) . "</td></tr>
					 <tr><td>Pensioen werknemer</td><td>- € " . number_format($pensioen['werknemer_bijdrage'], 2) . "</td></tr>
					 <tr><td>Heffing</td><td>- € " . number_format($heffing, 2) . "</td></tr>
					 <tr><td><strong>Nettoloon</strong></td><td><strong>€ " . number_format($nettoloon, 2) . "</strong></td></tr>
				 </table>";

		$mpdf->WriteHTML($html);
		$pdf_path = PDF_DIR . "loonstrook_{$werknemer_id}_{$periode_id}.pdf";
		$mpdf->Output($pdf_path, 'F');
		$pdf_files[] = $pdf_path;

		// Sla in DB
		db_execute("INSERT INTO loonstroken (werknemer_id, periode_id, brutoloon, reiskosten, pensioen_werknemer, loonheffing, nettoloon, vakantiegeld_opbouw, pdf_path) 
					VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", 
					[$werknemer_id, $periode_id, $bruto_maand, $reiskosten, $pensioen['werknemer_bijdrage'], $heffing, $nettoloon, $vakantiegeld_opbouw, $pdf_path],
					'iiddddddd');
	}

	// SEPA XML genereren (zie volgende bestand)
	// Voor nu placeholder
	$sepa_path = generate_sepa_xml($payments, $bedrijf, $periode);

	json_response([
		'pdf_files' => $pdf_files,
		'sepa_path' => $sepa_path,
		'total_payments' => count($payments),
		'total_amount' => $total_amount
	], true, 'Loonstroken en SEPA gegenereerd');
}

// Placeholder voor SEPA (volgende bestand)
function generate_sepa_xml($payments, $bedrijf, $periode) {
	// Volledige SEPA-code komt in sepa.php
	return 'sepa/placeholder.xml';
}
?>