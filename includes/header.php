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
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/cie2_system/assets/css/style.css">
</head>
<body>
    <!-- Header/Navbar -->
    <header class="app-header">
        <div class="header-container">
            <!-- College Branding (Logo + Text) -->
            <a href="/cie2_system/index.php" class="brand-link">
                <div class="logo-wrapper">
                    <!-- Custom Premium SVG College Crest -->
                    <svg viewBox="0 0 100 100" class="logo-svg">
                        <circle cx="50" cy="50" r="46" fill="none" stroke="#3454D1" stroke-width="4"/>
                        <circle cx="50" cy="50" r="40" fill="none" stroke="#6C7FE8" stroke-width="1.5" stroke-dasharray="4 2"/>
                        <path d="M50 20 L25 35 L50 50 L75 35 Z" fill="#3454D1"/>
                        <path d="M30 45 L30 65 C30 75 50 82 50 82 C50 82 70 75 70 65 L70 45 L50 57 Z" fill="#2941A8"/>
                        <path d="M25 35 L25 55" fill="none" stroke="#3454D1" stroke-width="2"/>
                        <circle cx="25" cy="55" r="3" fill="#3454D1"/>
                        <path d="M42 33 L58 33" stroke="#FFF" stroke-width="2"/>
                    </svg>
                </div>
                <div class="brand-text">
                    <span class=" society-name">Zeal Education Society's</span>
                    <h1 class="college-name">Zeal College of Engineering & Research, Pune</h1>
                    <span class="dept-name">Department of Electronics & Computer Engineering</span>
                </div>
            </a>

            <!-- Navigation Links -->
            <nav class="nav-menu">
                <a href="/cie2_system/index.php#home" class="nav-link">Home</a>
                <a href="/cie2_system/index.php#features" class="nav-link">Features</a>
                <a href="/cie2_system/index.php#roles" class="nav-link">User Roles</a>
            </nav>

            <!-- Navbar Actions (Clock + Buttons) -->
            <div class="header-actions">
                <!-- Live Clock Widget -->
                <div class="live-clock-widget">
                    <i class="far fa-calendar-alt clock-icon"></i>
                    <span id="live-datetime">-- --- ---- --:--:-- --</span>
                </div>
                
                <a href="/cie2_system/index.php#roles" class="btn btn-outline">
                    <i class="fas fa-user-plus btn-icon"></i> Register
                </a>
                <a href="/cie2_system/auth/login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt btn-icon"></i> Login
                </a>
            </div>
        </div>
    </header>
