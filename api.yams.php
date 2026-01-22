<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$roomId = $_GET['roomId'] ?? '';

if ($roomId) {
    $roomId = preg_replace('/[^a-zA-Z0-9]/', '', $roomId);
    $file = "rooms/yam_$roomId.json";
    // Créer le dossier rooms s'il n'existe pas
    if (!file_exists('rooms')) { mkdir('rooms', 0777, true); }
}

if ($action === 'sync' && $roomId) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        file_put_contents($file, file_get_contents('php://input'));
        echo json_encode(["status" => "saved"]);
    } else {
        echo file_exists($file) ? file_get_contents($file) : json_encode(null);
    }
    exit;
}
?>