<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$roomId = $_GET['roomId'] ?? '';

if ($roomId) {
    // Sécurité : on ne garde que les caractères alphanumériques
    $roomId = preg_replace('/[^a-zA-Z0-9]/', '', $roomId);
    $file = "rooms/yam_$roomId.json";
}

if ($action === 'sync' && $roomId) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sauvegarde des données envoyées par le client
        file_put_contents($file, file_get_contents('php://input'));
        echo json_encode(["status" => "saved"]);
    } else {
        // Lecture des données pour synchronisation
        echo file_exists($file) ? file_get_contents($file) : json_encode(null);
    }
    exit;
}
?>