<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once(__DIR__ . "/../config/db.php");

$error = "";
$role = "Faculty";
$redirect_url = "../faculty_dashboard.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_identity = strtolower(trim($_POST["username"]));
    $password       = trim($_POST["password"]);
    
    if (!empty($input_identity) && !empty($password)) {
        $stmt = $conn->prepare("SELECT user_id, name, password, role, is_first_login FROM users WHERE (LOWER(TRIM(username)) = ? OR LOWER(TRIM(email)) = ?) AND role = ?");
        $stmt->bind_param("sss", $input_identity, $input_identity, $role);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user["password"])) {
                $_SESSION["user_id"]   = $user["user_id"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["role"]      = $user["role"];

                if ((int)($user["is_first_login"] ?? 0) === 1) {
                    header("Location: setup_profile.php");
                    exit();
                }

                header("Location: " . $redirect_url);
                exit();
            } else {
                $error = "Invalid passkey credentials provided.";
            }
        } else {
            $error = "No active $role account found matching identity '$input_identity'.";
        }
        $stmt->close();
    } else {
        $error = "Please fill in all login fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZCOER // SAAES — <?php echo $role; ?> Login Terminal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@300;400;500&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg-base: #010103; --panel-bg: rgba(8, 8, 11, 0.88); --input-bg: rgba(16, 18, 23, 0.75); --silver-border: rgba(255, 255, 255, 0.1); --silver-text: #94a3b8; }
        * { font-family: "Plus Jakarta Sans", sans-serif; letter-spacing: -0.01em; box-sizing: border-box; }
        body { background-color: var(--bg-base); color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; position: relative; overflow-x: hidden; }
        #cometCanvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none; }
        .login-card { background: var(--panel-bg); border: 1px solid var(--silver-border); border-radius: 16px; width: 100%; max-width: 440px; z-index: 5; padding: 36px; backdrop-filter: blur(24px); box-shadow: 0 40px 80px -30px rgba(0,0,0,0.95); }
        .eyebrow { font-family: "JetBrains Mono", monospace; font-size: 0.66rem; text-transform: uppercase; letter-spacing: 0.14em; color: var(--silver-text); }
        .form-control-custom { background-color: var(--input-bg); border: 1px solid var(--silver-border); color: #f1f5f9; border-radius: 8px; padding: 12px 16px 12px 42px; font-size: 0.88rem; width: 100%; transition: all 0.2s ease; }
        .form-control-custom:focus { border-color: rgba(255, 255, 255, 0.35); outline: none; box-shadow: none; background-color: var(--input-bg); }
        .input-container { position: relative; margin-bottom: 18px; }
        .input-icon { position: absolute; top: 50%; left: 16px; transform: translateY(-50%); color: #475569; font-size: 13px; }
        .btn-action-silver { background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 100%); color: #050508; border: none; border-radius: 8px; padding: 14px; font-weight: 700; font-size: 0.88rem; width: 100%; transition: all 0.2s ease; }
        .btn-action-silver:hover { background: #ffffff; box-shadow: 0 4px 16px rgba(255, 255, 255, 0.2); }
    </style>
</head>
<body>
    <canvas id="cometCanvas"></canvas>
    <div class="login-card">
        <div class="mb-4 text-center">
            <img src="zeal logo.jpg" alt="ZCOER Logo" style="width:48px; filter: invert(1) grayscale(1) brightness(1.8);" class="mb-3">
            <div class="eyebrow mb-1">ZCOER // SAAES PORTAL</div>
            <h3 class="fw-bold m-0" style="font-family: 'Space Grotesk', sans-serif;"><?php echo $role; ?> Access Terminal</h3>
        </div>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger bg-dark text-danger border-danger small p-3 mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="input-container">
                <input type="text" name="username" class="form-control-custom" placeholder="ID or Email Address" required autocomplete="off">
                <i class="fa-solid fa-id-badge input-icon"></i>
            </div>
            <div class="input-container">
                <input type="password" name="password" class="form-control-custom" placeholder="Passkey" required>
                <i class="fa-solid fa-lock input-icon"></i>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <a href="forgot_password.php" class="text-secondary small text-decoration-none">Forgot passkey?</a>
            </div>
            <button type="submit" class="btn btn-action-silver">Authenticate & Enter &rarr;</button>
            <?php if ($role == "Student" || $role == "Parent"): ?>
            <div class="text-center mt-4">
                <a href="register.php" class="text-secondary small text-decoration-none">New student? <strong class="text-white">Request Dual IDP Access</strong></a>
            </div>
            <?php endif; ?>
        </form>
    </div>
    <script>
        const canvas = document.getElementById("cometCanvas"); const ctx = canvas.getContext("2d");
        let stars = []; const numStars = 160; function resize() { canvas.width = window.innerWidth; canvas.height = window.innerHeight; }
        window.addEventListener("resize", resize); resize();
        class Star { constructor() { this.x = Math.random() * canvas.width; this.y = Math.random() * canvas.height; this.size = Math.random() * 1.2 + 0.3; this.speed = Math.random() * 0.4 + 0.1; this.alpha = Math.random() * 0.5 + 0.1; } update() { this.y -= this.speed; if (this.y < 0) { this.y = canvas.height; this.x = Math.random() * canvas.width; } } draw() { ctx.fillStyle = `rgba(255, 255, 255, ${this.alpha})`; ctx.beginPath(); ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2); ctx.fill(); } }
        for (let i = 0; i < numStars; i++) stars.push(new Star());
        function loop() { ctx.fillStyle = "#010103"; ctx.fillRect(0, 0, canvas.width, canvas.height); stars.forEach(s => { s.update(); s.draw(); }); requestAnimationFrame(loop); } loop();
    </script>
</body>
</html>
