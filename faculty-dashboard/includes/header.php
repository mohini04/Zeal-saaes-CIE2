<?php
// includes/header.php - Left Sidebar Layout Header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Faculty Activity Portal' : 'Faculty Activity Portal'; ?></title>
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="app-container">

        <!-- LEFT SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="brand-logo">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <span>Faculty Hub</span>
                </a>
            </div>

            <div class="sidebar-menu">
                <div class="menu-label">Navigation</div>
                <a href="index.php" class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Dashboard & Matrix</span>
                </a>

                <a href="create_activity.php" class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'create_activity.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-circle-plus" style="color: #6366f1;"></i>
                    <span>Create Activity</span>
                </a>

                <a href="recreate_activity.php" class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'recreate_activity.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-rotate-right" style="color: #f59e0b;"></i>
                    <span>Recreate and Manage Activities</span>
                </a>

                <div class="menu-label">Quick Activity Pages</div>
                <a href="quiz.php?id=1" class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'quiz.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-clipboard-question" style="color: #6366f1;"></i>
                    <span>Quiz Activity</span>
                </a>
                <a href="poster_making.php?id=2" class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'poster_making.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-palette" style="color: #ec4899;"></i>
                    <span>Poster Making</span>
                </a>
                <a href="ppt.php?id=3" class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'ppt.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-powerpoint" style="color: #f59e0b;"></i>
                    <span>PPT Presentation</span>
                </a>
                <a href="case_study.php?id=4" class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'case_study.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-magnifying-glass-chart" style="color: #10b981;"></i>
                    <span>Case Study</span>
                </a>
                <a href="gd.php?id=5" class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'gd.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-comments" style="color: #8b5cf6;"></i>
                    <span>Group Discussion</span>
                </a>
                <a href="mini_project.php?id=6" class="sidebar-link <?php echo (basename($_SERVER['PHP_SELF']) == 'mini_project.php') ? 'active' : ''; ?>">
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

        <!-- MAIN CONTENT AREA -->
        <div class="content-wrapper">
            <header class="top-navbar">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <h3 style="font-size: 1.1rem; color: var(--text-primary);">
                        <?php echo isset($page_title) ? $page_title : 'Faculty Hub'; ?>
                    </h3>
                </div>

                <div style="display: flex; align-items: center; gap: 1rem;">
                    <a href="create_activity.php" class="btn btn-primary" style="font-size: 0.85rem;">
                        <i class="fa-solid fa-plus"></i> Create Activity
                    </a>
                    <a href="recreate_activity.php" class="btn btn-outline" style="font-size: 0.85rem; border-color: #f59e0b; color: #f59e0b;">
                        <i class="fa-solid fa-rotate-right"></i> Recreate & Manage
                    </a>
                </div>
            </header>

            <main class="main-content">
