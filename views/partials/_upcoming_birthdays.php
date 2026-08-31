<!-- views/partials/_upcoming_birthdays.php -->
<?php
$upcomingBirthdays = getUpcomingBirthdaysWithinDays(30);
?>
<div class="bg-white rounded-3xl border border-slate-200 shadow-sm p-5 sm:p-6 space-y-4">
    <div class="flex items-center justify-between gap-3 flex-wrap pb-3 border-b border-slate-100">
        <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-2xl bg-gradient-to-tr from-pink-500 to-rose-500 text-white flex items-center justify-center font-bold shadow-md shadow-pink-500/20 text-base">
                🎂
            </div>
            <div>
                <h3 class="font-extrabold text-slate-900 text-sm sm:text-base tracking-tight">Upcoming Birthdays & Celebrations (Next 30 Days)</h3>
                <p class="text-[11px] text-slate-400 font-medium">Automatic 30-day countdown with 24-hour live clock</p>
            </div>
        </div>
        <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-pink-50 text-pink-700 border border-pink-200">
            <?= count($upcomingBirthdays) ?> Celebrations Ahead
        </span>
    </div>

    <?php if (empty($upcomingBirthdays)): ?>
        <div class="text-center py-8 text-xs text-slate-400 italic bg-slate-50 rounded-2xl">
            No workforce birthdays in the upcoming 30 days.
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3.5">
            <?php foreach ($upcomingBirthdays as $b): ?>
                <?php
                $isToday = ($b['urgency'] === 'today');
                $isHours = ($b['urgency'] === 'hours');
                $isTomorrow = ($b['urgency'] === 'tomorrow');
                
                $badgeClass = $isToday 
                    ? 'bg-gradient-to-r from-pink-600 to-rose-600 text-white animate-pulse shadow-md shadow-pink-500/30' 
                    : ($isHours || $isTomorrow
                        ? 'bg-amber-100 text-amber-900 border border-amber-300 font-black'
                        : 'bg-indigo-50 text-indigo-700 border border-indigo-200');

                $waPhone = preg_replace('/[^0-9]/', '', $b['whatsapp_number'] ?: ($b['phone'] ?: ''));
                $empFirstName = explode(' ', $b['name'])[0];
                $waWishText = "Happy Birthday, {$empFirstName}! 🎉🎂 Wishing you a fantastic day and another year of great achievements at Ecofone! Best regards, Team Ecofone.";
                ?>
                <div class="rounded-2xl p-4 border transition hover:shadow-md <?= $isToday ? 'bg-gradient-to-br from-pink-50/80 via-rose-50/50 to-amber-50/30 border-pink-300 ring-2 ring-pink-400/20' : 'bg-slate-50/70 border-slate-200 hover:border-indigo-300' ?> flex flex-col justify-between gap-3">
                    <div class="flex items-start justify-between gap-2.5">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="relative shrink-0">
                                <img src="<?= $b['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($b['name']) . '&background=4f46e5&color=fff' ?>" class="w-10 h-10 rounded-xl object-cover ring-2 ring-white shadow-xs" alt="Avatar">
                                <?php if ($isToday): ?>
                                    <span class="absolute -top-1.5 -right-1.5 text-xs animate-bounce">👑</span>
                                <?php endif; ?>
                            </div>
                            <div class="min-w-0">
                                <h4 class="font-bold text-xs text-slate-900 truncate"><?= htmlspecialchars($b['name']) ?></h4>
                                <p class="text-[10px] text-slate-500 truncate"><?= htmlspecialchars($b['designation']) ?></p>
                                <p class="text-[10px] text-slate-400 truncate"><?= htmlspecialchars($b['department_name'] ?? 'General') ?></p>
                            </div>
                        </div>

                        <!-- Countdown Chip -->
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider shrink-0 <?= $badgeClass ?>">
                            <?= $b['countdown_text'] ?>
                        </span>
                    </div>

                    <!-- Footer Details & Wish Actions -->
                    <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between gap-2">
                        <div class="text-[11px] font-bold text-slate-600 flex items-center gap-1">
                            <span>📅 <?= $b['bday_formatted'] ?></span>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <?php if (!empty($waPhone)): ?>
                                <a href="https://api.whatsapp.com/send?phone=<?= $waPhone ?>&text=<?= urlencode($waWishText) ?>" target="_blank" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-[10px] font-bold transition flex items-center gap-1 shadow-xs cursor-pointer" title="Send WhatsApp Birthday Wish">
                                    <i data-lucide="message-circle" class="w-3 h-3"></i> Wish (WA)
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($b['email'])): ?>
                                <a href="mailto:<?= htmlspecialchars($b['email']) ?>?subject=Happy%20Birthday%20from%20Ecofone!&body=<?= urlencode($waWishText) ?>" class="p-1 bg-white hover:bg-slate-100 text-slate-600 rounded-lg border border-slate-200 transition" title="Send Email Wish">
                                    <i data-lucide="mail" class="w-3.5 h-3.5"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>