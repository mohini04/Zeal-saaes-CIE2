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
    <title>Request Access | SAAES</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Clean Academic Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

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
            padding: 40px 20px;
            -webkit-font-smoothing: antialiased;
        }

        a { text-decoration: none; color: inherit; }

        /* ================= REGISTRATION CARD ================= */
        .request-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            width: 100%;
            max-width: 700px;
            border-radius: var(--radius-lg);
            padding: 3rem;
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
            width: 56px;
            height: 56px;
            background: #eff6ff;
            color: var(--blue-accent);
            border-radius: 50%;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .card-title {
            font-size: 1.75rem;
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
        .section-divider {
            font-size: 1rem;
            font-weight: 600;
            color: var(--navy-primary);
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .section-divider i { color: var(--blue-accent); }

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
            padding: 0.85rem 1.5rem;
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
            margin-top: 2rem;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-primary:active {
            transform: scale(0.98);
        }

        .login-link {
            display: block;
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--text-muted);
            transition: color 0.2s ease;
        }
        .login-link strong { color: var(--blue-accent); font-weight: 600; }
        .login-link:hover strong { text-decoration: underline; }

        /* ================= ALERTS ================= */
        .alert { 
            font-size: 0.9rem; 
            font-weight: 500; 
            border-radius: var(--radius-md); 
            padding: 1rem 1.25rem; 
            margin-bottom: 2rem; 
            display: flex; 
            align-items: center; 
            gap: 0.75rem;
        }
        .alert-danger { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

        @media (max-width: 600px) {
            .request-card { padding: 2rem 1.5rem; }
            .card-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

    <div class="request-card">
        <div class="card-header">
            <div class="sys-icon"><i class="fa-solid fa-user-plus"></i></div>
            <h3 class="card-title">Registration</h3>
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
                    <label class="form-label">PRN Number <span style="color: #ef4444;">*</span></label>
                    <input type="text" name="prn_number" class="form-control-custom" placeholder="e.g. 72210982B" required autocomplete="off">
                </div>
                <div class="col-md-6 form-group">
                    <label class="form-label">Department <span style="color: #ef4444;">*</span></label>
                    <select name="department" class="form-select-custom" required>
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
                    <label class="form-label">Academic Year <span style="color: #ef4444;">*</span></label>
                    <select name="academic_year" class="form-select-custom" required>
                        <option value="" disabled selected>-- Select Year --</option>
                        <option value="FY">First Year (FY)</option>
                        <option value="SY">Second Year (SY)</option>
                        <option value="TY">Third Year (TY)</option>
                        <option value="Final Year">Final Year (B.Tech)</option>
                    </select>
                </div>
                <div class="col-md-6 form-group">
                    <label class="form-label">Class / Division <span style="color: #ef4444;">*</span></label>
                    <select name="division" class="form-select-custom" required>
                        <option value="" disabled selected>-- Select Div --</option>
                        <option value="A">Division A</option>
                        <option value="B">Division B</option>
                        <option value="C">Division C</option>
                        <option value="D">Division D</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Student Full Name <span style="color: #ef4444;">*</span></label>
                <input type="text" name="full_name" class="form-control-custom" placeholder="As per official records" required autocomplete="off">
            </div>

            <div class="form-group">
                <label class="form-label">Student Email <span style="color: #ef4444;">*</span></label>
                <input type="email" name="email" class="form-control-custom" placeholder="student@gmail.com" required autocomplete="off">
            </div>

            <div class="section-divider mt-4"><i class="fa-solid fa-user-shield"></i> Parent / Guardian Details</div>

            <div class="form-group">
                <label class="form-label">Parent / Guardian Name <span style="color: #ef4444;">*</span></label>
                <input type="text" name="parent_name" class="form-control-custom" placeholder="e.g. Robert Doe" required autocomplete="off">
            </div>

            <div class="form-group">
                <label class="form-label">Parent Email Address <span style="color: #ef4444;">*</span></label>
                <input type="email" name="parent_email" class="form-control-custom" placeholder="parent@domain.com" required autocomplete="off">
            </div>

            <button type="submit" class="btn-primary">
                Submit Request <i class="fa-solid fa-arrow-right"></i>
            </button>

            <a href="login.php" class="login-link">
                Already have an account? <strong>Login here</strong>
            </a>
        </form>
    </div>

</body>
</html>