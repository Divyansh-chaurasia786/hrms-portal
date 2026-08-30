<!-- views/calling/queue.php -->
<?php
$user = authUser();
$db = getDBConnection();
$today = date('Y-m-d');

// 1. Fetch all assigned leads for this BDA executive
$leads = $db->query("
    SELECT * FROM calling_leads 
    WHERE assigned_to = {$user['id']} 
    ORDER BY FIELD(status, 'interested', 'call_later', 'new', 'converted', 'not_interested'), id DESC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// 2. Fetch available colleagues and Team Lead for Conference Calling
$teamMembers = $db->query("
    SELECT id, name, designation, role, phone 
    FROM users 
    WHERE status = 'active' AND id != {$user['id']} 
      AND (department_name = 'Business Development' OR department_name LIKE '%Calling%' OR role IN ('team_lead', 'admin'))
    ORDER BY role DESC, name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

// 3. Today's calling metrics
$todayCallCount = (int)$db->query("SELECT COUNT(*) FROM call_logs WHERE caller_id = {$user['id']} AND call_date = '{$today}'")->fetchColumn();
$todayConverted = (int)$db->query("SELECT COUNT(*) FROM call_logs WHERE caller_id = {$user['id']} AND call_date = '{$today}' AND disposition = 'converted'")->fetchColumn();
$todayInterested = (int)$db->query("SELECT COUNT(*) FROM call_logs WHERE caller_id = {$user['id']} AND call_date = '{$today}' AND disposition = 'interested'")->fetchColumn();
$todayFollowUp = (int)$db->query("SELECT COUNT(*) FROM call_logs WHERE caller_id = {$user['id']} AND call_date = '{$today}' AND disposition = 'call_later'")->fetchColumn();

// 4. Group leads by Kanban pipeline stage
$pipeline = [
    'new' => [],
    'interested' => [],
    'call_later' => [],
    'converted' => [],
    'not_interested' => []
];

foreach ($leads as $l) {
    $st = $l['status'] ?? 'new';
    if (!isset($pipeline[$st])) $pipeline[$st] = [];
    $pipeline[$st][] = $l;
}
?>

<div class="space-y-6" x-data="bdaCrmEngine" x-init="initEngine()">

    <!-- 🌟 TOP EXECUTIVE CALLING HERO & STATS STRIP -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white p-5 sm:p-6 border border-indigo-500/20 shadow-xl">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Telecalling Softphone Active
                    </span>
                    <span class="text-xs text-slate-400">•</span>
                    <span class="text-xs font-semibold text-indigo-300">Lead Pipeline & Conference Suite</span>
                </div>
                <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight mt-1">
                    BDA Executive Lead Desk
                </h1>
            </div>

            <!-- View Switcher & Quick Direct Dial -->
                <button type="button" onclick="triggerPwaInstall()" class="px-3.5 py-2 bg-white/10 hover:bg-white/15 text-white text-xs font-bold rounded-xl backdrop-blur-md border border-white/15 transition flex items-center gap-1.5 cursor-pointer"><i data-lucide="download-cloud" class="w-3.5 h-3.5 text-indigo-300"></i><span>Download App</span></button>
            <div class="flex items-center gap-2 flex-wrap">
                <div class="bg-white/10 p-1 rounded-xl flex items-center border border-white/10 backdrop-blur-md">
                    <button type="button" @click="viewMode = 'kanban'" :class="viewMode === 'kanban' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-300 hover:text-white'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="kanban" class="w-3.5 h-3.5"></i> Kanban Funnel
                    </button>
                    <button type="button" @click="viewMode = 'roster'" :class="viewMode === 'roster' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-300 hover:text-white'" class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="list" class="w-3.5 h-3.5"></i> Roster List
                    </button>
                </div>

                <button type="button" @click="openQuickDialModal()" class="px-4 py-2 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-emerald-700/20 transition flex items-center gap-1.5 cursor-pointer border border-emerald-400/30">
                    <i data-lucide="phone-outgoing" class="w-4 h-4"></i> Quick Dialpad
                </button>
            </div>
        </div>

        <!-- Metric Sparkline Cards -->
        <div class="mt-5 pt-5 border-t border-white/10 grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-white/5 backdrop-blur-md p-3.5 rounded-2xl border border-white/10">
                <span class="text-[10px] font-bold text-slate-300 uppercase tracking-wider block">Assigned Pipeline</span>
                <div class="text-2xl font-black text-white mt-0.5"><?= count($leads) ?> <span class="text-[10px] text-slate-400 font-normal">prospects</span></div>
            </div>
            <div class="bg-white/5 backdrop-blur-md p-3.5 rounded-2xl border border-white/10">
                <span class="text-[10px] font-bold text-slate-300 uppercase tracking-wider block">Calls Completed Today</span>
                <div class="text-2xl font-black text-indigo-300 mt-0.5"><?= $todayCallCount ?> <span class="text-[10px] text-indigo-200 font-normal">calls</span></div>
            </div>
            <div class="bg-white/5 backdrop-blur-md p-3.5 rounded-2xl border border-white/10">
                <span class="text-[10px] font-bold text-slate-300 uppercase tracking-wider block">Interested / Hot</span>
                <div class="text-2xl font-black text-amber-300 mt-0.5"><?= $todayInterested ?> <span class="text-[10px] text-amber-200 font-normal">leads</span></div>
            </div>
            <div class="bg-white/5 backdrop-blur-md p-3.5 rounded-2xl border border-white/10">
                <span class="text-[10px] font-bold text-slate-300 uppercase tracking-wider block">Deals Converted 🏆</span>
                <div class="text-2xl font-black text-emerald-300 mt-0.5"><?= $todayConverted ?> <span class="text-[10px] text-emerald-200 font-normal">won</span></div>
            </div>
        </div>
    </div>

    <!-- 📊 VIEW 1: INTERACTIVE KANBAN SALES PIPELINE -->
    <div x-show="viewMode === 'kanban'" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 overflow-x-auto pb-4">
            
            <!-- 1. NEW LEADS COLUMN -->
            <div class="bg-slate-100/80 rounded-2xl p-3.5 border border-slate-200 space-y-3 min-w-[240px]">
                <div class="flex items-center justify-between pb-2 border-b border-slate-200">
                    <span class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span> New Leads
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-700"><?= count($pipeline['new']) ?></span>
                </div>
                <div class="space-y-2.5 max-h-[600px] overflow-y-auto pr-1">
                    <?php foreach ($pipeline['new'] as $lead): ?>
                        <?php include __DIR__ . '/_lead_card.php'; ?>
                    <?php endforeach; ?>
                    <?php if (empty($pipeline['new'])): ?>
                        <div class="text-center py-8 text-[11px] text-slate-400 font-medium">No fresh leads</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 2. INTERESTED COLUMN -->
            <div class="bg-blue-50/70 rounded-2xl p-3.5 border border-blue-200 space-y-3 min-w-[240px]">
                <div class="flex items-center justify-between pb-2 border-b border-blue-200">
                    <span class="text-xs font-black text-blue-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-blue-500 animate-ping"></span> Interested 🔥
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-200 text-blue-800"><?= count($pipeline['interested']) ?></span>
                </div>
                <div class="space-y-2.5 max-h-[600px] overflow-y-auto pr-1">
                    <?php foreach ($pipeline['interested'] as $lead): ?>
                        <?php include __DIR__ . '/_lead_card.php'; ?>
                    <?php endforeach; ?>
                    <?php if (empty($pipeline['interested'])): ?>
                        <div class="text-center py-8 text-[11px] text-blue-400 font-medium">No interested leads</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 3. FOLLOW-UP REQUIRED COLUMN -->
            <div class="bg-amber-50/70 rounded-2xl p-3.5 border border-amber-200 space-y-3 min-w-[240px]">
                <div class="flex items-center justify-between pb-2 border-b border-amber-200">
                    <span class="text-xs font-black text-amber-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span> Follow-Up ⏰
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-200 text-amber-800"><?= count($pipeline['call_later']) ?></span>
                </div>
                <div class="space-y-2.5 max-h-[600px] overflow-y-auto pr-1">
                    <?php foreach ($pipeline['call_later'] as $lead): ?>
                        <?php include __DIR__ . '/_lead_card.php'; ?>
                    <?php endforeach; ?>
                    <?php if (empty($pipeline['call_later'])): ?>
                        <div class="text-center py-8 text-[11px] text-amber-400 font-medium">No scheduled follow-ups</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 4. CLOSED WON 🏆 -->
            <div class="bg-emerald-50/70 rounded-2xl p-3.5 border border-emerald-200 space-y-3 min-w-[240px]">
                <div class="flex items-center justify-between pb-2 border-b border-emerald-200">
                    <span class="text-xs font-black text-emerald-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Closed Won 🏆
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-200 text-emerald-800"><?= count($pipeline['converted']) ?></span>
                </div>
                <div class="space-y-2.5 max-h-[600px] overflow-y-auto pr-1">
                    <?php foreach ($pipeline['converted'] as $lead): ?>
                        <?php include __DIR__ . '/_lead_card.php'; ?>
                    <?php endforeach; ?>
                    <?php if (empty($pipeline['converted'])): ?>
                        <div class="text-center py-8 text-[11px] text-emerald-400 font-medium">No closed deals yet</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 5. NOT INTERESTED / LOST -->
            <div class="bg-rose-50/50 rounded-2xl p-3.5 border border-rose-200 space-y-3 min-w-[240px]">
                <div class="flex items-center justify-between pb-2 border-b border-rose-200">
                    <span class="text-xs font-black text-rose-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-400"></span> Lost / Unreachable
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-200 text-rose-800"><?= count($pipeline['not_interested']) ?></span>
                </div>
                <div class="space-y-2.5 max-h-[600px] overflow-y-auto pr-1">
                    <?php foreach ($pipeline['not_interested'] as $lead): ?>
                        <?php include __DIR__ . '/_lead_card.php'; ?>
                    <?php endforeach; ?>
                    <?php if (empty($pipeline['not_interested'])): ?>
                        <div class="text-center py-8 text-[11px] text-rose-400 font-medium">No lost leads</div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <!-- 📋 VIEW 2: ROSTER TABLE VIEW -->
    <div x-show="viewMode === 'roster'" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" style="display: none;">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between gap-3 flex-wrap">
            <h2 class="text-xs font-bold text-slate-800 uppercase tracking-wider">All Assigned Calling Leads (<?= count($leads) ?>)</h2>
            <input type="text" x-model="searchQuery" placeholder="Search by name, phone, city..." class="bg-slate-50 border border-slate-300 rounded-xl px-3 py-1.5 text-xs font-medium focus:ring-2 focus:ring-indigo-500 w-64">
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-[10px]">
                    <tr>
                        <th class="p-3.5">Customer Name</th>
                        <th class="p-3.5">Phone Number</th>
                        <th class="p-3.5">City & Course</th>
                        <th class="p-3.5">Budget</th>
                        <th class="p-3.5">Status</th>
                        <th class="p-3.5 text-right">Instant Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($leads as $l): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="p-3.5 font-bold text-slate-900">
                                <div><?= htmlspecialchars($l['lead_name']) ?></div>
                                <div class="text-[10px] text-slate-400 font-normal"><?= htmlspecialchars($l['email'] ?: 'No email') ?></div>
                            </td>
                            <td class="p-3.5 font-mono font-semibold text-slate-700">
                                <?= htmlspecialchars($l['phone']) ?>
                            </td>
                            <td class="p-3.5">
                                <div class="font-medium text-slate-800"><?= htmlspecialchars($l['course_service'] ?: 'General') ?></div>
                                <div class="text-[10px] text-slate-400"><?= htmlspecialchars($l['city'] ?: 'India') ?></div>
                            </td>
                            <td class="p-3.5 font-bold text-slate-900">
                                ₹<?= number_format((float)($l['budget'] ?? 0)) ?>
                            </td>
                            <td class="p-3.5">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $l['status'] === 'converted' ? 'bg-emerald-100 text-emerald-800' : ($l['status'] === 'interested' ? 'bg-blue-100 text-blue-800' : ($l['status'] === 'call_later' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')) ?>">
                                    <?= str_replace('_', ' ', strtoupper($l['status'])) ?>
                                </span>
                            </td>
                            <td class="p-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <button type="button" @click="startSoftphoneCall(<?= htmlspecialchars(json_encode($l)) ?>)" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-xs shadow-xs transition flex items-center gap-1 cursor-pointer">
                                        <i data-lucide="phone" class="w-3.5 h-3.5"></i> Call
                                    </button>
                                    <button type="button" @click="openWhatsAppDrawer(<?= htmlspecialchars(json_encode($l)) ?>)" class="p-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg border border-emerald-200 transition cursor-pointer" title="WhatsApp Template">
                                        <i data-lucide="message-circle" class="w-4 h-4"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 📞 FLOATING IN-BROWSER SOFTPHONE DIALER & CONFERENCE SUITE -->
    <div x-show="activeCall" 
         x-cloak 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-10 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         class="fixed bottom-6 right-6 z-50 w-88 max-w-[calc(100vw-2rem)] bg-slate-900 text-white rounded-3xl shadow-2xl border border-indigo-500/30 overflow-hidden backdrop-blur-xl">
        
        <!-- Dialer Header -->
        <div class="bg-gradient-to-r from-slate-950 via-indigo-950 to-slate-950 p-4 flex items-center justify-between border-b border-indigo-500/20">
            <div class="flex items-center gap-2.5">
                <div class="w-3 h-3 rounded-full bg-emerald-400 animate-ping"></div>
                <span class="text-xs font-bold text-indigo-300 uppercase tracking-wide">Live Softphone Session</span>
            </div>
            <div class="font-mono text-sm font-black text-emerald-400 bg-emerald-950/80 px-2.5 py-0.5 rounded-full border border-emerald-500/30" x-text="formattedCallTimer">
                00:00
            </div>
        </div>

        <!-- Active Call Body -->
        <div class="p-5 text-center space-y-4">
            <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-indigo-600 to-violet-500 text-white font-extrabold text-2xl flex items-center justify-center mx-auto shadow-lg shadow-indigo-600/40 border-2 border-white/20">
                <span x-text="activeCallLead?.lead_name ? activeCallLead.lead_name.substring(0,2).toUpperCase() : 'CU'"></span>
            </div>

            <div>
                <h3 class="font-extrabold text-base text-white" x-text="activeCallLead?.lead_name || 'Direct Number'"></h3>
                <p class="font-mono text-xs text-indigo-300 mt-0.5" x-text="activeCallLead?.phone"></p>
                <p class="text-[11px] text-slate-400 mt-1" x-text="activeCallLead?.course_service || 'BDA Inquiry'"></p>
            </div>

            <!-- Conference Bridging Badge -->
            <template x-if="conferenceParticipant">
                <div class="bg-indigo-950/90 border border-indigo-400/40 rounded-2xl p-2.5 text-xs text-indigo-200 flex items-center justify-between gap-2 shadow-inner">
                    <div class="flex items-center gap-2">
                        <i data-lucide="users-2" class="w-4 h-4 text-indigo-300"></i>
                        <span class="font-bold text-white" x-text="'Conference with: ' + conferenceParticipant.name"></span>
                    </div>
                    <span class="px-2 py-0.5 bg-emerald-500/20 text-emerald-300 rounded text-[10px] font-bold">Bridged</span>
                </div>
            </template>

            <!-- Audio Wave Visualizer Animation -->
            <div class="flex items-center justify-center gap-1 h-6">
                <div class="w-1 bg-emerald-400 rounded-full animate-bounce h-3"></div>
                <div class="w-1 bg-emerald-400 rounded-full animate-bounce h-5" style="animation-delay: 0.1s"></div>
                <div class="w-1 bg-emerald-400 rounded-full animate-bounce h-6" style="animation-delay: 0.2s"></div>
                <div class="w-1 bg-emerald-400 rounded-full animate-bounce h-4" style="animation-delay: 0.3s"></div>
                <div class="w-1 bg-emerald-400 rounded-full animate-bounce h-2" style="animation-delay: 0.4s"></div>
            </div>

            <!-- Call Controls (Mute, Hold, Conference, End) -->
            <div class="grid grid-cols-4 gap-2 pt-2">
                <!-- Mute Button -->
                <button type="button" @click="isMuted = !isMuted" :class="isMuted ? 'bg-amber-600 text-white' : 'bg-white/10 text-slate-200 hover:bg-white/20'" class="p-3 rounded-2xl flex flex-col items-center gap-1 transition cursor-pointer">
                    <i data-lucide="mic-off" class="w-4 h-4" x-show="isMuted"></i>
                    <i data-lucide="mic" class="w-4 h-4" x-show="!isMuted"></i>
                    <span class="text-[9px] font-bold" x-text="isMuted ? 'Muted' : 'Mute'"></span>
                </button>

                <!-- Hold Button -->
                <button type="button" @click="isOnHold = !isOnHold" :class="isOnHold ? 'bg-amber-600 text-white' : 'bg-white/10 text-slate-200 hover:bg-white/20'" class="p-3 rounded-2xl flex flex-col items-center gap-1 transition cursor-pointer">
                    <i data-lucide="pause" class="w-4 h-4"></i>
                    <span class="text-[9px] font-bold" x-text="isOnHold ? 'On Hold' : 'Hold'"></span>
                </button>

                <!-- 👥 Conference Button (3-Way Merge) -->
                <button type="button" @click="openConferenceModal()" class="p-3 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white flex flex-col items-center gap-1 transition cursor-pointer shadow-sm">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    <span class="text-[9px] font-bold">Conf Call</span>
                </button>

                <!-- End Call Button -->
                <button type="button" @click="endSoftphoneCall()" class="p-3 rounded-2xl bg-rose-600 hover:bg-rose-700 text-white flex flex-col items-center gap-1 transition cursor-pointer shadow-lg shadow-rose-600/40">
                    <i data-lucide="phone-off" class="w-4 h-4"></i>
                    <span class="text-[9px] font-bold">End Call</span>
                </button>
            </div>
        </div>
    </div>

    <!-- 📝 CALL DISPOSITION MODAL (Triggered immediately after call ends) -->
    <div x-show="dispositionModalOpen" x-cloak class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                        <i data-lucide="clipboard-check" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">Record Call Outcome & Notes</h3>
                        <p class="text-[10px] text-slate-400 font-mono" x-text="'Talk time: ' + formattedCallTimer + ' • ' + (activeCallLead?.lead_name || 'Prospect')"></p>
                    </div>
                </div>
            </div>

            <form action="?action=save-call-disposition" method="POST" class="space-y-4">
                <input type="hidden" name="lead_id" :value="activeCallLead?.id || 0">
                <input type="hidden" name="phone" :value="activeCallLead?.phone || ''">
                <input type="hidden" name="customer_name" :value="activeCallLead?.lead_name || ''">
                <input type="hidden" name="call_duration_seconds" :value="callTimerSeconds">
                <input type="hidden" name="is_conference" :value="conferenceParticipant ? 1 : 0">

                <!-- Disposition Selector Grid -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1.5">Call Disposition *</label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <label class="p-2.5 rounded-xl border border-slate-200 hover:border-emerald-500 cursor-pointer flex items-center gap-2 transition" :class="selectedDisp === 'interested' ? 'bg-blue-50 border-blue-500 text-blue-900 font-bold' : 'text-slate-700'">
                            <input type="radio" name="disposition" value="interested" x-model="selectedDisp" class="sr-only">
                            <span>🔥 Interested</span>
                        </label>
                        <label class="p-2.5 rounded-xl border border-slate-200 hover:border-emerald-500 cursor-pointer flex items-center gap-2 transition" :class="selectedDisp === 'converted' ? 'bg-emerald-50 border-emerald-500 text-emerald-900 font-bold' : 'text-slate-700'">
                            <input type="radio" name="disposition" value="converted" x-model="selectedDisp" class="sr-only">
                            <span>🏆 Closed Won</span>
                        </label>
                        <label class="p-2.5 rounded-xl border border-slate-200 hover:border-emerald-500 cursor-pointer flex items-center gap-2 transition" :class="selectedDisp === 'call_later' ? 'bg-amber-50 border-amber-500 text-amber-900 font-bold' : 'text-slate-700'">
                            <input type="radio" name="disposition" value="call_later" x-model="selectedDisp" class="sr-only">
                            <span>⏰ Call Later</span>
                        </label>
                        <label class="p-2.5 rounded-xl border border-slate-200 hover:border-emerald-500 cursor-pointer flex items-center gap-2 transition" :class="selectedDisp === 'busy' ? 'bg-slate-100 border-slate-400 text-slate-900 font-bold' : 'text-slate-700'">
                            <input type="radio" name="disposition" value="busy" x-model="selectedDisp" class="sr-only">
                            <span>📵 Busy / Line Drop</span>
                        </label>
                        <label class="p-2.5 rounded-xl border border-slate-200 hover:border-emerald-500 cursor-pointer flex items-center gap-2 transition" :class="selectedDisp === 'ringing_no_answer' ? 'bg-slate-100 border-slate-400 text-slate-900 font-bold' : 'text-slate-700'">
                            <input type="radio" name="disposition" value="ringing_no_answer" x-model="selectedDisp" class="sr-only">
                            <span>🔕 Ringing No Answer</span>
                        </label>
                        <label class="p-2.5 rounded-xl border border-slate-200 hover:border-emerald-500 cursor-pointer flex items-center gap-2 transition" :class="selectedDisp === 'not_interested' ? 'bg-rose-50 border-rose-500 text-rose-900 font-bold' : 'text-slate-700'">
                            <input type="radio" name="disposition" value="not_interested" x-model="selectedDisp" class="sr-only">
                            <span>❌ Not Interested</span>
                        </label>
                    </div>
                </div>

                <!-- Follow-Up Date (If Call Later or Interested) -->
                <div x-show="selectedDisp === 'call_later' || selectedDisp === 'interested'">
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Schedule Next Callback Date</label>
                    <input type="date" name="follow_up_date" min="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                </div>

                <!-- Deal Value Won (If Converted) -->
                <div x-show="selectedDisp === 'converted'">
                    <label class="block text-[11px] font-bold text-emerald-800 uppercase mb-1">Deal / Fee Value (₹ INR) *</label>
                    <input type="number" name="deal_value" step="500" placeholder="e.g. 35000" class="w-full bg-emerald-50/60 border border-emerald-300 rounded-xl px-3 py-2 text-xs font-bold text-emerald-950 focus:ring-2 focus:ring-emerald-500">
                </div>

                <!-- Conversation Notes -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Call Conversation Summary & Client Feedback</label>
                    <textarea name="notes" rows="3" required placeholder="Customer discussed budget, requested syllabus on WhatsApp, parent consultation scheduled..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-medium focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 transition flex items-center gap-1.5 cursor-pointer">
                        <i data-lucide="check" class="w-4 h-4"></i> Save Call Disposition
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 👥 CONFERENCE CALL INVITE MODAL (Internal Team & External Any Number) -->
    <div x-show="conferenceModalOpen" x-cloak class="fixed inset-0 z-50 bg-slate-950/75 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="conferenceModalOpen = false" class="bg-white rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-800 flex items-center justify-center font-bold">
                        <i data-lucide="users-2" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">Bridge 3-Way Conference Call</h3>
                        <p class="text-[11px] text-slate-400">Add Team Lead, Closer, or External Phone Number</p>
                    </div>
                </div>
                <button type="button" @click="conferenceModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <!-- Option 1: Dial ANY External Phone Number (Parent / Co-applicant / Sponsor) -->
            <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200 space-y-2">
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">Option 1: Dial External Number</label>
                <div class="flex items-center gap-2">
                    <input type="tel" x-model="externalConfPhone" placeholder="Enter mobile number to bridge..." class="flex-1 bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-indigo-500 font-mono">
                    <button type="button" @click="bridgeExternalPhone()" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1 cursor-pointer shrink-0 shadow-xs">
                        <i data-lucide="phone-outgoing" class="w-3.5 h-3.5"></i> Dial & Bridge
                    </button>
                </div>
            </div>

            <!-- Option 2: 1-Click Internal Team & TL Bridge -->
            <div class="space-y-2">
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">Option 2: Internal Team & Leadership</label>
                <div class="divide-y divide-slate-100 max-h-56 overflow-y-auto border border-slate-100 rounded-2xl p-1">
                    <?php foreach ($teamMembers as $m): ?>
                        <div class="py-2 px-2.5 flex items-center justify-between gap-3 hover:bg-slate-50 rounded-xl transition">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-700 font-bold flex items-center justify-center text-xs shrink-0">
                                    <?= strtoupper(substr($m['name'], 0, 2)) ?>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-xs font-bold text-slate-900 truncate"><?= htmlspecialchars($m['name']) ?></h4>
                                    <p class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($m['designation']) ?></p>
                                </div>
                            </div>
                            <button type="button" @click="bridgeConference(<?= htmlspecialchars(json_encode($m)) ?>)" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold transition flex items-center gap-1 cursor-pointer shrink-0">
                                <i data-lucide="phone-forwarded" class="w-3 h-3"></i> Merge
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 💬 WHATSAPP TEMPLATE DRAWER -->
    <div x-show="whatsappModalOpen" x-cloak class="fixed inset-0 z-50 bg-slate-950/75 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.away="whatsappModalOpen = false" class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold">
                        <i data-lucide="message-circle" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-sm text-slate-900">WhatsApp Message Dispatcher</h3>
                        <p class="text-[11px] text-slate-400" x-text="'To: ' + (activeWaLead?.lead_name || 'Customer') + ' (' + (activeWaLead?.phone || '') + ')'"></p>
                    </div>
                </div>
                <button type="button" @click="whatsappModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>

            <!-- Template Selectors -->
            <div class="flex items-center gap-2 flex-wrap pb-2">
                <button type="button" @click="applyTemplate('brochure')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-xs font-bold text-slate-700">📄 Course Brochure</button>
                <button type="button" @click="applyTemplate('followup')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-xs font-bold text-slate-700">⏰ Callback Reminder</button>
                <button type="button" @click="applyTemplate('payment')" class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 rounded-lg text-xs font-bold text-slate-700">💳 Payment Link</button>
            </div>

            <textarea x-model="waCustomText" rows="6" class="w-full bg-slate-50 border border-slate-300 rounded-xl p-3 text-xs font-medium focus:ring-2 focus:ring-emerald-500 font-sans"></textarea>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" @click="whatsappModalOpen = false" class="px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold text-slate-600">Close</button>
                <a :href="'https://api.whatsapp.com/send?phone=' + (activeWaLead?.phone || '').replace(/[^0-9]/g, '') + '&text=' + encodeURIComponent(waCustomText)" target="_blank" @click="whatsappModalOpen = false" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-600/20 transition flex items-center gap-1.5 cursor-pointer">
                    <i data-lucide="send" class="w-3.5 h-3.5"></i> Open WhatsApp Web
                </a>
            </div>
        </div>
    </div>

</div>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('bdaCrmEngine', () => ({
        viewMode: 'kanban', // 'kanban' | 'roster'
        searchQuery: '',
        activeCall: false,
        activeCallLead: null,
        callTimerSeconds: 0,
        timerInterval: null,
        isMuted: false,
        isOnHold: false,
        conferenceParticipant: null,
        externalConfPhone: '',
        conferenceModalOpen: false,
        dispositionModalOpen: false,
        whatsappModalOpen: false,
        activeWaLead: null,
        waCustomText: '',
        selectedDisp: 'interested',

        get formattedCallTimer() {
            const m = Math.floor(this.callTimerSeconds / 60);
            const s = this.callTimerSeconds % 60;
            return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        },

        initEngine() {
            if (typeof lucide !== 'undefined') lucide.createIcons();
        },

        startSoftphoneCall(lead) {
            this.activeCallLead = lead;
            this.activeCall = true;
            this.callTimerSeconds = 0;
            this.conferenceParticipant = null;
            this.isMuted = false;
            this.isOnHold = false;

            clearInterval(this.timerInterval);
            this.timerInterval = setInterval(() => {
                this.callTimerSeconds++;
            }, 1000);

            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });

            // 📞 Trigger Native SIM Phone Dialer on Mobile/PC
            if (lead && lead.phone) {
                const cleanPhone = String(lead.phone).replace(/[^0-9+]/g, '');
                const telLink = document.createElement('a');
                telLink.href = 'tel:' + cleanPhone;
                telLink.target = '_self';
                telLink.style.display = 'none';
                document.body.appendChild(telLink);
                telLink.click();
                setTimeout(() => {
                    try { document.body.removeChild(telLink); } catch(e) {}
                }, 500);
            }
        },

        endSoftphoneCall() {
            clearInterval(this.timerInterval);
            this.activeCall = false;
            this.dispositionModalOpen = true;
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },

        openConferenceModal() {
            this.conferenceModalOpen = true;
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },

        bridgeConference(member) {
            this.conferenceParticipant = member;
            this.conferenceModalOpen = false;
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },

        bridgeExternalPhone() {
            if (!this.externalConfPhone || !this.externalConfPhone.trim()) {
                alert('Please enter a valid phone number to bridge.');
                return;
            }
            this.conferenceParticipant = {
                name: 'External (' + this.externalConfPhone.trim() + ')',
                designation: 'Co-Applicant / Decision Maker',
                phone: this.externalConfPhone.trim()
            };
            this.conferenceModalOpen = false;
            this.externalConfPhone = '';
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },

        openQuickDialModal() {
            const phone = prompt('Enter 10-digit customer phone number to dial:');
            if (phone && phone.trim()) {
                this.startSoftphoneCall({
                    id: 0,
                    lead_name: 'Direct Call (' + phone.trim() + ')',
                    phone: phone.trim(),
                    course_service: 'Outbound Prospecting'
                });
            }
        },

        openWhatsAppDrawer(lead) {
            this.activeWaLead = lead;
            this.applyTemplate('brochure');
            this.whatsappModalOpen = true;
            this.$nextTick(() => {
                if (typeof lucide !== 'undefined') lucide.createIcons();
            });
        },

        applyTemplate(type) {
            const name = this.activeWaLead?.lead_name || 'Customer';
            const course = this.activeWaLead?.course_service || 'Professional Program';

            if (type === 'brochure') {
                this.waCustomText = `Hello ${name}! 👋\n\nThank you for connecting with Ecofone. Here is the detailed curriculum brochure and fee structure for the *${course}*.\n\nLet me know when would be a good time for a quick 5-minute consultation!\n\nBest regards,\nBDA Admissions Team\nEcofone Global`;
            } else if (type === 'followup') {
                this.waCustomText = `Hi ${name},\n\nJust following up on our earlier conversation regarding the *${course}*. We have reserved your provisional scholarship seat.\n\nPlease confirm if you would like me to schedule your induction session!\n\nRegards,\nEcofone Team`;
            } else if (type === 'payment') {
                this.waCustomText = `Dear ${name},\n\nYour enrollment application for *${course}* has been approved! 🎉\n\nYou can complete the secure registration fee payment here: https://hrms-ecovista.vercel.app/pay\n\nKindly share the transaction screenshot once completed.\n\nRegards,\nEcofone Admissions`;
            }
        }
    }));
});
</script>