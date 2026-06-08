<?php
require_once 'Config/database.php';
require_once 'Auth/auth.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

// Only admins can assign
if ($user['role'] !== 'admin') {
    header('Location: ./login.php');
    exit;
}

$pdo = getConnexion();
$idRec = (int)($_POST['idRec'] ?? 0);
$agentId = (int)($_POST['agent_id'] ?? 0);

if (!$idRec || !$agentId) {
    $_SESSION['error'] = 'Données invalides';
    header('Location: ./admin.php');
    exit;
}

// Verify complaint exists and is unassigned
$complaintCheck = $pdo->prepare("SELECT idRec, idUtilisateurAssigne FROM reclamation WHERE idRec = ?");
$complaintCheck->execute([$idRec]);
$complaint = $complaintCheck->fetch(PDO::FETCH_ASSOC);

if (!$complaint) {
    $_SESSION['error'] = 'Réclamation introuvable';
    header('Location: ./admin.php');
    exit;
}

// Verify agent exists and is active
$agentCheck = $pdo->prepare("SELECT idUtilisateur FROM utilisateur WHERE idUtilisateur = ? AND role = 'agent' AND statut = 'actif'");
$agentCheck->execute([$agentId]);

if (!$agentCheck->fetch()) {
    $_SESSION['error'] = 'Agent invalide ou inactif';
    header('Location: ./admin.php');
    exit;
}

// Assign complaint
try {
    $update = $pdo->prepare("
        UPDATE reclamation 
        SET idUtilisateurAssigne = ?, dateAssignation = NOW(), dateModification = NOW()
        WHERE idRec = ?
    ");
    $update->execute([$agentId, $idRec]);
    
    $_SESSION['success'] = 'Réclamation assignée avec succès ✓';
    header('Location: ./admin.php');
} catch (Exception $e) {
    $_SESSION['error'] = 'Erreur lors de l\'assignation';
    header('Location: ./admin.php');
}
exit;
?>