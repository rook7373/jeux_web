<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

// Créer le répertoire rooms s'il n'existe pas
if (!is_dir('rooms')) {
    mkdir('rooms', 0777, true);
}

$action = $_GET['action'] ?? '';
$roomId = $_GET['roomId'] ?? '';

if ($action === 'sync' && $roomId) {
    $file = "rooms/room_$roomId.json";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = file_get_contents('php://input');
        $written = file_put_contents($file, $data);
        // Vérifier que le fichier a été écrit
        $fileExists = file_exists($file);
        $fileSize = filesize($file);
        error_log("POST room $roomId: written=$written, exists=$fileExists, size=$fileSize");
        echo json_encode(["status" => "saved", "size" => $written, "exists" => $fileExists]);
    } else {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            error_log("GET room $roomId: returning data, size=" . strlen($content));
            echo $content;
        } else {
            error_log("GET room $roomId: file not found");
            echo json_encode(null);
        }
    }
    exit;
}

if ($action === 'stats') {
    $file = 'stats.json';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        file_put_contents($file, file_get_contents('php://input'));
        echo json_encode(["status" => "stats_saved"]);
    } else {
        echo file_exists($file) ? file_get_contents($file) : json_encode([]);
    }
    exit;
}

