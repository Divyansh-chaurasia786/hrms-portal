<!-- views/admin/tl_reports.php -->
<?php
$user = authUser();
$db = getDBConnection();

// Fetch all Team Leads
$tls = $db->query("SELECT id, name, emp_id, designation, avatar, assigned_office_location, temp_office_location, temp_location_expires_at, temp_location_days FROM users WHERE role = 'team_lead' AND status = 'active'")->fetchAll();
$officeLocations = getOfficeLocations();

// For each TL, fetch team performance summary and feedback history
$tlReports = [];
foreach ($tls as $tl) {
    $teamMembers = $db->query("SELECT id, name, designation FROM users WHERE reporting_tl_id = {$tl['id']} AND status = 'active'")->fetchAll();
    $memberIds = array_column($teamMembers, 'id');
    $inClause = !empty($memberIds) ? implode(',', $memberIds) : '0';

    $totalTasks = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE created_by = {$tl['id']} OR assigned_to IN ($inClause)")->fetchColumn();
    $completedTasks = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE (created_by = {$tl['id']} OR assigned_to IN ($inClause)) AND status = 'completed'")->fetchColumn();
    $reviewTasks = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE (created_by = {$tl['id']} OR assigned_to IN ($inClause)) AND status = 'review'")->fetchColumn();
    $inProgressTasks = (int)$db->query("SELECT COUNT(*) FROM tasks WHERE (created_by = {$tl['id']} OR assigned_to IN ($inClause)) AND status = 'in_progress'")->fetchColumn();

    $recentTasks = $db->query("
        SELECT t.*, u.name as assigned_name
        FROM tasks t
        JOIN users u ON t.assigned_to = u.id
        WHERE t.created_by = {$tl['id']} OR t.assigned_to IN ($inClause)
        ORDER BY t.created_at DESC
        LIMIT 4
    ")->fetchAll();

    // Fetch all historical feedbacks for this TL
    $feedbacks = $db->query("
        SELECT f.*, hr.name as hr_name
        FROM tl_feedbacks f
        JOIN users hr ON f.hr_id = hr.id
        WHERE f.tl_id = {$tl['id']}
        ORDER BY f.created_at DESC
    ")->fetchAll();

    $tlReports[] = [
        'tl' => $tl,
        'team_count' => count($teamMembers),
        'total' => $totalTasks,
        'completed' => $completedTasks,
        'in_progress' => $inProgressTasks,
        'review' => $reviewTasks,
        'recent_tasks' => $recentTasks,
        'feedbacks' => $feedbacks
    ];
}

// Fetch all TL employee escalations
$allEscalations = $db->query("
    SELECT e.*, tl.name as tl_name, tl.avatar as tl_avatar, u.name as employee_name, u.designation as employee_designation, u.avatar as employee_avatar, hr.name as hr_name
    FROM employee_escalations e
    JOIN users tl ON e.tl_id = tl.id
    JOIN users u ON e.employee_id = u.id
    LEFT JOIN users hr ON e.hr_action_by = hr.id
    ORDER BY CASE e.status WHEN 'pending' THEN 1 WHEN 'under_review' THEN 2 ELSE 3 END, e.created_at DESC
")->fetchAll();

// Fetch all Daily Reports submitted by Team Leads to HR
$dailyTlReports = $db->query("
    SELECT r.*, tl.name as tl_name, tl.avatar as tl_avatar, tl.designation as tl_designation, hr.name as reviewer_name
    FROM daily_work_reports r
    JOIN users tl ON r.user_id = tl.id
    LEFT JOIN users hr ON r.reviewed_by = hr.id
    WHERE r.user_role = 'team_lead'
    ORDER BY r.report_date DESC, r.created_at DESC
")->fetchAll();

// Month filter for Admin Company Work History (defaults to current month YYYY-MM)
$selectedMonth = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $selectedMonth = date('Y-m');
}
$monthStartDate = $selectedMonth . '-01 00:00:00';
$monthEndDate = date('Y-m-t 23:59:59', strtotime($monthStartDate));
$monthTitle = date('F Y', strtotime($monthStartDate));

$prevMonth = date('Y-m', strtotime("$monthStartDate -1 month"));
$nextMonth = date('Y-m', strtotime("$monthStartDate +1 month"));
$isCurrentMonth = ($selectedMonth === date('Y-m'));

$filterEmp = (int)($_GET['emp_id'] ?? 0);
$filterTl = (int)($_GET['tl_id'] ?? 0);
$searchQuery = trim($_GET['search'] ?? '');

$allEmployees = $db->query("SELECT id, name, emp_id, designation FROM users WHERE role = 'employee' AND status = 'active' ORDER BY name ASC")->fetchAll();

// Query all company task submissions
$compHistorySql = "
    SELECT ts.*, t.title as task_title, t.description as task_description, t.status as task_status,
           t.due_date, p.title as project_title, u.name as employee_name, u.emp_id as employee_code, u.designation as employee_designation, u.avatar as employee_avatar,
           tl.name as tl_name
    FROM task_submissions ts
    JOIN tasks t ON ts.task_id = t.id
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON ts.submitted_by = u.id
    JOIN users tl ON t.created_by = tl.id
    WHERE ts.submitted_at >= :start_date AND ts.submitted_at <= :end_date
";
if ($filterEmp > 0) $compHistorySql .= " AND ts.submitted_by = :emp_id";
if ($filterTl > 0) $compHistorySql .= " AND t.created_by = :tl_id";
if (!empty($searchQuery)) $compHistorySql .= " AND (t.title LIKE :search OR u.name LIKE :search OR ts.notes LIKE :search)";
$compHistorySql .= " ORDER BY ts.submitted_at DESC";

$compHistoryStmt = $db->prepare($compHistorySql);
$compHistParams = [
    ':start_date' => $monthStartDate,
    ':end_date' => $monthEndDate
];
if ($filterEmp > 0) $compHistParams[':emp_id'] = $filterEmp;
if ($filterTl > 0) $compHistParams[':tl_id'] = $filterTl;
if (!empty($searchQuery)) $compHistParams[':search'] = "%{$searchQuery}%";
$compHistoryStmt->execute($compHistParams);
$companyWorkHistory = $compHistoryStmt->fetchAll();

$activeTab = $_GET['tab'] ?? 'escalations';
$pendingEscCount = count(array_filter($allEscalations, fn($e) => $e['status'] === 'pending'));
$pendingRepCount = count(array_filter($dailyTlReports, fn($r) => $r['status'] === 'submitted'));
?>

<div class="space-y-6" x-data="{
    hrReviewModal: false,
    selectedRep: null,
    hrFeedback: '',
    openHrReview(rep) {
        this.selectedRep = rep;
        this.hrFeedback = rep.reviewer_feedback || '';
        this.hrReviewModal = true;
    }
}">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="briefcase" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Team Lead Work Progress & HR Directives</h1>
                <p class="text-xs text-slate-500 mt-0.5">Track daily TL executive reports, review employee escalations, and audit company work deliverables.</p>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs (Horizontal Scrollable on Mobile) -->
    <div class="flex items-center gap-2 border-b border-slate-200 pb-2.5 overflow-x-auto no-scrollbar whitespace-nowrap">
        <a href="?page=admin-tl-reports&tab=daily_reports" class="shrink-0 whitespace-nowrap px-3.5 py-2 rounded-xl text-xs font-bold transition inline-flex items-center gap-1.5 <?= ($activeTab === 'daily_reports') ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' ?>">
            <i data-lucide="file-text" class="w-4 h-4"></i> Daily TL Executive Reports
            <?php if ($pendingRepCount > 0): ?>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= ($activeTab === 'daily_reports') ? 'bg-white text-indigo-700' : 'bg-rose-100 text-rose-700' ?>"><?= $pendingRepCount ?> New</span>
            <?php endif; ?>
        </a>
        <a href="?page=admin-tl-reports&tab=escalations" class="shrink-0 whitespace-nowrap px-3.5 py-2 rounded-xl text-xs font-bold transition inline-flex items-center gap-1.5 <?= ($activeTab === 'escalations') ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' ?>">
            <i data-lucide="shield-alert" class="w-4 h-4"></i> TL Referrals & Escalations
            <?php if ($pendingEscCount > 0): ?>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= ($activeTab === 'escalations') ? 'bg-white text-indigo-700' : 'bg-rose-100 text-rose-700' ?>"><?= $pendingEscCount ?></span>
            <?php endif; ?>
        </a>
        <a href="?page=admin-tl-reports&tab=progress" class="shrink-0 whitespace-nowrap px-3.5 py-2 rounded-xl text-xs font-bold transition inline-flex items-center gap-1.5 <?= ($activeTab === 'progress') ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' ?>">
            <i data-lucide="users" class="w-4 h-4"></i> Team Leads Overview (<?= count($tls) ?>)
        </a>
        <a href="?page=admin-tl-reports&tab=work_history" class="shrink-0 whitespace-nowrap px-3.5 py-2 rounded-xl text-xs font-bold transition inline-flex items-center gap-1.5 <?= ($activeTab === 'work_history') ? 'bg-indigo-600 text-white shadow-sm' : 'bg-white text-slate-700 hover:bg-slate-50 border border-slate-200' ?>">
            <i data-lucide="history" class="w-4 h-4"></i> Company Work History (<?= count($companyWorkHistory) ?>)
        </a>
    </div>

<?php if ($activeTab === 'daily_reports'): ?>
    <!-- Section: Daily TL Executive Reports -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="file-text" class="w-5 h-5 text-indigo-600"></i>
                    Daily Executive Reports Submitted by Team Leads (<?= count($dailyTlReports) ?>)
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">End-of-day sprint progress, team achievements, roadblocks, and next targets reported by Team Leads.</p>
            </div>
            <span class="self-start sm:self-auto px-2.5 py-1 rounded-full text-xs font-bold whitespace-nowrap bg-indigo-50 text-indigo-700 border border-indigo-200 shrink-0">
                <?= $pendingRepCount ?> Pending Acknowledgment
            </span>
        </div>

        <?php if (empty($dailyTlReports)): ?>
            <div class="text-center py-10 text-slate-400 bg-slate-50 rounded-xl">
                <i data-lucide="file-check-2" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                <p class="text-xs font-bold text-slate-600">No Daily TL Reports submitted yet.</p>
                <p class="text-[11px] text-slate-400 mt-0.5">When Team Leads submit their daily team executive reports, they will appear here for HR review.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($dailyTlReports as $rep): ?>
                    <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/70 space-y-3 hover:border-indigo-200 transition">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <img src="<?= $rep['tl_avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($rep['tl_name']) ?>" class="w-10 h-10 rounded-full object-cover ring-1 ring-slate-200" alt="Avatar">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h3 class="text-sm font-bold text-slate-900"><?= htmlspecialchars($rep['tl_name']) ?></h3>
                                        <span class="text-xs text-slate-500">(<?= htmlspecialchars($rep['tl_designation']) ?>)</span>
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800 font-mono">
                                            📅 <?= formatDate($rep['report_date']) ?>
                                        </span>
                                    </div>
                                    <div class="text-xs font-bold text-slate-800 mt-0.5"><?= htmlspecialchars($rep['title']) ?></div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <?php if (!empty($rep['is_auto_submitted']) || strpos($rep['title'] ?? '', '[AUTO-SUBMITTED') !== false): ?>
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase bg-purple-50 text-purple-700 border border-purple-200 inline-flex items-center gap-1">
                                        <i data-lucide="zap" class="w-3 h-3"></i> Auto-Submitted
                                    </span>
                                <?php endif; ?>
                                <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase <?= $rep['status'] === 'reviewed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
                                    <?= $rep['status'] === 'reviewed' ? '✅ Acknowledged by HR' : '⏳ Pending HR Review' ?>
                                </span>
                            </div>
                        </div>

                        <div class="p-4 bg-white rounded-xl space-y-2 text-xs border border-slate-200/80">
                            <div>
                                <strong class="text-slate-800 uppercase text-[10px] block">🏆 Team Deliverables & Completed Milestones:</strong>
                                <p class="text-slate-700 mt-0.5 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($rep['tasks_completed']) ?></p>
                            </div>
                            <?php if (!empty($rep['tasks_in_progress'])): ?>
                                <div>
                                    <strong class="text-slate-800 uppercase text-[10px] block">🔄 Ongoing Sprints:</strong>
                                    <p class="text-slate-700 mt-0.5 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($rep['tasks_in_progress']) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($rep['blockers'])): ?>
                                <div>
                                    <strong class="text-rose-700 uppercase text-[10px] block">⚠️ Blockers / Impediments needing HR attention:</strong>
                                    <p class="text-rose-600 mt-0.5 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($rep['blockers']) ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($rep['plan_for_tomorrow'])): ?>
                                <div>
                                    <strong class="text-indigo-700 uppercase text-[10px] block">🎯 Tomorrow's Target:</strong>
                                    <p class="text-slate-700 mt-0.5 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($rep['plan_for_tomorrow']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                                                <!-- Auto-Attached Daily Team Tasks Breakdown for HR Audit -->
                        <?php 
                        $attachedTasks = !empty($rep['tasks_snapshot_json']) ? json_decode($rep['tasks_snapshot_json'], true) : [];
                        ?>
                        <?php if (!empty($attachedTasks)): ?>
                            <div class="pt-2 border-t border-slate-200/80" x-data="{ showTasks: false }">
                                <button type="button" @click="showTasks = !showTasks" class="w-full flex items-center justify-between p-2.5 bg-indigo-50/70 hover:bg-indigo-50 rounded-xl border border-indigo-100 text-xs font-bold text-indigo-900 transition">
                                    <div class="flex items-center gap-2">
                                        <i data-lucide="check-square" class="w-4 h-4 text-indigo-600"></i>
                                        <span>Auto-Attached Team Tasks Breakdown (<?= count($attachedTasks) ?> Tasks Audited)</span>
                                    </div>
                                    <div class="flex items-center gap-1 text-[11px] text-indigo-600">
                                        <span x-text="showTasks ? 'Hide Tasks' : 'View Team Tasks & Employee Remarks'"></span>
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
                                                        Assigned to: <strong class="text-slate-800"><?= htmlspecialchars($t['employee_name']) ?></strong> (<?= htmlspecialchars($t['employee_designation'] ?? 'Staff') ?>)
                                                    </div>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <?= getStatusBadge($t['status']) ?>
                                                </div>
                                            </div>

                                            <!-- Dates (Original vs Extended) -->
                                            <div class="flex items-center gap-2 text-[11px] pt-1 border-t border-slate-50">
                                                <span class="text-slate-500 font-mono">Due Date: <strong><?= formatDate($t['due_date']) ?></strong></span>
                                                <?php if (!empty($t['is_extended'])): ?>
                                                    <span class="px-2 py-0.2 rounded bg-amber-50 text-amber-800 font-bold border border-amber-200 text-[10px]" title="Extended by TL: <?= htmlspecialchars($t['extension_reason'] ?? '') ?>">
                                                        ?? Extended by TL (Original Due: <?= formatDate($t['original_due_date']) ?>)
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Employee Latest Remarks & Media Attachments -->
                                            <?php if (!empty($t['latest_employee_remarks']) || !empty($t['latest_attachment_file']) || !empty($t['latest_attachment_url'])): ?>
                                                <div class="p-2.5 bg-slate-50 rounded-xl text-[11px] text-slate-700 mt-1.5 border border-slate-200/80 space-y-2">
                                                    <?php if (!empty($t['latest_employee_remarks'])): ?>
                                                        <div>
                                                            <strong class="text-slate-900 block text-[10px] uppercase font-bold tracking-wider">Employee Remark / Submission Notes:</strong>
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
                            <div class="p-3 bg-emerald-50 rounded-xl border border-emerald-200 text-xs text-emerald-900 flex items-start gap-2">
                                <i data-lucide="check-check" class="w-4 h-4 text-emerald-600 shrink-0 mt-0.5"></i>
                                <div>
                                    <strong>HR Response:</strong>
                                    <p class="mt-0.5"><?= htmlspecialchars($rep['reviewer_feedback']) ?></p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="flex items-center justify-between pt-1">
                            <span class="text-[10px] text-slate-400 font-mono">Submitted at <?= date('d M Y, h:i A', strtotime($rep['created_at'])) ?></span>
                            <button @click="openHrReview(<?= htmlspecialchars(json_encode($rep)) ?>)" class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                                <i data-lucide="message-square" class="w-3.5 h-3.5"></i>
                                <span><?= $rep['status'] === 'reviewed' ? 'Edit HR Feedback' : 'Acknowledge & Send Feedback' ?></span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal: HR Feedback for TL Daily Report -->
    <div x-show="hrReviewModal" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="hrReviewModal = false" class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i data-lucide="message-square" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Acknowledge & Respond to TL Report</h3>
                        <p class="text-xs text-slate-500" x-text="selectedRep ? 'TL: ' + selectedRep.tl_name + ' (' + selectedRep.report_date + ')' : ''"></p>
                    </div>
                </div>
                <button @click="hrReviewModal = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form action="?action=review-daily-report" method="POST" class="space-y-4">
                <input type="hidden" name="report_id" :value="selectedRep ? selectedRep.id : 0">
                <input type="hidden" name="status" value="reviewed">

                <div>
                    <label class="block text-xs font-bold text-slate-700 mb-1">HR Administration Response / Executive Remarks *</label>
                    <textarea name="reviewer_feedback" x-model="hrFeedback" rows="4" required placeholder="Acknowledge milestones, provide approvals, address blockers, or give directives..." class="w-full bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs text-slate-900"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="hrReviewModal = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">Cancel</button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-sm inline-flex items-center gap-1.5">
                        <i data-lucide="send" class="w-4 h-4"></i> Save & Send Response to TL
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if ($activeTab === 'escalations'): ?>
    <!-- Section: 🚨 Team Lead Employee Referrals & Complaints -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-100">
            <div>
                <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                    <i data-lucide="shield-alert" class="w-5 h-5 text-rose-600"></i>
                    TL Employee Referrals & Disciplinary Escalations (<?= count($allEscalations) ?>)
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Complaints and incident reports submitted by Team Leads requiring HR investigation and resolution.</p>
            </div>
            <span class="self-start sm:self-auto px-2.5 py-1 rounded-full text-xs font-bold whitespace-nowrap bg-rose-50 text-rose-700 border border-rose-200 shrink-0">
                <?= $pendingEscCount ?> Pending HR Action
            </span>
        </div>

        <?php if (empty($allEscalations)): ?>
            <div class="text-center py-8 text-slate-400 bg-slate-50 rounded-xl">
                <i data-lucide="check-circle-2" class="w-8 h-8 mx-auto text-emerald-400 mb-1.5"></i>
                <p class="text-xs font-bold text-slate-600">No active complaints or escalations from Team Leads.</p>
                <p class="text-[11px] text-slate-400">When a TL refers an employee for performance or behavioral issues, it will appear here for HR action.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($allEscalations as $esc): ?>
                    <div class="p-5 rounded-2xl border border-slate-200 bg-slate-50/60 space-y-3" x-data="{ hrActionOpen: false }">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-sm text-slate-900">Referred: <?= htmlspecialchars($esc['employee_name']) ?></span>
                                    <span class="text-xs text-slate-500">(<?= htmlspecialchars($esc['employee_designation']) ?>)</span>
                                    <span class="text-slate-300">•</span>
                                    <span class="text-xs text-slate-600 font-semibold">By TL: <strong><?= htmlspecialchars($esc['tl_name']) ?></strong></span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase <?= $esc['severity'] === 'urgent' ? 'bg-rose-600 text-white' : ($esc['severity'] === 'high' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800') ?>">
                                        <?= $esc['severity'] ?> severity
                                    </span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-indigo-50 text-indigo-700 border border-indigo-100">
                                        <?= htmlspecialchars(str_replace('_', ' ', $esc['category'])) ?>
                                    </span>
                                </div>
                                <h3 class="text-xs font-bold text-slate-800 pt-1"><?= htmlspecialchars($esc['title']) ?></h3>
                                <p class="text-xs text-slate-600 leading-relaxed"><?= nl2br(htmlspecialchars($esc['description'])) ?></p>
                                <div class="text-[10px] text-slate-400 font-mono">
                                    Submitted on <?= date('d M Y, h:i A', strtotime($esc['created_at'])) ?>
                                </div>
                            </div>

                            <div class="shrink-0 text-right space-y-2">
                                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase <?= $esc['status'] === 'resolved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($esc['status'] === 'action_taken' ? 'bg-blue-50 text-blue-700 border border-blue-200' : ($esc['status'] === 'dismissed' ? 'bg-slate-100 text-slate-700 border border-slate-300' : 'bg-rose-50 text-rose-700 border border-rose-200')) ?>">
                                    <?= str_replace('_', ' ', $esc['status']) ?>
                                </span>
                                <div>
                                    <button type="button" @click="hrActionOpen = !hrActionOpen" class="px-3 py-1.5 rounded-lg <?= $esc['status'] === 'pending' ? 'bg-indigo-600 hover:bg-indigo-700 text-white' : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200' ?> text-xs font-bold transition inline-flex items-center gap-1 shadow-2xs">
                                        <i data-lucide="edit-3" class="w-3.5 h-3.5"></i> <span x-text="hrActionOpen ? '✕ Close Form' : '<?= $esc['status'] === 'pending' ? 'Take HR Action' : 'Edit Decision' ?>'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($esc['hr_response'])): ?>
                            <div class="p-3 rounded-xl bg-indigo-50 border border-indigo-100 text-xs">
                                <div class="font-bold text-indigo-900 flex items-center gap-1.5">
                                    <i data-lucide="shield-check" class="w-4 h-4 text-indigo-600"></i> HR Resolution by <?= htmlspecialchars($esc['hr_name'] ?: 'HR Admin') ?> (<?= date('d M, h:i A', strtotime($esc['hr_action_at'])) ?>):
                                </div>
                                <p class="text-indigo-800 mt-1"><?= nl2br(htmlspecialchars($esc['hr_response'])) ?></p>
                            </div>
                        <?php endif; ?>

                        <!-- Expandable HR Action Form -->
                        <div x-show="hrActionOpen" class="pt-3 border-t border-slate-200/80 mt-2" x-cloak>
                            <form action="?action=resolve-escalation" method="POST" class="space-y-3 bg-white p-4 rounded-xl border border-slate-200">
                                <input type="hidden" name="escalation_id" value="<?= $esc['id'] ?>">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">HR Official Decision / Action *</label>
                                        <select name="status" required class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs font-semibold text-slate-800">
                                            <option value="under_review" <?= $esc['status'] === 'under_review' ? 'selected' : '' ?>>🔍 Under HR Investigation (Inquiry Ongoing)</option>
                                            <option value="action_taken" <?= $esc['status'] === 'action_taken' ? 'selected' : '' ?>>⚠️ Issue Formal Warning / Disciplinary Action</option>
                                            <option value="resolved" <?= $esc['status'] === 'resolved' ? 'selected' : '' ?>>✅ Issue Resolved (Clear & Restore Full Access)</option>
                                            <option value="dismissed" <?= $esc['status'] === 'dismissed' ? 'selected' : '' ?>>🚫 Terminate / Dismiss Employee (Revoke Login & Access)</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-slate-700 mb-1">HR Official Response / Remarks *</label>
                                        <input type="text" name="hr_response" required value="<?= htmlspecialchars($esc['hr_response'] ?? '') ?>" placeholder="e.g., Conducted 1-on-1 counseling with employee; final warning issued." class="w-full bg-slate-50 border border-slate-200 rounded-lg p-2 text-xs text-slate-800">
                                    </div>
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <button type="button" @click="hrActionOpen = false" class="px-3 py-1.5 text-xs text-slate-500 hover:text-slate-700">Cancel</button>
                                    <button type="submit" class="px-4 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-sm transition inline-flex items-center gap-1.5">
                                        <i data-lucide="check" class="w-3.5 h-3.5"></i> Save HR Action
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($activeTab === 'progress'): ?>
    <div class="space-y-6">
        <?php foreach ($tlReports as $r): ?>
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 space-y-6">
                <!-- TL Header Bar -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-100">
                    <div class="flex items-center gap-3.5">
                        <img src="<?= $r['tl']['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($r['tl']['name']) ?>" class="w-12 h-12 rounded-full object-cover ring-2 ring-indigo-500/30" alt="TL">
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-base font-bold text-slate-900"><?= htmlspecialchars($r['tl']['name']) ?></h2>
                                <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-[10px] font-bold rounded-full uppercase">Team Lead</span>
                            </div>
                            <p class="text-xs text-slate-500"><?= htmlspecialchars($r['tl']['designation']) ?> • Managing <strong><?= $r['team_count'] ?> Team Members</strong></p>
                        </div>
                    </div>

                    <!-- Metrics pill -->
                    <div class="grid grid-cols-2 sm:flex sm:items-center gap-2 text-xs font-semibold w-full sm:w-auto">
                        <span class="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg text-center whitespace-nowrap">Tasks: <?= $r['total'] ?></span>
                        <span class="px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-center whitespace-nowrap">Done: <?= $r['completed'] ?></span>
                        <span class="px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg text-center whitespace-nowrap">In Progress: <?= $r['in_progress'] ?></span>
                        <span class="px-3 py-1.5 bg-amber-50 text-amber-700 rounded-lg text-center whitespace-nowrap">Under Review: <?= $r['review'] ?></span>
                    </div>
                </div>

                <!-- 📍 Office Location Management (Permanent vs Temporary Override) -->
                <?php
                    $effLoc = getEffectiveUserLocation((int)$r['tl']['id']);
                    $currentPermLoc = getOfficeLocationById((int)($r['tl']['assigned_office_location'] ?: 2));
                    $isTempActive = !empty($effLoc['is_temporary']);
                ?>
                <div class="p-4 bg-gradient-to-r <?= $isTempActive ? 'from-amber-50 to-orange-50/60 border-amber-200' : 'from-indigo-50/70 to-blue-50/40 border-indigo-100' ?> rounded-2xl border space-y-3.5" x-data="{ 
                    openLocationForm: false, 
                    assignmentType: '<?= $isTempActive ? 'temporary' : 'permanent' ?>',
                    tempDays: <?= (int)($r['tl']['temp_location_days'] ?: 1) ?>
                }">
                    <!-- Current Status Display Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
                        <div class="flex items-start sm:items-center gap-2.5 text-xs">
                            <div class="w-8 h-8 rounded-xl <?= $isTempActive ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700' ?> flex items-center justify-center font-bold shrink-0">
                                <i data-lucide="map-pin" class="w-4 h-4"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-slate-800">Effective Reporting Location:</span>
                                    <span class="font-extrabold text-slate-900 px-2 py-0.5 rounded-lg <?= $isTempActive ? 'bg-amber-100 text-amber-900 border border-amber-200' : 'bg-white text-indigo-900 border border-indigo-200' ?>">
                                        <?= htmlspecialchars($effLoc['name']) ?>
                                    </span>
                                    <?php if ($isTempActive): ?>
                                        <span class="inline-flex items-center gap-1 text-[10px] font-bold bg-orange-100 text-orange-800 px-2 py-0.5 rounded-full border border-orange-200">
                                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                                            Temporary (<?= $effLoc['days_left'] ?> Day<?= $effLoc['days_left'] > 1 ? 's' : '' ?> Left till <?= formatDate($effLoc['expires_at']) ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] font-bold bg-emerald-50 text-emerald-700 px-2 py-0.5 rounded-full border border-emerald-200">Permanent</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-[11px] text-slate-500 mt-0.5">
                                    <?= htmlspecialchars($r['tl']['name']) ?>, TL Support, and all <?= (int)($r['team_count'] ?? 0) ?> team members punch in from this location.
                                    <?php if ($isTempActive): ?>
                                        <span class="text-slate-400 font-medium">(Permanent: <?= htmlspecialchars($currentPermLoc['name'] ?? 'HQ') ?>)</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0 self-start sm:self-auto">
                            <?php if ($isTempActive): ?>
                                <form action="?action=clear-temp-tl-location" method="POST" onsubmit="return confirm('Revert <?= htmlspecialchars(addslashes($r['tl']['name'])) ?> and team back to permanent office location?');">
                                    <input type="hidden" name="tl_id" value="<?= $r['tl']['id'] ?>">
                                    <button type="submit" class="px-2.5 py-1.5 bg-white hover:bg-slate-50 text-rose-600 border border-rose-200 rounded-xl text-xs font-bold transition shadow-2xs">
                                        Revert to Permanent
                                    </button>
                                </form>
                            <?php endif; ?>
                            <button type="button" @click="openLocationForm = !openLocationForm" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition inline-flex items-center gap-1.5 shadow-sm">
                                <i data-lucide="edit-2" class="w-3 h-3"></i>
                                <span x-text="openLocationForm ? 'Close' : 'Change Location'"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Expandable Change Location Form -->
                    <div x-show="openLocationForm" x-transition class="pt-3 border-t border-slate-200/80 mt-3" x-cloak>
                        <form action="?action=assign-tl-location" method="POST" class="space-y-3 bg-white p-4 rounded-xl border border-slate-200">
                            <input type="hidden" name="tl_id" value="<?= $r['tl']['id'] ?>">

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Target Office Location</label>
                                    <select name="location_id" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
                                        <?php foreach ($officeLocations as $loc): ?>
                                            <option value="<?= $loc['id'] ?>" <?= $effLoc['id'] === $loc['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($loc['name']) ?> (Radius: <?= $loc['radius'] ?>m)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Assignment Type</label>
                                    <div class="grid grid-cols-2 gap-2">
                                        <label class="flex items-center justify-center gap-1.5 p-2 rounded-xl border text-xs font-bold cursor-pointer transition" :class="assignmentType === 'permanent' ? 'bg-indigo-50 border-indigo-400 text-indigo-700' : 'bg-slate-50 border-slate-200 text-slate-600'">
                                            <input type="radio" name="assignment_type" value="permanent" x-model="assignmentType" class="hidden">
                                            <span>Permanent</span>
                                        </label>
                                        <label class="flex items-center justify-center gap-1.5 p-2 rounded-xl border text-xs font-bold cursor-pointer transition" :class="assignmentType === 'temporary' ? 'bg-amber-50 border-amber-400 text-amber-800' : 'bg-slate-50 border-slate-200 text-slate-600'">
                                            <input type="radio" name="assignment_type" value="temporary" x-model="assignmentType" class="hidden">
                                            <span>Temporary (Max 4 Days)</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Temporary Duration Selector (1, 2, 3, 4 Days Max) -->
                            <div x-show="assignmentType === 'temporary'" class="p-3 bg-amber-50/80 border border-amber-200 rounded-xl space-y-2">
                                <label class="block text-[11px] font-bold text-amber-950 uppercase">
                                    Select Duration (Maximum 4 Days Limit)
                                </label>
                                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
                                    <label class="flex items-center justify-center p-2 rounded-lg border text-xs font-bold cursor-pointer transition" :class="tempDays == 1 ? 'bg-amber-500 text-white border-amber-600 shadow-sm' : 'bg-white border-amber-200 text-amber-900'">
                                        <input type="radio" name="temp_days" value="1" x-model="tempDays" class="hidden">
                                        <span>1 Day</span>
                                    </label>
                                    <label class="flex items-center justify-center p-2 rounded-lg border text-xs font-bold cursor-pointer transition" :class="tempDays == 2 ? 'bg-amber-500 text-white border-amber-600 shadow-sm' : 'bg-white border-amber-200 text-amber-900'">
                                        <input type="radio" name="temp_days" value="2" x-model="tempDays" class="hidden">
                                        <span>2 Days</span>
                                    </label>
                                    <label class="flex items-center justify-center p-2 rounded-lg border text-xs font-bold cursor-pointer transition" :class="tempDays == 3 ? 'bg-amber-500 text-white border-amber-600 shadow-sm' : 'bg-white border-amber-200 text-amber-900'">
                                        <input type="radio" name="temp_days" value="3" x-model="tempDays" class="hidden">
                                        <span>3 Days</span>
                                    </label>
                                    <label class="flex items-center justify-center p-2 rounded-lg border text-xs font-bold cursor-pointer transition" :class="tempDays == 4 ? 'bg-amber-500 text-white border-amber-600 shadow-sm' : 'bg-white border-amber-200 text-amber-900'">
                                        <input type="radio" name="temp_days" value="4" x-model="tempDays" class="hidden">
                                        <span>4 Days (Max)</span>
                                    </label>
                                </div>
                                <p class="text-[10px] text-amber-800">After this period expires, <?= htmlspecialchars($r['tl']['name']) ?> and the team will automatically revert back to their permanent location.</p>
                            </div>

                            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                                <button type="button" @click="openLocationForm = false" class="px-3 py-1.5 text-xs font-semibold text-slate-500">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                                    Save & Apply to Team
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- 2 Columns: Left = Team Tasks, Right = HR Feedback & Comments -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Left: Recent Team Deliverables -->
                    <div>
                        <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                            <i data-lucide="check-square" class="w-4 h-4 text-indigo-600"></i> Recent Team Tasks Managed by TL
                        </h3>
                        <?php if (empty($r['recent_tasks'])): ?>
                            <p class="text-xs text-slate-400 py-4">No active tasks created by this TL yet.</p>
                        <?php else: ?>
                            <div class="space-y-2.5">
                                <?php foreach ($r['recent_tasks'] as $t): ?>
                                    <div class="p-3 rounded-xl border border-slate-100 bg-slate-50/70 flex items-start justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                             <div class="font-bold text-xs text-slate-900 truncate"><?= htmlspecialchars($t['title']) ?></div>
                                            <div class="text-[11px] text-slate-500 mt-0.5">Assigned to: <strong class="text-slate-700"><?= htmlspecialchars($t['assigned_name']) ?></strong> • Due: <?= formatDate($t['due_date']) ?></div>
                                        </div>
                                        <div class="shrink-0"><?= getStatusBadge($t['status']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Right: HR Feedback Feed & Post Form -->
                    <div class="bg-indigo-50/30 p-4 rounded-2xl border border-indigo-100 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-bold text-indigo-950 uppercase tracking-wider flex items-center gap-1.5">
                                <i data-lucide="message-square" class="w-4 h-4 text-indigo-600"></i> HR Feedback & Directives to TL
                            </h3>
                            <span class="text-[10px] text-indigo-600 font-semibold bg-indigo-100/70 px-2 py-0.5 rounded-full"><?= count($r['feedbacks']) ?> notes</span>
                        </div>

                        <!-- Post Feedback Form -->
                        <form action="?action=post-tl-feedback" method="POST" class="space-y-2.5">
                            <input type="hidden" name="tl_id" value="<?= $r['tl']['id'] ?>">
                            <textarea name="message" rows="2" required placeholder="Write official feedback, guidance, or sprint instruction for <?= htmlspecialchars($r['tl']['name']) ?>..." class="w-full bg-white border border-indigo-200 rounded-xl p-2.5 text-xs text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:outline-none shadow-sm"></textarea>
                            
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5">
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] font-bold text-slate-500 shrink-0">Priority:</span>
                                    <select name="priority" class="flex-1 sm:flex-none bg-white border border-indigo-200 rounded-lg px-2.5 py-1.5 text-xs text-slate-700 focus:ring-2 focus:ring-indigo-500">
                                        <option value="normal">Normal Note</option>
                                        <option value="important" selected>Important Directive</option>
                                        <option value="urgent">Urgent Action</option>
                                    </select>
                                </div>
                                <button type="submit" class="w-full sm:w-auto justify-center inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl shadow-sm transition shrink-0">
                                    <i data-lucide="send" class="w-3.5 h-3.5"></i> Send Feedback
                                </button>
                            </div>
                        </form>

                        <!-- Past Feedbacks List (Scrollable, Full History Preserved) -->
                        <?php if (!empty($r['feedbacks'])): ?>
                            <div class="space-y-2 pt-2 border-t border-indigo-100/80 max-h-72 overflow-y-auto pr-1">
                                <?php foreach ($r['feedbacks'] as $fb): ?>
                                    <div class="p-2.5 bg-white rounded-xl border border-slate-200/80 text-xs shadow-sm">
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="font-bold text-slate-800 flex items-center gap-1">
                                                <i data-lucide="user-check" class="w-3 h-3 text-indigo-600"></i> <?= htmlspecialchars($fb['hr_name']) ?>
                                            </span>
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded uppercase <?= $fb['priority'] === 'urgent' ? 'bg-rose-100 text-rose-700' : ($fb['priority'] === 'important' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') ?>"><?= $fb['priority'] ?></span>
                                                <span class="text-[10px] text-slate-400 font-mono"><?= date('d M, h:i A', strtotime($fb['created_at'])) ?></span>
                                            </div>
                                        </div>
                                        <p class="text-slate-600 font-medium text-[11px] leading-relaxed"><?= nl2br(htmlspecialchars($fb['message'])) ?></p>
                                        <div class="mt-1 flex items-center justify-end">
                                            <?php if ($fb['status'] === 'acknowledged'): ?>
                                                <span class="text-[10px] text-emerald-600 font-semibold flex items-center gap-1">
                                                    <i data-lucide="check" class="w-3 h-3"></i> Acknowledged by TL
                                                </span>
                                            <?php else: ?>
                                                <span class="text-[10px] text-amber-600 font-medium flex items-center gap-1">
                                                    <i data-lucide="clock" class="w-3 h-3"></i> Delivered to TL Dashboard
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($activeTab === 'work_history'): ?>
    <!-- Section: Company-Wide Work & Deliverables History (Audit Register) -->
    <div class="space-y-4">
        <!-- Month & Filter Controls Toolbar -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                    <i data-lucide="archive" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Company Work Deliverables Register: <?= $monthTitle ?></h3>
                    <p class="text-[11px] text-slate-500">Audit all employee submissions, deliverables, photo/video proofs, and TL feedback across the organization.</p>
                </div>
            </div>

            <!-- Month Navigation Controller -->
            <div class="flex items-center gap-2 flex-wrap">
                <a href="?page=admin-tl-reports&tab=work_history&month=<?= $prevMonth ?><?= $filterEmp ? "&emp_id=$filterEmp" : '' ?><?= $filterTl ? "&tl_id=$filterTl" : '' ?>" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition font-bold" title="Previous Month">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                </a>

                <form method="GET" action="" class="m-0 flex items-center gap-2">
                    <input type="hidden" name="page" value="admin-tl-reports">
                    <input type="hidden" name="tab" value="work_history">
                    <?php if ($filterEmp): ?><input type="hidden" name="emp_id" value="<?= $filterEmp ?>"><?php endif; ?>
                    <?php if ($filterTl): ?><input type="hidden" name="tl_id" value="<?= $filterTl ?>"><?php endif; ?>
                    <input type="month" name="month" value="<?= $selectedMonth ?>" onchange="this.form.submit()" class="bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
                </form>

                <a href="?page=admin-tl-reports&tab=work_history&month=<?= $nextMonth ?><?= $filterEmp ? "&emp_id=$filterEmp" : '' ?><?= $filterTl ? "&tl_id=$filterTl" : '' ?>" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition font-bold" title="Next Month">
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </a>

                <?php if (!$isCurrentMonth): ?>
                    <a href="?page=admin-tl-reports&tab=work_history&month=<?= date('Y-m') ?><?= $filterEmp ? "&emp_id=$filterEmp" : '' ?><?= $filterTl ? "&tl_id=$filterTl" : '' ?>" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-bold rounded-lg transition">
                        Current Month
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Toolbar for Employee & TL Selection -->
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
            <form method="GET" class="flex-1 w-full flex flex-col sm:flex-row items-center gap-2.5">
                <input type="hidden" name="page" value="admin-tl-reports">
                <input type="hidden" name="tab" value="work_history">
                <input type="hidden" name="month" value="<?= htmlspecialchars($selectedMonth) ?>">

                <!-- Search input -->
                <div class="relative flex-1 w-full">
                    <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search task, project, notes..." class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <!-- Employee Filter -->
                <div class="w-full sm:w-48">
                    <select name="emp_id" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">All Employees</option>
                        <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $filterEmp === (int)$emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['name']) ?> (<?= htmlspecialchars($emp['emp_id']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- TL Filter -->
                <div class="w-full sm:w-44">
                    <select name="tl_id" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">All Team Leads</option>
                        <?php foreach ($tls as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $filterTl === (int)$t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($filterEmp || $filterTl || !empty($searchQuery)): ?>
                    <a href="?page=admin-tl-reports&tab=work_history&month=<?= $selectedMonth ?>" class="text-xs text-rose-600 hover:text-rose-700 font-semibold px-2 py-1">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Date-Grouped Company Work Register -->
        <?php if (empty($companyWorkHistory)): ?>
            <div class="bg-white p-12 rounded-3xl border border-slate-200 shadow-sm text-center">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="folder-clock" class="w-6 h-6"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-800">No Deliverables Recorded For <?= $monthTitle ?></h3>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Adjust the month or filters above to audit other timeframes.</p>
            </div>
        <?php else: 
            // Group by date (YYYY-MM-DD)
            $compHistoryByDate = [];
            foreach ($companyWorkHistory as $wh) {
                $d = date('Y-m-d', strtotime($wh['submitted_at']));
                $compHistoryByDate[$d][] = $wh;
            }
        ?>
            <div class="space-y-4">
                <?php foreach ($compHistoryByDate as $dateKey => $subList): 
                    $dateFormatted = date('d F Y', strtotime($dateKey));
                    $dayName = date('l', strtotime($dateKey));
                    $isToday = ($dateKey === date('Y-m-d'));
                    $apprCount = count(array_filter($subList, fn($s) => $s['review_status'] === 'approved'));
                    $reqCount = count(array_filter($subList, fn($s) => $s['review_status'] === 'changes_requested'));
                ?>
                    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" x-data="{ expanded: true }">
                        <!-- Date Header Banner -->
                        <div @click="expanded = !expanded" class="flex items-center justify-between px-4 py-3 bg-slate-50/90 border-b border-slate-200 cursor-pointer hover:bg-slate-100 transition select-none">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-lg <?= $isToday ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 border border-slate-200' ?> flex items-center justify-center font-bold text-xs shadow-sm">
                                    <i data-lucide="calendar" class="w-4 h-4"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-slate-900 text-sm"><?= $dateFormatted ?></span>
                                        <span class="text-xs font-semibold text-slate-500">(<?= $dayName ?>)</span>
                                        <?php if ($isToday): ?>
                                            <span class="px-2 py-0.2 rounded-full text-[10px] font-extrabold uppercase bg-indigo-100 text-indigo-700">Today</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <div class="hidden sm:flex items-center gap-2 text-[11px] font-semibold">
                                    <span class="px-2.5 py-0.5 rounded-full bg-white text-slate-700 border border-slate-200 font-bold"><?= count($subList) ?> Deliverables</span>
                                    <?php if ($apprCount > 0): ?>
                                        <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold">✓ <?= $apprCount ?> Approved</span>
                                    <?php endif; ?>
                                    <?php if ($reqCount > 0): ?>
                                        <span class="px-2.5 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200 font-bold">⚠️ <?= $reqCount ?> Changes</span>
                                    <?php endif; ?>
                                </div>
                                <i data-lucide="chevron-down" class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': expanded }"></i>
                            </div>
                        </div>

                        <!-- Tasks Table for this Date -->
                        <div x-show="expanded" class="w-full overflow-x-auto no-scrollbar">
                            <table class="w-full text-left text-xs text-slate-600 border-collapse min-w-[850px]">
                                <thead class="bg-slate-50/50 text-slate-400 text-[10px] uppercase tracking-wider font-bold border-b border-slate-100">
                                    <tr>
                                        <th class="py-2.5 pl-4 pr-3 whitespace-nowrap min-w-[100px]">Time</th>
                                        <th class="py-2.5 px-3 min-w-[140px]">Employee</th>
                                        <th class="py-2.5 px-3 min-w-[130px]">Team Lead</th>
                                        <th class="py-2.5 px-3 min-w-[180px]">Project & Task</th>
                                        <th class="py-2.5 px-3 min-w-[180px]">Employee Notes</th>
                                        <th class="py-2.5 px-3 min-w-[140px]">Deliverable Proof</th>
                                        <th class="py-2.5 px-3 text-center min-w-[100px] whitespace-nowrap">Status</th>
                                        <th class="py-2.5 pl-3 pr-4 min-w-[150px]">TL Feedback</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-xs">
                                    <?php foreach ($subList as $wh): ?>
                                        <tr class="hover:bg-slate-50/80 transition">
                                            <!-- Time -->
                                            <td class="py-3 pl-4 pr-3 align-top whitespace-nowrap font-mono text-[11px] text-slate-500">
                                                <span class="inline-flex items-center gap-1 font-bold text-slate-700">
                                                    <i data-lucide="clock" class="w-3 h-3 text-slate-400"></i>
                                                    <?= date('h:i A', strtotime($wh['submitted_at'])) ?>
                                                </span>
                                            </td>

                                            <!-- Employee -->
                                            <td class="py-3 px-3 align-top whitespace-nowrap">
                                                <div class="flex items-center gap-2">
                                                    <img src="<?= $wh['employee_avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($wh['employee_name']) ?>" class="w-7 h-7 rounded-full object-cover ring-1 ring-slate-200" alt="Avatar">
                                                    <div>
                                                        <div class="font-bold text-slate-900 text-xs"><?= htmlspecialchars($wh['employee_name']) ?></div>
                                                        <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($wh['employee_code']) ?></div>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Team Lead -->
                                            <td class="py-3 px-3 align-top whitespace-nowrap">
                                                <span class="font-bold text-slate-700 text-xs"><?= htmlspecialchars($wh['tl_name']) ?></span>
                                            </td>

                                            <!-- Project & Task -->
                                            <td class="py-3 px-3 align-top">
                                                <div class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider">
                                                    <?= htmlspecialchars($wh['project_title']) ?>
                                                </div>
                                                <div class="font-bold text-slate-900 text-xs mt-0.5">
                                                    <?= htmlspecialchars($wh['task_title']) ?>
                                                </div>
                                            </td>

                                            <!-- Employee Notes -->
                                            <td class="py-3 px-3 align-top max-w-xs">
                                                <p class="text-slate-700 leading-tight text-xs" title="<?= htmlspecialchars($wh['notes']) ?>">
                                                    <?= htmlspecialchars($wh['notes'] ?: '—') ?>
                                                </p>
                                            </td>

                                            <!-- Deliverable Proof -->
                                            <td class="py-3 px-3 align-top whitespace-nowrap">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <?php if (!empty($wh['attachment_file'])): ?>
                                                        <a href="<?= htmlspecialchars($wh['attachment_file']) ?>" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-md text-[11px] font-bold border border-indigo-200">
                                                            <i data-lucide="eye" class="w-3 h-3"></i> View Proof
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($wh['attachment_url'])): ?>
                                                        <a href="<?= htmlspecialchars($wh['attachment_url']) ?>" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-md text-[11px] font-semibold border border-slate-200">
                                                            <i data-lucide="external-link" class="w-3 h-3"></i> URL
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="?page=tech-drive" class="text-slate-400 hover:text-indigo-600 p-1" title="Open in Tech Cloud Drive">
                                                        <i data-lucide="hard-drive" class="w-3.5 h-3.5"></i>
                                                    </a>
                                                </div>
                                            </td>

                                            <!-- Status -->
                                            <td class="py-3 px-3 align-top text-center whitespace-nowrap space-y-1">
                                                <div>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase <?= $wh['review_status'] === 'approved' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : ($wh['review_status'] === 'changes_requested' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-amber-50 text-amber-700 border border-amber-200') ?>">
                                                        <?= $wh['review_status'] === 'approved' ? '✓ Approved' : ($wh['review_status'] === 'changes_requested' ? '⚠️ Changes' : '⏳ Review') ?>
                                                    </span>
                                                </div>
                                                <?php if (!empty($wh['is_auto_submitted']) || strpos($wh['notes'] ?? '', '[AUTO-SUBMIT') !== false): ?>
                                                    <div>
                                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 border border-purple-200 text-[9px] font-extrabold uppercase" title="Auto-submitted upon shift end / logout">
                                                            <i data-lucide="zap" class="w-2.5 h-2.5"></i> Auto-Submitted
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </td>

                                            <!-- TL Feedback -->
                                            <td class="py-3 pl-3 pr-4 align-top max-w-xs">
                                                <?php if (!empty($wh['tl_feedback'])): ?>
                                                    <div class="text-[11px] text-amber-900 bg-amber-50/80 p-1.5 rounded-lg border border-amber-200">
                                                        <span><?= htmlspecialchars($wh['tl_feedback']) ?></span>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-slate-300 text-[11px]">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>
