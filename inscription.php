<?php
require_once 'Config/database.php';
require_once 'Config/session.php';

$pdo    = getConnexion();
$erreur = '';
$succes = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom          = trim($_POST["nom"]          ?? '');
    $prenom       = trim($_POST["prenom"]       ?? '');
    $email        = trim($_POST["email"]        ?? '');
    $mot_de_passe = $_POST["mot_de_passe"]      ?? '';
    $confirmPsw   = $_POST["psw1"]              ?? '';
    $role         = 'citoyen';

    if ($nom === '' || $prenom === '' || $email === '' || $mot_de_passe === '') {
        $erreur = 'Tous les champs obligatoires doivent être remplis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Adresse email invalide.';
    } elseif (strlen($mot_de_passe) < 6) {
        $erreur = 'Le mot de passe doit contenir au moins 6 caractères.';
    } elseif ($mot_de_passe !== $confirmPsw) {
        $erreur = 'Les mots de passe ne correspondent pas.';
    } else {
        $check = $pdo->prepare("SELECT idUtilisateur FROM utilisateur WHERE email = ?");
        $check->execute([$email]);

        if ($check->fetch()) {
            $erreur = 'Cette adresse email est déjà utilisée.';
        } else {
            try {
                $hashed_password = password_hash($mot_de_passe, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("
                    INSERT INTO utilisateur (nom, prenom, email, mot_de_passe, role, statut)
                    VALUES (?, ?, ?, ?, ?, 'actif')
                ");
                $stmt->execute([$nom, $prenom, $email, $hashed_password, $role]);
                $succes = true;
            } catch (Exception $e) {
                $erreur = 'Erreur lors de l\'inscription : ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Inscription — CityCare</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Arial, sans-serif; }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 16px;
        }

        .container {
            display: flex;
            width: 860px;
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
            width: 38%;
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
            width: 62%;
            padding: 44px 48px;
            overflow-y: auto;
            max-height: 95vh;
        }
        .right h2 {
            font-size: 22px;
            color: #667eea;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .subtitle { font-size: 13px; color: #888; margin-bottom: 26px; }

        /* Form */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

        .form-group { margin-bottom: 16px; }
        .form-group label {
            display: block; margin-bottom: 5px;
            color: #444; font-weight: 600; font-size: 13px;
        }
        .form-group input {
            width: 100%; padding: 10px 13px;
            border-radius: 8px; border: 1px solid #ddd;
            font-size: 14px; color: #333;
            transition: border 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .form-group input::placeholder { color: #bbb; }
        .form-group input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }
        .optional { color: #aaa; font-weight: 400; font-size: 11px; }

        /* Alerts */
        .alert-error {
            background: #fdecea; color: #b00020;
            padding: 12px 14px; border-radius: 8px;
            margin-bottom: 18px; border-left: 4px solid #b00020;
            font-size: 13px;
        }

        /* Button */
        .btn {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; border: none; border-radius: 8px;
            cursor: pointer; font-size: 15px;
            font-weight: 700; transition: opacity 0.2s;
            margin-top: 6px;
        }
        .btn:hover { opacity: 0.9; }

        .login-link { text-align: center; margin-top: 18px; font-size: 13px; color: #666; }
        .login-link a { color: #667eea; text-decoration: none; font-weight: 700; }
        .login-link a:hover { text-decoration: underline; }

        /* Success */
        .success-box {
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            text-align: center; padding: 30px 10px;
        }
        .success-icon {
            width: 72px; height: 72px; border-radius: 50%;
            background: #e6f4ea;
            display: flex; align-items: center; justify-content: center;
            font-size: 34px; margin-bottom: 18px;
            animation: pop .4s ease;
        }
        @keyframes pop {
            0%   { transform: scale(0); opacity: 0; }
            80%  { transform: scale(1.15); }
            100% { transform: scale(1); opacity: 1; }
        }
        .success-box h3 { color: #1e7e34; font-size: 20px; margin-bottom: 8px; }
        .success-box p  { color: #555; font-size: 14px; margin-bottom: 24px; line-height: 1.6; }
        .btn-login {
            display: inline-block; padding: 12px 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white; border-radius: 8px;
            text-decoration: none; font-weight: 700;
            font-size: 14px; transition: opacity 0.2s;
        }
        .btn-login:hover { opacity: 0.9; }

        @media(max-width: 768px) {
            .container { flex-direction: column; }
            .left, .right { width: 100%; max-height: none; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="container">

    <!-- LEFT PANEL -->
    <div class="left">
        <img src="LOGO.png" alt="CityCare Logo">
        <p>Plateforme municipale de gestion des réclamations citoyennes</p>
        <span class="badge">✨ Créez votre espace personnel</span>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right">
        <h2>Créer un compte</h2>
        <p class="subtitle">Rejoignez la plateforme en quelques secondes</p>

        <?php if ($succes): ?>
            <div class="success-box">
                <div class="success-icon">✅</div>
                <h3>Inscription réussie !</h3>
                <p>Votre compte a bien été créé.<br>Vous pouvez maintenant vous connecter.</p>
                <a href="login.php" class="btn-login">Se connecter →</a>
            </div>

        <?php else: ?>

            <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $erreur): ?>
                <div class="alert-error"><?= htmlspecialchars($erreur) ?></div>
            <?php endif; ?>

            <form method="POST" action="inscription.php">

                <div class="form-row">
                    <div class="form-group">
                        <label>Prénom *</label>
                        <input type="text" name="prenom" placeholder="Votre prénom" required
                               value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Nom *</label>
                        <input type="text" name="nom" placeholder="Votre nom" required
                               value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" placeholder="exemple@gmail.com" required
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Mot de passe * <span class="optional">(min. 6 caractères)</span></label>
                    <input type="password" name="mot_de_passe" placeholder="Votre mot de passe" required>
                </div>

                <div class="form-group">
                    <label>Confirmer le mot de passe *</label>
                    <input type="password" name="psw1" placeholder="Confirmer le mot de passe" required>
                </div>

                <button class="btn" type="submit">S'inscrire</button>
            </form>

            <div class="login-link">
                Déjà un compte ? <a href="login.php">Se connecter</a>
            </div>

        <?php endif; ?>
    </div>

</div>
</body>
</html>