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
        `academic_year` VARCHAR(50) NOT NULL DEFAULT 'FY',
        `division` VARCHAR(50) NOT NULL DEFAULT 'A',
        `parent_name` VARCHAR(100) NOT NULL,
        `parent_email` VARCHAR(150) NOT NULL,
        `status` VARCHAR(20) DEFAULT 'PENDING',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    @mysqli_query($conn, $createTableSQL);

    // Patch columns if table was created previously without them
    $parentCols = [
        "parent_name" => "VARCHAR(100) NOT NULL DEFAULT ''",
        "parent_email" => "VARCHAR(150) NOT NULL DEFAULT ''",
        "academic_year" => "VARCHAR(50) NOT NULL DEFAULT 'FY'",
        "division" => "VARCHAR(50) NOT NULL DEFAULT 'A'"
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
    $academic_year= trim($_POST['academic_year']);
    $division     = strtoupper(trim($_POST['division']));
    $parent_name  = trim($_POST['parent_name']);
    $parent_email = strtolower(trim($_POST['parent_email']));

    if (!empty($full_name) && !empty($prn_number) && !empty($email) && !empty($department) && !empty($academic_year) && !empty($division) && !empty($parent_name) && !empty($parent_email)) {
        
        $allowedDomains = ['zeal.in', 'zcoer.edu.in'];
        $emailParts = explode('@', $email);
        $domain = end($emailParts);

        if (!in_array($domain, $allowedDomains)) {
            $error = "Access denied: Student email must be a valid @zeal.in or @zcoer.edu.in address.";
        } else {
            // 1. Check if PRN or Student Email already active in users table
            $checkUserPrn = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $checkUserPrn->bind_param("s", $prn_number);
            $checkUserPrn->execute();
            $userPrnExists = $checkUserPrn->get_result()->num_rows > 0;
            $checkUserPrn->close();

            $checkUserEmail = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $checkUserEmail->bind_param("s", $email);
            $checkUserEmail->execute();
            $userEmailExists = $checkUserEmail->get_result()->num_rows > 0;
            $checkUserEmail->close();

            if ($userPrnExists) {
                $error = "An IDP account has already been issued for PRN: $prn_number.";
            } elseif ($userEmailExists) {
                $error = "An IDP account has already been issued for Student Email: $email.";
            } else {
                // 2. Check if a PENDING access request already exists for this PRN or Email
                $checkReqPrn = $conn->prepare("SELECT request_id FROM access_requests WHERE prn_number = ? AND status = 'PENDING'");
                $checkReqPrn->bind_param("s", $prn_number);
                $checkReqPrn->execute();
                $pendingPrnExists = $checkReqPrn->get_result()->num_rows > 0;
                $checkReqPrn->close();

                $checkReqEmail = $conn->prepare("SELECT request_id FROM access_requests WHERE email = ? AND status = 'PENDING'");
                $checkReqEmail->bind_param("s", $email);
                $checkReqEmail->execute();
                $pendingEmailExists = $checkReqEmail->get_result()->num_rows > 0;
                $checkReqEmail->close();

                if ($pendingPrnExists) {
                    $error = "A pending access request for PRN ($prn_number) is already under Admin review.";
                } elseif ($pendingEmailExists) {
                    $error = "A pending access request for Student Email ($email) is already under Admin review.";
                } else {
                    // 3. Check if email is already tied to a different PRN
                    $checkDiffPrn = $conn->prepare("SELECT prn_number FROM access_requests WHERE email = ? AND prn_number != ?");
                    $checkDiffPrn->bind_param("ss", $email, $prn_number);
                    $checkDiffPrn->execute();
                    $diffPrnRes = $checkDiffPrn->get_result();
                    $emailTiedToOtherPrn = ($diffPrnRes->num_rows > 0) ? $diffPrnRes->fetch_assoc()['prn_number'] : null;
                    $checkDiffPrn->close();

                    if ($emailTiedToOtherPrn) {
                        $error = "The student email ($email) is already associated with PRN: $emailTiedToOtherPrn.";
                    } else {
                        // 4. Upsert: Check if an existing request row exists for this PRN to update or insert new row
                        $checkExisting = $conn->prepare("SELECT request_id FROM access_requests WHERE prn_number = ?");
                        $checkExisting->bind_param("s", $prn_number);
                        $checkExisting->execute();
                        $existRes = $checkExisting->get_result();

                        if ($existRes->num_rows > 0) {
                            $row = $existRes->fetch_assoc();
                            $reqId = $row['request_id'];
                            $stmt = $conn->prepare("UPDATE access_requests SET prn_number = ?, full_name = ?, email = ?, department = ?, academic_year = ?, division = ?, parent_name = ?, parent_email = ?, status = 'PENDING', created_at = CURRENT_TIMESTAMP WHERE request_id = ?");
                            $stmt->bind_param("ssssssssi", $prn_number, $full_name, $email, $department, $academic_year, $division, $parent_name, $parent_email, $reqId);
                        } else {
                            $stmt = $conn->prepare("INSERT INTO access_requests (prn_number, full_name, email, department, academic_year, division, parent_name, parent_email, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'PENDING')");
                            $stmt->bind_param("ssssssss", $prn_number, $full_name, $email, $department, $academic_year, $division, $parent_name, $parent_email);
                        }
                        $checkExisting->close();

                        if ($stmt->execute()) {
                            $success = "Access request logged! Student and Parent IDP accounts will be provisioned simultaneously upon Admin approval.";
                        } else {
                            $error = "System write fault submitting request: " . $conn->error;
                        }
                        $stmt->close();
                    }
                }
            }
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
    <title>Register | SAAES</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Professional Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=JetBrains+Mono:wght@100;400;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Minimal Sci-Fi White & Purple Palette */
            --bg-base: #ffffff;
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
            /* Architectural Blueprint Grid (Static Layer) */
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

        /* ================= 3D PLEXUS CANVAS LAYER ================= */
        #bg-canvas {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            z-index: 0;
            pointer-events: none;
        }

        /* ================= REGISTRATION CARD ================= */
        .request-card {
            background: rgba(255, 255, 255, 0.95);
            border: var(--border-harsh);
            width: 100%;
            max-width: 650px;
            z-index: 5;
            padding: 3rem;
            position: relative;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            box-shadow: 20px 20px 0px rgba(124, 58, 237, 0.15);
            clip-path: polygon(0 0, calc(100% - 30px) 0, 100% 30px, 100% 100%, 30px 100%, 0 calc(100% - 30px));
            animation: fadeUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
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
        .section-divider {
            font-family: var(--font-mono);
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--text-dark);
            border-bottom: 2px solid var(--text-dark);
            padding-bottom: 8px;
            margin-bottom: 20px;
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
            border-color: var(--accent-main);
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
            position: relative; overflow: hidden; z-index: 1;
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
            border: 2px solid transparent; border-radius: 0; padding: 1rem 1.2rem; margin-bottom: 2rem; 
            display: flex; align-items: center; gap: 0.75rem;
        }
        .alert-danger { background: rgba(239, 68, 68, 0.05); color: #ef4444; border-color: #ef4444; }
        .alert-success { background: rgba(16, 185, 129, 0.05); color: #10b981; border-color: #10b981; }

        @media (max-width: 768px) {
            .request-card { padding: 2rem; clip-path: none; box-shadow: 8px 8px 0px rgba(124, 58, 237, 0.15); border-radius: 0;}
            .card-title { font-size: 1.8rem; }
        }
    </style>
</head>
<body>

    <!-- INTERACTIVE 3D PLEXUS MATRIX BACKGROUND -->
    <canvas id="bg-canvas"></canvas>

    <div class="request-card">
        <div class="mb-4">
            <div class="sys-tag"><i class="fa-solid fa-user-plus"></i> Registration</div>
            <h3 class="card-title">Access Request</h3>
            <p class="card-subtitle">Submit your details to request student and parent accounts.</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            
            <div class="section-divider"><i class="fa-solid fa-user-graduate"></i> Student Details</div>
            
            <div class="row">
                <div class="col-md-6 form-group">
                    <label class="custom-label">PRN Number *</label>
                    <input type="text" name="prn_number" class="form-control-custom interactive" placeholder="e.g. 72210982B" required autocomplete="off">
                </div>
                <div class="col-md-6 form-group">
                    <label class="custom-label">Department *</label>
                    <select name="department" class="form-select-custom interactive" required>
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
            </div>

            <div class="row">
                <div class="col-md-6 form-group">
                    <label class="custom-label">Academic Year *</label>
                    <select name="academic_year" class="form-select-custom interactive" required>
                        <option value="" disabled selected>-- Select Year --</option>
                        <option value="FY">First Year (FY)</option>
                        <option value="SY">Second Year (SY)</option>
                        <option value="TY">Third Year (TY)</option>
                        <option value="Final Year">Final Year (B.Tech)</option>
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label class="custom-label">Class / Division *</label>
                    <select name="division" class="form-select-custom interactive" required>
                        <option value="" disabled selected>-- Select Div --</option>
                        <option value="A">Division A</option>
                        <option value="B">Division B</option>
                        <option value="C">Division C</option>
                        <option value="D">Division D</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="custom-label">Student Full Name *</label>
                <input type="text" name="full_name" class="form-control-custom interactive" placeholder="As per official records" required autocomplete="off">
            </div>

            <div class="form-group">
                <label class="custom-label">College Email Address *</label>
                <input type="email" name="email" class="form-control-custom interactive" placeholder="student@zeal.in" required autocomplete="off">
            </div>

            <div class="section-divider mt-5"><i class="fa-solid fa-user-shield"></i> Parent / Guardian Details</div>

            <div class="form-group">
                <label class="custom-label">Parent / Guardian Name *</label>
                <input type="text" name="parent_name" class="form-control-custom interactive" placeholder="e.g. Robert Doe" required autocomplete="off">
            </div>

            <div class="form-group">
                <label class="custom-label">Parent Email Address *</label>
                <input type="email" name="parent_email" class="form-control-custom interactive" placeholder="parent@domain.com" required autocomplete="off">
            </div>

            <button type="submit" class="btn-tech interactive">
                Submit Request <i class="fa-solid fa-arrow-right"></i>
            </button>

            <a href="login.php" class="login-link interactive">
                Already have an account? <strong>Login here</strong>
            </a>
        </form>
    </div>

    <!-- VANILLA JS FOR PREMIUM INTERACTIONS & 3D CANVAS -->
    <script>
    document.addEventListener("DOMContentLoaded", () => {
        
        let mouseX = window.innerWidth / 2;
        let mouseY = window.innerHeight / 2;

        // Hover interaction for custom cursor
        if (window.matchMedia("(pointer: fine)").matches) {
            window.addEventListener("mousemove", (e) => {
                mouseX = e.clientX; mouseY = e.clientY;
            });

            document.querySelectorAll('.interactive, input, select, button, a').forEach(el => {
                el.addEventListener("mouseenter", () => document.body.classList.add("hovering"));
                el.addEventListener("mouseleave", () => document.body.classList.remove("hovering"));
            });
        }

        // ==========================================
        // 3D INTERACTIVE PLEXUS MATRIX BACKGROUND
        // ==========================================
        const canvas = document.getElementById('bg-canvas');
        const ctx = canvas.getContext('2d');
        let width, height;
        let particles = [];

        function resize() {
            width = canvas.width = window.innerWidth;
            height = canvas.height = window.innerHeight;
        }
        window.addEventListener('resize', resize);
        resize();

        class Particle {
            constructor() {
                // Spread particles slightly beyond the screen
                this.x = (Math.random() - 0.5) * (width * 1.5);
                this.y = (Math.random() - 0.5) * (height * 1.5);
                // Simulated Z depth for parallax
                this.z = Math.random() * 2;
                
                // Drift velocity
                this.vx = (Math.random() - 0.5) * 0.6;
                this.vy = (Math.random() - 0.5) * 0.6;
                this.baseSize = Math.random() * 2 + 1;
            }

            update(mx, my) {
                this.x += this.vx;
                this.y += this.vy;

                // Gentle screen wrap
                if (this.x < -width) this.x = width;
                if (this.x > width) this.x = -width;
                if (this.y < -height) this.y = height;
                if (this.y > height) this.y = -height;

                // Mouse Repulsion & Parallax Math
                const dx = mx - (this.x + width/2);
                const dy = my - (this.y + height/2);
                const dist = Math.sqrt(dx * dx + dy * dy);

                // Nodes further back (higher Z) move less
                this.projX = this.x + width/2 + (dx * this.z * 0.05);
                this.projY = this.y + height/2 + (dy * this.z * 0.05);

                // Direct mouse repulsion
                if (dist < 200) {
                    this.projX -= (dx / dist) * (200 - dist) * 0.1;
                    this.projY -= (dy / dist) * (200 - dist) * 0.1;
                }
            }

            draw() {
                // Size changes based on Z depth to fake 3D
                const renderSize = this.baseSize * (1 + this.z * 0.5);
                ctx.beginPath();
                ctx.arc(this.projX, this.projY, renderSize, 0, Math.PI * 2);
                ctx.fillStyle = `rgba(124, 58, 237, ${0.15 + (this.z * 0.15)})`;
                ctx.fill();
            }
        }

        // Initialize Nodes
        const numNodes = window.innerWidth > 768 ? 120 : 60; // Fewer nodes on mobile
        for (let i = 0; i < numNodes; i++) {
            particles.push(new Particle());
        }

        function animateMatrix() {
            ctx.clearRect(0, 0, width, height);

            for (let i = 0; i < particles.length; i++) {
                particles[i].update(mouseX, mouseY);
                particles[i].draw();

                // Draw connecting lines to nearby particles
                for (let j = i + 1; j < particles.length; j++) {
                    const dx = particles[i].projX - particles[j].projX;
                    const dy = particles[i].projY - particles[j].projY;
                    const dist = Math.sqrt(dx * dx + dy * dy);

                    // Threshold distance for connections
                    if (dist < 180) {
                        ctx.beginPath();
                        ctx.moveTo(particles[i].projX, particles[i].projY);
                        ctx.lineTo(particles[j].projX, particles[j].projY);
                        // Opacity fades as they get further apart
                        ctx.strokeStyle = `rgba(124, 58, 237, ${0.12 * (1 - dist / 180)})`;
                        ctx.lineWidth = 1;
                        ctx.stroke();
                    }
                }

                // Draw energetic connection from mouse to nearby nodes
                const mxDistX = mouseX - particles[i].projX;
                const mxDistY = mouseY - particles[i].projY;
                const mDist = Math.sqrt(mxDistX * mxDistX + mxDistY * mxDistY);

                if (mDist < 250) {
                    ctx.beginPath();
                    ctx.moveTo(particles[i].projX, particles[i].projY);
                    ctx.lineTo(mouseX, mouseY);
                    ctx.strokeStyle = `rgba(99, 102, 241, ${0.3 * (1 - mDist / 250)})`;
                    ctx.lineWidth = 1.5;
                    ctx.stroke();
                }
            }
            requestAnimationFrame(animateMatrix);
        }

        animateMatrix(); // Start loop
    });
    </script>
</body>
</html>