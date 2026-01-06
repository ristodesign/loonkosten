<?php
// backend/api_db.php - Centrale database laag voor AJAX calls
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// CSRF check
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if ($_POST['csrf_token'] ?? '' !== CSRF_TOKEN) {
		json_response([], false, 'Ongeldige CSRF-token');
	}
}
?>