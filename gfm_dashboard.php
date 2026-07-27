<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Anti-caching headers to prevent browser back-button access after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/config/db.php';

// 1. AUTO-INITIALIZE GFM FACULTY MAPPING TABLE
function init_gfm_tables() {
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS gfm_faculty_mapping (
            id INT AUTO_INCREMENT PRIMARY KEY,
            gfm_id INT NOT NULL,
            faculty_id INT NOT NULL,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_gfm_fac (gfm_id, faculty_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("ALTER TABLE activities ADD COLUMN IF NOT EXISTS faculty_id INT NULL AFTER activity_id");
    } catch (PDOException $e) {
        error_log("GFM Table Init Error: " . $e->getMessage());
    }
}
init_gfm_tables();

// Check user authorization
$role = strtolower($_SESSION['role'] ?? '');
if (empty($_SESSION['user_id']) || !in_array($role, ['gfm', 'faculty', 'hod', 'admin'])) {
    header('Location: auth/login.php');
    exit;
}

$gfm_id = (int)$_SESSION['user_id'];
$gfmName = $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? "Prof. GFM";
$deptName = $_SESSION['department'] ?? "Electronics and Computer Engineering";
$collegeName = "Zeal College of Engineering";

$message = '';
$success_message = '';
$view = $_GET['view'] ?? 'dashboard';

// ----------------------------------------------------
// 2. ACTION HANDLERS (EMAIL-BASED FACULTY MONITORING)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $form_action = $_POST['form_action'];

    if ($form_action === 'add_faculty_by_email') {
        $email = trim($_POST['faculty_email'] ?? '');
        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE email = ? AND LOWER(role) = 'faculty'");
            $stmt->execute([$email]);
            $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($faculty) {
                try {
                    $stmtInsert = $pdo->prepare("INSERT INTO gfm_faculty_mapping (gfm_id, faculty_id) VALUES (?, ?)");
                    $stmtInsert->execute([$gfm_id, $faculty['user_id']]);
                    $success_message = "Successfully linked Faculty: " . htmlspecialchars($faculty['name']);
                } catch (PDOException $e) {
                    $message = "This faculty member is already on your monitoring list.";
                }
            } else {
                $message = "No Faculty account found with email '{$email}'. Ensure they are registered with 'faculty' role.";
            }
        } else {
            $message = "Please enter a valid Faculty email address.";
        }
    } elseif ($form_action === 'remove_faculty') {
        $fac_id_to_remove = (int)($_POST['faculty_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM gfm_faculty_mapping WHERE gfm_id = ? AND faculty_id = ?");
        if ($stmt->execute([$gfm_id, $fac_id_to_remove])) {
            $success_message = "Faculty member removed from your monitoring list.";
        }
    }
}

// ----------------------------------------------------
// 3. FETCH DATA & ANALYTICS FOR HOD-STYLE DRILL-DOWN
// ----------------------------------------------------

// LEVEL 1: Fetch all faculty mapped to this GFM
$stmtFac = $pdo->prepare("
    SELECT u.user_id, u.name, u.email,
           (SELECT COUNT(*) FROM faculty_classes fc WHERE fc.faculty_id = u.user_id) AS total_classes,
           (SELECT COUNT(*) FROM activities a WHERE a.faculty_id = u.user_id) AS total_activities
    FROM gfm_faculty_mapping gfm
    JOIN users u ON gfm.faculty_id = u.user_id
    WHERE gfm.gfm_id = ?
    ORDER BY u.name ASC
");
$stmtFac->execute([$gfm_id]);
$mapped_faculty = $stmtFac->fetchAll(PDO::FETCH_ASSOC) ?: [];

// LEVEL 2: Fetch specific Faculty's Classes
$faculty_info = null;
$faculty_classes = [];
$fid = isset($_GET['fid']) ? (int)$_GET['fid'] : 0;
if ($view === 'faculty_classes' && $fid > 0) {
    $check = $pdo->prepare("SELECT 1 FROM gfm_faculty_mapping WHERE gfm_id = ? AND faculty_id = ?");
    $check->execute([$gfm_id, $fid]);
    
    if ($check->fetchColumn()) {
        $stmtFacInfo = $pdo->prepare("SELECT name, email FROM users WHERE user_id = ?");
        $stmtFacInfo->execute([$fid]);
        $faculty_info = $stmtFacInfo->fetch(PDO::FETCH_ASSOC);

        $stmtClasses = $pdo->prepare("
            SELECT fc.class_id, fc.class_name, fc.subject_code, 
                   (SELECT COUNT(*) FROM users us WHERE LOWER(us.role) = 'student' AND us.department = fc.department AND us.academic_year = fc.academic_year AND us.division = fc.division) AS student_count
            FROM faculty_classes fc WHERE fc.faculty_id = ?
            ORDER BY fc.created_at DESC
        ");
        $stmtClasses->execute([$fid]);
        $faculty_classes = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $message = "Unauthorized access to this faculty member.";
        $view = 'reports';
    }
}

// LEVEL 3: Fetch specific Class Report
$selected_class = null;
$class_students = [];
$class_activities = [];
$cid = isset($_GET['cid']) ? (int)$_GET['cid'] : 0;
if ($view === 'class_report' && $fid > 0 && $cid > 0) {
    $check = $pdo->prepare("SELECT 1 FROM gfm_faculty_mapping WHERE gfm_id = ? AND faculty_id = ?");
    $check->execute([$gfm_id, $fid]);
    
    if ($check->fetchColumn()) {
        $stmtFacInfo = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
        $stmtFacInfo->execute([$fid]);
        $faculty_info = $stmtFacInfo->fetch(PDO::FETCH_ASSOC);

        $stmtClassInfo = $pdo->prepare("SELECT class_name, subject_code FROM faculty_classes WHERE class_id = ? AND faculty_id = ?");
        $stmtClassInfo->execute([$cid, $fid]);
        $selected_class = $stmtClassInfo->fetch(PDO::FETCH_ASSOC);

        if ($selected_class) {
            // Enrolled students
            $stmtSt = $pdo->prepare("
                SELECT u.username AS student_prn, CURRENT_TIMESTAMP AS added_at, u.name AS student_name, u.email AS student_email, st.roll_no
                FROM faculty_classes fc
                JOIN users u ON LOWER(u.role) = 'student' AND u.department = fc.department AND u.academic_year = fc.academic_year AND u.division = fc.division
                LEFT JOIN students st ON st.user_id = u.user_id
                WHERE fc.class_id = ?
                ORDER BY u.name ASC
            ");
            $stmtSt->execute([$cid]);
            $class_students = $stmtSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            // Class activities & submission stats
            $stmtAct = $pdo->prepare("
                SELECT a.activity_id, a.title, a.type, a.due_date, a.max_marks,
                       (SELECT COUNT(*) FROM submissions s WHERE s.activity_id = a.activity_id) AS submitted_count,
                       (SELECT AVG(marks) FROM submissions s WHERE s.activity_id = a.activity_id AND marks IS NOT NULL) AS avg_score
                FROM activities a
                WHERE a.faculty_id = ? AND (a.target_type = 'all' OR (a.target_type = 'class' AND a.target_id = ?))
                ORDER BY a.due_date DESC
            ");
            $stmtAct->execute([$fid, $cid]);
            $class_activities = $stmtAct->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $message = "Class not found.";
            $view = 'faculty_classes';
        }
    } else {
        $message = "Unauthorized access.";
        $view = 'reports';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GFM Dashboard | SAAES</title>
    
    <!-- Professional Fonts matching Landing Page -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=JetBrains+Mono:wght@100;400;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- PDF and Excel Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <style>
    /* ==========================================================================
       RIGID LIGHT SCI-FI DESIGN SYSTEM
       ========================================================================== */
    :root {
      --bg-base: #ffffff;
      --bg-panel: #fcfcfd;
      --text-dark: #0f172a;
      --text-tech: #475569;
      --text-light: #94a3b8;
      
      --accent-main: #7c3aed; /* Electric purple */
      --accent-glow: #a855f7;
      --accent-bg: rgba(124, 58, 237, 0.05);
      
      --grid-size: 40px;
      --border-harsh: 2px solid var(--text-dark);
      
      --font-head: 'Space Grotesk', sans-serif;
      --font-mono: 'JetBrains Mono', monospace;
      --font-body: 'Inter', sans-serif;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: var(--font-body);
      background-color: var(--bg-base);
      /* Blueprint Grid */
      background-image: 
          linear-gradient(rgba(124, 58, 237, 0.08) 1px, transparent 1px),
          linear-gradient(90deg, rgba(124, 58, 237, 0.08) 1px, transparent 1px);
      background-size: var(--grid-size) var(--grid-size);
      background-position: center center;
      color: var(--text-dark);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      line-height: 1.6;
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

    .app-container { display: flex; min-height: 100vh; width: 100%; position: relative; z-index: 1;}

    /* ================= SIDEBAR ================= */
    .sidebar {
      width: 280px;
      background: rgba(255, 255, 255, 0.95);
      border-right: var(--border-harsh);
      display: flex; flex-direction: column;
      position: fixed; top: 0; bottom: 0; left: 0; z-index: 200;
    }
    .sidebar-header {
      padding: 1.5rem; border-bottom: var(--border-harsh);
      display: flex; align-items: center; gap: 0.75rem;
    }
    .brand-logo {
      display: flex; align-items: center; gap: 0.75rem;
      font-family: var(--font-head); font-size: 1.3rem; font-weight: 700; color: var(--text-dark); text-transform: uppercase;
    }
    .brand-logo i { color: var(--accent-main); font-size: 1.4rem; }
    
    .sidebar-menu { padding: 1.5rem 1rem; display: flex; flex-direction: column; gap: 0.3rem; flex: 1; overflow-y: auto; }
    .menu-label { font-family: var(--font-mono); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-tech); margin: 1.5rem 0.5rem 0.5rem; font-weight: 700;}
    
    .sidebar-link {
      display: flex; align-items: center; gap: 0.85rem; padding: 0.8rem 1rem;
      color: var(--text-tech); font-family: var(--font-mono);
      font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;
      border: 2px solid transparent; position: relative;
    }
    .sidebar-link::before {
        content: '>'; position: absolute; left: 5px; opacity: 0; color: var(--text-dark); transition: 0.2s;
    }
    .sidebar-link:hover { color: var(--text-dark); padding-left: 1.5rem; }
    .sidebar-link:hover::before { opacity: 1; }
    .sidebar-link.active {
      background: var(--bg-base); color: var(--text-dark); 
      border: 2px solid var(--text-dark);
      box-shadow: 4px 4px 0px rgba(15, 23, 42, 0.1);
    }
    .sidebar-link i { font-size: 1.1rem; width: 22px; text-align: center; }

    .sidebar-user {
      padding: 1.25rem; border-top: var(--border-harsh);
      display: flex; align-items: center; gap: 0.75rem; background: var(--bg-panel);
    }
    .avatar {
      width: 40px; height: 40px; border: 2px solid var(--text-dark); background: var(--bg-base);
      display: flex; align-items: center; justify-content: center;
      font-family: var(--font-head); font-weight: 700; font-size: 1.2rem; color: var(--accent-main);
    }

    /* ================= MAIN CONTENT ================= */
    .content-wrapper { margin-left: 280px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; background: transparent; }
    
    .top-navbar {
      background: rgba(255, 255, 255, 0.95);
      border-bottom: var(--border-harsh); padding: 1rem 2.5rem;
      display: flex; justify-content: space-between; align-items: center;
      position: sticky; top: 0; z-index: 100;
    }
    .top-navbar h3 { font-family: var(--font-mono); font-weight: 700; font-size: 1rem; color: var(--text-dark); text-transform: uppercase; margin: 0; }
    
    .main-content { padding: 2rem 2.5rem; flex: 1; max-width: 1600px; width: 100%; margin: 0 auto; display: flex; flex-direction: column; gap: 2rem; }

    /* ================= MODULE CARDS ================= */
    .module-card {
      background: var(--bg-panel); border: 2px solid var(--text-dark);
      padding: 2.5rem; position: relative; transition: transform 0.2s, box-shadow 0.2s;
      clip-path: polygon(0 0, calc(100% - 20px) 0, 100% 20px, 100% 100%, 20px 100%, 0 calc(100% - 20px));
    }
    .module-card::before { content: ''; position: absolute; top: 0; left: 0; width: 30px; height: 30px; border-right: 2px solid var(--text-dark); border-bottom: 2px solid var(--text-dark); }
    .module-card:hover { transform: translate(-4px, -4px); box-shadow: 10px 10px 0px rgba(124, 58, 237, 1); border-color: var(--accent-main); }
    
    .hero-banner {
      background: var(--bg-base); border: 2px solid var(--text-dark);
      padding: 3rem; position: relative; overflow: hidden;
      clip-path: polygon(0 0, calc(100% - 30px) 0, 100% 30px, 100% 100%, 0 100%);
    }
    .hero-banner::after {
        content: ''; position: absolute; top: 0; right: 0; width: 30px; height: 30px; background: var(--text-dark);
    }
    .hero-content { position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; }

    /* ================= METADATA LABELS (TAGS) ================= */
    .sys-tag { 
        font-family: var(--font-mono); font-size: 0.75rem; font-weight: 700; padding: 0.3rem 0.6rem; 
        border: 1px solid var(--text-dark); color: var(--text-dark); text-transform: uppercase; display: inline-flex; align-items: center; gap: 0.4rem;
    }
    .sys-tag.accent { background: rgba(15, 23, 42, 0.05); color: var(--accent-main); border-color: var(--accent-main);}

    /* ================= BUTTONS ================= */
    .btn {
        font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem; text-transform: uppercase;
        padding: 0.8rem 1.5rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
        background: var(--bg-base); color: var(--text-dark); border: 2px solid var(--text-dark);
        position: relative; overflow: hidden; z-index: 1;
        clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
        transition: color 0.3s; cursor: pointer; text-decoration: none;
    }
    .btn::before {
        content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
        background: var(--text-dark); z-index: -1; transition: left 0.3s cubic-bezier(0.7, 0, 0.3, 1);
    }
    .btn:hover { color: #fff; border-color: var(--text-dark); }
    .btn:hover::before { left: 0; }
    
    .btn-primary { background: var(--text-dark); color: #fff; border-color: var(--text-dark); }
    .btn-primary:hover { color: #fff; }
    .btn-primary::before { background: var(--accent-main); }

    .btn-danger { border-color: #ef4444; color: #ef4444; }
    .btn-danger::before { background: #ef4444; }
    .btn-danger:hover { color: #fff; border-color: #ef4444; }

    .btn-outline { background: transparent; border: 2px solid var(--text-dark); color: var(--text-dark); }
    .btn-outline:hover { background: var(--text-dark); color: #fff; }

    /* ================= STATS GRID ================= */
    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); border: 2px solid var(--text-dark); background: var(--bg-panel); margin-bottom: 2rem;}
    .stat-block { padding: 2rem 1.5rem; border-right: 2px solid var(--text-dark); display: flex; flex-direction: column; justify-content: center; }
    .stat-block:last-child { border-right: none; }
    .stat-val { font-family: var(--font-head); font-size: 3rem; font-weight: 700; color: var(--accent-main); line-height: 1; margin-bottom: 0.5rem; }
    .stat-label { font-family: var(--font-mono); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-tech);}

    /* ================= TABLES ================= */
    .table-responsive { overflow-x: auto; background: var(--bg-base); border: 2px solid var(--text-dark); margin-bottom: 1rem; }
    .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
    .custom-table th, .custom-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--text-tech); font-size: 0.9rem; }
    .custom-table th { background: var(--bg-panel); color: var(--text-dark); font-family: var(--font-mono); font-weight: 700; font-size: 0.8rem; text-transform: uppercase; }
    .custom-table tbody tr { transition: background 0.2s ease; }
    .custom-table tbody tr:hover { background: rgba(124, 58, 237, 0.05); }
    .custom-table tbody tr:last-child td { border-bottom: none; }
    
    /* ================= ALERTS & FORMS ================= */
    .alert { font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; border: 2px solid transparent; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 2rem;}
    .alert-danger { background: var(--bg-base); color: #ef4444; border-color: #ef4444; }
    .alert-success { background: var(--bg-base); color: #10b981; border-color: #10b981; }

    .form-control-custom {
      width: 100%; padding: 0.85rem 1.2rem; background: var(--bg-base); border: 1px solid var(--text-tech);
      color: var(--text-dark); font-family: var(--font-body); font-size: 0.95rem; outline: none; transition: border 0.2s;
      border-radius: 0; -webkit-appearance: none;
    }
    .form-control-custom:focus { border-color: var(--text-dark); border-width: 2px; padding: calc(0.85rem - 1px) calc(1.2rem - 1px); }

    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: 1fr; }
        .stat-block { border-right: none !important; border-bottom: 2px solid var(--text-dark); }
        .stat-block:last-child { border-bottom: none; }
        .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
        .sidebar.show { transform: translateX(0); }
        .content-wrapper { margin-left: 0; }
    }
    </style>
</head>
<body>

<div class="app-container">

    <!-- LEFT SIDEBAR -->
    <aside class="sidebar" id="erpSidebar">
        <div class="sidebar-header">
            <a href="gfm_dashboard.php?view=dashboard" class="brand-logo interactive">
                <i class="fa-solid fa-graduation-cap"></i>
                <span>GFM Hub</span>
            </a>
        </div>

        <div class="sidebar-menu">
            <div class="menu-label">Navigation</div>
            <a href="?view=dashboard" class="sidebar-link interactive <?php echo ($view === 'dashboard') ? 'active' : ''; ?>">
                <span>Dashboard Overview</span>
            </a>
            <a href="?view=reports" class="sidebar-link interactive <?php echo in_array($view, ['reports', 'faculty_classes', 'class_report']) ? 'active' : ''; ?>">
                <span>Performance Reports</span>
            </a>

            <div class="menu-label">Account</div>
            <a href="auth/logout.php" class="sidebar-link interactive" style="color: #ef4444;">
                <span>Logout</span>
            </a>
        </div>

        <div class="sidebar-user">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="avatar"><?php echo strtoupper(substr($gfmName, 0, 1)); ?></div>
                <div>
                    <div style="font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem; color: var(--text-dark);"><?php echo htmlspecialchars($gfmName); ?></div>
                    <div style="font-family: var(--font-mono); font-size: 0.65rem; color: var(--text-tech); text-transform: uppercase; font-weight: 700;">GFM - <?php echo htmlspecialchars($deptName); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- CONTENT WRAPPER -->
    <div class="content-wrapper">
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline interactive d-lg-none" id="sidebarToggle" style="padding: 0.4rem 0.8rem;">Menu</button>
                <h3>
                    <?php 
                    if ($view === 'dashboard') echo 'GFM Dashboard';
                    elseif ($view === 'reports') echo 'Faculty Directory';
                    elseif ($view === 'faculty_classes') echo 'Faculty Classes';
                    elseif ($view === 'class_report') echo 'Class Analytics';
                    else echo 'GFM Dashboard';
                    ?>
                </h3>
            </div>

            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; background: var(--text-dark); color: #fff; padding: 0.4rem 0.8rem;">
                    Time <span style="color: var(--accent-glow);">|</span> <span id="clock">00:00:00</span>
                </div>
            </div>
        </header>

        <main class="main-content">

        <!-- ALERTS -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> Error: <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> Success: <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <!-- VIEW 1: OVERVIEW DASHBOARD -->
        <?php if ($view === 'dashboard'): ?>
            <div class="hero-banner">
                <div class="hero-content">
                    <div>
                        <h1 style="font-family: var(--font-head); font-size: 2.2rem; margin-bottom: 0.5rem; font-weight: 700; text-transform: uppercase;">GFM Monitoring Hub</h1>
                        <p style="color: var(--text-tech); font-family: var(--font-mono); font-size: 0.9rem;">Add faculty by their email address to monitor their class activity and student performance.</p>
                    </div>
                </div>
            </div>

            <!-- ADD FACULTY BY EMAIL CARD -->
            <div class="module-card">
                <h3 style="font-family: var(--font-head); font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; text-transform: uppercase; color: var(--text-dark);">
                    Monitor New Faculty
                </h3>
                <p style="color: var(--text-tech); font-family: var(--font-mono); font-size: 0.85rem; margin-bottom: 1.5rem;">
                    Enter the registered email address of a Faculty member to add them to your monitoring list.
                </p>

                <form action="gfm_dashboard.php?view=dashboard" method="POST" style="display: flex; gap: 1rem; max-width: 650px;">
                    <input type="hidden" name="form_action" value="add_faculty_by_email">
                    <input type="email" name="faculty_email" class="form-control-custom interactive" placeholder="Enter Faculty Email Address" required>
                    <button type="submit" class="btn btn-primary interactive" style="white-space: nowrap;">
                        Monitor Faculty
                    </button>
                </form>
            </div>

            <!-- OVERVIEW STATS -->
            <div class="stats-grid interactive">
                <div class="stat-block">
                    <div class="stat-val"><?php echo count($mapped_faculty); ?></div>
                    <div class="stat-label">Monitored Faculty</div>
                </div>
                <div class="stat-block">
                    <div class="stat-val" style="color: #3b82f6;"><?php echo array_sum(array_column($mapped_faculty, 'total_classes')); ?></div>
                    <div class="stat-label">Total Classes Created</div>
                </div>
                <div class="stat-block">
                    <div class="stat-val" style="color: #10b981;"><?php echo array_sum(array_column($mapped_faculty, 'total_activities')); ?></div>
                    <div class="stat-label">Total Activities Assigned</div>
                </div>
            </div>

            <!-- MONITORED FACULTY OVERVIEW -->
            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3 style="font-family: var(--font-head); font-size: 1.4rem; font-weight: 700; text-transform: uppercase;">Monitored Faculty Summary</h3>
                    <a href="?view=reports" class="btn btn-outline interactive" style="font-size: 0.75rem;">View Full Directory</a>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Faculty Name</th>
                                <th>Email</th>
                                <th>Classes</th>
                                <th>Activities</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mapped_faculty)): ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-tech); padding: 3rem; font-family: var(--font-mono); font-weight: 700;">No faculty linked yet. Input a faculty email address above to begin monitoring.</td></tr>
                            <?php else: ?>
                                <?php foreach ($mapped_faculty as $fac): ?>
                                <tr>
                                    <td><strong style="font-family: var(--font-body); text-transform: uppercase;"><?php echo htmlspecialchars($fac['name']); ?></strong></td>
                                    <td style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);"><?php echo htmlspecialchars($fac['email']); ?></td>
                                    <td><span class="sys-tag"><?php echo $fac['total_classes']; ?> Classes</span></td>
                                    <td><span class="sys-tag accent"><?php echo $fac['total_activities']; ?> Activities</span></td>
                                    <td style="text-align: right;">
                                        <a href="?view=faculty_classes&fid=<?php echo $fac['user_id']; ?>" class="btn btn-primary interactive" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                                            View Classes
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- VIEW 2: REPORTS / FACULTY ROSTER -->
        <?php elseif ($view === 'reports'): ?>
            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <div>
                        <h2 style="font-family: var(--font-head); font-size: 1.8rem; font-weight: 700; text-transform: uppercase;">Monitored Faculty Directory</h2>
                        <p style="color: var(--text-tech); font-family: var(--font-mono); font-size: 0.85rem;">Select a faculty member below to review their active classes and student reports.</p>
                    </div>
                    <span class="sys-tag accent">Total: <?php echo count($mapped_faculty); ?></span>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Faculty Name</th>
                                <th>Email</th>
                                <th>Classes Created</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mapped_faculty)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-tech); padding: 3rem; font-family: var(--font-mono); font-weight: 700;">No faculty linked. Go to <a href="?view=dashboard" style="color: var(--accent-main); text-decoration: underline;">Dashboard Overview</a> to add faculty by email.</td></tr>
                            <?php else: ?>
                                <?php foreach ($mapped_faculty as $fac): ?>
                                <tr>
                                    <td><strong style="font-family: var(--font-body); font-size: 1rem; text-transform: uppercase;"><?php echo htmlspecialchars($fac['name']); ?></strong></td>
                                    <td style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);"><?php echo htmlspecialchars($fac['email']); ?></td>
                                    <td><span class="sys-tag accent"><?php echo $fac['total_classes']; ?> Active Classes</span></td>
                                    <td style="text-align: right; display: flex; justify-content: flex-end; gap: 0.5rem;">
                                        <a href="?view=faculty_classes&fid=<?php echo $fac['user_id']; ?>" class="btn btn-primary interactive" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                                            View Classes
                                        </a>
                                        <form action="gfm_dashboard.php?view=reports" method="POST" style="display: inline;" onsubmit="return confirm('Stop monitoring this faculty member?');">
                                            <input type="hidden" name="form_action" value="remove_faculty">
                                            <input type="hidden" name="faculty_id" value="<?php echo $fac['user_id']; ?>">
                                            <button type="submit" class="btn btn-danger interactive" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                                                Unlink
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- VIEW 3: FACULTY CLASSES LIST -->
        <?php elseif ($view === 'faculty_classes' && $faculty_info): ?>
            <div style="margin-bottom: 1.5rem;">
                <a href="?view=reports" class="btn btn-outline interactive" style="margin-bottom: 1rem; font-size: 0.8rem;">
                    Back to Directory
                </a>
                <h2 style="font-family: var(--font-head); font-size: 2rem; font-weight: 700; text-transform: uppercase;">Classes Managed by <?php echo htmlspecialchars($faculty_info['name']); ?></h2>
                <p style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);">Email: <strong><?php echo htmlspecialchars($faculty_info['email']); ?></strong></p>
            </div>

            <div class="module-card">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Class / Group Name</th>
                                <th>Subject Code</th>
                                <th>Enrolled Students</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($faculty_classes)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-tech); padding: 3rem; font-family: var(--font-mono); font-weight: 700;">This faculty member hasn't created any classes yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($faculty_classes as $cls): ?>
                                <tr>
                                    <td><strong style="font-family: var(--font-head); font-size: 1.05rem; text-transform: uppercase;"><?php echo htmlspecialchars($cls['class_name']); ?></strong></td>
                                    <td><span style="font-family: var(--font-mono); font-weight: 700; color: var(--accent-main);"><?php echo htmlspecialchars($cls['subject_code'] ?: 'N/A'); ?></span></td>
                                    <td style="font-family: var(--font-mono); font-weight: 700; color: var(--text-tech);"><?php echo $cls['student_count']; ?> Students</td>
                                    <td style="text-align: right;">
                                        <a href="?view=class_report&fid=<?php echo $fid; ?>&cid=<?php echo $cls['class_id']; ?>" class="btn btn-primary interactive" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                                            View Class Report
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- VIEW 4: DETAILED CLASS REPORT -->
        <?php elseif ($view === 'class_report' && $selected_class): ?>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
                <div>
                    <a href="?view=faculty_classes&fid=<?php echo $fid; ?>" class="btn btn-outline interactive" style="margin-bottom: 1rem; font-size: 0.8rem;">
                        Back to Classes
                    </a>
                    <h2 style="font-family: var(--font-head); font-size: 2rem; font-weight: 700; text-transform: uppercase;">Class Report: <?php echo htmlspecialchars($selected_class['class_name']); ?></h2>
                    <p style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);">
                        Faculty: <strong><?php echo htmlspecialchars($faculty_info['name'] ?? 'Faculty'); ?></strong> | Subject: <strong style="color: var(--accent-main);"><?php echo htmlspecialchars($selected_class['subject_code'] ?: 'General'); ?></strong>
                    </p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-outline interactive" onclick="exportPDF()">Export PDF</button>
                    <button class="btn btn-primary interactive" onclick="exportExcel()">Export Excel</button>
                </div>
            </div>

            <div id="exportTable">
                <!-- STATS SUMMARY -->
                <div class="stats-grid interactive" style="margin-bottom: 2rem;">
                    <div class="stat-block">
                        <div class="stat-val"><?php echo count($class_students); ?></div>
                        <div class="stat-label">Enrolled Students</div>
                    </div>
                    <div class="stat-block">
                        <div class="stat-val" style="color: #10b981;"><?php echo count($class_activities); ?></div>
                        <div class="stat-label">Total Activities</div>
                    </div>
                </div>

                <!-- ACTIVITIES ANALYTICS -->
                <div class="module-card" style="margin-bottom: 2rem;">
                    <h3 style="font-family: var(--font-head); font-size: 1.4rem; font-weight: 700; margin-bottom: 1.5rem; text-transform: uppercase;">Activity Submissions & Scores</h3>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Activity Title</th>
                                    <th>Due Date</th>
                                    <th style="text-align: center;">Submitted</th>
                                    <th style="text-align: center;">Pending</th>
                                    <th>Class Avg Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($class_activities)): ?>
                                    <tr><td colspan="5" style="text-align: center; color: var(--text-tech); padding: 2rem; font-family: var(--font-mono); font-weight: 700;">No activities assigned for this class yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($class_activities as $act): 
                                        $pending = max(0, count($class_students) - $act['submitted_count']);
                                        $avg = $act['avg_score'] !== null ? number_format($act['avg_score'], 1) : '-';
                                    ?>
                                    <tr>
                                        <td><strong style="font-family: var(--font-head); font-size: 1rem; text-transform: uppercase;"><?php echo htmlspecialchars($act['title']); ?></strong></td>
                                        <td style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-tech);"><?php echo date('M d, Y', strtotime($act['due_date'])); ?></td>
                                        <td style="text-align: center;"><strong style="color: #10b981; font-family: var(--font-mono); font-size: 1.1rem;"><?php echo $act['submitted_count']; ?></strong></td>
                                        <td style="text-align: center;"><strong style="color: #ef4444; font-family: var(--font-mono); font-size: 1.1rem;"><?php echo $pending; ?></strong></td>
                                        <td><strong style="font-family: var(--font-mono); font-size: 1.1rem;"><?php echo $avg; ?></strong> <span style="font-size: 0.75rem; color: var(--text-tech);">/ <?php echo $act['max_marks']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ENROLLED STUDENTS ROSTER -->
                <div class="module-card">
                    <h3 style="font-family: var(--font-head); font-size: 1.4rem; font-weight: 700; margin-bottom: 1.5rem; text-transform: uppercase;">Enrolled Student Roster</h3>
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Student PRN</th>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Roll No</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($class_students)): ?>
                                    <tr><td colspan="4" style="text-align: center; color: var(--text-tech); padding: 2rem; font-family: var(--font-mono); font-weight: 700;">No students added to this class yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($class_students as $st): ?>
                                    <tr>
                                        <td style="font-family: var(--font-mono); font-weight: 700; color: var(--accent-main);"><?php echo htmlspecialchars($st['student_prn']); ?></td>
                                        <td><strong style="font-family: var(--font-body); font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($st['student_name'] ?: 'Registered Student'); ?></strong></td>
                                        <td style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-tech);"><?php echo htmlspecialchars($st['student_email'] ?: '—'); ?></td>
                                        <td style="font-family: var(--font-mono); font-weight: 700;"><?php echo htmlspecialchars($st['roll_no'] ?: '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        </main>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // 1. Cursor Hover interactions
    const attachCursorHover = () => {
        document.querySelectorAll('.interactive, button, a, input, select, textarea').forEach(el => {
            el.addEventListener("mouseenter", () => document.body.classList.add("hovering"));
            el.addEventListener("mouseleave", () => document.body.classList.remove("hovering"));
        });
    };
    attachCursorHover();
    
    const observer = new MutationObserver(attachCursorHover);
    observer.observe(document.body, { childList: true, subtree: true });

    // 2. Sidebar Toggle (Mobile)
    const sidebarToggle = document.getElementById('sidebarToggle');
    const erpSidebar = document.getElementById('erpSidebar');

    if (sidebarToggle && erpSidebar) {
      sidebarToggle.addEventListener('click', () => {
        erpSidebar.classList.toggle('show');
      });
    }

    // 3. Live Clock
    const clockEl = document.getElementById('clock');
    if (clockEl) {
        function updateClock() {
            const now = new Date();
            const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
            const ist = new Date(utc + (3600000 * 5.5));
            
            let h = ist.getHours(), m = ist.getMinutes(), s = ist.getSeconds();
            h = h < 10 ? '0'+h : h;
            m = m < 10 ? '0'+m : m;
            s = s < 10 ? '0'+s : s;
            clockEl.textContent = `${h}:${m}:${s}`;
        }
        setInterval(updateClock, 1000);
        updateClock();
    }
});

function exportPDF() {
    var element = document.getElementById('exportTable');
    if (!element) return;
    var opt = {
      margin:       10,
      filename:     'Class_Report.pdf',
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2 },
      jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}

function exportExcel() {
    var element = document.getElementById("exportTable");
    if (!element) return;
    var wb = XLSX.utils.table_to_book(element, {sheet:"Class_Report"});
    XLSX.writeFile(wb, "Class_Report.xlsx");
}
</script>

<?php 
$modalPath = __DIR__ . '/includes/end_session_modal.php';
if (file_exists($modalPath)) {
    include_once $modalPath;
}
?>
</body>
</html>