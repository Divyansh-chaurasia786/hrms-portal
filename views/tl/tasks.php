<!-- views/tl/tasks.php -->
<?php
$user = authUser();
$db = getDBConnection();

// Fetch team report IDs (Supports TL and TL Support)
$teamIds = getManagedTeamUserIds($user['id']);
$inClause = !empty($teamIds) ? implode(',', array_map('intval', $teamIds)) : '0';
$teamMembers = $db->query("SELECT id, name, avatar, designation FROM users WHERE id IN ($inClause) AND status = 'active' ORDER BY name ASC")->fetchAll() ?: [];

// Projects
$projects = $db->query("SELECT * FROM projects ORDER BY title ASC")->fetchAll();

// Filters
$filterMember = (int)($_GET['member'] ?? 0);
$filterPriority = $_GET['priority'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');
$viewMode = $_GET['view'] ?? 'list'; // 'list' or 'kanban'

// Query tasks
$sql = "
    SELECT t.*, p.title as project_title, u.name as assigned_name, u.avatar as assigned_avatar, u.emp_id,
           ts.id as submission_id, ts.notes as submission_notes, ts.attachment_url, ts.attachment_file, ts.attachment_type, ts.submitted_at, ts.review_status, ts.tl_feedback
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON t.assigned_to = u.id
    LEFT JOIN (
        SELECT ts1.* FROM task_submissions ts1
        INNER JOIN (
            SELECT MAX(id) as max_id FROM task_submissions GROUP BY task_id
        ) ts2 ON ts1.id = ts2.max_id
    ) ts ON ts.task_id = t.id
    WHERE (t.created_by = :tl_id OR t.assigned_to IN ($inClause))
";

if ($filterMember > 0) $sql .= " AND t.assigned_to = :member_id";
if (!empty($filterPriority)) $sql .= " AND t.priority = :priority_val";
if (!empty($searchQuery)) $sql .= " AND (t.title LIKE :search OR t.description LIKE :search OR p.title LIKE :search)";

$sql .= " ORDER BY CASE t.status WHEN 'review' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'todo' THEN 3 ELSE 4 END, t.due_date ASC";

$stmt = $db->prepare($sql);
$params = [':tl_id' => $user['id']];
if ($filterMember > 0) $params[':member_id'] = $filterMember;
if (!empty($filterPriority)) $params[':priority_val'] = $filterPriority;
if (!empty($searchQuery)) $params[':search'] = "%{$searchQuery}%";
$stmt->execute($params);
$allTasks = $stmt->fetchAll();

// Month filter for Team Work History (defaults to current month YYYY-MM)
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

// Fetch Team Historical Work Submissions for selected month
$historySql = "
    SELECT ts.*, t.title as task_title, t.description as task_description, t.status as task_status,
           t.due_date, p.title as project_title, u.name as employee_name, u.emp_id, u.avatar as employee_avatar
    FROM task_submissions ts
    JOIN tasks t ON ts.task_id = t.id
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON ts.submitted_by = u.id
    WHERE (t.created_by = :tl_id OR ts.submitted_by IN ($inClause))
      AND ts.submitted_at >= :start_date AND ts.submitted_at <= :end_date
";
if ($filterMember > 0) $historySql .= " AND ts.submitted_by = :member_id";
if (!empty($searchQuery)) $historySql .= " AND (t.title LIKE :search OR u.name LIKE :search OR ts.notes LIKE :search)";
$historySql .= " ORDER BY ts.submitted_at DESC";

$historyStmt = $db->prepare($historySql);
$histParams = [
    ':tl_id' => $user['id'],
    ':start_date' => $monthStartDate,
    ':end_date' => $monthEndDate
];
if ($filterMember > 0) $histParams[':member_id'] = $filterMember;
if (!empty($searchQuery)) $histParams[':search'] = "%{$searchQuery}%";
$historyStmt->execute($histParams);
$teamWorkHistory = $historyStmt->fetchAll();

// Metrics
$totalTasks = count($allTasks);
$todoCount = 0;
$inProgressCount = 0;
$reviewCount = 0;
$completedCount = 0;
$overdueCount = 0;

$todayStr = date('Y-m-d');
$tasksByStatus = ['todo' => [], 'in_progress' => [], 'review' => [], 'completed' => []];
foreach ($allTasks as $t) {
    $tasksByStatus[$t['status']][] = $t;
    if ($t['status'] === 'todo') $todoCount++;
    if ($t['status'] === 'in_progress') $inProgressCount++;
    if ($t['status'] === 'review') $reviewCount++;
    if ($t['status'] === 'completed') $completedCount++;
    if ($t['status'] !== 'completed' && !empty($t['due_date']) && $t['due_date'] < $todayStr) {
        $overdueCount++;
    }
}
?>

<div class="space-y-6" x-data="{ newTaskModalOpen: false, reviewModalOpen: false, extendModalOpen: false, activeSubmission: null, activeTask: null }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="check-square" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Task Allocation & Review Board</h1>
                <p class="text-xs text-slate-500 mt-0.5">Assign deliverables, track SLAs & deadlines, and review submitted deliverables.</p>
            </div>
        </div>

        <button @click="newTaskModalOpen = true" class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
            <i data-lucide="plus-circle" class="w-4 h-4"></i> Create & Assign Task
        </button>
    </div>

    <!-- 4 Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                <i data-lucide="list-todo" class="w-4 h-4"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">To Do</span>
                <span class="text-lg font-extrabold text-slate-900 leading-none"><?= $todoCount ?></span>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <i data-lucide="loader" class="w-4 h-4"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">In Progress</span>
                <span class="text-lg font-extrabold text-blue-600 leading-none"><?= $inProgressCount ?></span>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-xl border <?= $overdueCount > 0 ? 'border-rose-300 bg-rose-50/40' : 'border-slate-200' ?> shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg <?= $overdueCount > 0 ? 'bg-rose-100 text-rose-700' : 'bg-amber-50 text-amber-600' ?> flex items-center justify-center shrink-0">
                <i data-lucide="<?= $overdueCount > 0 ? 'alert-triangle' : 'eye' ?>" class="w-4 h-4"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold <?= $overdueCount > 0 ? 'text-rose-600' : 'text-slate-400' ?> uppercase tracking-wider block">
                    <?= $overdueCount > 0 ? 'Overdue Tasks' : 'Needs TL Review' ?>
                </span>
                <span class="text-lg font-extrabold <?= $overdueCount > 0 ? 'text-rose-700' : 'text-amber-600' ?> leading-none">
                    <?= $overdueCount > 0 ? $overdueCount : $reviewCount ?>
                </span>
            </div>
        </div>

        <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <i data-lucide="check-circle" class="w-4 h-4"></i>
            </div>
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Completed</span>
                <span class="text-lg font-extrabold text-emerald-600 leading-none"><?= $completedCount ?></span>
            </div>
        </div>
    </div>

    <!-- Red Alert Overdue Banner -->
    <?php if ($overdueCount > 0): ?>
        <div class="p-3.5 bg-rose-50 rounded-xl border border-rose-200 text-xs text-rose-900 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2.5">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0 animate-bounce"></i>
                <span><strong>🚨 SLA Warning: <?= $overdueCount ?> deliverable(s)</strong> have passed their due date and require immediate attention or deadline extension!</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Review Alert Banner if tasks are waiting for TL -->
    <?php if ($reviewCount > 0): ?>
        <div class="p-3.5 bg-amber-50 rounded-xl border border-amber-200 text-xs text-amber-900 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2.5">
                <i data-lucide="alert-circle" class="w-4 h-4 text-amber-600 shrink-0"></i>
                <span><strong><?= $reviewCount ?> deliverable(s)</strong> submitted by team members are waiting for your review & approval.</span>
            </div>
            <a href="?page=tl-tasks&view=list" class="text-xs font-bold text-amber-800 hover:text-amber-900 underline">Review Now &rarr;</a>
        </div>
    <?php endif; ?>

    <!-- Control Toolbar (Search, Filter, View Toggle) -->
    <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
        <form method="GET" class="flex-1 w-full flex flex-col sm:flex-row items-center gap-2.5">
            <input type="hidden" name="page" value="tl-tasks">
            <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">

            <!-- Search input -->
            <div class="relative flex-1 w-full">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search tasks, deliverables, projects..." class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <!-- Member filter -->
            <div class="w-full sm:w-44">
                <select name="member" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="0">All Team Members</option>
                    <?php foreach ($teamMembers as $m): ?>
                        <option value="<?= $m['id'] ?>" <?= $filterMember === (int)$m['id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Priority filter -->
            <div class="w-full sm:w-36">
                <select name="priority" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                    <option value="">All Priorities</option>
                    <option value="urgent" <?= $filterPriority === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                    <option value="high" <?= $filterPriority === 'high' ? 'selected' : '' ?>>High</option>
                    <option value="medium" <?= $filterPriority === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="low" <?= $filterPriority === 'low' ? 'selected' : '' ?>>Low</option>
                </select>
            </div>

            <?php if (!empty($searchQuery) || $filterMember > 0 || !empty($filterPriority)): ?>
                <a href="?page=tl-tasks&view=<?= htmlspecialchars($viewMode) ?>" class="text-xs text-rose-600 hover:text-rose-700 font-semibold px-2 py-1">Clear</a>
            <?php endif; ?>
        </form>

        <!-- View Switcher Tabs -->
        <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200 shrink-0">
            <a href="?page=tl-tasks&view=list<?= $filterMember ? "&member=$filterMember" : '' ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $viewMode === 'list' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>">
                <i data-lucide="list" class="w-3.5 h-3.5"></i> List View
            </a>
            <a href="?page=tl-tasks&view=kanban<?= $filterMember ? "&member=$filterMember" : '' ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $viewMode === 'kanban' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>">
                <i data-lucide="kanban" class="w-3.5 h-3.5"></i> Board View
            </a>
            <a href="?page=tl-tasks&view=history<?= $filterMember ? "&member=$filterMember" : '' ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $viewMode === 'history' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>">
                <i data-lucide="history" class="w-3.5 h-3.5"></i> Work History (<?= count($teamWorkHistory) ?>)
            </a>
        </div>
    </div>

    <!-- VIEW 1: CLEAN ENTERPRISE LIST / TABLE VIEW (Default) -->
    <?php if ($viewMode === 'list'): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden w-full">
            <div class="overflow-x-auto no-scrollbar">
                <table class="w-full text-left text-xs text-slate-600 border-collapse min-w-[780px]">
                    <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                        <tr>
                            <th class="min-w-[220px] py-3.5 pl-5 pr-3">Task & Project</th>
                            <th class="min-w-[160px] py-3.5 px-3">Assignee</th>
                            <th class="min-w-[140px] py-3.5 px-2 whitespace-nowrap">Priority & Due</th>
                            <th class="min-w-[110px] py-3.5 px-2 text-center whitespace-nowrap">Status</th>
                            <th class="min-w-[130px] py-3.5 pl-2 pr-5 text-right whitespace-nowrap">TL Action</th>
                        </tr>
                    </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($allTasks)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-10 text-slate-400">
                                <i data-lucide="check-square" class="w-8 h-8 mx-auto text-slate-300 mb-1"></i>
                                <p class="text-xs font-semibold text-slate-600">No tasks found matching your filters</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($allTasks as $t): ?>
                        <tr class="hover:bg-slate-50/80 transition <?= $t['status'] === 'review' ? 'bg-amber-50/30' : '' ?>">
                            <!-- Task & Project -->
                            <td class="py-3.5 pl-5 pr-3 align-middle">
                                <div class="space-y-0.5 overflow-hidden">
                                    <div class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider truncate">
                                        <?= htmlspecialchars($t['project_title']) ?>
                                    </div>
                                    <div class="font-bold text-slate-900 text-xs truncate">
                                        <?= htmlspecialchars($t['title']) ?>
                                    </div>
                                    <?php if ($t['description']): ?>
                                        <div class="text-[11px] text-slate-400 truncate">
                                            <?= htmlspecialchars($t['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Assignee -->
                            <td class="py-3.5 px-3 align-middle">
                                <div class="flex items-center gap-2 overflow-hidden">
                                    <img src="<?= $t['assigned_avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($t['assigned_name']) ?>" class="w-7 h-7 rounded-full object-cover ring-1 ring-slate-200 shrink-0" alt="Avatar">
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-800 text-xs truncate"><?= htmlspecialchars($t['assigned_name']) ?></div>
                                        <div class="text-[10px] text-slate-400 font-mono truncate"><?= htmlspecialchars($t['emp_id']) ?></div>
                                    </div>
                                </div>
                            </td>

                            <!-- Priority & Due Date -->
                            <td class="py-3.5 px-2 align-middle">
                                <?php $dl = getDeadlineStatus($t['due_date'], $t['status']); ?>
                                <div class="space-y-1">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <?= getPriorityBadge($t['priority']) ?>
                                        <?php if ($dl['is_overdue']): ?>
                                            <?= $dl['badge'] ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono flex items-center justify-between">
                                        <span>Due: <?= formatDate($t['due_date']) ?></span>
                                        <?php if ($t['status'] !== 'completed'): ?>
                                            <button type="button" @click="activeTask = <?= htmlspecialchars(json_encode($t)) ?>; extendModalOpen = true" class="text-indigo-600 hover:text-indigo-800 text-[10px] font-bold underline" title="Extend Deadline">+Extend</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="py-3.5 px-2 align-middle text-center">
                                <?= getStatusBadge($t['status']) ?>
                            </td>

                            <!-- TL Actions -->
                            <td class="py-3.5 pl-2 pr-5 align-middle text-right whitespace-nowrap">
                                <?php if ($t['status'] === 'review'): ?>
                                    <button type="button" @click="activeSubmission = <?= htmlspecialchars(json_encode($t)) ?>; reviewModalOpen = true" class="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold rounded-lg shadow-sm transition animate-pulse">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> Review Work
                                    </button>
                                <?php elseif ($t['status'] === 'completed'): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600">
                                        <i data-lucide="check-check" class="w-3.5 h-3.5"></i> Verified
                                    </span>
                                <?php else: ?>
                                    <span class="text-[11px] text-slate-400">In Progress</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

    <!-- VIEW 2: REDESIGNED CLEAN KANBAN BOARD -->
    <?php elseif ($viewMode === 'kanban'): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <!-- TO DO -->
            <div class="bg-slate-100/70 p-3.5 rounded-2xl border border-slate-200/80 space-y-3 flex flex-col justify-between min-h-[400px]">
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200 mb-3">
                        <span class="text-xs font-bold text-slate-700 uppercase flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-slate-400"></span> To Do
                        </span>
                        <span class="px-2 py-0.2 bg-white rounded-full text-xs font-bold text-slate-600 shadow-sm"><?= count($tasksByStatus['todo']) ?></span>
                    </div>

                    <div class="space-y-2.5">
                        <?php foreach ($tasksByStatus['todo'] as $t): ?>
                            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm space-y-2">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-[10px] font-bold text-indigo-600 uppercase truncate"><?= htmlspecialchars($t['project_title']) ?></span>
                                    <?= getPriorityBadge($t['priority']) ?>
                                </div>
                                <h4 class="text-xs font-bold text-slate-900"><?= htmlspecialchars($t['title']) ?></h4>
                                <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-[11px] text-slate-400">
                                    <span class="font-bold text-slate-700"><?= htmlspecialchars($t['assigned_name']) ?></span>
                                    <span><?= formatDate($t['due_date']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- IN PROGRESS -->
            <div class="bg-blue-50/40 p-3.5 rounded-2xl border border-blue-100 space-y-3 flex flex-col justify-between min-h-[400px]">
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-blue-200 mb-3">
                        <span class="text-xs font-bold text-blue-900 uppercase flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-blue-500"></span> In Progress
                        </span>
                        <span class="px-2 py-0.2 bg-white rounded-full text-xs font-bold text-blue-700 shadow-sm"><?= count($tasksByStatus['in_progress']) ?></span>
                    </div>

                    <div class="space-y-2.5">
                        <?php foreach ($tasksByStatus['in_progress'] as $t): ?>
                            <?php $dl = getDeadlineStatus($t['due_date'], $t['status']); ?>
                            <div class="p-3 bg-white rounded-xl <?= $dl['is_overdue'] ? 'border-2 border-rose-300 shadow-rose-100' : 'border border-slate-200' ?> shadow-sm space-y-2">
                                <div class="flex items-center justify-between gap-1 flex-wrap">
                                    <span class="text-[10px] font-bold text-indigo-600 uppercase truncate"><?= htmlspecialchars($t['project_title']) ?></span>
                                    <div class="flex items-center gap-1">
                                        <?= getPriorityBadge($t['priority']) ?>
                                        <?php if ($dl['is_overdue']): ?>
                                            <?= $dl['badge'] ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <h4 class="text-xs font-bold text-slate-900"><?= htmlspecialchars($t['title']) ?></h4>
                                <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-[11px] text-slate-400">
                                    <span class="font-bold text-slate-700"><?= htmlspecialchars($t['assigned_name']) ?></span>
                                    <div class="flex items-center gap-1.5 font-mono text-[10px]">
                                        <span><?= formatDate($t['due_date']) ?></span>
                                        <button type="button" @click="activeTask = <?= htmlspecialchars(json_encode($t)) ?>; extendModalOpen = true" class="text-indigo-600 hover:text-indigo-800 font-bold underline" title="Extend Deadline">+Extend</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- UNDER TL REVIEW -->
            <div class="bg-amber-50/50 p-3.5 rounded-2xl border border-amber-200 space-y-3 flex flex-col justify-between min-h-[400px]">
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-amber-200 mb-3">
                        <span class="text-xs font-bold text-amber-900 uppercase flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span> Under TL Review
                        </span>
                        <span class="px-2 py-0.2 bg-white rounded-full text-xs font-bold text-amber-800 shadow-sm"><?= count($tasksByStatus['review']) ?></span>
                    </div>

                    <div class="space-y-2.5">
                        <?php foreach ($tasksByStatus['review'] as $t): ?>
                            <div class="p-3 bg-white rounded-xl border border-amber-200 shadow-sm space-y-2">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-[10px] font-bold text-indigo-600 uppercase truncate"><?= htmlspecialchars($t['project_title']) ?></span>
                                    <?= getPriorityBadge($t['priority']) ?>
                                </div>
                                <h4 class="text-xs font-bold text-slate-900"><?= htmlspecialchars($t['title']) ?></h4>
                                <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-[11px] text-slate-400">
                                    <span class="font-bold text-slate-700"><?= htmlspecialchars($t['assigned_name']) ?></span>
                                    <button @click="activeSubmission = <?= htmlspecialchars(json_encode($t)) ?>; reviewModalOpen = true" class="px-2 py-1 bg-amber-500 text-white rounded text-[10px] font-bold shadow-sm">Review &rarr;</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- COMPLETED -->
            <div class="bg-emerald-50/40 p-3.5 rounded-2xl border border-emerald-100 space-y-3 flex flex-col justify-between min-h-[400px]">
                <div>
                    <div class="flex items-center justify-between pb-2 border-b border-emerald-200 mb-3">
                        <span class="text-xs font-bold text-emerald-900 uppercase flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Completed
                        </span>
                        <span class="px-2 py-0.2 bg-white rounded-full text-xs font-bold text-emerald-700 shadow-sm"><?= count($tasksByStatus['completed']) ?></span>
                    </div>

                    <div class="space-y-2.5">
                        <?php foreach ($tasksByStatus['completed'] as $t): ?>
                            <div class="p-3 bg-white rounded-xl border border-slate-200 shadow-sm space-y-2 opacity-90">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-[10px] font-bold text-indigo-600 uppercase truncate"><?= htmlspecialchars($t['project_title']) ?></span>
                                    <span class="text-[10px] font-bold text-emerald-600">✓ Done</span>
                                </div>
                                <h4 class="text-xs font-bold text-slate-900"><?= htmlspecialchars($t['title']) ?></h4>
                                <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-[11px] text-slate-400">
                                    <span class="font-bold text-slate-700"><?= htmlspecialchars($t['assigned_name']) ?></span>
                                    <span><?= formatDate($t['due_date']) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

    <!-- VIEW 3: TEAM WORK & DELIVERABLES HISTORY (Audit Register) -->
    <?php elseif ($viewMode === 'history'): ?>
        <div class="space-y-4">
            <!-- Month & Member Selector Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i data-lucide="archive" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Team Deliverables Register: <?= $monthTitle ?></h3>
                        <p class="text-[11px] text-slate-500">Historical archive of all team deliverables, photo/video proofs, and reviews.</p>
                    </div>
                </div>

                <!-- Month Navigation Controller -->
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="?page=tl-tasks&view=history&month=<?= $prevMonth ?><?= $filterMember ? "&member=$filterMember" : '' ?>" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition font-bold" title="Previous Month">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </a>

                    <form method="GET" action="" class="m-0 flex items-center gap-2">
                        <input type="hidden" name="page" value="tl-tasks">
                        <input type="hidden" name="view" value="history">
                        <?php if ($filterMember): ?>
                            <input type="hidden" name="member" value="<?= $filterMember ?>">
                        <?php endif; ?>
                        <input type="month" name="month" value="<?= $selectedMonth ?>" onchange="this.form.submit()" class="bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </form>

                    <a href="?page=tl-tasks&view=history&month=<?= $nextMonth ?><?= $filterMember ? "&member=$filterMember" : '' ?>" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition font-bold" title="Next Month">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </a>

                    <?php if (!$isCurrentMonth): ?>
                        <a href="?page=tl-tasks&view=history&month=<?= date('Y-m') ?><?= $filterMember ? "&member=$filterMember" : '' ?>" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-bold rounded-lg transition">
                            Current Month
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Date-Grouped Team Deliverables Register -->
            <?php if (empty($teamWorkHistory)): ?>
                <div class="bg-white p-12 rounded-3xl border border-slate-200 shadow-sm text-center">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="folder-clock" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-800">No Deliverables Submitted By Team For <?= $monthTitle ?></h3>
                    <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">Use the month or member filter above to view historical logs.</p>
                </div>
            <?php else: 
                // Group by date (YYYY-MM-DD)
                $teamHistoryByDate = [];
                foreach ($teamWorkHistory as $wh) {
                    $d = date('Y-m-d', strtotime($wh['submitted_at']));
                    $teamHistoryByDate[$d][] = $wh;
                }
            ?>
                <div class="space-y-4">
                    <?php foreach ($teamHistoryByDate as $dateKey => $subList): 
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
                            <div x-show="expanded" class="w-full overflow-x-auto">
                                <table class="w-full text-left text-xs text-slate-600 border-collapse">
                                    <thead class="bg-slate-50/50 text-slate-400 text-[10px] uppercase tracking-wider font-bold border-b border-slate-100">
                                        <tr>
                                            <th class="py-2.5 pl-4 pr-3 whitespace-nowrap">Time</th>
                                            <th class="py-2.5 px-3">Employee</th>
                                            <th class="py-2.5 px-3">Project & Task</th>
                                            <th class="py-2.5 px-3">Employee Notes</th>
                                            <th class="py-2.5 px-3">Deliverable Proof</th>
                                            <th class="py-2.5 px-3 text-center">Status</th>
                                            <th class="py-2.5 pl-3 pr-4">TL Feedback</th>
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
                                                            <div class="text-[10px] text-slate-400 font-mono"><?= htmlspecialchars($wh['emp_id']) ?></div>
                                                        </div>
                                                    </div>
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

    <!-- Review Submission Detail Card Popup -->
    <div x-show="reviewModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="reviewModalOpen = false" class="bg-white rounded-2xl max-w-xl w-full shadow-2xl border border-slate-200 overflow-hidden" x-data="{ reqChangeOpen: false }">

            <!-- Modal Header -->
            <div class="bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-4 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center">
                        <i data-lucide="clipboard-check" class="w-5 h-5 text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold">Review Submitted Deliverable</h3>
                        <p class="text-xs text-indigo-200 mt-0.5" x-text="activeSubmission ? activeSubmission.assigned_name : ''"></p>
                    </div>
                </div>
                <button @click="reviewModalOpen = false" class="text-white/70 hover:text-white transition"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto" x-show="activeSubmission">

                <!-- Task & Project Info Card -->
                <div class="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider" x-text="activeSubmission ? activeSubmission.project_title : ''"></span>
                        <span class="text-[10px] font-mono text-slate-400" x-text="activeSubmission && activeSubmission.submitted_at ? '📤 Submitted: ' + activeSubmission.submitted_at : ''"></span>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900" x-text="activeSubmission ? activeSubmission.title : ''"></h4>
                    <template x-if="activeSubmission && activeSubmission.description">
                        <p class="text-xs text-slate-500 leading-relaxed" x-text="activeSubmission.description"></p>
                    </template>
                    <div class="flex items-center gap-3 pt-1 border-t border-slate-200 text-[11px]">
                        <span class="text-slate-500 flex items-center gap-1">
                            <i data-lucide="user" class="w-3 h-3"></i>
                            <span x-text="activeSubmission ? activeSubmission.assigned_name : ''"></span>
                        </span>
                        <span class="text-slate-400 flex items-center gap-1">
                            <i data-lucide="calendar" class="w-3 h-3"></i>
                            Due: <span x-text="activeSubmission ? activeSubmission.due_date : ''"></span>
                        </span>
                    </div>
                </div>

                <!-- Employee Submission Notes -->
                <div class="p-4 bg-emerald-50/60 rounded-xl border border-emerald-200 space-y-1.5">
                    <div class="flex items-center gap-1.5 mb-1">
                        <i data-lucide="message-circle" class="w-3.5 h-3.5 text-emerald-600"></i>
                        <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider">Employee Submission Notes</span>
                    </div>
                    <p class="text-xs text-slate-700 font-medium leading-relaxed whitespace-pre-wrap" x-text="activeSubmission && activeSubmission.submission_notes ? activeSubmission.submission_notes : 'No extra notes provided by the employee.'"></p>
                </div>

                <!-- Uploaded Media / Photos / Videos / Files (View Only - Redirect to Drive for Downloads) -->
                <template x-if="activeSubmission && activeSubmission.attachment_file">
                    <div class="p-4 bg-indigo-50/60 rounded-xl border border-indigo-200 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1.5">
                                <i data-lucide="file-check" class="w-3.5 h-3.5 text-indigo-600"></i>
                                <span class="text-[10px] font-bold text-indigo-700 uppercase tracking-wider">
                                    Submitted Deliverable Proof (<span x-text="activeSubmission.attachment_type || 'File'"></span>)
                                </span>
                            </div>
                            <a href="?page=tech-drive" class="inline-flex items-center gap-1 text-[11px] font-bold text-indigo-600 hover:text-indigo-800 transition">
                                <i data-lucide="hard-drive" class="w-3.5 h-3.5"></i> Open in Tech Cloud Drive &rarr;
                            </a>
                        </div>

                        <!-- 1. PHOTO / IMAGE PREVIEW (View Only) -->
                        <template x-if="activeSubmission.attachment_type === 'image' || (activeSubmission.attachment_file && activeSubmission.attachment_file.match(/\.(jpg|jpeg|png|webp|gif)$/i))">
                            <div class="rounded-xl overflow-hidden border border-indigo-200 bg-white p-1.5 text-center shadow-xs">
                                <a :href="activeSubmission.attachment_file" target="_blank" title="Click to open full resolution image">
                                    <img :src="activeSubmission.attachment_file" class="max-h-72 w-auto mx-auto rounded-lg object-contain hover:opacity-95 transition" alt="Task Deliverable Proof">
                                </a>
                                <div class="flex items-center justify-between px-2 pt-2 text-[11px] text-slate-500">
                                    <span>🔍 Click image to view full resolution</span>
                                    <a href="?page=tech-drive" class="text-indigo-600 font-bold hover:underline">Download from Cloud Drive &rarr;</a>
                                </div>
                            </div>
                        </template>

                        <!-- 2. VIDEO STREAMING PLAYER (View Only) -->
                        <template x-if="activeSubmission.attachment_type === 'video' || (activeSubmission.attachment_file && activeSubmission.attachment_file.match(/\.(mp4|webm|mov|mkv)$/i))">
                            <div class="space-y-1.5">
                                <div class="rounded-xl overflow-hidden border border-indigo-200 bg-black shadow-md">
                                    <video :src="activeSubmission.attachment_file" controls class="w-full max-h-72 rounded-xl bg-black" preload="metadata">
                                        Your browser does not support video playback.
                                    </video>
                                </div>
                                <div class="flex items-center justify-end text-[11px] text-slate-500 px-1">
                                    <a href="?page=tech-drive" class="text-indigo-600 font-bold hover:underline">Download video from Tech Cloud Drive &rarr;</a>
                                </div>
                            </div>
                        </template>

                        <!-- 3. OTHER DOCUMENT / ARCHIVE / PDF (View & Redirect to Drive) -->
                        <template x-if="activeSubmission.attachment_type === 'file' || (!activeSubmission.attachment_file.match(/\.(jpg|jpeg|png|webp|gif|mp4|webm|mov)$/i))">
                            <div class="p-3 bg-white rounded-xl border border-indigo-200 space-y-2">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2 text-xs font-bold text-slate-800 truncate">
                                        <i data-lucide="file-text" class="w-4 h-4 text-indigo-600 shrink-0"></i>
                                        <span class="truncate" x-text="activeSubmission.attachment_file.split('/').pop()"></span>
                                    </div>
                                    <a :href="activeSubmission.attachment_file" target="_blank" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-lg text-xs font-bold shrink-0 transition inline-flex items-center gap-1">
                                        <i data-lucide="eye" class="w-3.5 h-3.5"></i> Preview / View
                                    </a>
                                </div>
                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                                    <span>To download or manage this file:</span>
                                    <a href="?page=tech-drive" class="text-indigo-600 font-bold hover:underline inline-flex items-center gap-1">
                                        <i data-lucide="hard-drive" class="w-3 h-3"></i> Go to Tech Cloud Drive &rarr;
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Attachment / Deliverable Link -->
                <template x-if="activeSubmission && activeSubmission.attachment_url">
                    <div class="p-4 bg-indigo-50/60 rounded-xl border border-indigo-200">
                        <div class="flex items-center gap-1.5 mb-2">
                            <i data-lucide="paperclip" class="w-3.5 h-3.5 text-indigo-600"></i>
                            <span class="text-[10px] font-bold text-indigo-700 uppercase tracking-wider">Attached Deliverable / Project URL</span>
                        </div>
                        <div class="flex items-center justify-between gap-2 p-2.5 bg-white rounded-lg border border-indigo-200">
                            <div class="flex items-center gap-2 text-xs text-indigo-900 font-bold truncate overflow-hidden">
                                <i data-lucide="external-link" class="w-4 h-4 text-indigo-600 shrink-0"></i>
                                <span class="truncate" x-text="activeSubmission.attachment_url"></span>
                            </div>
                            <a :href="activeSubmission.attachment_url" target="_blank" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-[10px] font-bold shrink-0 transition">Open Link &rarr;</a>
                        </div>
                    </div>
                </template>

                <!-- Previous TL Feedback (if any) -->
                <template x-if="activeSubmission && activeSubmission.tl_feedback">
                    <div class="p-4 bg-amber-50/70 rounded-xl border border-amber-200 space-y-1.5">
                        <div class="flex items-center gap-1.5 mb-1">
                            <i data-lucide="message-square-warning" class="w-3.5 h-3.5 text-amber-600"></i>
                            <span class="text-[10px] font-bold text-amber-700 uppercase tracking-wider">Previous TL Review Feedback</span>
                        </div>
                        <p class="text-xs text-amber-900 font-medium leading-relaxed whitespace-pre-wrap" x-text="activeSubmission.tl_feedback"></p>
                        <template x-if="activeSubmission.review_status">
                            <span class="inline-flex items-center gap-1 text-[10px] font-bold mt-1 px-2 py-0.5 rounded-full"
                                :class="activeSubmission.review_status === 'approved' ? 'bg-emerald-100 text-emerald-700' : (activeSubmission.review_status === 'changes_requested' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600')"
                                x-text="activeSubmission.review_status === 'approved' ? '✓ Was Approved' : (activeSubmission.review_status === 'changes_requested' ? '⟳ Changes Were Requested' : activeSubmission.review_status)">
                            </span>
                        </template>
                    </div>
                </template>

                <!-- Action Buttons -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                    <button type="button" @click="reqChangeOpen = !reqChangeOpen" class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition flex items-center gap-1.5">
                        <i data-lucide="undo-2" class="w-3.5 h-3.5"></i> Request Changes
                    </button>
                    <form action="?action=review-submission" method="POST" class="inline">
                        <input type="hidden" name="submission_id" :value="activeSubmission ? activeSubmission.submission_id : ''">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="tl_feedback" value="Approved & completed by TL">
                        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5">
                            <i data-lucide="check" class="w-4 h-4"></i> Approve & Mark Completed
                        </button>
                    </form>
                </div>

                <!-- Expandable Request Changes Drawer -->
                <div x-show="reqChangeOpen" class="mt-1 pt-3 border-t border-slate-200" x-cloak>
                    <form action="?action=review-submission" method="POST" class="space-y-2.5">
                        <input type="hidden" name="submission_id" :value="activeSubmission ? activeSubmission.submission_id : ''">
                        <input type="hidden" name="action" value="reject">
                        <label class="block text-[11px] font-bold text-amber-800 uppercase">Change Instructions / Feedback for Employee</label>
                        <textarea name="tl_feedback" rows="3" required placeholder="Describe what corrections or additional work is required..." class="w-full text-xs p-3 rounded-xl border border-amber-300 bg-amber-50 focus:ring-2 focus:ring-indigo-500"></textarea>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="reqChangeOpen = false" class="px-2.5 py-1 text-xs text-slate-500">Cancel</button>
                            <button type="submit" class="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-xl flex items-center gap-1">
                                <i data-lucide="send" class="w-3 h-3"></i> Send Feedback & Re-assign
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Task Modal -->
    <div x-show="newTaskModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="newTaskModalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Create & Assign Team Task</h3>
                        <p class="text-[11px] text-slate-500">Assign sprint deliverables with deadline and priority.</p>
                    </div>
                </div>
                <button @click="newTaskModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form action="?action=create-task" method="POST" class="space-y-3.5">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Project</label>
                    <select name="project_id" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-800">
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Task Title</label>
                    <input type="text" name="title" required placeholder="e.g. Build REST API for Attendance Webhook" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Description & Requirements</label>
                    <textarea name="description" rows="3" placeholder="Provide task requirements, acceptance criteria, or links..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Assign to Member</label>
                        <select name="assigned_to" required class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold">
                            <?php foreach ($teamMembers as $m): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['designation']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Priority</label>
                        <select name="priority" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold">
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Due Date</label>
                    <input type="date" name="due_date" required min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs">
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="newTaskModalOpen = false" class="px-3.5 py-1.5 text-xs font-semibold text-slate-500">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                        Create & Assign Task
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Extend Deadline Modal -->
    <div x-show="extendModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="extendModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Extend Task Deadline</h3>
                        <p class="text-[11px] text-slate-500" x-text="activeTask ? activeTask.title : ''"></p>
                    </div>
                </div>
                <button @click="extendModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form action="?action=extend-deadline" method="POST" class="space-y-3.5">
                <input type="hidden" name="task_id" :value="activeTask ? activeTask.id : ''">

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">New Target Due Date</label>
                    <input type="date" name="new_due_date" required min="<?= date('Y-m-d') ?>" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-indigo-700">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Reason for Extension / TL Remark</label>
                    <textarea name="extension_reason" rows="3" placeholder="e.g. Scope revised, extra testing needed..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="extendModalOpen = false" class="px-3.5 py-1.5 text-xs font-semibold text-slate-500">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                        Update Deadline
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


