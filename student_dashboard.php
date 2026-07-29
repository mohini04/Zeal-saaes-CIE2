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

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function fmtDate($d) { return $d ? (new DateTime($d))->format('d M Y') : '—'; }
function fmtDateTime($d) { return $d ? (new DateTime($d))->format('d M Y, h:i A') : '—'; }

$studentUserId = (int) $_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? $_SESSION['user_name'] ?? 'Student';
$_SESSION['full_name'] = $fullName;

// 1. Fetch student user profile directly from users table
$stmt = $pdo->prepare("SELECT user_id, name, username, email, role, department, academic_year, roll_no, division, phone, linked_student_prn FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$studentUserId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$studentTableId   = (int) ($student['user_id'] ?? $studentUserId);
$_SESSION['student_id'] = $studentTableId;
$studentUsername  = $student['username'] ?? ($_SESSION['username'] ?? '');
$studentLinkedPrn = $student['linked_student_prn'] ?? $studentUsername;
$role             = $_SESSION['role'] ?? 'student';

// Determine PRN / ZPRN display label (uses real registered PRN)
$studentZprn      = !empty($studentUsername) ? $studentUsername : ('ZPRN' . str_pad((string)$studentTableId, 4, '0', STR_PAD_LEFT));

// Determine Roll No, Division, and Academic Year from users table
$rollNo           = !empty($student['roll_no']) ? $student['roll_no'] : 'N/A';
$division         = !empty($student['division']) ? $student['division'] : 'N/A';
$academic_year    = !empty($student['academic_year']) ? $student['academic_year'] : 'FY';

// Determine Department, Academic Year, Division with Auto-healing sync from access_requests if blank
$deptName = trim($student['department'] ?? '');
if (empty($deptName) || $deptName === 'N/A' || $division === 'N/A' || $academic_year === 'FY') {
    try {
        $stmtAr = $pdo->prepare("SELECT department, academic_year, division FROM access_requests WHERE (UPPER(prn_number) = UPPER(?) OR LOWER(email) = LOWER(?)) AND department != '' ORDER BY request_id DESC LIMIT 1");
        $stmtAr->execute([$studentUsername, $student['email'] ?? '']);
        $arRow = $stmtAr->fetch(PDO::FETCH_ASSOC);
        if ($arRow) {
            $updateFields = [];
            $updateParams = [];
            
            if ((empty($deptName) || $deptName === 'N/A') && !empty($arRow['department'])) {
                $deptName = trim($arRow['department']);
                $updateFields[] = "department = ?";
                $updateParams[] = $deptName;
            }
            if ($academic_year === 'FY' && !empty($arRow['academic_year'])) {
                $academic_year = trim($arRow['academic_year']);
                $updateFields[] = "academic_year = ?";
                $updateParams[] = $academic_year;
            }
            if ($division === 'N/A' && !empty($arRow['division'])) {
                $division = trim($arRow['division']);
                $updateFields[] = "division = ?";
                $updateParams[] = $division;
            }
            
            // Sync back to users table permanently
            if (!empty($updateFields)) {
                $updateParams[] = $studentUserId;
                $stmtUpDept = $pdo->prepare("UPDATE users SET " . implode(", ", $updateFields) . " WHERE user_id = ?");
                $stmtUpDept->execute($updateParams);
            }
        }
    } catch (PDOException $e) {
        // Swallowed safely
    }
}
if (empty($deptName)) {
    $deptName = 'Electronics and Computer Engineering';
}

// 2. Fetch Joined Classes for the Sidebar
$stmtClasses = $pdo->prepare("
    SELECT DISTINCT fc.class_id, fc.class_name, fc.subject_code, fc.description, u.name AS faculty_name
    FROM faculty_classes fc
    LEFT JOIN users u ON fc.faculty_id = u.user_id
    LEFT JOIN faculty_class_students fcs ON fcs.class_id = fc.class_id
    WHERE (fc.department = ? AND fc.academic_year = ? AND fc.division = ?)
       OR (fcs.student_prn = ? OR fcs.student_prn = ?)
    ORDER BY fc.created_at DESC
");
$stmtClasses->execute([$deptName, $academic_year, $division, $studentUsername, $studentLinkedPrn]);
$myClasses = $stmtClasses->fetchAll(PDO::FETCH_ASSOC) ?: [];

// 3. Fetch all activities & student submissions
$stmtAct = $pdo->prepare("
    SELECT DISTINCT a.activity_id AS id, a.type, a.subject AS subject_code, a.unit, a.title, a.description, a.due_date, a.max_marks,
           s.id AS submission_id, s.original_filename, s.submission_date, s.status AS sub_status,
           s.marks, s.file_type, s.remarks
    FROM activities a
    LEFT JOIN submissions s ON s.activity_id = a.activity_id AND s.student_id = ?
    WHERE a.target_type = 'all' 
       OR (a.target_type = 'individual' AND a.target_id = ?)
       OR (a.target_type IN ('class', 'group') AND a.target_id IN (
           SELECT fc.class_id FROM faculty_classes fc 
           LEFT JOIN faculty_class_students fcs ON fcs.class_id = fc.class_id
           WHERE (fc.department = ? AND fc.academic_year = ? AND fc.division = ?)
              OR (fcs.student_prn = ? OR fcs.student_prn = ?)
       ))
    ORDER BY a.due_date ASC
");
$stmtAct->execute([$studentTableId, $studentTableId, $deptName, $academic_year, $division, $studentUsername, $studentLinkedPrn]);
$activities = $stmtAct->fetchAll(PDO::FETCH_ASSOC) ?: [];

$totalActivities  = count($activities);
$totalSubmitted   = 0;
$totalEvaluated   = 0;
$totalMissed      = 0;
$totalPending     = 0;
$totalEarnedMarks = 0;
$maxPossibleMarks = 0;
$subjectStats     = [];
$upcomingDeadlines = [];
$actionRequiredItems = [];

$now = new DateTime();
foreach ($activities as &$a) {
    $maxPossibleMarks += (float)($a['max_marks'] ?? 5);
    $due = new DateTime($a['due_date']);
    $subj = $a['subject_code'];

    if (!isset($subjectStats[$subj])) {
        $subjectStats[$subj] = ['total' => 0, 'completed' => 0, 'earned' => 0, 'max' => 0];
    }
    $subjectStats[$subj]['total']++;
    $subjectStats[$subj]['max'] += (float)($a['max_marks'] ?? 5);

    if (!empty($a['submission_id'])) {
        $totalSubmitted++;
        $subjectStats[$subj]['completed']++;
        
        if ($a['sub_status'] === 'Submitted' && $a['marks'] !== null) {
            $totalEvaluated++;
            $totalEarnedMarks += (float) $a['marks'];
            $subjectStats[$subj]['earned'] += (float) $a['marks'];
            $a['display_status'] = 'Evaluated';
        } elseif ($a['sub_status'] === 'Approved') {
            $totalEvaluated++;
            $totalEarnedMarks += (float) ($a['marks'] ?? $a['max_marks']);
            $subjectStats[$subj]['earned'] += (float) ($a['marks'] ?? $a['max_marks']);
            $a['display_status'] = 'Evaluated';
        } else {
            $a['display_status'] = 'Submitted';
        }
    } else {
        if ($now > $due) {
            $totalMissed++;
            $a['display_status'] = 'Missed';
        } else {
            $totalPending++;
            $a['display_status'] = 'Pending';
            
            $diff = $now->diff($due);
            $daysLeft = $diff->days;
            $hoursLeft = $diff->h;
            
            if ($daysLeft == 0) {
                $a['countdown_label'] = "Due in {$hoursLeft} hrs";
                $a['countdown_class'] = "danger";
            } elseif ($daysLeft <= 3) {
                $a['countdown_label'] = "Due in {$daysLeft} day" . ($daysLeft > 1 ? 's' : '');
                $a['countdown_class'] = "warning";
            } else {
                $a['countdown_label'] = "Due in {$daysLeft} days";
                $a['countdown_class'] = "info";
            }

            if (!$diff->invert) {
                $upcomingDeadlines[] = $a;
            }
            if ($daysLeft <= 7 && !$diff->invert) {
                $actionRequiredItems[] = $a;
            }
        }
    }
}
unset($a);

$overallCompletionRate = $totalActivities > 0 ? round(($totalSubmitted / $totalActivities) * 100) : 0;
$scorePercent = $maxPossibleMarks > 0 ? round(($totalEarnedMarks / $maxPossibleMarks) * 100) : 0;

if ($scorePercent >= 85) {
    $standingBadge = ['label' => 'Outstanding', 'color' => '#10b981'];
} elseif ($scorePercent >= 70) {
    $standingBadge = ['label' => 'Good', 'color' => '#2563eb'];
} elseif ($scorePercent >= 50) {
    $standingBadge = ['label' => 'Average', 'color' => '#f59e0b'];
} else {
    $standingBadge = ['label' => 'Needs Attention', 'color' => '#ef4444'];
}

// Recent Graded Submissions Snapshot
$recentEvaluated = array_values(array_filter($activities, fn($x) => !empty($x['submission_id'])));
usort($recentEvaluated, fn($x, $y) => strtotime($y['submission_date']) <=> strtotime($x['submission_date']));
$recentEvaluatedSnapshot = array_slice($recentEvaluated, 0, 4);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard | SAAES</title>

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
.hero-title { font-size: 1.75rem; font-weight: 700; margin-bottom: 1rem; }

/* ================= TAGS / BADGES ================= */
.sys-tag { font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.6rem; border-radius: 999px; display: inline-flex; align-items: center; gap: 0.4rem; background: #f1f5f9; color: var(--text-muted); border: 1px solid var(--border-color); }
.sys-tag.accent { background: #eff6ff; color: var(--blue-accent); border-color: #bfdbfe; }
.sys-tag.success { background: #dcfce7; color: var(--success); border-color: #bbf7d0; }
.sys-tag.danger { background: #fee2e2; color: var(--danger); border-color: #fecaca; }
.sys-tag.warning { background: #fef3c7; color: var(--warning); border-color: #fde68a; }
.sys-tag.info { background: #e0f2fe; color: #0284c7; border-color: #bae6fd; }
.sys-tag.hero { background: rgba(255,255,255,0.15); color: #fff; border-color: rgba(255,255,255,0.3); }

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

/* ================= STATS GRID ================= */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; }
.stat-block { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; flex-direction: column; justify-content: center; box-shadow: var(--shadow-sm); }
.stat-val { font-size: 2.25rem; font-weight: 700; color: var(--navy-primary); line-height: 1; margin-bottom: 0.5rem; }
.stat-label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;}

/* ================= LISTS & GRIDS ================= */
.task-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
.task-card {
    background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem;
    display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--shadow-sm); transition: transform 0.2s, box-shadow 0.2s;
}
.task-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: #cbd5e1; }

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
            <a href="student_dashboard.php" class="brand-logo">
                <i class="fa-solid fa-graduation-cap"></i>
                <span>Student Hub</span>
            </a>
        </div>

        <div class="sidebar-menu">
            <div class="menu-label">Navigation</div>
            <a href="student_dashboard.php" class="sidebar-link active">
                <i class="fa-solid fa-house"></i> <span>Dashboard</span>
            </a>

            <a href="student_submit.php" class="sidebar-link">
                <i class="fa-solid fa-cloud-arrow-up"></i> <span>Upload Assignment</span>
            </a>

            <a href="student_history.php" class="sidebar-link">
                <i class="fa-solid fa-clock-rotate-left"></i> <span>Submission History</span>
            </a>

            <div class="menu-label">Account</div>
            <a href="auth/logout.php" class="sidebar-link" style="color: var(--danger);">
                <i class="fa-solid fa-power-off"></i> <span>Logout</span>
            </a>

            <!-- Joined Classes Section -->
            <div class="menu-label" style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
                <span>Joined Classes</span>
                <span class="sys-tag accent" style="margin: 0;"><?= count($myClasses) ?></span>
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
            <div class="avatar"><?php echo strtoupper(substr($fullName, 0, 1)); ?></div>
            <div>
                <div style="font-weight: 600; font-size: 0.85rem; color: var(--navy-primary);"><?php echo e($fullName); ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;">Role: <?php echo ucfirst(e($role)); ?></div>
            </div>
        </div>
    </aside>

    <!-- CONTENT WRAPPER -->
    <div class="content-wrapper">
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline d-lg-none" id="sidebarToggle" style="padding: 0.4rem 0.8rem;"><i class="fa-solid fa-bars"></i></button>
                <h3>Student Dashboard</h3>
            </div>
        </header>

        <main class="main-content">

            <!-- Hero Welcome Banner -->
            <div class="hero-banner">
                <div class="hero-content">
                    <div>
                        <h1 class="hero-title">Welcome, <?= e(explode(' ', $fullName)[0]) ?></h1>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <span class="sys-tag hero"><i class="fa-solid fa-barcode"></i> PRN: <?php echo e($studentZprn); ?></span>
                            <span class="sys-tag hero"><i class="fa-solid fa-id-card"></i> ROLL: <?php echo e($rollNo); ?></span>
                            <span class="sys-tag hero"><i class="fa-solid fa-layer-group"></i> DIV: <?php echo e($division); ?></span>
                            <span class="sys-tag hero"><i class="fa-solid fa-building-columns"></i> DEPT: <?php echo e($deptName); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- "Action Required" Priority Alert Banner -->
            <?php if (!empty($actionRequiredItems)): ?>
            <div class="module-card" style="border-left: 4px solid var(--danger); padding: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem;">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <div style="width: 48px; height: 48px; border-radius: 50%; background: #fee2e2; color: var(--danger); display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--danger); margin-bottom: 0.2rem;">Action Required: Pending Submissions</h2>
                            <p style="font-size: 0.9rem; color: var(--text-muted); margin: 0;">You have <strong><?= count($actionRequiredItems) ?> assignment(s)</strong> closing within the next 7 days.</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <?php foreach (array_slice($actionRequiredItems, 0, 2) as $actReq): ?>
                            <a href="student_submit.php" class="btn btn-danger">
                                <i class="fa-solid fa-cloud-arrow-up"></i> Submit: <?= e($actReq['subject_code']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Consolidated KPI Telemetry Grid -->
            <div class="stats-grid">
                <div class="stat-block">
                    <div class="stat-val" style="color: var(--navy-primary);"><?= $totalActivities ?></div>
                    <div class="stat-label">Total Activities</div>
                </div>
                
                <div class="stat-block">
                    <div class="stat-val" style="color: var(--warning);"><?= $totalPending ?></div>
                    <div class="stat-label">Pending Submit</div>
                </div>
                
                <div class="stat-block">
                    <div class="stat-val" style="color: var(--success);"><?= $totalEvaluated ?></div>
                    <div class="stat-label">Evaluated [<?= number_format((float)$totalEarnedMarks, 1) ?> PTS]</div>
                </div>
                
                <div class="stat-block">
                    <div class="stat-val" style="color: <?= $standingBadge['color'] ?>;"><?= $scorePercent ?>%</div>
                    <div class="stat-label">Standing: <?= strtoupper($standingBadge['label']) ?></div>
                </div>
            </div>

            <!-- Upcoming Deadlines Chronological Checklist -->
            <?php if (!empty($upcomingDeadlines)): ?>
            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap;">
                    <div>
                        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--navy-primary); margin: 0;"><i class="fa-solid fa-list-check" style="color: var(--blue-accent); margin-right: 0.5rem;"></i> Upcoming Deadlines</h2>
                        <span style="color: var(--text-muted); font-size: 0.85rem;">Chronological task queue</span>
                    </div>
                    <a href="student_submit.php" class="btn btn-outline">Submit Queue &rarr;</a>
                </div>

                <div class="task-grid">
                    <?php foreach ($upcomingDeadlines as $item): ?>
                    <div class="task-card">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                <div style="display: flex; gap: 0.5rem; align-items: center;">
                                    <span class="sys-tag accent"><?= e($item['subject_code']) ?> // U-<?= e($item['unit']) ?></span>
                                    <span class="sys-tag hero" style="background: var(--navy-primary); border: none; color: white; padding: 0.15rem 0.5rem; font-size: 0.7rem;"><?= e(ucwords(str_replace('_', ' ', $item['type']))) ?></span>
                                </div>
                                <span class="sys-tag <?= $item['countdown_class'] ?>">
                                    <?= $item['countdown_label'] ?>
                                </span>
                            </div>
                            <strong style="font-size: 1.05rem; font-weight: 600; margin-bottom: 0.5rem; display: block; color: var(--text-main);"><?= e($item['title']) ?></strong>
                            <small style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem; display: block;">Due Date: <?= fmtDate($item['due_date']) ?></small>
                        </div>
                        
                        <a href="student_submit.php" class="btn btn-primary" style="width: 100%;">
                            Go to Submit
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Grade & Feedback Snapshot Grid Section -->
            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap;">
                    <div>
                        <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--navy-primary); margin: 0;"><i class="fa-solid fa-square-poll-vertical" style="color: var(--success); margin-right: 0.5rem;"></i> Recent Evaluations</h2>
                        <span style="color: var(--text-muted); font-size: 0.85rem;">Latest graded assignments</span>
                    </div>
                </div>

                <?php if (empty($recentEvaluatedSnapshot)): ?>
                    <div style="text-align: center; padding: 3rem; border: 1px dashed var(--border-color); border-radius: var(--radius-md);">
                        <p style="font-size: 0.9rem; font-weight: 500; color: var(--text-muted); margin: 0;">No evaluation data available yet.</p>
                    </div>
                <?php else: ?>
                    <div class="task-grid">
                        <?php foreach ($recentEvaluatedSnapshot as $snap): ?>
                        <div class="task-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                <span class="sys-tag accent"><?= e($snap['subject_code']) ?></span>
                                <strong style="font-size: 1.1rem; color: var(--success);"><?= number_format((float)$snap['marks'], 1) ?>/<?= number_format((float)$snap['max_marks'], 1) ?></strong>
                            </div>
                            <strong style="font-size: 1rem; font-weight: 600; margin-bottom: 0.5rem; display: block; color: var(--text-main);"><?= e($snap['title']) ?></strong>
                            <small style="color: var(--text-muted); font-size: 0.85rem; margin-bottom: 1.5rem; display: block;">Status: Evaluated</small>
                            
                            <div style="font-size: 0.8rem; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: auto;">
                                Submitted: <?= fmtDate($snap['submission_date']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // Sidebar Toggle (Mobile)
    const sidebarToggle = document.getElementById('sidebarToggle');
    const erpSidebar = document.getElementById('erpSidebar');

    if (sidebarToggle && erpSidebar) {
      sidebarToggle.addEventListener('click', () => {
        erpSidebar.classList.toggle('show');
      });
    }
});
</script>

<?php 
$modalPath = __DIR__ . '/includes/end_session_modal.php';
if (file_exists($modalPath)) {
    include_once $modalPath;
}
?>
</body>
</html>