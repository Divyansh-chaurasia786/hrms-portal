<!-- views/partials/_upcoming_birthdays.php (Elegant Right-Column Widget Card) -->
<?php
$upcomingBirthdays = getUpcomingBirthdaysWithinDays(30);
$displayedBirthdays = array_slice($upcomingBirthdays, 0, 2);
if (!empty($displayedBirthdays)):
?>
<div class="bg-white rounded-2xl border border-slate-200/90 shadow-xs p-4 space-y-3">
    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
        <div class="flex items-center gap-2">
            <div class="w-7 h-7 rounded-lg bg-pink-50 text-pink-600 flex items-center justify-center text-xs font-bold shrink-0 border border-pink-200">
                🎂
            </div>
            <div>
                <h3 class="text-xs sm:text-sm font-bold text-slate-900">Upcoming Birthdays</h3>
            </div>
        </div>
        <a href="?page=admin-birthdays" class="text-xs font-bold text-pink-600 hover:text-pink-700 transition inline-flex items-center gap-1">
            <span>View All (<?= count($upcomingBirthdays) ?>)</span> &rarr;
        </a>
    </div>

    <!-- Short List of Birthday Cards -->
    <div class="space-y-2">
        <?php foreach ($displayedBirthdays as $b): ?>
            <?php
            $isToday = ($b['urgency'] === 'today');
            $isHours = ($b['urgency'] === 'hours');
            $isTomorrow = ($b['urgency'] === 'tomorrow');
            
            $cardBg = $isToday 
                ? 'bg-gradient-to-r from-pink-50/90 to-rose-50/90 border-pink-300 ring-1 ring-pink-400/30' 
                : ($isHours || $isTomorrow
                    ? 'bg-amber-50/70 border-amber-300/80'
                    : 'bg-slate-50 border-slate-200/80 hover:bg-indigo-50/30 hover:border-indigo-200');

            $badgeClass = $isToday 
                ? 'bg-pink-600 text-white animate-pulse' 
                : ($isHours || $isTomorrow
                    ? 'bg-amber-500 text-white font-black'
                    : 'bg-indigo-50 text-indigo-700 border border-indigo-200/60');

            $waPhone = preg_replace('/[^0-9]/', '', $b['whatsapp_number'] ?: ($b['phone'] ?: ''));
            $empFirstName = explode(' ', $b['name'])[0];
            $waWishText = "Happy Birthday, {$empFirstName}! 🎉🎂 Wishing you a fantastic day ahead! Warm regards from Ecofone HR.";
            ?>
            <div class="p-2.5 rounded-2xl border <?= $cardBg ?> flex items-center justify-between gap-3 text-xs transition">
                <div class="flex items-center gap-2.5 min-w-0">
                    <img src="<?= $b['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($b['name']) . '&background=4f46e5&color=fff' ?>" class="w-7 h-7 rounded-xl object-cover ring-1 ring-white shrink-0" alt="Avatar">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-slate-900 truncate text-[11px]"><?= htmlspecialchars($b['name']) ?></span>
                            <span class="px-1.5 py-0.2 rounded-md text-[9px] font-extrabold uppercase <?= $badgeClass ?> shrink-0">
                                <?= $b['countdown_text'] ?>
                            </span>
                        </div>
                        <div class="text-[10px] text-slate-400 font-mono"><?= $b['bday_formatted'] ?> • <?= htmlspecialchars($b['designation'] ?: 'Team Member') ?></div>
                    </div>
                </div>

                <?php if (!empty($waPhone)): ?>
                    <a href="https://api.whatsapp.com/send?phone=<?= $waPhone ?>&text=<?= urlencode($waWishText) ?>" target="_blank" class="px-2 py-1 rounded-xl bg-emerald-50 hover:bg-emerald-600 text-emerald-600 hover:text-white border border-emerald-200/70 text-[10px] font-bold inline-flex items-center gap-1 transition shrink-0" title="Send WhatsApp Birthday Wish">
                        <i data-lucide="message-circle" class="w-3 h-3"></i>
                        <span>Wish</span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>