<?php
// logout.php - Uitloggen (bedrijf of werknemer)
session_start();
session_destroy(); // Alle sessie-data verwijderen

// Redirect naar login (bedrijf of werknemer – detecteer via URL of standaard naar bedrijf)
header('Location: login_bedrijf.php');
exit;
?>