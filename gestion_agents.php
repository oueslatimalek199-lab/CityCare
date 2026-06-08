<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

if ($user['role'] !== 'admin') {
    Auth::redirectToDashboard();
}

// FIX: original had an extra require_once 'includes/header.php' BEFORE dashboard_header.php
// which caused double HTML output. Removed here — only dashboard_header.php is used.

$pdo     = getConnexion();
$message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$erreur  = isset($_SESSION['error'])   ? $_SESSION['error']   : '';
unset($_SESSION['success'], $_SESSION['error']);

// Agents et leurs statistiques
$agentsStats = $pdo->query("
    SELECT
        u.idUtilisateur,
        u.nom,
        u.prenom,
        u.email,
        u.statut,
        COUNT(r.idRec)                                                        AS total_assignees,
        SUM(CASE WHEN r.statut = 'résolu' THEN 1 ELSE 0 END)                 AS resolues,
        SUM(CASE WHEN r.statut != 'résolu'
                  AND DATEDIFF(NOW(), r.dateAssignation) > 15 THEN 1 ELSE 0 END) AS en_retard
    FROM utilisateur u
    LEFT JOIN reclamation r ON u.idUtilisateur = r.idUtilisateurAssigne
    WHERE u.role = 'agent'
    GROUP BY u.idUtilisateur
    ORDER BY u.nom, u.prenom
")->fetchAll(PDO::FETCH_ASSOC);

$headerConfig = [
    'title'       => 'Gestion des Agents',
    'subtitle'    => 'Gérez l\'ensemble de vos agents municipaux',
    'icon'        => '👥',
    'role'        => 'Administrateur',
    'profileLink' => './profil.php',
    'bgGradient'  => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
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
        <h3><?= count($agentsStats) ?></h3>
        <p>Total des agents</p>
    </div>
    <div class="card stat-box" style="border-left:4px solid #28a745">
        <h3><?= count(array_filter($agentsStats, fn($a) => $a['statut'] === 'actif')) ?></h3>
        <p>Actifs</p>
    </div>
    <div class="card stat-box" style="border-left:4px solid #dc3545">
        <h3><?= count(array_filter($agentsStats, fn($a) => $a['statut'] !== 'actif')) ?></h3>
        <p>Inactifs</p>
    </div>
</div>

<!-- TABLEAU DES AGENTS -->
<div class="card">
    <h2>👥 Liste de tous les agents</h2>

    <?php if (empty($agentsStats)): ?>
        <p style="color:#888;text-align:center;padding:40px 0">Aucun agent trouvé.</p>
    <?php else: ?>
        <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>Agent</th>
                    <th>Email</th>
                    <th>Statut</th>
                    <th>Assignées</th>
                    <th>Résolues</th>
                    <th>En retard</th>
                    <th>Taux de résolution</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($agentsStats as $agent):
                $taux = $agent['total_assignees'] > 0
                    ? round(($agent['resolues'] / $agent['total_assignees']) * 100, 1)
                    : 0;
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($agent['prenom'] . ' ' . $agent['nom']) ?></strong></td>
                <td style="font-size:13px;color:#555"><?= htmlspecialchars($agent['email']) ?></td>
                <td>
                    <span class="badge <?= $agent['statut'] === 'actif' ? 'badge-resolu' : 'badge-annule' ?>">
                        <?= htmlspecialchars($agent['statut']) ?>
                    </span>
                </td>
                <td><?= $agent['total_assignees'] ?></td>
                <td><?= $agent['resolues'] ?></td>
                <td <?= $agent['en_retard'] > 0 ? 'style="color:#dc3545;font-weight:bold"' : '' ?>>
                    <?= $agent['en_retard'] ?>
                </td>
                <td>
                    <strong style="color:<?= $taux >= 80 ? '#28a745' : ($taux >= 50 ? '#ffc107' : '#dc3545') ?>">
                        <?= $taux ?>%
                    </strong>
                </td>
                <td>
                    <form method="POST" action="./toggle_agent_status.php" style="display:inline">
                        <input type="hidden" name="idAgent"    value="<?= $agent['idUtilisateur'] ?>">
                        <input type="hidden" name="newStatus"  value="<?= $agent['statut'] === 'actif' ? 'inactif' : 'actif' ?>">
                        <button type="submit"
                                class="btn-sm btn-<?= $agent['statut'] === 'actif' ? 'danger' : 'success' ?>">
                            <?= $agent['statut'] === 'actif' ? '❌ Désactiver' : '✓ Activer' ?>
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

<div style="margin-top:25px">
    <a href="./admin.php" class="btn-back">← Retour au tableau de bord</a>
</div>

<style>
.card{background:white;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.1);margin-bottom:20px}
.stat-box{text-align:center;padding:20px !important;border-left:4px solid #2c7be5}
.stat-box h3{font-size:32px;margin:0 0 10px 0;color:#2c7be5}
.stat-box p{margin:0;color:#666;font-size:14px}
.badge{display:inline-block;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600}
.badge-resolu{background:#d4edda;color:#155724}
.badge-annule{background:#f8d7da;color:#721c24}
.btn-sm{padding:5px 10px;font-size:12px;display:inline-block;border:none;border-radius:4px;cursor:pointer;text-decoration:none}
.btn-success{background:#28a745;color:white}
.btn-danger{background:#dc3545;color:white}
.btn-success:hover,.btn-danger:hover{opacity:.9}
table{width:100%;border-collapse:collapse;margin-top:15px}
table thead{background:#f5f5f5}
table th,table td{padding:12px;text-align:left;border-bottom:1px solid #ddd;font-size:14px}
table th{font-weight:600;color:#333}
table tbody tr:hover{background:#f9f9f9}
.btn-back{display:inline-block;padding:10px 20px;background:#6c757d;color:white;text-decoration:none;border-radius:4px;cursor:pointer;transition:background .3s}
.btn-back:hover{background:#5a6268}
</style>

<?php require_once 'includes/footer.php'; ?>