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
    <title>Puissance 4 LIVE - ScoreMaster</title>
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
<body class="bg-slate-100 min-h-screen p-4 flex items-center justify-center font-sans text-slate-900">

    <div id="setup" class="max-w-md w-full bg-white p-8 rounded-[2.5rem] shadow-2xl">
        <h2 id="setup-title" class="text-3xl font-black mb-6 text-sky-600 uppercase text-center italic tracking-tighter">Puissance 4 <span class="text-slate-200">Live</span></h2>
        
        <div id="mode-selector" class="grid grid-cols-2 gap-3 mb-6">
            <button onclick="selectGameMode('local')" id="btn-local" class="bg-blue-500 text-white py-3 rounded-2xl font-black uppercase text-sm transition shadow-lg">Local</button>
            <button onclick="selectGameMode('remote')" id="btn-remote" class="bg-purple-500 text-white py-3 rounded-2xl font-black uppercase text-sm opacity-60 transition shadow-lg">En ligne</button>
        </div>

        <div class="space-y-6">
            <div id="local-options" class="space-y-4">
                <p class="text-center text-[10px] font-black uppercase text-slate-400">Choisis ton adversaire :</p>
                <div class="grid grid-cols-2 gap-3">
                    <button onclick="selectLocalOpponent('ai')" id="btn-ai" class="bg-green-500 text-white py-3 rounded-2xl font-black uppercase text-xs shadow-md transition">🤖 vs IA</button>
                    <button onclick="selectLocalOpponent('player')" id="btn-player" class="bg-orange-500 text-white py-3 rounded-2xl font-black uppercase text-xs shadow-md transition">👤 vs Humain</button>
                </div>
            </div>

            <div class="bg-sky-50 p-6 rounded-3xl border-2 border-sky-100">
                <label class="text-[10px] font-black uppercase text-sky-600 mb-2 block font-bold">Ton identité :</label>
                <input type="text" id="my-name-in" placeholder="Entre ton nom..." class="w-full bg-white border-2 border-sky-200 p-4 rounded-2xl outline-none font-black text-xl shadow-sm">
            </div>
            
            <button id="main-btn" onclick="startAction()" class="w-full bg-black text-white py-5 rounded-2xl font-black uppercase text-xl shadow-xl active:scale-95 transition">Démarrer</button>
        </div>
    </div>

    <div id="game" class="hidden max-w-2xl w-full bg-white p-6 rounded-[2.5rem] shadow-2xl border-t-8 border-sky-600">
        <div class="flex justify-between items-center mb-6 p-4 bg-slate-50 rounded-2xl border">
            <div id="players-display" class="flex gap-4 font-black uppercase text-[10px]"></div>
            <button onclick="copyRoomLink()" id="btn-copy" class="hidden bg-yellow-500 text-white px-4 py-2 rounded-xl text-[10px] font-black uppercase shadow-sm">Lien</button>
        </div>
        
        <p class="text-center mb-4 font-bold text-slate-400 uppercase text-[10px] tracking-widest">Tour de : <span id="player-turn" class="text-lg text-slate-900"></span></p>

        <div class="flex justify-center">
            <div id="board"></div>
        </div>

        <div id="win-overlay" class="hidden mt-6 p-6 bg-black text-white text-center rounded-3xl font-black uppercase text-xl animate-bounce">
            <p id="win-text" class="mb-4"></p>
            <button onclick="window.location.href='puissance4.php'" class="bg-yellow-500 text-black px-6 py-2 rounded-xl text-sm">Retour Menu</button>
        </div>
    </div>

    <script>
        const API = 'puissance4.php'; 
        const ROWS = 6, COLS = 7;
        let roomId = new URLSearchParams(window.location.search).get('room');
        let myName = '', myColor = '', gameMode = roomId ? 'remote' : 'local', localOpponent = null;
        let gameState = { players: [], board: Array(6).fill(null).map(() => Array(7).fill(null)), currentPlayer: 'red', gameOver: false, winner: '' };

        window.onload = async () => {
            if (roomId) {
                document.getElementById('mode-selector').classList.add('hidden');
                document.getElementById('local-options').classList.add('hidden');
                document.getElementById('setup-title').innerText = "Rejoindre";
                document.getElementById('main-btn').innerText = "Rejoindre la partie";
                gameMode = 'remote';
                await syncPull();
            } else {
                selectGameMode('local');
            }
        };

        function selectGameMode(m) {
            gameMode = m;
            document.getElementById('btn-local').classList.toggle('opacity-60', m !== 'local');
            document.getElementById('btn-remote').classList.toggle('opacity-60', m !== 'remote');
            document.getElementById('local-options').classList.toggle('hidden', m !== 'local');
        }

        function selectLocalOpponent(opp) {
            localOpponent = opp;
            document.getElementById('btn-ai').classList.toggle('ring-4', opp === 'ai');
            document.getElementById('btn-player').classList.toggle('ring-4', opp === 'player');
        }

        async function startAction() {
            const name = document.getElementById('my-name-in').value.trim();
            if(!name) return alert("Indique ton nom !");
            myName = name;

            if (gameMode === 'remote') {
                if (!roomId) {
                    roomId = Math.random().toString(36).substring(2, 8);
                    window.history.pushState({}, '', `?room=${roomId}`);
                    myColor = 'red';
                    gameState.players = [{ name: myName, color: 'red' }];
                } else {
                    await syncPull();
                    if (!gameState.players || gameState.players.length === 0) {
                        myColor = 'red';
                        gameState.players = [{ name: myName, color: 'red' }];
                    } else {
                        const exist = gameState.players.find(p => p.name === myName);
                        if(exist) {
                            myColor = exist.color;
                        } else if(gameState.players.length === 1) {
                            myColor = 'yellow';
                            gameState.players.push({ name: myName, color: 'yellow' });
                        } else {
                            return alert("Partie pleine !");
                        }
                    }
                }
                setInterval(syncPull, 2000);
            } else {
                if(!localOpponent) return alert("Choisis un adversaire local !");
                myColor = 'red';
                gameState.players = [
                    { name: myName, color: 'red' },
                    { name: localOpponent === 'ai' ? '🤖 IA' : 'Joueur 2', color: 'yellow' }
                ];
            }

            document.getElementById('setup').classList.add('hidden');
            document.getElementById('game').classList.remove('hidden');
            if(gameMode === 'remote') document.getElementById('btn-copy').classList.remove('hidden');
            
            if(gameMode === 'remote') await syncPush();
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
                const me = gameState.players.find(p => p.name === myName);
                if (me) myColor = me.color;
                render();
            }
        }

        function handleMove(col) {
            if (gameState.gameOver || (gameMode === 'remote' && gameState.players.length < 2)) return;
            if (gameMode === 'remote' && gameState.currentPlayer !== myColor) return;

            for (let r = ROWS - 1; r >= 0; r--) {
                if (!gameState.board[r][col]) {
                    gameState.board[r][col] = gameState.currentPlayer;
                    if (checkWin(r, col)) {
                        gameState.gameOver = true;
                        gameState.winner = gameState.currentPlayer === 'red' ? gameState.players[0].name : gameState.players[1].name;
                    } else {
                        gameState.currentPlayer = (gameState.currentPlayer === 'red') ? 'yellow' : 'red';
                        if(gameMode === 'local' && localOpponent === 'ai' && gameState.currentPlayer === 'yellow') setTimeout(aiPlay, 600);
                    }
                    if(gameMode === 'remote') syncPush();
                    render();
                    return;
                }
            }
        }

        function aiPlay() {
            let cols = []; for(let c=0; c<COLS; c++) if(!gameState.board[0][c]) cols.push(c);
            const move = cols[Math.floor(Math.random() * cols.length)];
            handleMove(move);
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
                <span class="${gameState.currentPlayer === 'red' ? 'text-red-600 underline decoration-2' : 'text-slate-400'}">${p1.name}</span>
                <span class="text-slate-200">VS</span>
                <span class="${gameState.currentPlayer === 'yellow' ? 'text-amber-500 underline decoration-2' : 'text-slate-400'}">${p2.name}</span>
            `;
            document.getElementById('player-turn').innerText = gameState.currentPlayer === 'red' ? p1.name : p2.name;
            document.getElementById('player-turn').style.color = gameState.currentPlayer === 'red' ? '#ef4444' : '#f59e0b';

            if (gameState.gameOver) {
                document.getElementById('win-overlay').classList.remove('hidden');
                document.getElementById('win-text').innerText = "GAGNÉ : " + gameState.winner;
            }
        }

        function copyRoomLink() { navigator.clipboard.writeText(window.location.href); alert("Lien copié !"); }
    </script>
</body>
</html>