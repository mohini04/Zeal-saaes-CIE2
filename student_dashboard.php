<?php
$studentName = "Mohini Deore";
$rollNo = "SE-2401";
$prnNo = "72202685E";
$deptName = "Electronics & Telecommunication (E&TC)";
$division = "SE Division A";
$semester = "Semester III (SE)";
$collegeName = "Zeal College of Engineering & Research, Pune";

$stats = [
    "totalActivities" => 12,
    "submittedActivities" => 9,
    "pendingActivities" => 3,
    "totalMarks" => "42 / 50",
    "averageScore" => 84,
    "activityRank" => "#8",
    "cgpa" => 8.65,
    "classRank" => 8,
    "attendance" => 91
];

$activities = [
    ["id" => "ACT-01", "unit" => "Unit 1", "name" => "Processor Basics", "faculty" => "Prof. Patil", "duedate" => "20 Jul", "status" => "Submitted"],
    ["id" => "ACT-02", "unit" => "Unit 2", "name" => "Number System", "faculty" => "Prof. Kulkarni", "duedate" => "25 Jul", "status" => "Pending"],
    ["id" => "ACT-03", "unit" => "Unit 3", "name" => "Boolean Algebra", "faculty" => "Prof. Patil", "duedate" => "30 Jul", "status" => "Upcoming"],
    ["id" => "ACT-04", "unit" => "Unit 2", "name" => "Number System Conversion", "faculty" => "Prof. Kulkarni", "duedate" => "20 Jul", "status" => "Pending"],
    ["id" => "ACT-05", "unit" => "Unit 3", "name" => "Flip-Flop Analysis", "faculty" => "Prof. Patil", "duedate" => "28 Jul", "status" => "Pending"]
];

$submissions = [
    ["id" => "SUB-01", "name" => "K-Map Problems", "date" => "16 Jul", "score" => "5/5", "feedback" => "Excellent", "status" => "Evaluated"],
    ["id" => "SUB-02", "name" => "Number System", "date" => "15 Jul", "score" => "4/5", "feedback" => "Good", "status" => "Evaluated"],
    ["id" => "SUB-03", "name" => "Processor Basics", "date" => "14 Jul", "score" => "Awaiting", "feedback" => "Pending", "status" => "Under Review"]
];

$deadlines = [
    ["date" => "20 Jul", "activity" => "Number System Conversion"],
    ["date" => "23 Jul", "activity" => "Boolean Algebra"],
    ["date" => "28 Jul", "activity" => "Flip-Flop Analysis"],
    ["date" => "02 Aug", "activity" => "Combinational Circuits"]
];

$attendance = [
    ["subject" => "Digital Logic", "rate" => 92],
    ["subject" => "Microprocessor", "rate" => 88],
    ["subject" => "DSP", "rate" => 90]
];

$feedback = [
    ["faculty" => "Prof. Patil", "activity" => "K-Map", "text" => "Excellent work"],
    ["faculty" => "Prof. Kulkarni", "activity" => "Number System", "text" => "Improve explanation"]
];

$notifications = [
    ["id" => "NTF-01", "message" => "New Activity Assigned: Boolean Algebra", "level" => "success"],
    ["id" => "NTF-02", "message" => "Marks Published: Unit 2 Lab Assessment", "level" => "info"],
    ["id" => "NTF-03", "message" => "Department Notice: Mid-Term Schedule Released", "level" => "purple"],
    ["id" => "NTF-04", "message" => "Internal Assessment Schedule Released", "level" => "warning"],
    ["id" => "NTF-05", "message" => "Seminar Registration Open for E&TC", "level" => "info"]
];

$notices = [
    ["date" => "15 Jul 2026", "title" => "Mid-Term assessment starting soon", "desc" => "Mid-Term evaluations for Odd semesters are scheduled from 1st Aug. Syllabus comprises Unit 1 and Unit 2."],
    ["date" => "10 Jul 2026", "title" => "NBA criteria portfolio review", "desc" => "All SE students are required to verify their CO-PO assessment sheets with class coordinators by next week."]
];

$studyMaterials = [
    ["unit" => "Unit 1", "name" => "Introduction to Intel 8086", "ext" => "pdf", "size" => "2.4 MB"],
    ["unit" => "Unit 2", "name" => "K-Map Simplification Guide", "ext" => "pdf", "size" => "1.8 MB"],
    ["unit" => "Unit 3", "name" => "Logic gates logic sheets", "ext" => "zip", "size" => "4.5 MB"]
];

$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath === '' || $basePath === '/') {
    $basePath = '';
}
$cssPath = $basePath . '/assets/css/style.css';
$jsPath = $basePath . '/assets/js/script.js';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | SAAES Zeal College</title>
    <!-- Google Fonts: Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="<?php echo $cssPath; ?>">
    <script>
        // Inject PHP serialized arrays directly into Client-side JS window object
        window.PHP_DATA = <?php echo json_encode([
            'stats' => $stats,
            'activities' => $activities,
            'submissions' => $submissions,
            'deadlines' => $deadlines,
            'attendance' => $attendance,
            'feedback' => $feedback,
            'notifications' => $notifications,
            'notices' => $notices,
            'studyMaterials' => $studyMaterials
        ]); ?>;
    </script>
</head>
<body class="theme-light">
    <!-- Main App Container -->
    <div class="app-container">
        
        <!-- Header -->
        <header class="main-header">
            <div class="header-left">
                <div class="logo-area">
                    <i class="fa-solid fa-graduation-cap logo-icon"></i>
                    <div class="logo-text">
                        <span class="college-name">Zeal College of Engineering & Research, Pune</span>
                        <span class="dept-name">Student Activity Assessment & Evaluation System (SAAES)</span>
                    </div>
                </div>
            </div>
            
            <div class="header-right">
                <div class="header-date" id="current-date">
                    <i class="fa-regular fa-calendar-days"></i>
                    <span id="header-time-string">July 16, 2026</span>
                </div>
                
                <div class="header-badge-btn" id="notif-bell-btn">
                    <i class="fa-regular fa-bell"></i>
                    <span class="badge-count" id="header-notif-count"><?php echo count($notifications); ?></span>
                </div>
                
                <div class="theme-toggle-btn" id="theme-toggle">
                    <i class="fa-solid fa-moon"></i>
                </div>
                
                <div class="profile-dropdown-container">
                    <div class="profile-btn" id="profile-btn">
                        <div class="avatar">MD</div>
                        <div class="profile-info">
                            <span class="profile-name"><?php echo htmlspecialchars($studentName); ?></span>
                            <span class="profile-role">SE E&TC - Div A</span>
                        </div>
                        <i class="fa-solid fa-chevron-down profile-arrow"></i>
                    </div>
                    <div class="profile-dropdown" id="profile-dropdown-menu">
                        <a href="#" class="dropdown-item" id="menu-view-profile"><i class="fa-regular fa-user"></i> My Profile</a>
                        <a href="#" class="dropdown-item" id="menu-view-settings"><i class="fa-solid fa-sliders"></i> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item text-danger" id="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="main-layout">
            <!-- Left Sidebar -->
            <aside class="sidebar">
                <div class="sidebar-student-card">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=200" alt="Student Profile Photo" class="student-avatar" id="student-avatar-img">
                    <div class="student-meta">
                        <h4 class="st-name" id="sb-student-name"><?php echo htmlspecialchars($studentName); ?></h4>
                        <p class="st-detail"><strong>Roll No:</strong> <span id="sb-roll-no"><?php echo htmlspecialchars($rollNo); ?></span></p>
                        <p class="st-detail"><strong>PRN:</strong> <span id="sb-prn-no"><?php echo htmlspecialchars($prnNo); ?></span></p>
                        <p class="st-detail"><strong>Dept:</strong> <span id="sb-dept">E&TC Engg.</span></p>
                        <p class="st-detail"><strong>Div:</strong> <span id="sb-div"><?php echo htmlspecialchars($division); ?></span></p>
                        <p class="st-detail"><strong>Sem:</strong> <span id="sb-sem"><?php echo htmlspecialchars($semester); ?></span></p>
                    </div>
                </div>
                <nav class="sidebar-nav">
                    <ul>
                        <li class="nav-item active" data-tab="dashboard">
                            <a href="#"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
                        </li>
                        <li class="nav-item" data-tab="my-activities">
                            <a href="#"><i class="fa-solid fa-book"></i> <span>My Activities</span></a>
                        </li>
                        <li class="nav-item" data-tab="submitted-activities">
                            <a href="#"><i class="fa-solid fa-square-check"></i> <span>Submitted Activities</span></a>
                        </li>
                        <li class="nav-item" data-tab="pending-activities">
                            <a href="#"><i class="fa-solid fa-clock"></i> <span>Pending Activities</span><span class="sidebar-badge" id="sidebar-pending-count"><?php echo $stats['pendingActivities']; ?></span></a>
                        </li>
                        <li class="nav-item" data-tab="performance">
                            <a href="#"><i class="fa-solid fa-chart-simple"></i> <span>Marks & Performance</span></a>
                        </li>
                        <li class="nav-item" data-tab="calendar">
                            <a href="#"><i class="fa-solid fa-calendar-days"></i> <span>Calendar</span></a>
                        </li>
                        <li class="nav-item" data-tab="notices">
                            <a href="#"><i class="fa-solid fa-bullhorn"></i> <span>Notices</span></a>
                        </li>
                        <li class="nav-item" data-tab="messages">
                            <a href="#"><i class="fa-solid fa-comments"></i> <span>Messages</span></a>
                        </li>
                        <li class="nav-item" data-tab="study-material">
                            <a href="#"><i class="fa-solid fa-book-open"></i> <span>Study Material</span></a>
                        </li>
                        <li class="nav-item" data-tab="profile">
                            <a href="#"><i class="fa-solid fa-user"></i> <span>Profile</span></a>
                        </li>
                        <li class="nav-item" data-tab="change-password">
                            <a href="#"><i class="fa-solid fa-key"></i> <span>Change Password</span></a>
                        </li>
                        <li class="nav-item" id="sidebar-logout-btn">
                            <a href="#"><i class="fa-solid fa-door-open"></i> <span>Logout</span></a>
                        </li>
                    </ul>
                </nav>
            </aside>

            <!-- Main Scrollable Content Area -->
            <main class="content-area">
                
                <!-- Tab: Dashboard -->
                <section class="tab-content active" id="tab-dashboard">
                    <!-- Welcome Section -->
                    <div class="welcome-banner">
                        <div class="welcome-text">
                            <h2>Welcome, <?php echo htmlspecialchars($studentName); ?> 👋</h2>
                            <p>Track your assignments, activities, performance, and upcoming deadlines in one place.</p>
                        </div>
                        <div class="banner-badge">
                            <span class="live-pulse"></span> Academic Year: 2026-27
                        </div>
                    </div>

                    <!-- Dashboard Summary Cards -->
                    <div class="summary-cards-grid">
                        <div class="summary-card card-total" data-target-tab="my-activities">
                            <div class="card-icon"><i class="fa-solid fa-book"></i></div>
                            <div class="card-info">
                                <span class="card-label">Total Activities</span>
                                <h3 class="card-value" id="stat-total-activities"><?php echo $stats['totalActivities']; ?></h3>
                                <span class="card-change text-info">Curriculum Tasks</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-submitted" data-target-tab="submitted-activities">
                            <div class="card-icon"><i class="fa-solid fa-circle-check"></i></div>
                            <div class="card-info">
                                <span class="card-label">Submitted</span>
                                <h3 class="card-value txt-green" id="stat-submitted-activities"><?php echo $stats['submittedActivities']; ?></h3>
                                <span class="card-change text-success"><i class="fa-solid fa-check-double"></i> 75% Completion</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-pending" data-target-tab="pending-activities">
                            <div class="card-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                            <div class="card-info">
                                <span class="card-label">Pending</span>
                                <h3 class="card-value txt-orange" id="stat-pending-activities"><?php echo $stats['pendingActivities']; ?></h3>
                                <span class="card-change text-warning"><i class="fa-solid fa-clock"></i> Action required</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-marks" data-target-tab="performance">
                            <div class="card-icon"><i class="fa-solid fa-trophy"></i></div>
                            <div class="card-info">
                                <span class="card-label">Total Marks</span>
                                <h3 class="card-value txt-purple" id="stat-total-marks"><?php echo htmlspecialchars($stats['totalMarks']); ?></h3>
                                <span class="card-change text-success">Cumulative Score</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-score" data-target-tab="performance">
                            <div class="card-icon"><i class="fa-solid fa-chart-line"></i></div>
                            <div class="card-info">
                                <span class="card-label">Average Score</span>
                                <h3 class="card-value txt-blue" id="stat-average-score"><?php echo $stats['averageScore']; ?>%</h3>
                                <span class="card-change text-success"><i class="fa-solid fa-arrow-trend-up"></i> Excellent range</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-rank" data-target-tab="performance">
                            <div class="card-icon"><i class="fa-solid fa-star"></i></div>
                            <div class="card-info">
                                <span class="card-label">Activity Rank</span>
                                <h3 class="card-value txt-purple" id="stat-activity-rank"><?php echo htmlspecialchars($stats['activityRank']); ?></h3>
                                <span class="card-change text-info">in Class Division</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>
                    </div>

                    <!-- Row 1: My Activities & Recent Submissions -->
                    <div class="dashboard-row double-column">
                        
                        <!-- My Activities Section -->
                        <div class="dashboard-card main-card flex-grow-1">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-folder-open header-icon"></i>
                                    <h3>My Activities</h3>
                                </div>
                                <button class="btn btn-secondary-sm" id="btn-view-all-activities-tab"><i class="fa-regular fa-eye"></i> View All Activities</button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table">
                                        <thead>
                                            <tr>
                                                <th>Unit</th>
                                                <th>Activity</th>
                                                <th>Faculty</th>
                                                <th>Due Date</th>
                                                <th>Status</th>
                                                <th class="text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="my-activities-list">
                                            <!-- Dynamic items from JS -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Submissions Section -->
                        <div class="dashboard-card main-card">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-inbox header-icon text-green"></i>
                                    <h3>Recent Submissions</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table">
                                        <thead>
                                            <tr>
                                                <th>Activity</th>
                                                <th>Submitted On</th>
                                                <th>Marks</th>
                                                <th>Feedback</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="recent-submissions-list">
                                            <!-- Dynamic items -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Deadlines (with Calendar) & Performance Charts -->
                    <div class="dashboard-row">
                        <!-- Upcoming Deadlines & Mini Calendar -->
                        <div class="dashboard-card main-card flex-grow-0" style="min-width: 320px;">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-calendar-xmark header-icon text-red"></i>
                                    <h3>Upcoming Deadlines</h3>
                                </div>
                            </div>
                            <div class="card-body flex-column gap-3">
                                <div class="deadlines-vertical-list" id="deadlines-list">
                                    <!-- Dynamic deadlines -->
                                </div>
                                <hr class="divider">
                                <!-- Mini Calendar -->
                                <div class="mini-calendar-wrapper">
                                    <h4 class="calendar-month-title">July 2026</h4>
                                    <div class="mini-calendar-grid">
                                        <div class="cal-day cal-header">M</div>
                                        <div class="cal-day cal-header">T</div>
                                        <div class="cal-day cal-header">W</div>
                                        <div class="cal-day cal-header">T</div>
                                        <div class="cal-day cal-header">F</div>
                                        <div class="cal-day cal-header">S</div>
                                        <div class="cal-day cal-header">S</div>
                                        <!-- July dates (16th is today) -->
                                        <div class="cal-day cal-empty"></div>
                                        <div class="cal-day cal-empty"></div>
                                        <div class="cal-day">1</div>
                                        <div class="cal-day">2</div>
                                        <div class="cal-day">3</div>
                                        <div class="cal-day">4</div>
                                        <div class="cal-day">5</div>
                                        <div class="cal-day">6</div>
                                        <div class="cal-day">7</div>
                                        <div class="cal-day">8</div>
                                        <div class="cal-day">9</div>
                                        <div class="cal-day">10</div>
                                        <div class="cal-day">11</div>
                                        <div class="cal-day">12</div>
                                        <div class="cal-day">13</div>
                                        <div class="cal-day">14</div>
                                        <div class="cal-day">15</div>
                                        <div class="cal-day cal-today">16</div>
                                        <div class="cal-day cal-due" title="Number System Conversion Due">17</div>
                                        <div class="cal-day">18</div>
                                        <div class="cal-day">19</div>
                                        <div class="cal-day cal-due" title="Processor Basics/Number System Assignment Due">20</div>
                                        <div class="cal-day">21</div>
                                        <div class="cal-day">22</div>
                                        <div class="cal-day cal-due" title="Boolean Algebra Due">23</div>
                                        <div class="cal-day">24</div>
                                        <div class="cal-day cal-due" title="Flip-Flop Analysis Due">25</div>
                                        <div class="cal-day">26</div>
                                        <div class="cal-day">27</div>
                                        <div class="cal-day cal-due" title="Flip-Flop Analysis Due">28</div>
                                        <div class="cal-day">29</div>
                                        <div class="cal-day cal-due" title="Boolean Algebra Due">30</div>
                                        <div class="cal-day">31</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Performance Analytics Charts -->
                        <div class="dashboard-card main-card flex-1">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-chart-column header-icon text-purple"></i>
                                    <h3>Performance Analytics</h3>
                                </div>
                                <div class="chart-tab-controls">
                                    <button class="chart-tab-btn active" data-chart="unit-marks">Unit-wise Marks</button>
                                    <button class="chart-tab-btn" data-chart="sub-status">Submission Status</button>
                                    <button class="chart-tab-btn" data-chart="weekly-progress">Weekly Progress</button>
                                    <button class="chart-tab-btn" data-chart="perf-trend">Performance Trend</button>
                                </div>
                            </div>
                            <div class="card-body chart-wrapper">
                                <div class="chart-container">
                                    <canvas id="student-performance-chart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Attendance, Faculty Feedback & Notifications -->
                    <div class="dashboard-row triple-column">
                        <!-- Attendance Summary -->
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-clipboard-user header-icon text-blue"></i>
                                    <h3>Attendance Summary</h3>
                                </div>
                            </div>
                            <div class="card-body flex-column gap-3 justify-center align-center">
                                <div class="attendance-flex-row">
                                    <div class="attendance-progress-ring-container">
                                        <svg class="progress-ring-svg" width="120" height="120">
                                            <circle class="ring-circle-bg" stroke="#E2E8F0" stroke-width="10" fill="transparent" r="50" cx="60" cy="60"/>
                                            <circle class="ring-circle-fill" stroke="var(--info)" stroke-width="10" fill="transparent" r="50" cx="60" cy="60" stroke-dasharray="314.15" stroke-dashoffset="28.27" id="attendance-ring-circle"/>
                                        </svg>
                                        <div class="progress-ring-text">
                                            <span class="pct"><?php echo $stats['attendance']; ?>%</span>
                                            <span class="lbl">Overall</span>
                                        </div>
                                    </div>
                                    <div class="attendance-list-table">
                                        <table class="dashboard-table clean-table font-semibold">
                                            <tbody id="attendance-list">
                                                <!-- Dynamic attendance values -->
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Faculty Feedback -->
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-message header-icon text-purple"></i>
                                    <h3>Faculty Feedback</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table clean-table">
                                        <thead>
                                            <tr>
                                                <th>Faculty</th>
                                                <th>Activity</th>
                                                <th>Feedback Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody id="faculty-feedback-list">
                                            <!-- Dynamic feedback items -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Notifications Panel -->
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-bell header-icon text-orange"></i>
                                    <h3>Notifications</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="notifications-list" id="notifications-list">
                                    <!-- Dynamic notifications -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Academic Progress, Achievements & Quick Actions -->
                    <div class="dashboard-row double-column">
                        <!-- Academic Progress & Achievements -->
                        <div class="dashboard-card main-card flex-grow-1">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-award header-icon text-purple"></i>
                                    <h3>Academic Progress & Achievements</h3>
                                </div>
                            </div>
                            <div class="card-body progress-achievement-flex">
                                <div class="academic-metrics-grid">
                                    <div class="metric-item-card">
                                        <span class="m-label">Activities Completed</span>
                                        <span class="m-value txt-green" id="met-activities"><?php echo $stats['submittedActivities']; ?>/12</span>
                                    </div>
                                    <div class="metric-item-card">
                                        <span class="m-label">Average Marks</span>
                                        <span class="m-value txt-blue" id="met-avg-marks">4.2 / 5</span>
                                    </div>
                                    <div class="metric-item-card">
                                        <span class="m-label">Current CGPA</span>
                                        <span class="m-value txt-purple" id="met-cgpa"><?php echo $stats['cgpa']; ?></span>
                                    </div>
                                    <div class="metric-item-card">
                                        <span class="m-label">Class Rank</span>
                                        <span class="m-value txt-orange" id="met-rank"><?php echo $stats['classRank']; ?></span>
                                    </div>
                                    <div class="metric-item-card">
                                        <span class="m-label">Attendance</span>
                                        <span class="m-value txt-teal" id="met-attendance"><?php echo $stats['attendance']; ?>%</span>
                                    </div>
                                </div>
                                <div class="achievements-badges-grid">
                                    <div class="badge-item-card" title="Scored top marks in recent assessments">
                                        <div class="badge-gold-icon"><i class="fa-solid fa-medal"></i></div>
                                        <span>Top Performer</span>
                                    </div>
                                    <div class="badge-item-card" title="Submitted all activities on time">
                                        <div class="badge-purple-icon"><i class="fa-solid fa-fire"></i></div>
                                        <span>Perfect Streak</span>
                                    </div>
                                    <div class="badge-item-card" title="Maintain 100% attendance this month">
                                        <div class="badge-teal-icon"><i class="fa-solid fa-user-check"></i></div>
                                        <span>100% Attendance</span>
                                    </div>
                                    <div class="badge-item-card" title="Completed curriculum activities portfolio">
                                        <div class="badge-blue-icon"><i class="fa-solid fa-certificate"></i></div>
                                        <span>Completion Cert.</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions Panel -->
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-bolt header-icon text-yellow"></i>
                                    <h3>Quick Actions</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="quick-actions-buttons-grid">
                                    <button class="btn btn-action-card bg-orange-light" id="qa-submit-activity">
                                        <i class="fa-solid fa-upload"></i>
                                        <span>Submit Activity</span>
                                    </button>
                                    <button class="btn btn-action-card bg-blue-light" id="qa-dl-assignment">
                                        <i class="fa-solid fa-download"></i>
                                        <span>Download Assignment</span>
                                    </button>
                                    <button class="btn btn-action-card bg-purple-light" id="qa-view-study">
                                        <i class="fa-solid fa-book-open"></i>
                                        <span>View Study Material</span>
                                    </button>
                                    <button class="btn btn-action-card bg-green-light" id="qa-contact-fac">
                                        <i class="fa-solid fa-envelope"></i>
                                        <span>Contact Faculty</span>
                                    </button>
                                    <button class="btn btn-action-card bg-teal-light" id="qa-open-cal">
                                        <i class="fa-solid fa-calendar-days"></i>
                                        <span>Open Calendar</span>
                                    </button>
                                    <button class="btn btn-action-card bg-red-light" id="qa-view-report">
                                        <i class="fa-solid fa-file-pdf"></i>
                                        <span>View Report</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Dynamic Tab Contents served via JS -->
                <section class="tab-content" id="tab-my-activities">
                    <div class="tab-header-flex">
                        <h2>My Assigned Activities</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4">
                        <div class="table-search-header">
                            <div class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="my-activities-search" placeholder="Search activities, units, status...">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Unit</th>
                                        <th>Activity Name</th>
                                        <th>Subject</th>
                                        <th>Assigned Faculty</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="my-activities-tbody">
                                    <!-- Dynamic rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-submitted-activities">
                    <div class="tab-header-flex">
                        <h2>My Submitted Activities Portfolio</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4">
                        <div class="table-search-header">
                            <div class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="submitted-search" placeholder="Search submitted activities...">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Activity Name</th>
                                        <th>Submitted On</th>
                                        <th>Awarded Score</th>
                                        <th>Faculty Feedback</th>
                                        <th>Evaluation Status</th>
                                    </tr>
                                </thead>
                                <tbody id="submitted-activities-tbody">
                                    <!-- Dynamic rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-pending-activities">
                    <div class="tab-header-flex">
                        <h2>Pending Assignments & Tasks</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4">
                        <div class="table-responsive">
                            <table class="dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Unit</th>
                                        <th>Activity Task</th>
                                        <th>Subject</th>
                                        <th>Assigned Faculty</th>
                                        <th>Due Date</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="pending-activities-tbody">
                                    <!-- Dynamic rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-performance">
                    <div class="tab-header-flex">
                        <h2>Marks & Performance Evaluation</h2>
                    </div>
                    <div class="grid-columns-2 gap-4 mt-4">
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Unit-wise Evaluation Marks</h3>
                            </div>
                            <div class="card-body chart-wrapper">
                                <canvas id="performance-tab-unit-chart" style="max-height: 350px;"></canvas>
                            </div>
                        </div>
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Submissions Status Distribution</h3>
                            </div>
                            <div class="card-body chart-wrapper">
                                <canvas id="performance-tab-submission-chart" style="max-height: 350px;"></canvas>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-calendar">
                    <div class="tab-header-flex">
                        <h2>My SAAES Calendar</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4">
                        <div class="card-header border-bottom">
                            <h3>July & August 2026 Deadlines</h3>
                        </div>
                        <div class="card-body">
                            <p class="mb-4">Visual calendar showing task timelines and important milestones.</p>
                            <div class="table-responsive">
                                <table class="dashboard-table">
                                    <thead>
                                        <tr>
                                            <th>Target Date</th>
                                            <th>Scheduled Course Milestone</th>
                                            <th>Target Subject</th>
                                            <th>Assigned Faculty</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong>20 Jul 2026</strong></td>
                                            <td><span class="badge badge-warning">Deadline</span> Number System Conversion</td>
                                            <td>Digital Logic</td>
                                            <td>Prof. Kulkarni</td>
                                        </tr>
                                        <tr>
                                            <td><strong>23 Jul 2026</strong></td>
                                            <td><span class="badge badge-warning">Deadline</span> Boolean Algebra</td>
                                            <td>Digital Logic</td>
                                            <td>Prof. Patil</td>
                                        </tr>
                                        <tr>
                                            <td><strong>28 Jul 2026</strong></td>
                                            <td><span class="badge badge-warning">Deadline</span> Flip-Flop Analysis</td>
                                            <td>Digital Logic</td>
                                            <td>Prof. Patil</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-notices">
                    <div class="tab-header-flex">
                        <h2>SAAES Notices Board</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4" style="max-width: 600px; margin: 24px auto 0 auto;">
                        <div class="card-header border-bottom">
                            <h3>Department Notices</h3>
                        </div>
                        <div class="card-body flex-column gap-3" id="notices-board-list">
                            <!-- Dynamic notices cards -->
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-messages">
                    <div class="tab-header-flex">
                        <h2>Contact Faculty & Messages</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4" style="max-width: 600px; margin: 24px auto 0 auto;">
                        <div class="card-header border-bottom">
                            <h3>Compose Message</h3>
                        </div>
                        <div class="card-body">
                            <form id="contact-faculty-tab-form">
                                <div class="form-group mb-3">
                                    <label class="form-label">Recipient Faculty</label>
                                    <select class="form-select" id="msg-faculty-select">
                                        <option value="Prof. Patil">Prof. Patil (Digital Logic)</option>
                                        <option value="Prof. Kulkarni">Prof. Kulkarni (Microprocessor)</option>
                                    </select>
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label">Message Text</label>
                                    <textarea class="form-control" rows="4" id="msg-text-input" placeholder="Type your inquiry here..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Send Message</button>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-study-material">
                    <div class="tab-header-flex">
                        <h2>Study Material Repository</h2>
                    </div>
                    <div class="grid-columns-3 gap-4 mt-4" id="study-material-grid">
                        <!-- Dynamic study items -->
                    </div>
                </section>

                <section class="tab-content" id="tab-profile">
                    <div class="tab-header-flex">
                        <h2>My Profile details</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4" style="max-width: 600px; margin: 24px auto 0 auto;">
                        <div class="card-header border-bottom">
                            <h3>Zeal SAAES Student Profile Card</h3>
                        </div>
                        <div class="card-body">
                            <div class="profile-tab-details-flex">
                                <div class="profile-photo-area">
                                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&q=80&w=200" alt="Student Profile Photo" class="profile-photo-large">
                                    <h4 class="mt-3"><?php echo htmlspecialchars($studentName); ?></h4>
                                </div>
                                <div class="profile-fields-list">
                                    <div class="form-group mb-2">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($studentName); ?>" disabled>
                                    </div>
                                    <div class="grid-columns-2 gap-3 mb-2">
                                        <div class="form-group">
                                            <label class="form-label">Roll Number</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($rollNo); ?>" disabled>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">PRN Number</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($prnNo); ?>" disabled>
                                        </div>
                                    </div>
                                    <div class="form-group mb-2">
                                        <label class="form-label">Academic Department</label>
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($deptName); ?>" disabled>
                                    </div>
                                    <div class="grid-columns-2 gap-3">
                                        <div class="form-group">
                                            <label class="form-label">Division</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($division); ?>" disabled>
                                        </div>
                                        <div class="form-group">
                                            <label class="form-label">Semester</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($semester); ?>" disabled>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-change-password">
                    <div class="tab-header-flex">
                        <h2>Change Account Password</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4" style="max-width: 500px; margin: 24px auto 0 auto;">
                        <div class="card-header border-bottom">
                            <h3>Update Security Credentials</h3>
                        </div>
                        <div class="card-body">
                            <form id="change-pwd-form">
                                <div class="form-group mb-3">
                                    <label class="form-label" for="curr-pwd">Current Password</label>
                                    <input type="password" id="curr-pwd" class="form-control" placeholder="••••••••" required>
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label" for="new-pwd">New Password</label>
                                    <input type="password" id="new-pwd" class="form-control" placeholder="••••••••" required>
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label" for="confirm-pwd">Confirm New Password</label>
                                    <input type="password" id="confirm-pwd" class="form-control" placeholder="••••••••" required>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Update Password</button>
                            </form>
                        </div>
                    </div>
                </section>
                
                <!-- Footer -->
                <footer class="dashboard-footer">
                    <div class="footer-left">
                        <span class="footer-copyright">&copy; 2026 Zeal College of Engineering & Research, Pune</span>
                    </div>
                    <div class="footer-right">
                        <span class="footer-sysname">Student Activity Assessment & Evaluation System (SAAES)</span>
                    </div>
                </footer>
            </main>
        </div>
    </div>

    <!-- Modals Section -->
    
    <!-- Modal: Submit Activity -->
    <div class="modal-overlay" id="modal-submit-activity">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-upload text-orange"></i> Submit SAAES Activity</h3>
                <button class="close-modal-btn" id="close-submit-modal-btn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form id="activity-submission-form">
                    <div class="form-group mb-3">
                        <label class="form-label" for="sub-act-select">Select Pending Activity</label>
                        <select id="sub-act-select" class="form-select" required>
                            <!-- Dynamic options -->
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label" for="sub-file">Upload Submission File (.pdf, .zip)</label>
                        <input type="file" id="sub-file" class="form-control" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label" for="sub-comments">Submission Comments (Optional)</label>
                        <textarea id="sub-comments" class="form-control" rows="2" placeholder="Describe your submission..."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary-outline" id="btn-cancel-submit">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Activity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Contact Faculty -->
    <div class="modal-overlay" id="modal-contact-faculty">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-envelope text-blue"></i> Send Message to Faculty</h3>
                <button class="close-modal-btn" id="close-contact-modal-btn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form id="contact-faculty-form">
                    <div class="form-group mb-3">
                        <label class="form-label" for="notif-target-fac">Select Faculty</label>
                        <select id="notif-target-fac" class="form-select">
                            <option value="Prof. Patil">Prof. Patil (Digital Logic)</option>
                            <option value="Prof. Kulkarni">Prof. Kulkarni (Microprocessor)</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label" for="notif-text-fac">Message / Inquiry</label>
                        <textarea id="notif-text-fac" class="form-control" rows="3" placeholder="Type your query..." required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary-outline" id="btn-cancel-contact">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Global Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Main Engine Script -->
    <script src="<?php echo $jsPath; ?>"></script>
</body>
</html>
