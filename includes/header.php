<?php
$basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($basePath === '' || $basePath === '/') {
    $basePath = '';
}
$cssPath = $basePath . '/assets/css/style.css';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOD Dashboard | Zeal College of Engineering & Research</title>
    <link rel="stylesheet" href="<?php echo $cssPath; ?>">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="page-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-icon">Z</div>
                <div>
                    <h2>Zeal CIE2</h2>
                    <p>Department Portal</p>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a class="nav-item active" href="hod_dashboard.php">🏠 Dashboard</a>
                <a class="nav-item" href="faculty_management.php">👨‍🏫 Faculty Management</a>
                <a class="nav-item" href="student_performance.php">👨‍🎓 Student Performance</a>
                <a class="nav-item" href="subject_management.php">📚 Subject & Course Management</a>
                <a class="nav-item" href="activity_monitoring.php">📋 Activity Monitoring</a>
                <a class="nav-item" href="approval_center.php">✅ Approval Center</a>
                <a class="nav-item" href="reports.php">📈 Department Analytics</a>
                <a class="nav-item" href="reports.php">📄 Reports</a>
                <a class="nav-item" href="notifications.php">🔔 Notifications</a>
                <a class="nav-item" href="settings.php">⚙ Settings</a>
            </nav>
        </aside>

        <main class="main-content" id="main-content">
            <header class="topbar">
                <div>
                    <p class="eyebrow">Head of Department</p>
                    <h1>Welcome, Dr. ABC</h1>
                    <p>Department Activity Assessment & Evaluation Dashboard</p>
                </div>
                <div class="topbar-actions">
                    <span class="pill">Date: 18 Jul 2026</span>
                    <span class="pill">8 Notifications</span>
                    <a class="topbar-link" href="profile.php">Profile</a>
                    <a class="topbar-link" href="auth/logout.php">Logout</a>
                </div>
            </header>
