<!-- views/tl/reports.php -->
<?php
$user = authUser();
$db = getDBConnection();
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// 1. Fetch team members (Supports TL and TL Support)
$teamIds = getManagedTeamUserIds($user['id']);
$inTeam = !empty($teamIds) ? implode(',', array_map('intval', $teamIds)) : '0';
$teamMembers = $db->query("SELECT id, name, designation, avatar FROM users WHERE id IN ($inTeam) AND status = 'active' ORDER BY name ASC")->fetchAll() ?: [];

// 2. Fetch TL's Own Reports submitted to HR
$myReportsStmt = $db->prepare("
    SELECT r.*, hr.name as hr_name
    FROM daily_work_reports r
    LEFT JOIN users hr ON r.reviewed_by = hr.id
    WHERE r.user_id = ?
    ORDER BY r.report_date DESC
");
$myReportsStmt->execute([$user['id']]);
$myReports = $myReportsStmt->fetchAll();

// 3. Fetch Live Active Team Tasks for auto-attachment preview in submit modal
$activeTeamTasks = $db->query("
    SELECT t.id, t.title, t.priority, t.status, t.due_date, t.original_due_date, t.is_extended, t.extension_reason,
           COALESCE(p.title, 'General') as project_name, u.name as employee_name, u.designation as employee_designation,
           (SELECT ts.notes FROM task_submissions ts WHERE ts.task_id = t.id ORDER BY ts.submitted_at DESC LIMIT 1) as latest_employee_remarks
    FROM tasks t
    LEFT JOIN projects p ON t.project_id = p.id
    JOIN users u ON t.assigned_to = u.id
    WHERE t.assigned_to IN ($inTeam) OR t.created_by = {$user['id']}
    ORDER BY CASE t.status WHEN 'review' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'completed' THEN 3 ELSE 4 END, t.due_date ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="space-y-6" x-data="{
    tlModalOpen: false
}">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center font-bold shadow-md shadow-indigo-500/20 shrink-0">
                <i data-lucide="file-text" class="w-5 h-5"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-extrabold text-slate-900 tracking-tight">Daily Work Reports & HR Transmission</h1>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-indigo-50 text-indigo-700 border border-indigo-200">TL Workspace</span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Transmit executive team progress, sprint milestones, and auto-attached task deliverables to HR Administration.</p>
            </div>
        </div>

        <button @click="tlModalOpen = true" class="px-5 py-2.5 bg-gradient-to-r from-indigo-600 via-indigo-700 to-purple-700 hover:from-indigo-500 hover:to-purple-600 text-white text-xs font-bold rounded-xl shadow-lg shadow-indigo-600/25 hover:shadow-indigo-600/40 transition-all transform hover:-translate-y-0.5 inline-flex items-center gap-2 cursor-pointer">
            <i data-lucide="send" class="w-4 h-4"></i>
            <span>Submit Daily TL Report to HR</span>
        </button>
    </div>

    <!-- My Reports Submitted to HR -->
    <?php if (empty($myReports)): ?>
        <div class="bg-white p-12 rounded-3xl border border-slate-200 shadow-sm text-center">
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center mx-auto mb-3">
                <i data-lucide="send" class="w-7 h-7"></i>
            </div>
            <h3 class="text-sm font-bold text-slate-800">No Management Reports Transmitted to HR Yet</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">Click "Submit Daily TL Report to HR" above to bundle your team's completed tasks, deliverable proofs, and sprint updates to HR Administration.</p>
            <button @click="tlModalOpen = true" class="mt-4 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition inline-flex items-center gap-1.5 cursor-pointer">
                <i data-lucide="send" class="w-3.5 h-3.5"></i>
                <span>Submit First Report</span>
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
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase <?= $rep['status'] === 'reviewed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200' ?>">
                            <?= $rep['status'] === 'reviewed' ? '✅ Acknowledged by HR' : '🚀 Delivered to HR' ?>
                        </span>
                    </div>

                    <div class="p-4 bg-slate-50/70 rounded-xl space-y-2.5 text-xs border border-slate-100">
                            <div>
                                <strong class="text-slate-800 uppercase text-[10px] font-bold tracking-wide block">?? Team Deliverables & Work Accomplished:</strong>
                                <p class="text-slate-700 mt-0.5 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($rep['tasks_completed']) ?></p>
                            </div>
                            <?php if (!empty($rep['tasks_in_progress'])): ?>
                                <div>
                                    <strong class="text-slate-800 uppercase text-[10px] font-bold tracking-wide block">?? Ongoing Sprints:</strong>
                                    <p class="text-slate-700 mt-0.5 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($rep['tasks_in_progress']) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($rep['blockers'])): ?>
                                <div class="p-2.5 bg-rose-50 rounded-lg border border-rose-100">
                                    <strong class="text-rose-700 uppercase text-[10px] font-bold tracking-wide block">?? Impediments / HR Escalations:</strong>
                                    <p class="text-rose-600 mt-0.5 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($rep['blockers']) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($rep['plan_for_tomorrow'])): ?>
                                <div>
                                    <strong class="text-indigo-700 uppercase text-[10px] font-bold tracking-wide block">?? Tomorrow's Target:</strong>
                                    <p class="text-slate-700 mt-0.5 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($rep['plan_for_tomorrow']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                                                <!-- Auto-Attached Daily Team Tasks Breakdown -->
                        <?php 
                        $attachedTasks = !empty($rep['tasks_snapshot_json']) ? json_decode($rep['tasks_snapshot_json'], true) : [];
                        ?>
                        <?php if (!empty($attachedTasks)): ?>
                            <div class="pt-2 border-t border-slate-100" x-data="{ showTasks: false }">
                                <button type="button" @click="showTasks = !showTasks" class="w-full flex items-center justify-between p-2.5 bg-indigo-50/60 hover:bg-indigo-50 rounded-xl border border-indigo-100 text-xs font-bold text-indigo-900 transition">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="check-square" class="w-4 h-4 text-indigo-600"></i>
                                        <span>Auto-Attached Team Tasks Breakdown (<?= count($attachedTasks) ?> Tasks)</span>
                                    </div>
                                    <div class="flex items-center gap-1 text-[11px] text-indigo-600">
                                        <span x-text="showTasks ? 'Hide Breakdown' : 'View Tasks (Status & Remarks)'"></span>
                                        <i data-lucide="chevron-down" class="w-4 h-4 transition-transform duration-200" :class="showTasks ? 'rotate-180' : ''"></i>
                                    </div>
                                </button>

                                <div x-show="showTasks" x-cloak class="mt-3 space-y-2">
                                    <?php foreach ($attachedTasks as $t): ?>
                                        <div class="p-3 bg-white rounded-xl border border-slate-200 text-xs space-y-1.5 shadow-sm">
                                            <div class="flex items-start justify-between gap-2">
                                                <div>
                                                    <div class="font-bold text-slate-900 flex items-center gap-1.5">
                                                        <span><?= htmlspecialchars($t['title']) ?></span>
                                                        <span class="text-[10px] px-1.5 py-0.2 rounded bg-slate-100 text-slate-600 font-normal"><?= htmlspecialchars($t['project_name']) ?></span>
                                                    </div>
                                                    <div class="text-[11px] text-slate-500 mt-0.5">
                                                        Assigned to: <strong class="text-slate-800"><?= htmlspecialchars($t['employee_name']) ?></strong>
                                                    </div>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <?= getStatusBadge($t['status']) ?>
                                                </div>
                                            </div>

                                            <!-- Dates (Original vs Extended) -->
                                            <div class="flex items-center gap-2 text-[11px] pt-1 border-t border-slate-50">
                                                <span class="text-slate-500 font-mono">Due: <strong><?= formatDate($t['due_date']) ?></strong></span>
                                                <?php if (!empty($t['is_extended'])): ?>
                                                    <span class="px-2 py-0.2 rounded bg-amber-50 text-amber-700 font-bold border border-amber-200 text-[10px]" title="Extended by TL: <?= htmlspecialchars($t['extension_reason'] ?? '') ?>">
                                                        ?? Extended by TL (Orig: <?= formatDate($t['original_due_date']) ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Employee Latest Remarks & Media Attachments -->
                                            <?php if (!empty($t['latest_employee_remarks']) || !empty($t['latest_attachment_file']) || !empty($t['latest_attachment_url'])): ?>
                                                <div class="p-2.5 bg-slate-50 rounded-xl text-[11px] text-slate-700 mt-1.5 border border-slate-200/80 space-y-2">
                                                    <?php if (!empty($t['latest_employee_remarks'])): ?>
                                                        <div>
                                                            <strong class="text-slate-900 block text-[10px] uppercase font-bold tracking-wider">Employee Remarks / Deliverable:</strong>
                                                            <p class="mt-0.5 leading-relaxed text-slate-800 font-medium"><?= htmlspecialchars($t['latest_employee_remarks']) ?></p>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- Photo / Video / File Attachment Proof -->
                                                    <?php if (!empty($t['latest_attachment_file'])): ?>
                                                        <?php 
                                                        $filePath = $t['latest_attachment_file'];
                                                        $fileType = $t['latest_attachment_type'] ?? '';
                                                        $isImg = ($fileType === 'image' || preg_match('/\.(jpg|jpeg|png|webp|gif)$/i', $filePath));
                                                        $isVid = ($fileType === 'video' || preg_match('/\.(mp4|webm|mov|mkv)$/i', $filePath));
                                                        ?>
                                                        <div class="pt-2 border-t border-slate-200 space-y-1.5">
                                                            <div class="flex items-center justify-between mb-1.5">
                                                                <span class="text-[10px] font-bold text-indigo-700 uppercase tracking-wider flex items-center gap-1">
                                                                    <i data-lucide="paperclip" class="w-3 h-3 text-indigo-600"></i>
                                                                    Deliverable Proof (<?= $isImg ? '🖼️ Photo' : ($isVid ? '🎥 Video Demo' : '📎 Document') ?>)
                                                                </span>
                                                                <a href="?page=tech-drive" class="text-[10px] font-bold text-indigo-600 hover:text-indigo-800 inline-flex items-center gap-1">
                                                                    <i data-lucide="hard-drive" class="w-3 h-3"></i> Tech Cloud Drive &rarr;
                                                                </a>
                                                            </div>

                                                            <?php if ($isImg): ?>
                                                                <div class="rounded-lg overflow-hidden border border-slate-200 bg-white p-1 max-w-sm">
                                                                    <a href="<?= htmlspecialchars($filePath) ?>" target="_blank" title="Click to view full image">
                                                                        <img src="<?= htmlspecialchars($filePath) ?>" class="max-h-48 w-auto rounded object-contain hover:opacity-95 transition" alt="Proof">
                                                                    </a>
                                                                    <div class="p-1 text-[10px] text-slate-400">Click photo to view full resolution</div>
                                                                </div>
                                                            <?php elseif ($isVid): ?>
                                                                <div class="rounded-lg overflow-hidden border border-slate-200 bg-black max-w-md shadow-xs">
                                                                    <video src="<?= htmlspecialchars($filePath) ?>" controls class="w-full max-h-56 rounded bg-black" preload="metadata">
                                                                        Your browser does not support video playback.
                                                                    </video>
                                                                </div>
                                                            <?php else: ?>
                                                                <div class="flex items-center gap-2">
                                                                    <a href="<?= htmlspecialchars($filePath) ?>" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-indigo-600 hover:text-indigo-800 font-semibold text-xs shadow-2xs">
                                                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                                                        <span>Preview Document</span>
                                                                    </a>
                                                                    <a href="?page=tech-drive" class="text-xs text-slate-500 hover:text-indigo-600 font-medium">Download from Drive &rarr;</a>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>

                                                    <!-- External URL Link -->
                                                    <?php if (!empty($t['latest_attachment_url'])): ?>
                                                        <div class="pt-1.5 border-t border-slate-200 flex items-center justify-between gap-2">
                                                            <span class="text-[10px] font-bold text-indigo-700 uppercase flex items-center gap-1">
                                                                <i data-lucide="external-link" class="w-3 h-3 text-indigo-600"></i>
                                                                Deliverable URL:
                                                            </span>
                                                            <a href="<?= htmlspecialchars($t['latest_attachment_url']) ?>" target="_blank" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 hover:underline truncate max-w-xs">
                                                                <?= htmlspecialchars($t['latest_attachment_url']) ?> &rarr;
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($rep['reviewer_feedback'])): ?>
                            <div class="p-3.5 bg-emerald-50 rounded-xl border border-emerald-200 text-xs text-emerald-950 flex items-start gap-2.5">
                                <i data-lucide="check-check" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5"></i>
                                <div>
                                    <strong class="text-emerald-900">HR Executive Response from <?= htmlspecialchars($rep['hr_name'] ?: 'Shra Fatima') ?>:</strong>
                                    <p class="mt-0.5 leading-relaxed"><?= htmlspecialchars($rep['reviewer_feedback']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <!-- Modal: Submit TL Report to HR (Redesigned Executive Form) -->
    <div x-show="tlModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-md flex items-center justify-center p-4 sm:p-6" x-cloak>
        <div @click.away="tlModalOpen = false" class="bg-white rounded-3xl max-w-2xl w-full shadow-2xl border border-slate-200/80 overflow-hidden transform transition-all">
            
            <!-- Modal Ambient Top Banner -->
            <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 text-white relative overflow-hidden">
                <div class="absolute -right-6 -bottom-6 w-32 h-32 bg-indigo-500/10 rounded-full blur-2xl pointer-events-none"></div>
                <div class="flex items-start justify-between relative z-10">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 border border-indigo-400/30 text-indigo-300 flex items-center justify-center font-bold shrink-0 shadow-inner">
                            <i data-lucide="send" class="w-6 h-6"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[9px] font-extrabold uppercase tracking-widest bg-indigo-500/30 text-indigo-200 border border-indigo-400/30">Executive Dispatch</span>
                                <span class="text-xs text-indigo-300 font-medium">To: <strong>Shra Fatima (HR Director)</strong></span>
                            </div>
                            <h3 class="text-lg font-bold text-white tracking-tight mt-0.5">Daily Team Management Report</h3>
                        </div>
                    </div>
                    <button @click="tlModalOpen = false" class="text-slate-400 hover:text-white p-1.5 rounded-xl hover:bg-white/10 transition">
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
                        <div class="relative">
                            <input type="date" name="report_date" required value="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3 py-2.5 text-xs text-slate-900 font-bold focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition">
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">Executive Briefing Title *</label>
                        <input type="text" name="title" required placeholder="e.g., Sprint Velocity, Key Deliverables & Milestones" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5 text-xs text-slate-900 font-medium focus:bg-white focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-600 transition">
                    </div>
                </div>

                <!-- Section 1: Team Deliverables & Key Highlights -->
                <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-1.5">
                    <label class="flex items-center gap-1.5 text-xs font-bold text-slate-900">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Key Deliverables & Completed Milestones *</span>
                    </label>
                    <textarea name="tasks_completed" rows="3" required placeholder="� Completed API endpoints and tested auth flow&#10;� Deployed staging release for client review&#10;� Resolved 4 high-priority QA tickets" class="w-full bg-white border border-slate-200 rounded-xl p-3 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 transition leading-relaxed"></textarea>
                </div>

                <!-- Section 2: Two Column Split for In-Progress & Tomorrow Target -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-1.5">
                        <label class="flex items-center gap-1.5 text-xs font-bold text-slate-900">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                            <span>Ongoing Tasks & Sprints</span>
                        </label>
                        <textarea name="tasks_in_progress" rows="2" placeholder="Tasks currently undergoing code review or final testing..." class="w-full bg-white border border-slate-200 rounded-xl p-3 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600 transition leading-relaxed"></textarea>
                    </div>

                    <div class="p-4 bg-slate-50/80 rounded-2xl border border-slate-200/80 space-y-1.5">
                        <label class="flex items-center gap-1.5 text-xs font-bold text-slate-900">
                            <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                            <span>Tomorrow's Team Targets</span>
                        </label>
                        <textarea name="plan_for_tomorrow" rows="2" placeholder="Next sprint priorities and upcoming module deliveries..." class="w-full bg-white border border-slate-200 rounded-xl p-3 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 transition leading-relaxed"></textarea>
                    </div>
                </div>

                <!-- Section 3: Blockers & Support Needed (Alert Style) -->
                <div class="p-4 bg-rose-50/50 rounded-2xl border border-rose-200/80 space-y-1.5">
                    <label class="flex items-center gap-1.5 text-xs font-bold text-rose-900">
                        <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-rose-600"></i>
                        <span>Impediments, Blockers & Matters Needing HR Support (Optional)</span>
                    </label>
                    <textarea name="blockers" rows="2" placeholder="Any resource bottlenecks, infrastructure dependencies, or matters requiring HR/Director assistance..." class="w-full bg-white border border-rose-200 rounded-xl p-3 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-rose-500/20 focus:border-rose-600 transition leading-relaxed"></textarea>
                </div>

                                <!-- Section 4: Auto-Attached Tasks Live Preview -->
                <div class="p-4 bg-indigo-50/70 rounded-2xl border border-indigo-200/80 space-y-2">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i data-lucide="paperclip" class="w-4 h-4 text-indigo-600"></i>
                            <span class="text-xs font-bold text-indigo-950">Auto-Attached Team Tasks Breakdown (<?= count($activeTeamTasks) ?> Tasks)</span>
                        </div>
                        <span class="px-2 py-0.5 rounded-full bg-indigo-200 text-indigo-900 text-[10px] font-extrabold uppercase">Auto Synced to HR</span>
                    </div>
                    <p class="text-[11px] text-indigo-800 leading-normal">
                        Live status, employee remarks, due dates, and extended dates for all assigned tasks will automatically be packaged into this executive report for HR audit.
                    </p>

                    <?php if (!empty($activeTeamTasks)): ?>
                        <div class="mt-2 max-h-44 overflow-y-auto space-y-1.5 pr-1 no-scrollbar">
                            <?php foreach ($activeTeamTasks as $t): ?>
                                <div class="p-2.5 bg-white rounded-xl border border-indigo-100 flex items-center justify-between text-xs gap-2">
                                    <div class="min-w-0 flex-1">
                                        <div class="font-bold text-slate-900 truncate"><?= htmlspecialchars($t['title']) ?></div>
                                        <div class="text-[10px] text-slate-500 flex items-center gap-1.5 flex-wrap mt-0.5">
                                            <span>Assigned: <strong><?= htmlspecialchars($t['employee_name']) ?></strong></span>
                                            <span>�</span>
                                            <span>Due: <strong><?= formatDate($t['due_date']) ?></strong></span>
                                            <?php if (!empty($t['is_extended'])): ?>
                                                <span class="px-1.5 py-0.2 rounded bg-amber-100 text-amber-800 font-bold text-[9px]">?? Extended</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="shrink-0">
                                        <?= getStatusBadge($t['status']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Footer Actions -->
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <button type="button" @click="tlModalOpen = false" class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white text-xs font-bold transition shadow-lg shadow-indigo-600/25 inline-flex items-center gap-2">
                        <i data-lucide="send" class="w-4 h-4"></i>
                        <span>Transmit Report to HR</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Review Team DSR -->
    <div x-show="reviewModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/70 backdrop-blur-md flex items-center justify-center p-4 sm:p-6" x-cloak>
        <div @click.away="reviewModalOpen = false" class="bg-white rounded-3xl max-w-lg w-full shadow-2xl border border-slate-200/80 overflow-hidden transform transition-all">
            
            <div class="bg-gradient-to-r from-slate-900 to-indigo-950 p-5 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-400/30 flex items-center justify-center font-bold">
                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-white">Review Team Daily Work Report</h3>
                        <p class="text-xs text-slate-300" x-text="selectedDsr ? 'Employee: ' + selectedDsr.employee_name : ''"></p>
                    </div>
                </div>
                <button @click="reviewModalOpen = false" class="text-slate-400 hover:text-white p-1 rounded-lg"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form action="?action=review-daily-report" method="POST" class="p-6 space-y-4">
                <input type="hidden" name="report_id" :value="selectedDsr ? selectedDsr.id : 0">

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">Review Decision *</label>
                    <select name="status" x-model="reviewStatus" required class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-900 font-bold focus:bg-white focus:ring-2 focus:ring-indigo-500">
                        <option value="reviewed">? Reviewed & Approved</option>
                        <option value="revision_requested">?? Needs Revision / Additional Details</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-wider text-slate-600 mb-1.5">Team Lead Feedback & Guidance</label>
                    <textarea name="reviewer_feedback" x-model="reviewFeedback" rows="4" placeholder="Add appreciation, feedback, guidance, or next steps..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-900 focus:bg-white focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="reviewModalOpen = false" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shadow-sm inline-flex items-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4"></i> Save Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
