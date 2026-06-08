<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
 
// If already logged in, redirect to appropriate dashboard
if (Auth::estConnecte()) {
    Auth::redirectToDashboard();
}
 
$erreur = '';
 
$resetSuccess = '';
if (isset($_SESSION['reset_success'])) {
    $resetSuccess = $_SESSION['reset_success'];
    unset($_SESSION['reset_success']);
}
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['mot_de_passe'] ?? '';
 
    if (empty($email) || empty($password)) {
        $erreur = 'Tous les champs sont requis.';
    } else {
        $result = Auth::connecter($email, $password);
        if ($result === true) {
            Auth::redirectToDashboard();
        } elseif ($result === 'agent_desactive') {
            $erreur = 'Votre compte a été désactivé. Veuillez contacter l\'administrateur.';
        } else {
            $erreur = 'Email ou mot de passe incorrect.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion — CityCare</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
 
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 16px;
        }
 
        .container {
            display: flex;
            width: 820px;
            max-width: 100%;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 12px 40px rgba(0,0,0,0.25);
        }
 
        /* LEFT PANEL */
        .left {
            background: linear-gradient(160deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 42%;
            padding: 50px 36px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .left img {
            width: 400px;
            margin-bottom: 24px;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));
        }
        .left h2 { font-size: 22px; font-weight: 700; margin-bottom: 10px; }
        .left p  { font-size: 13px; line-height: 1.7; opacity: 0.85; margin-bottom: 20px; }
        .badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.25);
            color: white;
            border-radius: 20px;
            padding: 6px 16px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.4px;
        }
 
        /* RIGHT PANEL */
        .right {
            width: 58%;
            padding: 50px 44px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .right h2 { font-size: 24px; color: #667eea; margin-bottom: 6px; font-weight: 700; }
        .subtitle { font-size: 13px; color: #888; margin-bottom: 28px; }
 
        /* Alerts */
        .alert-error {
            background: #fdecea; color: #b00020;
            padding: 11px 14px; border-radius: 8px;
            margin-bottom: 18px; border-left: 4px solid #b00020;
            font-size: 13px;
        }
        .alert-success {
            background: #d4edda; color: #155724;
            padding: 11px 14px; border-radius: 8px;
            margin-bottom: 18px; border-left: 4px solid #28a745;
            font-size: 13px;
        }
 
        /* Form */
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block; font-size: 13px;
            font-weight: 600; color: #444; margin-bottom: 6px;
        }
        .form-group input {
            width: 100%; padding: 10px 13px;
            border: 1px solid #ddd; border-radius: 8px;
            font-size: 14px; color: #333;
            transition: border 0.2s, box-shadow 0.2s; outline: none;
        }
        .form-group input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }
 
        .options {
            display: flex; justify-content: space-between;
            align-items: center; margin-bottom: 22px;
            font-size: 13px; color: #555;
        }
        .options label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
        .options a { color: #667eea; text-decoration: none; font-weight: 600; }
        .options a:hover { text-decoration: underline; }
 
        .btn {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; border: none; border-radius: 8px;
            font-size: 15px; font-weight: 700;
            cursor: pointer; transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
 
        .divider {
            display: flex; align-items: center;
            gap: 10px; margin: 22px 0;
        }
        .divider-line { flex: 1; height: 1px; background: #eee; }
        .divider-text { font-size: 12px; color: #bbb; white-space: nowrap; }
 
        .register-link { text-align: center; font-size: 13px; color: #666; }
        .register-link a { color: #667eea; font-weight: 700; text-decoration: none; }
        .register-link a:hover { text-decoration: underline; }
 
        @media(max-width: 680px) {
            .container { flex-direction: column; }
            .left, .right { width: 100%; padding: 36px 28px; }
        }
    </style>
</head>
<body>
<div class="container">
 
    <!-- LEFT PANEL -->
    <div class="left">
        <img src="LOGO.png" alt="CityCare Logo">
        <p>Plateforme municipale de gestion des réclamations citoyennes</p>
        <span class="badge">🏛️ Espace sécurisé</span>
    </div>
 
    <!-- RIGHT PANEL -->
    <div class="right">
        <h2>Bienvenue 👋</h2>
        <p class="subtitle">Connectez-vous à votre compte</p>
 
        <?php if ($erreur): ?>
            <div class="alert-error"><?= htmlspecialchars($erreur) ?></div>
        <?php endif; ?>
 
        <?php if ($resetSuccess): ?>
            <div class="alert-success">✅ <?= htmlspecialchars($resetSuccess) ?></div>
        <?php endif; ?>
 
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">Adresse email</label>
                <input type="email" name="email" id="email"
                       placeholder="votre@email.com"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       required autocomplete="email">
            </div>
 
            <div class="form-group">
                <label for="mot_de_passe">Mot de passe</label>
                <input type="password" name="mot_de_passe" id="mot_de_passe"
                       placeholder="Votre mot de passe"
                       required autocomplete="current-password">
            </div>
 
            <div class="options">
                <label><input type="checkbox"> Se souvenir de moi</label>
                <a href="./reset_password.php/">Mot de passe oublié ?</a>
            </div>
 
            <button type="submit" class="btn">Se connecter</button>
        </form>
 
        <div class="divider">
            <div class="divider-line"></div>
            <span class="divider-text">Pas encore de compte ?</span>
            <div class="divider-line"></div>
        </div>
 
        <div class="register-link">
            <a href="inscription.php">Créer un compte citoyen →</a>
        </div>
    </div>
 
</div>
<script src="script.js"></script>
</body>
</html>