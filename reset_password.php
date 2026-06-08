<?php

// Import PHPMailer (doit être installé via Composer)
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';
session_start();
$conn = new mysqli("localhost", "root", "", "ma_base",3306);

$step = isset($_POST['step']) ? $_POST['step'] : 'email';

if ($step === 'send_code') {
    $email = $_POST['email'];
    $res = $conn->query("SELECT * FROM utilisateur WHERE email='$email'");
    if ($res->num_rows > 0) {
        $code = rand(100000, 999999);
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_code'] = $code;
        $_SESSION['expires'] = time() + 900; // 15 min

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'amaltoumi535@gmail.com'; // ton adresse Gmail
            $mail->Password = 'crmr mydm sqtn zdqn'; // App Password Gmail
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            $mail->setFrom('amaltoumi535@gmail.com', 'Support');
            $mail->addAddress($email);

            $mail->isHTML(true);
            $mail->Subject = 'Code de récupération';
            $mail->Body    = "Votre code est : <b>$code</b>";

            $mail->send();
            $success = "Un code a été envoyé à votre adresse email.";
            $step = 'verify_code';
        } catch (Exception $e) {
            $error = "Erreur lors de l'envoi du mail : {$mail->ErrorInfo}";
            $step = 'email';
        }
    } else {
        $error = "Email introuvable.";
        $step = 'email';
    }
}

if ($step === 'check_code') {
    if ($_POST['code'] == $_SESSION['reset_code'] && time() < $_SESSION['expires']) {
        $success = "Code vérifié avec succès. Vous pouvez créer un nouveau mot de passe.";
        $step = 'reset_password';
    } else {
        $error = "Code invalide ou expiré.";
        $step = 'verify_code';
    }
}

if ($step === 'update_password') {
    if ($_POST['password'] === $_POST['confirm_password']) {
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $email = $_SESSION['reset_email'];
        $conn->query("UPDATE utilisateur SET mot_de_passe='$hash' WHERE email='$email'");
        session_destroy();
        session_start(); // restart session just to pass the message
        $_SESSION['reset_success'] = "Mot de passe modifié avec succès. Connectez-vous avec votre nouveau mot de passe.";
        header("Location: /Sprint1_AGL/login.php");
        exit;
    } else {
        $error = "Les mots de passe ne correspondent pas.";
        $step = 'reset_password';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Réinitialisation du mot de passe</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card shadow">
        <div class="card-body">
          <h3 class="card-title text-center mb-4">Réinitialisation du mot de passe</h3>

          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
          <?php endif; ?>

          <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
          <?php endif; ?>

          <?php if ($step === 'email'): ?>
            <form method="post">
              <input type="hidden" name="step" value="send_code">
              <div class="mb-3">
                <label for="email" class="form-label">Votre email</label>
                <input type="email" class="form-control" name="email" required>
              </div>
              <button type="submit" class="btn btn-primary w-100">Envoyer le code</button>
            </form>

          <?php elseif ($step === 'verify_code'): ?>
            <form method="post">
              <input type="hidden" name="step" value="check_code">
              <div class="mb-3">
                <label for="code" class="form-label">Code reçu par email</label>
                <input type="text" class="form-control" name="code" required>
              </div>
              <button type="submit" class="btn btn-success w-100">Vérifier</button>
            </form>

          <?php elseif ($step === 'reset_password'): ?>
            <form method="post">
              <input type="hidden" name="step" value="update_password">
              <div class="mb-3">
                <label for="password" class="form-label">Nouveau mot de passe</label>
                <input type="password" class="form-control" name="password" required>
              </div>
              <div class="mb-3">
                <label for="confirm_password" class="form-label">Confirmez le mot de passe</label>
                <input type="password" class="form-control" name="confirm_password" required>
              </div>
              <button type="submit" class="btn btn-primary w-100">Confirmer</button>
            </form>
          <?php endif; ?>

        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
