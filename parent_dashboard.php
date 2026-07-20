<?php
$parentName = "Mrs. Sunita Sharma";
$studentName = "Aarav Sharma";
$rollNo = "23EC074";
$deptName = "E&TC Engineering";
$class = "TE - Division A";
$academicYear = "2025–26";
$collegeName = "Zeal College of Engineering & Research, Pune";

$stats = [
    "studentPerf" => 84,
    "attendance" => 91,
    "completedActivities" => "18 / 20",
    "avgMarks" => "4.2 / 5",
    "pendingActivities" => 2,
    "newNotifs" => 5
];

$academicOverview = [
    ["subject" => "Digital Logic", "faculty" => "Prof. Patil", "attendance" => "95%", "marks" => "4.5/5", "status" => "Excellent"],
    ["subject" => "Microprocessor", "faculty" => "Prof. Kulkarni", "attendance" => "90%", "marks" => "4.2/5", "status" => "Good"],
    ["subject" => "DSP", "faculty" => "Prof. Shah", "attendance" => "88%", "marks" => "4.0/5", "status" => "Good"],
    ["subject" => "Communication", "faculty" => "Prof. Mehta", "attendance" => "93%", "marks" => "4.6/5", "status" => "Excellent"]
];

$activities = [
    ["name" => "K-Map Assignment", "duedate" => "20 Jul", "status" => "Submitted", "marks" => "5/5", "subject" => "Digital Logic"],
    ["name" => "Number System", "duedate" => "25 Jul", "status" => "Submitted", "marks" => "4/5", "subject" => "Digital Logic"],
    ["name" => "Boolean Algebra", "duedate" => "30 Jul", "status" => "Pending", "marks" => "—", "subject" => "Digital Logic"],
    ["name" => "Flip-Flop Analysis", "duedate" => "28 Jul", "status" => "Submitted", "marks" => "4.5/5", "subject" => "Digital Logic"],
    ["name" => "Combinational Circuits", "duedate" => "02 Aug", "status" => "Pending", "marks" => "—", "subject" => "Digital Logic"]
];

$feedback = [
    ["faculty" => "Prof. Patil", "subject" => "Digital Logic", "text" => "Excellent participation"],
    ["faculty" => "Prof. Kulkarni", "subject" => "Microprocessor", "text" => "Needs more practice"],
    ["faculty" => "Prof. Shah", "subject" => "DSP", "text" => "Good improvement"]
];

$gfmRemarks = [
    ["date" => "10 Jul", "text" => "Good academic progress", "status" => "Positive"],
    ["date" => "18 Jul", "text" => "Improve assignment submission time", "status" => "Follow-up"],
    ["date" => "25 Jul", "text" => "Parent meeting suggested", "status" => "Scheduled"]
];

$notifications = [
    ["message" => "Internal Assessment starts next week", "level" => "warning"],
    ["message" => "New activity assigned", "level" => "success"],
    ["message" => "Marks uploaded", "level" => "info"],
    ["message" => "Parent-Teacher Meeting on 25 July", "level" => "danger"],
    ["message" => "College Notice Published", "level" => "info"]
];

$notices = [
    ["date" => "15 Jul 2026", "title" => "Defaulter list review meeting", "desc" => "GFM defaulters meeting scheduled on 20th July at 11 AM in departmental room 402."],
    ["date" => "12 Jul 2026", "title" => "Mid-Term assessment schedule", "desc" => "Mid-Term exams starts next week. Roster schedules have been updated on student notice sheets."]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard | SAAES Zeal College</title>
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
            'academicOverview' => $academicOverview,
            'activities' => $activities,
            'feedback' => $feedback,
            'gfmRemarks' => $gfmRemarks,
            'notifications' => $notifications,
            'notices' => $notices
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
                        <span class="dept-name">Student Activity Assessment & Evaluation System</span>
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
                        <div class="avatar">SS</div>
                        <div class="profile-info">
                            <span class="profile-name">Mrs. Sunita Sharma</span>
                            <span class="profile-role">Parent Account</span>
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
                <div class="sidebar-parent-card">
                    <img src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?auto=format&fit=crop&q=80&w=200" alt="Parent Profile Photo" class="parent-avatar" id="parent-avatar-img">
                    <div class="parent-meta">
                        <h4 class="parent-name" id="sb-parent-name"><?php echo htmlspecialchars($parentName); ?></h4>
                        <p class="parent-detail"><strong>Parent of:</strong> <span id="sb-student-name"><?php echo htmlspecialchars($studentName); ?></span></p>
                        <p class="parent-detail"><strong>Roll No.:</strong> <span id="sb-roll-no"><?php echo htmlspecialchars($rollNo); ?></span></p>
                        <p class="parent-detail"><strong>Dept:</strong> <span id="sb-dept">E&TC Engineering</span></p>
                        <p class="parent-detail"><strong>Class:</strong> <span id="sb-class"><?php echo htmlspecialchars($class); ?></span></p>
                        <p class="parent-detail"><strong>A.Y.:</strong> <span id="sb-ay"><?php echo htmlspecialchars($academicYear); ?></span></p>
                    </div>
                </div>
                <nav class="sidebar-nav">
                    <ul>
                        <li class="nav-item active" data-tab="dashboard">
                            <a href="#"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
                        </li>
                        <li class="nav-item" data-tab="student-overview">
                            <a href="#"><i class="fa-solid fa-graduation-cap"></i> <span>Student Overview</span></a>
                        </li>
                        <li class="nav-item" data-tab="activities">
                            <a href="#"><i class="fa-solid fa-book"></i> <span>Activities</span><span class="sidebar-badge bg-warning" id="sidebar-pending-count"><?php echo $stats['pendingActivities']; ?></span></a>
                        </li>
                        <li class="nav-item" data-tab="performance">
                            <a href="#"><i class="fa-solid fa-chart-line"></i> <span>Academic Performance</span></a>
                        </li>
                        <li class="nav-item" data-tab="attendance">
                            <a href="#"><i class="fa-solid fa-calendar-days"></i> <span>Attendance</span></a>
                        </li>
                        <li class="nav-item" data-tab="results">
                            <a href="#"><i class="fa-solid fa-file-invoice"></i> <span>Results & Marks</span></a>
                        </li>
                        <li class="nav-item" data-tab="notices">
                            <a href="#"><i class="fa-solid fa-bullhorn"></i> <span>Notices</span></a>
                        </li>
                        <li class="nav-item" data-tab="messages">
                            <a href="#"><i class="fa-solid fa-envelope"></i> <span>Faculty Messages</span></a>
                        </li>
                        <li class="nav-item" data-tab="meet-gfm">
                            <a href="#"><i class="fa-solid fa-people-roof"></i> <span>Meet GFM</span></a>
                        </li>
                        <li class="nav-item" data-tab="reports">
                            <a href="#"><i class="fa-solid fa-file-pdf"></i> <span>Progress Reports</span></a>
                        </li>
                        <li class="nav-item" data-tab="settings">
                            <a href="#"><i class="fa-solid fa-gear"></i> <span>Settings</span></a>
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
                            <h2>Welcome, <?php echo htmlspecialchars($parentName); ?></h2>
                            <p>Monitor your child's academic progress, attendance, activities, and overall performance.</p>
                        </div>
                        <div class="banner-badge">
                            <span class="live-pulse"></span> Academic Year: <?php echo htmlspecialchars($academicYear); ?>
                        </div>
                    </div>

                    <!-- Dashboard Summary Cards -->
                    <div class="summary-cards-grid">
                        <div class="summary-card card-performance" data-target-tab="performance">
                            <div class="card-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                            <div class="card-info">
                                <span class="card-label">Student Performance</span>
                                <h3 class="card-value" id="stat-student-perf"><?php echo $stats['studentPerf']; ?>%</h3>
                                <span class="card-change text-success"><i class="fa-solid fa-chart-line"></i> Class average 78%</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-attendance" data-target-tab="attendance">
                            <div class="card-icon"><i class="fa-solid fa-calendar-days"></i></div>
                            <div class="card-info">
                                <span class="card-label">Attendance</span>
                                <h3 class="card-value txt-green" id="stat-attendance"><?php echo $stats['attendance']; ?>%</h3>
                                <span class="card-change text-success"><i class="fa-solid fa-circle-check"></i> Satisfies 75% limit</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-completed" data-target-tab="activities">
                            <div class="card-icon"><i class="fa-solid fa-circle-check"></i></div>
                            <div class="card-info">
                                <span class="card-label">Completed Activities</span>
                                <h3 class="card-value txt-blue" id="stat-completed-activities"><?php echo htmlspecialchars($stats['completedActivities']); ?></h3>
                                <span class="card-change text-info"><i class="fa-solid fa-check-double"></i> 90% Submit Rate</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-marks" data-target-tab="results">
                            <div class="card-icon"><i class="fa-solid fa-trophy"></i></div>
                            <div class="card-info">
                                <span class="card-label">Average Marks</span>
                                <h3 class="card-value txt-purple" id="stat-avg-marks"><?php echo htmlspecialchars($stats['avgMarks']); ?></h3>
                                <span class="card-change text-success"><i class="fa-solid fa-award"></i> Excellent level</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-pending" data-target-tab="activities">
                            <div class="card-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                            <div class="card-info">
                                <span class="card-label">Pending Activities</span>
                                <h3 class="card-value txt-orange" id="stat-pending-activities"><?php echo $stats['pendingActivities']; ?></h3>
                                <span class="card-change text-warning"><i class="fa-solid fa-clock"></i> Action required</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-notifications" data-target-tab="dashboard">
                            <div class="card-icon"><i class="fa-solid fa-bell"></i></div>
                            <div class="card-info">
                                <span class="card-label">New Notifications</span>
                                <h3 class="card-value txt-red" id="stat-notif-count"><?php echo $stats['newNotifs']; ?></h3>
                                <span class="card-change text-danger"><i class="fa-solid fa-triangle-exclamation"></i> Alerts/notices</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>
                    </div>

                    <!-- Row 1: Student Academic Overview & Alerts -->
                    <div class="dashboard-row double-column">
                        
                        <!-- Student Academic Overview Section -->
                        <div class="dashboard-card main-card flex-grow-1">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-user-graduate header-icon"></i>
                                    <h3>Student Academic Overview</h3>
                                </div>
                                <button class="btn btn-secondary-sm" id="btn-view-academic-report"><i class="fa-solid fa-file-pdf"></i> View Full Academic Report</button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Assigned Faculty</th>
                                                <th>Attendance Rate</th>
                                                <th>Average Marks</th>
                                                <th>Evaluation Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="student-academic-overview">
                                            <?php foreach ($academicOverview as $ao): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($ao['subject']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($ao['faculty']); ?></td>
                                                <td><span class="font-semibold text-success"><?php echo htmlspecialchars($ao['attendance']); ?></span></td>
                                                <td><strong><?php echo htmlspecialchars($ao['marks']); ?></strong></td>
                                                <td><span class="badge <?php echo $ao['status'] === 'Excellent' ? 'badge-success' : 'badge-info'; ?>"><?php echo htmlspecialchars($ao['status']); ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Notifications / Alerts Card -->
                        <div class="dashboard-card main-card" style="min-width: 320px;">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-bell header-icon text-red"></i>
                                    <h3>Notifications & Alerts</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="notifications-list" id="notifications-list">
                                    <!-- Rendered dynamically -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Attendance Monitor & Stats -->
                    <div class="dashboard-row">
                        <div class="dashboard-card main-card flex-1">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-calendar-days header-icon text-green"></i>
                                    <h3>Attendance Summary</h3>
                                </div>
                                <div class="chart-tab-controls">
                                    <button class="chart-tab-btn active" data-chart="attendance-ratio">Present/Absent Ratio</button>
                                    <button class="chart-tab-btn" data-chart="attendance-trend">Monthly Trend</button>
                                </div>
                            </div>
                            <div class="card-body chart-wrapper">
                                <div class="chart-container">
                                    <canvas id="parent-attendance-chart"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-card main-card flex-grow-0" style="min-width: 320px;">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-square-poll-vertical header-icon text-blue"></i>
                                    <h3>Attendance Statistics</h3>
                                </div>
                                <button class="btn btn-secondary-sm" id="btn-view-att-details-tab">Details</button>
                            </div>
                            <div class="card-body flex-column gap-3">
                                <div class="stats-metric-flex">
                                    <div class="stat-circle-highlight bg-green-light txt-green">
                                        <span class="val">91%</span>
                                        <span class="lbl">Present</span>
                                    </div>
                                    <div class="stat-circle-highlight bg-red-light txt-red">
                                        <span class="val">9%</span>
                                        <span class="lbl">Absent</span>
                                    </div>
                                </div>
                                <hr class="divider">
                                <div class="academic-metrics-grid">
                                    <div class="metric-item-card">
                                        <span class="m-label">Total Working Days</span>
                                        <span class="m-value txt-blue">110 Days</span>
                                    </div>
                                    <div class="metric-item-card">
                                        <span class="m-label">Days Present</span>
                                        <span class="m-value txt-green">100 Days</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Activity Submissions & Recent Actions -->
                    <div class="dashboard-row">
                        <div class="dashboard-card main-card flex-1">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-book header-icon text-orange"></i>
                                    <h3>Activity Submission Tracker</h3>
                                </div>
                                <div class="chart-tab-controls">
                                    <button class="chart-tab-btn2 active" data-chart="submission-ratio">Status Ratio</button>
                                </div>
                            </div>
                            <div class="card-body chart-wrapper">
                                <div class="chart-container">
                                    <canvas id="parent-activities-chart"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-card main-card flex-grow-0" style="min-width: 320px;">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-clock header-icon text-orange"></i>
                                    <h3>Recent Activities</h3>
                                </div>
                                <button class="btn btn-secondary-sm" id="btn-view-all-activities-tab-dash">View All</button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table clean-table">
                                        <thead>
                                            <tr>
                                                <th>Activity</th>
                                                <th>Due Date</th>
                                                <th>Status</th>
                                                <th>Marks</th>
                                            </tr>
                                        </thead>
                                        <tbody id="recent-activities-list">
                                            <?php foreach (array_slice($activities, 0, 3) as $act): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($act['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($act['duedate']); ?></td>
                                                <td><span class="badge <?php echo $act['status'] === 'Submitted' ? 'badge-success' : 'badge-warning'; ?>"><?php echo htmlspecialchars($act['status']); ?></span></td>
                                                <td><strong><?php echo htmlspecialchars($act['marks']); ?></strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Academic Performance & Quick Actions -->
                    <div class="dashboard-row double-column">
                        <div class="dashboard-card main-card flex-grow-1">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-chart-column header-icon text-purple"></i>
                                    <h3>Academic Performance Charts</h3>
                                </div>
                                <div class="chart-tab-controls">
                                    <button class="chart-tab-btn3 active" data-chart="subject-marks">Subject-wise Marks</button>
                                    <button class="chart-tab-btn3" data-chart="semester-perf">Semester Performance</button>
                                    <button class="chart-tab-btn3" data-chart="grade-dist">Grade Distribution</button>
                                    <button class="chart-tab-btn3" data-chart="monthly-progress">Monthly Progress</button>
                                </div>
                            </div>
                            <div class="card-body chart-wrapper">
                                <div class="chart-container">
                                    <canvas id="parent-performance-chart"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-card main-card" style="min-width: 320px;">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-bolt header-icon text-yellow"></i>
                                    <h3>Quick Actions</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="quick-actions-buttons-grid">
                                    <button class="btn btn-action-card bg-blue-light" id="qa-dl-report-card">
                                        <i class="fa-solid fa-file-pdf"></i>
                                        <span>Download Report Card</span>
                                    </button>
                                    <button class="btn btn-action-card bg-purple-light" id="qa-view-perf">
                                        <i class="fa-solid fa-chart-line"></i>
                                        <span>View Performance</span>
                                    </button>
                                    <button class="btn btn-action-card bg-green-light" id="qa-check-att">
                                        <i class="fa-solid fa-calendar-check"></i>
                                        <span>Check Attendance</span>
                                    </button>
                                    <button class="btn btn-action-card bg-orange-light" id="qa-message-fac">
                                        <i class="fa-solid fa-comment-dots"></i>
                                        <span>Message Faculty</span>
                                    </button>
                                    <button class="btn btn-action-card bg-red-light" id="qa-book-gfm">
                                        <i class="fa-solid fa-people-roof"></i>
                                        <span>Book GFM Meeting</span>
                                    </button>
                                    <button class="btn btn-action-card bg-teal-light" id="qa-view-notices">
                                        <i class="fa-solid fa-bullhorn"></i>
                                        <span>View Notices</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 5: Faculty Feedback & GFM Remarks -->
                    <div class="dashboard-row double-column">
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-message header-icon text-blue"></i>
                                    <h3>Faculty Feedback remarks</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table">
                                        <thead>
                                            <tr>
                                                <th>Faculty</th>
                                                <th>Subject</th>
                                                <th>Feedback Remarks</th>
                                            </tr>
                                        </thead>
                                        <tbody id="faculty-feedback-list">
                                            <?php foreach ($feedback as $fb): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($fb['faculty']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($fb['subject']); ?></td>
                                                <td><span class="badge badge-purple"><?php echo htmlspecialchars($fb['text']); ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-user-doctor header-icon text-purple"></i>
                                    <h3>GFM Remarks & Meetings</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>GFM Remark Log</th>
                                                <th>Meeting Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="gfm-remarks-list">
                                            <?php foreach ($gfmRemarks as $gr): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($gr['date']); ?></code></td>
                                                <td><strong><?php echo htmlspecialchars($gr['text']); ?></strong></td>
                                                <td><span class="badge <?php echo $gr['status'] === 'Positive' ? 'badge-success' : ($gr['status'] === 'Follow-up' ? 'badge-warning' : 'badge-purple'); ?>"><?php echo htmlspecialchars($gr['status']); ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Dynamic Tab Contents served via JS -->
                <section class="tab-content" id="tab-student-overview">
                    <div class="tab-header-flex">
                        <h2>Student Academic Performance Overview</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="overview-tab-wrapper"></div>
                </section>

                <section class="tab-content" id="tab-activities">
                    <div class="tab-header-flex">
                        <h2>Child Activities Portfolio</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4">
                        <div class="table-search-header">
                            <div class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="activities-search" placeholder="Search activities...">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Activity Name</th>
                                        <th>Subject</th>
                                        <th>Due Date</th>
                                        <th>Submission Status</th>
                                        <th>Marks</th>
                                    </tr>
                                </thead>
                                <tbody id="activities-tbody"></tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-performance">
                    <div class="tab-header-flex">
                        <h2>Child Academic Performance Analytics</h2>
                    </div>
                    <div class="grid-columns-2 gap-4 mt-4">
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Subject-wise Marks Scored</h3>
                            </div>
                            <div class="card-body chart-wrapper">
                                <canvas id="performance-tab-sub-chart" style="max-height:350px;"></canvas>
                            </div>
                        </div>
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Monthly Child Progress Trend</h3>
                            </div>
                            <div class="card-body chart-wrapper">
                                <canvas id="performance-tab-trend-chart" style="max-height:350px;"></canvas>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-attendance">
                    <div class="tab-header-flex">
                        <h2>Child Attendance Logs</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4">
                        <div class="card-header border-bottom">
                            <h3>Attendance Logs & Trend lines</h3>
                        </div>
                        <div class="card-body chart-wrapper">
                            <canvas id="attendance-tab-trend-chart" style="max-height:350px;"></canvas>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-results">
                    <div class="tab-header-flex">
                        <h2>Results & Evaluation Marks</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="results-tab-wrapper"></div>
                </section>

                <section class="tab-content" id="tab-notices">
                    <div class="tab-header-flex">
                        <h2>College Notice Board Announcements</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4" style="max-width: 600px; margin: 24px auto 0 auto;">
                        <div class="card-body flex-column gap-3" id="notices-board-list"></div>
                    </div>
                </section>

                <section class="tab-content" id="tab-messages">
                    <div class="tab-header-flex">
                        <h2>Compose Message to Class Professors</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4" style="max-width: 600px; margin: 24px auto 0 auto;">
                        <div class="card-body">
                            <form id="compose-msg-form">
                                <div class="form-group mb-3">
                                    <label class="form-label">Select Faculty</label>
                                    <select class="form-select" id="msg-faculty-select">
                                        <option value="Prof. Patil">Prof. Patil (Digital Logic)</option>
                                        <option value="Prof. Kulkarni">Prof. Kulkarni (Microprocessor)</option>
                                        <option value="Prof. Shah">Prof. Shah (DSP)</option>
                                    </select>
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label">Message Details</label>
                                    <textarea class="form-control" rows="4" id="msg-text-input" placeholder="Type your message here..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Dispatch Message</button>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-meet-gfm">
                    <div class="tab-header-flex">
                        <h2>GFM Meeting scheduler</h2>
                        <button class="btn btn-primary" id="btn-book-gfm-top"><i class="fa-solid fa-calendar-plus"></i> Book GFM Meeting</button>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="meet-gfm-tab-wrapper"></div>
                </section>

                <section class="tab-content" id="tab-reports">
                    <div class="tab-header-flex">
                        <h2>Reports Management console</h2>
                    </div>
                    <div class="reports-view-wrapper mt-4" id="reports-tab-wrapper"></div>
                </section>

                <section class="tab-content" id="tab-settings">
                    <div class="tab-header-flex">
                        <h2>Parent settings panel</h2>
                    </div>
                    <div class="grid-columns-2 gap-4 mt-4">
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Account Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($parentName); ?>">
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label">Associated Student</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($studentName); ?>" disabled>
                                </div>
                                <button class="btn btn-primary" id="settings-save-btn">Save Preferences</button>
                            </div>
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
    <div class="modal-overlay" id="modal-message-faculty">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-comment-dots text-orange"></i> Send Message to Faculty</h3>
                <button class="close-modal-btn" id="close-msg-modal-btn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form id="modal-message-form">
                    <div class="form-group mb-3">
                        <label class="form-label">Select Faculty</label>
                        <select id="modal-msg-fac" class="form-select">
                            <option value="Prof. Patil">Prof. Patil (Digital Logic)</option>
                            <option value="Prof. Kulkarni">Prof. Kulkarni (Microprocessor)</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Message / Inquiry</label>
                        <textarea id="modal-msg-text" class="form-control" rows="3" placeholder="Type query..." required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary-outline" id="btn-cancel-msg">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modal-book-gfm">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-calendar-days text-purple"></i> Schedule GFM Meeting Slot</h3>
                <button class="close-modal-btn" id="close-gfm-modal-btn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form id="book-gfm-form">
                    <div class="form-group mb-3">
                        <label class="form-label">Select Proposed Date</label>
                        <input type="date" class="form-control" id="meet-gfm-date" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Discussion Topic Notes</label>
                        <textarea id="meet-gfm-notes" class="form-control" rows="2" placeholder="Topic notes..."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary-outline" id="btn-cancel-gfm">Cancel</button>
                        <button type="submit" class="btn btn-primary">Book Slot</button>
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
