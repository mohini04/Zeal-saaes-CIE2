<?php
// create_activity.php - Dedicated Full-Screen Activity Creation Page
require_once 'config/db.php';

$message = '';
$pre_subject = isset($_GET['subject']) ? trim($_GET['subject']) : 'BEE';
$pre_type = isset($_GET['type']) ? trim($_GET['type']) : 'quiz';
$pre_unit = isset($_GET['unit']) ? trim($_GET['unit']) : 'Unit 1';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_activity') {
    $title = trim($_POST['title']);
    $type = trim($_POST['type']);
    $course = trim($_POST['course']);
    $subject = trim($_POST['subject']);
    $unit = trim($_POST['unit']);
    $batch = trim($_POST['batch']);
    $deadline = trim($_POST['deadline']);
    $total_marks = (int)$_POST['total_marks'];
    $description = trim($_POST['description']);

    if (!empty($title) && !empty($type)) {
        $new_id = add_new_activity([
            'title' => $title,
            'type' => $type,
            'course' => $course,
            'subject' => $subject,
            'unit' => $unit,
            'batch' => $batch,
            'deadline' => $deadline,
            'total_marks' => $total_marks,
            'description' => $description
        ]);
        
        $redirect_page = $type . '.php?id=' . $new_id;
        header("Location: " . $redirect_page);
        exit;
    } else {
        $message = "Please fill in all required fields.";
    }
}

$page_title = "Create New Activity Project (Full Screen)";
include 'includes/header.php';
?>

<div style="margin-bottom: 1rem;">
    <a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>
</div>

<!-- FULL SCREEN CREATION WORKSPACE CARD -->
<div class="single-window-card" style="border-top: 5px solid #6366f1; padding: 2.5rem;">
    <div style="margin-bottom: 2rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1.25rem;">
        <h1 style="font-size: 2rem; color: #fff; margin-bottom: 0.5rem;">
            <i class="fa-solid fa-circle-plus" style="color: #6366f1;"></i> Launch New Activity Project
        </h1>
        <p style="color: var(--text-secondary); font-size: 1rem;">
            Fill out the details below to assign a new activity to your students. No popups — full screen workspace for fast setup.
        </p>
    </div>

    <?php if (!empty($message)): ?>
        <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="create_activity.php" method="POST">
        <input type="hidden" name="action" value="create_activity">
        
        <!-- Row 1: Project Title -->
        <div class="form-group" style="margin-bottom: 1.75rem;">
            <label for="title" style="font-size: 1rem; font-weight: 600; color: #fff;">Activity Project Title *</label>
            <input type="text" id="title" name="title" class="form-control" style="font-size: 1.1rem; padding: 0.9rem 1.2rem;" placeholder="e.g. BEE Unit 1 Kirchhoff's Laws Quiz / Chemistry Nanomaterials Poster" required value="<?php echo htmlspecialchars($pre_subject . ' ' . $pre_unit . ' Activity'); ?>">
        </div>

        <!-- Row 2: Subject, Syllabus Unit, Category -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 1.75rem;">
            <div class="form-group">
                <label for="subject" style="font-weight: 600;">Subject *</label>
                <select id="subject" name="subject" class="form-control" style="padding: 0.85rem;" required onchange="autoFillCourse(this.value)">
                    <option value="BEE" <?php echo $pre_subject == 'BEE' ? 'selected' : ''; ?>>⚡ BEE (Basic Electrical Engg)</option>
                    <option value="Chemistry" <?php echo $pre_subject == 'Chemistry' ? 'selected' : ''; ?>>🧪 Engineering Chemistry</option>
                    <option value="Physics" <?php echo $pre_subject == 'Physics' ? 'selected' : ''; ?>>⚛️ Engineering Physics</option>
                    <option value="Maths" <?php echo $pre_subject == 'Maths' ? 'selected' : ''; ?>>📐 Engineering Mathematics</option>
                    <option value="Computer Science" <?php echo $pre_subject == 'Computer Science' ? 'selected' : ''; ?>>💻 Computer Science & Engg</option>
                </select>
            </div>

            <div class="form-group">
                <label for="unit" style="font-weight: 600;">Syllabus Unit *</label>
                <select id="unit" name="unit" class="form-control" style="padding: 0.85rem;" required>
                    <option value="Unit 1" <?php echo $pre_unit == 'Unit 1' ? 'selected' : ''; ?>>📌 Unit 1: Fundamentals</option>
                    <option value="Unit 2" <?php echo $pre_unit == 'Unit 2' ? 'selected' : ''; ?>>📌 Unit 2: Advanced Analysis</option>
                    <option value="Unit 3" <?php echo $pre_unit == 'Unit 3' ? 'selected' : ''; ?>>📌 Unit 3: Applications & Design</option>
                    <option value="Unit 4" <?php echo $pre_unit == 'Unit 4' ? 'selected' : ''; ?>>📌 Unit 4: Special Topics & Lab</option>
                    <option value="Unit 5" <?php echo $pre_unit == 'Unit 5' ? 'selected' : ''; ?>>📌 Unit 5: Project & Synthesis</option>
                </select>
            </div>

            <div class="form-group">
                <label for="type" style="font-weight: 600;">Activity Category *</label>
                <select id="type" name="type" class="form-control" style="padding: 0.85rem;" required>
                    <option value="quiz" <?php echo $pre_type == 'quiz' ? 'selected' : ''; ?>>📝 Quiz / Online Test</option>
                    <option value="poster_making" <?php echo $pre_type == 'poster_making' ? 'selected' : ''; ?>>🎨 Poster Making</option>
                    <option value="ppt" <?php echo $pre_type == 'ppt' ? 'selected' : ''; ?>>📊 PPT Presentation</option>
                    <option value="case_study" <?php echo $pre_type == 'case_study' ? 'selected' : ''; ?>>🔍 Case Study Assignment</option>
                    <option value="gd" <?php echo $pre_type == 'gd' ? 'selected' : ''; ?>>💬 Group Discussion (GD)</option>
                    <option value="mini_project" <?php echo $pre_type == 'mini_project' ? 'selected' : ''; ?>>🚀 Mini Project Portal</option>
                </select>
            </div>
        </div>

        <!-- Row 3: Course Code, Target Batch -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.75rem;">
            <div class="form-group">
                <label for="course" style="font-weight: 600;">Course Code / Full Name *</label>
                <input type="text" id="course" name="course" class="form-control" style="padding: 0.85rem;" value="BEE101 - Basic Electrical Engineering" required>
            </div>

            <div class="form-group">
                <label for="batch" style="font-weight: 600;">Target Student Batch / Section *</label>
                <input type="text" id="batch" name="batch" class="form-control" style="padding: 0.85rem;" value="2025-29 (Sec A & B)" required>
            </div>
        </div>

        <!-- Row 4: Deadline, Total Marks -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.75rem;">
            <div class="form-group">
                <label for="deadline" style="font-weight: 600;">Submission Deadline *</label>
                <input type="datetime-local" id="deadline" name="deadline" class="form-control" style="padding: 0.85rem;" value="<?php echo date('Y-m-d\TH:i', strtotime('+7 days')); ?>" required>
            </div>

            <div class="form-group">
                <label for="total_marks" style="font-weight: 600;">Total Max Marks *</label>
                <input type="number" id="total_marks" name="total_marks" class="form-control" style="padding: 0.85rem;" value="50" min="5" max="500" required>
            </div>
        </div>

        <!-- Row 5: Detailed Instructions -->
        <div class="form-group" style="margin-bottom: 2rem;">
            <label for="description" style="font-weight: 600;">Activity Instructions & Submission Guidelines</label>
            <textarea id="description" name="description" class="form-control" rows="5" placeholder="Provide syllabus guidelines, file submission formats, scoring criteria, and rules for students..."></textarea>
        </div>

        <!-- Form Action Buttons -->
        <div style="display: flex; justify-content: flex-end; gap: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1.75rem;">
            <a href="index.php" class="btn btn-outline" style="padding: 0.85rem 1.75rem; font-size: 1rem;">
                Cancel & Return
            </a>
            <button type="submit" class="btn btn-primary" style="padding: 0.85rem 2.25rem; font-size: 1rem;">
                <i class="fa-solid fa-paper-plane"></i> Submit & Launch Activity Workspace
            </button>
        </div>
    </form>
</div>

<script>
function autoFillCourse(subj) {
    const courseInput = document.getElementById('course');
    const courseMap = {
        'BEE': 'BEE101 - Basic Electrical Engineering',
        'Chemistry': 'CH101 - Engineering Chemistry',
        'Physics': 'PH101 - Engineering Physics',
        'Maths': 'MA101 - Engineering Mathematics',
        'Computer Science': 'CS302 - Computer Science & Engg'
    };
    if (courseMap[subj]) {
        courseInput.value = courseMap[subj];
    }
}
</script>

<?php include 'includes/footer.php'; ?>
