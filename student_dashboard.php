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

// Determine Department with Auto-healing sync from access_requests if blank
$deptName = trim($student['department'] ?? '');
if (empty($deptName) || $deptName === 'N/A') {
    try {
        $stmtAr = $pdo->prepare("SELECT department FROM access_requests WHERE (UPPER(prn_number) = UPPER(?) OR LOWER(email) = LOWER(?)) AND department != '' ORDER BY request_id DESC LIMIT 1");
        $stmtAr->execute([$studentUsername, $student['email'] ?? '']);
        $arRow = $stmtAr->fetch(PDO::FETCH_ASSOC);
        if ($arRow && !empty($arRow['department'])) {
            $deptName = trim($arRow['department']);
            // Sync back to users table permanently
            $stmtUpDept = $pdo->prepare("UPDATE users SET department = ? WHERE user_id = ?");
            $stmtUpDept->execute([$deptName, $studentUserId]);
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
    SELECT DISTINCT a.activity_id AS id, a.subject AS subject_code, a.unit, a.title, a.description, a.due_date, a.max_marks,
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
                $a['countdown_class'] = "danger"; // Used for custom CSS class
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
    $standingBadge = ['label' => 'Outstanding', 'color' => '#10b981']; // Green
} elseif ($scorePercent >= 70) {
    $standingBadge = ['label' => 'Good', 'color' => '#3b82f6']; // Blue
} elseif ($scorePercent >= 50) {
    $standingBadge = ['label' => 'Average', 'color' => '#f59e0b']; // Orange
} else {
    $standingBadge = ['label' => 'Needs Attention', 'color' => '#ef4444']; // Red
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

<!-- Professional Fonts -->
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
  
  --accent-main: #7c3aed; /* The Deep Electric Purple */
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
  /* Blueprint Grid tinted slightly purple */
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
  border: 2px solid var(--accent-main); /* Purple Border for Active */
  box-shadow: 4px 4px 0px rgba(124, 58, 237, 0.2); /* Purple Shadow */
}
.sidebar-link i { font-size: 1.1rem; width: 22px; text-align: center; }

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

.sidebar-user {
  padding: 1.25rem; border-top: var(--border-harsh);
  display: flex; align-items: center; gap: 0.75rem; background: var(--bg-panel);
}
.avatar {
  width: 40px; height: 40px; border: 2px solid var(--accent-main); background: var(--bg-base);
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

/* Custom tag colors based on status logic above */
.sys-tag.danger { border-color: #ef4444; color: #ef4444; background: rgba(239, 68, 68, 0.05); }
.sys-tag.warning { border-color: #f59e0b; color: #f59e0b; background: rgba(245, 158, 11, 0.05); }
.sys-tag.info { border-color: #3b82f6; color: #3b82f6; background: rgba(59, 130, 246, 0.05); }
.sys-tag.success { border-color: #10b981; color: #10b981; background: rgba(16, 185, 129, 0.05); }

/* ================= BUTTONS ================= */
.btn {
    font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem; text-transform: uppercase;
    padding: 0.8rem 1.5rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
    background: var(--bg-base); color: var(--text-dark); border: 2px solid var(--text-dark);
    position: relative; overflow: hidden; z-index: 1; cursor: pointer; text-decoration: none;
    clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
    transition: color 0.3s;
}
.btn::before {
    content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
    background: var(--accent-main); z-index: -1; transition: left 0.3s cubic-bezier(0.7, 0, 0.3, 1);
}
.btn:hover { color: #fff; border-color: var(--accent-main); }
.btn:hover::before { left: 0; }

/* Purple Primary Button */
.btn-primary { background: var(--accent-main); color: #fff; border-color: var(--accent-main); }
.btn-primary:hover { color: #fff; border-color: var(--text-dark);}
.btn-primary::before { background: var(--text-dark); }

.btn-danger { border-color: #ef4444; color: #ef4444; }
.btn-danger::before { background: #ef4444; }
.btn-danger:hover { color: #fff; border-color: #ef4444; }

.btn-outline { background: transparent; border: 2px solid var(--text-dark); color: var(--text-dark); font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem; padding: 0.6rem 1.2rem; cursor: pointer; transition: 0.2s;}
.btn-outline:hover { background: var(--text-dark); color: #fff; }

/* ================= TELEMETRY STATS GRID ================= */
.telemetry-grid { 
    display: grid; grid-template-columns: repeat(5, 1fr); 
    border: 2px solid var(--text-dark); background: var(--bg-panel);
}
.tel-block { 
    padding: 2rem 1.5rem; border-right: 2px solid var(--text-dark); 
    display: flex; flex-direction: column; justify-content: center; align-items: flex-start;
    transition: background 0.2s;
}
.tel-block:hover { background: var(--accent-bg); }
.tel-block:last-child { border-right: none; }
.tel-val { font-family: var(--font-head); font-size: 3rem; font-weight: 700; color: var(--text-dark); line-height: 1; margin-bottom: 0.5rem; }
.tel-label { font-family: var(--font-mono); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-tech); margin-bottom: 1rem;}
.tel-link { font-family: var(--font-mono); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--accent-main); margin-top: auto;}
.tel-link:hover { text-decoration: underline; }

/* ================= LISTS & GRIDS ================= */
.task-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
.task-card {
    border: 2px solid var(--text-dark); padding: 1.5rem; background: var(--bg-base);
    display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s, box-shadow 0.2s;
    clip-path: polygon(0 0, calc(100% - 15px) 0, 100% 15px, 100% 100%, 15px 100%, 0 calc(100% - 15px));
}
.task-card:hover { transform: translate(-4px, -4px); box-shadow: 8px 8px 0px rgba(124, 58, 237, 1); border-color: var(--accent-main); }

/* ================= ALERTS ================= */
.alert { font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; border: 2px solid transparent; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 0.5rem;}
.alert-danger { background: var(--bg-base); color: #ef4444; border-color: #ef4444; }
.alert-warning { background: var(--bg-base); color: #f59e0b; border-color: #f59e0b; }
.alert-info { background: var(--bg-base); color: #3b82f6; border-color: #3b82f6; }
.alert-success { background: var(--bg-base); color: #10b981; border-color: #10b981; }

/* ================= FORMS & MODALS ================= */
.form-group { margin-bottom: 1.5rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--text-dark); }
.form-control {
  width: 100%; padding: 0.85rem 1.2rem; background: var(--bg-base); border: 1px solid var(--text-tech);
  color: var(--text-dark); font-family: var(--font-body); font-size: 0.95rem; outline: none; transition: border 0.2s;
  border-radius: 0; -webkit-appearance: none;
}
.form-control:focus { border-color: var(--accent-main); border-width: 2px; padding: calc(0.85rem - 1px) calc(1.2rem - 1px); }

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

/* ================= TECHNICAL FOOTER ================= */
.tech-footer { background: var(--text-dark); color: #fff; padding: 2rem 5%; font-family: var(--font-mono); margin-top: auto; }
.ft-top { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 2rem; align-items: center;}
.ft-bottom { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-light); margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.2);}

@media (max-width: 1024px) {
    .telemetry-grid { grid-template-columns: repeat(2, 1fr); }
    .tel-block:nth-child(2), .tel-block:nth-child(4) { border-right: none; }
    .tel-block:nth-child(1), .tel-block:nth-child(2), .tel-block:nth-child(3) { border-bottom: 2px solid var(--text-dark); }
    .tel-block:last-child { border-right: none; }
    body { cursor: auto; }
    #cursor-crosshair, #cursor-brackets { display: none; }
    .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
    .sidebar.show { transform: translateX(0); }
    .content-wrapper { margin-left: 0; }
}
@media (max-width: 600px) {
    .telemetry-grid { grid-template-columns: 1fr; }
    .tel-block { border-right: none !important; border-bottom: 2px solid var(--text-dark); }
    .tel-block:last-child { border-bottom: none; }
}
</style>
</head>
<body>

<div class="scanline"></div>

<!-- Custom Rigid Cursor -->
<div id="cursor-crosshair"></div>
<div id="cursor-brackets"></div>

<div class="app-container">

    <!-- LEFT SIDEBAR LAYOUT -->
    <aside class="sidebar" id="erpSidebar">
        <div class="sidebar-header">
            <a href="student_dashboard.php" class="brand-logo interactive">
                <i class="fa-solid fa-graduation-cap"></i>
                <span>Student Hub</span>
            </a>
        </div>

        <div class="sidebar-menu">
            <div class="menu-label">Navigation</div>
            <a href="student_dashboard.php" class="sidebar-link interactive active">
                <span>Dashboard</span>
            </a>

            <a href="student_submit.php" class="sidebar-link interactive">
                <span>Upload Assignment</span>
            </a>

            <a href="student_history.php" class="sidebar-link interactive">
                <span>Submission History</span>
            </a>

            <div class="menu-label">Account</div>
            <a href="auth/logout.php" class="sidebar-link interactive" style="color: #ef4444;">
                <span>Logout</span>
            </a>

            <!-- Added Joined Classes Section -->
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
                <div class="avatar"><?php echo strtoupper(substr($fullName, 0, 1)); ?></div>
                <div>
                    <div style="font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem; color: var(--text-dark);"><?php echo e($fullName); ?></div>
                    <div style="font-family: var(--font-mono); font-size: 0.65rem; color: var(--accent-main); text-transform: uppercase; font-weight: 700;">Role: <?php echo e($role); ?></div>
                </div>
            </div>
        </div>
    </aside>

    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- CONTENT WRAPPER -->
    <div class="content-wrapper">
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline interactive d-lg-none" id="sidebarToggle" style="padding: 0.4rem 0.8rem;"><i class="fa-solid fa-bars"></i></button>
                <h3 style="color: var(--accent-main);">Student Dashboard</h3>
            </div>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="student_submit.php" class="btn btn-primary interactive">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload
                </a>
            </div>
        </header>

        <main class="main-content">

            <!-- Hero Welcome Banner -->
            <div class="hero-banner">
                <div class="hero-content">
                    <div>
                        <h1 style="font-family: var(--font-head); font-size: 2.2rem; margin-bottom: 1rem; font-weight: 700; text-transform: uppercase;">Welcome, <?= e(explode(' ', $fullName)[0]) ?></h1>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <span class="sys-tag accent"><i class="fa-solid fa-barcode"></i> PRN: <?php echo e($studentZprn); ?></span>
                            <span class="sys-tag"><i class="fa-solid fa-id-card"></i> ROLL: <?php echo e($rollNo); ?></span>
                            <span class="sys-tag"><i class="fa-solid fa-layer-group"></i> DIV: <?php echo e($division); ?></span>
                            <span class="sys-tag"><i class="fa-solid fa-building-columns"></i> DEPT: <?php echo e($deptName); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- "Action Required" Priority Alert Banner -->
            <?php if (!empty($actionRequiredItems)): ?>
            <div class="module-card" style="border-color: #ef4444; border-width: 4px; padding: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem;">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <i class="fa-solid fa-triangle-exclamation" style="font-size: 2.5rem; color: #ef4444;"></i>
                        <div>
                            <h2 style="font-family: var(--font-head); font-size: 1.25rem; font-weight: 700; color: #ef4444; margin-bottom: 0.2rem; text-transform: uppercase;">Action Required: Pending Submissions</h2>
                            <p style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);">You have <strong><?= count($actionRequiredItems) ?> assignment(s)</strong> closing within the next 48 hours.</p>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                        <?php foreach (array_slice($actionRequiredItems, 0, 2) as $actReq): ?>
                            <button type="button" class="btn btn-danger interactive btn-quick-upload" 
                                    data-id="<?= $actReq['id'] ?>" 
                                    data-title="<?= e($actReq['title']) ?>" 
                                    data-subject="<?= e($actReq['subject_code']) ?>">
                                <i class="fa-solid fa-cloud-arrow-up"></i> UP: <?= e($actReq['subject_code']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Consolidated KPI Telemetry Grid -->
            <div class="telemetry-grid">
                <div class="tel-block interactive">
                    <div class="tel-label"><i class="fa-solid fa-clipboard-list me-1"></i> Total Activities</div>
                    <div class="tel-val"><?= $totalActivities ?></div>
                    <a href="student_submit.php" class="tel-link">VIEW ALL ></a>
                </div>
                
                <div class="tel-block interactive">
                    <div class="tel-label"><i class="fa-solid fa-hourglass-half me-1"></i> Pending Submit</div>
                    <div class="tel-val" style="color: #f59e0b;"><?= $totalPending ?></div>
                    <a href="student_submit.php" class="tel-link" style="color: #f59e0b;">ACTION REQ ></a>
                </div>
                
                <div class="tel-block interactive">
                    <div class="tel-label"><i class="fa-solid fa-circle-check me-1"></i> Evaluated</div>
                    <div class="tel-val" style="color: #10b981;"><?= $totalEvaluated ?></div>
                    <span class="tel-link" style="color: #10b981; text-decoration: none; cursor: default;">[<?= number_format((float)$totalEarnedMarks, 1) ?> PTS]</span>
                </div>
                
                <div class="tel-block interactive">
                    <div class="tel-label"><i class="fa-solid fa-circle-xmark me-1"></i> Missed</div>
                    <div class="tel-val" style="color: #ef4444;"><?= $totalMissed ?></div>
                    <span class="tel-link" style="color: #ef4444; text-decoration: none; cursor: default;">OVERDUE</span>
                </div>
                
                <div class="tel-block interactive">
                    <div class="tel-label"><i class="fa-solid fa-award me-1"></i> Standing</div>
                    <div class="tel-val" style="color: <?= $standingBadge['color'] ?>;"><?= $scorePercent ?>%</div>
                    <span class="tel-link" style="color: <?= $standingBadge['color'] ?>; text-decoration: none; cursor: default;">[<?= strtoupper($standingBadge['label']) ?>]</span>
                </div>
            </div>

            <!-- Upcoming Deadlines Chronological Checklist -->
            <?php if (!empty($upcomingDeadlines)): ?>
            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap;">
                    <div>
                        <h2 style="font-family: var(--font-head); font-size: 1.5rem; font-weight: 700; text-transform: uppercase;"><i class="fa-solid fa-list-check" style="color: var(--accent-main);"></i> Upcoming Deadlines</h2>
                        <span style="font-family: var(--font-mono); color: var(--text-tech); font-size: 0.85rem;">Chronological task queue</span>
                    </div>
                    <a href="student_submit.php" class="btn btn-outline interactive">Submit Queue ></a>
                </div>

                <div class="task-grid">
                    <?php foreach ($upcomingDeadlines as $item): ?>
                    <div class="task-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                            <span class="sys-tag accent"><?= e($item['subject_code']) ?> // U-<?= e($item['unit']) ?></span>
                            <span class="sys-tag <?= $item['countdown_class'] ?>" style="border-color: transparent; background: transparent; padding: 0; font-size: 0.75rem;">
                                <?= $item['countdown_label'] ?>
                            </span>
                        </div>
                        <strong style="font-family: var(--font-head); font-size: 1.1rem; text-transform: uppercase; margin-bottom: 0.5rem; display: block;"><?= e($item['title']) ?></strong>
                        <small style="font-family: var(--font-mono); color: var(--text-tech); font-size: 0.8rem; margin-bottom: 1.5rem; display: block;">T-MINUS: <?= fmtDate($item['due_date']) ?></small>
                        
                        <button type="button" class="btn btn-primary interactive btn-quick-upload" style="width: 100%;"
                                data-id="<?= $item['id'] ?>" 
                                data-title="<?= e($item['title']) ?>" 
                                data-subject="<?= e($item['subject_code']) ?>">
                            UPLOAD FILE
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Grade & Feedback Snapshot Grid Section -->
            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap;">
                    <div>
                        <h2 style="font-family: var(--font-head); font-size: 1.5rem; font-weight: 700; text-transform: uppercase;"><i class="fa-solid fa-square-poll-vertical" style="color: #10b981;"></i> Recent Evaluations</h2>
                        <span style="font-family: var(--font-mono); color: var(--text-tech); font-size: 0.85rem;">Latest graded assignments</span>
                    </div>
                    <span class="sys-tag" style="border-color: #10b981; color: #10b981; background: rgba(16,185,129,0.1);">
                        LOGS: <?= count($recentEvaluatedSnapshot) ?>
                    </span>
                </div>

                <?php if (empty($recentEvaluatedSnapshot)): ?>
                    <div style="text-align: center; padding: 3rem; border: 1px dashed var(--text-dark);">
                        <p style="font-family: var(--font-mono); font-size: 0.9rem; font-weight: 700;">SYS.MSG // NO EVALUATION DATA AVAILABLE YET.</p>
                    </div>
                <?php else: ?>
                    <div class="task-grid">
                        <?php foreach ($recentEvaluatedSnapshot as $snap): ?>
                        <div class="task-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                <span class="sys-tag accent"><?= e($snap['subject_code']) ?></span>
                                <strong style="font-family: var(--font-mono); font-size: 1.1rem; color: #10b981;"><?= number_format((float)$snap['marks'], 1) ?>/<?= number_format((float)$snap['max_marks'], 1) ?></strong>
                            </div>
                            <strong style="font-family: var(--font-head); font-size: 1rem; text-transform: uppercase; margin-bottom: 0.5rem; display: block;"><?= e($snap['title']) ?></strong>
                            <small style="font-family: var(--font-mono); color: var(--text-tech); font-size: 0.8rem; margin-bottom: 1.5rem; display: block;">STATUS: EVALUATED</small>
                            
                            <div style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-light); border-top: 1px solid var(--text-dark); padding-top: 1rem; margin-top: auto;">
                                [UP]: <?= fmtDate($snap['submission_date']) ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </main>

        <!-- Technical Footer -->
        <footer class="tech-footer">
            <div class="ft-top">
                <div>
                    <h2 style="font-family: var(--font-head); font-size: 1.5rem; color: var(--accent-glow); margin-bottom: 0.5rem; text-transform: uppercase;">SAAES.CORE</h2>
                    <p style="max-width: 300px; color: var(--text-light); font-size: 0.85rem;">Engineered for structural efficiency and evaluation integrity. Zeal College of Engineering.</p>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <a href="#" class="btn btn-outline interactive" style="border-color: var(--text-light); color: #fff; padding: 0.5rem 1rem; font-size: 0.75rem;">Support</a>
                    <a href="#" class="btn btn-outline interactive" style="border-color: var(--text-light); color: #fff; padding: 0.5rem 1rem; font-size: 0.75rem;">Privacy</a>
                </div>
            </div>
            <div class="ft-bottom">
                <span>(C) <?= date('Y') ?> ZCOER. ALL SYSTEMS NOMINAL.</span>
                <span style="color: var(--accent-glow);">v2.4.1 BUILD</span>
            </div>
        </footer>
    </div>
</div>

<!-- Fast Inline Quick Upload Modal -->
<div class="modal-overlay" id="quickUploadModalWrapper">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Init Assignment Upload</h3>
      <button class="close-btn interactive" id="closeUploadModal">&times;</button>
    </div>
    <form id="quickUploadForm" enctype="multipart/form-data">
      <div class="modal-body py-3">
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <input type="hidden" name="activity_id" id="modalActivityId" value="">

        <div style="padding: 1rem; background: var(--bg-base); border: 1px solid var(--text-dark); margin-bottom: 1.5rem;">
          <small style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-tech); display: block; margin-bottom: 0.5rem;">TARGET VECTOR:</small>
          <strong style="font-family: var(--font-head); font-size: 1.1rem; color: var(--accent-main); text-transform: uppercase;" id="modalSubjectTitle">Subject — Title</strong>
        </div>

        <div class="form-group">
          <label>Select Payload (PDF, JPG, PNG)</label>
          <input type="file" name="activity_file" id="modalFile" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required style="border-radius: 0;">
          <div style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-tech); margin-top: 0.5rem;">
            SYS.MSG // Max size: 5 MB.
          </div>
        </div>

        <div id="uploadAlert" class="alert d-none" style="margin-top: 1rem; margin-bottom: 0;"></div>
      </div>
      <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 2px solid var(--text-dark); padding-top: 1.5rem; margin-top: 1.5rem;">
        <button type="button" class="btn btn-outline interactive" id="cancelUploadModal">Abort</button>
        <button type="submit" class="btn btn-primary interactive" id="submitBtn">
          Transmit Payload
        </button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // 1. Rigid Custom Cursor
    const dot = document.getElementById("cursor-crosshair");
    const b_box = document.getElementById("cursor-brackets");
    
    if (window.matchMedia("(pointer: fine)").matches) {
        let mouseX = window.innerWidth / 2, mouseY = window.innerHeight / 2;
        let bracketX = mouseX, bracketY = mouseY;
        
        window.addEventListener("mousemove", (e) => {
            mouseX = e.clientX; mouseY = e.clientY;
            dot.style.transform = `translate(${mouseX}px, ${mouseY}px)`;
        });

        const renderCursor = () => {
            bracketX += (mouseX - bracketX) * 0.3;
            bracketY += (mouseY - bracketY) * 0.3;
            b_box.style.transform = `translate(${bracketX}px, ${bracketY}px)`;
            requestAnimationFrame(renderCursor);
        };
        requestAnimationFrame(renderCursor);

        const attachCursorHover = () => {
            document.querySelectorAll('.interactive, button, a, input, select, textarea').forEach(el => {
                el.addEventListener("mouseenter", () => document.body.classList.add("hovering"));
                el.addEventListener("mouseleave", () => document.body.classList.remove("hovering"));
            });
        };
        attachCursorHover();
        
        const observer = new MutationObserver(attachCursorHover);
        observer.observe(document.body, { childList: true, subtree: true });
    }

    // Sidebar Toggle (Mobile)
    const sidebarToggle = document.getElementById('sidebarToggle');
    const erpSidebar = document.getElementById('erpSidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    if (sidebarToggle && erpSidebar && sidebarBackdrop) {
      const toggleSidebar = () => {
        erpSidebar.classList.toggle('show');
        sidebarBackdrop.classList.toggle('show');
      };
      sidebarToggle.addEventListener('click', toggleSidebar);
      sidebarBackdrop.addEventListener('click', toggleSidebar);
    }

    // Inline Quick Upload Modal Handling
    const uploadModalWrapper = document.getElementById('quickUploadModalWrapper');
    const quickUploadForm = document.getElementById('quickUploadForm');
    const modalAlert = document.getElementById('uploadAlert');
    const submitBtn = document.getElementById('submitBtn');

    const openUploadModal = () => { uploadModalWrapper.style.display = 'flex'; };
    const closeUploadModal = () => { uploadModalWrapper.style.display = 'none'; };

    document.getElementById('closeUploadModal').addEventListener('click', (e) => { e.preventDefault(); closeUploadModal(); });
    document.getElementById('cancelUploadModal').addEventListener('click', (e) => { e.preventDefault(); closeUploadModal(); });
    uploadModalWrapper.addEventListener('click', (e) => { if(e.target === uploadModalWrapper) closeUploadModal(); });

    document.querySelectorAll('.btn-quick-upload').forEach(btn => {
      if (btn.tagName.toLowerCase() === 'a') return;

      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        const title = btn.getAttribute('data-title');
        const subject = btn.getAttribute('data-subject');
        
        document.getElementById('modalActivityId').value = id;
        document.getElementById('modalSubjectTitle').textContent = `[${subject}] ${title}`;
        modalAlert.className = 'alert d-none';
        quickUploadForm.reset();
        openUploadModal();
      });
    });

    quickUploadForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      modalAlert.className = 'alert d-none';
      submitBtn.disabled = true;
      submitBtn.innerHTML = 'TRANSMITTING...';

      const formData = new FormData(quickUploadForm);
      try {
        const res = await fetch('student_submit.php?action=upload', {
          method: 'POST',
          body: formData
        });
        const data = await res.json();
        if (data.success) {
          modalAlert.className = 'alert alert-success d-flex';
          modalAlert.textContent = data.message || 'Payload transmitted successfully!';
          setTimeout(() => { location.reload(); }, 1000);
        } else {
          modalAlert.className = 'alert alert-danger d-flex';
          modalAlert.textContent = 'ERR // ' + (data.message || 'Transmission failed.');
          submitBtn.disabled = false;
          submitBtn.innerHTML = 'Transmit Payload';
        }
      } catch (err) {
        modalAlert.className = 'alert alert-danger d-flex';
        modalAlert.textContent = 'ERR // Network failure detected.';
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Transmit Payload';
      }
    });
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