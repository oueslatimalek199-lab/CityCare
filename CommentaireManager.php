<?php
// Classes/CommentaireManager.php
// Referenced by gestion_commentaires.php and delete_inappropriate_comments.php

class CommentaireManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function creer($idRec, $idUtilisateur, $contenu, $statut = 'publié') {
        if (strlen($contenu) < 5 || strlen($contenu) > 500) {
            throw new Exception('Contenu invalide');
        }
        $check = $this->pdo->prepare("SELECT idRec FROM reclamation WHERE idRec = ?");
        $check->execute([$idRec]);
        if ($check->rowCount() === 0) {
            throw new Exception('Réclamation introuvable');
        }
        $stmt = $this->pdo->prepare("
            INSERT INTO commentaire (idRec, idUtilisateur, contenu, statut)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$idRec, $idUtilisateur, $contenu, $statut]);
    }

    public function obtenirParReclamation($idRec, $statut = 'publié', $limit = null) {
        $query = "
            SELECT c.*, u.prenom, u.nom, u.photo
            FROM commentaire c
            LEFT JOIN utilisateur u ON c.idUtilisateur = u.idUtilisateur
            WHERE c.idRec = ? AND c.statut = ?
            ORDER BY c.dateCreation DESC
        ";
        if ($limit) {
            $query .= " LIMIT $limit";
        }
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$idRec, $statut]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtenir($idCommentaire) {
        $stmt = $this->pdo->prepare("
            SELECT c.*, u.prenom, u.nom, u.photo
            FROM commentaire c
            LEFT JOIN utilisateur u ON c.idUtilisateur = u.idUtilisateur
            WHERE c.idCommentaire = ?
        ");
        $stmt->execute([$idCommentaire]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function modifier($idCommentaire, $contenu) {
        if (strlen($contenu) < 5 || strlen($contenu) > 500) {
            throw new Exception('Contenu invalide');
        }
        $stmt = $this->pdo->prepare("
            UPDATE commentaire
            SET contenu = ?, dateModification = NOW()
            WHERE idCommentaire = ?
        ");
        return $stmt->execute([$contenu, $idCommentaire]);
    }

    public function supprimer($idCommentaire) {
        $stmt = $this->pdo->prepare("DELETE FROM commentaire WHERE idCommentaire = ?");
        return $stmt->execute([$idCommentaire]);
    }

    public function supprimerInappropries() {
        $inappropriateWords = [
            'badword1', 'badword2', 'insulte', 'spam', 'badword3',
            'profanity1', 'profanity2', 'racism', 'violence', 'hate',
            'con', 'merde', 'salaud', 'débile'
        ];

        $count = 0;
        $stmt = $this->pdo->query("SELECT idCommentaire, contenu FROM commentaire WHERE statut = 'modéré'");
        $commentaires = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($commentaires as $comment) {
            $shouldDelete = false;
            foreach ($inappropriateWords as $word) {
                if (stripos($comment['contenu'], $word) !== false) {
                    $shouldDelete = true;
                    break;
                }
            }
            if ($shouldDelete) {
                $this->supprimer($comment['idCommentaire']);
                $count++;
            }
        }
        return $count;
    }

    public function approuver($idCommentaire) {
        $stmt = $this->pdo->prepare("UPDATE commentaire SET statut = 'publié' WHERE idCommentaire = ?");
        return $stmt->execute([$idCommentaire]);
    }

    public function moderer($idCommentaire) {
        $stmt = $this->pdo->prepare("UPDATE commentaire SET statut = 'modéré' WHERE idCommentaire = ?");
        return $stmt->execute([$idCommentaire]);
    }

    public function compter($idRec, $statut = 'publié') {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM commentaire WHERE idRec = ? AND statut = ?");
        $stmt->execute([$idRec, $statut]);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
}