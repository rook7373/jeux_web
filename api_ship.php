<?php
header('Content-Type: application/json');

// Global error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => "PHP Error: $errstr (Line $errline)"]);
    exit;
});

// Global exception handler
set_exception_handler(function($exception) {
    http_response_code(200);
    echo json_encode(['success' => false, 'error' => "Exception: " . $exception->getMessage()]);
    exit;
});

$action = $_GET['action'] ?? '';
$roomId = $_GET['roomId'] ?? '';
$roomFile = __DIR__ . '/rooms/room_' . $roomId . '.json';

function getGameState($roomFile) {
    if (file_exists($roomFile)) {
        return json_decode(file_get_contents($roomFile), true);
    }
    return null;
}

function saveGameState($roomFile, $gameState) {
    file_put_contents($roomFile, json_encode($gameState, JSON_PRETTY_PRINT));
}

function generateShips() {
    $shipSizes = [5, 4, 3, 3, 2];
    $ships = [];
    $occupiedCells = [];

    foreach ($shipSizes as $size) {
        $placed = false;
        while (!$placed) {
            $horizontal = (bool)rand(0, 1);
            $startRow = rand(0, 9);
            $startCol = rand(0, 9);

            $shipCoords = [];
            $valid = true;

            for ($i = 0; $i < $size; $i++) {
                $r = $horizontal ? $startRow : $startRow + $i;
                $c = $horizontal ? $startCol + $i : $startCol;

                if ($r < 0 || $r >= 10 || $c < 0 || $c >= 10) {
                    $valid = false;
                    break;
                }
                $coord = $r * 10 + $c;
                if (in_array($coord, $occupiedCells)) {
                    $valid = false;
                    break;
                }
                // Check surrounding cells for buffer
                for ($dr = -1; $dr <= 1; $dr++) {
                    for ($dc = -1; $dc <= 1; $dc++) {
                        if ($dr == 0 && $dc == 0) continue;
                        $adjR = $r + $dr;
                        $adjC = $c + $dc;
                        $adjCoord = $adjR * 10 + $adjC;
                        if (in_array($adjCoord, $occupiedCells)) {
                            $valid = false;
                            break 2;
                        }
                    }
                }
                $shipCoords[] = $coord;
            }

            if ($valid) {
                $ships[] = ['coords' => $shipCoords, 'hits' => []];
                $occupiedCells = array_merge($occupiedCells, $shipCoords);
                $placed = true;
            }
        }
    }
    return $ships;
}


// Basic AI for shooting
function getAIMove($playerBoardShots) {
    $availableShots = [];
    for ($i = 0; $i < 100; $i++) {
        if (!in_array($i, $playerBoardShots)) {
            $availableShots[] = $i;
        }
    }

    if (empty($availableShots)) {
        return -1; // No more shots available
    }

    // Simple AI: just pick a random available shot
    return $availableShots[array_rand($availableShots)];
}


switch ($action) {
    case 'init_ai_game':
        $playerShipsData = json_decode(file_get_contents('php://input'), true);
        $playerShipsCoords = $playerShipsData['playerShips'] ?? [];
        $playerName = $playerShipsData['playerName'] ?? 'Player';
        
        // Normalize player ships to the same structure as AI ships
        $playerShips = [];
        if (!empty($playerShipsCoords)) {
            $playerShips[] = ['coords' => $playerShipsCoords, 'hits' => []];
        }
        
        $aiShips = generateShips();

        $gameState = [
            'players' => [
                [
                    'name' => $playerName,
                    'ships' => $playerShips,
                    'shotsReceived' => [],
                    'ready' => true,
                ],
                [
                    'name' => 'AI',
                    'ships' => $aiShips,
                    'shotsReceived' => [],
                    'ready' => true,
                ]
            ],
            'turn' => 0, // 0 for player, 1 for AI
            'status' => 'playing',
            'winner' => null,
        ];
        saveGameState($roomFile, $gameState);
        echo json_encode(['success' => true, 'roomId' => $roomId, 'gameState' => $gameState]); // Return full gameState
        break;

    case 'shoot':
        $gameState = getGameState($roomFile);
        if (!$gameState) {
            echo json_encode(['success' => false, 'error' => 'Game not found or not initialized.']);
            exit;
        }

        $requestData = json_decode(file_get_contents('php://input'), true);
        $shotIdx = $requestData['shotIdx'];
        $playerIdx = $requestData['playerIdx'];

        // Validate turn
        if ($gameState['turn'] !== $playerIdx) {
            echo json_encode(['success' => false, 'error' => 'Not your turn.']);
            exit;
        }

        $opponentIdx = 1 - $playerIdx;
        
        // Ensure shotsReceived is an array before pushing
        if (!isset($gameState['players'][$opponentIdx]['shotsReceived']) || !is_array($gameState['players'][$opponentIdx]['shotsReceived'])) {
            $gameState['players'][$opponentIdx]['shotsReceived'] = [];
        }

        // Prevent shooting the same spot twice
        if (in_array($shotIdx, $gameState['players'][$opponentIdx]['shotsReceived'])) {
            echo json_encode(['success' => false, 'error' => 'Already shot this spot.']);
            exit;
        }

        $gameState['players'][$opponentIdx]['shotsReceived'][] = $shotIdx;

        // Check for game over (current player wins)
        $opponentShipsRemaining = false;
        foreach ($gameState['players'][$opponentIdx]['ships'] as &$ship) { // Use reference to modify 'hits' if needed later
            $shipSunk = true;
            foreach ($ship['coords'] as $coord) {
                if (!in_array($coord, $gameState['players'][$opponentIdx]['shotsReceived'])) {
                    $shipSunk = false;
                    break;
                }
            }
            if (!$shipSunk) {
                $opponentShipsRemaining = true;
                break;
            }
        }

        if (!$opponentShipsRemaining) {
            $gameState['status'] = 'finished';
            $gameState['winner'] = $gameState['players'][$playerIdx]['name'];
        } else {
            $gameState['turn'] = $opponentIdx; // Switch to opponent's turn
        }

        saveGameState($roomFile, $gameState);
        echo json_encode(['success' => true, 'gameState' => $gameState]);
        break;

    case 'sync':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Save state
            $data = json_decode(file_get_contents('php://input'), true);
            saveGameState($roomFile, $data);
            echo json_encode(['success' => true]);
        } else {
            // Get state
            $gameState = getGameState($roomFile);
            if ($gameState) {
                echo json_encode($gameState);
            } else {
                echo json_encode(['error' => 'Game not found or not initialized.']);
            }
        }
        break;

    case 'ai_shoot':
        $gameState = getGameState($roomFile);
        if (!$gameState || $gameState['turn'] !== 1) { // Ensure it's AI's turn
            echo json_encode(['error' => 'Not AI\'s turn or game not initialized.']);
            exit;
        }

        $playerShotsReceived = $gameState['players'][0]['shotsReceived'];
        $aiShot = getAIMove($playerShotsReceived);

        if ($aiShot !== -1) {
            $gameState['players'][0]['shotsReceived'][] = $aiShot;
            // Check if AI hit a player ship
            $playerShipsCoords = [];
            foreach($gameState['players'][0]['ships'] as $ship) {
                $playerShipsCoords = array_merge($playerShipsCoords, $ship['coords']);
            }
            $isHit = in_array($aiShot, $playerShipsCoords);

            // Check for game over (AI wins)
            $playerShipsRemaining = false;
            foreach ($gameState['players'][0]['ships'] as $ship) {
                $shipSunk = true;
                foreach ($ship['coords'] as $coord) {
                    if (!in_array($coord, $gameState['players'][0]['shotsReceived'])) {
                        $shipSunk = false;
                        break;
                    }
                }
                if (!$shipSunk) {
                    $playerShipsRemaining = true;
                    break;
                }
            }

            if (!$playerShipsRemaining) {
                $gameState['status'] = 'finished';
                $gameState['winner'] = 'AI';
            } else {
                $gameState['turn'] = 0; // Switch back to player's turn
            }
        }

        saveGameState($roomFile, $gameState);
        echo json_encode(['success' => true, 'aiShot' => $aiShot, 'gameState' => $gameState]);
        break;

    case 'reset':
        if (file_exists($roomFile)) {
            unlink($roomFile);
        }
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>