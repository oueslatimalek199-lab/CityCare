<?php
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

$pageTitle = 'Tableau de bord - Agent';
$pdo = getConnexion();

// Handle status update from the table dropdown
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_statut'])) {
    $idRec   = (int)$_POST['idRec'];
    $statut  = $_POST['statut'];
    $allowed = ['en attente', 'en traitement', 'résolu'];

    if (in_array($statut, $allowed)) {
        // Get citizen email before updating
        $citizenStmt = $pdo->prepare("
            SELECT r.titre, u.email AS citoyen_email, u.prenom AS citoyen_prenom, u.nom AS citoyen_nom
            FROM reclamation r
            LEFT JOIN utilisateur u ON r.idUtilisateur = u.idUtilisateur
            WHERE r.idRec = ? AND r.idUtilisateurAssigne = ?
        ");
        $citizenStmt->execute([$idRec, $user['idUtilisateur']]);
        $citizenData = $citizenStmt->fetch(PDO::FETCH_ASSOC);

        // Update status
        $upd = $pdo->prepare("UPDATE reclamation SET statut = ?, dateModification = NOW() WHERE idRec = ? AND idUtilisateurAssigne = ?");
        $upd->execute([$statut, $idRec, $user['idUtilisateur']]);

        // Send email to citizen
        if ($citizenData && !empty($citizenData['citoyen_email'])) {
            sendStatusEmail(
                $citizenData['citoyen_email'],
                $citizenData['citoyen_prenom'] . ' ' . $citizenData['citoyen_nom'],
                $idRec,
                $citizenData['titre'],
                $statut,
                ''
            );
        }

        $_SESSION['success'] = 'Statut mis à jour ✓ - Email envoyé au citoyen';
    }
    header('Location: ./agent.php');
    exit;
}

$successMsg = $_SESSION['success'] ?? '';
$errorMsg   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Filter by status
$filterStatut = $_GET['statut'] ?? 'tous';
$whereStatut  = ($filterStatut !== 'tous') ? "AND r.statut = " . $pdo->quote($filterStatut) : '';

// Récupérer les réclamations assignées à cet agent
$reclamations = $pdo->prepare("
    SELECT r.idRec,
           r.titre,
           r.description,
           r.adresse,
           r.statut,
           r.dateCreation,
           r.dateAssignation,
           GROUP_CONCAT(c.label SEPARATOR ', ') AS categories,
           u.nom AS citoyen_nom,
           u.prenom AS citoyen_prenom,
           u.telephone AS citoyen_telephone,
           u.email AS citoyen_email,
           DATEDIFF(NOW(), r.dateAssignation) AS jours_assignation
    FROM reclamation r
    LEFT JOIN reclamation_categorie rc ON rc.idRec = r.idRec
    LEFT JOIN categorie c ON rc.idCateg = c.idCateg
    LEFT JOIN utilisateur u ON r.idUtilisateur = u.idUtilisateur
    WHERE r.idUtilisateurAssigne = ?
    $whereStatut
    GROUP BY r.idRec
    ORDER BY r.dateAssignation DESC
");
$reclamations->execute([$user['idUtilisateur']]);
$myReclamations = $reclamations->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'en attente'    THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'en traitement' THEN 1 ELSE 0 END) as en_traitement,
        SUM(CASE WHEN statut = 'résolu'        THEN 1 ELSE 0 END) as resolu,
        SUM(CASE WHEN statut = 'annulé'        THEN 1 ELSE 0 END) as annule
    FROM reclamation
    WHERE idUtilisateurAssigne = ?
");
$statsStmt->execute([$user['idUtilisateur']]);
$stat = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Réclamations en retard (> 15 jours)
$retardStmt = $pdo->prepare("
    SELECT COUNT(*) as nombre
    FROM reclamation
    WHERE idUtilisateurAssigne = ?
      AND statut != 'résolu'
      AND DATEDIFF(NOW(), dateAssignation) > 15
");
$retardStmt->execute([$user['idUtilisateur']]);
$retard   = $retardStmt->fetch(PDO::FETCH_ASSOC);
$enRetard = $retard ? $retard['nombre'] : 0;

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
        error_log("Email error (agent.php): " . $mail->ErrorInfo);
    }
}

// Custom header config
$headerConfig = [
    'title'       => 'Gestionnaire de Réclamations',
    'subtitle'    => 'Traitez et suivez vos réclamations assignées',
    'icon'        => '🔧',
    'role'        => 'Agent Municipal',
    'profileLink' => './profil.php',
    'bgGradient'  => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<?php if ($successMsg): ?>
    <div class="alert-success"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert-error"><?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<!-- 🆕 BF 9: LINK TO SERVICE REQUESTS -->
<div style="margin-bottom: 20px; padding: 15px; background: linear-gradient(135deg, #764ba2 0%, #667eea 100%); border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
    <a href="./agent_services.php" style="color: white; text-decoration: none; font-weight: 600; display: inline-block; padding: 8px 16px; background: rgba(255,255,255,0.15); border-radius: 6px; transition: all 0.3s;">
        🔧 Voir mes demandes de services
    </a>
</div>

<!-- ===== STATS CARDS ===== -->
<div class="stats-grid">
    <div class="stat-card" style="border-left:4px solid #667eea">
        <div class="stat-number" style="color:#667eea"><?= $stat['total'] ?></div>
        <div class="stat-label">Total assignées</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #f6c23e">
        <div class="stat-number" style="color:#f6c23e"><?= $stat['en_attente'] ?></div>
        <div class="stat-label">En attente</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #36b9cc">
        <div class="stat-number" style="color:#36b9cc"><?= $stat['en_traitement'] ?></div>
        <div class="stat-label">En traitement</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #1cc88a">
        <div class="stat-number" style="color:#1cc88a"><?= $stat['resolu'] ?></div>
        <div class="stat-label">Résolues</div>
    </div>
    <div class="stat-card" style="border-left:4px solid #e74a3b">
        <div class="stat-number" style="color:#e74a3b"><?= $enRetard ?></div>
        <div class="stat-label">En retard (&gt;15j)</div>
    </div>
</div>

<!-- ===== FILTER BAR ===== -->
<div class="card" style="margin-bottom:20px">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <span style="font-weight:600;color:#333">🔍 Filtrer par statut :</span>
        <?php
        $filters = [
            'tous'          => ['label' => 'Tous',          'color' => '#667eea'],
            'en attente'    => ['label' => 'En attente',    'color' => '#f6c23e'],
            'en traitement' => ['label' => 'En traitement', 'color' => '#36b9cc'],
            'résolu'        => ['label' => 'Résolu',        'color' => '#1cc88a'],
        ];
        foreach ($filters as $key => $f):
            $active = ($filterStatut === $key);
        ?>
            <a href="?statut=<?= urlencode($key) ?>"
               style="padding:6px 16px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;
                      background:<?= $active ? $f['color'] : '#f0f2f5' ?>;
                      color:<?= $active ? 'white' : '#555' ?>;
                      border:2px solid <?= $f['color'] ?>;">
                <?= $f['label'] ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ===== COMPLAINTS TABLE ===== -->
<div class="card">
    <h2 style="margin-bottom:5px">📋 Mes Réclamations Assignées</h2>
    <p style="color:#888;font-size:13px;margin-bottom:20px">
        <?= count($myReclamations) ?> réclamation(s) trouvée(s)
        <?= $filterStatut !== 'tous' ? '— filtrées : <strong>' . htmlspecialchars($filterStatut) . '</strong>' : '' ?>
    </p>

    <?php if (empty($myReclamations)): ?>
        <div style="text-align:center;padding:60px 20px;color:#aaa">
            <div style="font-size:48px;margin-bottom:15px">📭</div>
            <h3>Aucune réclamation trouvée</h3>
            <p>Aucune réclamation ne correspond à ce filtre.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Titre</th>
                    <th>Citoyen</th>
                    <th>Adresse</th>
                    <th>Catégorie</th>
                    <th>Jours écoulés</th>
                    <th>Statut</th>
                    <th>Modifier statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($myReclamations as $rec):
                $jours  = (int)$rec['jours_assignation'];
                $retard = ($jours > 15 && $rec['statut'] !== 'résolu');
                $rowBg  = $retard ? '#fff5f5' : 'white';
            ?>
            <tr style="background:<?= $rowBg ?>;<?= $retard ? 'border-left:4px solid #e74a3b' : '' ?>">
                <td><strong>#<?= $rec['idRec'] ?></strong></td>

                <td>
                    <span title="<?= htmlspecialchars($rec['description']) ?>"
                          style="cursor:help;font-weight:600">
                        <?= htmlspecialchars(mb_substr($rec['titre'], 0, 25)) ?><?= mb_strlen($rec['titre']) > 25 ? '…' : '' ?>
                    </span>
                </td>

                <td>
                    <div style="font-weight:600"><?= htmlspecialchars($rec['citoyen_prenom'] . ' ' . $rec['citoyen_nom']) ?></div>
                    <div style="font-size:11px;color:#888"><?= htmlspecialchars($rec['citoyen_email']) ?></div>
                </td>

                <td style="font-size:12px;color:#555"><?= htmlspecialchars(mb_substr($rec['adresse'] ?? '—', 0, 30)) ?></td>

                <td style="font-size:12px;color:#555"><?= htmlspecialchars($rec['categories'] ?? '—') ?></td>

                <td style="text-align:center">
                    <?php if ($retard): ?>
                        <span style="color:#e74a3b;font-weight:700"><?= $jours ?>j ⚠️</span>
                    <?php else: ?>
                        <span style="color:#555"><?= $jours ?>j</span>
                    <?php endif; ?>
                </td>

                <td>
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
                                 padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap">
                        <?= htmlspecialchars($rec['statut']) ?>
                    </span>
                </td>

                <td>
                    <?php if ($rec['statut'] !== 'résolu' && $rec['statut'] !== 'annulé'): ?>
                    <form method="POST" style="display:flex;gap:5px;align-items:center">
                        <input type="hidden" name="idRec" value="<?= $rec['idRec'] ?>">
                        <select name="statut"
                                style="padding:5px 8px;border-radius:5px;border:1px solid #ccc;font-size:12px">
                            <option value="en attente"    <?= $rec['statut'] === 'en attente'    ? 'selected' : '' ?>>En attente</option>
                            <option value="en traitement" <?= $rec['statut'] === 'en traitement' ? 'selected' : '' ?>>En traitement</option>
                            <option value="résolu"        <?= $rec['statut'] === 'résolu'        ? 'selected' : '' ?>>Résolu</option>
                        </select>
                        <button type="submit" name="update_statut"
                                title="Mettre à jour et notifier le citoyen"
                                style="background:#667eea;color:white;border:none;padding:5px 10px;
                                       border-radius:5px;cursor:pointer;font-size:12px;font-weight:600">
                            ✓
                        </button>
                    </form>
                    <?php else: ?>
                        <span style="color:#aaa;font-size:12px">—</span>
                    <?php endif; ?>
                </td>

                <td>
                    <div style="display:flex;gap:5px;flex-wrap:wrap">
                        <a href="./detail_reclamation.php?id=<?= $rec['idRec'] ?>&role=agent"
                           style="background:#17a2b8;color:white;padding:5px 10px;border-radius:5px;
                                  font-size:12px;text-decoration:none;font-weight:600">
                            👁️ Voir
                        </a>
                        <?php if ($rec['statut'] !== 'résolu' && $rec['statut'] !== 'annulé'): ?>
                        <a href="./traiter_reclamation.php?id=<?= $rec['idRec'] ?>"
                           style="background:#764ba2;color:white;padding:5px 10px;border-radius:5px;
                                  font-size:12px;text-decoration:none;font-weight:600">
                            🔧 Traiter
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>

            <tr style="background:#fafafa;border-bottom:2px solid #eee">
                <td colspan="9" style="padding:8px 16px;font-size:12px;color:#666">
                    <strong>📝 Description :</strong> <?= htmlspecialchars(mb_substr($rec['description'], 0, 200)) ?><?= mb_strlen($rec['description']) > 200 ? '…' : '' ?>
                    &nbsp;|&nbsp;
                    <strong>📅 Assignée le :</strong> <?= date('d/m/Y', strtotime($rec['dateAssignation'])) ?>
                    &nbsp;|&nbsp;
                    <strong>📅 Créée le :</strong> <?= date('d/m/Y', strtotime($rec['dateCreation'])) ?>
                </td>
            </tr>

            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<style>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}
.stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    text-align: center;
}
.stat-number {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 5px;
}
.stat-label {
    font-size: 13px;
    color: #888;
}
.card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}
table {
    width: 100%;
    border-collapse: collapse;
}
table thead {
    background: #f5f5f5;
}
table th, table td {
    padding: 11px 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
    font-size: 13px;
}
table th {
    font-weight: 600;
    color: #444;
    white-space: nowrap;
}
table tbody tr:hover td {
    background: #f8f9ff;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    padding: 14px;
    border-radius: 6px;
    margin-bottom: 20px;
    border-left: 4px solid #28a745;
    font-weight: 600;
}
.alert-error {
    background: #fdecea;
    color: #b00020;
    padding: 14px;
    border-radius: 6px;
    margin-bottom: 20px;
    border-left: 4px solid #b00020;
    font-weight: 600;
}
</style>

<?php require_once 'includes/footer.php'; ?>