<?php
// --- PARTIE SERVEUR (PHP) ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if (!is_dir('rooms')) { mkdir('rooms', 0777, true); }
    $roomId = $_GET['roomId'] ?? '';
    $file = "rooms/room_$roomId.json";

    if ($_GET['action'] === 'sync' && $roomId) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            file_put_contents($file, file_get_contents('php://input'));
            echo json_encode(["status" => "saved"]);
        } else {
            echo file_exists($file) ? file_get_contents($file) : json_encode(null);
        }
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Puissance 4 LIVE</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        #board { background-color: #0369a1; display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.5rem; padding: 1rem; border-radius: 2rem; border: 8px solid #075985; width: 100%; max-width: 500px; }
        .cell { background-color: #f8fafc; border-radius: 50%; aspect-ratio: 1 / 1; transition: all 0.3s; }
        .column { cursor: pointer; display: flex; flex-direction: column; gap: 0.5rem; border-radius: 1rem; }
        .column:hover { background-color: rgba(255,255,255,0.1); }
        .cell.red { background-color: #ef4444; box-shadow: inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.yellow { background-color: #f59e0b; box-shadow: inset 0 -4px 6px rgba(0,0,0,0.3); }
    </style>
</head>
<body class="bg-slate-100 min-h-screen p-4 flex items-center justify-center font-sans">

    <div id="setup" class="max-w-md w-full bg-white p-8 rounded-[2.5rem] shadow-2xl">
        <h2 id="setup-title" class="text-3xl font-black mb-6 text-sky-600 uppercase text-center italic">Puissance 4 <span class="text-slate-200">Live</span></h2>
        
        <div class="space-y-6">
            <div class="bg-sky-50 p-6 rounded-3xl border-2 border-sky-100">
                <label class="text-[10px] font-black uppercase text-sky-600 mb-2 block">Identité :</label>
                <input type="text" id="my-name-in" placeholder="Ton nom..." class="w-full bg-white border-2 border-sky-200 p-4 rounded-2xl outline-none font-black text-xl shadow-sm">
            </div>
            <button onclick="startAction()" id="main-btn" class="w-full bg-black text-white py-5 rounded-2xl font-black uppercase text-xl shadow-xl active:scale-95 transition">Démarrer</button>
        </div>
    </div>

    <div id="game" class="hidden max-w-2xl w-full bg-white p-6 rounded-[2.5rem] shadow-2xl">
        <div class="flex justify-between items-center mb-6 p-4 bg-slate-50 rounded-2xl border">
            <div id="players-display" class="flex gap-4 font-black uppercase text-xs"></div>
            <button onclick="copyRoomLink()" class="bg-yellow-500 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase shadow-sm">Lien</button>
        </div>
        
        <p class="text-center mb-4 font-bold text-slate-400 uppercase text-xs tracking-widest">
            Tour de : <span id="player-turn" class="text-lg text-slate-900"></span>
        </p>

        <div class="flex justify-center">
            <div id="board"></div>
        </div>

        <div id="win-overlay" class="hidden mt-6 p-6 bg-black text-white text-center rounded-3xl font-black uppercase text-xl">
            <p id="win-text" class="mb-4"></p>
            <button onclick="window.location.href='puissance4.php'" class="bg-yellow-500 text-black px-6 py-2 rounded-xl text-sm">Retour Menu</button>
        </div>
    </div>

    <script>
        // On utilise le même fichier pour l'API (Self-contained)
        const API = 'puissance4.php'; 
        const ROWS = 6, COLS = 7;

        let roomId = new URLSearchParams(window.location.search).get('room');
        let myName = '', myColor = '';
        let gameState = { players: [], board: Array(6).fill(null).map(() => Array(7).fill(null)), currentPlayer: 'red', gameOver: false, winner: '' };

        window.onload = () => {
            if (roomId) {
                document.getElementById('setup-title').innerText = "Rejoindre";
                document.getElementById('main-btn').innerText = "Rejoindre la partie";
            }
        };

        async function startAction() {
            const name = document.getElementById('my-name-in').value.trim();
            if(!name) return alert("Ton nom ?");
            myName = name;

            if (!roomId) {
                // CRÉATEUR
                roomId = Math.random().toString(36).substring(2, 8);
                window.history.pushState({}, '', `?room=${roomId}`);
                myColor = 'red';
                gameState.players = [{ name: myName, color: 'red' }];
            } else {
                // REJOIGNEUR (Comme dans Bac : syncPull avant de décider)
                await syncPull();
                if (!gameState.players || gameState.players.length === 0) {
                    // Si room vide ou erreur, on devient l'hôte
                    myColor = 'red';
                    gameState.players = [{ name: myName, color: 'red' }];
                } else if (!gameState.players.find(p => p.name === myName)) {
                    if (gameState.players.length >= 2) return alert("Partie pleine !");
                    myColor = 'yellow';
                    gameState.players.push({ name: myName, color: 'yellow' });
                } else {
                    // Reconnexion
                    myColor = gameState.players.find(p => p.name === myName).color;
                }
            }

            document.getElementById('setup').classList.add('hidden');
            document.getElementById('game').classList.remove('hidden');
            
            await syncPush();
            setInterval(syncPull, 2000);
            render();
        }

        async function syncPush() {
            if(!roomId) return;
            await fetch(`${API}?action=sync&roomId=${roomId}`, { 
                method: 'POST', body: JSON.stringify(gameState) 
            });
        }

        async function syncPull() {
            if(!roomId) return;
            const r = await fetch(`${API}?action=sync&roomId=${roomId}&t=${Date.now()}`);
            const data = await r.json();
            if (data) {
                gameState = data;
                // On s'assure que notre couleur locale est la bonne
                const me = gameState.players.find(p => p.name === myName);
                if (me) myColor = me.color;
                render();
            }
        }

        function handleMove(col) {
            if (gameState.gameOver || gameState.players.length < 2) return;
            if (gameState.currentPlayer !== myColor) return; // Pas ton tour

            for (let r = ROWS - 1; r >= 0; r--) {
                if (!gameState.board[r][col]) {
                    gameState.board[r][col] = myColor;
                    if (checkWin(r, col)) {
                        gameState.gameOver = true;
                        gameState.winner = myName;
                    } else {
                        gameState.currentPlayer = (myColor === 'red') ? 'yellow' : 'red';
                    }
                    syncPush();
                    render();
                    return;
                }
            }
        }

        function checkWin(r, c) {
            const p = gameState.board[r][c], d = [[0,1],[1,0],[1,1],[1,-1]];
            return d.some(([dr, dc]) => {
                let count = 1;
                [[dr, dc], [-dr, -dc]].forEach(([sr, sc]) => {
                    for(let i=1; i<4; i++) {
                        let nr=r+sr*i, nc=c+sc*i;
                        if(nr>=0 && nr<ROWS && nc>=0 && nc<COLS && gameState.board[nr][nc]===p) count++; else break;
                    }
                });
                return count >= 4;
            });
        }

        function render() {
            const boardDiv = document.getElementById('board');
            boardDiv.innerHTML = '';
            for (let c = 0; c < COLS; c++) {
                const colEl = document.createElement('div');
                colEl.className = 'column';
                colEl.onclick = () => handleMove(c);
                for (let r = 0; r < ROWS; r++) {
                    const cell = document.createElement('div');
                    cell.className = 'cell' + (gameState.board[r][c] ? ' ' + gameState.board[r][c] : '');
                    colEl.appendChild(cell);
                }
                boardDiv.appendChild(colEl);
            }

            const p1 = gameState.players[0] || { name: '...', color: 'red' };
            const p2 = gameState.players[1] || { name: 'En attente...', color: 'slate-300' };
            
            document.getElementById('players-display').innerHTML = `
                <span class="${gameState.currentPlayer === 'red' ? 'text-red-600' : 'text-slate-400'}">${p1.name}</span>
                <span class="text-slate-200">VS</span>
                <span class="${gameState.currentPlayer === 'yellow' ? 'text-amber-500' : 'text-slate-400'}">${p2.name}</span>
            `;

            document.getElementById('player-turn').innerText = gameState.currentPlayer === 'red' ? p1.name : p2.name;
            document.getElementById('player-turn').style.color = gameState.currentPlayer === 'red' ? '#ef4444' : '#f59e0b';

            if (gameState.gameOver) {
                document.getElementById('win-overlay').classList.remove('hidden');
                document.getElementById('win-text').innerText = "GAGNÉ : " + gameState.winner;
            }
        }

        function copyRoomLink() { 
            navigator.clipboard.writeText(window.location.href); 
            alert("Lien copié !"); 
        }
    </script>
</body>
</html>