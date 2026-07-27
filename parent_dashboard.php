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
    
    <!-- Professional Fonts matching Landing Page -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=JetBrains+Mono:wght@100;400;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- PDF & Excel Libraries -->
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

        .sys-clock {
            font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700;
            background: var(--text-dark); color: #fff; padding: 0.5rem 1rem;
            display: flex; align-items: center; gap: 0.5rem;
            clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
        }
        .sys-clock .blink { color: var(--accent-glow); animation: blinker 1s linear infinite; }
        @keyframes blinker { 50% { opacity: 0; } }

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

        .hero-banner {
            background: var(--bg-base); border: 2px solid var(--text-dark);
            padding: 3rem; margin-bottom: 2rem; position: relative; overflow: hidden;
            clip-path: polygon(0 0, calc(100% - 30px) 0, 100% 30px, 100% 100%, 0 100%);
        }
        .hero-banner::after {
            content: ''; position: absolute; top: 0; right: 0; width: 30px; height: 30px; background: var(--text-dark);
        }
        .hero-content { position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; }

        /* ================= BUTTONS ================= */
        .btn-tech {
            font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem; text-transform: uppercase;
            padding: 0.8rem 1.5rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
            background: var(--bg-base); color: var(--text-dark); border: 2px solid var(--text-dark);
            position: relative; overflow: hidden; z-index: 1; cursor: pointer;
            clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
            transition: color 0.3s; text-decoration: none;
        }
        .btn-tech::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: var(--accent-main); z-index: -1; transition: left 0.3s cubic-bezier(0.7, 0, 0.3, 1);
        }
        .btn-tech:hover { color: #fff; border-color: var(--accent-main); }
        .btn-tech:hover::before { left: 0; }
        
        .btn-tech.primary { background: var(--text-dark); color: #fff; border-color: var(--text-dark); }
        .btn-tech.primary:hover { color: #fff; }
        .btn-tech.danger { border-color: #ef4444; color: #ef4444; }
        .btn-tech.danger::before { background: #ef4444; }
        .btn-tech.danger:hover { color: #fff; border-color: #ef4444; }

        .btn-outline { background: transparent; border: 2px solid var(--text-dark); color: var(--text-dark); font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem; padding: 0.6rem 1.2rem; cursor: pointer; transition: 0.2s;}
        .btn-outline:hover { background: var(--text-dark); color: #fff; }

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
        .sys-tag.success { background: rgba(16, 185, 129, 0.05); color: #10b981; border-color: #10b981; }
        .sys-tag.danger { background: rgba(239, 68, 68, 0.05); color: #ef4444; border-color: #ef4444; }

        /* ================= TABLES ================= */
        .table-responsive { overflow-x: auto; background: var(--bg-base); border: 2px solid var(--text-dark); margin-bottom: 1rem; }
        .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
        .custom-table th, .custom-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--text-tech); font-size: 0.9rem; }
        .custom-table th { background: var(--bg-panel); color: var(--text-dark); font-family: var(--font-mono); font-weight: 700; font-size: 0.8rem; text-transform: uppercase; }
        .custom-table tbody tr { transition: background 0.2s ease; }
        .custom-table tbody tr:hover { background: rgba(124, 58, 237, 0.05); }
        .custom-table tbody tr:last-child td { border-bottom: none; }

        @media (max-width: 1024px) {
            .telemetry-grid { grid-template-columns: repeat(2, 1fr); }
            .tel-block:nth-child(2) { border-right: none; }
            .tel-block:nth-child(1), .tel-block:nth-child(2) { border-bottom: 2px solid var(--text-dark); }
            .hero-content { flex-direction: column; align-items: flex-start; }
        }
        @media (max-width: 600px) {
            .telemetry-grid { grid-template-columns: 1fr; }
            .tel-block { border-right: none !important; border-bottom: 2px solid var(--text-dark); }
            .tel-block:last-child { border-bottom: none; }
        }
    </style>
</head>
<body>

<header class="tech-header">
    <div class="sys-logo interactive">
        <i class="fa-solid fa-user-shield"></i> Parent Portal <div class="line"></div> ZCOER
    </div>
    
    <div style="display: flex; gap: 1.5rem; align-items: center;">
        <div class="sys-clock interactive">
            Time <span class="blink">|</span> <span id="clock">00:00:00</span>
        </div>
        <a href="auth/logout.php" class="btn-tech danger interactive">
            <i class="fa-solid fa-power-off"></i> Logout
        </a>
    </div>
</header>

<div class="dashboard-container">

    <?php if (!$wardStudent): ?>
        <!-- UNLINKED WARD ALERT -->
        <div class="module-card interactive" style="text-align: center; padding: 4rem 2rem;">
            <i class="fa-solid fa-user-slash" style="font-size: 3rem; color: var(--text-tech); margin-bottom: 1rem;"></i>
            <h2 style="font-family: var(--font-head); font-size: 1.8rem; font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem; color: var(--text-dark);">No Student Linked to Account</h2>
            <p style="font-family: var(--font-mono); color: var(--text-tech); max-width: 600px; margin: 0 auto 1.5rem; font-size: 0.9rem;">
                We could not find an approved student account matching your registered parent profile. Please contact college administration to link your student.
            </p>
            <?php if (!empty($parentEmail)): ?>
                <p style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-dark);">Registered Parent Email: <strong><?php echo htmlspecialchars($parentEmail); ?></strong></p>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <!-- WARD HEADER BANNER -->
        <div class="hero-banner interactive">
            <div class="hero-content">
                <div>
                    <h1 style="font-family: var(--font-head); font-size: 2.2rem; margin-bottom: 0.5rem; font-weight: 700; text-transform: uppercase;"><?php echo htmlspecialchars($wardStudent['student_name'] ?: 'Student Overview'); ?></h1>
                    <p style="color: var(--text-tech); font-family: var(--font-mono); font-size: 0.95rem; margin-bottom: 0;">
                        PRN: <span style="color: var(--accent-main); font-weight: 700;"><?php echo htmlspecialchars($wardStudent['prn']); ?></span> &bull; 
                        Roll No: <strong><?php echo htmlspecialchars($wardStudent['roll_no'] ?: 'N/A'); ?></strong> &bull; 
                        Dept: <strong><?php echo htmlspecialchars($wardStudent['department'] ?: 'Engineering'); ?></strong>
                    </p>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <button class="btn-tech interactive" onclick="exportPDF()">
                        <i class="fa-solid fa-file-pdf"></i> Export PDF
                    </button>
                    <button class="btn-tech primary interactive" onclick="exportExcel()">
                        <i class="fa-solid fa-file-excel"></i> Export Excel
                    </button>
                </div>
            </div>
        </div>

        <div id="exportTable">
            <!-- STATS SUMMARY (4 Columns) -->
            <div class="telemetry-grid interactive">
                <div class="tel-block">
                    <div class="tel-val" style="color: var(--text-dark);"><?php echo $totalAssigned; ?></div>
                    <div class="tel-label">Assigned Activities</div>
                </div>
                <div class="tel-block">
                    <div class="tel-val" style="color: #10b981;"><?php echo $totalSubmitted; ?></div>
                    <div class="tel-label">Completed</div>
                </div>
                <div class="tel-block">
                    <div class="tel-val" style="color: #ef4444;"><?php echo $totalPending; ?></div>
                    <div class="tel-label">Pending</div>
                </div>
                <div class="tel-block">
                    <div class="tel-val" style="color: #f59e0b;"><?php echo $avgPercentage; ?>%</div>
                    <div class="tel-label">Avg Score</div>
                </div>
            </div>

            <!-- SUBMISSIONS & EVALUATION SHEET -->
            <div class="module-card interactive">
                <h3 class="mod-title"><i class="fa-solid fa-clipboard-list"></i> Submissions & Evaluations</h3>
                
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
                                    <td colspan="5" style="text-align: center; color: var(--text-tech); padding: 2.5rem; font-family: var(--font-mono); font-weight: 700;">
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
                                        <strong style="font-family: var(--font-head); font-size: 1.05rem; text-transform: uppercase;"><?php echo htmlspecialchars($sub['activity_title']); ?></strong>
                                        <div style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-tech); margin-top: 4px;">Type: <?php echo htmlspecialchars(ucfirst($sub['activity_type'])); ?></div>
                                    </td>
                                    <td>
                                        <?php if ($is_late): ?>
                                            <span class="sys-tag warning"><i class="fa-solid fa-clock"></i> Late</span>
                                        <?php else: ?>
                                            <span class="sys-tag success"><i class="fa-solid fa-check"></i> On Time</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);">
                                        <?php echo date('M d, Y h:i A', strtotime($sub['submission_date'])); ?>
                                    </td>
                                    <td>
                                        <strong style="font-family: var(--font-body); font-size: 0.9rem; text-transform: uppercase;"><?php echo htmlspecialchars($sub['faculty_name'] ?: 'Faculty'); ?></strong>
                                    </td>
                                    <td>
                                        <strong style="font-family: var(--font-mono); font-size: 1.1rem; color: var(--accent-main);"><?php echo $score; ?></strong>
                                        <span style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-tech);">/ <?php echo $sub['max_marks']; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ALL ASSIGNED ACTIVITIES -->
            <div class="module-card interactive">
                <h3 class="mod-title"><i class="fa-solid fa-list-check"></i> All Assigned Activities</h3>
                
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
                                <tr><td colspan="5" style="text-align: center; color: var(--text-tech); padding: 2.5rem; font-family: var(--font-mono); font-weight: 700;">No activities assigned yet.</td></tr>
                            <?php else: ?>
                                <?php 
                                $submitted_act_ids = array_column($wardSubmissions, 'activity_id');
                                foreach ($wardActivities as $act): 
                                    $is_done = in_array($act['activity_id'], $submitted_act_ids);
                                ?>
                                <tr>
                                    <td><strong style="font-family: var(--font-head); font-size: 1.05rem; text-transform: uppercase;"><?php echo htmlspecialchars($act['title']); ?></strong></td>
                                    <td><span class="sys-tag accent"><?php echo htmlspecialchars($act['class_name'] ?: 'All Class'); ?></span></td>
                                    <td style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);"><?php echo date('M d, Y', strtotime($act['due_date'])); ?></td>
                                    <td style="font-family: var(--font-mono); font-weight: 700;"><?php echo $act['max_marks']; ?> Marks</td>
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