<?php
// Sécurité : Ne pas afficher d'erreurs brutes aux utilisateurs
error_reporting(0);

// Chargement des données
$file = 'stats.json';
$history = [];
if (file_exists($file)) {
    $json_content = file_get_contents($file);
    $history = json_decode($json_content, true);
}

// Inverser pour avoir les parties récentes en premier
$recent_games = array_reverse($history);

// Calcul des statistiques par jeu
$stats = [];
foreach ($history as $game) {
    $gameName = $game['game'];
    $winner = $game['winner'];
    
    if (!isset($stats[$gameName])) {
        $stats[$gameName] = ['players' => []];
    }
    
    foreach ($game['results'] as $res) {
        $pName = $res['name'];
        $pScore = intval($res['score']);
        
        if (!isset($stats[$gameName]['players'][$pName])) {
            $stats[$gameName]['players'][$pName] = ['wins' => 0, 'best' => null];
        }
        
        // Compter les victoires
        if ($pName === $winner) {
            $stats[$gameName]['players'][$pName]['wins']++;
        }
        
        // Calculer le record (Min pour Skyjo, Max pour les autres)
        $isSkyjo = (stripos($gameName, 'skyjo') !== false);
        if ($stats[$gameName]['players'][$pName]['best'] === null) {
            $stats[$gameName]['players'][$pName]['best'] = $pScore;
        } else {
            if ($isSkyjo) {
                $stats[$gameName]['players'][$pName]['best'] = min($stats[$gameName]['players'][$pName]['best'], $pScore);
            } else {
                $stats[$gameName]['players'][$pName]['best'] = max($stats[$gameName]['players'][$pName]['best'], $pScore);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Palmarès - Comme à la maison</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background: radial-gradient(circle at center, #0a2f1a 0%, #000000 100%); min-height: 100vh; color: white; }
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body class="p-4 sm:p-8 font-sans uppercase font-black">

    <div class="max-w-6xl mx-auto">
        <div class="flex justify-between items-center mb-12 mt-6">
            <h1 class="text-4xl sm:text-6xl font-black text-yellow-500 tracking-tighter italic">🏆 PALMARÈS</h1>
            <a href="index.html" class="glass text-white px-6 py-3 rounded-2xl font-bold border border-white/10 text-[10px] tracking-widest hover:bg-white/10 transition">RETOUR HUB</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
            <?php if (empty($stats)): ?>
                <div class="lg:col-span-3 text-center p-20 glass rounded-[3rem] text-slate-500 tracking-widest">EN ATTENTE DE DONNÉES...</div>
            <?php else: ?>
                <?php foreach ($stats as $gameName => $data): 
                    $accent = "border-yellow-500";
                    if(stripos($gameName, 'skyjo') !== false) $accent = "border-green-500";
                    if(stripos($gameName, 'bac') !== false) $accent = "border-indigo-500";
                    
                    // Trier les joueurs par nombre de victoires
                    uasort($data['players'], function($a, $b) { return $b['wins'] - $a['wins']; });
                ?>
                    <div class="glass rounded-[3rem] p-8 border-t-4 <?php echo $accent; ?> shadow-2xl">
                        <h3 class="text-xl font-black mb-6 italic text-white"><?php echo htmlspecialchars($gameName); ?></h3>
                        <div class="space-y-3">
                            <?php foreach ($data['players'] as $name => $info): ?>
                                <div class="flex justify-between items-center bg-white/5 p-4 rounded-2xl border border-white/5">
                                    <div>
                                        <div class="text-[10px] font-black text-slate-300 uppercase"><?php echo htmlspecialchars($name); ?></div>
                                        <div class="text-[8px] font-bold text-slate-500 tracking-widest uppercase">RECORD: <?php echo $info['best']; ?></div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-2xl font-black text-yellow-500"><?php echo $info['wins']; ?></span>
                                        <span class="text-[8px] block text-slate-500">VICTOIRES</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="glass rounded-[3.5rem] p-8 shadow-2xl border border-white/10">
            <h2 class="text-2xl font-black text-yellow-500 mb-8 italic tracking-tighter">Historique des parties</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="text-[10px] font-black text-slate-500 uppercase tracking-[0.3em] border-b border-white/5">
                            <th class="p-4">Date</th>
                            <th class="p-4">Jeu</th>
                            <th class="p-4">Gagnant</th>
                            <th class="p-4 text-center">Score</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs font-bold">
                        <?php foreach ($recent_games as $game): 
                            $winScore = '---';
                            foreach($game['results'] as $r) {
                                if($r['name'] === $game['winner']) { $winScore = $r['score']; break; }
                            }
                        ?>
                            <tr class="border-b border-white/5 hover:bg-white/5 transition-colors">
                                <td class="p-4 text-slate-400 text-[10px]"><?php echo htmlspecialchars($game['date'] ?? '18/01/2026'); ?></td>
                                <td class="p-4 text-white italic"><?php echo htmlspecialchars($game['game']); ?></td>
                                <td class="p-4">
                                    <span class="bg-yellow-500 text-black px-3 py-1 rounded-lg text-[9px] font-black"><?php echo htmlspecialchars($game['winner']); ?></span>
                                </td>
                                <td class="p-4 text-center text-xl font-black text-yellow-500"><?php echo $winScore; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <footer class="mt-20 pt-8 border-t border-white/5 flex justify-between items-center text-[10px] font-bold text-slate-700 tracking-widest">
            <p>Hébergé par OVH Cloud</p>
            <p>Comme à la maison &copy; 2026</p>
        </footer>
    </div>

</body>
</html>