<?php
require_once 'config.php';

// DB helpers
function db_query($sql, $params = [], $types = '') {
    $conn = getDb();
    $stmt = $conn->prepare($sql);
    if (!$stmt) die('Prepare failed');
    if ($params) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt;
}

function db_getRow($sql, $params = [], $types = '') {
    $stmt = db_query($sql, $params, $types);
    return $stmt->get_result()->fetch_assoc();
}

function db_getAll($sql, $params = [], $types = '') {
    $stmt = db_query($sql, $params, $types);
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function db_execute($sql, $params = [], $types = '') {
    $stmt = db_query($sql, $params, $types);
    return $stmt->affected_rows > 0;
}

// Email
function sendEmail($to, $subject, $body) {
    require_once 'vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;
        $mail->setFrom(SMTP_FROM);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// Overige berekeningen (bijtelling, verzuim, etc.) zoals in vorige responses
// ... (voeg hier de volledige functions uit eerdere berichten toe)
?>
