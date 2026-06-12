<?php include APP_PATH . '/Views/layouts/header.php'; ?>

<div class="dashboard admin-dashboard">
    <h1>Admin Dashboard</h1>
    
    <div class="dashboard-grid">
        <div class="dashboard-card">
            <h3>Manage Users</h3>
            <p>View and manage system users</p>
            <a href="<?php echo BASE_URL; ?>/admin/users" class="btn">Manage Users</a>
        </div>
        
        <div class="dashboard-card">
            <h3>Manage Agents</h3>
            <p>View and manage field agents</p>
            <a href="<?php echo BASE_URL; ?>/admin/agents" class="btn">Manage Agents</a>
        </div>
        
        <div class="dashboard-card">
            <h3>Manage Services</h3>
            <p>Create and manage services</p>
            <a href="<?php echo BASE_URL; ?>/admin/services" class="btn">Manage Services</a>
        </div>
        
        <div class="dashboard-card">
            <h3>Manage Complaints</h3>
            <p>Review and manage complaints</p>
            <a href="<?php echo BASE_URL; ?>/admin/complaints" class="btn">Manage Complaints</a>
        </div>
    </div>
</div>

<?php include APP_PATH . '/Views/layouts/footer.php'; ?>