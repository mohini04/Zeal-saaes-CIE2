<?php
$gfmName = "Prof. Rahul Deshmukh";
$role = "Group Faculty Member";
$deptName = "E&TC";
$academicYear = "2025–26";
$collegeName = "Zeal College of Engineering & Research, Pune";

$stats = [
    "assignedStudents" => 120,
    "avgAttendance" => 87,
    "submissionRate" => 88,
    "avgPerformance" => "4.2 / 5",
    "riskStudents" => 12,
    "parentMeetings" => 8
];

$students = [
    ["rank" => 1, "roll" => "TE-01", "name" => "Aarav Sharma", "attendance" => "95%", "marks" => "4.8/5", "status" => "Excellent"],
    ["rank" => 2, "roll" => "TE-05", "name" => "Priya Patil", "attendance" => "93%", "marks" => "4.6/5", "status" => "Excellent"],
    ["rank" => 3, "roll" => "TE-12", "name" => "Rohan Kulkarni", "attendance" => "91%", "marks" => "4.4/5", "status" => "Good"],
    ["rank" => 4, "roll" => "TE-18", "name" => "Sneha Jadhav", "attendance" => "90%", "marks" => "4.3/5", "status" => "Good"],
    ["rank" => 5, "roll" => "TE-22", "name" => "Aditya Joshi", "attendance" => "89%", "marks" => "4.2/5", "status" => "Good"],
    ["rank" => 6, "roll" => "TE-29", "name" => "Omkar Shinde", "attendance" => "63%", "marks" => "3.1/5", "status" => "Weak"],
    ["rank" => 7, "roll" => "TE-34", "name" => "Neha More", "attendance" => "65%", "marks" => "3.2/5", "status" => "Weak"],
    ["rank" => 8, "roll" => "TE-47", "name" => "Rahul Patil", "attendance" => "68%", "marks" => "3.4/5", "status" => "Weak"]
];

$lowAttendance = [
    ["roll" => "TE-29", "name" => "Omkar Shinde", "attendance" => "63%"],
    ["roll" => "TE-34", "name" => "Neha More", "attendance" => "65%"],
    ["roll" => "TE-47", "name" => "Rahul Patil", "attendance" => "68%"]
];

$pendingActivities = [
    ["roll" => "TE-29", "name" => "Omkar Shinde", "count" => 3],
    ["roll" => "TE-34", "name" => "Neha More", "count" => 2],
    ["roll" => "TE-47", "name" => "Rahul Patil", "count" => 2]
];

$counseling = [
    ["id" => "CNS-01", "student" => "Omkar Shinde", "reason" => "Low Attendance", "status" => "Scheduled", "date" => "20 Jul"],
    ["id" => "CNS-02", "student" => "Neha More", "reason" => "Low Marks", "status" => "Completed", "date" => "25 Jul"],
    ["id" => "CNS-03", "student" => "Rahul Patil", "reason" => "Activity Pending", "status" => "Pending", "date" => "22 Jul"]
];

$parentCommunication = [
    ["id" => "PAR-01", "parent" => "Mr. Sharma", "student" => "Aarav Sharma", "date" => "10 Jul", "status" => "Completed"],
    ["id" => "PAR-02", "parent" => "Mrs. Patil", "student" => "Priya Patil", "date" => "12 Jul", "status" => "Scheduled"],
    ["id" => "PAR-03", "parent" => "Mr. Shinde", "student" => "Omkar Shinde", "date" => "15 Jul", "status" => "Pending"]
];

$notifications = [
    ["id" => "NTF-01", "message" => "12 students have attendance below 75%", "level" => "danger"],
    ["id" => "NTF-02", "message" => "15 activities are pending submission", "level" => "warning"],
    ["id" => "NTF-03", "message" => "Mid-semester marks uploaded", "level" => "success"],
    ["id" => "NTF-04", "message" => "Parent meeting scheduled this week", "level" => "info"]
];

$notices = [
    ["date" => "14 Jul 2026", "title" => "Attendance Defaulters Review Meeting", "desc" => "All GFM batches are instructed to organize review meetings with students below 75% attendance before term-end."]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GFM Dashboard | SAAES Zeal College</title>
    <!-- Google Fonts: Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <script>
        // Inject PHP serialized arrays directly into Client-side JS window object
        window.PHP_DATA = <?php echo json_encode([
            'stats' => $stats,
            'students' => $students,
            'lowAttendance' => $lowAttendance,
            'pendingActivities' => $pendingActivities,
            'counseling' => $counseling,
            'parentCommunication' => $parentCommunication,
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
                        <div class="avatar">RD</div>
                        <div class="profile-info">
                            <span class="profile-name">Prof. Rahul Deshmukh</span>
                            <span class="profile-role">GFM – E&TC</span>
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
                <div class="sidebar-gfm-card">
                    <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&q=80&w=200" alt="GFM Profile Photo" class="gfm-avatar" id="gfm-avatar-img">
                    <div class="gfm-meta">
                        <h4 class="gfm-name" id="sb-gfm-name"><?php echo htmlspecialchars($gfmName); ?></h4>
                        <p class="gfm-detail"><strong>Role:</strong> <span id="sb-role"><?php echo htmlspecialchars($role); ?></span></p>
                        <p class="gfm-detail"><strong>Dept:</strong> <span id="sb-dept"><?php echo htmlspecialchars($deptName); ?></span></p>
                        <p class="gfm-detail"><strong>A.Y.:</strong> <span id="sb-ay"><?php echo htmlspecialchars($academicYear); ?></span></p>
                    </div>
                </div>
                <nav class="sidebar-nav">
                    <ul>
                        <li class="nav-item active" data-tab="dashboard">
                            <a href="#"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
                        </li>
                        <li class="nav-item" data-tab="my-students">
                            <a href="#"><i class="fa-solid fa-users"></i> <span>My Students</span></a>
                        </li>
                        <li class="nav-item" data-tab="student-progress">
                            <a href="#"><i class="fa-solid fa-chart-simple"></i> <span>Student Progress</span></a>
                        </li>
                        <li class="nav-item" data-tab="attendance">
                            <a href="#"><i class="fa-solid fa-clipboard-user"></i> <span>Attendance Monitoring</span></a>
                        </li>
                        <li class="nav-item" data-tab="activities">
                            <a href="#"><i class="fa-solid fa-folder-open"></i> <span>Student Activities</span></a>
                        </li>
                        <li class="nav-item" data-tab="performance">
                            <a href="#"><i class="fa-solid fa-graduation-cap"></i> <span>Academic Performance</span></a>
                        </li>
                        <li class="nav-item" data-tab="parent-comm">
                            <a href="#"><i class="fa-solid fa-people-roof"></i> <span>Parent Communication</span></a>
                        </li>
                        <li class="nav-item" data-tab="counseling">
                            <a href="#"><i class="fa-solid fa-user-doctor"></i> <span>Student Counseling</span></a>
                        </li>
                        <li class="nav-item" data-tab="notices">
                            <a href="#"><i class="fa-solid fa-bullhorn"></i> <span>Notices Board</span></a>
                        </li>
                        <li class="nav-item" data-tab="reports">
                            <a href="#"><i class="fa-solid fa-file-invoice"></i> <span>Reports Center</span></a>
                        </li>
                        <li class="nav-item" data-tab="notifications">
                            <a href="#"><i class="fa-solid fa-bell"></i> <span>Notifications</span><span class="sidebar-badge bg-danger" id="sidebar-notif-count"><?php echo count($notifications); ?></span></a>
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
                            <h2>Welcome, <?php echo htmlspecialchars($gfmName); ?></h2>
                            <p>Monitor your assigned students' academic performance, attendance, and overall progress.</p>
                        </div>
                        <div class="banner-badge">
                            <span class="live-pulse"></span> Academic Term: <?php echo htmlspecialchars($academicYear); ?>
                        </div>
                    </div>

                    <!-- Dashboard Summary Cards -->
                    <div class="summary-cards-grid">
                        <div class="summary-card card-students" data-target-tab="my-students">
                            <div class="card-icon"><i class="fa-solid fa-users"></i></div>
                            <div class="card-info">
                                <span class="card-label">Assigned Students</span>
                                <h3 class="card-value" id="stat-assigned-students"><?php echo $stats['assignedStudents']; ?></h3>
                                <span class="card-change text-success"><i class="fa-solid fa-circle-nodes"></i> GFM Batch</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-attendance" data-target-tab="attendance">
                            <div class="card-icon"><i class="fa-solid fa-clipboard-user"></i></div>
                            <div class="card-info">
                                <span class="card-label">Avg Attendance</span>
                                <h3 class="card-value txt-green" id="stat-avg-attendance"><?php echo $stats['avgAttendance']; ?>%</h3>
                                <span class="card-change text-success"><i class="fa-solid fa-arrow-up"></i> Target 75%+</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-submissions" data-target-tab="activities">
                            <div class="card-icon"><i class="fa-solid fa-folder-open"></i></div>
                            <div class="card-info">
                                <span class="card-label">Activity Submissions</span>
                                <h3 class="card-value txt-blue" id="stat-submission-rate"><?php echo $stats['submissionRate']; ?>%</h3>
                                <span class="card-change text-info"><i class="fa-solid fa-check-double"></i> 15 pending</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-performance" data-target-tab="performance">
                            <div class="card-icon"><i class="fa-solid fa-graduation-cap"></i></div>
                            <div class="card-info">
                                <span class="card-label">Avg Performance</span>
                                <h3 class="card-value txt-purple" id="stat-avg-performance"><?php echo htmlspecialchars($stats['avgPerformance']); ?></h3>
                                <span class="card-change text-success"><i class="fa-solid fa-chart-line"></i> Good level</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-atrisk" data-target-tab="attendance">
                            <div class="card-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="card-info">
                                <span class="card-label">Students at Risk</span>
                                <h3 class="card-value txt-red" id="stat-risk-students"><?php echo $stats['riskStudents']; ?></h3>
                                <span class="card-change text-danger"><i class="fa-solid fa-circle-exclamation"></i> Low attendance/marks</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-parent" data-target-tab="parent-comm">
                            <div class="card-icon"><i class="fa-solid fa-people-roof"></i></div>
                            <div class="card-info">
                                <span class="card-label">Parent Meetings</span>
                                <h3 class="card-value txt-orange" id="stat-parent-meetings"><?php echo $stats['parentMeetings']; ?></h3>
                                <span class="card-change text-warning"><i class="fa-solid fa-calendar"></i> 2 scheduled</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>
                    </div>

                    <!-- Row 1: Student Progress & GFM Notifications -->
                    <div class="dashboard-row double-column">
                        
                        <!-- Student Progress Section -->
                        <div class="dashboard-card main-card flex-grow-1">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-chart-simple header-icon"></i>
                                    <h3>Student Progress</h3>
                                </div>
                                <button class="btn btn-secondary-sm" id="btn-view-all-students-tab"><i class="fa-regular fa-eye"></i> View All Students</button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table">
                                        <thead>
                                            <tr>
                                                <th>Rank</th>
                                                <th>Roll No</th>
                                                <th>Student Name</th>
                                                <th>Attendance</th>
                                                <th>Average Marks</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="student-progress-list">
                                            <?php foreach (array_slice($students, 0, 5) as $st): ?>
                                            <tr>
                                                <td><strong>#<?php echo $st['rank']; ?></strong></td>
                                                <td><code><?php echo htmlspecialchars($st['roll']); ?></code></td>
                                                <td><strong><?php echo htmlspecialchars($st['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($st['attendance']); ?></td>
                                                <td><strong><?php echo htmlspecialchars($st['marks']); ?></strong></td>
                                                <td><span class="badge <?php echo $st['status'] === 'Excellent' ? 'badge-success' : ($st['status'] === 'Good' ? 'badge-info' : 'badge-danger'); ?>"><?php echo htmlspecialchars($st['status']); ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- GFM Notifications Card -->
                        <div class="dashboard-card main-card" style="min-width: 320px;">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-bell header-icon text-orange"></i>
                                    <h3>Notifications</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="notifications-list" id="notifications-list">
                                    <!-- Dynamic notifications from JS -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Attendance Monitor & Quick Actions -->
                    <div class="dashboard-row">
                        <!-- Attendance Monitoring Panel -->
                        <div class="dashboard-card main-card flex-1">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-clipboard-user header-icon text-green"></i>
                                    <h3>Attendance Monitoring</h3>
                                </div>
                                <div class="chart-tab-controls">
                                    <button class="chart-tab-btn active" data-chart="attendance-ratio">Present/Absent Ratio</button>
                                    <button class="chart-tab-btn" data-chart="attendance-trend">Monthly Trend</button>
                                </div>
                            </div>
                            <div class="card-body chart-wrapper">
                                <div class="chart-container">
                                    <canvas id="gfm-attendance-chart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Low Attendance List -->
                        <div class="dashboard-card main-card flex-grow-0" style="min-width: 320px;">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-triangle-exclamation header-icon text-red"></i>
                                    <h3>Low Attendance List (&lt;75%)</h3>
                                </div>
                                <button class="btn btn-secondary-sm" id="btn-view-att-report-dashboard">Report</button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table clean-table">
                                        <thead>
                                            <tr>
                                                <th>Roll No</th>
                                                <th>Student Name</th>
                                                <th>Attendance</th>
                                            </tr>
                                        </thead>
                                        <tbody id="low-attendance-list">
                                            <?php foreach ($lowAttendance as $la): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($la['roll']); ?></code></td>
                                                <td><strong><?php echo htmlspecialchars($la['name']); ?></strong></td>
                                                <td><span class="text-danger font-semibold"><?php echo htmlspecialchars($la['attendance']); ?></span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Activity Submission & Pending Students -->
                    <div class="dashboard-row">
                        <!-- Activity Submission Charts -->
                        <div class="dashboard-card main-card flex-1">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-folder-open header-icon text-purple"></i>
                                    <h3>Activity Submission Rate</h3>
                                </div>
                                <div class="chart-tab-controls">
                                    <button class="chart-tab-btn2 active" data-chart="submission-ratio">Submission Status</button>
                                </div>
                            </div>
                            <div class="card-body chart-wrapper">
                                <div class="chart-container">
                                    <canvas id="gfm-activities-chart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Pending Students List -->
                        <div class="dashboard-card main-card flex-grow-0" style="min-width: 320px;">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-clock header-icon text-orange"></i>
                                    <h3>Pending Students (Activities)</h3>
                                </div>
                                <button class="btn btn-secondary-sm" id="btn-view-submissions-dashboard">Submissions</button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table clean-table">
                                        <thead>
                                            <tr>
                                                <th>Roll No</th>
                                                <th>Student</th>
                                                <th>Pending Tasks</th>
                                            </tr>
                                        </thead>
                                        <tbody id="pending-students-list">
                                            <?php foreach ($pendingActivities as $pa): ?>
                                            <tr>
                                                <td><code><?php echo htmlspecialchars($pa['roll']); ?></code></td>
                                                <td><strong><?php echo htmlspecialchars($pa['name']); ?></strong></td>
                                                <td><span class="badge badge-warning"><?php echo $pa['count']; ?> Pending</span></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Academic Performance, Counseling, Parent Communication -->
                    <div class="dashboard-row double-column">
                        <!-- Academic Performance -->
                        <div class="dashboard-card main-card flex-grow-1">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-graduation-cap header-icon text-purple"></i>
                                    <h3>Academic Performance</h3>
                                </div>
                                <div class="chart-tab-controls">
                                    <button class="chart-tab-btn3 active" data-chart="semester-marks">Semester-wise Marks</button>
                                    <button class="chart-tab-btn3" data-chart="perf-trend">Performance Trend</button>
                                    <button class="chart-tab-btn3" data-chart="grade-dist">Grade Distribution</button>
                                    <button class="chart-tab-btn3" data-chart="weak-students">Weak Students Analysis</button>
                                </div>
                            </div>
                            <div class="card-body chart-wrapper">
                                <div class="chart-container">
                                    <canvas id="gfm-performance-chart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions Grid -->
                        <div class="dashboard-card main-card" style="min-width: 320px;">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-bolt header-icon text-yellow"></i>
                                    <h3>Quick Actions</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="quick-actions-buttons-grid">
                                    <button class="btn btn-action-card bg-blue-light" id="qa-view-students">
                                        <i class="fa-solid fa-user-graduate"></i>
                                        <span>View Students</span>
                                    </button>
                                    <button class="btn btn-action-card bg-green-light" id="qa-mark-attendance">
                                        <i class="fa-solid fa-clipboard-user"></i>
                                        <span>Mark Attendance</span>
                                    </button>
                                    <button class="btn btn-action-card bg-yellow-light" id="qa-review-activities">
                                        <i class="fa-solid fa-folder-open"></i>
                                        <span>Review Activities</span>
                                    </button>
                                    <button class="btn btn-action-card bg-purple-light" id="qa-gen-student-rpt">
                                        <i class="fa-solid fa-file-pdf"></i>
                                        <span>Generate Report</span>
                                    </button>
                                    <button class="btn btn-action-card bg-orange-light" id="qa-send-notice">
                                        <i class="fa-solid fa-bullhorn"></i>
                                        <span>Send Notice</span>
                                    </button>
                                    <button class="btn btn-action-card bg-red-light" id="qa-schedule-parent">
                                        <i class="fa-solid fa-people-roof"></i>
                                        <span>Schedule Parent Meeting</span>
                                    </button>
                                </div>
                                <button class="btn btn-secondary w-100 mt-3" id="qa-add-counseling"><i class="fa-solid fa-notes-medical"></i> Add Counseling Notes</button>
                            </div>
                        </div>
                    </div>

                    <!-- Row 5: counseling & Parent communications list -->
                    <div class="dashboard-row double-column">
                        <!-- Student Counseling Tracker -->
                        <div class="dashboard-card main-card">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-user-doctor header-icon text-purple"></i>
                                    <h3>Student Counseling Logs</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Reason</th>
                                                <th>Status</th>
                                                <th>Next Meeting</th>
                                                <th class="text-right">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="counseling-list">
                                            <!-- Dynamic items -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Parent Communication Tracker -->
                        <div class="dashboard-card main-card">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-people-roof header-icon text-orange"></i>
                                    <h3>Parent Communication Ledger</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table">
                                        <thead>
                                            <tr>
                                                <th>Parent</th>
                                                <th>Student Name</th>
                                                <th>Last Meeting</th>
                                                <th>Status</th>
                                                <th class="text-right">Quick Contact Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="parent-communication-list">
                                            <!-- Dynamic items -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 6: Report Generation Center -->
                    <div class="dashboard-row">
                        <div class="dashboard-card main-card w-100">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-file-pdf header-icon text-red"></i>
                                    <h3>Reports Center</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="reports-controls-container">
                                    <p class="reports-intro">Process and compile PDF documents or export excel rosters matching GFM requirements.</p>
                                    <div class="reports-buttons-grid">
                                        <button class="btn btn-secondary" data-report="student-progress">
                                            <i class="fa-solid fa-user-graduate text-blue"></i> Student Progress Report
                                        </button>
                                        <button class="btn btn-secondary" data-report="attendance">
                                            <i class="fa-solid fa-clipboard-user text-green"></i> Attendance Report
                                        </button>
                                        <button class="btn btn-secondary" data-report="performance">
                                            <i class="fa-solid fa-chart-line text-purple"></i> Performance Analysis
                                        </button>
                                        <button class="btn btn-secondary" data-report="activity">
                                            <i class="fa-solid fa-folder-open text-orange"></i> Activity Report
                                        </button>
                                        <button class="btn btn-secondary" data-report="parent">
                                            <i class="fa-solid fa-people-roof text-teal"></i> Parent Meeting Report
                                        </button>
                                        <button class="btn btn-primary" id="btn-export-excel">
                                            <i class="fa-regular fa-file-excel"></i> Export to Excel / PDF
                                        </button>
                                    </div>
                                    <!-- Simple reports processing feedback loader -->
                                    <div class="reports-loader-placeholder" id="reports-loader">
                                        <div class="spinner"></div>
                                        <span id="reports-loader-text">Compiling SAAES registers...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Dynamic Tab Contents served via JS -->
                <section class="tab-content" id="tab-my-students">
                    <div class="tab-header-flex">
                        <h2>Assigned GFM Students Directory</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4">
                        <div class="table-search-header">
                            <div class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="students-search" placeholder="Search student names, roll numbers...">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Roll No</th>
                                        <th>Student Name</th>
                                        <th>Attendance Rate</th>
                                        <th>Average marks Score</th>
                                        <th>Counseling Status</th>
                                        <th>Parent Contact Status</th>
                                        <th class="text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="students-directory-tbody">
                                    <!-- Dynamic rows -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-student-progress">
                    <div class="tab-header-flex">
                        <h2>Student Progress Review</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="progress-tab-wrapper">
                        <!-- Cloned progress table -->
                    </div>
                </section>

                <section class="tab-content" id="tab-attendance">
                    <div class="tab-header-flex">
                        <h2>Attendance Monitoring Registers</h2>
                    </div>
                    <div class="grid-columns-2 gap-4 mt-4">
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>GFM Batch Attendance Ratio</h3>
                            </div>
                            <div class="card-body chart-wrapper">
                                <canvas id="attendance-tab-pie-chart" style="max-height:350px;"></canvas>
                            </div>
                        </div>
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Monthly Attendance Trend</h3>
                            </div>
                            <div class="card-body chart-wrapper">
                                <canvas id="attendance-tab-trend-chart" style="max-height:350px;"></canvas>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-activities">
                    <div class="tab-header-flex">
                        <h2>Student Activity Submission Rates</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4">
                        <div class="card-header border-bottom">
                            <h3>Submissions vs Pending Task Ratios</h3>
                        </div>
                        <div class="card-body chart-wrapper">
                            <canvas id="activities-tab-pie-chart" style="max-height:350px;"></canvas>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-performance">
                    <div class="tab-header-flex">
                        <h2>Academic Performance Analysis</h2>
                    </div>
                    <div class="grid-columns-2 gap-4 mt-4">
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Academic grade distribution spectrum</h3>
                            </div>
                            <div class="card-body chart-wrapper">
                                <canvas id="performance-tab-dist-chart" style="max-height: 350px;"></canvas>
                            </div>
                        </div>
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Overall Student Performance Trend</h3>
                            </div>
                            <div class="card-body chart-wrapper">
                                <canvas id="performance-tab-trend-chart" style="max-height: 350px;"></canvas>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-parent-comm">
                    <div class="tab-header-flex">
                        <h2>Parent Communication Ledger</h2>
                        <button class="btn btn-primary" id="btn-schedule-parent-top"><i class="fa-solid fa-plus"></i> Schedule Meeting</button>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="parent-tab-wrapper">
                        <!-- Cloned table -->
                    </div>
                </section>

                <section class="tab-content" id="tab-counseling">
                    <div class="tab-header-flex">
                        <h2>Student Counseling Registry</h2>
                        <button class="btn btn-primary" id="btn-add-counseling-top"><i class="fa-solid fa-plus"></i> Add Counseling Note</button>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="counseling-tab-wrapper">
                        <!-- Cloned table -->
                    </div>
                </section>

                <section class="tab-content" id="tab-notices">
                    <div class="tab-header-flex">
                        <h2>SAAES Notice Board Announcements</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4" style="max-width: 600px; margin: 24px auto 0 auto;">
                        <div class="card-header border-bottom">
                            <h3>Compose GFM Notices / Announcements</h3>
                        </div>
                        <div class="card-body">
                            <form id="notice-composer-form">
                                <div class="form-group mb-3">
                                    <label class="form-label">Notice Title</label>
                                    <input type="text" id="notice-title" class="form-control" placeholder="e.g. Attendance Defaulters Review" required>
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label">Notice Content</label>
                                    <textarea id="notice-content" class="form-control" rows="4" placeholder="Type notice message here..." required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Broadcast Notice Announcement</button>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-reports">
                    <div class="tab-header-flex">
                        <h2>Reports Management console</h2>
                    </div>
                    <div class="reports-view-wrapper mt-4" id="reports-tab-wrapper">
                        <!-- Cloned content -->
                    </div>
                </section>

                <section class="tab-content" id="tab-settings">
                    <div class="tab-header-flex">
                        <h2>GFM settings panel</h2>
                    </div>
                    <div class="grid-columns-2 gap-4 mt-4">
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Account Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($gfmName); ?>">
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label">Academic Role</label>
                                    <input type="text" class="form-control" value="Group Faculty Member (GFM)" disabled>
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-control" value="Electronics & Telecommunication (E&TC)">
                                </div>
                                <button class="btn btn-primary" id="settings-save-btn">Save Account Details</button>
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
    <div class="modal-overlay" id="modal-contact-parent">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-people-roof text-orange"></i> GFM Parent Contact action</h3>
                <button class="close-modal-btn" id="close-contact-modal-btn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form id="contact-parent-form">
                    <input type="hidden" id="contact-parent-index">
                    <div class="form-group mb-3">
                        <label class="form-label">Student Name</label>
                        <input type="text" id="contact-student-name" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Parent Contact</label>
                        <input type="text" id="contact-parent-name" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Communication Medium</label>
                        <div class="radio-group-flex">
                            <label class="radio-label"><input type="radio" name="comm-type" value="SMS" checked> <span>📱 Send SMS</span></label>
                            <label class="radio-label"><input type="radio" name="comm-type" value="Email"> <span>📧 Send Email</span></label>
                            <label class="radio-label"><input type="radio" name="comm-type" value="Call"> <span>📞 Direct Call</span></label>
                        </div>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Message details</label>
                        <textarea id="contact-msg" class="form-control" rows="3" placeholder="Type summary details..."></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary-outline" id="btn-cancel-contact">Cancel</button>
                        <button type="submit" class="btn btn-primary">Dispatch & Log</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modal-schedule-meeting">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-calendar-days text-purple"></i> Schedule Parent Meeting</h3>
                <button class="close-modal-btn" id="close-meeting-modal-btn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form id="schedule-meeting-form">
                    <div class="form-group mb-3">
                        <label class="form-label">Select Student</label>
                        <select class="form-select" id="meet-student" required></select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Proposed Meeting Date</label>
                        <input type="date" class="form-control" id="meet-date" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary-outline" id="btn-cancel-meeting">Cancel</button>
                        <button type="submit" class="btn btn-primary">Schedule Meeting</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="modal-add-counseling">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-notes-medical text-blue"></i> Record Student Counseling Notes</h3>
                <button class="close-modal-btn" id="close-counseling-modal-btn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form id="add-counseling-form">
                    <div class="form-group mb-3">
                        <label class="form-label">Select Student</label>
                        <select class="form-select" id="counsel-student" required></select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Counseling Topic / Reason</label>
                        <input type="text" class="form-control" id="counsel-reason" required>
                    </div>
                    <div class="grid-columns-2 gap-3 mb-3">
                        <div class="form-group">
                            <label class="form-label">Next Meeting Date</label>
                            <input type="date" class="form-control" id="counsel-date" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="counsel-status">
                                <option value="Scheduled">Scheduled</option>
                                <option value="Pending">Pending</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary-outline" id="btn-cancel-counsel">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Notes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Global Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Main Engine Script -->
    <script src="assets/js/script.js"></script>
</body>
</html>
