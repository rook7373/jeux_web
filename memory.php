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
    <title>Memory Arena</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Yams_multi theme */
        body { background: radial-gradient(circle at center, #0a2f1a 0%, #000000 100%); color: white; min-height: 100vh; font-family: 'Inter', sans-serif; overflow-x: hidden; }
        
        #grid { display: grid; gap: 0.75rem; width: 100%; max-width: 700px; margin: auto; }
        .card { perspective: 1000px; aspect-ratio: 1/1; }
        .card-inner { position: relative; width: 100%; height: 100%; transition: transform 0.6s; transform-style: preserve-3d; }
        .card.flipped .card-inner { transform: rotateY(180deg); }
        
        .card-face {
            position: absolute;
            width: 100%; height: 100%;
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            border-radius: 1.5rem;
            overflow: hidden;
        }
        .card-front {
            background: rgba(0,0,0,0.4);
            border: 2px solid rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
        }
        .card-back {
            background-size: cover;
            background-position: center;
            transform: rotateY(180deg);
            border: 2px solid rgba(255,255,255,0.3);
        }

        .card.matched .card-inner { transform: scale(0.9); opacity: 0.4; filter: saturate(0.5); }
        .card.matched { cursor: default; }
        
        .active-turn { box-shadow: 0 0 20px var(--tw-shadow-color); transform: scale(1.05); }
        input::placeholder { color: #9ca3af; }
    </style>
</head>
<body class="p-4 flex items-center justify-center font-sans uppercase font-black">

    <div id="setup" class="max-w-md w-full bg-white p-8 md:p-12 rounded-[3.5rem] shadow-2xl text-slate-900 z-50">
        <h2 id="setup-title" class="text-4xl font-black mb-8 text-green-900 text-center italic tracking-tighter uppercase">Memory</h2>
        
        <div id="mode-selector" class="grid grid-cols-2 gap-4 mb-8">
            <button type="button" onclick="setMode('local')" id="m-local" class="bg-green-600 text-white py-4 rounded-2xl shadow-lg font-black">Local</button>
            <button type="button" onclick="setMode('remote')" id="m-remote" class="bg-slate-200 text-slate-500 py-4 rounded-2xl font-black">En Ligne</button>
        </div>

        <div id="difficulty-selector" class="mb-8">
            <p class="text-center text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Taille de la grille :</p>
            <div class="grid grid-cols-3 gap-2">
                <button type="button" onclick="setGridSize(4)" id="btn-s4" class="bg-slate-200 text-slate-500 py-3 rounded-xl text-xs font-black">4x4</button>
                <button type="button" onclick="setGridSize(6)" id="btn-s6" class="bg-green-600 text-white py-3 rounded-xl text-xs font-black shadow-md">6x6</button>
                <button type="button" onclick="setGridSize(8)" id="btn-s8" class="bg-slate-200 text-slate-500 py-3 rounded-xl text-xs font-black">8x8</button>
            </div>
        </div>

        <div id="local-options" class="space-y-4 mb-8">
            <p class="text-center text-[10px] font-black text-slate-400 uppercase tracking-widest">Adversaire :</p>
            <div class="grid grid-cols-2 gap-3">
                <button type="button" onclick="setOpponent('ai')" id="opp-ai" class="bg-green-600 text-white py-4 rounded-2xl text-xs font-black shadow-md">🤖 IA</button>
                <button type="button" onclick="setOpponent('human')" id="opp-human" class="bg-slate-200 text-slate-500 py-4 rounded-2xl text-xs font-black">👤 Humain</button>
            </div>
        </div>

        <div id="theme-selector" class="mb-8">
            <p class="text-center text-[10px] font-black text-slate-400 uppercase tracking-widest mb-3">Thème :</p>
            <div class="grid grid-cols-3 gap-2" id="themes-grid">
                <!-- Theme buttons will be rendered here by JavaScript -->
            </div>
        </div>

        <div class="space-y-6">
            <input type="text" id="my-name-in" autocomplete="off" onkeydown="if(event.key === 'Enter') startAction()" placeholder="TON PSEUDO..." class="w-full bg-slate-100 p-5 rounded-3xl outline-none text-xl text-center focus:border-green-400 font-black uppercase shadow-inner">
            <button type="button" onclick="startAction()" class="w-full bg-black text-white py-7 rounded-3xl text-2xl shadow-xl active:scale-95 transition-all font-black">Démarrer</button>
            <a href="index.html" class="block w-full text-center text-slate-400 text-[10px] tracking-widest uppercase font-black py-2">RETOUR HUB</a>
        </div>
    </div>

    <div id="game" class="hidden max-w-4xl w-full bg-black/40 backdrop-blur-xl p-6 rounded-[3.5rem] border-4 border-white/5">
        <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-8">
            <a href="index.html" class="text-[10px] bg-white/10 px-6 py-3 rounded-full hover:bg-white/20 transition font-black uppercase">QUITTER</a>
            <div id="players-display" class="flex gap-4 text-sm tracking-widest items-center"></div>
            <button onclick="copyLink()" id="btn-copy" class="hidden bg-green-600 px-6 py-3 rounded-full text-[10px] font-black shadow-lg uppercase">INVITER 🔗</button>
        </div>
        <div id="grid"></div>
        <div id="win-overlay" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-xl items-center justify-center p-4">
        <div class="bg-white rounded-[3rem] p-10 max-w-sm w-full text-center text-slate-900 animate-pop shadow-2xl">
            <p id="win-text" class="text-3xl font-black mb-8 italic tracking-tighter uppercase"></p>
            <div class="w-full space-y-4">
                 <button onclick="resetAndStartGame()" class="w-full bg-black text-white py-6 rounded-2xl font-black text-xl hover:scale-105 transition-all shadow-xl">REJOUER</button>
                 <a href="index.html" class="block w-full bg-slate-200 text-slate-600 py-5 rounded-2xl font-black text-lg hover:scale-105 transition-all shadow-inner">ACCUEIL</a>
            </div>
        </div>
    </div>
    </div>

    <script>
        const API = 'memory.php'; 
        let roomId = new URLSearchParams(window.location.search).get('room');
        let myName = '', gameMode = roomId ? 'remote' : 'local', localOpponent = 'ai';
        let selectedSize = 6;
        let isWaitingForAnimation = false;
        let syncInterval;

        let gameState = { 
            players: [], board: [], currentPlayerIdx: 0, flipped: [], matched: [], gameOver: false, winner: '', gridSize: 6, aiMemory: [], selectedThemeTag: 'kitten'
        };

        const themes = [
            { name: "Kittens", tag: "kitten" },
            { name: "Puppies", tag: "puppy" },
            { name: "Foods", tag: "food" },
            { name: "Nature", tag: "nature" },
            { name: "Cars", tag: "car" }
        ];
        let selectedTheme = "kitten"; // Default theme tag

        // Old kittenImages array is no longer needed as images will be generated dynamically
        // const kittenImages = Array.from({length: 32}, (_, i) => `https://loremflickr.com/200/200/kitten?lock=${i}`);

        window.onload = () => {
            renderThemeSelector();
            if(roomId) {
                document.getElementById('mode-selector').classList.add('hidden');
                document.getElementById('difficulty-selector').classList.add('hidden');
                document.getElementById('theme-selector').classList.add('hidden'); // Also hide theme selector for joining players
                document.getElementById('local-options').classList.add('hidden');
                document.getElementById('setup-title').innerHTML = "REJOINDRE <span class='text-slate-200'>ARENA</span>";
                setMode('remote');
            } else {
                setMode('local');
            }
        };

        function renderThemeSelector() {
            const themesGrid = document.getElementById('themes-grid');
            themesGrid.innerHTML = themes.map(theme => `
                <button type="button" onclick="setTheme('${theme.tag}')" id="btn-theme-${theme.tag}" class="py-3 rounded-xl text-xs font-black ${selectedTheme === theme.tag ? 'bg-green-600 text-white shadow-md' : 'bg-slate-200 text-slate-500'}">
                    ${theme.name}
                </button>
            `).join('');
        }

        function setTheme(themeTag) {
            selectedTheme = themeTag;
            renderThemeSelector(); // Re-render to update active state
        }

        function setMode(m) {
            gameMode = m;
            document.getElementById('m-local').className = (m === 'local') ? "bg-green-600 text-white py-4 rounded-2xl shadow-lg font-black" : "bg-slate-200 text-slate-500 py-4 rounded-2xl font-black";
            document.getElementById('m-remote').className = (m === 'remote') ? "bg-green-600 text-white py-4 rounded-2xl shadow-lg font-black" : "bg-slate-200 text-slate-500 py-4 rounded-2xl font-black";
            document.getElementById('local-options').classList.toggle('hidden', m !== 'local');
            // Remove the line that hides difficulty-selector for remote mode
        }

        function setGridSize(s) {
            selectedSize = s;
            [4, 6, 8].forEach(x => {
                document.getElementById('btn-s'+x).className = (x === s) ? "bg-green-600 text-white py-3 rounded-xl text-xs font-black shadow-md" : "bg-slate-200 text-slate-500 py-3 rounded-xl text-xs font-black";
            });
        }

        function setOpponent(opp) {
            localOpponent = opp;
            document.getElementById('opp-ai').className = (opp === 'ai') ? "bg-green-600 text-white py-4 rounded-2xl text-xs font-black shadow-md" : "bg-slate-200 text-slate-500 py-4 rounded-2xl text-xs font-black";
            document.getElementById('opp-human').className = (opp === 'human') ? "bg-green-600 text-white py-4 rounded-2xl text-xs font-black shadow-md" : "bg-slate-200 text-slate-500 py-4 rounded-2xl text-xs font-black";
        }

        async function startAction() {
            const name = document.getElementById('my-name-in').value.trim();
            if(!name) return alert("Pseudo !"); myName = name.toUpperCase();

            if (gameMode === 'remote') {
                if (!roomId) roomId = Math.random().toString(36).substring(2, 8);
                window.history.pushState({}, '', `?room=${roomId}`);
                await syncPull();
                
                // If this is the first player, or if rejoining and game is over (meaning new game should start)
                if (!gameState.players.find(p => p.name === myName) || gameState.gameOver) {
                    if (gameState.players.length < 2) {
                        resetGameState(); // Reset state
                        gameState.gridSize = selectedSize; // Set grid size from setup
                        gameState.selectedThemeTag = selectedTheme; // Set the selected theme
                        gameState.selectedThemeTag = selectedTheme; // Set the selected theme
                        initBoard(); // Create new board
                        gameState.players.push({ name: myName, score: 0 }); // Add player
                    } else return alert("Plein !"); // Room is full
                } 
                syncInterval = setInterval(syncPull, 1000);
            } else {
                resetGameState(); // Reset state for local game
                gameState.gridSize = selectedSize; // Set grid size from setup
                gameState.selectedThemeTag = selectedTheme; // Set the selected theme for local games
                initBoard(); // Create new board
                gameState.players = [{ name: myName, score: 0 }, { name: localOpponent === 'ai' ? 'IA 🤖' : 'JOUEUR 2', score: 0 }];
                if (localOpponent === 'ai') gameState.aiMemory = Array(gameState.gridSize * gameState.gridSize).fill(null);
            }

            document.getElementById('setup').classList.add('hidden');
            document.getElementById('game').classList.remove('hidden');
            if(gameMode === 'remote') { document.getElementById('btn-copy').classList.remove('hidden'); await syncPush(); }
            render();
        }

        function initBoard() {
            const numPairs = (gameState.gridSize * gameState.gridSize) / 2;
            // Dynamically generate images based on the selected theme tag
            let images = Array.from({length: numPairs}, (_, i) => `https://loremflickr.com/200/200/${gameState.selectedThemeTag}?lock=${i}`);
            let pairs = [...images, ...images].sort(() => Math.random() - 0.5);
            gameState.board = pairs;
        }

        // New function to reset game state to initial values
        function resetGameState() {
            gameState.board = [];
            gameState.currentPlayerIdx = 0;
            gameState.flipped = [];
            gameState.matched = [];
            gameState.gameOver = false;
            gameState.winner = '';
            gameState.aiMemory = []; // Reset AI memory
            gameState.players.forEach(p => p.score = 0); // Reset player scores
            isWaitingForAnimation = false; // Clear any lingering animation flags
        }

        // Function to reset and start a new game
        async function resetAndStartGame() {
            resetGameState(); // Reset all game state variables
            initBoard(); // Initialize the board with new pairs

            if (gameMode === 'remote') {
                // In remote mode, push the new state to the server to synchronize with other players
                await syncPush();
            } else if (gameMode === 'local' && localOpponent === 'ai') {
                // If local AI game, ensure AI memory is cleared
                gameState.aiMemory = Array(gameState.gridSize * gameState.gridSize).fill(null);
            }
            
            render(); // Render the new game state
            // Hide the win overlay if it was visible
            document.getElementById('win-overlay').classList.add('hidden');
            document.getElementById('win-overlay').classList.remove('flex');

            // If it's a local AI game and AI's turn, make AI move
            if (gameMode === 'local' && localOpponent === 'ai' && gameState.players[gameState.currentPlayerIdx].name === 'IA 🤖') {
                 setTimeout(aiMove, 500);
            }
        }

        async function syncPush() { if(roomId && gameMode === 'remote') await fetch(`${API}?action=sync&roomId=${roomId}`, { method: 'POST', body: JSON.stringify(gameState) }); }

        async function syncPull() {
            if(!roomId || gameMode !== 'remote') return;
            try { 
                const r = await fetch(`${API}?action=sync&roomId=${roomId}&t=${Date.now()}`); 
                const data = await r.json(); 
                if (data && JSON.stringify(data) !== JSON.stringify(gameState)) { 
                    gameState = data; 
                    render(); 
                } 
            } catch(e) {}
        }

        function handleFlip(idx) {
            const isMyTurn = gameState.players[gameState.currentPlayerIdx]?.name === myName;
            if (isWaitingForAnimation || gameState.gameOver || gameState.flipped.includes(idx) || gameState.matched.includes(idx) || (gameMode === 'remote' && !isMyTurn) || gameState.flipped.length >= 2) return;
            
            if (localOpponent === 'ai' && gameState.players[gameState.currentPlayerIdx]?.name === 'IA 🤖') gameState.aiMemory[idx] = gameState.board[idx];
            
            gameState.flipped.push(idx);
            render(); 

            if (gameState.flipped.length === 2) {
                isWaitingForAnimation = true;
                setTimeout(checkMatch, 1000);
            }
            if (gameMode === 'remote') syncPush();
        }

        function checkMatch() {
            isWaitingForAnimation = false;
            if (gameState.flipped.length < 2) return;
            const [i1, i2] = gameState.flipped;
            
            if (gameState.board[i1] === gameState.board[i2]) {
                gameState.matched.push(i1, i2);
                gameState.players[gameState.currentPlayerIdx].score++;
                if (gameState.matched.length === gameState.board.length) {
                    gameState.gameOver = true;
                    let winnerScore = -1;
                    let winnerName = "ÉGALITÉ";
                    let isTie = false;
                    
                    gameState.players.forEach(p => {
                        if (p.score > winnerScore) {
                            winnerScore = p.score;
                            winnerName = p.name;
                            isTie = false;
                        } else if (p.score === winnerScore) {
                            isTie = true;
                        }
                    });
                    gameState.winner = isTie ? "ÉGALITÉ" : winnerName;
                }
            } else {
                gameState.currentPlayerIdx = (gameState.currentPlayerIdx + 1) % gameState.players.length;
            }
            
            gameState.flipped = [];

            if (!gameState.gameOver && gameMode === 'local' && localOpponent === 'ai' && gameState.players[gameState.currentPlayerIdx].name === 'IA 🤖') {
                setTimeout(aiMove, 500);
            }
            
            syncPush();
            render();
        }

        function aiMove() {
            // AI logic remains the same
            for (let i = 0; i < gameState.aiMemory.length; i++) {
                if (gameState.aiMemory[i] && !gameState.matched.includes(i)) {
                    for (let j = i + 1; j < gameState.aiMemory.length; j++) {
                        if (gameState.aiMemory[i] === gameState.aiMemory[j] && !gameState.matched.includes(j)) {
                            handleFlip(i); setTimeout(() => handleFlip(j), 500); return;
                        }
                    }
                }
            }
            let available = gameState.board.map((_, i) => i).filter(i => !gameState.matched.includes(i) && gameState.aiMemory[i] === null);
            if (available.length === 0) available = gameState.board.map((_, i) => i).filter(i => !gameState.matched.includes(i));
            if(available.length === 0) return;
            let move1 = available[Math.floor(Math.random() * available.length)];
            handleFlip(move1);
            setTimeout(() => {
                const pairIndex = gameState.aiMemory.findIndex((card, index) => card === gameState.board[move1] && index !== move1 && !gameState.matched.includes(index));
                if (pairIndex !== -1) { handleFlip(pairIndex); } 
                else {
                    let available2 = gameState.board.map((_, i) => i).filter(i => !gameState.matched.includes(i) && !gameState.flipped.includes(i));
                    if(available2.length > 0) handleFlip(available2[Math.floor(Math.random() * available2.length)]);
                }
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
                    <div class="card-inner">
                        <div class="card-face card-front"></div>
                        <div class="card-face card-back" style="background-image: url('${img}')"></div>
                    </div>
                `;
                grid.appendChild(card);
            });

            const p1 = gameState.players[0] || { name: 'ATTENTE...', score: 0 };
            const p2 = gameState.players[1] || { name: '...', score: 0 };
            const p1Color = '#60a5fa'; // Blue for P1
            const p2Color = '#f87171'; // Red for P2
            
            document.getElementById('players-display').innerHTML = `
                <div style="--tw-shadow-color: ${p1Color};" class="px-4 py-2 rounded-2xl transition-all border-2 border-transparent ${gameState.currentPlayerIdx === 0 ? 'active-turn bg-black/40' : 'opacity-60'}">
                    <span style="color:${p1Color}; text-shadow: 0 0 10px ${p1Color};">${p1.name}</span>: ${p1.score}
                </div>
                <span class="text-slate-600 italic font-bold">VS</span>
                <div style="--tw-shadow-color: ${p2Color};" class="px-4 py-2 rounded-2xl transition-all border-2 border-transparent ${gameState.currentPlayerIdx === 1 ? 'active-turn bg-black/40' : 'opacity-60'}">
                    <span style="color:${p2Color}; text-shadow: 0 0 10px ${p2Color};">${p2.name}</span>: ${p2.score}
                </div>
            `;

            if (gameState.gameOver) {
                const modal = document.getElementById('win-overlay');
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.getElementById('win-text').innerText = gameState.winner === 'ÉGALITÉ' ? 'ÉGALITÉ !' : gameState.winner + " GAGNE !";
            }
        }

        function copyLink() { navigator.clipboard.writeText(window.location.href); alert("LIEN COPIÉ !"); }
    </script>
</body>
</html>
