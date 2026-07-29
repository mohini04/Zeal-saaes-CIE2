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
    $studentDept = trim($uRow['department'] ?? '');
    $studentYear = trim($uRow['academic_year'] ?? 'FY');
    $studentDiv  = trim($uRow['division'] ?? '');

    // Auto-heal missing academic details from access_requests
    if (empty($studentDept) || $studentDept === 'N/A' || empty($studentDiv) || $studentDiv === 'N/A' || $studentYear === 'FY') {
        try {
            $stmtAr = $pdo->prepare("SELECT department, academic_year, division FROM access_requests WHERE UPPER(prn_number) = UPPER(?) ORDER BY request_id DESC LIMIT 1");
            $stmtAr->execute([$studentPrn]);
            $arRow = $stmtAr->fetch(PDO::FETCH_ASSOC);
            if ($arRow) {
                $updateFields = [];
                $updateParams = [];
                if ((empty($studentDept) || $studentDept === 'N/A') && !empty($arRow['department'])) {
                    $studentDept = trim($arRow['department']);
                    $updateFields[] = "department = ?";
                    $updateParams[] = $studentDept;
                }
                if ($studentYear === 'FY' && !empty($arRow['academic_year'])) {
                    $studentYear = trim($arRow['academic_year']);
                    $updateFields[] = "academic_year = ?";
                    $updateParams[] = $studentYear;
                }
                if ((empty($studentDiv) || $studentDiv === 'N/A') && !empty($arRow['division'])) {
                    $studentDiv = trim($arRow['division']);
                    $updateFields[] = "division = ?";
                    $updateParams[] = $studentDiv;
                }
                if (!empty($updateFields)) {
                    $updateParams[] = $studentTableId;
                    $stmtUpDept = $pdo->prepare("UPDATE users SET " . implode(", ", $updateFields) . " WHERE user_id = ?");
                    $stmtUpDept->execute($updateParams);
                }
            }
        } catch (PDOException $e) {}
    }
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
           a.activity_id, a.type, a.subject AS subject_code, a.unit, a.title, a.max_marks, a.due_date
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
  
  <!-- Clean Academic Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
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
    .menu-label { font-size: 0.75rem; color: var(--text-muted); margin: 1.5rem 0.5rem 0.5rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; display: flex; justify-content: space-between; align-items: center;}
    
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

    /* Joined Classes UI in Sidebar */
    .joined-class-item {
        padding: 0.75rem 1rem;
        margin-bottom: 0.5rem;
        background: #f8fafc;
        border: 1px solid var(--border-color);
        border-left: 3px solid var(--blue-accent);
        border-radius: var(--radius-md);
        font-size: 0.8rem;
    }
    .joined-class-item strong { display: block; color: var(--text-main); margin-bottom: 0.2rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600;}
    .joined-class-item span { color: var(--text-muted); }

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

    /* ================= TAGS / BADGES ================= */
    .sys-tag { font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.6rem; border-radius: 999px; display: inline-flex; align-items: center; gap: 0.4rem; background: #f1f5f9; color: var(--text-muted); border: 1px solid var(--border-color); }
    .sys-tag.accent { background: #eff6ff; color: var(--blue-accent); border-color: #bfdbfe; }
    .sys-tag.success { background: #dcfce7; color: var(--success); border-color: #bbf7d0; }
    .sys-tag.danger { background: #fee2e2; color: var(--danger); border-color: #fecaca; }
    .sys-tag.warning { background: #fef3c7; color: var(--warning); border-color: #fde68a; }
    .sys-tag.info { background: #e0f2fe; color: #0284c7; border-color: #bae6fd; }

    /* ================= BUTTONS ================= */
    .btn {
        font-family: var(--font-main); font-weight: 500; font-size: 0.85rem;
        padding: 0.6rem 1.2rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
        border-radius: var(--radius-md); border: 1px solid transparent; cursor: pointer; transition: all 0.2s ease; text-decoration: none;
    }
    .btn-primary { background: var(--blue-accent); color: #fff; }
    .btn-primary:hover { background: #1d4ed8; }

    .btn-outline { background: transparent; border-color: var(--border-color); color: var(--text-main); }
    .btn-outline:hover { background: var(--bg-body); border-color: var(--text-muted); }

    /* ================= STATS GRID ================= */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; }
    .stat-block { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; flex-direction: column; justify-content: center; box-shadow: var(--shadow-sm); }
    .stat-val { font-size: 2.25rem; font-weight: 700; color: var(--navy-primary); line-height: 1; margin-bottom: 0.5rem; }
    .stat-label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;}

    /* ================= FILTERS & FORMS ================= */
    .filter-card {
        background: var(--bg-card); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--radius-lg);
        display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; box-shadow: var(--shadow-sm);
    }
    .form-control-custom, .form-select-custom {
        width: 100%; padding: 0.6rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color);
        color: var(--text-main); font-family: inherit; font-size: 0.9rem; outline: none; transition: border 0.2s;
        border-radius: var(--radius-md); -webkit-appearance: none;
    }
    .form-control-custom:focus, .form-select-custom:focus { border-color: var(--blue-accent); background: var(--bg-card); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

    /* ================= TABLES ================= */
    .table-responsive { overflow-x: auto; border: 1px solid var(--border-color); border-radius: var(--radius-md); }
    .custom-table { width: 100%; border-collapse: collapse; text-align: left; background: var(--bg-card); }
    .custom-table th, .custom-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; vertical-align: middle;}
    .custom-table th { background: var(--bg-body); color: var(--text-muted); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;}
    .custom-table tbody tr:hover { background: #f8fafc; }
    .custom-table tbody tr:last-child td { border-bottom: none; }

    /* Pagination */
    .pagination { display: flex; list-style: none; gap: 0.5rem; margin: 0; padding: 0;}
    .page-item .page-link { font-weight: 500; border: 1px solid var(--border-color); border-radius: var(--radius-md); color: var(--text-main); background: var(--bg-card); padding: 0.4rem 0.8rem; text-decoration: none; font-size: 0.85rem;}
    .page-item.active .page-link { background: var(--blue-accent); color: #fff; border-color: var(--blue-accent); }
    .page-item.disabled .page-link { opacity: 0.5; pointer-events: none; background: var(--bg-body);}

    /* ================= MODALS ================= */
    .modal-overlay {
      position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px);
      display: none; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;
    }
    .modal-content {
      background: var(--bg-card); border: 1px solid var(--border-color);
      max-width: 800px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 2rem;
      border-radius: var(--radius-lg); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
    .modal-header h3 { font-weight: 700; color: var(--navy-primary); font-size: 1.25rem; margin: 0;}
    .close-btn { background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; padding: 0; line-height: 1;}
    .close-btn:hover { color: var(--text-main); }
    
    #previewFrame { width: 100%; height: 60vh; border: 1px solid var(--border-color); border-radius: var(--radius-md); }
    #previewImg { max-width: 100%; max-height: 60vh; display: block; margin: 0 auto; border: 1px solid var(--border-color); border-radius: var(--radius-md); }

    @media (max-width: 1024px) {
        .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
        .sidebar.show { transform: translateX(0); }
        .content-wrapper { margin-left: 0; }
    }
    @media (max-width: 600px) {
        .filter-card { flex-direction: column; align-items: stretch; }
    }
  </style>
</head>
<body>

<div class="app-container">

    <!-- LEFT SIDEBAR -->
    <aside class="sidebar" id="erpSidebar">
        <div class="sidebar-header">
            <a href="student_dashboard.php" class="brand-logo">
                <i class="fa-solid fa-graduation-cap"></i>
                <span>Student Hub</span>
            </a>
        </div>

        <div class="sidebar-menu">
            <div class="menu-label">Navigation</div>
            <a href="student_dashboard.php" class="sidebar-link">
                <i class="fa-solid fa-house"></i> <span>Dashboard</span>
            </a>
            <a href="student_submit.php" class="sidebar-link">
                <i class="fa-solid fa-cloud-arrow-up"></i> <span>Upload Assignment</span>
            </a>
            <a href="student_history.php" class="sidebar-link active">
                <i class="fa-solid fa-clock-rotate-left"></i> <span>Submission History</span>
            </a>

            <div class="menu-label">Account</div>
            <a href="auth/logout.php" class="sidebar-link" style="color: var(--danger);">
                <i class="fa-solid fa-power-off"></i> <span>Logout</span>
            </a>

            <!-- Joined Classes Section -->
            <div class="menu-label" style="margin-top: 1rem;">
                <span>Joined Classes</span>
                <span class="sys-tag accent" style="margin: 0; padding: 0.1rem 0.5rem;"><?= count($myClasses) ?></span>
            </div>
            <div style="padding: 0 1rem;">
                <?php if (empty($myClasses)): ?>
                    <div style="font-size: 0.8rem; color: var(--text-muted);">No classes joined yet.</div>
                <?php else: ?>
                    <?php foreach ($myClasses as $c): ?>
                        <div class="joined-class-item">
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
            <div class="avatar"><?php echo strtoupper(substr($studentName, 0, 1)); ?></div>
            <div>
                <div style="font-weight: 600; font-size: 0.85rem; color: var(--navy-primary);"><?php echo e($studentName); ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">Role: <?php echo ucfirst(e($role)); ?></div>
            </div>
        </div>
    </aside>

    <!-- CONTENT WRAPPER -->
    <div class="content-wrapper">
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline d-lg-none" id="sidebarToggle" style="padding: 0.4rem 0.8rem;"><i class="fa-solid fa-bars"></i></button>
                <h3>Submission History</h3>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="student_submit.php" class="btn btn-primary">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload New
                </a>
            </div>
        </header>

        <main class="main-content">

            <!-- Hero Header -->
            <div class="hero-banner">
                <div class="hero-content">
                    <div>
                        <h1 class="hero-title">Submission Log</h1>
                        <p class="hero-subtitle">
                            Track all submitted activities, view earned scores, and preview uploaded files.
                        </p>
                    </div>
                </div>
            </div>

            <!-- KPI Metrics Grid -->
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                <div class="stat-block">
                    <div class="stat-val" style="color: var(--navy-primary);"><?= count($history) ?></div>
                    <div class="stat-label">Total Submissions</div>
                </div>
                <div class="stat-block">
                    <div class="stat-val" style="color: var(--success);"><?= $totalEvaluated ?></div>
                    <div class="stat-label">Graded</div>
                </div>
                <div class="stat-block">
                    <div class="stat-val" style="color: var(--blue-accent);"><?= $totalReview ?></div>
                    <div class="stat-label">Under Review</div>
                </div>
            </div>

            <!-- Control Toolbar & Filters -->
            <div class="filter-card">
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
                    <button id="resetFiltersBtn" class="btn btn-outline" style="margin:0;">
                        Reset Filters
                    </button>
                </div>
            </div>

            <!-- Submission History Table -->
            <div class="table-responsive">
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
                                <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted); font-weight: 500;">
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
                                    <strong style="font-size: 0.95rem; color: var(--text-main); display: block; margin-bottom: 0.3rem;"><?= e($h['title']) ?></strong>
                                    <span class="sys-tag hero" style="background: var(--navy-primary); border: none; color: white; padding: 0.15rem 0.5rem; font-size: 0.65rem; margin:0; margin-bottom: 0.3rem; margin-right: 0.3rem;"><?= e(ucwords(str_replace('_', ' ', $h['type']))) ?></span>
                                    <span class="sys-tag accent" style="font-size: 0.7rem; margin:0;">Unit <?= e($h['unit']) ?></span>
                                </td>
                                
                                <td>
                                    <strong style="color: var(--text-main);"><?= e($h['subject_code']) ?></strong>
                                </td>
                                
                                <td>
                                    <div style="font-size: 0.85rem; color: var(--blue-accent); font-weight: 600; max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($h['original_filename']) ?>">
                                        <i class="fa-regular fa-file-lines me-1"></i> <?= e($h['original_filename']) ?>
                                    </div>
                                </td>
                                
                                <td style="font-size: 0.85rem; color: var(--text-muted);">
                                    <?php if ($h['submission_date']): ?>
                                        <strong style="color: var(--text-main); font-weight: 500;"><?= fmtDateTime($h['submission_date']) ?></strong>
                                    <?php else: ?>
                                        Due: <?= fmtDateTime($h['due_date']) ?>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <?php if ($h['marks'] !== null): ?>
                                        <strong style="font-size: 1.1rem; color: var(--success);"><?= e($h['marks']) ?></strong>
                                        <span style="font-size: 0.75rem; color: var(--text-muted);">/ <?= e($h['max_marks']) ?></span>
                                    <?php elseif ($displayStatus === 'Under Review'): ?>
                                        <span class="sys-tag info" style="margin:0;">Under Evaluation</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <span class="sys-tag <?= badgeClass($displayStatus) ?>" style="margin:0;"><?= e($displayStatus) ?></span>
                                </td>
                                
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                        <?php if (!empty($h['id'])): ?>
                                            <button type="button" class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" title="View Document Preview"
                                                    onclick='openPreview(<?= (int)$h['id'] ?>, <?= jsAttr($h['file_type']) ?>, <?= jsAttr($h['original_filename']) ?>)'>
                                                <i class="fa-regular fa-eye"></i> View
                                            </button>
                                            <a class="btn btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; margin: 0;" title="Download Original File"
                                               href="student_history.php?action=download&id=<?= (int)$h['id'] ?>">
                                                <i class="fa-solid fa-download"></i> DL
                                            </a>
                                        <?php else: ?>
                                            <span class="sys-tag" style="background: transparent; color: var(--text-muted); border-color: var(--border-color); margin:0;">Closed</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination & Summary Footer -->
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);" id="resultsSummary">Showing submissions</div>
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
            <button class="close-btn" id="closePreviewModal">&times;</button>
        </div>
        <div style="padding-bottom: 1.5rem;">
            <iframe id="previewFrame" class="d-none"></iframe>
            <img id="previewImg" class="d-none" alt="Submitted file preview">
            <div id="previewUnsupported" class="d-none" style="text-align: center; padding: 3rem 2rem;">
                <i class="fa-solid fa-file-circle-xmark" style="font-size: 3rem; color: var(--border-color); margin-bottom: 1rem;"></i>
                <h4 style="font-weight: 600; color: var(--navy-primary); margin-bottom: 0.5rem;">Preview Unavailable</h4>
                <p style="font-size: 0.9rem; color: var(--text-muted);">Please download the file using the button below to view its contents.</p>
            </div>
        </div>
        <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <button type="button" class="btn btn-outline" id="cancelPreviewModal">Close</button>
            <a class="btn btn-primary" id="previewDownloadBtn" href="#" style="margin: 0;">
                <i class="fa-solid fa-download"></i> Download File
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

    // 1. Table Search, Filter & Pagination Logic
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
                    <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted); font-weight: 500;">
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
            let html = `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                            <a class="page-link" href="#" data-p="${currentPage - 1}">Prev</a>
                        </li>`;
            for (let p = 1; p <= totalPages; p++) {
                html += `<li class="page-item ${p === currentPage ? 'active' : ''}">
                            <a class="page-link" href="#" data-p="${p}">${p}</a>
                         </li>`;
            }
            html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                        <a class="page-link" href="#" data-p="${currentPage + 1}">Next</a>
                      </li>`;
            pager.innerHTML = html;
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

    // 2. Mobile Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const erpSidebar = document.getElementById('erpSidebar');
    if (sidebarToggle && erpSidebar) {
        sidebarToggle.addEventListener('click', () => {
            erpSidebar.classList.toggle('show');
        });
    }
});

// 3. Custom Modal Preview Logic
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