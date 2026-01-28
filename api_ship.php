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
// Use a prefix for ship games to avoid conflicts with other games
$roomFile = __DIR__ . '/rooms/ship_' . $roomId . '.json';

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
    $occupiedCells = []; // Stores actual ship cells AND their buffer zones

    foreach ($shipSizes as $size) {
        $placed = false;
        $attempts = 0; // Safety counter
        while (!$placed && $attempts < 1000) { // Limit attempts to prevent infinite loops
            $attempts++;
            $horizontal = (bool)rand(0, 1);
            $startRow = rand(0, 9);
            $startCol = rand(0, 9);

            $currentShipCoords = []; // Cells the current ship would occupy
            $currentBufferCoords = []; // Cells for the buffer zone around the current ship

            $validPlacement = true;

            for ($i = 0; $i < $size; $i++) {
                $r = $horizontal ? $startRow : $startRow + $i;
                $c = $horizontal ? $startCol + $i : $startCol;

                // Check bounds for the ship itself
                if ($r < 0 || $r >= 10 || $c < 0 || $c >= 10) {
                    $validPlacement = false;
                    break;
                }
                $coord = $r * 10 + $c;
                $currentShipCoords[] = $coord;

                // Calculate buffer cells around this segment of the ship
                for ($dr = -1; $dr <= 1; $dr++) {
                    for ($dc = -1; $dc <= 1; $dc++) {
                        $adjR = $r + $dr;
                        $adjC = $c + $dc;
                        // Only add valid grid coordinates to buffer
                        if ($adjR >= 0 && $adjR < 10 && $adjC >= 0 && $adjC < 10) {
                            $currentBufferCoords[] = $adjR * 10 + $adjC;
                        }
                    }
                }
            }

            if ($validPlacement) {
                // Combine ship and its buffer for collision check
                $potentialOccupied = array_unique(array_merge($currentShipCoords, $currentBufferCoords));
                
                // Check for overlap with already occupied cells (ships + their buffers)
                if (count(array_intersect($potentialOccupied, $occupiedCells)) === 0) {
                    // Valid placement found!
                    $ships[] = ['coords' => $currentShipCoords, 'hits' => []];
                    $occupiedCells = array_merge($occupiedCells, $potentialOccupied);
                    $placed = true;
                }
            }
        } // End of while loop

        if (!$placed) {
            // This case indicates that after many attempts, a ship could not be placed.
            error_log("Failed to place ship of size $size after $attempts attempts.");
            throw new Exception("Failed to place all AI ships.");
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
        
        // Ensure playerShipsCoords is an array and cast to integers
        if (!is_array($playerShipsCoords)) {
            $playerShipsCoords = [];
        }
        $playerShipsCoords = array_map('intval', $playerShipsCoords);
        
        // Group player ship coordinates into separate ships based on positions
        // For now, create a single ship entry with all coords (simplified approach)
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
            'turn' => 0,
            'status' => 'playing',
            'winner' => null,
        ];
        
        // Ensure directory exists
        if (!is_dir(__DIR__ . '/rooms')) {
            mkdir(__DIR__ . '/rooms', 0755, true);
        }
        
        saveGameState($roomFile, $gameState);
        echo json_encode(['success' => true, 'roomId' => $roomId, 'gameState' => $gameState]);
        break;

    case 'shoot':
        $gameState = getGameState($roomFile);
        if (!$gameState) {
            echo json_encode(['success' => false, 'error' => 'Game not found or not initialized.']);
            exit;
        }

        $requestData = json_decode(file_get_contents('php://input'), true);
        $shotIdx = isset($requestData['shotIdx']) ? intval($requestData['shotIdx']) : -1;
        $playerIdx = isset($requestData['playerIdx']) ? intval($requestData['playerIdx']) : -1;

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
        if (isset($gameState['players'][$opponentIdx]['ships']) && is_array($gameState['players'][$opponentIdx]['ships'])) {
            foreach ($gameState['players'][$opponentIdx]['ships'] as &$ship) {
                if (!isset($ship['coords']) || !is_array($ship['coords'])) continue;
                
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
        if (!$gameState) {
            echo json_encode(['success' => false, 'error' => 'Game state not found']);
            exit;
        }
        
        if ($gameState['turn'] !== 1) {
            echo json_encode(['success' => false, 'error' => 'Not AI\'s turn']);
            exit;
        }

        // Get player shots received (ensure it's an array)
        $playerShotsReceived = isset($gameState['players'][0]['shotsReceived']) ? 
                               (is_array($gameState['players'][0]['shotsReceived']) ? 
                                $gameState['players'][0]['shotsReceived'] : []) : [];
        
        $aiShot = getAIMove($playerShotsReceived);

        if ($aiShot === -1) {
            echo json_encode(['success' => false, 'error' => 'AI could not find an available shot.']);
            exit;
        }

        // If AI shot is found
        $gameState['players'][0]['shotsReceived'][] = $aiShot;
            
            // Collect all player ship coordinates
            $playerShipsCoords = [];
            if (isset($gameState['players'][0]['ships']) && is_array($gameState['players'][0]['ships'])) {
                foreach($gameState['players'][0]['ships'] as $ship) {
                    if (isset($ship['coords']) && is_array($ship['coords'])) {
                        $playerShipsCoords = array_merge($playerShipsCoords, $ship['coords']);
                    }
                }
            }
            
            // Check if AI hit
            $isHit = in_array($aiShot, $playerShipsCoords);

            // Check for game over (AI wins)
            $playerShipsRemaining = false;
            if (isset($gameState['players'][0]['ships']) && is_array($gameState['players'][0]['ships'])) {
                foreach ($gameState['players'][0]['ships'] as $ship) {
                    if (!isset($ship['coords']) || !is_array($ship['coords'])) continue;
                    
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