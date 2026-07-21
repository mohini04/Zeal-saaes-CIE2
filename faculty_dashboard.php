<?php
$facultyName = "Prof. Anjali Patil";
$deptName = "Faculty - E&TC";
$collegeName = "Zeal College of Engineering & Research, Pune";
$systemName = "Student Activity Assessment & Evaluation System";

$stats = [
    "totalActivities" => 24,
    "pendingEvaluations" => 7,
    "evaluatedActivities" => 92,
    "assignedStudents" => 58,
    "upcomingDeadlines" => 5,
    "averageScore" => "84%"
];

$activities = [
    ["name" => "Digital Logic K-Map", "subject" => "Digital Logic", "unit" => "Unit 2", "duedate" => "20 Jul", "maxmarks" => 5, "status" => "Active", "action" => "View"],
    ["name" => "Number System", "subject" => "Digital Logic", "unit" => "Unit 1", "duedate" => "10 Jul", "maxmarks" => 5, "status" => "Completed", "action" => "View"],
    ["name" => "Flip-Flop Analysis", "subject" => "Digital Logic", "unit" => "Unit 3", "duedate" => "25 Jul", "maxmarks" => 5, "status" => "Upcoming", "action" => "Edit"]
];

$submissions = [
    ["student" => "Siddhant Deshmukh", "activity" => "K-Map", "date" => "Today", "status" => "On Time", "marks" => "5/5", "action" => "Evaluate"],
    ["student" => "Pooja Sharma", "activity" => "Number System", "date" => "Yesterday", "status" => "On Time", "marks" => "4/5", "action" => "View"],
    ["student" => "Neha Jadhav", "activity" => "Flip-Flop", "date" => "Today", "status" => "Late", "marks" => "Pending", "action" => "Evaluate"]
];

$classPerformance = [
    ["label" => "Average Marks", "value" => "4.2 / 5"],
    ["label" => "Highest Marks", "value" => "5 / 5"],
    ["label" => "Lowest Marks", "value" => "2 / 5"],
    ["label" => "Submission Rate", "value" => "92%"],
    ["label" => "Late Submission", "value" => "8%"]
];

$notifications = [
    ["icon" => "fa-circle", "color" => "text-danger", "text" => "5 submissions pending evaluation"],
    ["icon" => "fa-circle", "color" => "text-warning", "text" => "Unit 3 activity due tomorrow"],
    ["icon" => "fa-circle", "color" => "text-success", "text" => "New submissions received"],
    ["icon" => "fa-circle", "color" => "text-primary", "text" => "Attendance updated"],
    ["icon" => "fa-circle", "color" => "text-analytics", "text" => "Report generation completed"]
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
    <title>Faculty Dashboard | Zeal College</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="faculty-theme">
    
    <!-- Header -->
    <header class="fac-header">
        <div class="fac-header-left">
            <button class="mobile-menu-btn" id="mobile-menu-btn" aria-label="Toggle Menu" style="margin-right: 15px;">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="fac-logo-container">
                <div class="fac-logo"><i class="fa-solid fa-graduation-cap text-primary" style="font-size: 1.5rem; margin-top:6px; margin-left:5px;"></i></div>
                <div>
                    <div class="fac-college-name"><?php echo htmlspecialchars($collegeName); ?></div>
                    <div class="fac-system-name"><?php echo htmlspecialchars($systemName); ?></div>
                </div>
            </div>
        </div>
        <div class="fac-header-right">
            <div class="fac-header-date">
                <i class="fa-regular fa-calendar-days"></i>
                <span id="current-date">July 21, 2026</span>
            </div>
            <div class="fac-notif-icon">
                <i class="fa-regular fa-bell"></i>
                <span class="fac-notif-badge">5</span>
            </div>
            <div class="fac-profile">
                <div class="fac-avatar"><i class="fa-regular fa-user"></i></div>
                <div class="fac-profile-info">
                    <div class="fac-profile-name"><?php echo htmlspecialchars($facultyName); ?></div>
                    <div class="fac-profile-role"><?php echo htmlspecialchars($deptName); ?></div>
                </div>
                <i class="fa-solid fa-right-from-bracket" title="Logout" style="margin-left: 10px;"></i>
            </div>
        </div>
    </header>

    <div class="fac-layout">
        <!-- Sidebar -->
        <aside class="fac-sidebar sidebar">
            <nav class="fac-nav">
                <a href="#" class="fac-nav-item active"><i class="fa-solid fa-house"></i> Dashboard</a>
                <a href="#" class="fac-nav-item"><i class="fa-solid fa-plus"></i> Create Activity</a>
                <a href="#" class="fac-nav-item"><i class="fa-solid fa-folder-open"></i> Manage Activities</a>
                <a href="#" class="fac-nav-item"><i class="fa-solid fa-inbox"></i> View Submissions</a>
                <a href="#" class="fac-nav-item"><i class="fa-solid fa-check-double"></i> Evaluate Activities</a>
                <a href="#" class="fac-nav-item"><i class="fa-solid fa-user-graduate"></i> Students</a>
                <a href="#" class="fac-nav-item"><i class="fa-solid fa-chart-simple"></i> Performance Analytics</a>
                <a href="#" class="fac-nav-item"><i class="fa-solid fa-arrow-trend-up"></i> Attendance Overview</a>
                <a href="#" class="fac-nav-item"><i class="fa-solid fa-file-alt"></i> Reports</a>
                <a href="#" class="fac-nav-item"><i class="fa-regular fa-bell"></i> Notifications <span class="fac-nav-badge">5</span></a>
                <a href="#" class="fac-nav-item"><i class="fa-solid fa-gear"></i> Settings</a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="fac-main">
            
            <div class="fac-grid">
                <!-- Left Content (Main Area) -->
                <div class="fac-col-left">
                    
                    <!-- 6 Summary Cards -->
                    <div class="fac-summary-cards">
                        <div class="fac-summary-card">
                            <div class="fac-sc-icon bg-primary"><i class="fa-solid fa-book"></i></div>
                            <div class="fac-sc-data">
                                <h3><?php echo $stats['totalActivities']; ?></h3>
                                <span>Total Activities</span>
                            </div>
                        </div>
                        <div class="fac-summary-card">
                            <div class="fac-sc-icon bg-warning"><i class="fa-solid fa-hourglass-half"></i></div>
                            <div class="fac-sc-data">
                                <h3><?php echo $stats['pendingEvaluations']; ?></h3>
                                <span>Pending Evaluations</span>
                            </div>
                        </div>
                        <div class="fac-summary-card">
                            <div class="fac-sc-icon bg-success"><i class="fa-solid fa-check-circle"></i></div>
                            <div class="fac-sc-data">
                                <h3><?php echo $stats['evaluatedActivities']; ?></h3>
                                <span>Evaluated Activities</span>
                            </div>
                        </div>
                        <div class="fac-summary-card">
                            <div class="fac-sc-icon bg-primary"><i class="fa-solid fa-users"></i></div>
                            <div class="fac-sc-data">
                                <h3><?php echo $stats['assignedStudents']; ?></h3>
                                <span>Assigned Students</span>
                            </div>
                        </div>
                        <div class="fac-summary-card">
                            <div class="fac-sc-icon bg-danger"><i class="fa-regular fa-calendar-xmark"></i></div>
                            <div class="fac-sc-data">
                                <h3><?php echo $stats['upcomingDeadlines']; ?></h3>
                                <span>Upcoming Deadlines</span>
                            </div>
                        </div>
                        <div class="fac-summary-card">
                            <div class="fac-sc-icon bg-analytics"><i class="fa-solid fa-star"></i></div>
                            <div class="fac-sc-data">
                                <h3><?php echo $stats['averageScore']; ?></h3>
                                <span>Average Student Score</span>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Management -->
                    <div class="fac-card">
                        <div class="fac-card-header">
                            <h2>Activity Management</h2>
                            <div>
                                <button class="fac-btn-primary fac-btn-sm"><i class="fa-solid fa-plus"></i> Create Activity</button>
                                <button class="fac-btn-outline fac-btn-sm"><i class="fa-regular fa-file-alt"></i> View All Activities</button>
                            </div>
                        </div>
                        <div class="fac-table-wrapper">
                            <table class="fac-table">
                                <thead>
                                    <tr>
                                        <th>Activity</th>
                                        <th>Subject</th>
                                        <th>Unit</th>
                                        <th>Due Date</th>
                                        <th>Max Marks</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $act): 
                                        $pillClass = 'pill-active';
                                        if($act['status'] === 'Completed') $pillClass = 'pill-completed';
                                        if($act['status'] === 'Upcoming') $pillClass = 'pill-upcoming';
                                    ?>
                                    <tr>
                                        <td class="fw-600 text-dark"><?php echo htmlspecialchars($act['name']); ?></td>
                                        <td><?php echo htmlspecialchars($act['subject']); ?></td>
                                        <td><?php echo htmlspecialchars($act['unit']); ?></td>
                                        <td><?php echo htmlspecialchars($act['duedate']); ?></td>
                                        <td><?php echo $act['maxmarks']; ?></td>
                                        <td><span class="fac-pill <?php echo $pillClass; ?>"><?php echo htmlspecialchars($act['status']); ?></span></td>
                                        <td><button class="fac-btn-outline fac-btn-sm"><?php echo htmlspecialchars($act['action']); ?></button></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Submissions & Class Performance -->
                    <div class="fac-row-split">
                        <!-- Recent Submissions -->
                        <div class="fac-card">
                            <div class="fac-card-header">
                                <h2>Recent Student Submissions</h2>
                            </div>
                            <div class="fac-table-wrapper">
                                <table class="fac-table">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Activity</th>
                                            <th>Submitted On</th>
                                            <th>Status</th>
                                            <th>Marks</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($submissions as $sub): ?>
                                        <tr>
                                            <td class="fw-600 text-dark"><?php echo htmlspecialchars($sub['student']); ?></td>
                                            <td><?php echo htmlspecialchars($sub['activity']); ?></td>
                                            <td><?php echo htmlspecialchars($sub['date']); ?></td>
                                            <td class="<?php echo $sub['status'] === 'Late' ? 'text-danger' : 'text-success'; ?> fw-600"><?php echo htmlspecialchars($sub['status']); ?></td>
                                            <td class="fw-600"><?php echo htmlspecialchars($sub['marks']); ?></td>
                                            <td><button class="fac-btn-primary fac-btn-sm"><?php echo htmlspecialchars($sub['action']); ?></button></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Class Performance -->
                        <div class="fac-card">
                            <div class="fac-card-header">
                                <h2>Class Performance</h2>
                            </div>
                            <div class="fac-card-body">
                                <div class="fac-metric-list">
                                    <?php foreach ($classPerformance as $cp): ?>
                                    <div class="fac-metric-item">
                                        <span class="lbl"><?php echo htmlspecialchars($cp['label']); ?></span>
                                        <span class="val"><?php echo htmlspecialchars($cp['value']); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Analytics Charts (Grid of 4) -->
                    <div class="fac-card">
                        <div class="fac-card-header">
                            <h2>Student Performance Analytics</h2>
                        </div>
                        <div class="fac-card-body fac-charts-grid">
                            <div class="fac-chart-box">
                                <h4>Unit-wise Average Marks</h4>
                                <canvas id="chartUnitMarks" height="180"></canvas>
                            </div>
                            <div class="fac-chart-box">
                                <h4>Submission Status</h4>
                                <canvas id="chartSubStatus" height="180"></canvas>
                            </div>
                            <div class="fac-chart-box">
                                <h4>Weekly Submission Trend</h4>
                                <canvas id="chartWeeklyTrend" height="180"></canvas>
                            </div>
                            <div class="fac-chart-box">
                                <h4>Marks Distribution</h4>
                                <canvas id="chartMarksDist" height="180"></canvas>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Content (Widgets) -->
                <div class="fac-col-right">
                    
                    <!-- Quick Actions -->
                    <div class="fac-card">
                        <div class="fac-card-header">
                            <h2>Quick Actions</h2>
                        </div>
                        <div class="fac-card-body fac-qa-grid">
                            <button class="fac-qa-btn"><i class="fa-solid fa-plus"></i> Create Activity</button>
                            <button class="fac-qa-btn"><i class="fa-solid fa-upload"></i> Publish Marks</button>
                            <button class="fac-qa-btn"><i class="fa-solid fa-paper-plane"></i> Send Notification</button>
                            <button class="fac-qa-btn"><i class="fa-solid fa-download"></i> Download Reports</button>
                            <button class="fac-qa-btn" style="grid-column: span 2;"><i class="fa-solid fa-chart-line"></i> View Analytics</button>
                        </div>
                    </div>

                    <!-- Notifications Panel -->
                    <div class="fac-card">
                        <div class="fac-card-header">
                            <h2>Notifications Panel</h2>
                        </div>
                        <div>
                            <?php foreach ($notifications as $n): ?>
                            <div class="fac-list-item">
                                <i class="fa-solid <?php echo $n['icon']; ?> <?php echo $n['color']; ?> fac-list-icon"></i>
                                <span class="fac-list-text"><?php echo htmlspecialchars($n['text']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Upcoming Deadlines -->
                    <div class="fac-card">
                        <div class="fac-card-header">
                            <h2>Upcoming Deadlines</h2>
                        </div>
                        <div>
                            <?php foreach ($deadlines as $d): ?>
                            <div class="fac-list-item">
                                <span class="fac-list-time fw-600" style="width: 50px;"><?php echo htmlspecialchars($d['date']); ?></span>
                                <span class="fac-list-text fw-600"><?php echo htmlspecialchars($d['activity']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Report Generation -->
                    <div class="fac-card">
                        <div class="fac-card-header">
                            <h2>Report Generation</h2>
                        </div>
                        <div>
                            <button class="fac-report-btn">
                                <i class="fa-solid fa-file-pdf text-danger"></i> <span>Activity Report (PDF)</span>
                            </button>
                            <button class="fac-report-btn">
                                <i class="fa-solid fa-chart-bar text-primary"></i> <span>Student Performance Report</span>
                            </button>
                            <button class="fac-report-btn">
                                <i class="fa-solid fa-clipboard-check text-success"></i> <span>Evaluation Report</span>
                            </button>
                            <button class="fac-report-btn">
                                <i class="fa-solid fa-user-clock text-warning"></i> <span>Attendance Report</span>
                            </button>
                            <button class="fac-report-btn">
                                <i class="fa-solid fa-file-excel text-success"></i> <span>Export Excel</span>
                            </button>
                        </div>
                    </div>

                </div>
            </div>

            <footer class="fac-footer">
                © 2026 Zeal College of Engineering & Research, Pune<br>
                Student Activity Assessment & Evaluation System
            </footer>

        </main>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>
