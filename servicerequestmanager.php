<?php
// Classes/ServiceRequestManager.php

require_once __DIR__ . '/../EmailNotifications.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

        $check = $this->pdo->prepare("SELECT idService FROM service WHERE idService = ? AND statut = 'actif'");
        $check->execute([$idService]);
        if (!$check->fetch()) {
            throw new Exception('Service introuvable ou inactif.');
        }

        // Look up which agent handles this service
        $agentStmt = $this->pdo->prepare("SELECT idUtilisateur FROM service_agent WHERE idService = ? LIMIT 1");
        $agentStmt->execute([$idService]);
        $agentRow = $agentStmt->fetch(PDO::FETCH_ASSOC);
        $idAgent  = $agentRow ? $agentRow['idUtilisateur'] : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO demande_service (idUtilisateur, idService, idAgent, description, statut, dateCreation, dateAssignation)
            VALUES (?, ?, ?, ?, 'assignée', NOW(), NOW())
        ");
        $stmt->execute([$idCitoyen, $idService, $idAgent, $description]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Alias: creerDemande — used by soumettre_service.php
     */
    public function creerDemande($idService, $idCitoyen, $description) {
        return $this->soumettre($idService, $idCitoyen, $description);
    }

    /**
     * Obtenir une demande par ID
     * FIX: use ds.idUtilisateur (not ds.idCitoyen — that column has no data)
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
            JOIN utilisateur  c  ON c.idUtilisateur = ds.idUtilisateur
            LEFT JOIN utilisateur a ON a.idUtilisateur = ds.idAgent
            WHERE ds.idRequest = ?
        ");
        $stmt->execute([$idRequest]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Alias — used by detail_service_request.php
     */
    public function obtenirDemande($idRequest) {
        return $this->obtenir($idRequest);
    }

    /**
     * Obtenir les demandes en attente pour un agent
     * FIX: use ds.idUtilisateur for citizen join
     */
    public function obtenirDemandesEnAttente($idAgent) {
        $stmt = $this->pdo->prepare("
            SELECT
                ds.idRequest,
                ds.description,
                ds.note,
                ds.statut,
                ds.dateCreation,
                ds.dateAssignation,
                s.nomService,
                c.prenom,
                c.nom,
                c.email AS citoyen_email,
                TIMESTAMPDIFF(HOUR, ds.dateCreation, NOW()) AS heures_ecoulees
            FROM demande_service ds
            JOIN service s      ON s.idService     = ds.idService
            JOIN utilisateur c  ON c.idUtilisateur = ds.idUtilisateur
            WHERE ds.idAgent = ?
              AND ds.statut IN ('en_attente', 'assignée', 'en attente')
            ORDER BY ds.dateCreation ASC
        ");
        $stmt->execute([$idAgent]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtenir les demandes d'un citoyen
     * FIX: use ds.idUtilisateur for citizen join
     */
    public function obtenirDemandesCitoyen($idCitoyen) {
        $stmt = $this->pdo->prepare("
            SELECT
                ds.*,
                s.nomService,
                a.prenom AS agent_prenom,
                a.nom    AS agent_nom
            FROM demande_service ds
            JOIN service s         ON s.idService     = ds.idService
            LEFT JOIN utilisateur a ON a.idUtilisateur = ds.idAgent
            WHERE ds.idUtilisateur = ?
            ORDER BY ds.dateCreation DESC
        ");
        $stmt->execute([$idCitoyen]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Alias — used by older code
     */
    public function obtenirParCitoyen($idCitoyen) {
        return $this->obtenirDemandesCitoyen($idCitoyen);
    }

    /**
     * Accepter une demande
     * FIX: use ds.idUtilisateur for citizen join
     */
    public function accepterDemande($idRequest, $idAgent, $notes = '') {
        $check = $this->pdo->prepare("
            SELECT ds.*, s.nomService,
                   c.email   AS citoyen_email,
                   c.prenom  AS citoyen_prenom,
                   c.nom     AS citoyen_nom
            FROM demande_service ds
            JOIN service s         ON s.idService     = ds.idService
            JOIN utilisateur c     ON c.idUtilisateur = ds.idUtilisateur
            WHERE ds.idRequest = ? AND ds.idAgent = ?
        ");
        $check->execute([$idRequest, $idAgent]);
        $demande = $check->fetch(PDO::FETCH_ASSOC);

        if (!$demande) {
            throw new Exception("Demande introuvable ou non assignée à cet agent.");
        }

        $this->pdo->prepare("
            UPDATE demande_service
            SET statut = 'acceptée', dateModification = NOW()
            WHERE idRequest = ?
        ")->execute([$idRequest]);

        $this->envoyerEmailCitoyen(
            $demande['citoyen_email'],
            $demande['citoyen_prenom'] . ' ' . $demande['citoyen_nom'],
            $idRequest,
            $demande['nomService'],
            'acceptée',
            $notes
        );

        $this->insererHistorique($idRequest, 'acceptée', $notes);
    }

    /**
     * Refuser une demande
     * FIX: use ds.idUtilisateur for citizen join — duplicate removed
     */
    public function refuserDemande($idRequest, $idAgent, $motif) {
        $check = $this->pdo->prepare("
            SELECT ds.*, s.nomService,
                   c.email   AS citoyen_email,
                   c.prenom  AS citoyen_prenom,
                   c.nom     AS citoyen_nom
            FROM demande_service ds
            JOIN service s         ON s.idService     = ds.idService
            JOIN utilisateur c     ON c.idUtilisateur = ds.idUtilisateur
            WHERE ds.idRequest = ? AND ds.idAgent = ?
        ");
        $check->execute([$idRequest, $idAgent]);
        $demande = $check->fetch(PDO::FETCH_ASSOC);

        if (!$demande) {
            throw new Exception("Demande introuvable ou non assignée à cet agent.");
        }

        $this->pdo->prepare("
            UPDATE demande_service
            SET statut = 'refusée', motifRefus = ?, dateModification = NOW()
            WHERE idRequest = ?
        ")->execute([$motif, $idRequest]);

        $this->envoyerEmailCitoyen(
            $demande['citoyen_email'],
            $demande['citoyen_prenom'] . ' ' . $demande['citoyen_nom'],
            $idRequest,
            $demande['nomService'],
            'refusée',
            $motif
        );

        $this->insererHistorique($idRequest, 'refusée', $motif);
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

        $this->pdo->prepare("
            UPDATE demande_service
            SET idAgent = ?, statut = 'assignée', dateAssignation = NOW()
            WHERE idRequest = ?
        ")->execute([$idAgent, $idRequest]);

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

        $this->pdo->prepare("
            UPDATE demande_service
            SET idAgent = ?, statut = 'assignée', dateAssignation = NOW()
            WHERE idRequest = ?
        ")->execute([$idNouvelAgent, $idRequest]);

        $this->insererHistorique($idRequest, 'assignée', "Réassignée à l'agent #$idNouvelAgent");
    }

    /**
     * Marquer automatiquement en retard (> 24h sans traitement)
     */
    public function marquerEnRetard() {
        $this->pdo->prepare("
            UPDATE demande_service
            SET statut = 'en_retard'
            WHERE statut IN ('assignée', 'en_attente', 'en attente')
              AND dateAssignation IS NOT NULL
              AND TIMESTAMPDIFF(HOUR, dateAssignation, NOW()) > 24
        ")->execute();
    }

    /**
     * Obtenir toutes les demandes en retard
     * FIX: use ds.idUtilisateur for citizen join
     */
    public function obtenirDemandesEnRetard() {
        $stmt = $this->pdo->query("
            SELECT
                ds.idRequest,
                ds.idAgent,
                s.nomService,
                c.prenom  AS citoyen_prenom,
                c.nom     AS citoyen_nom,
                a.prenom  AS agent_prenom,
                a.nom     AS agent_nom,
                TIMESTAMPDIFF(HOUR, ds.dateAssignation, NOW()) AS heures_ecoulees
            FROM demande_service ds
            JOIN service s         ON s.idService     = ds.idService
            JOIN utilisateur c     ON c.idUtilisateur = ds.idUtilisateur
            JOIN utilisateur a     ON a.idUtilisateur = ds.idAgent
            WHERE ds.statut = 'en_retard'
            ORDER BY heures_ecoulees DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compter les demandes
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
     * Send status email to citizen
     */
    private function envoyerEmailCitoyen($to, $citizenName, $idRequest, $nomService, $statut, $message = '') {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'amaltoumi535@gmail.com';
            $mail->Password   = 'crmr mydm sqtn zdqn';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->CharSet    = 'UTF-8';

            $mail->setFrom('amaltoumi535@gmail.com', 'Plateforme CityCare');
            $mail->addAddress($to);
            $mail->isHTML(true);

            $statusColors = [
                'acceptée' => ['bg' => '#d4edda', 'color' => '#155724', 'icon' => '✅'],
                'refusée'  => ['bg' => '#f8d7da', 'color' => '#721c24', 'icon' => '❌'],
            ];
            $badge = $statusColors[$statut] ?? ['bg' => '#eee', 'color' => '#333', 'icon' => '📋'];

            $mail->Subject = $badge['icon'] . " Votre demande de service #$idRequest a été " . $statut;

            $messageHtml = !empty($message)
                ? "<div style='margin-top:12px;padding:12px;background:#f9f9f9;border-left:3px solid #667eea;border-radius:4px;font-style:italic;color:#444'>"
                  . nl2br(htmlspecialchars($message)) . "</div>"
                : '';

            $mail->Body = "
            <html><body style='font-family:Arial,sans-serif;color:#333;line-height:1.6'>
            <div style='max-width:600px;margin:0 auto;padding:20px;background:#f9f9f9;border-radius:8px'>
                <div style='background:linear-gradient(135deg,#667eea,#764ba2);color:white;padding:20px;border-radius:8px;margin-bottom:20px'>
                    <h2 style='margin:0'>{$badge['icon']} Mise à jour de votre demande de service</h2>
                </div>
                <div style='background:white;padding:20px;border-radius:8px;border-left:4px solid #667eea'>
                    <p>Bonjour <strong>" . htmlspecialchars($citizenName) . "</strong>,</p>
                    <p>Votre demande de service a été traitée.</p>
                    <div style='background:#f5f5f5;padding:15px;border-radius:5px;margin:15px 0'>
                        <div style='margin:8px 0'><span style='font-weight:bold;color:#667eea'>📌 Référence :</span> #$idRequest</div>
                        <div style='margin:8px 0'><span style='font-weight:bold;color:#667eea'>🏢 Service :</span> " . htmlspecialchars($nomService) . "</div>
                        <div style='margin:8px 0'><span style='font-weight:bold;color:#667eea'>📊 Statut :</span>
                            <span style='background:{$badge['bg']};color:{$badge['color']};padding:3px 10px;border-radius:12px;font-weight:700;font-size:13px'>" . htmlspecialchars($statut) . "</span>
                        </div>
                    </div>
                    $messageHtml
                    <div style='margin-top:20px;font-size:12px;color:#999;text-align:center'>
                        <p>Cet email a été envoyé automatiquement. Veuillez ne pas répondre.</p>
                        <p>Plateforme CityCare — Gestion des Services Municipaux</p>
                    </div>
                </div>
            </div>
            </body></html>";

            $mail->send();
        } catch (Exception $e) {
            error_log("ServiceRequestManager email error: " . $mail->ErrorInfo);
        }
    }

    /**
     * Insert into historique_statut (silent if table missing)
     * FIX: column is idDemande in your DB (not idRequest)
     */
    private function insererHistorique($idRequest, $statut, $commentaire = '') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO historique_statut (idDemande, statut, commentaire, dateChangement)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$idRequest, $statut, $commentaire]);
        } catch (PDOException $e) {
            // Silently ignore
        }
    }
}