<?php
require_once 'Config/database.php';
require_once 'Auth/auth.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

require_once 'includes/header.php';

$pdo = getConnexion();
$id = (int)($_GET['id'] ?? 0);
$role = $_GET['role'] ?? $user['role'];
$message = '';
$erreur = '';

// Get messages from redirect
if (isset($_GET['success'])) {
    if ($_GET['success'] === 'status_updated') {
        $message = 'Statut mis à jour avec succès ✓';
    }
}

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'invalid_data') {
        $erreur = 'Données invalides';
    } elseif ($_GET['error'] === 'unauthorized') {
        $erreur = 'Vous n\'êtes pas autorisé à modifier cette réclamation';
    } elseif ($_GET['error'] === 'invalid_status') {
        $erreur = 'Statut invalide';
    } elseif ($_GET['error'] === 'update_failed') {
        $erreur = 'Erreur lors de la mise à jour';
    }
}

// Récupérer la réclamation
$stmt = $pdo->prepare("
    SELECT r.*, 
           u.nom AS citoyen_nom, u.prenom AS citoyen_prenom, u.email AS citoyen_email, u.telephone AS citoyen_telephone,
           a.nom AS agent_nom, a.prenom AS agent_prenom, a.email AS agent_email,
           GROUP_CONCAT(c.label SEPARATOR ', ') AS categories
    FROM reclamation r
    LEFT JOIN utilisateur u ON r.idUtilisateur = u.idUtilisateur
    LEFT JOIN utilisateur a ON r.idUtilisateurAssigne = a.idUtilisateur
    LEFT JOIN reclamation_categorie rc ON rc.idRec = r.idRec
    LEFT JOIN categorie c ON c.idCateg = rc.idCateg
    WHERE r.idRec = ?
    GROUP BY r.idRec
");
$stmt->execute([$id]);
$rec = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rec) {
    header('Location: ./citoyen.php');
    exit;
}

// Vérifications d'accès
if ($user['role'] === 'citoyen' && $rec['idUtilisateur'] != $user['idUtilisateur']) {
    header('Location: ./citoyen.php');
    exit;
}

if ($user['role'] === 'agent' && $rec['idUtilisateurAssigne'] != $user['idUtilisateur']) {
    header('Location: ./agent.php');
    exit;
}
?>
<?php if ($user['role'] === 'citoyen' && $rec['idUtilisateurAssigne']): ?>
    <a href="./nouvelle_conversation.php?idRec=<?= $rec['idRec'] ?>" class="btn btn-message">
        💬 Contacter l'agent
    </a>
<?php endif; ?>

<?php if ($user['role'] === 'agent' && $rec['idUtilisateur']): ?>
    <a href="./nouvelle_conversation.php?idRec=<?= $rec['idRec'] ?>" class="btn btn-message">
        💬 Contacter le citoyen
    </a>
<?php endif; ?>

<div class="card">
    <h1>📋 Détails de la réclamation #<?= $rec['idRec'] ?></h1>
</div>

<?php if ($erreur): ?>
    <div class="alert-error"><?= htmlspecialchars($erreur) ?></div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:30px">
    <!-- CONTENU PRINCIPAL -->
    <div class="card">
        <h2><?= htmlspecialchars($rec['titre']) ?></h2>
        
        <div style="margin:20px 0;padding:15px;background:#f8f9fa;border-radius:4px;border-left:4px solid #2c7be5">
            <strong>Description:</strong><br>
            <?= nl2br(htmlspecialchars($rec['description'])) ?>
        </div>

        <table style="width:100%;margin:20px 0">
            <tr style="border-bottom:1px solid #ddd">
                <td style="padding:10px;font-weight:bold;width:150px">📍 Adresse:</td>
                <td style="padding:10px"><?= htmlspecialchars($rec['adresse'] ?? 'N/A') ?></td>
            </tr>
            <tr style="border-bottom:1px solid #ddd">
                <td style="padding:10px;font-weight:bold">🏷️ Catégories:</td>
                <td style="padding:10px"><?= htmlspecialchars($rec['categories'] ?? 'N/A') ?></td>
            </tr>
            <tr style="border-bottom:1px solid #ddd">
                <td style="padding:10px;font-weight:bold">📅 Créée le:</td>
                <td style="padding:10px"><?= date('d/m/Y H:i', strtotime($rec['dateCreation'])) ?></td>
            </tr>
            <tr style="border-bottom:1px solid #ddd">
                <td style="padding:10px;font-weight:bold">🔄 Modifiée le:</td>
                <td style="padding:10px"><?= date('d/m/Y H:i', strtotime($rec['dateModification'])) ?></td>
            </tr>
            <?php if ($rec['dateAssignation']): ?>
            <tr style="border-bottom:1px solid #ddd">
                <td style="padding:10px;font-weight:bold">📌 Assignée depuis:</td>
                <td style="padding:10px">
                    <?= date('d/m/Y H:i', strtotime($rec['dateAssignation'])) ?>
                    (<?= floor((time() - strtotime($rec['dateAssignation'])) / 86400) ?> jours)
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- SIDEBAR - STATUT & ACTIONS -->
    <div>
        <!-- STATUT -->
        <div class="card">
            <strong style="font-size:16px">📊 Statut Actuel</strong>
            <div style="margin:15px 0">
                <span class="badge badge-<?= $rec['statut'] === 'résolu' ? 'resolu' : ($rec['statut'] === 'en traitement' ? 'traitement' : ($rec['statut'] === 'annulé' ? 'annule' : 'attente')) ?>" style="font-size:16px;padding:10px 15px">
                    <?= htmlspecialchars($rec['statut']) ?>
                </span>
            </div>

            <!-- AGENT: METTRE À JOUR LE STATUT -->
            <?php if ($user['role'] === 'agent'): ?>
            <form method="POST" action="./update_status.php" style="margin-top:15px">
                <input type="hidden" name="idRec" value="<?= $rec['idRec'] ?>">
                
                <label style="display:block;font-weight:bold;margin-bottom:8px;font-size:14px">Mettre à jour le statut:</label>
                <select name="new_status" style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;margin-bottom:10px" required>
                    <option value="">-- Sélectionner --</option>
                    <option value="en attente" <?= $rec['statut'] === 'en attente' ? 'disabled' : '' ?>>En attente</option>
                    <option value="en traitement" <?= $rec['statut'] === 'en traitement' ? 'disabled' : '' ?>>En traitement</option>
                    <option value="résolu" <?= $rec['statut'] === 'résolu' ? 'disabled' : '' ?>>Résolu</option>
                    <option value="annulé" <?= $rec['statut'] === 'annulé' ? 'disabled' : '' ?>>Annulé</option>
                </select>
                <button type="submit" style="width:100%;padding:8px;background:#28a745;color:white;border:none;border-radius:4px;cursor:pointer;font-weight:bold">
                    ✓ Mettre à jour
                </button>
            </form>
            <?php endif; ?>
        </div>

        <!-- CITOYEN INFO -->
        <div class="card">
            <strong style="font-size:14px">👤 Citoyen</strong>
            <div style="margin:10px 0;padding:10px;background:#f8f9fa;border-radius:4px;font-size:13px">
                <div style="margin-bottom:5px"><strong><?= htmlspecialchars($rec['citoyen_prenom'] . ' ' . $rec['citoyen_nom']) ?></strong></div>
                <div style="color:#666;margin-bottom:3px">📧 <?= htmlspecialchars($rec['citoyen_email']) ?></div>
                <div style="color:#666">☎️ <?= htmlspecialchars($rec['citoyen_telephone'] ?? 'N/A') ?></div>
            </div>
        </div>

        <!-- AGENT INFO -->
        <div class="card">
            <strong style="font-size:14px">🔧 Agent Assigné</strong>
            <div style="margin:10px 0;padding:10px;background:#f8f9fa;border-radius:4px;font-size:13px">
                <?php if ($rec['agent_nom']): ?>
                    <div style="margin-bottom:5px"><strong><?= htmlspecialchars($rec['agent_prenom'] . ' ' . $rec['agent_nom']) ?></strong></div>
                    <div style="color:#666">📧 <?= htmlspecialchars($rec['agent_email']) ?></div>
                <?php else: ?>
                    <div style="color:#999">⚠️ Non assignée</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- BOUTON RETOUR -->
<div>
    <?php if ($user['role'] === 'citoyen'): ?>
        <a href="./citoyen.php" class="btn" style="padding:10px 20px;background:#2c7be5;color:white;border:none;border-radius:4px;text-decoration:none;cursor:pointer">← Retour aux réclamations</a>
    <?php elseif ($user['role'] === 'agent'): ?>
        <a href="./agent.php" class="btn" style="padding:10px 20px;background:#2c7be5;color:white;border:none;border-radius:4px;text-decoration:none;cursor:pointer">← Retour au tableau de bord</a>
    <?php elseif ($user['role'] === 'admin'): ?>
        <a href="./admin.php" class="btn" style="padding:10px 20px;background:#2c7be5;color:white;border:none;border-radius:4px;text-decoration:none;cursor:pointer">← Retour à l'administration</a>
    <?php endif; ?>
</div>

<style>
.card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-attente {
    background: #fff3cd;
    color: #856404;
}

.badge-traitement {
    background: #d1ecf1;
    color: #0c5460;
}

.badge-resolu {
    background: #d4edda;
    color: #155724;
}

.badge-annule {
    background: #f8d7da;
    color: #721c24;
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
</style>

<?php require_once 'includes/footer.php'; ?>