<?php
// dashboard_bedrijf.php - Hoofddashboard voor bedrijf
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

// Medewerkers ophalen met contractgegevens
$medewerkers = db_getAll("
	SELECT w.id, w.naam, w.email, w.geboortedatum, w.statuur,
		   c.type AS contract_type, c.bruto_salaris, c.contract_uren_per_week,
		   c.reiskosten_recht, c.verzuimverzekering_actief,
		   c.heeft_auto_van_de_zaak, c.heeft_13e_maand
	FROM werknemers w
	JOIN contracten c ON w.id = c.werknemer_id
	WHERE w.bedrijf_id = ?
	ORDER BY w.naam
", [$bedrijf_id], 'i');

// Statistieken
$aantal_medewerkers = count($medewerkers);
$gem_salaris = $aantal_medewerkers > 0 ? array_sum(array_column($medewerkers, 'bruto_salaris')) / $aantal_medewerkers : 0;
$gem_uren = $aantal_medewerkers > 0 ? array_sum(array_column($medewerkers, 'contract_uren_per_week')) / $aantal_medewerkers : 0;
$aantal_verzuimverzekerd = count(array_filter($medewerkers, fn($m) => $m['verzuimverzekering_actief']));
?>

<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Dashboard - Loonkosten.nl</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<style>
		.tooltip-inner { max-width: 300px; }
		.card-hover { transition: transform 0.2s; }
		.card-hover:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
		.stat-card { background: linear-gradient(135deg, #007bff 0%, #00bfff 100%); color: white; border: none; }
	</style>
</head>
<body class="bg-light">
	<!-- Navbar -->
	<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
		<div class="container-fluid">
			<a class="navbar-brand fw-bold" href="dashboard_bedrijf.php">
				<?php if ($bedrijf['logo_path'] && file_exists($bedrijf['logo_path'])): ?>
					<img src="<?= htmlspecialchars($bedrijf['logo_path']) ?>" alt="Logo" height="40" class="me-2">
				<?php endif; ?>
				Loonkosten.nl
			</a>
			<div class="navbar-nav ms-auto">
				<span class="navbar-text me-3 text-white">Welkom, <?= htmlspecialchars($bedrijf['naam']) ?> (<?= strtoupper($land) ?>)</span>
				<a href="logout.php" class="btn btn-outline-light" title="Uitloggen van je bedrijfssessie">Uitloggen</a>
			</div>
		</div>
	</nav>

	<div class="container mt-5 mb-5">
		<!-- Statistieken -->
		<div class="row mb-4 g-4">
			<div class="col-md-3">
				<div class="card stat-card card-hover text-center">
					<div class="card-body">
						<i class="fas fa-users fa-3x mb-3"></i>
						<h5>Medewerkers</h5>
						<h2><?= $aantal_medewerkers ?></h2>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card stat-card card-hover text-center">
					<div class="card-body">
						<i class="fas fa-euro-sign fa-3x mb-3"></i>
						<h5>Gem. bruto salaris</h5>
						<h2>€ <?= number_format($gem_salaris, 0) ?></h2>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card stat-card card-hover text-center">
					<div class="card-body">
						<i class="fas fa-clock fa-3x mb-3"></i>
						<h5>Gem. uren/week</h5>
						<h2><?= number_format($gem_uren, 1) ?></h2>
					</div>
				</div>
			</div>
			<div class="col-md-3">
				<div class="card stat-card card-hover text-center">
					<div class="card-body">
						<i class="fas fa-shield-alt fa-3x mb-3"></i>
						<h5>Verzuimverzekerd</h5>
						<h2><?= $aantal_verzuimverzekerd ?></h2>
					</div>
				</div>
			</div>
		</div>

		<!-- Actieknoppen -->
		<div class="row mb-4">
			<div class="col-12 text-end">
				<button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addEmployeeModal" title="Voeg een nieuwe medewerker toe met volledig contract">
					<i class="fas fa-user-plus"></i> Nieuwe medewerker
				</button>
				<a href="ziekteverzuim_calculator.php" class="btn btn-warning me-2" title="Bereken de kosten van ziekteverzuim voor je bedrijf">
					<i class="fas fa-calculator"></i> Verzuim calculator
				</a>
				<button class="btn btn-info text-white" title="Upload je bedrijfslogo (zichtbaar op contracten en loonstroken)">
					<i class="fas fa-upload"></i> Logo uploaden
				</button>
			</div>
		</div>

		<!-- Medewerkers tabel -->
		<div class="card shadow">
			<div class="card-header bg-primary text-white">
				<h4><i class="fas fa-users me-2"></i>Medewerkers overzicht</h4>
			</div>
			<div class="card-body p-0">
				<?php if (empty($medewerkers)): ?>
					<div class="p-4 text-center text-muted">
						Nog geen medewerkers. Voeg je eerste medewerker toe!
					</div>
				<?php else: ?>
				<div class="table-responsive">
					<table class="table table-hover mb-0">
						<thead class="table-light">
							<tr>
								<th>Naam</th>
								<th>Email</th>
								<th>Contract</th>
								<th>Bruto salaris</th>
								<th>Uren/week</th>
								<th>Auto v.d. zaak</th>
								<th>Verzuimverzekering</th>
								<th>Acties</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($medewerkers as $m): ?>
							<tr>
								<td><strong><?= htmlspecialchars($m['naam']) ?></strong></td>
								<td><?= htmlspecialchars($m['email']) ?></td>
								<td>
									<span class="badge bg-secondary" title="Contracttype bepaalt toeslagen en opbouw"><?= $m['contract_type'] ?></span>
									<?php if ($land === 'BE' && $m['heeft_13e_maand']): ?>
										<br><small class="text-success" title="Heeft recht op 13e maand">13e maand</small>
									<?php endif; ?>
								</td>
								<td>€ <?= number_format($m['bruto_salaris'], 2) ?></td>
								<td><?= $m['contract_uren_per_week'] ?></td>
								<td>
									<?php if ($m['heeft_auto_van_de_zaak']): ?>
										<i class="fas fa-car text-success" title="Heeft auto van de zaak (bijtelling/VAA toegepast)"></i>
									<?php else: ?>
										<i class="fas fa-times text-muted"></i>
									<?php endif; ?>
								</td>
								<td>
									<?php if ($m['verzuimverzekering_actief']): ?>
										<span class="badge bg-success" title="Verzuimverzekering actief voor deze medewerker">Ja</span>
									<?php else: ?>
										<span class="badge bg-danger" title="Geen verzuimverzekering – volledige loondoorbetaling bij ziekte">Nee</span>
									<?php endif; ?>
								</td>
								<td>
									<div class="btn-group" role="group">
										<a href="generate_payslip.php?werknemer_id=<?= $m['id'] ?>" 
										   class="btn btn-primary btn-sm" 
										   title="Genereer loonstrook inclusief alle toeslagen, bijtelling en inhoudingen">
											<i class="fas fa-file-invoice-dollar"></i>
										</a>
										<a href="generate_contract.php?werknemer_id=<?= $m['id'] ?>" 
										   class="btn btn-success btn-sm" 
										   title="Genereer arbeidscontract met bedrijfsclausules en logo">
											<i class="fas fa-file-contract"></i>
										</a>
										<button class="btn btn-outline-secondary btn-sm" 
												title="Bewerk medewerkergegevens en contract"
												onclick="editEmployee(<?= $m['id'] ?>)">
											<i class="fas fa-edit"></i>
										</button>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Tooltips activeren -->
	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		const tooltipTriggerList = document.querySelectorAll('[title]');
		const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
	</script>
</body>
</html>