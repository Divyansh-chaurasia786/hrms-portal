<!-- views/admin/birthdays.php -->
<?php
$user = authUser();
$db = getDBConnection();

// 1. Fetch All Active Staff with Birthdays
$allStaff = $db->query("
    SELECT id, name, emp_id, email, phone, whatsapp_number, designation, department_name, avatar, date_of_birth,
           DATE_FORMAT(date_of_birth, '%d %M') as dob_formatted,
           DATE_FORMAT(date_of_birth, '%m-%d') as dob_month_day,
           TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as current_age
    FROM users 
    WHERE status = 'active'
    ORDER BY 
        CASE WHEN date_of_birth IS NOT NULL THEN 0 ELSE 1 END,
        DATE_FORMAT(date_of_birth, '%m-%d') ASC,
        name ASC
")->fetchAll(PDO::FETCH_ASSOC) ?: [];

$todayMonthDay = date('m-d');
$currentMonth = date('m');

$todayBirthdays = [];
$upcomingThisMonth = [];
$allUpcoming = [];
$missingDob = [];

foreach ($allStaff as $st) {
    if (empty($st['date_of_birth'])) {
        $missingDob[] = $st;
        continue;
    }

    $mDay = $st['dob_month_day'];
    if ($mDay === $todayMonthDay) {
        $todayBirthdays[] = $st;
    } else {
        // Calculate days remaining until next birthday
        $thisYearDob = date('Y') . '-' . $mDay;
        if ($thisYearDob < date('Y-m-d')) {
            $nextBday = (date('Y') + 1) . '-' . $mDay;
        } else {
            $nextBday = $thisYearDob;
        }

        $diffDays = (int)round((strtotime($nextBday) - strtotime(date('Y-m-d'))) / 86400);
        $st['days_remaining'] = $diffDays;
        $st['turning_age'] = (int)($st['current_age'] ?? 0) + 1;

        if (substr($mDay, 0, 2) === $currentMonth && $diffDays > 0) {
            $upcomingThisMonth[] = $st;
        }
        $allUpcoming[] = $st;
    }
}

// Sort upcoming by days remaining
usort($allUpcoming, fn($a, $b) => ($a['days_remaining'] ?? 0) <=> ($b['days_remaining'] ?? 0));
usort($upcomingThisMonth, fn($a, $b) => ($a['days_remaining'] ?? 0) <=> ($b['days_remaining'] ?? 0));
?>

<div class="space-y-6" x-data="{
    editModalOpen: false,
    selectedUser: null,
    dobInput: '',
    searchQuery: '',

    openEditModal(u) {
        this.selectedUser = u;
        this.dobInput = u.date_of_birth || '';
        this.editModalOpen = true;
    }
}">
    <!-- Header Banner -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-3xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3.5">
            <div class="w-11 h-11 rounded-2xl bg-pink-50 text-pink-600 flex items-center justify-center font-extrabold shadow-sm shrink-0">
                <i data-lucide="cake" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Employee Birthday & Celebration Hub</h1>
                <p class="text-xs text-slate-500 mt-0.5">Manage workforce birthdays, track upcoming celebrations, and send 1-click official greetings.</p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="?page=admin-smart-sheets" class="px-4 py-2.5 bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold rounded-2xl border border-emerald-200 transition flex items-center gap-1.5">
                <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Import Birthdays via Sheet
            </a>
        </div>
    </div>

    <!-- 🎂 TODAY'S BIRTHDAY HERO CARD -->
    <?php if (!empty($todayBirthdays)): ?>
        <div class="p-6 rounded-3xl bg-gradient-to-r from-pink-500 via-rose-500 to-purple-600 text-white shadow-xl shadow-pink-500/20 relative overflow-hidden space-y-4">
            <div class="flex items-center justify-between relative z-10 flex-wrap gap-3">
                <div class="flex items-center gap-2.5">
                    <span class="text-2xl animate-bounce">🎉</span>
                    <div>
                        <h2 class="text-lg font-black tracking-wide">TODAY'S CELEBRATIONS!</h2>
                        <p class="text-xs text-pink-100">Happy Birthday to our fantastic team members celebrating today!</p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full bg-white/20 backdrop-blur-md text-xs font-extrabold">
                    <?= count($todayBirthdays) ?> Birthday(s) Today
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 relative z-10">
                <?php foreach ($todayBirthdays as $tb): 
                    $waMsg = urlencode("🎂 Happy Birthday {$tb['name']}! Wishing you great success, good health, and a fantastic year ahead from all of us at Ecovista Global!");
                    $cleanPhone = preg_replace('/[^\d]/', '', (string)($tb['whatsapp_number'] ?: $tb['phone']));
                ?>
                    <div class="bg-white/15 backdrop-blur-md p-4 rounded-2xl border border-white/20 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <img src="<?= $tb['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($tb['name']) . '&background=fff&color=db2777' ?>" class="w-12 h-12 rounded-full object-cover ring-2 ring-white/50 shrink-0">
                            <div>
                                <h3 class="font-bold text-sm text-white"><?= htmlspecialchars($tb['name']) ?></h3>
                                <p class="text-[11px] text-pink-100"><?= htmlspecialchars($tb['designation']) ?></p>
                                <span class="text-[10px] text-pink-200 font-mono"><?= htmlspecialchars($tb['department_name']) ?></span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1.5 shrink-0">
                            <form action="?action=send-birthday-wish" method="POST" class="m-0">
                                <input type="hidden" name="user_id" value="<?= $tb['id'] ?>">
                                <button type="submit" class="px-2.5 py-1 rounded-xl bg-white text-pink-700 hover:bg-pink-50 text-[11px] font-extrabold shadow-sm transition flex items-center gap-1 cursor-pointer">
                                    <i data-lucide="mail" class="w-3 h-3"></i> Send Email
                                </button>
                            </form>
                            <?php if (!empty($cleanPhone)): ?>
                                <a href="https://wa.me/<?= $cleanPhone ?>?text=<?= $waMsg ?>" target="_blank" class="px-2.5 py-1 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-[11px] font-extrabold shadow-sm transition flex items-center gap-1 text-center justify-center">
                                    <i data-lucide="message-circle" class="w-3 h-3"></i> WhatsApp
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- UPCOMING BIRTHDAYS & FULL ROSTER (Grid Layout) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left: Upcoming This Month Timeline (1 Col) -->
        <div class="bg-white p-5 rounded-3xl border border-slate-200 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i data-lucide="calendar" class="w-4 h-4 text-purple-600"></i>
                    <h3 class="font-extrabold text-sm text-slate-900">Upcoming This Month</h3>
                </div>
                <span class="text-xs font-bold text-slate-400 font-mono"><?= date('F Y') ?></span>
            </div>

            <?php if (empty($upcomingThisMonth)): ?>
                <div class="text-center py-8 text-slate-400 text-xs">
                    <i data-lucide="gift" class="w-8 h-8 mx-auto text-slate-300 mb-2"></i>
                    No more birthdays remaining in <?= date('F') ?>.
                </div>
            <?php else: ?>
                <div class="space-y-2.5">
                    <?php foreach ($upcomingThisMonth as $ub): ?>
                        <div class="p-3 rounded-2xl bg-slate-50 border border-slate-100 hover:border-purple-200 transition flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-700 font-bold text-xs flex items-center justify-center shrink-0">
                                    <?= date('d', strtotime($ub['date_of_birth'])) ?>
                                </div>
                                <div>
                                    <h4 class="font-bold text-xs text-slate-900"><?= htmlspecialchars($ub['name']) ?></h4>
                                    <p class="text-[10px] text-slate-500"><?= htmlspecialchars($ub['designation']) ?></p>
                                </div>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 shrink-0">
                                In <?= $ub['days_remaining'] ?> day(s)
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Full Workforce Birthday Directory & Management (2 Cols) -->
        <div class="lg:col-span-2 bg-white p-5 rounded-3xl border border-slate-200 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h3 class="font-extrabold text-sm text-slate-900">Workforce Birthday Register</h3>
                    <p class="text-xs text-slate-500">All employee date-of-birth records with 1-click update.</p>
                </div>

                <div class="relative max-w-xs w-full">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                    <input type="text" x-model="searchQuery" placeholder="Search employee or department..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-9 pr-3 py-2 text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-pink-500">
                </div>
            </div>

            <div class="overflow-x-auto border border-slate-200 rounded-2xl">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50 text-slate-400 uppercase text-[10px] font-bold border-b border-slate-200">
                        <tr>
                            <th class="py-3 px-3">Employee</th>
                            <th class="py-3 px-3">Department</th>
                            <th class="py-3 px-3">Date of Birth</th>
                            <th class="py-3 px-3">Next Celebration</th>
                            <th class="py-3 px-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($allStaff as $st): 
                            $hasDob = !empty($st['date_of_birth']);
                            $isToday = $st['dob_month_day'] === $todayMonthDay;
                        ?>
                            <tr class="hover:bg-slate-50/80 transition" x-show="!searchQuery.trim() || '<?= strtolower(addslashes($st['name'] . ' ' . $st['department_name'] . ' ' . $st['emp_id'])) ?>'.includes(searchQuery.toLowerCase())">
                                <td class="py-3 px-3">
                                    <div class="flex items-center gap-2.5">
                                        <img src="<?= $st['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($st['name']) ?>" class="w-8 h-8 rounded-full object-cover ring-1 ring-slate-200 shrink-0">
                                        <div>
                                            <div class="font-bold text-slate-900"><?= htmlspecialchars($st['name']) ?></div>
                                            <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($st['emp_id']) ?> • <?= htmlspecialchars($st['designation']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-3 text-slate-600 font-medium">
                                    <?= htmlspecialchars($st['department_name']) ?>
                                </td>
                                <td class="py-3 px-3 font-mono font-bold">
                                    <?php if ($hasDob): ?>
                                        <span class="text-slate-800"><?= $st['dob_formatted'] ?></span>
                                        <span class="text-[10px] text-slate-400 block font-normal">(<?= date('Y', strtotime($st['date_of_birth'])) ?>)</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            Not Set
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3">
                                    <?php if ($isToday): ?>
                                        <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-pink-100 text-pink-800 animate-pulse flex items-center gap-1 w-fit">
                                            🎂 TODAY!
                                        </span>
                                    <?php elseif ($hasDob): ?>
                                        <span class="text-slate-500 font-medium">
                                            <?= $st['dob_formatted'] ?> (In <?= $st['days_remaining'] ?? '-' ?>d)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-[10px]">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-3 text-right">
                                    <button type="button" @click="openEditModal(<?= htmlspecialchars(json_encode($st)) ?>)" class="px-3 py-1 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] transition cursor-pointer">
                                        <i data-lucide="edit-3" class="w-3 h-3 inline"></i> Edit DOB
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 🎂 EDIT DOB MODAL -->
    <div x-show="editModalOpen" class="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4" style="display:none;">
        <div @click.away="editModalOpen = false" class="bg-white rounded-3xl max-w-sm w-full p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-pink-50 text-pink-600 flex items-center justify-center font-bold">
                        <i data-lucide="cake" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">Set Date of Birth</h3>
                        <p class="text-[11px] text-slate-500" x-text="selectedUser ? selectedUser.name : ''"></p>
                    </div>
                </div>
                <button type="button" @click="editModalOpen = false" class="text-slate-400 hover:text-slate-700">
                    <i data-lucide="x" class="w-4 h-4"></i>
                </button>
            </div>

            <form action="?action=update-employee-dob" method="POST" class="space-y-4">
                <input type="hidden" name="user_id" :value="selectedUser ? selectedUser.id : 0">
                
                <div class="space-y-1">
                    <label class="block text-xs font-bold text-slate-700">Date of Birth *</label>
                    <input type="date" name="date_of_birth" x-model="dobInput" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-900 focus:ring-2 focus:ring-pink-500">
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="editModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-xl">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2.5 bg-pink-600 hover:bg-pink-700 text-white rounded-xl text-xs font-bold shadow-md transition">
                        Save Birthday
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>