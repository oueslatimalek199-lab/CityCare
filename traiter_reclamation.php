<?php
$pageTitle = 'Traiter réclamation';
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

if ($user['role'] !== 'agent') {
    Auth::redirectToDashboard();
}

$pdo   = getConnexion();
$idRec = $_GET['id'] ?? null;

if (!$idRec) {
    die("ID de réclamation manquant.");
}

// Vérifier que la réclamation existe et est assignée à l'agent connecté
$stmt = $pdo->prepare("
    SELECT r.*, u.nom AS citoyen_nom, u.prenom AS citoyen_prenom, u.email AS citoyen_email
    FROM reclamation r
    LEFT JOIN utilisateur u ON r.idUtilisateur = u.idUtilisateur
    WHERE r.idRec = :idRec AND r.idUtilisateurAssigne = :idUtilisateur
");
$stmt->execute([
    'idRec'         => $idRec,
    'idUtilisateur' => $user['idUtilisateur']
]);
$rec = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rec) {
    die("Réclamation introuvable ou non assignée à cet agent.");
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatut   = $_POST['statut'] ?? $rec['statut'];
    $commentaire = trim($_POST['commentaire'] ?? '');

    $update = $pdo->prepare("
        UPDATE reclamation 
        SET statut = :statut, commentaireAgent = :commentaire, dateModification = NOW()
        WHERE idRec = :idRec AND idUtilisateurAssigne = :idUtilisateur
    ");
    $update->execute([
        'statut'        => $newStatut,
        'commentaire'   => $commentaire,
        'idRec'         => $idRec,
        'idUtilisateur' => $user['idUtilisateur']
    ]);

    // Send email to citizen
    sendStatusEmail(
        $rec['citoyen_email'],
        $rec['citoyen_prenom'] . ' ' . $rec['citoyen_nom'],
        $rec['idRec'],
        $rec['titre'],
        $newStatut,
        $commentaire
    );

    $_SESSION['success'] = 'Réclamation mise à jour avec succès ✓ - Email envoyé au citoyen';
    header("Location: agent.php");
    exit;
}

/**
 * Send status update email to citizen
 */
function sendStatusEmail($to, $citizenName, $idRec, $titre, $newStatut, $commentaire = '') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'amaltoumi535@gmail.com';
        $mail->Password   = 'crmr mydm sqtn zdqn';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('amaltoumi535@gmail.com', 'Plateforme CityCare');
        $mail->addAddress($to);

        $mail->isHTML(true);
        $mail->Subject = "📋 Mise à jour de votre réclamation #$idRec";

        $statusColors = [
            'en attente'    => ['bg' => '#fff3cd', 'color' => '#856404'],
            'en traitement' => ['bg' => '#d1ecf1', 'color' => '#0c5460'],
            'résolu'        => ['bg' => '#d4edda', 'color' => '#155724'],
            'annulé'        => ['bg' => '#f8d7da', 'color' => '#721c24'],
        ];
        $badge = $statusColors[$newStatut] ?? ['bg' => '#eee', 'color' => '#333'];

        $commentaireHtml = !empty($commentaire)
            ? "<div class='detail-row' style='margin-top:12px'>
                <span class='label'>💬 Message de l'agent :</span>
                <div style='margin-top:8px;padding:12px;background:#f9f9f9;border-radius:5px;
                            border-left:3px solid #667eea;font-style:italic;color:#444'>
                    " . nl2br(htmlspecialchars($commentaire)) . "
                </div>
               </div>"
            : '';

        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
                .header h2 { margin: 0; font-size: 20px; }
                .content { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea; }
                .details { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .detail-row { margin: 10px 0; }
                .label { font-weight: bold; color: #667eea; }
                .status-badge { display:inline-block; padding:6px 16px; border-radius:20px; font-weight:700;
                                font-size:14px; background:{$badge['bg']}; color:{$badge['color']}; }
                .button { display:inline-block; background:#667eea; color:white; padding:12px 25px;
                          text-decoration:none; border-radius:5px; margin-top:15px; font-weight:600; }
                .footer { margin-top:20px; font-size:12px; color:#999; text-align:center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>📋 Mise à jour de votre réclamation</h2>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>" . htmlspecialchars($citizenName) . "</strong>,</p>
                    <p>Le statut de votre réclamation a été mis à jour par notre équipe.</p>
                    <div class='details'>
                        <div class='detail-row'>
                            <span class='label'>📌 Numéro :</span> #" . $idRec . "
                        </div>
                        <div class='detail-row'>
                            <span class='label'>📝 Titre :</span> " . htmlspecialchars($titre) . "
                        </div>
                        <div class='detail-row'>
                            <span class='label'>📊 Nouveau statut :</span>
                            <span class='status-badge'>" . htmlspecialchars($newStatut) . "</span>
                        </div>
                        $commentaireHtml
                    </div>
                    <p>Vous pouvez suivre l'avancement de votre réclamation dans votre espace personnel.</p>
                    <a href='http://localhost/Sprint1_AGL/citoyen.php' class='button'>Voir ma réclamation</a>
                    <div class='footer'>
                        <p>Cet email a été envoyé automatiquement. Veuillez ne pas répondre.</p>
                        <p>Plateforme CityCare — Gestion des Réclamations</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";

        $mail->send();
    } catch (Exception $e) {
        error_log("Email error (traiter_reclamation): " . $mail->ErrorInfo);
    }
}

$headerConfig = [
    'title'       => 'Traiter une Réclamation',
    'subtitle'    => 'Mettez à jour le statut de la réclamation',
    'icon'        => '🔧',
    'role'        => 'Agent Municipal',
    'profileLink' => './profil.php',
    'bgGradient'  => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<!-- Complaint details -->
<div class="card">
    <h2>📋 Réclamation #<?= htmlspecialchars($rec['idRec']) ?></h2>
    <p style="margin-top:10px">
        <strong>Titre :</strong> <?= htmlspecialchars($rec['titre']) ?>
    </p>
    <p style="margin-top:8px">
        <strong>Description :</strong><br>
        <?= nl2br(htmlspecialchars($rec['description'])) ?>
    </p>
    <p style="margin-top:8px">
        <strong>Adresse :</strong> <?= htmlspecialchars($rec['adresse'] ?? '—') ?>
    </p>
    <p style="margin-top:8px">
        <strong>Citoyen :</strong> <?= htmlspecialchars($rec['citoyen_prenom'] . ' ' . $rec['citoyen_nom']) ?>
    </p>
    <p style="margin-top:8px">
        <strong>Statut actuel :</strong>
        <?php
        $badgeColors = [
            'en attente'    => ['bg' => '#fff3cd', 'color' => '#856404'],
            'en traitement' => ['bg' => '#d1ecf1', 'color' => '#0c5460'],
            'résolu'        => ['bg' => '#d4edda', 'color' => '#155724'],
            'annulé'        => ['bg' => '#f8d7da', 'color' => '#721c24'],
        ];
        $badge = $badgeColors[$rec['statut']] ?? ['bg' => '#eee', 'color' => '#333'];
        ?>
        <span style="background:<?= $badge['bg'] ?>;color:<?= $badge['color'] ?>;
                     padding:4px 12px;border-radius:20px;font-size:13px;font-weight:600">
            <?= htmlspecialchars($rec['statut']) ?>
        </span>
    </p>
</div>

<!-- Update form -->
<form method="POST" class="card" style="margin-top:20px">
    <h3 style="margin-bottom:20px">🔄 Mettre à jour</h3>

    <label style="font-weight:600;display:block;margin-bottom:6px">Statut :</label>
    <select name="statut" required
            style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;margin-bottom:20px">
        <option value="en attente"    <?= $rec['statut'] === 'en attente'    ? 'selected' : '' ?>>En attente</option>
        <option value="en traitement" <?= $rec['statut'] === 'en traitement' ? 'selected' : '' ?>>En traitement</option>
        <option value="résolu"        <?= $rec['statut'] === 'résolu'        ? 'selected' : '' ?>>Résolu</option>
        <option value="annulé"        <?= $rec['statut'] === 'annulé'        ? 'selected' : '' ?>>Annulé</option>
    </select>

    <label style="font-weight:600;display:block;margin-bottom:6px">
        💬 Message / Note pour le citoyen :
        <span style="font-weight:400;color:#888;font-size:12px">(optionnel — sera inclus dans l'email)</span>
    </label>
    <textarea name="commentaire" rows="4"
              style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-size:14px;margin-bottom:20px;resize:vertical"
              placeholder="Ex: Votre réclamation est en cours de traitement, un technicien sera envoyé dans 48h..."><?= htmlspecialchars($rec['commentaireAgent'] ?? '') ?></textarea>

    <div style="display:flex;gap:10px">
        <button type="submit"
                style="background:#667eea;color:white;border:none;padding:10px 24px;
                       border-radius:6px;cursor:pointer;font-weight:600;font-size:14px">
            ✓ Mettre à jour &amp; Notifier le citoyen
        </button>
        <a href="agent.php"
           style="background:#6c757d;color:white;padding:10px 24px;border-radius:6px;
                  text-decoration:none;font-weight:600;font-size:14px">
            ← Retour
        </a>
    </div>
</form>

<style>
.card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}
</style>

<?php require_once 'includes/footer.php'; ?>