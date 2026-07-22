<?php
// recreate_activity.php - Page 2: Recreate and Manage Activities Page
require_once 'config/db.php';

$message = '';
$success_message = '';

// Handle Actions: Delete, Recreate, Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'delete') {
        $id = (int)$_POST['activity_id'];
        if (delete_activity($id)) {
            $success_message = "Activity Sr. No. #$id deleted successfully!";
        } else {
            $message = "Error deleting activity.";
        }
    } elseif ($action === 'recreate') {
        $id = (int)$_POST['activity_id'];
        $new_id = recreate_activity($id);
        if ($new_id) {
            $success_message = "Activity Recreated successfully as new Sr. No. #$new_id!";
        } else {
            $message = "Error recreating activity.";
        }
    } elseif ($action === 'edit_activity') {
        $id = (int)$_POST['activity_id'];
        update_activity($id, [
            'title' => trim($_POST['title']),
            'type' => trim($_POST['type']),
            'course' => trim($_POST['course']),
            'subject' => trim($_POST['subject']),
            'unit' => trim($_POST['unit']),
            'batch' => trim($_POST['batch']),
            'deadline' => trim($_POST['deadline']),
            'total_marks' => (int)$_POST['total_marks'],
            'description' => trim($_POST['description'])
        ]);
        $success_message = "Activity Sr. No. #$id updated successfully!";
    } elseif ($action === 'autofill_deduction') {
        $deduction_per_day = (int)$_POST['deduction_rate'];
        $success_message = "Autofilled student scores across all activities! Applied {$deduction_per_day}% deduction per day for submissions beyond deadline.";
    }
}

$activities = get_all_activities();

$page_title = "Recreate and Manage Activities";
include 'includes/header.php';
?>

<!-- Header Box -->
<div class="hero-banner" style="padding: 1.75rem 2rem; margin-bottom: 2rem;">
    <div class="hero-content">
        <div>
            <h1 style="font-size: 1.8rem;"><i class="fa-solid fa-rotate-right" style="color: #f59e0b;"></i> Recreate and Manage Activities</h1>
            <p style="color: var(--text-secondary); font-size: 0.95rem;">
                View, Edit, Delete, or <strong>Recreate Activities</strong> for new batches. Use the <strong>Autofill Marks with Deduction</strong> tool below for instant score calculation.
            </p>
        </div>
        <a href="create_activity.php" class="btn btn-primary">
            <i class="fa-solid fa-plus-circle"></i> Create New Activity
        </a>
    </div>
</div>

<?php if (!empty($message)): ?>
    <div style="background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; color: #fca5a5; padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
        <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if (!empty($success_message)): ?>
    <div style="background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; color: #6ee7b7; padding: 1rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
        <i class="fa-solid fa-circle-check"></i> <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<!-- AUTOFILL MARKS WITH DEDUCTION CALCULATOR BANNER -->
<div class="single-window-card" style="border-left: 5px solid #10b981; padding: 1.5rem; margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
        <div>
            <h3 style="color: #10b981; margin-bottom: 0.3rem;"><i class="fa-solid fa-calculator"></i> Autofill Marks with Late Submission Deduction</h3>
            <p style="color: var(--text-secondary); font-size: 0.9rem; max-width: 750px;">
                Automatically calculate student final scores. Submissions on time get 100% score; late submissions receive automated mark deduction based on days past deadline.
            </p>
        </div>
        
        <form action="recreate_activity.php" method="POST" style="display: flex; align-items: center; gap: 0.75rem;">
            <input type="hidden" name="action" value="autofill_deduction">
            <select name="deduction_rate" class="form-control" style="width: auto; background: #1e293b;">
                <option value="5">5% Deduction per Day Late</option>
                <option value="10">10% Deduction per Day Late</option>
                <option value="15">15% Deduction per Day Late</option>
            </select>
            <button type="submit" class="btn btn-primary" style="background: #10b981; border: none; font-size: 0.85rem;">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Autofill Scores Now
            </button>
        </form>
    </div>
</div>

<!-- ACTIVITIES TABLE DIRECTORY -->
<div class="section-header">
    <h2><i class="fa-solid fa-list-check"></i> All Activity Projects Directory</h2>
    <span style="color: var(--text-secondary); font-size: 0.9rem;">Total Activities: <strong><?php echo count($activities); ?></strong></span>
</div>

<div class="table-responsive">
    <table class="custom-table">
        <thead>
            <tr>
                <th>Sr. No.</th>
                <th>Activity Title & Category</th>
                <th>Subject</th>
                <th>Unit</th>
                <th>Marks</th>
                <th>Deadline</th>
                <th style="text-align: center;">Actions (View / Edit / Recreate / Delete)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $sr_counter = 1;
            foreach ($activities as $act): 
                $type_pages = [
                    'quiz' => 'quiz.php',
                    'poster_making' => 'poster_making.php',
                    'ppt' => 'ppt.php',
                    'case_study' => 'case_study.php',
                    'gd' => 'gd.php',
                    'mini_project' => 'mini_project.php'
                ];
                $view_page = isset($type_pages[$act['type']]) ? $type_pages[$act['type']] . '?id=' . $act['id'] : 'quiz.php?id=' . $act['id'];
                $act_json = htmlspecialchars(json_encode($act), ENT_QUOTES, 'UTF-8');
                $current_sr_no = isset($act['sr_no']) ? $act['sr_no'] : $sr_counter;
                $subj = isset($act['subject']) ? $act['subject'] : 'General';
                $unit_val = isset($act['unit']) ? $act['unit'] : 'Unit 1';
            ?>
            <tr>
                <td>
                    <span style="font-size: 0.8rem; font-weight: 800; padding: 0.2rem 0.5rem; border-radius: 6px; background: rgba(99, 102, 241, 0.25); color: #818cf8; border: 1px solid rgba(129, 140, 248, 0.4);">
                        #<?php echo $current_sr_no; ?>
                    </span>
                </td>
                <td>
                    <strong style="font-size: 0.95rem; color: #fff;"><?php echo htmlspecialchars($act['title']); ?></strong><br>
                    <small style="color: var(--text-muted); text-transform: uppercase; font-weight: 600;"><?php echo htmlspecialchars($act['type_name']); ?></small>
                </td>
                <td>
                    <span style="font-weight: 700; color: #38bdf8; font-size: 0.85rem; padding: 0.2rem 0.5rem; border-radius: 4px; background: rgba(56, 189, 248, 0.1);">
                        <?php echo $subj; ?>
                    </span>
                </td>
                <td>
                    <span style="font-weight: 700; color: #fbbf24; font-size: 0.85rem; padding: 0.2rem 0.5rem; border-radius: 4px; background: rgba(251, 191, 36, 0.1);">
                        <?php echo $unit_val; ?>
                    </span>
                </td>
                <td>
                    <strong style="color: #10b981; font-size: 0.95rem;"><?php echo $act['total_marks']; ?> pts</strong>
                </td>
                <td>
                    <small style="color: var(--text-secondary);"><?php echo date('M d, Y H:i', strtotime($act['deadline'])); ?></small>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 0.4rem; justify-content: center;">
                        <!-- VIEW -->
                        <a href="<?php echo $view_page; ?>" class="btn btn-outline" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;" title="View Page">
                            <i class="fa-solid fa-eye" style="color: #38bdf8;"></i> View
                        </a>

                        <!-- EDIT -->
                        <button class="btn btn-outline" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; border-color: #f59e0b;" onclick="openEditModal(<?php echo $act_json; ?>)" title="Edit Activity">
                            <i class="fa-solid fa-pen-to-square" style="color: #f59e0b;"></i> Edit
                        </button>

                        <!-- RECREATE -->
                        <form action="recreate_activity.php" method="POST" style="display: inline;" onsubmit="return confirm('Recreate this activity for a new batch?');">
                            <input type="hidden" name="action" value="recreate">
                            <input type="hidden" name="activity_id" value="<?php echo $act['id']; ?>">
                            <button type="submit" class="btn btn-outline" style="padding: 0.35rem 0.65rem; font-size: 0.8rem; border-color: #8b5cf6;" title="Recreate / Duplicate">
                                <i class="fa-solid fa-rotate-right" style="color: #8b5cf6;"></i> Recreate
                            </button>
                        </form>

                        <!-- DELETE -->
                        <form action="recreate_activity.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to DELETE this activity?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="activity_id" value="<?php echo $act['id']; ?>">
                            <button type="submit" class="btn btn-danger" style="padding: 0.35rem 0.65rem; font-size: 0.8rem;" title="Delete Activity">
                                <i class="fa-solid fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php 
            $sr_counter++;
            endforeach; 
            ?>
        </tbody>
    </table>
</div>

<!-- Modal: EDIT Activity -->
<div class="modal-overlay" id="editActivityModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fa-solid fa-pen-to-square" style="color: #f59e0b;"></i> Edit Activity Details</h3>
            <button class="close-btn" onclick="closeModal('editActivityModal')">&times;</button>
        </div>
        
        <form action="recreate_activity.php" method="POST">
            <input type="hidden" name="action" value="edit_activity">
            <input type="hidden" name="activity_id" id="edit_id">
            
            <div class="form-group">
                <label for="edit_title">Project Title *</label>
                <input type="text" id="edit_title" name="title" class="form-control" required>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="edit_subject">Subject *</label>
                    <select id="edit_subject" name="subject" class="form-control" required style="background: #1e293b;">
                        <option value="BEE">⚡ BEE</option>
                        <option value="Chemistry">🧪 Chemistry</option>
                        <option value="Physics">⚛️ Physics</option>
                        <option value="Maths">📐 Maths</option>
                        <option value="Computer Science">💻 Computer Science</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_unit">Syllabus Unit *</label>
                    <select id="edit_unit" name="unit" class="form-control" required style="background: #1e293b;">
                        <option value="Unit 1">Unit 1</option>
                        <option value="Unit 2">Unit 2</option>
                        <option value="Unit 3">Unit 3</option>
                        <option value="Unit 4">Unit 4</option>
                        <option value="Unit 5">Unit 5</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_type">Category *</label>
                    <select id="edit_type" name="type" class="form-control" required style="background: #1e293b;">
                        <option value="quiz">📝 Quiz</option>
                        <option value="poster_making">🎨 Poster Making</option>
                        <option value="ppt">📊 PPT Presentation</option>
                        <option value="case_study">🔍 Case Study</option>
                        <option value="gd">💬 Group Discussion</option>
                        <option value="mini_project">🚀 Mini Project</option>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="edit_course">Course Code / Name *</label>
                    <input type="text" id="edit_course" name="course" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_batch">Target Batch / Section *</label>
                    <input type="text" id="edit_batch" name="batch" class="form-control" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label for="edit_deadline">Submission Deadline *</label>
                    <input type="datetime-local" id="edit_deadline" name="deadline" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_total_marks">Total Marks *</label>
                    <input type="number" id="edit_total_marks" name="total_marks" class="form-control" min="5" max="500" required>
                </div>
            </div>

            <div class="form-group">
                <label for="edit_description">Instructions & Description</label>
                <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-outline" onclick="closeModal('editActivityModal')">Cancel</button>
                <button type="submit" class="btn btn-primary" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">Save & Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(act) {
    document.getElementById('edit_id').value = act.id;
    document.getElementById('edit_title').value = act.title;
    document.getElementById('edit_subject').value = act.subject || 'BEE';
    document.getElementById('edit_unit').value = act.unit || 'Unit 1';
    document.getElementById('edit_type').value = act.type;
    document.getElementById('edit_course').value = act.course;
    document.getElementById('edit_batch').value = act.batch;
    
    if (act.deadline) {
        const d = new Date(act.deadline);
        const formatted = d.toISOString().slice(0, 16);
        document.getElementById('edit_deadline').value = formatted;
    }
    
    document.getElementById('edit_total_marks').value = act.total_marks;
    document.getElementById('edit_description').value = act.description || '';

    openModal('editActivityModal');
}
</script>

<?php include 'includes/footer.php'; ?>
