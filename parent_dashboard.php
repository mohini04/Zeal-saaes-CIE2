<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Anti-caching headers to prevent browser back-button access after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/config/db.php';

// Check authorization
$role = strtolower($_SESSION['role'] ?? '');
if (empty($_SESSION['user_id']) || !in_array($role, ['parent', 'admin'])) {
    header('Location: auth/login.php');
    exit;
}

$parent_user_id = (int)$_SESSION['user_id'];

// 1. Fetch Parent Account Info
$stmtP = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmtP->execute([$parent_user_id]);
$parentUser = $stmtP->fetch(PDO::FETCH_ASSOC);

$parentName = $parentUser['name'] ?? $_SESSION['full_name'] ?? 'Parent / Guardian';
$parentEmail = $parentUser['email'] ?? '';
$ward_prn = trim($parentUser['linked_student_prn'] ?? '');

// Fallback lookup via access_requests if linked_student_prn is empty
if (empty($ward_prn) && !empty($parentEmail)) {
    $stmtReq = $pdo->prepare("SELECT prn_number FROM access_requests WHERE LOWER(parent_email) = ? AND status = 'APPROVED' ORDER BY request_id DESC LIMIT 1");
    $stmtReq->execute([strtolower($parentEmail)]);
    $ward_prn = $stmtReq->fetchColumn() ?: '';
}

// 2. Fetch Ward (Student) Details
$wardStudent = null;
$wardSubmissions = [];
$wardActivities = [];

if (!empty($ward_prn)) {
    $stmtWard = $pdo->prepare("
        SELECT u.user_id, u.name AS student_name, u.email AS student_email, u.username AS prn, u.department, u.academic_year, u.division,
               st.student_id, st.roll_no
        FROM users u
        LEFT JOIN students st ON st.user_id = u.user_id
        WHERE (UPPER(u.username) = UPPER(?) OR UPPER(u.linked_student_prn) = UPPER(?)) AND LOWER(u.role) = 'student'
        LIMIT 1
    ");
    $stmtWard->execute([$ward_prn, $ward_prn]);
    $wardStudent = $stmtWard->fetch(PDO::FETCH_ASSOC);
}

if ($wardStudent) {
    $student_user_id = (int)$wardStudent['user_id'];
    $student_table_id = (int)($wardStudent['student_id'] ?? 0);

    // Fetch Ward's Submissions & Scores
    $stmtSub = $pdo->prepare("
        SELECT s.*, a.title AS activity_title, a.type AS activity_type, a.max_marks, a.due_date,
               u_fac.name AS faculty_name
        FROM submissions s
        JOIN activities a ON s.activity_id = a.activity_id
        LEFT JOIN users u_fac ON a.faculty_id = u_fac.user_id
        WHERE s.student_id = ? OR s.student_id = ?
        ORDER BY s.submission_date DESC
    ");
    $stmtSub->execute([$student_user_id, $student_table_id]);
    $wardSubmissions = $stmtSub->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Fetch Ward's Classes
    $stmtClassIds = $pdo->prepare("
        SELECT class_id FROM faculty_classes WHERE department = ? AND academic_year = ? AND division = ?
    ");
    $stmtClassIds->execute([$wardStudent['department'] ?? '', $wardStudent['academic_year'] ?? 'FY', $wardStudent['division'] ?? '']);
    $class_ids = $stmtClassIds->fetchAll(PDO::FETCH_COLUMN) ?: [];

    // Query Ward's Activities
    if (!empty($class_ids)) {
        $inClause = implode(',', array_map('intval', $class_ids));
        $stmtActs = $pdo->prepare("
            SELECT a.*, u.name AS faculty_name, fc.class_name
            FROM activities a
            LEFT JOIN users u ON a.faculty_id = u.user_id
            LEFT JOIN faculty_classes fc ON a.target_id = fc.class_id
            WHERE a.target_type = 'all' OR (a.target_type = 'class' AND a.target_id IN ($inClause))
            ORDER BY a.due_date DESC
        ");
        $stmtActs->execute();
        $wardActivities = $stmtActs->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $stmtActs = $pdo->prepare("
            SELECT a.*, u.name AS faculty_name, 'All Students' AS class_name
            FROM activities a
            LEFT JOIN users u ON a.faculty_id = u.user_id
            WHERE a.target_type = 'all'
            ORDER BY a.due_date DESC
        ");
        $stmtActs->execute();
        $wardActivities = $stmtActs->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

// Compute statistics
$totalAssigned = count($wardActivities);
$totalSubmitted = count($wardSubmissions);
$totalPending = max(0, $totalAssigned - $totalSubmitted);

$totalObtained = 0;
$totalPossible = 0;
foreach ($wardSubmissions as $sub) {
    $totalObtained += ($sub['marks'] !== null) ? (float)$sub['marks'] : (float)$sub['max_marks'];
    $totalPossible += (float)$sub['max_marks'];
}
$avgPercentage = ($totalPossible > 0) ? round(($totalObtained / $totalPossible) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Portal | SAAES</title>
    
    <!-- Clean Academic Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- PDF & Excel Libraries -->
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
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        ::selection { background: var(--blue-accent); color: #fff; }
        a { text-decoration: none; color: inherit; }

        .app-container { display: flex; min-height: 100vh; width: 100%; position: relative; }

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
        .menu-label { font-size: 0.75rem; color: var(--text-muted); margin: 1.5rem 0.5rem 0.5rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em;}
        
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
        .stat-val { font-size: 2.25rem; font-weight: 700; color: var(--navy-primary); line-height: 1; margin-bottom: 0.5rem; }
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

        /* ================= TABLES ================= */
        .table-responsive { overflow-x: auto; border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 1rem; }
        .custom-table { width: 100%; border-collapse: collapse; text-align: left; background: var(--bg-card); }
        .custom-table th, .custom-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; vertical-align: middle; }
        .custom-table th { background: var(--bg-body); color: var(--text-muted); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;}
        .custom-table tbody tr:hover { background: #f8fafc; }
        .custom-table tbody tr:last-child td { border-bottom: none; }

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
            <a href="parent_dashboard.php" class="brand-logo">
                <i class="fa-solid fa-user-shield"></i>
                <span>Parent Portal</span>
            </a>
        </div>

        <div class="sidebar-menu">
            <div class="menu-label">Navigation</div>
            <a href="parent_dashboard.php" class="sidebar-link active">
                <i class="fa-solid fa-house"></i> <span>Dashboard Overview</span>
            </a>

            <div class="menu-label">Account</div>
            <a href="auth/logout.php" class="sidebar-link" style="color: var(--danger);">
                <i class="fa-solid fa-power-off"></i> <span>Logout</span>
            </a>
        </div>

        <div class="sidebar-user">
            <div class="avatar"><?php echo strtoupper(substr($parentName, 0, 1)); ?></div>
            <div>
                <div style="font-weight: 600; font-size: 0.85rem; color: var(--navy-primary);"><?php echo htmlspecialchars($parentName); ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Parent / Guardian</div>
            </div>
        </div>
    </aside>

    <!-- CONTENT WRAPPER -->
    <div class="content-wrapper">
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline d-lg-none" id="sidebarToggle" style="padding: 0.4rem 0.8rem;"><i class="fa-solid fa-bars"></i></button>
                <h3>Parent Dashboard</h3>
            </div>

            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fa-regular fa-clock"></i> <span id="clock">00:00:00</span>
                </div>
            </div>
        </header>

        <main class="main-content">

        <?php if (!$wardStudent): ?>
            <!-- UNLINKED WARD ALERT -->
            <div class="module-card" style="text-align: center; padding: 4rem 2rem;">
                <i class="fa-solid fa-user-slash" style="font-size: 3rem; color: var(--border-color); margin-bottom: 1rem;"></i>
                <h2 style="font-size: 1.8rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--navy-primary);">No Student Linked to Account</h2>
                <p style="color: var(--text-muted); max-width: 600px; margin: 0 auto 1.5rem; font-size: 0.95rem;">
                    We could not find an approved student account matching your registered parent profile. Please contact college administration to link your student.
                </p>
                <?php if (!empty($parentEmail)): ?>
                    <p style="font-size: 0.85rem; color: var(--text-main);">Registered Parent Email: <strong><?php echo htmlspecialchars($parentEmail); ?></strong></p>
                <?php endif; ?>
            </div>
        <?php else: ?>

            <!-- WARD HEADER BANNER -->
            <div class="hero-banner" style="margin-bottom: 1.5rem;">
                <div class="hero-content">
                    <div>
                        <h1 class="hero-title"><?php echo htmlspecialchars($wardStudent['student_name'] ?: 'Student Overview'); ?></h1>
                        <p class="hero-subtitle">
                            PRN: <strong><?php echo htmlspecialchars($wardStudent['prn']); ?></strong> &bull; 
                            Roll No: <strong><?php echo htmlspecialchars($wardStudent['roll_no'] ?: 'N/A'); ?></strong> &bull; 
                            Dept: <strong><?php echo htmlspecialchars($wardStudent['department'] ?: 'Engineering'); ?></strong>
                        </p>
                    </div>
                    <div style="display: flex; gap: 0.75rem;">
                        <button class="btn btn-outline" style="border-color: rgba(255,255,255,0.4); color: #fff;" onclick="exportPDF()">
                            <i class="fa-solid fa-file-pdf"></i> Export PDF
                        </button>
                        <button class="btn btn-primary" style="background: #fff; color: var(--navy-primary);" onclick="exportExcel()">
                            <i class="fa-solid fa-file-excel"></i> Export Excel
                        </button>
                    </div>
                </div>
            </div>

            <div id="exportTable">
                <!-- STATS SUMMARY (4 Columns) -->
                <div class="stats-grid">
                    <div class="stat-block">
                        <div class="stat-val"><?php echo $totalAssigned; ?></div>
                        <div class="stat-label">Assigned Activities</div>
                    </div>
                    <div class="stat-block">
                        <div class="stat-val" style="color: var(--success);"><?php echo $totalSubmitted; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="stat-block">
                        <div class="stat-val" style="color: var(--danger);"><?php echo $totalPending; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="stat-block">
                        <div class="stat-val" style="color: var(--blue-accent);"><?php echo $avgPercentage; ?>%</div>
                        <div class="stat-label">Avg Score</div>
                    </div>
                </div>

                <!-- SUBMISSIONS & EVALUATION SHEET -->
                <div class="module-card">
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--navy-primary); margin-bottom: 1.5rem;"><i class="fa-solid fa-clipboard-list me-2"></i> Submissions & Evaluations</h3>
                    
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Activity Title</th>
                                    <th>Status</th>
                                    <th>Submission Date</th>
                                    <th>Faculty</th>
                                    <th>Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($wardSubmissions)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2.5rem; font-weight: 500;">
                                            No activity submissions recorded for your ward yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($wardSubmissions as $sub): 
                                        $is_late = !empty($sub['is_late']);
                                        $score = $sub['marks'] !== null ? $sub['marks'] : $sub['max_marks'];
                                    ?>
                                    <tr>
                                        <td>
                                            <strong style="font-size: 1rem; color: var(--text-main); display: block; margin-bottom: 0.2rem;"><?php echo htmlspecialchars($sub['activity_title']); ?></strong>
                                            <span class="sys-tag accent" style="font-size: 0.7rem; margin: 0;">Type: <?php echo htmlspecialchars(ucfirst($sub['activity_type'])); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($is_late): ?>
                                                <span class="sys-tag warning"><i class="fa-solid fa-clock"></i> Late</span>
                                            <?php else: ?>
                                                <span class="sys-tag success"><i class="fa-solid fa-check"></i> On Time</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 0.85rem; color: var(--text-muted);">
                                            <?php echo date('M d, Y h:i A', strtotime($sub['submission_date'])); ?>
                                        </td>
                                        <td>
                                            <strong style="font-weight: 500; font-size: 0.9rem; color: var(--text-main);"><?php echo htmlspecialchars($sub['faculty_name'] ?: 'Faculty'); ?></strong>
                                        </td>
                                        <td>
                                            <strong style="font-size: 1.1rem; color: var(--navy-primary);"><?php echo $score; ?></strong>
                                            <span style="font-size: 0.8rem; color: var(--text-muted);">/ <?php echo $sub['max_marks']; ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ALL ASSIGNED ACTIVITIES -->
                <div class="module-card">
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--navy-primary); margin-bottom: 1.5rem;"><i class="fa-solid fa-list-check me-2"></i> All Assigned Activities</h3>
                    
                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Activity Title</th>
                                    <th>Class / Group</th>
                                    <th>Due Date</th>
                                    <th>Max Marks</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($wardActivities)): ?>
                                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2.5rem; font-weight: 500;">No activities assigned yet.</td></tr>
                                <?php else: ?>
                                    <?php 
                                    $submitted_act_ids = array_column($wardSubmissions, 'activity_id');
                                    foreach ($wardActivities as $act): 
                                        $is_done = in_array($act['activity_id'], $submitted_act_ids);
                                    ?>
                                    <tr>
                                        <td><strong style="font-size: 0.95rem; color: var(--text-main);"><?php echo htmlspecialchars($act['title']); ?></strong></td>
                                        <td><span class="sys-tag accent"><?php echo htmlspecialchars($act['class_name'] ?: 'All Class'); ?></span></td>
                                        <td style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($act['due_date'])); ?></td>
                                        <td style="font-weight: 600;"><?php echo $act['max_marks']; ?> Marks</td>
                                        <td>
                                            <?php if ($is_done): ?>
                                                <span class="sys-tag success"><i class="fa-solid fa-check"></i> Completed</span>
                                            <?php else: ?>
                                                <span class="sys-tag danger"><i class="fa-solid fa-hourglass-half"></i> Pending</span>
                                            <?php endif; ?>
                                        </td>
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
      filename:     'Student_Performance_Report.pdf',
      image:        { type: 'jpeg', quality: 0.98 },
      html2canvas:  { scale: 2 },
      jsPDF:        { unit: 'mm', format: 'a4', orientation: 'landscape' }
    };
    html2pdf().set(opt).from(element).save();
}

function exportExcel() {
    var element = document.getElementById("exportTable");
    if (!element) return;
    var wb = XLSX.utils.table_to_book(element, {sheet:"Ward_Report"});
    XLSX.writeFile(wb, "Student_Performance_Report.xlsx");
}
</script>

<?php 
// Safely include modal without fatal error
$modalPath = __DIR__ . '/includes/end_session_modal.php';
if (file_exists($modalPath)) {
    include_once $modalPath;
}
?>
</body>
</html>