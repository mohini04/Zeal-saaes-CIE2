<?php
/**
 * index.php
 * SAAES — Landing Page (Entry point)
 * Premium UI with College-Appropriate Wording
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
$userName   = $_SESSION['user_name'] ?? 'User';

$dashUrl = 'auth/login.php';
if ($isLoggedIn) {
    switch ($userRole) {
        case 'Admin':   $dashUrl = 'auth/admin_dashboard.php'; break;
        case 'Faculty': $dashUrl = 'faculty_dashboard.php'; break;
        case 'HOD':     $dashUrl = 'hod_dashboard.php'; break;
        case 'GFM':     $dashUrl = 'gfm_dashboard.php'; break;
        case 'Student': $dashUrl = 'student_dashboard.php'; break;
        case 'Parent':  $dashUrl = 'parent_dashboard.php'; break;
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
        $rows = $stmt->fetchAll();
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
        "Notice // Unit 2 Activity Deadline Confirmed.",
        "Notice // Final Evaluation Results Pending.",
        "Notice // Data Structures Activity is now live.",
        "Notice // Please upload assignments in PDF format only."
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SAAES Student Activity System">
    <title>SAAES // Zeal College of Engineering</title>

    <!-- Professional Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=JetBrains+Mono:wght@100;400;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --bg-base: #ffffff;
            --bg-panel: #fcfcfd;
            --text-dark: #0f172a;
            --text-tech: #475569;
            --text-light: #94a3b8;
            
            --accent-main: #7c3aed; /* Deep electric purple */
            --accent-glow: #a855f7;
            --accent-border: rgba(124, 58, 237, 0.25);
            
            --grid-size: 40px;
            --border-harsh: 1px solid var(--accent-main);
            
            --font-head: 'Space Grotesk', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
            --font-body: 'Inter', sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--bg-base);
            /* Architectural Blueprint Grid */
            background-image: 
                linear-gradient(rgba(124, 58, 237, 0.08) 1px, transparent 1px),
                linear-gradient(90deg, rgba(124, 58, 237, 0.08) 1px, transparent 1px);
            background-size: var(--grid-size) var(--grid-size);
            background-position: center center;
            color: var(--text-dark);
            font-family: var(--font-body);
            overflow-x: hidden;
            
            /* PIXELATED PURPLE CUSTOM CURSOR */
            cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32' shape-rendering='crispEdges'%3E%3Cpath d='M4 4v20l5-5 4 8 4-2-4-8h8L4 4z' fill='%237c3aed' stroke='white' stroke-width='2'/%3E%3C/svg%3E") 4 4, auto;
            -webkit-font-smoothing: antialiased;
        }

        /* PIXELATED HOVER CURSOR */
        a, button, input, select, .interactive {
            cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 32 32' shape-rendering='crispEdges'%3E%3Cpath d='M4 4v20l5-5 4 8 4-2-4-8h8L4 4z' fill='%23a855f7' stroke='%230f172a' stroke-width='2.5'/%3E%3C/svg%3E") 4 4, pointer !important;
        }

        ::selection { background: var(--accent-main); color: #fff; }
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }

        /* ================= SCANLINE OVERLAY ================= */
        .scanline {
            position: fixed; top: 0; left: 0; width: 100vw; height: 15px;
            background: linear-gradient(to bottom, transparent, rgba(124, 58, 237, 0.15), transparent);
            opacity: 0.6; animation: scan 6s linear infinite; z-index: 9998; pointer-events: none;
        }
        @keyframes scan { 0% { transform: translateY(-100vh); } 100% { transform: translateY(100vh); } }

        /* ================= REVEAL MECHANICS ================= */
        .reveal-box { position: relative; overflow: hidden; }
        .reveal-box::after {
            content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: var(--accent-main); transform: scaleX(1); transform-origin: right;
            transition: transform 0.6s cubic-bezier(0.7, 0, 0.3, 1); z-index: 10;
        }
        .reveal-box.in-view::after { transform: scaleX(0); transform-origin: left; }
        .fade-up { opacity: 0; transform: translateY(20px); transition: all 0.8s ease; }
        .fade-up.in-view { opacity: 1; transform: translateY(0); }

        /* ================= HARSH HEADER ================= */
        .tech-header {
            position: fixed; top: 0; left: 0; width: 100%; z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            border-bottom: 2px solid var(--text-dark);
            padding: 0 2rem;
            display: flex; justify-content: space-between; align-items: center;
            height: 70px; text-transform: uppercase;
        }
        
        .sys-logo {
            display: flex; align-items: center; gap: 1rem;
            font-family: var(--font-head); font-weight: 700; font-size: 1.2rem;
            letter-spacing: -0.02em;
        }
        .sys-logo i { color: var(--accent-main); font-size: 1.4rem; }
        .sys-logo .line { width: 30px; height: 2px; background: var(--text-dark); transform: skewX(-45deg); }

        .nav-protocols { display: flex; gap: 2rem; font-family: var(--font-mono); font-size: 0.8rem; font-weight: 600; }
        .nav-protocols a { position: relative; padding: 0.5rem 0; overflow: hidden;}
        .nav-protocols a::before {
            content: '>'; position: absolute; left: -15px; opacity: 0; color: var(--accent-main); transition: 0.2s;
        }
        .nav-protocols a:hover { color: var(--accent-main); padding-left: 15px; }
        .nav-protocols a:hover::before { opacity: 1; left: 0; }

        .sys-clock {
            font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700;
            background: var(--text-dark); color: #fff; padding: 0.3rem 0.8rem;
            display: flex; align-items: center; gap: 0.5rem;
        }
        .sys-clock .blink { color: var(--accent-glow); animation: blinker 1s linear infinite; }
        @keyframes blinker { 50% { opacity: 0; } }

        /* ================= BUTTONS ================= */
        .btn-tech {
            font-family: var(--font-mono); font-weight: 700; font-size: 0.85rem; text-transform: uppercase;
            padding: 1rem 2rem; display: inline-flex; align-items: center; gap: 0.75rem;
            background: var(--bg-base); color: var(--text-dark); border: 2px solid var(--text-dark);
            position: relative; overflow: hidden; z-index: 1;
            /* 45deg chamfer cut */
            clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
            transition: color 0.3s;
        }
        .btn-tech::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: var(--accent-main); z-index: -1; transition: left 0.3s cubic-bezier(0.7, 0, 0.3, 1);
        }
        .btn-tech:hover { color: #fff; border-color: var(--accent-main); }
        .btn-tech:hover::before { left: 0; }
        
        .btn-tech.primary { background: var(--text-dark); color: #fff; border-color: var(--text-dark); }
        .btn-tech.primary:hover { color: #fff; }
        
        .btn-tech.danger { border-color: #ef4444; color: #ef4444; padding: 0.4rem 1rem; font-size: 0.75rem;}
        .btn-tech.danger::before { background: #ef4444; }
        .btn-tech.danger:hover { color: #fff; border-color: #ef4444; }

        /* ================= HERO MATRIX ================= */
        .hero-matrix {
            min-height: 100vh; padding: 120px 5% 40px; display: grid; grid-template-columns: 1.2fr 0.8fr;
            align-items: center; gap: 2rem; border-bottom: 2px solid var(--text-dark);
        }

        .hero-text-block { position: relative; z-index: 2; }
        .sys-status {
            font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; color: var(--accent-main);
            margin-bottom: 2rem; display: inline-block; border: 1px solid var(--accent-main); padding: 0.4rem 0.8rem;
        }

        .hero-headline {
            font-family: var(--font-head); font-size: clamp(3rem, 5vw, 6rem); font-weight: 700;
            line-height: 0.95; text-transform: uppercase; letter-spacing: -0.03em; margin-bottom: 2rem;
        }
        .scramble-text { display: block; }
        .hl-accent { color: var(--accent-main); }

        .hero-sub {
            font-family: var(--font-body); font-size: 1.05rem; color: var(--text-tech); line-height: 1.6;
            max-width: 550px; margin-bottom: 3rem; border-left: 2px solid var(--accent-main); padding-left: 1rem;
        }

        /* 3D HELIX MODULE CONTAINER */
        .hero-graphic {
            position: relative; height: 100%; min-height: 480px; border: 1px solid var(--text-dark);
            background: rgba(255,255,255,0.5);
            display: flex; flex-direction: column; justify-content: flex-end; padding: 2rem;
            clip-path: polygon(0 0, 100% 0, 100% calc(100% - 40px), calc(100% - 40px) 100%, 0 100%);
            overflow: hidden;
        }
        .hg-corner { position: absolute; top: 0; right: 0; width: 60px; height: 60px; border-left: 2px solid var(--text-dark); border-bottom: 2px solid var(--text-dark); z-index: 10; pointer-events: none;}
        .hg-data { font-family: var(--font-mono); font-size: 0.7rem; font-weight: 700; color: var(--accent-main); display: flex; justify-content: space-between; position: relative; z-index: 10; pointer-events: none;}

        /* CSS for the interactive 3D nodes */
        .helix-link {
            position: absolute; top: 0; left: 0;
            text-decoration: none;
            will-change: transform, opacity;
            pointer-events: auto;
        }
        .hl-inner {
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid var(--accent-main);
            padding: 0.5rem 1rem;
            display: flex; flex-direction: column;
            color: var(--text-dark); font-family: var(--font-mono);
            backdrop-filter: blur(4px);
            clip-path: polygon(8px 0, 100% 0, 100% calc(100% - 8px), calc(100% - 8px) 100%, 0 100%, 0 8px);
            box-shadow: 4px 4px 0px rgba(124, 58, 237, 0.15);
            transition: all 0.3s ease;
            white-space: nowrap;
        }
        .helix-link:hover .hl-inner {
            background: var(--text-dark); color: #fff;
            border-color: var(--text-dark);
            box-shadow: 6px 6px 0px var(--accent-main);
            transform: scale(1.08);
        }
        .hl-title { font-weight: 800; font-size: 0.8rem; color: var(--accent-main); transition: color 0.3s; }
        .helix-link:hover .hl-title { color: var(--accent-glow); }
        .hl-detail { font-size: 0.65rem; font-weight: 600; opacity: 0.7; }

        /* ================= MARQUEE TICKER ================= */
        .ticker-strip {
            background: var(--text-dark); color: #fff; padding: 0.8rem 0; overflow: hidden; display: flex;
            border-bottom: 1px solid var(--accent-main);
        }
        .ticker-track { display: flex; white-space: nowrap; animation: ticker 25s linear infinite; font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; }
        .ticker-item { margin-right: 3rem; }
        .ticker-item span { color: var(--accent-main); margin: 0 1rem; }
        @keyframes ticker { 0% { transform: translateX(0); } 100% { transform: translateX(-50%); } }

        /* ================= SYSTEM MODULES (CARDS) ================= */
        .module-section { padding: 8rem 5%; border-bottom: 2px solid var(--text-dark); }
        .sec-header { margin-bottom: 4rem; display: flex; justify-content: space-between; align-items: flex-end; }
        .sec-title { font-family: var(--font-head); font-size: 3rem; font-weight: 700; text-transform: uppercase; line-height: 1; }

        .module-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
        
        .module-card {
            border: 2px solid var(--text-dark); background: var(--bg-panel); padding: 2.5rem;
            position: relative; transition: transform 0.2s, box-shadow 0.2s;
            clip-path: polygon(0 0, calc(100% - 20px) 0, 100% 20px, 100% 100%, 20px 100%, 0 calc(100% - 20px));
        }
        .module-card:hover { transform: translate(-5px, -5px); box-shadow: 10px 10px 0px rgba(124, 58, 237, 1); border-color: var(--accent-main); }
        .module-card::before { content: ''; position: absolute; top: 0; left: 0; width: 30px; height: 30px; border-right: 2px solid var(--text-dark); border-bottom: 2px solid var(--text-dark); }
        
        .mod-icon { font-size: 2rem; color: var(--accent-main); margin-bottom: 1.5rem; }
        .mod-title { font-family: var(--font-head); font-size: 1.4rem; font-weight: 700; margin-bottom: 1rem; text-transform: uppercase; }
        .mod-desc { font-family: var(--font-body); font-size: 0.95rem; color: var(--text-tech); line-height: 1.6; }

        /* ================= TELEMETRY STATS ================= */
        .telemetry-grid { display: grid; grid-template-columns: repeat(4, 1fr); border: 2px solid var(--text-dark); }
        .tel-block { padding: 3rem 2rem; border-right: 2px solid var(--text-dark); display: flex; flex-direction: column; justify-content: center; transition: background 0.2s;}
        .tel-block:hover { background: rgba(124, 58, 237, 0.05); }
        .tel-block:last-child { border-right: none; }
        .tel-val { font-family: var(--font-head); font-size: 4rem; font-weight: 700; color: var(--accent-main); line-height: 1; margin-bottom: 0.5rem; }
        .tel-label { font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; }

        /* ================= ACCESS PROTOCOLS (LOGIN) ================= */
        .access-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
        .access-card {
            border: 1px solid var(--text-tech); background: var(--bg-base); padding: 2rem; text-align: center;
            transition: background 0.3s, color 0.3s, transform 0.2s, border-color 0.2s;
        }
        .access-card:hover { background: var(--text-dark); color: #fff; border-color: var(--text-dark); transform: translateY(-5px); }
        .access-card:hover .acc-icon { color: var(--accent-main); }
        .acc-icon { font-size: 2.5rem; margin-bottom: 1rem; color: var(--text-dark); transition: color 0.3s;}
        .acc-title { font-family: var(--font-mono); font-weight: 700; font-size: 1.1rem; text-transform: uppercase; margin-bottom: 0.5rem; }
        .acc-desc { font-family: var(--font-body); font-size: 0.85rem; opacity: 0.7; }

        /* ================= TECHNICAL FOOTER ================= */
        .tech-footer { background: var(--text-dark); color: #fff; padding: 4rem 5% 2rem; font-family: var(--font-mono); }
        .ft-top { display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 3rem; margin-bottom: 2rem; flex-wrap: wrap; gap: 2rem;}
        .ft-brand h2 { font-family: var(--font-head); font-size: 2rem; margin-bottom: 0.5rem; color: var(--accent-main); }
        .ft-info { display: flex; flex-direction: column; gap: 0.8rem; font-size: 0.8rem; }
        .ft-info i { color: var(--accent-main); margin-right: 0.5rem; }
        .ft-bottom { display: flex; justify-content: space-between; font-size: 0.75rem; color: var(--text-light); }

        @media (max-width: 1024px) {
            .hero-matrix { grid-template-columns: 1fr; padding-top: 100px; }
            .telemetry-grid { grid-template-columns: repeat(2, 1fr); }
            .tel-block:nth-child(2) { border-right: none; }
            .tel-block:nth-child(1), .tel-block:nth-child(2) { border-bottom: 2px solid var(--text-dark); }
            .nav-protocols { display: none; }
            .hero-graphic { min-height: 400px; }
            .hero-headline { font-size: clamp(2.5rem, 8vw, 4rem); }
        }
        @media (max-width: 600px) {
            .telemetry-grid { grid-template-columns: 1fr; }
            .tel-block { border-right: none !important; border-bottom: 2px solid var(--text-dark); }
            .tel-block:last-child { border-bottom: none; }
        }
    </style>
</head>
<body>

<!-- HEADER -->
<header class="tech-header">
    <div class="sys-logo interactive">
        <i class="fa-solid fa-layer-group"></i> SAAES <div class="line"></div> ZCOER
    </div>
    <nav class="nav-protocols">
        <a href="#home" class="interactive">01. HOME</a>
        <a href="#features" class="interactive">02. FEATURES</a>
        <a href="#stats" class="interactive">03. STATS</a>
        <a href="#portals" class="interactive">04. PORTALS</a>
    </nav>
    <div style="display: flex; gap: 1rem; align-items: center;">
        <div class="sys-clock interactive">
            Time <span class="blink">|</span> <span id="clock">00:00:00</span>
        </div>
        <?php if ($isLoggedIn): ?>
            <a href="auth/logout.php" class="btn-tech danger interactive">
                <i class="fa-solid fa-power-off"></i> Logout
            </a>
        <?php endif; ?>
    </div>
</header>

<!-- HERO MATRIX -->
<section class="hero-matrix" id="home">
    <div class="hero-text-block">
        <div class="sys-status interactive">Status: Online // Portal Active</div>
        <h1 class="hero-headline">
            <span class="scramble-text" data-text="STUDENT ACTIVITY">STUDENT ACTIVITY</span>
            <span class="scramble-text" data-text="ASSESSMENT &">ASSESSMENT &</span>
            <span class="scramble-text hl-accent" data-text="EVALUATION.">EVALUATION.</span>
        </h1>
        <p class="hero-sub fade-up reveal-scroll">
            Continuous Internal Evaluation (CIE 2) Portal. An easy and automated platform for students and faculty to track assignments, submit activities, and manage marksheets.
        </p>
        <div class="hero-actions fade-up reveal-scroll" style="transition-delay: 0.2s;">
            <?php if ($isLoggedIn): ?>
                <a href="<?= htmlspecialchars($dashUrl) ?>" class="btn-tech primary interactive">Go to Dashboard <i class="fa-solid fa-arrow-right"></i></a>
            <?php else: ?>
                <a href="auth/login.php" class="btn-tech primary interactive">Login <i class="fa-solid fa-arrow-right"></i></a>
                <a href="auth/register.php" class="btn-tech interactive">Register / Request Access</a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 3D DATA HELIX MODULE -->
    <div class="hero-graphic reveal-box reveal-scroll" id="helix-wrapper">
        <div class="hg-corner"></div>
        
        <!-- Canvas for the rotating wireframe -->
        <canvas id="dna-canvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none;"></canvas>
        
        <!-- DOM Container for interactive links synced to the 3D canvas -->
        <div id="helix-dom-nodes" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 2; pointer-events: none;"></div>

        <div class="hg-data">
            <span>ZCOER</span>
            <span>v2.0.26</span>
        </div>
    </div>
</section>

<!-- TICKER STRIP -->
<div class="ticker-strip">
    <div class="ticker-track">
        <?php 
        $tickerString = implode(" <span>||</span> ", $tickerNotices) . " <span>||</span> ";
        echo $tickerString . $tickerString . $tickerString; 
        ?>
    </div>
</div>

<!-- PLATFORM FEATURES -->
<section class="module-section" id="features">
    <div class="sec-header fade-up reveal-scroll">
        <h2 class="sec-title">Platform<br><span class="hl-accent">Features.</span></h2>
    </div>

    <div class="module-grid">
        <div class="module-card fade-up reveal-scroll interactive">
            <i class="fa-solid fa-microchip mod-icon"></i>
            <h3 class="mod-title">Automated Grading</h3>
            <p class="mod-desc">Grades are calculated automatically based on submission time. Late submissions receive automatic mark deductions, ensuring fair and accurate final marksheets.</p>
        </div>
        <div class="module-card fade-up reveal-scroll interactive" style="transition-delay: 0.1s;">
            <i class="fa-solid fa-network-wired mod-icon"></i>
            <h3 class="mod-title">Secure Access</h3>
            <p class="mod-desc">Dedicated logins ensure that Students, Faculty, and Parents only see the information relevant to them.</p>
        </div>
        <div class="module-card fade-up reveal-scroll interactive" style="transition-delay: 0.2s;">
            <i class="fa-solid fa-server mod-icon"></i>
            <h3 class="mod-title">Easy File Uploads</h3>
            <p class="mod-desc">Students can quickly and securely upload their assignments in PDF, JPG, or PNG formats for immediate faculty review.</p>
        </div>
    </div>
</section>

<!-- STATISTICS -->
<section class="module-section" id="stats" style="border-bottom: none;">
    <div class="sec-header fade-up reveal-scroll">
        <h2 class="sec-title">Platform<br><span class="hl-accent">Statistics.</span></h2>
    </div>

    <div class="telemetry-grid fade-up reveal-scroll">
        <div class="tel-block interactive">
            <div class="tel-val" data-target="<?= (int)max($statsData['users'], 1) ?>">0</div>
            <div class="tel-label">Registered Users</div>
        </div>
        <div class="tel-block interactive">
            <div class="tel-val" data-target="<?= (int)max($statsData['activities'], 1) ?>">0</div>
            <div class="tel-label">Active Assignments</div>
        </div>
        <div class="tel-block interactive">
            <div class="tel-val" data-target="<?= (int)max($statsData['submissions'], 1) ?>">0</div>
            <div class="tel-label">Student Submissions</div>
        </div>
        <div class="tel-block interactive">
            <div class="tel-val" data-target="6">0</div>
            <div class="tel-label">Total Units</div>
        </div>
    </div>
</section>

<!-- USER PORTALS (LOGIN) -->
<section class="module-section" id="portals" style="padding-top: 0;">
    <div class="sec-header fade-up reveal-scroll">
        <h2 class="sec-title">User<br><span class="hl-accent">Portals.</span></h2>
    </div>

    <div class="access-grid fade-up reveal-scroll">
        <a href="auth/login.php?role=Student" class="access-card interactive">
            <i class="fa-solid fa-user-graduate acc-icon"></i>
            <h3 class="acc-title">Student Portal</h3>
            <p class="acc-desc">Submit assignments and check grades.</p>
        </a>
        <a href="auth/login.php?role=Faculty" class="access-card interactive">
            <i class="fa-solid fa-chalkboard-user acc-icon"></i>
            <h3 class="acc-title">Faculty Portal</h3>
            <p class="acc-desc">Create activities and evaluate students.</p>
        </a>
        <a href="auth/login.php?role=HOD" class="access-card interactive">
            <i class="fa-solid fa-layer-group acc-icon"></i>
            <h3 class="acc-title">HOD / GFM Portal</h3>
            <p class="acc-desc">Monitor department and class progress.</p>
        </a>
        <a href="auth/login.php?role=Parent" class="access-card interactive">
            <i class="fa-solid fa-users acc-icon"></i>
            <h3 class="acc-title">Parent Portal</h3>
            <p class="acc-desc">Track student academic progress.</p>
        </a>
    </div>
</section>

<!-- FOOTER -->
<footer class="tech-footer">
    <div class="ft-top">
        <div class="ft-brand">
            <h2>SAAES SYSTEM</h2>
            <p style="color: var(--text-light); font-size: 0.85rem; max-width: 300px;">Student Activity Assessment & Evaluation System. Zeal College of Engineering & Research.</p>
        </div>
        <div class="ft-info">
            <div class="interactive"><i class="fa-solid fa-location-dot"></i> Location: Narhe, Pune - 411041</div>
            <div class="interactive"><i class="fa-solid fa-phone"></i> Phone: +91 755866663</div>
            <div class="interactive"><i class="fa-solid fa-envelope"></i> Email: zcoer@zealeducation.com</div>
        </div>
    </div>
    <div class="ft-bottom">
        <span>© <?= date('Y') ?> ZCOER. All Rights Reserved.</span>
        <span style="color: var(--accent-glow);">STATUS: ONLINE</span>
    </div>
</footer>

<!-- JAVASCRIPT LOGIC -->
<script>
document.addEventListener("DOMContentLoaded", () => {
    
    // 1. 3D Kinetic Data Helix Logic
    const wrapper = document.getElementById('helix-wrapper');
    const canvas = document.getElementById('dna-canvas');
    const ctx = canvas.getContext('2d');
    const nodesContainer = document.getElementById('helix-dom-nodes');
    
    // Define the anchor sections that will orbit the helix
    const helixLinks = [
        { id: '#home', title: '01. HOME', detail: 'START' },
        { id: '#features', title: '02. FEATURES', detail: 'INFO' },
        { id: '#stats', title: '03. STATS', detail: 'DATA' },
        { id: '#portals', title: '04. PORTALS', detail: 'LOGINS' }
    ];

    // Inject DOM nodes into the tracking layer
    helixLinks.forEach(sec => {
        const a = document.createElement('a');
        a.href = sec.id;
        a.className = 'helix-link interactive';
        a.innerHTML = `
            <div class="hl-inner">
                <span class="hl-title">${sec.title}</span>
                <span class="hl-detail">${sec.detail}</span>
            </div>
        `;
        // Trigger smooth scrolling on click
        a.addEventListener('click', (e) => {
            e.preventDefault();
            const target = document.querySelector(sec.id);
            if(target) target.scrollIntoView({ behavior: 'smooth' });
        });

        nodesContainer.appendChild(a);
        sec.el = a;
    });

    let cw, ch;
    function resizeCanvas() {
        cw = canvas.width = wrapper.clientWidth;
        ch = canvas.height = wrapper.clientHeight;
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    const rungsCount = 14; 
    const activeIndices = [2, 5, 8, 11]; // The specific rungs the DOM nodes attach to

    let time = 0;
    let speed = 0.006;
    let targetSpeed = 0.006;

    // Time Dilation Effect: Slow down helix when hovering so user can easily click
    wrapper.addEventListener('mouseenter', () => targetSpeed = 0.001);
    wrapper.addEventListener('mouseleave', () => targetSpeed = 0.006);

    function drawHelix() {
        ctx.clearRect(0, 0, cw, ch);
        
        // Smoothly accelerate/decelerate
        speed += (targetSpeed - speed) * 0.1;
        time += speed;

        const radius = cw * 0.28; // Helix width
        const startY = 60;
        const endY = ch - 80;
        const ySpacing = (endY - startY) / (rungsCount - 1);

        // Draw Center Spine
        ctx.beginPath();
        ctx.moveTo(cw/2, startY - 20);
        ctx.lineTo(cw/2, endY + 20);
        ctx.strokeStyle = 'rgba(124, 58, 237, 0.2)';
        ctx.lineWidth = 1;
        ctx.setLineDash([4, 4]);
        ctx.stroke();
        ctx.setLineDash([]);

        for (let i = 0; i < rungsCount; i++) {
            const y = startY + i * ySpacing;
            const angle = time + (i * 0.45); // phase shift creates the twist
            
            // Calculate 3D positions using sine and cosine
            const x1 = cw/2 + Math.sin(angle) * radius;
            const z1 = Math.cos(angle) * radius;
            
            const x2 = cw/2 + Math.sin(angle + Math.PI) * radius;
            const z2 = Math.cos(angle + Math.PI) * radius;

            // Draw Rung
            ctx.beginPath();
            ctx.moveTo(x1, y);
            ctx.lineTo(x2, y);
            // Opacity scales with Z-depth
            ctx.strokeStyle = `rgba(124, 58, 237, ${0.1 + ((z1 + radius)/(2*radius)) * 0.3})`;
            ctx.lineWidth = 1.5;
            ctx.stroke();

            // Draw Wireframe Dots
            ctx.fillStyle = `rgba(124, 58, 237, ${0.3 + ((z1 + radius)/(2*radius)) * 0.7})`;
            ctx.beginPath(); ctx.arc(x1, y, 3, 0, Math.PI*2); ctx.fill();
            
            ctx.fillStyle = `rgba(124, 58, 237, ${0.3 + ((z2 + radius)/(2*radius)) * 0.7})`;
            ctx.beginPath(); ctx.arc(x2, y, 3, 0, Math.PI*2); ctx.fill();

            // Sync HTML DOM nodes to 3D canvas coordinates
            const secIndex = activeIndices.indexOf(i);
            if (secIndex !== -1) {
                const sec = helixLinks[secIndex];
                
                // Scale physical size based on Z distance to fake 3D depth
                const scale = 0.85 + ((z1 + radius)/(2*radius)) * 0.3; 
                
                sec.el.style.transform = `translate(calc(-50% + ${x1}px), calc(-50% + ${y}px)) scale(${scale})`;
                sec.el.style.zIndex = Math.floor(z1 + 1000);
                
                // If it rotates "behind" the spine, blur it and disable clicks
                if (z1 < -radius * 0.3) {
                    sec.el.style.opacity = '0.15';
                    sec.el.style.pointerEvents = 'none';
                    sec.el.style.filter = 'blur(3px)';
                } else {
                    sec.el.style.opacity = '1';
                    sec.el.style.pointerEvents = 'auto';
                    sec.el.style.filter = 'none';
                }
            }
        }
        requestAnimationFrame(drawHelix);
    }
    drawHelix(); // Initiate render loop

    // 2. Text Scramble Effect (Deliberate & Smooth Timing)
    class TextScramble {
        constructor(el) {
            this.el = el;
            this.chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ!<>-_\\/[]{}—=+*^?#_01';
            this.update = this.update.bind(this);
        }
        setText(newText) {
            const oldText = this.el.innerText;
            const length = Math.max(oldText.length, newText.length);
            const promise = new Promise((resolve) => this.resolve = resolve);
            this.queue = [];
            for (let i = 0; i < length; i++) {
                const from = oldText[i] || '';
                const to = newText[i] || '';
                // Smooth deliberate timing (longer duration for smoother feel)
                const start = Math.floor(Math.random() * 40);
                const end = start + Math.floor(Math.random() * 40) + 40;
                this.queue.push({ from, to, start, end });
            }
            cancelAnimationFrame(this.frameRequest);
            this.frame = 0;
            this.update();
            return promise;
        }
        update() {
            let output = '';
            let complete = 0;
            for (let i = 0, n = this.queue.length; i < n; i++) {
                let { from, to, start, end, char } = this.queue[i];
                if (this.frame >= end) {
                    complete++;
                    output += to;
                } else if (this.frame >= start) {
                    // Lowered random chance so characters don't jitter too fast
                    if (!char || Math.random() < 0.1) {
                        char = this.randomChar();
                        this.queue[i].char = char;
                    }
                    const color = Math.random() > 0.5 ? 'var(--accent-main)' : 'var(--text-light)';
                    output += `<span style="color:${color}; opacity:0.8;">${char}</span>`;
                } else {
                    output += from;
                }
            }
            this.el.innerHTML = output;
            if (complete === this.queue.length) {
                this.resolve();
            } else {
                this.frameRequest = requestAnimationFrame(this.update);
                this.frame++;
            }
        }
        randomChar() {
            return this.chars[Math.floor(Math.random() * this.chars.length)];
        }
    }

    const phrases = document.querySelectorAll('.scramble-text');
    phrases.forEach((el, index) => {
        const fx = new TextScramble(el);
        const text = el.getAttribute('data-text');
        
        // Staggered boot-up delay based on the line index
        setTimeout(() => fx.setText(text), 500 + (index * 600));
        
        // Isolated scramble on hover
        el.addEventListener('mouseenter', () => fx.setText(text));
    });

    // 3. Scroll Reveal Logic & Counter Animation
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add("in-view");
                
                // Trigger number counter if it's a telemetry block
                if (entry.target.classList.contains('telemetry-grid')) {
                    document.querySelectorAll('.tel-val').forEach(el => {
                        const target = parseInt(el.getAttribute('data-target'));
                        animateValue(el, 0, target, 1500);
                    });
                }
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: "0px 0px -50px 0px" });

    document.querySelectorAll(".reveal-scroll, .reveal-box").forEach(el => observer.observe(el));

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

    // 4. Live Clock (IST)
    const clockEl = document.getElementById('clock');
    function updateClock() {
        const now = new Date();
        const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
        const ist = new Date(utc + (3600000 * 5.5));
        
        let h = ist.getHours(), m = ist.getMinutes(), s = ist.getSeconds();
        h = h < 10 ? '0'+h : h;
        m = m < 10 ? '0'+m : m;
        s = s < 10 ? '0'+s : s;
        clockEl.textContent = `${h}:${m}:${s}`;
    }
    setInterval(updateClock, 1000);
    updateClock();
});
</script>

</body>
</html>