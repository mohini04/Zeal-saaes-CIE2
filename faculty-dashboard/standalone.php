<?php
// standalone.php - Complete Merged Faculty Activity Assignment System
// All-In-One Single-File Application (DB Core, Layout, CSS, JS, Matrix & Activity Workspaces)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------
// 1. DATABASE & SESSION DATA FALLBACK CORE
// ----------------------------------------------------
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'faculty_activity_db';

$conn = null;
$db_connected = false;

try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $db_connected = true;
} catch (PDOException $e) {
    $db_connected = false;
}

// Initialize Default Sample Data in Session if DB not connected or empty
if (!isset($_SESSION['activities'])) {
    $_SESSION['activities'] = [
        1 => [
            'id' => 1,
            'sr_no' => 1,
            'unit' => 'Unit 1',
            'title' => 'Circuit Analysis & AC Fundamentals Quiz',
            'type' => 'quiz',
            'type_name' => 'Quiz',
            'icon' => 'fa-clipboard-question',
            'color' => '#6366f1',
            'course' => 'BEE101 - Basic Electrical Engineering',
            'subject' => 'BEE',
            'batch' => '2025-29 (Sec A & B)',
            'deadline' => '2026-07-28T23:59',
            'total_marks' => 20,
            'status' => 'Active',
            'description' => 'Multiple choice quiz covering Kirchhoff\'s Laws, RLC Circuits, Phasor Diagrams, and 3-Phase Systems.',
            'submissions_count' => 42,
            'total_students' => 50,
            'questions_count' => 10
        ],
        2 => [
            'id' => 2,
            'sr_no' => 2,
            'unit' => 'Unit 2',
            'title' => 'Nanomaterials & Polymer Tech Poster',
            'type' => 'poster_making',
            'type_name' => 'Poster Making',
            'icon' => 'fa-palette',
            'color' => '#ec4899',
            'course' => 'CH101 - Engineering Chemistry',
            'subject' => 'Chemistry',
            'batch' => '2025-29 (All Sections)',
            'deadline' => '2026-07-30T18:00',
            'total_marks' => 50,
            'status' => 'Active',
            'description' => 'Design an infographic poster on Industrial Water Treatment, Corrosion Control, or Synthetic Polymers.',
            'submissions_count' => 28,
            'total_students' => 60,
            'dimensions' => 'A3 Format (300 DPI)'
        ],
        3 => [
            'id' => 3,
            'sr_no' => 3,
            'unit' => 'Unit 3',
            'title' => 'Quantum Mechanics & Lasers PPT',
            'type' => 'ppt',
            'type_name' => 'PPT Presentation',
            'icon' => 'fa-file-powerpoint',
            'color' => '#f59e0b',
            'course' => 'PH101 - Engineering Physics',
            'subject' => 'Physics',
            'batch' => '2025-29 (Sec C & D)',
            'deadline' => '2026-08-05T12:00',
            'total_marks' => 30,
            'status' => 'Active',
            'description' => 'Prepare a 10-slide presentation on Fiber Optics Applications, Wave-Particle Duality, or Semiconductor Physics.',
            'submissions_count' => 31,
            'total_students' => 55,
            'slide_limit' => '8 - 12 Slides'
        ],
        4 => [
            'id' => 4,
            'sr_no' => 4,
            'unit' => 'Unit 4',
            'title' => 'Differential Equations & Calculus Case Study',
            'type' => 'case_study',
            'type_name' => 'Case Study',
            'icon' => 'fa-magnifying-glass-chart',
            'color' => '#10b981',
            'course' => 'MA101 - Engineering Mathematics',
            'subject' => 'Maths',
            'batch' => '2025-29 (Sec E)',
            'deadline' => '2026-08-02T23:59',
            'total_marks' => 40,
            'status' => 'Active',
            'description' => 'Real-world application of Fourier Series and Laplace Transforms in signal processing and vibration analysis.',
            'submissions_count' => 35,
            'total_students' => 48,
            'word_limit' => '1500 Words'
        ],
        5 => [
            'id' => 5,
            'sr_no' => 5,
            'unit' => 'Unit 2',
            'title' => 'Electromagnetic Field Theory GD',
            'type' => 'gd',
            'type_name' => 'Group Discussion',
            'icon' => 'fa-comments',
            'color' => '#8b5cf6',
            'course' => 'PH101 - Engineering Physics',
            'subject' => 'Physics',
            'batch' => '2025-29 (Sec A)',
            'deadline' => '2026-07-29T15:00',
            'total_marks' => 25,
            'status' => 'Scheduled',
            'description' => 'Group Discussion on Wireless Power Transfer vs High-Voltage Power Lines: Environmental Impact & Feasibility.',
            'submissions_count' => 6,
            'total_students' => 36,
            'group_size' => 6
        ],
        6 => [
            'id' => 6,
            'sr_no' => 6,
            'unit' => 'Unit 5',
            'title' => 'Smart IoT & Embedded Mini Project',
            'type' => 'mini_project',
            'type_name' => 'Mini Project',
            'icon' => 'fa-laptop-code',
            'color' => '#06b6d4',
            'course' => 'BEE101 - Basic Electrical Engineering',
            'subject' => 'BEE',
            'batch' => '2024-28 (CSE-A)',
            'deadline' => '2026-08-15T23:59',
            'total_marks' => 100,
            'status' => 'Active',
            'description' => 'Build an Arduino/ESP32 based Smart Energy Meter with live voltage monitoring and IoT dashboard connectivity.',
            'submissions_count' => 10,
            'total_students' => 40,
            'max_team_size' => 4
        ]
    ];
}

// Data Helper Functions
function get_all_activities() {
    global $conn, $db_connected;
    if ($db_connected) {
        try {
            $stmt = $conn->query("SELECT * FROM activities ORDER BY id DESC");
            $rows = $stmt->fetchAll();
            if (!empty($rows)) return $rows;
        } catch (Exception $e) {}
    }
    return $_SESSION['activities'];
}

function get_activity_by_id($id) {
    global $conn, $db_connected;
    if ($db_connected) {
        try {
            $stmt = $conn->prepare("SELECT * FROM activities WHERE id = ?");
            $stmt->execute([$id]);
            $res = $stmt->fetch();
            if ($res) return $res;
        } catch (Exception $e) {}
    }
    return isset($_SESSION['activities'][$id]) ? $_SESSION['activities'][$id] : null;
}

function add_new_activity($data) {
    global $conn, $db_connected;
    $type_map = [
        'quiz' => ['name' => 'Quiz', 'icon' => 'fa-clipboard-question', 'color' => '#6366f1'],
        'poster_making' => ['name' => 'Poster Making', 'icon' => 'fa-palette', 'color' => '#ec4899'],
        'ppt' => ['name' => 'PPT Presentation', 'icon' => 'fa-file-powerpoint', 'color' => '#f59e0b'],
        'case_study' => ['name' => 'Case Study', 'icon' => 'fa-magnifying-glass-chart', 'color' => '#10b981'],
        'gd' => ['name' => 'Group Discussion', 'icon' => 'fa-comments', 'color' => '#8b5cf6'],
        'mini_project' => ['name' => 'Mini Project', 'icon' => 'fa-laptop-code', 'color' => '#06b6d4']
    ];
    $meta = isset($type_map[$data['type']]) ? $type_map[$data['type']] : ['name' => ucfirst($data['type']), 'icon' => 'fa-tasks', 'color' => '#3b82f6'];

    if ($db_connected) {
        try {
            $stmt = $conn->prepare("INSERT INTO activities (title, type, course, subject, unit, batch, deadline, total_marks, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
            $stmt->execute([
                $data['title'], $data['type'], $data['course'], $data['subject'],
                isset($data['unit']) ? $data['unit'] : 'Unit 1', $data['batch'],
                $data['deadline'], $data['total_marks'], $data['description']
            ]);
            return $conn->lastInsertId();
        } catch (Exception $e) {}
    }
    
    $new_id = count($_SESSION['activities']) + 1;
    $_SESSION['activities'][$new_id] = [
        'id' => $new_id,
        'sr_no' => $new_id,
        'title' => $data['title'],
        'type' => $data['type'],
        'type_name' => $meta['name'],
        'icon' => $meta['icon'],
        'color' => $meta['color'],
        'course' => $data['course'],
        'subject' => $data['subject'],
        'unit' => isset($data['unit']) ? $data['unit'] : 'Unit 1',
        'batch' => $data['batch'],
        'deadline' => $data['deadline'],
        'total_marks' => (int)$data['total_marks'],
        'status' => 'Active',
        'description' => $data['description'],
        'submissions_count' => 0,
        'total_students' => 45
    ];
    return $new_id;
}

function update_activity($id, $data) {
    global $conn, $db_connected;
    if ($db_connected) {
        try {
            $stmt = $conn->prepare("UPDATE activities SET title = ?, type = ?, course = ?, subject = ?, unit = ?, batch = ?, deadline = ?, total_marks = ?, description = ? WHERE id = ?");
            $stmt->execute([
                $data['title'], $data['type'], $data['course'], $data['subject'],
                isset($data['unit']) ? $data['unit'] : 'Unit 1', $data['batch'],
                $data['deadline'], $data['total_marks'], $data['description'], $id
            ]);
            return true;
        } catch (Exception $e) {}
    }

    if (isset($_SESSION['activities'][$id])) {
        $type_map = [
            'quiz' => ['name' => 'Quiz', 'icon' => 'fa-clipboard-question', 'color' => '#6366f1'],
            'poster_making' => ['name' => 'Poster Making', 'icon' => 'fa-palette', 'color' => '#ec4899'],
            'ppt' => ['name' => 'PPT Presentation', 'icon' => 'fa-file-powerpoint', 'color' => '#f59e0b'],
            'case_study' => ['name' => 'Case Study', 'icon' => 'fa-magnifying-glass-chart', 'color' => '#10b981'],
            'gd' => ['name' => 'Group Discussion', 'icon' => 'fa-comments', 'color' => '#8b5cf6'],
            'mini_project' => ['name' => 'Mini Project', 'icon' => 'fa-laptop-code', 'color' => '#06b6d4']
        ];
        $meta = isset($type_map[$data['type']]) ? $type_map[$data['type']] : ['name' => ucfirst($data['type']), 'icon' => 'fa-tasks', 'color' => '#3b82f6'];

        $_SESSION['activities'][$id]['title'] = $data['title'];
        $_SESSION['activities'][$id]['type'] = $data['type'];
        $_SESSION['activities'][$id]['type_name'] = $meta['name'];
        $_SESSION['activities'][$id]['icon'] = $meta['icon'];
        $_SESSION['activities'][$id]['color'] = $meta['color'];
        $_SESSION['activities'][$id]['course'] = $data['course'];
        $_SESSION['activities'][$id]['subject'] = $data['subject'];
        if (isset($data['unit'])) $_SESSION['activities'][$id]['unit'] = $data['unit'];
        $_SESSION['activities'][$id]['batch'] = $data['batch'];
        $_SESSION['activities'][$id]['deadline'] = $data['deadline'];
        $_SESSION['activities'][$id]['total_marks'] = (int)$data['total_marks'];
        $_SESSION['activities'][$id]['description'] = $data['description'];
        return true;
    }
    return false;
}

function delete_activity($id) {
    global $conn, $db_connected;
    if ($db_connected) {
        try {
            $stmt = $conn->prepare("DELETE FROM activities WHERE id = ?");
            $stmt->execute([$id]);
            return true;
        } catch (Exception $e) {}
    }
    if (isset($_SESSION['activities'][$id])) {
        unset($_SESSION['activities'][$id]);
        return true;
    }
    return false;
}

function recreate_activity($id) {
    $existing = get_activity_by_id($id);
    if ($existing) {
        $new_data = $existing;
        $new_data['title'] = $existing['title'] . ' (Recreated Batch)';
        $new_data['deadline'] = date('Y-m-d\TH:i', strtotime('+7 days'));
        return add_new_activity($new_data);
    }
    return false;
}

// ----------------------------------------------------
// 2. ROUTING & POST ACTION HANDLERS
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
            'description' => trim($_POST['description'])
        ]);
        header("Location: standalone.php?view=" . trim($_POST['type']) . "&id=" . $new_id);
        exit;
    } elseif ($action === 'edit_activity') {
        $edit_id = (int)$_POST['activity_id'];
        update_activity($edit_id, [
            'title' => trim($_POST['title']),
            'type' => trim($_POST['type']),
            'course' => trim($_POST['course']),
            'subject' => trim($_POST['subject']),
            'unit' => trim($_POST['unit']),
            'batch' => trim($_POST['batch']),
            'deadline' => trim($_POST['deadline']),
            'total_marks' => (int)$_POST['total_marks'],
            'description' => trim($_POST['description'])
        ]);
        $success_message = "Activity Sr. No. #$edit_id updated successfully!";
    } elseif ($action === 'delete_activity') {
        $delete_id = (int)$_POST['activity_id'];
        if (delete_activity($delete_id)) {
            $success_message = "Activity deleted successfully!";
        } else {
            $message = "Error deleting activity.";
        }
    } elseif ($action === 'recreate') {
        $recreate_id = (int)$_POST['activity_id'];
        $new_id = recreate_activity($recreate_id);
        if ($new_id) {
            $success_message = "Activity Recreated successfully as new Sr. No. #$new_id!";
        }
    } elseif ($action === 'autofill_deduction') {
        $rate = (int)$_POST['deduction_rate'];
        $success_message = "Autofilled scores across all activities with {$rate}% deduction rate per day for late submissions!";
    } elseif ($action === 'add_question') {
        $q_id = (int)$_POST['activity_id'];
        if (!isset($_SESSION['quiz_questions_' . $q_id])) {
            $_SESSION['quiz_questions_' . $q_id] = [];
        }
        $_SESSION['quiz_questions_' . $q_id][] = [
            'q' => trim($_POST['question']),
            'a' => trim($_POST['opt_a']),
            'b' => trim($_POST['opt_b']),
            'c' => trim($_POST['opt_c']),
            'd' => trim($_POST['opt_d']),
            'correct' => $_POST['correct'],
            'points' => (int)$_POST['points']
        ];
        $success_message = "New question added to Quiz successfully!";
    }
}

$activities = get_all_activities();
$current_activity = get_activity_by_id($id);
if (!$current_activity && !empty($activities)) {
    $current_activity = reset($activities);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Activity Portal - Standalone Application</title>
    <!-- FontAwesome 6 Icons & Google Fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
    /* ----------------------------------------------------
       3. EMBEDDED SINGLE-FILE DESIGN SYSTEM (CSS)
       ---------------------------------------------------- */
    :root {
      --bg-primary: #0b0f19;
      --bg-secondary: #151c2c;
      --bg-card: rgba(21, 28, 44, 0.85);
      --bg-card-hover: rgba(30, 41, 59, 0.9);
      --sidebar-bg: #0d1322;
      --border-color: rgba(255, 255, 255, 0.1);
      --text-primary: #f8fafc;
      --text-secondary: #94a3b8;
      --text-muted: #64748b;
      --accent-primary: #6366f1;
      --accent-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
      --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.4);
      --radius-sm: 8px;
      --radius-md: 14px;
      --radius-lg: 20px;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', sans-serif;
      background-color: var(--bg-primary);
      color: var(--text-primary);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      line-height: 1.6;
    }
    a { color: inherit; text-decoration: none; }

    .app-container { display: flex; min-height: 100vh; width: 100%; }

    /* Sidebar Styling */
    .sidebar {
      width: 270px;
      background: var(--sidebar-bg);
      border-right: 1px solid var(--border-color);
      display: flex;
      flex-direction: column;
      position: fixed;
      top: 0; bottom: 0; left: 0;
      z-index: 200;
    }
    .sidebar-header {
      padding: 1.5rem;
      border-bottom: 1px solid var(--border-color);
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }
    .brand-logo {
      display: flex; align-items: center; gap: 0.75rem;
      font-size: 1.25rem; font-weight: 700; color: #fff;
    }
    .brand-logo i {
      background: var(--accent-gradient);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      font-size: 1.6rem;
    }
    .sidebar-menu {
      padding: 1.5rem 1rem;
      display: flex; flex-direction: column; gap: 0.5rem; flex: 1;
      overflow-y: auto;
    }
    .menu-label {
      font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em;
      color: var(--text-muted); margin: 1rem 0.5rem 0.4rem; font-weight: 700;
    }
    .sidebar-link {
      display: flex; align-items: center; gap: 0.85rem; padding: 0.75rem 1rem;
      border-radius: var(--radius-sm); color: var(--text-secondary);
      font-size: 0.92rem; font-weight: 500; transition: all 0.2s ease; cursor: pointer;
    }
    .sidebar-link:hover, .sidebar-link.active {
      background: rgba(99, 102, 241, 0.15); color: #fff; border-left: 3px solid #6366f1;
    }
    .sidebar-link i { font-size: 1.1rem; width: 22px; }

    .sidebar-user {
      padding: 1.25rem; border-top: 1px solid var(--border-color);
      display: flex; align-items: center; gap: 0.75rem; background: rgba(0, 0, 0, 0.2);
    }
    .avatar {
      width: 36px; height: 36px; border-radius: 50%; background: var(--accent-gradient);
      display: flex; align-items: center; justify-content: center;
      font-weight: 700; font-size: 0.85rem; color: white;
    }

    /* Main Content Area */
    .content-wrapper { margin-left: 270px; flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
    .top-navbar {
      background: rgba(11, 15, 25, 0.85); backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border-color); padding: 1rem 2rem;
      display: flex; justify-content: space-between; align-items: center;
      position: sticky; top: 0; z-index: 100;
    }
    .main-content { padding: 2rem; flex: 1; max-width: 1400px; width: 100%; margin: 0 auto; }

    /* Single Window Card Containers */
    .single-window-card {
      background: var(--bg-card); border: 1px solid var(--border-color);
      border-radius: var(--radius-lg); padding: 2rem; box-shadow: var(--shadow-lg); margin-bottom: 2rem;
    }
    .hero-banner {
      background: linear-gradient(135deg, rgba(21, 28, 44, 0.95) 0%, rgba(11, 15, 25, 0.95) 100%);
      border: 1px solid var(--border-color); border-radius: var(--radius-lg);
      padding: 2rem 2.5rem; margin-bottom: 2rem; position: relative; overflow: hidden;
    }
    .hero-content { position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem; }

    /* Section Header & Pills */
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; }
    .filter-pills { display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.25rem; }
    .pill {
      padding: 0.45rem 1.1rem; border-radius: 50px; background: rgba(255, 255, 255, 0.05);
      border: 1px solid var(--border-color); color: var(--text-secondary);
      font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease;
    }
    .pill:hover, .pill.active { background: var(--accent-gradient); color: white; border-color: transparent; }

    /* Tables */
    .table-responsive { overflow-x: auto; background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 2rem; }
    .custom-table { width: 100%; border-collapse: collapse; text-align: left; }
    .custom-table th, .custom-table td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; }
    .custom-table th { background: rgba(11, 15, 25, 0.7); color: var(--text-secondary); font-weight: 600; text-transform: uppercase; font-size: 0.78rem; letter-spacing: 0.05em; }
    .custom-table tr:hover td { background: rgba(255, 255, 255, 0.03); }

    /* Cards Grid */
    .activity-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
    .activity-card {
      background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md);
      padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between;
      position: relative; overflow: hidden; transition: all 0.3s ease;
    }
    .activity-card:hover { transform: translateY(-4px); border-color: rgba(255, 255, 255, 0.25); box-shadow: var(--shadow-lg); }
    .card-accent-bar { position: absolute; top: 0; left: 0; right: 0; height: 4px; }

    /* Buttons */
    .btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
      padding: 0.6rem 1.25rem; border-radius: var(--radius-sm); font-size: 0.88rem;
      font-weight: 600; cursor: pointer; border: none; transition: all 0.2s ease;
    }
    .btn-primary { background: var(--accent-gradient); color: white; }
    .btn-primary:hover { opacity: 0.92; transform: translateY(-1px); }
    .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-primary); }
    .btn-outline:hover { background: rgba(255, 255, 255, 0.08); }
    .btn-danger { background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); }
    .btn-danger:hover { background: #ef4444; color: white; }

    /* Forms */
    .form-group { margin-bottom: 1.25rem; }
    .form-group label { display: block; margin-bottom: 0.5rem; font-size: 0.88rem; color: var(--text-secondary); }
    .form-control {
      width: 100%; padding: 0.75rem 1rem; border-radius: var(--radius-sm);
      background: rgba(15, 23, 42, 0.6); border: 1px solid var(--border-color);
      color: var(--text-primary); font-size: 0.95rem; outline: none; transition: border 0.2s ease;
    }
    .form-control:focus { border-color: #6366f1; }

    /* Modals */
    .modal-overlay {
      position: fixed; top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 0, 0.8); backdrop-filter: blur(8px);
      display: none; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;
    }
    .modal-content {
      background: var(--bg-secondary); border: 1px solid var(--border-color);
      border-radius: var(--radius-lg); max-width: 650px; width: 100%; max-height: 90vh;
      overflow-y: auto; padding: 2rem; box-shadow: var(--shadow-lg);
    }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-color); }
    .close-btn { background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; }
    .close-btn:hover { color: #fff; }

    /* Badges & Headers */
    .activity-type-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.25rem 0.75rem; border-radius: 50px; font-size: 0.78rem; font-weight: 600; text-transform: uppercase; }
    .back-link { display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1.5rem; }
    .back-link:hover { color: #6366f1; }
    .page-header-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.75rem 2rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1.5rem; }
    footer { text-align: center; padding: 1.5rem; color: var(--text-muted); font-size: 0.85rem; border-top: 1px solid var(--border-color); margin-top: auto; }
    </style>
</head>
<body>

<div class="app-container">

    <!-- LEFT SIDEBAR LAYOUT -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <a href="standalone.php?view=dashboard" class="brand-logo">
                <i class="fa-solid fa-graduation-cap"></i>
                <span>Faculty Hub</span>
            </a>
        </div>

        <div class="sidebar-menu">
            <div class="menu-label">Navigation</div>
            <a href="standalone.php?view=dashboard" class="sidebar-link <?php echo ($view == 'dashboard') ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Dashboard & Matrix</span>
            </a>

            <a href="standalone.php?view=create" class="sidebar-link <?php echo ($view == 'create') ? 'active' : ''; ?>">
                <i class="fa-solid fa-circle-plus" style="color: #6366f1;"></i>
                <span>Create Activity</span>
            </a>

            <a href="standalone.php?view=recreate" class="sidebar-link <?php echo ($view == 'recreate') ? 'active' : ''; ?>">
                <i class="fa-solid fa-rotate-right" style="color: #f59e0b;"></i>
                <span>Recreate & Manage</span>
            </a>

            <div class="menu-label">Quick Activity Pages</div>
            <a href="standalone.php?view=quiz&id=1" class="sidebar-link <?php echo ($view == 'quiz') ? 'active' : ''; ?>">
                <i class="fa-solid fa-clipboard-question" style="color: #6366f1;"></i>
                <span>Quiz Activity</span>
            </a>
            <a href="standalone.php?view=poster_making&id=2" class="sidebar-link <?php echo ($view == 'poster_making') ? 'active' : ''; ?>">
                <i class="fa-solid fa-palette" style="color: #ec4899;"></i>
                <span>Poster Making</span>
            </a>
            <a href="standalone.php?view=ppt&id=3" class="sidebar-link <?php echo ($view == 'ppt') ? 'active' : ''; ?>">
                <i class="fa-solid fa-file-powerpoint" style="color: #f59e0b;"></i>
                <span>PPT Presentation</span>
            </a>
            <a href="standalone.php?view=case_study&id=4" class="sidebar-link <?php echo ($view == 'case_study') ? 'active' : ''; ?>">
                <i class="fa-solid fa-magnifying-glass-chart" style="color: #10b981;"></i>
                <span>Case Study</span>
            </a>
            <a href="standalone.php?view=gd&id=5" class="sidebar-link <?php echo ($view == 'gd') ? 'active' : ''; ?>">
                <i class="fa-solid fa-comments" style="color: #8b5cf6;"></i>
                <span>Group Discussion</span>
            </a>
            <a href="standalone.php?view=mini_project&id=6" class="sidebar-link <?php echo ($view == 'mini_project') ? 'active' : ''; ?>">
                <i class="fa-solid fa-laptop-code" style="color: #06b6d4;"></i>
                <span>Mini Project</span>
            </a>
        </div>

        <div class="sidebar-user">
            <div class="avatar">DR</div>
            <div>
                <div style="font-weight: 600; font-size: 0.88rem; color: #fff;">Dr. Rajesh Kumar</div>
                <div style="font-size: 0.75rem; color: var(--text-muted);">Senior Professor, CSE</div>
            </div>
        </div>
    </aside>

    <!-- CONTENT WRAPPER -->
    <div class="content-wrapper">
        <header class="top-navbar">
            <div>
                <h3 style="font-size: 1.1rem; color: var(--text-primary);">
                    <?php 
                    $titles = [
                        'dashboard' => 'Faculty Activity Workspace Hub',
                        'create' => 'Launch New Activity Project',
                        'recreate' => 'Recreate & Manage Activities Directory',
                        'quiz' => 'Quiz Management Portal',
                        'poster_making' => 'Poster Making Workspace',
                        'ppt' => 'PPT Presentation Portal',
                        'case_study' => 'Case Study Assignment Workspace',
                        'gd' => 'Group Discussion Portal',
                        'mini_project' => 'Mini Project Hub'
                    ];
                    echo isset($titles[$view]) ? $titles[$view] : 'Faculty Hub';
                    ?>
                </h3>
            </div>

            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="standalone.php?view=create" class="btn btn-primary" style="font-size: 0.85rem;">
                    <i class="fa-solid fa-plus"></i> Create Activity
                </a>
                <a href="standalone.php?view=recreate" class="btn btn-outline" style="font-size: 0.85rem; border-color: #f59e0b; color: #f59e0b;">
                    <i class="fa-solid fa-rotate-right"></i> Recreate & Manage
                </a>
            </div>
        </header>

        <main class="main-content">

        <!-- ALERTS -->
        <?php if (!empty($message)): ?>
            <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
                <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div style="background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #6ee7b7; padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
                <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
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
                        <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Single-Window Faculty Activity Hub</h1>
                        <p style="color: var(--text-secondary);">Manage, View, Edit, and Delete student activities across <strong>BEE, Chemistry, Physics, Maths, and CS</strong>.</p>
                    </div>
                    <div style="display: flex; gap: 0.75rem;">
                        <a href="standalone.php?view=create" class="btn btn-primary">
                            <i class="fa-solid fa-circle-plus"></i> + Create Activity
                        </a>
                        <a href="standalone.php?view=recreate" class="btn btn-outline" style="border-color: #f59e0b; color: #f59e0b;">
                            <i class="fa-solid fa-rotate-right"></i> Recreate & Manage
                        </a>
                    </div>
                </div>
            </div>

            <div class="single-window-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2><i class="fa-solid fa-window-maximize" style="color: #6366f1;"></i> Active Activity Projects Workspace</h2>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">View, Edit, or Delete active activity projects.</p>
                    </div>

                    <div class="filter-pills" id="unitFilterPills">
                        <button class="pill active" data-unit-filter="all">All Units</button>
                        <button class="pill" data-unit-filter="Unit 1">Unit 1</button>
                        <button class="pill" data-unit-filter="Unit 2">Unit 2</button>
                        <button class="pill" data-unit-filter="Unit 3">Unit 3</button>
                        <button class="pill" data-unit-filter="Unit 4">Unit 4</button>
                        <button class="pill" data-unit-filter="Unit 5">Unit 5</button>
                    </div>
                </div>

                <div class="filter-pills" style="margin-bottom: 1.5rem;" id="subjectFilterPills">
                    <span style="font-size: 0.85rem; color: var(--text-muted); align-self: center; font-weight: 600;">Subject Filter:</span>
                    <button class="pill active" data-subject-filter="all">All Subjects</button>
                    <button class="pill" data-subject-filter="BEE"><i class="fa-solid fa-bolt"></i> BEE</button>
                    <button class="pill" data-subject-filter="Chemistry"><i class="fa-solid fa-flask"></i> Chemistry</button>
                    <button class="pill" data-subject-filter="Physics"><i class="fa-solid fa-atom"></i> Physics</button>
                    <button class="pill" data-subject-filter="Maths"><i class="fa-solid fa-calculator"></i> Maths</button>
                </div>

                <div class="activity-grid">
                    <?php 
                    $sr_counter = 1;
                    foreach ($activities as $act): 
                        $type_map = [
                            'quiz' => ['name' => 'Quiz', 'icon' => 'fa-clipboard-question', 'color' => '#6366f1'],
                            'poster_making' => ['name' => 'Poster Making', 'icon' => 'fa-palette', 'color' => '#ec4899'],
                            'ppt' => ['name' => 'PPT Presentation', 'icon' => 'fa-file-powerpoint', 'color' => '#f59e0b'],
                            'case_study' => ['name' => 'Case Study', 'icon' => 'fa-magnifying-glass-chart', 'color' => '#10b981'],
                            'gd' => ['name' => 'Group Discussion', 'icon' => 'fa-comments', 'color' => '#8b5cf6'],
                            'mini_project' => ['name' => 'Mini Project', 'icon' => 'fa-laptop-code', 'color' => '#06b6d4']
                        ];
                        $subj = isset($act['subject']) ? $act['subject'] : 'General';
                        $unit_val = isset($act['unit']) ? $act['unit'] : 'Unit 1';
                        $t_info = isset($type_map[$act['type']]) ? $type_map[$act['type']] : ['name' => ucfirst($act['type']), 'icon' => 'fa-tasks', 'color' => '#3b82f6'];
                        $dedicated_url = "standalone.php?view=" . $act['type'] . "&id=" . $act['id'];
                        $progress = round(($act['submissions_count'] / max($act['total_students'], 1)) * 100);
                        $act_json = htmlspecialchars(json_encode($act), ENT_QUOTES, 'UTF-8');
                        $current_sr_no = isset($act['sr_no']) ? $act['sr_no'] : $sr_counter;
                    ?>
                    <div class="activity-card" data-type="<?php echo $act['type']; ?>" data-subject="<?php echo $subj; ?>" data-unit="<?php echo $unit_val; ?>">
                        <div class="card-accent-bar" style="background: <?php echo $t_info['color']; ?>;"></div>
                        
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; gap: 0.4rem; align-items: center;">
                                    <span style="font-size: 0.75rem; font-weight: 800; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(99, 102, 241, 0.25); color: #818cf8; border: 1px solid rgba(129, 140, 248, 0.4);">
                                        Sr. No. #<?php echo $current_sr_no; ?>
                                    </span>
                                    <span style="font-size: 0.75rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.4);">
                                        <i class="fa-solid fa-bookmark"></i> <?php echo $unit_val; ?>
                                    </span>
                                </div>

                                <span class="activity-type-badge" style="background: <?php echo $t_info['color']; ?>22; color: <?php echo $t_info['color']; ?>; border: 1px solid <?php echo $t_info['color']; ?>44;">
                                    <i class="fa-solid <?php echo $t_info['icon']; ?>"></i> <?php echo $t_info['name']; ?>
                                </span>
                            </div>

                            <h3 style="margin-top: 0.75rem; font-size: 1.1rem;"><?php echo htmlspecialchars($act['title']); ?></h3>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;"><?php echo htmlspecialchars($act['description']); ?></p>
                            
                            <div style="font-size: 0.82rem; display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 1.25rem;">
                                <div><i class="fa-solid fa-graduation-cap" style="color: #38bdf8;"></i> <strong style="color: #38bdf8;"><?php echo $subj; ?></strong> &bull; <?php echo htmlspecialchars($act['course']); ?></div>
                                <div><i class="fa-solid fa-users" style="color: var(--text-muted);"></i> <?php echo htmlspecialchars($act['batch']); ?></div>
                                <div><i class="fa-solid fa-clock" style="color: var(--text-muted);"></i> Due: <?php echo date('M d, Y H:i', strtotime($act['deadline'])); ?></div>
                            </div>
                        </div>

                        <div>
                            <div style="display: flex; justify-content: space-between; font-size: 0.82rem; color: var(--text-secondary); margin-bottom: 0.4rem;">
                                <span>Submissions Progress</span>
                                <span style="font-weight: 600; color: #fff;"><?php echo $act['submissions_count']; ?> / <?php echo $act['total_students']; ?> (<?php echo $progress; ?>%)</span>
                            </div>
                            <div style="height: 6px; background: rgba(255,255,255,0.1); border-radius: 4px; overflow: hidden; margin-bottom: 1.2rem;">
                                <div style="width: <?php echo $progress; ?>%; height: 100%; background: <?php echo $t_info['color']; ?>; border-radius: 4px;"></div>
                            </div>

                            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                                <a href="<?php echo $dedicated_url; ?>" class="btn btn-outline" style="padding: 0.45rem 0.75rem; font-size: 0.82rem;">
                                    <i class="fa-solid fa-eye" style="color: #38bdf8;"></i> View
                                </a>
                                <button class="btn btn-outline" style="padding: 0.45rem 0.75rem; font-size: 0.82rem; border-color: #f59e0b;" onclick="openEditModal(<?php echo $act_json; ?>)">
                                    <i class="fa-solid fa-pen-to-square" style="color: #f59e0b;"></i> Edit
                                </button>
                                <form action="standalone.php?view=dashboard" method="POST" style="display: inline;" onsubmit="return confirm('Delete this activity?');">
                                    <input type="hidden" name="action" value="delete_activity">
                                    <input type="hidden" name="activity_id" value="<?php echo $act['id']; ?>">
                                    <button type="submit" class="btn btn-danger" style="padding: 0.45rem 0.75rem; font-size: 0.82rem;">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php 
                    $sr_counter++;
                    endforeach; 
                    ?>
                </div>
            </div>

        <?php
        // VIEW 2: CREATE ACTIVITY WORKSPACE
        elseif ($view === 'create'):
        ?>
            <a href="standalone.php?view=dashboard" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

            <div class="single-window-card" style="border-top: 5px solid #6366f1; padding: 2.5rem;">
                <div style="margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.25rem;">
                    <h1 style="font-size: 2rem; color: #fff; margin-bottom: 0.5rem;">
                        <i class="fa-solid fa-circle-plus" style="color: #6366f1;"></i> Launch New Activity Project
                    </h1>
                    <p style="color: var(--text-secondary); font-size: 1rem;">
                        Fill out the details below to assign a new activity to your students. Full screen workspace for fast setup.
                    </p>
                </div>

                <form action="standalone.php" method="POST">
                    <input type="hidden" name="action" value="create_activity">
                    
                    <div class="form-group" style="margin-bottom: 1.75rem;">
                        <label style="font-size: 1rem; font-weight: 600; color: #fff;">Activity Project Title *</label>
                        <input type="text" name="title" class="form-control" style="font-size: 1.1rem; padding: 0.9rem 1.2rem;" placeholder="e.g. BEE Unit 1 Kirchhoff's Laws Quiz" required>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 1.75rem;">
                        <div class="form-group">
                            <label style="font-weight: 600;">Subject *</label>
                            <select id="create_subject" name="subject" class="form-control" style="padding: 0.85rem;" required onchange="autoFillCourse(this.value)">
                                <option value="BEE">⚡ BEE (Basic Electrical Engg)</option>
                                <option value="Chemistry">🧪 Engineering Chemistry</option>
                                <option value="Physics">⚛️ Engineering Physics</option>
                                <option value="Maths">📐 Engineering Mathematics</option>
                                <option value="Computer Science">💻 Computer Science & Engg</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="font-weight: 600;">Syllabus Unit *</label>
                            <select name="unit" class="form-control" style="padding: 0.85rem;" required>
                                <option value="Unit 1">📌 Unit 1: Fundamentals</option>
                                <option value="Unit 2">📌 Unit 2: Advanced Analysis</option>
                                <option value="Unit 3">📌 Unit 3: Applications & Design</option>
                                <option value="Unit 4">📌 Unit 4: Special Topics & Lab</option>
                                <option value="Unit 5">📌 Unit 5: Project & Synthesis</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label style="font-weight: 600;">Activity Category *</label>
                            <select name="type" class="form-control" style="padding: 0.85rem;" required>
                                <option value="quiz">📝 Quiz / Online Test</option>
                                <option value="poster_making">🎨 Poster Making</option>
                                <option value="ppt">📊 PPT Presentation</option>
                                <option value="case_study">🔍 Case Study Assignment</option>
                                <option value="gd">💬 Group Discussion (GD)</option>
                                <option value="mini_project">🚀 Mini Project Portal</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.75rem;">
                        <div class="form-group">
                            <label style="font-weight: 600;">Course Code / Full Name *</label>
                            <input type="text" id="create_course" name="course" class="form-control" style="padding: 0.85rem;" value="BEE101 - Basic Electrical Engineering" required>
                        </div>
                        <div class="form-group">
                            <label style="font-weight: 600;">Target Student Batch / Section *</label>
                            <input type="text" name="batch" class="form-control" style="padding: 0.85rem;" value="2025-29 (Sec A & B)" required>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.75rem;">
                        <div class="form-group">
                            <label style="font-weight: 600;">Submission Deadline *</label>
                            <input type="datetime-local" name="deadline" class="form-control" style="padding: 0.85rem;" value="<?php echo date('Y-m-d\TH:i', strtotime('+7 days')); ?>" required>
                        </div>
                        <div class="form-group">
                            <label style="font-weight: 600;">Total Max Marks *</label>
                            <input type="number" name="total_marks" class="form-control" style="padding: 0.85rem;" value="50" min="5" max="500" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 2rem;">
                        <label style="font-weight: 600;">Activity Instructions & Guidelines</label>
                        <textarea name="description" class="form-control" rows="5" placeholder="Provide submission guidelines and instructions..."></textarea>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1.75rem;">
                        <a href="standalone.php?view=dashboard" class="btn btn-outline" style="padding: 0.85rem 1.75rem; font-size: 1rem;">Cancel</a>
                        <button type="submit" class="btn btn-primary" style="padding: 0.85rem 2.25rem; font-size: 1rem;">
                            <i class="fa-solid fa-paper-plane"></i> Submit & Launch Activity
                        </button>
                    </div>
                </form>
            </div>

        <?php
        // VIEW 3: RECREATE & MANAGE DIRECTORY
        elseif ($view === 'recreate'):
        ?>
            <div class="hero-banner" style="padding: 1.75rem 2rem; margin-bottom: 2rem;">
                <div class="hero-content">
                    <div>
                        <h1 style="font-size: 1.8rem;"><i class="fa-solid fa-rotate-right" style="color: #f59e0b;"></i> Recreate and Manage Activities</h1>
                        <p style="color: var(--text-secondary); font-size: 0.95rem;">
                            View, Edit, Delete, or <strong>Recreate Activities</strong> for new batches. Use the <strong>Autofill Marks with Deduction</strong> tool below.
                        </p>
                    </div>
                    <a href="standalone.php?view=create" class="btn btn-primary">
                        <i class="fa-solid fa-plus-circle"></i> Create New Activity
                    </a>
                </div>
            </div>

            <!-- AUTOFILL SCORE DEDUCTION TOOL -->
            <div class="single-window-card" style="border-left: 5px solid #10b981; padding: 1.5rem; margin-bottom: 2rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
                    <div>
                        <h3 style="color: #10b981; margin-bottom: 0.3rem;"><i class="fa-solid fa-calculator"></i> Autofill Marks with Late Submission Deduction</h3>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;">
                            Automatically calculate final scores. On-time submissions get 100%; late submissions receive automated mark deduction.
                        </p>
                    </div>
                    
                    <form action="standalone.php?view=recreate" method="POST" style="display: flex; align-items: center; gap: 0.75rem;">
                        <input type="hidden" name="action" value="autofill_deduction">
                        <select name="deduction_rate" class="form-control" style="width: auto; background: #1e293b;">
                            <option value="5">5% Deduction per Day Late</option>
                            <option value="10">10% Deduction per Day Late</option>
                            <option value="15">15% Deduction per Day Late</option>
                        </select>
                        <button type="submit" class="btn btn-primary" style="background: #10b981; border: none; font-size: 0.85rem;">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> Autofill Scores Now
                        </button>
                    </form>
                </div>
            </div>

            <div class="section-header">
                <h2><i class="fa-solid fa-list-check"></i> All Activity Projects Directory</h2>
                <span style="color: var(--text-secondary); font-size: 0.9rem;">Total Activities: <strong><?php echo count($activities); ?></strong></span>
            </div>

            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>Sr. No.</th>
                            <th>Activity Title & Category</th>
                            <th>Subject</th>
                            <th>Unit</th>
                            <th>Marks</th>
                            <th>Deadline</th>
                            <th style="text-align: center;">Actions (View / Edit / Recreate / Delete)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $sr_counter = 1;
                        foreach ($activities as $act): 
                            $view_url = "standalone.php?view=" . $act['type'] . "&id=" . $act['id'];
                            $act_json = htmlspecialchars(json_encode($act), ENT_QUOTES, 'UTF-8');
                            $current_sr_no = isset($act['sr_no']) ? $act['sr_no'] : $sr_counter;
                            $subj = isset($act['subject']) ? $act['subject'] : 'General';
                            $unit_val = isset($act['unit']) ? $act['unit'] : 'Unit 1';
                        ?>
                        <tr>
                            <td>
                                <span style="font-size: 0.8rem; font-weight: 800; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(99, 102, 241, 0.25); color: #818cf8; border: 1px solid rgba(129, 140, 248, 0.4);">
                                    #<?php echo $current_sr_no; ?>
                                </span>
                            </td>
                            <td>
                                <strong style="font-size: 0.95rem; color: #fff;"><?php echo htmlspecialchars($act['title']); ?></strong><br>
                                <small style="color: var(--text-muted); text-transform: uppercase; font-weight: 600;"><?php echo htmlspecialchars($act['type_name']); ?></small>
                            </td>
                            <td><span style="font-weight: 700; color: #38bdf8; font-size: 0.85rem; padding: 0.2rem 0.5rem; border-radius: 4px; background: rgba(56, 189, 248, 0.1);"><?php echo $subj; ?></span></td>
                            <td><span style="font-weight: 700; color: #fbbf24; font-size: 0.85rem; padding: 0.2rem 0.5rem; border-radius: 4px; background: rgba(251, 191, 36, 0.1);"><?php echo $unit_val; ?></span></td>
                            <td><strong style="color: #10b981; font-size: 0.95rem;"><?php echo $act['total_marks']; ?> pts</strong></td>
                            <td><small style="color: var(--text-secondary);"><?php echo date('M d, Y H:i', strtotime($act['deadline'])); ?></small></td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 0.4rem; justify-content: center;">
                                    <a href="<?php echo $view_url; ?>" class="btn btn-outline" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
                                        <i class="fa-solid fa-eye" style="color: #38bdf8;"></i> View
                                    </a>
                                    <button class="btn btn-outline" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; border-color: #f59e0b;" onclick="openEditModal(<?php echo $act_json; ?>)">
                                        <i class="fa-solid fa-pen-to-square" style="color: #f59e0b;"></i> Edit
                                    </button>
                                    <form action="standalone.php?view=recreate" method="POST" style="display: inline;" onsubmit="return confirm('Recreate this activity for a new batch?');">
                                        <input type="hidden" name="action" value="recreate">
                                        <input type="hidden" name="activity_id" value="<?php echo $act['id']; ?>">
                                        <button type="submit" class="btn btn-outline" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; border-color: #8b5cf6;">
                                            <i class="fa-solid fa-rotate-right" style="color: #8b5cf6;"></i> Recreate
                                        </button>
                                    </form>
                                    <form action="standalone.php?view=recreate" method="POST" style="display: inline;" onsubmit="return confirm('Delete this activity?');">
                                        <input type="hidden" name="action" value="delete_activity">
                                        <input type="hidden" name="activity_id" value="<?php echo $act['id']; ?>">
                                        <button type="submit" class="btn btn-danger" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;">
                                            <i class="fa-solid fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php 
                        $sr_counter++;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>

        <?php
        // VIEW 4: QUIZ PAGE
        elseif ($view === 'quiz'):
            if (!isset($_SESSION['quiz_questions_' . $id])) {
                $_SESSION['quiz_questions_' . $id] = [
                    ['q' => 'Which component is used to limit current in an AC circuit?', 'a' => 'Resistor', 'b' => 'Inductor', 'c' => 'Capacitor', 'd' => 'All of above', 'correct' => 'd', 'points' => 2],
                    ['q' => 'What is the phase angle between voltage and current in a purely resistive circuit?', 'a' => '0°', 'b' => '90°', 'c' => '180°', 'd' => '45°', 'correct' => 'a', 'points' => 2]
                ];
            }
            $questions = $_SESSION['quiz_questions_' . $id];
        ?>
            <a href="standalone.php?view=dashboard" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

            <div class="page-header-box" style="border-left: 5px solid #6366f1;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.8rem; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: 6px; background: rgba(99, 102, 241, 0.25); color: #818cf8; border: 1px solid rgba(129, 140, 248, 0.4);">
                            Sr. No. #<?php echo isset($current_activity['sr_no']) ? $current_activity['sr_no'] : $id; ?>
                        </span>
                        <span class="activity-type-badge" style="background: rgba(99, 102, 241, 0.2); color: #6366f1;">
                            <i class="fa-solid fa-clipboard-question"></i> Quiz Portal
                        </span>
                        <span style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo htmlspecialchars($current_activity['course']); ?></span>
                    </div>
                    <h1 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($current_activity['title']); ?></h1>
                    <p style="color: var(--text-secondary); max-width: 800px;"><?php echo htmlspecialchars($current_activity['description']); ?></p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="openModal('addQuestionModal')">
                        <i class="fa-solid fa-plus"></i> Add Question
                    </button>
                </div>
            </div>

            <div style="margin-bottom: 3rem;">
                <h3 style="margin-bottom: 1.25rem;"><i class="fa-solid fa-list-ol"></i> Quiz Question Paper Preview</h3>
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <?php foreach ($questions as $idx => $q): ?>
                    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.75rem;">
                            <strong style="color: #818cf8;">Q<?php echo $idx + 1; ?>: <?php echo htmlspecialchars($q['q']); ?></strong>
                            <span style="font-size: 0.8rem; background: rgba(99, 102, 241, 0.2); color: #818cf8; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600;"><?php echo $q['points']; ?> Marks</span>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; font-size: 0.9rem; color: var(--text-secondary);">
                            <div>A) <?php echo htmlspecialchars($q['a']); ?></div>
                            <div>B) <?php echo htmlspecialchars($q['b']); ?></div>
                            <div>C) <?php echo htmlspecialchars($q['c']); ?></div>
                            <div>D) <?php echo htmlspecialchars($q['d']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Modal: Add Question -->
            <div class="modal-overlay" id="addQuestionModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3>Add Quiz Question</h3>
                        <button class="close-btn" onclick="closeModal('addQuestionModal')">&times;</button>
                    </div>
                    <form action="standalone.php?view=quiz&id=<?php echo $id; ?>" method="POST">
                        <input type="hidden" name="action" value="add_question">
                        <input type="hidden" name="activity_id" value="<?php echo $id; ?>">
                        <div class="form-group">
                            <label>Question Text *</label>
                            <input type="text" name="question" class="form-control" required>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group"><label>Option A *</label><input type="text" name="opt_a" class="form-control" required></div>
                            <div class="form-group"><label>Option B *</label><input type="text" name="opt_b" class="form-control" required></div>
                            <div class="form-group"><label>Option C *</label><input type="text" name="opt_c" class="form-control" required></div>
                            <div class="form-group"><label>Option D *</label><input type="text" name="opt_d" class="form-control" required></div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label>Correct Answer *</label>
                                <select name="correct" class="form-control" style="background: #1e293b;">
                                    <option value="a">Option A</option>
                                    <option value="b">Option B</option>
                                    <option value="c">Option C</option>
                                    <option value="d">Option D</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Marks *</label>
                                <input type="number" name="points" class="form-control" value="2" min="1" required>
                            </div>
                        </div>
                        <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem;">
                            <button type="button" class="btn btn-outline" onclick="closeModal('addQuestionModal')">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save Question</button>
                        </div>
                    </form>
                </div>
            </div>

        <?php
        // VIEW 5: POSTER MAKING / PPT / CASE STUDY / GD / MINI PROJECT VIEWS
        elseif (in_array($view, ['poster_making', 'ppt', 'case_study', 'gd', 'mini_project'])):
            $badge_colors = [
                'poster_making' => ['color' => '#ec4899', 'icon' => 'fa-palette', 'name' => 'Poster Making'],
                'ppt' => ['color' => '#f59e0b', 'icon' => 'fa-file-powerpoint', 'name' => 'PPT Presentation'],
                'case_study' => ['color' => '#10b981', 'icon' => 'fa-magnifying-glass-chart', 'name' => 'Case Study Assignment'],
                'gd' => ['color' => '#8b5cf6', 'icon' => 'fa-comments', 'name' => 'Group Discussion (GD)'],
                'mini_project' => ['color' => '#06b6d4', 'icon' => 'fa-laptop-code', 'name' => 'Mini Project Portal']
            ];
            $b_info = $badge_colors[$view];
        ?>
            <a href="standalone.php?view=dashboard" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

            <div class="page-header-box" style="border-left: 5px solid <?php echo $b_info['color']; ?>;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.8rem; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: 6px; background: rgba(99, 102, 241, 0.25); color: #818cf8; border: 1px solid rgba(129, 140, 248, 0.4);">
                            Sr. No. #<?php echo isset($current_activity['sr_no']) ? $current_activity['sr_no'] : $id; ?>
                        </span>
                        <span class="activity-type-badge" style="background: <?php echo $b_info['color']; ?>22; color: <?php echo $b_info['color']; ?>;">
                            <i class="fa-solid <?php echo $b_info['icon']; ?>"></i> <?php echo $b_info['name']; ?>
                        </span>
                        <span style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo htmlspecialchars($current_activity['course']); ?></span>
                    </div>
                    <h1 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($current_activity['title']); ?></h1>
                    <p style="color: var(--text-secondary); max-width: 800px;"><?php echo htmlspecialchars($current_activity['description']); ?></p>
                </div>
                <div>
                    <button class="btn btn-outline" style="border-color: #f59e0b; color: #f59e0b;" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($current_activity), ENT_QUOTES, 'UTF-8'); ?>)">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Details
                    </button>
                </div>
            </div>

            <!-- Detailed Activity Workspace Card -->
            <div class="single-window-card">
                <h3 style="margin-bottom: 1.25rem;"><i class="fa-solid fa-user-check" style="color: <?php echo $b_info['color']; ?>;"></i> Student Submissions & Evaluation Sheet</h3>
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Roll No.</th>
                                <th>Student Name</th>
                                <th>Submission Status</th>
                                <th>File / Link</th>
                                <th>Score / Marks (Max <?php echo $current_activity['total_marks']; ?>)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>25CSE01</strong></td>
                                <td>Aarav Sharma</td>
                                <td><span style="color: #10b981; font-weight: 600;"><i class="fa-solid fa-circle-check"></i> Submitted On Time</span></td>
                                <td><a href="#" style="color: #38bdf8;"><i class="fa-solid fa-file-arrow-down"></i> submission_doc_01.pdf</a></td>
                                <td><strong style="color: #10b981;"><?php echo round($current_activity['total_marks'] * 0.9); ?> / <?php echo $current_activity['total_marks']; ?></strong></td>
                            </tr>
                            <tr>
                                <td><strong>25CSE02</strong></td>
                                <td>Bhavna Patel</td>
                                <td><span style="color: #10b981; font-weight: 600;"><i class="fa-solid fa-circle-check"></i> Submitted On Time</span></td>
                                <td><a href="#" style="color: #38bdf8;"><i class="fa-solid fa-file-arrow-down"></i> submission_doc_02.pdf</a></td>
                                <td><strong style="color: #10b981;"><?php echo round($current_activity['total_marks'] * 0.95); ?> / <?php echo $current_activity['total_marks']; ?></strong></td>
                            </tr>
                            <tr>
                                <td><strong>25CSE03</strong></td>
                                <td>Chirag Verma</td>
                                <td><span style="color: #f59e0b; font-weight: 600;"><i class="fa-solid fa-clock"></i> Late Submission (1 Day)</span></td>
                                <td><a href="#" style="color: #38bdf8;"><i class="fa-solid fa-file-arrow-down"></i> submission_doc_03.pdf</a></td>
                                <td><strong style="color: #f59e0b;"><?php echo round($current_activity['total_marks'] * 0.8); ?> / <?php echo $current_activity['total_marks']; ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>

        </main> <!-- End main-content -->

        <footer>
            <p>&copy; <?php echo date('Y'); ?> Faculty Activity Management Portal. Merged Standalone Academic System for XAMPP.</p>
        </footer>
    </div>
</div>

<!-- GLOBAL MODAL: EDIT ACTIVITY -->
<div class="modal-overlay" id="editActivityModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-pen-to-square" style="color: #f59e0b;"></i> Edit Activity Project Details</h3>
            <button class="close-btn" onclick="closeModal('editActivityModal')">&times;</button>
        </div>
        
        <form action="standalone.php?view=<?php echo $view; ?>&id=<?php echo $id; ?>" method="POST">
            <input type="hidden" name="action" value="edit_activity">
            <input type="hidden" name="activity_id" id="edit_id">
            
            <div class="form-group">
                <label>Project Title *</label>
                <input type="text" id="edit_title" name="title" class="form-control" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Subject *</label>
                    <select id="edit_subject" name="subject" class="form-control" required style="background: #1e293b;">
                        <option value="BEE">⚡ BEE</option>
                        <option value="Chemistry">🧪 Chemistry</option>
                        <option value="Physics">⚛️ Physics</option>
                        <option value="Maths">📐 Maths</option>
                        <option value="Computer Science">💻 Computer Science</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Syllabus Unit *</label>
                    <select id="edit_unit" name="unit" class="form-control" required style="background: #1e293b;">
                        <option value="Unit 1">Unit 1</option>
                        <option value="Unit 2">Unit 2</option>
                        <option value="Unit 3">Unit 3</option>
                        <option value="Unit 4">Unit 4</option>
                        <option value="Unit 5">Unit 5</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Category *</label>
                    <select id="edit_type" name="type" class="form-control" required style="background: #1e293b;">
                        <option value="quiz">📝 Quiz</option>
                        <option value="poster_making">🎨 Poster Making</option>
                        <option value="ppt">📊 PPT Presentation</option>
                        <option value="case_study">🔍 Case Study</option>
                        <option value="gd">💬 Group Discussion</option>
                        <option value="mini_project">🚀 Mini Project</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Course Code / Name *</label>
                    <input type="text" id="edit_course" name="course" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Target Batch / Section *</label>
                    <input type="text" id="edit_batch" name="batch" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label>Submission Deadline *</label>
                    <input type="datetime-local" id="edit_deadline" name="deadline" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Total Marks *</label>
                    <input type="number" id="edit_total_marks" name="total_marks" class="form-control" min="5" max="500" required>
                </div>
            </div>

            <div class="form-group">
                <label>Instructions & Description</label>
                <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-outline" onclick="closeModal('editActivityModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ----------------------------------------------------
     5. EMBEDDED JAVASCRIPT LOGIC
     ---------------------------------------------------- -->
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

  // Unit Filtering
  const unitPills = document.querySelectorAll('#unitFilterPills .pill');
  unitPills.forEach(pill => {
    pill.addEventListener('click', () => {
      unitPills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      currentUnit = pill.getAttribute('data-unit-filter');
      filterCards();
    });
  });

  // Subject Filtering
  const subjectPills = document.querySelectorAll('#subjectFilterPills .pill');
  subjectPills.forEach(pill => {
    pill.addEventListener('click', () => {
      subjectPills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      currentSubject = pill.getAttribute('data-subject-filter');
      filterCards();
    });
  });

  // Modal Open/Close Logic
  window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'flex';
  };

  window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.style.display = 'none';
  };

  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) overlay.style.display = 'none';
    });
  });
});

function openEditModal(act) {
    document.getElementById('edit_id').value = act.id;
    document.getElementById('edit_title').value = act.title;
    document.getElementById('edit_subject').value = act.subject || 'BEE';
    document.getElementById('edit_unit').value = act.unit || 'Unit 1';
    document.getElementById('edit_type').value = act.type;
    document.getElementById('edit_course').value = act.course;
    document.getElementById('edit_batch').value = act.batch;
    
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
</script>

</body>
</html>
