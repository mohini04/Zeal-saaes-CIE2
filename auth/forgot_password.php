<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../config/db.php');

$step = 1; // Step 1: Find Account | Step 2: Answer Security Question | Step 3: Success
$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    // STEP 1: FIND USER ACCOUNT & FETCH SECURITY QUESTION
    if ($action === 'verify_identity') {
        $input_identity = strtolower(trim($_POST['username'] ?? ''));
        $selected_role   = trim($_POST['role'] ?? '');

        if (!empty($input_identity) && !empty($selected_role)) {
            $stmt = $conn->prepare("SELECT user_id, name, username, security_question, security_answer FROM users WHERE (LOWER(TRIM(username)) = ? OR LOWER(TRIM(email)) = ?) AND role = ?");
            $stmt->bind_param("sss", $input_identity, $input_identity, $selected_role);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                if (!empty($user['security_question']) && !empty($user['security_answer'])) {
                    $_SESSION['reset_user_id'] = $user['user_id'];
                    $_SESSION['reset_question'] = $user['security_question'];
                    $_SESSION['reset_answer_hash'] = strtolower(trim($user['security_answer']));
                    $step = 2;
                } else {
                    $error = "Security recovery question not configured for this account. Contact System Admin.";
                }
            } else {
                $error = "No active $selected_role account found matching identity '$input_identity'.";
            }
            $stmt->close();
        } else {
            $error = "Please fill in all identity verification fields.";
        }
    }

    // STEP 2: VERIFY ANSWER & RESET PASSWORD
    if ($action === 'reset_password') {
        $user_id          = $_SESSION['reset_user_id'] ?? 0;
        $answer_input     = strtolower(trim($_POST['security_answer'] ?? ''));
        $new_password     = trim($_POST['new_password'] ?? '');
        $confirm_password = trim($_POST['confirm_password'] ?? '');

        if ($user_id > 0) {
            $stored_answer = $_SESSION['reset_answer_hash'] ?? '';

            if ($answer_input === $stored_answer) {
                if (!empty($new_password) && $new_password === $confirm_password) {
                    if (strlen($new_password) >= 6) {
                        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

                        $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                        $updateStmt->bind_param("si", $hashed_password, $user_id);

                        if ($updateStmt->execute()) {
                            unset($_SESSION['reset_user_id'], $_SESSION['reset_question'], $_SESSION['reset_answer_hash']);
                            $success = "Passkey successfully reset! Redirecting to login terminal...";
                            $step = 3;
                            echo "<script>
                                    setTimeout(function(){
                                        window.location.href = 'login.php';
                                    }, 2000);
                                  </script>";
                        } else {
                            $error = "System write fault updating password.";
                            $step = 2;
                        }
                        $updateStmt->close();
                    } else {
                        $error = "Password security requirement failed: Minimum 6 characters required.";
                        $step = 2;
                    }
                } else {
                    $error = "New passkey confirmation mismatch.";
                    $step = 2;
                }
            } else {
                $error = "Security question verification answer is incorrect.";
                $step = 2;
            }
        } else {
            $error = "Session expired. Please restart the recovery process.";
            $step = 1;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Recovery | SAAES</title>
    
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

        /* ================= REGISTRATION/LOGIN CARD ================= */
        .request-card {
            background: rgba(255, 255, 255, 0.95);
            border: var(--border-harsh);
            width: 100%;
            max-width: 500px;
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
            padding: 11px 15px; /* Offset border width to prevent jitter */
        }
        .form-control-custom::placeholder { color: var(--text-light); font-family: var(--font-mono); font-size: 0.85rem; }

        .question-box {
            background: var(--bg-panel);
            border: 1px dashed var(--text-tech);
            border-left: 4px solid var(--accent-main);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        /* ================= BUTTON ================= */
        .btn-tech {
            font-family: var(--font-mono); font-weight: 700; font-size: 1rem; text-transform: uppercase;
            padding: 1rem 1.2rem; display: inline-flex; align-items: center; justify-content: center; gap: 0.75rem;
            background: var(--text-dark); color: #fff; border: 2px solid var(--text-dark);
            position: relative; overflow: hidden; z-index: 1; cursor: pointer;
            clip-path: polygon(15px 0, 100% 0, 100% calc(100% - 15px), calc(100% - 15px) 100%, 0 100%, 0 15px);
            transition: color 0.3s; width: 100%; margin-top: 0.5rem;
        }
        .btn-tech::before {
            content: ''; position: absolute; top: 0; left: -100%; width: 100%; height: 100%;
            background: var(--accent-main); z-index: -1; transition: left 0.3s cubic-bezier(0.7, 0, 0.3, 1);
        }
        .btn-tech:hover { color: #fff; border-color: var(--accent-main); }
        .btn-tech:hover::before { left: 0; }
        .btn-tech i { transition: transform 0.3s; }
        .btn-tech:hover i { transform: translateX(5px); }

        .login-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            font-family: var(--font-mono);
            font-size: 0.85rem;
            color: var(--text-tech);
            transition: color 0.3s ease;
            text-transform: uppercase;
        }
        .login-link strong { color: var(--accent-main); font-weight: 700; border-bottom: 1px solid var(--accent-main);}
        .login-link:hover strong { color: var(--text-dark); border-color: var(--text-dark); }

        /* Alerts styling */
        .alert { 
            font-family: var(--font-mono); font-size: 0.85rem; font-weight: 700; text-transform: uppercase; 
            border: 2px solid transparent; border-radius: 0; padding: 1rem 1.2rem; margin-bottom: 1.5rem; 
            display: flex; align-items: center; gap: 0.75rem;
        }
        .alert-danger { background: rgba(239, 68, 68, 0.05); color: #ef4444; border-color: #ef4444; }
        .alert-success { background: rgba(16, 185, 129, 0.05); color: #10b981; border-color: #10b981; }

        @media (max-width: 768px) {
            .request-card { padding: 2rem; clip-path: none; box-shadow: 8px 8px 0px rgba(124, 58, 237, 0.15); border-radius: 0;}
            .card-title { font-size: 1.6rem; }
        }
    </style>
</head>
<body>

    <div class="request-card">
        <div class="mb-4">
            <div class="sys-tag"><i class="fa-solid fa-bolt"></i> SYS.INIT // RECOVERY</div>
            <h3 class="card-title">Account Recovery</h3>
            <p class="card-subtitle">Verify your identity to reset your password.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- STEP 1: IDENTITY LOOKUP -->
            <form method="POST" action="forgot_password.php">
                <input type="hidden" name="action" value="verify_identity">

                <div class="form-group">
                    <label class="custom-label">Account Role *</label>
                    <select name="role" class="form-select-custom interactive" required>
                        <option value="" disabled selected>-- Select Role --</option>
                        <option value="Student">Student</option>
                        <option value="Parent">Parent / Guardian</option>
                        <option value="Faculty">Faculty</option>
                        <option value="HOD">HOD (Head of Department)</option>
                        <option value="GFM">GFM (Guardian Faculty Member)</option>
                        <option value="Admin">Administrator</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="custom-label">PRN or Email Address *</label>
                    <input type="text" name="username" class="form-control-custom interactive" placeholder="Enter PRN or Email" required autocomplete="off">
                </div>

                <button type="submit" class="btn-tech interactive">
                    Verify Identity <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

        <?php elseif ($step === 2): ?>
            <!-- STEP 2: SECURITY QUESTION & PASSWORD RESET -->
            <form method="POST" action="forgot_password.php">
                <input type="hidden" name="action" value="reset_password">

                <div class="question-box">
                    <div class="custom-label" style="color: var(--text-tech); margin-bottom: 0.2rem;">Security Question:</div>
                    <strong style="font-family: var(--font-body); font-size: 1rem; color: var(--text-dark);">
                        <i class="fa-solid fa-circle-question" style="color: var(--accent-main);"></i> <?php echo htmlspecialchars($_SESSION['reset_question'] ?? ''); ?>
                    </strong>
                </div>

                <div class="form-group">
                    <label class="custom-label">Your Answer *</label>
                    <input type="text" name="security_answer" class="form-control-custom interactive" placeholder="Enter your answer" required autocomplete="off">
                </div>

                <div class="form-group">
                    <label class="custom-label">New Password *</label>
                    <input type="password" name="new_password" class="form-control-custom interactive" placeholder="Minimum 6 characters" required>
                </div>

                <div class="form-group">
                    <label class="custom-label">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control-custom interactive" placeholder="Retype new password" required>
                </div>

                <button type="submit" class="btn-tech interactive">
                    Reset Password <i class="fa-solid fa-check"></i>
                </button>
            </form>
        <?php endif; ?>

        <a href="login.php" class="login-link interactive">
            Return to <strong>Login</strong>
        </a>
    </div>

    <!-- VANILLA JS FOR PREMIUM INTERACTIONS -->
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        // Connect interactive hover class for cursor styling
        document.querySelectorAll('.interactive, button, a, input, select, textarea').forEach(el => {
            el.addEventListener("mouseenter", () => document.body.classList.add("hovering"));
            el.addEventListener("mouseleave", () => document.body.classList.remove("hovering"));
        });
    });
    </script>
</body>
</html>