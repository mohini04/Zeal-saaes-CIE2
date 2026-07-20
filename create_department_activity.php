<?php include 'includes/header.php'; ?>

<section class="hero-panel">
    <div>
        <p class="eyebrow">Create Department Activity</p>
        <h2>Publish a new activity for faculty and students.</h2>
    </div>
</section>

<section class="panel" style="margin-top: 20px;">
    <h3>New Activity Details</h3>
    <form class="form-grid" method="post">
        <div class="form-field">
            <label for="activity-name">Activity Name</label>
            <input id="activity-name" name="activity_name" type="text" placeholder="e.g. Digital Logic Quiz" required>
        </div>
        <div class="form-field">
            <label for="subject">Subject</label>
            <select id="subject" name="subject">
                <option>Digital Logic</option>
                <option>Microprocessor</option>
                <option>DSP</option>
            </select>
        </div>
        <div class="form-field">
            <label for="deadline">Deadline</label>
            <input id="deadline" name="deadline" type="date" required>
        </div>
        <div class="form-field">
            <label for="description">Description</label>
            <textarea id="description" name="description" rows="4" placeholder="Add instructions for students and faculty"></textarea>
        </div>
        <button class="submit-btn" type="submit">Publish Activity</button>
    </form>
    <div class="success-box">Activity will be available to students and faculty after submission.</div>
</section>

<?php include 'includes/footer.php'; ?>
