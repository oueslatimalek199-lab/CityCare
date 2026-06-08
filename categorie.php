<?php
$pageTitle = 'Catégories';
require_once 'includes/header.php';
require_once 'config/database.php';

$pdo    = getConnexion();
$erreur = '';
$succes = '';

// --- AJOUT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'ajouter') {
        $label = trim($_POST['label'] ?? '');
        if ($label === '') {
            $erreur = 'Le nom de la catégorie est obligatoire.';
        } else {
            $pdo->prepare("INSERT INTO categorie (label) VALUES (?)")->execute([$label]);
            $succes = 'Catégorie ajoutée.';
        }
    }

    if ($_POST['action'] === 'modifier') {
        $idCateg = (int)$_POST['idCateg'];
        $label   = trim($_POST['label'] ?? '');
        if ($label === '') {
            $erreur = 'Le nom ne peut pas être vide.';
        } else {
            $pdo->prepare("UPDATE categorie SET label = ? WHERE idCateg = ?")->execute([$label, $idCateg]);
            $succes = 'Catégorie modifiée.';
        }
    }

    if ($_POST['action'] === 'supprimer') {
        $idCateg = (int)$_POST['idCateg'];
        $nb = $pdo->prepare("SELECT COUNT(*) FROM reclamation_categorie WHERE idCateg = ?");
        $nb->execute([$idCateg]);
        if ($nb->fetchColumn() > 0) {
            $erreur = 'Impossible de supprimer : cette catégorie est utilisée par des réclamations.';
        } else {
            $pdo->prepare("DELETE FROM categorie WHERE idCateg = ?")->execute([$idCateg]);
            $succes = 'Catégorie supprimée.';
        }
    }
}

$categories = $pdo->query("
    SELECT c.idCateg, c.label,
           COUNT(rc.idRec) AS nbRec
    FROM   categorie c
    LEFT JOIN reclamation_categorie rc ON rc.idCateg = c.idCateg
    GROUP  BY c.idCateg
    ORDER  BY c.label
")->fetchAll();
?>

<div class="card">
    <h2 style="margin-top:0">Gestion des catégories</h2>

    <?php if ($erreur): ?><div class="alert-error"><?= htmlspecialchars($erreur) ?></div><?php endif; ?>
    <?php if ($succes): ?><div class="alert-success"><?= htmlspecialchars($succes) ?></div><?php endif; ?>

    <!-- Formulaire ajout -->
    <form method="POST" style="display:flex;gap:10px;align-items:flex-end;margin-bottom:24px">
        <input type="hidden" name="action" value="ajouter">
        <div style="flex:1">
            <label>Nouvelle catégorie</label>
            <input type="text" name="label" placeholder="Ex : Voirie, Éclairage…" style="margin:0">
        </div>
        <button type="submit" class="btn btn-primary" style="margin-bottom:1px">Ajouter</button>
    </form>

    <!-- Liste -->
    <?php if (empty($categories)): ?>
        <p style="color:#888;text-align:center;padding:20px 0">Aucune catégorie pour l'instant.</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Nom</th>
                <th>Réclamations liées</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $cat): ?>
        <tr id="row-<?= $cat['idCateg'] ?>">
            <td><?= $cat['idCateg'] ?></td>
            <td>
                <span id="label-<?= $cat['idCateg'] ?>"><?= htmlspecialchars($cat['label']) ?></span>
                <form method="POST" id="form-<?= $cat['idCateg'] ?>" style="display:none">
                    <input type="hidden" name="action" value="modifier">
                    <input type="hidden" name="idCateg" value="<?= $cat['idCateg'] ?>">
                    <input type="text" name="label" value="<?= htmlspecialchars($cat['label']) ?>"
                           style="width:auto;display:inline;margin:0;padding:4px 8px">
                    <button type="submit" class="btn btn-primary btn-sm">OK</button>
                    <button type="button" class="btn btn-sm" style="background:#aaa;color:white"
                        onclick="toggleEdit(<?= $cat['idCateg'] ?>, false)">✕</button>
                </form>
            </td>
            <td><?= $cat['nbRec'] ?></td>
            <td style="white-space:nowrap">
                <button onclick="toggleEdit(<?= $cat['idCateg'] ?>, true)"
                    class="btn btn-primary btn-sm" id="btn-edit-<?= $cat['idCateg'] ?>">Modifier</button>

                <?php if ($cat['nbRec'] == 0): ?>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="supprimer">
                    <input type="hidden" name="idCateg" value="<?= $cat['idCateg'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm"
                        onclick="return confirm('Supprimer cette catégorie ?')">Supprimer</button>
                </form>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
function toggleEdit(id, show) {
    document.getElementById('label-' + id).style.display  = show ? 'none' : '';
    document.getElementById('form-' + id).style.display   = show ? 'block' : 'none';
    document.getElementById('btn-edit-' + id).style.display = show ? 'none' : '';
}
</script>

<?php require_once 'includes/footer.php'; ?>