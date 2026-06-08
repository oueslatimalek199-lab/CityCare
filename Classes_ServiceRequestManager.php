<?php

class ServiceRequestManager {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Soumettre une nouvelle demande de service
     */
    public function soumettre($idService, $idCitoyen, $description, $adresse = null) {
        if (empty($idService) || empty($idCitoyen)) {
            throw new Exception('Données invalides');
        }

        // Vérifier que le service existe et est actif
        $check = $this->pdo->prepare("SELECT idService FROM service WHERE idService = ? AND statut = 'actif'");
        $check->execute([$idService]);
        if (!$check->fetch()) {
            throw new Exception('Service introuvable ou inactif.');
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO demande_service (idService, idCitoyen, description, adresse, statut, dateCreation)
            VALUES (?, ?, ?, ?, 'en_attente', NOW())
        ");
        $stmt->execute([$idService, $idCitoyen, $description, $adresse]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Obtenir une demande par ID
     */
    public function obtenir($idRequest) {
        $stmt = $this->pdo->prepare("
            SELECT
                ds.*,
                s.nomService,
                c.prenom  AS citoyen_prenom,
                c.nom     AS citoyen_nom,
                c.email   AS citoyen_email,
                a.prenom  AS agent_prenom,
                a.nom     AS agent_nom,
                a.email   AS agent_email
            FROM demande_service ds
            JOIN service      s  ON s.idService     = ds.idService
            JOIN utilisateur  c  ON c.idUtilisateur = ds.idCitoyen
            LEFT JOIN utilisateur a ON a.idUtilisateur = ds.idAgent
            WHERE ds.idRequest = ?
        ");
        $stmt->execute([$idRequest]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtenir toutes les demandes, avec filtres optionnels
     */
    public function obtenirTous($statut = null, $idAgent = null, $idService = null) {
        $query = "
            SELECT
                ds.*,
                s.nomService,
                c.prenom  AS citoyen_prenom,
                c.nom     AS citoyen_nom,
                a.prenom  AS agent_prenom,
                a.nom     AS agent_nom,
                TIMESTAMPDIFF(HOUR, ds.dateAssignation, NOW()) AS heures_ecoulees
            FROM demande_service ds
            JOIN service      s  ON s.idService     = ds.idService
            JOIN utilisateur  c  ON c.idUtilisateur = ds.idCitoyen
            LEFT JOIN utilisateur a ON a.idUtilisateur = ds.idAgent
            WHERE 1=1
        ";
        $params = [];

        if ($statut) {
            $query   .= " AND ds.statut = ?";
            $params[] = $statut;
        }
        if ($idAgent) {
            $query   .= " AND ds.idAgent = ?";
            $params[] = $idAgent;
        }
        if ($idService) {
            $query   .= " AND ds.idService = ?";
            $params[] = $idService;
        }

        $query .= " ORDER BY ds.dateCreation DESC";

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtenir les demandes d'un citoyen
     */
    public function obtenirParCitoyen($idCitoyen) {
        $stmt = $this->pdo->prepare("
            SELECT
                ds.*,
                s.nomService,
                a.prenom AS agent_prenom,
                a.nom    AS agent_nom
            FROM demande_service ds
            JOIN service      s  ON s.idService     = ds.idService
            LEFT JOIN utilisateur a ON a.idUtilisateur = ds.idAgent
            WHERE ds.idCitoyen = ?
            ORDER BY ds.dateCreation DESC
        ");
        $stmt->execute([$idCitoyen]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assigner une demande à un agent
     */
    public function assigner($idRequest, $idAgent) {
        $check = $this->pdo->prepare("
            SELECT idUtilisateur FROM utilisateur
            WHERE idUtilisateur = ? AND role = 'agent' AND statut = 'actif'
        ");
        $check->execute([$idAgent]);
        if (!$check->fetch()) {
            throw new Exception("Agent #$idAgent introuvable ou inactif.");
        }

        $stmt = $this->pdo->prepare("
            UPDATE demande_service
            SET idAgent         = ?,
                statut          = 'assignée',
                dateAssignation = NOW()
            WHERE idRequest = ?
        ");
        $stmt->execute([$idAgent, $idRequest]);

        $this->insererHistorique($idRequest, 'assignée', "Assignée à l'agent #$idAgent");
    }

    /**
     * Réassigner une demande à un nouvel agent
     */
    public function reassignerDemande($idRequest, $idNouvelAgent) {
        $check = $this->pdo->prepare("SELECT idRequest FROM demande_service WHERE idRequest = ?");
        $check->execute([$idRequest]);
        if (!$check->fetch()) {
            throw new Exception("Demande #$idRequest introuvable.");
        }

        $checkAgent = $this->pdo->prepare("
            SELECT idUtilisateur FROM utilisateur
            WHERE idUtilisateur = ? AND role = 'agent' AND statut = 'actif'
        ");
        $checkAgent->execute([$idNouvelAgent]);
        if (!$checkAgent->fetch()) {
            throw new Exception("Agent #$idNouvelAgent introuvable ou inactif.");
        }

        $stmt = $this->pdo->prepare("
            UPDATE demande_service
            SET idAgent         = ?,
                statut          = 'assignée',
                dateAssignation = NOW()
            WHERE idRequest = ?
        ");
        $stmt->execute([$idNouvelAgent, $idRequest]);

        $this->insererHistorique($idRequest, 'assignée', "Réassignée à l'agent #$idNouvelAgent");
    }

    /**
     * Changer le statut d'une demande
     */
    public function changerStatut($idRequest, $statut, $commentaire = '') {
        $statutsValides = ['en_attente', 'assignée', 'en_cours', 'en_retard', 'résolue', 'fermée'];
        if (!in_array($statut, $statutsValides)) {
            throw new Exception("Statut invalide : $statut");
        }

        $stmt = $this->pdo->prepare("
            UPDATE demande_service
            SET statut = ?
            WHERE idRequest = ?
        ");
        $stmt->execute([$statut, $idRequest]);

        $this->insererHistorique($idRequest, $statut, $commentaire);
    }

    /**
     * Marquer automatiquement en retard toutes les demandes
     * assignées depuis plus de 24h et non encore résolues
     */
    public function marquerEnRetard() {
        $stmt = $this->pdo->prepare("
            UPDATE demande_service
            SET statut = 'en_retard'
            WHERE statut IN ('assignée', 'en_cours')
              AND dateAssignation IS NOT NULL
              AND TIMESTAMPDIFF(HOUR, dateAssignation, NOW()) > 24
        ");
        $stmt->execute();
    }

    /**
     * Obtenir toutes les demandes en retard
     */
    public function obtenirDemandesEnRetard() {
        $stmt = $this->pdo->query("
            SELECT
                ds.idRequest ,
                ds.idUtilisateur,
                s.nomService,
                c.prenom  AS citoyen_prenom,
                c.nom     AS citoyen_nom,
                a.prenom  AS agent_prenom,
                a.nom     AS agent_nom,
                TIMESTAMPDIFF(HOUR, ds.dateAssignation, NOW()) AS heures_ecoulees
            FROM demande_service ds
            JOIN service      s  ON s.idService     = ds.idService
            JOIN utilisateur  c  ON c.idUtilisateur = ds.idCitoyen
            JOIN utilisateur  a  ON a.idUtilisateur = ds.idUtilisateur
            WHERE ds.statut = 'en_retard'
            ORDER BY heures_ecoulees DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compter les demandes, avec filtre optionnel par statut
     */
    public function compter($statut = null) {
        $query  = "SELECT COUNT(*) as total FROM demande_service";
        $params = [];

        if ($statut) {
            $query   .= " WHERE statut = ?";
            $params[] = $statut;
        }

        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Insérer une entrée dans l'historique des statuts
     * (silencieux si la table n'existe pas encore)
     */
    private function insererHistorique($idRequest, $statut, $commentaire = '') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO historique_statut (idRequest, statut, commentaire, dateChangement)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$idRequest, $statut, $commentaire]);
        } catch (PDOException $e) {
            // Table absente : on ignore silencieusement
        }
    }
}