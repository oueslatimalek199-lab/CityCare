<?php

class ServiceManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Créer un service
     */
    public function creer($nomService, $descriptionService, $idCateg, $adresse, $telephone, $email, $horaire_debut, $horaire_fin, $jours_ouverture) {
        if (empty($nomService) || empty($idCateg)) {
            throw new Exception('Données invalides');
        }

        $check = $this->pdo->prepare("SELECT idService FROM service WHERE nomService = ?");
        $check->execute([$nomService]);
        if ($check->rowCount() > 0) {
            throw new Exception('Ce service existe déjà');
        }

        // Insérer d'abord avec qrcode_data vide
        $stmt = $this->pdo->prepare("
            INSERT INTO service (nomService, descriptionService, idCateg, adresse, telephone, email, horaire_debut, horaire_fin, jours_ouverture, qrcode_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, '')
        ");
        $stmt->execute([$nomService, $descriptionService, $idCateg, $adresse, $telephone, $email, $horaire_debut, $horaire_fin, $jours_ouverture]);

        // Récupérer l'ID généré, puis construire le QR code
        $idService = (int) $this->pdo->lastInsertId();
        $qrData = $this->genererQRCode($idService, $nomService, $descriptionService, $adresse, $telephone, $email, $horaire_debut, $horaire_fin);

        $this->pdo->prepare("UPDATE service SET qrcode_data = ? WHERE idService = ?")
                  ->execute([$qrData, $idService]);

        return true;
    }

    /**
     * Lire tous les services
     */
    public function obtenirTous($statut = null) {
        $query = "
            SELECT s.*, c.label as nomCateg
            FROM service s
            LEFT JOIN categorie c ON s.idCateg = c.idCateg
        ";

        if ($statut) {
            $query .= " WHERE s.statut = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$statut]);
        } else {
            $stmt = $this->pdo->query($query);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lire un service par ID
     */
    public function obtenir($idService) {
        $stmt = $this->pdo->prepare("
            SELECT s.*, c.label as nomCateg
            FROM service s
            LEFT JOIN categorie c ON s.idCateg = c.idCateg
            WHERE s.idService = ?
        ");

        $stmt->execute([$idService]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Modifier un service
     */
    public function modifier($idService, $nomService, $descriptionService, $idCateg, $adresse, $telephone, $email, $horaire_debut, $horaire_fin, $jours_ouverture) {
        $stmt = $this->pdo->prepare("
            UPDATE service
            SET nomService = ?, descriptionService = ?, idCateg = ?, adresse = ?, telephone = ?,
                email = ?, horaire_debut = ?, horaire_fin = ?, jours_ouverture = ?, dateModification = NOW()
            WHERE idService = ?
        ");

        return $stmt->execute([$nomService, $descriptionService, $idCateg, $adresse, $telephone, $email, $horaire_debut, $horaire_fin, $jours_ouverture, $idService]);
    }

    /**
     * Supprimer un service
     */
    public function supprimer($idService) {
        $stmt = $this->pdo->prepare("DELETE FROM service WHERE idService = ?");
        return $stmt->execute([$idService]);
    }

    /**
     * Changer le statut d'un service
     */
    public function changerStatut($idService, $statut) {
        $stmt = $this->pdo->prepare("
            UPDATE service
            SET statut = ?, dateModification = NOW()
            WHERE idService = ?
        ");

        return $stmt->execute([$statut, $idService]);
    }

    /**
 * Obtenir un agent par ID
 */
    public function getAgentById($idUtilisateur) {
        $stmt = $this->pdo->prepare("
            SELECT idUtilisateur, prenom, nom, email
            FROM utilisateur
            WHERE idUtilisateur = ? AND role = 'agent'
        ");
        $stmt->execute([$idUtilisateur]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Assigner un agent à un service
     */
    public function assignerAgent($idService, $idUtilisateur) {
        $stmt = $this->pdo->prepare("
            INSERT INTO service_agent (idService, idUtilisateur)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE dateAssignation = NOW()
        ");

        return $stmt->execute([$idService, $idUtilisateur]);
    }

    /**
     * Retirer un agent d'un service
     */
    public function retirerAgent($idService, $idUtilisateur) {
        $stmt = $this->pdo->prepare("
            DELETE FROM service_agent
            WHERE idService = ? AND idUtilisateur = ?
        ");

        return $stmt->execute([$idService, $idUtilisateur]);
    }

    /**
     * Obtenir les agents d'un service
     */
    public function obtenirAgents($idService) {
        $stmt = $this->pdo->prepare("
            SELECT u.idUtilisateur, u.prenom, u.nom, u.email
            FROM service_agent sa
            LEFT JOIN utilisateur u ON sa.idUtilisateur = u.idUtilisateur
            WHERE sa.idService = ?
        ");

        $stmt->execute([$idService]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Générer les données du QR Code
     */
    private function genererQRCode($idService, $nomService, $description, $adresse, $telephone, $email, $horaire_debut, $horaire_fin) {
        $serviceUrl = "http://localhost/Sprint1_AGL/service.php?id=" . $idService;

        $data  = "Service : $nomService\n";
        $data .= "Description : $description\n";
        $data .= "Adresse : $adresse\n";
        $data .= "Téléphone : $telephone\n";
        $data .= "Email : $email\n";
        $data .= "Horaires : $horaire_debut - $horaire_fin\n";
        $data .= "URL : $serviceUrl";

        return $data;
    }

    /**
     * Compter les services
     */
    public function compter($statut = null) {
        $query = "SELECT COUNT(*) as total FROM service";

        if ($statut) {
            $query .= " WHERE statut = ?";
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([$statut]);
        } else {
            $stmt = $this->pdo->query($query);
        }

        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }
}