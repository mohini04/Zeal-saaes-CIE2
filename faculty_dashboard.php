<?php
$facultyName = "Dr. Mohini Deore";
$deptName = "E&TC Department";
$collegeName = "Zeal College of Engineering & Research, Pune";

$stats = [
    "totalActivities" => 24,
    "pendingEvaluations" => 7,
    "evaluatedActivities" => 92,
    "assignedStudents" => 58,
    "upcomingDeadlines" => 5,
    "averageScore" => 84
];

$activities = [
    ["id" => "ACT-01", "name" => "Digital Logic K-Map", "subject" => "Digital Logic", "unit" => "Unit 2", "duedate" => "20 Jul", "maxmarks" => 5, "status" => "Active"],
    ["id" => "ACT-02", "name" => "Number System", "subject" => "Digital Logic", "unit" => "Unit 1", "duedate" => "10 Jul", "maxmarks" => 5, "status" => "Completed"],
    ["id" => "ACT-03", "name" => "Flip-Flop Analysis", "subject" => "Digital Logic", "unit" => "Unit 3", "duedate" => "25 Jul", "maxmarks" => 5, "status" => "Upcoming"]
];

$submissions = [
    ["id" => "SUB-01", "student" => "Siddhant Deshmukh", "activity" => "K-Map", "date" => "Today", "delay" => "On Time", "marks" => "5/5", "status" => "Evaluated"],
    ["id" => "SUB-02", "student" => "Pooja Sharma", "activity" => "Number System", "date" => "Yesterday", "delay" => "On Time", "marks" => "4/5", "status" => "Evaluated"],
    ["id" => "SUB-03", "student" => "Neha Jadhav", "activity" => "Flip-Flop", "date" => "Today", "delay" => "Late", "marks" => "Pending", "status" => "Pending"],
    ["id" => "SUB-04", "student" => "Amit Shinde", "activity" => "K-Map", "date" => "Yesterday", "delay" => "On Time", "marks" => "Pending", "status" => "Pending"],
    ["id" => "SUB-05", "student" => "Karan Patil", "activity" => "Number System", "date" => "2 Days Ago", "delay" => "On Time", "marks" => "3/5", "status" => "Evaluated"],
    ["id" => "SUB-06", "student" => "Rohan Mane", "activity" => "K-Map", "date" => "Today", "delay" => "On Time", "marks" => "Pending", "status" => "Pending"],
    ["id" => "SUB-07", "student" => "Snehal Gore", "activity" => "Flip-Flop", "date" => "Yesterday", "delay" => "Late", "marks" => "Pending", "status" => "Pending"]
];

$students = [
    ["roll" => "SE-2401", "name" => "Siddhant Deshmukh", "division" => "SE Div A", "completed" => 12, "avg" => "4.5 / 5", "attendance" => "68%"],
    ["roll" => "TE-2315", "name" => "Pooja Sharma", "division" => "TE Div A", "completed" => 14, "avg" => "4.8 / 5", "attendance" => "82%"],
    ["roll" => "BE-2208", "name" => "Neha Jadhav", "division" => "BE Div B", "completed" => 10, "avg" => "3.9 / 5", "attendance" => "72%"],
    ["roll" => "SE-2402", "name" => "Amit Shinde", "division" => "SE Div A", "completed" => 11, "avg" => "4.1 / 5", "attendance" => "78%"],
    ["roll" => "SE-2403", "name" => "Karan Patil", "division" => "SE Div A", "completed" => 12, "avg" => "4.3 / 5", "attendance" => "88%"]
];

$notifications = [
    ["id" => "NTF-01", "message" => "5 submissions pending evaluation", "level" => "danger"],
    ["id" => "NTF-02", "message" => "Unit 3 activity due tomorrow", "level" => "warning"],
    ["id" => "NTF-03", "message" => "New submissions received", "level" => "success"],
    ["id" => "NTF-04", "message" => "Attendance updated", "level" => "info"],
    ["id" => "NTF-05", "message" => "Report generation completed", "level" => "purple"]
];

$deadlines = [
    ["date" => "17 Jul", "activity" => "Number System"],
    ["date" => "20 Jul", "activity" => "K-Map Assignment"],
    ["date" => "25 Jul", "activity" => "Flip-Flop Analysis"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Dashboard | Zeal College of Engineering & Research</title>
    <!-- Google Fonts: Outfit & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="style.css">
    <script>
        // Inject PHP serialized arrays directly into Client-side JS window object
        window.PHP_DATA = <?php echo json_encode([
            'stats' => $stats,
            'activities' => $activities,
            'submissions' => $submissions,
            'students' => $students,
            'notifications' => $notifications,
            'deadlines' => $deadlines
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
                    <span>July 16, 2026</span>
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
                            <span class="profile-name"><?php echo htmlspecialchars($facultyName); ?></span>
                            <span class="profile-role">E&TC Department</span>
                        </div>
                        <i class="fa-solid fa-chevron-down profile-arrow"></i>
                    </div>
                    <div class="profile-dropdown" id="profile-dropdown-menu">
                        <a href="#" class="dropdown-item"><i class="fa-regular fa-user"></i> My Profile</a>
                        <a href="#" class="dropdown-item"><i class="fa-solid fa-sliders"></i> Preferences</a>
                        <div class="dropdown-divider"></div>
                        <a href="#" class="dropdown-item text-danger" id="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="main-layout">
            <!-- Left Sidebar -->
            <aside class="sidebar">
                <nav class="sidebar-nav">
                    <ul>
                        <li class="nav-item active" data-tab="dashboard">
                            <a href="#"><i class="fa-solid fa-house"></i> <span>Dashboard</span></a>
                        </li>
                        <li class="nav-item" data-tab="create-activity">
                            <a href="#"><i class="fa-solid fa-plus-circle"></i> <span>Create Activity</span></a>
                        </li>
                        <li class="nav-item" data-tab="manage-activities">
                            <a href="#"><i class="fa-solid fa-folder-open"></i> <span>Manage Activities</span></a>
                        </li>
                        <li class="nav-item" data-tab="view-submissions">
                            <a href="#"><i class="fa-solid fa-inbox"></i> <span>View Submissions</span></a>
                        </li>
                        <li class="nav-item" data-tab="evaluate-activities">
                            <a href="#"><i class="fa-solid fa-square-check"></i> <span>Evaluate Activities</span><span class="sidebar-badge" id="sidebar-eval-count"><?php echo $stats['pendingEvaluations']; ?></span></a>
                        </li>
                        <li class="nav-item" data-tab="students">
                            <a href="#"><i class="fa-solid fa-user-graduate"></i> <span>Students</span></a>
                        </li>
                        <li class="nav-item" data-tab="analytics">
                            <a href="#"><i class="fa-solid fa-chart-line"></i> <span>Performance Analytics</span></a>
                        </li>
                        <li class="nav-item" data-tab="attendance">
                            <a href="#"><i class="fa-solid fa-clipboard-user"></i> <span>Attendance Overview</span></a>
                        </li>
                        <li class="nav-item" data-tab="reports">
                            <a href="#"><i class="fa-solid fa-file-invoice"></i> <span>Reports</span></a>
                        </li>
                        <li class="nav-item" data-tab="notifications">
                            <a href="#"><i class="fa-solid fa-bell"></i> <span>Notifications</span><span class="sidebar-badge bg-danger" id="sidebar-notif-count"><?php echo count($notifications); ?></span></a>
                        </li>
                        <li class="nav-item" data-tab="settings">
                            <a href="#"><i class="fa-solid fa-gear"></i> <span>Settings</span></a>
                        </li>
                    </ul>
                </nav>
                <div class="sidebar-footer">
                    <div class="faculty-profile-card">
                        <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&q=80&w=200" alt="Faculty Portrait" class="faculty-img">
                        <div class="faculty-details">
                            <p class="faculty-name"><?php echo htmlspecialchars($facultyName); ?></p>
                            <p class="faculty-title">Assistant Professor</p>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Main Scrollable Content Area -->
            <main class="content-area">
                
                <!-- Tab: Dashboard -->
                <section class="tab-content active" id="tab-dashboard">
                    <!-- Top Welcome banner inside dashboard -->
                    <div class="welcome-banner">
                        <div class="welcome-text">
                            <h2>Welcome back, <?php echo htmlspecialchars($facultyName); ?></h2>
                            <p>Here is the activity assessment status for your assigned classes today.</p>
                        </div>
                        <div class="banner-badge">
                            <span class="live-pulse"></span> Academic Year: 2026-27 (Odd Sem)
                        </div>
                    </div>

                    <!-- Top Summary Cards -->
                    <div class="summary-cards-grid">
                        <div class="summary-card card-activities" data-target-tab="manage-activities">
                            <div class="card-icon"><i class="fa-solid fa-book"></i></div>
                            <div class="card-info">
                                <span class="card-label">Total Activities</span>
                                <h3 class="card-value" id="stat-total-activities"><?php echo $stats['totalActivities']; ?></h3>
                                <span class="card-change text-info"><i class="fa-solid fa-calendar"></i> 3 active now</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-pending" data-target-tab="evaluate-activities">
                            <div class="card-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                            <div class="card-info">
                                <span class="card-label">Pending Evaluations</span>
                                <h3 class="card-value txt-orange" id="stat-pending-evals"><?php echo $stats['pendingEvaluations']; ?></h3>
                                <span class="card-change text-warning"><i class="fa-solid fa-clock"></i> Action required</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-evaluated" data-target-tab="manage-activities">
                            <div class="card-icon"><i class="fa-solid fa-circle-check"></i></div>
                            <div class="card-info">
                                <span class="card-label">Evaluated Activities</span>
                                <h3 class="card-value txt-green" id="stat-evaluated-activities"><?php echo $stats['evaluatedActivities']; ?></h3>
                                <span class="card-change text-success"><i class="fa-solid fa-check"></i> Portfolio records</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-students" data-target-tab="students">
                            <div class="card-icon"><i class="fa-solid fa-users"></i></div>
                            <div class="card-info">
                                <span class="card-label">Assigned Students</span>
                                <h3 class="card-value txt-blue" id="stat-assigned-students"><?php echo $stats['assignedStudents']; ?></h3>
                                <span class="card-change text-success">Active roster</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-deadlines" data-target-tab="manage-activities">
                            <div class="card-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                            <div class="card-info">
                                <span class="card-label">Upcoming Deadlines</span>
                                <h3 class="card-value txt-red" id="stat-upcoming-deadlines"><?php echo $stats['upcomingDeadlines']; ?></h3>
                                <span class="card-change text-danger">Within 7 days</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-score" data-target-tab="analytics">
                            <div class="card-icon"><i class="fa-solid fa-star"></i></div>
                            <div class="card-info">
                                <span class="card-label">Average Student Score</span>
                                <h3 class="card-value txt-purple" id="stat-average-score"><?php echo $stats['averageScore']; ?>%</h3>
                                <span class="card-change text-success"><i class="fa-solid fa-arrow-trend-up"></i> Excellent range</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>
                    </div>

                    <!-- Row 1: Activity Management & Submissions -->
                    <div class="dashboard-row double-column">
                        
                        <!-- Activity Management Section -->
                        <div class="dashboard-card main-card flex-grow-1">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-book-open header-icon"></i>
                                    <h3>Activity Management</h3>
                                </div>
                                <div class="action-buttons">
                                    <button class="btn btn-primary-sm" id="btn-create-activity-dash"><i class="fa-solid fa-plus"></i> Create Activity</button>
                                    <button class="btn btn-secondary-sm" id="btn-view-activities-dash"><i class="fa-regular fa-eye"></i> View All Activities</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table">
                                        <thead>
                                            <tr>
                                                <th>Activity</th>
                                                <th>Subject</th>
                                                <th>Unit</th>
                                                <th>Due Date</th>
                                                <th>Max Marks</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="activity-list-dash">
                                            <?php foreach ($activities as $act): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($act['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($act['subject']); ?></td>
                                                <td><?php echo htmlspecialchars($act['unit']); ?></td>
                                                <td><?php echo htmlspecialchars($act['duedate']); ?></td>
                                                <td><strong><?php echo $act['maxmarks']; ?></strong></td>
                                                <td>
                                                    <span class="badge <?php echo $act['status'] === 'Active' ? 'badge-success' : ($act['status'] === 'Completed' ? 'badge-info' : 'badge-warning'); ?>">
                                                        <?php echo htmlspecialchars($act['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Submissions Review -->
                        <div class="dashboard-card main-card">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-inbox header-icon text-blue"></i>
                                    <h3>Submissions Overview</h3>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table">
                                        <thead>
                                            <tr>
                                                <th>Student</th>
                                                <th>Activity</th>
                                                <th>Submitted On</th>
                                                <th>Marks</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="submissions-list-dash">
                                            <?php foreach (array_slice($submissions, 0, 4) as $sub): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($sub['student']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($sub['activity']); ?></td>
                                                <td><?php echo htmlspecialchars($sub['date']); ?></td>
                                                <td><strong><?php echo htmlspecialchars($sub['marks']); ?></strong></td>
                                                <td>
                                                    <span class="badge <?php echo $sub['status'] === 'Evaluated' ? 'badge-success' : 'badge-warning'; ?>">
                                                        <?php echo htmlspecialchars($sub['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Analytics & Deadlines -->
                    <div class="dashboard-row">
                        <!-- Chart Card 1 -->
                        <div class="dashboard-card main-card flex-1">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-chart-line header-icon text-purple"></i>
                                    <h3>Performance Analytics</h3>
                                </div>
                                <div class="chart-tab-controls">
                                    <button class="chart-tab-btn active" data-chart="unit-marks">Unit-wise Marks</button>
                                    <button class="chart-tab-btn" data-chart="submission">Submission Status</button>
                                </div>
                            </div>
                            <div class="card-body chart-wrapper">
                                <div class="chart-container">
                                    <canvas id="faculty-performance-chart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Deadlines Card -->
                        <div class="dashboard-card main-card flex-grow-0" style="min-width: 320px;">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-clock header-icon text-red"></i>
                                    <h3>Upcoming Deadlines</h3>
                                </div>
                            </div>
                            <div class="card-body flex-column gap-3">
                                <div class="deadlines-vertical-list">
                                    <?php foreach ($deadlines as $dl): ?>
                                    <div class="deadline-item-card">
                                        <span class="dl-date"><i class="fa-regular fa-calendar-days"></i> <?php echo htmlspecialchars($dl['date']); ?></span>
                                        <span class="dl-name"><?php echo htmlspecialchars($dl['activity']); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Dynamic Tab Contents Served via JS -->
                <section class="tab-content" id="tab-create-activity">
                    <div class="tab-header-flex">
                        <h2>Create Student Activity Task</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4" style="max-width:600px; margin: 0 auto;">
                        <div class="card-body">
                            <form id="create-activity-form-tab">
                                <div class="form-group mb-3">
                                    <label class="form-label">Activity Name</label>
                                    <input type="text" class="form-control" id="act-name-tab" placeholder="e.g. Logic Minimization Quiz" required>
                                </div>
                                <div class="grid-columns-2 gap-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Subject</label>
                                        <select class="form-select" id="act-subj-tab">
                                            <option value="Digital Logic">Digital Logic</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Unit Code</label>
                                        <select class="form-select" id="act-unit-tab">
                                            <option value="Unit 1">Unit 1</option>
                                            <option value="Unit 2">Unit 2</option>
                                            <option value="Unit 3">Unit 3</option>
                                            <option value="Unit 4">Unit 4</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="grid-columns-2 gap-3 mb-3">
                                    <div class="form-group">
                                        <label class="form-label">Due Date</label>
                                        <input type="date" class="form-control" id="act-date-tab" required>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Maximum Marks</label>
                                        <input type="number" class="form-control" id="act-marks-tab" min="1" max="100" value="5" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Broadcast Activity Task</button>
                            </form>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-manage-activities">
                    <div class="tab-header-flex">
                        <h2>Manage Assigned Activities</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="manage-activities-container">
                        <!-- Dynamic list -->
                    </div>
                </section>

                <section class="tab-content" id="tab-view-submissions">
                    <div class="tab-header-flex">
                        <h2>View Student Submissions</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="view-submissions-container">
                        <!-- Dynamic list -->
                    </div>
                </section>

                <section class="tab-content" id="tab-evaluate-activities">
                    <div class="tab-header-flex">
                        <h2>Evaluate Pending Submissions</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="evaluate-submissions-container">
                        <!-- Dynamic list -->
                    </div>
                </section>

                <section class="tab-content" id="tab-students">
                    <div class="tab-header-flex">
                        <h2>Students Performance Ledger</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="students-ledger-container">
                        <!-- Dynamic list -->
                    </div>
                </section>

                <section class="tab-content" id="tab-analytics">
                    <div class="tab-header-flex">
                        <h2>Performance Analytics Details</h2>
                    </div>
                    <div class="grid-columns-2 gap-4 mt-4">
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Unit-wise student grades</h3>
                            </div>
                            <div class="card-body chart-wrapper">
                                <canvas id="analytics-tab-marks-chart" style="max-height: 350px;"></canvas>
                            </div>
                        </div>
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Submission Rates Matrix</h3>
                            </div>
                            <div class="card-body chart-wrapper">
                                <canvas id="analytics-tab-rates-chart" style="max-height: 350px;"></canvas>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-attendance">
                    <div class="tab-header-flex">
                        <h2>Student Attendance overview</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="attendance-overview-container">
                        <!-- Dynamic list -->
                    </div>
                </section>

                <section class="tab-content" id="tab-reports">
                    <div class="tab-header-flex">
                        <h2>Reports compiler</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4" style="max-width: 600px; margin: 0 auto;">
                        <div class="card-header border-bottom">
                            <h3>Compile Performance reports</h3>
                        </div>
                        <div class="card-body">
                            <div class="reports-controls-container">
                                <p class="mb-4">Select class and compile reports sheets to export matching Excel sheets or PDF documents.</p>
                                <div class="grid-columns-2 gap-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Target Class</label>
                                        <select class="form-select" id="report-class">
                                            <option value="SE">SE E&TC (Div A)</option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="form-label">Report Type</label>
                                        <select class="form-select" id="report-type">
                                            <option value="progress">Student Progress Ledger</option>
                                            <option value="attendance">Defaulter list</option>
                                        </select>
                                    </div>
                                </div>
                                <button class="btn btn-primary w-100" id="btn-generate-report"><i class="fa-regular fa-file-excel"></i> Export spreadsheet</button>
                                <div class="reports-loader-placeholder mt-3" id="reports-loader">
                                    <div class="spinner"></div>
                                    <span id="reports-loader-text">Compiling SAAES registers...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-notifications">
                    <div class="tab-header-flex">
                        <h2>Notifications list</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4" id="notifications-tab-container">
                        <!-- Cloned notifications -->
                    </div>
                </section>

                <section class="tab-content" id="tab-settings">
                    <div class="tab-header-flex">
                        <h2>Faculty settings panel</h2>
                    </div>
                    <div class="grid-columns-2 gap-4 mt-4">
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Account Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($facultyName); ?>">
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label">Primary Department</label>
                                    <input type="text" class="form-control" value="Electronics & Telecommunication (E&TC)">
                                </div>
                                <button class="btn btn-primary" id="settings-save-btn">Save Preferences</button>
                            </div>
                        </div>
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Layout Preferences</h3>
                            </div>
                            <div class="card-body">
                                <div class="theme-option-box mb-4">
                                    <label class="form-label">Theme Mode Selection</label>
                                    <div class="theme-mode-flex">
                                        <button class="btn btn-secondary w-50" id="btn-set-light-theme"><i class="fa-regular fa-sun"></i> Light Mode</button>
                                        <button class="btn btn-secondary-outline w-50" id="btn-set-dark-theme"><i class="fa-regular fa-moon"></i> Dark Mode</button>
                                    </div>
                                </div>
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
    
    <!-- Modal: Evaluate Activity -->
    <div class="modal-overlay" id="modal-evaluate-submission">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-square-check text-primary"></i> Evaluate Submission</h3>
                <button class="close-modal-btn" id="close-eval-modal-btn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form id="evaluation-submission-form">
                    <input type="hidden" id="eval-sub-id">
                    <div class="form-group mb-3">
                        <label class="form-label">Student Name</label>
                        <input type="text" id="eval-student" class="form-control" disabled>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label">Activity</label>
                        <input type="text" id="eval-activity" class="form-control" disabled>
                    </div>
                    <div class="grid-columns-2 gap-3 mb-3">
                        <div class="form-group">
                            <label class="form-label" for="eval-marks">Award Marks</label>
                            <input type="number" id="eval-marks" class="form-control" min="0" max="5" step="0.5" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="eval-feedback">Feedback Remarks</label>
                            <select id="eval-feedback" class="form-select">
                                <option value="Excellent">Excellent</option>
                                <option value="Good">Good</option>
                                <option value="Needs Review">Needs Review</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary-outline" id="btn-cancel-eval">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Assessment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Global Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <!-- Main Engine Script -->
    <script src="app.js"></script>
</body>
</html>
