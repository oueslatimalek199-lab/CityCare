<?php
session_start();
$pageTitle = 'Mes réclamations';
require_once 'includes/header.php';
require_once 'config/database.php';

$pdo = getConnexion();

// Filtre statut
$filtreStatut = $_GET['statut'] ?? '';
$params = [$_SESSION['idUtilisateur']];
$where        = '';
if (in_array($filtreStatut, ['en_attente', 'en_traitement', 'resolu', 'annule'])) {
    $where  = 'AND r.statut = ?';
    $params[] = $filtreStatut;
}

$stmt = $pdo->prepare("
    SELECT r.*,
           GROUP_CONCAT(c.label SEPARATOR ', ') AS categories
    FROM   reclamation r
    LEFT JOIN reclamation_categorie rc ON rc.idRec   = r.idRec
    LEFT JOIN categorie             c  ON c.idCateg  = rc.idCateg
    WHERE  r.idUtilisateur = ? $where
    GROUP  BY r.idRec
    ORDER  BY r.dateCreation DESC
");
$stmt->execute($params);
$reclamations = $stmt->fetchAll();

$badges = [
    'en_attente'    => ['label' => 'En attente',    'class' => 'badge-attente'],
    'en_traitement' => ['label' => 'En traitement', 'class' => 'badge-traitement'],
    'resolu'        => ['label' => 'Résolu',        'class' => 'badge-resolu'],
    'annule'        => ['label' => 'Annulé',        'class' => 'badge-annule'],
];

?>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
        <h2 style="margin:0">Mes réclamations</h2>
        <a href="soumettre.php" class="btn btn-primary">+ Nouvelle</a>
    </div>

    <!-- Filtres -->
    <form method="GET" style="margin-bottom:16px;display:flex;gap:10px;align-items:center">
        <label style="margin:0;font-size:14px">Filtrer :</label>
        <select name="statut" onchange="this.form.submit()" style="padding:6px 10px;border-radius:5px;border:1px solid #ccc;font-size:14px;width:auto;margin:0">
            <option value="">Tous les statuts</option>
            <?php foreach ($badges as $val => $info): ?>
            <option value="<?= $val ?>" <?= $filtreStatut === $val ? 'selected' : '' ?>>
                <?= $info['label'] ?>
            </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (empty($reclamations)): ?>
        <p style="color:#888;text-align:center;padding:30px 0">Aucune réclamation trouvée.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Titre</th>
                <th>Catégorie(s)</th>
                <th>Date</th>
                <th>Statut</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reclamations as $rec): ?>
            <?php $badge = $badges[$rec['statut']] ?? ['label' => $rec['statut'], 'class' => '']; ?>
            <tr>
                <td><?= $rec['idRec'] ?></td>
                <td><?= htmlspecialchars($rec['titre']) ?></td>
                <td style="color:#666;font-size:13px"><?= htmlspecialchars($rec['categories'] ?? '—') ?></td>
                <td><?= $rec['dateCreation'] ?></td>
                <td><span class="badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span></td>
                <td style="white-space:nowrap">
                    <a href="detail_reclamation.php?id=<?= $rec['idRec'] ?>" class="btn btn-primary btn-sm">Voir</a>
                    <?php if ($rec['statut'] === 'en_attente'): ?>
                    <a href="annuler_reclamation.php?id=<?= $rec['idRec'] ?>"
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Annuler cette réclamation ?')">Annuler</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>