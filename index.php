<?php
/**
 * index.php
 * SAAES — Landing Page (Entry point)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connect database safely with fallback
$pdo = null;
try {
    $pdo = require __DIR__ . '/config/db.php';
} catch (Exception $e) {
    // Database connection failure will be handled gracefully
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

// Fetch dynamic ticker announcements from database
$tickerNotices = [];
if ($pdo) {
    try {
        // Try activities table
        $stmt = $pdo->query("SELECT title, unit, subject, due_date FROM activities ORDER BY created_at DESC LIMIT 5");
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $subj = !empty($row['subject']) ? htmlspecialchars($row['subject']) . " - " : "";
            $unit = !empty($row['unit']) ? "Unit " . htmlspecialchars($row['unit']) . " " : "";
            $due  = !empty($row['due_date']) ? " (Due: " . date("d M Y", strtotime($row['due_date'])) . ")" : "";
            $tickerNotices[] = $subj . $unit . htmlspecialchars($row['title']) . $due;
        }
    } catch (Exception $ex) {
        // Table or query fallback
    }
}

// Default notices if empty
if (empty($tickerNotices)) {
    $tickerNotices = [
        "Unit 2 Activity last date 20 May 2025.",
        "Final Activity Marksheet will be available after completion of all 6 units.",
        "Unit 3 Activity for Data Structures is now live.",
        "Students are requested to upload original PDF submissions only.",
        "Parents can monitor attendance & CIE 2 marks in real-time."
    ];
}

// Fetch dynamic system metrics for statistics section
$statsData = [
    'users' => 0,
    'activities' => 0,
    'submissions' => 0,
    'units' => 6
];

if ($pdo) {
    try {
        $uStmt = $pdo->query("SELECT COUNT(*) FROM users");
        $statsData['users'] = (int)$uStmt->fetchColumn();

        $aStmt = $pdo->query("SELECT COUNT(*) FROM activities");
        $statsData['activities'] = (int)$aStmt->fetchColumn();

        $sStmt = $pdo->query("SELECT COUNT(*) FROM submission");
        $statsData['submissions'] = (int)$sStmt->fetchColumn();
    } catch (Exception $e) {
        // Default numbers if tables fail
        $statsData['users'] = 120;
        $statsData['activities'] = 24;
        $statsData['submissions'] = 310;
    }
}

// Include layout header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section id="home" class="hero-section" style="background-image: url('assets/images/college_building.jpg'), linear-gradient(135deg, #0A1128 0%, #1B2029 100%);">
    <div class="hero-overlay"></div>
    
    <div class="hero-content">
        <?php if ($isLoggedIn): ?>
            <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(52, 84, 209, 0.25); border: 1px solid rgba(108, 127, 232, 0.4); border-radius: 50px; padding: 0.4rem 1.2rem; margin-bottom: 1.25rem; font-size: 0.9rem; color: #E0E7FF; backdrop-filter: blur(8px);">
                <i class="fas fa-user-circle" style="color: #6C7FE8;"></i>
                <span>Welcome back, <strong><?php echo htmlspecialchars($userName); ?></strong> (<?php echo htmlspecialchars($userRole); ?>)</span>
                <a href="<?php echo htmlspecialchars($dashUrl); ?>" style="color: #6C7FE8; text-decoration: underline; font-weight: 600; margin-left: 0.5rem;">Go to Dashboard &rarr;</a>
            </div>
        <?php endif; ?>

        <h2>Student Activity <br>Assessment & Evaluation System <span>(CIE 2)</span></h2>
        <p>A smart platform to manage activities, submit assignments, evaluate performance and generate final marksheets efficiently and transparently.</p>
        
        <div style="margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <?php if ($isLoggedIn): ?>
                <a href="<?php echo htmlspecialchars($dashUrl); ?>" class="btn btn-primary btn-lg">
                    <i class="fas fa-gauge-high btn-icon"></i> Access Dashboard
                </a>
            <?php else: ?>
                <a href="auth/student_login.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt btn-icon"></i> Portal Login
                </a>
                <a href="auth/register.php" class="btn btn-outline btn-lg">
                    <i class="fas fa-user-plus btn-icon"></i> New Registration
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notice Board (Sliding Ticker) -->
    <div class="ticker-wrap">
        <div class="ticker-title">
            <i class="fas fa-bullhorn"></i> Notice Board
        </div>
        <div class="ticker-content">
            <div class="ticker-items">
                <?php 
                // Loop items twice to ensure seamless continuous ticker loop
                $allNotices = array_merge($tickerNotices, $tickerNotices);
                foreach ($allNotices as $notice): 
                ?>
                    <div class="ticker-item"><?php echo $notice; ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- Key Features Section -->
<section id="features" class="section">
    <div class="section-header">
        <h2 class="section-title">Key Features</h2>
    </div>
    
    <div class="features-grid">
        <!-- Feature 1 -->
        <div class="feature-card">
            <div class="feat-icon-box f1-icon">
                <i class="fas fa-tasks"></i>
            </div>
            <h3 class="feat-title">Activity Management</h3>
            <p class="feat-desc">Faculty can create and manage unit-wise activities with due dates.</p>
        </div>
        
        <!-- Feature 2 -->
        <div class="feature-card">
            <div class="feat-icon-box f2-icon">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <h3 class="feat-title">Easy Submission</h3>
            <p class="feat-desc">Students can upload PDF, JPG or PNG files in just a few clicks.</p>
        </div>
        
        <!-- Feature 3 -->
        <div class="feature-card">
            <div class="feat-icon-box f3-icon">
                <i class="fas fa-history"></i>
            </div>
            <h3 class="feat-title">Automatic Evaluation</h3>
            <p class="feat-desc">Marks are allocated automatically based on submission time.</p>
        </div>
        
        <!-- Feature 4 -->
        <div class="feature-card">
            <div class="feat-icon-box f4-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <h3 class="feat-title">Progress Tracking</h3>
            <p class="feat-desc">Students, Parents and Faculty can track performance and view results.</p>
        </div>
        
        <!-- Feature 5 -->
        <div class="feature-card">
            <div class="feat-icon-box f5-icon">
                <i class="fas fa-file-invoice"></i>
            </div>
            <h3 class="feat-title">Transparent Marksheets</h3>
            <p class="feat-desc">Final marksheets are generated automatically once all units are complete.</p>
        </div>
    </div>
</section>

<!-- System Statistics Section -->
<section id="stats" class="section" style="background: linear-gradient(180deg, rgba(10, 17, 40, 0.4) 0%, rgba(27, 32, 41, 0.6) 100%); border-top: 1px solid rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.05);">
    <div class="section-header">
        <h2 class="section-title">System Activity Overview</h2>
    </div>
    
    <div class="features-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <div class="feature-card" style="text-align: center;">
            <div class="feat-icon-box f1-icon" style="margin: 0 auto 1rem auto;">
                <i class="fas fa-users"></i>
            </div>
            <h3 style="font-size: 2rem; font-weight: 700; color: #3454D1; margin-bottom: 0.25rem;">
                <?php echo htmlspecialchars((string)max($statsData['users'], 1)); ?>+
            </h3>
            <p class="feat-desc" style="font-weight: 600;">Registered Users</p>
        </div>

        <div class="feature-card" style="text-align: center;">
            <div class="feat-icon-box f2-icon" style="margin: 0 auto 1rem auto;">
                <i class="fas fa-file-signature"></i>
            </div>
            <h3 style="font-size: 2rem; font-weight: 700; color: #2E7D32; margin-bottom: 0.25rem;">
                <?php echo htmlspecialchars((string)max($statsData['activities'], 1)); ?>+
            </h3>
            <p class="feat-desc" style="font-weight: 600;">Course Activities</p>
        </div>

        <div class="feature-card" style="text-align: center;">
            <div class="feat-icon-box f3-icon" style="margin: 0 auto 1rem auto;">
                <i class="fas fa-upload"></i>
            </div>
            <h3 style="font-size: 2rem; font-weight: 700; color: #E65100; margin-bottom: 0.25rem;">
                <?php echo htmlspecialchars((string)max($statsData['submissions'], 1)); ?>+
            </h3>
            <p class="feat-desc" style="font-weight: 600;">Student Submissions</p>
        </div>

        <div class="feature-card" style="text-align: center;">
            <div class="feat-icon-box f4-icon" style="margin: 0 auto 1rem auto;">
                <i class="fas fa-award"></i>
            </div>
            <h3 style="font-size: 2rem; font-weight: 700; color: #6A1B9A; margin-bottom: 0.25rem;">6 Units</h3>
            <p class="feat-desc" style="font-weight: 600;">CIE 2 Evaluation</p>
        </div>
    </div>
</section>

<!-- User Roles Section -->
<section id="roles" class="section section-bg">
    <div class="section-header">
        <h2 class="section-title">User Roles & Portals</h2>
    </div>
    
    <div class="roles-grid">
        <!-- Student Card -->
        <a href="auth/student_login.php" class="role-card r-student">
            <div class="role-icon-box">
                <i class="fas fa-user-graduate"></i>
            </div>
            <h3 class="role-title">Student</h3>
            <p class="role-desc">View activities, submit assignments and track performance.</p>
        </a>
        
        <!-- Faculty Card -->
        <a href="auth/faculty_login.php" class="role-card r-faculty">
            <div class="role-icon-box">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <h3 class="role-title">Faculty</h3>
            <p class="role-desc">Create activities, evaluate submissions and generate reports.</p>
        </a>
        
        <!-- Parent Card -->
        <a href="auth/parent_login.php" class="role-card r-parent">
            <div class="role-icon-box">
                <i class="fas fa-users"></i>
            </div>
            <h3 class="role-title">Parent</h3>
            <p class="role-desc">Monitor student progress, marks and pending activities.</p>
        </a>
        
        <!-- Admin Card -->
        <a href="auth/admin_login.php" class="role-card r-admin">
            <div class="role-icon-box">
                <i class="fas fa-user-shield"></i>
            </div>
            <h3 class="role-title">Admin</h3>
            <p class="role-desc">Manage users, subjects, activities and system settings.</p>
        </a>
        
        <!-- HOD Card -->
        <a href="auth/hod_login.php" class="role-card r-hod">
            <div class="role-icon-box">
                <i class="fas fa-id-card-alt"></i>
            </div>
            <h3 class="role-title">HOD</h3>
            <p class="role-desc">Oversee department activities and performance.</p>
        </a>
        
        <!-- GFM Card -->
        <a href="auth/gfm_login.php" class="role-card r-gfm">
            <div class="role-icon-box">
                <i class="fas fa-users-cog"></i>
            </div>
            <h3 class="role-title">GFM</h3>
            <p class="role-desc">Monitor student progress and academic data.</p>
        </a>
    </div>
</section>

<?php
// Include layout footer
require_once __DIR__ . '/includes/footer.php';
?>