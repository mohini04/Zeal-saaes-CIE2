<?php include 'includes/header.php'; ?>

<section class="hero-panel">
    <div>
        <p class="eyebrow">Approval Center</p>
        <h2>Review and approve faculty requests, marks changes, and reports.</h2>
    </div>
</section>

<section class="panel" style="margin-top: 20px;">
    <h3>Pending Approvals</h3>
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
        </tbody>
    </table>
</section>

<?php include 'includes/footer.php'; ?>
