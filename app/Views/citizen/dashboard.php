<?php include APP_PATH . '/Views/layouts/header.php'; ?>

<div class="dashboard citizen-dashboard">
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
    
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3>My Service Requests</h3>
            <p>Track your submitted service requests</p>
            <a href="<?php echo BASE_URL; ?>/citizen/requests" class="btn">View Requests</a>
        </div>
        
        <div class="dashboard-card">
            <h3>Submit New Request</h3>
            <p>Submit a new service request</p>
            <a href="<?php echo BASE_URL; ?>/citizen/submit-request" class="btn">Submit Request</a>
        </div>
        
        <div class="dashboard-card">
            <h3>File Complaint</h3>
            <p>Report an issue or file a complaint</p>
            <a href="<?php echo BASE_URL; ?>/complaints/submit" class="btn">File Complaint</a>
        </div>
        
        <div class="dashboard-card">
            <h3>Messages</h3>
            <p>View your messages</p>
            <a href="<?php echo BASE_URL; ?>/messages" class="btn">View Messages</a>
        </div>
    </div>
</div>

<?php include APP_PATH . '/Views/layouts/footer.php'; ?>