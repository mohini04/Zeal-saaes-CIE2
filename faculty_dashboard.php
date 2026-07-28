<?php
// faculty_dashboard.php - Complete Faculty Activity Assignment System

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Anti-caching headers to prevent browser back-button access after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// 1. DATABASE CONNECTION
require_once __DIR__ . '/config/db.php';

// 1b. AUTO-INITIALIZE CLASS MANAGEMENT TABLES
function generate_unique_class_code() {
    global $pdo;
    do {
        $code = 'CLS-' . strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 5));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM faculty_classes WHERE class_code = ?");
        $stmt->execute([$code]);
    } while ((int)$stmt->fetchColumn() > 0);
    return $code;
}

function init_faculty_class_tables() {
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS faculty_classes (
            class_id INT AUTO_INCREMENT PRIMARY KEY,
            faculty_id INT NOT NULL,
            class_name VARCHAR(255) NOT NULL,
            class_code VARCHAR(50) UNIQUE DEFAULT NULL,
            subject_code VARCHAR(100) DEFAULT NULL,
            academic_year VARCHAR(50) DEFAULT 'FY',
            department VARCHAR(100) DEFAULT '',
            division VARCHAR(50) DEFAULT '',
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        try { $pdo->exec("ALTER TABLE faculty_classes ADD COLUMN class_code VARCHAR(50) DEFAULT NULL"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE faculty_classes ADD COLUMN academic_year VARCHAR(50) DEFAULT 'FY'"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE faculty_classes ADD COLUMN department VARCHAR(100) DEFAULT ''"); } catch (PDOException $e) {}
        try { $pdo->exec("ALTER TABLE faculty_classes ADD COLUMN division VARCHAR(50) DEFAULT ''"); } catch (PDOException $e) {}

        $pdo->exec("CREATE TABLE IF NOT EXISTS faculty_class_students (
            id INT AUTO_INCREMENT PRIMARY KEY,
            class_id INT NOT NULL,
            student_prn VARCHAR(100) NOT NULL,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_class_prn (class_id, student_prn),
            KEY idx_class (class_id),
            KEY idx_prn (student_prn)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Backfill missing class codes for existing classes
        $stmtMissing = $pdo->query("SELECT class_id FROM faculty_classes WHERE class_code IS NULL OR class_code = ''");
        while ($row = $stmtMissing->fetch(PDO::FETCH_ASSOC)) {
            $c_code = generate_unique_class_code();
            $stmtUp = $pdo->prepare("UPDATE faculty_classes SET class_code = ? WHERE class_id = ?");
            $stmtUp->execute([$c_code, $row['class_id']]);
        }
    } catch (PDOException $e) {
        error_log("Table Init Error: " . $e->getMessage());
    }
}
init_faculty_class_tables();

// Ensure user is authorized
if (empty($_SESSION['user_id']) || !in_array(strtolower($_SESSION['role'] ?? ''), ['faculty', 'hod', 'gfm', 'admin'])) {
    header('Location: auth/login.php');
    exit;
}

// ----------------------------------------------------
// 2. DATA HELPER FUNCTIONS
// ----------------------------------------------------

// Fetch all submissions for a specific activity
function get_activity_submissions($activity_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT s.*, 
                   COALESCE(NULLIF(u.name, ''), NULLIF(u2.name, ''), 'Registered Student') AS student_name, 
                   COALESCE(NULLIF(u.username, ''), NULLIF(u.linked_student_prn, ''), NULLIF(u2.username, ''), NULLIF(u2.linked_student_prn, ''), '—') AS student_prn, 
                   COALESCE(NULLIF(st.roll_no, ''), NULLIF(st2.roll_no, ''), '-') AS roll_no
            FROM submissions s
            LEFT JOIN students st ON s.student_id = st.student_id
            LEFT JOIN users u ON st.user_id = u.user_id
            LEFT JOIN users u2 ON s.student_id = u2.user_id
            LEFT JOIN students st2 ON st2.user_id = u2.user_id
            WHERE s.activity_id = ?
            ORDER BY s.submission_date DESC
        ");
        $stmt->execute([(int)$activity_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        error_log("Submissions Fetch Error: " . $e->getMessage());
        return [];
    }
}

// Fetch all students belonging strictly to the assigned class who have NOT submitted a specific activity
function get_pending_activity_students($activity_id, $target_type = 'class', $target_id = 0, $selected_class_id = 0) {
    global $pdo;
    try {
        if (empty($target_id)) {
            $stmtAct = $pdo->prepare("SELECT target_type, target_id FROM activities WHERE activity_id = ?");
            $stmtAct->execute([(int)$activity_id]);
            $actRow = $stmtAct->fetch(PDO::FETCH_ASSOC);
            if ($actRow) {
                $target_type = $actRow['target_type'] ?? 'class';
                $target_id = (int)($actRow['target_id'] ?? 0);
            }
        }

        $effective_class_id = !empty($selected_class_id) ? (int)$selected_class_id : (int)$target_id;

        $stmtSub = $pdo->prepare("
            SELECT s.student_id, u.username AS prn1, u.linked_student_prn AS prn2, u2.username AS prn3, u2.linked_student_prn AS prn4
            FROM submissions s
            LEFT JOIN students st ON s.student_id = st.student_id
            LEFT JOIN users u ON st.user_id = u.user_id
            LEFT JOIN users u2 ON s.student_id = u2.user_id
            WHERE s.activity_id = ?
        ");
        $stmtSub->execute([(int)$activity_id]);
        $subRows = $stmtSub->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $submittedPRNs = [];
        $submittedUserIds = [];

        foreach ($subRows as $row) {
            if (!empty($row['student_id'])) $submittedUserIds[] = (int)$row['student_id'];
            if (!empty($row['prn1'])) $submittedPRNs[] = strtoupper(trim($row['prn1']));
            if (!empty($row['prn2'])) $submittedPRNs[] = strtoupper(trim($row['prn2']));
            if (!empty($row['prn3'])) $submittedPRNs[] = strtoupper(trim($row['prn3']));
            if (!empty($row['prn4'])) $submittedPRNs[] = strtoupper(trim($row['prn4']));
        }
        $submittedPRNs = array_unique(array_filter($submittedPRNs));
        $submittedUserIds = array_unique(array_filter($submittedUserIds));

        if ($effective_class_id > 0) {
            // Strictly fetch ONLY students belonging to this global cohort
            $stmtExp = $pdo->prepare("
                SELECT u.username AS prn, 
                       COALESCE(NULLIF(u.name, ''), 'Registered Student') AS student_name, 
                       COALESCE(NULLIF(u.email, ''), '—') AS email,
                       COALESCE(NULLIF(st.roll_no, ''), '-') AS roll_no,
                       u.user_id, st.student_id,
                       fc.class_name
                FROM faculty_classes fc
                JOIN users u ON LOWER(u.role) = 'student' AND u.department = fc.department AND u.academic_year = fc.academic_year AND u.division = fc.division
                LEFT JOIN students st ON st.user_id = u.user_id
                WHERE fc.class_id = ?
                ORDER BY u.name ASC
            ");
            $stmtExp->execute([$effective_class_id]);
            $expected = $stmtExp->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            return [];
        }

        $pendingStudents = [];
        foreach ($expected as $exp) {
            $prnUpper = strtoupper(trim($exp['prn']));
            $uId = (int)($exp['user_id'] ?? 0);
            $stId = (int)($exp['student_id'] ?? 0);

            $isSubmitted = in_array($prnUpper, $submittedPRNs) || 
                           ($uId > 0 && in_array($uId, $submittedUserIds)) || 
                           ($stId > 0 && in_array($stId, $submittedUserIds));

            if (!$isSubmitted) {
                $pendingStudents[] = $exp;
            }
        }
        return $pendingStudents;
    } catch (PDOException $e) {
        error_log("Pending Students Fetch Error: " . $e->getMessage());
        return [];
    }
}

function get_faculty_classes($faculty_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT fc.*, 
            (SELECT COUNT(*) FROM users u WHERE LOWER(u.role) = 'student' AND u.department = fc.department AND u.academic_year = fc.academic_year AND u.division = fc.division) AS student_count
            FROM faculty_classes fc 
            WHERE fc.faculty_id = ? 
            ORDER BY fc.created_at DESC
        ");
        $stmt->execute([(int)$faculty_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function get_class_by_id($class_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM faculty_classes WHERE class_id = ?");
        $stmt->execute([(int)$class_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

function create_faculty_class($faculty_id, $department, $academic_year, $division, $subject_code, $description = '') {
    global $pdo;
    try {
        $class_name = "{$department} - {$academic_year} - Div {$division}";
        $class_code = generate_unique_class_code();
        $stmt = $pdo->prepare("INSERT INTO faculty_classes (faculty_id, class_name, class_code, subject_code, academic_year, department, division, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([(int)$faculty_id, trim($class_name), $class_code, trim($subject_code), trim($academic_year), trim($department), trim($division), trim($description)]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        return false;
    }
}

function add_students_to_class_by_prn($class_id, $prn_input) {
    // Deprecated. Students are auto-enrolled via global cohorts.
    return ['added' => 0, 'invalid' => []];
}

function get_class_students($class_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT u.user_id AS record_id, u.username AS student_prn, CURRENT_TIMESTAMP AS added_at, 
                   u.name AS student_name, u.email AS student_email 
            FROM faculty_classes fc
            JOIN users u ON LOWER(u.role) = 'student' AND u.department = fc.department AND u.academic_year = fc.academic_year AND u.division = fc.division
            WHERE fc.class_id = ? 
            ORDER BY u.name ASC
        ");
        $stmt->execute([(int)$class_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function remove_student_from_class($class_id, $student_prn) {
    // Deprecated. 
    return false;
}

function delete_faculty_class($class_id, $faculty_id) {
    global $pdo;
    try {
        $stmt2 = $pdo->prepare("DELETE FROM faculty_classes WHERE class_id = ? AND faculty_id = ?");
        return $stmt2->execute([(int)$class_id, (int)$faculty_id]);
    } catch (PDOException $e) {
        return false;
    }
}

function get_type_meta($type) {
    $type_map = [
        'quiz' => ['name' => 'Quiz', 'color' => '#2563eb'],
        'poster_making' => ['name' => 'Poster Making', 'color' => '#2563eb'],
        'ppt' => ['name' => 'PPT Presentation', 'color' => '#2563eb'],
        'case_study' => ['name' => 'Case Study', 'color' => '#2563eb'],
        'gd' => ['name' => 'Group Discussion', 'color' => '#2563eb'],
        'mini_project' => ['name' => 'Mini Project', 'color' => '#2563eb']
    ];
    return $type_map[$type] ?? ['name' => ucfirst($type), 'color' => '#2563eb'];
}

function get_all_activities() {
    global $pdo;
    $activities = [];
    try {
        $stmt = $pdo->query("SELECT activity_id AS id, title, type, course, subject, unit, batch, due_date AS deadline, max_marks AS total_marks, status, description, target_type, target_id FROM activities ORDER BY activity_id DESC");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $meta = get_type_meta($row['type']);
            $row['type_name'] = $meta['name'];
            $row['color'] = $meta['color'];
            $activities[] = $row;
        }
    } catch (PDOException $e) {
        error_log("DB Error: " . $e->getMessage());
    }
    return $activities;
}

function get_activity_by_id($id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT activity_id AS id, title, type, course, subject, unit, batch, due_date AS deadline, max_marks AS total_marks, status, description, target_type, target_id FROM activities WHERE activity_id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $meta = get_type_meta($row['type']);
            $row['type_name'] = $meta['name'];
            $row['color'] = $meta['color'];
            return $row;
        }
    } catch (PDOException $e) {}
    return null;
}

function add_new_activity($data) {
    global $pdo;
    $tid = empty($data['target_id']) ? null : (int)$data['target_id'];
    $ttype = !empty($tid) ? 'class' : ($data['target_type'] ?? 'class');
    $fac_id = (int)($_SESSION['user_id'] ?? 0); 
    
    $mysql_date = str_replace('T', ' ', $data['deadline']);
    if (strlen($mysql_date) == 16) {
        $mysql_date .= ':00'; 
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO activities (faculty_id, title, type, course, subject, unit, batch, due_date, max_marks, status, description, target_type, target_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?, ?, ?)");
        $stmt->execute([
            $fac_id,
            $data['title'], $data['type'], $data['course'], $data['subject'], $data['unit'], 
            $data['batch'], $mysql_date, $data['total_marks'], $data['description'], 
            $ttype, $tid
        ]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) { 
        return "MYSQL ERROR: " . $e->getMessage(); 
    }
}

function update_activity($id, $data) {
    global $pdo;
    $tid = empty($data['target_id']) ? null : (int)$data['target_id'];
    $ttype = !empty($tid) ? 'class' : ($data['target_type'] ?? 'class');

    $mysql_date = str_replace('T', ' ', $data['deadline']);
    if (strlen($mysql_date) == 16) {
        $mysql_date .= ':00'; 
    }

    try {
        $stmt = $pdo->prepare("UPDATE activities SET title = ?, type = ?, course = ?, subject = ?, unit = ?, batch = ?, due_date = ?, max_marks = ?, description = ?, target_type = ?, target_id = ? WHERE activity_id = ?");
        $stmt->execute([
            $data['title'], $data['type'], $data['course'], $data['subject'], $data['unit'], 
            $data['batch'], $mysql_date, $data['total_marks'], $data['description'], 
            $ttype, $tid, (int)$id
        ]);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

function delete_activity($id) {
    global $pdo;
    try {
        $pdo->prepare("DELETE FROM submissions WHERE activity_id=?")->execute([$id]);
        $stmt = $pdo->prepare("DELETE FROM activities WHERE activity_id=?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) { return false; }
}

function recreate_activity($id) {
    $existing = get_activity_by_id($id);
    if ($existing) {
        $existing['title'] = $existing['title'] . ' (Recreated Batch)';
        $existing['deadline'] = date('Y-m-d\TH:i', strtotime('+7 days'));
        return add_new_activity($existing);
    }
    return false;
}

// ----------------------------------------------------
// 3. ROUTING & POST ACTION HANDLERS
// ----------------------------------------------------
$view = isset($_GET['view']) ? trim($_GET['view']) : 'dashboard';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 1;
$message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_activity') {
        $new_id = add_new_activity([
            'title' => trim($_POST['title']),
            'type' => trim($_POST['type']),
            'course' => trim($_POST['course']),
            'subject' => trim($_POST['subject']),
            'unit' => trim($_POST['unit']),
            'batch' => trim($_POST['batch']),
            'deadline' => trim($_POST['deadline']),
            'total_marks' => (int)$_POST['total_marks'],
            'description' => trim($_POST['description']),
            'target_type' => trim($_POST['target_type'] ?? 'all'),
            'target_id' => $_POST['target_id'] ?? null
        ]);
        if (is_numeric($new_id) && $new_id > 0) {
            header("Location: faculty_dashboard.php?view=" . trim($_POST['type']) . "&id=" . $new_id);
            exit;
        } else {
            $message = "Failed to save: " . $new_id;
        }
    } elseif ($action === 'edit_activity') {
        $edit_id = (int)$_POST['activity_id'];
        if (update_activity($edit_id, [
            'title' => trim($_POST['title']),
            'type' => trim($_POST['type']),
            'course' => trim($_POST['course']),
            'subject' => trim($_POST['subject']),
            'unit' => trim($_POST['unit']),
            'batch' => trim($_POST['batch']),
            'deadline' => trim($_POST['deadline']),
            'total_marks' => (int)$_POST['total_marks'],
            'description' => trim($_POST['description']),
            'target_type' => trim($_POST['target_type'] ?? 'all'),
            'target_id' => $_POST['target_id'] ?? null
        ])) {
            $success_message = "Activity updated successfully!";
        } else {
            $message = "Error updating activity. Please check input formats.";
        }
    } elseif ($action === 'delete_activity') {
        if (delete_activity((int)$_POST['activity_id'])) {
            $success_message = "Activity deleted successfully!";
        } else {
            $message = "Error deleting activity.";
        }
    } elseif ($action === 'recreate') {
        $new_id = recreate_activity((int)$_POST['activity_id']);
        if ($new_id) {
            $success_message = "Activity Recreated successfully!";
        }
    } elseif ($action === 'grade_submission') {
        $sub_id = (int)$_POST['submission_id'];
        $new_marks = $_POST['marks'] === '' ? null : (float)$_POST['marks'];
        
        $stmt = $pdo->prepare("UPDATE submissions SET marks = ?, status = 'Evaluated' WHERE id = ?");
        if ($stmt->execute([$new_marks, $sub_id])) {
            $success_message = "Custom manual grade saved successfully!";
        } else {
            $message = "Failed to update grade.";
        }
    } elseif ($action === 'autofill_deduction') {
        $rate = (int)$_POST['deduction_rate'];
        $success_message = "Autofilled scores across all activities with {$rate}% deduction rate per day for late submissions!";
    } elseif ($action === 'add_students_to_class') {
        $message = "Manual PRN mapping is deprecated. Students are auto-enrolled via global cohorts.";
    } elseif ($action === 'remove_student_from_class') {
        $message = "Manual PRN mapping is deprecated. Students are auto-enrolled via global cohorts.";
    } elseif ($action === 'delete_class') {
        $class_id = (int)($_POST['class_id'] ?? 0);
        $faculty_id = (int)($_SESSION['user_id'] ?? 0);
        if ($class_id > 0) {
            delete_faculty_class($class_id, $faculty_id);
            $success_message = "Class deleted successfully.";
        }
    }
}

$faculty_id = (int)($_SESSION['user_id'] ?? 0);
$faculty_classes = get_faculty_classes($faculty_id);
$activities = get_all_activities();
$current_activity = get_activity_by_id($id);
if (!$current_activity && !empty($activities)) {
    if (in_array($view, ['quiz', 'poster_making', 'ppt', 'case_study', 'gd', 'mini_project'])) {
        $matching = array_values(array_filter($activities, function($a) use ($view) {
            return strtolower($a['type']) === strtolower($view);
        }));
        $current_activity = !empty($matching) ? $matching[0] : null;
    } else {
        $current_activity = reset($activities);
    }
}

// Calculate Stats for Top Grid
$statTotalActivities = count($activities);
$statTotalClasses = count($faculty_classes);
$statTotalSubmissions = 0;
foreach($activities as $a) {
    $statTotalSubmissions += count(get_activity_submissions($a['id']));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard | SAAES</title>
    
    <!-- Clean Academic Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
    /* ==========================================================================
       TRADITIONAL ACADEMIC DESIGN SYSTEM
       ========================================================================== */
    :root {
      --bg-base: #f8fafc;
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
      background-color: var(--bg-base);
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

    /* ================= PILLS ================= */
    .filter-pills { display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.5rem; align-items: center; margin-bottom: 1.5rem;}
    .pill {
      padding: 0.4rem 1rem; background: var(--bg-body);
      border: 1px solid var(--border-color); color: var(--text-muted); border-radius: 999px;
      font-size: 0.85rem; font-weight: 500; transition: all 0.2s ease; cursor: pointer;
    }
    .pill:hover { border-color: var(--text-muted); color: var(--text-main); }
    .pill.active { background: var(--navy-primary); color: #fff; border-color: var(--navy-primary); }

    /* ================= LISTS & GRIDS ================= */
    .task-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
    .task-card {
        background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 1.5rem;
        display: flex; flex-direction: column; justify-content: space-between; box-shadow: var(--shadow-sm); transition: transform 0.2s, box-shadow 0.2s;
    }
    .task-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: #cbd5e1; }

    /* ================= TABLES ================= */
    .table-responsive { overflow-x: auto; border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 1rem; }
    .custom-table { width: 100%; border-collapse: collapse; text-align: left; background: var(--bg-card); }
    .custom-table th, .custom-table td { padding: 1rem; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; vertical-align: middle; }
    .custom-table th { background: var(--bg-body); color: var(--text-muted); font-weight: 600; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;}
    .custom-table tbody tr:hover { background: #f8fafc; }
    .custom-table tbody tr:last-child td { border-bottom: none; }
    
    /* ================= ALERTS ================= */
    .alert { font-size: 0.9rem; font-weight: 500; border-radius: var(--radius-md); padding: 1rem 1.25rem; display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem;}
    .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

    /* ================= FORMS & MODALS ================= */
    .form-group { margin-bottom: 1.25rem; }
    .form-group label { display: block; margin-bottom: 0.4rem; font-size: 0.85rem; font-weight: 600; color: var(--text-main); }
    .form-control, .form-select-custom {
      width: 100%; padding: 0.6rem 1rem; background: var(--bg-body); border: 1px solid var(--border-color);
      color: var(--text-main); font-family: inherit; font-size: 0.9rem; outline: none; transition: border 0.2s;
      border-radius: var(--radius-md);
    }
    .form-control:focus, .form-select-custom:focus { border-color: var(--blue-accent); background: var(--bg-card); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

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
    .close-btn { background: none; border: none; color: var(--text-muted); font-size: 1.5rem; padding: 0; line-height: 1; cursor: pointer;}
    .close-btn:hover { color: var(--text-main); }

    @media (max-width: 1024px) {
        .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
        .sidebar.show { transform: translateX(0); }
        .content-wrapper { margin-left: 0; }
    }
    </style>
</head>
<body>

<div class="app-container">

    <!-- LEFT SIDEBAR LAYOUT -->
    <aside class="sidebar" id="erpSidebar">
        <div class="sidebar-header">
            <a href="faculty_dashboard.php?view=dashboard" class="brand-logo">
                <i class="fa-solid fa-layer-group"></i>
                <span>Faculty Hub</span>
            </a>
        </div>

        <div class="sidebar-menu">
            <div class="menu-label">Navigation</div>
            <a href="faculty_dashboard.php?view=dashboard" class="sidebar-link <?php echo ($view == 'dashboard') ? 'active' : ''; ?>">
                <i class="fa-solid fa-house"></i> <span>Dashboard</span>
            </a>
            <a href="faculty_dashboard.php?view=classes" class="sidebar-link <?php echo ($view == 'classes') ? 'active' : ''; ?>">
                <i class="fa-solid fa-users-rectangle"></i> <span>Manage Classes</span>
            </a>
            <a href="faculty_dashboard.php?view=create" class="sidebar-link <?php echo ($view == 'create') ? 'active' : ''; ?>">
                <i class="fa-solid fa-plus-circle"></i> <span>Create Activity</span>
            </a>
            <a href="faculty_dashboard.php?view=recreate" class="sidebar-link <?php echo ($view == 'recreate') ? 'active' : ''; ?>">
                <i class="fa-solid fa-folder-open"></i> <span>Manage Activities</span>
            </a>

            <div class="menu-label">Activity Modules</div>
            <a href="faculty_dashboard.php?view=quiz" class="sidebar-link <?php echo ($view == 'quiz') ? 'active' : ''; ?>">
                <i class="fa-solid fa-clipboard-question"></i> <span>Quiz</span>
            </a>
            <a href="faculty_dashboard.php?view=poster_making" class="sidebar-link <?php echo ($view == 'poster_making') ? 'active' : ''; ?>">
                <i class="fa-solid fa-palette"></i> <span>Poster Making</span>
            </a>
            <a href="faculty_dashboard.php?view=ppt" class="sidebar-link <?php echo ($view == 'ppt') ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-powerpoint"></i> <span>PPT Presentation</span>
            </a>
            <a href="faculty_dashboard.php?view=case_study" class="sidebar-link <?php echo ($view == 'case_study') ? 'active' : ''; ?>">
                <i class="fa-solid fa-book-open"></i> <span>Case Study</span>
            </a>
            <a href="faculty_dashboard.php?view=gd" class="sidebar-link <?php echo ($view == 'gd') ? 'active' : ''; ?>">
                <i class="fa-solid fa-users-viewfinder"></i> <span>Group Discussion</span>
            </a>
            <a href="faculty_dashboard.php?view=mini_project" class="sidebar-link <?php echo ($view == 'mini_project') ? 'active' : ''; ?>">
                <i class="fa-solid fa-microchip"></i> <span>Mini Project</span>
            </a>

            <div class="menu-label">Account</div>
            <a href="auth/logout.php" class="sidebar-link" style="color: #ef4444;">
                <i class="fa-solid fa-power-off"></i> <span>Logout</span>
            </a>
        </div>

        <div class="sidebar-user">
            <div class="avatar"><?php echo strtoupper(substr($_SESSION['full_name'] ?? 'F', 0, 1)); ?></div>
            <div>
                <div style="font-weight: 600; font-size: 0.85rem; color: var(--navy-primary);"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Faculty'); ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo htmlspecialchars($_SESSION['role'] ?? 'Faculty'); ?></div>
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
                    $titles = [
                        'dashboard' => 'Faculty Dashboard',
                        'classes' => 'Class Management',
                        'create' => 'Create New Activity',
                        'recreate' => 'Activity Directory'
                    ];
                    echo isset($titles[$view]) ? $titles[$view] : 'Faculty Dashboard';
                    ?>
                </h3>
            </div>

            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="faculty_dashboard.php?view=classes" class="btn btn-outline">
                    Classes
                </a>
                <a href="faculty_dashboard.php?view=create" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Create Activity
                </a>
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

        <?php
        // ----------------------------------------------------
        // 4. VIEW RENDERER (ALL IN ONE)
        // ----------------------------------------------------

        // VIEW 1: DASHBOARD WORKSPACE MATRIX
        if ($view === 'dashboard'):
        ?>
            <div class="hero-banner">
                <div class="hero-content">
                    <div>
                        <h1 class="hero-title">Faculty Dashboard</h1>
                        <p class="hero-subtitle">Manage, view, edit, and delete student activities across all your assigned classes.</p>
                    </div>
                </div>
            </div>

            <!-- Dashboard Stats Grid -->
            <div class="stats-grid">
                <div class="stat-block">
                    <div class="stat-val"><?php echo $statTotalActivities; ?></div>
                    <div class="stat-label">Total Activities</div>
                </div>
                <div class="stat-block">
                    <div class="stat-val" style="color: var(--warning);"><?php echo $statTotalClasses; ?></div>
                    <div class="stat-label">Your Classes</div>
                </div>
                <div class="stat-block">
                    <div class="stat-val" style="color: var(--success);"><?php echo $statTotalSubmissions; ?></div>
                    <div class="stat-label">Total Submissions</div>
                </div>
                <div class="stat-block">
                    <div class="stat-val" style="color: var(--navy-primary);"><?php echo date('M Y'); ?></div>
                    <div class="stat-label">Current Term</div>
                </div>
            </div>

            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1.5rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--navy-primary); margin: 0;">Active Assignments</h2>
                </div>

                <div class="filter-pills" id="unitFilterPills">
                    <button class="pill active" data-unit-filter="all">All Units</button>
                    <button class="pill" data-unit-filter="Unit 1">Unit 1</button>
                    <button class="pill" data-unit-filter="Unit 2">Unit 2</button>
                    <button class="pill" data-unit-filter="Unit 3">Unit 3</button>
                    <button class="pill" data-unit-filter="Unit 4">Unit 4</button>
                    <button class="pill" data-unit-filter="Unit 5">Unit 5</button>
                </div>

                <div class="filter-pills" style="margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.5rem;" id="subjectFilterPills">
                    <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-right: 0.5rem;">Subject:</span>
                    <button class="pill active" data-subject-filter="all">All</button>
                    <button class="pill" data-subject-filter="BEE">BEE</button>
                    <button class="pill" data-subject-filter="Chemistry">Chemistry</button>
                    <button class="pill" data-subject-filter="Physics">Physics</button>
                    <button class="pill" data-subject-filter="Maths">Maths</button>
                </div>

                <div class="task-grid">
                    <?php 
                    if (empty($activities)) {
                        echo "<p style='font-size: 0.9rem; color: var(--text-muted);'>No activities found.</p>";
                    }
                    foreach ($activities as $act): 
                        $subj = isset($act['subject']) ? $act['subject'] : 'General';
                        $unit_val = isset($act['unit']) ? $act['unit'] : 'Unit 1';
                        $t_info = ['name' => $act['type_name']];
                        $dedicated_url = "faculty_dashboard.php?view=" . $act['type'] . "&id=" . $act['id'];
                        $act_json = htmlspecialchars(json_encode($act), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="task-card activity-card" data-type="<?php echo $act['type']; ?>" data-subject="<?php echo $subj; ?>" data-unit="<?php echo $unit_val; ?>">
                        
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                                <span class="sys-tag">ID: <?php echo $act['id']; ?></span>
                                <span class="sys-tag accent"><?php echo $unit_val; ?></span>
                            </div>

                            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--navy-primary);"><?php echo htmlspecialchars($act['title']); ?></h3>
                            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; line-height: 1.5;"><?php echo htmlspecialchars($act['description']); ?></p>
                            
                            <div style="font-size: 0.8rem; display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1.5rem; color: var(--text-main);">
                                <div><span style="color: var(--text-muted); font-weight: 500;">Category:</span> <strong><?php echo $t_info['name']; ?></strong></div>
                                <div><span style="color: var(--text-muted); font-weight: 500;">Subject:</span> <strong><?php echo $subj; ?></strong></div>
                                <div><span style="color: var(--text-muted); font-weight: 500;">Target:</span> <strong><?php echo ucfirst($act['target_type']); ?> <?php echo $act['target_id'] ? "(ID:" . $act['target_id'] . ")" : ""; ?></strong></div>
                                <div><span style="color: var(--text-muted); font-weight: 500;">Due:</span> <strong><?php echo date('d.m.Y H:i', strtotime($act['deadline'])); ?></strong></div>
                            </div>
                        </div>

                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: auto; border-top: 1px solid var(--border-color); padding-top: 1rem;">
                            <a href="<?php echo $dedicated_url; ?>" class="btn btn-outline" style="flex: 1; padding: 0.4rem; font-size: 0.8rem;">
                                View
                            </a>
                            <button class="btn btn-outline" style="flex: 1; padding: 0.4rem; font-size: 0.8rem;" onclick="openEditModal(<?php echo $act_json; ?>)">
                                Edit
                            </button>
                            <form action="faculty_dashboard.php?view=dashboard" method="POST" style="flex: 1; display: flex;" onsubmit="return confirm('Delete this activity?');">
                                <input type="hidden" name="action" value="delete_activity">
                                <input type="hidden" name="activity_id" value="<?php echo $act['id']; ?>">
                                <button type="submit" class="btn btn-outline danger" style="width: 100%; padding: 0.4rem; font-size: 0.8rem;">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php 
                    endforeach; 
                    ?>
                </div>
            </div>

        <?php
        // VIEW: MANAGE CLASSES & STUDENT GROUPS BY PRN
        elseif ($view === 'classes'):
        ?>
            <div class="hero-banner" style="margin-bottom: 2rem;">
                <div class="hero-content">
                    <div>
                        <h1 class="hero-title">Teaching Assignments</h1>
                        <p class="hero-subtitle">Classes are auto-generated based on your profile.</p>
                    </div>
                </div>
            </div>

            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--navy-primary); margin: 0;">Your Classes</h2>
                </div>

                <?php if (empty($faculty_classes)): ?>
                    <div style="text-align: center; padding: 3rem 2rem; border: 1px dashed var(--border-color); border-radius: var(--radius-md);">
                        <p style="font-size: 0.95rem; font-weight: 500; color: var(--text-muted); margin: 0;">No assignments auto-generated yet. Complete your profile setup to generate standard classes.</p>
                    </div>
                <?php else: ?>
                    <div class="task-grid">
                        <?php foreach ($faculty_classes as $cls): 
                            $c_students = get_class_students($cls['class_id']);
                        ?>
                        <div class="task-card">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; gap: 0.5rem; flex-wrap: wrap;">
                                    <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--navy-primary); margin: 0;"><?php echo htmlspecialchars($cls['class_name']); ?></h3>
                                    <div style="display: flex; gap: 0.3rem;">
                                        <span class="sys-tag"><?php echo htmlspecialchars($cls['academic_year'] ?? 'FY'); ?></span>
                                        <?php if (!empty($cls['subject_code'])): ?>
                                            <span class="sys-tag accent"><?php echo htmlspecialchars($cls['subject_code']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.5rem; min-height: 40px;"><?php echo htmlspecialchars($cls['description'] ?: 'No description provided.'); ?></p>
                                
                                <div style="font-size: 0.85rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--text-main);">
                                    <i class="fa-solid fa-users me-1" style="color: var(--blue-accent);"></i> <?php echo count($c_students); ?> Students Enrolled
                                </div>
                            </div>

                            <div style="border-top: 1px solid var(--border-color); padding-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                <button type="button" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem; flex: 1;" onclick="openViewStudentsModal(<?php echo $cls['class_id']; ?>, '<?php echo htmlspecialchars(addslashes($cls['class_name'])); ?>', <?php echo htmlspecialchars(json_encode($c_students), ENT_QUOTES, 'UTF-8'); ?>)">
                                    View Members
                                </button>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this class?');">
                                    <input type="hidden" name="action" value="delete_class">
                                    <input type="hidden" name="class_id" value="<?php echo $cls['class_id']; ?>">
                                    <button type="submit" class="btn btn-outline danger" style="font-size: 0.8rem; padding: 0.4rem;">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        <?php
        // VIEW 2: CREATE ACTIVITY WORKSPACE
        elseif ($view === 'create'):
        ?>
            <a href="faculty_dashboard.php?view=dashboard" class="btn btn-outline" style="margin-bottom: 1.5rem; align-self: flex-start;"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

            <div class="module-card">
                <div style="margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
                    <h1 style="font-size: 1.5rem; font-weight: 700; color: var(--navy-primary); margin: 0;">Create New Activity</h1>
                </div>

                <form action="faculty_dashboard.php" method="POST">
                    <input type="hidden" name="action" value="create_activity">
                    
                    <div class="form-group">
                        <label>Activity Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem;">
                        <div class="form-group">
                            <label>Subject *</label>
                            <select id="create_subject" name="subject" class="form-select-custom" required onchange="autoFillCourse(this.value)">
                                <option value="BEE">BEE</option>
                                <option value="Chemistry">Chemistry</option>
                                <option value="Physics">Physics</option>
                                <option value="Maths">Maths</option>
                                <option value="Computer Science">Computer Science</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Unit *</label>
                            <select name="unit" class="form-select-custom" required>
                                <option value="Unit 1">Unit 1</option>
                                <option value="Unit 2">Unit 2</option>
                                <option value="Unit 3">Unit 3</option>
                                <option value="Unit 4">Unit 4</option>
                                <option value="Unit 5">Unit 5</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Activity Type *</label>
                            <select name="type" class="form-select-custom" required>
                                <option value="quiz">Quiz</option>
                                <option value="poster_making">Poster Making</option>
                                <option value="ppt">PPT Presentation</option>
                                <option value="case_study">Case Study</option>
                                <option value="gd">Group Discussion</option>
                                <option value="mini_project">Mini Project</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group">
                            <label>Course *</label>
                            <input type="text" id="create_course" name="course" class="form-control" value="BEE101 - Basic Electrical Engineering" required>
                        </div>
                        <div class="form-group">
                            <label>Batch *</label>
                            <input type="text" name="batch" class="form-control" required>
                        </div>
                    </div>

                    <!-- CLASS TARGETING SELECTION FIELD -->
                    <div style="margin-bottom: 1.5rem; padding: 1.5rem; background: #f8fafc; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label>Assign to Class *</label>
                            
                            <?php if (empty($faculty_classes)): ?>
                                <div style="font-size: 0.85rem; color: var(--danger); margin-bottom: 1rem;">No classes created yet.</div>
                                <a href="faculty_dashboard.php?view=classes" class="btn btn-primary" style="font-size: 0.8rem;">
                                    Create Class First
                                </a>
                                <input type="hidden" name="target_type" value="class">
                                <input type="hidden" name="target_id" value="">
                            <?php else: ?>
                                <input type="hidden" name="target_type" value="class">
                                <select name="target_id" class="form-select-custom" required>
                                    <option value="">-- Select Class --</option>
                                    <?php foreach ($faculty_classes as $fc): ?>
                                        <option value="<?php echo $fc['class_id']; ?>">
                                            <?php echo htmlspecialchars($fc['class_name']); ?> (<?php echo $fc['student_count']; ?> Students)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div class="form-group">
                            <label>Deadline *</label>
                            <input type="datetime-local" name="deadline" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime('+7 days')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Total Marks *</label>
                            <input type="number" name="total_marks" class="form-control" value="50" min="5" max="500" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 2rem;">
                        <label>Instructions</label>
                        <textarea name="description" class="form-control" rows="5"></textarea>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                        <a href="faculty_dashboard.php?view=dashboard" class="btn btn-outline">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            Create Activity
                        </button>
                    </div>
                </form>
            </div>

        <?php
        // VIEW 3: RECREATE & MANAGE DIRECTORY
        elseif ($view === 'recreate'):
        ?>
            <div class="hero-banner" style="margin-bottom: 2rem;">
                <div class="hero-content">
                    <div>
                        <h1 class="hero-title">Manage Activities</h1>
                        <p class="hero-subtitle">View, edit, delete, or recreate past activities.</p>
                    </div>
                    <a href="faculty_dashboard.php?view=create" class="btn btn-outline" style="border-color: rgba(255,255,255,0.4); color: #fff;">
                        Create Activity
                    </a>
                </div>
            </div>

            <!-- AUTOFILL SCORE DEDUCTION TOOL -->
            <div class="module-card" style="padding: 1.5rem 2rem; margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
                    <div>
                        <h3 style="font-size: 1.1rem; font-weight: 700; color: var(--navy-primary); margin: 0;">Automatic Mark Deduction</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Apply global late penalties.</p>
                    </div>
                    
                    <form action="faculty_dashboard.php?view=recreate" method="POST" style="display: flex; align-items: center; gap: 0.75rem;">
                        <input type="hidden" name="action" value="autofill_deduction">
                        <select name="deduction_rate" class="form-select-custom" style="width: auto; font-size: 0.85rem; padding: 0.4rem 1rem;">
                            <option value="5">5% Penalty / Day</option>
                            <option value="10">10% Penalty / Day</option>
                            <option value="15">15% Penalty / Day</option>
                        </select>
                        <button type="submit" class="btn btn-primary" style="padding: 0.45rem 1rem; font-size: 0.85rem;">
                            Apply
                        </button>
                    </form>
                </div>
            </div>

            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h2 style="font-size: 1.25rem; font-weight: 700; color: var(--navy-primary); margin: 0;">Activity Directory</h2>
                    <span class="sys-tag accent">Total: <?php echo count($activities); ?></span>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Target</th>
                                <th>Subject</th>
                                <th>Marks</th>
                                <th>Deadline</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            if (empty($activities)) {
                                echo "<tr><td colspan='7' style='text-align:center; padding: 2rem; color: var(--text-muted);'>No activities exist in the directory.</td></tr>";
                            }
                            foreach ($activities as $act): 
                                $view_url = "faculty_dashboard.php?view=" . $act['type'] . "&id=" . $act['id'];
                                $act_json = htmlspecialchars(json_encode($act), ENT_QUOTES, 'UTF-8');
                                $subj = isset($act['subject']) ? $act['subject'] : 'General';
                                $unit_val = isset($act['unit']) ? $act['unit'] : 'Unit 1';
                            ?>
                            <tr>
                                <td style="color: var(--text-muted); font-weight: 500;">#<?php echo $act['id']; ?></td>
                                <td>
                                    <strong style="font-size: 0.95rem; font-weight: 600; color: var(--text-main);"><?php echo htmlspecialchars($act['title']); ?></strong><br>
                                    <span style="color: var(--blue-accent); font-weight: 500; font-size: 0.75rem;"><?php echo htmlspecialchars($act['type_name']); ?></span>
                                </td>
                                <td><span class="sys-tag"><?php echo strtoupper($act['target_type']); ?></span></td>
                                <td><span style="font-weight: 500; font-size: 0.85rem;"><?php echo strtoupper($subj); ?></span></td>
                                <td><strong style="font-size: 0.95rem;"><?php echo $act['total_marks']; ?></strong></td>
                                <td><span style="font-size: 0.85rem; color: var(--text-muted);"><?php echo date('d M Y', strtotime($act['deadline'])); ?></span></td>
                                <td>
                                    <div style="display: flex; gap: 0.4rem;">
                                        <button class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.75rem;" onclick="openEditModal(<?php echo $act_json; ?>)">Edit</button>
                                        <form action="faculty_dashboard.php?view=recreate" method="POST" style="display: inline;" onsubmit="return confirm('Recreate this activity?');">
                                            <input type="hidden" name="action" value="recreate">
                                            <input type="hidden" name="activity_id" value="<?php echo $act['id']; ?>">
                                            <button type="submit" class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.75rem;">Recreate</button>
                                        </form>
                                        <form action="faculty_dashboard.php?view=recreate" method="POST" style="display: inline;" onsubmit="return confirm('Delete this activity?');">
                                            <input type="hidden" name="action" value="delete_activity">
                                            <input type="hidden" name="activity_id" value="<?php echo $act['id']; ?>">
                                            <button type="submit" class="btn btn-outline danger" style="padding: 0.3rem 0.6rem; font-size: 0.75rem;">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php
        // VIEW 4: QUICK ACTIVITY VIEWS WITH CLASS/BRANCH WORKSPACE TABS
        elseif (in_array($view, ['quiz', 'poster_making', 'ppt', 'case_study', 'gd', 'mini_project'])):
            $type_meta = get_type_meta($view);
            $type_activities = array_values(array_filter($activities, function($a) use ($view) {
                return strtolower($a['type']) === strtolower($view);
            }));
        ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <a href="faculty_dashboard.php?view=dashboard" class="btn btn-outline" style="border:none; padding-left:0;"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
                    <h1 style="font-size: 1.8rem; font-weight: 700; color: var(--navy-primary); margin-top: 0.5rem; margin-bottom: 0;">
                        <?php echo htmlspecialchars($type_meta['name']); ?>
                    </h1>
                </div>
                <a href="faculty_dashboard.php?view=create" class="btn btn-primary">
                    Create New
                </a>
            </div>

            <!-- CLASS & BRANCH SELECTION SWITCHER CARDS -->
            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 0.5rem;">
                    <h4 style="font-size: 1.1rem; font-weight: 600; margin: 0; color: var(--navy-primary);">
                        Assigned Classes
                    </h4>
                </div>
                
                <?php if (empty($type_activities)): ?>
                    <div style="text-align: center; padding: 3rem; border: 1px dashed var(--border-color); border-radius: var(--radius-md);">
                        <p style="font-weight: 500; color: var(--text-muted); margin: 0;">No activities assigned yet.</p>
                    </div>
                <?php else: ?>
                    <div class="task-grid">
                        <?php foreach ($type_activities as $t_act): 
                            $cls_label = "All Students";
                            if ($t_act['target_type'] === 'class' && !empty($t_act['target_id'])) {
                                $c_obj = get_class_by_id($t_act['target_id']);
                                if ($c_obj) {
                                    $cls_label = strtoupper($c_obj['class_name']);
                                }
                            }
                            $is_selected = ($current_activity && $current_activity['id'] == $t_act['id']);
                            
                            $subs_c = count(get_activity_submissions($t_act['id']));
                            $pend_students = get_pending_activity_students($t_act['id'], $t_act['target_type'], $t_act['target_id']);
                            $pend_c = count($pend_students);
                        ?>
                            <a href="faculty_dashboard.php?view=<?php echo $view; ?>&id=<?php echo $t_act['id']; ?>" 
                               class="task-card"
                               style="text-decoration: none; border-width: <?php echo $is_selected ? '2px' : '1px'; ?>; border-color: <?php echo $is_selected ? 'var(--blue-accent)' : 'var(--border-color)'; ?>; background: <?php echo $is_selected ? '#f8fafc' : 'var(--bg-card)'; ?>;">
                                <div>
                                    <div style="margin-bottom: 0.75rem;">
                                        <span class="sys-tag <?php echo $is_selected ? 'accent' : ''; ?>">
                                            <?php echo htmlspecialchars($cls_label); ?>
                                        </span>
                                    </div>
                                    <h3 style="font-size: 1.05rem; font-weight: 600; margin-bottom: 0.5rem; color: var(--text-main);"><?php echo htmlspecialchars($t_act['title']); ?></h3>
                                </div>
                                
                                <div style="display: flex; gap: 1rem; font-size: 0.8rem; font-weight: 500; border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 1rem; color: var(--text-muted);">
                                    <span style="color: var(--success);"><i class="fa-solid fa-check"></i> Submitted: <?php echo $subs_c; ?></span>
                                    <span style="color: var(--warning);"><i class="fa-solid fa-clock"></i> Pending: <?php echo $pend_c; ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($current_activity): ?>
                <!-- SELECTED CLASS INSTANCE DETAILS CARD -->
                <div class="module-card">
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem; flex-wrap: wrap;">
                        <span class="sys-tag">ID: <?php echo $current_activity['id']; ?></span>
                        <span class="sys-tag accent"><?php echo $current_activity['type_name']; ?></span>
                    </div>
                    <h1 style="font-size: 1.6rem; margin-bottom: 1rem; font-weight: 700; color: var(--navy-primary);"><?php echo htmlspecialchars($current_activity['title']); ?></h1>
                    <p style="color: var(--text-muted); font-size: 0.95rem; max-width: 900px; margin-bottom: 1.5rem; line-height: 1.6;"><?php echo htmlspecialchars($current_activity['description'] ?: 'No description provided.'); ?></p>
                    
                    <button class="btn btn-outline" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($current_activity), ENT_QUOTES, 'UTF-8'); ?>)">
                        <i class="fa-solid fa-pen"></i> Edit Details
                    </button>
                </div>

                <!-- SUBMISSIONS & EVALUATION SHEET FOR THIS CLASS -->
                <div class="module-card">
                    <h3 style="margin-bottom: 1.5rem; font-size: 1.25rem; font-weight: 600; color: var(--navy-primary);">Submissions</h3>
                    <div class="table-responsive">
                        <?php 
                        $activity_submissions = get_activity_submissions($current_activity['id']); 
                        if (empty($activity_submissions)): 
                        ?>
                            <p style="padding: 2rem; font-size: 0.9rem; font-weight: 500; text-align: center; color: var(--text-muted); margin: 0;">No submissions yet.</p>
                        <?php else: ?>
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>PRN</th>
                                        <th>Name</th>
                                        <th>Status</th>
                                        <th>File</th>
                                        <th>Score</th>
                                        <th>Manual Grade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activity_submissions as $sub): 
                                        $status_text = $sub['is_late'] ? 'Late' : 'On Time';
                                        $file_type = strtolower($sub['file_type'] ?? pathinfo($sub['original_filename'], PATHINFO_EXTENSION));
                                    ?>
                                    <tr>
                                        <td>
                                            <span style="font-weight: 600; font-size: 0.85rem; color: var(--text-main);">
                                                <?php echo htmlspecialchars($sub['student_prn'] ?: '—'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="font-weight: 500; font-size: 0.9rem; color: var(--text-main);"><?php echo htmlspecialchars($sub['student_name'] ?: 'UNKNOWN'); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($sub['is_late']): ?>
                                                <span class="sys-tag warning"><?php echo $status_text; ?></span>
                                            <?php else: ?>
                                                <span class="sys-tag success"><?php echo $status_text; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.75rem;" 
                                                    onclick="openFacultyPreview(<?php echo $sub['id']; ?>, '<?php echo htmlspecialchars(addslashes($file_type)); ?>', '<?php echo htmlspecialchars(addslashes($sub['original_filename'])); ?>')">
                                                <i class="fa-regular fa-eye"></i> View
                                            </button>
                                        </td>
                                        <td>
                                            <strong style="font-size: 1rem; color: <?php echo $sub['marks'] !== null ? 'var(--success)' : 'var(--text-main)'; ?>;">
                                                <?php echo $sub['marks'] !== null ? $sub['marks'] : $current_activity['total_marks']; ?>
                                            </strong>
                                            <span style="font-size: 0.75rem; color: var(--text-muted);">/ <?php echo $current_activity['total_marks']; ?></span>
                                        </td>
                                        <td>
                                            <form action="faculty_dashboard.php?view=<?php echo $current_activity['type']; ?>&id=<?php echo $current_activity['id']; ?>" method="POST" style="display: flex; gap: 0.5rem; align-items: center; margin: 0;">
                                                <input type="hidden" name="action" value="grade_submission">
                                                <input type="hidden" name="submission_id" value="<?php echo $sub['id']; ?>">
                                                <input type="number" name="marks" value="<?php echo $sub['marks']; ?>" class="form-control" style="width: 80px; padding: 0.3rem; font-size: 0.85rem; text-align: center; margin-bottom: 0;" max="<?php echo $current_activity['total_marks']; ?>" min="0" step="0.5" required>
                                                <button type="submit" class="btn btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.75rem; margin: 0;">
                                                    Save
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- NON-SUBMITTED STUDENTS STRICTLY FOR THIS CLASS INSTANCE -->
                <?php 
                $selected_class_id = (int)($_GET['filter_class'] ?? 0);
                $pending_students = get_pending_activity_students($current_activity['id'], $current_activity['target_type'] ?? 'all', $current_activity['target_id'] ?? 0, $selected_class_id);
                ?>
                <div class="module-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap;">
                        <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--navy-primary); margin: 0;">Pending Students</h3>
                        <span class="sys-tag danger">Pending: <?php echo count($pending_students); ?></span>
                    </div>

                    <div class="table-responsive">
                        <?php if (empty($pending_students)): ?>
                            <div style="text-align: center; padding: 2rem; font-weight: 500; font-size: 0.9rem; color: var(--text-muted);">
                                All students have submitted.
                            </div>
                        <?php else: ?>
                            <table class="custom-table">
                                <thead>
                                    <tr>
                                        <th>PRN</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_students as $p_stu): ?>
                                    <tr>
                                        <td>
                                            <span style="font-weight: 600; font-size: 0.85rem; color: var(--text-main);">
                                                <?php echo htmlspecialchars($p_stu['prn'] ?: '—'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="font-weight: 500; font-size: 0.9rem; color: var(--text-main);"><?php echo htmlspecialchars($p_stu['student_name'] ?: 'UNKNOWN'); ?></strong>
                                        </td>
                                        <td style="font-size: 0.85rem; color: var(--text-muted);">
                                            <?php echo htmlspecialchars($p_stu['email'] ?: '—'); ?>
                                        </td>
                                        <td>
                                            <span class="sys-tag danger" style="background: transparent; border: 1px solid var(--danger);">Not Submitted</span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        </main> <!-- End main-content -->
    </div>
</div>

<!-- GLOBAL MODAL: EDIT ACTIVITY -->
<div class="modal-overlay" id="editActivityModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit Activity</h3>
            <button class="close-btn" onclick="closeModal('editActivityModal')">&times;</button>
        </div>
        
        <form action="faculty_dashboard.php?view=<?php echo $view; ?>&id=<?php echo $id; ?>" method="POST">
            <input type="hidden" name="action" value="edit_activity">
            <input type="hidden" name="activity_id" id="edit_id">
            
            <div class="form-group">
                <label>Title *</label>
                <input type="text" id="edit_title" name="title" class="form-control" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Subject *</label>
                    <select id="edit_subject" name="subject" class="form-select-custom" required>
                        <option value="BEE">BEE</option>
                        <option value="Chemistry">Chemistry</option>
                        <option value="Physics">Physics</option>
                        <option value="Maths">Maths</option>
                        <option value="Computer Science">Computer Science</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Unit *</label>
                    <select id="edit_unit" name="unit" class="form-select-custom" required>
                        <option value="Unit 1">Unit 1</option>
                        <option value="Unit 2">Unit 2</option>
                        <option value="Unit 3">Unit 3</option>
                        <option value="Unit 4">Unit 4</option>
                        <option value="Unit 5">Unit 5</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Category *</label>
                    <select id="edit_type" name="type" class="form-select-custom" required>
                        <option value="quiz">Quiz</option>
                        <option value="poster_making">Poster Making</option>
                        <option value="ppt">PPT</option>
                        <option value="case_study">Case Study</option>
                        <option value="gd">Group Discussion</option>
                        <option value="mini_project">Mini Project</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Course *</label>
                    <input type="text" id="edit_course" name="course" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Batch *</label>
                    <input type="text" id="edit_batch" name="batch" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; padding: 1.5rem; background: var(--bg-body); border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Assign To *</label>
                    <select id="edit_target_type" name="target_type" class="form-select-custom" required>
                        <option value="all">Entire Class / All</option>
                        <option value="group">Specific Group</option>
                        <option value="individual">Specific Student</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label>Target ID</label>
                    <input type="number" id="edit_target_id" name="target_id" class="form-control" placeholder="Optional">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Deadline *</label>
                    <input type="datetime-local" id="edit_deadline" name="deadline" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Total Marks *</label>
                    <input type="number" id="edit_total_marks" name="total_marks" class="form-control" min="5" max="500" required>
                </div>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea id="edit_description" name="description" class="form-control" rows="4"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
                <button type="button" class="btn btn-outline" onclick="closeModal('editActivityModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- GLOBAL MODAL: ADD PRN TO CLASS -->
<div class="modal-overlay" id="modalAddPrn">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Add Students to <span id="add_prn_class_title" style="color: var(--blue-accent);"></span></h3>
            <button class="close-btn" onclick="closeModal('modalAddPrn')">&times;</button>
        </div>
        <form action="faculty_dashboard.php?view=classes" method="POST">
            <input type="hidden" name="action" value="add_students_to_class">
            <input type="hidden" name="class_id" id="add_prn_class_id">
            <div class="form-group">
                <label>Student PRNs *</label>
                <textarea name="prn_list" class="form-control" rows="6" placeholder="Enter PRNs separated by commas or newlines..." required></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-outline" onclick="closeModal('modalAddPrn')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Students</button>
            </div>
        </form>
    </div>
</div>

<!-- GLOBAL MODAL: VIEW CLASS MEMBERS -->
<div class="modal-overlay" id="modalViewStudents">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Students in <span id="view_students_class_title" style="color: var(--blue-accent);"></span></h3>
            <button class="close-btn" onclick="closeModal('modalViewStudents')">&times;</button>
        </div>
        <div id="view_students_list_container" style="max-height: 400px; overflow-y: auto; margin-bottom: 1.5rem; border: 1px solid var(--border-color); border-radius: var(--radius-md);">
            <!-- JS populated -->
        </div>
        <div style="display: flex; justify-content: flex-end; border-top: 1px solid var(--border-color); padding-top: 1.5rem;">
            <button type="button" class="btn btn-outline" onclick="closeModal('modalViewStudents')">Close</button>
        </div>
    </div>
</div>

<!-- GLOBAL MODAL: FACULTY INLINE FILE PREVIEW -->
<div class="modal-overlay" id="modalFacultyPreview">
    <div class="modal-content" style="max-width: 900px; width: 95%; max-height: 90vh; padding: 0; overflow: hidden; display: flex; flex-direction: column;">
        <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 2rem; margin: 0; border-bottom: 1px solid var(--border-color); background: var(--bg-body);">
            <h3 id="facultyPreviewTitle" style="font-size: 1.15rem; margin: 0;">File Preview</h3>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="#" id="facultyPreviewDownloadBtn" class="btn btn-primary" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">
                    <i class="fa-solid fa-download"></i> Download
                </a>
                <button class="close-btn" onclick="closeModal('modalFacultyPreview')" style="font-size: 1.5rem;">&times;</button>
            </div>
        </div>
        <div class="modal-body" style="background: var(--bg-body); padding: 0; text-align: center; flex: 1; overflow: hidden;">
            <iframe id="facultyPreviewFrame" style="width: 100%; height: 100%; border: none;" class="d-none"></iframe>
            <img id="facultyPreviewImg" style="max-width: 100%; max-height: 100%; object-fit: contain; padding: 2rem;" class="d-none" alt="Submission Preview">
            <div id="facultyPreviewUnsupported" style="padding: 4rem 2rem; font-weight: 500; color: var(--text-muted);" class="d-none">
                <i class="fa-solid fa-file-circle-xmark" style="font-size: 3rem; margin-bottom: 1rem; color: var(--border-color);"></i>
                <p>Preview unavailable. Please download the file to view.</p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {

  let currentUnit = 'all';
  let currentSubject = 'all';

  const activityCards = document.querySelectorAll('.activity-card');

  function filterCards() {
    activityCards.forEach(card => {
      const cardUnit = card.getAttribute('data-unit') || 'Unit 1';
      const cardSubject = card.getAttribute('data-subject') || 'General';

      const matchUnit = (currentUnit === 'all' || cardUnit === currentUnit);
      const matchSubject = (currentSubject === 'all' || cardSubject === currentSubject);

      if (matchUnit && matchSubject) {
        card.style.display = 'flex';
      } else {
        card.style.display = 'none';
      }
    });
  }

  const unitPills = document.querySelectorAll('#unitFilterPills .pill');
  unitPills.forEach(pill => {
    pill.addEventListener('click', () => {
      unitPills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      currentUnit = pill.getAttribute('data-unit-filter');
      filterCards();
    });
  });

  const subjectPills = document.querySelectorAll('#subjectFilterPills .pill');
  subjectPills.forEach(pill => {
    pill.addEventListener('click', () => {
      subjectPills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      currentSubject = pill.getAttribute('data-subject-filter');
      filterCards();
    });
  });

  window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'flex';
  };

  window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        if(modalId === 'modalFacultyPreview') {
            document.getElementById('facultyPreviewFrame').src = '';
            document.getElementById('facultyPreviewImg').src = '';
        }
    }
  };

  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
          overlay.style.display = 'none';
          if(overlay.id === 'modalFacultyPreview') {
            document.getElementById('facultyPreviewFrame').src = '';
            document.getElementById('facultyPreviewImg').src = '';
          }
      }
    });
  });
});

function openFacultyPreview(subId, fileType, fileName) {
    const frame = document.getElementById('facultyPreviewFrame');
    const img = document.getElementById('facultyPreviewImg');
    const unsupported = document.getElementById('facultyPreviewUnsupported');
    const title = document.getElementById('facultyPreviewTitle');
    const dlBtn = document.getElementById('facultyPreviewDownloadBtn');

    title.innerHTML = `${escapeHtml(fileName)}`;
    dlBtn.href = `student_submit.php?action=download&id=${subId}`;
    
    frame.classList.add('d-none');
    img.classList.add('d-none');
    unsupported.classList.add('d-none');
    frame.src = '';
    img.src = '';

    const previewUrl = `student_submit.php?action=preview&id=${subId}`;
    const ext = (fileType || '').toLowerCase();

    if (ext === 'pdf') {
        frame.src = previewUrl;
        frame.classList.remove('d-none');
    } else if (['jpg', 'jpeg', 'png'].includes(ext)) {
        img.src = previewUrl;
        img.classList.remove('d-none');
    } else {
        unsupported.classList.remove('d-none');
    }

    openModal('modalFacultyPreview');
}

function openAddPrnModal(classId, className) {
    document.getElementById('add_prn_class_id').value = classId;
    document.getElementById('add_prn_class_title').innerText = className;
    openModal('modalAddPrn');
}

function openViewStudentsModal(classId, className, students) {
    document.getElementById('view_students_class_title').innerText = className;
    const container = document.getElementById('view_students_list_container');
    
    if (!students || students.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 2rem 1rem; font-weight: 500; color: var(--text-muted);">No students in this class.</div>';
    } else {
        let html = '<table class="custom-table" style="width: 100%; border: none;"><thead><tr><th>PRN</th><th>Name</th><th>Email</th></tr></thead><tbody>';
        students.forEach(st => {
            const stName = st.student_name || 'Unknown';
            const stEmail = st.student_email || '—';
            html += `<tr>
                <td style="font-weight:600; font-size: 0.85rem;">${escapeHtml(st.student_prn)}</td>
                <td style="font-weight: 500; font-size: 0.9rem;">${escapeHtml(stName)}</td>
                <td style="font-size:0.85rem; color: var(--text-muted);">${escapeHtml(stEmail)}</td>
            </tr>`;
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    }
    openModal('modalViewStudents');
}

function escapeHtml(text) {
    if (!text) return '';
    return text.toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

function openEditModal(act) {
    document.getElementById('edit_id').value = act.id;
    document.getElementById('edit_title').value = act.title;
    document.getElementById('edit_subject').value = act.subject || 'BEE';
    document.getElementById('edit_unit').value = act.unit || 'Unit 1';
    document.getElementById('edit_type').value = act.type;
    document.getElementById('edit_course').value = act.course;
    document.getElementById('edit_batch').value = act.batch;
    
    document.getElementById('edit_target_type').value = act.target_type || 'all';
    document.getElementById('edit_target_id').value = act.target_id || '';
    
    if (act.deadline) {
        const d = new Date(act.deadline);
        const formatted = d.toISOString().slice(0, 16);
        document.getElementById('edit_deadline').value = formatted;
    }
    
    document.getElementById('edit_total_marks').value = act.total_marks;
    document.getElementById('edit_description').value = act.description || '';

    openModal('editActivityModal');
}

function autoFillCourse(subj) {
    const courseInput = document.getElementById('create_course');
    if (!courseInput) return;
    const courseMap = {
        'BEE': 'BEE101 - Basic Electrical Engineering',
        'Chemistry': 'CH101 - Engineering Chemistry',
        'Physics': 'PH101 - Engineering Physics',
        'Maths': 'MA101 - Engineering Mathematics',
        'Computer Science': 'CS302 - Computer Science & Engg'
    };
    if (courseMap[subj]) {
        courseInput.value = courseMap[subj];
    }
}

// Sidebar toggle for mobile
const sidebarToggle = document.getElementById('sidebarToggle');
const erpSidebar = document.getElementById('erpSidebar');
if (sidebarToggle && erpSidebar) {
    sidebarToggle.addEventListener('click', () => {
        erpSidebar.classList.toggle('show');
    });
}
</script>

<?php 
// Include End Session Modal if it exists
@include_once __DIR__ . '/includes/end_session_modal.php'; 
?>
</body>
</html>