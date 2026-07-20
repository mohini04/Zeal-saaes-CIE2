<?php include 'includes/header.php'; ?>

<section class="hero-panel">
    <div>
        <p class="eyebrow">Subject & Course Management</p>
        <h2>Organize subjects, courses, and assigned faculty.</h2>
    </div>
</section>

<section class="panel" style="margin-top: 20px;">
    <h3>Subject Allocation</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Subject</th>
                <th>Semester</th>
                <th>Faculty</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Digital Logic</td>
                <td>III</td>
                <td>Prof. Patil</td>
                <td><span class="badge good">Assigned</span></td>
            </tr>
            <tr>
                <td>Microprocessor</td>
                <td>IV</td>
                <td>Prof. Kulkarni</td>
                <td><span class="badge good">Assigned</span></td>
            </tr>
            <tr>
                <td>DSP</td>
                <td>V</td>
                <td>Prof. Shah</td>
                <td><span class="badge warning">Pending</span></td>
            </tr>
        </tbody>
    </table>
</section>

<?php include 'includes/footer.php'; ?>
