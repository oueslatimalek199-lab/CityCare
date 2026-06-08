<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

if ($user['role'] !== 'admin') {
    Auth::redirectToDashboard();
}
$pdo = getConnexion();

// Récupérer filtres
$statutFiltre = $_GET['statut'] ?? '';
$keyword = trim($_GET['q'] ?? '');

// Construire la requête avec filtres
$sql = "
    SELECT r.idRec, r.titre, r.statut, r.dateCreation,
           u.prenom, u.nom, u.email,
           a.prenom AS agent_prenom, a.nom AS agent_nom,
           a.idUtilisateur AS agent_id,
           GROUP_CONCAT(c.label SEPARATOR ', ') AS categories
    FROM reclamation r
    LEFT JOIN utilisateur u ON r.idUtilisateur = u.idUtilisateur
    LEFT JOIN utilisateur a ON r.idUtilisateurAssigne = a.idUtilisateur
    LEFT JOIN reclamation_categorie rc ON rc.idRec = r.idRec
    LEFT JOIN categorie c ON c.idCateg = rc.idCateg
";

$params = [];

if ($statutFiltre !== '') {
    $sql .= " WHERE r.statut = :statut";
    $params['statut'] = $statutFiltre;
}

if ($keyword !== '') {
    if ($statutFiltre !== '') {
        $sql .= " AND (r.titre LIKE :kw OR r.description LIKE :kw)";
    } else {
        $sql .= " WHERE (r.titre LIKE :kw OR r.description LIKE :kw)";
    }
    $params['kw'] = "%$keyword%";
}

$sql .= " GROUP BY r.idRec ORDER BY r.dateCreation DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$allReclamations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques globales
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN statut = 'en attente' THEN 1 ELSE 0 END) as en_attente,
        SUM(CASE WHEN statut = 'en traitement' THEN 1 ELSE 0 END) as en_traitement,
        SUM(CASE WHEN statut = 'résolu' THEN 1 ELSE 0 END) as resolu,
        SUM(CASE WHEN statut = 'annulé' THEN 1 ELSE 0 END) as annule
    FROM reclamation
")->fetch(PDO::FETCH_ASSOC);

// Custom header config
$headerConfig = [
    'title' => 'Gestion des Réclamations',
    'subtitle' => 'Consultez et gérez l\'ensemble des réclamations',
    'icon' => '📋',
    'role' => 'Administrateur',
    'profileLink' => './profil.php',
    'bgGradient' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)'
];
require_once 'includes/dashboard_header.php';
?>

<!-- STATISTIQUES GLOBALES -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:15px;margin-bottom:30px">
    <div class="card stat-box">
        <h3><?= $stats['total'] ?? 0 ?></h3>
        <p>Total</p>
    </div>
    <div class="card stat-box" style="border-left:4px solid #ffc107">
        <h3><?= $stats['en_attente'] ?? 0 ?></h3>
        <p>En attente</p>
    </div>
    <div class="card stat-box" style="border-left:4px solid #17a2b8">
        <h3><?= $stats['en_traitement'] ?? 0 ?></h3>
        <p>En traitement</p>
    </div>
    <div class="card stat-box" style="border-left:4px solid #28a745">
        <h3><?= $stats['resolu'] ?? 0 ?></h3>
        <p>Résolues</p>
    </div>
    <div class="card stat-box" style="border-left:4px solid #dc3545">
        <h3><?= $stats['annule'] ?? 0 ?></h3>
        <p>Annulées</p>
    </div>
</div>

<!-- SEARCH & FILTER -->
<div class="card" style="margin-bottom:20px">
    <form method="get" style="display:flex;gap:15px;align-items:center;flex-wrap:wrap">
        <input type="text" name="q" placeholder="Rechercher par titre ou description..." 
               value="<?= htmlspecialchars($keyword) ?>" 
               style="flex:1;min-width:200px;padding:8px;border:1px solid #ccc;border-radius:4px">

        <select name="statut" style="padding:8px;border:1px solid #ccc;border-radius:4px">
            <option value="">-- Tous les statuts --</option>
            <option value="en attente" <?= $statutFiltre==='en attente'?'selected':'' ?>>En attente</option>
            <option value="en traitement" <?= $statutFiltre==='en traitement'?'selected':'' ?>>En traitement</option>
            <option value="résolu" <?= $statutFiltre==='résolu'?'selected':'' ?>>Résolu</option>
            <option value="annulé" <?= $statutFiltre==='annulé'?'selected':'' ?>>Annulé</option>
        </select>

        <button type="submit" class="btn" style="padding:8px 20px;background:#f093fb;color:white;border:none;border-radius:4px;cursor:pointer">Filtrer</button>
        <a href="./gestion_reclamations.php" class="btn" style="padding:8px 20px;background:#6c757d;color:white;border:none;border-radius:4px;cursor:pointer;text-decoration:none">Réinitialiser</a>
    </form>
</div>

<!-- TABLEAU DES RÉCLAMATIONS -->
<div class="card">
    <h2>📋 Liste complète des réclamations</h2>
    
    <?php if (empty($allReclamations)): ?>
        <p style="color:#888;text-align:center;padding:40px 0">
            Aucune réclamation trouvée avec ces critères.
        </p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Titre</th>
                    <th>Citoyen</th>
                    <th>Agent</th>
                    <th>Catégories</th>
                    <th>Statut</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($allReclamations as $rec): ?>
            <tr>
                <td><strong>#<?= $rec['idRec'] ?></strong></td>
                <td><?= htmlspecialchars(substr($rec['titre'], 0, 25)) ?></td>
                <td><?= htmlspecialchars(($rec['prenom'] ?? '') . ' ' . ($rec['nom'] ?? '')) ?></td>
                <td><?= htmlspecialchars(($rec['agent_prenom'] ?? 'Non') . ' ' . ($rec['agent_nom'] ?? 'assignée')) ?></td>
                <td><?= htmlspecialchars($rec['categories'] ?? 'N/A') ?></td>
                <td>
                    <span class="badge badge-<?= $rec['statut'] === 'résolu' ? 'resolu' : ($rec['statut'] === 'en traitement' ? 'traitement' : ($rec['statut'] === 'annulé' ? 'annule' : 'attente')) ?>">
                        <?= htmlspecialchars($rec['statut']) ?>
                    </span>
                </td>
                <td><?= date('d/m/Y', strtotime($rec['dateCreation'])) ?></td>
                <td>
                    <a href="./detail_reclamation.php?id=<?= $rec['idRec'] ?>&role=admin" class="btn-sm btn-info"> Voir</a>
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
    font-size: 14px;
}

table th {
    font-weight: 600;
    color: #333;
}

table tbody tr:hover {
    background: #f9f9f9;
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