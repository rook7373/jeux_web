<?php
// error_reporting(E_ALL); // Removed as it's not effective
// ini_set('display_errors', 1); // Removed as it's not effective
header('Content-Type: application/json');

function log_debug($message) {
    file_put_contents('/tmp/api_ship_debug.log', date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, FILE_APPEND);
}

log_debug('API Request Received: ' . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
log_debug('POST Data: ' . file_get_contents('php://input'));

// Global error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $error_message = "PHP Error: $errstr (Line $errline) in $errfile";
    log_debug($error_message);
    http_response_code(200); // Keep 200 for JS to parse JSON, even if it's an error
    echo json_encode(['success' => false, 'error' => $error_message]);
    exit;
});

// Global exception handler
set_exception_handler(function($exception) {
    $error_message = "Exception: " . $exception->getMessage() . " (Line " . $exception->getLine() . ") in " . $exception->getFile();
    log_debug($error_message);
    http_response_code(200); // Keep 200 for JS to parse JSON, even if it's an error
    echo json_encode(['success' => false, 'error' => $error_message]);
    exit;
});

$action = $_GET['action'] ?? '';
$roomId = $_GET['roomId'] ?? '';
// Use a prefix for ship games to avoid conflicts with other games
$roomFile = __DIR__ . '/rooms/ship_' . $roomId . '.json';

function getGameState($roomFile) {
    log_debug("getGameState: Checking for file: $roomFile");
    if (file_exists($roomFile)) {
        $content = file_get_contents($roomFile);
        log_debug("getGameState: File content: " . substr($content, 0, 200) . '...'); // Log first 200 chars
        return json_decode($content, true);
    }
    log_debug("getGameState: File not found: $roomFile");
    return null;
}

function saveGameState($roomFile, $gameState) {
    log_debug("saveGameState: Attempting to save to: $roomFile");
    $json_data = json_encode($gameState, JSON_PRETTY_PRINT);
    if ($json_data === false) {
        log_debug("saveGameState: json_encode failed: " . json_last_error_msg());
        throw new Exception("Failed to encode game state to JSON.");
    }
    $result = file_put_contents($roomFile, $json_data);
    if ($result === false) {
        log_debug("saveGameState: file_put_contents failed.");
        throw new Exception("Failed to write game state to file: $roomFile");
    }
    log_debug("saveGameState: Successfully saved " . strlen($json_data) . " bytes.");
}

function generateShips() {
    log_debug("generateShips: Starting ship generation.");
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
                    log_debug("generateShips: Placed ship of size $size after $attempts attempts.");
                }
            }
        } // End of while loop

        if (!$placed) {
            $error_message = "Failed to place ship of size $size after $attempts attempts.";
            log_debug("generateShips: " . $error_message);
            throw new Exception($error_message);
        }
    }
    log_debug("generateShips: Finished ship generation. Total ships: " . count($ships));
    return $ships;
}


switch ($action) {
    case 'init_ai_game':
        log_debug('init_ai_game: Action received.');
        try {
            $playerShipsData = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON input: " . json_last_error_msg());
            }
            log_debug('init_ai_game: Player data decoded.');
            $playerShipsCoords = $playerShipsData['playerShips'] ?? [];
            $playerName = $playerShipsData['playerName'] ?? 'Player';
            
            // Ensure playerShipsCoords is an array and cast to integers
            if (!is_array($playerShipsCoords)) {
                $playerShipsCoords = [];
                log_debug('init_ai_game: playerShipsCoords was not an array, initialized to empty.');
            }
            $playerShipsCoords = array_map('intval', $playerShipsCoords);
            
            // Group player ship coordinates into separate ships based on positions
            // For now, create a single ship entry with all coords (simplified approach)
            $playerShips = [];
            if (!empty($playerShipsCoords)) {
                $playerShips[] = ['coords' => $playerShipsCoords, 'hits' => []];
            }
            log_debug('init_ai_game: Player ships processed.');
            
            $aiShips = generateShips();
            log_debug('init_ai_game: AI ships generated.');

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
                log_debug('init_ai_game: Creating rooms directory.');
                if (!mkdir(__DIR__ . '/rooms', 0755, true)) {
                    throw new Exception("Failed to create rooms directory.");
                }
                log_debug('init_ai_game: Rooms directory created.');
            }
            
            saveGameState($roomFile, $gameState);
            log_debug('init_ai_game: Game state saved.');
            echo json_encode(['success' => true, 'roomId' => $roomId, 'gameState' => $gameState]);
            log_debug('init_ai_game: Response sent.');

        } catch (Exception $e) {
            log_debug('init_ai_game: Caught exception - ' . $e->getMessage());
            http_response_code(200);
            echo json_encode(['success' => false, 'error' => "Init AI Game Exception: " . $e->getMessage()]);
        }
        break;

    case 'shoot':
        log_debug('shoot: Action received.');
        try {
            $gameState = getGameState($roomFile);
            if (!$gameState) {
                throw new Exception('Game not found or not initialized.');
            }

            $requestData = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Invalid JSON input for shoot: " . json_last_error_msg());
            }
            $shotIdx = isset($requestData['shotIdx']) ? intval($requestData['shotIdx']) : -1;
            $playerIdx = isset($requestData['playerIdx']) ? intval($requestData['playerIdx']) : -1;

            // Validate turn
            if ($gameState['turn'] !== $playerIdx) {
                throw new Exception('Not your turn.');
            }

            $opponentIdx = 1 - $playerIdx;
            
            // Ensure shotsReceived is an array before pushing
            if (!isset($gameState['players'][$opponentIdx]['shotsReceived']) || !is_array($gameState['players'][$opponentIdx]['shotsReceived'])) {
                $gameState['players'][$opponentIdx]['shotsReceived'] = [];
            }

            // Prevent shooting the same spot twice
            if (in_array($shotIdx, $gameState['players'][$opponentIdx]['shotsReceived'])) {
                throw new Exception('Already shot this spot.');
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
        } catch (Exception $e) {
            log_debug('shoot: Caught exception - ' . $e->getMessage());
            http_response_code(200);
            echo json_encode(['success' => false, 'error' => "Shoot Exception: " . $e->getMessage()]);
        }
        break;

    case 'sync':
        log_debug('sync: Action received.');
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Save state
                $data = json_decode(file_get_contents('php://input'), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception("Invalid JSON input for sync POST: " . json_last_error_msg());
                }
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
        } catch (Exception $e) {
            log_debug('sync: Caught exception - ' . $e->getMessage());
            http_response_code(200);
            echo json_encode(['success' => false, 'error' => "Sync Exception: " . $e->getMessage()]);
        }
        break;

    case 'ai_shoot':
        log_debug('ai_shoot: Action received.');
        try {
            $gameState = getGameState($roomFile);
            if (!$gameState) {
                throw new Exception('Game state not found');
            }
            
            if ($gameState['turn'] !== 1) {
                throw new Exception('Not AI\'s turn');
            }

            // Get player shots received (ensure it's an array)
            $playerShotsReceived = isset($gameState['players'][0]['shotsReceived']) ? 
                                (is_array($gameState['players'][0]['shotsReceived']) ? 
                                    $gameState['players'][0]['shotsReceived'] : []) : [];
            
            $aiShot = getAIMove($playerShotsReceived);

            if ($aiShot === -1) {
                throw new Exception('AI could not find an available shot.');
            }

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

            saveGameState($roomFile, $gameState);
            echo json_encode(['success' => true, 'aiShot' => $aiShot, 'gameState' => $gameState]);
        } catch (Exception $e) {
            log_debug('ai_shoot: Caught exception - ' . $e->getMessage());
            http_response_code(200);
            echo json_encode(['success' => false, 'error' => "AI Shoot Exception: " . $e->getMessage()]);
        }
        break;

    case 'reset':
        log_debug('reset: Action received.');
        if (file_exists($roomFile)) {
            if (!unlink($roomFile)) {
                log_debug("reset: Failed to unlink file: $roomFile");
                throw new Exception("Failed to delete room file.");
            }
            log_debug("reset: Deleted file: $roomFile");
        }
        echo json_encode(['success' => true]);
        break;

    default:
        log_debug('Invalid action: ' . $action);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
?>
