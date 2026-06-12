<?php include APP_PATH . '/Views/layouts/header.php'; ?>

<div class="services-container">
    <h1>Available Services</h1>
    
    <div class="services-grid">
        <?php if (isset($services) && is_array($services)): ?>
            <?php foreach ($services as $service): ?>
                <div class="service-card">
                    <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                    <p><?php echo htmlspecialchars($service['description']); ?></p>
                    <a href="<?php echo BASE_URL; ?>/services/<?php echo $service['id']; ?>" class="btn">View Details</a>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No services available.</p>
        <?php endif; ?>
    </div>
</div>

<?php include APP_PATH . '/Views/layouts/footer.php'; ?>