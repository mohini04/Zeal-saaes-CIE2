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
    <title>ZCOER // SAAES — Account Recovery</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@300;400;500&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

    <style>
        :root { --bg-base: #010103; --panel-bg: rgba(8, 8, 11, 0.88); --input-bg: rgba(16, 18, 23, 0.75); --silver-border: rgba(255, 255, 255, 0.1); --silver-text: #94a3b8; }
        * { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.01em; box-sizing: border-box; }
        body { background-color: var(--bg-base); color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; position: relative; overflow-x: hidden; }
        #cometCanvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none; }
        .recovery-card { background: var(--panel-bg); border: 1px solid var(--silver-border); border-radius: 16px; width: 100%; max-width: 460px; z-index: 5; padding: 36px; backdrop-filter: blur(24px); box-shadow: 0 40px 80px -30px rgba(0,0,0,0.95); }
        .eyebrow { font-family: 'JetBrains Mono', monospace; font-size: 0.66rem; text-transform: uppercase; letter-spacing: 0.14em; color: var(--silver-text); }
        .form-control-custom, .form-select-custom { background-color: var(--input-bg); border: 1px solid var(--silver-border); color: #f1f5f9; border-radius: 8px; padding: 12px 16px 12px 42px; font-size: 0.88rem; width: 100%; transition: all 0.2s ease; }
        .form-control-custom:focus, .form-select-custom:focus { border-color: rgba(255, 255, 255, 0.35); outline: none; box-shadow: none; background-color: var(--input-bg); }
        .form-select-custom option { background-color: #0b0c0e; color: #f1f5f9; }
        .input-container { position: relative; margin-bottom: 18px; }
        .input-icon { position: absolute; top: 50%; left: 16px; transform: translateY(-50%); color: #475569; font-size: 13px; }
        .btn-action-silver { background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 100%); color: #050508; border: none; border-radius: 8px; padding: 14px; font-weight: 700; font-size: 0.88rem; width: 100%; transition: all 0.2s ease; }
        .btn-action-silver:hover { background: #ffffff; box-shadow: 0 4px 16px rgba(255, 255, 255, 0.2); }
        .question-box { background: rgba(255,255,255,0.03); border: 1px dashed var(--silver-border); border-radius: 8px; padding: 12px 16px; font-size: 0.88rem; color: #cbd5e1; margin-bottom: 18px; }
    </style>
</head>
<body>

    <canvas id="cometCanvas"></canvas>

    <div class="recovery-card">
        <div class="mb-4">
            <div class="eyebrow mb-1">ZCOER // RECOVERY PROTOCOL</div>
            <h3 class="fw-bold m-0" style="font-family: 'Space Grotesk', sans-serif;">Passkey Reset Gate</h3>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger bg-dark text-danger border-danger small p-3 mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success bg-dark text-success border-success small p-3 mb-4"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- STEP 1: IDENTITY LOOKUP -->
            <form method="POST" action="forgot_password.php">
                <input type="hidden" name="action" value="verify_identity">

                <div class="input-container">
                    <select name="role" class="form-select-custom" required>
                        <option value="" disabled selected>Select Portal Role Context</option>
                        <option value="Student">Student Portal</option>
                        <option value="Parent">Parent / Guardian Portal</option>
                        <option value="Faculty">Faculty Command</option>
                        <option value="HOD">HOD (Head of Department)</option>
                        <option value="GFM">GFM (Guardian Faculty Member)</option>
                        <option value="Admin">System Administrator</option>
                    </select>
                    <i class="fa-solid fa-user-gear input-icon"></i>
                </div>

                <div class="input-container">
                    <input type="text" name="username" class="form-control-custom" placeholder="PRN (Student) or Email Address" required autocomplete="off">
                    <i class="fa-solid fa-id-badge input-icon"></i>
                </div>

                <button type="submit" class="btn btn-action-silver">
                    Verify Identity & Continue &rarr;
                </button>
            </form>
        <?php elseif ($step === 2): ?>
            <!-- STEP 2: SECURITY QUESTION & PASSWORD RESET -->
            <form method="POST" action="forgot_password.php">
                <input type="hidden" name="action" value="reset_password">

                <div class="question-box">
                    <div class="eyebrow text-secondary mb-1">Security Verification Question:</div>
                    <strong><i class="fa-solid fa-circle-question me-1"></i> <?php echo htmlspecialchars($_SESSION['reset_question'] ?? ''); ?></strong>
                </div>

                <div class="input-container">
                    <input type="text" name="security_answer" class="form-control-custom" placeholder="Your Security Answer" required autocomplete="off">
                    <i class="fa-solid fa-shield-halved input-icon"></i>
                </div>

                <div class="input-container">
                    <input type="password" name="new_password" class="form-control-custom" placeholder="New Passkey (Min 6 chars)" required>
                    <i class="fa-solid fa-lock input-icon"></i>
                </div>

                <div class="input-container">
                    <input type="password" name="confirm_password" class="form-control-custom" placeholder="Confirm New Passkey" required>
                    <i class="fa-solid fa-shield-check input-icon"></i>
                </div>

                <button type="submit" class="btn btn-action-silver">
                    Reset Passkey & Complete &rarr;
                </button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="login.php" class="text-secondary small text-decoration-none">&larr; Return to <strong class="text-white">Login Terminal</strong></a>
        </div>
    </div>

    <script>
        const canvas = document.getElementById('cometCanvas'); const ctx = canvas.getContext('2d');
        let stars = []; const numStars = 160; function resize() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        window.addEventListener('resize', resize); resize();
        class Star { constructor() { this.x = Math.random() * canvas.width; this.y = Math.random() * canvas.height; this.size = Math.random() * 1.2 + 0.3; this.speed = Math.random() * 0.4 + 0.1; this.alpha = Math.random() * 0.5 + 0.1; } update() { this.y -= this.speed; if (this.y < 0) { this.y = canvas.height; this.x = Math.random() * canvas.width; } } draw() { ctx.fillStyle = `rgba(255, 255, 255, ${this.alpha})`; ctx.beginPath(); ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2); ctx.fill(); } }
        for (let i = 0; i < numStars; i++) stars.push(new Star());
        function loop() { ctx.fillStyle = '#010103'; ctx.fillRect(0, 0, canvas.width, canvas.height); stars.forEach(s => { s.update(); s.draw(); }); requestAnimationFrame(loop); } loop();
    </script>
</body>
</html>