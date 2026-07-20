<?php include 'includes/header.php'; ?>

<section class="hero-panel">
    <div>
        <p class="eyebrow">Faculty Management</p>
        <h2>Manage faculty profiles, assignments, and review status.</h2>
    </div>
</section>

<section class="panel" style="margin-top: 20px;">
    <h3>Faculty Records</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Department</th>
                <th>Subject</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Prof. Patil</td>
                <td>E&TC</td>
                <td>Digital Logic</td>
                <td><span class="badge good">Active</span></td>
            </tr>
            <tr>
                <td>Prof. Kulkarni</td>
                <td>E&TC</td>
                <td>Microprocessor</td>
                <td><span class="badge good">Active</span></td>
            </tr>
            <tr>
                <td>Prof. Shah</td>
                <td>E&TC</td>
                <td>DSP</td>
                <td><span class="badge warning">Needs Review</span></td>
            </tr>
        </tbody>
    </table>
</section>

<?php include 'includes/footer.php'; ?>
