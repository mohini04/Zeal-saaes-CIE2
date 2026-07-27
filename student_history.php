<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Anti-caching headers to prevent browser back-button access after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id']) || strtolower($_SESSION['role'] ?? '') !== 'student') {
  header('Location: auth/login.php');
  exit;
}

$studentUserId  = (int) $_SESSION['user_id'];
$studentTableId = (int) ($_SESSION['student_id'] ?? 0);
$studentCode    = $_SESSION['student_code'] ?? ('STU' . $studentTableId);
$studentName    = $_SESSION['full_name']   ?? ($_SESSION['user_name'] ?? 'Student');
$_SESSION['full_name'] = $studentName;
$role           = $_SESSION['role']        ?? 'student';

// Fetch PRN to retrieve joined classes
$studentPrn = '';
$linkedPrn = '';
$stmtU = $pdo->prepare("SELECT username, linked_student_prn, department, academic_year, division FROM users WHERE user_id = ? LIMIT 1");
$stmtU->execute([$studentUserId]);
$uRow = $stmtU->fetch(PDO::FETCH_ASSOC);
if ($uRow) {
    $studentPrn = $uRow['username'] ?? '';
    $linkedPrn  = $uRow['linked_student_prn'] ?? '';
    $studentDept = $uRow['department'] ?? '';
    $studentYear = $uRow['academic_year'] ?? 'FY';
    $studentDiv  = $uRow['division'] ?? '';
}

function e($v) { return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function fmtDateTime($d) { return $d ? (new DateTime($d))->format('d M Y, h:i A') : '—'; }
function jsAttr($v) { return htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8'); }

// File Download/Preview Handler
$action = $_GET['action'] ?? '';
if ($action === 'preview' || $action === 'download') {
  $UPLOAD_ROOT = __DIR__ . '/uploads/';
  $subId = (int) ($_GET['id'] ?? 0);
  $stmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ? AND student_id = ?");
  $stmt->execute([$subId, $studentTableId]);
  $sub = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$sub) {
    http_response_code(404);
    exit('File not found.');
  }
  $fullPath = $UPLOAD_ROOT . $sub['student_id'] . '/' . ($sub['original_filename'] ?? '');
  if (!is_file($fullPath)) {
    http_response_code(404);
    exit('File is missing on the server.');
  }
  $mimeMap = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
  header('Content-Type: ' . ($mimeMap[$sub['file_type']] ?? 'application/octet-stream'));
  header('Content-Disposition: ' . ($action === 'download' ? 'attachment' : 'inline') . '; filename="' . basename($sub['original_filename']) . '"');
  header('Content-Length: ' . filesize($fullPath));
  header('X-Content-Type-Options: nosniff');
  readfile($fullPath);
  exit;
}

// Fetch Joined Classes for Sidebar
$stmtClasses = $pdo->prepare("
    SELECT fc.class_id, fc.class_name, fc.subject_code, fc.description, u.name AS faculty_name
    FROM faculty_classes fc
    LEFT JOIN users u ON fc.faculty_id = u.user_id
    WHERE fc.department = ? AND fc.academic_year = ? AND fc.division = ?
    ORDER BY fc.created_at DESC
");
$stmtClasses->execute([$studentDept ?? '', $studentYear ?? 'FY', $studentDiv ?? '']);
$myClasses = $stmtClasses->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Clean up any orphan submission records where activity no longer exists
try {
    $pdo->exec("DELETE FROM submissions WHERE activity_id NOT IN (SELECT activity_id FROM activities)");
} catch (PDOException $e) {
    // Ignore error if table doesn't exist
}

// Fetch History Data: ONLY SUBMITTED ACTIVITIES THAT BELONG TO EXISTING ACTIVITIES
$stmt = $pdo->prepare("
    SELECT s.id AS submission_id, s.original_filename, s.submission_date, s.status AS sub_status,
           s.marks, s.file_type, s.remarks,
           a.activity_id, a.subject AS subject_code, a.unit, a.title, a.max_marks, a.due_date
    FROM submissions s
    JOIN activities a ON s.activity_id = a.activity_id
    WHERE s.student_id = ?
    ORDER BY s.submission_date DESC
");
$stmt->execute([$studentTableId]);
$allRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$history = [];
$totalEvaluated = 0;
$totalReview = 0;

foreach ($allRows as $row) {
    $row['status'] = $row['sub_status'] ?: 'Submitted';
    if ($row['status'] === 'Submitted' && $row['marks'] !== null) {
        $totalEvaluated++;
        $row['display_status'] = 'Graded';
    } elseif (in_array($row['status'], ['Approved', 'Graded', 'Evaluated'], true)) {
        $totalEvaluated++;
        $row['display_status'] = 'Graded';
    } else {
        $totalReview++;
        $row['display_status'] = 'Under Review';
    }
    $row['id'] = $row['submission_id'];
    $history[] = $row;
}

$subjects = [];
foreach ($history as $h) {
  if (!empty($h['subject_code'])) {
    $subjects[$h['subject_code']] = true;
  }
}
$subjects = array_keys($subjects);
sort($subjects);

function badgeClass($status) {
    return [
        'Submission Closed' => 'danger',
        'Missed'        => 'danger',
        'Pending'       => 'warning',
        'Late'          => 'warning',
        'Submitted'     => 'info',
        'Under Review'  => 'info',
        'Approved'      => 'success',
        'Rejected'      => 'danger',
        'Evaluated'     => 'success',
        'Graded'        => 'success',
    ][$status] ?? 'accent';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submission History | SAAES</title>
  
  <!-- Professional Fonts matching Landing Page -->
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=JetBrains+Mono:wght@100;400;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
  <style>
/* ==========================================================================
   RIGID LIGHT SCI-FI DESIGN SYSTEM (PURPLE EDITION)
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
  cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32' shape-rendering='crispEdges'%3E%3Cpath d='M4 4v20l5-5 4 8 4-2-4-8h8L4 4z' fill='%237c3aed' stroke='white' stroke-width='2'/%3E%3C/svg%3E") 4 4, auto;
  -webkit-font-smoothing: antialiased;
}

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
.menu-label { font-family: var(--font-mono); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--accent-main); margin: 1.5rem 0.5rem 0.5rem; font-weight: 700; display: flex; justify-content: space-between; align-items: center;}

.sidebar-link {
  display: flex; align-items: center; gap: 0.85rem; padding: 0.8rem 1rem;
  color: var(--text-tech); font-family: var(--font-mono);
  font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;
  border: 2px solid transparent; position: relative;
}
.sidebar-link::before {
    content: '>'; position: absolute; left: 5px; opacity: 0; color: var(--accent-main); transition: 0.2s;
}
.sidebar-link:hover { color: var(--accent-main); padding-left: 1.5rem; }
.sidebar-link:hover::before { opacity: 1; }
.sidebar-link.active {
  background: var(--bg-base); color: var(--accent-main); 
  border: 2px solid var(--accent-main);
  box-shadow: 4px 4px 0px rgba(124, 58, 237, 0.2);
}
.sidebar-link i { font-size: 1.1rem; width: 22px; text-align: center; }

.sidebar-user {
  padding: 1.25rem; border-top: var(--border-harsh);
  display: flex; align-items: center; gap: 0.75rem; background: var(--bg-panel);
}
.avatar {
  width: 40px; height: 40px; border: 2px solid var(--accent-main); background: var(--bg-base);
  display: flex; align-items: center; justify-content: center;
  font-family: var(--font-head); font-weight: 700; font-size: 1.2rem; color: var(--accent-main);
}

/* Joined Classes UI in Sidebar */
.joined-class-item {
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    background: var(--bg-panel);
    border: 1px solid var(--border-light);
    border-left: 3px solid var(--accent-main);
    font-family: var(--font-mono);
    font-size: 0.75rem;
}
.joined-class-item strong { display: block; color: var(--text-dark); margin-bottom: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.joined-class-item span { color: var(--text-tech); }

/* ================= MAIN CONTENT ================= */
.content-wrapper { margin-left: 280px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; background: transparent; }

.top-navbar {
  background: rgba(255, 255, 255, 0.95);
  border-bottom: var(--border-harsh); padding: 1rem 2.5rem;
  display: flex; justify-content: space-between; align-items: center;
  position: sticky; top: 0; z-index: 100;
}
.top-navbar h3 { font-family: var(--font-mono); font-weight: 700; font-size: 1rem; color: var(--accent-main); text-transform: uppercase; margin: 0; }

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
  background: var(--bg-base); border: 2px solid var(--accent-main);
  padding: 3rem; position: relative; overflow: hidden;
  clip-path: polygon(0 0, calc(100% - 30px) 0, 100% 30px, 100% 100%, 0 100%);
  box-shadow: 8px 8px 0px rgba(124, 58, 237, 0.15);
}
.hero-banner::after {
    content: ''; position: absolute; top: 0; right: 0; width: 30px; height: 30px; background: var(--accent-main);
}
.hero-content { position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; }

/* ================= METADATA LABELS (TAGS) ================= */
.sys-tag { 
    font-family: var(--font-mono); font-size: 0.75rem; font-weight: 700; padding: 0.3rem 0.6rem; 
    border: 1px solid var(--text-dark); color: var(--text-dark); text-transform: uppercase; display: inline-flex; align-items: center; gap: 0.4rem;
}
.sys-tag.accent { background: rgba(124, 58, 237, 0.08); border-color: var(--accent-main); color: var(--accent-main); }
.sys-tag.danger { background: rgba(239, 68, 68, 0.08); border-color: #ef4444; color: #ef4444; }
.sys-tag.warning { background: rgba(245, 158, 11, 0.08); border-color: #f59e0b; color: #f59e0b; }
.sys-tag.info { background: rgba(59, 130, 246, 0.08); border-color: #3b82f6; color: #3b82f6; }
.sys-tag.success { background: rgba(16, 185, 129, 0.05); border-color: #10b981; color: #10b981; }

/* ================= BUTTONS ================= */
.btn-tech {
    font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem; text-transform: uppercase;
    padding: 0.6rem 1.2rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
    background: var(--bg-base); color: var(--text-dark); border: 2px solid var(--text-dark);
    position: relative; overflow: hidden; z-index: 1; cursor: pointer; text-decoration: none;
    clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
    transition: color 0.3s;
}
.btn-tech::before {
    content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
    background: var(--text-dark); z-index: -1; transition: left 0.3s cubic-bezier(0.7, 0, 0.3, 1);
}
.btn-tech:hover { color: #fff; border-color: var(--text-dark); }
.btn-tech:hover::before { left: 0; }

.btn-tech.primary { background: var(--accent-main); color: #fff; border-color: var(--accent-main); }
.btn-tech.primary:hover { color: #fff; border-color: var(--text-dark);}
.btn-tech.primary::before { background: var(--text-dark); }

.btn-outline { background: transparent; border: 2px solid var(--text-dark); color: var(--text-dark); font-family: var(--font-mono); font-weight: 700; font-size: 0.75rem; padding: 0.4rem 0.8rem; cursor: pointer; transition: 0.2s; text-decoration: none; display: inline-flex; align-items: center;}
.btn-outline:hover { background: var(--text-dark); color: #fff; }

/* ================= KPI TELEMETRY GRID ================= */
.telemetry-grid { 
    display: grid; grid-template-columns: repeat(4, 1fr); 
    border: 2px solid var(--text-dark); background: var(--bg-panel); margin-bottom: 2rem;
}
.tel-block { 
    padding: 2rem 1.5rem; border-right: 2px solid var(--text-dark); 
    display: flex; flex-direction: column; justify-content: center; align-items: flex-start;
    transition: background 0.2s;
}
.tel-block:hover { background: var(--accent-bg); }
.tel-block:last-child { border-right: none; }
.tel-val { font-family: var(--font-head); font-size: 3rem; font-weight: 700; color: var(--text-dark); line-height: 1; margin-bottom: 0.5rem; }
.tel-label { font-family: var(--font-mono); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-tech); margin-bottom: 0;}

/* ================= FILTERS & FORMS ================= */
.filter-card {
    background: var(--bg-panel); border: 2px solid var(--text-dark); padding: 1.5rem; margin-bottom: 2rem;
    display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;
}
.form-control-custom, .form-select-custom {
    width: 100%; padding: 0.75rem 1rem; background: var(--bg-base); border: 1px solid var(--text-tech);
    color: var(--text-dark); font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; outline: none; transition: border 0.2s; border-radius: 0; -webkit-appearance: none;
}
.form-control-custom:focus, .form-select-custom:focus { border-color: var(--accent-main); border-width: 2px; padding: calc(0.75rem - 1px) calc(1rem - 1px); }

/* ================= TABLES ================= */
.table-responsive { overflow-x: auto; background: var(--bg-base); border: 2px solid var(--text-dark); margin-bottom: 1rem; }
.custom-table { width: 100%; border-collapse: collapse; text-align: left; }
.custom-table th, .custom-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--text-light); font-size: 0.9rem; }
.custom-table th { background: var(--bg-panel); color: var(--text-dark); font-family: var(--font-mono); font-weight: 700; font-size: 0.8rem; text-transform: uppercase; }
.custom-table tbody tr { transition: background 0.2s ease; }
.custom-table tbody tr:hover { background: rgba(124, 58, 237, 0.05); }
.custom-table tbody tr:last-child td { border-bottom: none; }

/* Pagination */
.pagination { display: flex; list-style: none; gap: 0.5rem; margin: 0; padding: 0;}
.page-item .page-link { font-family: var(--font-mono); font-weight: 700; border: 2px solid var(--text-dark); color: var(--text-dark); background: transparent; padding: 0.4rem 0.8rem; text-decoration: none;}
.page-item.active .page-link { background: var(--accent-main); color: #fff; border-color: var(--accent-main); }
.page-item.disabled .page-link { opacity: 0.5; pointer-events: none; }

/* ================= ALERTS & MODALS ================= */
.alert { font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; border: 2px solid transparent; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 0.5rem;}
.alert-danger { background: var(--bg-base); color: #ef4444; border-color: #ef4444; }
.alert-warning { background: var(--bg-base); color: #f59e0b; border-color: #f59e0b; }
.alert-info { background: var(--bg-base); color: #3b82f6; border-color: #3b82f6; }
.alert-success { background: var(--bg-base); color: #10b981; border-color: #10b981; }

.modal-overlay {
  position: fixed; top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px);
  display: none; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;
}
.modal-content {
  background: var(--bg-base); border: 2px solid var(--accent-main);
  max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 2.5rem;
  box-shadow: 15px 15px 0px rgba(124, 58, 237, 0.3);
  clip-path: polygon(0 0, calc(100% - 30px) 0, 100% 30px, 100% 100%, 30px 100%, 0 calc(100% - 30px));
}
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid var(--text-dark); }
.modal-header h3 { font-family: var(--font-head); font-weight: 700; color: var(--accent-main); text-transform: uppercase; font-size: 1.5rem; margin: 0;}
.close-btn { background: none; border: none; color: var(--text-dark); font-size: 1.8rem; cursor: pointer; transition: color 0.2s; padding: 0; line-height: 1;}
.close-btn:hover { color: var(--accent-main); }
#previewFrame { width: 100%; height: 60vh; border: 2px solid var(--text-dark); }
#previewImg { max-width: 100%; max-height: 60vh; display: block; margin: 0 auto; border: 2px solid var(--text-dark); }

@media (max-width: 1024px) {
    .telemetry-grid { grid-template-columns: repeat(2, 1fr); }
    .tel-block:nth-child(2) { border-right: none; }
    .tel-block:nth-child(1), .tel-block:nth-child(2) { border-bottom: 2px solid var(--text-dark); }
    .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
    .sidebar.show { transform: translateX(0); }
    .content-wrapper { margin-left: 0; }
}
@media (max-width: 600px) {
    .telemetry-grid { grid-template-columns: 1fr; }
    .tel-block { border-right: none !important; border-bottom: 2px solid var(--text-dark); }
    .tel-block:last-child { border-bottom: none; }
    .filter-card { flex-direction: column; align-items: stretch; }
}
</style>
</head>
<body>

<div class="app-container">

    <!-- LEFT SIDEBAR -->
    <aside class="sidebar" id="erpSidebar">
        <div class="sidebar-header">
            <a href="student_dashboard.php" class="brand-logo interactive">
                <i class="fa-solid fa-graduation-cap"></i>
                <span>Student Hub</span>
            </a>
        </div>

        <div class="sidebar-menu">
            <div class="menu-label">Navigation</div>
            <a href="student_dashboard.php" class="sidebar-link interactive">
                <span>Dashboard</span>
            </a>
            <a href="student_submit.php" class="sidebar-link interactive">
                <span>Upload Assignment</span>
            </a>
            <a href="student_history.php" class="sidebar-link interactive active">
                <span>Submission History</span>
            </a>

            <div class="menu-label">Account</div>
            <a href="auth/logout.php" class="sidebar-link interactive" style="color: #ef4444;">
                <span>Logout</span>
            </a>

            <div class="menu-label" style="margin-top: 2rem;">
                <span>Joined Classes</span>
                <span class="sys-tag accent" style="margin: 0;"><?= count($myClasses) ?></span>
            </div>
            <div style="padding: 0 1rem;">
                <?php if (empty($myClasses)): ?>
                    <div style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-tech);">No classes joined yet.</div>
                <?php else: ?>
                    <?php foreach ($myClasses as $c): ?>
                        <div class="joined-class-item interactive">
                            <strong title="<?= e($c['class_name']) ?>"><?= e($c['class_name']) ?></strong>
                            <?php if (!empty($c['subject_code'])): ?>
                                <span><?= e($c['subject_code']) ?></span><br>
                            <?php endif; ?>
                            <?php if (!empty($c['faculty_name'])): ?>
                                <span><?= e($c['faculty_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="sidebar-user">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="avatar"><?php echo strtoupper(substr($studentName, 0, 1)); ?></div>
                <div>
                    <div style="font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem; color: var(--text-dark);"><?php echo e($studentName); ?></div>
                    <div style="font-family: var(--font-mono); font-size: 0.65rem; color: var(--accent-main); text-transform: uppercase; font-weight: 700;">Role: <?php echo e($role); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <!-- CONTENT WRAPPER -->
    <div class="content-wrapper">
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline interactive d-lg-none" id="sidebarToggle" style="padding: 0.4rem 0.8rem;">Menu</button>
                <h3 style="color: var(--accent-main);">Submission History</h3>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="student_submit.php" class="btn-tech primary interactive">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload New
                </a>
            </div>
        </header>

        <main class="main-content">

            <!-- Hero Header -->
            <div class="hero-banner interactive">
                <div class="hero-content">
                    <div>
                        <h1 style="font-family: var(--font-head); font-size: 2.2rem; margin-bottom: 0.5rem; font-weight: 700; text-transform: uppercase;">Submission Log</h1>
                        <p style="color: var(--text-tech); font-family: var(--font-mono); font-size: 0.95rem; margin-bottom: 0;">
                            Track all submitted activities, view earned scores, and preview uploaded files.
                        </p>
                    </div>
                </div>
            </div>

            <!-- KPI Metrics Grid -->
            <div class="telemetry-grid interactive" style="grid-template-columns: repeat(3, 1fr);">
                <div class="tel-block">
                    <div class="tel-val" style="color: var(--text-dark);"><?= count($history) ?></div>
                    <div class="tel-label">Total Submissions</div>
                </div>
                <div class="tel-block">
                    <div class="tel-val" style="color: #10b981;"><?= $totalEvaluated ?></div>
                    <div class="tel-label">Graded</div>
                </div>
                <div class="tel-block">
                    <div class="tel-val" style="color: #3b82f6;"><?= $totalReview ?></div>
                    <div class="tel-label">Under Review</div>
                </div>
            </div>

            <!-- Control Toolbar & Filters -->
            <div class="filter-card interactive">
                <div style="flex: 2; min-width: 250px;">
                    <input type="text" id="searchInput" class="form-control-custom" placeholder="Search activity title or subject...">
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <select id="subjectFilter" class="form-select-custom">
                        <option value="">All Subjects</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= e($s) ?>"><?= e($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="flex: 1; min-width: 150px;">
                    <select id="statusFilter" class="form-select-custom">
                        <option value="">All Statuses</option>
                        <option value="Graded">Graded</option>
                        <option value="Under Review">Under Review</option>
                    </select>
                </div>
                <div>
                    <button id="resetFiltersBtn" class="btn-tech interactive" style="margin:0;">
                        Reset
                    </button>
                </div>
            </div>

            <!-- Submission History Table -->
            <div class="table-responsive interactive">
                <table class="custom-table" id="historyTable">
                    <thead>
                        <tr>
                            <th>Activity Title</th>
                            <th>Subject</th>
                            <th>Uploaded File</th>
                            <th>Submission Date</th>
                            <th>Score</th>
                            <th>Status</th>
                            <th style="text-align: center;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <?php if (!$history): ?>
                            <tr class="js-empty">
                                <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-tech); font-family: var(--font-mono); font-weight: 700;">
                                    No activity submissions recorded yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php foreach ($history as $h): 
                            $displayStatus = $h['display_status'];
                            $fileExt = strtolower((string)($h['file_type'] ?? ''));
                        ?>
                            <tr data-subject="<?= e($h['subject_code']) ?>" 
                                data-status="<?= e($displayStatus) ?>" 
                                data-title="<?= e(mb_strtolower($h['title'] . ' ' . $h['subject_code'] . ' ' . ($h['original_filename'] ?? ''))) ?>">
                                
                                <td>
                                    <strong style="font-family: var(--font-head); font-size: 1.05rem; text-transform: uppercase; color: var(--text-dark); display: block; margin-bottom: 0.2rem;"><?= e($h['title']) ?></strong>
                                    <span class="sys-tag accent" style="font-size: 0.65rem; margin:0;">Unit <?= e($h['unit']) ?></span>
                                </td>
                                
                                <td>
                                    <strong style="font-family: var(--font-mono); color: var(--text-dark); text-transform: uppercase;"><?= e($h['subject_code']) ?></strong>
                                </td>
                                
                                <td>
                                    <div style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--accent-main); font-weight: 700; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($h['original_filename']) ?>">
                                        <?= e($h['original_filename']) ?>
                                    </div>
                                </td>
                                
                                <td style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);">
                                    <strong style="color: var(--text-dark);"><?= fmtDateTime($h['submission_date']) ?></strong>
                                </td>
                                
                                <td>
                                    <?php if ($h['marks'] !== null): ?>
                                        <strong style="font-family: var(--font-mono); font-size: 1.1rem; color: #10b981;"><?= e($h['marks']) ?></strong>
                                        <span style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-tech);">/ <?= e($h['max_marks']) ?></span>
                                    <?php else: ?>
                                        <span class="sys-tag info">Under Evaluation</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <span class="sys-tag <?= badgeClass($displayStatus) ?>" style="margin:0;"><?= e($displayStatus) ?></span>
                                </td>
                                
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                        <button type="button" class="btn-outline interactive" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;" title="View Document Preview"
                                                onclick='openPreview(<?= (int)$h['id'] ?>, <?= jsAttr($h['file_type']) ?>, <?= jsAttr($h['original_filename']) ?>)'>
                                            View
                                        </button>
                                        <a class="btn-tech primary interactive" style="padding: 0.4rem 0.8rem; font-size: 0.75rem; margin: 0;" title="Download Original File"
                                           href="student_history.php?action=download&id=<?= (int)$h['id'] ?>">
                                            DL
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination & Summary Footer -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; color: var(--text-tech); text-transform: uppercase;" id="resultsSummary">Showing submissions</div>
                <nav aria-label="Page navigation">
                    <ul class="pagination" id="historyPagination"></ul>
                </nav>
            </div>

        </main>
    </div>
</div>

<!-- Document Preview Modal -->
<div class="modal-overlay" id="previewModalWrapper">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="previewTitle">Document Preview</h3>
            <button class="close-btn interactive" id="closePreviewModal">&times;</button>
        </div>
        <div style="padding-bottom: 1.5rem;">
            <iframe id="previewFrame" class="d-none"></iframe>
            <img id="previewImg" class="d-none" alt="Submitted file preview">
            <div id="previewUnsupported" class="d-none" style="text-align: center; padding: 4rem 2rem; border: 1px dashed var(--text-dark);">
                <i class="fa-solid fa-file-excel" style="font-size: 3rem; color: var(--text-tech); margin-bottom: 1rem;"></i>
                <h4 style="font-family: var(--font-head); font-weight: 700; text-transform: uppercase; margin-bottom: 0.5rem;">Preview Unavailable</h4>
                <p style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);">Please download the file using the button below to view its contents.</p>
            </div>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 2px solid var(--text-dark); padding-top: 1.5rem;">
            <button type="button" class="btn-outline interactive" id="cancelPreviewModal">Close</button>
            <a class="btn-tech primary interactive" id="previewDownloadBtn" href="#" style="margin: 0;">
                Download File
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

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

    // 2. Table Search, Filter & Pagination Logic
    const tbody = document.getElementById('historyTableBody');
    const allRows = Array.from(tbody.querySelectorAll('tr[data-subject]'));
    const pager = document.getElementById('historyPagination');
    const summary = document.getElementById('resultsSummary');
    const searchInput = document.getElementById('searchInput');
    const subjectFilter = document.getElementById('subjectFilter');
    const statusFilter = document.getElementById('statusFilter');
    const resetBtn = document.getElementById('resetFiltersBtn');
    const perPage = 8;
    let currentPage = 1;

    function getFilteredRows() {
        const q = (searchInput.value || '').trim().toLowerCase();
        const subj = subjectFilter.value;
        const stat = statusFilter.value;

        return allRows.filter(row => {
            const matchTitle = !q || row.dataset.title.includes(q);
            const matchSubj = !subj || row.dataset.subject === subj;
            const matchStat = !stat || row.dataset.status === stat;
            return matchTitle && matchSubj && matchStat;
        });
    }

    function renderTable() {
        if (!allRows.length) return;
        const rows = getFilteredRows();
        const totalPages = Math.max(1, Math.ceil(rows.length / perPage));
        if (currentPage > totalPages) currentPage = totalPages;

        allRows.forEach(r => r.style.display = 'none');
        const startIdx = (currentPage - 1) * perPage;
        const endIdx = startIdx + perPage;
        const visibleRows = rows.slice(startIdx, endIdx);
        visibleRows.forEach(r => r.style.display = '');

        let emptyRow = tbody.querySelector('tr.js-empty');
        if (!rows.length) {
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.className = 'js-empty';
                emptyRow.innerHTML = `
                    <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-tech); font-family: var(--font-mono); font-weight: 700;">
                        No submissions found matching your filters.
                    </td>`;
                tbody.appendChild(emptyRow);
            }
            emptyRow.style.display = '';
        } else if (emptyRow) {
            emptyRow.style.display = 'none';
        }

        // Render Pagination Links
        pager.innerHTML = '';
        if (totalPages > 1) {
            let html = `<li class="page-item ${currentPage === 1 ? 'disabled' : 'interactive'}">
                            <a class="page-link" href="#" data-p="${currentPage - 1}">PREV</a>
                        </li>`;
            for (let p = 1; p <= totalPages; p++) {
                html += `<li class="page-item ${p === currentPage ? 'active' : 'interactive'}">
                            <a class="page-link" href="#" data-p="${p}">${p}</a>
                         </li>`;
            }
            html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : 'interactive'}">
                        <a class="page-link" href="#" data-p="${currentPage + 1}">NEXT</a>
                      </li>`;
            pager.innerHTML = html;
            attachCursorHover(); // Re-attach cursor to new pagination buttons
        }

        const start = rows.length ? startIdx + 1 : 0;
        const end = Math.min(endIdx, rows.length);
        summary.textContent = `Showing ${start}-${end} of ${rows.length} records`;
    }

    pager.addEventListener('click', e => {
        e.preventDefault();
        const target = e.target.closest('[data-p]');
        if (!target || target.parentElement.classList.contains('disabled')) return;
        currentPage = parseInt(target.dataset.p, 10);
        renderTable();
    });

    searchInput.addEventListener('input', () => { currentPage = 1; renderTable(); });
    subjectFilter.addEventListener('change', () => { currentPage = 1; renderTable(); });
    statusFilter.addEventListener('change', () => { currentPage = 1; renderTable(); });

    resetBtn.addEventListener('click', () => {
        searchInput.value = '';
        subjectFilter.value = '';
        statusFilter.value = '';
        currentPage = 1;
        renderTable();
    });

    renderTable();

    // 3. Mobile Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const erpSidebar = document.getElementById('erpSidebar');
    if (sidebarToggle && erpSidebar) {
        sidebarToggle.addEventListener('click', () => {
            erpSidebar.classList.toggle('show');
        });
    }
});

// 4. Custom Modal Preview Logic
const previewModalWrapper = document.getElementById('previewModalWrapper');
const closePreviewBtn = document.getElementById('closePreviewModal');
const cancelPreviewBtn = document.getElementById('cancelPreviewModal');

function closePreviewModalFunc() {
    previewModalWrapper.style.display = 'none';
    document.getElementById('previewFrame').src = '';
    document.getElementById('previewImg').src = '';
}

closePreviewBtn.addEventListener('click', closePreviewModalFunc);
cancelPreviewBtn.addEventListener('click', closePreviewModalFunc);
previewModalWrapper.addEventListener('click', (e) => {
    if(e.target === previewModalWrapper) closePreviewModalFunc();
});

function openPreview(submissionId, fileType, fileName) {
    const frame = document.getElementById('previewFrame');
    const img = document.getElementById('previewImg');
    const unsupported = document.getElementById('previewUnsupported');
    const title = document.getElementById('previewTitle');
    const dlBtn = document.getElementById('previewDownloadBtn');

    title.innerHTML = fileName;
    dlBtn.href = `student_history.php?action=download&id=${submissionId}`;

    frame.classList.add('d-none');
    img.classList.add('d-none');
    unsupported.classList.add('d-none');
    frame.src = '';
    img.src = '';

    const previewUrl = `student_history.php?action=preview&id=${submissionId}`;
    const type = (fileType || '').toLowerCase();

    if (type === 'pdf') {
        frame.src = previewUrl;
        frame.classList.remove('d-none');
    } else if (['jpg', 'jpeg', 'png'].includes(type)) {
        img.src = previewUrl;
        img.classList.remove('d-none');
    } else {
        unsupported.classList.remove('d-none');
    }
    
    previewModalWrapper.style.display = 'flex';
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