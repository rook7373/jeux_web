<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Puissance 4 Arena - Live</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Yams_multi theme */
        body { background: radial-gradient(circle at center, #0a2f1a 0%, #000000 100%); color: white; min-height: 100vh; overflow-x: hidden; -webkit-tap-highlight-color: transparent; font-family: 'Inter', sans-serif; }
        
        #board { 
            background-color: rgba(10, 40, 60, 0.7);
            display: grid; 
            grid-template-columns: repeat(7, 1fr); 
            gap: 0.6rem; 
            padding: 1rem; 
            border-radius: 2.5rem; 
            border: 8px solid rgba(17, 24, 39, 0.8);
            width: 100%; 
            max-width: 580px; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.6); 
        }
        .cell { 
            background-color: rgba(0,0,0,0.4); 
            border-radius: 50%; 
            aspect-ratio: 1 / 1; 
            position: relative; 
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
            box-shadow: inset 0 4px 8px rgba(0,0,0,0.5); 
        }
        .column { cursor: pointer; display: flex; flex-direction: column; gap: 0.6rem; border-radius: 1.5rem; padding: 4px; transition: background-color 0.2s; }
        .column:hover { background-color: rgba(255,255,255,0.1); }

        .cell.red { background: #ef4444; box-shadow: 0 0 25px rgba(239, 68, 68, 0.6), inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.blue { background: #3b82f6; box-shadow: 0 0 25px rgba(59, 130, 246, 0.6), inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.green { background: #22c55e; box-shadow: 0 0 25px rgba(34, 197, 94, 0.6), inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.purple { background: #a855f7; box-shadow: 0 0 25px rgba(168, 85, 247, 0.6), inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.cyan { background: #06b6d4; box-shadow: 0 0 25px rgba(6, 182, 212, 0.6), inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.orange { background: #f97316; box-shadow: 0 0 25px rgba(249, 115, 22, 0.6), inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.yellow { background: #facc15; box-shadow: 0 0 25px rgba(250, 204, 21, 0.6), inset 0 -4px 6px rgba(0,0,0,0.3); }

        .last-move::after { content: ''; position: absolute; inset: 4px; border: 3px solid white; border-radius: 50%; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { transform: scale(0.8); opacity: 0.8; } 100% { transform: scale(1.3); opacity: 0; } }

        .color-dot { width: 45px; height: 45px; border-radius: 50%; cursor: pointer; border: 4px solid transparent; transition: 0.3s; }
        .color-dot.active { border-color: #000000; transform: scale(1.2); box-shadow: 0 0 10px rgba(0,0,0,0.2); }
        input::placeholder { color: #9ca3af; }
    </style>
</head>
<body class="p-4 flex items-center justify-center font-sans uppercase font-black">

    <div id="setup" class="max-w-md w-full bg-white p-8 md:p-12 rounded-[3.5rem] shadow-2xl text-slate-900 z-50">
        <h2 id="setup-title" class="text-4xl font-black mb-8 text-green-900 text-center italic tracking-tighter uppercase">P4 ARENA</h2>
        
        <div id="mode-selector" class="grid grid-cols-2 gap-4 mb-8">
            <button type="button" onclick="setMode('local')" id="m-local" class="bg-green-600 text-white py-4 rounded-2xl shadow-lg transition font-black">LOCAL</button>
            <button type="button" onclick="setMode('remote')" id="m-remote" class="bg-slate-200 text-slate-500 py-4 rounded-2xl transition font-black">EN LIGNE</button>
        </div>

        <div id="local-options" class="space-y-4 mb-8">
            <p class="text-center text-[10px] font-black text-slate-400">CHOIX DE L'ADVERSAIRE :</p>
            <div class="grid grid-cols-2 gap-3">
                <button type="button" onclick="setOpponent('ai')" id="opp-ai" class="bg-green-600 text-white py-4 rounded-2xl text-xs font-black shadow-md">🤖 IA</button>
                <button type="button" onclick="setOpponent('human')" id="opp-human" class="bg-slate-200 text-slate-500 py-4 rounded-2xl text-xs font-black">👤 HUMAIN</button>
            </div>
        </div>

        <div class="space-y-6">
            <div class="text-center">
                <p class="text-[10px] text-slate-400 mb-4 tracking-widest uppercase">CHOISIS TA COULEUR</p>
                <div class="flex justify-center gap-4 flex-wrap">
                    <div onclick="setColor('red')" id="c-red" class="color-dot bg-red-500 active"></div>
                    <div onclick="setColor('blue')" id="c-blue" class="color-dot bg-blue-500"></div>
                    <div onclick="setColor('green')" id="c-green" class="color-dot bg-emerald-500"></div>
                    <div onclick="setColor('purple')" id="c-purple" class="color-dot bg-purple-500"></div>
                    <div onclick="setColor('cyan')" id="c-cyan" class="color-dot bg-cyan-500"></div>
                    <div onclick="setColor('orange')" id="c-orange" class="color-dot bg-orange-500"></div>
                </div>
            </div>

            <input type="text" id="my-name-in" onkeydown="if(event.key === 'Enter') startAction()" placeholder="TON PSEUDO..." class="w-full bg-slate-100 p-5 rounded-3xl outline-none text-xl text-center focus:border-green-400 font-black uppercase shadow-inner">
            
            <button onclick="startAction()" class="w-full bg-black text-white py-7 rounded-3xl text-2xl shadow-xl active:scale-95 transition-all font-black">DÉMARRER</button>
            <a href="index.html" class="block w-full text-center text-slate-400 text-[10px] tracking-widest uppercase font-black py-2">RETOUR HUB</a>
        </div>
    </div>

    <div id="game" class="hidden relative max-w-2xl w-full bg-black/40 backdrop-blur-xl p-6 md:p-8 rounded-[3.5rem] border-4 border-white/5">
        <div class="flex justify-between items-center mb-8">
            <a href="index.html" class="text-[10px] bg-white/10 px-6 py-3 rounded-full hover:bg-white/20 transition font-black">MENU</a>
            <div id="players-display" class="flex gap-4 md:gap-8 text-sm md:text-base tracking-widest items-center font-black"></div>
            <button onclick="copyLink()" id="btn-copy" class="hidden bg-green-600 text-white text-[10px] px-4 py-2 rounded-full tracking-widest shadow-lg active:scale-90 transition-all font-black uppercase">LIEN</button>
        </div>
        <div class="flex justify-center mb-6"><div id="board"></div></div>
        <div id="win-overlay" class="hidden text-center backdrop-blur-sm bg-black/50 absolute inset-0 m-4 md:m-8 rounded-[3rem] flex-col items-center justify-center">
            <p id="win-text" class="text-5xl text-yellow-400 mb-8 italic tracking-tighter"></p>
            <button onclick="saveAndReset()" class="bg-white text-black px-12 py-5 rounded-full text-sm font-black shadow-2xl hover:scale-105 transition uppercase">ENREGISTRER & QUITTER</button>
        </div>
    </div>

    <script>
        const API = 'api_puissance4.php';
        const ROWS = 6, COLS = 7;
        let roomId = new URLSearchParams(window.location.search).get('room');
        let myName = '', myColor = 'red', gameMode = roomId ? 'remote' : 'local', localOpponent = 'ai';
        let gameState = { players: [], board: Array(6).fill(null).map(() => Array(7).fill(null)), currentPlayer: '', lastMove: null, gameOver: false, winner: '' };
        const availableColors = ['red', 'blue', 'green', 'purple', 'cyan', 'orange', 'yellow'];

        window.onload = () => {
            if(roomId) {
                document.getElementById('mode-selector').classList.add('hidden');
                document.getElementById('local-options').classList.add('hidden');
                document.getElementById('setup-title').innerHTML = "REJOINDRE <span class='text-slate-200'>ARENA</span>";
                gameMode = 'remote';
            } else {
                setMode('local');
                setOpponent('ai');
            }
        };

        function setMode(m) { 
            gameMode = m; 
            document.getElementById('m-local').className = (m === 'local') ? "bg-green-600 text-white py-4 rounded-2xl shadow-lg font-black" : "bg-slate-200 text-slate-500 py-4 rounded-2xl font-black";
            document.getElementById('m-remote').className = (m === 'remote') ? "bg-green-600 text-white py-4 rounded-2xl shadow-lg font-black" : "bg-slate-200 text-slate-500 py-4 rounded-2xl font-black";
            document.getElementById('local-options').classList.toggle('hidden', m !== 'local');
        }

        function setOpponent(opp) { 
            localOpponent = opp; 
            document.getElementById('opp-ai').className = (opp === 'ai') ? "bg-green-600 text-white py-4 rounded-2xl text-xs font-black shadow-md" : "bg-slate-200 text-slate-500 py-4 rounded-2xl text-xs font-black";
            document.getElementById('opp-human').className = (opp === 'human') ? "bg-green-600 text-white py-4 rounded-2xl text-xs font-black shadow-md" : "bg-slate-200 text-slate-500 py-4 rounded-2xl text-xs font-black";
        }

        function setColor(c) { 
            myColor = c; 
            document.querySelectorAll('.color-dot').forEach(d => d.classList.remove('active')); 
            document.getElementById('c-' + c).classList.add('active'); 
        }

        async function startAction() {
            const name = document.getElementById('my-name-in').value.trim();
            if(!name) return alert("Pseudo !"); myName = name.toUpperCase();
            
            if (gameMode === 'remote') {
                if (!roomId) roomId = Math.random().toString(36).substring(2, 8);
                window.history.pushState({}, '', `?room=${roomId}`);
                await syncPull();
                if (gameState.players.length === 1 && gameState.players[0].color === myColor) {
                    myColor = availableColors.find(c => c !== gameState.players[0].color);
                }
                if (!gameState.players.find(p => p.name === myName)) {
                    if (gameState.players.length < 2) {
                        gameState.players.push({ name: myName, color: myColor });
                        if (gameState.players.length === 1) gameState.currentPlayer = myColor;
                    } else return alert("Plein !");
                } setInterval(syncPull, 2000);
            } else {
                const opponentColor = availableColors.find(c => c !== myColor);
                gameState.players = [{ name: myName, color: myColor }, { name: localOpponent === 'ai' ? 'IA 🤖' : 'JOUEUR 2', color: opponentColor }];
                gameState.currentPlayer = myColor;
            }
            document.getElementById('setup').classList.add('hidden');
            document.getElementById('game').classList.remove('hidden');
            if(gameMode === 'remote') { document.getElementById('btn-copy').classList.remove('hidden'); await syncPush(); }
            render();
        }

        async function syncPush() { if(roomId) await fetch(`${API}?action=sync&roomId=${roomId}`, { method: 'POST', body: JSON.stringify(gameState) }); }
        
        async function syncPull() {
            if(!roomId) return;
            try { 
                const r = await fetch(`${API}?action=sync&roomId=${roomId}&t=${Date.now()}`); 
                const data = await r.json(); 
                if (data) { 
                    const isGameOver = gameState.gameOver;
                    gameState = data; 
                    if(isGameOver !== gameState.gameOver) {
                         document.getElementById('win-overlay').classList.toggle('hidden', !gameState.gameOver);
                    }
                    render();
                } 
            } catch(e) {}
        }

        function handleMove(col) {
            if (gameState.gameOver) return;
            const isMyTurn = (gameState.currentPlayer === myColor && gameMode === 'remote') || gameMode === 'local';
            if (!isMyTurn || gameState.players.length < 2) return;

            for (let r = ROWS - 1; r >= 0; r--) {
                if (!gameState.board[r][col]) {
                    gameState.board[r][col] = gameState.currentPlayer;
                    gameState.lastMove = { r, c: col };
                    if (checkWin(r, col)) {
                        gameState.gameOver = true;
                        gameState.winner = gameState.players.find(p => p.color === gameState.currentPlayer).name;
                    } else if (gameState.board[0].every(cell => cell !== null)) {
                        gameState.gameOver = true;
                        gameState.winner = "draw"; // Match Nul
                    } else {
                        const otherP = gameState.players.find(p => p.color !== gameState.currentPlayer);
                        gameState.currentPlayer = otherP.color;
                        if(gameMode === 'local' && gameState.currentPlayer !== myColor && localOpponent === 'ai') setTimeout(aiMove, 600);
                    }
                    if(gameMode === 'remote') syncPush(); 
                    render(); 
                    return;
                }
            }
        }

        function aiMove() {
            let valid = []; for(let c=0; c<COLS; c++) if(!gameState.board[0][c]) valid.push(c);
            if(valid.length) handleMove(valid[Math.floor(Math.random()*valid.length)]);
        }

        function checkWin(r, c) {
            const p = gameState.board[r][c];
            const dirs = [[0,1],[1,0],[1,1],[1,-1]];
            return dirs.some(([dr, dc]) => {
                let count = 1;
                for (let i = 1; i < 4; i++) if (gameState.board[r + dr * i]?.[c + dc * i] === p) count++; else break;
                for (let i = 1; i < 4; i++) if (gameState.board[r - dr * i]?.[c - dc * i] === p) count++; else break;
                return count >= 4;
            });
        }

        function render() {
            const b = document.getElementById('board'); b.innerHTML = '';
            for (let c = 0; c < COLS; c++) {
                const colEl = document.createElement('div'); 
                colEl.className = 'column'; 
                colEl.onclick = () => handleMove(c);
                for (let r = 0; r < ROWS; r++) {
                    const cell = document.createElement('div');
                    const last = gameState.lastMove && gameState.lastMove.r === r && gameState.lastMove.c === c;
                    cell.className = `cell ${gameState.board[r][c] || ''} ${last ? 'last-move' : ''}`;
                    colEl.appendChild(cell);
                } 
                b.appendChild(colEl);
            }
            const p1 = gameState.players[0], p2 = gameState.players[1];
            if(p1) {
                 document.getElementById('players-display').innerHTML = `
                    <span class="${gameState.currentPlayer === p1.color ? 'ring-2 ring-white/30 bg-white/10' : 'opacity-40'} px-4 py-2 rounded-2xl" style="color:${p1.color}; text-shadow: 0 0 10px ${p1.color};">${p1.name}</span>
                    <span class="text-slate-600 font-bold italic">VS</span>
                    <span class="${p2 ? (gameState.currentPlayer === p2.color ? 'ring-2 ring-white/30 bg-white/10' : 'opacity-40') : 'opacity-40'} px-4 py-2 rounded-2xl" style="color:${p2?.color}; text-shadow: 0 0 10px ${p2?.color};">${p2 ? p2.name : '...'}</span>
                `;
            }
           
            if (gameState.gameOver) { 
                document.getElementById('win-overlay').classList.remove('hidden'); 
                document.getElementById('win-text').innerText = gameState.winner === 'draw' ? 'MATCH NUL' : `${gameState.winner} GAGNE !`;
            } else {
                 document.getElementById('win-overlay').classList.add('hidden');
            }
        }

        async function saveAndReset() {
            if (gameMode === 'local') { window.location.href='index.html'; return; }
            const gameData = { game: "Puissance 4", date: new Date().toLocaleDateString('fr-FR'), winner: gameState.winner };
            try { 
                await fetch(`${API}?action=stats`, { method: 'POST', body: JSON.stringify(gameData) }); 
            } catch (e) { console.error("Save failed:", e); }
            finally { window.location.href = 'index.html'; }
        }
        function copyLink() { navigator.clipboard.writeText(window.location.href); alert("LIEN COPIÉ !"); }
    </script>
</body>
</html>
