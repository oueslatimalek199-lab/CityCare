<?php include APP_PATH . '/Views/layouts/header.php'; ?>

<div class="complaints-container">
    <h1>Public Complaints</h1>
    
    <div class="complaints-list">
        <?php if (isset($complaints) && is_array($complaints)): ?>
            <?php foreach ($complaints as $complaint): ?>
                <div class="complaint-item">
                    <h3><?php echo htmlspecialchars($complaint['title']); ?></h3>
                    <p><?php echo htmlspecialchars(substr($complaint['description'], 0, 200)); ?>...</p>
                    <p class="status">Status: <span><?php echo htmlspecialchars($complaint['status']); ?></span></p>
                    <a href="<?php echo BASE_URL; ?>/complaints/<?php echo $complaint['id']; ?>" class="btn">View Details</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No complaints found.</p>
        <?php endif; ?>
    </div>
</div>

<?php include APP_PATH . '/Views/layouts/footer.php'; ?>