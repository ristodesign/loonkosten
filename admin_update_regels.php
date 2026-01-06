<?php
// admin_update_regels.php - Jaarlijkse update van loonregels NL/BE
session_start();
require_once 'config.php';
require_once 'functions.php';

// Beveiliging: Alleen voor admin (voeg admin-rol toe in bedrijven tabel als nodig)
if (!isset($_SESSION['bedrijf_id']) || $_SESSION['bedrijf_id'] != 1) { // Voorbeeld: alleen bedrijf ID 1
	header('Location: dashboard_bedrijf.php');
	exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$jaar = (int)$_POST['jaar'];
	$land = $_POST['land'];

	if ($land === 'NL') {
		$data = [
			'schijf1_grens' => (float)$_POST['schijf1_grens'],
			'tarief1' => (float)$_POST['tarief1'],
			'tarief1_aow' => (float)$_POST['tarief1_aow'],
			'tarief2' => (float)$_POST['tarief2'],
			'premies_volks' => (float)$_POST['premies_volks'],
			'algemene_heffingskorting_max' => (float)$_POST['algemene_heffingskorting_max'],
			'arbeidskorting_max' => (float)$_POST['arbeidskorting_max'],
			'minimumloon_uur' => (float)$_POST['minimumloon_uur'],
			'aow_franchise' => (float)$_POST['aow_franchise'],
			'reiskosten_tarief' => (float)$_POST['reiskosten_tarief'],
			'vakantie_pct' => (float)$_POST['vakantie_pct']
		];

		// Check of jaar al bestaat
		$exists = db_getRow("SELECT id FROM regels_nl WHERE jaar = ?", [$jaar], 'i');
		if ($exists) {
			// Update
			$sets = [];
			$params = [];
			$types = '';
			foreach ($data as $key => $value) {
				$sets[] = "$key = ?";
				$params[] = $value;
				$types .= 'd';
			}
			$params[] = $jaar;
			$types .= 'i';
			db_execute("UPDATE regels_nl SET " . implode(', ', $sets) . " WHERE jaar = ?", $params, $types);
			$message = "Regels NL voor $jaar bijgewerkt!";
		} else {
			// Insert
			$columns = implode(', ', array_keys($data));
			$placeholders = str_repeat('?,', count($data) - 1) . '?';
			$types = str_repeat('d', count($data));
			db_execute("INSERT INTO regels_nl (jaar, $columns) VALUES (?, $placeholders)", array_merge([$jaar], array_values($data)), 'i' . $types);
			$message = "Regels NL voor $jaar toegevoegd!";
		}
	} elseif ($land === 'BE') {
		// Vergelijkbare logica voor BE
		// ... (velden uit regels_be)
		$message = "Regels BE voor $jaar bijgewerkt!";
	}
}
?>

<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="UTF-8">
	<title>Admin - Regels Bijwerken</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
	<div class="card shadow">
		<div class="card-header bg-danger text-white">
			<h4>Admin: Jaarlijkse Regels Update (NL/BE)</h4>
		</div>
		<div class="card-body">
			<?php if ($message): ?>
			<div class="alert alert-success"><?= $message ?></div>
			<?php endif; ?>

			<form method="POST">
				<div class="mb-3">
					<label>Jaar</label>
					<input type="number" name="jaar" class="form-control" value="<?= date('Y') + 1 ?>" required title="Voer het jaar in waarvoor je de regels wilt bijwerken">
				</div>
				<div class="mb-3">
					<label>Land</label>
					<select name="land" class="form-control" required title="Kies Nederland of België">
						<option value="NL">Nederland</option>
						<option value="BE">België</option>
					</select>
				</div>

				<!-- Velden voor NL (verberg/show met JS) -->
				<div id="nl_fields">
					<h5>Nederland</h5>
					<div class="row g-3">
						<div class="col-md-6">
							<label>Schijf 1 grens (€)</label>
							<input type="number" step="0.01" name="schijf1_grens" class="form-control" value="38883.00" title="Grens eerste belastingschijf">
						</div>
						<div class="col-md-6">
							<label>Tarief 1 (%)</label>
							<input type="number" step="0.01" name="tarief1" class="form-control" value="35.75" title="Tarief schijf 1 (niet-AOW)">
						</div>
						<!-- Voeg alle NL-velden toe met default 2026 waarden -->
					</div>
				</div>

				<!-- Velden voor BE (verberg/show met JS) -->
				<div id="be_fields" style="display:none;">
					<h5>België</h5>
					<div class="row g-3">
						<div class="col-md-6">
							<label>RSZ werknemer (%)</label>
							<input type="number" step="0.01" name="rsz_werknemer_pct" class="form-control" value="13.07">
						</div>
						<!-- Voeg alle BE-velden toe -->
					</div>
				</div>

				<button type="submit" class="btn btn-danger mt-4" title="Sla de nieuwe regels op voor het gekozen jaar">Regels Bijwerken</button>
			</form>
		</div>
	</div>
</div>

<script>
$('select[name="land"]').change(function() {
	if ($(this).val() === 'NL') {
		$('#nl_fields').show();
		$('#be_fields').hide();
	} else {
		$('#nl_fields').hide();
		$('#be_fields').show();
	}
}).trigger('change');
</script>
</body>
</html>