<?php include APP_PATH . '/Views/layouts/header.php'; ?>

<div class="register-container">
    <form method="POST" action="<?php echo BASE_URL; ?>/auth/register" class="register-form">
        <h2>Register to CityCare</h2>
        
        <div class="form-group">
            <label for="name">Full Name:</label>
            <input type="text" id="name" name="name" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
        </div>
        
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        
        <div class="form-group">
            <label for="password_confirm">Confirm Password:</label>
            <input type="password" id="password_confirm" name="password_confirm" required>
        </div>
        
        <button type="submit" class="btn">Register</button>
        
        <p>Already have an account? <a href="<?php echo BASE_URL; ?>/login">Login here</a></p>
    </form>
</div>

<?php include APP_PATH . '/Views/layouts/footer.php'; ?>