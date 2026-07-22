<?php
// config/db.php - Faculty Activity Portal DB Configuration

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'faculty_activity_db';

$conn = null;
$db_connected = false;

try {
    // Attempt PDO Connection
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $db_connected = true;
} catch (PDOException $e) {
    $db_connected = false;
    $db_error = $e->getMessage();
}

// Session-based sample data fallback if database connection isn't configured yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

/**
 * Get all activities
 */
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

/**
 * Get activity by ID
 */
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

/**
 * Add a new activity
 */
function add_new_activity($data) {
    global $conn, $db_connected;
    if ($db_connected) {
        try {
            $stmt = $conn->prepare("INSERT INTO activities (title, type, course, subject, unit, batch, deadline, total_marks, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Active', ?)");
            $stmt->execute([
                $data['title'],
                $data['type'],
                $data['course'],
                $data['subject'],
                isset($data['unit']) ? $data['unit'] : 'Unit 1',
                $data['batch'],
                $data['deadline'],
                $data['total_marks'],
                $data['description']
            ]);
            return $conn->lastInsertId();
        } catch (Exception $e) {}
    }
    
    $new_id = count($_SESSION['activities']) + 1;
    
    $type_map = [
        'quiz' => ['name' => 'Quiz', 'icon' => 'fa-clipboard-question', 'color' => '#6366f1'],
        'poster_making' => ['name' => 'Poster Making', 'icon' => 'fa-palette', 'color' => '#ec4899'],
        'ppt' => ['name' => 'PPT Presentation', 'icon' => 'fa-file-powerpoint', 'color' => '#f59e0b'],
        'case_study' => ['name' => 'Case Study', 'icon' => 'fa-magnifying-glass-chart', 'color' => '#10b981'],
        'gd' => ['name' => 'Group Discussion', 'icon' => 'fa-comments', 'color' => '#8b5cf6'],
        'mini_project' => ['name' => 'Mini Project', 'icon' => 'fa-laptop-code', 'color' => '#06b6d4']
    ];
    
    $meta = isset($type_map[$data['type']]) ? $type_map[$data['type']] : ['name' => ucfirst($data['type']), 'icon' => 'fa-tasks', 'color' => '#3b82f6'];
    
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

/**
 * Update an existing activity
 */
function update_activity($id, $data) {
    global $conn, $db_connected;
    if ($db_connected) {
        try {
            $stmt = $conn->prepare("UPDATE activities SET title = ?, type = ?, course = ?, subject = ?, unit = ?, batch = ?, deadline = ?, total_marks = ?, description = ? WHERE id = ?");
            $stmt->execute([
                $data['title'],
                $data['type'],
                $data['course'],
                $data['subject'],
                isset($data['unit']) ? $data['unit'] : 'Unit 1',
                $data['batch'],
                $data['deadline'],
                $data['total_marks'],
                $data['description'],
                $id
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

/**
 * Delete an activity
 */
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

/**
 * Reset First Activity (Clears submissions and resets deadline)
 */
function reset_activity($id) {
    global $conn, $db_connected;
    if ($db_connected) {
        try {
            $stmt = $conn->prepare("UPDATE activities SET status = 'Active', deadline = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id = ?");
            $stmt->execute([$id]);
            
            // Delete submissions for this activity
            $stmt_sub = $conn->prepare("DELETE FROM submissions WHERE activity_id = ?");
            $stmt_sub->execute([$id]);
            return true;
        } catch (Exception $e) {}
    }

    if (isset($_SESSION['activities'][$id])) {
        $_SESSION['activities'][$id]['submissions_count'] = 0;
        $_SESSION['activities'][$id]['status'] = 'Active';
        $_SESSION['activities'][$id]['deadline'] = date('Y-m-d\TH:i', strtotime('+7 days'));
        
        // Clear session question/submission buffers
        unset($_SESSION['quiz_questions_' . $id]);
        return true;
    }
    return false;
}
