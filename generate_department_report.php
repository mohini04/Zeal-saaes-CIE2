<?php include 'includes/header.php'; ?>

<section class="hero-panel">
    <div>
        <p class="eyebrow">Department Report Generator</p>
        <h2>Create a department summary report for faculty, students, and outcomes.</h2>
    </div>
</section>

<section class="panel" style="margin-top: 20px;">
    <h3>Generate Report</h3>
    <form class="form-grid" method="post">
        <div class="form-field">
            <label for="report-type">Report Type</label>
            <select id="report-type" name="report_type">
                <option>Faculty Performance</option>
                <option>Student Performance</option>
                <option>Activity Summary</option>
                <option>NAAC/NBA Summary</option>
            </select>
        </div>
        <div class="form-field">
            <label for="semester">Semester</label>
            <select id="semester" name="semester">
                <option>Semester III</option>
                <option>Semester IV</option>
                <option>Semester V</option>
            </select>
        </div>
        <div class="form-field">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="4" placeholder="Add any special instructions or target outcomes"></textarea>
        </div>
        <button class="submit-btn" type="submit">Generate Report</button>
    </form>
    <div class="success-box">The report is prepared and can be downloaded from the reports page.</div>
</section>

<?php include 'includes/footer.php'; ?>
