<?php
require_once 'Config/database.php';
require_once 'Auth/auth.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

// Only admins can manage agents
if ($user['role'] !== 'admin') {
    header('Location: ./login.php');
    exit;
}

$pdo = getConnexion();
$idAgent = (int)($_POST['idAgent'] ?? 0);
$newStatus = trim($_POST['newStatus'] ?? '');

if (!$idAgent || !in_array($newStatus, ['actif', 'inactif'])) {
    $_SESSION['error'] = 'Données invalides';
    header('Location: ./admin.php');
    exit;
}

// Verify agent exists
$agentCheck = $pdo->prepare("SELECT idUtilisateur, statut FROM utilisateur WHERE idUtilisateur = ? AND role = 'agent'");
$agentCheck->execute([$idAgent]);
$agent = $agentCheck->fetch(PDO::FETCH_ASSOC);

if (!$agent) {
    $_SESSION['error'] = 'Agent introuvable';
    header('Location: ./admin.php');
    exit;
}

// Update agent status
try {
    $update = $pdo->prepare("UPDATE utilisateur SET statut = ? WHERE idUtilisateur = ?");
    $update->execute([$newStatus, $idAgent]);
    
    if ($newStatus === 'actif') {
        $_SESSION['success'] = 'Agent activé avec succès ✓';
    } else {
        $_SESSION['success'] = 'Agent désactivé avec succès ✓';
    }
    
    header('Location: ./admin.php');
} catch (Exception $e) {
    $_SESSION['error'] = 'Erreur lors de la mise à jour du statut';
    header('Location: ./admin.php');
}
exit;
?>