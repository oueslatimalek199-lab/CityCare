-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : dim. 19 avr. 2026 à 14:17
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `ma_base`
--

-- --------------------------------------------------------

--
-- Structure de la table `administrateur`
--

CREATE TABLE `administrateur` (
  `idUtilisateur` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `agentmunicipal`
--

CREATE TABLE `agentmunicipal` (
  `idUtilisateur` int(11) NOT NULL,
  `matricule` varchar(100) NOT NULL,
  `idService` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `categorie`
--

CREATE TABLE `categorie` (
  `idCateg` int(11) NOT NULL,
  `label` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `categorie`
--

INSERT INTO `categorie` (`idCateg`, `label`) VALUES
(1, 'eclairage'),
(2, 'voirie'),
(3, 'moshkla kbira'),
(4, 'jarna haz 7it'),
(5, 'Voirie'),
(6, 'Éclairage public'),
(7, 'Eau et assainissement'),
(8, 'Espaces verts'),
(9, 'Collecte des déchets'),
(10, 'Transport'),
(11, 'Bâtiments publics'),
(12, 'Autres');

-- --------------------------------------------------------

--
-- Structure de la table `citoyen`
--

CREATE TABLE `citoyen` (
  `idUtilisateur` int(11) NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `citoyen`
--

INSERT INTO `citoyen` (`idUtilisateur`, `adresse`, `telephone`) VALUES
(1, 'beja', '+216 20 000 000');

-- --------------------------------------------------------

--
-- Structure de la table `commentaire`
--

CREATE TABLE `commentaire` (
  `idCommentaire` int(11) NOT NULL,
  `idRec` int(11) NOT NULL,
  `idUtilisateur` int(11) NOT NULL,
  `contenu` text NOT NULL,
  `dateCreation` timestamp NOT NULL DEFAULT current_timestamp(),
  `dateModification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `statut` enum('publié','modéré','supprimé') DEFAULT 'publié'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `commentaire`
--

INSERT INTO `commentaire` (`idCommentaire`, `idRec`, `idUtilisateur`, `contenu`, `dateCreation`, `dateModification`, `statut`) VALUES
(1, 18, 1, 'ouuh ya weldi yarham weldik', '2026-04-05 10:54:20', '2026-04-05 10:54:20', 'publié'),
(2, 22, 1, 'ALLOOOOO ALOOOOOOOOO', '2026-04-19 08:04:52', '2026-04-19 08:04:52', 'publié');

-- --------------------------------------------------------

--
-- Structure de la table `compte`
--

CREATE TABLE `compte` (
  `idCompte` int(11) NOT NULL,
  `login` varchar(100) NOT NULL,
  `mdp` varchar(255) NOT NULL,
  `statut` varchar(50) NOT NULL,
  `idUtilisateur` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `conversation`
--

CREATE TABLE `conversation` (
  `idConversation` int(11) NOT NULL,
  `idRec` int(11) DEFAULT NULL COMMENT 'Related complaint',
  `idService` int(11) DEFAULT NULL COMMENT 'Related service',
  `idRequest` int(11) DEFAULT NULL,
  `idUtilisateur1` int(11) NOT NULL,
  `idUtilisateur2` int(11) NOT NULL,
  `dernier_message_id` int(11) DEFAULT NULL,
  `dateUpdate` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `demande_annulation_token`
--

CREATE TABLE `demande_annulation_token` (
  `id` int(11) NOT NULL,
  `idDemande` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `demande_annulation_token`
--

INSERT INTO `demande_annulation_token` (`id`, `idDemande`, `token`, `expires_at`, `used`) VALUES
(1, 1, 'bad6b22cb6ec795e1b7ce6a3051cad367fd5b7ef67dd28c1d4406c6f54814217', '2026-04-19 11:31:02', 0);

-- --------------------------------------------------------

--
-- Structure de la table `demande_service`
--

CREATE TABLE `demande_service` (
  `idRequest` int(11) NOT NULL,
  `idUtilisateur` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `idService` int(11) NOT NULL,
  `idAgent` int(11) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `statut` enum('en attente','en_attente','assignée','en cours','acceptée','refusée','en_retard','terminée','annulée') NOT NULL DEFAULT 'en_attente',
  `dateCreation` datetime NOT NULL DEFAULT current_timestamp(),
  `dateModification` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `dateAssignation` datetime DEFAULT NULL,
  `motifRefus` text DEFAULT NULL,
  `idCitoyen` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `demande_service`
--

INSERT INTO `demande_service` (`idRequest`, `idUtilisateur`, `description`, `idService`, `idAgent`, `note`, `statut`, `dateCreation`, `dateModification`, `dateAssignation`, `motifRefus`, `idCitoyen`) VALUES
(1, 1, '', 6, 22, 'TESSSSTTTTTTTTTTTTTTTTTTTTTTTTTTTT', 'acceptée', '2026-04-19 08:31:02', '2026-04-19 08:33:04', '2026-04-19 08:31:02', NULL, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `historique_statut`
--

CREATE TABLE `historique_statut` (
  `id` int(11) NOT NULL,
  `idRequest` int(11) NOT NULL,
  `statut` varchar(50) NOT NULL,
  `commentaire` text DEFAULT NULL,
  `dateChangement` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `message`
--

CREATE TABLE `message` (
  `idMessage` int(11) NOT NULL,
  `idRec` int(11) DEFAULT NULL COMMENT 'Reference to complaint',
  `idService` int(11) DEFAULT NULL COMMENT 'Reference to service request',
  `idRequest` int(11) DEFAULT NULL,
  `idExpediteaur` int(11) NOT NULL COMMENT 'User sending message (citoyen or agent)',
  `idDestinataire` int(11) NOT NULL COMMENT 'User receiving message',
  `contenu` text NOT NULL,
  `typeMessage` enum('text','note') DEFAULT 'text',
  `lu` tinyint(1) DEFAULT 0,
  `dateCreation` datetime DEFAULT current_timestamp(),
  `dateModification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `municipalite`
--

CREATE TABLE `municipalite` (
  `idMunicipalite` int(11) NOT NULL,
  `nom` varchar(150) NOT NULL,
  `ville` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `photos`
--

CREATE TABLE `photos` (
  `idPrint` int(11) NOT NULL,
  `cheminFichier` varchar(255) NOT NULL,
  `date` date NOT NULL,
  `idRec` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `photos`
--

INSERT INTO `photos` (`idPrint`, `cheminFichier`, `date`, `idRec`) VALUES
(2, 'uploads/rec_69c5af4572c0f.jpg', '2026-03-26', 16);

-- --------------------------------------------------------

--
-- Structure de la table `reclamation`
--

CREATE TABLE `reclamation` (
  `idRec` int(11) NOT NULL,
  `titre` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `statut` varchar(50) NOT NULL,
  `dateCreation` date NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `idUtilisateur` int(11) NOT NULL,
  `dateModification` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `dateAssignation` date DEFAULT NULL,
  `commentaireAgent` text DEFAULT NULL,
  `idUtilisateurAssigne` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reclamation`
--

INSERT INTO `reclamation` (`idRec`, `titre`, `description`, `statut`, `dateCreation`, `adresse`, `idUtilisateur`, `dateModification`, `dateAssignation`, `commentaireAgent`, `idUtilisateurAssigne`) VALUES
(10, 'bh xsdhjbbxdbx', 'hcdxbhxchbxw', 'en attente', '2026-03-26', 'ghxhsqbhxsdb', 4, NULL, NULL, NULL, NULL),
(11, 'ouh', 'hjvgujvghg', 'en attente', '2026-03-26', 'beja', 4, NULL, NULL, NULL, NULL),
(12, 'haya belleh ekhdem', 'brabi brabi brabi', 'en attente', '2026-03-26', 'isgs', 4, NULL, '2026-03-26', NULL, 5),
(13, 'haya belleh ekhdem', 'brabi brabi brabi', 'en attente', '2026-03-26', 'isgs', 4, NULL, '2026-03-26', NULL, 5),
(15, 'haya belleh ekhdem', 'brabi brabi brabi', 'en attente', '2026-03-26', 'isgs', 4, '2026-04-18 18:26:10', '2026-04-18', NULL, 22),
(16, 'haya belleh ekhdem', 'brabi brabi brabi', 'en traitement', '2026-03-26', 'isgs', 4, '2026-03-27 00:00:00', NULL, 'os barka yezzi mn 7essek', 1),
(17, 'Test Complaint for Reassignment', 'This is a test complaint to test reassignment', 'en traitement', '2025-02-01', '123 Test Street', 1, '2026-03-28 00:00:00', '2026-03-28', NULL, 22),
(18, 'Problème de voirie dégradée', 'La rue principale a des nids-de-poule importants qui endommagent les véhicules', 'résolu', '2025-03-08', '123 Rue Principale, Ville', 1, '2026-03-28 21:35:54', '2026-03-28', 'votre reclamation a ete resolue , une équipe de chantier sera envoyer dans deux jours que vous recevez cet email', 23),
(19, 'Éclairage public défaillant', 'Les lampadaires du quartier nord ne fonctionnent plus depuis longtemps', 'résolu', '2025-03-03', '456 Avenue de la Paix, Ville', 1, '2026-03-28 21:56:09', '2026-03-28', 'ASLEMAAAAAAAAAAAAAAAAAAAAA WESLEK EMAIL?????????????????????,', 23),
(20, 'Déchets accumulés dans le parc', 'Les poubelles du parc central ne sont pas vidées régulièrement', 'résolu', '2025-02-26', '789 Boulevard du Parc, Ville', 1, '2026-03-28 22:54:25', '2026-03-28', 'aya thannit mme haw resolu l prb', 23),
(21, 'Canalisation bouchée', 'L\'égout principal est bouché et cause des débordements d\'eau sale', 'résolu', '2025-03-10', '321 Rue de la Fontaine, Ville', 1, '2026-04-18 19:31:57', '2026-03-30', 'TEST TEST TEST 1 2 1 2', 23),
(22, 'Problème de voirie dégradée', 'La rue principale a des nids-de-poule importants qui endommagent les véhicules', 'en traitement', '2025-03-08', '123 Rue Principale, Ville', 1, '2026-04-12 16:02:14', '2026-04-12', NULL, 27),
(23, 'hamdoulah khdem', 'mais wlh finallement', 'en attente', '2026-03-28', 'isgs', 1, '2026-03-28 22:57:19', NULL, NULL, NULL),
(24, 'ya amallllll', 'wesletek ah ??????????', 'en attente', '2026-03-29', 'sousssssse', 1, '2026-03-29 11:40:04', NULL, NULL, NULL),
(25, 'ya amallllll', 'wesletek ah ??????????', 'en attente', '2026-03-29', 'sousssssse', 1, '2026-03-29 11:59:41', NULL, NULL, NULL),
(26, 'brabi ousel', 'belllleh bellleh ekhdem svp brooooooooooooooooo', 'en attente', '2026-03-29', 'ti eli houa', 1, '2026-04-18 18:25:54', '2026-04-18', NULL, 23),
(27, 'Réclamation concernant des nids-de-poule sur l’avenue Habib Bourguiba', 'Depuis plus d’un mois, plusieurs nids-de-poule importants sont apparus sur l’avenue Habib Bourguiba, rendant la circulation dangereuse, surtout la nuit. Je sollicite une réparation urgente afin d’éviter des accidents et d’améliorer la sécurité routière.', 'annulé', '2026-03-29', 'tunis', 1, '2026-03-30 19:03:43', '2026-03-29', NULL, 4),
(28, 'ttrah', 'njarbou photo tekhdem ou non', 'en attente', '2026-03-29', 'bdjduhcd', 1, '2026-03-29 20:51:14', '2026-03-29', NULL, 22),
(29, 'vbhgbk', 'gjhbjhnbj;hn,khjbjhvgjhhgkj', 'en attente', '2026-04-05', 'hbhjbhn,', 1, '2026-04-05 12:58:03', NULL, NULL, NULL),
(30, 'gvjtfctgv', 'hhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhhh', 'en attente', '2026-04-05', 'jggvghjvhk', 1, '2026-04-05 13:03:04', '2026-04-05', NULL, 25),
(31, 'jhykhjbuhyj', 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk', 'résolu', '2026-04-05', 'hbhjvhj', 1, '2026-04-10 10:50:14', '2026-04-05', 'hayyyya sahhha lik haww rigelnelek moshkeltekkkk', 4),
(32, 'jhykhjbuhyj', 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk', 'en attente', '2026-04-05', 'hbhjvhj', 1, '2026-04-05 13:04:21', '2026-04-05', NULL, 4),
(33, 'jhykhjbuhyj', 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk', 'en attente', '2026-04-05', 'hbhjvhj', 1, '2026-04-05 13:04:24', '2026-04-05', NULL, 4),
(34, 'jhykhjbuhyj', 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk', 'en attente', '2026-04-05', 'hbhjvhj', 1, '2026-04-05 13:05:52', '2026-04-05', NULL, 4),
(35, 'jhykhjbuhyj', 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk', 'en attente', '2026-04-05', 'hbhjvhj', 1, '2026-04-05 13:05:59', '2026-04-05', NULL, 4),
(36, 'jhykhjbuhyj', 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk', 'en attente', '2026-04-05', 'hbhjvhj', 1, '2026-04-05 13:06:24', '2026-04-05', NULL, 4);

-- --------------------------------------------------------

--
-- Structure de la table `reclamation_categorie`
--

CREATE TABLE `reclamation_categorie` (
  `idRec` int(11) NOT NULL,
  `idCateg` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `reclamation_categorie`
--

INSERT INTO `reclamation_categorie` (`idRec`, `idCateg`) VALUES
(10, 1),
(11, 4),
(16, 2),
(23, 3),
(24, 1),
(25, 1),
(26, 1),
(27, 2),
(28, 1),
(29, 3),
(30, 4),
(31, 2),
(32, 2),
(33, 2),
(34, 2),
(35, 2),
(36, 2);

-- --------------------------------------------------------

--
-- Structure de la table `reclamation_token`
--

CREATE TABLE `reclamation_token` (
  `id` int(11) NOT NULL,
  `idRec` int(11) NOT NULL,
  `token` varchar(100) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `service`
--

CREATE TABLE `service` (
  `idService` int(11) NOT NULL,
  `nomService` varchar(100) NOT NULL,
  `descriptionService` text DEFAULT NULL,
  `idCateg` int(11) NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `horaire_debut` time DEFAULT NULL,
  `horaire_fin` time DEFAULT NULL,
  `jours_ouverture` varchar(50) DEFAULT NULL,
  `dateCreation` timestamp NOT NULL DEFAULT current_timestamp(),
  `dateModification` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `statut` enum('actif','inactif') DEFAULT 'actif',
  `qrcode_data` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `service`
--

INSERT INTO `service` (`idService`, `nomService`, `descriptionService`, `idCateg`, `adresse`, `telephone`, `email`, `horaire_debut`, `horaire_fin`, `jours_ouverture`, `dateCreation`, `dateModification`, `statut`, `qrcode_data`) VALUES
(2, 'oooooooh', 'haya brabiii brabiiiiii', 4, 'isgs', '50267578', 'oueslatimalek199@gmail.com', '12:59:00', '00:00:00', '', '2026-04-10 09:58:21', '2026-04-10 09:58:21', 'actif', 'SERVICE: oooooooh\nDESC: haya brabiii brabiiiiii\nADDR: isgs\nTEL: 50267578\nEMAIL: oueslatimalek199@gmail.com\nHOURS: 12:59 - '),
(3, 'sdcxbdj;chdcxhbj', 'sdchbswkxjwhj', 1, 'dhkcxhj', '10200322', 'oueslatimalek199@gmail.com', '12:03:00', '17:07:00', 'hkcj x', '2026-04-10 10:02:25', '2026-04-10 10:02:25', 'actif', 'SERVICE: sdcxbdj;chdcxhbj\nDESC: sdchbswkxjwhj\nADDR: dhkcxhj\nTEL: 10200322\nEMAIL: oueslatimalek199@gmail.com\nHOURS: 12:03 - 17:07'),
(4, 'ahla', 'hdxcbhjvgcxbjcxh', 3, 'wiiiiiw', '50267578', 'oueslatimalek199@gmail.com', '12:00:00', '22:00:00', 'Lundi,Mardi,Mercredi,Jeudi,Vendredi', '2026-04-12 10:37:39', '2026-04-12 13:24:14', 'actif', '{\"service\":\"ahla\",\"categorie\":\"\",\"description\":\"hdxcbhjvgcxbjcxh\",\"adresse\":\"wiiiiiw\",\"telephone\":\"50267578\",\"email\":\"oueslatimalek199@gmail.com\",\"horaires\":\"12:00 - 22:00\",\"jours\":\"Lundi,Mardi,Mercredi,Jeudi,Vendredi\"}'),
(5, 'Maintenance de l’éclairage public', 'Signalez les lampadaires défectueux ou en panne.', 6, 'Avenue Habib Bourguiba, Tunis 1000', '23569847', 'amaltoumi099@gmail.com', '08:00:00', '15:00:00', 'lun-ven', '2026-04-18 19:42:42', '2026-04-18 19:42:42', 'actif', 'Service : Maintenance de l’éclairage public\nDescription : Signalez les lampadaires défectueux ou en panne.\nAdresse : Avenue Habib Bourguiba, Tunis 1000\nTéléphone : 23569847\nEmail : amaltoumi099@gmail.com\nHoraires : 08:00 - 15:00\nURL : http://localhost/Sprint1_AGL/service.php?id=5'),
(6, 'Surveillance de la propreté publique', 'Signalez les zones sales et dépôts sauvages.', 9, '22 Rue de la Propreté, La Marsa 2070', '78546932', 'amaltoumi1@gmail.com', '09:00:00', '16:00:00', 'lun-Sam', '2026-04-19 07:09:25', '2026-04-19 07:09:25', 'actif', 'Service : Surveillance de la propreté publique\nDescription : Signalez les zones sales et dépôts sauvages.\nAdresse : 22 Rue de la Propreté, La Marsa 2070\nTéléphone : 78546932\nEmail : amaltoumi1@gmail.com\nHoraires : 09:00 - 16:00\nURL : http://localhost/Sprint1_AGL/service.php?id=6');

-- --------------------------------------------------------

--
-- Structure de la table `servicemunicipal`
--

CREATE TABLE `servicemunicipal` (
  `idService` int(11) NOT NULL,
  `nomService` varchar(150) NOT NULL,
  `idMunicipalite` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `service_agent`
--

CREATE TABLE `service_agent` (
  `idServiceAgent` int(11) NOT NULL,
  `idService` int(11) NOT NULL,
  `idUtilisateur` int(11) NOT NULL,
  `dateAssignation` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `service_agent`
--

INSERT INTO `service_agent` (`idServiceAgent`, `idService`, `idUtilisateur`, `dateAssignation`) VALUES
(2, 5, 23, '2026-04-18 19:48:45'),
(4, 5, 22, '2026-04-19 07:00:06'),
(5, 6, 22, '2026-04-19 07:09:35');

-- --------------------------------------------------------

--
-- Structure de la table `service_request`
--

CREATE TABLE `service_request` (
  `idRequest` int(11) NOT NULL,
  `idService` int(11) NOT NULL,
  `idCitoyen` int(11) NOT NULL,
  `idAgent` int(11) DEFAULT NULL,
  `description` text NOT NULL,
  `statut` enum('en_attente','acceptée','refusée','hors_délai') DEFAULT 'en_attente',
  `dateCreation` datetime DEFAULT current_timestamp(),
  `dateAssignation` datetime DEFAULT NULL,
  `dateTraitement` datetime DEFAULT NULL,
  `motifRefus` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `service_request_audit`
--

CREATE TABLE `service_request_audit` (
  `idAudit` int(11) NOT NULL,
  `idRequest` int(11) NOT NULL,
  `action` varchar(50) DEFAULT NULL,
  `idUser` int(11) DEFAULT NULL,
  `dateAction` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure de la table `utilisateur`
--

CREATE TABLE `utilisateur` (
  `idUtilisateur` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `prenom` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('citoyen','agent','admin') NOT NULL,
  `telephone` varchar(20) NOT NULL,
  `photo` varchar(255) NOT NULL,
  `idCateg` int(11) DEFAULT NULL,
  `statut` varchar(20) NOT NULL DEFAULT 'actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Déchargement des données de la table `utilisateur`
--

INSERT INTO `utilisateur` (`idUtilisateur`, `nom`, `prenom`, `email`, `mot_de_passe`, `role`, `telephone`, `photo`, `idCateg`, `statut`) VALUES
(1, 'oueslati', 'malek', 'oueslatimalek199@gmail.com', '$2y$10$cdTwT/sVnSZ/k4hDAgon6OTTJr6gOXhCNwxuhU/3vNn/iW6HGGQ9.', 'citoyen', '', '', NULL, 'actif'),
(4, 'moni', 'boukari', 'boukarimonia29@gmail.com', '$2y$10$fn1c1xC7BZzYsEcIMsl93OJa8p50ORJHojt9Ig3wkVR/IBfL/lseG', 'agent', '', '', 2, 'actif'),
(5, 'ridha', 'oueslati', 'ridha@gmail.com', '$2y$10$Sb2H6QIuQvyOjOiw1X8i8efK9tLqbHDdxvGIQePxNHtzsr9k2OP4O', 'admin', '', '', NULL, 'actif'),
(14, 'Ben Ali', 'Mohamed', 'admin1@admin.tn', '$2y$10$ITlW8wZcN9JSZQ4VJqxfuuaVtdwek8gFM3o1MFlrBbee/j8Mj6o4O', 'admin', '22000001', '', NULL, 'actif'),
(22, 'Agent', 'Test', 'amaltoumi535@gmail.com', '$2y$10$ivO/78BafeIZVD7bjFbliOHByCMhRxAz3gUdy2EoAlbFLLGOq5O2i', 'agent', '', '', 1, 'actif'),
(23, 'Toumi', 'Amal', 'amaltoumi099@gmail.com', '$2y$10$YlV5iPiWEr/dHgvLWvzpT.j0iPQUOakm8cLgaic9GTPv4Gc5fbNOa', 'agent', '', '', NULL, 'actif'),
(24, 'Toumi', 'Amal', 'amaltoumi1@gmail.com', '$2y$10$CL8HQoQRiA6n2WbV2k15oOFuu0nJewohGZUOCUggsL8eNpIE1vhZm', 'citoyen', '', '', NULL, 'actif'),
(25, 'yygtyh', 'uiciud', 'agent@yahoo.com', '$2y$10$apBqTOY38Ny4oMgdpK/y0.JOXoD79d5/HgCdXmst7SJHmcRQOXEOK', 'agent', '', '', 4, 'actif'),
(26, 'omri', 'dorra', 'dorraomri73@gmail.com', '$2y$10$TRoJWx9EY0SC4gTmutVvvO1dK9t6wqn04sSwLaOHiHC7lbkde9Ciu', 'citoyen', '', '', NULL, 'actif'),
(27, 'zramdini', 'zouhour', 'zzramdini@gmail.com', '$2y$10$QuItK2yZembDiVvuzc7W0uXakVfDdlXrirQRcgW4yDx8GuRgHm0ki', 'agent', '', '', 3, 'actif');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `administrateur`
--
ALTER TABLE `administrateur`
  ADD PRIMARY KEY (`idUtilisateur`);

--
-- Index pour la table `agentmunicipal`
--
ALTER TABLE `agentmunicipal`
  ADD PRIMARY KEY (`idUtilisateur`),
  ADD KEY `idService` (`idService`);

--
-- Index pour la table `categorie`
--
ALTER TABLE `categorie`
  ADD PRIMARY KEY (`idCateg`);

--
-- Index pour la table `citoyen`
--
ALTER TABLE `citoyen`
  ADD PRIMARY KEY (`idUtilisateur`);

--
-- Index pour la table `commentaire`
--
ALTER TABLE `commentaire`
  ADD PRIMARY KEY (`idCommentaire`),
  ADD KEY `idx_reclamation` (`idRec`),
  ADD KEY `idx_utilisateur` (`idUtilisateur`);

--
-- Index pour la table `compte`
--
ALTER TABLE `compte`
  ADD PRIMARY KEY (`idCompte`),
  ADD UNIQUE KEY `login` (`login`),
  ADD KEY `idUtilisateur` (`idUtilisateur`);

--
-- Index pour la table `conversation`
--
ALTER TABLE `conversation`
  ADD PRIMARY KEY (`idConversation`),
  ADD UNIQUE KEY `unique_conversation` (`idRec`,`idService`,`idUtilisateur1`,`idUtilisateur2`),
  ADD KEY `idService` (`idService`),
  ADD KEY `idUtilisateur1` (`idUtilisateur1`),
  ADD KEY `idUtilisateur2` (`idUtilisateur2`),
  ADD KEY `idx_request` (`idRequest`);

--
-- Index pour la table `demande_annulation_token`
--
ALTER TABLE `demande_annulation_token`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `fk_annulation_request` (`idDemande`);

--
-- Index pour la table `demande_service`
--
ALTER TABLE `demande_service`
  ADD PRIMARY KEY (`idRequest`),
  ADD KEY `idx_utilisateur` (`idUtilisateur`),
  ADD KEY `idx_service` (`idService`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `demande_service_ibfk_agent` (`idAgent`);

--
-- Index pour la table `historique_statut`
--
ALTER TABLE `historique_statut`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_historique_demande` (`idRequest`);

--
-- Index pour la table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`idMessage`),
  ADD KEY `idx_reclamation` (`idRec`),
  ADD KEY `idx_service` (`idService`),
  ADD KEY `idx_expediteur` (`idExpediteaur`),
  ADD KEY `idx_destinataire` (`idDestinataire`),
  ADD KEY `idx_non_lu` (`lu`),
  ADD KEY `idx_date` (`dateCreation`),
  ADD KEY `idx_request` (`idRequest`);

--
-- Index pour la table `municipalite`
--
ALTER TABLE `municipalite`
  ADD PRIMARY KEY (`idMunicipalite`);

--
-- Index pour la table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `photos`
--
ALTER TABLE `photos`
  ADD PRIMARY KEY (`idPrint`),
  ADD KEY `idRec` (`idRec`);

--
-- Index pour la table `reclamation`
--
ALTER TABLE `reclamation`
  ADD PRIMARY KEY (`idRec`),
  ADD KEY `idUtilisateur` (`idUtilisateur`),
  ADD KEY `idUtilisateurAssigné` (`idUtilisateurAssigne`);

--
-- Index pour la table `reclamation_categorie`
--
ALTER TABLE `reclamation_categorie`
  ADD PRIMARY KEY (`idRec`,`idCateg`),
  ADD KEY `idCateg` (`idCateg`),
  ADD KEY `idRec` (`idRec`);

--
-- Index pour la table `reclamation_token`
--
ALTER TABLE `reclamation_token`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idRec` (`idRec`);

--
-- Index pour la table `service`
--
ALTER TABLE `service`
  ADD PRIMARY KEY (`idService`),
  ADD UNIQUE KEY `nomService` (`nomService`),
  ADD KEY `idx_categorie` (`idCateg`),
  ADD KEY `idx_statut` (`statut`);

--
-- Index pour la table `servicemunicipal`
--
ALTER TABLE `servicemunicipal`
  ADD PRIMARY KEY (`idService`),
  ADD KEY `idMunicipalite` (`idMunicipalite`);

--
-- Index pour la table `service_agent`
--
ALTER TABLE `service_agent`
  ADD PRIMARY KEY (`idServiceAgent`),
  ADD UNIQUE KEY `unique_service_agent` (`idService`,`idUtilisateur`),
  ADD KEY `idUtilisateur` (`idUtilisateur`);

--
-- Index pour la table `service_request`
--
ALTER TABLE `service_request`
  ADD PRIMARY KEY (`idRequest`),
  ADD KEY `idCitoyen` (`idCitoyen`),
  ADD KEY `idx_agent_status` (`idAgent`,`statut`),
  ADD KEY `idx_service_status` (`idService`,`statut`),
  ADD KEY `idx_date_created` (`dateCreation`),
  ADD KEY `idx_date_assigned` (`dateAssignation`);

--
-- Index pour la table `service_request_audit`
--
ALTER TABLE `service_request_audit`
  ADD PRIMARY KEY (`idAudit`),
  ADD KEY `idUser` (`idUser`),
  ADD KEY `idx_request` (`idRequest`),
  ADD KEY `idx_action_date` (`action`,`dateAction`);

--
-- Index pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD PRIMARY KEY (`idUtilisateur`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idCateg` (`idCateg`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `categorie`
--
ALTER TABLE `categorie`
  MODIFY `idCateg` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `commentaire`
--
ALTER TABLE `commentaire`
  MODIFY `idCommentaire` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `compte`
--
ALTER TABLE `compte`
  MODIFY `idCompte` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `conversation`
--
ALTER TABLE `conversation`
  MODIFY `idConversation` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `demande_annulation_token`
--
ALTER TABLE `demande_annulation_token`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `demande_service`
--
ALTER TABLE `demande_service`
  MODIFY `idRequest` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `historique_statut`
--
ALTER TABLE `historique_statut`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `message`
--
ALTER TABLE `message`
  MODIFY `idMessage` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `municipalite`
--
ALTER TABLE `municipalite`
  MODIFY `idMunicipalite` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `photos`
--
ALTER TABLE `photos`
  MODIFY `idPrint` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `reclamation`
--
ALTER TABLE `reclamation`
  MODIFY `idRec` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT pour la table `reclamation_token`
--
ALTER TABLE `reclamation_token`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `service`
--
ALTER TABLE `service`
  MODIFY `idService` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT pour la table `servicemunicipal`
--
ALTER TABLE `servicemunicipal`
  MODIFY `idService` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `service_agent`
--
ALTER TABLE `service_agent`
  MODIFY `idServiceAgent` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT pour la table `service_request`
--
ALTER TABLE `service_request`
  MODIFY `idRequest` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `service_request_audit`
--
ALTER TABLE `service_request_audit`
  MODIFY `idAudit` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  MODIFY `idUtilisateur` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `administrateur`
--
ALTER TABLE `administrateur`
  ADD CONSTRAINT `administrateur_ibfk_1` FOREIGN KEY (`idUtilisateur`) REFERENCES `utilisateur` (`idUtilisateur`);

--
-- Contraintes pour la table `agentmunicipal`
--
ALTER TABLE `agentmunicipal`
  ADD CONSTRAINT `agentmunicipal_ibfk_1` FOREIGN KEY (`idUtilisateur`) REFERENCES `utilisateur` (`idUtilisateur`),
  ADD CONSTRAINT `agentmunicipal_ibfk_2` FOREIGN KEY (`idService`) REFERENCES `servicemunicipal` (`idService`);

--
-- Contraintes pour la table `citoyen`
--
ALTER TABLE `citoyen`
  ADD CONSTRAINT `citoyen_ibfk_1` FOREIGN KEY (`idUtilisateur`) REFERENCES `utilisateur` (`idUtilisateur`);

--
-- Contraintes pour la table `commentaire`
--
ALTER TABLE `commentaire`
  ADD CONSTRAINT `commentaire_ibfk_1` FOREIGN KEY (`idRec`) REFERENCES `reclamation` (`idRec`) ON DELETE CASCADE,
  ADD CONSTRAINT `commentaire_ibfk_2` FOREIGN KEY (`idUtilisateur`) REFERENCES `utilisateur` (`idUtilisateur`) ON DELETE CASCADE;

--
-- Contraintes pour la table `compte`
--
ALTER TABLE `compte`
  ADD CONSTRAINT `compte_ibfk_1` FOREIGN KEY (`idUtilisateur`) REFERENCES `utilisateur` (`idUtilisateur`);

--
-- Contraintes pour la table `conversation`
--
ALTER TABLE `conversation`
  ADD CONSTRAINT `conversation_ibfk_1` FOREIGN KEY (`idRec`) REFERENCES `reclamation` (`idRec`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversation_ibfk_2` FOREIGN KEY (`idService`) REFERENCES `service` (`idService`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversation_ibfk_3` FOREIGN KEY (`idUtilisateur1`) REFERENCES `utilisateur` (`idUtilisateur`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversation_ibfk_4` FOREIGN KEY (`idUtilisateur2`) REFERENCES `utilisateur` (`idUtilisateur`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversation_ibfk_request` FOREIGN KEY (`idRequest`) REFERENCES `demande_service` (`idRequest`) ON DELETE CASCADE;

--
-- Contraintes pour la table `demande_annulation_token`
--
ALTER TABLE `demande_annulation_token`
  ADD CONSTRAINT `fk_annulation_request` FOREIGN KEY (`idDemande`) REFERENCES `demande_service` (`idRequest`) ON DELETE CASCADE;

--
-- Contraintes pour la table `demande_service`
--
ALTER TABLE `demande_service`
  ADD CONSTRAINT `demande_service_ibfk_1` FOREIGN KEY (`idUtilisateur`) REFERENCES `utilisateur` (`idUtilisateur`) ON DELETE CASCADE,
  ADD CONSTRAINT `demande_service_ibfk_2` FOREIGN KEY (`idService`) REFERENCES `service` (`idService`) ON DELETE CASCADE,
  ADD CONSTRAINT `demande_service_ibfk_agent` FOREIGN KEY (`idAgent`) REFERENCES `utilisateur` (`idUtilisateur`) ON DELETE SET NULL;

--
-- Contraintes pour la table `historique_statut`
--
ALTER TABLE `historique_statut`
  ADD CONSTRAINT `fk_historique_demande` FOREIGN KEY (`idRequest`) REFERENCES `demande_service` (`idRequest`) ON DELETE CASCADE;

--
-- Contraintes pour la table `message`
--
ALTER TABLE `message`
  ADD CONSTRAINT `message_ibfk_1` FOREIGN KEY (`idRec`) REFERENCES `reclamation` (`idRec`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_ibfk_2` FOREIGN KEY (`idService`) REFERENCES `service` (`idService`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_ibfk_3` FOREIGN KEY (`idExpediteaur`) REFERENCES `utilisateur` (`idUtilisateur`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_ibfk_4` FOREIGN KEY (`idDestinataire`) REFERENCES `utilisateur` (`idUtilisateur`) ON DELETE CASCADE,
  ADD CONSTRAINT `message_ibfk_request` FOREIGN KEY (`idRequest`) REFERENCES `demande_service` (`idRequest`) ON DELETE CASCADE;

--
-- Contraintes pour la table `photos`
--
ALTER TABLE `photos`
  ADD CONSTRAINT `photos_ibfk_1` FOREIGN KEY (`idRec`) REFERENCES `reclamation` (`idRec`);

--
-- Contraintes pour la table `reclamation`
--
ALTER TABLE `reclamation`
  ADD CONSTRAINT `reclamation_ibfk_1` FOREIGN KEY (`idUtilisateur`) REFERENCES `utilisateur` (`idUtilisateur`),
  ADD CONSTRAINT `reclamation_ibfk_2` FOREIGN KEY (`idUtilisateurAssigne`) REFERENCES `utilisateur` (`idUtilisateur`);

--
-- Contraintes pour la table `reclamation_categorie`
--
ALTER TABLE `reclamation_categorie`
  ADD CONSTRAINT `reclamation_categorie_ibfk_1` FOREIGN KEY (`idRec`) REFERENCES `reclamation` (`idRec`),
  ADD CONSTRAINT `reclamation_categorie_ibfk_2` FOREIGN KEY (`idCateg`) REFERENCES `categorie` (`idCateg`);

--
-- Contraintes pour la table `reclamation_token`
--
ALTER TABLE `reclamation_token`
  ADD CONSTRAINT `reclamation_token_ibfk_1` FOREIGN KEY (`idRec`) REFERENCES `reclamation` (`idRec`) ON DELETE CASCADE;

--
-- Contraintes pour la table `service`
--
ALTER TABLE `service`
  ADD CONSTRAINT `service_ibfk_1` FOREIGN KEY (`idCateg`) REFERENCES `categorie` (`idCateg`) ON DELETE CASCADE;

--
-- Contraintes pour la table `servicemunicipal`
--
ALTER TABLE `servicemunicipal`
  ADD CONSTRAINT `servicemunicipal_ibfk_1` FOREIGN KEY (`idMunicipalite`) REFERENCES `municipalite` (`idMunicipalite`);

--
-- Contraintes pour la table `service_agent`
--
ALTER TABLE `service_agent`
  ADD CONSTRAINT `service_agent_ibfk_1` FOREIGN KEY (`idService`) REFERENCES `service` (`idService`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_agent_ibfk_2` FOREIGN KEY (`idUtilisateur`) REFERENCES `utilisateur` (`idUtilisateur`) ON DELETE CASCADE;

--
-- Contraintes pour la table `service_request`
--
ALTER TABLE `service_request`
  ADD CONSTRAINT `service_request_ibfk_1` FOREIGN KEY (`idService`) REFERENCES `service` (`idService`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_request_ibfk_2` FOREIGN KEY (`idCitoyen`) REFERENCES `utilisateur` (`idUtilisateur`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_request_ibfk_3` FOREIGN KEY (`idAgent`) REFERENCES `utilisateur` (`idUtilisateur`) ON DELETE SET NULL;

--
-- Contraintes pour la table `service_request_audit`
--
ALTER TABLE `service_request_audit`
  ADD CONSTRAINT `service_request_audit_ibfk_1` FOREIGN KEY (`idRequest`) REFERENCES `service_request` (`idRequest`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_request_audit_ibfk_2` FOREIGN KEY (`idUser`) REFERENCES `utilisateur` (`idUtilisateur`) ON DELETE SET NULL;

--
-- Contraintes pour la table `utilisateur`
--
ALTER TABLE `utilisateur`
  ADD CONSTRAINT `utilisateur_ibfk_1` FOREIGN KEY (`idCateg`) REFERENCES `categorie` (`idCateg`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
