<?php
/**
 * index.php
 * SAAES — Landing Page (Entry point)
 * Traditional Academic Theme (Zeal College UI)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connect database safely with fallback
$pdo = null;
try {
    $pdo = require __DIR__ . '/config/db.php';
} catch (Exception $e) {
    $pdo = null;
}

// Check logged in user state
$isLoggedIn = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$userRole   = $_SESSION['role'] ?? '';

$dashUrl = 'auth/login.php';
if ($isLoggedIn) {
    switch (strtolower($userRole)) {
        case 'admin':   $dashUrl = 'auth/admin_dashboard.php'; break;
        case 'faculty': $dashUrl = 'faculty_dashboard.php'; break;
        case 'hod':     $dashUrl = 'hod_dashboard.php'; break;
        case 'gfm':     $dashUrl = 'gfm_dashboard.php'; break;
        case 'student': $dashUrl = 'student_dashboard.php'; break;
        case 'parent':  $dashUrl = 'parent_dashboard.php'; break;
        default:        $dashUrl = 'auth/login.php'; break;
    }
}

// Fetch dynamic system metrics for statistics section
$statsData = ['users' => 0, 'activities' => 0, 'submissions' => 0, 'units' => 6];
if ($pdo) {
    try {
        $statsData['users'] = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $statsData['activities'] = (int)$pdo->query("SELECT COUNT(*) FROM activities")->fetchColumn();
        $statsData['submissions'] = (int)$pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
    } catch (Exception $e) {
        $statsData['users'] = 120; $statsData['activities'] = 24; $statsData['submissions'] = 310;
    }
}

// Fetch dynamic ticker announcements from database
$tickerNotices = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT title, unit, subject, due_date FROM activities ORDER BY created_at DESC LIMIT 5");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $subj = !empty($row['subject']) ? htmlspecialchars($row['subject']) . " - " : "";
            $unit = !empty($row['unit']) ? "Unit " . htmlspecialchars($row['unit']) . " - " : "";
            $due  = !empty($row['due_date']) ? " (Due: " . date("d M Y", strtotime($row['due_date'])) . ")" : "";
            $tickerNotices[] = $subj . $unit . htmlspecialchars($row['title']) . $due;
        }
    } catch (Exception $ex) {}
}

if (empty($tickerNotices)) {
    $tickerNotices = [
        "Unit 2 Activity last date 20 May 2025.",
        "Final Activity Marksheet will be available after completion of all 6 units.",
        "Unit 3 Activity for Data Structures is now live.",
        "Please ensure all submissions are uploaded in PDF format."
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIE 2 | Zeal College of Engineering</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #2563EB;
            --primary-hover: #1D4ED8;
            --navy-dark: #0B2A6B;
            --navy-footer: #081638;
            --text-dark: #1E293B;
            --text-muted: #64748B;
            --bg-body: #F8FAFC;
            --bg-white: #FFFFFF;
            --border-light: #E2E8F0;
            
            --font-head: 'Plus Jakarta Sans', sans-serif;
            --font-body: 'Inter', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: var(--font-body);
            background-color: var(--bg-body);
            color: var(--text-dark);
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }

        /* ================= NAVBAR ================= */
        .navbar {
            background-color: var(--bg-white);
            height: 80px;
            padding: 0 3%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        .logo-text {
            display: flex;
            flex-direction: column;
        }

        .logo-top { font-size: 0.75rem; color: var(--text-muted); font-weight: 500; }
        .logo-main { font-family: var(--font-head); font-size: 1.15rem; font-weight: 800; color: var(--navy-dark); line-height: 1.2;}
        .logo-sub { font-size: 0.8rem; color: var(--text-muted); }

        .nav-links {
            display: flex;
            gap: 2.5rem;
            font-family: var(--font-head);
            font-weight: 600;
            font-size: 0.95rem;
        }

        .nav-links a {
            color: var(--text-dark);
            transition: color 0.3s;
            padding-bottom: 5px;
            border-bottom: 2px solid transparent;
        }
        
        .nav-links a:hover, .nav-links a.active {
            color: var(--primary-blue);
            border-bottom-color: var(--primary-blue);
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .datetime-display {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--navy-dark);
            font-weight: 600;
            font-family: var(--font-head);
        }
        .datetime-display i { color: var(--primary-blue); font-size: 1.2rem; }
        .dt-text { display: flex; flex-direction: column; line-height: 1.2;}

        .btn {
            font-family: var(--font-head);
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.6rem 1.5rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            cursor: pointer;
        }

        .btn-outline {
            border: 1.5px solid var(--primary-blue);
            color: var(--primary-blue);
            background: transparent;
        }
        .btn-outline:hover { background: rgba(37, 99, 235, 0.05); }

        .btn-primary {
            background: var(--primary-blue);
            color: #fff;
            border: 1.5px solid var(--primary-blue);
        }
        .btn-primary:hover { background: var(--primary-hover); border-color: var(--primary-hover); }

        /* ================= HERO SECTION ================= */
        .hero {
            position: relative;
            height: calc(100vh - 80px);
            min-height: 600px;
            /* Updated Path as requested */
            background-image: url('assets/images/college_building.jpg'); 
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            display: flex;
            align-items: center;
            padding-left: 5%;
        }

        .hero-overlay {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(90deg, rgba(11,42,107,0.95) 0%, rgba(11,42,107,0.7) 40%, rgba(0,0,0,0.1) 100%);
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 600px;
            color: #fff;
        }

        .hero-title {
            font-family: var(--font-head);
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
        }
        .hero-title span { color: #60A5FA; }

        .hero-subtitle {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #E2E8F0;
        }

        /* ================= TICKER BOARD ================= */
        .notice-board {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 40px;
            background: var(--navy-dark);
            z-index: 10;
            display: flex;
            align-items: center;
        }

        .notice-label {
            background: var(--navy-dark);
            color: #FBBF24;
            font-family: var(--font-head);
            font-weight: 700;
            font-size: 0.9rem;
            padding: 0 2rem;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 2;
            white-space: nowrap;
        }
        
        .notice-label::after {
            content: ''; position: absolute; top: 0; right: -20px; width: 20px; height: 100%;
            background: linear-gradient(90deg, var(--navy-dark), transparent);
        }

        .notice-scroll {
            flex: 1;
            overflow: hidden;
            white-space: nowrap;
            color: #fff;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }

        .ticker-text {
            display: inline-block;
            animation: scroll-left 35s linear infinite;
        }

        .ticker-item { margin-right: 3rem; }
        .ticker-dot { color: #FBBF24; margin-right: 0.5rem; font-size: 0.5rem; vertical-align: middle; }

        @keyframes scroll-left {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }

        /* ================= SECTION STYLES ================= */
        .section-wrapper {
            padding: 5rem 5%;
            background: var(--bg-white);
        }
        .section-wrapper.alt-bg { background: var(--bg-body); }

        .section-title {
            font-family: var(--font-head);
            font-size: 2rem;
            font-weight: 800;
            color: var(--navy-dark);
            text-align: center;
            margin-bottom: 3.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        .section-title::before, .section-title::after {
            content: '';
            display: block;
            width: 40px;
            height: 3px;
            background: var(--primary-blue);
            border-radius: 2px;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* ================= CARDS ================= */
        .feature-card {
            background: var(--bg-white);
            border-radius: 16px;
            padding: 2rem 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.04);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(0,0,0,0.02);
        }
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }

        .f-icon-wrap {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
        }
        
        .fi-1 { background: #EEF2FF; color: #3B82F6; }
        .fi-2 { background: #ECFDF5; color: #10B981; }
        .fi-3 { background: #F5F3FF; color: #8B5CF6; }
        .fi-4 { background: #FFFBEB; color: #F59E0B; }
        .fi-5 { background: #EFF6FF; color: #0EA5E9; }

        .f-title {
            font-family: var(--font-head);
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--navy-dark);
            margin-bottom: 0.75rem;
        }
        .f-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* Role Cards */
        .role-icon-wrap {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            color: #fff;
            margin-bottom: 1.5rem;
        }
        
        .ri-1 { background: #2563EB; } 
        .ri-2 { background: #10B981; } 
        .ri-3 { background: #8B5CF6; } 
        .ri-4 { background: #F59E0B; } 
        .ri-5 { background: #0EA5E9; } 
        .ri-6 { background: #EF4444; } 

        /* ================= STATS GRID ================= */
        .stats-grid { 
            display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 1.5rem; max-width: 1400px; margin: 0 auto; 
        }
        .stat-block { 
            padding: 2rem; border: 1px solid var(--border-light); 
            border-radius: 16px; background: var(--bg-white); text-align: center; 
            box-shadow: 0 4px 20px rgba(0,0,0,0.04); transition: transform 0.3s;
        }
        .stat-block:hover { transform: translateY(-5px); }
        .stat-val { font-family: var(--font-head); font-size: 3rem; font-weight: 800; color: var(--primary-blue); line-height: 1; margin-bottom: 0.5rem; }
        .stat-label { font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;}

        /* ================= FOOTER ================= */
        .main-footer {
            background-color: var(--navy-footer);
            color: #E2E8F0;
            padding: 1.5rem 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .footer-info {
            display: flex;
            gap: 2rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .f-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .f-item i { color: #60A5FA; font-size: 1rem;}

        /* ANIMATIONS */
        .reveal-scroll { opacity: 0; transform: translateY(20px); transition: all 0.6s ease-out; }
        .reveal-scroll.in-view { opacity: 1; transform: translateY(0); }

        @media (max-width: 1024px) {
            .nav-links { display: none; }
            .hero-title { font-size: 2.8rem; }
        }
        @media (max-width: 768px) {
            .navbar { height: auto; padding: 15px 5%; flex-wrap: wrap; gap: 15px;}
            .datetime-display { display: none; }
            .hero { padding-left: 5%; padding-right: 5%; min-height: 500px;}
            .hero-title { font-size: 2.2rem; }
            .notice-label { padding: 0 1rem; font-size: 0.8rem;}
            .main-footer { flex-direction: column; text-align: center; justify-content: center; }
            .footer-info { justify-content: center; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <div class="logo-container">
            <img src="logo.jpg" alt="ZCOER Logo" class="logo-img" onerror="this.style.display='none'">
            <div class="logo-text">
                <span class="logo-top">Zeal Education Society's</span>
                <span class="logo-main">Zeal College of Engineering & Research, Pune</span>
                <span class="logo-sub">Department of Electronics & Computer Engineering</span>
            </div>
        </div>

        <div class="nav-links">
            <a href="#home" class="active">Home</a>
            <a href="#features">Features</a>
            <a href="#stats">Statistics</a>
            <a href="#roles">User Roles</a>
        </div>

        <div class="nav-actions">
            <div class="datetime-display">
                <i class="fa-regular fa-calendar"></i>
                <div class="dt-text">
                    <span id="currentDate"></span>
                    <span id="currentTime" style="color: var(--text-muted); font-size: 0.75rem; font-weight: 500;"></span>
                </div>
            </div>
            
            <?php if ($isLoggedIn): ?>
                <a href="<?= htmlspecialchars($dashUrl) ?>" class="btn btn-primary">
                    Dashboard <i class="fa-solid fa-arrow-right"></i>
                </a>
            <?php else: ?>
                <a href="auth/register.php" class="btn btn-outline">
                    <i class="fa-solid fa-user-plus"></i> Register
                </a>
                <a href="auth/login.php" class="btn btn-primary">
                    <i class="fa-solid fa-right-to-bracket"></i> Login
                </a>
            <?php endif; ?>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero" id="home">
        <div class="hero-overlay"></div>
        <div class="hero-content">
            <h1 class="hero-title reveal-scroll">
                Student Activity<br>
                Assessment & Evaluation<br>
                System <span>(CIE 2)</span>
            </h1>
            <p class="hero-subtitle reveal-scroll">
                A smart platform to manage activities, submit assignments, evaluate performance and generate final marksheets efficiently and transparently.
            </p>
        </div>

        <!-- TICKER BOARD -->
        <div class="notice-board">
            <div class="notice-label">
                <i class="fa-solid fa-bullhorn"></i> Notice Board
            </div>
            <div class="notice-scroll">
                <div class="ticker-text">
                    <?php 
                    $formattedNotices = array_map(function($notice) {
                        return "<span class='ticker-item'><i class='fa-solid fa-circle ticker-dot'></i> " . $notice . "</span>";
                    }, $tickerNotices);
                    $tickerString = implode("", $formattedNotices);
                    echo $tickerString . $tickerString; 
                    ?>
                </div>
            </div>
        </div>
    </section>

    <!-- KEY FEATURES SECTION -->
    <section class="section-wrapper" id="features">
        <h2 class="section-title reveal-scroll">Key Features</h2>
        
        <div class="grid-container reveal-scroll">
            <div class="feature-card">
                <div class="f-icon-wrap fi-1"><i class="fa-solid fa-list-check"></i></div>
                <h3 class="f-title">Activity Management</h3>
                <p class="f-desc">Faculty can create and manage unit-wise activities with due dates.</p>
            </div>
            
            <div class="feature-card">
                <div class="f-icon-wrap fi-2"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                <h3 class="f-title">Easy Submission</h3>
                <p class="f-desc">Students can upload PDF, JPG or PNG files in just a few clicks.</p>
            </div>
            
            <div class="feature-card">
                <div class="f-icon-wrap fi-3"><i class="fa-regular fa-clock"></i></div>
                <h3 class="f-title">Automatic Evaluation</h3>
                <p class="f-desc">Marks are allocated automatically based on submission time.</p>
            </div>
            
            <div class="feature-card">
                <div class="f-icon-wrap fi-4"><i class="fa-solid fa-chart-line"></i></div>
                <h3 class="f-title">Progress Tracking</h3>
                <p class="f-desc">Students, Parents and Faculty can track performance and view results.</p>
            </div>
            
            <div class="feature-card">
                <div class="f-icon-wrap fi-5"><i class="fa-solid fa-file-invoice"></i></div>
                <h3 class="f-title">Transparent Marksheets</h3>
                <p class="f-desc">Final marksheets are generated automatically once all units are complete.</p>
            </div>
        </div>
    </section>

    <!-- STATISTICS SECTION -->
    <section class="section-wrapper alt-bg" id="stats">
        <h2 class="section-title reveal-scroll">System Statistics</h2>
        
        <div class="stats-grid reveal-scroll">
            <div class="stat-block">
                <div class="stat-val" data-target="<?= (int)max($statsData['users'], 1) ?>">0</div>
                <div class="stat-label">Registered Users</div>
            </div>
            <div class="stat-block">
                <div class="stat-val" data-target="<?= (int)max($statsData['activities'], 1) ?>">0</div>
                <div class="stat-label">Active Assignments</div>
            </div>
            <div class="stat-block">
                <div class="stat-val" data-target="<?= (int)max($statsData['submissions'], 1) ?>">0</div>
                <div class="stat-label">Student Submissions</div>
            </div>
            <div class="stat-block">
                <div class="stat-val" data-target="6">0</div>
                <div class="stat-label">Total Units</div>
            </div>
        </div>
    </section>

    <!-- USER ROLES SECTION -->
    <section class="section-wrapper" id="roles">
        <h2 class="section-title reveal-scroll">User Roles</h2>
        
        <div class="grid-container reveal-scroll">
            <a href="auth/login.php?role=student" class="feature-card" style="text-decoration: none;">
                <div class="role-icon-wrap ri-1"><i class="fa-solid fa-user-graduate"></i></div>
                <h3 class="f-title">Student</h3>
                <p class="f-desc">View activities, submit assignments and track performance.</p>
            </a>
            
            <a href="auth/login.php?role=faculty" class="feature-card" style="text-decoration: none;">
                <div class="role-icon-wrap ri-2"><i class="fa-solid fa-chalkboard-user"></i></div>
                <h3 class="f-title">Faculty</h3>
                <p class="f-desc">Create activities, evaluate submissions and generate reports.</p>
            </a>
            
            <a href="auth/login.php?role=parent" class="feature-card" style="text-decoration: none;">
                <div class="role-icon-wrap ri-3"><i class="fa-solid fa-users"></i></div>
                <h3 class="f-title">Parent</h3>
                <p class="f-desc">Monitor student progress, marks and pending activities.</p>
            </a>
            
            <a href="auth/login.php?role=admin" class="feature-card" style="text-decoration: none;">
                <div class="role-icon-wrap ri-4"><i class="fa-solid fa-user-shield"></i></div>
                <h3 class="f-title">Admin</h3>
                <p class="f-desc">Manage users, subjects, activities and system settings.</p>
            </a>
            
            <a href="auth/login.php?role=hod" class="feature-card" style="text-decoration: none;">
                <div class="role-icon-wrap ri-5"><i class="fa-solid fa-user-tie"></i></div>
                <h3 class="f-title">HOD</h3>
                <p class="f-desc">Oversee department activities and performance.</p>
            </a>
            
            <a href="auth/login.php?role=gfm" class="feature-card" style="text-decoration: none;">
                <div class="role-icon-wrap ri-6"><i class="fa-solid fa-users-gear"></i></div>
                <h3 class="f-title">GFM</h3>
                <p class="f-desc">Monitor student progress and academic data.</p>
            </a>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="main-footer">
        <div class="footer-info">
            <div class="f-item">
                <i class="fa-solid fa-location-dot"></i>
                <span>Narhe, Pune - 411041, Maharashtra, India</span>
            </div>
            <div class="f-item">
                <i class="fa-solid fa-phone"></i>
                <span>755866663</span>
            </div>
            <div class="f-item">
                <i class="fa-solid fa-envelope"></i>
                <span>zcoer@zealeducation.com</span>
            </div>
        </div>
        <div>
            &copy; <?= date('Y') ?> Zeal College of Engineering & Research, Pune. All Rights Reserved.
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Update Live Date & Time in Navbar
        function updateDateTime() {
            const dateEl = document.getElementById('currentDate');
            const timeEl = document.getElementById('currentTime');
            if(!dateEl || !timeEl) return;

            const now = new Date();
            
            const optionsDate = { day: '2-digit', month: 'short', year: 'numeric' };
            dateEl.textContent = now.toLocaleDateString('en-GB', optionsDate);
            
            const optionsTime = { hour: '2-digit', minute: '2-digit', hour12: true };
            timeEl.textContent = now.toLocaleTimeString('en-US', optionsTime);
        }
        
        setInterval(updateDateTime, 1000);
        updateDateTime();

        // Smooth Scroll for Nav Links
        document.querySelectorAll('.nav-links a').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const targetId = this.getAttribute('href');
                if(targetId.startsWith('#')) {
                    e.preventDefault();
                    document.querySelectorAll('.nav-links a').forEach(a => a.classList.remove('active'));
                    this.classList.add('active');
                    
                    const targetSection = document.querySelector(targetId);
                    if(targetSection) {
                        const headerOffset = 80;
                        const elementPosition = targetSection.getBoundingClientRect().top;
                        const offsetPosition = elementPosition + window.pageYOffset - headerOffset;
                    
                        window.scrollTo({
                            top: offsetPosition,
                            behavior: "smooth"
                        });
                    }
                }
            });
        });

        // Intersection Observer for scroll animations and counting numbers
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("in-view");
                    
                    // Trigger number counter if it's the stats block
                    if (entry.target.classList.contains('stats-grid')) {
                        document.querySelectorAll('.stat-val').forEach(el => {
                            const target = parseInt(el.getAttribute('data-target'));
                            if (el.innerText === "0") {
                                animateValue(el, 0, target, 1500);
                            }
                        });
                    }
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        document.querySelectorAll(".reveal-scroll").forEach(el => observer.observe(el));

        function animateValue(obj, start, end, duration) {
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const ease = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
                obj.innerHTML = Math.floor(ease * (end - start) + start) + (end > 10 ? '+' : '');
                if (progress < 1) window.requestAnimationFrame(step);
            };
            window.requestAnimationFrame(step);
        }
    </script>
</body>
</html>