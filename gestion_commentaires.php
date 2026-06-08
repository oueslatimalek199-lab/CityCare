<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';
require_once 'CommentaireManager.php'; // fixed path

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

if ($user['role'] !== 'admin') {
    Auth::redirectToDashboard();
}

$pdo = getConnexion();
$commentManager = new CommentaireManager($pdo);

// Traiter les actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $idCommentaire = (int)$_POST['idCommentaire'];

    if ($_POST['action'] === 'approuver') {
        $commentManager->approuver($idCommentaire);
        $_SESSION['success'] = 'Commentaire approuvé avec succès.';
    } elseif ($_POST['action'] === 'supprimer') {
        $commentManager->supprimer($idCommentaire);
        $_SESSION['success'] = 'Commentaire supprimé avec succès.';
    } elseif ($_POST['action'] === 'nettoyer') {
        $deleted = $commentManager->supprimerInappropries();
        $_SESSION['success'] = "$deleted commentaire(s) inapproprié(s) supprimé(s).";
    }

    header('Location: gestion_commentaires.php');
    exit;
}

// Récupérer les commentaires modérés
$stmt = $pdo->query("
    SELECT c.*, r.titre, u.prenom, u.nom
    FROM commentaire c
    LEFT JOIN reclamation r ON c.idRec = r.idRec
    LEFT JOIN utilisateur u ON c.idUtilisateur = u.idUtilisateur
    WHERE c.statut = 'modéré'
    ORDER BY c.dateCreation DESC
");
$moderatedComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer aussi les commentaires publiés récents
$stmt2 = $pdo->query("
    SELECT c.*, r.titre, u.prenom, u.nom
    FROM commentaire c
    LEFT JOIN reclamation r ON c.idRec = r.idRec
    LEFT JOIN utilisateur u ON c.idUtilisateur = u.idUtilisateur
    WHERE c.statut = 'publié'
    ORDER BY c.dateCreation DESC
    LIMIT 20
");
$publishedComments = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$successMsg = $_SESSION['success'] ?? '';
unset($_SESSION['success']);

$headerConfig = [
    'title'       => 'Gestion des Commentaires',
    'subtitle'    => 'Modérez les commentaires inappropriés',
    'icon'        => '💬',
    'role'        => 'Administrateur',
    'profileLink' => './profil.php',
    'bgGradient'  => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<?php if (!empty($successMsg)): ?>
    <div class="alert-success"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

<div style="max-width:1200px;margin:0 auto;padding:20px">

    <!-- BOUTON NETTOYAGE AUTOMATIQUE -->
    <div class="card" style="margin-bottom:20px">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div>
                <h2 style="margin:0">🛠️ Actions de Modération</h2>
                <p style="margin:5px 0 0;color:#666;font-size:14px;">Gérez automatiquement ou manuellement les commentaires</p>
            </div>
            <form method="POST" style="display:inline">
                <input type="hidden" name="action" value="nettoyer">
                <button type="submit"
                        onclick="return confirm('Supprimer tous les commentaires inappropriés modérés ?')"
                        style="padding:10px 20px;background:#dc3545;color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">
                    🗑️ Nettoyer automatiquement les commentaires inappropriés
                </button>
            </form>
        </div>
    </div>

    <!-- COMMENTAIRES EN ATTENTE DE MODÉRATION -->
    <div class="card" style="margin-bottom:20px">
        <h2>⏳ Commentaires en Attente de Modération (<?= count($moderatedComments) ?>)</h2>

        <?php if (empty($moderatedComments)): ?>
            <p style="color:#999;text-align:center;padding:30px;">✅ Aucun commentaire à modérer</p>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;">
                    <thead style="background:#f5f5f5;">
                        <tr>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">#</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Auteur</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Réclamation</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Contenu</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Date</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($moderatedComments as $comment): ?>
                            <tr style="border-bottom:1px solid #eee;background:#fff5f5;">
                                <td style="padding:12px;font-weight:bold;">#<?= $comment['idCommentaire'] ?></td>
                                <td style="padding:12px;">
                                    <?= htmlspecialchars(($comment['prenom'] ?? 'Anonyme') . ' ' . ($comment['nom'] ?? '')) ?>
                                </td>
                                <td style="padding:12px;font-size:13px;color:#555;">
                                    <?= htmlspecialchars(substr($comment['titre'] ?? '—', 0, 30)) ?>
                                </td>
                                <td style="padding:12px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                    title="<?= htmlspecialchars($comment['contenu']) ?>">
                                    <?= htmlspecialchars(substr($comment['contenu'], 0, 60)) ?>...
                                </td>
                                <td style="padding:12px;font-size:12px;color:#999;">
                                    <?= date('d/m/Y H:i', strtotime($comment['dateCreation'])) ?>
                                </td>
                                <td style="padding:12px;">
                                    <form method="POST" style="display:flex;gap:5px;">
                                        <input type="hidden" name="idCommentaire" value="<?= $comment['idCommentaire'] ?>">
                                        <button type="submit" name="action" value="approuver"
                                                style="padding:5px 10px;background:#28a745;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;">
                                            ✅ Approuver
                                        </button>
                                        <button type="submit" name="action" value="supprimer"
                                                onclick="return confirm('Supprimer ce commentaire ?')"
                                                style="padding:5px 10px;background:#dc3545;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;">
                                            ❌ Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- COMMENTAIRES RÉCENTS PUBLIÉS -->
    <div class="card">
        <h2>✅ Commentaires Publiés Récents (<?= count($publishedComments) ?>)</h2>

        <?php if (empty($publishedComments)): ?>
            <p style="color:#999;text-align:center;padding:30px;">Aucun commentaire publié pour le moment</p>
        <?php else: ?>
            <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;">
                    <thead style="background:#f5f5f5;">
                        <tr>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">#</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Auteur</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Réclamation</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Contenu</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Date</th>
                            <th style="padding:12px;text-align:left;border-bottom:1px solid #ddd;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($publishedComments as $comment): ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td style="padding:12px;font-weight:bold;">#<?= $comment['idCommentaire'] ?></td>
                                <td style="padding:12px;">
                                    <?= htmlspecialchars(($comment['prenom'] ?? 'Anonyme') . ' ' . ($comment['nom'] ?? '')) ?>
                                </td>
                                <td style="padding:12px;font-size:13px;color:#555;">
                                    <?= htmlspecialchars(substr($comment['titre'] ?? '—', 0, 30)) ?>
                                </td>
                                <td style="padding:12px;max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
                                    title="<?= htmlspecialchars($comment['contenu']) ?>">
                                    <?= htmlspecialchars(substr($comment['contenu'], 0, 60)) ?>
                                </td>
                                <td style="padding:12px;font-size:12px;color:#999;">
                                    <?= date('d/m/Y H:i', strtotime($comment['dateCreation'])) ?>
                                </td>
                                <td style="padding:12px;">
                                    <form method="POST" style="display:inline">
                                        <input type="hidden" name="idCommentaire" value="<?= $comment['idCommentaire'] ?>">
                                        <button type="submit" name="action" value="supprimer"
                                                onclick="return confirm('Supprimer ce commentaire ?')"
                                                style="padding:5px 10px;background:#dc3545;color:white;border:none;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;">
                                            🗑️ Supprimer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div style="margin-top:20px;">
        <a href="./admin.php"
           style="display:inline-block;padding:10px 20px;background:#6c757d;color:white;text-decoration:none;border-radius:4px;font-weight:600;">
            ← Retour au tableau de bord
        </a>
    </div>
</div>

<style>
.card { background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:20px; }
.alert-success { background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin-bottom:20px;border-left:4px solid #28a745;font-weight:600; }
</style>

<?php require_once 'includes/footer.php'; ?>