<?php
require_once 'Config/database.php';
require_once 'Auth/auth.php';

Auth::exigerConnexion('./login.php');
$user = Auth::getUtilisateur();

// Only agents can update status
if ($user['role'] !== 'agent') {
    header('Location: ./login.php');
    exit;
}

$pdo = getConnexion();
$id = (int)($_POST['idRec'] ?? 0);
$newStatus = trim($_POST['new_status'] ?? '');

if (!$id || !$newStatus) {
    header('Location: ./agent.php?error=invalid_data');
    exit;
}

// Verify this complaint is assigned to this agent
$check = $pdo->prepare("SELECT idRec FROM reclamation WHERE idRec = ? AND idUtilisateurAssigne = ?");
$check->execute([$id, $user['idUtilisateur']]);

if (!$check->fetch()) {
    header('Location: ./agent.php?error=unauthorized');
    exit;
}

// Validate status
$validStatuses = ['en attente', 'en traitement', 'résolu', 'annulé'];
if (!in_array($newStatus, $validStatuses)) {
    header('Location: ./detail_reclamation.php?id=' . $id . '&role=agent&error=invalid_status');
    exit;
}

// Update the status
try {
    $update = $pdo->prepare("
        UPDATE reclamation 
        SET statut = ?, dateModification = NOW()
        WHERE idRec = ?
    ");
    $update->execute([$newStatus, $id]);
    
    header('Location: ./detail_reclamation.php?id=' . $id . '&role=agent&success=status_updated');
} catch (Exception $e) {
    header('Location: ./detail_reclamation.php?id=' . $id . '&role=agent&error=update_failed');
}
exit;
?>