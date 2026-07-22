<?php
// mini_project.php - Dedicated Mini Project Portal Page
require_once 'config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 6;
$activity = get_activity_by_id($id);

if (!$activity) {
    header("Location: index.php");
    exit;
}

$page_title = $activity['title'] . " - Mini Project Portal";
include 'includes/header.php';
?>

<a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

<div class="page-header-box" style="border-left: 5px solid #06b6d4;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
            <span style="font-size: 0.8rem; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: 6px; background: rgba(99, 102, 241, 0.25); color: #818cf8; border: 1px solid rgba(129, 140, 248, 0.4);">
                Sr. No. #<?php echo isset($activity['sr_no']) ? $activity['sr_no'] : $id; ?>
            </span>
            <span style="font-size: 0.8rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 6px; background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.4);">
                <i class="fa-solid fa-bookmark"></i> <?php echo isset($activity['unit']) ? $activity['unit'] : 'Unit 5'; ?>
            </span>
            <span class="activity-type-badge" style="background: rgba(6, 182, 212, 0.2); color: #06b6d4;">
                <i class="fa-solid fa-laptop-code"></i> Mini Project Portal
            </span>
            <span style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo htmlspecialchars($activity['course']); ?> (<?php echo htmlspecialchars($activity['batch']); ?>)</span>
        </div>
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($activity['title']); ?></h1>
        <p style="color: var(--text-secondary); max-width: 800px;"><?php echo htmlspecialchars($activity['description']); ?></p>
    </div>
    
    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.75rem;">
        <span style="background: rgba(6, 182, 212, 0.15); color: #06b6d4; padding: 0.4rem 1rem; border-radius: 50px; font-weight: 600; font-size: 0.9rem;">
            <i class="fa-solid fa-users"></i> Max Team Size: 4 Members
        </span>
        <span style="font-size: 0.85rem; color: var(--text-secondary);">
            Final Viva Deadline: <strong style="color: #fff;"><?php echo date('M d, Y H:i', strtotime($activity['deadline'])); ?></strong>
        </span>
    </div>
</div>

<!-- Milestone Breakdown Banner -->
<div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; margin-bottom: 2rem;">
    <h3 style="margin-bottom: 1rem; color: #06b6d4;"><i class="fa-solid fa-bars-progress"></i> Project Evaluation Milestones</h3>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
        <div style="background: rgba(15, 23, 42, 0.6); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.2rem;">Milestone 1</div>
            <div style="font-weight: 600; font-size: 1rem; color: #fff;">Abstract & SRS Document</div>
            <div style="font-size: 0.85rem; color: #06b6d4; font-weight: 700; margin-top: 0.5rem;">20 Points</div>
        </div>

        <div style="background: rgba(15, 23, 42, 0.6); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.2rem;">Milestone 2</div>
            <div style="font-weight: 600; font-size: 1rem; color: #fff;">Mid-Term Code Review</div>
            <div style="font-size: 0.85rem; color: #06b6d4; font-weight: 700; margin-top: 0.5rem;">30 Points</div>
        </div>

        <div style="background: rgba(15, 23, 42, 0.6); padding: 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
            <div style="font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); font-weight: 700; margin-bottom: 0.2rem;">Milestone 3</div>
            <div style="font-weight: 600; font-size: 1rem; color: #fff;">Final Demo & Defense Viva</div>
            <div style="font-size: 0.85rem; color: #06b6d4; font-weight: 700; margin-top: 0.5rem;">50 Points</div>
        </div>
    </div>
</div>

<!-- Team Project Registry Table -->
<div style="margin-bottom: 3rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h3><i class="fa-solid fa-folder-tree" style="color: #06b6d4;"></i> Registered Student Teams & Projects</h3>
        <button class="btn btn-outline" style="font-size: 0.85rem;" onclick="alert('Export all team GitHub repos')">
            <i class="fa-brands fa-github"></i> Download Repositories CSV
        </button>
    </div>

    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Sr. No.</th>
                    <th>Team Name</th>
                    <th>Project Title</th>
                    <th>Team Members</th>
                    <th>Links (GitHub / Live)</th>
                    <th>Milestone Progress</th>
                    <th>Total Score (100)</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>1</strong></td>
                    <td><strong style="color: #06b6d4;">Team CyberShield</strong><br><small style="color: var(--text-muted);">Team ID: #T01</small></td>
                    <td>Hospital Management System with HIPAA Compliance</td>
                    <td>
                        <div style="font-size: 0.85rem;">• Rohan Das (Lead)<br>• Simran Kaur<br>• Amit Sen</div>
                    </td>
                    <td>
                        <a href="https://github.com" target="_blank" style="color: #06b6d4;"><i class="fa-brands fa-github"></i> GitHub Repo</a><br>
                        <a href="#" style="color: #10b981; font-size: 0.8rem;"><i class="fa-solid fa-globe"></i> Live Preview</a>
                    </td>
                    <td>
                        <div style="font-size: 0.78rem; color: #10b981; margin-bottom: 0.2rem;">100% Completed</div>
                        <div style="height: 6px; width: 100px; background: rgba(255,255,255,0.1); border-radius: 3px;">
                            <div style="width: 100%; height: 100%; background: #10b981; border-radius: 3px;"></div>
                        </div>
                    </td>
                    <td><strong style="color: #10b981; font-size: 1.1rem;">94 / 100</strong></td>
                    <td><button class="btn btn-outline" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">Review Marks</button></td>
                </tr>

                <tr>
                    <td><strong>2</strong></td>
                    <td><strong style="color: #06b6d4;">Team CodeCraft</strong><br><small style="color: var(--text-muted);">Team ID: #T02</small></td>
                    <td>Smart Campus Navigation & Event Tracker App</td>
                    <td>
                        <div style="font-size: 0.85rem;">• Yashwardhan R.<br>• Meera Menon<br>• Siddharth B.</div>
                    </td>
                    <td>
                        <a href="https://github.com" target="_blank" style="color: #06b6d4;"><i class="fa-brands fa-github"></i> GitHub Repo</a>
                    </td>
                    <td>
                        <div style="font-size: 0.78rem; color: #f59e0b; margin-bottom: 0.2rem;">60% (Mid-term Done)</div>
                        <div style="height: 6px; width: 100px; background: rgba(255,255,255,0.1); border-radius: 3px;">
                            <div style="width: 60%; height: 100%; background: #f59e0b; border-radius: 3px;"></div>
                        </div>
                    </td>
                    <td><strong style="color: #f59e0b; font-size: 1.1rem;">50 / 100</strong></td>
                    <td><button class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">Grade Viva Demo</button></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
