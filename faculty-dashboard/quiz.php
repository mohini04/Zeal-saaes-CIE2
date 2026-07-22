<?php
// quiz.php - Dedicated Quiz Activity Management Page
require_once 'config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 1;
$activity = get_activity_by_id($id);

if (!$activity) {
    header("Location: index.php");
    exit;
}

// Handle activity edit POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_activity_details') {
    update_activity($id, [
        'title' => trim($_POST['title']),
        'type' => $activity['type'],
        'course' => trim($_POST['course']),
        'subject' => trim($_POST['subject']),
        'batch' => trim($_POST['batch']),
        'deadline' => trim($_POST['deadline']),
        'total_marks' => (int)$_POST['total_marks'],
        'description' => trim($_POST['description'])
    ]);
    header("Location: quiz.php?id=" . $id);
    exit;
}

$page_title = $activity['title'] . " - Quiz Portal";
include 'includes/header.php';

// Mock questions for preview
if (!isset($_SESSION['quiz_questions_' . $id])) {
    $_SESSION['quiz_questions_' . $id] = [
        [
            'q' => 'Which HTML5 element is used to specify a header for a document or section?',
            'a' => '<head>', 'b' => '<header>', 'c' => '<top>', 'd' => '<section-head>',
            'correct' => 'b', 'points' => 2
        ],
        [
            'q' => 'Which CSS Flexbox property controls alignment along the cross-axis?',
            'a' => 'justify-content', 'b' => 'flex-direction', 'c' => 'align-items', 'd' => 'align-content',
            'correct' => 'c', 'points' => 2
        ],
        [
            'q' => 'In JavaScript, which method adds one or more elements to the end of an array?',
            'a' => 'push()', 'b' => 'append()', 'c' => 'concat()', 'd' => 'insert()',
            'correct' => 'a', 'points' => 2
        ]
    ];
}

$questions = $_SESSION['quiz_questions_' . $id];

// Handle new question submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_question') {
    $_SESSION['quiz_questions_' . $id][] = [
        'q' => trim($_POST['question']),
        'a' => trim($_POST['opt_a']),
        'b' => trim($_POST['opt_b']),
        'c' => trim($_POST['opt_c']),
        'd' => trim($_POST['opt_d']),
        'correct' => $_POST['correct'],
        'points' => (int)$_POST['points']
    ];
    header("Location: quiz.php?id=" . $id);
    exit;
}
?>

<a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

<!-- Activity Header Box -->
<div class="page-header-box" style="border-left: 5px solid #6366f1;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
            <span style="font-size: 0.8rem; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: 6px; background: rgba(99, 102, 241, 0.25); color: #818cf8; border: 1px solid rgba(129, 140, 248, 0.4);">
                Sr. No. #<?php echo isset($activity['sr_no']) ? $activity['sr_no'] : $id; ?>
            </span>
            <span style="font-size: 0.8rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 6px; background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.4);">
                <i class="fa-solid fa-bookmark"></i> <?php echo isset($activity['unit']) ? $activity['unit'] : 'Unit 1'; ?>
            </span>
            <span class="activity-type-badge" style="background: rgba(99, 102, 241, 0.2); color: #6366f1;">
                <i class="fa-solid fa-clipboard-question"></i> Quiz Portal
            </span>
            <span style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo htmlspecialchars($activity['course']); ?> (<?php echo htmlspecialchars($activity['batch']); ?>)</span>
        </div>
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($activity['title']); ?></h1>
        <p style="color: var(--text-secondary); max-width: 800px;"><?php echo htmlspecialchars($activity['description']); ?></p>
    </div>
    
    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.75rem;">
        <div style="display: flex; gap: 0.5rem;">
            <button class="btn btn-outline" style="border-color: #f59e0b; color: #f59e0b;" onclick="openModal('editActivityDetailsModal')">
                <i class="fa-solid fa-pen-to-square"></i> Edit Details
            </button>
            <button class="btn btn-primary" onclick="openModal('addQuestionModal')">
                <i class="fa-solid fa-plus"></i> Add Question
            </button>
        </div>
        <span style="font-size: 0.85rem; color: var(--text-secondary);">
            Deadline: <strong style="color: #fff;"><?php echo date('M d, Y H:i', strtotime($activity['deadline'])); ?></strong>
        </span>
    </div>
</div>

<!-- Grid Layout: Questions & Submissions -->
<div style="display: grid; grid-template-columns: 1.2fr 1fr; gap: 2rem; margin-bottom: 3rem;">

    <!-- Question Bank Section -->
    <div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <h3><i class="fa-solid fa-layer-group" style="color: #6366f1;"></i> Quiz Question Bank (<?php echo count($questions); ?> Questions)</h3>
        </div>

        <?php foreach ($questions as $idx => $q): ?>
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; margin-bottom: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem;">
                <h4 style="font-size: 1.05rem; line-height: 1.4;">Q<?php echo ($idx + 1); ?>: <?php echo htmlspecialchars($q['q']); ?></h4>
                <span style="background: rgba(99, 102, 241, 0.2); color: #6366f1; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.78rem; font-weight: 600;">
                    <?php echo $q['points']; ?> Points
                </span>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.6rem; font-size: 0.9rem;">
                <div style="padding: 0.5rem 0.8rem; border-radius: var(--radius-sm); background: <?php echo $q['correct'] === 'a' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(255,255,255,0.03)'; ?>; border: 1px solid <?php echo $q['correct'] === 'a' ? '#10b981' : 'var(--border-color)'; ?>;">
                    A. <?php echo htmlspecialchars($q['a']); ?> <?php if ($q['correct'] === 'a') echo '✓'; ?>
                </div>
                <div style="padding: 0.5rem 0.8rem; border-radius: var(--radius-sm); background: <?php echo $q['correct'] === 'b' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(255,255,255,0.03)'; ?>; border: 1px solid <?php echo $q['correct'] === 'b' ? '#10b981' : 'var(--border-color)'; ?>;">
                    B. <?php echo htmlspecialchars($q['b']); ?> <?php if ($q['correct'] === 'b') echo '✓'; ?>
                </div>
                <div style="padding: 0.5rem 0.8rem; border-radius: var(--radius-sm); background: <?php echo $q['correct'] === 'c' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(255,255,255,0.03)'; ?>; border: 1px solid <?php echo $q['correct'] === 'c' ? '#10b981' : 'var(--border-color)'; ?>;">
                    C. <?php echo htmlspecialchars($q['c']); ?> <?php if ($q['correct'] === 'c') echo '✓'; ?>
                </div>
                <div style="padding: 0.5rem 0.8rem; border-radius: var(--radius-sm); background: <?php echo $q['correct'] === 'd' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(255,255,255,0.03)'; ?>; border: 1px solid <?php echo $q['correct'] === 'd' ? '#10b981' : 'var(--border-color)'; ?>;">
                    D. <?php echo htmlspecialchars($q['d']); ?> <?php if ($q['correct'] === 'd') echo '✓'; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Student Attempts Table -->
    <div>
        <h3 style="margin-bottom: 1rem;"><i class="fa-solid fa-user-check" style="color: #10b981;"></i> Student Quiz Submissions</h3>
        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Sr. No.</th>
                        <th>Student Name</th>
                        <th>Score</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>1</strong></td>
                        <td><strong>Aarav Sharma</strong><br><small style="color: var(--text-muted);">CS202401</small></td>
                        <td><span style="color: #10b981; font-weight: 700;">18 / 20</span></td>
                        <td><span style="color: #10b981;">Submitted</span></td>
                    </tr>
                    <tr>
                        <td><strong>2</strong></td>
                        <td><strong>Priya Patel</strong><br><small style="color: var(--text-muted);">CS202412</small></td>
                        <td><span style="color: #10b981; font-weight: 700;">20 / 20</span></td>
                        <td><span style="color: #10b981;">Submitted</span></td>
                    </tr>
                    <tr>
                        <td><strong>3</strong></td>
                        <td><strong>Rahul Verma</strong><br><small style="color: var(--text-muted);">CS202418</small></td>
                        <td><span style="color: #f59e0b; font-weight: 700;">14 / 20</span></td>
                        <td><span style="color: #10b981;">Submitted</span></td>
                    </tr>
                    <tr>
                        <td><strong>4</strong></td>
                        <td><strong>Ananya Roy</strong><br><small style="color: var(--text-muted);">CS202425</small></td>
                        <td><span style="color: #ef4444; font-weight: 700;">--</span></td>
                        <td><span style="color: var(--text-muted);">Pending</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Question -->
<div class="modal-overlay" id="addQuestionModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-plus-circle" style="color: #6366f1;"></i> Add Quiz Question</h3>
            <button class="close-btn" onclick="closeModal('addQuestionModal')">&times;</button>
        </div>
        <form action="quiz.php?id=<?php echo $id; ?>" method="POST">
            <input type="hidden" name="action" value="add_question">
            <div class="form-group">
                <label>Question Text *</label>
                <textarea name="question" class="form-control" rows="2" placeholder="Type the question..." required></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div class="form-group">
                    <label>Option A *</label>
                    <input type="text" name="opt_a" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Option B *</label>
                    <input type="text" name="opt_b" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Option C *</label>
                    <input type="text" name="opt_c" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Option D *</label>
                    <input type="text" name="opt_d" class="form-control" required>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div class="form-group">
                    <label>Correct Option *</label>
                    <select name="correct" class="form-control" style="background: #1e293b;">
                        <option value="a">Option A</option>
                        <option value="b">Option B</option>
                        <option value="c">Option C</option>
                        <option value="d">Option D</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Points *</label>
                    <input type="number" name="points" class="form-control" value="2" min="1" max="10">
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem;">
                <button type="button" class="btn btn-outline" onclick="closeModal('addQuestionModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Question</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Activity Details -->
<div class="modal-overlay" id="editActivityDetailsModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-pen-to-square" style="color: #f59e0b;"></i> Edit Quiz Activity Details</h3>
            <button class="close-btn" onclick="closeModal('editActivityDetailsModal')">&times;</button>
        </div>
        <form action="quiz.php?id=<?php echo $id; ?>" method="POST">
            <input type="hidden" name="action" value="edit_activity_details">
            <div class="form-group">
                <label>Title *</label>
                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($activity['title']); ?>" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div class="form-group">
                    <label>Subject *</label>
                    <input type="text" name="subject" class="form-control" value="<?php echo htmlspecialchars(isset($activity['subject']) ? $activity['subject'] : 'BEE'); ?>" required>
                </div>
                <div class="form-group">
                    <label>Course Code *</label>
                    <input type="text" name="course" class="form-control" value="<?php echo htmlspecialchars($activity['course']); ?>" required>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div class="form-group">
                    <label>Target Batch *</label>
                    <input type="text" name="batch" class="form-control" value="<?php echo htmlspecialchars($activity['batch']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Total Marks *</label>
                    <input type="number" name="total_marks" class="form-control" value="<?php echo htmlspecialchars($activity['total_marks']); ?>" required>
                </div>
            </div>
            <div class="form-group">
                <label>Deadline *</label>
                <input type="datetime-local" name="deadline" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($activity['deadline'])); ?>" required>
            </div>
            <div class="form-group">
                <label>Instructions / Description</label>
                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($activity['description']); ?></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1rem;">
                <button type="button" class="btn btn-outline" onclick="closeModal('editActivityDetailsModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: #f59e0b;">Save & Update</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
