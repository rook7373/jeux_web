<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$roomId = $_GET['roomId'] ?? '';

if ($roomId) {
    $roomId = preg_replace('/[^a-zA-Z0-9]/', '', $roomId);
}

if ($action === 'sync' && $roomId) {
    $file = "rooms/room_$roomId.json";
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = file_get_contents('php://input');
        file_put_contents($file, $data);
        echo json_encode(["status" => "saved"]);
    } else {
        echo file_exists($file) ? file_get_contents($file) : json_encode(null);
    }
    exit;
}