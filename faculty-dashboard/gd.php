<?php
// gd.php - Dedicated Group Discussion Activity Page
require_once 'config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 5;
$activity = get_activity_by_id($id);

if (!$activity) {
    header("Location: index.php");
    exit;
}

$page_title = $activity['title'] . " - GD Portal";
include 'includes/header.php';
?>

<a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

<div class="page-header-box" style="border-left: 5px solid #8b5cf6;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
            <span class="activity-type-badge" style="background: rgba(139, 92, 246, 0.2); color: #8b5cf6;">
                <i class="fa-solid fa-comments"></i> Group Discussion (GD)
            </span>
            <span style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo htmlspecialchars($activity['course']); ?> (<?php echo htmlspecialchars($activity['batch']); ?>)</span>
        </div>
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($activity['title']); ?></h1>
        <p style="color: var(--text-secondary); max-width: 800px;"><?php echo htmlspecialchars($activity['description']); ?></p>
    </div>
    
    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.75rem;">
        <button class="btn btn-primary" style="background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);" onclick="alert('Auto-generating random GD groups')">
            <i class="fa-solid fa-wand-magic-sparkles"></i> Auto-Allocate Groups
        </button>
        <span style="font-size: 0.85rem; color: var(--text-secondary);">
            Batch Size: <strong style="color: #fff;">6 Students per Group</strong>
        </span>
    </div>
</div>

<!-- GD Groups & Live Grading Sheet -->
<div style="margin-bottom: 3rem;">
    <h3 style="margin-bottom: 1.25rem;"><i class="fa-solid fa-users-viewfinder" style="color: #8b5cf6;"></i> Allocated Discussion Groups & Slot Timings</h3>

    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem;">
        
        <!-- Group 1 Card -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                <h4 style="font-size: 1.15rem; color: #8b5cf6;">Group A - Slot 1</h4>
                <span style="font-size: 0.8rem; background: rgba(139, 92, 246, 0.15); color: #8b5cf6; padding: 0.25rem 0.6rem; border-radius: 12px; font-weight: 600;">
                    2:00 PM - 2:20 PM
                </span>
            </div>

            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                <i class="fa-solid fa-location-dot" style="color: #8b5cf6;"></i> Venue: Seminar Hall 102
            </p>

            <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.88rem; margin-bottom: 1.25rem;">
                <li style="display: flex; justify-content: space-between; padding: 0.4rem 0.6rem; background: rgba(255,255,255,0.03); border-radius: 6px;">
                    <span>1. Abhinav Gupta (25CSE01)</span>
                    <span style="color: #10b981; font-weight: 700;">22 / 25</span>
                </li>
                <li style="display: flex; justify-content: space-between; padding: 0.4rem 0.6rem; background: rgba(255,255,255,0.03); border-radius: 6px;">
                    <span>2. Bhavna Nair (25CSE07)</span>
                    <span style="color: #10b981; font-weight: 700;">24 / 25</span>
                </li>
                <li style="display: flex; justify-content: space-between; padding: 0.4rem 0.6rem; background: rgba(255,255,255,0.03); border-radius: 6px;">
                    <span>3. Chirag Shah (25CSE12)</span>
                    <span style="color: #10b981; font-weight: 700;">20 / 25</span>
                </li>
                <li style="display: flex; justify-content: space-between; padding: 0.4rem 0.6rem; background: rgba(255,255,255,0.03); border-radius: 6px;">
                    <span>4. Divya Kulkarni (25CSE19)</span>
                    <span style="color: #10b981; font-weight: 700;">23 / 25</span>
                </li>
            </ul>

            <button class="btn btn-outline" style="width: 100%; border-color: rgba(139, 92, 246, 0.4);" onclick="alert('Opening Live Evaluation Sheet for Group A')">
                <i class="fa-solid fa-pen-to-square"></i> Open Live Group Score Sheet
            </button>
        </div>

        <!-- Group 2 Card -->
        <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">
                <h4 style="font-size: 1.15rem; color: #8b5cf6;">Group B - Slot 2</h4>
                <span style="font-size: 0.8rem; background: rgba(245, 158, 11, 0.15); color: #f59e0b; padding: 0.25rem 0.6rem; border-radius: 12px; font-weight: 600;">
                    2:25 PM - 2:45 PM
                </span>
            </div>

            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                <i class="fa-solid fa-location-dot" style="color: #8b5cf6;"></i> Venue: Seminar Hall 102
            </p>

            <ul style="list-style: none; display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.88rem; margin-bottom: 1.25rem;">
                <li style="display: flex; justify-content: space-between; padding: 0.4rem 0.6rem; background: rgba(255,255,255,0.03); border-radius: 6px;">
                    <span>1. Esha Sharma (25CSE24)</span>
                    <span style="color: #f59e0b;">Pending GD</span>
                </li>
                <li style="display: flex; justify-content: space-between; padding: 0.4rem 0.6rem; background: rgba(255,255,255,0.03); border-radius: 6px;">
                    <span>2. Farhan Ali (25CSE30)</span>
                    <span style="color: #f59e0b;">Pending GD</span>
                </li>
                <li style="display: flex; justify-content: space-between; padding: 0.4rem 0.6rem; background: rgba(255,255,255,0.03); border-radius: 6px;">
                    <span>3. Gayatri Mohan (25CSE35)</span>
                    <span style="color: #f59e0b;">Pending GD</span>
                </li>
                <li style="display: flex; justify-content: space-between; padding: 0.4rem 0.6rem; background: rgba(255,255,255,0.03); border-radius: 6px;">
                    <span>4. Harsh Vardhan (25CSE41)</span>
                    <span style="color: #f59e0b;">Pending GD</span>
                </li>
            </ul>

            <button class="btn btn-primary" style="width: 100%; background: linear-gradient(135deg, #8b5cf6 0%, #6366f1 100%);" onclick="alert('Start Evaluation for Group B')">
                <i class="fa-solid fa-play"></i> Start Group B Discussion
            </button>
        </div>

    </div>
</div>

<?php include 'includes/footer.php'; ?>
