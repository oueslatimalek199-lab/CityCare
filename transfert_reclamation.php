<?php
require_once "../config/database.php";

$message = "";
$conn = getConnexion();

// --- Pagination setup ---
$limit = 5; 
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Compter le total
$total_stmt = $conn->query("SELECT COUNT(*) AS total FROM reclamation");
$total_rows = $total_stmt->fetch();
$total = $total_rows['total'];
$total_pages = ceil($total / $limit);

// --- Traitement transfert ---
if (isset($_POST['transferer'])) {
    $reclamation_id = intval($_POST['reclamation_id']);
    $id_citoyen = $_POST['id_citoyen'] ?? null;
    $service = $_POST['service'] ?? null;

    if ($reclamation_id && $id_citoyen && $service) {
        $sql = "UPDATE reclamation 
                SET idCitoyen = :id_citoyen, service = :service 
                WHERE idRec = :reclamation_id";
        $stmt = $conn->prepare($sql);
        if ($stmt->execute([
            ':id_citoyen' => $id_citoyen,
            ':service' => $service,
            ':reclamation_id' => $reclamation_id
        ])) {
            $message = "Réclamation transférée ✅";
        } else {
            $message = "Erreur SQL ❌";
        }
    } else {
        $message = "Veuillez choisir un citoyen et un service ❌";
    }
}

// --- Récupérer données avec jointure + pagination ---
$sql = "SELECT r.idRec, r.titre, r.service, r.idCitoyen, u.email AS citoyen_email
        FROM reclamation r
        LEFT JOIN utilisateur u ON r.idCitoyen = u.idUtilisateur
        ORDER BY r.idRec DESC
        LIMIT :limit OFFSET :offset";

$reclamations = $conn->prepare($sql);
$reclamations->bindValue(':limit', $limit, PDO::PARAM_INT);
$reclamations->bindValue(':offset', $offset, PDO::PARAM_INT);
$reclamations->execute();
$reclamations = $reclamations->fetchAll();

// ⚡ Ici on filtre uniquement les citoyens
$citoyens = $conn->query("SELECT * FROM utilisateur WHERE role='citoyen'")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Transfert Réclamation</title>
</head>
<body>

<h2>Transférer une réclamation</h2>
<p><?php echo $message; ?></p>

<table border="1">
<tr>
    <th>ID</th>
    <th>Titre</th>
    <th>Citoyen</th>
    <th>Service</th>
    <th>Action</th>
</tr>

<?php foreach ($reclamations as $rec) { ?>
<tr>
<form method="POST">
    <td><?php echo $rec['idRec']; ?></td>
    <td><?php echo $rec['titre']; ?></td>
    <td><?php echo $rec['citoyen_email'] ?: 'Non attribué'; ?></td>
    <td><?php echo $rec['service']; ?></td>
    <td>
        <input type="hidden" name="reclamation_id" value="<?php echo $rec['idRec']; ?>">

        <!-- Liste citoyens -->
        <select name="id_citoyen" required>
            <option value="" disabled selected>-- Sélectionner un citoyen --</option>
            <?php foreach ($citoyens as $c) { ?>
                <option value="<?php echo $c['idUtilisateur']; ?>">
                    <?php echo $c['email']; ?>
                </option>
            <?php } ?>
        </select>

        <!-- Liste services -->
        <select name="service" required>
            <option value="" disabled selected>-- Sélectionner un service --</option>
            <option value="technique">Technique</option>
            <option value="nettoyage">Nettoyage</option>
            <option value="eclairage">Éclairage</option>
        </select>

        <button type="submit" name="transferer">Transférer</button>
    </td>
</form>
</tr>
<?php } ?>
</table>

<!-- Pagination links -->
<div style="margin-top:20px;">
    <?php if ($page > 1): ?>
        <a href="?page=<?php echo $page - 1; ?>">⬅️ Précédent</a>
    <?php endif; ?>

    Page <?php echo $page; ?> / <?php echo $total_pages; ?>

    <?php if ($page < $total_pages): ?>
        <a href="?page=<?php echo $page + 1; ?>">Suivant ➡️</a>
    <?php endif; ?>
</div>

</body>
</html>


