<!-- views/employee/reports.php -->
<?php
$user = authUser();
$db = getDBConnection();

// Fetch reporting TL name
$tlName = 'Team Lead';
if (!empty($user['reporting_tl_id'])) {
    $tlRow = $db->query("SELECT name FROM users WHERE id = {$user['reporting_tl_id']}")->fetch();
    if ($tlRow) $tlName = $tlRow['name'];
} else {
    $tlName = 'HR Administration';
}

// Fetch all DSRs submitted by this employee
$dsrStmt = $db->prepare("
    SELECT r.*, u.name as reviewer_name
    FROM daily_work_reports r
    LEFT JOIN users u ON r.reviewed_by = u.id
    WHERE r.user_id = ?
    ORDER BY r.report_date DESC
");
$dsrStmt->execute([$user['id']]);
$myReports = $dsrStmt->fetchAll();
?>

<div class="space-y-6" x-data="{ modalOpen: false }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center font-bold shadow-md shadow-indigo-500/20 shrink-0">
                <i data-lucide="file-text" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">My Daily Work Reports (DSR)</h1>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-indigo-50 text-indigo-700 border border-indigo-200">Daily Log</span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Submit your daily deliverables, progress, and blockers to <strong><?= htmlspecialchars($tlName) ?></strong>.</p>
            </div>
        </div>

        <button @click="modalOpen = true" class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-500/25 hover:shadow-indigo-500/40 transition-all transform hover:-translate-y-0.5 inline-flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i>
            <span>Submit Today's DSR</span>
        </button>
    </div>

    <!-- Reports Timeline / Cards -->
    <?php if (empty($myReports)): ?>
        <div class="bg-white p-12 rounded-3xl border border-slate-200 shadow-sm text-center">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="file-plus" class="w-7 h-7"></i>
            </div>
            <h3 class="text-base font-bold text-slate-800">No Daily Work Reports Submitted Yet</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Fill and submit your end-of-day report to keep your Team Lead updated on your daily tasks and milestones.</p>
            <button @click="modalOpen = true" class="mt-4 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-md transition inline-flex items-center gap-1.5">
                <i data-lucide="send" class="w-4 h-4"></i> Submit Your First DSR
            </button>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($myReports as $rep): ?>
                <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-sm space-y-3 hover:border-indigo-200 transition">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5">
                            <span class="px-3 py-1 rounded-xl bg-indigo-50 text-indigo-700 font-bold font-mono text-xs border border-indigo-100 flex items-center gap-1">
                                <i data-lucide="calendar" class="w-3 h-3"></i> <?= formatDate($rep['report_date']) ?>
                            </span>
                            <h2 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($rep['title']) ?></h2>
                        </div>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase <?= $rep['status'] === 'reviewed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                            <?= $rep['status'] === 'reviewed' ? '? Reviewed by TL' : '? Submitted to TL' ?>
                        </span>
                    </div>

                    <div class="p-4 bg-slate-50/70 rounded-xl space-y-2.5 text-xs border border-slate-100">
                        <div>
                            <strong class="text-emerald-700 uppercase text-[10px] font-bold tracking-wide block">? Tasks Completed:</strong>
                            <p class="text-slate-700 mt-0.5 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($rep['tasks_completed']) ?></p>
                        </div>
                        <?php if (!empty($rep['tasks_in_progress'])): ?>
                            <div>
                                <strong class="text-blue-700 uppercase text-[10px] font-bold tracking-wide block">?? In Progress:</strong>
                                <p class="text-slate-700 mt-0.5 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($rep['tasks_in_progress']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($rep['blockers'])): ?>
                            <div class="p-2.5 bg-rose-50 rounded-lg border border-rose-100">
                                <strong class="text-rose-700 uppercase text-[10px] font-bold tracking-wide block">?? Blockers / Help Needed:</strong>
                                <p class="text-rose-600 mt-0.5 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($rep['blockers']) ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($rep['plan_for_tomorrow'])): ?>
                            <div>
                                <strong class="text-purple-700 uppercase text-[10px] font-bold tracking-wide block">?? Tomorrow's Target:</strong>
                                <p class="text-slate-700 mt-0.5 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($rep['plan_for_tomorrow']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($rep['reviewer_feedback'])): ?>
                        <div class="p-3.5 bg-emerald-50 rounded-xl border border-emerald-200 text-xs text-emerald-950 flex items-start gap-2.5">
                            <i data-lucide="message-circle" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5"></i>
                            <div>
                                <strong class="text-emerald-900">Feedback from <?= htmlspecialchars($rep['reviewer_name'] ?: $tlName) ?>:</strong>
                                <p class="mt-0.5 leading-relaxed"><?= htmlspecialchars($rep['reviewer_feedback']) ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Modal: Submit DSR Form (Redesigned Executive Form) -->
    <div x-show="modalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-md flex items-center justify-center p-4 sm:p-6" x-cloak>
        <div @click.away="modalOpen = false" class="bg-white rounded-3xl max-w-2xl w-full shadow-2xl border border-slate-200/80 overflow-hidden transform transition-all">
            
            <!-- Modal Ambient Top Banner -->
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 text-white relative overflow-hidden">
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>
                <div class="flex items-start justify-between relative z-10">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 flex items-center justify-center font-bold shrink-0 shadow-inner">
                            <i data-lucide="file-text" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-widest bg-indigo-500/30 text-indigo-200 border border-indigo-400/30">DSR Submission</span>
                                <span class="text-xs text-indigo-300 font-medium">To: <strong><?= htmlspecialchars($tlName) ?></strong></span>
                            </div>
                            <h3 class="text-lg font-bold text-white tracking-tight mt-0.5">Submit Daily Work Report (DSR)</h3>
                        </div>
                    </div>
                    <button @click="modalOpen = false" class="text-slate-400 hover:text-white p-1.5 rounded-xl hover:bg-white/10 transition">
                        <i data-lucide="x" class="w-5 h-5"></i>
                    </button>
                </div>
            </div>

            <!-- Modal Form Body -->
            <form action="?action=submit-daily-report" method="POST" class="p-6 space-y-4 max-h-[75vh] overflow-y-auto no-scrollbar">
                
                <!-- Row 1: Date & Title -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">Reporting Date *</label>
                        <input type="date" name="report_date" required value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 font-bold focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">Today's Summary Title *</label>
                        <input type="text" name="title" required placeholder="e.g., Frontend UI, API Integration & Bug Fixes" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 font-medium focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition">
                    </div>
                </div>

                <!-- Section 1: Tasks Completed Today -->
                <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-1.5">
                    <label class="flex items-center gap-1.5 text-xs font-bold text-slate-900">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Tasks Completed Today *</span>
                    </label>
                    <textarea name="tasks_completed" rows="3" required placeholder="• Completed responsive navbar and dark theme&#10;• Integrated login OTP endpoints with backend&#10;• Fixed CSS overflow issue on mobile screens" class="w-full bg-white border border-slate-200 rounded-xl p-3 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition leading-relaxed"></textarea>
                </div>

                <!-- Section 2: Two Column Split for In-Progress & Tomorrow Target -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-1.5">
                        <label class="flex items-center gap-1.5 text-xs font-bold text-slate-900">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            <span>In-Progress Tasks</span>
                        </label>
                        <textarea name="tasks_in_progress" rows="2" placeholder="Tasks currently being coded or verified..." class="w-full bg-white border border-slate-200 rounded-xl p-3 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition leading-relaxed"></textarea>
                    </div>

                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-1.5">
                        <label class="flex items-center gap-1.5 text-xs font-bold text-slate-900">
                            <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                            <span>Plan for Tomorrow</span>
                        </label>
                        <textarea name="plan_for_tomorrow" rows="2" placeholder="Next prioritized deliverables on your sprint list..." class="w-full bg-white border border-slate-200 rounded-xl p-3 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 transition leading-relaxed"></textarea>
                    </div>
                </div>

                <!-- Section 3: Blockers / Help Needed -->
                <div class="p-4 bg-rose-50/50 rounded-2xl border border-rose-200/80 space-y-1.5">
                    <label class="flex items-center gap-1.5 text-xs font-bold text-rose-900">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-rose-600"></i>
                        <span>Blockers / Help Needed (Optional)</span>
                    </label>
                    <textarea name="blockers" rows="2" placeholder="Any blockers, missing Figma assets, or technical issues you need TL help with..." class="w-full bg-white border border-rose-200 rounded-xl p-3 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-rose-500/20 focus:border-rose-600 transition leading-relaxed"></textarea>
                </div>

                <!-- Footer Actions -->
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" @click="modalOpen = false" class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-bold transition shadow-lg shadow-indigo-600/25 inline-flex items-center gap-2">
                        <i data-lucide="send" class="w-4 h-4"></i>
                        <span>Submit DSR to TL</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
