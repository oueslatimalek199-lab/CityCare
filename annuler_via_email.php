<?php

require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'EmailNotifications.php';
require_once 'vendor/autoload.php';

$pdo   = getConnexion();
$token = trim($_GET['token'] ?? '');


$rec        = null;
$tokenRow   = null;
$errorMsg   = '';
$successMsg = '';

if (empty($token)) {
    $errorMsg = 'Lien invalide ou manquant.';
} else {
    // Fetch token record
    $stmt = $pdo->prepare("
        SELECT rt.*, r.titre, r.statut, r.idUtilisateur,
               u.nom, u.prenom, u.email
        FROM reclamation_token rt
        JOIN reclamation r ON rt.idRec = r.idRec
        JOIN utilisateur u ON r.idUtilisateur = u.idUtilisateur
        WHERE rt.token = ?
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tokenRow) {
        $errorMsg = 'Ce lien d\'annulation est invalide ou inexistant.';
    } elseif ($tokenRow['used']) {
        $errorMsg = 'Ce lien d\'annulation a déjà été utilisé.';
    } elseif (strtotime($tokenRow['expires_at']) < time()) {
        $errorMsg = 'Ce lien d\'annulation a expiré (valable 2 heures). Vous pouvez contacter le support si nécessaire.';
    } elseif (!in_array($tokenRow['statut'], ['en attente', 'en traitement'])) {
        $errorMsg = 'Cette réclamation ne peut plus être annulée (statut actuel : <strong>' . htmlspecialchars($tokenRow['statut']) . '</strong>).';
    }
}

// ──────────────────────────────────────────────
// Handle cancellation POST
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cancel']) && !$errorMsg && $tokenRow) {
    $motif = trim($_POST['motif'] ?? '');

    try {
        $pdo->beginTransaction();

        // 1. Update complaint status
        $update = $pdo->prepare("
            UPDATE reclamation
            SET statut = 'annulé', dateModification = NOW()
            WHERE idRec = ? AND statut IN ('en attente','en traitement')
        ");
        $update->execute([$tokenRow['idRec']]);

        // 2. Mark token as used
        $pdo->prepare("UPDATE reclamation_token SET used = 1 WHERE token = ?")
            ->execute([$token]);

        $pdo->commit();

        // 3. Send confirmation email
        EmailNotifications::annulationReclamation(
            $tokenRow['email'],
            $tokenRow['prenom'] . ' ' . $tokenRow['nom'],
            (int)$tokenRow['idRec'],
            $tokenRow['titre'],
            $motif
        );

        $successMsg = 'Votre réclamation <strong>#' . $tokenRow['idRec'] . '</strong> a été annulée avec succès.';
        $tokenRow   = null; // prevent re-display of form

    } catch (\Throwable $e) {
        $pdo->rollBack();
        $errorMsg = 'Une erreur est survenue lors de l\'annulation. Veuillez réessayer.';
        error_log("annuler_via_email.php error: " . $e->getMessage());
    }
}

// Remaining time in minutes
$minutesLeft = 0;
if ($tokenRow && !$errorMsg) {
    $minutesLeft = max(0, (int)floor((strtotime($tokenRow['expires_at']) - time()) / 60));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Annuler ma réclamation — CityCare</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: #f0f2f5;
    background-image:
      radial-gradient(ellipse at 20% 30%, rgba(252,74,26,.08) 0%, transparent 60%),
      radial-gradient(ellipse at 80% 70%, rgba(247,183,51,.06) 0%, transparent 60%);
  }

  .card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 8px 40px rgba(0,0,0,.10);
    max-width: 520px;
    width: 100%;
    overflow: hidden;
  }

  /* ── HEADER ── */
  .card-header {
    padding: 28px 32px 24px;
    display: flex;
    align-items: center;
    gap: 14px;
    border-bottom: 1px solid #f0f0f0;
  }

  .card-header .logo-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
  }

  .card-header h1 { font-size: 18px; font-weight: 700; color: #111; }
  .card-header p  { font-size: 12px; color: #999; margin-top: 2px; }

  /* ── BODY ── */
  .card-body { padding: 28px 32px; }

  /* Alert states */
  .alert {
    padding: 16px 18px;
    border-radius: 10px;
    font-size: 14px;
    line-height: 1.6;
  }
  .alert-error   { background: #fff0f0; border-left: 4px solid #fc4a1a; color: #7a1a00; }
  .alert-success { background: #f0fff4; border-left: 4px solid #22c55e; color: #14532d; }
  .alert-warning { background: #fffbeb; border-left: 4px solid #f59e0b; color: #78350f; }

  /* Detail rows */
  .details {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 16px;
    margin: 18px 0;
  }
  .detail-row {
    display: flex;
    gap: 10px;
    padding: 8px 0;
    border-bottom: 1px solid #eee;
    font-size: 13px;
  }
  .detail-row:last-child { border-bottom: none; }
  .detail-label { min-width: 110px; font-weight: 600; color: #666; }
  .detail-value { color: #222; }

  /* Badge */
  .badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
  }
  .badge-attente    { background: #fff3cd; color: #856404; }
  .badge-traitement { background: #d1ecf1; color: #0c5460; }
  .badge-annule     { background: #f8d7da; color: #721c24; }

  /* Timer */
  .timer-bar {
    background: #fff8e1;
    border-radius: 8px;
    padding: 12px 16px;
    margin: 16px 0;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #78350f;
    font-weight: 600;
  }

  /* Motif textarea */
  textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
    min-height: 80px;
    margin-top: 6px;
    outline: none;
    transition: border .2s;
  }
  textarea:focus { border-color: #fc4a1a; box-shadow: 0 0 0 3px rgba(252,74,26,.1); }

  /* Buttons */
  .btn-group { display: flex; gap: 10px; margin-top: 22px; flex-wrap: wrap; }

  .btn {
    flex: 1;
    padding: 12px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    border: none;
    cursor: pointer;
    text-align: center;
    text-decoration: none;
    transition: all .2s;
    min-width: 140px;
  }
  .btn-cancel {
    background: linear-gradient(135deg, #fc4a1a, #f7b733);
    color: white;
    box-shadow: 0 4px 12px rgba(252,74,26,.3);
  }
  .btn-cancel:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(252,74,26,.4); }
  .btn-back {
    background: #f5f5f5;
    color: #555;
    border: 1px solid #e0e0e0;
  }
  .btn-back:hover { background: #eee; }
  .btn-primary {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    box-shadow: 0 4px 12px rgba(102,126,234,.3);
  }
  .btn-primary:hover { transform: translateY(-1px); }

  /* Footer */
  .card-footer {
    padding: 16px 32px;
    background: #f8f8f8;
    border-top: 1px solid #f0f0f0;
    text-align: center;
    font-size: 11px;
    color: #bbb;
  }

  /* Countdown animation */
  @keyframes ticktock { 0%, 100% { opacity: 1; } 50% { opacity: .6; } }
  .tick { animation: ticktock 1s ease-in-out infinite; }
</style>
</head>
<body>

<div class="card">

  <!-- HEADER -->
  <div class="card-header">
    <div class="logo-icon" style="background:linear-gradient(135deg,#fc4a1a,#f7b733)">❌</div>
    <div>
      <h1>Annulation de réclamation</h1>
      <p>Plateforme CityCare — Gestion des Réclamations</p>
    </div>
  </div>

  <!-- BODY -->
  <div class="card-body">

    <?php if ($successMsg): ?>
      <!-- ✅ SUCCESS STATE -->
      <div class="alert alert-success" style="margin-bottom:20px">
        ✅ <?= $successMsg ?>
      </div>
      <p style="font-size:14px;color:#555;line-height:1.7;margin-bottom:20px">
        Un email de confirmation a été envoyé à votre adresse.
        Vous pouvez soumettre une nouvelle réclamation à tout moment.
      </p>
      <a href="./citoyen.php" class="btn btn-primary" style="display:block;text-align:center">
        ← Retour à mon espace
      </a>

    <?php elseif ($errorMsg): ?>
      <!-- ❌ ERROR STATE -->
      <div class="alert alert-error">
        ⚠️ <?= $errorMsg ?>
      </div>
      <div class="btn-group">
        <a href="./citoyen.php"  class="btn btn-back">← Mon espace</a>
        <a href="./soumettre.php" class="btn btn-primary">Nouvelle réclamation</a>
      </div>

    <?php elseif ($tokenRow): ?>
      <!-- ⏳ CONFIRMATION FORM -->

      <p style="font-size:14px;color:#444;line-height:1.7;margin-bottom:16px">
        Vous êtes sur le point d'annuler la réclamation suivante. Cette action est <strong>irréversible</strong>.
      </p>

      <!-- Timer -->
      <div class="timer-bar">
        <span class="tick" style="font-size:18px">⏱️</span>
        <span>Lien valable encore <strong id="countdown"><?= $minutesLeft ?> min</strong></span>
      </div>

      <!-- Details -->
      <div class="details">
        <div class="detail-row">
          <span class="detail-label">📌 Numéro</span>
          <span class="detail-value"><strong>#<?= $tokenRow['idRec'] ?></strong></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">📝 Titre</span>
          <span class="detail-value"><?= htmlspecialchars($tokenRow['titre']) ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">👤 Citoyen</span>
          <span class="detail-value"><?= htmlspecialchars($tokenRow['prenom'] . ' ' . $tokenRow['nom']) ?></span>
        </div>
        <div class="detail-row">
          <span class="detail-label">📊 Statut actuel</span>
          <span class="detail-value">
            <?php
            $cls = match($tokenRow['statut']) {
                'en attente'    => 'badge-attente',
                'en traitement' => 'badge-traitement',
                default         => ''
            };
            ?>
            <span class="badge <?= $cls ?>"><?= htmlspecialchars($tokenRow['statut']) ?></span>
          </span>
        </div>
        <div class="detail-row">
          <span class="detail-label">🕐 Expire à</span>
          <span class="detail-value"><?= date('H:i', strtotime($tokenRow['expires_at'])) ?></span>
        </div>
      </div>

      <!-- Cancellation form -->
      <form method="POST">
        <input type="hidden" name="confirm_cancel" value="1">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <label style="font-size:13px;font-weight:600;color:#555;display:block">
          Motif d'annulation <span style="font-weight:400;color:#aaa">(optionnel)</span>
        </label>
        <textarea name="motif" placeholder="Expliquez brièvement la raison de l'annulation…" maxlength="300"></textarea>

        <div class="btn-group">
          <a href="./citoyen.php" class="btn btn-back">← Garder ma réclamation</a>
          <button type="submit" class="btn btn-cancel">❌ Confirmer l'annulation</button>
        </div>
      </form>

    <?php endif; ?>

  </div>

  <div class="card-footer">
    Lien à usage unique • Valable 2 heures uniquement
  </div>
</div>

<?php if ($tokenRow && !$errorMsg): ?>
<script>
// Live countdown
(function() {
  const expires = <?= strtotime($tokenRow['expires_at']) * 1000 ?>;
  const el = document.getElementById('countdown');
  if (!el) return;

  function update() {
    const diff = Math.max(0, Math.floor((expires - Date.now()) / 1000));
    const m = Math.floor(diff / 60);
    const s = diff % 60;
    el.textContent = m + 'min ' + String(s).padStart(2,'0') + 's';
    if (diff === 0) {
      el.closest('.timer-bar').innerHTML = '⏱️ <strong>Ce lien a expiré — rechargez la page</strong>';
      clearInterval(iv);
    }
  }
  update();
  const iv = setInterval(update, 1000);
})();
</script>
<?php endif; ?>

</body>
</html>