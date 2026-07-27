<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Anti-caching headers to prevent browser back-button access after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/config/db.php';

// 1. Security Check: Are they logged in?
if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$role = strtolower($_SESSION['role'] ?? '');
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// 2. Role Routing: Allow faculty/admin/HOD/GFM strictly for 'preview' or 'download' actions
if ($role !== 'student') {
    if (!in_array($role, ['faculty', 'hod', 'gfm', 'admin'], true) || !in_array($action, ['preview', 'download'], true)) {
        header('Location: auth/login.php');
        exit;
    }
}

// 3. HARDENED STUDENT IDENTITY RESOLUTION (Prevents student_id = 0 bug)
$studentUserId = (int) $_SESSION['user_id'];
$studentTableId = 0;
$studentPrn = '';
$linkedPrn = '';
$studentName = 'Student';
$rollNo = '-';
$division = '-';

// Always fetch up-to-date user PRN & info from DB
$stmtU = $pdo->prepare("SELECT username, name, linked_student_prn, department, academic_year, division FROM users WHERE user_id = ? LIMIT 1");
$stmtU->execute([$studentUserId]);
$uRow = $stmtU->fetch(PDO::FETCH_ASSOC);

if ($uRow) {
    $studentPrn = $uRow['username'] ?? '';
    $linkedPrn  = $uRow['linked_student_prn'] ?? '';
    $studentDept = $uRow['department'] ?? '';
    $studentYear = $uRow['academic_year'] ?? 'FY';
    $studentDiv  = $uRow['division'] ?? '';
    $studentName = !empty($_SESSION['full_name']) ? $_SESSION['full_name'] : ($uRow['name'] ?? 'Student');
    $_SESSION['full_name'] = $studentName;
}

if ($role === 'student') {
    // Actively lookup student_id from students table
    $stmtSt = $pdo->prepare("SELECT student_id, roll_no, division FROM students WHERE user_id = ? LIMIT 1");
    $stmtSt->execute([$studentUserId]);
    $stRow = $stmtSt->fetch(PDO::FETCH_ASSOC);

    if ($stRow && !empty($stRow['student_id'])) {
        $studentTableId = (int)$stRow['student_id'];
        $rollNo   = $stRow['roll_no']  ?: ($_SESSION['roll_no'] ?? '-');
        $division = $stRow['division']  ?: ($_SESSION['division'] ?? '-');
    } else {
        // Fallback: Use user_id so student_id in submissions is NEVER 0
        $studentTableId = $studentUserId;
        $rollNo   = $_SESSION['roll_no']   ?? '-';
        $division = $_SESSION['division']  ?? '-';
    }
    $_SESSION['student_id'] = $studentTableId;
}

$studentCode = $_SESSION['student_code'] ?? ('STU' . $studentTableId);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];
const MAX_SIZE     = 5 * 1024 * 1024; // 5 MB
$UPLOAD_ROOT = __DIR__ . '/uploads/';

function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function fmtDate($d) { return $d ? (new DateTime($d))->format('d M Y') : '—'; }
function fmtDateTime($d) { return $d ? (new DateTime($d))->format('d M Y, h:i A') : '—'; }
function jsAttr($v) { return htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8'); }

function badgeClass($status) {
    return [
        'Submission Closed' => 'danger',
        'Missed'        => 'danger',
        'Pending'       => 'warning',
        'Late'          => 'warning',
        'Submitted'     => 'info',
        'Approved'      => 'success',
        'Rejected'      => 'danger',
        'Evaluated'     => 'success',
    ][$status] ?? 'accent';
}

function displayStatus(array $row): string {
    if (!empty($row['submission_id'])) return $row['sub_status'];
    $due = new DateTime($row['due_date']);
    $now = new DateTime();
    if ($now > $due) return 'Submission Closed';
    return 'Pending';
}

function calculateAutomaticMarks($dueDate, $submissionDate, $maxMarks) {
    if (!$dueDate || !$submissionDate) return null;
    $due = (new DateTime($dueDate))->setTime(23, 59, 59);
    $sub = new DateTime($submissionDate);
    
    $dueDay = (clone $due)->setTime(0,0,0);
    $subDay = (clone $sub)->setTime(0,0,0);
    $diffDays = $dueDay->diff($subDay)->days;
    $isLate = ($sub > $due);
    $diffDays = $isLate ? $diffDays : 0;
    
    if (!$isLate) return $maxMarks;
    if ($diffDays == 0 || $diffDays == 1) return max(0, $maxMarks - 1);
    if ($diffDays == 2) return max(0, $maxMarks - 2);
    return 0;
}

// ---------------------------------------------------------------------------------
// UPLOAD HANDLER (Student Upload)
// ---------------------------------------------------------------------------------
if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST' && $role === 'student') {
    header('Content-Type: application/json');
    try {
        if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
            throw new Exception('Your session expired. Please refresh the page and try again.');
        }
        $activityId = (int) ($_POST['activity_id'] ?? 0);
        if (!$activityId) throw new Exception('Invalid activity.');

        // Verify Student Assignment
        $stmt = $pdo->prepare("
            SELECT * FROM activities 
            WHERE activity_id = ? 
              AND (target_type = 'all' 
                   OR (target_type = 'individual' AND (target_id = ? OR target_id = ?)) 
                   OR (target_type = 'group' AND target_id IN (SELECT group_id FROM group_members WHERE student_id = ? OR student_id = ?))
                   OR (target_type = 'class' AND target_id IN (SELECT fc.class_id FROM faculty_classes fc LEFT JOIN faculty_class_students fcs ON fcs.class_id = fc.class_id WHERE (fc.department = ? AND fc.academic_year = ? AND fc.division = ?) OR (fcs.student_prn = ? OR fcs.student_prn = ?))))
        ");
        $stmt->execute([$activityId, $studentTableId, $studentUserId, $studentTableId, $studentUserId, $studentDept, $studentYear, $studentDiv, $studentPrn, $linkedPrn]);
        $activity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$activity) throw new Exception('Activity not found or not assigned to you.');

        $now = new DateTime();
        $due = new DateTime($activity['due_date']);

        if (empty($_FILES['activity_file']) || $_FILES['activity_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please choose a valid file to upload.');
        }
        $file = $_FILES['activity_file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ALLOWED_EXT, true)) {
            throw new Exception('Only PDF, JPG, and PNG files are allowed.');
        }

        $fileSize = (int) ($file['size'] ?? 0);
        if ($fileSize > MAX_SIZE) {
            throw new Exception('File exceeds the 5 MB size limit.');
        }

        // Check for Existing Submissions
        $stmt = $pdo->prepare("SELECT * FROM submissions WHERE activity_id = ? AND (student_id = ? OR student_id = ?)");
        $stmt->execute([$activityId, $studentTableId, $studentUserId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $studentDir = $UPLOAD_ROOT . $studentTableId . '/';
        if (!is_dir($studentDir) && !mkdir($studentDir, 0755, true) && !is_dir($studentDir)) {
            throw new Exception('Server could not prepare the upload folder.');
        }

        $storedName = 'act' . $activityId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath   = $studentDir . $storedName;
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new Exception('Failed to save the uploaded file on the server.');
        }

        $isLate = ($due < $now) ? 1 : 0;
        $submissionDateStr = (new DateTime())->format('Y-m-d H:i:s');
        $calcMarks = calculateAutomaticMarks($activity['due_date'], $submissionDateStr, $activity['max_marks']);

        if ($existing) {
            if (!empty($existing['file_path']) && is_file($existing['file_path'])) {
                @unlink($existing['file_path']);
            }

            $stmt = $pdo->prepare(
                "UPDATE submissions SET
                    student_id = ?, original_filename = ?, file_path = ?, file_type = ?, file_size = ?, submission_date = ?, is_late = ?, marks = ?, status = 'Submitted'
                 WHERE id = ?"
            );
            $stmt->execute([$studentTableId, $file['name'], $destPath, $ext, $fileSize, $submissionDateStr, $isLate, $calcMarks, $existing['id']]);
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO submissions
                    (activity_id, student_id, original_filename, file_path, file_type, file_size, submission_date, is_late, marks, status)
                 VALUES (?,?,?,?,?,?,?,?,?,'Submitted')"
            );
            $stmt->execute([$activityId, $studentTableId, $file['name'], $destPath, $ext, $fileSize, $submissionDateStr, $isLate, $calcMarks]);
        }

        echo json_encode(['success' => true, 'message' => 'Activity uploaded successfully.', 'late' => (bool) $isLate]);
    } catch (Exception $ex) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
    }
    exit;
}

// ---------------------------------------------------------------------------------
// DOWNLOAD / PREVIEW HANDLER (Faculty, HOD, GFM, Admin & Students)
// ---------------------------------------------------------------------------------
if ($action === 'preview' || $action === 'download') {
    $subId = (int) ($_GET['id'] ?? 0);
    if (in_array(strtolower($_SESSION['role'] ?? ''), ['faculty', 'admin', 'gfm', 'hod'], true)) {
        $stmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ?");
        $stmt->execute([$subId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ? AND (student_id = ? OR student_id = ?)");
        $stmt->execute([$subId, $studentTableId, $studentUserId]);
    }
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sub) { http_response_code(404); exit('File not found or access denied.'); }

    // Multi-fallback File Path Resolver
    $fullPath = '';
    if (!empty($sub['file_path']) && is_file($sub['file_path'])) {
        $fullPath = $sub['file_path'];
    } else {
        $fullPath = $UPLOAD_ROOT . $sub['student_id'] . '/' . ($sub['original_filename'] ?? '');
    }

    if (!is_file($fullPath)) { http_response_code(404); exit('File is missing on the server.'); }

    $mimeMap = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
    $fileExt = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    if (empty($fileExt)) $fileExt = strtolower($sub['file_type'] ?? '');
    $mime = $mimeMap[$fileExt] ?? 'application/octet-stream';

    $displayFileName = !empty($sub['original_filename']) ? basename($sub['original_filename']) : basename($fullPath);

    header('Content-Type: ' . $mime);
    header('Content-Disposition: ' . ($action === 'download' ? 'attachment' : 'inline') . '; filename="' . $displayFileName . '"');
    header('Content-Length: ' . filesize($fullPath));
    header('X-Content-Type-Options: nosniff');
    readfile($fullPath);
    exit;
}

// Fetch joined classes for the student
$stmtClasses = $pdo->prepare("
    SELECT fc.class_id, fc.class_name, fc.subject_code, fc.description, u.name AS faculty_name
    FROM faculty_classes fc
    LEFT JOIN users u ON fc.faculty_id = u.user_id
    WHERE fc.department = ? AND fc.academic_year = ? AND fc.division = ?
    ORDER BY fc.created_at DESC
");
$stmtClasses->execute([$studentDept, $studentYear, $studentDiv]);
$myClasses = $stmtClasses->fetchAll(PDO::FETCH_ASSOC) ?: [];

// Fetch activities targeted at "All", "Individual", "Group", or assigned "Class"
$subjectContextSQL = "
    a.target_type = 'all' 
    OR (a.target_type = 'individual' AND (a.target_id = ? OR a.target_id = ?))
    OR (a.target_type = 'group' AND a.target_id IN (SELECT group_id FROM group_members WHERE student_id = ? OR student_id = ?))
    OR (a.target_type = 'class' AND a.target_id IN (
        SELECT class_id FROM faculty_classes WHERE department = ? AND academic_year = ? AND division = ?
    ))
";
$queryParams = [$studentTableId, $studentUserId, $studentTableId, $studentUserId, $studentDept, $studentYear, $studentDiv];

$stmt = $pdo->prepare(
    "SELECT a.activity_id AS id, a.subject AS subject_code, a.unit, a.title, a.due_date, a.max_marks, a.target_type, a.target_id,
            fc.class_name,
            s.id AS submission_id, s.original_filename, s.submission_date, s.status AS sub_status,
            s.marks, s.file_type, s.remarks
     FROM activities a
     LEFT JOIN faculty_classes fc ON a.target_type = 'class' AND a.target_id = fc.class_id
     LEFT JOIN submissions s ON s.activity_id = a.activity_id AND (s.student_id = ? OR s.student_id = ?)
     WHERE $subjectContextSQL
     ORDER BY a.due_date ASC"
);
$stmt->execute(array_merge([$studentTableId, $studentUserId], $queryParams));
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

$subjects = [];
foreach ($activities as $a) if (!empty($a['subject_code'])) $subjects[$a['subject_code']] = true;
$subjects = array_keys($subjects);

$units = [];
foreach ($activities as $a) if (!empty($a['unit'])) $units[$a['unit']] = true;
$units = array_keys($units);

$totalActivities = count($activities);

$recent = array_values(array_filter($activities, fn($a) => !empty($a['submission_id'])));
usort($recent, fn($x, $y) => strtotime($y['submission_date']) <=> strtotime($x['submission_date']));
$recent = array_slice($recent, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Activity | SAAES</title>
  
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

/* ================= STUDENT INFO GRID ================= */
.info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
.info-block { border: 1px solid var(--text-light); padding: 1rem; background: var(--bg-base); }
.info-label { font-family: var(--font-mono); font-size: 0.7rem; color: var(--text-tech); text-transform: uppercase; margin-bottom: 0.2rem; display: block;}
.info-value { font-family: var(--font-head); font-weight: 700; color: var(--text-dark); font-size: 1.1rem; text-transform: uppercase;}

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
            <a href="student_submit.php" class="sidebar-link interactive active">
                <span>Upload Assignment</span>
            </a>
            <a href="student_history.php" class="sidebar-link interactive">
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
                <h3 style="color: var(--accent-main);">Submit Activity</h3>
            </div>
        </header>

        <main class="main-content">

            <div class="alert alert-info" style="margin-bottom: 2rem;">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Submission Info:</strong> Upload your activity files before the due date. Accepted formats: PDF, JPG, PNG (max 5 MB).
                </div>
            </div>

            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2 style="font-family: var(--font-head); font-size: 1.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-dark); margin-bottom: 0.2rem;">Student Activities</h2>
                        <p style="color: var(--text-tech); font-family: var(--font-mono); font-size: 0.85rem; margin: 0;">Upload, track and manage your unit-wise activity submissions.</p>
                    </div>
                    <span class="sys-tag accent">
                        <?= $totalActivities ?> Activities &middot; <?= count($subjects) ?> Subjects
                    </span>
                </div>

                <!-- Student Details Grid -->
                <div class="info-grid interactive">
                    <div class="info-block">
                        <span class="info-label">Student PRN</span>
                        <span class="info-value"><?= e($studentPrn ?: $studentCode) ?></span>
                    </div>
                    <div class="info-block">
                        <span class="info-label">Student Name</span>
                        <span class="info-value"><?= e($studentName) ?></span>
                    </div>
                    <div class="info-block">
                        <span class="info-label">Roll Number</span>
                        <span class="info-value"><?= e($rollNo) ?></span>
                    </div>
                    <div class="info-block">
                        <span class="info-label">Division</span>
                        <span class="info-value"><?= e($division) ?></span>
                    </div>
                    <div class="info-block">
                        <span class="info-label">Today's Date</span>
                        <span class="info-value"><?= (new DateTime())->format('d M Y') ?></span>
                    </div>
                </div>

                <!-- Control Toolbar & Filters -->
                <h3 style="font-family: var(--font-head); font-size: 1.2rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1rem;">Activities Upload</h3>
                <div class="filter-card interactive">
                    <div style="flex: 2; min-width: 200px;">
                        <input type="text" id="searchInput" class="form-control-custom" placeholder="Search by activity title...">
                    </div>
                    <div style="flex: 1; min-width: 140px;">
                        <select id="subjectFilter" class="form-select-custom">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $s): ?><option><?= e($s) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 140px;">
                        <select id="unitFilter" class="form-select-custom">
                            <option value="">All Units</option>
                            <?php foreach ($units as $u): ?><option>Unit <?= e($u) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 140px;">
                        <select id="statusFilter" class="form-select-custom">
                            <option value="">All Status</option>
                            <option>Pending</option><option>Submission Closed</option><option>Submitted</option>
                        </select>
                    </div>
                </div>

                <!-- Submission Table -->
                <div class="table-responsive interactive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Subject</th>
                                <th>Unit</th>
                                <th>Activity Title</th>
                                <th>Due Date</th>
                                <th>Sub. Date</th>
                                <th>File</th>
                                <th>Marks</th>
                                <th>Status</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="activitiesTableBody">
                            <?php if (!$activities): ?>
                                <tr class="js-empty"><td colspan="10" style="text-align: center; padding: 3rem; color: var(--text-tech); font-family: var(--font-mono); font-weight: 700;">No activities have been assigned yet.</td></tr>
                            <?php endif; ?>
                            
                            <?php foreach ($activities as $a):
                                $status = displayStatus($a);
                                $hasSub = !empty($a['submission_id']);
                                $due = new DateTime($a['due_date']);
                            ?>
                            <tr data-subject="<?= e($a['subject_code']) ?>" data-unit="Unit <?= e($a['unit']) ?>" data-status="<?= e($status) ?>" data-title="<?= e(mb_strtolower($a['title'])) ?>">
                                <td style="font-family: var(--font-mono); font-weight: 700; color: var(--text-light);">#<?= str_pad($a['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                <td><span class="sys-tag" style="margin:0;"><?= e($a['subject_code']) ?></span></td>
                                <td><span style="font-family: var(--font-mono); font-weight: 700; font-size: 0.8rem; color: var(--text-tech);">Unit <?= e($a['unit']) ?></span></td>
                                <td>
                                    <strong style="font-family: var(--font-head); font-size: 1rem; text-transform: uppercase; display: block; margin-bottom: 0.2rem;"><?= e($a['title']) ?></strong>
                                    <?php if (!empty($a['target_type']) && $a['target_type'] === 'class' && !empty($a['class_name'])): ?>
                                        <span class="sys-tag info" style="font-size: 0.65rem; margin:0;">Class: <?= e($a['class_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-tech);"><?= fmtDate($a['due_date']) ?></td>
                                <td style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-dark); font-weight: 700;"><?= $hasSub ? fmtDateTime($a['submission_date']) : '—' ?></td>
                                <td style="font-family: var(--font-mono); font-size: 0.8rem;">
                                    <?= $hasSub ? '<span style="color: var(--accent-main); font-weight: 700; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block;" title="'.e($a['original_filename']).'">' . e($a['original_filename']) . '</span>' : '<span style="color: var(--text-light);">Not uploaded</span>' ?>
                                </td>
                                <td>
                                    <?php if ($hasSub && $a['marks'] !== null): ?>
                                        <strong style="font-family: var(--font-mono); font-size: 1.1rem; color: #10b981;"><?= e($a['marks']) ?></strong> <span style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-tech);">/ <?= e($a['max_marks']) ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-light);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="sys-tag <?= badgeClass($status) ?>" style="margin:0;"><?= e($status) ?></span></td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 0.4rem; justify-content: center;">
                                        <?php if (!$hasSub): ?>
                                            <button class="btn-tech primary interactive" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;" onclick='openUploadModal(<?= (int)$a['id'] ?>, <?= jsAttr($a['subject_code']) ?>, <?= jsAttr("Unit " . $a['unit']) ?>, <?= jsAttr($a['title']) ?>, <?= jsAttr(fmtDate($a['due_date'])) ?>)'>
                                                Upload
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-outline interactive" style="padding: 0.4rem 0.6rem; font-size: 0.75rem;" title="View File" onclick='openPreview(<?= (int)$a['submission_id'] ?>, <?= jsAttr($a['file_type']) ?>, <?= jsAttr($a['original_filename']) ?>)'>View</button>
                                            <a class="btn-tech primary interactive" style="padding: 0.4rem 0.6rem; font-size: 0.75rem;" title="Download File" href="student_submit.php?action=download&id=<?= (int)$a['submission_id'] ?>">DL</a>
                                            <button class="btn-outline interactive" style="padding: 0.4rem 0.6rem; font-size: 0.75rem; color: #f59e0b; border-color: #f59e0b;" title="Replace File" onclick='openUploadModal(<?= (int)$a['id'] ?>, <?= jsAttr($a['subject_code']) ?>, <?= jsAttr("Unit " . $a['unit']) ?>, <?= jsAttr($a['title']) ?>, <?= jsAttr(fmtDate($a['due_date'])) ?>)'>Swap</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-bottom: 2px solid var(--text-dark); padding-bottom: 2rem; margin-bottom: 2rem;">
                    <div style="font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; color: var(--text-tech); text-transform: uppercase;" id="resultsSummary">Showing activities</div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination" id="activitiesPagination"></ul>
                    </nav>
                </div>

                <!-- Recent Uploads Section -->
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h3 style="font-family: var(--font-head); font-size: 1.2rem; font-weight: 700; text-transform: uppercase;">Recent Uploads</h3>
                    <a href="student_history.php" class="btn-outline interactive" style="font-size: 0.75rem; text-decoration: none;">View Full History</a>
                </div>

                <div class="table-responsive interactive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Activity Title</th>
                                <th>Subject</th>
                                <th>Uploaded File</th>
                                <th>Submission Date</th>
                                <th>Marks</th>
                                <th>Status</th>
                                <th style="text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$recent): ?>
                                <tr><td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-tech); font-family: var(--font-mono); font-weight: 700;">You haven't uploaded anything yet.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($recent as $a): ?>
                                <tr>
                                    <td>
                                        <strong style="font-family: var(--font-head); font-size: 1rem; text-transform: uppercase; display: block; margin-bottom: 0.2rem;"><?= e($a['title']) ?></strong>
                                        <span class="sys-tag accent" style="font-size: 0.65rem; margin:0;">Unit <?= e($a['unit']) ?></span>
                                    </td>
                                    <td><strong style="font-family: var(--font-mono); color: var(--text-dark); text-transform: uppercase;"><?= e($a['subject_code']) ?></strong></td>
                                    <td>
                                        <div style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--accent-main); font-weight: 700; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($a['original_filename']) ?>">
                                            <?= e($a['original_filename']) ?>
                                        </div>
                                    </td>
                                    <td style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-dark); font-weight: 700;"><?= fmtDateTime($a['submission_date']) ?></td>
                                    <td>
                                        <?php if ($a['marks'] !== null): ?>
                                            <strong style="font-family: var(--font-mono); font-size: 1.1rem; color: #10b981;"><?= e($a['marks']) ?></strong> <span style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-tech);">/ <?= e($a['max_marks']) ?></span>
                                        <?php else: ?>
                                            <span style="color: var(--text-light);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="sys-tag <?= badgeClass($a['sub_status']) ?>" style="margin:0;"><?= e($a['sub_status']) ?></span></td>
                                    <td style="text-align: center;">
                                        <div style="display: flex; gap: 0.4rem; justify-content: center;">
                                            <button class="btn-outline interactive" style="padding: 0.4rem 0.6rem; font-size: 0.75rem;" onclick='openPreview(<?= (int)$a['submission_id'] ?>, <?= jsAttr($a['file_type']) ?>, <?= jsAttr($a['original_filename']) ?>)'>View</button>
                                            <a class="btn-tech primary interactive" style="padding: 0.4rem 0.6rem; font-size: 0.75rem; margin: 0;" href="student_submit.php?action=download&id=<?= (int)$a['submission_id'] ?>">DL</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>
</div>

<!-- Upload Activity Modal -->
<div class="modal-overlay" id="uploadModalWrapper">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Upload Activity</h3>
            <button class="close-btn interactive" id="closeUploadModal">&times;</button>
        </div>
        <form id="uploadForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="activity_id" id="mActivityId">
            
            <div id="uploadAlert" class="alert d-none" style="margin-bottom: 1.5rem;"></div>

            <div class="info-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 1.5rem;">
                <div class="info-block">
                    <span class="info-label">Student PRN</span>
                    <span class="info-value" style="font-size: 0.9rem;"><?= e($studentPrn ?: $studentCode) ?></span>
                </div>
                <div class="info-block">
                    <span class="info-label">Subject & Unit</span>
                    <span class="info-value" style="font-size: 0.9rem; color: var(--accent-main);"><span id="mSubject"></span> - <span id="mUnit"></span></span>
                </div>
                <div class="info-block" style="grid-column: span 2;">
                    <span class="info-label">Activity Title</span>
                    <span class="info-value" style="font-size: 0.9rem;" id="mTitle"></span>
                </div>
            </div>

            <div class="form-group">
                <label>Choose File (PDF, JPG, PNG)</label>
                <input type="file" class="form-control-custom interactive" id="fileInput" name="activity_file" accept=".pdf,.jpg,.jpeg,.png">
                <div style="font-family: var(--font-mono); font-size: 0.75rem; font-weight: 700; color: var(--text-tech); margin-top: 0.5rem;" id="fileHelp">Max size: 5 MB.</div>
            </div>
            
            <!-- Custom Progress Bar -->
            <div id="progressWrap" class="d-none" style="width: 100%; height: 10px; border: 1px solid var(--text-dark); background: var(--bg-base); margin-bottom: 1.5rem; overflow: hidden;">
                <div id="progressBar" style="width: 0%; height: 100%; background: var(--accent-main); transition: width 0.3s;"></div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 2px solid var(--text-dark); padding-top: 1.5rem;">
                <button type="button" class="btn-outline interactive" id="cancelUploadModal">Cancel</button>
                <button type="submit" class="btn-tech primary interactive" id="uploadBtn">
                    Upload Payload
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Preview Modal -->
<div class="modal-overlay" id="previewModalWrapper">
    <div class="modal-content" style="max-width: 800px;">
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
    const tbody = document.getElementById('activitiesTableBody');
    const allRows = Array.from(tbody.querySelectorAll('tr[data-subject]'));
    const pager = document.getElementById('activitiesPagination');
    const summary = document.getElementById('resultsSummary');
    const searchInput = document.getElementById('searchInput');
    const subjectFilter = document.getElementById('subjectFilter');
    const unitFilter = document.getElementById('unitFilter');
    const statusFilter = document.getElementById('statusFilter');
    const perPage = 8;
    let page = 1;

    function filteredRows() {
        const q = searchInput.value.toLowerCase();
        const subj = subjectFilter.value;
        const unit = unitFilter.value;
        const status = statusFilter.value;
        return allRows.filter(r =>
            (!q || r.dataset.title.includes(q)) &&
            (!subj || r.dataset.subject === subj) &&
            (!unit || r.dataset.unit === unit) &&
            (!status || r.dataset.status === status)
        );
    }

    function renderTable() {
        if (!allRows.length) return;
        const rows = filteredRows();
        const totalPages = Math.max(1, Math.ceil(rows.length / perPage));
        page = Math.min(page, totalPages);

        allRows.forEach(r => r.style.display = 'none');
        const slice = rows.slice((page - 1) * perPage, page * perPage);
        slice.forEach(r => r.style.display = '');

        let emptyRow = tbody.querySelector('tr.js-empty');
        if (!rows.length) {
            if (!emptyRow) {
                emptyRow = document.createElement('tr');
                emptyRow.className = 'js-empty';
                emptyRow.innerHTML = `<td colspan="10" style="text-align: center; padding: 3rem; color: var(--text-tech); font-family: var(--font-mono); font-weight: 700;">No activities match your filters.</td>`;
                tbody.appendChild(emptyRow);
            }
            emptyRow.style.display = '';
        } else if (emptyRow) {
            emptyRow.style.display = 'none';
        }

        pager.innerHTML = '';
        if (totalPages > 1) {
            let html = `<li class="page-item ${page===1?'disabled':'interactive'}"><a class="page-link" href="#" data-p="${page-1}">PREV</a></li>`;
            for (let p = 1; p <= totalPages; p++) html += `<li class="page-item ${p===page?'active':'interactive'}"><a class="page-link" href="#" data-p="${p}">${p}</a></li>`;
            html += `<li class="page-item ${page===totalPages?'disabled':'interactive'}"><a class="page-link" href="#" data-p="${page+1}">NEXT</a></li>`;
            pager.innerHTML = html;
            attachCursorHover(); // Reattach cursor to new DOM elements
        }
        const start = rows.length ? (page - 1) * perPage + 1 : 0;
        const end = Math.min(page * perPage, rows.length);
        summary.textContent = `Showing ${start}-${end} of ${rows.length} activities`;
    }

    pager.addEventListener('click', e => {
        e.preventDefault();
        const t = e.target.closest('[data-p]');
        if (!t || t.parentElement.classList.contains('disabled')) return;
        page = parseInt(t.dataset.p, 10);
        renderTable();
    });

    searchInput.addEventListener('input', () => { page = 1; renderTable(); });
    ['subjectFilter', 'unitFilter', 'statusFilter'].forEach(id =>
        document.getElementById(id).addEventListener('change', () => { page = 1; renderTable(); })
    );
    renderTable();

    // 3. Upload Modal Logic
    const uploadModalWrapper = document.getElementById('uploadModalWrapper');
    const uploadForm = document.getElementById('uploadForm');
    const uploadAlert = document.getElementById('uploadAlert');
    const fileHelp = document.getElementById('fileHelp');
    const progressWrap = document.getElementById('progressWrap');
    const progressBar = document.getElementById('progressBar');
    const uploadBtn = document.getElementById('uploadBtn');

    function closeUploadModalFunc() { uploadModalWrapper.style.display = 'none'; }
    document.getElementById('closeUploadModal').addEventListener('click', (e) => { e.preventDefault(); closeUploadModalFunc(); });
    document.getElementById('cancelUploadModal').addEventListener('click', (e) => { e.preventDefault(); closeUploadModalFunc(); });
    uploadModalWrapper.addEventListener('click', (e) => { if(e.target === uploadModalWrapper) closeUploadModalFunc(); });

    window.openUploadModal = function(activityId, subject, unit, title, dueDate) {
        uploadForm.reset();
        document.getElementById('mActivityId').value = activityId;
        document.getElementById('mSubject').textContent = subject;
        document.getElementById('mUnit').textContent = unit;
        document.getElementById('mDueDate').value = dueDate;
        document.getElementById('mTitle').textContent = title;
        fileHelp.textContent = 'Max size: 5 MB.';
        uploadAlert.className = 'alert d-none';
        progressWrap.classList.add('d-none');
        progressBar.style.width = '0%';
        uploadModalWrapper.style.display = 'flex';
    };

    document.getElementById('fileInput').addEventListener('change', function () {
        const f = this.files[0];
        if (!f) { fileHelp.textContent = 'Max size: 5 MB.'; return; }
        const ext = f.name.split('.').pop().toLowerCase();
        if (!['pdf','jpg','jpeg','png'].includes(ext)) { fileHelp.innerHTML = '<span style="color: #ef4444;">Only PDF, JPG, PNG files are allowed.</span>'; this.value=''; return; }
        if (f.size > 5*1024*1024) { fileHelp.innerHTML = '<span style="color: #ef4444;">File exceeds the 5 MB size limit.</span>'; this.value=''; return; }
        fileHelp.innerHTML = `<span style="color: #10b981;">${f.name} (${(f.size/1024).toFixed(0)} KB) — Ready.</span>`;
    });

    uploadForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const fileInput = document.getElementById('fileInput');
        if (!fileInput.files.length) { showAlert('Please choose a file to upload.', 'danger'); return; }

        uploadBtn.disabled = true; uploadBtn.innerHTML = 'UPLOADING...';
        progressWrap.classList.remove('d-none'); progressBar.style.width = '10%';

        const formData = new FormData(this);

        fetch('student_submit.php?action=upload', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                progressBar.style.width = '100%';
                if (data.success) {
                    showAlert(data.message, 'success');
                    uploadBtn.disabled = false; uploadBtn.innerHTML = 'Upload Payload';
                    setTimeout(() => { closeUploadModalFunc(); location.reload(); }, 1000);
                } else {
                    showAlert(data.message || 'Upload failed.', 'danger');
                    uploadBtn.disabled = false; uploadBtn.innerHTML = 'Upload Payload';
                }
            })
            .catch(() => {
                showAlert('A network error occurred. Please try again.', 'danger');
                uploadBtn.disabled = false; uploadBtn.innerHTML = 'Upload Payload';
            });
    });

    function showAlert(msg, type) {
        uploadAlert.className = `alert alert-${type}`;
        uploadAlert.innerHTML = `<i class="fa-solid fa-circle-exclamation"></i> ` + msg;
        uploadAlert.classList.remove('d-none');
    }

    // 4. Custom Modal Preview Logic
    const previewModalWrapper = document.getElementById('previewModalWrapper');
    
    function closePreviewModalFunc() {
        previewModalWrapper.style.display = 'none';
        document.getElementById('previewFrame').src = '';
        document.getElementById('previewImg').src = '';
    }

    document.getElementById('closePreviewModal').addEventListener('click', closePreviewModalFunc);
    document.getElementById('cancelPreviewModal').addEventListener('click', closePreviewModalFunc);
    previewModalWrapper.addEventListener('click', (e) => {
        if(e.target === previewModalWrapper) closePreviewModalFunc();
    });

    window.openPreview = function(submissionId, fileType, fileName) {
        const frame = document.getElementById('previewFrame');
        const img = document.getElementById('previewImg');
        const unsupported = document.getElementById('previewUnsupported');
        const title = document.getElementById('previewTitle');
        const dlBtn = document.getElementById('previewDownloadBtn');

        title.innerHTML = fileName;
        dlBtn.href = `student_submit.php?action=download&id=${submissionId}`;

        frame.classList.add('d-none');
        img.classList.add('d-none');
        unsupported.classList.add('d-none');
        frame.src = '';
        img.src = '';

        const previewUrl = `student_submit.php?action=preview&id=${submissionId}`;
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
    };

    // Mobile Sidebar Toggle
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
// Safely include modal without fatal error
$modalPath = __DIR__ . '/includes/end_session_modal.php';
if (file_exists($modalPath)) {
    include_once $modalPath;
}
?>
</body>
</html>