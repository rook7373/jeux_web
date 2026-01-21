<?php
// --- PARTIE SERVEUR (PHP) ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

// Si c'est une requête API (sync ou stats)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // Créer le répertoire rooms s'il n'existe pas
    if (!is_dir('rooms')) {
        mkdir('rooms', 0777, true);
    }

    $action = $_GET['action'];
    $roomId = $_GET['roomId'] ?? '';

    if ($action === 'sync' && $roomId) {
        $file = "rooms/room_$roomId.json";
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = file_get_contents('php://input');
            $written = file_put_contents($file, $data);
            echo json_encode(["status" => "saved", "size" => $written]);
        } else {
            if (file_exists($file)) {
                echo file_get_contents($file);
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
}
// Si ce n'est pas une requête API, on continue vers l'affichage du HTML
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Puissance 4 - ScoreMaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        #board {
            background-color: #0369a1;
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.75rem;
            padding: 1rem;
            border-radius: 1.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border: 8px solid #075985;
            width: 100%;
            max-width: 600px;
        }
        .cell {
            background-color: #f8fafc;
            border-radius: 50%;
            aspect-ratio: 1 / 1;
            transition: background-color 0.2s;
            min-width: 40px;
        }
        .column {
            cursor: pointer;
            border-radius: 0.5rem;
            transition: background-color 0.2s;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        .column:hover { background-color: rgba(255,255,255,0.2); }
        .cell.red { background-color: #ef4444; box-shadow: inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.yellow { background-color: #f59e0b; box-shadow: inset 0 -4px 6px rgba(0,0,0,0.3); }
    </style>
</head>
<body class="bg-slate-100 min-h-screen p-4 font-sans text-slate-900 flex items-center justify-center">

    <div id="setup" class="max-w-md w-full">
        <div class="bg-white p-6 rounded-[2.5rem] shadow-2xl">
            <h2 class="text-3xl font-black mb-6 text-sky-600 uppercase italic tracking-tighter text-center">Puissance 4 <span class="text-slate-200">Live</span></h2>
            
            <div id="mode-selector" class="space-y-4 mb-6">
                <div class="grid grid-cols-2 gap-3">
                    <button id="local-mode-btn" onclick="selectGameMode('local')" class="bg-blue-500 text-white py-3 rounded-2xl font-black uppercase text-sm transition">Local</button>
                    <button id="remote-mode-btn" onclick="selectGameMode('remote')" class="bg-purple-500 text-white py-3 rounded-2xl font-black uppercase text-sm transition opacity-60">En ligne</button>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-sky-50 p-6 rounded-3xl border-2 border-sky-100">
                    <label class="text-[10px] font-black uppercase text-sky-600 mb-2 block">Ton nom :</label>
                    <input type="text" id="my-name-in" placeholder="Entre ton nom..." class="w-full bg-white border-2 border-sky-200 p-4 rounded-2xl outline-none font-black text-xl shadow-sm">
                </div>

                <div id="local-options" class="hidden space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <button id="vs-ai-btn" onclick="selectLocalOpponent('ai')" class="bg-green-500 text-white py-3 rounded-2xl font-black uppercase text-xs">vs IA</button>
                        <button id="vs-player-btn" onclick="selectLocalOpponent('player')" class="bg-orange-500 text-white py-3 rounded-2xl font-black uppercase text-xs">vs Joueur</button>
                    </div>
                </div>
                
                <button id="main-btn" onclick="startAction()" class="w-full bg-black text-white py-5 rounded-2xl font-black uppercase text-xl shadow-xl active:scale-95 transition">Démarrer</button>
            </div>
        </div>
    </div>

    <div id="game" class="hidden max-w-2xl w-full">
        <div class="bg-white p-6 rounded-[2.5rem] shadow-2xl">
            <div id="game-info" class="flex justify-between items-center mb-4 p-4 bg-slate-50 rounded-2xl border">
                <div id="players-display" class="flex gap-4 items-center flex-wrap"></div>
            </div>
            <p class="font-bold text-center mb-2">Tour de: <span id="player-turn" class="font-black text-xl"></span></p>
            <div id="board-container" class="relative flex justify-center">
                <div id="board"></div>
                <div id="win-message" class="absolute inset-0 bg-black/50 backdrop-blur-sm hidden items-center justify-center flex-col gap-4 rounded-[1rem]">
                     <p class="text-white text-3xl font-black uppercase"></p>
                     <button id="play-again" class="bg-yellow-500 text-black px-8 py-4 rounded-2xl font-black uppercase text-sm">Rejouer</button>
                </div>
            </div>
            <div class="mt-4 flex justify-center">
                <button onclick="copyRoomLink()" id="copy-link-btn" class="bg-yellow-500 text-white px-6 py-2 rounded-xl font-bold text-xs uppercase hidden">📋 Copier le lien de la partie</button>
            </div>
        </div>
    </div>

    <script>
        // ON UTILISE LE MEME FICHIER POUR L'API
        const API = 'puissance4.php'; 
        const ROWS = 6;
        const COLS = 7;

        let roomId = new URLSearchParams(window.location.search).get('room');
        let myName = '';
        let myColor = '';
        let gameMode = roomId ? 'remote' : 'local';
        let localOpponent = null;
        let gameState = {};
        let syncInterval;

        window.onload = () => {
            if (roomId) {
                document.getElementById('mode-selector').classList.add('hidden');
                document.getElementById('main-btn').innerText = "Rejoindre la partie";
                selectGameMode('remote');
            }
        };

        function selectGameMode(mode) {
            gameMode = mode;
            document.getElementById('local-mode-btn').classList.toggle('opacity-60', mode !== 'local');
            document.getElementById('remote-mode-btn').classList.toggle('opacity-60', mode !== 'remote');
            document.getElementById('local-options').classList.toggle('hidden', mode !== 'local');
        }

        function selectLocalOpponent(opp) {
            localOpponent = opp;
            document.getElementById('vs-ai-btn').classList.toggle('bg-green-700', opp === 'ai');
            document.getElementById('vs-player-btn').classList.toggle('bg-orange-700', opp === 'player');
        }

        async function startAction() {
            const name = document.getElementById('my-name-in').value.trim();
            if (!name) return alert("Indique ton nom !");
            myName = name;

            if (gameMode === 'local') {
                if (!localOpponent) return alert("Choisis ton adversaire !");
                myColor = 'red';
                gameState = getInitialGameState(myName);
                gameState.players.push({ name: localOpponent === 'ai' ? 'IA' : 'Joueur 2', color: 'yellow' });
                showGame();
            } else {
                if (!roomId) {
                    // CRÉATION
                    roomId = Math.random().toString(36).substring(2, 8);
                    window.history.pushState({}, '', `?room=${roomId}`);
                    myColor = 'red';
                    gameState = getInitialGameState(myName);
                    await syncPush();
                } else {
                    // REJOINDRE
                    await syncPull();
                    if (!gameState.players) {
                        gameState = getInitialGameState('Hôte'); // Fallback si room vide
                    }
                    // Si on est pas déjà dans la liste, on s'ajoute en Jaune
                    if (!gameState.players.find(p => p.name === myName)) {
                        if (gameState.players.length === 1) {
                            gameState.players.push({ name: myName, color: 'yellow' });
                        }
                    }
                    myColor = gameState.players.find(p => p.name === myName)?.color || 'yellow';
                    await syncPush();
                }
                showGame();
                syncInterval = setInterval(syncPull, 2000);
            }
        }

        function showGame() {
            document.getElementById('setup').classList.add('hidden');
            document.getElementById('game').classList.remove('hidden');
            if (gameMode === 'remote') document.getElementById('copy-link-btn').classList.remove('hidden');
            render();
        }

        function getInitialGameState(name) {
            return {
                players: [{ name: name, color: 'red' }],
                board: Array(ROWS).fill(null).map(() => Array(COLS).fill(null)),
                currentPlayer: 'red',
                gameOver: false,
                winner: null
            };
        }

        async function syncPush() {
            if (!roomId) return;
            await fetch(`${API}?action=sync&roomId=${roomId}`, { 
                method: 'POST', 
                body: JSON.stringify(gameState) 
            });
        }

        async function syncPull() {
            if (!roomId) return;
            const r = await fetch(`${API}?action=sync&roomId=${roomId}`);
            const data = await r.json();
            if (data) {
                gameState = data;
                render();
            }
        }

        function handleColumnClick(col) {
            if (gameState.gameOver) return;
            if (gameMode === 'remote' && gameState.currentPlayer !== myColor) return;
            if (gameMode === 'remote' && gameState.players.length < 2) return alert("Attends le 2ème joueur !");

            for (let row = ROWS - 1; row >= 0; row--) {
                if (!gameState.board[row][col]) {
                    gameState.board[row][col] = gameState.currentPlayer;
                    if (checkWin(row, col)) {
                        gameState.gameOver = true;
                        gameState.winner = gameState.currentPlayer;
                    } else {
                        gameState.currentPlayer = gameState.currentPlayer === 'red' ? 'yellow' : 'red';
                        if (gameMode === 'local' && localOpponent === 'ai' && gameState.currentPlayer === 'yellow') {
                            setTimeout(aiPlay, 500);
                        }
                    }
                    if (gameMode === 'remote') syncPush();
                    render();
                    return;
                }
            }
        }

        function aiPlay() {
            const validCols = [];
            for (let c = 0; c < COLS; c++) if (!gameState.board[0][c]) validCols.push(c);
            const col = validCols[Math.floor(Math.random() * validCols.length)];
            handleColumnClick(col);
        }

        function checkWin(r, c) {
            const p = gameState.board[r][c];
            const dirs = [[0,1], [1,0], [1,1], [1,-1]];
            for (let [dr, dc] of dirs) {
                let count = 1;
                for (let i = 1; i < 4; i++) {
                    let nr = r + dr*i, nc = c + dc*i;
                    if (nr>=0 && nr<ROWS && nc>=0 && nc<COLS && gameState.board[nr][nc] === p) count++; else break;
                }
                for (let i = 1; i < 4; i++) {
                    let nr = r - dr*i, nc = c - dc*i;
                    if (nr>=0 && nr<ROWS && nc>=0 && nc<COLS && gameState.board[nr][nc] === p) count++; else break;
                }
                if (count >= 4) return true;
            }
            return false;
        }

        function render() {
            const boardEl = document.getElementById('board');
            boardEl.innerHTML = '';
            for (let c = 0; c < COLS; c++) {
                const colEl = document.createElement('div');
                colEl.className = 'column';
                colEl.onclick = () => handleColumnClick(c);
                for (let r = 0; r < ROWS; r++) {
                    const cellEl = document.createElement('div');
                    cellEl.className = 'cell' + (gameState.board[r][c] ? ' ' + gameState.board[r][c] : '');
                    colEl.appendChild(cellEl);
                }
                boardEl.appendChild(colEl);
            }

            const p1 = gameState.players[0];
            const p2 = gameState.players[1] || { name: 'En attente...', color: 'slate-400' };
            
            document.getElementById('players-display').innerHTML = `
                <span class="font-bold ${gameState.currentPlayer === 'red' ? 'text-red-600 underline' : ''}">${p1.name} (Rouge)</span>
                <span class="text-slate-300">vs</span>
                <span class="font-bold ${gameState.currentPlayer === 'yellow' ? 'text-amber-500 underline' : ''}">${p2.name} (Jaune)</span>
            `;
            
            document.getElementById('player-turn').innerText = gameState.currentPlayer === 'red' ? 'Rouge' : 'Jaune';
            document.getElementById('player-turn').style.color = gameState.currentPlayer === 'red' ? '#ef4444' : '#f59e0b';

            if (gameState.gameOver) {
                document.getElementById('win-message').classList.remove('hidden');
                document.getElementById('win-message').classList.add('flex');
                document.querySelector('#win-message p').innerText = "Victoire : " + (gameState.winner === 'red' ? p1.name : p2.name);
            } else {
                document.getElementById('win-message').classList.add('hidden');
            }
        }

        document.getElementById('play-again').onclick = () => {
            if (gameMode === 'remote' && myName !== gameState.players[0].name) return alert("Seul l'hôte peut relancer !");
            gameState = getInitialGameState(gameState.players[0].name);
            if (gameMode === 'remote') {
                gameState.players.push({ name: 'Joueur 2', color: 'yellow' }); // Réinitialise mais garde la structure
                syncPush();
            }
            render();
        };

        function copyRoomLink() {
            navigator.clipboard.writeText(window.location.href);
            alert("Lien copié ! Envoie-le à ton ami.");
        }
    </script>
</body>
</html>