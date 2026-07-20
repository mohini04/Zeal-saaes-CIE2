<?php include 'includes/header.php'; ?>

<section class="hero-panel">
    <div>
        <p class="eyebrow">Activity Monitoring</p>
        <h2>Monitor department activities, submissions, and completion rates.</h2>
    </div>
</section>

<section class="panel" style="margin-top: 20px;">
    <h3>Activity Summary</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Activity</th>
                <th>Subject</th>
                <th>Completion</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>K-Map</td>
                <td>Digital Logic</td>
                <td>95%</td>
                <td><span class="badge good">Active</span></td>
            </tr>
            <tr>
                <td>Flip-Flop</td>
                <td>Digital Logic</td>
                <td>83%</td>
                <td><span class="badge good">Running</span></td>
            </tr>
            <tr>
                <td>FFT Assignment</td>
                <td>DSP</td>
                <td>58%</td>
                <td><span class="badge warning">Pending</span></td>
            </tr>
        </tbody>
    </table>
</section>

<?php include 'includes/footer.php'; ?>
