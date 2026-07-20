<?php include 'includes/header.php'; ?>

<section class="hero-panel">
    <div>
        <p class="eyebrow">Settings</p>
        <h2>Adjust portal preferences and departmental configuration.</h2>
    </div>
</section>

<section class="panel" style="margin-top: 20px;">
    <h3>Portal Settings</h3>
    <form class="form-grid">
        <div class="form-field">
            <label for="department">Department Name</label>
            <input id="department" type="text" value="E&TC Department">
        </div>
        <div class="form-field">
            <label for="academic-year">Academic Year</label>
            <select id="academic-year">
                <option>2026-2027</option>
                <option>2025-2026</option>
            </select>
        </div>
        <div class="form-field">
            <label for="message">Notification Message</label>
            <textarea id="message">Department review cycle is active.</textarea>
        </div>
        <button class="submit-btn" type="submit">Save Settings</button>
    </form>
</section>

<?php include 'includes/footer.php'; ?>
