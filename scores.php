<?php
error_reporting(0);
$file = 'stats.json';
$history = [];
if (file_exists($file)) {
    $json_content = file_get_contents($file);
    $history = json_decode($json_content, true);
}
$recent_games = array_reverse($history);

$stats = [];
foreach ($history as $game) {
    $gameName = $game['game'];
    $winner = $game['winner'];
    if (!isset($stats[$gameName])) { $stats[$gameName] = ['players' => []]; }
    
    foreach ($game['results'] as $res) {
        $pName = $res['name'];
        $pScore = intval($res['score']);
        if (!isset($stats[$gameName]['players'][$pName])) {
            $stats[$gameName]['players'][$pName] = ['wins' => 0, 'best' => null];
        }
        if ($pName === $winner) { $stats[$gameName]['players'][$pName]['wins']++; }
        
        $isSkyjo = (stripos($gameName, 'skyjo') !== false);
        if ($stats[$gameName]['players'][$pName]['best'] === null) {
            $stats[$gameName]['players'][$pName]['best'] = $pScore;
        } else {
            $stats[$gameName]['players'][$pName]['best'] = $isSkyjo ? min($stats[$gameName]['players'][$pName]['best'], $pScore) : max($stats[$gameName]['players'][$pName]['best'], $pScore);
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
        <div class="flex justify-between items-center mb-16 mt-6">
            <h1 class="text-5xl sm:text-7xl font-black text-yellow-500 tracking-tighter italic">🏆 PALMARÈS</h1>
            <a href="index.html" class="glass text-white px-8 py-4 rounded-2xl font-bold border border-white/10 text-xs tracking-widest hover:bg-white/10 transition">RETOUR HUB</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10 mb-20">
            <?php if (empty($stats)): ?>
                <div class="lg:col-span-3 text-center p-20 glass rounded-[3rem] text-slate-500 tracking-widest">EN ATTENTE DE DONNÉES...</div>
            <?php else: ?>
                <?php foreach ($stats as $gameName => $data): 
                    $accent = "border-yellow-500";
                    if(stripos($gameName, 'skyjo') !== false) $accent = "border-green-500";
                    if(stripos($gameName, 'bac') !== false) $accent = "border-indigo-500";
                    uasort($data['players'], function($a, $b) { return $b['wins'] - $a['wins']; });
                ?>
                    <div class="glass rounded-[4rem] p-10 border-t-4 <?php echo $accent; ?> shadow-2xl">
                        <h3 class="text-3xl font-black mb-8 italic text-white tracking-tighter"><?php echo htmlspecialchars($gameName); ?></h3>
                        <div class="space-y-6">
                            <?php foreach ($data['players'] as $name => $info): ?>
                                <div class="flex justify-between items-center bg-white/5 p-6 rounded-[2rem] border border-white/5">
                                    <div>
                                        <div class="text-sm font-black text-slate-300 uppercase"><?php echo htmlspecialchars($name); ?></div>
                                        <div class="text-[10px] font-bold text-slate-500 tracking-widest uppercase">RECORD: <span class="text-slate-300"><?php echo $info['best']; ?></span></div>
                                    </div>
                                    <div class="text-right">
                                        <span class="text-6xl font-black text-yellow-500 leading-none"><?php echo $info['wins']; ?></span>
                                        <span class="text-[9px] block text-slate-500 mt-1">VICTOIRES</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="glass rounded-[4rem] p-10 shadow-2xl border border-white/10">
            <h2 class="text-3xl font-black text-yellow-500 mb-10 italic tracking-tighter">Historique des parties</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-separate border-spacing-y-2">
                    <thead>
                        <tr class="text-[11px] font-black text-slate-500 uppercase tracking-[0.4em]">
                            <th class="px-6 pb-4">Date</th>
                            <th class="px-6 pb-4">Jeu</th>
                            <th class="px-6 pb-4">Gagnant</th>
                            <th class="px-6 pb-4 text-center">Score</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm font-bold">
                        <?php foreach ($recent_games as $game): 
                            $winScore = '---';
                            foreach($game['results'] as $r) {
                                if($r['name'] === $game['winner']) { $winScore = $r['score']; break; }
                            }
                        ?>
                            <tr class="bg-white/5 hover:bg-white/10 transition-colors">
                                <td class="px-6 py-6 rounded-l-3xl text-slate-400 text-xs"><?php echo htmlspecialchars($game['date'] ?? '2026'); ?></td>
                                <td class="px-6 py-6 text-white italic text-lg"><?php echo htmlspecialchars($game['game']); ?></td>
                                <td class="px-6 py-6">
                                    <span class="bg-yellow-500 text-black px-4 py-2 rounded-xl text-xs font-black"><?php echo htmlspecialchars($game['winner']); ?></span>
                                </td>
                                <td class="px-6 py-6 rounded-r-3xl text-center text-5xl font-black text-yellow-500 tabular-nums">
                                    <?php echo $winScore; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <footer class="mt-20 pt-10 border-t border-white/5 flex justify-between items-center text-[11px] font-bold text-slate-700 tracking-widest pb-10">
            <p>HÉBERGÉ PAR OVH CLOUD</p>
            <p>COMME À LA MAISON &copy; 2026</p>
        </footer>
    </div>

</body>
</html>