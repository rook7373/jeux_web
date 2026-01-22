<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0">
    <title>Puissance 4 Arena - Live</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: radial-gradient(circle at center, #0f172a 0%, #000000 100%); color: white; min-height: 100vh; overflow-x: hidden; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); }
        
        /* Plateau Bleu Vif pour visibilité */
        #board { background-color: #2563eb; display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.6rem; padding: 1.2rem; border-radius: 2.5rem; border: 8px solid #1e40af; width: 100%; max-width: 500px; box-shadow: 0 25px 50px rgba(0,0,0,0.6); }
        .cell { background-color: #0f172a; border-radius: 50%; aspect-ratio: 1 / 1; position: relative; transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); box-shadow: inset 0 4px 8px rgba(0,0,0,0.5); }
        .column { cursor: pointer; display: flex; flex-direction: column; gap: 0.6rem; border-radius: 1.5rem; padding: 4px; }

        /* Couleurs Jetons */
        .cell.red { background: #ef4444; box-shadow: 0 0 30px rgba(239, 68, 68, 0.7), inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.blue { background: #0ea5e9; box-shadow: 0 0 30px rgba(14, 165, 233, 0.7), inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.green { background: #22c55e; box-shadow: 0 0 30px rgba(34, 197, 94, 0.7), inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.purple { background: #a855f7; box-shadow: 0 0 30px rgba(168, 85, 247, 0.7), inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.cyan { background: #06b6d4; box-shadow: 0 0 30px rgba(6, 182, 212, 0.7), inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.orange { background: #f97316; box-shadow: 0 0 30px rgba(249, 115, 22, 0.7), inset 0 -4px 6px rgba(0,0,0,0.3); }
        .cell.yellow { background: #facc15; box-shadow: 0 0 30px rgba(250, 204, 21, 0.7), inset 0 -4px 6px rgba(0,0,0,0.3); }

        .last-move::after { content: ''; position: absolute; inset: -4px; border: 4px solid white; border-radius: 50%; animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { transform: scale(1); opacity: 1; } 100% { transform: scale(1.4); opacity: 0; } }

        /* Selection Couleur Ultra Visible */
        .color-dot { width: 45px; height: 45px; border-radius: 50%; cursor: pointer; border: 4px solid #f1f5f9; transition: 0.3s; }
        .color-dot.active { border-color: #000000; transform: scale(1.3); box-shadow: 0 0 20px rgba(255,255,255,0.4); }
    </style>
</head>
<body class="p-4 flex items-center justify-center font-sans uppercase font-black">

    <div id="setup" class="max-w-md w-full bg-white p-10 rounded-[3.5rem] shadow-2xl text-slate-900 z-50">
        <h2 id="setup-title" class="text-4xl font-black mb-8 text-blue-600 text-center italic tracking-tighter uppercase">P4 ARENA</h2>
        
        <div id="mode-selector" class="grid grid-cols-2 gap-4 mb-8">
            <button type="button" onclick="setMode('local')" id="m-local" class="bg-blue-600 text-white py-4 rounded-2xl shadow-lg transition font-black">LOCAL</button>
            <button type="button" onclick="setMode('remote')" id="m-remote" class="bg-slate-200 text-slate-500 py-4 rounded-2xl transition font-black">EN LIGNE</button>
        </div>

        <div id="local-options" class="space-y-4 mb-8">
            <p class="text-center text-[10px] font-black text-slate-400">CHOIX DE L'ADVERSAIRE :</p>
            <div class="grid grid-cols-2 gap-3">
                <button type="button" onclick="setOpponent('ai')" id="opp-ai" class="bg-blue-600 text-white py-4 rounded-2xl text-xs font-black shadow-md">🤖 IA</button>
                <button type="button" onclick="setOpponent('human')" id="opp-human" class="bg-slate-200 text-slate-500 py-4 rounded-2xl text-xs font-black">👤 HUMAIN</button>
            </div>
        </div>

        <div class="space-y-6">
            <div class="text-center">
                <p class="text-[10px] text-slate-400 mb-4 tracking-widest uppercase">COULEUR DES PIONS</p>
                <div class="flex justify-center gap-4 flex-wrap">
                    <div onclick="setColor('red')" id="c-red" class="color-dot bg-red-500 active"></div>
                    <div onclick="setColor('blue')" id="c-blue" class="color-dot bg-blue-500"></div>
                    <div onclick="setColor('green')" id="c-green" class="color-dot bg-emerald-500"></div>
                    <div onclick="setColor('purple')" id="c-purple" class="color-dot bg-purple-500"></div>
                    <div onclick="setColor('cyan')" id="c-cyan" class="color-dot bg-cyan-500"></div>
                    <div onclick="setColor('orange')" id="c-orange" class="color-dot bg-orange-500"></div>
                </div>
            </div>

            <input type="text" id="my-name-in" placeholder="TON PSEUDO..." class="w-full bg-slate-100 border-2 border-slate-100 p-5 rounded-3xl outline-none text-xl text-center focus:border-blue-400 font-black uppercase">
            
            <button onclick="startAction()" class="w-full bg-black text-white py-6 rounded-[2rem] text-xl shadow-2xl active:scale-95 transition-all font-black">DÉMARRER</button>
            <button onclick="window.location.href='index.html'" class="w-full text-slate-400 text-[10px] tracking-widest uppercase font-black py-2">RETOUR HUB</button>
        </div>
    </div>

    <div id="game" class="hidden max-w-2xl w-full glass p-8 rounded-[4rem] border border-white/10 shadow-2xl">
        <div class="flex justify-between items-center mb-8">
            <button onclick="window.location.href='index.html'" class="text-[10px] bg-white/10 px-6 py-3 rounded-full hover:bg-white/20 transition font-black">MENU</button>
            <div id="players-display" class="flex gap-8 text-[12px] tracking-widest items-center font-black"></div>
            <button onclick="copyLink()" id="btn-copy" class="hidden bg-blue-600 px-6 py-3 rounded-full text-[10px] font-black shadow-lg uppercase">LIEN</button>
        </div>
        <div class="flex justify-center mb-10"><div id="board"></div></div>
        <div id="win-overlay" class="hidden text-center">
            <p id="win-text" class="text-5xl text-yellow-500 mb-8 italic tracking-tighter"></p>
            <button onclick="saveAndReset()" class="bg-white text-black px-12 py-5 rounded-full text-sm font-black shadow-xl hover:scale-105 transition uppercase">ENREGISTRER & QUITTER</button>
        </div>
    </div>

    <script>
        const API = 'api_puissance4.php'; // On pointe vers le nouvel API
        const ROWS = 6, COLS = 7;
        let roomId = new URLSearchParams(window.location.search).get('room');
        let myName = '', myColor = 'red', gameMode = roomId ? 'remote' : 'local', localOpponent = 'ai';
        let gameState = { players: [], board: Array(6).fill(null).map(() => Array(7).fill(null)), currentPlayer: '', lastMove: null, gameOver: false, winner: '' };

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
            document.getElementById('m-local').className = (m === 'local') ? "bg-blue-600 text-white py-4 rounded-2xl shadow-lg font-black" : "bg-slate-200 text-slate-500 py-4 rounded-2xl font-black";
            document.getElementById('m-remote').className = (m === 'remote') ? "bg-purple-600 text-white py-4 rounded-2xl shadow-lg font-black" : "bg-slate-200 text-slate-500 py-4 rounded-2xl font-black";
            document.getElementById('local-options').classList.toggle('hidden', m !== 'local');
        }

        function setOpponent(opp) { 
            localOpponent = opp; 
            document.getElementById('opp-ai').className = (opp === 'ai') ? "bg-blue-600 text-white py-4 rounded-2xl text-xs font-black shadow-md" : "bg-slate-200 text-slate-500 py-4 rounded-2xl text-xs font-black";
            document.getElementById('opp-human').className = (opp === 'human') ? "bg-blue-600 text-white py-4 rounded-2xl text-xs font-black shadow-md" : "bg-slate-200 text-slate-500 py-4 rounded-2xl text-xs font-black";
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
                    myColor = ['red', 'blue', 'green', 'purple', 'cyan', 'orange'].find(c => c !== gameState.players[0].color);
                }
                if (!gameState.players.find(p => p.name === myName)) {
                    if (gameState.players.length < 2) {
                        gameState.players.push({ name: myName, color: myColor });
                        if (gameState.players.length === 1) gameState.currentPlayer = myColor;
                    } else return alert("Plein !");
                } setInterval(syncPull, 2000);
            } else {
                gameState.players = [{ name: myName, color: myColor }, { name: localOpponent === 'ai' ? 'IA 🤖' : 'JOUEUR 2', color: (myColor === 'yellow' ? 'red' : 'yellow') }];
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
                if (data) { gameState = data; render(); } 
            } catch(e) {}
        }

        function handleMove(col) {
            if (gameState.gameOver || (gameMode === 'remote' && (gameState.players.length < 2 || gameState.currentPlayer !== myColor))) return;
            for (let r = ROWS - 1; r >= 0; r--) {
                if (!gameState.board[r][col]) {
                    gameState.board[r][col] = gameState.currentPlayer;
                    gameState.lastMove = { r, c: col };
                    if (checkWin(r, col)) {
                        gameState.gameOver = true;
                        gameState.winner = gameState.players.find(p => p.color === gameState.currentPlayer).name;
                    } else {
                        const otherP = gameState.players.find(p => p.color !== gameState.currentPlayer);
                        gameState.currentPlayer = otherP ? otherP.color : '';
                        if(gameMode === 'local' && gameState.currentPlayer !== myColor && localOpponent === 'ai') setTimeout(aiMove, 600);
                    }
                    if(gameMode === 'remote') syncPush(); render(); return;
                }
            }
        }

        function aiMove() {
            let valid = []; for(let c=0; c<COLS; c++) if(!gameState.board[0][c]) valid.push(c);
            if(valid.length) handleMove(valid[Math.floor(Math.random()*valid.length)]);
        }

        function checkWin(r, c) {
            const p = gameState.board[r][c], dirs = [[0,1],[1,0],[1,1],[1,-1]];
            return dirs.some(([dr, dc]) => {
                let count = 1; [[dr, dc], [-dr, -dc]].forEach(([sr, sc]) => {
                    for(let i=1; i<4; i++) {
                        let nr=r+sr*i, nc=c+sc*i; if(nr>=0 && nr<ROWS && nc>=0 && nc<COLS && gameState.board[nr][nc]===p) count++; else break;
                    }
                }); return count >= 4;
            });
        }

        function render() {
            const b = document.getElementById('board'); b.innerHTML = '';
            for (let c = 0; c < COLS; c++) {
                const colEl = document.createElement('div'); colEl.className = 'column'; colEl.onclick = () => handleMove(c);
                for (let r = 0; r < ROWS; r++) {
                    const cell = document.createElement('div');
                    const last = gameState.lastMove && gameState.lastMove.r === r && gameState.lastMove.c === c;
                    cell.className = `cell ${gameState.board[r][c] || ''} ${last ? 'last-move' : ''}`;
                    colEl.appendChild(cell);
                } b.appendChild(colEl);
            }
            const p1 = gameState.players[0] || { name: '...', color: 'slate' }, p2 = gameState.players[1] || { name: '...', color: 'slate' };
            document.getElementById('players-display').innerHTML = `
                <span class="${gameState.currentPlayer === p1.color ? 'ring-2 ring-white/20 bg-white/5' : 'opacity-40'} px-4 py-2 rounded-2xl" style="color:${p1.color}">${p1.name}</span>
                <span class="text-slate-700 font-bold italic">VS</span>
                <span class="${gameState.currentPlayer === p2.color ? 'ring-2 ring-white/20 bg-white/5' : 'opacity-40'} px-4 py-2 rounded-2xl" style="color:${p2.color}">${p2.name}</span>
            `;
            if (gameState.gameOver) { document.getElementById('win-overlay').classList.remove('hidden'); document.getElementById('win-text').innerText = gameState.winner + " GAGNE !"; }
        }

        async function saveAndReset() {
            const gameData = { 
                game: "Puissance 4", 
                date: new Date().toLocaleDateString('fr-FR'), 
                results: gameState.players.map(p => ({ name: p.name, score: p.name === gameState.winner ? 1 : 0 })), 
                winner: gameState.winner 
            };
            try { 
                const r = await fetch(`${API}?action=stats`); 
                let history = await r.json(); 
                if (!Array.isArray(history)) history = []; 
                history.push(gameData);
                await fetch(`${API}?action=stats`, { method: 'POST', body: JSON.stringify(history) }); 
                window.location.href = 'index.html';
            } catch (e) { window.location.href = 'index.html'; }
        }
        function copyLink() { navigator.clipboard.writeText(window.location.href); alert("LIEN COPIÉ !"); }
    </script>
</body>
</html>