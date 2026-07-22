<?php
// case_study.php - Dedicated Case Study Assignment Page
require_once 'config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 4;
$activity = get_activity_by_id($id);

if (!$activity) {
    header("Location: index.php");
    exit;
}

$page_title = $activity['title'] . " - Case Study";
include 'includes/header.php';
?>

<a href="index.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

<div class="page-header-box" style="border-left: 5px solid #10b981;">
    <div>
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
            <span style="font-size: 0.8rem; font-weight: 800; padding: 0.2rem 0.6rem; border-radius: 6px; background: rgba(99, 102, 241, 0.25); color: #818cf8; border: 1px solid rgba(129, 140, 248, 0.4);">
                Sr. No. #<?php echo isset($activity['sr_no']) ? $activity['sr_no'] : $id; ?>
            </span>
            <span style="font-size: 0.8rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 6px; background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.4);">
                <i class="fa-solid fa-bookmark"></i> <?php echo isset($activity['unit']) ? $activity['unit'] : 'Unit 4'; ?>
            </span>
            <span class="activity-type-badge" style="background: rgba(16, 185, 129, 0.2); color: #10b981;">
                <i class="fa-solid fa-magnifying-glass-chart"></i> Case Study Assignment
            </span>
            <span style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo htmlspecialchars($activity['course']); ?> (<?php echo htmlspecialchars($activity['batch']); ?>)</span>
        </div>
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($activity['title']); ?></h1>
        <p style="color: var(--text-secondary); max-width: 800px;"><?php echo htmlspecialchars($activity['description']); ?></p>
    </div>
    
    <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 0.75rem;">
        <button class="btn btn-outline" style="font-size: 0.85rem;" onclick="alert('Downloading Case Briefing PDF')">
            <i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i> Case Briefing Document.pdf
        </button>
        <span style="font-size: 0.85rem; color: var(--text-secondary);">
            Max Word Count: <strong style="color: #fff;">1,500 Words</strong>
        </span>
    </div>
</div>

<!-- Case Analysis Questions & Submissions -->
<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; margin-bottom: 3rem;">

    <!-- Case Study Prompts -->
    <div style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.5rem; height: fit-content;">
        <h3 style="margin-bottom: 1rem; color: #10b981;"><i class="fa-solid fa-clipboard-list"></i> Analytical Questions</h3>

        <ol style="padding-left: 1.25rem; color: var(--text-secondary); font-size: 0.9rem; display: flex; flex-direction: column; gap: 1rem;">
            <li>Identify the root vulnerabilities in the 2023 Payment Gateway Breach.</li>
            <li>Evaluate the zero-trust framework response time vs traditional firewalling.</li>
            <li>Formulate an emergency incident response protocol for regulatory reporting.</li>
        </ol>
    </div>

    <!-- Student Case Solution Tracker -->
    <div>
        <h3 style="margin-bottom: 1.25rem;"><i class="fa-solid fa-user-pen" style="color: #10b981;"></i> Submitted Case Reports</h3>

        <div class="table-responsive">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Sr. No.</th>
                        <th>Student Details</th>
                        <th>Submitted Solution</th>
                        <th>Plagiarism Check</th>
                        <th>Grade (40 Marks)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>1</strong></td>
                        <td><strong>Tanvi Deshmukh</strong><br><small style="color: var(--text-muted);">24IT005</small></td>
                        <td><a href="#" style="color: #6366f1;"><i class="fa-solid fa-file-lines"></i> fintech_security_report.pdf</a></td>
                        <td><span style="color: #10b981; font-weight: 600;">2% Match</span></td>
                        <td><span style="color: #10b981; font-weight: 700;">38 / 40</span></td>
                    </tr>
                    <tr>
                        <td><strong>2</strong></td>
                        <td><strong>Aditya Nambiar</strong><br><small style="color: var(--text-muted);">24IT019</small></td>
                        <td><a href="#" style="color: #6366f1;"><i class="fa-solid fa-file-lines"></i> security_case_aditya.docx</a></td>
                        <td><span style="color: #10b981; font-weight: 600;">4% Match</span></td>
                        <td><span style="color: #10b981; font-weight: 700;">36 / 40</span></td>
                    </tr>
                    <tr>
                        <td><strong>3</strong></td>
                        <td><strong>Ishaan Kapoor</strong><br><small style="color: var(--text-muted);">24IT031</small></td>
                        <td><a href="#" style="color: #6366f1;"><i class="fa-solid fa-file-lines"></i> breach_analysis_ishaan.pdf</a></td>
                        <td><span style="color: #f59e0b; font-weight: 600;">12% Match</span></td>
                        <td><button class="btn btn-primary" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">Evaluate Report</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
