<?php include 'includes/header.php'; ?>

<section class="hero-panel">
    <div>
        <p class="eyebrow">Department Activity Assessment & Evaluation Dashboard</p>
        <h2>Leadership view for faculty, students, and academic performance.</h2>
    </div>
    <div class="hero-actions">
        <a class="btn btn-primary" href="create_department_activity.php">➕ Create Department Activity</a>
        <a class="btn btn-secondary" href="generate_department_report.php">📄 Generate Department Report</a>
    </div>
</section>

<section class="stats-grid">
    <article class="stat-card blue">
        <div class="label">👨‍🏫 Total Faculty</div>
        <div class="value">28</div>
        <div class="foot">4 new faculty this term</div>
    </article>
    <article class="stat-card green">
        <div class="label">👨‍🎓 Total Students</div>
        <div class="value">612</div>
        <div class="foot">Across 12 active batches</div>
    </article>
    <article class="stat-card orange">
        <div class="label">📚 Total Activities</div>
        <div class="value">185</div>
        <div class="foot">14 scheduled this week</div>
    </article>
    <article class="stat-card red">
        <div class="label">⏳ Pending Approvals</div>
        <div class="value">17</div>
        <div class="foot">Critical review pending</div>
    </article>
    <article class="stat-card purple">
        <div class="label">📊 Department Performance</div>
        <div class="value">84%</div>
        <div class="foot">Above last term average</div>
    </article>
</section>

<section class="dashboard-grid">
    <div class="panel">
        <h3>Faculty Performance</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Faculty</th>
                    <th>Subject</th>
                    <th>Activities Created</th>
                    <th>Pending Evaluation</th>
                    <th>Average Marks</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Prof. Patil</td>
                    <td>Digital Logic</td>
                    <td>12</td>
                    <td>2</td>
                    <td>4.5</td>
                    <td><span class="badge good">Excellent</span></td>
                </tr>
                <tr>
                    <td>Prof. Kulkarni</td>
                    <td>Microprocessor</td>
                    <td>10</td>
                    <td>1</td>
                    <td>4.2</td>
                    <td><span class="badge good">Good</span></td>
                </tr>
                <tr>
                    <td>Prof. Shah</td>
                    <td>DSP</td>
                    <td>9</td>
                    <td>4</td>
                    <td>3.8</td>
                    <td><span class="badge warning">Needs Review</span></td>
                </tr>
            </tbody>
        </table>
        <div class="report-buttons">
            <a class="report-btn" href="faculty_management.php">View Faculty</a>
            <a class="report-btn" href="reports.php">Download Report</a>
        </div>
    </div>

    <div class="panel">
        <h3>Quick Statistics</h3>
        <div class="quick-stats">
            <div class="quick-item"><span>Activities Today</span><strong>18</strong></div>
            <div class="quick-item"><span>Pending Reviews</span><strong>17</strong></div>
            <div class="quick-item"><span>Faculty Online</span><strong>11</strong></div>
            <div class="quick-item"><span>Students Active</span><strong>243</strong></div>
            <div class="quick-item"><span>Average Attendance</span><strong>91%</strong></div>
        </div>

        <h3 style="margin-top: 18px;">Quick Actions</h3>
        <div class="small-list">
            <a class="small-item" href="faculty_management.php"><h4>Add Faculty</h4><p>Register or update faculty profile</p></a>
            <a class="small-item" href="subject_management.php"><h4>Assign Subject</h4><p>Map subjects to the right faculty</p></a>
            <a class="small-item" href="approval_center.php"><h4>Approve Activities</h4><p>Review pending submissions</p></a>
            <a class="small-item" href="reports.php"><h4>Publish Results</h4><p>Release evaluation outcomes</p></a>
        </div>
    </div>
</section>

<section class="dashboard-grid" style="margin-top: 20px;">
    <div class="panel">
        <h3>Department Activity Overview</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Activity</th>
                    <th>Subject</th>
                    <th>Faculty</th>
                    <th>Students Submitted</th>
                    <th>Completion</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>K-Map</td>
                    <td>Digital Logic</td>
                    <td>Prof. Patil</td>
                    <td>55/58</td>
                    <td>95%</td>
                    <td><span class="badge good">Active</span></td>
                </tr>
                <tr>
                    <td>Flip-Flop</td>
                    <td>Digital Logic</td>
                    <td>Prof. Patil</td>
                    <td>48/58</td>
                    <td>83%</td>
                    <td><span class="badge good">Running</span></td>
                </tr>
                <tr>
                    <td>FFT Assignment</td>
                    <td>DSP</td>
                    <td>Prof. Shah</td>
                    <td>35/60</td>
                    <td>58%</td>
                    <td><span class="badge warning">Pending</span></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h3>Department Insights</h3>
        <div class="chart-stack">
            <div class="chart-bar"><span>Average Marks by Semester</span><div class="track"><div class="fill blue" style="width: 82%"></div></div></div>
            <div class="chart-bar"><span>Submission Trend</span><div class="track"><div class="fill green" style="width: 74%"></div></div></div>
            <div class="chart-bar"><span>Pass / Fail Ratio</span><div class="track"><div class="fill orange" style="width: 68%"></div></div></div>
            <div class="chart-bar"><span>Weak Student Distribution</span><div class="track"><div class="fill red" style="width: 31%"></div></div></div>
        </div>
    </div>
</section>

<section class="dashboard-grid" style="margin-top: 20px;">
    <div class="panel">
        <h3>Approval Center</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Faculty</th>
                    <th>Request</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Prof. Patil</td>
                    <td>Activity Approval</td>
                    <td>Today</td>
                    <td><a class="report-btn" href="#">Approve</a></td>
                </tr>
                <tr>
                    <td>Prof. Shah</td>
                    <td>Marks Modification</td>
                    <td>Yesterday</td>
                    <td><a class="report-btn" href="#">Approve</a></td>
                </tr>
                <tr>
                    <td>Prof. Kulkarni</td>
                    <td>Report Approval</td>
                    <td>Today</td>
                    <td><a class="report-btn" href="#">Approve</a></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="panel">
        <h3>Notifications & Deadlines</h3>
        <div class="small-list">
            <div class="small-item"><h4>8 faculty activities awaiting approval</h4><p>Review requests from the academic team.</p></div>
            <div class="small-item"><h4>12 students have low attendance</h4><p>Send reminder and monitor follow-up.</p></div>
            <div class="small-item"><h4>5 reports ready for download</h4><p>Department reports are prepared.</p></div>
            <div class="small-item"><h4>Upcoming Deadlines</h4><p>18 Jul – Unit-2 Evaluation<br>20 Jul – Faculty Report Submission</p></div>
        </div>
    </div>
</section>

<div class="panel" style="margin-top: 20px;">
    <h3>Department Reports</h3>
    <div class="report-buttons">
        <a class="report-btn" href="generate_department_report.php">📄 Generate Department Report</a>
        <a class="report-btn" href="reports.php">📊 Export Excel</a>
        <a class="report-btn" href="reports.php">📈 Faculty Performance Report</a>
        <a class="report-btn" href="student_performance.php">📋 Student Performance Report</a>
        <a class="report-btn" href="activity_monitoring.php">📚 Activity Summary Report</a>
        <a class="report-btn" href="reports.php">🎯 NAAC/NBA Report</a>
    </div>
</div>

<div class="footer-note">
    © 2026 Zeal College of Engineering & Research, Pune<br>
    Department Activity Assessment & Evaluation System
</div>

<?php include 'includes/footer.php'; ?>
