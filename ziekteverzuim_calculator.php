<?php
// ziekteverzuim_calculator.php - Ziekteverzuim kosten calculator
session_start();
require_once 'config.php';
require_once 'functions.php';

if (!isset($_SESSION['bedrijf_id'])) {
	header('Location: login_bedrijf.php');
	exit;
}

$bedrijf_id = $_SESSION['bedrijf_id'];
$bedrijf = db_getRow("SELECT * FROM bedrijven WHERE id = ?", [$bedrijf_id], 'i');
$land = $bedrijf['land'];

// Medewerkers ophalen voor gemiddelde berekening
$medewerkers = db_getAll("
	SELECT w.id, w.naam, c.bruto_salaris, c.verzuimverzekering_actief
	FROM werknemers w
	JOIN contracten c ON w.id = c.werknemer_id
	WHERE w.bedrijf_id = ?
", [$bedrijf_id], 'i');

$regels = getZiekteverzuimRegels($land, 2026);
?>

<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Ziekteverzuim Calculator - Loonkosten.nl</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<style>
		.card-hover { transition: transform 0.2s; }
		.card-hover:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
		.result-card { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); color: white; }
		.tooltip-inner { max-width: 300px; }
	</style>
</head>
<body class="bg-light">
	<div class="container mt-5 mb-5">
		<div class="row">
			<div class="col-12 text-center mb-4">
				<h1 class="display-5"><i class="fas fa-calculator text-warning me-3"></i>Ziekteverzuim Kosten Calculator</h1>
				<p class="lead">Bereken wat ziekteverzuim jouw bedrijf écht kost – inclusief doorbetaling, vervanging en arbodienst.</p>
			</div>
		</div>

		<div class="row g-4">
			<!-- Input sectie -->
			<div class="col-lg-5">
				<div class="card shadow card-hover">
					<div class="card-header bg-primary text-white">
						<h5><i class="fas fa-sliders-h me-2"></i>Instellingen</h5>
					</div>
					<div class="card-body">
						<div class="mb-3">
							<label class="form-label">Gemiddeld verzuimpercentage (%)</label>
							<input type="number" id="verzuim_pct" class="form-control form-control-lg text-center" value="5.0" step="0.1" min="0" max="100" title="Gemiddeld verzuim in Nederland: ~5%, België: ~4-5%. Pas aan op basis van je eigen cijfers.">
							<small class="text-muted">Standaard: 5%</small>
						</div>
						<div class="form-check mb-3">
							<input type="checkbox" id="verzekering_actief" class="form-check-input" <?= $medewerkers[0]['verzuimverzekering_actief'] ?? '' ? 'checked' : '' ?> title="Vink aan als je een verzuimverzekering hebt (dekt loondoorbetaling na wachttijd)">
							<label class="form-check-label" for="verzekering_actief">Verzuimverzekering actief</label>
						</div>
						<button id="berekenBtn" class="btn btn-primary w-100 btn-lg" title="Klik om de verzuimkosten direct te berekenen">
							<i class="fas fa-calculator me-2"></i>Berekenen
						</button>
					</div>
				</div>
			</div>

			<!-- Resultaten sectie -->
			<div class="col-lg-7">
				<div class="card shadow result-card card-hover text-white">
					<div class="card-body text-center">
						<h2 id="totaal_kosten" class="display-4 mb-3">€ 0</h2>
						<p class="lead mb-4">Totale verwachte verzuimkosten per jaar</p>
						<div class="row text-white">
							<div class="col-md-4 mb-3">
								<h5>Verzuimdagen</h5>
								<p id="verzuim_dagen" class="fs-3 mb-0">0</p>
							</div>
							<div class="col-md-4 mb-3">
								<h5>Directe kosten</h5>
								<p id="directe_kosten" class="fs-3 mb-0">€ 0</p>
							</div>
							<div class="col-md-4 mb-3">
								<h5>Indirecte kosten</h5>
								<p id="indirecte_kosten" class="fs-3 mb-0">€ 0</p>
							</div>
						</div>
						<hr class="border-light">
						<p id="besparing_text" class="fs-5 mt-3"></p>
					</div>
				</div>

				<div class="card shadow mt-4">
					<div class="card-body">
						<h5>Uitleg</h5>
						<ul>
							<li><strong>Directe kosten</strong>: Loondoorbetaling tijdens ziekte</li>
							<li><strong>Indirecte kosten</strong>: Vervanging, productieverlies, arbodienst</li>
							<li><strong>Besparing</strong>: Door 1% lager verzuim of verzekering</li>
						</ul>
					</div>
				</div>
			</div>
		</div>

		<div class="text-center mt-5">
			<a href="dashboard_bedrijf.php" class="btn btn-outline-primary btn-lg" title="Terug naar je dashboard">
				<i class="fas fa-arrow-left me-2"></i>Terug naar dashboard
			</a>
		</div>
	</div>

	<script>
		function bereken() {
			const verzuim_pct = parseFloat($('#verzuim_pct').val()) || 5;
			const verzekering = $('#verzekering_actief').is(':checked');

			// Aantal medewerkers
			const aantal = <?= count($medewerkers) ?>;

			// Gemiddeld bruto salaris (schatting)
			const gem_bruto_maand = <?= $aantal_medewerkers > 0 ? array_sum(array_column($medewerkers, 'bruto_salaris')) / $aantal_medewerkers : 0 ?>;
			const gem_bruto_jaar = gem_bruto_maand * 12;

			// Verzuimdagen
			const werkdagen_jaar = 220;
			const verzuim_dagen = Math.round(werkdagen_jaar * (verzuim_pct / 100) * aantal);

			// Kosten (gemiddeld uit regels)
			const dagloon = gem_bruto_maand / 21.67;
			const directe = verzuim_dagen * dagloon;
			const indirecte = verzuim_dagen * 200; // Gemiddeld €200 extra per dag (vervanging etc.)
			const arbo = aantal * 500; // Jaarlijks per medewerker

			let totaal = directe + indirecte + arbo;

			// Verzekering besparing (schatting 50% dekking)
			if (verzekering) {
				totaal *= 0.5; // Verzekering dekt helft
			}

			// UI updaten
			$('#verzuim_dagen').text(verzuim_dagen.toLocaleString());
			$('#directe_kosten').text('€ ' + directe.toLocaleString(undefined, {minimumFractionDigits: 0}));
			$('#indirecte_kosten').text('€ ' + indirecte.toLocaleString(undefined, {minimumFractionDigits: 0}));
			$('#totaal_kosten').text('€ ' + totaal.toLocaleString(undefined, {minimumFractionDigits: 0}));

			const besparing = (directe + indirecte + arbo) - totaal;
			$('#besparing_text').html(
				verzekering ?
				`<strong>Met verzekering bespaar je ongeveer € ${besparing.toLocaleString()} per jaar</strong>` :
				`<strong>Verlaag verzuim met 1% → bespaar € ${(totaal * 0.2).toLocaleString()} per jaar</strong>`
			);
		}

		$('#berekenBtn').click(bereken);
		$('#verzuim_pct, #verzekering_actief').change(bereken);

		// Init
		bereken();
	</script>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		// Tooltips
		const tooltipTriggerList = document.querySelectorAll('[title]');
		const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
	</script>
</body>
</html>