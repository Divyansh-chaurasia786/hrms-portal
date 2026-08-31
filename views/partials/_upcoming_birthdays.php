<!-- views/partials/_upcoming_birthdays.php (Sleek Compact Card) -->
<?php
$upcomingBirthdays = getUpcomingBirthdaysWithinDays(30);
if (!empty($upcomingBirthdays)):
?>
<div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs p-3 sm:py-2.5 sm:px-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <!-- Left Badge Strip -->
    <div class="flex items-center gap-2 shrink-0">
        <span class="w-7 h-7 rounded-xl bg-pink-100 text-pink-600 flex items-center justify-center text-sm shadow-2xs">
            🎂
        </span>
        <div class="flex items-center gap-2">
            <span class="text-xs font-black text-slate-800 tracking-tight">Birthdays Ahead</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-pink-50 text-pink-700 border border-pink-200">
                <?= count($upcomingBirthdays) ?> in 30 Days
            </span>
        </div>
    </div>

    <!-- Compact Horizontal List of Birthday Chips -->
    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5 min-w-0 flex-1 w-full sm:w-auto">
        <?php foreach ($upcomingBirthdays as $b): ?>
            <?php
            $isToday = ($b['urgency'] === 'today');
            $isHours = ($b['urgency'] === 'hours');
            $isTomorrow = ($b['urgency'] === 'tomorrow');
            
            $chipBorder = $isToday 
                ? 'bg-gradient-to-r from-pink-50 to-rose-50 border-pink-300 ring-1 ring-pink-400/30' 
                : ($isHours || $isTomorrow
                    ? 'bg-amber-50/80 border-amber-300'
                    : 'bg-slate-50 border-slate-200 hover:border-indigo-300');

            $badgeClass = $isToday 
                ? 'bg-pink-600 text-white animate-pulse' 
                : ($isHours || $isTomorrow
                    ? 'bg-amber-500 text-white font-black'
                    : 'bg-indigo-100 text-indigo-800');

            $waPhone = preg_replace('/[^0-9]/', '', $b['whatsapp_number'] ?: ($b['phone'] ?: ''));
            $empFirstName = explode(' ', $b['name'])[0];
            $waWishText = "Happy Birthday, {$empFirstName}! 🎉🎂 Wishing you a fantastic day! Best regards from Team Ecofone.";
            ?>
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-xl border <?= $chipBorder ?> shrink-0 shadow-2xs transition hover:bg-white">
                <img src="<?= $b['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($b['name']) . '&background=4f46e5&color=fff' ?>" class="w-6 h-6 rounded-lg object-cover ring-1 ring-white" alt="Avatar">
                
                <div class="flex items-center gap-1.5 text-xs">
                    <strong class="font-bold text-slate-800 text-[11px] truncate max-w-[90px]"><?= htmlspecialchars($empFirstName) ?></strong>
                    <span class="text-[10px] text-slate-400 font-mono"><?= $b['bday_formatted'] ?></span>
                    <span class="px-1.5 py-0.2 rounded-md text-[9px] font-extrabold uppercase <?= $badgeClass ?>">
                        <?= $b['countdown_text'] ?>
                    </span>
                </div>

                <?php if (!empty($waPhone)): ?>
                    <a href="https://api.whatsapp.com/send?phone=<?= $waPhone ?>&text=<?= urlencode($waWishText) ?>" target="_blank" class="w-5 h-5 rounded-md bg-emerald-50 hover:bg-emerald-600 text-emerald-600 hover:text-white flex items-center justify-center transition" title="Send WhatsApp Wish to <?= htmlspecialchars($empFirstName) ?>">
                        <i data-lucide="message-circle" class="w-3 h-3"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>