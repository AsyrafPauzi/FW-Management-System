<?php
/**
 * View: Hardened Responsive Dashboard
 * Location: views/dashboard.php
 * Version: 3.0.0
 */

// 1. Fetch Analytics Data via hardened DB class
$stats = $db->get_stats(); 
$recent = $db->get_recent_workers(5); 
$expiring = $db->get_expiring_workers(5);

// Current Stage Labels for the Chart (aligned with wizard)
$chart_labels = $stats['stage_labels'] ?? ['Reg & Pay', 'FOMEMA', 'Insurance', 'Levy', 'Permit', 'CIDB/Done'];
?>

<div class="space-y-6 md:space-y-10 animate-fade-in pb-20 px-1">
    
    <!-- HEADER AREA -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-end border-b border-slate-200 pb-6 gap-4">
        <div>
            <h2 class="text-3xl md:text-4xl font-black text-slate-800 uppercase italic tracking-tighter leading-none">Overview</h2>
            <p class="text-slate-400 font-bold uppercase text-[10px] md:text-xs tracking-widest mt-2">Real-time Operational Intelligence</p>
        </div>
        <div class="bg-white px-4 py-2 rounded-2xl shadow-sm border border-slate-100 hidden sm:block">
            <span class="text-[10px] font-black uppercase tracking-widest text-slate-400 block mb-1">Server Clock</span>
            <p class="text-sm font-mono font-bold text-slate-700"><?php echo date('d M Y | H:i'); ?></p>
        </div>
    </div>

    <!-- KPI CARDS GRID -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6">
        <!-- Card 1: Total -->
        <div class="bg-white p-5 md:p-6 rounded-[2rem] shadow-sm border border-slate-200 border-l-4 border-l-blue-500 transition-transform hover:scale-[1.02]">
            <p class="text-[9px] md:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Active Workers</p>
            <p class="text-2xl md:text-4xl font-black text-slate-800"><?php echo number_format($stats['total']); ?></p>
        </div>
        <!-- Card 2: Expired -->
        <div class="bg-white p-5 md:p-6 rounded-[2rem] shadow-sm border border-slate-200 border-l-4 border-l-red-500 transition-transform hover:scale-[1.02]">
            <p class="text-[9px] md:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Expired Permits</p>
            <p class="text-2xl md:text-4xl font-black text-red-600"><?php echo number_format($stats['expired']); ?></p>
        </div>
        <!-- Card 3: FOMEMA -->
        <div class="bg-white p-5 md:p-6 rounded-[2rem] shadow-sm border border-slate-200 border-l-4 border-l-orange-400 transition-transform hover:scale-[1.02]">
            <p class="text-[9px] md:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Pending FOMEMA</p>
            <p class="text-2xl md:text-4xl font-black text-orange-500"><?php echo number_format($stats['fomema_pending']); ?></p>
        </div>
        <!-- Card 4: Done -->
        <div class="bg-white p-5 md:p-6 rounded-[2rem] shadow-sm border border-slate-200 border-l-4 border-l-emerald-500 transition-transform hover:scale-[1.02]">
            <p class="text-[9px] md:text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Ready/Done</p>
            <p class="text-2xl md:text-4xl font-black text-emerald-600"><?php echo number_format($stats['completed']); ?></p>
        </div>
    </div>

    <!-- CHARTS & HEATMAP SECTION -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">
        <!-- BAR CHART: PROGRESS -->
        <div class="lg:col-span-2 bg-white p-5 md:p-8 rounded-[2.5rem] shadow-sm border border-slate-200">
            <h3 class="font-black text-slate-800 uppercase tracking-widest text-[10px] md:text-xs mb-6 flex items-center gap-2">
                <span class="bg-blue-100 p-1 rounded-lg">📊</span> Workflow Distribution
            </h3>
            <div class="relative w-full h-[250px] md:h-[300px]">
                <canvas id="stageChart"></canvas>
            </div>
        </div>

        <!-- ACTIVITY HEATMAP -->
        <div class="bg-white p-5 md:p-8 rounded-[2.5rem] shadow-sm border border-slate-200">
            <h3 class="font-black text-slate-800 uppercase tracking-widest text-[10px] md:text-xs mb-6 flex items-center gap-2">
                <span class="bg-orange-100 p-1 rounded-lg">🔥</span> 30-Day System Pulse
            </h3>
            <div class="grid grid-cols-6 sm:grid-cols-5 md:grid-cols-6 gap-2">
                <?php 
                $map = []; 
                foreach($stats['heatmap'] as $h) $map[$h->date] = $h->count;
                
                for($i=29; $i>=0; $i--):
                    $d = date('Y-m-d', strtotime("-$i days"));
                    $c = $map[$d] ?? 0;
                    
                    $bg = 'bg-slate-100';
                    $txt = 'text-slate-300';
                    
                    if($c > 0) { $bg = 'bg-blue-100'; $txt = 'text-blue-600'; }
                    if($c > 5) { $bg = 'bg-blue-400'; $txt = 'text-white'; }
                    if($c > 10) { $bg = 'bg-blue-600'; $txt = 'text-white shadow-lg shadow-blue-200'; }
                ?>
                <div class="aspect-square rounded-xl <?php echo $bg; ?> flex items-center justify-center text-[10px] font-black transition-all hover:scale-110 cursor-help <?php echo $txt; ?>" 
                     title="<?php echo date('D, d M', strtotime($d)) . ": " . (int)$c . " actions"; ?>">
                    <?php echo $c > 0 ? (int)$c : ''; ?>
                </div>
                <?php endfor; ?>
            </div>
            <div class="mt-6 flex justify-between items-center px-1">
                <p class="text-[9px] text-slate-400 uppercase font-black italic">Past Activity</p>
                <p class="text-[9px] text-slate-400 uppercase font-black italic">Today</p>
            </div>
        </div>
    </div>

    <!-- LISTS SECTION -->
    <div class="grid grid-cols-1 md:grid-cols-[65%_35%] gap-6 md:gap-8">
        
        <!-- LIST 1: CRITICAL EXPIRY -->
        <!-- COMPLIANCE ALERT CENTER (Grade A+) -->
    <?php $intelligence = $db->get_compliance_alerts(); ?>
    <div class="grid grid-cols-1 gap-8 md:gap-8">
        <div class="bg-white rounded-[2.5rem] shadow-2xl border border-slate-200 overflow-hidden">
            <div class="p-6 bg-slate-900 flex justify-between items-center">
                <div>
                    <h3 class="font-black text-white text-xs uppercase tracking-widest flex items-center gap-2 leading-none">
                        <span class="animate-pulse text-red-500">⬤</span> Compliance Alert
                    </h3>
                    <p class="text-[9px] text-slate-500 uppercase font-bold mt-1">Permit expiry only · Red ≤30 · Orange ≤40 · Blue ≤50</p>
                </div>
                <div class="flex gap-2">
                    <span class="bg-red-500 text-white text-[9px] font-black px-2 py-1 rounded-lg">RED: <?php echo count($intelligence['critical']); ?></span>
                    <span class="bg-orange-500 text-white text-[9px] font-black px-2 py-1 rounded-lg">ORANGE: <?php echo count($intelligence['warning']); ?></span>
                    <span class="bg-blue-500 text-white text-[9px] font-black px-2 py-1 rounded-lg">BLUE: <?php echo count($intelligence['upcoming']); ?></span>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 divide-y lg:divide-y-0 lg:divide-x divide-slate-100">
                
                <!-- COLUMN 1: RED (≤ 30 Days / expired) -->
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4 px-2">
                        <span class="text-[10px] font-black text-red-600 uppercase italic">Red Zone (≤30 days)</span>
                        <span class="w-2 h-2 rounded-full bg-red-500 animate-ping"></span>
                    </div>
                    <div class="space-y-3 max-h-[400px] overflow-y-auto custom-scrollbar pr-2">
                        <?php if(empty($intelligence['critical'])): ?>
                            <p class="text-center py-10 text-[10px] font-bold text-slate-300 uppercase">Clear</p>
                        <?php else: foreach($intelligence['critical'] as $item): ?>
                            <div onclick="window.location='?page=wizard&id=<?php echo $item['id']; ?>'" class="bg-red-50 hover:bg-red-100 p-4 rounded-2xl border border-red-100 transition cursor-pointer group">
                                <div class="flex justify-between items-start">
                                    <div class="text-[11px] font-black text-slate-800 uppercase"><?php echo e($item['name']); ?></div>
                                    <div class="text-[10px] font-black text-red-600 italic"><?php echo $item['days'] < 0 ? 'EXPIRED' : $item['days'].' Days'; ?></div>
                                </div>
                                <div class="flex justify-between items-center mt-1">
                                    <div class="text-[9px] font-bold text-red-400 uppercase"><?php echo $item['label']; ?></div>
                                    <div class="text-[9px] font-mono font-bold text-slate-400 group-hover:text-red-600"><?php echo format_date_my($item['date']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- COLUMN 2: WARNING (30 - 60 Days) -->
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4 px-2">
                        <span class="text-[10px] font-black text-orange-600 uppercase italic">Orange Zone (31–40 days)</span>
                    </div>
                    <div class="space-y-3 max-h-[400px] overflow-y-auto custom-scrollbar pr-2">
                        <?php if(empty($intelligence['warning'])): ?>
                            <p class="text-center py-10 text-[10px] font-bold text-slate-300 uppercase">Clear</p>
                        <?php else: foreach($intelligence['warning'] as $item): ?>
                            <div onclick="window.location='?page=wizard&id=<?php echo $item['id']; ?>'" class="bg-orange-50 hover:bg-orange-100 p-4 rounded-2xl border border-orange-100 transition cursor-pointer group">
                                <div class="flex justify-between items-start">
                                    <div class="text-[11px] font-black text-slate-800 uppercase"><?php echo e($item['name']); ?></div>
                                    <div class="text-[10px] font-black text-orange-600 italic"><?php echo $item['days']; ?> Days</div>
                                </div>
                                <div class="flex justify-between items-center mt-1">
                                    <div class="text-[9px] font-bold text-orange-400 uppercase"><?php echo $item['label']; ?></div>
                                    <div class="text-[9px] font-mono font-bold text-slate-400"><?php echo format_date_my($item['date']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- COLUMN 3: UPCOMING (60 - 90 Days) -->
                <div class="p-4">
                    <div class="flex items-center justify-between mb-4 px-2">
                        <span class="text-[10px] font-black text-blue-600 uppercase italic">Blue Zone (41–50 days)</span>
                    </div>
                    <div class="space-y-3 max-h-[400px] overflow-y-auto custom-scrollbar pr-2">
                        <?php if(empty($intelligence['upcoming'])): ?>
                            <p class="text-center py-10 text-[10px] font-bold text-slate-300 uppercase">Clear</p>
                        <?php else: foreach($intelligence['upcoming'] as $item): ?>
                            <div onclick="window.location='?page=wizard&id=<?php echo $item['id']; ?>'" class="bg-blue-50 hover:bg-blue-100 p-4 rounded-2xl border border-blue-100 transition cursor-pointer group">
                                <div class="flex justify-between items-start">
                                    <div class="text-[11px] font-black text-slate-800 uppercase"><?php echo e($item['name']); ?></div>
                                    <div class="text-[10px] font-black text-blue-600 italic"><?php echo $item['days']; ?> Days</div>
                                </div>
                                <div class="flex justify-between items-center mt-1">
                                    <div class="text-[9px] font-bold text-blue-400 uppercase"><?php echo $item['label']; ?></div>
                                    <div class="text-[9px] font-mono font-bold text-slate-400"><?php echo format_date_my($item['date']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>

        <!-- LIST 2: RECENT APPLICATIONS -->
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-6 bg-slate-900 flex justify-between items-center">
                <h3 class="font-black text-white text-[10px] uppercase tracking-widest flex items-center gap-2 leading-none">
                    <span>🚀</span> Recent Applications
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <tbody class="divide-y divide-slate-100">
                        <?php if(empty($recent)): ?>
                            <tr><td class="p-10 text-center text-slate-400 font-bold uppercase text-xs">No recent activity on record.</td></tr>
                        <?php endif; ?>
                        <?php foreach($recent as $w): ?>
                        <tr class="hover:bg-slate-50 transition-colors cursor-pointer group" onclick="window.location='?page=wizard&id=<?php echo (int)$w->id; ?>'">
                            <td class="p-5">
                                <div class="font-black text-slate-800 font-mono tracking-tighter group-hover:text-blue-600"><?php echo e($w->passport_number); ?></div>
                                <div class="text-[14px] text-slate-400 font-bold uppercase truncate max-w-[120px] md:max-w-none"><?php echo e($w->full_name); ?></div>
                            </td>
                            <td class="p-5 text-right">
                                <span class="bg-blue-50 text-blue-700 px-3 py-1 rounded-full text-[9px] font-black uppercase border border-blue-100">
                                    Stage <?php echo (int)$w->current_stage; ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<script>
/**
 * Initialize Dashboard Analytics
 */
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('stageChart').getContext('2d');
    
    if (window.myDashboardChart) {
        window.myDashboardChart.destroy();
    }

    window.myDashboardChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Workers',
                data: <?php echo json_encode($stats['stage_dist']); ?>,
                backgroundColor: '#3b82f6',
                hoverBackgroundColor: '#2563eb',
                borderRadius: 12,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    titleFont: { size: 12, weight: 'bold' },
                    bodyFont: { size: 12 },
                    padding: 12,
                    cornerRadius: 12,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        font: { size: 10, weight: '600' },
                        color: '#94a3b8'
                    },
                    grid: {
                        color: '#f1f5f9',
                        drawBorder: false
                    }
                },
                x: {
                    ticks: {
                        font: { size: 9, weight: 'bold' },
                        color: '#64748b',
                        autoSkip: false,
                        maxRotation: 45,
                        minRotation: 45
                    },
                    grid: { display: false }
                }
            }
        }
    });
});
</script>