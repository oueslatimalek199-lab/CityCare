<?php
$pageTitle = 'Détail réclamation (Admin)';
require_once 'includes/header.php';
require_once 'config/database.php';
require_once 'auth/Auth.php';

Auth::exigerConnexion();
$user = Auth::getUtilisateur();

// Vérification du rôle
if ($user['role'] !== 'administrateur') {
    die("Accès refusé : réservé à l'administrateur.");
}

$pdo   = getConnexion();
$idRec = $_GET['id'] ?? null;

if (!$idRec) {
    die("Réclamation introuvable (ID manquant).");
}

// L’admin peut voir toutes les réclamations
$stmt = $pdo->prepare("
    SELECT r.*, 
           u.nom AS citoyen_nom, u.prenom AS citoyen_prenom, u.email AS citoyen_email,
           a.nom AS agent_nom, a.prenom AS agent_prenom, a.email AS agent_email
    FROM reclamation r
    LEFT JOIN utilisateur u ON r.idUtilisateur = u.idUtilisateur
    LEFT JOIN utilisateur a ON r.idUtilisateurAssigne = a.idUtilisateur
    WHERE r.idRec = :idRec
");
$stmt->execute(['idRec' => $idRec]);
$rec = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$rec) {
    echo '<div class="card"><p>Réclamation introuvable.</p><a href="admin.php">← Retour</a></div>';
    require_once 'includes/footer.php';
    exit;
}

// Catégories
$stmtCat = $pdo->prepare("
    SELECT c.label 
    FROM categorie c
    JOIN reclamation_categorie rc ON rc.idCateg = c.idCateg
    WHERE rc.idRec = ?
");
$stmtCat->execute([$idRec]);
$categs = $stmtCat->fetchAll(PDO::FETCH_COLUMN);

// Photos
$stmtPhotos = $pdo->prepare("SELECT * FROM photos WHERE idRec = ? ORDER BY idPrint");
$stmtPhotos->execute([$idRec]);
$photos = $stmtPhotos->fetchAll();

// Badges statut
$badges = [
    'en_attente'    => ['label' => 'En attente',    'class' => 'badge-attente'],
    'en_traitement' => ['label' => 'En traitement', 'class' => 'badge-traitement'],
    'resolu'        => ['label' => 'Résolu',        'class' => 'badge-resolu'],
    'annule'        => ['label' => 'Annulé',        'class' => 'badge-annule'],
];
$badge = $badges[$rec['statut']] ?? ['label' => $rec['statut'], 'class' => ''];
?>

<a href="admin.php" style="font-size:14px;color:#003366">← Retour</a>

<div class="card" style="margin-top:14px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <h2 style="margin:0 0 8px"><?= htmlspecialchars($rec['titre']) ?></h2>
        <span class="badge <?= $badge['class'] ?>"><?= $badge['label'] ?></span>
    </div>

    <p style="font-size:13px;color:#888;margin:0 0 16px">
        Soumise le <?= date('d/m/Y', strtotime($rec['dateCreation'])) ?>
        <?= $rec['adresse'] ? ' · ' . htmlspecialchars($rec['adresse']) : '' ?>
    </p>

    <p><strong>Citoyen :</strong> <?= htmlspecialchars($rec['citoyen_prenom'].' '.$rec['citoyen_nom']) ?> (<?= htmlspecialchars($rec['citoyen_email']) ?>)</p>
    <p><strong>Agent assigné :</strong> <?= $rec['agent_prenom'] ? htmlspecialchars($rec['agent_prenom'].' '.$rec['agent_nom']).' ('.$rec['agent_email'].')' : 'Aucun' ?></p>

    <?php if (!empty($categs)): ?>
    <p><strong>Catégories :</strong>
        <?php foreach ($categs as $label): ?>
            <span class="badge" style="background:#e0e7ff;color:#3730a3;margin-right:4px"><?= htmlspecialchars($label) ?></span>
        <?php endforeach; ?>
    </p>
    <?php endif; ?>

    <p style="line-height:1.7;white-space:pre-wrap"><?= htmlspecialchars($rec['description']) ?></p>
</div>

<div class="card">
    <h3>Photos (<?= count($photos) ?>)</h3>
    <?php if (!empty($photos)): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
            <?php foreach ($photos as $photo): ?>
                <img src="<?= htmlspecialchars($photo['cheminFichier']) ?>" style="width:100%;height:130px;object-fit:cover;border-radius:6px">
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color:#888">Aucune photo pour cette réclamation.</p>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
