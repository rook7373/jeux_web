<?php
// --- LOGIQUE SERVEUR (PHP) ---
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
            $data = file_get_contents('php://input');
            file_put_contents($file, $data);
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
    <title>Puissance 4 - Live</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        #board {
            background-color: #0369a1; display: grid; grid-template-columns: repeat(7, 1fr);
            gap: 0.75rem; padding: 1rem; border-radius: 1.5rem; border: 8px solid #075985;
            width: 100%; max-width: 600px;
        }
        .cell { background-color: #f8fafc; border-radius: 50%; aspect-ratio: 1 / 1; transition: background-color 0.2s; }
        .column { cursor: pointer; display: flex; flex-direction: column; gap: 0.75rem; border-radius: 0.5rem; }
        .column:hover { background-color: rgba(255,255,255,0.2); }
        .cell.red { background-color: #ef4444; box-shadow: inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.yellow { background-color: #f59e0b; box-shadow: inset 0 -4px 6px rgba(0,0,0,0.3); }
    </style>
</head>
<body class="bg-slate-100 min-h-screen p-4 flex items-center justify-center font-sans">

    <div id="setup" class="max-w-md w-full bg-white p-6 rounded-[2.5rem] shadow-2xl">
        <h2 class="text-3xl font-black mb-6 text-sky-600 uppercase text-center italic">Puissance 4 <span class="text-slate-200">Live</span></h2>
        
        <div id="mode-selector" class="grid grid-cols-2 gap-3 mb-6">
            <button onclick="selectGameMode('local')" id="btn-local" class="bg-blue-500 text-white py-3 rounded-2xl font-black uppercase text-sm transition">Local</button>
            <button onclick="selectGameMode('remote')" id="btn-remote" class="bg-purple-500 text-white py-3 rounded-2xl font-black uppercase text-sm transition opacity-60">En ligne</button>
        </div>

        <div class="space-y-4">
            <div class="bg-sky-50 p-4 rounded-2xl border-2 border-sky-100">
                <label class="text-[10px] font-black uppercase text-sky-600 mb-1 block">Ton nom :</label>
                <input type="text" id="my-name-in" placeholder="Entre ton nom..." class="w-full bg-white border-2 border-sky-100 p-4 rounded-xl outline-none font-black text-xl">
            </div>

            <div id="local-options" class="hidden grid grid-cols-2 gap-3">
                <button onclick="localOpponent='ai'" class="bg-green-500 text-white py-3 rounded-2xl font-bold uppercase text-xs">vs IA</button>
                <button onclick="localOpponent='player'" class="bg-orange-500 text-white py-3 rounded-2xl font-bold uppercase text-xs">vs Joueur</button>
            </div>
            
            <button onclick="startAction()" id="main-btn" class="w-full bg-black text-white py-5 rounded-2xl font-black uppercase text-xl shadow-xl active:scale-95 transition">Démarrer</button>
        </div>
    </div>

    <div id="game" class="hidden max-w-2xl w-full bg-white p-6 rounded-[2.5rem] shadow-2xl">
        <div class="flex justify-between items-center mb-4 p-4 bg-slate-50 rounded-2xl border">
            <div id="players-display" class="font-bold text-sm uppercase flex gap-4"></div>
            <button onclick="copyRoomLink()" class="bg-yellow-500 text-white px-4 py-2 rounded-xl text-[10px] font-bold">COPIER LIEN</button>
        </div>
        <p class="text-center mb-4 font-bold">Tour de : <span id="player-turn" class="font-black text-xl"></span></p>
        <div id="board-container" class="relative">
            <div id="board"></div>
            <div id="win-overlay" class="absolute inset-0 bg-black/50 backdrop-blur-sm hidden flex-col items-center justify-center rounded-[1rem]">
                <p class="text-white text-3xl font-black mb-4 uppercase"></p>
                <button onclick="window.location.href='puissance4.php'" class="bg-yellow-500 px-8 py-4 rounded-2xl font-black uppercase">Menu Principal</button>
            </div>
        </div>
    </div>

    <script>
        const API = 'puissance4.php';
        const ROWS = 6, COLS = 7;
        let roomId = new URLSearchParams(window.location.search).get('room');
        let myName = '', myColor = '', gameMode = roomId ? 'remote' : 'local', localOpponent = null;
        let gameState = { board: Array(6).fill(null).map(() => Array(7).fill(null)), players: [], currentPlayer: 'red', gameOver: false };

        window.onload = () => {
            if(roomId) {
                document.getElementById('mode-selector').classList.add('hidden');
                document.getElementById('main-btn').innerText = "Rejoindre la partie";
                selectGameMode('remote');
            }
        };

        function selectGameMode(m) {
            gameMode = m;
            document.getElementById('btn-local').classList.toggle('opacity-60', m !== 'local');
            document.getElementById('btn-remote').classList.toggle('opacity-60', m !== 'remote');
            document.getElementById('local-options').classList.toggle('hidden', m !== 'local');
        }

        async function startAction() {
            myName = document.getElementById('my-name-in').value.trim();
            if(!myName) return alert("Nom requis !");

            if(gameMode === 'remote') {
                if(!roomId) { // CRÉATION
                    roomId = Math.random().toString(36).substring(2, 8);
                    window.history.pushState({}, '', `?room=${roomId}`);
                    myColor = 'red';
                    gameState.players = [{name: myName, color: 'red'}];
                    await syncPush();
                } else { // REJOINDRE
                    await syncPull();
                    // On vérifie si on est déjà dedans ou si on prend la place libre
                    const exist = gameState.players.find(p => p.name === myName);
                    if(exist) {
                        myColor = exist.color;
                    } else if(gameState.players.length < 2) {
                        myColor = 'yellow';
                        gameState.players.push({name: myName, color: 'yellow'});
                        await syncPush();
                    } else {
                        return alert("Partie pleine !");
                    }
                }
                setInterval(syncPull, 2000);
            } else { // LOCAL
                myColor = 'red';
                gameState.players = [{name: myName, color: 'red'}, {name: localOpponent==='ai'?'IA':'Joueur 2', color: 'yellow'}];
            }
            document.getElementById('setup').classList.add('hidden');
            document.getElementById('game').classList.remove('hidden');
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
            if(data) { gameState = data; render(); }
        }

        function handleMove(col) {
            if(gameState.gameOver || (gameMode==='remote' && gameState.currentPlayer !== myColor)) return;
            if(gameMode==='remote' && gameState.players.length < 2) return alert("Attendez le second joueur !");

            for(let r = ROWS-1; r >= 0; r--) {
                if(!gameState.board[r][col]) {
                    gameState.board[r][col] = gameState.currentPlayer;
                    if(checkWin(r, col)) { gameState.gameOver = true; gameState.winner = gameState.currentPlayer; }
                    else gameState.currentPlayer = gameState.currentPlayer === 'red' ? 'yellow' : 'red';
                    if(gameMode==='remote') syncPush();
                    render();
                    if(!gameState.gameOver && gameMode==='local' && localOpponent==='ai' && gameState.currentPlayer==='yellow') setTimeout(aiMove, 600);
                    return;
                }
            }
        }

        function aiMove() {
            let cols = []; for(let c=0; c<COLS; c++) if(!gameState.board[0][c]) cols.push(c);
            handleMove(cols[Math.floor(Math.random()*cols.length)]);
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
            document.getElementById('players-display').innerHTML = `
                <span class="${gameState.currentPlayer==='red'?'text-red-600 underline':''}">${p1.name}</span> 
                <span class="text-slate-300">VS</span> 
                <span class="${gameState.currentPlayer==='yellow'?'text-amber-500 underline':''}">${p2.name}</span>
            `;
            document.getElementById('player-turn').innerText = gameState.currentPlayer === 'red' ? 'ROUGE' : 'JAUNE';
            document.getElementById('player-turn').style.color = gameState.currentPlayer === 'red' ? '#ef4444' : '#f59e0b';
            if(gameState.gameOver) {
                document.getElementById('win-overlay').classList.replace('hidden', 'flex');
                document.querySelector('#win-overlay p').innerText = "Gagné : " + (gameState.winner === 'red' ? p1.name : p2.name);
            }
        }

        function copyRoomLink() { navigator.clipboard.writeText(window.location.href); alert("Lien copié !"); }
    </script>
</body>
</html>