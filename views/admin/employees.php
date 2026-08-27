<!-- views/admin/employees.php -->
<?php
$user = authUser();
$db = getDBConnection();

$filterRole = $_GET['filter_role'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');

// Fetch all HR managers (Head HR & HR Support)
$hrManagers = $db->query("SELECT id, name, designation FROM users WHERE role = 'admin' AND status = 'active' ORDER BY name ASC")->fetchAll();

// Fetch all team leads and TL Supports
$tls = $db->query("SELECT id, name, designation FROM users WHERE role = 'team_lead' AND status = 'active' ORDER BY name ASC")->fetchAll();

$officeLocations = getOfficeLocations();

// Fetch Master Roles
$rolesMaster = $db->query("SELECT * FROM roles_master ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Eligible Reporting Authorities only (Admin, TLs, and Custom Roles with authority)
$reportingAuthorities = $db->query("
    SELECT u.id, u.name, u.designation, u.role, u.department_name
    FROM users u
    LEFT JOIN roles_master rm ON LOWER(u.designation) = LOWER(rm.name)
    WHERE u.status = 'active' AND (u.role IN ('admin', 'team_lead') OR rm.can_be_reporting_authority = 1)
    ORDER BY CASE WHEN u.role = 'admin' THEN 1 WHEN u.role = 'team_lead' THEN 2 ELSE 3 END, u.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch available active employees/interns for team assignment
$availableStaff = $db->query("
    SELECT u.id, u.name, u.designation, u.employment_type, u.reporting_tl_id, tl.name as current_tl_name
    FROM users u
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id
    WHERE u.status = 'active' AND u.role = 'employee'
    ORDER BY u.name ASC
")->fetchAll();

// Count team members for each TL
$tlMemberCounts = $db->query("SELECT reporting_tl_id, COUNT(*) as cnt, GROUP_CONCAT(name SEPARATOR ', ') as member_names FROM users WHERE status = 'active' AND reporting_tl_id IS NOT NULL GROUP BY reporting_tl_id")->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC);

// Query employees, team leads, and HR/Admin
$sql = "
    SELECT u.*, tl.name as tl_name, tl.designation as tl_designation
    FROM users u
    LEFT JOIN users tl ON u.reporting_tl_id = tl.id
    WHERE u.status = 'active'
";

if (!empty($filterRole)) {
    if ($filterRole === 'admin') {
        $sql .= " AND u.role = 'admin'";
    } elseif ($filterRole === 'interns') {
        $sql .= " AND u.employment_type IN ('intern_paid', 'intern_unpaid')";
    } elseif ($filterRole === 'team_lead') {
        $sql .= " AND u.role = 'team_lead'";
    } elseif ($filterRole === 'full_time') {
        $sql .= " AND (u.employment_type = 'full_time' OR u.employment_type IS NULL) AND u.role != 'team_lead' AND u.role != 'admin'";
    }
}

if (!empty($searchQuery)) {
    $sql .= " AND (u.name LIKE :search OR u.emp_id LIKE :search OR u.designation LIKE :search OR u.email LIKE :search)";
}

$sql .= " ORDER BY CASE WHEN u.role = 'admin' THEN 1 WHEN u.role = 'team_lead' THEN 2 ELSE 3 END, u.created_at ASC";
$stmt = $db->prepare($sql);
$params = [];
if (!empty($searchQuery)) $params[':search'] = "%{$searchQuery}%";
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Consolidated Stats in 1 Single Query (Including Total Workforce)
$empStats = $db->query("
    SELECT 
        COUNT(CASE WHEN status = 'active' THEN 1 END) as totalMembers,
        COUNT(CASE WHEN status = 'active' AND role = 'admin' THEN 1 END) as totalHR,
        COUNT(CASE WHEN status = 'active' AND role = 'team_lead' THEN 1 END) as totalTLs,
        COUNT(CASE WHEN status = 'active' AND (employment_type = 'full_time' OR employment_type IS NULL) AND role != 'admin' THEN 1 END) as totalFullTime,
        COUNT(CASE WHEN status = 'active' AND employment_type IN ('intern_paid', 'intern_unpaid') THEN 1 END) as totalInterns
    FROM users
")->fetch() ?: [];

$totalMembers = (int)($empStats['totalMembers'] ?? 0);
$totalHR = (int)($empStats['totalHR'] ?? 0);
$totalTLs = (int)($empStats['totalTLs'] ?? 0);
$totalFullTime = (int)($empStats['totalFullTime'] ?? 0);
$totalInterns = (int)($empStats['totalInterns'] ?? 0);
?>

<script>
    window.allEmployeesData = <?= json_encode($employees, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.masterRolesData = <?= json_encode($rolesMaster, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<div class="space-y-6" x-data="{ 
    onboardModalOpen: false, 
    viewModalOpen: false, 
    reassignModalOpen: false,
    manageTeamModalOpen: false,
    selectedEmp: null, 
    editMode: false,
    targetTlId: 0,
    targetTlName: '',
    targetTlMembersCount: 0,
    targetTlMemberNames: '',
    manageTlId: 0,
    manageTlName: '',
    manageAssignedIds: [],
    rolesList: (window.masterRolesData && window.masterRolesData.length) ? window.masterRolesData : [],
    roleDropdownOpen: false,
    addRolePopupOpen: false,
    newRoleTitle: '',
    newRoleAuth: false,
    selectedDesig: '',
    async submitNewRole() {
        if (!this.newRoleTitle || !this.newRoleTitle.trim()) return;
        const formData = new FormData();
        formData.append('name', this.newRoleTitle.trim());
        formData.append('can_be_reporting_authority', this.newRoleAuth ? '1' : '0');
        formData.append('ajax', '1');

        try {
            const res = await fetch('?action=create-role', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const data = await res.json();
            if (data && data.success && data.role) {
                this.rolesList.push(data.role);
                this.selectedDesig = data.role.name;
                this.newRoleTitle = '';
                this.newRoleAuth = false;
                this.addRolePopupOpen = false;
                this.roleDropdownOpen = false;
            } else {
                alert(data && data.error ? data.error : 'Failed to add role');
            }
        } catch(e) {
            alert('Error creating role');
        }
    },
    async deleteRole(id, e) {
        if (e) e.stopPropagation();
        if (!confirm('Are you sure you want to delete this role?')) return;
        const formData = new FormData();
        formData.append('role_id', id);
        formData.append('ajax', '1');

        try {
            const res = await fetch('?action=delete-role', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const data = await res.json();
            if (data && data.success) {
                this.rolesList = this.rolesList.filter(r => Number(r.id) !== Number(id));
                if (this.selectedDesig && !this.rolesList.some(r => r.name === this.selectedDesig)) {
                    this.selectedDesig = '';
                }
            } else {
                alert(data && data.error ? data.error : 'Failed to delete role');
            }
        } catch(e) {
            alert('Error deleting role');
        }
    },
    openDetails(empId) {
        const found = (window.allEmployeesData || []).find(x => Number(x.id) === Number(empId));
        if (found) {
            this.selectedEmp = JSON.parse(JSON.stringify(found));
            this.editMode = false;
            this.viewModalOpen = true;
        }
    },
    openEdit(empId) {
        const found = (window.allEmployeesData || []).find(x => Number(x.id) === Number(empId));
        if (found) {
            this.selectedEmp = JSON.parse(JSON.stringify(found));
            this.editMode = true;
            this.viewModalOpen = true;
        }
    },
    openReassignAndTerminate(id, name, cnt, names) {
        this.targetTlId = id;
        this.targetTlName = name;
        this.targetTlMembersCount = cnt;
        this.targetTlMemberNames = names;
        this.reassignModalOpen = true;
    },
    openManageTeam(id, name, assignedIds) {
        this.manageTlId = id;
        this.manageTlName = name;
        this.manageAssignedIds = assignedIds || [];
        this.manageTeamModalOpen = true;
    }
}">
    <!-- Top Action Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold shrink-0">
                <i data-lucide="users" class="w-5 h-5"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Workforce & Roles Directory</h1>
                <p class="text-xs text-slate-500 mt-0.5">Manage Head HR, HR Support, Team Leads, TL Support, and Team Members hierarchy.</p>
            </div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" @click="onboardModalOpen = true" class="px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white text-xs font-bold rounded-xl shadow-sm transition flex items-center gap-1.5 cursor-pointer">
                <i data-lucide="user-plus" class="w-4 h-4"></i> Onboard Member
            </button>
        </div>
    </div>

    <!-- 4 Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Workforce</span>
                <div class="text-2xl font-extrabold text-slate-900 mt-0.5"><?= $totalMembers ?></div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                <i data-lucide="users" class="w-4 h-4"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">HR Leadership</span>
                <div class="text-2xl font-extrabold text-purple-700 mt-0.5"><?= $totalHR ?></div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Team Leads / Support</span>
                <div class="text-2xl font-extrabold text-blue-700 mt-0.5"><?= $totalTLs ?></div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                <i data-lucide="user-check" class="w-4 h-4"></i>
            </div>
        </div>

        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Staff & Interns</span>
                <div class="text-2xl font-extrabold text-emerald-700 mt-0.5"><?= $totalFullTime + $totalInterns ?></div>
            </div>
            <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i data-lucide="briefcase" class="w-4 h-4"></i>
            </div>
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm">
        <form method="GET" action="" class="flex flex-col sm:flex-row items-center justify-between gap-3">
            <input type="hidden" name="page" value="admin-employees">
            
            <div class="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto pb-1 sm:pb-0">
                <a href="?page=admin-employees" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= empty($filterRole) ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">All Staff</a>
                <a href="?page=admin-employees&filter_role=admin" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $filterRole === 'admin' ? 'bg-purple-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">HR Team</a>
                <a href="?page=admin-employees&filter_role=team_lead" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $filterRole === 'team_lead' ? 'bg-blue-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Team Leads</a>
                <a href="?page=admin-employees&filter_role=full_time" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $filterRole === 'full_time' ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Full-Time</a>
                <a href="?page=admin-employees&filter_role=interns" class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $filterRole === 'interns' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">Interns</a>
            </div>

            <div class="relative w-full sm:w-64">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
                <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search name, designation, email..." class="w-full pl-9 pr-4 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs focus:ring-2 focus:ring-purple-500 focus:bg-white">
            </div>
        </form>
    </div>

    <!-- Clean Sleek Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden w-full">
        <div class="overflow-x-auto no-scrollbar">
            <table class="w-full text-left text-xs text-slate-600 border-collapse min-w-[780px]">
                <thead class="bg-slate-50 text-slate-500 text-[11px] uppercase tracking-wider font-bold border-b border-slate-200">
                    <tr>
                        <th class="py-3.5 pl-5 pr-3 min-w-[220px]">Employee Details</th>
                        <th class="py-3.5 px-3 min-w-[150px] whitespace-nowrap">Role & Designation</th>
                        <th class="py-3.5 px-3 min-w-[160px] whitespace-nowrap">Reporting Manager</th>
                        <th class="py-3.5 px-3 min-w-[130px] whitespace-nowrap">Employment Type</th>
                        <th class="py-3.5 pl-2 pr-5 text-right min-w-[120px] whitespace-nowrap">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                <?php if (empty($employees)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-10 text-slate-400">
                            <i data-lucide="users" class="w-8 h-8 mx-auto text-slate-300 mb-1"></i>
                            <p class="text-xs font-semibold text-slate-600">No members found matching your search</p>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($employees as $e): 
                    $desig = $e['designation'] ?? '';
                    $isHRSupport = (stripos($desig, 'hr support') !== false);
                    $isHeadHR = (stripos($desig, 'head hr') !== false || stripos($desig, 'hr director') !== false);
                    $isTLSupport = (stripos($desig, 'tl support') !== false);
                    $isTeamLead = (stripos($desig, 'team lead') !== false || $e['role'] === 'team_lead');
                ?>
                    <tr class="hover:bg-slate-50/80 transition <?= $e['role'] === 'admin' ? 'bg-indigo-50/20' : '' ?>">
                        <!-- Employee Profile -->
                        <td class="py-3.5 pl-5 pr-3 align-middle">
                            <div class="flex items-center gap-3 overflow-hidden cursor-pointer group" @click="openDetails(<?= (int)$e['id'] ?>)">
                                <img src="<?= $e['avatar'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($e['name']) ?>" class="w-9 h-9 rounded-full object-cover ring-1 ring-slate-200 shrink-0 group-hover:ring-indigo-400 transition" alt="Avatar">
                                <div class="min-w-0 flex-1">
                                    <div class="font-bold text-slate-900 text-xs truncate flex items-center gap-1.5 group-hover:text-indigo-600 transition">
                                        <span class="truncate"><?= htmlspecialchars($e['name']) ?></span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 truncate">
                                        <?= htmlspecialchars($e['email']) ?>
                                    </div>
                                </div>
                            </div>
                        </td>

                        <!-- Role & Designation Badges (Clean Single-Line Badges) -->
                        <td class="py-3.5 px-3 align-middle whitespace-nowrap">
                            <div class="inline-flex items-center">
                                <?php if ($isHeadHR): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-full text-[11px] font-extrabold shadow-2xs tracking-wide whitespace-nowrap">
                                        👑 Head HR
                                    </span>
                                <?php elseif ($isHRSupport): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50 text-indigo-700 border border-indigo-200 rounded-full text-[11px] font-extrabold shadow-2xs whitespace-nowrap">
                                        💼 HR Support
                                    </span>
                                <?php elseif ($isTLSupport): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-sky-50 text-sky-700 border border-sky-200 rounded-full text-[11px] font-extrabold shadow-2xs whitespace-nowrap">
                                        🤝 TL Support
                                    </span>
                                <?php elseif ($e['role'] === 'team_lead'): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-700 border border-blue-200 rounded-full text-[11px] font-extrabold shadow-2xs whitespace-nowrap">
                                        👔 Team Lead
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 text-slate-700 border border-slate-200 rounded-full text-[11px] font-semibold whitespace-nowrap">
                                        💻 <?= htmlspecialchars($desig ?: 'Employee') ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </td>

                        <!-- Reporting Manager -->
                        <td class="py-3.5 px-3 align-middle text-xs">
                            <?php if ($isHeadHR && empty($e['reporting_tl_id'])): ?>
                                <span class="inline-flex items-center gap-1 text-purple-700 font-bold">
                                    <i data-lucide="crown" class="w-3.5 h-3.5 text-purple-600"></i> Apex Authority
                                </span>
                            <?php elseif (!empty($e['tl_name'])): ?>
                                <div class="text-slate-800">
                                    <span class="text-slate-400 text-[10px] block uppercase font-bold">Reports To:</span>
                                    <span class="font-bold text-indigo-700"><?= htmlspecialchars($e['tl_name']) ?></span>
                                    <span class="text-[10px] text-slate-400">(<?= htmlspecialchars($e['tl_designation'] ?: 'Lead') ?>)</span>
                                </div>
                            <?php else: ?>
                                <span class="text-slate-400 font-medium italic">Direct / None</span>
                            <?php endif; ?>
                        </td>

                        <!-- Employment Type Badge -->
                        <td class="py-3.5 px-3 align-middle">
                            <?php if ($e['role'] === 'admin'): ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-purple-50 text-purple-700 border border-purple-200">
                                    Executive HR
                                </span>
                            <?php else: ?>
                                <?= getEmploymentBadge($e['employment_type'] ?? 'full_time', (float)($e['salary_basic'] ?? 0)) ?>
                            <?php endif; ?>
                        </td>

                        <!-- Sleek Actions -->
                        <td class="py-3.5 pl-2 pr-5 align-middle text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1.5">
                                <?php if ($e['role'] === 'team_lead'): ?>
                                    <?php
                                     $assignedStaffForThisTL = [];
                                     foreach ($availableStaff as $s) {
                                         if ((int)$s['reporting_tl_id'] === (int)$e['id']) {
                                             $assignedStaffForThisTL[] = (int)$s['id'];
                                         }
                                     }
                                    ?>
                                    <button type="button" @click="openManageTeam(<?= (int)$e['id'] ?>, '<?= addslashes($e['name']) ?>', <?= htmlspecialchars(json_encode($assignedStaffForThisTL)) ?>)" class="px-2.5 py-1 text-purple-700 hover:text-purple-900 bg-purple-50 hover:bg-purple-100 border border-purple-200 rounded-lg transition inline-flex items-center gap-1 text-xs font-bold shadow-2xs cursor-pointer" title="Assign & Manage Team">
                                        <i data-lucide="users" class="w-3.5 h-3.5"></i> Team
                                    </button>
                                <?php endif; ?>
                                
                                <button type="button" @click="openEdit(<?= (int)$e['id'] ?>)" class="px-2.5 py-1 text-slate-700 hover:text-indigo-600 bg-slate-100 hover:bg-indigo-50 border border-slate-200 rounded-lg transition inline-flex items-center gap-1 text-xs font-bold cursor-pointer">
                                    <i data-lucide="edit-2" class="w-3.5 h-3.5"></i> Edit
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

                        <!-- Smart Onboarding Modal (Wide Horizontal Landscape Box) -->
    <div x-show="onboardModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div @click.away="onboardModalOpen = false" class="bg-white rounded-3xl max-w-4xl w-full p-6 shadow-2xl border border-slate-200 text-left my-auto" x-data="{ empType: 'full_time', userRole: 'employee', workMode: 'office' }">
            
            <!-- Modal Header (Horizontal Strip) -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 to-purple-600 text-white flex items-center justify-center font-bold shrink-0 shadow-sm">
                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 leading-tight">Onboard New Team Member</h3>
                        <p class="text-xs text-slate-400">Register employee identity, assign reporting authority, and configure work setup.</p>
                    </div>
                </div>
                <button type="button" @click="onboardModalOpen = false" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-50 transition cursor-pointer">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- Onboarding Form (2 Wide Horizontal Columns) -->
            <form action="?action=create-employee" method="POST" class="space-y-4">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    
                    <!-- LEFT HORIZONTAL PANEL: Profile & Role -->
                    <div class="bg-slate-50/70 p-4 rounded-2xl border border-slate-200/80 space-y-3">
                        <div class="pb-1 border-b border-slate-200/60">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-700 flex items-center gap-1.5">
                                <i data-lucide="user" class="w-3.5 h-3.5"></i> 1. Member Profile & Role
                            </span>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Full Name <span class="text-rose-500">*</span></label>
                            <input type="text" name="name" required placeholder="e.g. Shruti Singh" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Official Email Address <span class="text-rose-500">*</span></label>
                            <input type="email" name="email" required placeholder="shruti@company.com" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Department <span class="text-rose-500">*</span></label>
                                <select name="department_name" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                    <option value="Tech / Development">💻 Tech</option>
                                    <option value="Calling / BDA Team">📞 BDA Team</option>
                                    <option value="Field Operations">🚗 Field</option>
                                    <option value="HR & Administration">👑 HR</option>
                                </select>
                            </div>
                            <div class="relative" @click.away="roleDropdownOpen = false">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase">Designation <span class="text-rose-500">*</span></label>
                                    <button type="button" @click="addRolePopupOpen = true" class="w-5 h-5 rounded-md bg-indigo-100 hover:bg-indigo-600 text-indigo-700 hover:text-white border border-indigo-200 flex items-center justify-center text-xs font-extrabold transition cursor-pointer shadow-2xs" title="Add New Role (+)">
                                        +
                                    </button>
                                </div>

                                <input type="hidden" name="designation" :value="selectedDesig" required>

                                <!-- Dropdown Trigger -->
                                <button type="button" @click="roleDropdownOpen = !roleDropdownOpen" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-bold text-left flex items-center justify-between focus:ring-2 focus:ring-indigo-500 shadow-2xs transition cursor-pointer">
                                    <span :class="selectedDesig ? 'text-indigo-950 font-bold truncate' : 'text-slate-400 font-normal'" x-text="selectedDesig || 'Select Designation...'"></span>
                                    <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                                </button>

                                <!-- Custom Dropdown List with - on every role (Fit Within Box) -->
                                <div x-show="roleDropdownOpen" class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl z-50 p-1 max-h-36 overflow-y-auto space-y-0.5" x-cloak>
                                    <template x-for="r in rolesList" :key="r.id">
                                        <div @click="selectedDesig = r.name; roleDropdownOpen = false" class="px-2 py-1.5 rounded-lg hover:bg-indigo-50 flex items-center justify-between cursor-pointer group transition gap-1.5">
                                            <div class="flex items-center gap-1 min-w-0">
                                                <span class="text-xs font-semibold text-slate-800 truncate" x-text="r.name"></span>
                                                <span x-show="Number(r.can_be_reporting_authority) === 1" class="text-[9px] px-1 py-0.2 bg-purple-100 text-purple-800 font-extrabold rounded shrink-0" title="Lead Role">👑</span>
                                            </div>
                                            <button type="button" @click.stop="deleteRole(r.id, $event)" class="w-4 h-4 rounded bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white flex items-center justify-center font-extrabold text-[11px] transition shrink-0 cursor-pointer" title="Delete Role (-)">
                                                -
                                            </button>
                                        </div>
                                    </template>
                                    <div x-show="!rolesList || !rolesList.length" class="p-2 text-center text-[11px] text-slate-400 italic">
                                        No roles found. Click + to add.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- RIGHT HORIZONTAL PANEL: Hierarchy & Work Setup -->
                    <div class="bg-slate-50/70 p-4 rounded-2xl border border-slate-200/80 space-y-3">
                        <div class="pb-1 border-b border-slate-200/60">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-purple-700 flex items-center gap-1.5">
                                <i data-lucide="shield" class="w-3.5 h-3.5"></i> 2. Hierarchy & Employment Setup
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">System Role <span class="text-rose-500">*</span></label>
                                <select name="role" x-model="userRole" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                    <option value="employee">Employee / Intern</option>
                                    <option value="team_lead">Team Lead / TL Support</option>
                                    <option value="admin">HR Administration</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Reporting Authority</label>
                                <select name="reporting_tl_id" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                    <option value="">Apex Authority / Direct HR</option>
                                    <?php foreach ($reportingAuthorities as $ra): ?>
                                        <option value="<?= $ra['id'] ?>">
                                            <?= htmlspecialchars($ra['name']) ?> (<?= htmlspecialchars($ra['designation'] ?: ucfirst($ra['role'])) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Work Mode <span class="text-rose-500">*</span></label>
                                <select name="work_mode" x-model="workMode" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                    <option value="office">🏢 In-Office (150m)</option>
                                    <option value="field">🚗 Field Staff (GPS)</option>
                                    <option value="wfh">🏠 WFH / Remote</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Office Location</label>
                                <select name="assigned_office_location" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                    <?php foreach ($officeLocations as $loc): ?>
                                        <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Employment Type</label>
                                <select name="employment_type" x-model="empType" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                    <option value="full_time">Full Time Staff</option>
                                    <option value="intern_paid">Paid Intern</option>
                                    <option value="intern_unpaid">Unpaid Intern</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Monthly Pay (₹)</label>
                                <input type="number" step="0.01" name="salary_basic" placeholder="0.00" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal Footer (Horizontal Action Strip) -->
                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                    <span class="text-[11px] text-slate-400 flex items-center gap-1">
                        <i data-lucide="info" class="w-3.5 h-3.5 text-slate-400"></i> Member will receive an instant login invitation.
                    </span>
                    <div class="flex items-center gap-2.5">
                        <button type="button" @click="onboardModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white rounded-xl text-xs font-bold shadow-md hover:shadow-lg transition inline-flex items-center gap-1.5 cursor-pointer">
                            <i data-lucide="check" class="w-4 h-4"></i> Complete Onboarding
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- VIEW / EDIT PROFILE MODAL (Wide Horizontal Landscape Box) -->
    <div x-show="viewModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4" x-cloak>
        <div @click.away="viewModalOpen = false" class="bg-white rounded-3xl max-w-4xl w-full p-6 shadow-2xl border border-slate-200 text-left my-auto" x-show="selectedEmp">
            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-3">
                    <img :src="selectedEmp && selectedEmp.avatar ? selectedEmp.avatar : ('https://ui-avatars.com/api/?name=' + encodeURIComponent((selectedEmp && selectedEmp.name) ? selectedEmp.name : 'User'))" class="w-10 h-10 rounded-2xl object-cover ring-2 ring-indigo-100 shrink-0 shadow-xs" alt="Avatar">
                    <div>
                        <h3 class="text-base font-bold text-slate-900" x-text="selectedEmp ? selectedEmp.name : ''"></h3>
                        <p class="text-xs text-slate-400 font-mono" x-text="selectedEmp ? (selectedEmp.emp_id + ' • ' + (selectedEmp.designation || 'Staff')) : ''"></p>
                    </div>
                </div>
                <button type="button" @click="viewModalOpen = false" class="p-1.5 text-slate-400 hover:text-slate-600 rounded-xl hover:bg-slate-50 transition cursor-pointer">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <!-- 1. VIEW OVERVIEW MODE (Horizontal Summary) -->
            <template x-if="!editMode && selectedEmp">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                        <div class="bg-slate-50 p-3 rounded-2xl border border-slate-200/80">
                            <span class="text-[10px] font-bold uppercase text-slate-400 block">System Role</span>
                            <span class="text-xs font-extrabold text-slate-800 uppercase" x-text="selectedEmp.role ? selectedEmp.role.replace('_', ' ') : '-'"></span>
                        </div>
                        <div class="bg-indigo-50/60 p-3 rounded-2xl border border-indigo-100">
                            <span class="text-[10px] font-bold uppercase text-indigo-400 block">Employment</span>
                            <span class="text-xs font-extrabold text-indigo-700 uppercase" x-text="selectedEmp.employment_type ? selectedEmp.employment_type.replace('_', ' ') : 'Full Time'"></span>
                        </div>
                        <div class="bg-purple-50/60 p-3 rounded-2xl border border-purple-100">
                            <span class="text-[10px] font-bold uppercase text-purple-400 block">Work Mode</span>
                            <span class="text-xs font-extrabold text-purple-700 uppercase" x-text="selectedEmp.work_mode || 'office'"></span>
                        </div>
                        <div class="bg-emerald-50/60 p-3 rounded-2xl border border-emerald-100">
                            <span class="text-[10px] font-bold uppercase text-emerald-400 block">Monthly Pay</span>
                            <span class="text-xs font-extrabold text-emerald-700" x-text="selectedEmp.salary_basic > 0 ? ('₹' + Number(selectedEmp.salary_basic).toLocaleString('en-IN')) : 'Unpaid'"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 bg-slate-50/60 p-4 rounded-2xl border border-slate-200/80 text-xs">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase block mb-0.5">Official Email</span>
                            <span class="font-semibold text-slate-800 font-mono" x-text="selectedEmp.email || '-'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase block mb-0.5">Department</span>
                            <span class="font-bold text-slate-800" x-text="selectedEmp.department_name || 'Tech / Development'"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase block mb-0.5">Reporting Authority</span>
                            <span class="font-bold text-indigo-700" x-text="selectedEmp.tl_name ? selectedEmp.tl_name : 'Apex HR Direct'"></span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <button type="button" @click="viewModalOpen = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition">
                            Close
                        </button>
                        <button type="button" @click="editMode = true" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold transition shadow-sm inline-flex items-center gap-1.5 cursor-pointer">
                            <i data-lucide="edit" class="w-4 h-4"></i> Edit Profile Information
                        </button>
                    </div>
                </div>
            </template>

            <!-- 2. EDIT PROFILE MODE (Horizontal 2-Column Box) -->
            <template x-if="editMode && selectedEmp">
                <form action="?action=update-employee" method="POST" class="space-y-4">
                    <input type="hidden" name="user_id" :value="selectedEmp.id">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <!-- LEFT PANEL -->
                        <div class="bg-slate-50/70 p-4 rounded-2xl border border-slate-200/80 space-y-3">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-700 block pb-1 border-b border-slate-200/60">
                                1. Personal & Role Details
                            </span>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Full Name <span class="text-rose-500">*</span></label>
                                <input type="text" name="name" :value="selectedEmp.name" required class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                            </div>

                            <div>
                                <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Official Email <span class="text-rose-500">*</span></label>
                                <input type="email" name="email" :value="selectedEmp.email" required class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Department <span class="text-rose-500">*</span></label>
                                    <select name="department_name" x-model="selectedEmp.department_name" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                        <option value="Tech / Development">💻 Tech</option>
                                        <option value="Calling / BDA Team">📞 BDA Team</option>
                                        <option value="Field Operations">🚗 Field</option>
                                        <option value="HR & Administration">👑 HR</option>
                                    </select>
                                </div>
                                                            <div class="relative" x-data="{ editRoleDrop: false }" @click.away="editRoleDrop = false">
                                <div class="flex items-center justify-between mb-1">
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase">Designation <span class="text-rose-500">*</span></label>
                                    <button type="button" @click="addRolePopupOpen = true" class="px-2 py-0.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-md text-[10px] font-extrabold flex items-center gap-1 transition cursor-pointer shadow-2xs" title="Add New Role">
                                        <i data-lucide="plus" class="w-3 h-3"></i> Add Role
                                    </button>
                                </div>

                                <input type="hidden" name="designation" :value="selectedEmp ? selectedEmp.designation : ''" required>

                                <button type="button" @click="editRoleDrop = !editRoleDrop" class="w-full bg-white border border-slate-300 rounded-xl px-3 py-2 text-xs font-bold text-left flex items-center justify-between focus:ring-2 focus:ring-indigo-500 shadow-2xs transition">
                                    <span class="text-indigo-950 font-bold" x-text="selectedEmp ? (selectedEmp.designation || 'Select Designation...') : ''"></span>
                                    <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400"></i>
                                </button>

                                <div x-show="editRoleDrop" class="absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-2xl shadow-xl z-50 p-1.5 max-h-52 overflow-y-auto space-y-1" x-cloak>
                                    <template x-for="r in rolesList" :key="r.id">
                                        <div @click="if (selectedEmp) selectedEmp.designation = r.name; editRoleDrop = false" class="px-2.5 py-1.5 rounded-xl hover:bg-indigo-50 flex items-center justify-between cursor-pointer group transition">
                                            <div class="flex items-center gap-1.5 min-w-0 pr-2">
                                                <span class="text-xs font-bold text-slate-800 truncate" x-text="r.name"></span>
                                                <span x-show="Number(r.can_be_reporting_authority) === 1" class="text-[9px] px-1.5 py-0.2 bg-emerald-100 text-emerald-800 font-extrabold rounded">👑</span>
                                            </div>
                                            <button type="button" @click.stop="deleteRole(r.id, $event)" class="w-5 h-5 rounded-lg bg-rose-50 hover:bg-rose-600 text-rose-600 hover:text-white flex items-center justify-center transition shrink-0 cursor-pointer shadow-2xs" title="Delete Role (-)">
                                                <i data-lucide="minus" class="w-3 h-3"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>
                            </div>
                        </div>

                        <!-- RIGHT PANEL -->
                        <div class="bg-slate-50/70 p-4 rounded-2xl border border-slate-200/80 space-y-3">
                            <span class="text-[11px] font-extrabold uppercase tracking-wider text-purple-700 block pb-1 border-b border-slate-200/60">
                                2. Hierarchy & Office Terms
                            </span>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Account Role <span class="text-rose-500">*</span></label>
                                    <select name="role" x-model="selectedEmp.role" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                        <option value="employee">Employee / Intern</option>
                                        <option value="team_lead">Team Lead / TL Support</option>
                                        <option value="admin">HR Administration</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Reports To</label>
                                    <select name="reporting_tl_id" x-model="selectedEmp.reporting_tl_id" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-bold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                        <option value="">Apex Authority / Direct HR</option>
                                        <?php foreach ($reportingAuthorities as $ra): ?>
                                            <option value="<?= $ra['id'] ?>">
                                                <?= htmlspecialchars($ra['name']) ?> (<?= htmlspecialchars($ra['designation'] ?: ucfirst($ra['role'])) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Work Mode <span class="text-rose-500">*</span></label>
                                    <select name="work_mode" x-model="selectedEmp.work_mode" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                        <option value="office">🏢 In-Office (150m)</option>
                                        <option value="field">🚗 Field Staff (GPS)</option>
                                        <option value="wfh">🏠 WFH / Remote</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Office Location</label>
                                    <select name="assigned_office_location" x-model="selectedEmp.assigned_office_location" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                        <?php foreach ($officeLocations as $loc): ?>
                                            <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2.5">
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Employment Type</label>
                                    <select name="employment_type" x-model="selectedEmp.employment_type" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-800 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                        <option value="full_time">Full Time Staff</option>
                                        <option value="intern_paid">Paid Intern</option>
                                        <option value="intern_unpaid">Unpaid Intern</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Monthly Pay (₹)</label>
                                    <input type="number" step="0.01" name="salary_basic" :value="selectedEmp.salary_basic" class="w-full bg-white border border-slate-300 rounded-xl px-2.5 py-2 text-xs font-semibold text-slate-900 focus:ring-2 focus:ring-indigo-500 transition shadow-2xs">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Buttons -->
                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <button type="button" @click="editMode = false" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-sm transition inline-flex items-center gap-1.5 cursor-pointer">
                            <i data-lucide="save" class="w-4 h-4"></i> Save Profile Changes
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>

<!-- Modal: Reassign Team & Terminate Team Lead -->
    <div x-show="reassignModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="reassignModalOpen = false" class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center font-bold">
                        <i data-lucide="shield-alert" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Reassign Team & Terminate TL</h3>
                        <p class="text-xs text-slate-500">A new Team Lead must be assigned before deleting this Lead.</p>
                    </div>
                </div>
                <button @click="reassignModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form action="?action=delete-employee" method="POST" class="space-y-4">
                <input type="hidden" name="user_id" :value="targetTlId">

                <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-xs text-amber-900">
                    <div class="font-bold flex items-center gap-1 mb-1">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600"></i> Active Team Members Found
                    </div>
                    <p class="text-amber-800 leading-relaxed">
                        <strong x-text="targetTlName"></strong> currently manages <strong x-text="targetTlMembersCount + ' active member(s)'"></strong>:
                        <span class="block mt-1 font-semibold text-slate-800" x-text="targetTlMemberNames"></span>
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1.5">Assign New Team Lead / Supervisor *</label>
                    <select name="new_tl_id" required class="w-full bg-slate-50 border border-slate-300 rounded-xl p-2.5 text-xs text-slate-900 focus:bg-white focus:ring-2 focus:ring-rose-500">
                        <option value="">-- Select Replacement Team Lead --</option>
                        <option value="admin">👑 HR Administration (Direct Management)</option>
                        <?php foreach ($tls as $t): ?>
                            <template x-if="'<?= $t['id'] ?>' != targetTlId">
                                <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (Team Lead)</option>
                            </template>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-[11px] text-slate-500 mt-1">
                        All <span x-text="targetTlMembersCount"></span> members will be transferred to this supervisor and notified immediately.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="reassignModalOpen = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold transition shadow-sm inline-flex items-center gap-1.5">
                        <i data-lucide="user-x" class="w-4 h-4"></i> Transfer Team & Terminate TL
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Manage & Assign Team Members to TL -->
    <div x-show="manageTeamModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4" x-cloak>
        <div @click.away="manageTeamModalOpen = false" class="bg-white rounded-3xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-700 flex items-center justify-center font-bold">
                        <i data-lucide="users" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900">Assign Team to <span x-text="manageTlName" class="text-purple-700"></span></h3>
                        <p class="text-xs text-slate-500">Select employees and interns who report to this Team Lead.</p>
                    </div>
                </div>
                <button @click="manageTeamModalOpen = false" class="text-slate-400 hover:text-slate-600"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <form action="?action=assign-tl-team" method="POST" class="space-y-4">
                <input type="hidden" name="tl_id" :value="manageTlId">

                <div class="space-y-2">
                    <div class="flex items-center justify-between px-1">
                        <span class="text-xs font-bold uppercase text-slate-600 tracking-wider">Active Staff Directory (<?= count($availableStaff) ?>)</span>
                        <span class="text-[11px] text-indigo-600 font-semibold">Check to assign</span>
                    </div>

                    <div class="max-h-60 overflow-y-auto space-y-1.5 pr-1 bg-slate-50 p-2.5 rounded-xl border border-slate-200 divide-y divide-slate-100">
                        <?php if (empty($availableStaff)): ?>
                            <p class="text-xs text-slate-400 text-center py-4">No active staff members found in the organization.</p>
                        <?php else: ?>
                            <?php foreach ($availableStaff as $st): ?>
                                <template x-if="!<?= (int)($st['reporting_tl_id'] ?? 0) ?> || '<?= (int)($st['reporting_tl_id'] ?? 0) ?>' == manageTlId">
                                    <label class="flex items-center justify-between p-2 rounded-lg hover:bg-white cursor-pointer text-xs transition">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <input type="checkbox" name="assigned_member_ids[]" value="<?= $st['id'] ?>" :checked="manageAssignedIds.includes(<?= $st['id'] ?>)" class="rounded text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                                            <div class="min-w-0">
                                                <span class="font-bold text-slate-900 block truncate"><?= htmlspecialchars($st['name']) ?></span>
                                                <span class="text-[11px] text-slate-400 truncate"><?= htmlspecialchars($st['designation']) ?></span>
                                            </div>
                                        </div>
                                        <span class="text-[10px] px-2 py-0.5 rounded-full font-mono font-medium" :class="manageAssignedIds.includes(<?= $st['id'] ?>) ? 'bg-emerald-100 text-emerald-800 font-bold' : 'bg-purple-100 text-purple-800'">
                                            <span x-text="manageAssignedIds.includes(<?= $st['id'] ?>) ? '✓ Current Team' : 'Direct to HR (Available)'"></span>
                                        </span>
                                    </label>
                                </template>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="p-3 bg-indigo-50/70 rounded-xl border border-indigo-100 text-xs text-indigo-900 leading-relaxed">
                    <strong>💡 Note:</strong> Assigned members will immediately appear on this Lead's dashboard roster and receive an instant hierarchy notification.
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" @click="manageTeamModalOpen = false" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition shadow-sm inline-flex items-center gap-1.5">
                        <i data-lucide="check" class="w-4 h-4"></i> Save Team Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- INLINE ADD ROLE POPUP MODAL -->
<div x-show="addRolePopupOpen" class="fixed inset-0 z-60 overflow-y-auto" x-cloak>
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="addRolePopupOpen = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-3xl max-w-sm w-full p-5 shadow-2xl border border-slate-200 text-left">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="font-bold text-sm text-slate-900 flex items-center gap-1.5">
                    <i data-lucide="plus-circle" class="w-4 h-4 text-indigo-600"></i> Add New Role
                </h3>
                <button type="button" @click="addRolePopupOpen = false" class="text-slate-400 hover:text-slate-600 p-1"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="space-y-3 pt-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-700 uppercase mb-1">Designation Title *</label>
                    <input type="text" x-model="newRoleTitle" @keydown.enter.prevent="submitNewRole()" placeholder="e.g. Senior Backend Developer" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3 py-2 text-xs font-semibold focus:ring-2 focus:ring-indigo-500">
                </div>
                <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="newRoleAuth" class="w-4 h-4 rounded text-indigo-600 focus:ring-indigo-500">
                        <span class="text-xs font-bold text-slate-800">Can be a Reporting Authority?</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="addRolePopupOpen = false" class="px-3 py-1.5 bg-slate-100 rounded-lg text-xs font-bold text-slate-600">Cancel</button>
                    <button type="button" @click="submitNewRole()" class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-bold shadow-sm transition">Add Role (+)</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- INLINE MANAGE / DELETE ROLES POPUP MODAL -->
<div x-show="manageRolesPopupOpen" class="fixed inset-0 z-60 overflow-y-auto" x-cloak>
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="manageRolesPopupOpen = false"></div>
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="relative bg-white rounded-3xl max-w-md w-full p-5 shadow-2xl border border-slate-200 text-left">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <h3 class="font-bold text-sm text-slate-900 flex items-center gap-1.5">
                    <i data-lucide="trash-2" class="w-4 h-4 text-rose-600"></i> Delete / Manage Roles (-)
                </h3>
                <button type="button" @click="manageRolesPopupOpen = false" class="text-slate-400 hover:text-slate-600 p-1"><i data-lucide="x" class="w-4 h-4"></i></button>
            </div>
            <div class="space-y-2 pt-3 max-h-60 overflow-y-auto no-scrollbar">
                <template x-for="r in rolesList" :key="r.id">
                    <div class="p-2.5 rounded-xl border border-slate-100 bg-slate-50 flex items-center justify-between gap-2">
                        <div>
                            <span class="text-xs font-bold text-slate-800" x-text="r.name"></span>
                            <span x-show="Number(r.can_be_reporting_authority) === 1" class="ml-1.5 px-1.5 py-0.2 rounded bg-emerald-100 text-emerald-700 text-[9px] font-extrabold">Authority</span>
                        </div>
                        <button type="button" @click="deleteRole(r.id)" class="p-1 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Delete Role">
                            <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>
                </template>
            </div>
            <div class="flex justify-end pt-3 border-t border-slate-100 mt-3">
                <button type="button" @click="manageRolesPopupOpen = false" class="px-4 py-1.5 bg-slate-100 rounded-lg text-xs font-bold text-slate-600">Done</button>
            </div>
        </div>
    </div>
</div>