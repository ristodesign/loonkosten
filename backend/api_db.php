<?php
// backend/api_db.php - Centrale database laag voor AJAX calls
require_once '../config.php';

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

function db_query($sql, $params = [], $types = '') {
	$conn = getDb();
	$stmt = $conn->prepare($sql);
	if (!$stmt) {
		error_log("Prepare failed: " . $conn->error);
		json_response([], false, 'Database fout');
	}
	if ($params && $types) {
		$stmt->bind_param($types, ...$params);
	}
	$stmt->execute();
	return $stmt;
}

function db_getRow($sql, $params = [], $types = '') {
	$stmt = db_query($sql, $params, $types);
	$result = $stmt->get_result();
	return $result->fetch_assoc();
}

function db_getAll($sql, $params = [], $types = '') {
	$stmt = db_query($sql, $params, $types);
	$result = $stmt->get_result();
	return $result->fetch_all(MYSQLI_ASSOC);
}

function db_execute($sql, $params = [], $types = '') {
	$stmt = db_query($sql, $params, $types);
	return [
		'success' => $stmt->affected_rows > 0,
		'insert_id' => getDb()->insert_id
	];
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
?>