<?php
require_once '../Config/database.php';
require_once '../Config/session.php';
require_once '../Auth/auth.php';

// Vérifier la connexion
Auth::exigerConnexion('../login.php');
$user = Auth::getUtilisateur();

// Vérifier le POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Méthode non autorisée';
    header('Location: ../reclamations_publiques.php');
    exit;
}

$pdo = getConnexion();
$idRec = isset($_POST['idRec']) ? (int)$_POST['idRec'] : 0;
$contenu = trim($_POST['contenu'] ?? '');

// Validation
if ($idRec === 0 || empty($contenu)) {
    $_SESSION['error'] = 'Données invalides';
    header('Location: ../reclamations_publiques.php');
    exit;
}

if (strlen($contenu) < 5) {
    $_SESSION['error'] = 'Le commentaire doit contenir au moins 5 caractères';
    header("Location: ../reclamations_publiques.php?page=1#rec_$idRec");
    exit;
}

if (strlen($contenu) > 500) {
    $_SESSION['error'] = 'Le commentaire ne doit pas dépasser 500 caractères';
    header("Location: ../reclamations_publiques.php?page=1#rec_$idRec");
    exit;
}

// Vérifier que la réclamation existe
$checkRec = $pdo->prepare("SELECT idRec FROM reclamation WHERE idRec = ?");
$checkRec->execute([$idRec]);
if ($checkRec->rowCount() === 0) {
    $_SESSION['error'] = 'Réclamation introuvable';
    header('Location: ../reclamations_publiques.php');
    exit;
}

// Détecter les commentaires inappropriés
$inappropriateWords = [
    'badword1', 'badword2', 'insulte', 'spam', 'badword3',
    'profanity1', 'profanity2', 'racism', 'violence', 'hate',
    'con', 'merde', 'salaud', 'débile'  // Exemples français
];

$statut = 'publié';
$isInappropriate = false;

foreach ($inappropriateWords as $word) {
    if (stripos($contenu, $word) !== false) {
        $statut = 'modéré';
        $isInappropriate = true;
        break;
    }
}

try {
    // Vérifier que la table commentaire existe
    $checkTable = $pdo->query("SHOW TABLES LIKE 'commentaire'");
    if ($checkTable->rowCount() === 0) {
        throw new Exception('Table commentaire n\'existe pas. Exécutez le script SQL.');
    }
    
    // Insérer le commentaire
    $stmt = $pdo->prepare("
        INSERT INTO commentaire (idRec, idUtilisateur, contenu, statut)
        VALUES (?, ?, ?, ?)
    ");
    
    $stmt->execute([$idRec, $user['idUtilisateur'], $contenu, $statut]);
    
    if ($isInappropriate) {
        $_SESSION['warning'] = '⚠️ Votre commentaire a été soumis à modération en raison de contenu potentiellement inapproprié.';
    } else {
        $_SESSION['success'] = '✅ Commentaire publié avec succès!';
    }
    
    header("Location: ../reclamations_publiques.php#rec_$idRec");
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Erreur lors de la publication du commentaire: ' . $e->getMessage();
    header("Location: ../reclamations_publiques.php#rec_$idRec");
    exit;
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: ../reclamations_publiques.php");
    exit;
}
?>