<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Anti-caching headers to prevent browser back-button access after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: login.php");
    exit();
}

require_once(__DIR__ . '/../config/db.php');

// AUTO-HEAL: Sync missing emails in users table for seamless recovery
function healUserEmails($conn) {
    // 1. Ensure email and department columns exist
    @mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `email` VARCHAR(150) DEFAULT NULL");
    @mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `department` VARCHAR(150) DEFAULT NULL");
    @mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `academic_year` VARCHAR(50) DEFAULT NULL");
    @mysqli_query($conn, "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `division` VARCHAR(50) DEFAULT NULL");

    // 2. Fill empty user emails with username if username is formatted like an email
    @mysqli_query($conn, "UPDATE `users` SET `email` = `username` WHERE (`email` IS NULL OR `email` = '') AND `username` LIKE '%@%'");

    // 3. For parents, sync parent_email from access_requests if still blank
    $syncParents = "UPDATE `users` u 
                    JOIN `access_requests` ar ON u.`linked_student_prn` = ar.`prn_number` 
                    SET u.`email` = ar.`parent_email` 
                    WHERE u.`role` = 'Parent' AND (u.`email` IS NULL OR u.`email` = '')";
    @mysqli_query($conn, $syncParents);
}
healUserEmails($conn);

$flashMessage = '';
$flashClass = 'alert-dark text-white border-secondary';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // DUAL IDP APPROVAL (Student via PRN + Parent via Email)
    if ($action === 'approve_request') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        
        $reqStmt = $conn->prepare("SELECT * FROM access_requests WHERE request_id = ? AND status = 'PENDING'");
        $reqStmt->bind_param("i", $requestId);
        $reqStmt->execute();
        $reqData = $reqStmt->get_result()->fetch_assoc();
        $reqStmt->close();

        if ($reqData) {
            $prn          = strtoupper(trim($reqData['prn_number']));
            $student_name = trim($reqData['full_name']);
            $student_email= strtolower(trim($reqData['email']));
            $parent_name  = trim($reqData['parent_name'] ?? ($student_name . " Guardian"));
            $parent_email = strtolower(trim($reqData['parent_email'] ?? ''));
            $dept_name    = trim($reqData['department'] ?? '');
            $academic_year= trim($reqData['academic_year'] ?? 'FY');
            $division     = trim($reqData['division'] ?? 'A');
            $defaultPass  = password_hash('Zeal@2026', PASSWORD_BCRYPT);

            // 1. Create Student Account (Username = PRN, Email = student_email)
            $createStudent = $conn->prepare("INSERT IGNORE INTO users (name, username, email, department, academic_year, division, password, role, is_first_login) VALUES (?, ?, ?, ?, ?, ?, ?, 'Student', 1)");
            $createStudent->bind_param("sssssss", $student_name, $prn, $student_email, $dept_name, $academic_year, $division, $defaultPass);
            $createStudent->execute();
            $createStudent->close();

            // 2. Create Linked Parent Account (Username = Parent Email, Email = Parent Email)
            $createParent = $conn->prepare("INSERT IGNORE INTO users (name, username, email, password, role, linked_student_prn, is_first_login) VALUES (?, ?, ?, ?, 'Parent', ?, 1)");
            $createParent->bind_param("sssss", $parent_name, $parent_email, $parent_email, $defaultPass, $prn);
            $createParent->execute();
            $createParent->close();

            // 3. Update Request Status
            $upReq = $conn->prepare("UPDATE access_requests SET status = 'APPROVED' WHERE request_id = ?");
            $upReq->bind_param("i", $requestId);
            $upReq->execute();
            $upReq->close();

            $flashMessage = "Dual IDPs issued! Student PRN: $prn | Parent Email: $parent_email (Default Passkey: Zeal@2026)";
            $flashClass = "alert-success";
        }
    }

    // MANUAL STAFF IDP PROVISIONING
    if ($action === 'create_staff_idp') {
        $staff_name = trim($_POST['staff_name'] ?? '');
        $username   = strtolower(trim($_POST['staff_username'] ?? ''));
        $staff_role = trim($_POST['staff_role'] ?? '');
        $staff_dept = trim($_POST['staff_department'] ?? '');
        $defaultPass = password_hash('Zeal@2026', PASSWORD_BCRYPT);

        $allowedStaffRoles = ['Faculty', 'HOD', 'GFM', 'Admin'];

        if (!empty($staff_name) && !empty($username) && in_array($staff_role, $allowedStaffRoles)) {
            $checkU = $conn->prepare("SELECT user_id FROM users WHERE LOWER(username) = ?");
            $checkU->bind_param("s", $username);
            $checkU->execute();
            if ($checkU->get_result()->num_rows > 0) {
                $flashMessage = "Account creation aborted: Username/Email '$username' is already registered.";
                $flashClass = "alert-danger";
            } else {
                $createStaff = $conn->prepare("INSERT INTO users (name, username, email, department, password, role, is_first_login) VALUES (?, ?, ?, ?, ?, ?, 1)");
                $createStaff->bind_param("ssssss", $staff_name, $username, $username, $staff_dept, $defaultPass, $staff_role);
                if ($createStaff->execute()) {
                    $deptStr = !empty($staff_dept) ? " | Branch: $staff_dept" : "";
                    $flashMessage = "Staff account created successfully! Role: $staff_role$deptStr | Login: $username | Default Passkey: Zeal@2026";
                    $flashClass = "alert-success";
                } else {
                    $flashMessage = "Error writing staff user record.";
                    $flashClass = "alert-danger";
                }
                $createStaff->close();
            }
            $checkU->close();
        } else {
            $flashMessage = "Please select a valid staff role and complete all fields.";
            $flashClass = "alert-warning";
        }
    }

    if ($action === 'reject_request') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $rejStmt = $conn->prepare("UPDATE access_requests SET status = 'REJECTED' WHERE request_id = ?");
        $rejStmt->bind_param("i", $requestId);
        $rejStmt->execute();
        $rejStmt->close();
        $flashMessage = "Request rejected.";
        $flashClass = "alert-warning";
    }

    if ($action === 'delete_user') {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        if ($targetUserId > 0 && $targetUserId !== (int)$_SESSION['user_id']) {
            $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $targetUserId);
            $stmt->execute();
            $stmt->close();
            $flashMessage = "User account deleted.";
            $flashClass = "alert-success";
        }
    }
}

function getCount($conn, $query) {
    $res = @mysqli_query($conn, $query);
    if ($res) { $row = mysqli_fetch_assoc($res); return (int)($row['c'] ?? 0); }
    return 0;
}

$totalUsers      = getCount($conn, "SELECT COUNT(*) AS c FROM users");
$totalStudents   = getCount($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'Student'");
$totalParents    = getCount($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'Parent'");
$pendingRequests = getCount($conn, "SELECT COUNT(*) AS c FROM access_requests WHERE status = 'PENDING'");

$requestsList = [];
$reqQ = @mysqli_query($conn, "SELECT * FROM access_requests WHERE status = 'PENDING' ORDER BY request_id DESC");
if ($reqQ && mysqli_num_rows($reqQ) > 0) {
    while ($r = mysqli_fetch_assoc($reqQ)) { $requestsList[] = $r; }
}

// Fetch System Users List with explicit Email and Department fields
$userList = [];
$userQ = @mysqli_query($conn, "SELECT user_id, name, username, email, department, role, is_first_login FROM users ORDER BY user_id DESC LIMIT 30");
if ($userQ && mysqli_num_rows($userQ) > 0) {
    while ($r = mysqli_fetch_assoc($userQ)) { $userList[] = $r; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | SAAES</title>
    
    <!-- Clean Academic Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            /* Traditional Academic Color Palette */
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --navy-primary: #0f172a;
            --blue-accent: #2563eb;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            
            --radius-md: 8px;
            --radius-lg: 12px;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            
            --font-main: 'Inter', system-ui, -apple-system, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-main);
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        a { text-decoration: none; color: inherit; }

        /* ================= HEADER ================= */
        .academic-header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border-color);
            padding: 0 2rem;
            display: flex; justify-content: space-between; align-items: center;
            height: 70px;
            position: sticky; top: 0; z-index: 100;
            box-shadow: var(--shadow-sm);
        }
        .sys-logo {
            display: flex; align-items: center; gap: 0.75rem;
            font-weight: 700; font-size: 1.25rem; color: var(--navy-primary);
        }
        .sys-logo i { color: var(--blue-accent); font-size: 1.4rem; }
        .sys-logo .line { width: 1px; height: 24px; background: var(--border-color); margin: 0 0.5rem; }

        .dashboard-container {
            max-width: 1300px; margin: 2rem auto; padding: 0 1.5rem; flex: 1;
        }

        /* ================= MODULE CARDS ================= */
        .module-card {
            background: var(--bg-card); border: 1px solid var(--border-color);
            padding: 2rem; margin-bottom: 2rem; border-radius: var(--radius-lg); 
            box-shadow: var(--shadow-sm);
        }
        .mod-title { 
            font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem; 
            display: flex; align-items: center; gap: 0.75rem; color: var(--navy-primary);
        }
        .mod-title i { color: var(--blue-accent); }
        .mod-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 2rem; }

        /* ================= TELEMETRY STATS ================= */
        .telemetry-grid { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1.5rem; margin-bottom: 2rem; 
        }
        .tel-block { 
            background: var(--bg-card); border: 1px solid var(--border-color); 
            border-radius: var(--radius-lg); padding: 1.5rem; text-align: center; 
            box-shadow: var(--shadow-sm); 
        }
        .tel-val { font-size: 2.5rem; font-weight: 700; color: var(--blue-accent); line-height: 1; margin-bottom: 0.5rem; }
        .tel-label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

        /* ================= TAGS / BADGES ================= */
        .sys-tag { 
            font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.6rem; 
            background: #f1f5f9; color: var(--text-muted); border-radius: 999px; 
            display: inline-flex; align-items: center; gap: 0.4rem; 
        }
        .sys-tag.accent { background: #eff6ff; color: var(--blue-accent); }
        .sys-tag.warning { background: #fef3c7; color: var(--warning); }
        .sys-tag.success { background: #dcfce7; color: var(--success); }

        /* ================= BUTTONS ================= */
        .btn {
            font-family: var(--font-main); font-weight: 500; font-size: 0.85rem;
            padding: 0.6rem 1.2rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            border-radius: var(--radius-md); border: 1px solid transparent; cursor: pointer;
            transition: all 0.2s ease; text-decoration: none;
        }
        .btn-primary { background: var(--blue-accent); color: #fff; }
        .btn-primary:hover { background: #1d4ed8; }
        
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { background: #dc2626; }

        .btn-outline { background: transparent; border-color: var(--border-color); color: var(--text-main); }
        .btn-outline:hover { background: var(--bg-body); border-color: var(--text-muted); }
        .btn-outline.danger { color: var(--danger); border-color: #fca5a5; }
        .btn-outline.danger:hover { background: #fef2f2; color: #dc2626; }

        /* ================= FORMS ================= */
        .form-label { font-size: 0.85rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.4rem; display: block;}
        .form-control-custom, .form-select-custom {
            width: 100%; padding: 0.6rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color);
            color: var(--text-main); font-family: inherit; font-size: 0.9rem; outline: none; transition: border 0.2s;
            border-radius: var(--radius-md);
        }
        .form-control-custom:focus, .form-select-custom:focus { 
            border-color: var(--blue-accent); background: var(--bg-card);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); 
        }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1.5fr auto; gap: 1rem; align-items: flex-end; }

        /* ================= TABLES ================= */
        .table-responsive { overflow-x: auto; border: 1px solid var(--border-color); border-radius: var(--radius-md); }
        .custom-table { width: 100%; border-collapse: collapse; text-align: left; background: var(--bg-card); }
        .custom-table th, .custom-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; vertical-align: middle; }
        .custom-table th { background: var(--bg-body); color: var(--text-muted); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; }
        .custom-table tbody tr:last-child td { border-bottom: none; }
        .custom-table tbody tr:hover { background: #f8fafc; }

        /* ================= ALERTS ================= */
        .alert { 
            font-size: 0.9rem; font-weight: 500; border-radius: var(--radius-md); padding: 1rem 1.25rem; 
            margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between;
        }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .btn-close-alert { background: none; border: none; color: inherit; font-size: 1.25rem; cursor: pointer; line-height: 1; opacity: 0.5;}
        .btn-close-alert:hover { opacity: 1; }

        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="academic-header">
    <div class="sys-logo">
        <i class="fa-solid fa-server"></i> Admin Dashboard <div class="line"></div> ZCOER
    </div>
    <a href="logout.php" class="btn btn-outline danger"><i class="fa-solid fa-power-off"></i> Logout</a>
</header>

<div class="dashboard-container">

    <?php if ($flashMessage): ?>
        <div class="alert <?php echo $flashClass; ?>" id="alertBox">
            <div><?php echo htmlspecialchars($flashMessage); ?></div>
            <button type="button" class="btn-close-alert" onclick="document.getElementById('alertBox').style.display='none'">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Summary Strip (4 Columns) -->
    <div class="telemetry-grid">
        <div class="tel-block">
            <div class="tel-val" style="color: var(--warning);"><?php echo $pendingRequests; ?></div>
            <div class="tel-label">Pending Requests</div>
        </div>
        <div class="tel-block">
            <div class="tel-val" style="color: var(--navy-primary);"><?php echo $totalStudents; ?></div>
            <div class="tel-label">Active Students</div>
        </div>
        <div class="tel-block">
            <div class="tel-val" style="color: var(--navy-primary);"><?php echo $totalParents; ?></div>
            <div class="tel-label">Active Parents</div>
        </div>
        <div class="tel-block">
            <div class="tel-val" style="color: var(--text-muted);"><?php echo $totalUsers; ?></div>
            <div class="tel-label">Total Users</div>
        </div>
    </div>

    <!-- MANUAL STAFF IDP ASSIGNMENT PANEL -->
    <div class="module-card">
        <h3 class="mod-title"><i class="fa-solid fa-user-plus"></i> Create Staff Account</h3>
        <p class="mod-subtitle">Assign login credentials for faculty and staff roles. Default password: <strong>Zeal@2026</strong></p>

        <form method="POST" action="admin_dashboard.php">
            <input type="hidden" name="action" value="create_staff_idp">
            <div class="form-grid">
                <div>
                    <label class="form-label">Staff Member Name</label>
                    <input type="text" name="staff_name" class="form-control-custom" placeholder="e.g. Dr. Alan Smith" required autocomplete="off">
                </div>
                <div>
                    <label class="form-label">Username / Email</label>
                    <input type="text" name="staff_username" class="form-control-custom" placeholder="e.g. alansmith@zeal.in" required autocomplete="off">
                </div>
                <div>
                    <label class="form-label">Assign Role</label>
                    <select name="staff_role" class="form-select-custom" required>
                        <option value="" disabled selected>-- Select --</option>
                        <option value="Faculty">Faculty</option>
                        <option value="HOD">HOD</option>
                        <option value="GFM">GFM</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Branch / Dept</label>
                    <select name="staff_department" class="form-select-custom" required>
                        <option value="" disabled selected>-- Select --</option>
                        <option value="AI and Machine Learning">AI and Machine Learning</option>
                        <option value="AI and Data Science">AI and Data Science</option>
                        <option value="Computer Engineering">Computer Engineering</option>
                        <option value="ENTC">ENTC</option>
                        <option value="Mechanical Engineering">Mechanical Engineering</option>
                        <option value="Electrical Engineering">Electrical Engineering</option>
                        <option value="Electronics and Computer Engineering">Electronics and Computer Engineering</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Civil Engineering">Civil Engineering</option>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary" style="height: 38px; width: 100%;">Create Account</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Student & Parent Approval Queue -->
    <div class="module-card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 class="mod-title" style="margin: 0;"><i class="fa-solid fa-list-check"></i> Pending Requests</h3>
            <span class="sys-tag warning"><?php echo count($requestsList); ?> PENDING</span>
        </div>

        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>PRN</th>
                        <th>Student & Email</th>
                        <th>Parent & Login Email</th>
                        <th>Department</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($requestsList) === 0): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">No pending requests.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($requestsList as $req): ?>
                        <tr>
                            <td style="font-weight: 600; color: var(--navy-primary);"><?php echo htmlspecialchars($req['prn_number']); ?></td>
                            <td>
                                <strong style="display: block; color: var(--text-main);"><?php echo htmlspecialchars($req['full_name']); ?></strong>
                                <span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($req['email']); ?></span>
                            </td>
                            <td>
                                <strong style="display: block; color: var(--text-main);"><?php echo htmlspecialchars($req['parent_name'] ?? 'Parent'); ?></strong>
                                <span style="font-size: 0.8rem; color: var(--blue-accent);"><i class="fa-solid fa-envelope me-1"></i><?php echo htmlspecialchars($req['parent_email'] ?? '-'); ?></span>
                            </td>
                            <td><span class="sys-tag"><?php echo htmlspecialchars($req['department']); ?></span></td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.5rem; justify-content: flex-end;">
                                    <form method="post">
                                        <input type="hidden" name="action" value="approve_request">
                                        <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                        <button type="submit" class="btn btn-primary" style="padding: 0.4rem 0.8rem;"><i class="fa-solid fa-check"></i> Approve</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Reject request?');">
                                        <input type="hidden" name="action" value="reject_request">
                                        <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                        <button type="submit" class="btn btn-outline danger" style="padding: 0.4rem 0.8rem;" title="Reject"><i class="fa-solid fa-xmark"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ISSUED SYSTEM USERS REGISTRY -->
    <div class="module-card">
        <h3 class="mod-title"><i class="fa-solid fa-database"></i> Registered Users</h3>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>UID</th>
                        <th>Name</th>
                        <th>Username / PRN</th>
                        <th>Email Address</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th style="text-align: right;">Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userList as $u): ?>
                        <tr>
                            <td style="color: var(--text-muted);">#<?php echo $u['user_id']; ?></td>
                            <td style="font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($u['name']); ?></td>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($u['email'] ?? $u['username']); ?></td>
                            <td><span class="sys-tag accent"><?php echo htmlspecialchars($u['role']); ?></span></td>
                            <td><span class="sys-tag"><?php echo htmlspecialchars($u['department'] ?: 'General'); ?></span></td>
                            <td>
                                <?php if ((int)$u['is_first_login'] === 1): ?>
                                    <span class="sys-tag warning">Pending</span>
                                <?php else: ?>
                                    <span class="sys-tag success">Active</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ((int)$u['user_id'] !== (int)$_SESSION['user_id']): ?>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Delete user account?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="target_user_id" value="<?php echo $u['user_id']; ?>">
                                        <button type="submit" class="btn btn-outline danger" style="padding: 0.3rem 0.6rem;"><i class="fa-solid fa-trash-can"></i></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
// Safely include modal without fatal error
$modalPath = __DIR__ . '/../includes/end_session_modal.php';
if (file_exists($modalPath)) {
    include_once $modalPath;
}
?>
</body>
</html>