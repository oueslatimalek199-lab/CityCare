<?php
require_once 'Config/database.php';
require_once 'Config/session.php';
require_once 'Auth/auth.php';

Auth::exigerConnexion();

$user = Auth::getUtilisateur();

// ✅ Noms de rôles alignés avec la base de données
if ($user['role'] === 'citoyen') {
    header('Location: ./citoyen.php');
} elseif ($user['role'] === 'agent') {
    header('Location: ./agent.php');
} elseif ($user['role'] === 'admin') {
    header('Location: ./admin.php');
} else {
    // ⚠️ Rôle inconnu → déconnecter proprement au lieu de boucler
    Auth::deconnecter();
    header('Location: ./login.php?erreur=role_inconnu');
}
exit;