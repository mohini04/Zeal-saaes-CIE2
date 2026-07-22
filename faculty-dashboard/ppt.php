<?php
// ppt.php - Dedicated PPT Presentation Activity Page
require_once 'config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 3;
$activity = get_activity_by_id($id);

if (!$activity) {
    header("Location: index.php");
    exit;
}

$page_title = $activity['title'] . " - PPT Presentation";
include 'includes/header.php';
?>

<a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

<div class="page-header-box" style="border-left: 5px solid #f59e0b;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
            <span style="font-size: 0.8rem; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: 6px; background: rgba(99, 102, 241, 0.25); color: #818cf8; border: 1px solid rgba(129, 140, 248, 0.4);">
                Sr. No. #<?php echo isset($activity['sr_no']) ? $activity['sr_no'] : $id; ?>
            </span>
            <span style="font-size: 0.8rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 6px; background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.4);">
                <i class="fa-solid fa-bookmark"></i> <?php echo isset($activity['unit']) ? $activity['unit'] : 'Unit 3'; ?>
            </span>
            <span class="activity-type-badge" style="background: rgba(245, 158, 11, 0.2); color: #f59e0b;">
                <i class="fa-solid fa-file-powerpoint"></i> PPT Presentation
            </span>
            <span style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo htmlspecialchars($activity['course']); ?> (<?php echo htmlspecialchars($activity['batch']); ?>)</span>
        </div>
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($activity['title']); ?></h1>
        <p style="color: var(--text-secondary); max-width: 800px;"><?php echo htmlspecialchars($activity['description']); ?></p>
    </div>
    
    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.75rem;">
        <span style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 0.4rem 1rem; border-radius: 50px; font-weight: 600; font-size: 0.9rem;">
            <i class="fa-solid fa-layer-group"></i> 8-12 Slides (PPTX / PDF)
        </span>
        <span style="font-size: 0.85rem; color: var(--text-secondary);">
            Presentation Start: <strong style="color: #fff;"><?php echo date('M d, Y H:i', strtotime($activity['deadline'])); ?></strong>
        </span>
    </div>
</div>

<!-- Presentation Slots & Submissions Table -->
<div style="margin-bottom: 3rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h3><i class="fa-solid fa-calendar-days" style="color: #f59e0b;"></i> Presentation Schedule & File Submissions</h3>
        <button class="btn btn-outline" style="font-size: 0.85rem;" onclick="alert('Export Schedule as PDF')">
            <i class="fa-solid fa-download"></i> Export Schedule
        </button>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Sr. No.</th>
                    <th>Slot #</th>
                    <th>Presenter Name & Roll</th>
                    <th>Presentation Topic</th>
                    <th>Uploaded Deck</th>
                    <th>Defense Viva Marks (30)</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>1</strong></td>
                    <td><span style="font-weight: 700; color: #f59e0b;">Slot 01</span><br><small style="color: var(--text-muted);">10:00 AM</small></td>
                    <td><strong>Vikram Malhotra</strong><br><small style="color: var(--text-muted);">Roll: 23CSE08</small></td>
                    <td>Transformer Models in Vision Processing</td>
                    <td><a href="#" style="color: #6366f1; font-weight: 600;"><i class="fa-solid fa-file-powerpoint"></i> vision_trans.pptx</a></td>
                    <td><strong style="color: #10b981;">27 / 30</strong></td>
                    <td><button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">Edit Score</button></td>
                </tr>

                <tr>
                    <td><strong>2</strong></td>
                    <td><span style="font-weight: 700; color: #f59e0b;">Slot 02</span><br><small style="color: var(--text-muted);">10:20 AM</small></td>
                    <td><strong>Neha Singhania</strong><br><small style="color: var(--text-muted);">Roll: 23CSE14</small></td>
                    <td>Comparing RNNs vs LSTMs in NLP Tasks</td>
                    <td><a href="#" style="color: #6366f1; font-weight: 600;"><i class="fa-solid fa-file-pdf"></i> nlp_rnn.pdf</a></td>
                    <td><strong style="color: #10b981;">29 / 30</strong></td>
                    <td><button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">Edit Score</button></td>
                </tr>

                <tr>
                    <td><strong>3</strong></td>
                    <td><span style="font-weight: 700; color: #f59e0b;">Slot 03</span><br><small style="color: var(--text-muted);">10:40 AM</small></td>
                    <td><strong>Devansh Joshi</strong><br><small style="color: var(--text-muted);">Roll: 23CSE22</small></td>
                    <td>Generative Adversarial Networks (GANs)</td>
                    <td><a href="#" style="color: #6366f1; font-weight: 600;"><i class="fa-solid fa-file-powerpoint"></i> gans_arch.pptx</a></td>
                    <td><span style="color: #f59e0b; font-weight: 600;">Pending Demo</span></td>
                    <td><button class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">Grade Presentation</button></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
