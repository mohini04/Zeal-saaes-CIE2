<?php include 'includes/header.php'; ?>

<section class="hero-panel">
    <div>
        <p class="eyebrow">Student Performance</p>
        <h2>Track academic progress, attendance, and evaluation outcomes.</h2>
    </div>
</section>

<section class="panel" style="margin-top: 20px;">
    <h3>Performance Overview</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Student</th>
                <th>Semester</th>
                <th>Average Marks</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Rahul J.</td>
                <td>III</td>
                <td>84%</td>
                <td><span class="badge good">Pass</span></td>
            </tr>
            <tr>
                <td>Asha M.</td>
                <td>IV</td>
                <td>76%</td>
                <td><span class="badge warning">Borderline</span></td>
            </tr>
            <tr>
                <td>Neha S.</td>
                <td>V</td>
                <td>68%</td>
                <td><span class="badge alert">Needs Attention</span></td>
            </tr>
        </tbody>
    </table>
</section>

<?php include 'includes/footer.php'; ?>
