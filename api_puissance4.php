<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$roomId = $_GET['roomId'] ?? '';
$statsFile = 'stats.json'; // Fichier dédié aux scores du Puissance 4

if ($roomId) {
    $roomId = preg_replace('/[^a-zA-Z0-9]/', '', $roomId);
    $file = "rooms/p4_$roomId.json";
}

// 1. GESTION DES SCORES
if ($action === 'stats') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        file_put_contents($statsFile, file_get_contents('php://input'));
        echo json_encode(["status" => "saved"]);
    } else {
        echo file_exists($statsFile) ? file_get_contents($statsFile) : json_encode([]);
    }
    exit;
}

// 2. GESTION DU MULTIJOUEUR (SYNC)
if ($action === 'sync' && $roomId) {
    if (!is_dir('rooms')) { mkdir('rooms', 0777, true); }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newData = json_decode(file_get_contents('php://input'), true);
        
        if (file_exists($file)) {
            $currentData = json_decode(file_get_contents($file), true);
            if (isset($currentData['status']) && $currentData['status'] === 'validation') {
                if (isset($newData['status']) && $newData['status'] === 'playing') {
                    $newData['status'] = 'validation'; 
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