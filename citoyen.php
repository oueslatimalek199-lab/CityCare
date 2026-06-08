<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

if ($user['role'] !== 'citoyen') {
    Auth::redirectToDashboard();
}

$pdo = getConnexion();

$successMsg = $_SESSION['success'] ?? '';
$errorMsg   = $_SESSION['error']   ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Récupérer filtres
$statutFiltre = $_GET['statut'] ?? '';
$keyword      = trim($_GET['q'] ?? '');

// Construire la requête avec filtres
$sql = "
    SELECT r.idRec,
           r.titre,
           r.description,
           r.statut,
           r.adresse,
           r.dateCreation,
           r.commentaireAgent,
           GROUP_CONCAT(c.label SEPARATOR ', ') AS categories
    FROM reclamation r
    LEFT JOIN reclamation_categorie rc ON rc.idRec = r.idRec
    LEFT JOIN categorie c ON c.idCateg = rc.idCateg
    WHERE r.idUtilisateur = :idUtilisateur
";

$params = ['idUtilisateur' => $user['idUtilisateur']];

if ($statutFiltre !== '') {
    $sql .= " AND r.statut = :statut";
    $params['statut'] = $statutFiltre;
}

if ($keyword !== '') {
    $sql .= " AND (r.titre LIKE :kw OR r.description LIKE :kw)";
    $params['kw'] = "%$keyword%";
}

$sql .= " GROUP BY r.idRec ORDER BY r.dateCreation DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$myReclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$statsStmt = $pdo->prepare("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'en attente'    THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'en traitement' THEN 1 ELSE 0 END) as en_traitement,
        SUM(CASE WHEN statut = 'résolu'        THEN 1 ELSE 0 END) as resolu,
        SUM(CASE WHEN statut = 'annulé'        THEN 1 ELSE 0 END) as annule
    FROM reclamation
    WHERE idUtilisateur = ?
");
$statsStmt->execute([$user['idUtilisateur']]);
$stat = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Custom header config
$headerConfig = [
    'title'       => 'Bonjour, ' . htmlspecialchars($user['prenom'] . ' ' . $user['nom']) . ' 👋',
    'subtitle'    => 'Gérez vos réclamations facilement',
    'profileLink' => './profil.php',
    'bgGradient'  => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<?php if ($successMsg): ?>
    <div class="alert-success">✅ <?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert-error">❌ <?= htmlspecialchars($errorMsg) ?></div>
<?php endif; ?>

<!-- ===== STATS CARDS ===== -->
<div class="stats-grid">
    <div class="stat-card" style="border-left:4px solid #4facfe">
        <div class="stat-number" style="color:#4facfe"><?= $stat['total'] ?></div>
        <div class="stat-label">Total soumises</div>
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
        <div class="stat-number" style="color:#e74a3b"><?= $stat['annule'] ?></div>
        <div class="stat-label">Annulées</div>
    </div>
</div>

<!-- ===== QUICK ACTIONS ===== -->
<div class="quick-actions">
    <a href="./soumettre.php" class="action-card">
        <div class="action-icon" style="background:linear-gradient(135deg,#4facfe,#00f2fe)">➕</div>
        <div class="action-label">Nouvelle réclamation</div>
    </a>
    <a href="?statut=en attente" class="action-card">
        <div class="action-icon" style="background:linear-gradient(135deg,#f6c23e,#f4a700)">⏳</div>
        <div class="action-label">En attente</div>
    </a>
    <a href="?statut=résolu" class="action-card">
        <div class="action-icon" style="background:linear-gradient(135deg,#1cc88a,#17a673)">✅</div>
        <div class="action-label">Résolues</div>
    </a>
    
    <!--  NOUVEAU: LIEN VERS RÉCLAMATIONS PUBLIQUES -->
    <a href="./reclamations_publiques.php" class="action-card" style="border:2px solid #667eea">
        <div class="action-icon" style="background:linear-gradient(135deg,#667eea,#764ba2)">🌍</div>
        <div class="action-label">Réclamations Publiques</div>
    </a>
<!--  LIEN VERS Services PUBLIQUES  -->
    <a href="./services_publiques.php" class="action-card" style="border:2px solid #667eea">
        <div class="action-icon" style="background:linear-gradient(135deg,#667eea,#764ba2)">🌍</div>
        <div class="action-label">Services Publiques</div>
    </a>
</div>
</div>
<!-- ===== FILTER & SEARCH BAR ===== -->
<div class="card" style="margin-bottom:20px">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>"
               placeholder="🔍 Rechercher une réclamation..."
               style="flex:1;min-width:200px;padding:9px 12px;border:1px solid #ddd;
                      border-radius:6px;font-size:13px;outline:none">

        <select name="statut"
                style="padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:13px">
            <option value="">Tous les statuts</option>
            <option value="en attente"    <?= $statutFiltre === 'en attente'    ? 'selected' : '' ?>>En attente</option>
            <option value="en traitement" <?= $statutFiltre === 'en traitement' ? 'selected' : '' ?>>En traitement</option>
            <option value="résolu"        <?= $statutFiltre === 'résolu'        ? 'selected' : '' ?>>Résolu</option>
            <option value="annulé"        <?= $statutFiltre === 'annulé'        ? 'selected' : '' ?>>Annulé</option>
        </select>

        <button type="submit"
                style="padding:9px 18px;background:#4facfe;color:white;border:none;
                       border-radius:6px;font-size:13px;font-weight:600;cursor:pointer">
            Filtrer
        </button>

        <?php if ($statutFiltre || $keyword): ?>
            <a href="./citoyen.php"
               style="padding:9px 14px;background:#f0f2f5;color:#555;border-radius:6px;
                      font-size:13px;text-decoration:none;font-weight:600">
                ✕ Réinitialiser
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- ===== COMPLAINTS TABLE ===== -->
<div class="card">
    <h2 style="margin-bottom:5px">📋 Mes Réclamations</h2>
    <p style="color:#888;font-size:13px;margin-bottom:20px">
        <?= count($myReclamations) ?> réclamation(s) trouvée(s)
        <?= $statutFiltre ? '— filtrées : <strong>' . htmlspecialchars($statutFiltre) . '</strong>' : '' ?>
        <?= $keyword ? '— recherche : <strong>' . htmlspecialchars($keyword) . '</strong>' : '' ?>
    </p>
    <?php if (empty($myReclamations)): ?>
        <div style="text-align:center;padding:60px 20px;color:#aaa">
            <div style="font-size:48px;margin-bottom:15px">📭</div>
            <h3>Aucune réclamation trouvée</h3>
            <p style="margin-top:10px">
                <?php if ($statutFiltre || $keyword): ?>
                    Aucun résultat pour ce filtre.
                    <a href="./citoyen.php" style="color:#4facfe">Voir toutes mes réclamations</a>
                <?php else: ?>
                    Vous n'avez pas encore soumis de réclamation.
                    <a href="./soumettre.php" style="color:#4facfe">Soumettre maintenant</a>
                <?php endif; ?>
            </p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Titre</th>
                    <th>Catégorie</th>
                    <th>Adresse</th>
                    <th>Date soumission</th>
                    <th>Statut</th>
                    <th>Message agent</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($myReclamations as $rec):
                $badgeColors = [
                    'en attente'    => ['bg' => '#fff3cd', 'color' => '#856404'],
                    'en traitement' => ['bg' => '#d1ecf1', 'color' => '#0c5460'],
                    'résolu'        => ['bg' => '#d4edda', 'color' => '#155724'],
                    'annulé'        => ['bg' => '#f8d7da', 'color' => '#721c24'],
                ];
                $badge = $badgeColors[$rec['statut']] ?? ['bg' => '#eee', 'color' => '#333'];
            ?>
            <tr>
                <td><strong>#<?= $rec['idRec'] ?></strong></td>

                <td>
                    <span title="<?= htmlspecialchars($rec['description']) ?>"
                          style="cursor:help;font-weight:600">
                        <?= htmlspecialchars(mb_substr($rec['titre'], 0, 28)) ?><?= mb_strlen($rec['titre']) > 28 ? '…' : '' ?>
                    </span>
                </td>

                <td style="font-size:12px;color:#555">
                    <?= htmlspecialchars($rec['categories'] ?? '—') ?>
                </td>

                <td style="font-size:12px;color:#555">
                    <?= htmlspecialchars(mb_substr($rec['adresse'] ?? '—', 0, 28)) ?>
                </td>

                <td style="font-size:12px;color:#777">
                    <?= date('d/m/Y', strtotime($rec['dateCreation'])) ?>
                </td>

                <!-- Status — read only -->
                <td>
                    <span style="background:<?= $badge['bg'] ?>;color:<?= $badge['color'] ?>;
                                 padding:4px 12px;border-radius:20px;font-size:12px;
                                 font-weight:600;white-space:nowrap">
                        <?= htmlspecialchars($rec['statut']) ?>
                    </span>
                </td>

                <!-- Agent message -->
                <td style="font-size:12px;color:#555;max-width:180px">
                    <?php if (!empty($rec['commentaireAgent'])): ?>
                        <span title="<?= htmlspecialchars($rec['commentaireAgent']) ?>"
                              style="cursor:help;font-style:italic">
                            💬 <?= htmlspecialchars(mb_substr($rec['commentaireAgent'], 0, 40)) ?><?= mb_strlen($rec['commentaireAgent']) > 40 ? '…' : '' ?>
                        </span>
                    <?php else: ?>
                        <span style="color:#ccc">—</span>
                    <?php endif; ?>
                </td>

                <!-- View only — no edit -->
                <td>
                    <a href="./reclamation.php?id=<?= $rec['idRec'] ?>"
                       style="background:#4facfe;color:white;padding:5px 12px;border-radius:5px;
                              font-size:12px;text-decoration:none;font-weight:600;white-space:nowrap">
                        👁️ Voir
                    </a>
                </td>
            </tr>

            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</div>

<style>
.welcome-banner {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    padding: 24px 28px;
    border-radius: 10px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
}
.welcome-banner h2 { margin: 0; font-size: 20px; }
.welcome-banner p  { margin: 5px 0 0; opacity: 0.9; font-size: 14px; }
.btn-submit {
    background: white;
    color: #4facfe;
    padding: 10px 22px;
    border-radius: 6px;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
    white-space: nowrap;
}
.btn-submit:hover { background: #f0f8ff; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 15px;
    margin-bottom: 24px;
}
.stat-card {
    background: white;
    padding: 18px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    text-align: center;
}
.stat-number { font-size: 30px; font-weight: 700; margin-bottom: 5px; }
.stat-label  { font-size: 12px; color: #888; }

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
    gap: 15px;
    margin-bottom: 24px;
}
.action-card {
    background: white;
    border-radius: 10px;
    padding: 20px 15px;
    text-align: center;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: transform 0.2s, box-shadow 0.2s;
}
.action-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.12);
}
.action-icon {
    width: 48px; height: 48px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px;
    margin: 0 auto 10px;
}
.action-label { font-size: 13px; font-weight: 600; color: #444; }

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
table thead { background: #f5f5f5; }
table th, table td {
    padding: 11px 12px;
    text-align: left;
    border-bottom: 1px solid #eee;
    font-size: 13px;
}
table th { font-weight: 600; color: #444; white-space: nowrap; }
table tbody tr:hover td { background: #f0faff; }

.alert-success {
    background: #d4edda; color: #155724;
    padding: 14px; border-radius: 6px;
    margin-bottom: 20px; border-left: 4px solid #28a745;
    font-weight: 600;
}
.alert-error {
    background: #fdecea; color: #b00020;
    padding: 14px; border-radius: 6px;
    margin-bottom: 20px; border-left: 4px solid #b00020;
    font-weight: 600;
}
</style>

<?php require_once 'includes/footer.php'; ?>