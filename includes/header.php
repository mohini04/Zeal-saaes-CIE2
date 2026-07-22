<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIE 2 | Zeal College of Engineering & Research</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    
    <!-- FontAwesome for Premium Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS (with cache buster) -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
</head>
<body>
    <!-- Header/Navbar -->
    <header class="app-header">
        <div class="header-container">
            <!-- College Branding (Logo + Text) -->
            <a href="index.php" class="brand-link">
                <div class="logo-wrapper">
                    <!-- College Crest Logo Badge -->
                    <div class="logo-badge">
                        <i class="fa-solid fa-graduation-cap"></i>
                    </div>
                </div>
                <div class="brand-text">
                    <span class="society-name">Zeal Education Society's</span>
                    <h1 class="college-name">Zeal College of Engineering & Research, Pune</h1>
                    <span class="dept-name">Department of Electronics & Computer Engineering</span>
                </div>
            </a>

            <!-- Navigation Links -->
            <nav class="nav-menu">
                <a href="index.php#home" class="nav-link">Home</a>
                <a href="index.php#features" class="nav-link">Features</a>
                <a href="index.php#roles" class="nav-link">User Roles</a>
            </nav>

            <!-- Navbar Actions (Clock + Buttons) -->
            <div class="header-actions">
                <!-- Live Clock Widget -->
                <div class="live-clock-widget">
                    <i class="far fa-calendar-alt clock-icon"></i>
                    <span id="live-datetime">-- --- ---- --:--:-- --</span>
                </div>
                
                <a href="index.php#roles" class="btn btn-outline">
                    <i class="fas fa-user-plus btn-icon"></i> Register
                </a>
                <a href="auth/login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt btn-icon"></i> Login
                </a>
            </div>
        </div>
    </header>
