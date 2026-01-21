<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$roomId = $_GET['roomId'] ?? '';

if ($action === 'sync' && $roomId) {
    $file = "rooms/room_$roomId.json";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = file_get_contents('php://input');
        file_put_contents($file, $data);
        // Immédiatement relire pour vérifier
        $saved = file_get_contents($file);
        echo json_encode(["status" => "saved", "size" => strlen($saved)]);
    } else {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            echo $content;
        } else {
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

