<?php
// views/tech/drive.php
$user = authUser();
$db = getDBConnection();
DriveController::checkAccess();
DriveController::autoSyncIfNeeded();

$activeTeamLeadId = DriveController::getActiveTeamLeadId();
$tlUser = $db->query("SELECT id, name, emp_id, designation FROM users WHERE id = " . (int)$activeTeamLeadId)->fetch();
$teamLeadName = $tlUser ? $tlUser['name'] : 'Team';

// Fetch all Team Leads for Admin Switcher
$allTeamLeads = [];
if (isAdmin()) {
    $allTeamLeads = $db->query("SELECT id, name, emp_id, designation FROM users WHERE role = 'team_lead' AND status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

$currentFolderId = (int)($_GET['folder'] ?? 0);
$searchQuery = trim($_GET['search'] ?? '');
$typeFilter = $_GET['type'] ?? '';
$viewMode = $_GET['view'] ?? 'grid';

// Fetch Drive Settings for the active Team Lead
$stmt = $db->prepare("SELECT * FROM drive_settings WHERE team_lead_id = ?");
$stmt->execute([$activeTeamLeadId]);
$settings = $stmt->fetch();
if (!$settings) {
    $settings = [
        'team_lead_id' => $activeTeamLeadId,
        'is_connected' => 0,
        'connected_account_email' => '',
        'root_folder_name' => $teamLeadName . ' Team Drive',
        'total_storage_bytes' => 15 * 1024 * 1024 * 1024,
        'used_storage_bytes' => 0,
        'client_id' => '',
        'client_secret' => ''
    ];
}

$totalStorage = $settings['total_storage_bytes'] ?: (15 * 1024 * 1024 * 1024);
$usedStorage = $settings['used_storage_bytes'] ?: 0;
$usedPercent = $totalStorage > 0 ? min(100, round(($usedStorage / $totalStorage) * 100, 1)) : 0;
$usedGB = round($usedStorage / (1024 * 1024 * 1024), 2);
$totalGB = round($totalStorage / (1024 * 1024 * 1024), 1);

// Build Breadcrumbs scoped to active Team Lead
$breadcrumbs = [];
$checkFolderId = $currentFolderId;
while ($checkFolderId > 0) {
    $stmt = $db->prepare("SELECT id, name, parent_folder_id FROM drive_items WHERE id = ? AND type = 'folder' AND team_lead_id = ?");
    $stmt->execute([(int)$checkFolderId, $activeTeamLeadId]);
    $f = $stmt->fetch();
    if ($f) {
        array_unshift($breadcrumbs, $f);
        $checkFolderId = (int)$f['parent_folder_id'];
    } else {
        break;
    }
}

// Current folder name
$currentFolderName = !empty($breadcrumbs) ? end($breadcrumbs)['name'] : ($teamLeadName . ' Team Drive');

// Query items inside current folder scoped to active Team Lead
$sql = "
    SELECT d.*, u.name as uploader_name, u.avatar as uploader_avatar, u.emp_id
    FROM drive_items d
    LEFT JOIN users u ON d.uploaded_by = u.id
    WHERE d.is_deleted = 0 AND d.team_lead_id = :team_lead_id
";

if (!empty($searchQuery)) {
    $sql .= " AND d.name LIKE :search";
} else {
    $sql .= " AND d.parent_folder_id = :folder_id";
}

if (!empty($typeFilter)) {
    if ($typeFilter === 'folder') {
        $sql .= " AND d.type = 'folder'";
    } elseif ($typeFilter === 'video') {
        $sql .= " AND (d.mime_type LIKE '%video%' OR d.file_extension IN ('mp4', 'mkv', 'mov', 'webm'))";
    } elseif ($typeFilter === 'image') {
        $sql .= " AND (d.mime_type LIKE '%image%' OR d.file_extension IN ('jpg', 'jpeg', 'png', 'svg', 'webp', 'gif'))";
    } elseif ($typeFilter === 'doc') {
        $sql .= " AND (d.mime_type LIKE '%pdf%' OR d.file_extension IN ('pdf', 'doc', 'docx', 'txt', 'xlsx', 'csv'))";
    } elseif ($typeFilter === 'zip') {
        $sql .= " AND (d.mime_type LIKE '%zip%' OR d.file_extension IN ('zip', 'rar', 'tar', 'gz', 'json', 'sql'))";
    }
}

$sql .= " ORDER BY CASE d.type WHEN 'folder' THEN 1 ELSE 2 END, d.created_at DESC";

$stmt = $db->prepare($sql);
$params = [':team_lead_id' => $activeTeamLeadId];
if (!empty($searchQuery)) {
    $params[':search'] = "%{$searchQuery}%";
} else {
    $params[':folder_id'] = $currentFolderId;
}
$stmt->execute($params);
$items = $stmt->fetchAll();

// Separate folders and files
$folders = array_filter($items, fn($i) => $i['type'] === 'folder');
$files = array_filter($items, fn($i) => $i['type'] === 'file');

if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
?>

<div class="space-y-6" x-data="{ 
    uploadModalOpen: false, 
    newFolderModalOpen: false, 
    settingsModalOpen: false,
    previewModalOpen: false,
    activeFile: null,
    usedGB: '<?= $usedGB ?>',
    usedPercent: <?= $usedPercent ?>,
    notificationMsg: '',
    init() {
        // 24/7 Silent background sync (no page refresh)
        setInterval(() => {
            fetch('?action=drive-auto-sync-json&team_lead_id=<?= $activeTeamLeadId ?>')
                .then(r => r.json())
                .then(data => {
                    if (data && data.status === 'ok' && data.used_bytes) {
                        const used = (data.used_bytes / (1024 * 1024 * 1024)).toFixed(2);
                        const total = (data.total_bytes / (1024 * 1024 * 1024)).toFixed(1);
                        this.usedGB = used;
                        this.usedPercent = Math.min(100, Math.round((data.used_bytes / data.total_bytes) * 100));
                    }
                })
                .catch(() => {});
        }, 120000);
    },
    openPreview(file) {
        this.activeFile = file;
        this.previewModalOpen = true;
    },
    async deleteItemAjax(itemId, parentFolderId, event) {
        if (!confirm('Delete this item from Team Google Drive?')) return;
        const btn = event.currentTarget;
        const card = btn.closest('.drive-item-card') || btn.closest('tr');
        if (card) {
            card.style.transition = 'all 0.3s ease';
            card.style.opacity = '0.4';
            card.style.pointerEvents = 'none';
        }
        try {
            const formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('parent_folder_id', parentFolderId);
            formData.append('is_ajax', '1');
            formData.append('team_lead_id', '<?= $activeTeamLeadId ?>');
            const res = await fetch('?action=drive-delete', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (data.success) {
                if (card) {
                    card.style.transform = 'scale(0.85)';
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 300);
                }
                this.notificationMsg = data.message || 'Item removed successfully';
                setTimeout(() => { this.notificationMsg = ''; }, 4000);
            } else {
                alert(data.message || 'Failed to delete item.');
                if (card) { card.style.opacity = '1'; card.style.pointerEvents = 'auto'; }
            }
        } catch (err) {
            alert('Failed to delete item. Please check network connection.');
            if (card) { card.style.opacity = '1'; card.style.pointerEvents = 'auto'; }
        }
    }
}">
    <!-- Floating Toast Notification (Zero Page Reload) -->
    <div x-show="notificationMsg" x-transition.opacity.duration.300ms class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-bold shadow-sm flex items-center justify-between gap-3" x-cloak>
        <div class="flex items-center gap-2">
            <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600 shrink-0"></i>
            <span x-text="notificationMsg"></span>
        </div>
        <button @click="notificationMsg = ''" class="text-emerald-500 hover:text-emerald-700"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>

    <!-- Professional SaaS Header -->
    <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <!-- Title & Team Info -->
            <div class="flex items-center gap-3.5 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-500 to-blue-600 text-white flex items-center justify-center font-bold shadow-md shadow-indigo-500/20 shrink-0">
                    <i data-lucide="hard-drive" class="w-5 h-5"></i>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="text-xl font-bold text-slate-900 tracking-tight">
                            <?php if (isTL()): ?>
                                My Team Cloud Drive
                            <?php elseif (isAdmin()): ?>
                                Team Cloud Drive (<?= htmlspecialchars($teamLeadName) ?>)
                            <?php else: ?>
                                <?= htmlspecialchars($teamLeadName) ?>'s Team Cloud Drive
                            <?php endif; ?>
                        </h1>

                        <?php if (!empty($settings['is_connected']) && !empty($settings['connected_account_email'])): ?>
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-bold bg-emerald-50 text-emerald-700 px-2.5 py-0.5 rounded-full border border-emerald-200 whitespace-nowrap shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                <?= htmlspecialchars($settings['connected_account_email']) ?> (Connected)
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-bold bg-rose-50 text-rose-700 px-2.5 py-0.5 rounded-full border border-rose-200 whitespace-nowrap shrink-0">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                Cloud Drive Disconnected
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">Centralized cloud storage for <?= htmlspecialchars($teamLeadName) ?>'s team assets, deliverables, videos, and PDFs.</p>
                </div>
            </div>

            <!-- Action Buttons Toolbar (2x2 Grid on Mobile, Flex Row on Desktop) -->
            <div class="grid grid-cols-2 sm:flex sm:items-center gap-2 w-full lg:w-auto shrink-0">
                <a href="?action=drive-download-zip&folder=<?= $currentFolderId ?>&team_lead_id=<?= $activeTeamLeadId ?>" class="justify-center inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-xs font-bold rounded-xl border border-emerald-200 transition shadow-2xs whitespace-nowrap" title="Download all uploaded files in this drive/folder at one go in a single ZIP file">
                    <i data-lucide="download-cloud" class="w-4 h-4 text-emerald-600 shrink-0"></i>
                    <span>Download All</span>
                </a>

                <button @click="newFolderModalOpen = true" class="justify-center inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 transition shadow-2xs whitespace-nowrap">
                    <i data-lucide="folder-plus" class="w-4 h-4 text-slate-500 shrink-0"></i>
                    <span>New Folder</span>
                </button>

                <button @click="uploadModalOpen = true" class="<?= isTL() ? '' : 'col-span-2' ?> justify-center inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition whitespace-nowrap">
                    <i data-lucide="upload-cloud" class="w-4 h-4 shrink-0"></i>
                    <span>Upload Files</span>
                </button>

                <!-- 🔒 STRICT RULE: Only Team Leads can see settings button & register drive. HR cannot register. -->
                <?php if (isTL()): ?>
                    <button @click="settingsModalOpen = true" class="justify-center inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl border border-slate-200 transition whitespace-nowrap" title="Team Google Drive Settings">
                        <i data-lucide="settings" class="w-4 h-4 shrink-0"></i>
                        <span class="sm:hidden">Settings</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (isAdmin() && !empty($allTeamLeads)): ?>
            <!-- Admin Team Drive Switcher Bar -->
            <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-slate-700 flex items-center gap-1.5">
                        <i data-lucide="users" class="w-4 h-4 text-indigo-600"></i> Switch Team Drive:
                    </span>
                    <select onchange="window.location.href='?page=tech-drive&team_lead_id=' + this.value" class="bg-slate-50 border border-slate-200 rounded-xl px-3 py-1.5 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500">
                        <?php foreach ($allTeamLeads as $tl): ?>
                            <option value="<?= $tl['id'] ?>" <?= $tl['id'] == $activeTeamLeadId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tl['name']) ?> (<?= htmlspecialchars($tl['designation'] ?: 'Team Lead') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <span class="text-slate-400 text-[11px]">HR Admin View Access (Read & File Management Mode)</span>
            </div>
        <?php endif; ?>

        <?php if (empty($settings['is_connected'])): ?>
            <!-- Disconnected State Banner -->
            <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div class="flex items-start sm:items-center gap-2.5 text-amber-900">
                    <i data-lucide="cloud-off" class="w-5 h-5 text-amber-600 shrink-0 mt-0.5 sm:mt-0"></i>
                    <div>
                        <strong class="font-bold block sm:inline">Team Cloud Drive is Disconnected.</strong>
                        <span class="text-amber-800">
                            <?php if (isTL()): ?>
                                Your Team Google Drive is not connected yet. Click Settings to link your Google Drive Client ID & Refresh Token.
                            <?php elseif (isAdmin()): ?>
                                This team's Google Drive is not linked yet by Team Lead (<?= htmlspecialchars($teamLeadName) ?>).
                            <?php else: ?>
                                Cloud storage is disconnected. Please approach your Team Lead (<?= htmlspecialchars($teamLeadName) ?>) to register and connect your team's Google Drive.
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php if (isTL()): ?>
                    <button @click="settingsModalOpen = true" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-lg shadow-sm transition inline-flex items-center gap-1 shrink-0 self-start sm:self-auto">
                        <i data-lucide="settings" class="w-3.5 h-3.5"></i> Connect Drive
                    </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($settings['is_connected'])): ?>
            <!-- Integrated Storage Meter Bar (Live Cloud Storage Quota) -->
            <div class="pt-3 border-t border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2 text-slate-600 font-medium">
                    <i data-lucide="cloud" class="w-4 h-4 text-blue-500"></i>
                    <span>Storage: <strong><?= $usedGB ?> GB</strong> used of <?= $totalGB ?> GB Cloud Quota</span>
                    <span class="text-[11px] text-slate-400 font-mono">(<?= $usedPercent ?>%)</span>
                </div>
                <div class="w-full sm:w-64">
                    <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden border border-slate-200">
                        <div class="bg-gradient-to-r from-blue-500 to-indigo-600 h-full rounded-full transition-all duration-500" style="width: <?= $usedPercent ?>%"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Control Toolbar (Search, Filter, Breadcrumb & View Mode) -->
    <div class="bg-white p-3.5 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
        <!-- Breadcrumb Path Navigator -->
        <nav class="flex items-center gap-1.5 text-xs font-semibold text-slate-600 px-1 overflow-x-auto no-scrollbar w-full md:w-auto">
            <a href="?page=tech-drive&folder=0&team_lead_id=<?= $activeTeamLeadId ?>" class="flex items-center gap-1.5 text-indigo-600 hover:text-indigo-800 transition shrink-0">
                <i data-lucide="hard-drive" class="w-3.5 h-3.5"></i> <?= htmlspecialchars(($settings['root_folder_name'] ?? '') ?: ($teamLeadName . ' Drive')) ?>
            </a>

            <?php foreach ($breadcrumbs as $crumb): ?>
                <span class="text-slate-300 shrink-0">&sol;</span>
                <?php if ($crumb['id'] == $currentFolderId): ?>
                    <span class="text-slate-900 font-bold shrink-0"><?= htmlspecialchars($crumb['name']) ?></span>
                <?php else: ?>
                    <a href="?page=tech-drive&folder=<?= $crumb['id'] ?>&team_lead_id=<?= $activeTeamLeadId ?>" class="text-indigo-600 hover:text-indigo-800 transition shrink-0">
                        <?= htmlspecialchars($crumb['name']) ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <!-- Search & Filter Form + View Switcher -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5 w-full md:w-auto">
            <form method="GET" class="flex items-center gap-2 flex-1">
                <input type="hidden" name="page" value="tech-drive">
                <input type="hidden" name="folder" value="<?= $currentFolderId ?>">
                <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
                <?php if (isAdmin()): ?>
                    <input type="hidden" name="team_lead_id" value="<?= $activeTeamLeadId ?>">
                <?php endif; ?>

                <div class="relative flex-1 min-w-0 md:w-56">
                    <i data-lucide="search" class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search files..." class="w-full bg-slate-50 border border-slate-200 rounded-xl pl-8 pr-3 py-1.5 text-xs focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                </div>

                <div class="w-36 shrink-0">
                    <select name="type" onchange="this.form.submit()" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1.5 text-xs font-semibold text-slate-700 focus:ring-2 focus:ring-indigo-500 focus:outline-none">
                        <option value="">All Types</option>
                        <option value="video" <?= $typeFilter === 'video' ? 'selected' : '' ?>>🎬 Videos</option>
                        <option value="image" <?= $typeFilter === 'image' ? 'selected' : '' ?>>🖼️ Images</option>
                        <option value="doc" <?= $typeFilter === 'doc' ? 'selected' : '' ?>>📄 Documents</option>
                        <option value="zip" <?= $typeFilter === 'zip' ? 'selected' : '' ?>>🗜️ ZIP / Code</option>
                        <option value="folder" <?= $typeFilter === 'folder' ? 'selected' : '' ?>>📁 Folders</option>
                    </select>
                </div>

                <?php if (!empty($searchQuery) || !empty($typeFilter)): ?>
                    <a href="?page=tech-drive&folder=<?= $currentFolderId ?>&view=<?= htmlspecialchars($viewMode) ?><?= isAdmin() ? '&team_lead_id='.$activeTeamLeadId : '' ?>" class="text-xs text-rose-600 hover:text-rose-700 font-semibold px-1.5 py-1 shrink-0">Clear</a>
                <?php endif; ?>
            </form>

            <!-- View Switcher -->
            <div class="flex items-center justify-end gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200 shrink-0 self-end sm:self-auto">
                <a href="?page=tech-drive&folder=<?= $currentFolderId ?>&type=<?= urlencode($typeFilter) ?>&search=<?= urlencode($searchQuery) ?>&view=grid<?= isAdmin() ? '&team_lead_id='.$activeTeamLeadId : '' ?>" class="p-1.5 rounded-lg text-xs font-bold transition <?= $viewMode === 'grid' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>" title="Grid View">
                    <i data-lucide="layout-grid" class="w-3.5 h-3.5"></i>
                </a>
                <a href="?page=tech-drive&folder=<?= $currentFolderId ?>&type=<?= urlencode($typeFilter) ?>&search=<?= urlencode($searchQuery) ?>&view=list<?= isAdmin() ? '&team_lead_id='.$activeTeamLeadId : '' ?>" class="p-1.5 rounded-lg text-xs font-bold transition <?= $viewMode === 'list' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-500 hover:text-slate-800' ?>" title="List View">
                    <i data-lucide="list" class="w-3.5 h-3.5"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Content: Empty or Disconnected State -->
    <?php if (empty($items)): ?>
        <div class="bg-white p-10 sm:p-12 rounded-2xl border border-slate-200 text-center shadow-sm">
            <?php if (empty($settings['is_connected'])): ?>
                <div class="w-14 h-14 bg-rose-50 text-rose-500 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="cloud-off" class="w-7 h-7"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-800">Team Cloud Storage is Disconnected</h3>
                <p class="text-xs text-slate-500 mt-1.5 max-w-md mx-auto leading-relaxed">
                    <?php if (isTL()): ?>
                        Link your Google Drive account in Settings with your Client ID, Client Secret, and Refresh Token to enable centralized team storage and automatic file sync for your members.
                    <?php elseif (isAdmin()): ?>
                        Google Cloud Drive storage for <?= htmlspecialchars($teamLeadName) ?>'s team is currently disconnected.
                    <?php else: ?>
                        Google Cloud Drive storage is currently disconnected. Please approach your Team Lead (<?= htmlspecialchars($teamLeadName) ?>) to configure and link the team cloud drive.
                    <?php endif; ?>
                </p>
                <?php if (isTL()): ?>
                    <div class="flex items-center justify-center gap-2.5 mt-5">
                        <button @click="settingsModalOpen = true" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition inline-flex items-center gap-1.5">
                            <i data-lucide="settings" class="w-4 h-4"></i> Open Settings to Connect
                        </button>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="w-14 h-14 bg-indigo-50 text-indigo-500 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <i data-lucide="folder-open" class="w-7 h-7"></i>
                </div>
                <h3 class="text-sm font-bold text-slate-800">This folder is empty</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Upload videos, screenshots, PDFs, or create subfolders to organize your deliverables.</p>
                <div class="flex items-center justify-center gap-2.5 mt-5">
                    <button @click="uploadModalOpen = true" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition inline-flex items-center gap-1.5">
                        <i data-lucide="upload-cloud" class="w-4 h-4"></i> Upload File
                    </button>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- VIEW 1: GRID VIEW -->
    <?php if ($viewMode === 'grid' && !empty($items)): ?>
        <!-- FOLDERS SECTION -->
        <?php if (!empty($folders)): ?>
            <div>
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 px-1">Folders (<?= count($folders) ?>)</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3.5">
                    <?php foreach ($folders as $f): ?>
                        <div class="drive-item-card group bg-white p-4 rounded-xl border border-slate-200 hover:border-indigo-300 hover:shadow-md transition flex items-center justify-between gap-3">
                            <a href="?page=tech-drive&folder=<?= $f['id'] ?><?= isAdmin() ? '&team_lead_id='.$activeTeamLeadId : '' ?>" class="flex items-center gap-3 min-w-0 flex-1">
                                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center shrink-0 group-hover:scale-105 transition">
                                    <i data-lucide="folder" class="w-5 h-5 fill-amber-400 text-amber-500"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-xs font-bold text-slate-800 truncate group-hover:text-indigo-600 transition"><?= htmlspecialchars($f['name']) ?></h4>
                                    <span class="text-[10px] text-slate-400 font-mono"><?= formatDate($f['created_at']) ?></span>
                                </div>
                            </a>

                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition shrink-0">
                                <a href="?action=drive-download-zip&folder=<?= $f['id'] ?>&team_lead_id=<?= $activeTeamLeadId ?>" class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition" title="Download Folder as ZIP">
                                    <i data-lucide="download-cloud" class="w-3.5 h-3.5"></i>
                                </a>
                                <button @click="deleteItemAjax(<?= $f['id'] ?>, <?= $currentFolderId ?>, $event)" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Delete Folder">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- FILES SECTION -->
        <?php if (!empty($files)): ?>
            <div>
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 px-1">Files (<?= count($files) ?>)</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <?php foreach ($files as $file): ?>
                        <?php 
                        $ext = strtolower($file['file_extension']);
                        $isVideo = in_array($ext, ['mp4', 'mkv', 'mov', 'webm']);
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'svg', 'webp', 'gif']);
                        $isPdf = ($ext === 'pdf');
                        $isCodeOrZip = in_array($ext, ['zip', 'rar', 'tar', 'gz', 'json', 'sql', 'js', 'php']);
                        
                        $fileJson = htmlspecialchars(json_encode([
                            'id' => $file['id'],
                            'name' => $file['name'],
                            'google_file_id' => $file['google_file_id'],
                            'web_view_link' => $file['web_view_link'],
                            'file_extension' => $ext,
                            'mime_type' => $file['mime_type'],
                            'size' => formatBytes($file['size_bytes'])
                        ]));
                        ?>
                        <div class="drive-item-card group bg-white rounded-2xl border border-slate-200 overflow-hidden hover:shadow-lg hover:border-indigo-300 transition flex flex-col justify-between">
                            <!-- Thumbnail / Visual Preview Header -->
                            <div class="h-36 bg-slate-50 relative flex items-center justify-center overflow-hidden border-b border-slate-100 cursor-pointer" @click="openPreview(<?= $fileJson ?>)">
                                <?php if ($isImage && !empty($file['thumbnail_url'])): ?>
                                    <img src="<?= htmlspecialchars($file['thumbnail_url']) ?>" alt="Thumbnail" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                                <?php elseif ($isVideo): ?>
                                    <div class="w-12 h-12 rounded-full bg-indigo-600/90 text-white flex items-center justify-center shadow-lg group-hover:scale-110 transition">
                                        <i data-lucide="play" class="w-6 h-6 ml-0.5 fill-white"></i>
                                    </div>
                                    <span class="absolute bottom-2 right-2 px-1.5 py-0.5 bg-slate-900/75 text-white text-[10px] font-bold rounded font-mono">VIDEO</span>
                                <?php elseif ($isPdf): ?>
                                    <i data-lucide="file-text" class="w-14 h-14 text-rose-500 stroke-[1.5]"></i>
                                    <span class="absolute bottom-2 right-2 px-1.5 py-0.5 bg-rose-50 text-rose-600 border border-rose-200 text-[10px] font-bold rounded font-mono">PDF</span>
                                <?php elseif ($isCodeOrZip): ?>
                                    <i data-lucide="file-archive" class="w-14 h-14 text-amber-500 stroke-[1.5]"></i>
                                    <span class="absolute bottom-2 right-2 px-1.5 py-0.5 bg-amber-50 text-amber-600 border border-amber-200 text-[10px] font-bold rounded font-mono">ARCHIVE</span>
                                <?php else: ?>
                                    <i data-lucide="file" class="w-14 h-14 text-slate-400 stroke-[1.5]"></i>
                                    <span class="absolute bottom-2 right-2 px-1.5 py-0.5 bg-slate-100 text-slate-600 border border-slate-200 text-[10px] font-bold rounded font-mono uppercase"><?= $ext ?: 'FILE' ?></span>
                                <?php endif; ?>

                                <?php if (!empty($file['google_file_id'])): ?>
                                    <span class="absolute top-2 left-2 px-1.5 py-0.5 bg-white/90 backdrop-blur-sm text-indigo-700 text-[9px] font-bold rounded-md shadow-2xs flex items-center gap-1 border border-indigo-100">
                                        <span class="w-1 h-1 rounded-full bg-indigo-500"></span> Google Synced
                                    </span>
                                <?php endif; ?>
                            </div>

                            <!-- Meta Info & Controls -->
                            <div class="p-3.5 space-y-2 flex-1 flex flex-col justify-between">
                                <div>
                                    <h4 class="text-xs font-bold text-slate-800 line-clamp-2 hover:text-indigo-600 transition cursor-pointer" @click="openPreview(<?= $fileJson ?>)" title="<?= htmlspecialchars($file['name']) ?>">
                                        <?= htmlspecialchars($file['name']) ?>
                                    </h4>
                                    <div class="flex items-center justify-between text-[10px] text-slate-400 mt-1 font-mono">
                                        <span><?= formatBytes($file['size_bytes']) ?></span>
                                        <span><?= formatDate($file['created_at']) ?></span>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 min-w-0" title="Uploaded by <?= htmlspecialchars($file['uploader_name'] ?? 'System') ?>">
                                        <div class="w-5 h-5 rounded-full bg-slate-100 flex items-center justify-center text-[9px] font-bold text-slate-600 shrink-0">
                                            <?= strtoupper(substr($file['uploader_name'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <span class="text-[10px] text-slate-500 truncate max-w-[80px]"><?= htmlspecialchars($file['uploader_name'] ?? 'System') ?></span>
                                    </div>

                                    <div class="flex items-center gap-1">
                                        <button @click="openPreview(<?= $fileJson ?>)" class="p-1 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Preview File">
                                            <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                        </button>
                                        <a href="<?= !empty($file['google_file_id']) ? ('https://drive.google.com/uc?id=' . $file['google_file_id'] . '&export=download') : ('?action=drive-stream&id=' . $file['id'] . '&team_lead_id=' . $activeTeamLeadId) ?>" download class="p-1 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition" title="Download">
                                            <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                        </a>
                                        <button @click="deleteItemAjax(<?= $file['id'] ?>, <?= $currentFolderId ?>, $event)" class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Delete">
                                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- VIEW 2: LIST VIEW -->
    <?php if ($viewMode === 'list' && !empty($items)): ?>
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="overflow-x-auto no-scrollbar">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3 min-w-[280px]">Name</th>
                            <th class="px-4 py-3 min-w-[130px]">Uploaded By</th>
                            <th class="px-4 py-3 min-w-[90px]">Size</th>
                            <th class="px-4 py-3 min-w-[110px]">Date</th>
                            <th class="px-4 py-3 text-right min-w-[120px]">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($items as $item): ?>
                            <?php 
                            $isFolder = ($item['type'] === 'folder');
                            $ext = strtolower($item['file_extension']);
                            $fileJson = htmlspecialchars(json_encode([
                                'id' => $item['id'],
                                'name' => $item['name'],
                                'google_file_id' => $item['google_file_id'],
                                'web_view_link' => $item['web_view_link'],
                                'file_extension' => $ext,
                                'mime_type' => $item['mime_type'],
                                'size' => formatBytes($item['size_bytes'])
                            ]));
                            ?>
                            <tr class="hover:bg-slate-50 transition">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2.5">
                                        <?php if ($isFolder): ?>
                                            <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-500 flex items-center justify-center shrink-0">
                                                <i data-lucide="folder" class="w-4 h-4 fill-amber-400 text-amber-500"></i>
                                            </div>
                                            <a href="?page=tech-drive&folder=<?= $item['id'] ?><?= isAdmin() ? '&team_lead_id='.$activeTeamLeadId : '' ?>" class="font-bold text-slate-800 hover:text-indigo-600 transition truncate max-w-sm">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </a>
                                        <?php else: ?>
                                            <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center shrink-0">
                                                <i data-lucide="file" class="w-4 h-4"></i>
                                            </div>
                                            <span @click="openPreview(<?= $fileJson ?>)" class="font-bold text-slate-800 hover:text-indigo-600 transition cursor-pointer truncate max-w-sm">
                                                <?= htmlspecialchars($item['name']) ?>
                                            </span>
                                            <?php if (!empty($item['google_file_id'])): ?>
                                                <span class="px-1.5 py-0.2 text-[9px] bg-indigo-50 text-indigo-600 rounded font-bold">Synced</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">
                                    <?= htmlspecialchars($item['uploader_name'] ?? 'System') ?>
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] text-slate-500">
                                    <?= $isFolder ? '—' : formatBytes($item['size_bytes']) ?>
                                </td>
                                <td class="px-4 py-3 font-mono text-[11px] text-slate-400">
                                    <?= formatDate($item['created_at']) ?>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <?php if ($isFolder): ?>
                                            <a href="?action=drive-download-zip&folder=<?= $item['id'] ?>&team_lead_id=<?= $activeTeamLeadId ?>" class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition" title="Download ZIP">
                                                <i data-lucide="download-cloud" class="w-4 h-4"></i>
                                            </a>
                                        <?php else: ?>
                                            <button @click="openPreview(<?= $fileJson ?>)" class="p-1.5 text-slate-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Preview">
                                                <i data-lucide="eye" class="w-4 h-4"></i>
                                            </button>
                                            <a href="<?= !empty($item['google_file_id']) ? ('https://drive.google.com/uc?id=' . $item['google_file_id'] . '&export=download') : ('?action=drive-stream&id=' . $item['id'] . '&team_lead_id=' . $activeTeamLeadId) ?>" download class="p-1.5 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition" title="Download">
                                                <i data-lucide="download" class="w-4 h-4"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button @click="deleteItemAjax(<?= $item['id'] ?>, <?= $currentFolderId ?>, $event)" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Delete">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- MODAL 1: Upload Modal -->
    <div x-show="uploadModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="uploadModalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        <i data-lucide="upload-cloud" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Upload Files to Team Drive</h3>
                        <p class="text-[11px] text-slate-500">Destination: <strong><?= htmlspecialchars($currentFolderName) ?></strong></p>
                    </div>
                </div>
                <button @click="uploadModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form action="?action=drive-upload" method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="parent_folder_id" value="<?= $currentFolderId ?>">
                <input type="hidden" name="team_lead_id" value="<?= $activeTeamLeadId ?>">

                <div class="border-2 border-dashed border-slate-200 rounded-2xl p-6 text-center hover:border-indigo-400 transition bg-slate-50/50">
                    <i data-lucide="file-up" class="w-8 h-8 text-indigo-500 mx-auto mb-2"></i>
                    <p class="text-xs font-bold text-slate-800">Select Videos, Photos, PDFs, or ZIP files</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Stored directly in <?= htmlspecialchars($teamLeadName) ?>'s Team Google Drive</p>
                    <input type="file" name="files[]" multiple required class="mt-4 block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 cursor-pointer">
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="uploadModalOpen = false" class="px-3.5 py-1.5 text-xs font-semibold text-slate-500">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5">
                        <i data-lucide="cloud-upload" class="w-4 h-4"></i> Upload Files
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 2: Create Folder Modal -->
    <div x-show="newFolderModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="newFolderModalOpen = false" class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                        <i data-lucide="folder-plus" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Create New Folder</h3>
                        <p class="text-[11px] text-slate-500">Inside <strong><?= htmlspecialchars($currentFolderName) ?></strong></p>
                    </div>
                </div>
                <button @click="newFolderModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form action="?action=drive-create-folder" method="POST" class="space-y-3.5">
                <input type="hidden" name="parent_folder_id" value="<?= $currentFolderId ?>">
                <input type="hidden" name="team_lead_id" value="<?= $activeTeamLeadId ?>">

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Folder Name</label>
                    <input type="text" name="folder_name" required placeholder="e.g. Sprint 2 Videos, Architecture Diagrams" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold">
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="newFolderModalOpen = false" class="px-3.5 py-1.5 text-xs font-semibold text-slate-500">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition">
                        Create Folder
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL 3: In-Dashboard Media Preview Modal (Native Google Drive Viewer) -->
    <div x-show="previewModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/85 backdrop-blur-md flex items-center justify-center p-2 sm:p-4" x-cloak>
        <div @click.away="previewModalOpen = false" class="bg-white rounded-2xl max-w-5xl w-full p-4 sm:p-5 shadow-2xl border border-slate-200 flex flex-col h-[90vh]">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3 shrink-0">
                <div class="flex items-center gap-2.5 overflow-hidden">
                    <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold shrink-0">
                        <i data-lucide="eye" class="w-4 h-4"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 truncate" x-text="activeFile ? activeFile.name : 'Media Preview'"></h3>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <template x-if="activeFile && activeFile.google_file_id">
                        <a :href="'https://drive.google.com/file/d/' + activeFile.google_file_id + '/view?usp=sharing'" target="_blank" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold flex items-center gap-1.5 transition">
                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i> Open in Google Drive
                        </a>
                    </template>
                    <a :href="activeFile ? (activeFile.google_file_id ? ('https://drive.google.com/uc?id=' + activeFile.google_file_id + '&export=download') : ('?action=drive-stream&id=' + activeFile.id + '&team_lead_id=<?= $activeTeamLeadId ?>')) : '#'" download class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-sm transition">
                        <i data-lucide="download" class="w-3.5 h-3.5"></i> Download
                    </a>
                    <button @click="previewModalOpen = false" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-100 transition"><i data-lucide="x" class="w-5 h-5"></i></button>
                </div>
            </div>

            <!-- Preview Body (Native Google Drive Interface) -->
            <div class="flex-1 overflow-hidden flex items-center justify-center bg-slate-900 rounded-xl relative w-full h-full">
                <!-- If Google Drive File: Official Google Drive Native Player/Viewer -->
                <template x-if="activeFile && activeFile.google_file_id">
                    <iframe :src="'https://drive.google.com/file/d/' + activeFile.google_file_id + '/preview'" class="w-full h-full rounded-xl border-0 bg-transparent" allow="autoplay; encrypted-media; fullscreen" allowfullscreen></iframe>
                </template>

                <!-- Local Non-Google File Fallbacks -->
                <template x-if="activeFile && !activeFile.google_file_id && ['mp4', 'mkv', 'mov', 'webm'].includes(activeFile.file_extension)">
                    <video controls autoplay class="max-h-[75vh] w-full rounded-lg" :src="'?action=drive-stream&id=' + activeFile.id + '&team_lead_id=<?= $activeTeamLeadId ?>'"></video>
                </template>

                <template x-if="activeFile && !activeFile.google_file_id && ['jpg', 'jpeg', 'png', 'svg', 'webp', 'gif'].includes(activeFile.file_extension)">
                    <img :src="'?action=drive-stream&id=' + activeFile.id + '&team_lead_id=<?= $activeTeamLeadId ?>'" class="max-h-[75vh] max-w-full object-contain rounded-lg shadow-lg" alt="Preview Image">
                </template>

                <template x-if="activeFile && !activeFile.google_file_id && activeFile.file_extension === 'pdf'">
                    <iframe :src="'?action=drive-stream&id=' + activeFile.id + '&team_lead_id=<?= $activeTeamLeadId ?>'" class="w-full h-full rounded-xl border-0 bg-white"></iframe>
                </template>

                <template x-if="activeFile && !activeFile.google_file_id && !['mp4', 'mkv', 'mov', 'webm', 'jpg', 'jpeg', 'png', 'svg', 'webp', 'gif', 'pdf'].includes(activeFile.file_extension)">
                    <div class="text-center p-8 text-white space-y-3">
                        <i data-lucide="file-archive" class="w-12 h-12 text-indigo-400 mx-auto"></i>
                        <p class="text-sm font-semibold" x-text="activeFile.name"></p>
                        <p class="text-xs text-slate-400">Direct inline preview not supported for this file type.</p>
                        <a :href="'?action=drive-stream&id=' + activeFile.id + '&team_lead_id=<?= $activeTeamLeadId ?>'" download class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold">
                            <i data-lucide="download" class="w-4 h-4"></i> Download File
                        </a>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- MODAL 4: Team Google Drive Settings & OAuth Modal (Team Leads ONLY) -->
    <?php if (isTL()): ?>
    <div x-show="settingsModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="settingsModalOpen = false" class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 text-white flex items-center justify-center font-bold shadow-md">
                        <i data-lucide="hard-drive" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Register Team Google Drive</h3>
                        <p class="text-[11px] text-slate-500">Only Team Leads can configure and manage team cloud drive</p>
                    </div>
                </div>
                <button @click="settingsModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <!-- OAuth Form -->
            <form action="?action=drive-oauth-start" method="POST" class="space-y-3.5">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Google OAuth Client ID</label>
                    <input type="text" name="client_id" required value="<?= htmlspecialchars($settings['client_id'] ?: '') ?>" placeholder="xxxx.apps.googleusercontent.com" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Google OAuth Client Secret</label>
                    <input type="password" name="client_secret" required value="<?= htmlspecialchars($settings['client_secret'] ?: '') ?>" placeholder="••••••••••••••••" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-mono">
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Team Root Folder Name in Drive</label>
                    <input type="text" name="root_folder_name" value="<?= htmlspecialchars(($settings['root_folder_name'] ?? '') ?: ($teamLeadName . '_Team_Drive')) ?>" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold">
                </div>

                <!-- Redirect URI Helper Box -->
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-1.5">
                    <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Google Cloud Console Authorized Redirect URI:</span>
                    <?php
                    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
                    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
                    $callbackUrl = $protocol . $host . '/?action=drive-oauth-callback';
                    ?>
                    <div class="flex items-center gap-2">
                        <input type="text" readonly value="<?= htmlspecialchars($callbackUrl) ?>" class="bg-white border border-slate-200 rounded-lg px-2.5 py-1 text-[11px] font-mono text-slate-600 w-full select-all">
                    </div>
                </div>

                <!-- Permanent Token Assurance Notice -->
                <div class="p-3 bg-indigo-50/70 rounded-xl border border-indigo-100 text-indigo-900 text-[11px] flex items-start gap-2">
                    <i data-lucide="shield-check" class="w-4 h-4 text-indigo-600 shrink-0 mt-0.5"></i>
                    <div>
                        <strong class="block font-bold">1-Time Permanent Connection:</strong>
                        <span>Once TL authorizes this Google account, Google issues a permanent Refresh Token. Your team members can immediately store and access deliverables!</span>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <?php if ($settings['is_connected']): ?>
                        <a href="?action=drive-disconnect" onclick="return confirm('Disconnect your Team Google Drive account?');" class="text-xs text-rose-600 hover:text-rose-700 font-bold">
                            Disconnect Drive
                        </a>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="settingsModalOpen = false" class="px-3.5 py-1.5 text-xs font-semibold text-slate-500">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5">
                            <i data-lucide="link-2" class="w-4 h-4"></i> Sign in & Connect Google Drive
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>
