<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Anti-caching headers to prevent browser back-button access after logout
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

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
    
    <!-- Clean Academic Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            /* Traditional Academic Color Palette */
            --bg-body: #f8fafc;
            --bg-card: #ffffff;
            --navy-primary: #0f172a;
            --blue-accent: #2563eb;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            
            --radius-md: 8px;
            --radius-lg: 12px;
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            
            --font-main: 'Inter', system-ui, -apple-system, sans-serif;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            -webkit-font-smoothing: antialiased;
        }

        a { text-decoration: none; color: inherit; }

        /* ================= LOGIN CARD ================= */
        .login-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            width: 100%;
            max-width: 450px;
            border-radius: var(--radius-lg);
            padding: 3rem 2.5rem;
            box-shadow: var(--shadow-md);
        }

        .card-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .sys-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background: #eff6ff;
            color: var(--blue-accent);
            border-radius: 50%;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--navy-primary);
            margin-bottom: 0.5rem;
        }

        .card-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        /* ================= FORM ELEMENTS ================= */
        .form-group { margin-bottom: 1.25rem; }
        
        .form-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 0.4rem;
            display: block;
        }

        .form-control-custom, .form-select-custom {
            background-color: var(--bg-body);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 0.75rem 1rem;
            font-family: var(--font-main);
            font-size: 0.95rem;
            width: 100%;
            transition: all 0.2s ease;
            border-radius: var(--radius-md);
            -webkit-appearance: none;
        }
        
        .form-select-custom {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
            padding-right: 2.5rem;
        }
        
        .form-control-custom:focus, .form-select-custom:focus {
            border-color: var(--blue-accent);
            background-color: var(--bg-card);
            outline: none;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-control-custom::placeholder { color: #94a3b8; font-size: 0.9rem; }

        /* ================= BUTTON ================= */
        .btn-primary {
            font-family: var(--font-main);
            font-weight: 600;
            font-size: 1rem;
            padding: 0.85rem 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: var(--blue-accent);
            color: #fff;
            border: none;
            border-radius: var(--radius-md);
            width: 100%;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.1s ease;
            margin-top: 1.5rem;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-primary:active {
            transform: scale(0.98);
        }

        /* ================= HELPER LINKS ================= */
        .helper-links {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            font-size: 0.85rem;
        }
        
        .helper-links a {
            color: var(--text-muted);
            font-weight: 500;
            transition: color 0.2s ease;
        }
        
        .helper-links a:hover {
            color: var(--navy-primary);
        }
        
        .helper-links a.primary-link {
            color: var(--blue-accent);
            font-weight: 600;
        }

        .helper-links a.primary-link:hover {
            text-decoration: underline;
        }

        /* ================= ALERTS ================= */
        .alert { 
            font-size: 0.9rem; 
            font-weight: 500; 
            border-radius: var(--radius-md); 
            padding: 1rem 1.25rem; 
            margin-bottom: 1.5rem; 
            display: flex; 
            align-items: center; 
            gap: 0.75rem;
        }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        @media (max-width: 600px) {
            .login-card { padding: 2rem 1.5rem; }
            .card-title { font-size: 1.4rem; }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="card-header">
            <div class="sys-icon"><i class="fa-solid fa-lock"></i></div>
            <h3 class="card-title">Portal Login</h3>
            <p class="card-subtitle">Enter your credentials to access your dashboard.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            
            <div class="form-group">
                <label class="form-label">Account Role <span style="color: #ef4444;">*</span></label>
                <?php $getRole = strtolower($_GET['role'] ?? ''); ?>
                <select name="role" class="form-select-custom" required>
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
                <label class="form-label">PRN or Email Address <span style="color: #ef4444;">*</span></label>
                <input type="text" name="username" class="form-control-custom" placeholder="Enter PRN or Email" required autocomplete="username">
            </div>

            <div class="form-group">
                <label class="form-label">Password <span style="color: #ef4444;">*</span></label>
                <input type="password" name="password" class="form-control-custom" placeholder="Enter password" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-primary" id="submitBtn">
                Login <i class="fa-solid fa-arrow-right"></i>
            </button>

            <div class="helper-links">
                <a href="forgot_password.php">Forgot Password?</a>
                <a href="register.php" class="primary-link">Request Access</a>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", () => {
        // Submit Button Feedback
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.style.pointerEvents = 'none';
            btn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Authenticating...';
        });
    });
    </script>
</body>
</html>