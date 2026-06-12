<?php include APP_PATH . '/Views/layouts/header.php'; ?>

<div class="login-container">
    <form method="POST" action="<?php echo BASE_URL; ?>/auth/login" class="login-form">
        <h2>Login to CityCare</h2>
        
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <button type="submit" class="btn">Login</button>
        
        <p>Don't have an account? <a href="<?php echo BASE_URL; ?>/register">Register here</a></p>
        <p><a href="<?php echo BASE_URL; ?>/reset-password">Forgot password?</a></p>
    </form>
</div>

<?php include APP_PATH . '/Views/layouts/footer.php'; ?>