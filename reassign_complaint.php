<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
 require_once 'vendor/autoload.php';
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\Exception;

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

if ($user['role'] !== 'admin') {
    Auth::redirectToDashboard();
}

$pdo = getConnexion();
$message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$erreur = isset($_SESSION['error']) ? $_SESSION['error'] : '';

// Clear session messages
unset($_SESSION['success'], $_SESSION['error']);

// Réclamations en retard avec possibilité de réassignment
$retard = $pdo->query("
    SELECT r.idRec, r.titre, r.statut, 
           DATEDIFF(NOW(), r.dateAssignation) AS jours,
           u.prenom, u.nom, u.email,
           a.prenom AS agent_prenom, a.nom AS agent_nom,
           a.idUtilisateur AS agent_id
    FROM reclamation r
    LEFT JOIN utilisateur u ON r.idUtilisateur = u.idUtilisateur
    LEFT JOIN utilisateur a ON r.idUtilisateurAssigne = a.idUtilisateur
    WHERE r.statut != 'résolu' AND DATEDIFF(NOW(), r.dateAssignation) > 15
    ORDER BY jours DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Tous les agents actifs
$allAgents = $pdo->query("
    SELECT idUtilisateur, nom, prenom, email, statut
    FROM utilisateur
    WHERE role = 'agent'
    ORDER BY nom, prenom
")->fetchAll(PDO::FETCH_ASSOC);

// Handle reassignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reassign'])) {
    $idRec = (int)$_POST['idRec'];
    $newAgent = (int)$_POST['newAgent'];

    if (!$idRec || !$newAgent) {
        $_SESSION['error'] = 'Données invalides';
    } else {
        // Verify complaint exists and get details
        $complaintCheck = $pdo->prepare("
            SELECT r.*, u.email AS citoyen_email, u.prenom AS citoyen_prenom, u.nom AS citoyen_nom
            FROM reclamation r
            LEFT JOIN utilisateur u ON r.idUtilisateur = u.idUtilisateur
            WHERE r.idRec = ?
        ");
        $complaintCheck->execute([$idRec]);
        $complaint = $complaintCheck->fetch(PDO::FETCH_ASSOC);

        if (!$complaint) {
            $_SESSION['error'] = 'Réclamation introuvable';
        } else {
            // Verify new agent exists and is active
            $agentCheck = $pdo->prepare("SELECT idUtilisateur, email, prenom, nom FROM utilisateur WHERE idUtilisateur = ? AND role = 'agent' AND statut = 'actif'");
            $agentCheck->execute([$newAgent]);
            $newAgentData = $agentCheck->fetch(PDO::FETCH_ASSOC);

            if (!$newAgentData) {
                $_SESSION['error'] = 'Agent invalide ou inactif';
            } else {
                // Perform reassignment
                try {
                    $update = $pdo->prepare("
                        UPDATE reclamation 
                        SET idUtilisateurAssigne = ?, dateAssignation = NOW(), dateModification = NOW()
                        WHERE idRec = ?
                    ");
                    $update->execute([$newAgent, $idRec]);
                    
                    // ===== SEND EMAILS =====
                    
                    // 1. EMAIL TO NEW AGENT
                    sendEmailToAgent($newAgentData, $complaint);
                    
                    // 2. EMAIL TO CITIZEN
                    sendEmailToCitizen($complaint, $newAgentData);
                    
                    $_SESSION['success'] = 'Réclamation réassignée avec succès ✓ - Emails envoyés';
                    header('Location: ./reassign_complaint.php');
                    exit;
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Erreur lors de la réassignation: ' . $e->getMessage();
                }
            }
        }
    }

    header('Location: ./reassign_complaint.php');
    exit;
}

// ===== EMAIL FUNCTIONS =====

/**
 * Send email to the newly assigned agent
 */
function sendEmailToAgent($agent, $complaint) {
    $to = $agent['email'];
    $subject = "🔄 Nouvelle réclamation assignée - #" . $complaint['idRec'];
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
            .content { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #667eea; }
            .details { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .detail-row { margin: 8px 0; }
            .label { font-weight: bold; color: #667eea; }
            .button { display: inline-block; background: #667eea; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
            .footer { margin-top: 20px; font-size: 12px; color: #999; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🔄 Nouvelle Réclamation Assignée</h2>
            </div>
            
            <div class='content'>
                <p>Bonjour <strong>" . htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) . "</strong>,</p>
                
                <p>Une nouvelle réclamation vient d'être assignée à votre compte.</p>
                
                <div class='details'>
                    <div class='detail-row'><span class='label'>📌 ID Réclamation:</span> #" . $complaint['idRec'] . "</div>
                    <div class='detail-row'><span class='label'>📝 Titre:</span> " . htmlspecialchars($complaint['titre']) . "</div>
                    <div class='detail-row'><span class='label'>📋 Description:</span> " . htmlspecialchars(substr($complaint['description'], 0, 100)) . "...</div>
                    <div class='detail-row'><span class='label'>📍 Adresse:</span> " . htmlspecialchars($complaint['adresse']) . "</div>
                    <div class='detail-row'><span class='label'>📊 Statut:</span> " . htmlspecialchars($complaint['statut']) . "</div>
                    <div class='detail-row'><span class='label'>👤 Citoyen:</span> " . htmlspecialchars($complaint['citoyen_prenom'] . ' ' . $complaint['citoyen_nom']) . "</div>
                    <div class='detail-row'><span class='label'>📅 Date Création:</span> " . date('d/m/Y H:i', strtotime($complaint['dateCreation'])) . "</div>
                </div>
                
                <p>Veuillez consulter le détail complet de cette réclamation dans votre tableau de bord et commencer le traitement.</p>
                
                <a href='http://localhost/Sprint1_AGL/agent.php' class='button'>Accéder à mon tableau de bord</a>
                
                <div class='footer'>
                    <p>Cet email a été envoyé automatiquement. Veuillez ne pas répondre à cet email.</p>
                    <p>Plateforme de Gestion des Réclamations</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    sendEmail($to, $subject, $message);
}

/**
 * Send email to the citizen about reassignment
 */
function sendEmailToCitizen($complaint, $newAgent) {
    $to = $complaint['citoyen_email'];
    $subject = "✓ Votre réclamation #" . $complaint['idRec'] . " a été réassignée";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px; }
            .header { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
            .content { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #4facfe; }
            .details { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .detail-row { margin: 8px 0; }
            .label { font-weight: bold; color: #4facfe; }
            .button { display: inline-block; background: #4facfe; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin-top: 15px; }
            .footer { margin-top: 20px; font-size: 12px; color: #999; text-align: center; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>✓ Réclamation Réassignée</h2>
            </div>
            
            <div class='content'>
                <p>Bonjour <strong>" . htmlspecialchars($complaint['citoyen_prenom'] . ' ' . $complaint['citoyen_nom']) . "</strong>,</p>
                
                <p>Votre réclamation a été réassignée à un nouvel agent municipal qui assurera un suivi optimal.</p>
                
                <div class='details'>
                    <div class='detail-row'><span class='label'>📌 Numéro de réclamation:</span> #" . $complaint['idRec'] . "</div>
                    <div class='detail-row'><span class='label'>📝 Titre:</span> " . htmlspecialchars($complaint['titre']) . "</div>
                    <div class='detail-row'><span class='label'>👤 Nouvel agent assigné:</span> " . htmlspecialchars($newAgent['prenom'] . ' ' . $newAgent['nom']) . "</div>
                    <div class='detail-row'><span class='label'>📧 Email agent:</span> " . htmlspecialchars($newAgent['email']) . "</div>
                    <div class='detail-row'><span class='label'>📊 Statut:</span> " . htmlspecialchars($complaint['statut']) . "</div>
                </div>
                
                <p>L'agent assigné prendra rapidement connaissance de votre dossier et vous contactera si nécessaire.</p>
                
                <p>Vous pouvez suivre l'avancement de votre réclamation dans votre espace personnel.</p>
                
                <a href='http://localhost/Sprint1_AGL/citoyen.php' class='button'>Consulter ma réclamation</a>
                
                <div class='footer'>
                    <p>Cet email a été envoyé automatiquement. Veuillez ne pas répondre à cet email.</p>
                    <p>Plateforme de Gestion des Réclamations</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ";
    
    sendEmail($to, $subject, $message);
}

/**
 * Generic email sending function — uses PHPMailer via SMTP Gmail
 */
function sendEmail($to, $subject, $message) {

    $mail = new PHPMailer(true);
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'amaltoumi099@gmail.com';   // 👈 Remplacez par votre Gmail
        $mail->Password   = 'sfmw iqmj jxxb uwpv';    // 👈 Remplacez par votre App Password Gmail
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Sender & Recipient
        $mail->setFrom('your-gmail@gmail.com', 'Plateforme Réclamations');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $message;

        $mail->send();
    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
    }
}

// Custom header config
$headerConfig = [
    'title' => 'Réaffecter les Réclamations',
    'subtitle' => 'Réassignez les réclamations en retard à d\'autres agents',
    'icon' => '🔄',
    'role' => 'Administrateur',
    'profileLink' => './profil.php',
    'bgGradient' => 'linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<?php if (!empty($erreur)): ?>
    <div class="alert-error"><?= htmlspecialchars($erreur) ?></div>
<?php endif; ?>

<?php if (!empty($message)): ?>
    <div class="alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<!-- STATISTIQUES -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:30px">
    <div class="card stat-box">
        <h3><?= count($retard) ?></h3>
        <p>En retard</p>
    </div>
    <div class="card stat-box" style="border-left:4px solid #28a745">
        <h3><?= count($allAgents) ?></h3>
        <p>Agents disponibles</p>
    </div>
</div>

<!-- TABLEAU DE RÉAFFECTATION -->
<div class="card">
    <h2>🔄 Réaffecter les Réclamations en Retard</h2>
    <p style="color:#666;margin-bottom:20px">Sélectionnez une réclamation et choisissez un nouvel agent assigné</p>
    
    <?php if (empty($retard)): ?>
        <div style="text-align:center;padding:60px 20px;color:#999">
            <h3>✓ Aucune réclamation en retard!</h3>
            <p>Toutes les réclamations sont à jour. Bravo! 🎉</p>
        </div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Titre</th>
                    <th>Citoyen</th>
                    <th>Agent actuel</th>
                    <th>Jours en retard</th>
                    <th>Statut</th>
                    <th>Réaffecter à</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($retard as $rec): ?>
            <tr style="background:#fff3cd;border-left:4px solid #dc3545">
                <td><strong>#<?= $rec['idRec'] ?></strong></td>
                <td><?= htmlspecialchars(substr($rec['titre'], 0, 20)) ?></td>
                <td><?= htmlspecialchars(($rec['prenom'] ?? '') . ' ' . ($rec['nom'] ?? '')) ?></td>
                <td><?= htmlspecialchars(($rec['agent_prenom'] ?? 'Non') . ' ' . ($rec['agent_nom'] ?? 'assignée')) ?></td>
                <td><strong style="color:#dc3545"><?= $rec['jours'] ?> jours</strong></td>
                <td>
                    <span class="badge badge-<?= $rec['statut'] === 'résolu' ? 'resolu' : 'traitement' ?>">
                        <?= htmlspecialchars($rec['statut']) ?>
                    </span>
                </td>
                <td>
                    <form method="POST" style="display:inline;display:flex;gap:5px;flex-wrap:wrap">
                        <input type="hidden" name="idRec" value="<?= $rec['idRec'] ?>">
                        <select name="newAgent" required style="padding:6px;border-radius:4px;border:1px solid #ccc;font-size:12px;flex:1;min-width:120px">
                            <option value="">-- Choisir --</option>
                            <?php foreach ($allAgents as $agent): ?>
                                <option value="<?= $agent['idUtilisateur'] ?>"
                                    <?= $agent['statut'] !== 'actif' ? 'disabled' : ($agent['idUtilisateur'] == $rec['agent_id'] ? 'disabled' : '') ?>>
                                    <?= htmlspecialchars($agent['prenom'] . ' ' . $agent['nom'])  ?>
                                    <?php if ($agent['statut'] !== 'actif'): ?>
                                        (Inactif)
                                    <?php elseif ($agent['idUtilisateur'] == $rec['agent_id']): ?>
                                        (Assigné actuellement)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" name="reassign" class="btn-reassign">🔄 Réaffecter</button>
                    </form>
                </td>
                <td>
                    <a href="./detail_reclamation.php?id=<?= $rec['idRec'] ?>&role=admin" class="btn-sm btn-info">Voir</a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- BACK BUTTON -->
<div style="margin-top:30px">
    <a href="./admin.php" class="btn-back">← Retour au tableau de bord</a>
</div>

<style>
.card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.stat-box {
    text-align: center;
    padding: 20px !important;
    border-left: 4px solid #2c7be5;
}

.stat-box h3 {
    font-size: 32px;
    margin: 0 0 10px 0;
    color: #2c7be5;
}

.stat-box p {
    margin: 0;
    color: #666;
    font-size: 14px;
}

.badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-traitement {
    background: #d1ecf1;
    color: #0c5460;
}

.badge-resolu {
    background: #d4edda;
    color: #155724;
}

.btn-reassign {
    background: #ff6b6b;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    transition: background 0.3s;
}

.btn-reassign:hover {
    background: #ee5a6f;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
    display: inline-block;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
}

.btn-info {
    background: #17a2b8;
    color: white;
}

.btn-info:hover {
    background: #138496;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

table thead {
    background: #f5f5f5;
}

table th, table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #ddd;
    font-size: 13px;
}

table th {
    font-weight: 600;
    color: #333;
}

table tbody tr:hover {
    background: #fff0e0;
}

.alert-error {
    background: #fdecea;
    color: #b00020;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    border-left: 4px solid #b00020;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    border-left: 4px solid #28a745;
}

.btn-back {
    display: inline-block;
    padding: 10px 20px;
    background: #6c757d;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-back:hover {
    background: #5a6268;
}
</style>

<?php require_once 'includes/footer.php'; ?>