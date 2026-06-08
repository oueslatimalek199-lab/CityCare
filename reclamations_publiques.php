<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';

$pdo = getConnexion();

$isLoggedIn = isset($_SESSION['idUtilisateur']);
$user = $isLoggedIn ? Auth::getUtilisateur() : null;

if ($isLoggedIn && $user && $user['role'] !== 'citoyen' && $user['role'] !== 'admin') {
    Auth::redirectToDashboard();
}

// Pagination
$page    = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset  = ($page - 1) * $perPage;

// Filtres
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statut = isset($_GET['statut']) ? $_GET['statut'] : 'tous';
$tri    = isset($_GET['tri'])    ? $_GET['tri']    : 'recents';

// Conditions WHERE
$whereConditions = ["r.statut != 'annulé'"];
$params = [];

if (!empty($search)) {
    $whereConditions[] = "(r.titre LIKE ? OR r.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($statut !== 'tous') {
    $whereConditions[] = "r.statut = ?";
    $params[] = $statut;
}

$where = implode(' AND ', $whereConditions);

$orderBy = 'r.dateCreation DESC';
switch ($tri) {
    case 'ancien':      $orderBy = 'r.dateCreation ASC';          break;
    case 'commentaires': $orderBy = 'nb_commentaires DESC';        break;
    case 'populaire':   $orderBy = 'r.idRec DESC';                 break;
}

// Compter le total
$countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM reclamation r WHERE $where");
$countStmt->execute($params);
$total      = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = max(1, (int)ceil($total / $perPage));

// Requête principale — LIMIT/OFFSET directement (pas de paramètres nommés pour ces valeurs int)
$query = "
    SELECT r.*,
           u.prenom, u.nom, u.photo,
           COUNT(c.idCommentaire) as nb_commentaires
    FROM reclamation r
    LEFT JOIN utilisateur u ON r.idUtilisateur = u.idUtilisateur
    LEFT JOIN commentaire c ON r.idRec = c.idRec AND c.statut = 'publié'
    WHERE $where
    GROUP BY r.idRec
    ORDER BY $orderBy
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Header
$headerConfig = [
    'title'    => 'Réclamations Publiques',
    'subtitle' => 'Consultez toutes les réclamations publiées et partagez vos commentaires',
    'icon'     => '🌍',
    'bgGradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($_SESSION['success']) ?></div>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['warning'])): ?>
    <div class="alert alert-warning">⚠️ <?= htmlspecialchars($_SESSION['warning']) ?></div>
    <?php unset($_SESSION['warning']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error">❌ <?= htmlspecialchars($_SESSION['error']) ?></div>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<!-- Bouton retour -->
<div style="max-width:1200px;margin:0 auto;padding:20px 20px 0;">
    <?php if ($isLoggedIn && $user): ?>
        <?php if ($user['role'] === 'citoyen'): ?>
            <a href="./citoyen.php" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:linear-gradient(135deg,#4facfe,#00f2fe);color:white;text-decoration:none;border-radius:6px;font-weight:600;margin-bottom:20px;">
                ← Retour à mon espace
            </a>
        <?php elseif ($user['role'] === 'admin'): ?>
            <a href="./admin.php" style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:linear-gradient(135deg,#f093fb,#f5576c);color:white;text-decoration:none;border-radius:6px;font-weight:600;margin-bottom:20px;">
                ← Retour au tableau de bord
            </a>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="container" style="max-width:1200px;margin:0 auto;padding:20px;">

    <!-- FILTRES -->
    <div class="card" style="margin-bottom:30px;">
        <h2>🔍 Rechercher et Filtrer</h2>
        <form method="GET" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-top:15px;">
            <div>
                <label style="display:block;margin-bottom:5px;font-weight:600;font-size:13px;">Recherche</label>
                <input type="text" name="search" placeholder="Titre ou description..."
                       value="<?= htmlspecialchars($search) ?>"
                       style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;box-sizing:border-box;">
            </div>
            <div>
                <label style="display:block;margin-bottom:5px;font-weight:600;font-size:13px;">Statut</label>
                <select name="statut" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
                    <option value="tous"          <?= $statut === 'tous'          ? 'selected' : '' ?>>Tous</option>
                    <option value="en attente"    <?= $statut === 'en attente'    ? 'selected' : '' ?>>En attente</option>
                    <option value="en traitement" <?= $statut === 'en traitement' ? 'selected' : '' ?>>En traitement</option>
                    <option value="résolu"        <?= $statut === 'résolu'        ? 'selected' : '' ?>>Résolu</option>
                </select>
            </div>
            <div>
                <label style="display:block;margin-bottom:5px;font-weight:600;font-size:13px;">Tri</label>
                <select name="tri" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;">
                    <option value="recents"      <?= $tri === 'recents'      ? 'selected' : '' ?>>Plus récents</option>
                    <option value="ancien"       <?= $tri === 'ancien'       ? 'selected' : '' ?>>Plus anciens</option>
                    <option value="commentaires" <?= $tri === 'commentaires' ? 'selected' : '' ?>>Plus commentés</option>
                </select>
            </div>
            <div style="display:flex;align-items:flex-end;gap:10px;">
                <button type="submit"
                        style="flex:1;padding:10px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">
                    🔍 Rechercher
                </button>
                <a href="reclamations_publiques.php"
                   style="padding:10px 15px;background:#f5f5f5;border-radius:6px;text-decoration:none;color:#333;font-weight:600;">
                    ✕
                </a>
            </div>
        </form>
    </div>

    <div style="margin-bottom:20px;color:#666;font-weight:600;">
        📊 <?= $total ?> réclamation(s) — Page <?= $page ?>/<?= $totalPages ?>
    </div>

    <?php if (empty($reclamations)): ?>
        <div class="card" style="text-align:center;padding:40px;">
            <h3 style="color:#999;">Aucune réclamation trouvée</h3>
            <p style="color:#bbb;">Essayez de modifier vos critères de recherche</p>
        </div>
    <?php else: ?>
        <?php foreach ($reclamations as $rec): ?>
            <div class="card" id="rec_<?= $rec['idRec'] ?>" style="margin-bottom:20px;border-left:5px solid #667eea;">
                <!-- Header réclamation -->
                <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:15px;flex-wrap:wrap;gap:10px;">
                    <div style="flex:1;min-width:200px;">
                        <h2 style="margin:0 0 10px 0;color:#333;"><?= htmlspecialchars($rec['titre']) ?></h2>
                        <div style="display:flex;gap:15px;flex-wrap:wrap;font-size:14px;color:#666;">
                            <span>👤 <?= htmlspecialchars(($rec['prenom'] ?? 'Anonyme') . ' ' . ($rec['nom'] ?? '')) ?></span>
                            <span>📅 <?= date('d/m/Y à H:i', strtotime($rec['dateCreation'])) ?></span>
                            <span>💬 <?= $rec['nb_commentaires'] ?> commentaire(s)</span>
                        </div>
                    </div>
                    <div>
                        <?php
                        $statutColors = [
                            'en attente'    => '#ffc107',
                            'en traitement' => '#17a2b8',
                            'résolu'        => '#28a745',
                        ];
                        $color = $statutColors[$rec['statut']] ?? '#6c757d';
                        ?>
                        <span style="background:<?= $color ?>;color:white;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;white-space:nowrap;">
                            <?= ucfirst($rec['statut']) ?>
                        </span>
                    </div>
                </div>

                <!-- Description -->
                <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-bottom:15px;line-height:1.6;color:#555;">
                    <?= nl2br(htmlspecialchars(substr($rec['description'], 0, 300))) ?>
                    <?php if (strlen($rec['description']) > 300): ?>
                        <span style="color:#667eea;font-weight:600;">...</span>
                    <?php endif; ?>
                </div>

                <!-- Adresse -->
                <?php if (!empty($rec['adresse'])): ?>
                <div style="display:flex;gap:15px;margin-bottom:15px;padding:10px;background:#f0f0f0;border-radius:6px;font-size:13px;">
                    <span style="color:#666;"><strong>📍 Adresse :</strong> <?= htmlspecialchars($rec['adresse']) ?></span>
                </div>
                <?php endif; ?>

                <!-- Commentaires -->
                <div style="border-top:1px solid #ddd;padding-top:15px;margin-top:15px;">
                    <h3 style="margin:0 0 15px 0;">💬 Commentaires (<?= $rec['nb_commentaires'] ?>)</h3>

                    <?php
                    $commentsStmt = $pdo->prepare("
                        SELECT c.*, u.prenom, u.nom, u.photo
                        FROM commentaire c
                        LEFT JOIN utilisateur u ON c.idUtilisateur = u.idUtilisateur
                        WHERE c.idRec = ? AND c.statut = 'publié'
                        ORDER BY c.dateCreation DESC
                        LIMIT 5
                    ");
                    $commentsStmt->execute([$rec['idRec']]);
                    $comments = $commentsStmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>

                    <?php if (!empty($comments)): ?>
                        <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-bottom:15px;max-height:300px;overflow-y:auto;">
                            <?php foreach ($comments as $comment): ?>
                                <div style="padding:10px;border-bottom:1px solid #eee;display:flex;gap:10px;">
                                    <div style="width:36px;height:36px;border-radius:50%;background:#ddd;display:flex;align-items:center;justify-content:center;color:#666;flex-shrink:0;font-size:14px;">
                                        <?php if (!empty($comment['photo'])): ?>
                                            <img src="<?= htmlspecialchars($comment['photo']) ?>"
                                                 style="width:36px;height:36px;border-radius:50%;object-fit:cover;">
                                        <?php else: ?>
                                            👤
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex:1;min-width:0;">
                                        <div style="font-weight:600;color:#333;font-size:13px;">
                                            <?= htmlspecialchars(($comment['prenom'] ?? 'Anonyme') . ' ' . ($comment['nom'] ?? '')) ?>
                                        </div>
                                        <div style="font-size:11px;color:#999;margin-bottom:4px;">
                                            <?= date('d/m/Y à H:i', strtotime($comment['dateCreation'])) ?>
                                        </div>
                                        <div style="color:#555;word-break:break-word;font-size:13px;">
                                            <?= nl2br(htmlspecialchars($comment['contenu'])) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin-bottom:15px;color:#999;text-align:center;font-size:13px;">
                            Aucun commentaire. Soyez le premier à commenter !
                        </div>
                    <?php endif; ?>

                    <!-- Formulaire commentaire — FIXED: action now points to root-level file -->
                    <?php if ($isLoggedIn): ?>
                        <form method="POST" action="./ajouter_commentaire.php" style="margin-top:15px;">
                            <input type="hidden" name="idRec" value="<?= $rec['idRec'] ?>">
                            <textarea name="contenu" placeholder="Partager votre commentaire..." required
                                      style="width:100%;padding:10px;border:1px solid #ddd;border-radius:6px;font-family:inherit;min-height:80px;resize:vertical;box-sizing:border-box;font-size:14px;"></textarea>
                            <button type="submit"
                                    style="margin-top:10px;padding:10px 20px;background:linear-gradient(135deg,#667eea,#764ba2);color:white;border:none;border-radius:6px;cursor:pointer;font-weight:600;">
                                💬 Envoyer le commentaire
                            </button>
                        </form>
                    <?php else: ?>
                        <div style="background:#f0f7ff;padding:15px;border-radius:6px;text-align:center;color:#0066cc;border:1px solid #b3d9ff;">
                            <a href="./login.php?redirect=reclamations_publiques.php"
                               style="color:#0066cc;text-decoration:none;font-weight:600;">
                                🔐 Connectez-vous pour commenter →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
            <?php
            $qParams = '';
            if (!empty($search))       $qParams .= '&search=' . urlencode($search);
            if ($statut !== 'tous')    $qParams .= '&statut=' . urlencode($statut);
            if ($tri    !== 'recents') $qParams .= '&tri='    . urlencode($tri);
            ?>
            <div style="display:flex;gap:10px;justify-content:center;margin:30px 0;flex-wrap:wrap;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $qParams ?>"
                       style="padding:10px 15px;background:#f5f5f5;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;font-weight:600;">
                        ← Précédent
                    </a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?><?= $qParams ?>"
                       style="padding:8px 12px;border:1px solid #ddd;border-radius:4px;text-decoration:none;<?= $i === $page ? 'background:#667eea;color:white;border-color:#667eea;' : 'background:#fff;color:#333;' ?>font-weight:600;">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $qParams ?>"
                       style="padding:10px 15px;background:#f5f5f5;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#333;font-weight:600;">
                        Suivant →
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.card { background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1); }
.alert { padding:15px;margin:10px auto;max-width:1200px;border-radius:8px;font-weight:600; }
.alert-success { background:#d4edda;color:#155724;border-left:4px solid #28a745; }
.alert-warning { background:#fff3cd;color:#856404;border-left:4px solid #ffc107; }
.alert-error   { background:#fdecea;color:#b00020;border-left:4px solid #dc3545; }
</style>

<?php require_once 'includes/footer.php'; ?>