<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$roomId = $_GET['roomId'] ?? '';

if ($roomId) {
    $roomId = preg_replace('/[^a-zA-Z0-9]/', '', $roomId);
    $file = "rooms/room_$roomId.json";
}

if ($action === 'sync' && $roomId) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newData = json_decode(file_get_contents('php://input'), true);
        
        // --- LOGIQUE DE VERROUILLAGE ---
        if (file_exists($file)) {
            $currentData = json_decode(file_get_contents($file), true);
            // Si le serveur est déjà en validation, on interdit de repasser en 'playing'
            if (isset($currentData['status']) && $currentData['status'] === 'validation') {
                if (isset($newData['status']) && $newData['status'] === 'playing') {
                    $newData['status'] = 'validation'; // On force le maintien du STOP
                }
            }
        }
        
        file_put_contents($file, json_encode($newData));
        echo json_encode(["status" => "saved"]);
    } else {
        echo file_exists($file) ? file_get_contents($file) : json_encode(null);
    }
    exit;
}
?>