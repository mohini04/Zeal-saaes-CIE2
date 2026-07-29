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
if (empty($_SESSION['user_id']) || !in_array($role, ['parent', 'admin', 'student', 'faculty', 'hod', 'gfm'])) {
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
function jsAttr($v) { return htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8'); }

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

// Fix undefined variables used in the UI
$studentName = $fullName;
$studentPrn = $studentZprn;
$studentCode = $studentLinkedPrn;

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

// Fetch Assigned Activities
$stmtActs = $pdo->prepare("
    SELECT a.*, a.subject AS subject_code,
           s.id AS submission_id, s.status AS sub_status, s.marks, s.submission_date, s.file_path, s.original_filename, s.is_late
    FROM activities a
    LEFT JOIN submissions s ON a.activity_id = s.activity_id AND (s.student_id = ? OR s.student_id = ?)
    WHERE a.target_type = 'all' 
       OR (a.target_type = 'individual' AND (a.target_id = ? OR a.target_id = ?)) 
       OR (a.target_type = 'group' AND a.target_id IN (SELECT group_id FROM group_members WHERE student_id = ? OR student_id = ?))
       OR (a.target_type = 'class' AND a.target_id IN (SELECT fc.class_id FROM faculty_classes fc LEFT JOIN faculty_class_students fcs ON fcs.class_id = fc.class_id WHERE (fc.department = ? AND fc.academic_year = ? AND fc.division = ?) OR (fcs.student_prn = ? OR fcs.student_prn = ?)))
    ORDER BY a.due_date ASC, a.activity_id DESC
");
$stmtActs->execute([$studentTableId, $studentUserId, $studentTableId, $studentUserId, $studentTableId, $studentUserId, $deptName, $academic_year, $division, $studentUsername, $studentLinkedPrn]);
$activities = $stmtActs->fetchAll(PDO::FETCH_ASSOC) ?: [];
foreach ($activities as &$a) {
    $a['id'] = $a['activity_id'];
}
unset($a);
$totalActivities = count($activities);

function displayStatus($a) {
    if (!empty($a['submission_id'])) {
        $st = $a['sub_status'] ?: 'Submitted';
        if ($a['marks'] !== null && $st === 'Submitted') return 'Graded';
        if (in_array($st, ['Approved', 'Graded', 'Evaluated'], true)) return 'Graded';
        if ($st === 'Submitted') return 'Submitted';
        return 'Under Review';
    }
    try {
        $now = new DateTime();
        $due = new DateTime($a['due_date']);
        if ($now > $due) {
            return 'Missed';
        }
    } catch (Exception $e) {}
    return 'Pending';
}

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

// ---------------------------------------------------------------------------------
// UPLOAD HANDLER (Student Upload)
// ---------------------------------------------------------------------------------
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST' && strtolower($role) === 'student') {
    header('Content-Type: application/json');
    $UPLOAD_ROOT = __DIR__ . '/uploads/';
    $ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];
    $MAX_SIZE     = 5 * 1024 * 1024; // 5 MB

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
        $stmt->execute([$activityId, $studentTableId, $studentUserId, $studentTableId, $studentUserId, $deptName, $academic_year, $division, $studentUsername, $studentLinkedPrn]);
        $activity = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$activity) throw new Exception('Activity not found or not assigned to you.');

        $now = new DateTime();
        $due = new DateTime($activity['due_date']);

        if (empty($_FILES['activity_file']) || $_FILES['activity_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please choose a valid file to upload.');
        }
        $file = $_FILES['activity_file'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $ALLOWED_EXT, true)) {
            throw new Exception('Only PDF, JPG, and PNG files are allowed.');
        }

        $fileSize = (int) ($file['size'] ?? 0);
        if ($fileSize > $MAX_SIZE) {
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
        
        // Simple automatic mark deduction logic if late (as per original)
        $calcMarks = $activity['max_marks'];
        if ($isLate) {
            $diffDays = (new DateTime($activity['due_date']))->setTime(0,0)->diff((new DateTime($submissionDateStr))->setTime(0,0))->days;
            if ($diffDays == 0 || $diffDays == 1) $calcMarks = max(0, $calcMarks - 1);
            elseif ($diffDays == 2) $calcMarks = max(0, $calcMarks - 2);
            else $calcMarks = 0;
        }

        if ($existing) {
            if (!empty($existing['file_path']) && is_file($existing['file_path'])) {
                @unlink($existing['file_path']);
            }
            $stmt = $pdo->prepare("UPDATE submissions SET student_id = ?, original_filename = ?, saved_filename = ?, file_path = ?, file_type = ?, file_size = ?, submission_date = ?, is_late = ?, marks = ?, status = 'Submitted' WHERE id = ?");
            $stmt->execute([$studentTableId, basename($file['name']), $storedName, $destPath, $ext, $fileSize, $submissionDateStr, $isLate, $calcMarks, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO submissions (activity_id, student_id, original_filename, saved_filename, file_path, file_type, file_size, submission_date, is_late, marks, status) VALUES (?,?,?,?,?,?,?,?,?,?,'Submitted')");
            $stmt->execute([$activityId, $studentTableId, basename($file['name']), $storedName, $destPath, $ext, $fileSize, $submissionDateStr, $isLate, $calcMarks]);
        }

        echo json_encode(['success' => true, 'message' => 'Activity uploaded successfully.', 'late' => (bool) $isLate]);
    } catch (Exception $ex) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $ex->getMessage()]);
    }
    exit;
}

// ---------------------------------------------------------------------------------
// DOWNLOAD / PREVIEW HANDLER
// ---------------------------------------------------------------------------------
if ($action === 'preview' || $action === 'download') {
    $UPLOAD_ROOT = __DIR__ . '/uploads/';
    $subId = (int) ($_GET['id'] ?? 0);
    if (in_array($role, ['faculty', 'hod', 'gfm', 'admin'])) {
        $stmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ?");
        $stmt->execute([$subId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM submissions WHERE id = ? AND student_id = ?");
        $stmt->execute([$subId, $studentTableId]);
    }
    $sub = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$sub) {
        http_response_code(404);
        exit('File not found.');
    }
    
    $fullPath = '';
    if (!empty($sub['file_path']) && is_file($sub['file_path'])) {
        $fullPath = $sub['file_path'];
    } else {
        $fullPath = $UPLOAD_ROOT . $sub['student_id'] . '/' . ($sub['original_filename'] ?? '');
    }

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Upload Assignment | SAAES</title>
  
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

    /* ================= INFO GRID ================= */
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .info-block { border: 1px solid var(--border-color); padding: 1rem; background: var(--bg-body); border-radius: var(--radius-md);}
    .info-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.2rem; display: block; letter-spacing: 0.05em;}
    .info-value { font-weight: 700; color: var(--text-main); font-size: 1.1rem;}

    /* ================= FILTERS & FORMS ================= */
    .filter-card {
        background: var(--bg-card); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--radius-lg);
        display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; box-shadow: var(--shadow-sm); margin-bottom: 1.5rem;
    }
    .form-label { font-size: 0.85rem; font-weight: 600; color: var(--text-main); margin-bottom: 0.4rem; display: block;}
    .form-control-custom, .form-select-custom {
        width: 100%; padding: 0.6rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color);
        color: var(--text-main); font-family: inherit; font-size: 0.9rem; outline: none; transition: border 0.2s;
        border-radius: var(--radius-md); -webkit-appearance: none;
    }
    .form-control-custom:focus, .form-select-custom:focus { border-color: var(--blue-accent); background: var(--bg-card); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
    .form-select-custom {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 1rem center;
        background-size: 1em;
        padding-right: 2.5rem;
    }

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

    /* ================= ALERTS & MODALS ================= */
    .alert { font-size: 0.9rem; font-weight: 500; border-radius: var(--radius-md); padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;}
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-info { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; }

    .modal-overlay {
      position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px);
      display: none; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;
    }
    .modal-content {
      background: var(--bg-card); border: 1px solid var(--border-color);
      max-width: 600px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 2rem;
      border-radius: var(--radius-lg); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); 
    }
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
            <a href="student_submit.php" class="sidebar-link active">
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
                <h3>Submit Activity</h3>
            </div>
        </header>

        <main class="main-content">

            <div class="alert alert-info">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Submission Info:</strong> Upload your activity files before the due date. Accepted formats: PDF, JPG, PNG (max 5 MB).
                </div>
            </div>

            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--navy-primary); margin-bottom: 0.2rem;">Student Activities</h2>
                        <p style="color: var(--text-muted); font-size: 0.9rem; margin: 0;">Upload, track and manage your unit-wise activity submissions.</p>
                    </div>
                    <span class="sys-tag accent">
                        <?= $totalActivities ?> Activities &middot; <?= count($subjects) ?> Subjects
                    </span>
                </div>

                <!-- Student Details Grid -->
                <div class="info-grid">
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
                <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--navy-primary); margin-bottom: 1rem;">Activities Upload</h3>
                <div class="filter-card">
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
                            <option value="">All Statuses</option>
                            <option>Pending</option><option>Submission Closed</option><option>Submitted</option>
                        </select>
                    </div>
                </div>

                <!-- Submission Table -->
                <div class="table-responsive">
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
                                <tr class="js-empty"><td colspan="10" style="text-align: center; padding: 3rem; color: var(--text-muted); font-weight: 500;">No activities have been assigned yet.</td></tr>
                            <?php endif; ?>
                            
                            <?php foreach ($activities as $a):
                                $status = displayStatus($a);
                                $hasSub = !empty($a['submission_id']);
                                $due = new DateTime($a['due_date']);
                            ?>
                            <tr data-subject="<?= e($a['subject_code']) ?>" data-unit="Unit <?= e($a['unit']) ?>" data-status="<?= e($status) ?>" data-title="<?= e(mb_strtolower($a['title'])) ?>">
                                <td style="font-weight: 600; color: var(--text-muted);">#<?= str_pad($a['id'], 3, '0', STR_PAD_LEFT) ?></td>
                                <td><span class="sys-tag" style="margin:0;"><?= e($a['subject_code']) ?></span></td>
                                <td><span style="font-weight: 600; font-size: 0.8rem; color: var(--text-muted);">Unit <?= e($a['unit']) ?></span></td>
                                <td>
                                    <strong style="font-size: 0.95rem; display: block; margin-bottom: 0.2rem; color: var(--text-main);"><?= e($a['title']) ?></strong>
                                    <span class="sys-tag hero" style="background: var(--navy-primary); border: none; color: white; padding: 0.15rem 0.5rem; font-size: 0.65rem; margin:0; margin-bottom: 0.3rem; margin-right: 0.3rem;"><?= e(ucwords(str_replace('_', ' ', $a['type']))) ?></span>
                                    <?php if (!empty($a['target_type']) && $a['target_type'] === 'class' && !empty($a['class_name'])): ?>
                                        <span class="sys-tag info" style="font-size: 0.65rem; margin:0;">Class: <?= e($a['class_name']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.85rem; color: var(--text-muted);"><?= fmtDate($a['due_date']) ?></td>
                                <td style="font-size: 0.85rem; color: var(--text-main); font-weight: 500;"><?= $hasSub ? fmtDateTime($a['submission_date']) : '—' ?></td>
                                <td style="font-size: 0.85rem;">
                                    <?= $hasSub ? '<span style="color: var(--blue-accent); font-weight: 500; max-width: 150px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block;" title="'.e($a['original_filename']).'">' . e($a['original_filename']) . '</span>' : '<span style="color: var(--text-muted);">Not uploaded</span>' ?>
                                </td>
                                <td>
                                    <?php if ($hasSub && $a['marks'] !== null): ?>
                                        <strong style="font-size: 1.05rem; color: var(--success);"><?= e($a['marks']) ?></strong> <span style="font-size: 0.75rem; color: var(--text-muted);">/ <?= e($a['max_marks']) ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="sys-tag <?= badgeClass($status) ?>" style="margin:0;"><?= e($status) ?></span></td>
                                <td style="text-align: center;">
                                    <div style="display: flex; gap: 0.4rem; justify-content: center;">
                                        <?php if (!$hasSub): ?>
                                            <button class="btn btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" onclick='openUploadModal(<?= (int)$a['id'] ?>, <?= jsAttr($a['subject_code']) ?>, <?= jsAttr("Unit " . $a['unit']) ?>, <?= jsAttr($a['title']) ?>, <?= jsAttr(fmtDate($a['due_date'])) ?>)'>
                                                Upload
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.8rem;" title="View File" onclick='openPreview(<?= (int)$a['submission_id'] ?>, <?= jsAttr($a['file_type']) ?>, <?= jsAttr($a['original_filename']) ?>)'><i class="fa-regular fa-eye"></i></button>
                                            <a class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; margin: 0;" title="Download File" href="student_submit.php?action=download&id=<?= (int)$a['submission_id'] ?>"><i class="fa-solid fa-download"></i></a>
                                            <button class="btn btn-outline warning" style="padding: 0.3rem 0.6rem; font-size: 0.8rem; color: var(--warning); border-color: #fde68a;" title="Replace File" onclick='openUploadModal(<?= (int)$a['id'] ?>, <?= jsAttr($a['subject_code']) ?>, <?= jsAttr("Unit " . $a['unit']) ?>, <?= jsAttr($a['title']) ?>, <?= jsAttr(fmtDate($a['due_date'])) ?>)'><i class="fa-solid fa-rotate"></i> Swap</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted);" id="resultsSummary">Showing activities</div>
                    <nav aria-label="Page navigation">
                        <ul class="pagination" id="activitiesPagination"></ul>
                    </nav>
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
            <button class="close-btn" id="closeUploadModal">&times;</button>
        </div>
        <form id="uploadForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="activity_id" id="mActivityId">
            
            <div id="uploadAlert" class="alert d-none" style="margin-bottom: 1.5rem;"></div>

            <div class="info-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 1.5rem;">
                <div class="info-block">
                    <span class="info-label">Student PRN</span>
                    <span class="info-value" style="font-size: 0.95rem;"><?= e($studentPrn ?: $studentCode) ?></span>
                </div>
                <div class="info-block">
                    <span class="info-label">Subject & Unit</span>
                    <span class="info-value" style="font-size: 0.95rem; color: var(--blue-accent);"><span id="mSubject"></span> - <span id="mUnit"></span></span>
                </div>
                <div class="info-block" style="grid-column: span 2;">
                    <span class="info-label">Activity Title</span>
                    <span class="info-value" style="font-size: 0.95rem;" id="mTitle"></span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Choose File (PDF, JPG, PNG)</label>
                <input type="file" class="form-control-custom" id="fileInput" name="activity_file" accept=".pdf,.jpg,.jpeg,.png">
                <div style="font-size: 0.75rem; font-weight: 500; color: var(--text-muted); margin-top: 0.5rem;" id="fileHelp">Max size: 5 MB.</div>
            </div>
            
            <!-- Custom Progress Bar -->
            <div id="progressWrap" class="d-none" style="width: 100%; height: 8px; border-radius: 4px; background: var(--border-color); margin-bottom: 1.5rem; overflow: hidden;">
                <div id="progressBar" style="width: 0%; height: 100%; background: var(--blue-accent); transition: width 0.3s;"></div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                <button type="button" class="btn btn-outline" id="cancelUploadModal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="uploadBtn">
                    <i class="fa-solid fa-cloud-arrow-up"></i> Upload File
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
            <button class="close-btn" id="closePreviewModal">&times;</button>
        </div>
        <div style="padding-bottom: 1.5rem;">
            <iframe id="previewFrame" class="d-none"></iframe>
            <img id="previewImg" class="d-none" alt="Submitted file preview">
            <div id="previewUnsupported" class="d-none" style="text-align: center; padding: 4rem 2rem;">
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
                emptyRow.innerHTML = `<td colspan="10" style="text-align: center; padding: 3rem; color: var(--text-muted); font-weight: 500;">No activities match your filters.</td>`;
                tbody.appendChild(emptyRow);
            }
            emptyRow.style.display = '';
        } else if (emptyRow) {
            emptyRow.style.display = 'none';
        }

        pager.innerHTML = '';
        if (totalPages > 1) {
            let html = `<li class="page-item ${page===1?'disabled':''}"><a class="page-link" href="#" data-p="${page-1}">Prev</a></li>`;
            for (let p = 1; p <= totalPages; p++) html += `<li class="page-item ${p===page?'active':''}"><a class="page-link" href="#" data-p="${p}">${p}</a></li>`;
            html += `<li class="page-item ${page===totalPages?'disabled':''}"><a class="page-link" href="#" data-p="${page+1}">Next</a></li>`;
            pager.innerHTML = html;
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

    // 2. Upload Modal Logic
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
        // document.getElementById('mDueDate').value = dueDate; // Element was removed in UI redesign
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
        if (!['pdf','jpg','jpeg','png'].includes(ext)) { fileHelp.innerHTML = '<span style="color: var(--danger);">Only PDF, JPG, PNG files are allowed.</span>'; this.value=''; return; }
        if (f.size > 5*1024*1024) { fileHelp.innerHTML = '<span style="color: var(--danger);">File exceeds the 5 MB size limit.</span>'; this.value=''; return; }
        fileHelp.innerHTML = `<span style="color: var(--success);"><i class="fa-solid fa-check"></i> ${f.name} (${(f.size/1024).toFixed(0)} KB)</span>`;
    });

    uploadForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const fileInput = document.getElementById('fileInput');
        if (!fileInput.files.length) { showAlert('Please choose a file to upload.', 'danger'); return; }

        uploadBtn.disabled = true; uploadBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Uploading...';
        progressWrap.classList.remove('d-none'); progressBar.style.width = '10%';

        const formData = new FormData(this);

        fetch('student_submit.php?action=upload', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                progressBar.style.width = '100%';
                if (data.success) {
                    showAlert(data.message, 'success');
                    uploadBtn.disabled = false; uploadBtn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Upload File';
                    setTimeout(() => { closeUploadModalFunc(); location.reload(); }, 1000);
                } else {
                    showAlert(data.message || 'Upload failed.', 'danger');
                    uploadBtn.disabled = false; uploadBtn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Upload File';
                }
            })
            .catch(() => {
                showAlert('A network error occurred. Please try again.', 'danger');
                uploadBtn.disabled = false; uploadBtn.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Upload File';
            });
    });

    function showAlert(msg, type) {
        uploadAlert.className = `alert alert-${type}`;
        const icon = type === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation';
        uploadAlert.innerHTML = `<i class="fa-solid ${icon}"></i> ` + msg;
        uploadAlert.classList.remove('d-none');
    }

    // 3. Custom Modal Preview Logic
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