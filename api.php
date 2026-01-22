<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';
$roomId = $_GET['roomId'] ?? '';
$statsFile = 'stats.json'; // Nom du fichier où sont stockés les scores

// Nettoyage du roomId si présent
if ($roomId) {
    $roomId = preg_replace('/[^a-zA-Z0-9]/', '', $roomId);
    $file = "rooms/room_$roomId.json";
}

// --- 1. GESTION DES STATISTIQUES (stats.json) ---
if ($action === 'stats') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // On récupère le JSON envoyé et on l'écrase dans stats.json
        $jsonStats = file_get_contents('php://input');
        file_put_contents($statsFile, $jsonStats);
        echo json_encode(["status" => "saved"]);
    } else {
        // On renvoie le contenu de stats.json ou un tableau vide s'il n'existe pas
        if (file_exists($statsFile)) {
            echo file_get_contents($statsFile);
        } else {
            echo json_encode([]);
        }
    }
    exit;
}

// --- 2. GESTION DU MULTIJOUEUR (Salons de jeu) ---
if ($action === 'sync' && $roomId) {
    // Créer le dossier rooms s'il n'existe pas
    if (!is_dir('rooms')) { mkdir('rooms', 0777, true); }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newData = json_decode(file_get_contents('php://input'), true);
        
        if (file_exists($file)) {
            $currentData = json_decode(file_get_contents($file), true);
            // Verrouillage du statut 'validation' pour éviter les conflits
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