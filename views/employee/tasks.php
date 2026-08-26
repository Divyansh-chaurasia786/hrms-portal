<!-- views/employee/tasks.php -->
<?php
$user = authUser();
$db = getDBConnection();

$searchQuery = trim($_GET['search'] ?? '');
$viewMode = $_GET['view'] ?? 'list'; // 'list', 'kanban', or 'history'
$filterPriority = $_GET['priority'] ?? '';

$sql = "
    SELECT t.*, p.title as project_title, u.name as tl_name,
           ts.id as submission_id, ts.notes as submission_notes, ts.attachment_url, ts.tl_feedback, ts.review_status, ts.submitted_at
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON t.created_by = u.id
    LEFT JOIN (
        SELECT ts1.* FROM task_submissions ts1
        INNER JOIN (
            SELECT MAX(id) as max_id FROM task_submissions GROUP BY task_id
        ) ts2 ON ts1.id = ts2.max_id
    ) ts ON ts.task_id = t.id
    WHERE t.assigned_to = :emp_id
";

if (!empty($filterPriority)) $sql .= " AND t.priority = :priority_val";
if (!empty($searchQuery)) $sql .= " AND (t.title LIKE :search OR t.description LIKE :search OR p.title LIKE :search)";

$sql .= " ORDER BY CASE t.status WHEN 'in_progress' THEN 1 WHEN 'todo' THEN 2 WHEN 'review' THEN 3 ELSE 4 END, t.due_date ASC";

$stmt = $db->prepare($sql);
$params = [':emp_id' => $user['id']];
if (!empty($filterPriority)) $params[':priority_val'] = $filterPriority;
if (!empty($searchQuery)) $params[':search'] = "%{$searchQuery}%";
$stmt->execute($params);
$allTasks = $stmt->fetchAll();

// Month filter for Work History (defaults to current month YYYY-MM)
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

// Fetch Historical Work Submissions for Work History Log in selected month
$historySql = "
    SELECT ts.*, t.title as task_title, t.description as task_description, t.status as task_status,
           t.due_date, p.title as project_title, tl.name as tl_name
    FROM task_submissions ts
    JOIN tasks t ON ts.task_id = t.id
    JOIN projects p ON t.project_id = p.id
    JOIN users tl ON t.created_by = tl.id
    WHERE ts.submitted_by = ? AND ts.submitted_at >= ? AND ts.submitted_at <= ?
";
if (!empty($searchQuery)) {
    $historySql .= " AND (t.title LIKE ? OR p.title LIKE ? OR ts.notes LIKE ?)";
}
$historySql .= " ORDER BY ts.submitted_at DESC";

$historyStmt = $db->prepare($historySql);
$histParams = [$user['id'], $monthStartDate, $monthEndDate];
if (!empty($searchQuery)) {
    $histParams[] = "%{$searchQuery}%";
    $histParams[] = "%{$searchQuery}%";
    $histParams[] = "%{$searchQuery}%";
}
$historyStmt->execute($histParams);
$workHistory = $historyStmt->fetchAll();

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

<div class="space-y-6" x-data="{ submitModalOpen: false, activeTask: null }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="kanban" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">My Tasks & Deliverables</h1>
                <p class="text-xs text-slate-500 mt-0.5">Track your assigned sprint workload, start tasks, and submit deliverables for TL review.</p>
            </div>
        </div>
    </div>

    <?php 
    $isUserInShift = isInActiveShift($user['id']);
    if (!$isUserInShift): 
    ?>
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-900 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-800 flex items-center justify-center shrink-0">
                    <i data-lucide="eye" class="w-5 h-5"></i>
                </div>
                <div>
                    <h4 class="text-xs font-bold text-amber-900 uppercase tracking-wider flex items-center gap-1.5">
                        <span>🔒 View-Only Mode Active (Out of Shift)</span>
                    </h4>
                    <p class="text-xs text-amber-800 mt-0.5 font-medium">You can view all tasks and sprint items, but you cannot start work, submit deliverables, or edit tasks until you <strong>Punch In</strong>.</p>
                </div>
            </div>
            <a href="?page=dashboard" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold transition shrink-0 shadow-sm inline-flex items-center gap-1.5">
                <i data-lucide="play-circle" class="w-4 h-4"></i> Punch In (Start Shift)
            </a>
        </div>
    <?php endif; ?>

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
                    <?= $overdueCount > 0 ? 'Overdue Tasks' : 'Under Review' ?>
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

    <!-- Red Alert Overdue Banner for Employee -->
    <?php if ($overdueCount > 0): ?>
        <div class="p-3.5 bg-rose-50 rounded-xl border border-rose-200 text-xs text-rose-900 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2.5">
                <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-600 shrink-0 animate-bounce"></i>
                <span><strong>🚨 Action Required: You have <?= $overdueCount ?> task(s) past deadline!</strong> Please complete & submit your deliverables or consult your Team Lead.</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filter & View Switcher Toolbar -->
    <div class="bg-white p-3.5 rounded-xl border border-slate-200 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-3">
        <form method="GET" class="flex-1 w-full flex flex-col sm:flex-row items-center gap-2.5">
            <input type="hidden" name="page" value="employee-tasks">
            <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">

            <!-- Search input -->
            <div class="relative flex-1 w-full">
                <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search my tasks, projects..." class="w-full bg-slate-50 border border-slate-200 rounded-lg pl-8 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
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

            <?php if (!empty($searchQuery) || !empty($filterPriority)): ?>
                <a href="?page=employee-tasks&view=<?= htmlspecialchars($viewMode) ?>" class="text-xs text-rose-600 hover:text-rose-700 font-semibold px-2 py-1">Clear</a>
            <?php endif; ?>
        </form>

        <!-- View Switcher Tabs -->
        <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200 shrink-0">
            <a href="?page=employee-tasks&view=list" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $viewMode === 'list' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>">
                <i data-lucide="list" class="w-3.5 h-3.5"></i> List View
            </a>
            <a href="?page=employee-tasks&view=kanban" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $viewMode === 'kanban' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>">
                <i data-lucide="kanban" class="w-3.5 h-3.5"></i> Board View
            </a>
            <a href="?page=employee-tasks&view=history" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition <?= $viewMode === 'history' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>">
                <i data-lucide="history" class="w-3.5 h-3.5"></i> Work History (<?= count($workHistory) ?>)
            </a>
        </div>
    </div>

    <!-- VIEW 1: CLEAN ENTERPRISE LIST / TABLE VIEW (Default) -->
    <?php if ($viewMode === 'list'): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden w-full">
            <div class="overflow-x-auto no-scrollbar">
                <table class="w-full text-left text-xs text-slate-600 border-collapse min-w-[750px]">
                    <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                        <tr>
                            <th class="min-w-[240px] py-3.5 pl-5 pr-3">Task Details</th>
                            <th class="min-w-[150px] py-3.5 px-3">Assigned By</th>
                            <th class="min-w-[140px] py-3.5 px-2 whitespace-nowrap">Priority & Due</th>
                            <th class="min-w-[110px] py-3.5 px-2 text-center whitespace-nowrap">Status</th>
                            <th class="min-w-[130px] py-3.5 pl-2 pr-5 text-right whitespace-nowrap">Action</th>
                        </tr>
                    </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($allTasks)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-10 text-slate-400">
                                <i data-lucide="check-square" class="w-8 h-8 mx-auto text-slate-300 mb-1"></i>
                                <p class="text-xs font-semibold text-slate-600">No tasks currently assigned to you</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($allTasks as $t): ?>
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Task Details -->
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

                            <!-- Assigned By TL -->
                            <td class="py-3.5 px-3 align-middle">
                                <div class="font-bold text-slate-800 text-xs truncate"><?= htmlspecialchars($t['tl_name']) ?></div>
                                <div class="text-[10px] text-slate-400">Team Lead</div>
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
                                    <div class="text-[10px] text-slate-400 font-mono">Due: <?= formatDate($t['due_date']) ?></div>
                                </div>
                            </td>

                            <!-- Status -->
                            <td class="py-3.5 px-2 align-middle text-center">
                                <?= getStatusBadge($t['status']) ?>
                            </td>

                            <!-- Employee Actions -->
                            <td class="py-3.5 pl-2 pr-5 align-middle text-right whitespace-nowrap">
                                <?php if ($t['status'] === 'todo'): ?>
                                    <form action="?action=update-task-status" method="POST" class="inline">
                                        <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                        <input type="hidden" name="status" value="in_progress">
                                        <button type="submit" class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-lg shadow-sm transition">
                                            <i data-lucide="play" class="w-3 h-3"></i> Start Work
                                        </button>
                                    </form>
                                <?php elseif ($t['status'] === 'in_progress'): ?>
                                    <button @click="activeTask = <?= htmlspecialchars(json_encode($t)) ?>; submitModalOpen = true" class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg shadow-sm transition">
                                        <i data-lucide="send" class="w-3 h-3"></i> Submit Work
                                    </button>
                                <?php elseif ($t['status'] === 'review'): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-600">
                                        <i data-lucide="clock" class="w-3.5 h-3.5"></i> In TL Review
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-bold text-emerald-600">
                                        <i data-lucide="check-circle" class="w-3.5 h-3.5"></i> Approved
                                    </span>
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
            <div class="bg-slate-100/70 p-3.5 rounded-2xl border border-slate-200/80 space-y-3 flex flex-col min-h-[400px]">
                <div class="flex items-center justify-between pb-2 border-b border-slate-200 mb-3">
                    <span class="text-xs font-bold text-slate-700 uppercase flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-slate-400"></span> To Do
                    </span>
                    <span class="px-2 py-0.2 bg-white rounded-full text-xs font-bold text-slate-600 shadow-sm"><?= count($tasksByStatus['todo']) ?></span>
                </div>

                <div class="space-y-2.5 flex-1">
                    <?php foreach ($tasksByStatus['todo'] as $t): ?>
                        <div class="p-3.5 bg-white rounded-xl border border-slate-200 shadow-sm space-y-2.5">
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-[10px] font-bold text-indigo-600 uppercase truncate"><?= htmlspecialchars($t['project_title']) ?></span>
                                <?= getPriorityBadge($t['priority']) ?>
                            </div>
                            <h4 class="text-xs font-bold text-slate-900"><?= htmlspecialchars($t['title']) ?></h4>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-[11px]">
                                <span class="text-slate-400 font-mono text-[10px]">Due: <?= formatDate($t['due_date']) ?></span>
                                <form action="?action=update-task-status" method="POST" class="inline">
                                    <input type="hidden" name="task_id" value="<?= $t['id'] ?>">
                                    <input type="hidden" name="status" value="in_progress">
                                    <button type="submit" class="px-2.5 py-1 bg-blue-600 text-white rounded-lg text-[10px] font-bold shadow-sm">Start &rarr;</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- IN PROGRESS -->
            <div class="bg-blue-50/40 p-3.5 rounded-2xl border border-blue-100 space-y-3 flex flex-col min-h-[400px]">
                <div class="flex items-center justify-between pb-2 border-b border-blue-200 mb-3">
                    <span class="text-xs font-bold text-blue-900 uppercase flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-blue-500"></span> In Progress
                    </span>
                    <span class="px-2 py-0.2 bg-white rounded-full text-xs font-bold text-blue-700 shadow-sm"><?= count($tasksByStatus['in_progress']) ?></span>
                </div>

                <div class="space-y-2.5 flex-1">
                    <?php foreach ($tasksByStatus['in_progress'] as $t): ?>
                        <div class="p-3.5 bg-white rounded-xl border border-slate-200 shadow-sm space-y-2.5">
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-[10px] font-bold text-indigo-600 uppercase truncate"><?= htmlspecialchars($t['project_title']) ?></span>
                                <?= getPriorityBadge($t['priority']) ?>
                            </div>
                            <h4 class="text-xs font-bold text-slate-900"><?= htmlspecialchars($t['title']) ?></h4>
                            <div class="flex items-center justify-between pt-2 border-t border-slate-100 text-[11px]">
                                <span class="text-slate-400 font-mono text-[10px]">Due: <?= formatDate($t['due_date']) ?></span>
                                <button @click="activeTask = <?= htmlspecialchars(json_encode($t)) ?>; submitModalOpen = true" class="px-2.5 py-1 bg-emerald-600 text-white rounded-lg text-[10px] font-bold shadow-sm">Submit &rarr;</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- UNDER REVIEW -->
            <div class="bg-amber-50/50 p-3.5 rounded-2xl border border-amber-200 space-y-3 flex flex-col min-h-[400px]">
                <div class="flex items-center justify-between pb-2 border-b border-amber-200 mb-3">
                    <span class="text-xs font-bold text-amber-900 uppercase flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span> In Review
                    </span>
                    <span class="px-2 py-0.2 bg-white rounded-full text-xs font-bold text-amber-800 shadow-sm"><?= count($tasksByStatus['review']) ?></span>
                </div>

                <div class="space-y-2.5 flex-1">
                    <?php foreach ($tasksByStatus['review'] as $t): ?>
                        <div class="p-3.5 bg-white rounded-xl border border-slate-200 shadow-sm space-y-2.5">
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-[10px] font-bold text-indigo-600 uppercase truncate"><?= htmlspecialchars($t['project_title']) ?></span>
                                <?= getPriorityBadge($t['priority']) ?>
                            </div>
                            <h4 class="text-xs font-bold text-slate-900"><?= htmlspecialchars($t['title']) ?></h4>
                            <div class="pt-2 border-t border-slate-100 text-[10px] text-amber-700 font-bold flex items-center gap-1">
                                <i data-lucide="clock" class="w-3 h-3"></i> Awaiting TL Review
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- COMPLETED -->
            <div class="bg-emerald-50/40 p-3.5 rounded-2xl border border-emerald-100 space-y-3 flex flex-col min-h-[400px]">
                <div class="flex items-center justify-between pb-2 border-b border-emerald-200 mb-3">
                    <span class="text-xs font-bold text-emerald-900 uppercase flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Completed
                    </span>
                    <span class="px-2 py-0.2 bg-white rounded-full text-xs font-bold text-emerald-700 shadow-sm"><?= count($tasksByStatus['completed']) ?></span>
                </div>

                <div class="space-y-2.5 flex-1">
                    <?php foreach ($tasksByStatus['completed'] as $t): ?>
                        <div class="p-3.5 bg-white rounded-xl border border-slate-200 shadow-sm space-y-2.5 opacity-90">
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-[10px] font-bold text-indigo-600 uppercase truncate"><?= htmlspecialchars($t['project_title']) ?></span>
                                <span class="text-[10px] font-bold text-emerald-600">✓ Done</span>
                            </div>
                            <h4 class="text-xs font-bold text-slate-900"><?= htmlspecialchars($t['title']) ?></h4>
                            <div class="pt-2 border-t border-slate-100 text-[10px] text-emerald-700 font-bold">
                                Verified by TL
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <!-- VIEW 3: WORK HISTORY LOG & DELIVERABLES ARCHIVE (Like Attendance Register) -->
    <?php elseif ($viewMode === 'history'): ?>
        <div class="space-y-4">
            <!-- Month Selector Toolbar -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i data-lucide="calendar" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-bold text-slate-900 uppercase tracking-wider">Work Deliverables Register: <?= $monthTitle ?></h3>
                        <p class="text-[11px] text-slate-500">Historical audit of all task deliverables submitted during this month.</p>
                    </div>
                </div>

                <!-- Month Navigation Controller -->
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="?page=employee-tasks&view=history&month=<?= $prevMonth ?>" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition font-bold" title="Previous Month">
                        <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    </a>

                    <form method="GET" action="" class="m-0 flex items-center gap-2">
                        <input type="hidden" name="page" value="employee-tasks">
                        <input type="hidden" name="view" value="history">
                        <input type="month" name="month" value="<?= $selectedMonth ?>" onchange="this.form.submit()" class="bg-slate-50 border border-slate-300 rounded-xl px-2.5 py-1 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
                    </form>

                    <a href="?page=employee-tasks&view=history&month=<?= $nextMonth ?>" class="p-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg transition font-bold" title="Next Month">
                        <i data-lucide="chevron-right" class="w-4 h-4"></i>
                    </a>

                    <?php if (!$isCurrentMonth): ?>
                        <a href="?page=employee-tasks&view=history&month=<?= date('Y-m') ?>" class="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 text-xs font-bold rounded-lg transition">
                            Current Month
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Compact Audit Table Grouped By Date -->
            <?php if (empty($workHistory)): ?>
                <div class="bg-white p-12 rounded-3xl border border-slate-200 shadow-sm text-center">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-500 flex items-center justify-center mx-auto mb-3">
                        <i data-lucide="folder-clock" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-800">No Deliverables Logged For <?= $monthTitle ?></h3>
                    <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">When you submit assigned tasks with photo/video proofs and notes, your historical work log will be listed here date-by-date.</p>
                </div>
            <?php else: 
                // Group by date (YYYY-MM-DD)
                $historyByDate = [];
                foreach ($workHistory as $wh) {
                    $d = date('Y-m-d', strtotime($wh['submitted_at']));
                    $historyByDate[$d][] = $wh;
                }
            ?>
                <div class="space-y-4">
                    <?php foreach ($historyByDate as $dateKey => $subList): 
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
                                        <span class="px-2.5 py-0.5 rounded-full bg-white text-slate-700 border border-slate-200 font-bold"><?= count($subList) ?> Works Submitted</span>
                                        <?php if ($apprCount > 0): ?>
                                            <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold">✓ <?= $apprCount ?> Approved</span>
                                        <?php endif; ?>
                                        <?php if ($reqCount > 0): ?>
                                            <span class="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200 font-bold">⚠️ <?= $reqCount ?> Changes</span>
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
                                            <th class="py-2.5 px-3">Project & Task</th>
                                            <th class="py-2.5 px-3">My Remarks</th>
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

                                                <!-- Project & Task -->
                                                <td class="py-3 px-3 align-top">
                                                    <div class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider">
                                                        <?= htmlspecialchars($wh['project_title']) ?>
                                                    </div>
                                                    <div class="font-bold text-slate-900 text-xs mt-0.5">
                                                        <?= htmlspecialchars($wh['task_title']) ?>
                                                    </div>
                                                </td>

                                                <!-- My Remarks -->
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
                                                            <strong class="font-bold text-[10px] text-amber-800 block"><?= htmlspecialchars($wh['tl_name']) ?>:</strong>
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

    <!-- Submit Work Modal -->
    <div x-show="submitModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="submitModalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i data-lucide="send" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Submit Work for TL Review</h3>
                        <p class="text-[11px] text-slate-500" x-text="activeTask ? activeTask.title : ''"></p>
                    </div>
                </div>
                <button @click="submitModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form action="?action=submit-work" method="POST" enctype="multipart/form-data" class="space-y-3.5" x-data="{ fileName: '', fileType: '' }">
                <input type="hidden" name="task_id" :value="activeTask ? activeTask.id : ''">

                <!-- Upload Deliverable Photo / Video / File -->
                <div>
                    <?php
                    $tlId = (int)($user['reporting_tl_id'] ?: 30010);
                    $tlDriveRow = $db->query("SELECT is_connected FROM drive_settings WHERE team_lead_id = {$tlId}")->fetch();
                    $isDriveActive = !empty($tlDriveRow['is_connected']);
                    $assignedTlName = $db->query("SELECT name FROM users WHERE id = {$tlId}")->fetchColumn() ?: 'Team Lead';
                    ?>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">
                        Upload Deliverable Photo / Video Demo / File (Optional)
                    </label>
                    <?php if (!$isDriveActive): ?>
                        <div class="space-y-2">
                            <!-- Alert Notification Banner -->
                            <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-amber-900 text-xs flex items-start gap-2.5">
                                <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                                <div>
                                    <strong class="font-bold block">Team Cloud Drive is Inactive:</strong>
                                    <span class="text-amber-800 text-[11px]">Photo, video, and file uploads are disabled because your Team Lead (<strong><?= htmlspecialchars($assignedTlName) ?></strong>) has not connected Google Drive. You can submit your report as text only, or provide a link below.</span>
                                </div>
                            </div>
                            
                            <!-- Locked Upload Area with Interactive Click Alert -->
                            <div @click="alert('⚠️ Photo/Video/File uploads are disabled because Team Cloud Drive is not active. Please contact Team Lead (<?= htmlspecialchars(addslashes($assignedTlName)) ?>) to link Google Drive, or submit your report using the Work Description field below.')" class="border-2 border-dashed border-slate-200 bg-slate-100 rounded-xl p-4 text-center cursor-pointer hover:bg-amber-50/50 hover:border-amber-300 transition">
                                <div class="flex items-center justify-center gap-2 text-slate-500 font-bold text-xs">
                                    <i data-lucide="lock" class="w-4 h-4 text-slate-400"></i>
                                    <span>Photo/Video Uploads Locked (Drive Inactive)</span>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-0.5">Click for alert • Only text report submissions allowed</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="p-3 bg-slate-50 border-2 border-dashed border-slate-200 hover:border-indigo-400 rounded-xl transition cursor-pointer relative group text-center">
                            <input 
                                type="file" 
                                name="attachment_file" 
                                accept="image/*,video/*,.pdf,.zip,.rar,.doc,.docx,.txt" 
                                @change="
                                    if ($event.target.files.length > 0) {
                                        fileName = $event.target.files[0].name;
                                        fileType = $event.target.files[0].type;
                                    } else {
                                        fileName = '';
                                        fileType = '';
                                    }
                                "
                                class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                            >
                            <div class="space-y-1 py-1 pointer-events-none">
                                <div class="flex items-center justify-center gap-2 text-indigo-600 font-bold text-xs">
                                    <i data-lucide="upload-cloud" class="w-5 h-5"></i>
                                    <span x-text="fileName ? 'Selected: ' + fileName : 'Choose Screenshot, Video Demo, or Archive'"></span>
                                </div>
                                <p class="text-[10px] text-slate-400" x-show="!fileName">Supports PNG, JPG, MP4/WEBM Video (up to 50MB), PDF, ZIP</p>
                                <div x-show="fileName" class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 text-[10px] font-bold">
                                    <span x-text="fileType.includes('video') ? '🎥 Video File' : (fileType.includes('image') ? '🖼️ Photo/Image' : '📎 Document')"></span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Deliverable URL Link -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Deliverable Link (GitHub PR / Figma / Drive Link - Optional)</label>
                    <input type="url" name="attachment_url" placeholder="https://github.com/... or https://figma.com/..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs">
                </div>

                <!-- Notes / Remarks -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Work Description & Implementation Notes *</label>
                    <textarea name="notes" rows="3" required placeholder="Describe the changes made, features implemented, or testing verified..." class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs"></textarea>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="submitModalOpen = false" class="px-3.5 py-1.5 text-xs font-semibold text-slate-500">Cancel</button>
                    <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-sm transition inline-flex items-center gap-1.5">
                        <i data-lucide="send" class="w-3.5 h-3.5"></i>
                        <span>Submit Deliverable to TL</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

