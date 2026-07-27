<?php
// hod_dashboard.php - HOD & GFM Monitoring Portal

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Anti-caching headers to prevent browser back-button access after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

require_once __DIR__ . '/config/db.php';

// 1. AUTO-INITIALIZE HOD MANAGEMENT TABLES
function init_hod_tables() {
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS hod_faculty_mapping (
            id INT AUTO_INCREMENT PRIMARY KEY,
            hod_id INT NOT NULL,
            faculty_id INT NOT NULL,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_hod_fac (hod_id, faculty_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Ensure activities table tracks WHICH faculty created it
        $pdo->exec("ALTER TABLE activities ADD COLUMN IF NOT EXISTS faculty_id INT NULL AFTER activity_id");

        try {
            $pdo->exec("ALTER TABLE faculty_classes ADD COLUMN academic_year VARCHAR(50) DEFAULT 'FY'");
        } catch (PDOException $e) {}
    } catch (PDOException $e) {
        error_log("Table Init Error: " . $e->getMessage());
    }
}
init_hod_tables();

// Ensure user is authorized
$role = strtolower($_SESSION['role'] ?? '');
if (empty($_SESSION['user_id']) || !in_array($role, ['hod', 'gfm', 'admin'])) {
    header('Location: auth/login.php');
    exit;
}

$hod_id = (int)$_SESSION['user_id'];
$hod_name = $_SESSION['full_name'] ?? 'HOD / GFM';

$view = $_GET['view'] ?? 'dashboard';
$message = '';
$success_message = '';

// ----------------------------------------------------
// 2. ACTION HANDLERS
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    if ($_POST['form_action'] === 'add_faculty_by_email') {
        $email = trim($_POST['faculty_email']);
        $stmt = $pdo->prepare("SELECT user_id, name FROM users WHERE email = ? AND LOWER(role) = 'faculty'");
        $stmt->execute([$email]);
        $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($faculty) {
            try {
                $stmtInsert = $pdo->prepare("INSERT INTO hod_faculty_mapping (hod_id, faculty_id) VALUES (?, ?)");
                $stmtInsert->execute([$hod_id, $faculty['user_id']]);
                $success_message = "Successfully linked Faculty: " . htmlspecialchars($faculty['name']);
            } catch (PDOException $e) {
                $message = "This faculty member is already on your monitoring list.";
            }
        } else {
            $message = "No Faculty account found with the email '{$email}'. Ensure they are registered.";
        }
    } elseif ($_POST['form_action'] === 'remove_faculty') {
        $fac_id_to_remove = (int)$_POST['faculty_id'];
        $stmt = $pdo->prepare("DELETE FROM hod_faculty_mapping WHERE hod_id = ? AND faculty_id = ?");
        if ($stmt->execute([$hod_id, $fac_id_to_remove])) {
            $success_message = "Faculty removed from your monitoring list.";
        }
    }
}

// Handle AJAX Logout
$input = json_decode(file_get_contents('php://input'), true);
if ($input && isset($input['action']) && $input['action'] === 'logout') {
    header('Content-Type: application/json');
    session_unset();
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

// ----------------------------------------------------
// 3. FETCH REAL DB DATA FOR DRILL-DOWN DASHBOARD
// ----------------------------------------------------

// LEVEL 1: Fetch all faculty mapped to this HOD (Used in Directory list)
$stmtFac = $pdo->prepare("
    SELECT u.user_id, u.name, u.email,
           (SELECT COUNT(*) FROM faculty_classes fc WHERE fc.faculty_id = u.user_id) AS total_classes,
           (SELECT COUNT(*) FROM activities a WHERE a.faculty_id = u.user_id) AS total_activities
    FROM hod_faculty_mapping hfm
    JOIN users u ON hfm.faculty_id = u.user_id
    WHERE hfm.hod_id = ?
    ORDER BY u.name ASC
");
$stmtFac->execute([$hod_id]);
$mapped_faculty = $stmtFac->fetchAll(PDO::FETCH_ASSOC) ?: [];

// LEVEL 2: ACADEMIC YEARS STATS
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
        JOIN hod_faculty_mapping hfm ON hfm.faculty_id = fc.faculty_id
        WHERE hfm.hod_id = ? AND UPPER(fc.academic_year) = UPPER(?)
    ");
    $stmtY->execute([$hod_id, $y_code]);
    $rowY = $stmtY->fetch(PDO::FETCH_ASSOC);
    $year_stats[$y_code] = [
        'name' => $y_name,
        'subject_count' => (int)($rowY['subject_count'] ?? 0),
        'faculty_count' => (int)($rowY['faculty_count'] ?? 0),
        'class_count' => (int)($rowY['class_count'] ?? 0)
    ];
}

// DRILL-DOWN 1: Subjects in a selected Year
$selected_year = $_GET['year'] ?? 'FY';
$year_subjects = [];
if ($view === 'hod_subjects' && !empty($selected_year)) {
    $stmtSubj = $pdo->prepare("
        SELECT fc.subject_code, fc.academic_year,
               COUNT(DISTINCT fc.faculty_id) AS faculty_count,
               COUNT(fc.class_id) AS class_count
        FROM faculty_classes fc
        JOIN hod_faculty_mapping hfm ON hfm.faculty_id = fc.faculty_id
        WHERE hfm.hod_id = ? AND UPPER(fc.academic_year) = UPPER(?)
        GROUP BY fc.subject_code, fc.academic_year
        ORDER BY fc.subject_code ASC
    ");
    $stmtSubj->execute([$hod_id, $selected_year]);
    $year_subjects = $stmtSubj->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// DRILL-DOWN 2: Faculty & Classes for a selected Subject in a Year
$selected_subject = $_GET['subject'] ?? '';
$subject_classes = [];
if ($view === 'hod_subject_classes' && !empty($selected_year) && !empty($selected_subject)) {
    $stmtSubjCls = $pdo->prepare("
        SELECT fc.class_id, fc.class_name, fc.subject_code, fc.academic_year, fc.faculty_id,
               u.name AS faculty_name, u.email AS faculty_email,
               (SELECT COUNT(*) FROM users us WHERE LOWER(us.role) = 'student' AND us.department = fc.department AND us.academic_year = fc.academic_year AND us.division = fc.division) AS student_count
        FROM faculty_classes fc
        JOIN hod_faculty_mapping hfm ON hfm.faculty_id = fc.faculty_id
        JOIN users u ON u.user_id = fc.faculty_id
        WHERE hfm.hod_id = ? AND UPPER(fc.academic_year) = UPPER(?) AND UPPER(fc.subject_code) = UPPER(?)
        ORDER BY u.name ASC, fc.class_name ASC
    ");
    $stmtSubjCls->execute([$hod_id, $selected_year, $selected_subject]);
    $subject_classes = $stmtSubjCls->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// DRILL-DOWN 3: Fetch specific Faculty's Classes directly
$faculty_info = null;
$faculty_classes = [];
if ($view === 'faculty_classes' && isset($_GET['fid'])) {
    $fid = (int)$_GET['fid'];
    $check = $pdo->prepare("SELECT 1 FROM hod_faculty_mapping WHERE hod_id = ? AND faculty_id = ?");
    $check->execute([$hod_id, $fid]);
    
    if ($check->fetchColumn()) {
        $stmtFacInfo = $pdo->prepare("SELECT name, email FROM users WHERE user_id = ?");
        $stmtFacInfo->execute([$fid]);
        $faculty_info = $stmtFacInfo->fetch(PDO::FETCH_ASSOC);

        $stmtClasses = $pdo->prepare("
            SELECT fc.class_id, fc.class_name, fc.subject_code, fc.academic_year,
                   (SELECT COUNT(*) FROM users us WHERE LOWER(us.role) = 'student' AND us.department = fc.department AND us.academic_year = fc.academic_year AND us.division = fc.division) AS student_count
            FROM faculty_classes fc WHERE fc.faculty_id = ?
            ORDER BY fc.created_at DESC
        ");
        $stmtClasses->execute([$fid]);
        $faculty_classes = $stmtClasses->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $message = "Unauthorized access to this faculty.";
        $view = 'reports';
    }
}

// DRILL-DOWN 4: Fetch specific Class Report with 20-Mark Scaled Average Performance
$selected_class = null;
$class_info = null;
$class_activities = [];
$class_students = [];
$total_students_in_class = 0;
$class_total_max_marks = 0;

if ($view === 'class_report' && isset($_GET['fid']) && isset($_GET['cid'])) {
    $fid = (int)$_GET['fid'];
    $cid = (int)$_GET['cid'];
    
    $check = $pdo->prepare("SELECT 1 FROM hod_faculty_mapping WHERE hod_id = ? AND faculty_id = ?");
    $check->execute([$hod_id, $fid]);
    
    if ($check->fetchColumn()) {
        $stmtFacInfo = $pdo->prepare("SELECT name FROM users WHERE user_id = ?");
        $stmtFacInfo->execute([$fid]);
        $faculty_info = $stmtFacInfo->fetch(PDO::FETCH_ASSOC);

        $stmtClassInfo = $pdo->prepare("SELECT class_name, subject_code, academic_year FROM faculty_classes WHERE class_id = ? AND faculty_id = ?");
        $stmtClassInfo->execute([$cid, $fid]);
        $class_info = $stmtClassInfo->fetch(PDO::FETCH_ASSOC);

        if ($class_info) {
            $selected_class = $class_info;

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
            $total_students_in_class = count($class_students);

            // Activities assigned to this class or all
            $stmtActs = $pdo->prepare("
                SELECT a.activity_id, a.title, a.due_date, a.max_marks,
                       (SELECT COUNT(*) FROM submissions s WHERE s.activity_id = a.activity_id) AS submitted_count,
                       (SELECT AVG(marks) FROM submissions s WHERE s.activity_id = a.activity_id AND marks IS NOT NULL) AS avg_score
                FROM activities a 
                WHERE a.faculty_id = ? AND (a.target_type = 'all' OR (a.target_type = 'class' AND a.target_id = ?))
                ORDER BY a.due_date DESC
            ");
            $stmtActs->execute([$fid, $cid]);
            $class_activities = $stmtActs->fetchAll(PDO::FETCH_ASSOC);

            // Total possible max marks across all activities for this class
            foreach ($class_activities as $act) {
                $class_total_max_marks += (float)($act['max_marks'] ?? 0);
            }

            // Calculate each student's total obtained marks, percentage, and 20-mark scaled score
            foreach ($class_students as &$st) {
                $stmtStMarks = $pdo->prepare("
                    SELECT s.activity_id, s.marks, a.max_marks
                    FROM submissions s
                    JOIN activities a ON s.activity_id = a.activity_id
                    LEFT JOIN users u ON (UPPER(u.username) = UPPER(?) OR UPPER(u.linked_student_prn) = UPPER(?)) AND LOWER(u.role) = 'student'
                    WHERE (s.student_id = u.user_id OR s.student_id IN (SELECT student_id FROM students WHERE user_id = u.user_id))
                      AND a.faculty_id = ? AND (a.target_type = 'all' OR (a.target_type = 'class' AND a.target_id = ?))
                ");
                $stmtStMarks->execute([$st['student_prn'], $st['student_prn'], $fid, $cid]);
                $st_subs = $stmtStMarks->fetchAll(PDO::FETCH_ASSOC) ?: [];
                
                $st_obtained = 0;
                $evaluated_count = 0;
                foreach ($st_subs as $sb) {
                    if ($sb['marks'] !== null) {
                        $st_obtained += (float)$sb['marks'];
                        $evaluated_count++;
                    }
                }
                
                $st['obtained_marks'] = $st_obtained;
                $st['total_max_marks'] = $class_total_max_marks;
                $st['evaluated_count'] = $evaluated_count;
                $st['percentage'] = $class_total_max_marks > 0 ? round(($st_obtained / $class_total_max_marks) * 100, 2) : 0;
                // Scaled Score converted out of 20: (Total Marks Obtained / Total Max Marks) * 20
                $st['scaled_score_20'] = $class_total_max_marks > 0 ? round(($st_obtained / $class_total_max_marks) * 20, 2) : 0;
            }
            unset($st);
        } else {
            $message = "Class not found.";
            $view = 'faculty_classes';
        }
    } else {
        $message = "Unauthorized access.";
        $view = 'reports';
    }
}

// CUMULATIVE DRILL-DOWN 1: Classes in a selected Academic Year (Direct Class Selection)
$cum_year_classes = [];
if ($view === 'cumulative_classes' && !empty($selected_year)) {
    $stmtCumCls = $pdo->prepare("
        SELECT fc.class_id, fc.class_name, fc.subject_code, fc.academic_year, fc.faculty_id,
               u.name AS faculty_name, u.email AS faculty_email,
               (SELECT COUNT(*) FROM users us WHERE LOWER(us.role) = 'student' AND us.department = fc.department AND us.academic_year = fc.academic_year AND us.division = fc.division) AS student_count
        FROM faculty_classes fc
        JOIN hod_faculty_mapping hfm ON hfm.faculty_id = fc.faculty_id
        JOIN users u ON u.user_id = fc.faculty_id
        WHERE hfm.hod_id = ? AND UPPER(fc.academic_year) = UPPER(?)
        ORDER BY fc.class_name ASC, u.name ASC
    ");
    $stmtCumCls->execute([$hod_id, $selected_year]);
    $cum_year_classes = $stmtCumCls->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

// CUMULATIVE DRILL-DOWN 2: Cumulative Master Sheet for a selected Class
$cum_class_info = null;
$cum_class_students = [];
$cum_distinct_subjects = [];
$cum_subject_max_marks = [];

if ($view === 'cumulative_report' && isset($_GET['cid'])) {
    $cid = (int)$_GET['cid'];
    
    $stmtCumInfo = $pdo->prepare("
        SELECT fc.class_id, fc.class_name, fc.subject_code, fc.academic_year, fc.faculty_id,
               u.name AS faculty_name, u.email AS faculty_email
        FROM faculty_classes fc
        JOIN hod_faculty_mapping hfm ON hfm.faculty_id = fc.faculty_id
        JOIN users u ON u.user_id = fc.faculty_id
        WHERE fc.class_id = ? AND hfm.hod_id = ?
    ");
    $stmtCumInfo->execute([$cid, $hod_id]);
    $cum_class_info = $stmtCumInfo->fetch(PDO::FETCH_ASSOC);

    if ($cum_class_info) {
        $cum_fid = (int)$cum_class_info['faculty_id'];

        // Enrolled Students
        $stmtCumSt = $pdo->prepare("
            SELECT u.username AS student_prn, CURRENT_TIMESTAMP AS added_at, u.name AS student_name, u.email AS student_email, st.roll_no
            FROM faculty_classes fc
            JOIN users u ON LOWER(u.role) = 'student' AND u.department = fc.department AND u.academic_year = fc.academic_year AND u.division = fc.division
            LEFT JOIN students st ON st.user_id = u.user_id
            WHERE fc.class_id = ?
            ORDER BY u.name ASC
        ");
        $stmtCumSt->execute([$cid]);
        $cum_class_students = $stmtCumSt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // All activities for this class / faculty to find all subjects taught
        $stmtCumActs = $pdo->prepare("
            SELECT a.activity_id, a.subject, a.max_marks
            FROM activities a
            WHERE a.faculty_id = ? AND (a.target_type = 'all' OR (a.target_type = 'class' AND a.target_id = ?))
        ");
        $stmtCumActs->execute([$cum_fid, $cid]);
        $cum_activities = $stmtCumActs->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Collect distinct subject names
        $raw_subjects = array_filter(array_map('trim', array_column($cum_activities, 'subject')));
        $cum_distinct_subjects = array_values(array_unique($raw_subjects));
        if (empty($cum_distinct_subjects) && !empty($cum_class_info['subject_code'])) {
            $cum_distinct_subjects = [trim($cum_class_info['subject_code'])];
        }
        if (empty($cum_distinct_subjects)) {
            $cum_distinct_subjects = ['General'];
        }

        // Calculate max marks per subject for this class
        foreach ($cum_distinct_subjects as $sub_name) {
            $cum_subject_max_marks[$sub_name] = 0;
            foreach ($cum_activities as $act) {
                if (strcasecmp($act['subject'] ?? '', $sub_name) === 0 || count($cum_distinct_subjects) === 1) {
                    $cum_subject_max_marks[$sub_name] += (float)($act['max_marks'] ?? 0);
                }
            }
        }

        // Compute each student's subject-wise marks and 20-mark scaled score
        foreach ($cum_class_students as &$c_st) {
            $c_st['subject_scores_20'] = [];
            $c_st['subject_obtained'] = [];
            $c_st['subject_max'] = [];
            
            $total_obtained_all_subs = 0;
            $total_max_all_subs = 0;

            foreach ($cum_distinct_subjects as $sub_name) {
                $stmtSubMarks = $pdo->prepare("
                    SELECT s.marks, a.max_marks
                    FROM submissions s
                    JOIN activities a ON s.activity_id = a.activity_id
                    LEFT JOIN users u ON (UPPER(u.username) = UPPER(?) OR UPPER(u.linked_student_prn) = UPPER(?)) AND LOWER(u.role) = 'student'
                    WHERE (s.student_id = u.user_id OR s.student_id IN (SELECT student_id FROM students WHERE user_id = u.user_id))
                      AND a.faculty_id = ? AND (a.target_type = 'all' OR (a.target_type = 'class' AND a.target_id = ?))
                      AND (UPPER(a.subject) = UPPER(?) OR ? = 1)
                ");
                $single_flag = (count($cum_distinct_subjects) === 1) ? 1 : 0;
                $stmtSubMarks->execute([$c_st['student_prn'], $c_st['student_prn'], $cum_fid, $cid, $sub_name, $single_flag]);
                $sub_subs = $stmtSubMarks->fetchAll(PDO::FETCH_ASSOC) ?: [];

                $sub_obtained = 0;
                foreach ($sub_subs as $ss) {
                    if ($ss['marks'] !== null) {
                        $sub_obtained += (float)$ss['marks'];
                    }
                }

                $max_for_sub = $cum_subject_max_marks[$sub_name] ?? 0;
                $score_20 = ($max_for_sub > 0) ? round(($sub_obtained / $max_for_sub) * 20, 2) : 0;

                $c_st['subject_scores_20'][$sub_name] = $score_20;
                $c_st['subject_obtained'][$sub_name] = $sub_obtained;
                $c_st['subject_max'][$sub_name] = $max_for_sub;

                $total_obtained_all_subs += $sub_obtained;
                $total_max_all_subs += $max_for_sub;
            }

            $c_st['total_obtained_all'] = $total_obtained_all_subs;
            $c_st['total_max_all'] = $total_max_all_subs;
            $c_st['overall_percentage'] = $total_max_all_subs > 0 ? round(($total_obtained_all_subs / $total_max_all_subs) * 100, 2) : 0;
            $c_st['overall_score_20'] = $total_max_all_subs > 0 ? round(($total_obtained_all_subs / $total_max_all_subs) * 20, 2) : 0;
        }
        unset($c_st);
    } else {
        $message = "Unauthorized access or class not found.";
        $view = 'cumulative';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard | SAAES</title>
    
    <!-- Professional Fonts matching Landing Page -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=JetBrains+Mono:wght@100;400;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- PDF and Excel Libraries -->
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
      /* Blueprint Grid */
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
    .menu-label { font-family: var(--font-mono); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; color: var(--text-tech); margin: 1.5rem 0.5rem 0.5rem; font-weight: 700;}
    
    .sidebar-link {
      display: flex; align-items: center; gap: 0.85rem; padding: 0.8rem 1rem;
      color: var(--text-tech); font-family: var(--font-mono);
      font-size: 0.85rem; font-weight: 600; transition: all 0.2s ease;
      border: 2px solid transparent; position: relative;
    }
    .sidebar-link::before {
        content: '>'; position: absolute; left: 5px; opacity: 0; color: var(--text-dark); transition: 0.2s;
    }
    .sidebar-link:hover { color: var(--text-dark); padding-left: 1.5rem; }
    .sidebar-link:hover::before { opacity: 1; }
    .sidebar-link.active {
      background: var(--bg-base); color: var(--text-dark); 
      border: 2px solid var(--text-dark);
      box-shadow: 4px 4px 0px rgba(15, 23, 42, 0.1);
    }
    .sidebar-link i { font-size: 1.1rem; width: 22px; text-align: center; }

    .sidebar-user {
      padding: 1.25rem; border-top: var(--border-harsh);
      display: flex; align-items: center; gap: 0.75rem; background: var(--bg-panel);
    }
    .avatar {
      width: 40px; height: 40px; border: 2px solid var(--text-dark); background: var(--bg-base);
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
      background: var(--bg-base); border: 2px solid var(--text-dark);
      padding: 3rem; position: relative; overflow: hidden;
      clip-path: polygon(0 0, calc(100% - 30px) 0, 100% 30px, 100% 100%, 0 100%);
    }
    .hero-banner::after {
        content: ''; position: absolute; top: 0; right: 0; width: 30px; height: 30px; background: var(--text-dark);
    }
    .hero-content { position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; }

    /* ================= METADATA LABELS (TAGS) ================= */
    .sys-tag { 
        font-family: var(--font-mono); font-size: 0.75rem; font-weight: 700; padding: 0.3rem 0.6rem; 
        border: 1px solid var(--text-dark); color: var(--text-dark); text-transform: uppercase; display: inline-flex; align-items: center; gap: 0.4rem;
    }
    .sys-tag.accent { background: rgba(15, 23, 42, 0.05); color: var(--accent-main); border-color: var(--accent-main); }

    /* ================= BUTTONS ================= */
    .btn {
        font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem; text-transform: uppercase;
        padding: 0.8rem 1.5rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
        background: var(--bg-base); color: var(--text-dark); border: 2px solid var(--text-dark);
        position: relative; overflow: hidden; z-index: 1;
        clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
        transition: color 0.3s; cursor: pointer; text-decoration: none;
    }
    .btn::before {
        content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
        background: var(--text-dark); z-index: -1; transition: left 0.3s cubic-bezier(0.7, 0, 0.3, 1);
    }
    .btn:hover { color: #fff; border-color: var(--text-dark); }
    .btn:hover::before { left: 0; }
    
    .btn-primary { background: var(--text-dark); color: #fff; border-color: var(--text-dark); }
    .btn-primary:hover { color: #fff; }
    .btn-primary::before { background: var(--accent-main); }

    .btn-danger { border-color: #ef4444; color: #ef4444; }
    .btn-danger::before { background: #ef4444; }
    .btn-danger:hover { color: #fff; border-color: #ef4444; }

    .btn-outline { background: transparent; border: 2px solid var(--text-dark); color: var(--text-dark); }
    .btn-outline:hover { background: var(--text-dark); color: #fff; }

    /* ================= STATS GRID ================= */
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); border: 2px solid var(--text-dark); background: var(--bg-panel); margin-bottom: 2rem;}
    .stat-block { padding: 2rem 1.5rem; border-right: 2px solid var(--text-dark); display: flex; flex-direction: column; justify-content: center; }
    .stat-block:last-child { border-right: none; }
    .stat-val { font-family: var(--font-head); font-size: 3rem; font-weight: 700; color: var(--accent-main); line-height: 1; margin-bottom: 0.5rem; }
    .stat-label { font-family: var(--font-mono); font-size: 0.8rem; font-weight: 700; text-transform: uppercase; color: var(--text-tech);}

    /* ================= TABLES ================= */
    .table-responsive { overflow-x: auto; background: var(--bg-base); border: 2px solid var(--text-dark); margin-bottom: 1rem; }
    .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
    .custom-table th, .custom-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--text-tech); font-size: 0.9rem; }
    .custom-table th { background: var(--bg-panel); color: var(--text-dark); font-family: var(--font-mono); font-weight: 700; font-size: 0.8rem; text-transform: uppercase; }
    .custom-table tbody tr { transition: background 0.2s ease; }
    .custom-table tbody tr:hover { background: rgba(124, 58, 237, 0.05); }
    .custom-table tbody tr:last-child td { border-bottom: none; }
    
    /* ================= ALERTS & FORMS ================= */
    .alert { font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; border: 2px solid transparent; padding: 1rem 1.5rem; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 2rem;}
    .alert-danger { background: var(--bg-base); color: #ef4444; border-color: #ef4444; }
    .alert-success { background: var(--bg-base); color: #10b981; border-color: #10b981; }

    .form-control-custom {
      width: 100%; padding: 0.85rem 1.2rem; background: var(--bg-base); border: 1px solid var(--text-tech);
      color: var(--text-dark); font-family: var(--font-body); font-size: 0.95rem; outline: none; transition: border 0.2s;
      border-radius: 0; -webkit-appearance: none;
    }
    .form-control-custom:focus { border-color: var(--text-dark); border-width: 2px; padding: calc(0.85rem - 1px) calc(1.2rem - 1px); }

    @media (max-width: 1024px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .stat-block:nth-child(2) { border-right: none; }
        .stat-block:nth-child(1), .stat-block:nth-child(2) { border-bottom: 2px solid var(--text-dark); }
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
            <a href="hod_dashboard.php?view=dashboard" class="brand-logo interactive">
                <i class="fa-solid fa-sitemap"></i>
                <span>HOD Hub</span>
            </a>
        </div>

        <div class="sidebar-menu">
            <div class="menu-label">Navigation</div>
            <a href="?view=dashboard" class="sidebar-link interactive <?php echo ($view === 'dashboard') ? 'active' : ''; ?>">
                <span>Dashboard Overview</span>
            </a>
            <a href="?view=reports" class="sidebar-link interactive <?php echo in_array($view, ['reports', 'hod_subjects', 'hod_subject_classes', 'faculty_classes', 'class_report']) ? 'active' : ''; ?>">
                <span>Performance Reports</span>
            </a>
            <a href="?view=cumulative" class="sidebar-link interactive <?php echo in_array($view, ['cumulative', 'cumulative_classes', 'cumulative_report']) ? 'active' : ''; ?>">
                <span>Cumulative Report</span>
            </a>
            <a href="?view=profile" class="sidebar-link interactive <?php echo ($view === 'profile') ? 'active' : ''; ?>">
                <span>My Profile</span>
            </a>

            <div class="menu-label">Account</div>
            <a href="auth/logout.php" class="sidebar-link interactive" style="color: #ef4444;">
                <span>Logout</span>
            </a>
        </div>

        <div class="sidebar-user">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div class="avatar"><?php echo strtoupper(substr($hod_name, 0, 1)); ?></div>
                <div>
                    <div style="font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem; color: var(--text-dark);"><?php echo htmlspecialchars($hod_name); ?></div>
                    <div style="font-family: var(--font-mono); font-size: 0.65rem; color: var(--text-tech); text-transform: uppercase; font-weight: 700;">HOD - Department</div>
                </div>
            </div>
        </div>
    </aside>

    <!-- CONTENT WRAPPER -->
    <div class="content-wrapper">
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline interactive d-lg-none" id="sidebarToggle" style="padding: 0.4rem 0.8rem;">Menu</button>
                <h3>
                    <?php 
                    if ($view === 'dashboard') echo 'HOD Overview';
                    elseif ($view === 'reports') echo 'Academic Year Reports';
                    elseif ($view === 'hod_subjects') echo 'Year Subjects';
                    elseif ($view === 'hod_subject_classes') echo 'Subject Faculty & Classes';
                    elseif ($view === 'faculty_classes') echo 'Faculty Classes';
                    elseif ($view === 'class_report') echo 'Class Performance (20-Mark Scale)';
                    elseif ($view === 'cumulative') echo 'Cumulative Reports (Year Select)';
                    elseif ($view === 'cumulative_classes') echo 'Cumulative Year Classes';
                    elseif ($view === 'cumulative_report') echo 'Master Cumulative Sheet (Out of 20)';
                    elseif ($view === 'profile') echo 'HOD Profile';
                    else echo 'HOD Dashboard';
                    ?>
                </h3>
            </div>

            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; background: var(--text-dark); color: #fff; padding: 0.4rem 0.8rem;">
                    Time <span style="color: var(--accent-glow);">|</span> <span id="clock">00:00:00</span>
                </div>
            </div>
        </header>

        <main class="main-content">

        <!-- ALERTS -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> Error: <?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> Success: <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <!-- VIEW 1: OVERVIEW DASHBOARD -->
        <?php if ($view === 'dashboard'): ?>
            <div class="hero-banner">
                <div class="hero-content">
                    <div>
                        <h1 style="font-family: var(--font-head); font-size: 2.2rem; margin-bottom: 0.5rem; font-weight: 700; text-transform: uppercase;">HOD Monitoring Hub</h1>
                        <p style="color: var(--text-tech); font-family: var(--font-mono); font-size: 0.9rem;">Add faculty by their email address to monitor their class activity and student performance.</p>
                    </div>
                </div>
            </div>

            <!-- ADD FACULTY BY EMAIL CARD -->
            <div class="module-card">
                <h3 style="font-family: var(--font-head); font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; text-transform: uppercase; color: var(--text-dark);">
                    Monitor New Faculty
                </h3>
                <p style="color: var(--text-tech); font-family: var(--font-mono); font-size: 0.85rem; margin-bottom: 1.5rem;">
                    Enter the registered email address of a Faculty member to add them to your department monitoring list.
                </p>

                <form action="hod_dashboard.php?view=dashboard" method="POST" style="display: flex; gap: 1rem; max-width: 650px;">
                    <input type="hidden" name="form_action" value="add_faculty_by_email">
                    <input type="email" name="faculty_email" class="form-control-custom interactive" placeholder="Enter Faculty Email Address" required>
                    <button type="submit" class="btn btn-primary interactive" style="white-space: nowrap;">
                        Monitor Faculty
                    </button>
                </form>
            </div>

            <!-- OVERVIEW STATS -->
            <div class="stats-grid interactive">
                <div class="stat-block">
                    <div class="stat-val"><?php echo count($mapped_faculty); ?></div>
                    <div class="stat-label">Monitored Faculty</div>
                </div>
                <div class="stat-block">
                    <div class="stat-val" style="color: #3b82f6;"><?php echo array_sum(array_column($mapped_faculty, 'total_classes')); ?></div>
                    <div class="stat-label">Total Classes Created</div>
                </div>
                <div class="stat-block">
                    <div class="stat-val" style="color: #10b981;"><?php echo array_sum(array_column($mapped_faculty, 'total_activities')); ?></div>
                    <div class="stat-label">Total Activities Assigned</div>
                </div>
            </div>

            <!-- MONITORED FACULTY OVERVIEW -->
            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3 style="font-family: var(--font-head); font-size: 1.4rem; font-weight: 700; text-transform: uppercase;">Monitored Faculty Summary</h3>
                    <a href="?view=reports" class="btn btn-outline interactive" style="font-size: 0.75rem;">View Full Directory</a>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Faculty Name</th>
                                <th>Email</th>
                                <th>Classes</th>
                                <th>Activities</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mapped_faculty)): ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-tech); padding: 3rem; font-family: var(--font-mono); font-weight: 700;">No faculty linked yet. Input a faculty email address above to begin monitoring.</td></tr>
                            <?php else: ?>
                                <?php foreach ($mapped_faculty as $fac): ?>
                                <tr>
                                    <td><strong style="font-family: var(--font-body); text-transform: uppercase;"><?php echo htmlspecialchars($fac['name']); ?></strong></td>
                                    <td style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);"><?php echo htmlspecialchars($fac['email']); ?></td>
                                    <td><span class="sys-tag"><?php echo $fac['total_classes']; ?> Classes</span></td>
                                    <td><span class="sys-tag accent"><?php echo $fac['total_activities']; ?> Activities</span></td>
                                    <td style="text-align: right;">
                                        <a href="?view=faculty_classes&fid=<?php echo $fac['user_id']; ?>" class="btn btn-primary interactive" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                                            View Classes
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- VIEW 2: REPORTS / ACADEMIC YEARS SELECTION -->
        <?php elseif ($view === 'reports'): ?>
            <div class="hero-banner" style="margin-bottom: 2rem;">
                <div class="hero-content">
                    <div>
                        <h1 style="font-family: var(--font-head); font-size: 2rem; margin-bottom: 0.5rem; font-weight: 700; text-transform: uppercase;">ACADEMIC YEAR PERFORMANCE REPORTS</h1>
                        <p style="color: var(--text-tech); font-family: var(--font-mono); font-size: 0.85rem;">Select an academic year to inspect taught subjects, faculty classes, and converted 20-mark average performance reports.</p>
                    </div>
                </div>
            </div>

            <!-- YEAR SELECTION CARDS -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
                <?php foreach ($year_stats as $y_code => $y_data): ?>
                <div class="module-card interactive" style="padding: 2rem; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span class="sys-tag accent" style="font-size: 0.9rem; font-weight: 800;"><?php echo $y_code; ?></span>
                            <span style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-tech); font-weight: 700;"><?php echo $y_data['class_count']; ?> Classes</span>
                        </div>
                        <h3 style="font-family: var(--font-head); font-size: 1.3rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1rem; color: var(--text-dark);"><?php echo htmlspecialchars($y_data['name']); ?></h3>
                        
                        <div style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech); margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.4rem;">
                            <div>Subjects: <strong><?php echo $y_data['subject_count']; ?></strong></div>
                            <div>Teaching Faculty: <strong><?php echo $y_data['faculty_count']; ?></strong></div>
                        </div>
                    </div>

                    <a href="?view=hod_subjects&year=<?php echo urlencode($y_code); ?>" class="btn btn-primary interactive" style="width: 100%; font-size: 0.8rem;">
                        View Subjects &rarr;
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- DIRECT MONITORED FACULTY DIRECTORY -->
            <div class="module-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <div>
                        <h3 style="font-family: var(--font-head); font-size: 1.4rem; font-weight: 700; text-transform: uppercase;">Direct Faculty Directory</h3>
                        <p style="color: var(--text-tech); font-family: var(--font-mono); font-size: 0.85rem;">Or select a faculty member directly to view all their classes across years.</p>
                    </div>
                    <span class="sys-tag accent">Total: <?php echo count($mapped_faculty); ?></span>
                </div>

                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Faculty Name</th>
                                <th>Email</th>
                                <th>Classes Created</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($mapped_faculty)): ?>
                                <tr><td colspan="4" style="text-align: center; color: var(--text-tech); padding: 3rem; font-family: var(--font-mono); font-weight: 700;">No faculty linked. Go to <a href="?view=dashboard" style="color: var(--accent-main); text-decoration: underline;">Dashboard Overview</a> to add faculty by email.</td></tr>
                            <?php else: ?>
                                <?php foreach ($mapped_faculty as $fac): ?>
                                <tr>
                                    <td><strong style="font-family: var(--font-body); font-size: 1rem; text-transform: uppercase;"><?php echo htmlspecialchars($fac['name']); ?></strong></td>
                                    <td style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);"><?php echo htmlspecialchars($fac['email']); ?></td>
                                    <td><span class="sys-tag accent"><?php echo $fac['total_classes']; ?> Active Classes</span></td>
                                    <td style="text-align: right; display: flex; justify-content: flex-end; gap: 0.5rem;">
                                        <a href="?view=faculty_classes&fid=<?php echo $fac['user_id']; ?>" class="btn btn-primary interactive" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                                            View Classes
                                        </a>
                                        <form action="hod_dashboard.php?view=reports" method="POST" style="display: inline;" onsubmit="return confirm('Stop monitoring this faculty member?');">
                                            <input type="hidden" name="form_action" value="remove_faculty">
                                            <input type="hidden" name="faculty_id" value="<?php echo $fac['user_id']; ?>">
                                            <button type="submit" class="btn btn-danger interactive" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                                                Unlink
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- DRILL-DOWN VIEW: SUBJECTS IN A YEAR -->
        <?php elseif ($view === 'hod_subjects'): ?>
            <div style="margin-bottom: 1.5rem;">
                <a href="?view=reports" class="btn btn-outline interactive" style="margin-bottom: 1rem; font-size: 0.8rem;">
                    &larr; Back to Year Selection
                </a>
                <h2 style="font-family: var(--font-head); font-size: 2rem; font-weight: 700; text-transform: uppercase;">
                    Subjects in <?php echo htmlspecialchars($years_list[$selected_year] ?? $selected_year); ?>
                </h2>
                <p style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);">Select a subject to view faculty members teaching it and their created classes.</p>
            </div>

            <div class="module-card">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Subject Code / Name</th>
                                <th>Academic Year</th>
                                <th>Teaching Faculty</th>
                                <th>Total Classes</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($year_subjects)): ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-tech); padding: 3rem; font-family: var(--font-mono); font-weight: 700;">No faculty classes created for this academic year yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($year_subjects as $subj): ?>
                                <tr>
                                    <td><strong style="font-family: var(--font-head); font-size: 1.1rem; text-transform: uppercase; color: var(--accent-main);"><?php echo htmlspecialchars($subj['subject_code'] ?: 'General Subject'); ?></strong></td>
                                    <td><span class="sys-tag"><?php echo htmlspecialchars($subj['academic_year']); ?></span></td>
                                    <td style="font-family: var(--font-mono); font-weight: 700; color: var(--text-tech);"><?php echo $subj['faculty_count']; ?> Faculty Member(s)</td>
                                    <td style="font-family: var(--font-mono); font-weight: 700; color: var(--text-tech);"><?php echo $subj['class_count']; ?> Class(es)</td>
                                    <td style="text-align: right;">
                                        <a href="?view=hod_subject_classes&year=<?php echo urlencode($selected_year); ?>&subject=<?php echo urlencode($subj['subject_code']); ?>" class="btn btn-primary interactive" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                                            View Faculty & Classes &rarr;
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- DRILL-DOWN VIEW: FACULTY & CLASSES FOR A SUBJECT & YEAR -->
        <?php elseif ($view === 'hod_subject_classes'): ?>
            <div style="margin-bottom: 1.5rem;">
                <a href="?view=hod_subjects&year=<?php echo urlencode($selected_year); ?>" class="btn btn-outline interactive" style="margin-bottom: 1rem; font-size: 0.8rem;">
                    &larr; Back to Subjects
                </a>
                <h2 style="font-family: var(--font-head); font-size: 2rem; font-weight: 700; text-transform: uppercase;">
                    Subject: <?php echo htmlspecialchars($selected_subject ?: 'General'); ?> (<?php echo htmlspecialchars($selected_year); ?>)
                </h2>
                <p style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);">Classes created by faculty members for this subject.</p>
            </div>

            <div class="module-card">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Teaching Faculty</th>
                                <th>Email</th>
                                <th>Enrolled Students</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subject_classes)): ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-tech); padding: 3rem; font-family: var(--font-mono); font-weight: 700;">No classes found for this subject and year.</td></tr>
                            <?php else: ?>
                                <?php foreach ($subject_classes as $s_cls): ?>
                                <tr>
                                    <td><strong style="font-family: var(--font-head); font-size: 1.05rem; text-transform: uppercase;"><?php echo htmlspecialchars($s_cls['class_name']); ?></strong></td>
                                    <td><strong style="font-family: var(--font-body); font-size: 0.95rem; text-transform: uppercase;"><?php echo htmlspecialchars($s_cls['faculty_name']); ?></strong></td>
                                    <td style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);"><?php echo htmlspecialchars($s_cls['faculty_email']); ?></td>
                                    <td style="font-family: var(--font-mono); font-weight: 700; color: var(--text-tech);"><?php echo $s_cls['student_count']; ?> Students</td>
                                    <td style="text-align: right;">
                                        <a href="?view=class_report&fid=<?php echo $s_cls['faculty_id']; ?>&cid=<?php echo $s_cls['class_id']; ?>" class="btn btn-primary interactive" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                                            View Class Report &rarr;
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- VIEW 3: FACULTY CLASSES LIST (DIRECT) -->
        <?php elseif ($view === 'faculty_classes' && $faculty_info): ?>
            <div style="margin-bottom: 1.5rem;">
                <a href="?view=reports" class="btn btn-outline interactive" style="margin-bottom: 1rem; font-size: 0.8rem;">
                    &larr; Back to Directory
                </a>
                <h2 style="font-family: var(--font-head); font-size: 2rem; font-weight: 700; text-transform: uppercase;">Classes Managed by <?php echo htmlspecialchars($faculty_info['name']); ?></h2>
                <p style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);">Email: <strong><?php echo htmlspecialchars($faculty_info['email']); ?></strong></p>
            </div>

            <div class="module-card">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Class / Group Name</th>
                                <th>Year</th>
                                <th>Subject</th>
                                <th>Enrolled Students</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($faculty_classes)): ?>
                                <tr><td colspan="5" style="text-align: center; color: var(--text-tech); padding: 3rem; font-family: var(--font-mono); font-weight: 700;">This faculty member hasn't created any classes yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($faculty_classes as $cls): ?>
                                <tr>
                                    <td><strong style="font-family: var(--font-head); font-size: 1.05rem; text-transform: uppercase;"><?php echo htmlspecialchars($cls['class_name']); ?></strong></td>
                                    <td><span class="sys-tag"><?php echo htmlspecialchars($cls['academic_year'] ?? 'FY'); ?></span></td>
                                    <td><span style="font-family: var(--font-mono); font-weight: 700; color: var(--accent-main);"><?php echo htmlspecialchars($cls['subject_code'] ?: 'N/A'); ?></span></td>
                                    <td style="font-family: var(--font-mono); font-weight: 700; color: var(--text-tech);"><?php echo $cls['student_count']; ?> Students</td>
                                    <td style="text-align: right;">
                                        <a href="?view=class_report&fid=<?php echo $fid; ?>&cid=<?php echo $cls['class_id']; ?>" class="btn btn-primary interactive" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                                            View Class Report &rarr;
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- VIEW 4: DETAILED CLASS REPORT WITH 20-MARK SCALED CONVERSION -->
        <?php elseif ($view === 'class_report' && $selected_class): ?>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
                <div>
                    <a href="javascript:history.back()" class="btn btn-outline interactive" style="margin-bottom: 1rem; font-size: 0.8rem;">
                        &larr; Back
                    </a>
                    <h2 style="font-family: var(--font-head); font-size: 2rem; font-weight: 700; text-transform: uppercase;">Class Report: <?php echo htmlspecialchars($selected_class['class_name']); ?></h2>
                    <p style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);">
                        Faculty: <strong><?php echo htmlspecialchars($faculty_info['name'] ?? 'Faculty'); ?></strong> | 
                        Year: <span class="sys-tag"><?php echo htmlspecialchars($selected_class['academic_year'] ?? 'FY'); ?></span> | 
                        Subject: <strong style="color: var(--accent-main);"><?php echo htmlspecialchars($selected_class['subject_code'] ?: 'General'); ?></strong>
                    </p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-outline interactive" onclick="exportPDF()">Export PDF</button>
                    <button class="btn btn-primary interactive" onclick="exportExcel()">Export Excel</button>
                </div>
            </div>

            <div id="exportTable">
                <!-- STATS SUMMARY -->
                <div class="stats-grid interactive" style="margin-bottom: 2rem;">
                    <div class="stat-block">
                        <div class="stat-val"><?php echo count($class_students); ?></div>
                        <div class="stat-label">Enrolled Students</div>
                    </div>
                    <div class="stat-block">
                        <div class="stat-val" style="color: #3b82f6;"><?php echo count($class_activities); ?></div>
                        <div class="stat-label">Total Activities</div>
                    </div>
                    <div class="stat-block">
                        <div class="stat-val" style="color: #10b981;"><?php echo $class_total_max_marks; ?></div>
                        <div class="stat-label">Total Max Marks</div>
                    </div>
                    <div class="stat-block">
                        <?php 
                            $class_avg_20 = 0;
                            if (!empty($class_students)) {
                                $sum_20 = array_sum(array_column($class_students, 'scaled_score_20'));
                                $class_avg_20 = round($sum_20 / count($class_students), 2);
                            }
                        ?>
                        <div class="stat-val" style="color: var(--accent-main);"><?php echo $class_avg_20; ?> <span style="font-size: 1rem; color: var(--text-tech);">/ 20</span></div>
                        <div class="stat-label">Class Scaled Avg (/20)</div>
                    </div>
                </div>

                <!-- ENROLLED STUDENTS PERFORMANCE ROSTER (SCALED MARKS TO 20) -->
                <div class="module-card" style="margin-bottom: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <div>
                            <h3 style="font-family: var(--font-head); font-size: 1.4rem; font-weight: 700; text-transform: uppercase;">Student Marks &amp; Converted Average (Scale of 20)</h3>
                            <p style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-tech);">
                                Formula: <strong>(Total Marks Obtained &divide; Total Max Marks) &times; 20</strong>
                            </p>
                        </div>
                        <span class="sys-tag accent">Max Scale: 20 Marks</span>
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Student PRN</th>
                                    <th>Student Name</th>
                                    <th>Roll No</th>
                                    <th style="text-align: center;">Evaluated</th>
                                    <th>Total Obtained</th>
                                    <th>Percentage</th>
                                    <th style="text-align: right;">Converted Score (Out of 20)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($class_students)): ?>
                                    <tr><td colspan="7" style="text-align: center; color: var(--text-tech); padding: 2rem; font-family: var(--font-mono); font-weight: 700;">No students added to this class yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($class_students as $st): ?>
                                    <tr>
                                        <td style="font-family: var(--font-mono); font-weight: 700; color: var(--accent-main);"><?php echo htmlspecialchars($st['student_prn']); ?></td>
                                        <td><strong style="font-family: var(--font-body); font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($st['student_name'] ?: 'Registered Student'); ?></strong></td>
                                        <td style="font-family: var(--font-mono); font-weight: 700;"><?php echo htmlspecialchars($st['roll_no'] ?: '-'); ?></td>
                                        <td style="text-align: center; font-family: var(--font-mono); font-weight: 700; color: #3b82f6;">
                                            <?php echo $st['evaluated_count']; ?> / <?php echo count($class_activities); ?>
                                        </td>
                                        <td style="font-family: var(--font-mono); font-weight: 700;">
                                            <?php echo $st['obtained_marks']; ?> <span style="font-size: 0.75rem; color: var(--text-tech);">/ <?php echo $st['total_max_marks']; ?></span>
                                        </td>
                                        <td style="font-family: var(--font-mono); font-weight: 700; color: #10b981;">
                                            <?php echo number_format($st['percentage'], 2); ?>%
                                        </td>
                                        <td style="text-align: right;">
                                            <span class="sys-tag accent" style="font-size: 1rem; font-weight: 800; padding: 0.4rem 0.8rem; background: rgba(124, 58, 237, 0.1);">
                                                <?php echo number_format($st['scaled_score_20'], 2); ?> / 20
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- ACTIVITIES ANALYTICS -->
                <div class="module-card">
                    <h3 style="font-family: var(--font-head); font-size: 1.4rem; font-weight: 700; margin-bottom: 1.5rem; text-transform: uppercase;">Activity Breakdown</h3>
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
                                    <tr><td colspan="5" style="text-align: center; color: var(--text-tech); padding: 2rem; font-family: var(--font-mono); font-weight: 700;">No activities assigned for this class yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($class_activities as $act): 
                                        $pending = max(0, count($class_students) - $act['submitted_count']);
                                        $avg = $act['avg_score'] !== null ? number_format($act['avg_score'], 1) : '-';
                                    ?>
                                    <tr>
                                        <td><strong style="font-family: var(--font-head); font-size: 1rem; text-transform: uppercase;"><?php echo htmlspecialchars($act['title']); ?></strong></td>
                                        <td style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-tech);"><?php echo date('M d, Y', strtotime($act['due_date'])); ?></td>
                                        <td style="text-align: center;"><strong style="color: #10b981; font-family: var(--font-mono); font-size: 1.1rem;"><?php echo $act['submitted_count']; ?></strong></td>
                                        <td style="text-align: center;"><strong style="color: #ef4444; font-family: var(--font-mono); font-size: 1.1rem;"><?php echo $pending; ?></strong></td>
                                        <td><strong style="font-family: var(--font-mono); font-size: 1.1rem;"><?php echo $avg; ?></strong> <span style="font-size: 0.75rem; color: var(--text-tech);">/ <?php echo $act['max_marks']; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
        <!-- CUMULATIVE VIEW 1: ACADEMIC YEAR CARDS FOR DIRECT CLASS ACCESS -->
        <?php elseif ($view === 'cumulative'): ?>
            <div class="hero-banner" style="margin-bottom: 2rem;">
                <div class="hero-content">
                    <div>
                        <h1 style="font-family: var(--font-head); font-size: 2rem; margin-bottom: 0.5rem; font-weight: 700; text-transform: uppercase;">CUMULATIVE MARKSHEET REPORTS</h1>
                        <p style="color: var(--text-tech); font-family: var(--font-mono); font-size: 0.85rem;">Select an academic year to inspect classes directly and generate master cumulative subject marksheets scaled out of 20.</p>
                    </div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
                <?php foreach ($year_stats as $y_code => $y_data): ?>
                <div class="module-card interactive" style="padding: 2rem; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <span class="sys-tag accent" style="font-size: 0.9rem; font-weight: 800;"><?php echo $y_code; ?></span>
                            <span style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-tech); font-weight: 700;"><?php echo $y_data['class_count']; ?> Active Classes</span>
                        </div>
                        <h3 style="font-family: var(--font-head); font-size: 1.35rem; font-weight: 700; text-transform: uppercase; margin-bottom: 1rem; color: var(--text-dark);"><?php echo htmlspecialchars($y_data['name']); ?></h3>
                        
                        <div style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech); margin-bottom: 1.5rem; display: flex; flex-direction: column; gap: 0.4rem;">
                            <div>Total Classes: <strong><?php echo $y_data['class_count']; ?></strong></div>
                            <div>Faculty Members: <strong><?php echo $y_data['faculty_count']; ?></strong></div>
                        </div>
                    </div>

                    <a href="?view=cumulative_classes&year=<?php echo urlencode($y_code); ?>" class="btn btn-primary interactive" style="width: 100%; font-size: 0.8rem;">
                        Select Class in Year &rarr;
                    </a>
                </div>
                <?php endforeach; ?>
            </div>

        <!-- CUMULATIVE VIEW 2: DIRECT CLASSES LIST IN SELECTED YEAR -->
        <?php elseif ($view === 'cumulative_classes'): ?>
            <div style="margin-bottom: 1.5rem;">
                <a href="?view=cumulative" class="btn btn-outline interactive" style="margin-bottom: 1rem; font-size: 0.8rem;">
                    &larr; Back to Year Selection
                </a>
                <h2 style="font-family: var(--font-head); font-size: 2rem; font-weight: 700; text-transform: uppercase;">
                    Cumulative Classes: <?php echo htmlspecialchars($years_list[$selected_year] ?? $selected_year); ?>
                </h2>
                <p style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);">Select any class below to open its cumulative subject-wise marksheet.</p>
            </div>

            <div class="module-card">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Teaching Faculty</th>
                                <th>Email</th>
                                <th>Subject Code</th>
                                <th>Enrolled Students</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($cum_year_classes)): ?>
                                <tr><td colspan="6" style="text-align: center; color: var(--text-tech); padding: 3rem; font-family: var(--font-mono); font-weight: 700;">No classes created for this academic year yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($cum_year_classes as $c_item): ?>
                                <tr>
                                    <td><strong style="font-family: var(--font-head); font-size: 1.05rem; text-transform: uppercase;"><?php echo htmlspecialchars($c_item['class_name']); ?></strong></td>
                                    <td><strong style="font-family: var(--font-body); font-size: 0.95rem; text-transform: uppercase;"><?php echo htmlspecialchars($c_item['faculty_name']); ?></strong></td>
                                    <td style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);"><?php echo htmlspecialchars($c_item['faculty_email']); ?></td>
                                    <td><span style="font-family: var(--font-mono); font-weight: 700; color: var(--accent-main);"><?php echo htmlspecialchars($c_item['subject_code'] ?: 'General'); ?></span></td>
                                    <td style="font-family: var(--font-mono); font-weight: 700; color: var(--text-tech);"><?php echo $c_item['student_count']; ?> Students</td>
                                    <td style="text-align: right;">
                                        <a href="?view=cumulative_report&cid=<?php echo $c_item['class_id']; ?>" class="btn btn-primary interactive" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                                            Cumulative Report &rarr;
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- CUMULATIVE VIEW 3: MASTER CUMULATIVE MARKSHEET (STUDENTS X SUBJECTS CONVERTED TO 20) -->
        <?php elseif ($view === 'cumulative_report' && $cum_class_info): ?>
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem;">
                <div>
                    <a href="?view=cumulative_classes&year=<?php echo urlencode($cum_class_info['academic_year']); ?>" class="btn btn-outline interactive" style="margin-bottom: 1rem; font-size: 0.8rem;">
                        &larr; Back to Classes
                    </a>
                    <h2 style="font-family: var(--font-head); font-size: 2rem; font-weight: 700; text-transform: uppercase;">
                        Cumulative Marksheet: <?php echo htmlspecialchars($cum_class_info['class_name']); ?>
                    </h2>
                    <p style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);">
                        Faculty: <strong><?php echo htmlspecialchars($cum_class_info['faculty_name']); ?></strong> | 
                        Year: <span class="sys-tag"><?php echo htmlspecialchars($cum_class_info['academic_year'] ?? 'FY'); ?></span> | 
                        Subject: <strong style="color: var(--accent-main);"><?php echo htmlspecialchars($cum_class_info['subject_code'] ?: 'General'); ?></strong>
                    </p>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button class="btn btn-outline interactive" onclick="exportPDF()">Export PDF</button>
                    <button class="btn btn-primary interactive" onclick="exportExcel()">Export Excel</button>
                </div>
            </div>

            <div id="exportTable">
                <!-- STATS SUMMARY -->
                <div class="stats-grid interactive" style="margin-bottom: 2rem;">
                    <div class="stat-block">
                        <div class="stat-val"><?php echo count($cum_class_students); ?></div>
                        <div class="stat-label">Enrolled Students</div>
                    </div>
                    <div class="stat-block">
                        <div class="stat-val" style="color: #3b82f6;"><?php echo count($cum_distinct_subjects); ?></div>
                        <div class="stat-label">Subject Columns</div>
                    </div>
                    <div class="stat-block">
                        <?php 
                            $cum_overall_avg = 0;
                            if (!empty($cum_class_students)) {
                                $sum_overall = array_sum(array_column($cum_class_students, 'overall_score_20'));
                                $cum_overall_avg = round($sum_overall / count($cum_class_students), 2);
                            }
                        ?>
                        <div class="stat-val" style="color: var(--accent-main);"><?php echo $cum_overall_avg; ?> <span style="font-size: 1rem; color: var(--text-tech);">/ 20</span></div>
                        <div class="stat-label">Master Scaled Avg (/20)</div>
                    </div>
                </div>

                <!-- CUMULATIVE MASTER TABLE -->
                <div class="module-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <div>
                            <h3 style="font-family: var(--font-head); font-size: 1.4rem; font-weight: 700; text-transform: uppercase;">Master Cumulative Sheet (Subject-Wise Marks Scaled to 20)</h3>
                            <p style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-tech);">
                                Shows every student's average marks per subject converted to a scale of 20.
                            </p>
                        </div>
                        <span class="sys-tag accent">Cumulative Mode</span>
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>PRN</th>
                                    <th>Student Name</th>
                                    <th>Roll No</th>
                                    <?php foreach ($cum_distinct_subjects as $s_name): ?>
                                        <th style="text-align: center; color: var(--accent-main);"><?php echo htmlspecialchars($s_name); ?> (/20)</th>
                                    <?php endforeach; ?>
                                    <th style="text-align: center; color: #10b981;">Overall (/20)</th>
                                    <th style="text-align: right;">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($cum_class_students)): ?>
                                    <tr><td colspan="<?php echo (4 + count($cum_distinct_subjects) + 1); ?>" style="text-align: center; color: var(--text-tech); padding: 3rem; font-family: var(--font-mono); font-weight: 700;">No students enrolled in this class yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($cum_class_students as $st): ?>
                                    <tr>
                                        <td style="font-family: var(--font-mono); font-weight: 700; color: var(--accent-main);"><?php echo htmlspecialchars($st['student_prn']); ?></td>
                                        <td><strong style="font-family: var(--font-body); font-weight: 600; text-transform: uppercase;"><?php echo htmlspecialchars($st['student_name'] ?: 'Registered Student'); ?></strong></td>
                                        <td style="font-family: var(--font-mono); font-weight: 700;"><?php echo htmlspecialchars($st['roll_no'] ?: '-'); ?></td>
                                        
                                        <?php foreach ($cum_distinct_subjects as $s_name): 
                                            $s_score = $st['subject_scores_20'][$s_name] ?? 0;
                                        ?>
                                            <td style="text-align: center; font-family: var(--font-mono); font-weight: 700;">
                                                <span class="sys-tag" style="background: rgba(124, 58, 237, 0.08); color: var(--accent-main); border-color: var(--accent-main);">
                                                    <?php echo number_format($s_score, 2); ?> / 20
                                                </span>
                                            </td>
                                        <?php endforeach; ?>

                                        <td style="text-align: center; font-family: var(--font-mono); font-weight: 800;">
                                            <span class="sys-tag accent" style="font-size: 0.95rem; padding: 0.3rem 0.6rem;">
                                                <?php echo number_format($st['overall_score_20'], 2); ?> / 20
                                            </span>
                                        </td>
                                        <td style="text-align: right; font-family: var(--font-mono); font-weight: 700; color: #10b981;">
                                            <?php echo number_format($st['overall_percentage'], 2); ?>%
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        
        <!-- VIEW 5: PROFILE SECTION -->
        <?php elseif ($view === 'profile'): ?>
            <div class="page-header" style="margin-bottom: 1.5rem;">
                <h1 style="font-family: var(--font-head); font-size: 2rem; font-weight: 700; text-transform: uppercase;">My Profile</h1>
                <p style="font-family: var(--font-mono); font-size: 0.85rem; color: var(--text-tech);">View professional information.</p>
            </div>
            
            <div class="module-card" style="max-width: 800px; padding: 3rem;">
                <div style="display:flex; gap: 2rem; align-items:flex-start; flex-wrap: wrap;">
                    <div style="flex-shrink:0;">
                        <div style="width:120px; height:120px; border: 2px solid var(--text-dark); background-color:var(--bg-base); display:flex; align-items:center; justify-content:center; font-size:4rem; color: var(--accent-main);">
                            <i class="fa-solid fa-user"></i>
                        </div>
                    </div>
                    <div style="flex:1;">
                        <h2 style="font-size:2rem; margin-bottom:0.2rem; color:var(--text-dark); font-family: var(--font-head); font-weight: 700; text-transform: uppercase;"><?= htmlspecialchars($hod_name) ?></h2>
                        <p style="color:var(--text-tech); font-size:1rem; margin-bottom:1.5rem; font-weight:600; font-family: var(--font-mono); text-transform: uppercase;">Head of Department</p>
                        
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem; border-top: 2px solid var(--text-dark); padding-top: 1.5rem;">
                            <div>
                                <strong style="display:block; margin-bottom:0.25rem; font-size:0.85rem; color:var(--text-tech); font-family: var(--font-mono); text-transform:uppercase;">Account Role</strong>
                                <span class="sys-tag accent"><?= strtoupper($role) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        </main>
    </div>
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

    // 2. Sidebar Toggle (Mobile)
    const sidebarToggle = document.getElementById('sidebarToggle');
    const erpSidebar = document.getElementById('erpSidebar');

    if (sidebarToggle && erpSidebar) {
      sidebarToggle.addEventListener('click', () => {
        erpSidebar.classList.toggle('show');
      });
    }

    // 3. Live Clock
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
// Safely include modal without fatal error
$modalPath = __DIR__ . '/includes/end_session_modal.php';
if (file_exists($modalPath)) {
    include_once $modalPath;
}
?>
</body>
</html>