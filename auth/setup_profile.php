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
    switch ($actual_role) {
        case 'Admin': header("Location: admin_dashboard.php"); break;
        case 'Faculty': 
        case 'HOD':
<<<<<<< HEAD
        case 'GFM': header("Location: ../faculty-dashboard/index.php"); break;
=======
        case 'GFM': header("Location: faculty_dashboard.php"); break;
>>>>>>> c111bf448285e9533500973e107c27a15c1da2a1
        case 'Parent': header("Location: parent_dashboard.php"); break;
        default: header("Location: student_dashboard.php"); break;
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

    $roll_no  = ($actual_role === 'Student') ? trim($_POST['roll_no'] ?? '') : NULL;
    $division = ($actual_role === 'Student') ? trim($_POST['division'] ?? '') : NULL;

    $isValid = !empty($new_password) && !empty($confirm_password) && !empty($security_q) && !empty($security_a);
    if ($actual_role === 'Student') {
        if (empty($roll_no) || empty($division)) {
            $isValid = false;
        }
    }

    if ($isValid) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

                $updateStmt = $conn->prepare("UPDATE users SET password = ?, security_question = ?, security_answer = ?, roll_no = ?, division = ?, phone = ?, is_first_login = 0 WHERE user_id = ?");
                $updateStmt->bind_param("ssssssi", $hashed_password, $security_q, $security_a, $roll_no, $division, $phone, $user_id);

                if ($updateStmt->execute()) {
                    $success = "Passkey and recovery protocol successfully established. Redirecting...";
                    
                    $targetUrl = "student_dashboard.php";
                    if ($actual_role === 'Admin') $targetUrl = "admin_dashboard.php";
<<<<<<< HEAD
                    elseif (in_array($actual_role, ['Faculty', 'HOD', 'GFM'])) $targetUrl = "../faculty_dashboard.php";
=======
                    elseif (in_array($actual_role, ['Faculty', 'HOD', 'GFM'])) $targetUrl = "faculty_dashboard.php";
>>>>>>> c111bf448285e9533500973e107c27a15c1da2a1
                    elseif ($actual_role === 'Parent') $targetUrl = "parent_dashboard.php";

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
    <title>ZCOER // SAAES — Terminal Setup Gate</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@300;400;500&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

    <style>
        :root { --bg-base: #010103; --panel-bg: rgba(8, 8, 11, 0.88); --input-bg: rgba(16, 18, 23, 0.75); --silver-border: rgba(255, 255, 255, 0.1); --silver-text: #94a3b8; }
        * { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.01em; box-sizing: border-box; }
        body { background-color: var(--bg-base); color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 30px; position: relative; overflow-x: hidden; }
        #cometCanvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none; }
        .terminal-container { position: relative; width: 100%; max-width: 560px; z-index: 5; }
        .glass-panel { background: var(--panel-bg); border: 1px solid var(--silver-border); border-radius: 16px; backdrop-filter: blur(24px); box-shadow: 0 40px 80px -30px rgba(0,0,0,0.95); padding: 40px; }
        .eyebrow { font-family: 'JetBrains Mono', monospace; font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.14em; color: var(--silver-text); }
        .terminal-header { border-bottom: 1px solid rgba(255, 255, 255, 0.06); padding-bottom: 20px; margin-bottom: 28px; }
        .terminal-title { font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 1.4rem; color: #fff; }
        .form-control-custom, .form-select-custom { background-color: var(--input-bg); border: 1px solid var(--silver-border); color: #f1f5f9; border-radius: 8px; padding: 12px 16px 12px 44px; font-size: 0.88rem; width: 100%; transition: all 0.2s ease; }
        .form-control-custom:focus, .form-select-custom:focus { border-color: rgba(255, 255, 255, 0.35); outline: none; box-shadow: none; background-color: var(--input-bg); }
        .form-select-custom option { background-color: #0b0c0e; color: #f1f5f9; }
        .input-container { position: relative; }
        .input-icon { position: absolute; top: 50%; left: 16px; transform: translateY(-50%); color: #475569; font-size: 13px; }
        .btn-action-silver { background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 100%); color: #050508; border: none; border-radius: 8px; padding: 14px; font-weight: 700; font-size: 0.88rem; width: 100%; transition: all 0.2s ease; }
        .btn-action-silver:hover { background: #ffffff; box-shadow: 0 4px 16px rgba(255, 255, 255, 0.2); }
        .section-label { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; color: var(--silver-text); margin-bottom: 12px; display: block; }
    </style>
</head>
<body>

    <canvas id="cometCanvas"></canvas>

    <div class="terminal-container">
        <div class="glass-panel">
            <div class="terminal-header d-flex justify-content-between align-items-center">
                <div>
                    <div class="eyebrow mb-1">ZCOER // FIRST LOGIN ONBOARDING</div>
                    <div class="terminal-title"><?php echo htmlspecialchars($actual_role); ?> Account Setup</div>
                    <p class="text-secondary small mb-0">Identity: <strong><?php echo htmlspecialchars($userData['name']); ?></strong> (<?php echo htmlspecialchars($userData['username']); ?>)</p>
                </div>
                <span class="badge bg-dark border border-secondary text-white-50"><?php echo strtoupper($actual_role); ?></span>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger bg-dark text-danger border-danger small p-3 mb-4"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success bg-dark text-success border-success small p-3 mb-4"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="setup_profile.php">
                <!-- Section 1: Security Passkey -->
                <span class="section-label"><i class="fa-solid fa-key me-1"></i> 1. Update Default Access Passkey</span>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="input-container">
                            <input type="password" name="new_password" class="form-control-custom" placeholder="New Private Passkey" required>
                            <i class="fa-solid fa-lock input-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-container">
                            <input type="password" name="confirm_password" class="form-control-custom" placeholder="Confirm Passkey" required>
                            <i class="fa-solid fa-shield-check input-icon"></i>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Account Recovery -->
                <span class="section-label"><i class="fa-solid fa-life-ring me-1"></i> 2. Password Recovery Setup</span>
                <div class="mb-3">
                    <div class="input-container">
                        <select name="security_question" class="form-select-custom" required>
                            <option value="" disabled selected>Select Recovery Security Question</option>
                            <option value="What was the name of your first school?">What was the name of your first school?</option>
                            <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                            <option value="What city were you born in?">What city were you born in?</option>
                            <option value="What was the name of your first pet?">What was the name of your first pet?</option>
                        </select>
                        <i class="fa-solid fa-circle-question input-icon"></i>
                    </div>
                </div>
                <div class="mb-4">
                    <div class="input-container">
                        <input type="text" name="security_answer" class="form-control-custom" placeholder="Security Answer" required autocomplete="off">
                        <i class="fa-solid fa-shield-halved input-icon"></i>
                    </div>
                </div>

                <!-- Section 3: Role-Adaptive Metadata -->
                <?php if ($actual_role === 'Student'): ?>
                    <span class="section-label"><i class="fa-solid fa-graduation-cap me-1"></i> 3. Academic Profile Metadata</span>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="input-container">
                                <input type="text" name="roll_no" class="form-control-custom" placeholder="Roll No (e.g. 45)" required autocomplete="off">
                                <i class="fa-solid fa-id-badge input-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-container">
                                <select name="division" class="form-select-custom" required>
                                    <option value="" disabled selected>Division</option>
                                    <option value="A">Div A</option>
                                    <option value="B">Div B</option>
                                    <option value="C">Div C</option>
                                </select>
                                <i class="fa-solid fa-users-rectangle input-icon"></i>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="input-container">
                                <input type="text" name="phone" class="form-control-custom" placeholder="Phone No" autocomplete="off">
                                <i class="fa-solid fa-phone input-icon"></i>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <span class="section-label"><i class="fa-solid fa-address-book me-1"></i> 3. Contact Parameters</span>
                    <div class="mb-4">
                        <div class="input-container">
                            <input type="text" name="phone" class="form-control-custom" placeholder="Primary Contact Phone Number" autocomplete="off">
                            <i class="fa-solid fa-phone input-icon"></i>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit" class="btn btn-action-silver">
                    Finalise Setup & Enter Workspace &rarr;
                </button>
            </form>
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