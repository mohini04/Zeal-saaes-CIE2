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
    
    <!-- Professional Fonts matching Landing Page -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=JetBrains+Mono:wght@100;400;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-base: #ffffff;
            --bg-panel: #fcfcfd;
            --text-dark: #0f172a;
            --text-tech: #475569;
            --text-light: #94a3b8;
            
            --accent-main: #7c3aed; /* Electric purple */
            --accent-glow: #a855f7;
            
            --grid-size: 40px;
            --border-harsh: 2px solid var(--text-dark);
            
            --font-head: 'Space Grotesk', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --font-body: 'Inter', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--bg-base);
            /* Architectural Blueprint Grid */
            background-image: 
                linear-gradient(rgba(124, 58, 237, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(124, 58, 237, 0.08) 1px, transparent 1px);
            background-size: var(--grid-size) var(--grid-size);
            background-position: center center;
            color: var(--text-dark);
            font-family: var(--font-body);
            min-height: 100vh;
            overflow-x: hidden;
            
            /* PIXELATED PURPLE CUSTOM CURSOR */
            cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32' shape-rendering='crispEdges'%3E%3Cpath d='M4 4v20l5-5 4 8 4-2-4-8h8L4 4z' fill='%237c3aed' stroke='white' stroke-width='2'/%3E%3C/svg%3E") 4 4, auto;
            -webkit-font-smoothing: antialiased;
        }

        /* PIXELATED HOVER CURSOR */
        a, button, input, select, textarea, .interactive {
            cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32' shape-rendering='crispEdges'%3E%3Cpath d='M4 4v20l5-5 4 8 4-2-4-8h8L4 4z' fill='%23a855f7' stroke='%230f172a' stroke-width='2.5'/%3E%3C/svg%3E") 4 4, pointer !important;
        }

        ::selection { background: var(--accent-main); color: #fff; }
        a { text-decoration: none; color: inherit; }

        /* ================= HEADER ================= */
        .tech-header {
            background: rgba(255, 255, 255, 0.95);
            border-bottom: var(--border-harsh);
            padding: 1.5rem 2.5rem;
            display: flex; justify-content: space-between; align-items: center;
            position: sticky; top: 0; z-index: 100;
        }
        .sys-logo {
            display: flex; align-items: center; gap: 1rem;
            font-family: var(--font-head); font-weight: 700; font-size: 1.4rem;
            text-transform: uppercase;
        }
        .sys-logo i { color: var(--accent-main); }
        .sys-logo .line { width: 30px; height: 2px; background: var(--text-dark); transform: skewX(-45deg); }

        .dashboard-container {
            max-width: 1400px; margin: 0 auto; padding: 3rem 2.5rem; flex: 1;
        }

        /* ================= MODULE CARDS ================= */
        .module-card {
            background: var(--bg-panel); border: 2px solid var(--text-dark);
            padding: 2.5rem; margin-bottom: 2rem; position: relative; transition: transform 0.2s, box-shadow 0.2s;
            clip-path: polygon(0 0, calc(100% - 20px) 0, 100% 20px, 100% 100%, 20px 100%, 0 calc(100% - 20px));
        }
        .module-card::before { content: ''; position: absolute; top: 0; left: 0; width: 30px; height: 30px; border-right: 2px solid var(--text-dark); border-bottom: 2px solid var(--text-dark); }
        .module-card:hover { transform: translate(-4px, -4px); box-shadow: 10px 10px 0px rgba(124, 58, 237, 1); border-color: var(--accent-main); }
        
        .mod-title { font-family: var(--font-head); font-size: 1.5rem; font-weight: 700; margin-bottom: 1rem; text-transform: uppercase; display: flex; align-items: center; gap: 0.75rem;}
        .mod-title i { color: var(--accent-main); }

        /* ================= TELEMETRY STATS ================= */
        .telemetry-grid { display: grid; grid-template-columns: repeat(4, 1fr); border: 2px solid var(--text-dark); margin-bottom: 3rem; background: var(--bg-panel);}
        .tel-block { padding: 2rem 1.5rem; border-right: 2px solid var(--text-dark); display: flex; flex-direction: column; justify-content: center; }
        .tel-block:last-child { border-right: none; }
        .tel-val { font-family: var(--font-head); font-size: 3rem; font-weight: 700; color: var(--accent-main); line-height: 1; margin-bottom: 0.5rem; }
        .tel-label { font-family: var(--font-mono); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-tech);}

        /* ================= METADATA TAGS ================= */
        .sys-tag { 
            font-family: var(--font-mono); font-size: 0.75rem; font-weight: 700; padding: 0.3rem 0.6rem; 
            border: 1px solid var(--text-dark); color: var(--text-dark); text-transform: uppercase; display: inline-flex; align-items: center; gap: 0.4rem;
        }
        .sys-tag.accent { background: rgba(124, 58, 237, 0.05); color: var(--accent-main); border-color: var(--accent-main); }
        .sys-tag.warning { background: rgba(245, 158, 11, 0.05); color: #f59e0b; border-color: #f59e0b; }

        /* ================= BUTTONS ================= */
        .btn-tech {
            font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem; text-transform: uppercase;
            padding: 0.8rem 1.5rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            background: var(--bg-base); color: var(--text-dark); border: 2px solid var(--text-dark);
            position: relative; overflow: hidden; cursor: pointer;
            clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
            transition: color 0.3s;
        }
        .btn-tech::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: var(--accent-main); z-index: -1; transition: left 0.3s cubic-bezier(0.7, 0, 0.3, 1);
        }
        .btn-tech:hover { color: #fff; border-color: var(--accent-main); }
        .btn-tech:hover::before { left: 0; }
        
        .btn-tech.danger { border-color: #ef4444; color: #ef4444; }
        .btn-tech.danger::before { background: #ef4444; }
        .btn-tech.danger:hover { color: #fff; border-color: #ef4444; }

        .btn-action {
            background: var(--text-dark); color: #fff; border: none; padding: 0.6rem 1rem;
            font-family: var(--font-mono); font-weight: 700; font-size: 0.8rem; cursor: pointer;
            clip-path: polygon(8px 0, 100% 0, 100% calc(100% - 8px), calc(100% - 8px) 100%, 0 100%, 0 8px);
            transition: background 0.3s;
        }
        .btn-action:hover { background: var(--accent-main); }

        .btn-outline {
            background: transparent; color: var(--text-dark); border: 2px solid var(--text-dark); padding: 0.6rem 1rem;
            font-family: var(--font-mono); font-weight: 700; font-size: 0.8rem; cursor: pointer;
            clip-path: polygon(8px 0, 100% 0, 100% calc(100% - 8px), calc(100% - 8px) 100%, 0 100%, 0 8px);
            transition: color 0.3s, background 0.3s;
        }
        .btn-outline.danger { color: #ef4444; border-color: #ef4444; }
        .btn-outline.danger:hover { background: #ef4444; color: #fff; }

        /* ================= FORMS ================= */
        .form-label { font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--text-dark); margin-bottom: 0.5rem; display: block;}
        .form-control-custom, .form-select-custom {
            width: 100%; padding: 0.85rem 1.2rem; background: var(--bg-base); border: 1px solid var(--text-tech);
            color: var(--text-dark); font-family: var(--font-body); font-size: 0.95rem; outline: none; transition: border 0.2s;
            border-radius: 0; -webkit-appearance: none;
        }
        .form-control-custom:focus, .form-select-custom:focus { border-color: var(--text-dark); border-width: 2px; padding: calc(0.85rem - 1px) calc(1.2rem - 1px); }

        /* Grid for Form */
        .form-grid { display: grid; grid-template-columns: 1fr 1fr 1fr 1.5fr auto; gap: 1rem; align-items: flex-end; }

        /* ================= TABLES ================= */
        .table-responsive { overflow-x: auto; background: var(--bg-base); border: 2px solid var(--text-dark); margin-bottom: 1rem; }
        .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
        .custom-table th, .custom-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--text-tech); font-size: 0.9rem; }
        .custom-table th { background: var(--bg-panel); color: var(--text-dark); font-family: var(--font-mono); font-weight: 700; font-size: 0.8rem; text-transform: uppercase; }
        .custom-table tbody tr { transition: background 0.2s ease; }
        .custom-table tbody tr:hover { background: rgba(124, 58, 237, 0.05); }
        .custom-table tbody tr:last-child td { border-bottom: none; }

        /* ================= ALERTS ================= */
        .alert { font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; border: 2px solid transparent; padding: 1rem 1.5rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;}
        .alert-danger { background: var(--bg-base); color: #ef4444; border-color: #ef4444; }
        .alert-success { background: var(--bg-base); color: #10b981; border-color: #10b981; }
        .alert-warning { background: var(--bg-base); color: #f59e0b; border-color: #f59e0b; }
        .btn-close-alert { background: none; border: none; color: inherit; font-size: 1.2rem; cursor: pointer; }

        @media (max-width: 1024px) {
            .telemetry-grid { grid-template-columns: repeat(2, 1fr); }
            .tel-block:nth-child(2) { border-right: none; }
            .tel-block:nth-child(1), .tel-block:nth-child(2) { border-bottom: 2px solid var(--text-dark); }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header class="tech-header">
    <div class="sys-logo interactive">
        <i class="fa-solid fa-server"></i> Admin Dashboard <div class="line"></div> ZCOER
    </div>
    <a href="logout.php" class="btn-tech danger interactive"><i class="fa-solid fa-power-off"></i> Logout</a>
</header>

<div class="dashboard-container">

    <?php if ($flashMessage): ?>
        <div class="alert <?php echo $flashClass; ?> interactive" id="alertBox">
            <div><?php echo htmlspecialchars($flashMessage); ?></div>
            <button type="button" class="btn-close-alert" onclick="document.getElementById('alertBox').style.display='none'">&times;</button>
        </div>
    <?php endif; ?>

    <!-- Summary Strip (4 Columns) -->
    <div class="telemetry-grid interactive">
        <div class="tel-block">
            <div class="tel-val" style="color: #f59e0b;"><?php echo $pendingRequests; ?></div>
            <div class="tel-label">Pending Requests</div>
        </div>
        <div class="tel-block">
            <div class="tel-val"><?php echo $totalStudents; ?></div>
            <div class="tel-label">Active Students</div>
        </div>
        <div class="tel-block">
            <div class="tel-val" style="color: #3b82f6;"><?php echo $totalParents; ?></div>
            <div class="tel-label">Active Parents</div>
        </div>
        <div class="tel-block">
            <div class="tel-val" style="color: var(--text-dark);"><?php echo $totalUsers; ?></div>
            <div class="tel-label">Total Users</div>
        </div>
    </div>

    <!-- MANUAL STAFF IDP ASSIGNMENT PANEL -->
    <div class="module-card interactive">
        <h3 class="mod-title"><i class="fa-solid fa-user-plus"></i> Create Staff Account</h3>
        <p style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech); margin-bottom: 2rem;">
            Assign login credentials for staff roles. Default password: <span class="sys-tag">Zeal@2026</span>
        </p>

        <form method="POST" action="admin_dashboard.php">
            <input type="hidden" name="action" value="create_staff_idp">
            <div class="form-grid">
                <div>
                    <label class="form-label">Staff Member Name</label>
                    <input type="text" name="staff_name" class="form-control-custom interactive" placeholder="e.g. Dr. Alan Smith" required autocomplete="off">
                </div>
                <div>
                    <label class="form-label">Username / Email</label>
                    <input type="text" name="staff_username" class="form-control-custom interactive" placeholder="e.g. alansmith@zeal.in" required autocomplete="off">
                </div>
                <div>
                    <label class="form-label">Assign Role</label>
                    <select name="staff_role" class="form-select-custom interactive" required>
                        <option value="" disabled selected>-- Select --</option>
                        <option value="Faculty">Faculty</option>
                        <option value="HOD">HOD</option>
                        <option value="GFM">GFM</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Branch / Dept</label>
                    <select name="staff_department" class="form-select-custom interactive" required>
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
                    <button type="submit" class="btn-action w-100 interactive" style="height: 44px;">Create Account</button>
                </div>
            </div>
        </form>
    </div>

    <!-- Student & Parent Approval Queue -->
    <div class="module-card interactive">
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
                        <tr><td colspan="5" style="text-align: center; padding: 2rem; font-family: var(--font-mono); font-weight: 700; color: var(--text-light);">No pending requests.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($requestsList as $req): ?>
                        <tr>
                            <td style="font-family: var(--font-mono); font-weight: 700; color: var(--accent-main);"><?php echo htmlspecialchars($req['prn_number']); ?></td>
                            <td>
                                <strong style="font-family: var(--font-body); text-transform: uppercase; display: block;"><?php echo htmlspecialchars($req['full_name']); ?></strong>
                                <span style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-tech);"><?php echo htmlspecialchars($req['email']); ?></span>
                            </td>
                            <td>
                                <strong style="font-family: var(--font-body); text-transform: uppercase; display: block;"><?php echo htmlspecialchars($req['parent_name'] ?? 'Parent'); ?></strong>
                                <span style="font-family: var(--font-mono); font-size: 0.75rem; color: #3b82f6;"><i class="fa-solid fa-envelope me-1"></i><?php echo htmlspecialchars($req['parent_email'] ?? '-'); ?></span>
                            </td>
                            <td><span class="sys-tag"><?php echo htmlspecialchars($req['department']); ?></span></td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.5rem; justify-content: flex-end;">
                                    <form method="post">
                                        <input type="hidden" name="action" value="approve_request">
                                        <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                        <button type="submit" class="btn-action interactive"><i class="fa-solid fa-check"></i> Approve</button>
                                    </form>
                                    <form method="post" onsubmit="return confirm('Reject request?');">
                                        <input type="hidden" name="action" value="reject_request">
                                        <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                        <button type="submit" class="btn-outline danger interactive"><i class="fa-solid fa-xmark"></i></button>
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
    <div class="module-card interactive">
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
                            <td style="font-family: var(--font-mono); font-weight: 700; color: var(--text-light);">#<?php echo $u['user_id']; ?></td>
                            <td style="font-family: var(--font-body); font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($u['name']); ?></td>
                            <td style="font-family: var(--font-mono); font-weight: 700;"><?php echo htmlspecialchars($u['username']); ?></td>
                            <td style="font-family: var(--font-mono); font-size: 0.8rem; color: #3b82f6;"><?php echo htmlspecialchars($u['email'] ?? $u['username']); ?></td>
                            <td><span class="sys-tag"><?php echo htmlspecialchars($u['role']); ?></span></td>
                            <td><span class="sys-tag accent"><?php echo htmlspecialchars($u['department'] ?: 'General'); ?></span></td>
                            <td>
                                <?php if ((int)$u['is_first_login'] === 1): ?>
                                    <span style="font-family: var(--font-mono); font-size: 0.75rem; color: #f59e0b; font-weight: 700;">Pending</span>
                                <?php else: ?>
                                    <span style="font-family: var(--font-mono); font-size: 0.75rem; color: #10b981; font-weight: 700;">Active</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <?php if ((int)$u['user_id'] !== (int)$_SESSION['user_id']): ?>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Delete user account?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="target_user_id" value="<?php echo $u['user_id']; ?>">
                                        <button type="submit" class="btn-outline danger interactive" style="padding: 0.4rem 0.6rem;"><i class="fa-solid fa-trash-can"></i></button>
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

<script>
document.addEventListener("DOMContentLoaded", () => {
    // Connect interactive hover class for cursor styling
    document.querySelectorAll('.interactive, button, a, input, select, textarea').forEach(el => {
        el.addEventListener("mouseenter", () => document.body.classList.add("hovering"));
        el.addEventListener("mouseleave", () => document.body.classList.remove("hovering"));
    });
});
</script>

<?php require_once __DIR__ . '/../includes/end_session_modal.php'; ?>
</body>
</html>