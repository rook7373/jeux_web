<?php
// --- PARTIE SERVEUR ---
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
            echo json_encode(["status" => "ok"]);
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
    <title>Puissance 4 - ScoreMaster</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        #board { background-color: #0369a1; display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.5rem; padding: 0.75rem; border-radius: 1rem; border: 6px solid #075985; width: 100%; max-width: 500px; }
        .cell { background-color: #f8fafc; border-radius: 50%; aspect-ratio: 1 / 1; }
        .column { cursor: pointer; display: flex; flex-direction: column; gap: 0.5rem; }
        .cell.red { background-color: #ef4444; box-shadow: inset 0 -4px 4px rgba(0,0,0,0.2); }
        .cell.yellow { background-color: #f59e0b; box-shadow: inset 0 -4px 4px rgba(0,0,0,0.2); }
    </style>
</head>
<body class="bg-slate-100 min-h-screen p-4 flex items-center justify-center">

    <div id="setup" class="max-w-md w-full bg-white p-6 rounded-3xl shadow-xl">
        <h2 class="text-2xl font-black text-sky-600 uppercase text-center mb-6 italic">Puissance 4 Live</h2>
        <input type="text" id="my-name-in" placeholder="Ton nom..." class="w-full border-2 p-4 rounded-xl font-bold mb-4 outline-none focus:border-sky-500">
        <button onclick="startAction()" id="main-btn" class="w-full bg-black text-white py-4 rounded-xl font-black uppercase shadow-lg active:scale-95 transition">Démarrer</button>
    </div>

    <div id="game" class="hidden max-w-xl w-full bg-white p-4 rounded-3xl shadow-xl">
        <div class="flex justify-between items-center mb-4 bg-slate-50 p-3 rounded-xl border">
            <div id="players-display" class="text-xs font-bold uppercase flex gap-2"></div>
            <button onclick="copyRoomLink()" class="bg-sky-600 text-white px-3 py-1 rounded-lg text-[10px] font-bold">COPIER LIEN</button>
        </div>
        <p class="text-center mb-2 font-bold text-sm">Tour : <span id="player-turn" class="font-black"></span></p>
        <div id="board"></div>
        <div id="win-msg" class="hidden mt-4 p-4 bg-black text-white text-center rounded-xl font-bold uppercase"></div>
    </div>

    <script>
        const API = 'puissance4.php'; // Doit être le nom de CE fichier
        const ROWS = 6, COLS = 7;
        let roomId = new URLSearchParams(window.location.search).get('room');
        let myName = '', myColor = '', gameState = { board: Array(6).fill(null).map(() => Array(7).fill(null)), players: [], currentPlayer: 'red', gameOver: false };

        window.onload = () => { if(roomId) document.getElementById('main-btn').innerText = "Rejoindre la partie"; };

        async function startAction() {
            myName = document.getElementById('my-name-in').value.trim();
            if(!myName) return alert("Nom ?");

            if(!roomId) { 
                // CRÉATEUR
                roomId = Math.random().toString(36).substring(2, 8);
                window.history.pushState({}, '', `?room=${roomId}`);
                myColor = 'red';
                gameState.players = [{name: myName, color: 'red'}];
                await syncPush();
            } else { 
                // REJOIGNEUR : On attend de voir le Joueur 1
                await syncPull();
                if(!gameState.players.length) { 
                    alert("Cette salle n'existe pas ou l'hôte n'est pas prêt."); 
                    return; 
                }
                myColor = 'yellow';
                // On s'ajoute si on n'y est pas
                if(!gameState.players.find(p => p.name === myName)) {
                    if(gameState.players.length < 2) {
                        gameState.players.push({name: myName, color: 'yellow'});
                        await syncPush();
                    } else { return alert("Partie pleine !"); }
                }
            }
            document.getElementById('setup').classList.add('hidden');
            document.getElementById('game').classList.remove('hidden');
            setInterval(syncPull, 2000);
            render();
        }

        async function syncPush() {
            await fetch(`${API}?action=sync&roomId=${roomId}`, { method: 'POST', body: JSON.stringify(gameState) });
        }

        async function syncPull() {
            const r = await fetch(`${API}?action=sync&roomId=${roomId}&t=${Date.now()}`);
            const data = await r.json();
            if(data) { gameState = data; render(); }
        }

        function handleMove(col) {
            if(gameState.gameOver || gameState.currentPlayer !== myColor || gameState.players.length < 2) return;
            for(let r = ROWS-1; r >= 0; r--) {
                if(!gameState.board[r][col]) {
                    gameState.board[r][col] = myColor;
                    if(checkWin(r, col)) { gameState.gameOver = true; gameState.winner = myColor; }
                    else { gameState.currentPlayer = (myColor === 'red') ? 'yellow' : 'red'; }
                    syncPush(); render(); return;
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
            const b = document.getElementById('board'); b.innerHTML = '';
            for(let c=0; c<COLS; c++) {
                const colEl = document.createElement('div'); colEl.className = 'column'; colEl.onclick = () => handleMove(c);
                for(let r=0; r<ROWS; r++) {
                    const cell = document.createElement('div');
                    cell.className = 'cell' + (gameState.board[r][c] ? ' ' + gameState.board[r][c] : '');
                    colEl.appendChild(cell);
                }
                b.appendChild(colEl);
            }
            const p1 = gameState.players[0] || {name: '...', color: 'red'};
            const p2 = gameState.players[1] || {name: 'Attente...', color: 'slate-400'};
            document.getElementById('players-display').innerHTML = `<span class="${gameState.currentPlayer==='red'?'text-red-600':''}">${p1.name}</span> <span class="text-slate-300">VS</span> <span class="${gameState.currentPlayer==='yellow'?'text-amber-500':''}">${p2.name}</span>`;
            document.getElementById('player-turn').innerText = gameState.currentPlayer.toUpperCase();
            if(gameState.gameOver) {
                const winBox = document.getElementById('win-msg');
                winBox.innerText = "GAGNÉ : " + (gameState.winner === 'red' ? p1.name : p2.name);
                winBox.classList.remove('hidden');
            }
        }

        function copyRoomLink() { navigator.clipboard.writeText(window.location.href); alert("Lien copié !"); }
    </script>
</body>
</html>