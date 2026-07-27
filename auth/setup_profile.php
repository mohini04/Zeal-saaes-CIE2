<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../config/db.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'Student';

$checkStmt = $conn->prepare("SELECT is_first_login, name, username, role FROM users WHERE user_id = ?");
$checkStmt->bind_param("i", $user_id);
$checkStmt->execute();
$userData = $checkStmt->get_result()->fetch_assoc();
$checkStmt->close();

$actual_role = $userData['role'] ?? $user_role;

// Route if setup already completed
if ((int)($userData['is_first_login'] ?? 0) === 0) {
    switch (strtolower($actual_role)) {
        case 'admin': header("Location: admin_dashboard.php"); break;
        case 'faculty': header("Location: ../faculty_dashboard.php"); break;
        case 'hod': header("Location: ../hod_dashboard.php"); break;
        case 'gfm': header("Location: ../gfm_dashboard.php"); break;
        case 'parent': header("Location: ../parent_dashboard.php"); break;
        default: header("Location: ../student_dashboard.php"); break;
    }
    exit();
}

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $new_password     = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    $security_q       = trim($_POST['security_question']);
    $security_a       = trim($_POST['security_answer']);
    $phone            = trim($_POST['phone'] ?? '');

    $roll_no  = (strtolower($actual_role) === 'student') ? trim($_POST['roll_no'] ?? '') : NULL;
    
    // Faculty assignments
    $fac_dept = (strtolower($actual_role) === 'faculty') ? trim($_POST['fac_department'] ?? '') : NULL;
    $fac_year = (strtolower($actual_role) === 'faculty') ? trim($_POST['fac_academic_year'] ?? '') : NULL;
    $fac_subj = (strtolower($actual_role) === 'faculty') ? trim($_POST['fac_subject'] ?? '') : NULL;

    $isValid = !empty($new_password) && !empty($confirm_password) && !empty($security_q) && !empty($security_a);
    if (strtolower($actual_role) === 'student') {
        if (empty($roll_no)) {
            $isValid = false;
        }
    }
    if (strtolower($actual_role) === 'faculty') {
        if (empty($fac_dept) || empty($fac_year) || empty($fac_subj)) {
            $isValid = false;
        }
    }

    if ($isValid) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

                $updateStmt = $conn->prepare("UPDATE users SET password = ?, security_question = ?, security_answer = ?, roll_no = ?, phone = ?, is_first_login = 0 WHERE user_id = ?");
                $updateStmt->bind_param("sssssi", $hashed_password, $security_q, $security_a, $roll_no, $phone, $user_id);

                if ($updateStmt->execute()) {
                    // If Faculty, auto-seed the standard 4 teaching assignments (Divisions A, B, C, D)
                    if (strtolower($actual_role) === 'faculty') {
                        @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS faculty_classes (
                            class_id INT AUTO_INCREMENT PRIMARY KEY,
                            faculty_id INT NOT NULL,
                            class_name VARCHAR(150) NOT NULL,
                            subject_code VARCHAR(100),
                            academic_year VARCHAR(50) DEFAULT 'FY',
                            department VARCHAR(100) DEFAULT '',
                            division VARCHAR(50) DEFAULT '',
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )");
                        
                        // Patch columns just in case table exists but missing these new global fields
                        @mysqli_query($conn, "ALTER TABLE faculty_classes ADD COLUMN IF NOT EXISTS department VARCHAR(100) DEFAULT ''");
                        @mysqli_query($conn, "ALTER TABLE faculty_classes ADD COLUMN IF NOT EXISTS division VARCHAR(50) DEFAULT ''");

                        $divisions = ['A', 'B', 'C', 'D'];
                        $facStmt = $conn->prepare("INSERT INTO faculty_classes (faculty_id, class_name, subject_code, academic_year, department, division) VALUES (?, ?, ?, ?, ?, ?)");
                        
                        foreach ($divisions as $fac_div) {
                            $className = "{$fac_dept} - {$fac_year} - Div {$fac_div}";
                            $facStmt->bind_param("isssss", $user_id, $className, $fac_subj, $fac_year, $fac_dept, $fac_div);
                            $facStmt->execute();
                        }
                        $facStmt->close();
                    }

                    $success = "Account setup successfully completed. Redirecting...";
                    
                    $roleLow = strtolower($actual_role);
                    $targetUrl = "../student_dashboard.php";
                    if ($roleLow === 'admin') $targetUrl = "admin_dashboard.php";
                    elseif ($roleLow === 'faculty') $targetUrl = "../faculty_dashboard.php";
                    elseif ($roleLow === 'hod') $targetUrl = "../hod_dashboard.php";
                    elseif ($roleLow === 'gfm') $targetUrl = "../gfm_dashboard.php";
                    elseif ($roleLow === 'parent') $targetUrl = "../parent_dashboard.php";

                    echo "<script>
                            setTimeout(function(){
                                window.location.href = '$targetUrl';
                            }, 1800);
                          </script>";
                } else {
                    $error = "System write error updating profile.";
                }
                $updateStmt->close();
            } else {
                $error = "Password requirement failed: Minimum 6 characters required.";
            }
        } else {
            $error = "Password confirmation mismatch.";
        }
    } else {
        $error = "Please fill in all required setup parameters.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Setup | SAAES</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Professional Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=JetBrains+Mono:wght@100;400;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-base: #ffffff;
            --text-dark: #0f172a; 
            --text-tech: #475569; 
            --text-light: #94a3b8;
            
            --accent-main: #7c3aed; 
            --accent-glow: #a855f7; 
            
            --grid-size: 40px;
            --border-harsh: 2px solid var(--text-dark);
            
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
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            position: relative;
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

        /* ================= SETUP CARD ================= */
        .setup-card {
            background: rgba(255, 255, 255, 0.95);
            border: var(--border-harsh);
            width: 100%;
            max-width: 650px;
            z-index: 5;
            padding: 3rem;
            position: relative;
            backdrop-filter: blur(10px);
            box-shadow: 15px 15px 0px rgba(124, 58, 237, 0.15);
            clip-path: polygon(0 0, calc(100% - 30px) 0, 100% 30px, 100% 100%, 30px 100%, 0 calc(100% - 30px));
            animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .setup-card::before {
            content: ''; position: absolute; top: 0; left: 0; width: 40px; height: 40px;
            border-right: 2px solid var(--text-dark); border-bottom: 2px solid var(--text-dark);
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .sys-tag {
            font-family: var(--font-mono);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: var(--accent-main);
            margin-bottom: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(124, 58, 237, 0.08);
            padding: 0.3rem 0.8rem;
            border: 1px solid rgba(124, 58, 237, 0.2);
        }

        .card-title {
            font-family: var(--font-head);
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            text-transform: uppercase;
            letter-spacing: -0.02em;
            margin-bottom: 0.5rem;
            line-height: 1.1;
        }

        .card-subtitle {
            font-family: var(--font-body);
            color: var(--text-tech);
            font-size: 0.9rem;
            line-height: 1.5;
            margin-bottom: 2.5rem;
            border-left: 2px solid var(--accent-main);
            padding-left: 1rem;
        }

        /* ================= FORM ELEMENTS ================= */
        .section-divider {
            font-family: var(--font-mono);
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-dark);
            border-bottom: 2px solid var(--text-dark);
            padding-bottom: 8px;
            margin-bottom: 20px;
            margin-top: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
        }
        .section-divider i { color: var(--accent-main); }

        .form-group { margin-bottom: 1.25rem; }
        
        .custom-label {
            font-family: var(--font-mono);
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-dark);
            margin-bottom: 6px;
            display: block;
        }

        .form-control-custom, .form-select-custom {
            background-color: var(--bg-base);
            border: 1px solid var(--text-tech);
            color: var(--text-dark);
            padding: 12px 16px;
            font-family: var(--font-body);
            font-size: 0.95rem;
            width: 100%;
            transition: all 0.3s ease;
            border-radius: 0;
            -webkit-appearance: none;
        }
        
        .form-control-custom:focus, .form-select-custom:focus {
            border-color: var(--text-dark);
            border-width: 2px;
            outline: none;
            padding: 11px 15px; /* Offset border width */
        }
        .form-control-custom::placeholder { color: var(--text-light); font-family: var(--font-mono); font-size: 0.85rem; }

        /* ================= BUTTON ================= */
        .btn-tech {
            font-family: var(--font-mono); font-weight: 700; font-size: 1rem; text-transform: uppercase;
            padding: 1.2rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.75rem;
            background: var(--text-dark); color: #fff; border: 2px solid var(--text-dark);
            position: relative; overflow: hidden; z-index: 1; cursor: pointer;
            clip-path: polygon(15px 0, 100% 0, 100% calc(100% - 15px), calc(100% - 15px) 100%, 0 100%, 0 15px);
            transition: color 0.3s; width: 100%; margin-top: 1rem;
        }
        .btn-tech::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: var(--accent-main); z-index: -1; transition: left 0.3s cubic-bezier(0.7, 0, 0.3, 1);
        }
        .btn-tech:hover { color: #fff; border-color: var(--accent-main); }
        .btn-tech:hover::before { left: 0; }
        .btn-tech i { transition: transform 0.3s; }
        .btn-tech:hover i { transform: translateX(5px); }

        /* Alerts styling */
        .alert { 
            font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; 
            border: 2px solid transparent; border-radius: 0; padding: 1rem 1.2rem; margin-bottom: 2rem; 
            display: flex; align-items: center; gap: 0.75rem;
        }
        .alert-danger { background: rgba(239, 68, 68, 0.05); color: #ef4444; border-color: #ef4444; }
        .alert-success { background: rgba(16, 185, 129, 0.05); color: #10b981; border-color: #10b981; }

        @media (max-width: 768px) {
            .setup-card { padding: 2rem; clip-path: none; box-shadow: 8px 8px 0px rgba(124, 58, 237, 0.15); border-radius: 0;}
            .card-title { font-size: 1.6rem; }
        }
    </style>
</head>
<body>

    <div class="setup-card">
        <div class="mb-4">
            <div class="sys-tag"><i class="fa-solid fa-user-shield"></i> SYS.INIT // ACCOUNT SETUP</div>
            <h3 class="card-title"><?php echo htmlspecialchars($actual_role); ?> Setup</h3>
            <p class="card-subtitle">Welcome, <strong><?php echo htmlspecialchars($userData['name']); ?></strong> (<?php echo htmlspecialchars($userData['username']); ?>). Please complete your profile to continue.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> ERR // <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> SYS // <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="setup_profile.php" id="setupForm">
            
            <!-- Section 1: Security Passkey -->
            <div class="section-divider"><i class="fa-solid fa-key"></i> 1. Security Credentials</div>
            
            <div class="row">
                <div class="col-md-6 form-group">
                    <label class="custom-label">New Password *</label>
                    <input type="password" name="new_password" class="form-control-custom interactive" placeholder="Min 6 characters" required>
                </div>
                <div class="col-md-6 form-group">
                    <label class="custom-label">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control-custom interactive" placeholder="Retype password" required>
                </div>
            </div>

            <!-- Section 2: Account Recovery -->
            <div class="section-divider"><i class="fa-solid fa-life-ring"></i> 2. Recovery Setup</div>
            
            <div class="form-group">
                <label class="custom-label">Security Question *</label>
                <select name="security_question" class="form-select-custom interactive" required>
                    <option value="" disabled selected>-- Select a Question --</option>
                    <option value="What was the name of your first school?">What was the name of your first school?</option>
                    <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                    <option value="What city were you born in?">What city were you born in?</option>
                    <option value="What was the name of your first pet?">What was the name of your first pet?</option>
                </select>
            </div>

            <div class="form-group">
                <label class="custom-label">Security Answer *</label>
                <input type="text" name="security_answer" class="form-control-custom interactive" placeholder="Your Answer" required autocomplete="off">
            </div>

            <!-- Section 3: Role-Adaptive Metadata -->
            <?php if ($actual_role === 'Student'): ?>
                <div class="section-divider"><i class="fa-solid fa-graduation-cap"></i> 3. Academic Details</div>
                
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="custom-label">Roll No *</label>
                        <input type="text" name="roll_no" class="form-control-custom interactive" placeholder="e.g. 45" required autocomplete="off">
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="custom-label">Phone No</label>
                        <input type="text" name="phone" class="form-control-custom interactive" placeholder="Optional" autocomplete="off">
                    </div>
                </div>
            <?php elseif ($actual_role === 'Faculty'): ?>
                <div class="section-divider"><i class="fa-solid fa-chalkboard-user"></i> 3. Primary Teaching Assignment</div>
                <p style="font-family: var(--font-mono); font-size: 0.8rem; color: var(--text-tech); margin-bottom: 1.5rem;">Select your primary global cohort. You can add more later in your dashboard.</p>
                <div class="row">
                    <div class="col-md-6 form-group">
                        <label class="custom-label">Department / Branch *</label>
                        <select name="fac_department" class="form-select-custom interactive" required>
                            <option value="" disabled selected>-- Select Dept --</option>
                            <option value="AI and Machine Learning">AI and Machine Learning</option>
                            <option value="AI and Data Science">AI and Data Science</option>
                            <option value="Computer Engineering">Computer Engineering</option>
                            <option value="ENTC">ENTC</option>
                            <option value="Mechanical Engineering">Mechanical Engineering</option>
                            <option value="Electrical Engineering">Electrical Engineering</option>
                            <option value="Electronics and Computer Engineering">Electronics and Computer Engineering</option>
                            <option value="Information Technology">Information Technology</option>
                            <option value="Civil Engineering">Civil Engineering</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="custom-label">Academic Year *</label>
                        <select name="fac_academic_year" class="form-select-custom interactive" required>
                            <option value="" disabled selected>-- Select Year --</option>
                            <option value="FY">First Year (FY)</option>
                            <option value="SY">Second Year (SY)</option>
                            <option value="TY">Third Year (TY)</option>
                            <option value="Final Year">Final Year (B.Tech)</option>
                        </select>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="custom-label">Subject / Course Code *</label>
                        <input type="text" name="fac_subject" class="form-control-custom interactive" placeholder="e.g. BEE or CS101" required autocomplete="off">
                    </div>
                    <div class="col-12 form-group">
                        <label class="custom-label">Phone Number (Optional)</label>
                        <input type="text" name="phone" class="form-control-custom interactive" placeholder="Optional contact" autocomplete="off">
                    </div>
                </div>
            <?php else: ?>
                <div class="section-divider"><i class="fa-solid fa-address-book"></i> 3. Contact Details</div>
                
                <div class="form-group">
                    <label class="custom-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control-custom interactive" placeholder="Optional primary contact" autocomplete="off">
                </div>
            <?php endif; ?>

            <button type="submit" class="btn-tech interactive" id="submitBtn">
                <span>Complete Setup</span> <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>
    </div>

    <!-- VANILLA JS FOR PREMIUM INTERACTIONS -->
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        // Connect interactive hover class for cursor styling
        document.querySelectorAll('.interactive, button, a, input, select, textarea').forEach(el => {
            el.addEventListener("mouseenter", () => document.body.classList.add("hovering"));
            el.addEventListener("mouseleave", () => document.body.classList.remove("hovering"));
        });

        // Submit Button Feedback
        document.getElementById('setupForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.style.pointerEvents = 'none';
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin me-2"></i> PROCESSING...';
        });
    });
    </script>
</body>
</html>