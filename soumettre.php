<?php
require_once 'Config/database.php';
require_once 'Auth/auth.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once 'vendor/autoload.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

if ($user['role'] !== 'citoyen') {
    header('Location: ./login.php');
    exit;
}

$pdo = getConnexion();
$message = '';
$erreur = '';

// Récupérer les catégories
$categories = $pdo->query("SELECT idCateg, label FROM categorie ORDER BY label")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre       = trim($_POST['titre']       ?? '');
    $description = trim($_POST['description'] ?? '');
    $adresse     = trim($_POST['adresse']     ?? '');
    $categs      = $_POST['categories']       ?? [];
    $photos      = $_POST['photos']           ?? [];

    // Validation
    if (empty($titre) || empty($description) || empty($adresse)) {
        $erreur = 'Tous les champs sont obligatoires.';
    } elseif (strlen($titre) < 5) {
        $erreur = 'Le titre doit contenir au moins 5 caractères.';
    } elseif (strlen($description) < 20) {
        $erreur = 'La description doit contenir au moins 20 caractères.';
    } else {
        try {
            // Insérer la réclamation
            $stmt = $pdo->prepare("
                INSERT INTO reclamation (titre, description, adresse, idUtilisateur, statut, dateCreation, dateModification)
                VALUES (?, ?, ?, ?, 'en attente', NOW(), NOW())
            ");
            $stmt->execute([$titre, $description, $adresse, $user['idUtilisateur']]);
            $idRec = $pdo->lastInsertId();

            // Ajouter les catégories
            if (!empty($categs)) {
                $catStmt = $pdo->prepare("INSERT INTO reclamation_categorie (idRec, idCateg) VALUES (?, ?)");
                foreach ($categs as $catId) {
                    $catStmt->execute([$idRec, (int)$catId]);
                }
            }

            // AUTO-ASSIGNMENT
            $agentAssigne = null;
            $agentData    = null;

            if (!empty($categs)) {
                $placeholders = implode(',', array_fill(0, count($categs), '?'));
                $agentStmt = $pdo->prepare("
                    SELECT idUtilisateur, nom, prenom, email
                    FROM utilisateur
                    WHERE role = 'agent'
                      AND statut = 'actif'
                      AND idCateg IN ($placeholders)
                    LIMIT 1
                ");
                $agentStmt->execute(array_map('intval', $categs));
                $agentData    = $agentStmt->fetch(PDO::FETCH_ASSOC);
                $agentAssigne = $agentData ? $agentData['idUtilisateur'] : null;
            }

            if ($agentAssigne) {
                // Assign the complaint
                $assignStmt = $pdo->prepare("
                    UPDATE reclamation
                    SET idUtilisateurAssigne = ?, dateAssignation = NOW()
                    WHERE idRec = ?
                ");
                $assignStmt->execute([$agentAssigne, $idRec]);

                // Send email to agent
                sendAssignmentEmail($agentData, [
                    'idRec'       => $idRec,
                    'titre'       => $titre,
                    'description' => $description,
                    'adresse'     => $adresse,
                    'citoyen'     => $user['prenom'] . ' ' . $user['nom'],
                    'dateCreation'=> date('d/m/Y'),
                ]);
            }

            // ✅ MODIFICATION: Stocker le message dans la SESSION
            $_SESSION['success'] = 'Réclamation soumise avec succès ! Référence: #' . $idRec;
            
            // Ne pas afficher le formulaire après succès
            $message = 'Réclamation soumise avec succès ! Référence: #' . $idRec;
            $showForm = false;

        } catch (Exception $e) {
            $erreur = 'Erreur lors de la soumission: ' . $e->getMessage();
            $showForm = true;
        }
    }
} else {
    $showForm = true;
}

/**
 * Send assignment notification email to the agent
 */
function sendAssignmentEmail($agent, $complaint) {
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
        $mail->addAddress($agent['email']);

        $mail->isHTML(true);
        $mail->Subject = "🔔 Nouvelle réclamation assignée - #" . $complaint['idRec'];

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
                .button { display:inline-block; background:#667eea; color:white; padding:12px 25px;
                          text-decoration:none; border-radius:5px; margin-top:15px; font-weight:600; }
                .footer { margin-top:20px; font-size:12px; color:#999; text-align:center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔔 Nouvelle Réclamation Assignée</h2>
                </div>
                <div class='content'>
                    <p>Bonjour <strong>" . htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) . "</strong>,</p>
                    <p>Une nouvelle réclamation vient d'être automatiquement assignée à votre compte.</p>

                    <div class='details'>
                        <div class='detail-row'>
                            <span class='label'>📌 Numéro :</span> #" . $complaint['idRec'] . "
                        </div>
                        <div class='detail-row'>
                            <span class='label'>📝 Titre :</span> " . htmlspecialchars($complaint['titre']) . "
                        </div>
                        <div class='detail-row'>
                            <span class='label'>📋 Description :</span> " . htmlspecialchars(mb_substr($complaint['description'], 0, 150)) . "...
                        </div>
                        <div class='detail-row'>
                            <span class='label'>📍 Adresse :</span> " . htmlspecialchars($complaint['adresse']) . "
                        </div>
                        <div class='detail-row'>
                            <span class='label'>👤 Citoyen :</span> " . htmlspecialchars($complaint['citoyen']) . "
                        </div>
                        <div class='detail-row'>
                            <span class='label'>📅 Date :</span> " . $complaint['dateCreation'] . "
                        </div>
                    </div>

                    <p>Veuillez consulter votre tableau de bord et commencer le traitement.</p>
                    <a href='http://localhost/Sprint1_AGL/agent.php' class='button'>Accéder à mon tableau de bord</a>

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
        error_log("Email error (soumettre.php): " . $mail->ErrorInfo);
    }
}

// Custom header config
$headerConfig = [
    'title'       => 'Soumettre une réclamation',
    'subtitle'    => 'Décrivez votre problème et nous y répondrons rapidement',
    'icon'        => '✍️',
    'role'        => 'Citoyen',
    'profileLink' => './profil.php',
    'bgGradient'  => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<?php if ($erreur): ?>
    <div class="alert-error" style="max-width:1200px;margin:20px auto">❌ <?= htmlspecialchars($erreur) ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <!-- ✅ MESSAGE DE SUCCÈS -->
    <div class="alert-success" style="max-width:1200px;margin:20px auto">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px">
            <div>
                <strong>✅ <?= htmlspecialchars($message) ?></strong>
                <p style="margin:10px 0 0;color:#155724;font-size:14px">Un agent a été assigné pour traiter votre réclamation.</p>
            </div>
        </div>
    </div>

    <!-- ✨ OPTIONS APRÈS SUCCÈS -->
    <div class="card" style="max-width:1200px;margin:20px auto">
        <h2 style="margin-top:0">Que souhaitez-vous faire?</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px">
            
            <!-- Option 1: Retour au dashboard -->
            <a href="./citoyen.php" style="padding:15px;background:linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);color:white;text-decoration:none;border-radius:6px;text-align:center;font-weight:600;transition:all 0.3s">
                📊 Voir mes réclamations
            </a>
            
            <!-- Option 2: Voir les réclamations publiques -->
            <a href="./reclamations_publiques.php" style="padding:15px;background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);color:white;text-decoration:none;border-radius:6px;text-align:center;font-weight:600;transition:all 0.3s">
                🌍 Consulter les réclamations publiques
            </a>
            
            <!-- Option 3: Soumettre une autre -->
            <button onclick="location.reload()" style="padding:15px;background:linear-gradient(135deg, #1cc88a 0%, #0e9b5a 100%);color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;transition:all 0.3s">
                ➕ Soumettre une autre
            </button>
        </div>
    </div>

<?php else: ?>

    <!-- FORMULAIRE -->
    <div class="card" style="max-width:1200px;margin:20px auto">
        <h2 style="margin-top:0">📝 Formulaire de Réclamation</h2>
        
        <form method="POST">
            <div class="form-group">
                <label>Titre de la réclamation *</label>
                <input type="text" name="titre" placeholder="Résumé du problème" required minlength="5"
                       value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>"
                       style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box">
                <small style="color:#666">Au moins 5 caractères</small>
            </div>

            <div class="form-group">
                <label>Description détaillée *</label>
                <textarea name="description" placeholder="Décrivez le problème en détail..." required minlength="20"
                          style="width:100%;min-height:120px;padding:10px;border:1px solid #ccc;border-radius:4px;font-family:Arial;box-sizing:border-box"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                <small style="color:#666">Au moins 20 caractères</small>
            </div>

            <div class="form-group">
                <label>Adresse ou localisation *</label>
                <input type="text" name="adresse" placeholder="Lieu du problème" required
                       value="<?= htmlspecialchars($_POST['adresse'] ?? '') ?>"
                       style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box">
            </div>

            <div class="form-group">
                <label>Photo (optionnel)</label>
                <input type="file" name="photos" accept="image/*"
                       style="width:100%;padding:10px;border:1px solid #ccc;border-radius:4px;box-sizing:border-box">
                <small style="color:#666">Format: JPG, PNG (Max 5MB)</small>
            </div>

            <div class="form-group">
                <label>Catégories (sélectionner au moins une) *</label>
                <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:10px">
                    <?php foreach ($categories as $cat): ?>
                        <label style="display:flex;align-items:center;gap:8px;padding:8px;background:#f9f9f9;border-radius:4px;cursor:pointer">
                            <input type="checkbox" name="categories[]" value="<?= $cat['idCateg'] ?>"
                                   <?= in_array($cat['idCateg'], $_POST['categories'] ?? []) ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($cat['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:25px;flex-wrap:wrap">
                <button type="submit" style="padding:12px 30px;background:linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);color:white;border:none;border-radius:4px;cursor:pointer;font-weight:bold;transition:all 0.3s">
                    ✅ Soumettre la réclamation
                </button>
                <a href="./citoyen.php" style="padding:12px 30px;background:#6c757d;color:white;border:none;border-radius:4px;text-decoration:none;text-align:center;font-weight:bold;transition:all 0.3s">
                    ✕ Annuler
                </a>
            </div>
        </form>
    </div>

<?php endif; ?>

<style>
.card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    padding: 20px;
    border-radius: 6px;
    border-left: 5px solid #28a745;
}

.alert-error {
    background: #fdecea;
    color: #b00020;
    padding: 20px;
    border-radius: 6px;
    border-left: 5px solid #b00020;
}

button:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

a:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>

<?php require_once 'includes/footer.php'; ?>