<?php
// generate_contract.php - Arbeidscontract genereren
session_start();
require_once 'config.php';
require_once 'functions.php';
require_once 'vendor/autoload.php'; // mPDF

if (!isset($_SESSION['bedrijf_id'])) {
	header('Location: login_bedrijf.php');
	exit;
}

if (!isset($_GET['werknemer_id'])) {
	die('Geen werknemer ID opgegeven');
}

$werknemer_id = (int)$_GET['werknemer_id'];

$bedrijf_id = $_SESSION['bedrijf_id'];

// Gegevens ophalen
$werknemer = db_getRow("
	SELECT w.*, c.*, b.naam AS bedrijf_naam, b.adres AS bedrijf_adres, b.logo_path
	FROM werknemers w
	JOIN contracten c ON w.id = c.werknemer_id
	JOIN bedrijven b ON w.bedrijf_id = b.id
	WHERE w.id = ? AND w.bedrijf_id = ?
", [$werknemer_id, $bedrijf_id], 'ii');

if (!$werknemer) {
	die('Werknemer niet gevonden of geen toegang');
}

$land = db_getRow("SELECT land FROM bedrijven WHERE id = ?", [$bedrijf_id], 'i')['land'];

// Bedrijfsspecifieke clausules
$clausules = db_getAll("SELECT * FROM contract_clausules WHERE bedrijf_id = ? ORDER BY positie", [$bedrijf_id], 'i');

// mPDF
$mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);

// Logo
$logo = '';
if ($werknemer['logo_path'] && file_exists($werknemer['logo_path'])) {
	$logo = '<img src="' . $werknemer['logo_path'] . '" width="200" style="float:right;">';
}

// Contract template
$html = '
<!DOCTYPE html>
<html>
<head>
	<style>
		body { font-family: Arial, sans-serif; line-height: 1.6; }
		h1, h2, h3 { color: #007bff; }
		table { width: 100%; border-collapse: collapse; margin: 20px 0; }
		td { padding: 8px; border: 1px solid #ddd; }
		.signature { margin-top: 50px; page-break-inside: avoid; }
	</style>
</head>
<body>
	<h1 style="text-align:center;">Arbeidsovereenkomst</h1>
	' . $logo . '
	<p><strong>Datum:</strong> ' . date('d-m-Y') . '</p>

	<h2>Partijen</h2>
	<table>
		<tr><td><strong>Werkgever</strong></td><td>' . htmlspecialchars($werknemer['bedrijf_naam']) . '<br>' . htmlspecialchars($werknemer['bedrijf_adres']) . '</td></tr>
		<tr><td><strong>Werknemer</strong></td><td>' . htmlspecialchars($werknemer['naam']) . '<br>' . htmlspecialchars($werknemer['adres']) . '</td></tr>
	</table>

	<h2>Artikel 1: Functie en aanvang</h2>
	<p>De werknemer treedt in dienst als <strong>[Functie]</strong> met ingang van <strong>[Startdatum]</strong>.
	' . ($werknemer['duur_type'] === 'bepaald' ? 'Het contract eindigt op ' . date('d-m-Y', strtotime($werknemer['einddatum'])) . '.' : 'Het betreft een contract voor onbepaalde tijd.') . '</p>

	<h2>Artikel 2: Arbeidsduur en salaris</h2>
	<p>Arbeidsduur: ' . $werknemer['contract_uren_per_week'] . ' uur per week.<br>
	Bruto salaris: € ' . number_format($werknemer['bruto_salaris'], 2) . ' per maand.<br>
	Contracttype: ' . $werknemer['type'] . '</p>

	<h2>Artikel 3: Proeftijd</h2>
	<p>Proeftijd: ' . $werknemer['proeftijd_maanden'] . ' maanden.</p>

	<h2>Artikel 4: Overige afspraken</h2>
	<p>Reiskostenvergoeding: ' . ($werknemer['reiskosten_recht'] ? 'Ja (' . $werknemer['reiskosten_km_per_dag'] . ' km/dag)' : 'Nee') . '<br>
	Auto van de zaak: ' . ($werknemer['heeft_auto_van_de_zaak'] ? 'Ja' : 'Nee') . '<br>
	Verzuimverzekering: ' . ($werknemer['verzuimverzekering_actief'] ? 'Ja' : 'Nee') . '</p>';

foreach ($clausules as $c) {
	$html .= '<h3>' . htmlspecialchars($c['titel']) . '</h3>';
	$html .= '<p>' . nl2br(htmlspecialchars($c['tekst'])) . '</p>';
}

$html .= '
	<div class="signature">
		<table width="100%">
			<tr>
				<td>_______________________________<br>Werkgever<br>Datum: _______________</td>
				<td>_______________________________<br>Werknemer<br>Datum: _______________</td>
			</tr>
		</table>
	</div>
</body>
</html>';

$mpdf->WriteHTML($html);

$contract_path = CONTRACT_DIR . "contract_{$werknemer_id}_" . date('Ymd') . ".pdf";
$mpdf->Output($contract_path, 'F');

// Email naar werknemer
sendEmail($werknemer['email'], 'Je arbeidscontract', "Beste {$werknemer['naam']},<br><br>Bijgevoegd je arbeidscontract.<br><a href='https://jouwdomein.nl/$contract_path'>Download PDF</a>");

header('Location: dashboard_bedrijf.php?success=contract_generated');
exit;
?>