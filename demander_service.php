<?php
require_once __DIR__ . '/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// ===================== DÉBOGAGE (À désactiver en production) =====================
ob_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
// ================================================================================

require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
 require_once 'canceltokenhelper.php';

// Vérifier que l'utilisateur est connecté
Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

// Vérifier que l'utilisateur est un citoyen
if ($user['role'] !== 'citoyen') {
    Auth::redirectToDashboard();
}

// Connexion à la base de données
$pdo = getConnexion();

// Récupération de l'ID du service depuis l'URL
$idService = (int)($_GET['service'] ?? 0);

if ($idService === 0) {
    header('Location: ./services_publiques.php');
    exit;
}

// ===================== RÉCUPÉRATION DU SERVICE =====================
$stmt = $pdo->prepare("
    SELECT s.*, c.label AS nomCateg
    FROM service s
    LEFT JOIN categorie c ON s.idCateg = c.idCateg
    WHERE s.idService = ? AND s.statut = 'actif'
");
$stmt->execute([$idService]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    $_SESSION['error'] = 'Service introuvable ou inactif.';
    header('Location: ./services_publiques.php');
    exit;
}

$message = '';
$erreur  = '';

//  TRAITEMENT DE LA DEMANDE 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmer'])) {
    $note = trim($_POST['note'] ?? '');

    try {
        // Check for existing active request
        $checkStmt = $pdo->prepare("
            SELECT idRequest FROM demande_service
            WHERE idUtilisateur = ? AND idService = ?
            AND statut IN ('en attente', 'assignée', 'en cours')
        ");
        $checkStmt->execute([$user['idUtilisateur'], $idService]);

        if ($checkStmt->fetch()) {
            $erreur = 'Vous avez déjà une demande en cours pour ce service.';
        } else {
            // Find the agent assigned to this service
            $agentStmt = $pdo->prepare("
                SELECT idUtilisateur FROM service_agent
                WHERE idService = ? LIMIT 1
            ");
            $agentStmt->execute([$idService]);
            $agentRow = $agentStmt->fetch(PDO::FETCH_ASSOC);
            $idAgent  = $agentRow ? $agentRow['idUtilisateur'] : null;

            // Insert with idAgent and correct status
            $insertStmt = $pdo->prepare("
                INSERT INTO demande_service
                    (idUtilisateur, idService, idAgent, note, statut, dateAssignation)
                VALUES (?, ?, ?, ?, 'assignée', NOW())
            ");
            $insertStmt->execute([$user['idUtilisateur'], $idService, $idAgent, $note]);

            $idRequest = $pdo->lastInsertId();
            $cancelUrl = genererLienAnnulation($pdo, 'demande', $idRequest, 'http://localhost/Sprint1_AGL');
            sendConfirmationDemandeEmail(
                $user['email'],
                $user['prenom'] . ' ' . $user['nom'],
                $service['nomService'],
                $cancelUrl
            );

            $_SESSION['success'] = 'Demande envoyée avec succès !';
            header('Location: ./mes_demandes_services.php');
            exit;
        }
    } catch (PDOException $e) {
        $erreur = "Erreur lors de l'enregistrement : " . $e->getMessage();
    }               
} 
// NOUVELLE FONCTION : email de confirmation de demande
function sendConfirmationDemandeEmail($to, $citizenName, $nomService, $cancelUrl) {
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
        $mail->Subject = "✅ Demande de service reçue — " . htmlspecialchars($nomService);

        $blocAnnulation = htmlBlocAnnulation($cancelUrl, 2);

        $mail->Body = "
        <html><body style='font-family:Arial,sans-serif;color:#333;line-height:1.6'>
        <div style='max-width:600px;margin:0 auto;padding:20px;background:#f9f9f9;border-radius:8px'>

            <div style='background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
                        color:white;padding:20px;border-radius:8px;margin-bottom:20px'>
                <h2 style='margin:0'>✅ Demande de service reçue</h2>
            </div>

            <div style='background:white;padding:20px;border-radius:8px;border-left:4px solid #667eea'>
                <p>Bonjour <strong>" . htmlspecialchars($citizenName) . "</strong>,</p>
                <p>Votre demande pour le service ci-dessous a bien été enregistrée.</p>

                <div style='background:#f5f5f5;padding:15px;border-radius:5px;margin:15px 0'>
                    <div style='margin:8px 0'>
                        <span style='font-weight:bold;color:#667eea'>🏢 Service :</span>
                        " . htmlspecialchars($nomService) . "
                    </div>
                    <div style='margin:8px 0'>
                        <span style='font-weight:bold;color:#667eea'>📊 Statut :</span>
                        <span style='background:#fff3cd;color:#856404;padding:3px 10px;
                                     border-radius:12px;font-weight:700;font-size:13px'>
                            En attente
                        </span>
                    </div>
                </div>

                <p>Un agent municipal traitera votre demande dans les meilleurs délais.
                   Vous pouvez suivre son avancement depuis votre espace personnel.</p>

                $blocAnnulation

                <div style='margin-top:20px;font-size:12px;color:#999;text-align:center'>
                    <p>Cet email a été envoyé automatiquement. Veuillez ne pas répondre.</p>
                    <p>Plateforme CityCare — Gestion des Services Municipaux</p>
                </div>
            </div>
        </div>
        </body></html>";

        $mail->send();
    } catch (Exception $e) {
        error_log("Email confirmation demande error: " . $mail->ErrorInfo);
    }
}

// ===================== CONFIGURATION DU HEADER =====================
$headerConfig = [
    'title'       => 'Demander un Service',
    'subtitle'    => 'Soumettre une demande de service municipal',
    'icon'        => '📋',
    'role'        => 'Citoyen',
    'profileLink' => './profil.php',
    'bgGradient'  => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<!-- ===================== CSS INTÉGRÉ ===================== -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Sora:wght@300;400;600;700&display=swap');

    :root {
        --violet: #667eea;
        --purple: #764ba2;
        --success: #10b981;
        --danger: #ef4444;
        --surface: #ffffff;
        --surface-2: #f8f7ff;
        --border: #e8e4f3;
        --text: #1e1b2e;
        --text-muted: #7c6fa0;
    }

    body {
        font-family: 'Sora', sans-serif;
        background: var(--surface-2);
        color: var(--text);
    }

    .ds-wrapper {
        max-width: 780px;
        margin: 30px auto;
        padding: 0 20px 60px;
    }

    /* Breadcrumb */
    .ds-breadcrumb {
        display: flex;
        gap: 8px;
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 28px;
    }

    .ds-breadcrumb a {
        color: var(--violet);
        text-decoration: none;
        font-weight: 600;
    }

    /* Hero */
    .ds-hero {
        background: linear-gradient(135deg, var(--violet), var(--purple));
        border-radius: 20px;
        padding: 32px;
        color: white;
        margin-bottom: 24px;
    }

    .ds-hero h1 {
        margin: 0 0 10px;
        font-size: 28px;
    }

    .ds-hero-badge {
        background: rgba(255,255,255,.2);
        border-radius: 20px;
        padding: 5px 12px;
        display: inline-block;
        margin-bottom: 10px;
        font-size: 12px;
        font-weight: 600;
    }

    /* Info Grid */
    .ds-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 14px;
        margin-bottom: 24px;
    }

    .ds-info-item {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 16px;
        display: flex;
        gap: 12px;
    }

    .ds-info-icon {
        font-size: 18px;
    }

    .ds-info-label {
        font-size: 11px;
        color: var(--text-muted);
        text-transform: uppercase;
        font-weight: 600;
    }

    .ds-info-value {
        font-size: 13px;
        font-weight: 600;
    }

    /* Agents */
    .ds-agents {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 20px;
        margin-bottom: 20px;
    }

    .ds-agent-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: 30px;
        padding: 7px 14px;
        font-size: 12px;
        font-weight: 600;
        margin: 4px;
    }

    /* Formulaire */
    .ds-form-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 32px;
    }

    .ds-field textarea {
        width: 100%;
        padding: 14px;
        border: 2px solid var(--border);
        border-radius: 12px;
        resize: vertical;
        min-height: 110px;
        margin-bottom: 20px;
    }

    .ds-btn {
        padding: 13px 26px;
        border-radius: 12px;
        font-weight: 700;
        border: none;
        cursor: pointer;
        text-decoration: none;
    }

    .ds-btn-primary {
        background: linear-gradient(135deg, var(--violet), var(--purple));
        color: white;
    }

    .ds-btn-secondary {
        background: var(--surface-2);
        border: 2px solid var(--border);
        color: var(--text-muted);
    }

    .ds-alert-error {
        background: #fef2f2;
        color: #b91c1c;
        border-left: 4px solid var(--danger);
        padding: 14px;
        border-radius: 12px;
        margin-bottom: 20px;
    }
</style>

<!-- ===================== AFFICHAGE DES ERREURS ===================== -->
<?php if (!empty($erreur)): ?>
    <div class="ds-alert-error">
        ⚠️ <?= htmlspecialchars($erreur) ?>
    </div>
<?php endif; ?>

<div class="ds-wrapper">

    <!-- Breadcrumb -->
    <div class="ds-breadcrumb">
        <a href="./citoyen.php">🏠 Accueil</a> ›
        <a href="./services_publiques.php">Services Publics</a> ›
        <span>Demander un service</span>
    </div>

    <!-- Hero -->
    <div class="ds-hero">
        <div class="ds-hero-badge">
            🏢 <?= htmlspecialchars($service['nomCateg'] ?? 'Service municipal') ?>
        </div>
        <h1><?= htmlspecialchars($service['nomService']) ?></h1>
        <p><?= htmlspecialchars($service['descriptionService']) ?></p>
    </div>

    <!-- Informations -->
    <div class="ds-info-grid">
        <?php if (!empty($service['adresse'])): ?>
        <div class="ds-info-item">
            <div class="ds-info-icon">📍</div>
            <div>
                <div class="ds-info-label">Adresse</div>
                <div class="ds-info-value"><?= htmlspecialchars($service['adresse']) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($service['telephone'])): ?>
        <div class="ds-info-item">
            <div class="ds-info-icon">📞</div>
            <div>
                <div class="ds-info-label">Téléphone</div>
                <div class="ds-info-value"><?= htmlspecialchars($service['telephone']) ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Agents responsables -->
    <?php
    $agents = $pdo->prepare("
        SELECT u.prenom, u.nom
        FROM service_agent sa
        JOIN utilisateur u ON sa.idUtilisateur = u.idUtilisateur
        WHERE sa.idService = ?
    ");
    $agents->execute([$idService]);
    $agentsList = $agents->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <?php if (!empty($agentsList)): ?>
    <div class="ds-agents">
        <h3>👥 Agents responsables</h3>
        <?php foreach ($agentsList as $a): ?>
            <div class="ds-agent-pill">
                <?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Formulaire -->
    <div class="ds-form-card">
        <h2>📋 Confirmer votre demande</h2>
        <form method="POST">
            <div class="ds-field">
                <label for="note">Note / Commentaire (optionnel)</label>
                <textarea id="note" name="note"><?= htmlspecialchars($_POST['note'] ?? '') ?></textarea>
            </div>

            <button type="submit" name="confirmer" class="ds-btn ds-btn-primary">
                🚀 Envoyer la demande
            </button>
            <a href="./services_publiques.php" class="ds-btn ds-btn-secondary">
                ← Retour
            </a>
            <a href="./mes_demandes_services.php" class="ds-btn ds-btn-secondary">
               📜 Mes demandes
             </a>
        </form>
    </div>


</div>


<?php
require_once 'includes/footer.php';
ob_end_flush();
?>