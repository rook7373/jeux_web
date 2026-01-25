<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST");
header("Access-Control-Allow-Headers: Content-Type");

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if (!is_dir('rooms')) { mkdir('rooms', 0777, true); }
    $roomId = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['roomId'] ?? '');
    $file = "rooms/mem_$roomId.json";

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
    <title>Memory Arena - Kittens</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: radial-gradient(circle at center, #0f172a 0%, #000000 100%); color: white; min-height: 100vh; font-family: sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); }
        #grid { display: grid; gap: 0.5rem; width: 100%; max-width: 600px; margin: auto; }
        .card { aspect-ratio: 1/1; position: relative; transform-style: preserve-3d; transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; }
        .card.flipped { transform: rotateY(180deg); }
        .card.matched { opacity: 0.4; cursor: default; }
        .card-face { position: absolute; inset: 0; backface-visibility: hidden; border-radius: 1rem; border: 2px solid rgba(255,255,255,0.1); }
        .card-front { background: #1e293b; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .card-back { background: #3b82f6; transform: rotateY(180deg); background-size: cover; background-position: center; }
    </style>
</head>
<body class="p-4 flex items-center justify-center font-sans uppercase font-black">

    <div id="setup" class="max-w-md w-full bg-white p-10 rounded-[3.5rem] shadow-2xl text-slate-900 z-50">
        <h2 id="setup-title" class="text-4xl font-black mb-8 text-blue-600 text-center italic tracking-tighter uppercase">Memory <span class="text-slate-200">Kittens</span></h2>
        
        <div id="mode-selector" class="grid grid-cols-2 gap-4 mb-8">
            <button type="button" onclick="setMode('local')" id="m-local" class="bg-blue-600 text-white py-4 rounded-2xl shadow-lg font-black">Local</button>
            <button type="button" onclick="setMode('remote')" id="m-remote" class="bg-slate-200 text-slate-500 py-4 rounded-2xl font-black">En Ligne</button>
        </div>

        <div id="difficulty-selector" class="mb-8">
            <p class="text-center text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Taille de la grille :</p>
            <div class="grid grid-cols-3 gap-2">
                <button type="button" onclick="setGridSize(4)" id="btn-s4" class="bg-slate-200 text-slate-500 py-3 rounded-xl text-xs font-black">4x4</button>
                <button type="button" onclick="setGridSize(6)" id="btn-s6" class="bg-blue-600 text-white py-3 rounded-xl text-xs font-black shadow-md">6x6</button>
                <button type="button" onclick="setGridSize(8)" id="btn-s8" class="bg-slate-200 text-slate-500 py-3 rounded-xl text-xs font-black">8x8</button>
            </div>
        </div>

        <div id="local-options" class="space-y-4 mb-8">
            <p class="text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Adversaire :</p>
            <div class="grid grid-cols-2 gap-3">
                <button type="button" onclick="setOpponent('ai')" id="opp-ai" class="bg-blue-600 text-white py-4 rounded-2xl text-xs font-black shadow-md">🤖 IA</button>
                <button type="button" onclick="setOpponent('human')" id="opp-human" class="bg-slate-200 text-slate-500 py-4 rounded-2xl text-xs font-black">👤 Humain</button>
            </div>
        </div>

        <div class="space-y-6">
            <input type="text" id="my-name-in" autocomplete="off" onkeydown="if(event.key === 'Enter') startAction()" placeholder="TON PSEUDO..." class="w-full bg-slate-50 border-2 border-slate-100 p-5 rounded-3xl outline-none text-xl text-center focus:border-blue-400 font-black uppercase">
            <button type="button" onclick="startAction()" class="w-full bg-black text-white py-6 rounded-[2rem] text-xl shadow-2xl active:scale-95 transition-all font-black">Démarrer</button>
        </div>
    </div>

    <div id="game" class="hidden max-w-4xl w-full glass p-6 rounded-[4rem] border border-white/10 shadow-2xl">
        <div class="flex justify-between items-center mb-8">
            <button onclick="location.reload()" class="text-[10px] bg-white/10 px-6 py-3 rounded-full hover:bg-white/20 transition font-black uppercase">QUITTER</button>
            <div id="players-display" class="flex gap-8 text-[12px] tracking-widest items-center"></div>
            <button onclick="copyLink()" id="btn-copy" class="hidden bg-blue-600 px-6 py-3 rounded-full text-[10px] font-black shadow-lg uppercase">LIEN INVITE</button>
        </div>
        <div id="grid"></div>
        <div id="win-overlay" class="hidden text-center mt-10">
            <p id="win-text" class="text-5xl text-yellow-500 mb-8 italic tracking-tighter"></p>
            <button onclick="location.reload()" class="bg-white text-black px-12 py-5 rounded-full text-sm font-black shadow-xl hover:scale-105 transition uppercase">REJOUER</button>
        </div>
    </div>

    <script>
        const API = 'memory.php'; 
        let roomId = new URLSearchParams(window.location.search).get('room');
        let myName = '', gameMode = roomId ? 'remote' : 'local', localOpponent = 'ai';
        let selectedSize = 6;
        let isProcessingMatch = false;

        let gameState = { 
            players: [], board: [], currentPlayerIdx: 0, flipped: [], matched: [], gameOver: false, winner: '', gridSize: 6
        };

        const kittenImages = Array.from({length: 32}, (_, i) => `https://loremflickr.com/200/200/kitten?lock=${i}`);

        window.onload = () => {
            if(roomId) {
                // On cache tout pour celui qui rejoint (il subit la config de l'hôte)
                document.getElementById('mode-selector').classList.add('hidden');
                document.getElementById('difficulty-selector').classList.add('hidden');
                document.getElementById('local-options').classList.add('hidden');
                document.getElementById('setup-title').innerHTML = "REJOINDRE <span class='text-slate-200'>ARENA</span>";
                setMode('remote');
            } else {
                setMode('local');
            }
        };

        function setMode(m) {
            gameMode = m;
            document.getElementById('m-local').className = (m === 'local') ? "bg-blue-600 text-white py-4 rounded-2xl shadow-lg font-black" : "bg-slate-200 text-slate-500 py-4 rounded-2xl font-black";
            document.getElementById('m-remote').className = (m === 'remote') ? "bg-purple-600 text-white py-4 rounded-2xl shadow-lg font-black" : "bg-slate-200 text-slate-500 py-4 rounded-2xl font-black";
            
            // On ne montre les options adversaire que si c'est LOCAL
            document.getElementById('local-options').classList.toggle('hidden', m !== 'local');
            
            // On garde le choix de difficulté visible pour l'HÔTE (si pas de roomId)
            if (!roomId) {
                document.getElementById('difficulty-selector').classList.remove('hidden');
            }
        }

        function setGridSize(s) {
            selectedSize = s;
            [4, 6, 8].forEach(x => {
                document.getElementById('btn-s'+x).className = (x === s) ? "bg-blue-600 text-white py-3 rounded-xl text-xs font-black shadow-md" : "bg-slate-200 text-slate-500 py-3 rounded-xl text-xs font-black";
            });
        }

        function setOpponent(opp) {
            localOpponent = opp;
            document.getElementById('opp-ai').className = (opp === 'ai') ? "bg-blue-600 text-white py-4 rounded-2xl text-xs font-black shadow-md" : "bg-slate-200 text-slate-500 py-4 rounded-2xl text-xs font-black";
            document.getElementById('opp-human').className = (opp === 'human') ? "bg-blue-600 text-white py-4 rounded-2xl text-xs font-black shadow-md" : "bg-slate-200 text-slate-500 py-4 rounded-2xl text-xs font-black";
        }

        async function startAction() {
            const name = document.getElementById('my-name-in').value.trim();
            if(!name) return alert("Pseudo !"); myName = name.toUpperCase();

            if (gameMode === 'remote') {
                if (!roomId) roomId = Math.random().toString(36).substring(2, 8);
                window.history.pushState({}, '', `?room=${roomId}`);
                await syncPull();
                if (!gameState.players.find(p => p.name === myName)) {
                    if (gameState.players.length < 2) {
                        gameState.players.push({ name: myName, score: 0 });
                        if (gameState.players.length === 1) {
                            gameState.gridSize = selectedSize; // L'hôte définit la taille ici
                            initBoard();
                        }
                    } else return alert("Plein !");
                } 
                setInterval(syncPull, 1000);
            } else {
                gameState.gridSize = selectedSize;
                initBoard();
                gameState.players = [{ name: myName, score: 0 }, { name: localOpponent === 'ai' ? 'IA 🤖' : 'JOUEUR 2', score: 0 }];
            }

            document.getElementById('setup').classList.add('hidden');
            document.getElementById('game').classList.remove('hidden');
            if(gameMode === 'remote') { document.getElementById('btn-copy').classList.remove('hidden'); await syncPush(); }
            render();
        }

        function initBoard() {
            const numPairs = (gameState.gridSize * gameState.gridSize) / 2;
            let selectedImages = kittenImages.slice(0, numPairs);
            let pairs = [...selectedImages, ...selectedImages].sort(() => Math.random() - 0.5);
            gameState.board = pairs;
        }

        async function syncPush() { if(roomId) await fetch(`${API}?action=sync&roomId=${roomId}`, { method: 'POST', body: JSON.stringify(gameState) }); }

        async function syncPull() {
            if(!roomId || isProcessingMatch) return;
            try { 
                const r = await fetch(`${API}?action=sync&roomId=${roomId}&t=${Date.now()}`); 
                const data = await r.json(); 
                if (data) { 
                    // Sécurité : on ne pull pas si l'autre joueur a déjà fini son tour mais que nous on n'a pas encore fini l'animation
                    if (gameState.flipped.length < 2) {
                        gameState = data; 
                        render(); 
                    }
                } 
            } catch(e) {}
        }

        function handleFlip(idx) {
            if (gameState.gameOver || gameState.flipped.length >= 2 || gameState.matched.includes(idx) || gameState.flipped.includes(idx)) return;
            if (gameMode === 'remote' && gameState.players[gameState.currentPlayerIdx].name !== myName) return;

            gameState.flipped.push(idx);
            render();

            // Correction Bug : On push immédiatement la 2ème carte pour que l'adversaire la voie
            if(gameMode === 'remote') syncPush();

            if (gameState.flipped.length === 2) {
                isProcessingMatch = true; 
                setTimeout(checkMatch, 1000);
            }
        }

        function checkMatch() {
            if (gameState.flipped.length < 2) return;
            const [i1, i2] = gameState.flipped;
            if (gameState.board[i1] === gameState.board[i2]) {
                gameState.matched.push(i1, i2);
                gameState.players[gameState.currentPlayerIdx].score++;
                if (gameState.matched.length === gameState.board.length) {
                    gameState.gameOver = true;
                    gameState.winner = gameState.players.reduce((prev, curr) => (prev.score > curr.score) ? prev : curr).name;
                }
            } else {
                gameState.currentPlayerIdx = (gameState.currentPlayerIdx + 1) % gameState.players.length;
                if(gameMode === 'local' && gameState.players[gameState.currentPlayerIdx].name === 'IA 🤖') setTimeout(aiMove, 500);
            }
            gameState.flipped = [];
            isProcessingMatch = false;
            render();
            if(gameMode === 'remote') syncPush();
        }

        function aiMove() {
            let available = gameState.board.map((_, i) => i).filter(i => !gameState.matched.includes(i));
            if(available.length === 0) return;
            let move1 = available[Math.floor(Math.random() * available.length)];
            handleFlip(move1);
            setTimeout(() => {
                let available2 = available.filter(i => i !== move1);
                if(available2.length === 0) return;
                let move2 = available2[Math.floor(Math.random() * available2.length)];
                handleFlip(move2);
            }, 1000);
        }

        function render() {
            const grid = document.getElementById('grid');
            grid.innerHTML = '';
            grid.style.gridTemplateColumns = `repeat(${gameState.gridSize}, 1fr)`;

            gameState.board.forEach((img, i) => {
                const card = document.createElement('div');
                card.className = `card ${gameState.flipped.includes(i) || gameState.matched.includes(i) ? 'flipped' : ''} ${gameState.matched.includes(i) ? 'matched' : ''}`;
                card.onclick = () => handleFlip(i);
                card.innerHTML = `
                    <div class="card-face card-front">🐾</div>
                    <div class="card-face card-back" style="background-image: url('${img}')"></div>
                `;
                grid.appendChild(card);
            });

            const p1 = gameState.players[0] || { name: '...', score: 0 };
            const p2 = gameState.players[1] || { name: '...', score: 0 };
            document.getElementById('players-display').innerHTML = `
                <span class="${gameState.currentPlayerIdx === 0 ? 'ring-2 ring-white/20 bg-white/5' : 'opacity-40'} px-4 py-2 rounded-2xl text-blue-400">${p1.name}: ${p1.score}</span>
                <span class="text-slate-700 italic font-bold text-xs">VS</span>
                <span class="${gameState.currentPlayerIdx === 1 ? 'ring-2 ring-white/20 bg-white/5' : 'opacity-40'} px-4 py-2 rounded-2xl text-purple-400">${p2.name}: ${p2.score}</span>
            `;

            if (gameState.gameOver) {
                document.getElementById('win-overlay').classList.remove('hidden');
                document.getElementById('win-text').innerText = gameState.winner + " GAGNE !";
            }
        }

        function copyLink() { navigator.clipboard.writeText(window.location.href); alert("LIEN COPIÉ !"); }
    </script>
</body>
</html>