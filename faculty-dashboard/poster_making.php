<?php
// poster_making.php - Dedicated Poster Making Activity Page
require_once 'config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 2;
$activity = get_activity_by_id($id);

if (!$activity) {
    header("Location: index.php");
    exit;
}

$page_title = $activity['title'] . " - Poster Portal";
include 'includes/header.php';
?>

<a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

<div class="page-header-box" style="border-left: 5px solid #ec4899;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
            <span class="activity-type-badge" style="background: rgba(236, 72, 153, 0.2); color: #ec4899;">
                <i class="fa-solid fa-palette"></i> Poster Making Activity
            </span>
            <span style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo htmlspecialchars($activity['course']); ?> (<?php echo htmlspecialchars($activity['batch']); ?>)</span>
        </div>
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($activity['title']); ?></h1>
        <p style="color: var(--text-secondary); max-width: 800px;"><?php echo htmlspecialchars($activity['description']); ?></p>
    </div>
    
    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.75rem;">
        <span style="background: rgba(236, 72, 153, 0.15); color: #ec4899; padding: 0.4rem 1rem; border-radius: 50px; font-weight: 600; font-size: 0.9rem;">
            <i class="fa-solid fa-image"></i> Max File Size: 25 MB (PNG/JPG)
        </span>
        <span style="font-size: 0.85rem; color: var(--text-secondary);">
            Deadline: <strong style="color: #fff;"><?php echo date('M d, Y H:i', strtotime($activity['deadline'])); ?></strong>
        </span>
    </div>
</div>

<!-- Rubric Guidelines & Submissions -->
<div style="display: grid; grid-template-columns: 1fr 2.5fr; gap: 2rem; margin-bottom: 3rem;">

    <!-- Evaluation Rubric Card -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; height: fit-content;">
        <h3 style="margin-bottom: 1rem; color: #ec4899;"><i class="fa-solid fa-sliders"></i> Scoring Rubric (50 Pts)</h3>
        
        <div style="display: flex; flex-direction: column; gap: 1rem; font-size: 0.9rem;">
            <div style="padding-bottom: 0.75rem; border-bottom: 1px dashed var(--border-color);">
                <div style="display: flex; justify-content: space-between; font-weight: 600;">
                    <span>1. Visual Layout & Aesthetics</span>
                    <span style="color: #ec4899;">15 Pts</span>
                </div>
                <small style="color: var(--text-secondary);">Color harmony, typography, high resolution graphics.</small>
            </div>
            
            <div style="padding-bottom: 0.75rem; border-bottom: 1px dashed var(--border-color);">
                <div style="display: flex; justify-content: space-between; font-weight: 600;">
                    <span>2. Technical Content & Accuracy</span>
                    <span style="color: #ec4899;">20 Pts</span>
                </div>
                <small style="color: var(--text-secondary);">Factual accuracy, dataset inclusion, subject depth.</small>
            </div>

            <div style="padding-bottom: 0.75rem; border-bottom: 1px dashed var(--border-color);">
                <div style="display: flex; justify-content: space-between; font-weight: 600;">
                    <span>3. Originality & Innovation</span>
                    <span style="color: #ec4899;">15 Pts</span>
                </div>
                <small style="color: var(--text-secondary);">Unique perspective and infographic creativity.</small>
            </div>
        </div>
    </div>

    <!-- Student Submitted Posters Gallery -->
    <div>
        <h3 style="margin-bottom: 1.25rem;"><i class="fa-solid fa-images" style="color: #ec4899;"></i> Student Submissions Gallery</h3>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1.25rem;">
            
            <!-- Poster Card 1 -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden;">
                <img src="https://images.unsplash.com/photo-1542601906990-b4d3fb778b09?q=80&w=600&auto=format&fit=crop" style="width: 100%; height: 160px; object-fit: cover;">
                <div style="padding: 1rem;">
                    <h4 style="font-size: 1rem; margin-bottom: 0.2rem;">Karan Mehta</h4>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Submitted Jul 20, 2026</span>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                        <span style="color: #10b981; font-weight: 700; font-size: 0.9rem;">Score: 46 / 50</span>
                        <button class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.78rem;" onclick="alert('Viewing Karan Mehta poster details')">
                            Evaluate
                        </button>
                    </div>
                </div>
            </div>

            <!-- Poster Card 2 -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden;">
                <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?q=80&w=600&auto=format&fit=crop" style="width: 100%; height: 160px; object-fit: cover;">
                <div style="padding: 1rem;">
                    <h4 style="font-size: 1rem; margin-bottom: 0.2rem;">Sneha Reddy</h4>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Submitted Jul 21, 2026</span>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                        <span style="color: #10b981; font-weight: 700; font-size: 0.9rem;">Score: 48 / 50</span>
                        <button class="btn btn-outline" style="padding: 0.3rem 0.6rem; font-size: 0.78rem;" onclick="alert('Viewing Sneha Reddy poster details')">
                            Evaluate
                        </button>
                    </div>
                </div>
            </div>

            <!-- Poster Card 3 -->
            <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); overflow: hidden;">
                <img src="https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?q=80&w=600&auto=format&fit=crop" style="width: 100%; height: 160px; object-fit: cover;">
                <div style="padding: 1rem;">
                    <h4 style="font-size: 1rem; margin-bottom: 0.2rem;">Rohan Gupta</h4>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Submitted Jul 21, 2026</span>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1rem;">
                        <span style="color: #f59e0b; font-weight: 700; font-size: 0.9rem;">Ungraded</span>
                        <button class="btn btn-primary" style="padding: 0.3rem 0.6rem; font-size: 0.78rem;" onclick="alert('Open Evaluation Form')">
                            Grade Now
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
