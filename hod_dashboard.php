<?php
$hodName = "Dr. Mohini Deore";
$deptName = "E&TC Department";
$collegeName = "Zeal College of Engineering & Research, Pune";

$stats = [
    "totalFaculty" => 27,
    "totalStudents" => 612,
    "totalActivities" => 185,
    "pendingApprovals" => 17,
    "completedEvaluations" => 154,
    "deptPerformance" => 84
];

$faculty = [
    ["id" => "ETC-F04", "name" => "Prof. Patil", "subject" => "Digital Logic", "created" => 12, "pending" => 2, "marks" => 4.5, "status" => "Excellent"],
    ["id" => "ETC-F09", "name" => "Prof. Kulkarni", "subject" => "Microprocessor", "created" => 10, "pending" => 1, "marks" => 4.2, "status" => "Good"],
    ["id" => "ETC-F15", "name" => "Prof. Shah", "subject" => "DSP", "created" => 9, "pending" => 4, "marks" => 3.8, "status" => "Needs Review"],
    ["id" => "ETC-F21", "name" => "Prof. Deshmukh", "subject" => "Signals & Systems", "created" => 8, "pending" => 2, "marks" => 4.1, "status" => "Good"]
];

$activities = [
    ["id" => "ACT-101", "name" => "K-Map", "subject" => "Digital Logic", "faculty" => "Prof. Patil", "submitted" => 55, "total" => 58, "completion" => 95, "status" => "Active", "deadline" => "2026-07-28"],
    ["id" => "ACT-102", "name" => "Flip-Flop", "subject" => "Digital Logic", "faculty" => "Prof. Patil", "submitted" => 48, "total" => 58, "completion" => 83, "status" => "Running", "deadline" => "2026-07-30"],
    ["id" => "ACT-103", "name" => "FFT Assignment", "subject" => "DSP", "faculty" => "Prof. Shah", "submitted" => 35, "total" => 60, "completion" => 58, "status" => "Pending", "deadline" => "2026-07-24"],
    ["id" => "ACT-104", "name" => "8086 Assembly", "subject" => "Microprocessor", "faculty" => "Prof. Kulkarni", "submitted" => 50, "total" => 55, "completion" => 91, "status" => "Active", "deadline" => "2026-07-22"]
];

$subjects = [
    ["code" => "ETC-301", "name" => "Digital Logic", "coordinator" => "Prof. Patil", "progress" => 92],
    ["code" => "ETC-302", "name" => "Microprocessor", "coordinator" => "Prof. Kulkarni", "progress" => 87],
    ["code" => "ETC-401", "name" => "DSP", "coordinator" => "Prof. Shah", "progress" => 65],
    ["code" => "ETC-402", "name" => "Signals & Systems", "coordinator" => "Prof. Deshmukh", "progress" => 79]
];

$approvals = [
    ["id" => "APP-01", "faculty" => "Prof. Patil", "request" => "Activity Approval (K-Map Quiz)", "date" => "Today", "detail" => "Proposed new online assessment for SE students."],
    ["id" => "APP-02", "faculty" => "Prof. Shah", "request" => "Marks Modification (DSP Lab)", "date" => "Yesterday", "detail" => "Modify term work marks of roll no TE-2315 due to medical re-test."],
    ["id" => "APP-03", "faculty" => "Prof. Kulkarni", "request" => "Report Approval (Microprocessor)", "date" => "Today", "detail" => "Approve final lab evaluation reports for printing."]
];

$notifications = [
    ["id" => "NTF-01", "message" => "8 faculty activities awaiting approval", "level" => "warning"],
    ["id" => "NTF-02", "message" => "12 students have low attendance", "level" => "danger"],
    ["id" => "NTF-03", "message" => "5 reports ready for download", "level" => "success"],
    ["id" => "NTF-04", "message" => "NAAC report generated", "level" => "info"],
    ["id" => "NTF-05", "message" => "NBA data updated", "level" => "success"]
];

$deadlines = [
    ["date" => "18 Jul", "event" => "Unit-2 Evaluation", "days" => 2],
    ["date" => "20 Jul", "event" => "Faculty Report Submission", "days" => 4],
    ["date" => "25 Jul", "event" => "Internal Assessment", "days" => 9],
    ["date" => "28 Jul", "event" => "Activity Completion", "days" => 12]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard | Zeal College of Engineering & Research</title>
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
            'faculty' => $faculty,
            'activities' => $activities,
            'subjects' => $subjects,
            'approvals' => $approvals,
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
                        <span class="college-name">Zeal College of Engineering</span>
                        <span class="dept-name"><?php echo htmlspecialchars($deptName); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="header-center">
                <h1 class="dashboard-title">Department Activity Assessment & Evaluation Dashboard</h1>
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
                            <span class="profile-name"><?php echo htmlspecialchars($hodName); ?></span>
                            <span class="profile-role">HOD - E&TC</span>
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
                        <li class="nav-item" data-tab="faculty">
                            <a href="#"><i class="fa-solid fa-chalkboard-user"></i> <span>Faculty Management</span></a>
                        </li>
                        <li class="nav-item" data-tab="students">
                            <a href="#"><i class="fa-solid fa-user-graduate"></i> <span>Student Performance</span></a>
                        </li>
                        <li class="nav-item" data-tab="subjects">
                            <a href="#"><i class="fa-solid fa-book"></i> <span>Subject & Course</span></a>
                        </li>
                        <li class="nav-item" data-tab="monitoring">
                            <a href="#"><i class="fa-solid fa-clipboard-list"></i> <span>Activity Monitoring</span></a>
                        </li>
                        <li class="nav-item" data-tab="approvals">
                            <a href="#"><i class="fa-solid fa-circle-check"></i> <span>Approval Center</span><span class="sidebar-badge" id="sidebar-approval-count"><?php echo $stats['pendingApprovals']; ?></span></a>
                        </li>
                        <li class="nav-item" data-tab="analytics">
                            <a href="#"><i class="fa-solid fa-chart-line"></i> <span>Department Analytics</span></a>
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
                    <div class="hod-profile-card">
                        <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&q=80&w=200" alt="HOD Portrait" class="hod-img" id="hod-portrait-img">
                        <div class="hod-details">
                            <p class="hod-name"><?php echo htmlspecialchars($hodName); ?></p>
                            <p class="hod-title">Head of Department</p>
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
                            <h2>Welcome back, <?php echo htmlspecialchars($hodName); ?></h2>
                            <p>Here is your department activity summary and evaluation statistics for today.</p>
                        </div>
                        <div class="banner-badge">
                            <span class="live-pulse"></span> Academic Year: <?php echo htmlspecialchars($academicYear); ?>
                        </div>
                    </div>

                    <!-- Top Summary Cards -->
                    <div class="summary-cards-grid">
                        <div class="summary-card card-faculty" data-target-tab="faculty">
                            <div class="card-icon"><i class="fa-solid fa-chalkboard-user"></i></div>
                            <div class="card-info">
                                <span class="card-label">Total Faculty</span>
                                <h3 class="card-value" id="stat-total-faculty"><?php echo $stats['totalFaculty']; ?></h3>
                                <span class="card-change text-success"><i class="fa-solid fa-arrow-up"></i> Active now: 12</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-students" data-target-tab="students">
                            <div class="card-icon"><i class="fa-solid fa-user-graduate"></i></div>
                            <div class="card-info">
                                <span class="card-label">Total Students</span>
                                <h3 class="card-value txt-green" id="stat-total-students"><?php echo $stats['totalStudents']; ?></h3>
                                <span class="card-change text-success"><i class="fa-solid fa-check-double"></i> Enrollment base</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-activities" data-target-tab="monitoring">
                            <div class="card-icon"><i class="fa-solid fa-book"></i></div>
                            <div class="card-info">
                                <span class="card-label">Total Activities</span>
                                <h3 class="card-value txt-blue" id="stat-total-activities"><?php echo $stats['totalActivities']; ?></h3>
                                <span class="card-change text-info"><i class="fa-solid fa-plus"></i> Term syllabus</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-pending" data-target-tab="approvals">
                            <div class="card-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                            <div class="card-info">
                                <span class="card-label">Pending Approvals</span>
                                <h3 class="card-value txt-orange" id="stat-pending-approvals"><?php echo $stats['pendingApprovals']; ?></h3>
                                <span class="card-change text-warning"><i class="fa-solid fa-clock"></i> Action required</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-evaluations" data-target-tab="monitoring">
                            <div class="card-icon"><i class="fa-solid fa-circle-check"></i></div>
                            <div class="card-info">
                                <span class="card-label">Completed Evaluations</span>
                                <h3 class="card-value txt-purple" id="stat-completed-evaluations"><?php echo $stats['completedEvaluations']; ?></h3>
                                <span class="card-change text-success"><i class="fa-solid fa-check"></i> Portfolio records</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>

                        <div class="summary-card card-performance" data-target-tab="analytics">
                            <div class="card-icon"><i class="fa-solid fa-chart-line"></i></div>
                            <div class="card-info">
                                <span class="card-label">Dept Performance</span>
                                <h3 class="card-value txt-purple" id="stat-dept-performance"><?php echo $stats['deptPerformance']; ?>%</h3>
                                <span class="card-change text-success"><i class="fa-solid fa-arrow-trend-up"></i> Target 85%+</span>
                            </div>
                            <div class="card-decor"></div>
                        </div>
                    </div>

                    <!-- Row 1: Faculty Performance & Activity Overview -->
                    <div class="dashboard-row double-column">
                        
                        <!-- Faculty Performance Section -->
                        <div class="dashboard-card main-card flex-grow-1">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-chalkboard-user header-icon"></i>
                                    <h3>Faculty Performance Section</h3>
                                </div>
                                <div class="action-buttons">
                                    <button class="btn btn-secondary-sm" id="btn-view-faculty-tab"><i class="fa-regular fa-eye"></i> View Faculty</button>
                                    <button class="btn btn-primary-sm" id="btn-download-department-report"><i class="fa-solid fa-download"></i> Download Report</button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table">
                                        <thead>
                                            <tr>
                                                <th>Faculty</th>
                                                <th>Subject</th>
                                                <th>Activities Created</th>
                                                <th>Pending Evaluation</th>
                                                <th>Average Marks</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="faculty-performance-list">
                                            <?php foreach ($faculty as $fac): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($fac['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($fac['subject']); ?></td>
                                                <td><?php echo $fac['created']; ?></td>
                                                <td><span class="txt-orange font-semibold"><?php echo $fac['pending']; ?></span></td>
                                                <td><strong><?php echo $fac['marks']; ?></strong></td>
                                                <td>
                                                    <span class="badge <?php echo $fac['status'] === 'Excellent' ? 'badge-success' : ($fac['status'] === 'Good' ? 'badge-info' : 'badge-warning'); ?>">
                                                        <?php echo htmlspecialchars($fac['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Department Activity Overview -->
                        <div class="dashboard-card main-card">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-list-check header-icon"></i>
                                    <h3>Department Activity Overview</h3>
                                </div>
                                <button class="btn btn-primary-sm open-create-activity-modal-btn">
                                    <i class="fa-solid fa-plus"></i> Create Department Activity
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="dashboard-table">
                                        <thead>
                                            <tr>
                                                <th>Activity</th>
                                                <th>Subject</th>
                                                <th>Faculty</th>
                                                <th>Students Submitted</th>
                                                <th>Completion</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="activity-overview-list">
                                            <?php foreach ($activities as $act): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($act['name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($act['subject']); ?></td>
                                                <td><?php echo htmlspecialchars($act['faculty']); ?></td>
                                                <td><?php echo $act['submitted'] . '/' . $act['total']; ?></td>
                                                <td><strong><?php echo $act['completion']; ?>%</strong></td>
                                                <td>
                                                    <span class="badge <?php echo $act['status'] === 'Active' ? 'badge-success' : ($act['status'] === 'Running' ? 'badge-info' : 'badge-warning'); ?>">
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
                    </div>

                    <!-- Row 2: Charts Panel -->
                    <div class="dashboard-row">
                        <!-- Chart Card 1: Student Analytics -->
                        <div class="dashboard-card main-card flex-1">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-graduation-cap header-icon text-purple"></i>
                                    <h3>Student Performance Analytics</h3>
                                </div>
                                <div class="chart-tab-controls">
                                    <button class="chart-tab-btn active" data-chart="semester">Average Marks</button>
                                    <button class="chart-tab-btn" data-chart="submission">Submission Trend</button>
                                    <button class="chart-tab-btn" data-chart="passfail">Pass/Fail Ratio</button>
                                    <button class="chart-tab-btn" data-chart="weakdist">Weak Student Distribution</button>
                                </div>
                            </div>
                            <div class="card-body chart-wrapper">
                                <div class="chart-container">
                                    <canvas id="student-analytics-chart"></canvas>
                                </div>
                            </div>
                        </div>

                        <!-- Chart Card 2: Faculty Analytics -->
                        <div class="dashboard-card main-card flex-1">
                            <div class="card-header border-bottom">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-chart-bar header-icon text-blue"></i>
                                    <h3>Faculty Analytics</h3>
                                </div>
                                <div class="chart-tab-controls">
                                    <button class="chart-tab-btn2 active" data-chart="activity-bars">Activities Evaluated</button>
                                    <button class="chart-tab-btn2" data-chart="satisfaction">Student Satisfaction</button>
                                </div>
                            </div>
                            <div class="card-body chart-wrapper">
                                <div class="chart-container">
                                    <canvas id="faculty-analytics-chart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Approvals, Notifications & Deadlines -->
                    <div class="dashboard-row triple-column">
                        <!-- Approval Center Card -->
                        <div class="dashboard-card main-card">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-circle-check header-icon text-yellow"></i>
                                    <h3>Approval Center (Pending Items)</h3>
                                </div>
                                <span class="badge badge-warning" id="approval-badge-count">3 Pending</span>
                            </div>
                            <div class="card-body">
                                <div class="approval-items-list" id="approval-items-list">
                                    <!-- Rendered dynamically or via JS mapping -->
                                </div>
                            </div>
                        </div>

                        <!-- Department Notifications Card -->
                        <div class="dashboard-card main-card">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-bell header-icon text-red"></i>
                                    <h3>Department Notifications</h3>
                                </div>
                                <button class="text-btn" id="mark-all-read-btn">Mark all read</button>
                            </div>
                            <div class="card-body">
                                <div class="notifications-list" id="notifications-list">
                                    <!-- Rendered dynamically -->
                                </div>
                            </div>
                        </div>

                        <!-- Upcoming Deadlines & Insights -->
                        <div class="dashboard-card main-card">
                            <div class="card-header">
                                <div class="header-title-container">
                                    <i class="fa-solid fa-clock-rotate-left header-icon text-info"></i>
                                    <h3>Upcoming Deadlines</h3>
                                </div>
                            </div>
                            <div class="card-body flex-column gap-3">
                                <div class="deadlines-vertical-list">
                                    <?php foreach ($deadlines as $dl): ?>
                                    <div class="deadline-item-card">
                                        <span class="dl-date"><i class="fa-regular fa-calendar-days"></i> <?php echo htmlspecialchars($dl['date']); ?></span>
                                        <span class="dl-name"><?php echo htmlspecialchars($dl['event']); ?></span>
                                        <span class="dl-days text-muted"><?php echo $dl['days']; ?> Days Left</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Tab: Faculty Management -->
                <section class="tab-content" id="tab-faculty">
                    <div class="tab-header-flex">
                        <h2>Faculty Management</h2>
                        <button class="btn btn-primary open-faculty-modal-btn"><i class="fa-solid fa-plus"></i> Onboard New Faculty</button>
                    </div>
                    
                    <div class="table-card-wrapper main-card dashboard-card mt-4">
                        <div class="table-search-header">
                            <div class="search-box">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="faculty-search" placeholder="Search faculty name, subject...">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="dashboard-table">
                                <thead>
                                    <tr>
                                        <th>Faculty ID</th>
                                        <th>Faculty Name</th>
                                        <th>Subject Coordination</th>
                                        <th>Activities Created</th>
                                        <th>Pending Evaluations</th>
                                        <th>Department Performance</th>
                                        <th class="text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="faculty-table-tbody">
                                    <!-- Dynamic rows from JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Other Tabs Placed with dynamic JS content connections -->
                <section class="tab-content" id="tab-students">
                    <div class="tab-header-flex">
                        <h2>Student Activity Performance</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="students-tab-content">
                        <!-- Dynamic list -->
                    </div>
                </section>

                <section class="tab-content" id="tab-subjects">
                    <div class="tab-header-flex">
                        <h2>Subject & Course Coordination</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="subjects-tab-content">
                        <!-- Dynamic list -->
                    </div>
                </section>

                <section class="tab-content" id="tab-monitoring">
                    <div class="tab-header-flex">
                        <h2>Department Activity Monitoring</h2>
                    </div>
                    <div class="table-card-wrapper main-card dashboard-card mt-4" id="monitoring-tab-content">
                        <!-- Dynamic list -->
                    </div>
                </section>

                <section class="tab-content" id="tab-approvals">
                    <div class="tab-header-flex">
                        <h2>HOD Approval Center</h2>
                    </div>
                    <div class="grid-columns-2 gap-4 mt-4" id="approvals-tab-content">
                        <!-- Dynamic approvals list -->
                    </div>
                </section>

                <section class="tab-content" id="tab-analytics">
                    <div class="tab-header-flex">
                        <h2>Department Analytics Overview</h2>
                    </div>
                    <div class="grid-columns-2 gap-4 mt-4">
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Student Evaluation Marks Distribution</h3>
                            </div>
                            <div class="card-body chart-wrapper">
                                <canvas id="analytics-tab-student-chart" style="max-height: 350px;"></canvas>
                            </div>
                        </div>
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Faculty Evaluation Speed Ratios</h3>
                            </div>
                            <div class="card-body chart-wrapper">
                                <canvas id="analytics-tab-faculty-chart" style="max-height: 350px;"></canvas>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-reports">
                    <div class="tab-header-flex">
                        <h2>Department Reports Center</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4" style="max-width: 600px; margin: 24px auto 0 auto;">
                        <div class="card-header border-bottom">
                            <h3>Generate Department Evaluation Reports</h3>
                        </div>
                        <div class="card-body">
                            <div class="reports-controls-container">
                                <p class="mb-4">Select the class academic performance matrix and export matching spreadsheets or PDF document rosters.</p>
                                <div class="grid-columns-2 gap-4">
                                    <div class="form-group mb-3">
                                        <label class="form-label">Target Class</label>
                                        <select class="form-select" id="report-class-select">
                                            <option value="SE">SE E&TC (Div A & B)</option>
                                            <option value="TE">TE E&TC (Div A & B)</option>
                                            <option value="BE">BE E&TC (Div A & B)</option>
                                        </select>
                                    </div>
                                    <div class="form-group mb-3">
                                        <label class="form-label">Report Type</label>
                                        <select class="form-select" id="report-type-select">
                                            <option value="progress">Student Progress Ledger</option>
                                            <option value="attendance">Defaulter attendance list</option>
                                            <option value="faculty">Faculty Performance Sheet</option>
                                        </select>
                                    </div>
                                </div>
                                <button class="btn btn-primary w-100 mt-2" id="btn-generate-report-tab"><i class="fa-regular fa-file-excel"></i> Compile & Export to Excel</button>
                                <div class="reports-loader-placeholder mt-3" id="reports-loader">
                                    <div class="spinner"></div>
                                    <span id="reports-loader-text">Compiling department evaluation matrix...</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="tab-content" id="tab-notifications">
                    <div class="tab-header-flex">
                        <h2>Notifications Roster Log</h2>
                    </div>
                    <div class="dashboard-card main-card mt-4" id="notifications-tab-content">
                        <!-- Dynamic list -->
                    </div>
                </section>

                <section class="tab-content" id="tab-settings">
                    <div class="tab-header-flex">
                        <h2>HOD Settings & Preferences</h2>
                    </div>
                    <div class="grid-columns-2 gap-4 mt-4">
                        <div class="dashboard-card main-card">
                            <div class="card-header border-bottom">
                                <h3>Account Information</h3>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label class="form-label">HOD Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($hodName); ?>">
                                </div>
                                <div class="form-group mb-3">
                                    <label class="form-label">Department Primary</label>
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
    
    <!-- Modal: Create Activity -->
    <div class="modal-overlay" id="modal-create-activity">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-plus text-primary"></i> Create Department Activity</h3>
                <button class="close-modal-btn" id="close-activity-modal-btn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form id="create-activity-form">
                    <div class="form-group mb-3">
                        <label class="form-label" for="act-name">Activity Name</label>
                        <input type="text" id="act-name" class="form-control" placeholder="e.g. K-Map Analysis" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label" for="act-subject">Target Subject Course</label>
                        <select id="act-subject" class="form-select" required>
                            <option value="Digital Logic">Digital Logic (Prof. Patil)</option>
                            <option value="Microprocessor">Microprocessor (Prof. Kulkarni)</option>
                            <option value="DSP">DSP (Prof. Shah)</option>
                        </select>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label" for="act-deadline">Evaluation Deadline</label>
                        <input type="date" id="act-deadline" class="form-control" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary-outline" id="btn-cancel-create-act">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Activity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Onboard Faculty -->
    <div class="modal-overlay" id="modal-onboard-faculty">
        <div class="modal-card">
            <div class="modal-header">
                <h3><i class="fa-solid fa-plus text-primary"></i> Onboard New Department Faculty</h3>
                <button class="close-modal-btn" id="close-faculty-modal-btn"><i class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body">
                <form id="onboard-faculty-form">
                    <div class="form-group mb-3">
                        <label class="form-label" for="fac-name">Faculty Name</label>
                        <input type="text" id="fac-name" class="form-control" placeholder="e.g. Prof. R. S. Mane" required>
                    </div>
                    <div class="form-group mb-3">
                        <label class="form-label" for="fac-subject">Assigned Coordinator Subject</label>
                        <input type="text" id="fac-subject" class="form-control" placeholder="e.g. Signals & Systems" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary-outline" id="btn-cancel-onboard">Cancel</button>
                        <button type="submit" class="btn btn-primary">Onboard Faculty</button>
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
