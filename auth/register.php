<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../config/db.php');

// AUTO-PATCH: Safely ensures parent_name and parent_email columns exist in access_requests
function patchAccessRequestsTable($conn) {
    // Ensure table exists first
    $createTableSQL = "CREATE TABLE IF NOT EXISTS `access_requests` (
        `request_id` INT AUTO_INCREMENT PRIMARY KEY,
        `prn_number` VARCHAR(50) NOT NULL UNIQUE,
        `full_name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(150) NOT NULL UNIQUE,
        `department` VARCHAR(100) NOT NULL,
        `parent_name` VARCHAR(100) NOT NULL,
        `parent_email` VARCHAR(150) NOT NULL,
        `status` VARCHAR(20) DEFAULT 'PENDING',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    @mysqli_query($conn, $createTableSQL);

    // Patch columns if table was created previously without them
    $parentCols = [
        "parent_name" => "VARCHAR(100) NOT NULL DEFAULT ''",
        "parent_email" => "VARCHAR(150) NOT NULL DEFAULT ''"
    ];

    foreach ($parentCols as $col => $definition) {
        try {
            $checkCol = @mysqli_query($conn, "SHOW COLUMNS FROM `access_requests` LIKE '$col'");
            if ($checkCol && mysqli_num_rows($checkCol) === 0) {
                @mysqli_query($conn, "ALTER TABLE `access_requests` ADD COLUMN `$col` $definition");
            }
        } catch (Exception $e) {
            // Swallowed gracefully
        }
    }
}

patchAccessRequestsTable($conn);

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name    = trim($_POST['full_name']);
    $prn_number   = strtoupper(trim($_POST['prn_number']));
    $email        = strtolower(trim($_POST['email']));
    $department   = trim($_POST['department']);
    $parent_name  = trim($_POST['parent_name']);
    $parent_email = strtolower(trim($_POST['parent_email']));

    if (!empty($full_name) && !empty($prn_number) && !empty($email) && !empty($department) && !empty($parent_name) && !empty($parent_email)) {
        
        $allowedDomains = ['zeal.in', 'zcoer.edu.in'];
        $emailParts = explode('@', $email);
        $domain = end($emailParts);

        if (!in_array($domain, $allowedDomains)) {
            $error = "Access denied: Student email must be a valid @zeal.in or @zcoer.edu.in address.";
        } else {
            // Check if PRN already active in users table
            $checkUser = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $checkUser->bind_param("s", $prn_number);
            $checkUser->execute();
            if ($checkUser->get_result()->num_rows > 0) {
                $error = "An IDP account has already been issued for PRN: $prn_number.";
            } else {
                // Check if request already pending
                $checkReq = $conn->prepare("SELECT request_id FROM access_requests WHERE prn_number = ? OR email = ?");
                $checkReq->bind_param("ss", $prn_number, $email);
                $checkReq->execute();
                if ($checkReq->get_result()->num_rows > 0) {
                    $error = "A pending access request for PRN ($prn_number) is already under Admin review.";
                } else {
                    $stmt = $conn->prepare("INSERT INTO access_requests (prn_number, full_name, email, department, parent_name, parent_email, status) VALUES (?, ?, ?, ?, ?, ?, 'PENDING')");
                    $stmt->bind_param("ssssss", $prn_number, $full_name, $email, $department, $parent_name, $parent_email);
                    if ($stmt->execute()) {
                        $success = "Access request logged! Student and Parent IDP accounts will be provisioned simultaneously upon Admin approval.";
                    } else {
                        $error = "System write fault submitting request: " . $conn->error;
                    }
                    $stmt->close();
                }
                $checkReq->close();
            }
            $checkUser->close();
        }
    } else {
        $error = "Please fill in all mandatory student and parent parameters.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZCOER // SAAES — Unified Student & Parent IDP Request</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@300;400;500&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">

    <style>
        :root { --bg-base: #010103; --panel-bg: rgba(8, 8, 11, 0.88); --input-bg: rgba(16, 18, 23, 0.75); --silver-border: rgba(255, 255, 255, 0.1); --silver-text: #94a3b8; }
        * { font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: -0.01em; box-sizing: border-box; }
        body { background-color: var(--bg-base); color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 30px; position: relative; overflow-x: hidden; }
        #cometCanvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none; }
        .request-card { background: var(--panel-bg); border: 1px solid var(--silver-border); border-radius: 16px; width: 100%; max-width: 560px; z-index: 5; padding: 40px; backdrop-filter: blur(24px); box-shadow: 0 40px 80px -30px rgba(0,0,0,0.95); }
        .eyebrow { font-family: 'JetBrains Mono', monospace; font-size: 0.66rem; text-transform: uppercase; letter-spacing: 0.14em; color: var(--silver-text); }
        .form-control-custom, .form-select-custom { background-color: var(--input-bg); border: 1px solid var(--silver-border); color: #f1f5f9; border-radius: 8px; padding: 12px 16px 12px 42px; font-size: 0.88rem; width: 100%; transition: all 0.2s ease; }
        .form-control-custom:focus, .form-select-custom:focus { border-color: rgba(255, 255, 255, 0.35); outline: none; box-shadow: none; background-color: var(--input-bg); }
        .form-select-custom option { background-color: #0b0c0e; color: #f1f5f9; }
        .input-container { position: relative; margin-bottom: 16px; }
        .input-icon { position: absolute; top: 38px; left: 16px; color: #475569; font-size: 13px; }
        .btn-action-silver { background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 100%); color: #050508; border: none; border-radius: 8px; padding: 14px; font-weight: 700; font-size: 0.88rem; width: 100%; transition: all 0.2s ease; }
        .btn-action-silver:hover { background: #ffffff; box-shadow: 0 4px 16px rgba(255, 255, 255, 0.2); }
        .custom-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; color: var(--silver-text); margin-bottom: 6px; display: block; }
        .section-divider { border-bottom: 1px solid rgba(255,255,255,0.06); padding-bottom: 6px; margin-bottom: 16px; font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; color: #fff; text-transform: uppercase; letter-spacing: 0.1em; }
    </style>
</head>
<body>

    <canvas id="cometCanvas"></canvas>

    <div class="request-card">
        <div class="mb-4">
            <div class="eyebrow mb-1">ZCOER // SAAES SYSTEM</div>
            <h3 class="fw-bold m-0" style="font-family: 'Space Grotesk', sans-serif;">Request Student & Parent Access</h3>
            <p class="text-secondary small mt-1">Both Student and Parent IDP accounts will be generated upon approval.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger bg-dark text-danger border-danger small p-3 mb-4"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success bg-dark text-success border-success small p-3 mb-4"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="section-divider"><i class="fa-solid fa-user-graduate me-1"></i> Student Credentials</div>
            
            <div class="row">
                <div class="col-md-6 input-container">
                    <label class="custom-label">PRN Number</label>
                    <input type="text" name="prn_number" class="form-control-custom" placeholder="e.g. 72210982B" required autocomplete="off">
                    <i class="fa-solid fa-id-card input-icon"></i>
                </div>
                <div class="col-md-6 input-container">
                    <label class="custom-label">Department</label>
                    <select name="department" class="form-select-custom" required>
                        <option value="" disabled selected>Select Dept</option>
                        <option value="Computer Engineering">Computer Engineering</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="AI & Data Science">AI & Data Science</option>
                        <option value="ENTC">ENTC</option>
                        <option value="Mechanical">Mechanical</option>
                    </select>
                    <i class="fa-solid fa-building-columns input-icon"></i>
                </div>
            </div>

            <div class="input-container">
                <label class="custom-label">Student Full Name</label>
                <input type="text" name="full_name" class="form-control-custom" placeholder="Full name as per records" required autocomplete="off">
                <i class="fa-solid fa-user input-icon"></i>
            </div>

            <div class="input-container">
                <label class="custom-label">Student Institutional Email (@zeal.in)</label>
                <input type="email" name="email" class="form-control-custom" placeholder="student@zeal.in" required autocomplete="off">
                <i class="fa-solid fa-envelope input-icon"></i>
            </div>

            <div class="section-divider mt-4"><i class="fa-solid fa-users me-1"></i> Parent / Guardian Credentials</div>

            <div class="input-container">
                <label class="custom-label">Parent / Guardian Full Name</label>
                <input type="text" name="parent_name" class="form-control-custom" placeholder="e.g. Robert Doe" required autocomplete="off">
                <i class="fa-solid fa-user-shield input-icon"></i>
            </div>

            <div class="input-container">
                <label class="custom-label">Parent Email Address</label>
                <input type="email" name="parent_email" class="form-control-custom" placeholder="parent@gmail.com" required autocomplete="off">
                <i class="fa-solid fa-at input-icon"></i>
            </div>

            <button type="submit" class="btn btn-action-silver mt-3">
                Transmit Dual IDP Request &rarr;
            </button>

            <div class="text-center mt-4">
                <a href="login.php" class="text-secondary small text-decoration-none">Already have IDP credentials? <strong class="text-white">Login Terminal</strong></a>
            </div>
        </form>
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