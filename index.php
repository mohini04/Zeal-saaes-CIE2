<?php
/**
 * index.php
 * SAAES — Landing Page (Entry point)
 */
session_start();

// Connect database to trigger auto-seeding if empty
$pdo = require __DIR__ . '/config/db.php';

// Include layout header
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section id="home" class="hero-section" style="background-image: url('assets/images/college_building.jpg'), linear-gradient(135deg, #0A1128 0%, #1B2029 100%);">
    <div class="hero-overlay"></div>
    
    <div class="hero-content">
        <h2>Student Activity <br>Assessment & Evaluation System <span>(CIE 2)</span></h2>
        <p>A smart platform to manage activities, submit assignments, evaluate performance and generate final marksheets efficiently and transparently.</p>
    </div>

    <!-- Notice Board (Sliding Ticker) -->
    <div class="ticker-wrap">
        <div class="ticker-title">
            <i class="fas fa-bullhorn"></i> Notice Board
        </div>
        <div class="ticker-content">
            <div class="ticker-items">
                <div class="ticker-item">Unit 2 Activity last date 20 May 2025.</div>
                <div class="ticker-item">Final Activity Marksheet will be available after completion of all 6 units.</div>
                <div class="ticker-item">Unit 3 Activity for Data Structures is now live.</div>
                <!-- Loop items for continuous scrolling -->
                <div class="ticker-item">Unit 2 Activity last date 20 May 2025.</div>
                <div class="ticker-item">Final Activity Marksheet will be available after completion of all 6 units.</div>
                <div class="ticker-item">Unit 3 Activity for Data Structures is now live.</div>
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

<!-- User Roles Section -->
<section id="roles" class="section section-bg">
    <div class="section-header">
        <h2 class="section-title">User Roles</h2>
    </div>
    
    <div class="roles-grid">
        <!-- Student Card -->
        <a href="auth/login.php?role=Student" class="role-card r-student">
            <div class="role-icon-box">
                <i class="fas fa-user-graduate"></i>
            </div>
            <h3 class="role-title">Student</h3>
            <p class="role-desc">View activities, submit assignments and track performance.</p>
        </a>
        
        <!-- Faculty Card -->
        <a href="auth/login.php?role=Faculty" class="role-card r-faculty">
            <div class="role-icon-box">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <h3 class="role-title">Faculty</h3>
            <p class="role-desc">Create activities, evaluate submissions and generate reports.</p>
        </a>
        
        <!-- Parent Card -->
        <a href="auth/login.php?role=Parent" class="role-card r-parent">
            <div class="role-icon-box">
                <i class="fas fa-users"></i>
            </div>
            <h3 class="role-title">Parent</h3>
            <p class="role-desc">Monitor student progress, marks and pending activities.</p>
        </a>
        
        <!-- Admin Card -->
        <a href="auth/login.php?role=Admin" class="role-card r-admin">
            <div class="role-icon-box">
                <i class="fas fa-user-shield"></i>
            </div>
            <h3 class="role-title">Admin</h3>
            <p class="role-desc">Manage users, subjects, activities and system settings.</p>
        </a>
        
        <!-- HOD Card -->
        <a href="auth/login.php?role=HOD" class="role-card r-hod">
            <div class="role-icon-box">
                <i class="fas fa-id-card-alt"></i>
            </div>
            <h3 class="role-title">HOD</h3>
            <p class="role-desc">Oversee department activities and performance.</p>
        </a>
        
        <!-- GFM Card -->
        <a href="auth/login.php?role=GFM" class="role-card r-gfm">
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