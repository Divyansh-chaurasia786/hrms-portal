<!-- views/calling/_lead_card.php -->
<div class="bg-white rounded-xl p-3 shadow-xs border border-slate-200/90 space-y-2.5 hover:shadow-md hover:border-indigo-300 transition group">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <h4 class="font-bold text-xs text-slate-900 truncate"><?= htmlspecialchars($lead['lead_name']) ?></h4>
            <p class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($lead['phone']) ?></p>
        </div>
        <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full <?= $lead['priority'] === 'hot' ? 'bg-rose-100 text-rose-700' : ($lead['priority'] === 'warm' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600') ?>">
            <?= htmlspecialchars($lead['priority'] ?? 'warm') ?>
        </span>
    </div>

    <div class="text-[11px] text-slate-600 bg-slate-50 p-2 rounded-lg border border-slate-100 space-y-0.5">
        <div class="font-semibold text-slate-800 truncate"><?= htmlspecialchars($lead['course_service'] ?: 'BDA Prospect') ?></div>
        <div class="flex items-center justify-between text-[10px] text-slate-400">
            <span>📍 <?= htmlspecialchars($lead['city'] ?: 'India') ?></span>
            <span class="font-bold text-emerald-700">₹<?= number_format((float)($lead['budget'] ?? 0)) ?></span>
        </div>
    </div>

    <?php if (!empty($lead['notes'])): ?>
        <p class="text-[10px] text-slate-500 line-clamp-2 italic bg-amber-50/50 p-1.5 rounded border border-amber-100/50">
            <?= htmlspecialchars(substr($lead['notes'], 0, 100)) ?>
        </p>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="flex items-center gap-1.5 pt-1 border-t border-slate-100">
        <button type="button" @click="startSoftphoneCall(<?= htmlspecialchars(json_encode($lead)) ?>)" class="flex-1 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[11px] transition flex items-center justify-center gap-1 cursor-pointer shadow-xs">
            <i data-lucide="phone-call" class="w-3 h-3"></i> Call
        </button>
        <button type="button" @click="openWhatsAppDrawer(<?= htmlspecialchars(json_encode($lead)) ?>)" class="p-1.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg border border-emerald-200 transition cursor-pointer" title="Send WhatsApp">
            <i data-lucide="message-circle" class="w-3.5 h-3.5"></i>
        </button>
    </div>
</div>