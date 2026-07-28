<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Anti-caching headers to prevent browser back-button access after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/config/db.php';

// 1. AUTO-INITIALIZE GFM TABLES (Mapping removed, now department-based)
function init_gfm_tables() {
    global $pdo;
    try {
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
    // Legacy mapping actions removed; automatic department matching is used.
}

// ----------------------------------------------------
// 3. FETCH DATA & ANALYTICS FOR HOD-STYLE DRILL-DOWN
// ----------------------------------------------------

// LEVEL 1: Fetch all faculty in this GFM's department
$stmtFac = $pdo->prepare("
    SELECT DISTINCT u.user_id, u.name, u.email,
           (SELECT COUNT(*) FROM faculty_classes fc WHERE fc.faculty_id = u.user_id AND fc.department = ?) AS total_classes,
           (SELECT COUNT(*) FROM activities a WHERE a.faculty_id = u.user_id) AS total_activities
    FROM users u
    JOIN faculty_classes fc_main ON fc_main.faculty_id = u.user_id
    WHERE fc_main.department = ? AND LOWER(u.role) = 'faculty'
    ORDER BY u.name ASC
");
$stmtFac->execute([$deptName, $deptName]);
$mapped_faculty = $stmtFac->fetchAll(PDO::FETCH_ASSOC) ?: [];

// LEVEL 1.5: ACADEMIC YEARS STATS for Reports View
$years_list = [
    'FY' => 'First Year (FY)', 
    'SY' => 'Second Year (SY)', 
    'TY' => 'Third Year (TY)', 
    'Final Year' => 'Final Year (B.Tech)'
];
$year_stats = [];
foreach ($years_list as $y_code => $y_name) {
    $stmtY = $pdo->prepare("
        SELECT COUNT(DISTINCT fc.subject_code) AS subject_count,
               COUNT(DISTINCT fc.faculty_id) AS faculty_count,
               COUNT(fc.class_id) AS class_count
        FROM faculty_classes fc
        WHERE fc.department = ? AND UPPER(fc.academic_year) = UPPER(?)
    ");
    $stmtY->execute([$deptName, $y_code]);
    $rowY = $stmtY->fetch(PDO::FETCH_ASSOC);
    $year_stats[$y_code] = [
        'name' => $y_name,
        'subject_count' => (int)($rowY['subject_count'] ?? 0),
        'faculty_count' => (int)($rowY['faculty_count'] ?? 0),
        'class_count' => (int)($rowY['class_count'] ?? 0)
    ];
}

// NEW DRILL-DOWN: Classes in a selected Year
$selected_year = $_GET['year'] ?? '';
$year_classes = [];
if ($view === 'gfm_year_classes' && !empty($selected_year)) {
    $stmtYrCls = $pdo->prepare("
        SELECT fc.class_id, fc.class_name, fc.subject_code, fc.academic_year, fc.faculty_id,
               u.name AS faculty_name, u.email AS faculty_email,
               (SELECT COUNT(*) FROM users us WHERE LOWER(us.role) = 'student' AND us.department = fc.department AND us.academic_year = fc.academic_year AND us.division = fc.division) AS student_count
        FROM faculty_classes fc
        JOIN users u ON u.user_id = fc.faculty_id
        WHERE fc.department = ? AND UPPER(fc.academic_year) = UPPER(?)
        ORDER BY fc.class_name ASC, u.name ASC
    ");
    $stmtYrCls->execute([$deptName, $selected_year]);
    $year_classes = $stmtYrCls->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// LEVEL 2: Fetch specific Faculty's Classes
$faculty_info = null;
$faculty_classes = [];
$fid = isset($_GET['fid']) ? (int)$_GET['fid'] : 0;
if ($view === 'faculty_classes' && $fid > 0) {
    $check = $pdo->prepare("SELECT 1 FROM faculty_classes WHERE department = ? AND faculty_id = ? LIMIT 1");
    $check->execute([$deptName, $fid]);
    
    if ($check->fetchColumn()) {
        $stmtFacInfo = $pdo->prepare("SELECT name, email FROM users WHERE user_id = ?");
        $stmtFacInfo->execute([$fid]);
        $faculty_info = $stmtFacInfo->fetch(PDO::FETCH_ASSOC);

        $stmtClasses = $pdo->prepare("
            SELECT fc.class_id, fc.class_name, fc.subject_code, fc.academic_year,
                   (SELECT COUNT(*) FROM users us WHERE LOWER(us.role) = 'student' AND us.department = fc.department AND us.academic_year = fc.academic_year AND us.division = fc.division) AS student_count
            FROM faculty_classes fc WHERE fc.faculty_id = ? AND fc.department = ?
            ORDER BY fc.created_at DESC
        ");
        $stmtClasses->execute([$fid, $deptName]);
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
    $check = $pdo->prepare("SELECT 1 FROM faculty_classes WHERE class_id = ? AND department = ?");
    $check->execute([$cid, $deptName]);
    
    if ($check->fetchColumn()) {
        $stmtFacInfo = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
        $stmtFacInfo->execute([$fid]);
        $faculty_info = $stmtFacInfo->fetch(PDO::FETCH_ASSOC);

        $stmtClassInfo = $pdo->prepare("SELECT class_name, subject_code, academic_year FROM faculty_classes WHERE class_id = ? AND faculty_id = ?");
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
    
    <!-- Clean Academic Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- PDF and Excel Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    
    <style>
    /* ==========================================================================
       TRADITIONAL ACADEMIC DESIGN SYSTEM
       ========================================================================== */
    :root {
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
      font-family: var(--font-main);
      background-color: var(--bg-body);
      color: var(--text-main);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }

    ::selection { background: var(--blue-accent); color: #fff; }
    a { text-decoration: none; color: inherit; }

    .app-container { display: flex; min-height: 100vh; width: 100%; position: relative;}

    /* ================= SIDEBAR ================= */
    .sidebar {
      width: 260px;
      background: var(--bg-card);
      border-right: 1px solid var(--border-color);
      display: flex; flex-direction: column;
      position: fixed; top: 0; bottom: 0; left: 0; z-index: 200;
    }
    .sidebar-header {
      padding: 1.5rem; border-bottom: 1px solid var(--border-color);
      display: flex; align-items: center; gap: 0.75rem;
    }
    .brand-logo {
      display: flex; align-items: center; gap: 0.75rem;
      font-weight: 700; font-size: 1.25rem; color: var(--navy-primary);
    }
    .brand-logo i { color: var(--blue-accent); font-size: 1.4rem; }
    
    .sidebar-menu { padding: 1.5rem 1rem; display: flex; flex-direction: column; gap: 0.25rem; flex: 1; overflow-y: auto; }
    
    .sidebar-link {
      display: flex; align-items: center; gap: 0.75rem; padding: 0.65rem 1rem;
      color: var(--text-main); font-weight: 500; font-size: 0.9rem; border-radius: var(--radius-md);
      transition: all 0.2s ease;
    }
    .sidebar-link:hover { background: #f1f5f9; color: var(--blue-accent); }
    .sidebar-link.active {
      background: #eff6ff; color: var(--blue-accent); font-weight: 600;
    }
    .sidebar-link i { font-size: 1rem; width: 20px; text-align: center; color: var(--text-muted); }
    .sidebar-link.active i, .sidebar-link:hover i { color: var(--blue-accent); }

    .sidebar-user {
      padding: 1.25rem; border-top: 1px solid var(--border-color);
      display: flex; align-items: center; gap: 0.75rem; background: var(--bg-card);
    }
    .avatar {
      width: 36px; height: 36px; background: #f1f5f9; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-weight: 600; font-size: 1rem; color: var(--navy-primary);
    }

    /* ================= MAIN CONTENT ================= */
    .content-wrapper { margin-left: 260px; flex: 1; display: flex; flex-direction: column; min-height: 100vh;}
    
    .top-navbar {
      background: var(--bg-card); border-bottom: 1px solid var(--border-color); padding: 0 2rem;
      display: flex; justify-content: space-between; align-items: center;
      position: sticky; top: 0; z-index: 100; height: 70px;
    }
    .top-navbar h3 { font-weight: 600; font-size: 1.1rem; color: var(--navy-primary); margin: 0; }
    
    .main-content { padding: 2rem; flex: 1; max-width: 1400px; width: 100%; margin: 0 auto; display: flex; flex-direction: column; gap: 1.5rem; }

    /* ================= MODULE CARDS ================= */
    .module-card {
      background: var(--bg-card); border: 1px solid var(--border-color);
      padding: 1.5rem 2rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); 
    }
    
    .hero-banner {
      background: linear-gradient(135deg, var(--navy-primary), #1e3a8a); color: #fff;
      padding: 2.5rem 3rem; border-radius: var(--radius-lg); box-shadow: var(--shadow-md);
    }
    .hero-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; }
    .hero-title { font-size: 1.75rem; font-weight: 700; margin-bottom: 0.5rem; }
    .hero-subtitle { color: #e2e8f0; font-size: 0.95rem; margin: 0; }

    /* ================= STATS GRID ================= */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1rem;}
    .stat-block { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; flex-direction: column; justify-content: center; box-shadow: var(--shadow-sm); }
    .stat-val { font-size: 2.25rem; font-weight: 700; color: var(--blue-accent); line-height: 1; margin-bottom: 0.5rem; }
    .stat-label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;}

    /* ================= TAGS / BADGES ================= */
    .sys-tag { font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.6rem; border-radius: 999px; display: inline-flex; align-items: center; gap: 0.4rem; background: #f1f5f9; color: var(--text-muted); }
    .sys-tag.accent { background: #eff6ff; color: var(--blue-accent); }
    .sys-tag.success { background: #dcfce7; color: var(--success); }
    .sys-tag.danger { background: #fee2e2; color: var(--danger); }
    .sys-tag.warning { background: #fef3c7; color: var(--warning); }
    .sys-tag.info { background: #e0f2fe; color: #0284c7; }

    /* ================= BUTTONS ================= */
    .btn {
        font-family: var(--font-main); font-weight: 500; font-size: 0.85rem;
        padding: 0.6rem 1.2rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
        border-radius: var(--radius-md); border: 1px solid transparent; cursor: pointer; transition: all 0.2s ease; text-decoration: none;
    }
    .btn-primary { background: var(--blue-accent); color: #fff; }
    .btn-primary:hover { background: #1d4ed8; }
    
    .btn-danger { background: var(--danger); color: #fff; }
    .btn-danger:hover { background: #dc2626; }

    .btn-outline { background: transparent; border-color: var(--border-color); color: var(--text-main); }
    .btn-outline:hover { background: var(--bg-body); border-color: var(--text-muted); }
    
    .btn-outline.danger { color: var(--danger); border-color: #fca5a5; }
    .btn-outline.danger:hover { background: #fef2f2; color: #dc2626; }

    /* ================= TABLES ================= */
    .table-responsive { overflow-x: auto; border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 1rem; }
    .custom-table { width: 100%; border-collapse: collapse; text-align: left; background: var(--bg-card); }
    .custom-table th, .custom-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; vertical-align: middle; }
    .custom-table th { background: var(--bg-body); color: var(--text-muted); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;}
    .custom-table tbody tr:hover { background: #f8fafc; }
    .custom-table tbody tr:last-child td { border-bottom: none; }

    /* ================= ALERTS & FORMS ================= */
    .alert { font-size: 0.9rem; font-weight: 500; border-radius: var(--radius-md); padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;}
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

    .form-label { font-size: 0.85rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.4rem; display: block;}
    .form-control-custom, .form-select-custom {
      width: 100%; padding: 0.6rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color);
      color: var(--text-main); font-family: inherit; font-size: 0.9rem; outline: none; transition: border 0.2s;
      border-radius: var(--radius-md);
    }
    .form-control-custom:focus, .form-select-custom:focus { border-color: var(--blue-accent); background: var(--bg-card); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

    @media (max-width: 1024px) {
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
            <a href="gfm_dashboard.php?view=dashboard" class="brand-logo">
                <i class="fa-solid fa-graduation-cap"></i>
                <span>GFM Hub</span>
            </a>
        </div>

        <div class="sidebar-menu">
            <a href="?view=dashboard" class="sidebar-link <?php echo ($view === 'dashboard') ? 'active' : ''; ?>">
                <i class="fa-solid fa-house"></i> <span>Dashboard Overview</span>
            </a>
            <a href="?view=reports" class="sidebar-link <?php echo in_array($view, ['reports', 'faculty_classes', 'class_report']) ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i> <span>Performance Reports</span>
            </a>
            <a href="auth/logout.php" class="sidebar-link" style="color: #ef4444; margin-top: auto;">
                <i class="fa-solid fa-power-off"></i> <span>Logout</span>
            </a>
        </div>

        <div class="sidebar-user">
            <div class="avatar"><?php echo strtoupper(substr($gfmName, 0, 1)); ?></div>
            <div>
                <div style="font-weight: 600; font-size: 0.85rem; color: var(--navy-primary);"><?php echo htmlspecialchars($gfmName); ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">GFM - <?php echo htmlspecialchars($deptName); ?></div>
            </div>
        </div>
    </aside>

    <!-- CONTENT WRAPPER -->
    <div class="content-wrapper">
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline d-lg-none" id="sidebarToggle" style="padding: 0.4rem 0.8rem;"><i class="fa-solid fa-bars"></i></button>
                <h3>
                    <?php 
                    if ($view === 'dashboard') echo 'GFM Dashboard';
                    elseif ($view === 'reports') echo 'Academic Years';
                    elseif ($view === 'gfm_year_classes') echo 'Year Classes';
                    elseif ($view === 'faculty_classes') echo 'Faculty Classes';
                    elseif ($view === 'class_report') echo 'Class Analytics';
                    else echo 'GFM Dashboard';
                    ?>
                </h3>
            </div>

            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-regular fa-clock"></i> <span id="clock">00:00:00</span>
                </div>
            </div>
        </header>

        <main class="main-content">

        <!-- ALERTS -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <!-- VIEW 1: OVERVIEW DASHBOARD -->
        <?php if ($view === 'dashboard'): ?>
            <div class="hero-banner">
                <div class="hero-content">
                    <div>
                        <h1 class="hero-title">GFM Monitoring Hub</h1>
                        <p class="hero-subtitle">Your dashboard automatically fetches all active faculty and classes in your department.</p>
                    </div>
                </div>
            </div>

            <!-- OVERVIEW STATS -->
            <div class="stats-grid">
                <div class="stat-block">
                    <div class="stat-val"><?php echo count($mapped_faculty); ?></div>
                    <div class="stat-label">Active Faculty</div>
                </div>
                <div class="stat-block">
                    <div class="stat-val" style="color: var(--warning);"><?php echo array_sum(array_column($mapped_faculty, 'total_classes')); ?></div>
                    <div class="stat-label">Total Classes Created</div>
                </div>
                <div class="stat-block">
                    <div class="stat-val" style="color: var(--success);"><?php echo array_sum(array_column($mapped_faculty, 'total_activities')); ?></div>
                    <div class="stat-label">Total Activities Assigned</div>
                </div>
            </div>

        <!-- VIEW 2: REPORTS / ACADEMIC YEARS SELECTION -->
        <?php elseif ($view === 'reports'): ?>
            <div class="hero-banner" style="margin-bottom: 2rem;">
                <div class="hero-content">
                    <div>
                        <h1 class="hero-title">Academic Year Performance Reports</h1>
                        <p class="hero-subtitle">Select an academic year to inspect faculty classes and performance reports for your department.</p>
                    </div>
                </div>
            </div>

            <!-- YEAR SELECTION CARDS -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
                <?php foreach ($year_stats as $y_code => $y_data): ?>
                <div class="module-card" style="display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span class="sys-tag accent" style="font-size: 0.85rem;"><?php echo $y_code; ?></span>
                            <span style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;"><?php echo $y_data['class_count']; ?> Classes</span>
                        </div>
                        <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1rem; color: var(--text-main);"><?php echo htmlspecialchars($y_data['name']); ?></h3>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 1.5rem;">
                            <div style="background: var(--bg-body); padding: 0.75rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 1.1rem; font-weight: 700; color: var(--navy-primary);"><?php echo $y_data['subject_count']; ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Subjects</div>
                            </div>
                            <div style="background: var(--bg-body); padding: 0.75rem; border-radius: 8px; text-align: center;">
                                <div style="font-size: 1.1rem; font-weight: 700; color: var(--navy-primary);"><?php echo $y_data['faculty_count']; ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">Faculty</div>
                            </div>
                        </div>
                    </div>
                    
                    <a href="?view=gfm_year_classes&year=<?php echo urlencode($y_code); ?>" class="btn btn-primary" style="width: 100%; justify-content: center;">
                        View Classes <i class="fa-solid fa-arrow-right" style="margin-left: 0.5rem;"></i>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

        <!-- NEW DRILL-DOWN VIEW: CLASSES IN A YEAR -->
        <?php elseif ($view === 'gfm_year_classes' && !empty($selected_year)): ?>
            <div style="margin-bottom: 1.5rem;">
                <a href="?view=reports" class="btn btn-outline" style="margin-bottom: 1rem; font-size: 0.8rem;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Year Selection
                </a>
                <h2 style="font-size: 1.8rem; font-weight: 700; color: var(--navy-primary); margin-bottom: 0.2rem;">
                    Classes in <?php echo htmlspecialchars($years_list[$selected_year] ?? $selected_year); ?>
                </h2>
                <p style="font-size: 0.9rem; color: var(--text-muted);">Select a class to view its detailed performance report.</p>
            </div>

            <div class="module-card">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Class / Group Name</th>
                                <th>Subject Code</th>
                                <th>Faculty</th>
                                <th>Enrolled Students</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($year_classes)): ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 3rem; font-weight: 500;">No classes found for this year in your department.</td></tr>
                            <?php else: ?>
                                <?php foreach ($year_classes as $cls): ?>
                                <tr>
                                    <td><strong style="font-weight: 600; color: var(--text-main); font-size: 0.95rem;"><?php echo htmlspecialchars($cls['class_name']); ?></strong></td>
                                    <td><span class="sys-tag accent"><?php echo htmlspecialchars($cls['subject_code']); ?></span></td>
                                    <td>
                                        <div style="font-size: 0.9rem; font-weight: 500; color: var(--text-main);"><?php echo htmlspecialchars($cls['faculty_name']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($cls['faculty_email']); ?></div>
                                    </td>
                                    <td>
                                        <span class="sys-tag info">
                                            <i class="fa-solid fa-users" style="margin-right: 4px;"></i> <?php echo $cls['student_count']; ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="?view=class_report&fid=<?php echo $cls['faculty_id']; ?>&cid=<?php echo $cls['class_id']; ?>" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                                            View Report
                                        </a>
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
                <a href="?view=reports" class="btn btn-outline" style="margin-bottom: 1rem; font-size: 0.8rem;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Directory
                </a>
                <h2 style="font-size: 1.8rem; font-weight: 700; color: var(--navy-primary); margin-bottom: 0.2rem;">Classes Managed by <?php echo htmlspecialchars($faculty_info['name']); ?></h2>
                <p style="font-size: 0.9rem; color: var(--text-muted);">Email: <strong><?php echo htmlspecialchars($faculty_info['email']); ?></strong></p>
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
                                <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 3rem; font-weight: 500;">This faculty member hasn't created any classes yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($faculty_classes as $cls): ?>
                                <tr>
                                    <td><strong style="font-size: 1rem; color: var(--text-main);"><?php echo htmlspecialchars($cls['class_name']); ?></strong></td>
                                    <td><span style="font-weight: 600; color: var(--blue-accent);"><?php echo htmlspecialchars($cls['subject_code'] ?: 'N/A'); ?></span></td>
                                    <td><span style="font-weight: 500; color: var(--text-muted);"><?php echo $cls['student_count']; ?> Students</span></td>
                                    <td style="text-align: right;">
                                        <a href="?view=class_report&fid=<?php echo $fid; ?>&cid=<?php echo $cls['class_id']; ?>" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
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
                    <a href="?view=faculty_classes&fid=<?php echo $fid; ?>" class="btn btn-outline" style="margin-bottom: 1rem; font-size: 0.8rem;">
                        <i class="fa-solid fa-arrow-left"></i> Back to Classes
                    </a>
                    <h2 style="font-size: 1.8rem; font-weight: 700; color: var(--navy-primary); margin-bottom: 0.2rem;">Class Report: <?php echo htmlspecialchars($selected_class['class_name']); ?></h2>
                    <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">
                        Faculty: <strong><?php echo htmlspecialchars($faculty_info['name'] ?? 'Faculty'); ?></strong> | Subject: <strong style="color: var(--blue-accent);"><?php echo htmlspecialchars($selected_class['subject_code'] ?: 'General'); ?></strong>
                    </p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-outline" onclick="exportPDF()"><i class="fa-solid fa-file-pdf text-danger me-1"></i> Export PDF</button>
                    <button class="btn btn-primary" onclick="exportExcel()"><i class="fa-solid fa-file-excel me-1"></i> Export Excel</button>
                </div>
            </div>

            <div id="exportTable">
                <!-- STATS SUMMARY -->
                <div class="stats-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 2rem;">
                    <div class="stat-block">
                        <div class="stat-val"><?php echo count($class_students); ?></div>
                        <div class="stat-label">Enrolled Students</div>
                    </div>
                    <div class="stat-block">
                        <div class="stat-val" style="color: var(--success);"><?php echo count($class_activities); ?></div>
                        <div class="stat-label">Total Activities</div>
                    </div>
                </div>

                <!-- ACTIVITIES ANALYTICS -->
                <div class="module-card" style="margin-bottom: 2rem;">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--navy-primary);">Activity Submissions & Scores</h3>
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
                                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem; font-weight: 500;">No activities assigned for this class yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($class_activities as $act): 
                                        $pending = max(0, count($class_students) - $act['submitted_count']);
                                        $avg = $act['avg_score'] !== null ? number_format($act['avg_score'], 1) : '-';
                                    ?>
                                    <tr>
                                        <td><strong style="font-size: 0.95rem; color: var(--text-main);"><?php echo htmlspecialchars($act['title']); ?></strong></td>
                                        <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($act['due_date'])); ?></td>
                                        <td style="text-align: center;"><strong style="color: var(--success); font-size: 1rem;"><?php echo $act['submitted_count']; ?></strong></td>
                                        <td style="text-align: center;"><strong style="color: var(--danger); font-size: 1rem;"><?php echo $pending; ?></strong></td>
                                        <td><strong style="font-size: 1rem;"><?php echo $avg; ?></strong> <span style="font-size: 0.75rem; color: var(--text-muted);">/ <?php echo $act['max_marks']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ENROLLED STUDENTS ROSTER -->
                <div class="module-card">
                    <h3 style="font-size: 1.25rem; font-weight: 700; margin-bottom: 1.5rem; color: var(--navy-primary);">Enrolled Student Roster</h3>
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
                                    <tr><td colspan="4" style="text-align: center; color: var(--text-muted); padding: 2rem; font-weight: 500;">No students added to this class yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($class_students as $st): ?>
                                    <tr>
                                        <td style="font-weight: 600; color: var(--navy-primary);"><?php echo htmlspecialchars($st['student_prn']); ?></td>
                                        <td><strong style="font-weight: 500; color: var(--text-main);"><?php echo htmlspecialchars($st['student_name'] ?: 'Registered Student'); ?></strong></td>
                                        <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo htmlspecialchars($st['student_email'] ?: '—'); ?></td>
                                        <td style="font-weight: 600;"><?php echo htmlspecialchars($st['roll_no'] ?: '-'); ?></td>
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
    // 1. Sidebar Toggle (Mobile)
    const sidebarToggle = document.getElementById('sidebarToggle');
    const erpSidebar = document.getElementById('erpSidebar');

    if (sidebarToggle && erpSidebar) {
      sidebarToggle.addEventListener('click', () => {
        erpSidebar.classList.toggle('show');
      });
    }

    // 2. Live Clock
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