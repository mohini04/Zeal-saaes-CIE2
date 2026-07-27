<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Steps out of 'auth' folder to find 'config/db.php'
require_once(__DIR__ . '/../config/db.php');

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_identity = strtolower(trim($_POST['username'] ?? ''));
    $password       = trim($_POST['password'] ?? '');
    $selected_role  = strtolower(trim($_POST['role'] ?? ''));

    if (!empty($input_identity) && !empty($password) && !empty($selected_role)) {
        
        // Checks BOTH username and email columns with case-insensitive matching
        $stmt = $conn->prepare("SELECT user_id, name, password, role, is_first_login, department FROM users WHERE (LOWER(TRIM(username)) = ? OR LOWER(TRIM(email)) = ?) AND LOWER(role) = ?");
        $stmt->bind_param("sss", $input_identity, $input_identity, $selected_role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $normalized_role = strtolower($user['role']);
                
                $_SESSION['user_id']    = (int) $user['user_id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['full_name']  = $user['name']; // Required by student dashboard
                $_SESSION['role']       = $normalized_role; // Required in lowercase ('student')
                if (!empty($user['department'])) {
                    $_SESSION['department'] = $user['department'];
                }

                // If student, pre-populate student_id & session data
                if ($normalized_role === 'student') {
                    $stuStmt = $conn->prepare("SELECT student_id, roll_no, division, department FROM students WHERE user_id = ? LIMIT 1");
                    if ($stuStmt) {
                        $stuStmt->bind_param("i", $user['user_id']);
                        $stuStmt->execute();
                        $stuRes = $stuStmt->get_result();
                        if ($stuRes && $stuRow = $stuRes->fetch_assoc()) {
                            $_SESSION['student_id'] = (int) $stuRow['student_id'];
                            $_SESSION['roll_no']    = $stuRow['roll_no'];
                            $_SESSION['division']   = $stuRow['division'];
                            $_SESSION['department'] = $stuRow['department'];
                        }
                        $stuStmt->close();
                    }
                }

                // FIRST LOGIN INTERCEPTOR GATE
                if ((int)($user['is_first_login'] ?? 0) === 1) {
                    header("Location: setup_profile.php");
                    exit();
                }

                // Dynamic relative routing depending on role
                switch ($normalized_role) {
                    case 'admin': 
                        header("Location: admin_dashboard.php"); 
                        exit();
                    case 'faculty': 
                        header("Location: ../faculty_dashboard.php"); 
                        exit();
                    case 'hod': 
                        header("Location: ../hod_dashboard.php"); 
                        exit();
                    case 'gfm': 
                        header("Location: ../gfm_dashboard.php"); 
                        exit();
                    case 'student': 
                        header("Location: ../student_dashboard.php"); 
                        exit();
                    case 'parent': 
                        header("Location: ../parent_dashboard.php"); 
                        exit();
                    default: 
                        $error = "No dashboard routing defined for role: " . htmlspecialchars($user['role']);
                        break;
                }
            } else {
                $error = "Invalid password credentials provided.";
            }
        } else {
            $error = "No active account found matching identity '" . htmlspecialchars($input_identity) . "' for " . htmlspecialchars($selected_role) . ".";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all login fields including role selection.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | SAAES</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Sci-Fi / Technical Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=JetBrains+Mono:wght@100;400;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-base: #ffffff;
            --bg-panel: #fcfcfd;
            --text-dark: #0f172a; 
            --text-tech: #475569; 
            --text-light: #94a3b8;
            
            --accent-main: #7c3aed; /* Vibrant Purple */
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
        a { text-decoration: none; color: inherit; }

        /* ================= LOGIN CARD ================= */
        .request-card {
            background: rgba(255, 255, 255, 0.95);
            border: var(--border-harsh);
            width: 100%;
            max-width: 480px;
            z-index: 5;
            padding: 3rem;
            position: relative;
            backdrop-filter: blur(10px);
            box-shadow: 15px 15px 0px rgba(124, 58, 237, 0.15);
            clip-path: polygon(0 0, calc(100% - 30px) 0, 100% 30px, 100% 100%, 30px 100%, 0 calc(100% - 30px));
            animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .request-card::before {
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
            font-size: 2.2rem;
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
            padding: 1rem 1.2rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.75rem;
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

        .helper-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            font-family: var(--font-mono);
            font-size: 0.8rem;
            color: var(--text-tech);
            text-transform: uppercase;
        }
        .helper-links a {
            font-weight: 700;
            color: var(--text-dark);
            transition: color 0.3s;
            border-bottom: 1px solid transparent;
        }
        .helper-links a:hover {
            color: var(--accent-main);
            border-color: var(--accent-main);
        }

        /* Alerts styling */
        .alert { 
            font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; 
            border: 2px solid transparent; border-radius: 0; padding: 1rem 1.2rem; margin-bottom: 2rem; 
            display: flex; align-items: center; gap: 0.75rem;
        }
        .alert-danger { background: rgba(239, 68, 68, 0.05); color: #ef4444; border-color: #ef4444; }

        @media (max-width: 768px) {
            .request-card { padding: 2rem; clip-path: none; box-shadow: 8px 8px 0px rgba(124, 58, 237, 0.15); border-radius: 0;}
            .card-title { font-size: 1.8rem; }
        }
    </style>
</head>
<body>

    <div class="request-card">
        <div class="mb-4">
            <div class="sys-tag"><i class="fa-solid fa-lock"></i> SYS.AUTH</div>
            <h3 class="card-title">System Login</h3>
            <p class="card-subtitle">Enter your credentials to access your dashboard.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> ERR // <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            
            <div class="form-group">
                <label class="custom-label">Account Role *</label>
                <?php $getRole = strtolower($_GET['role'] ?? ''); ?>
                <select name="role" class="form-select-custom interactive" required>
                    <option value="" disabled <?php echo empty($getRole) ? 'selected' : ''; ?>>-- Select Role --</option>
                    <option value="student" <?php echo $getRole === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="parent" <?php echo $getRole === 'parent' ? 'selected' : ''; ?>>Parent / Guardian</option>
                    <option value="faculty" <?php echo $getRole === 'faculty' ? 'selected' : ''; ?>>Faculty</option>
                    <option value="hod" <?php echo $getRole === 'hod' ? 'selected' : ''; ?>>HOD (Head of Department)</option>
                    <option value="gfm" <?php echo $getRole === 'gfm' ? 'selected' : ''; ?>>GFM</option>
                    <option value="admin" <?php echo $getRole === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                </select>
            </div>

            <div class="form-group">
                <label class="custom-label">PRN or Email Address *</label>
                <input type="text" name="username" class="form-control-custom interactive" placeholder="Enter PRN or Email" required autocomplete="username">
            </div>

            <div class="form-group">
                <label class="custom-label">Password *</label>
                <input type="password" name="password" class="form-control-custom interactive" placeholder="Enter password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-tech interactive" id="submitBtn">
                <span>Login</span> <i class="fa-solid fa-arrow-right"></i>
            </button>

            <div class="helper-links">
                <a href="forgot_password.php" class="interactive">Forgot Password?</a>
                <a href="register.php" class="interactive" style="color: var(--accent-main); border-color: var(--accent-main);">Register / Access</a>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", () => {
        // Connect interactive hover class for cursor styling
        document.querySelectorAll('.interactive, button, a, input, select').forEach(el => {
            el.addEventListener("mouseenter", () => document.body.classList.add("hovering"));
            el.addEventListener("mouseleave", () => document.body.classList.remove("hovering"));
        });

        // Submit Button Feedback
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.style.pointerEvents = 'none';
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin me-2"></i> AUTHENTICATING...';
        });
    });
    </script>
</body>
</html>