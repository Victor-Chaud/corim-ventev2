-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: sql_corim_vente_
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.4

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `acces`
--

DROP TABLE IF EXISTS `acces`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acces` (
  `iduser` int NOT NULL,
  `idprojet` int NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `acces_lot`
--

DROP TABLE IF EXISTS `acces_lot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acces_lot` (
  `iduser` int NOT NULL,
  `idlot` int NOT NULL,
  UNIQUE KEY `id` (`iduser`,`idlot`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `acces_lot_backup`
--

DROP TABLE IF EXISTS `acces_lot_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acces_lot_backup` (
  `iduser` int NOT NULL,
  `idlot` int NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `agenda`
--

DROP TABLE IF EXISTS `agenda`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agenda` (
  `agenda_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `agenda_titre` varchar(250) NOT NULL,
  `agenda_description` text NOT NULL,
  `agenda_date` varchar(8) NOT NULL,
  `agenda_heure` varchar(10) NOT NULL,
  PRIMARY KEY (`agenda_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `appartements`
--

DROP TABLE IF EXISTS `appartements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appartements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `lot` varchar(20) NOT NULL,
  `date` varchar(200) NOT NULL,
  `dept` int DEFAULT NULL,
  `ville` varchar(100) NOT NULL,
  `type` varchar(20) NOT NULL,
  `categorie` varchar(20) NOT NULL,
  `prix` varchar(100) NOT NULL,
  `prix_m2` varchar(100) NOT NULL,
  `etage` varchar(50) NOT NULL DEFAULT '0',
  `RDC` varchar(50) NOT NULL DEFAULT '0',
  `jardin` varchar(50) NOT NULL DEFAULT '0',
  `terrasse` varchar(50) NOT NULL DEFAULT '0',
  `superficie` varchar(50) NOT NULL,
  `stationnement` varchar(100) NOT NULL,
  `exposition` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `statut` varchar(100) NOT NULL,
  `client` varchar(100) NOT NULL,
  `vendeur` int DEFAULT NULL,
  `numero` varchar(150) NOT NULL,
  `cellier` varchar(150) NOT NULL,
  `accession` varchar(250) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `idclient` int NOT NULL AUTO_INCREMENT,
  `date` varchar(20) NOT NULL DEFAULT 'X',
  `source` varchar(250) NOT NULL DEFAULT 'X',
  `projets` varchar(250) NOT NULL DEFAULT 'X',
  `civilite` varchar(20) NOT NULL DEFAULT 'X',
  `nom` varchar(250) NOT NULL DEFAULT 'X',
  `adresse` varchar(255) NOT NULL DEFAULT 'X',
  `tel` varchar(70) NOT NULL DEFAULT 'X',
  `email` varchar(200) NOT NULL DEFAULT 'X',
  `type_achat` varchar(200) NOT NULL DEFAULT 'X',
  `type_logement` varchar(200) NOT NULL DEFAULT 'X',
  `budget` varchar(200) NOT NULL DEFAULT 'X',
  `observation` text NOT NULL,
  `reponse` text NOT NULL,
  PRIMARY KEY (`idclient`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `d_options`
--

DROP TABLE IF EXISTS `d_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `d_options` (
  `iddoption` int NOT NULL AUTO_INCREMENT,
  `iduser` smallint NOT NULL DEFAULT '1',
  `nom` varchar(100) NOT NULL,
  `adresse` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `iddlot` int NOT NULL,
  `idprojet` int NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`iddoption`),
  UNIQUE KEY `iddlot` (`iddlot`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `d_prospects`
--

DROP TABLE IF EXISTS `d_prospects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `d_prospects` (
  `idprospect` int NOT NULL AUTO_INCREMENT,
  `nomprospect` varchar(150) NOT NULL,
  `cpprospect` varchar(6) NOT NULL,
  `villeprospect` varchar(150) NOT NULL,
  `smspromoteur` text NOT NULL,
  `idprojet` int NOT NULL,
  `iduser` int NOT NULL DEFAULT '1',
  `date` datetime NOT NULL,
  PRIMARY KEY (`idprospect`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `d_reservations`
--

DROP TABLE IF EXISTS `d_reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `d_reservations` (
  `iddresa` int NOT NULL AUTO_INCREMENT,
  `iduser` smallint NOT NULL DEFAULT '1',
  `nom` varchar(100) NOT NULL,
  `adresse` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `iddlot` int NOT NULL,
  `idprojet` int NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`iddresa`),
  UNIQUE KEY `iddlot` (`iddlot`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `docs`
--

DROP TABLE IF EXISTS `docs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `docs` (
  `iddocs` int NOT NULL AUTO_INCREMENT,
  `email` text NOT NULL,
  `url` text NOT NULL,
  `time` varchar(20) NOT NULL,
  PRIMARY KEY (`iddocs`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `emailing`
--

DROP TABLE IF EXISTS `emailing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `emailing` (
  `id` int NOT NULL AUTO_INCREMENT,
  `type` varchar(100) NOT NULL,
  `destinataire` longtext NOT NULL,
  `sujet` text NOT NULL,
  `message` longtext NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mp`
--

DROP TABLE IF EXISTS `mp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mp` (
  `id` int NOT NULL AUTO_INCREMENT,
  `idemetteur` int NOT NULL,
  `idreceveur` int NOT NULL,
  `sujet` varchar(250) NOT NULL,
  `message` text NOT NULL,
  `date` datetime NOT NULL,
  `lu` int NOT NULL DEFAULT '0',
  `cle` varchar(250) NOT NULL,
  `parent` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `news`
--

DROP TABLE IF EXISTS `news`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `news` (
  `id` int NOT NULL AUTO_INCREMENT,
  `string1` varchar(250) NOT NULL,
  `string2` varchar(250) NOT NULL,
  `string3` varchar(250) NOT NULL,
  `string4` varchar(250) NOT NULL,
  `thumb` varchar(250) NOT NULL,
  `date` int NOT NULL,
  `url` varchar(250) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `options`
--

DROP TABLE IF EXISTS `options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `options` (
  `idoption` int NOT NULL AUTO_INCREMENT,
  `iduser` int NOT NULL,
  `nom` varchar(250) NOT NULL,
  `adresse` varchar(250) NOT NULL,
  `message` text NOT NULL,
  `idlot` int NOT NULL,
  `idprojet` int NOT NULL,
  `date` datetime NOT NULL,
  `statut` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`idoption`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `privileges`
--

DROP TABLE IF EXISTS `privileges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `privileges` (
  `iduser` int NOT NULL,
  `acces` int NOT NULL,
  UNIQUE KEY `iduser` (`iduser`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `projet`
--

DROP TABLE IF EXISTS `projet`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projet` (
  `projet_id` int NOT NULL AUTO_INCREMENT,
  `projet_titre` varchar(100) NOT NULL,
  `projet_titre_key` varchar(100) NOT NULL,
  `projet_lieu` varchar(100) NOT NULL,
  `projet_image` varchar(100) NOT NULL,
  `projet_nb_appartements` int NOT NULL,
  `projet_description` text NOT NULL,
  `archive` tinyint unsigned NOT NULL,
  `dept` varchar(10) NOT NULL,
  `livraison` varchar(100) NOT NULL,
  PRIMARY KEY (`projet_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `prospects`
--

DROP TABLE IF EXISTS `prospects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prospects` (
  `idprospect` int NOT NULL AUTO_INCREMENT,
  `nomprospect` varchar(150) NOT NULL,
  `cpprospect` varchar(6) NOT NULL,
  `villeprospect` varchar(150) NOT NULL,
  `smspromoteur` text NOT NULL,
  `idprojet` int NOT NULL,
  `iduser` int NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`idprospect`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `r_prospects`
--

DROP TABLE IF EXISTS `r_prospects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `r_prospects` (
  `idprospect` int NOT NULL AUTO_INCREMENT,
  `nomprospect` varchar(150) NOT NULL,
  `cpprospect` varchar(6) NOT NULL,
  `villeprospect` varchar(150) NOT NULL,
  `smspromoteur` text NOT NULL,
  `idprojet` int NOT NULL,
  `iduser` int NOT NULL,
  `date` datetime NOT NULL,
  PRIMARY KEY (`idprospect`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reservations`
--

DROP TABLE IF EXISTS `reservations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reservations` (
  `idresa` int NOT NULL AUTO_INCREMENT,
  `iduser` int NOT NULL,
  `nom` varchar(250) NOT NULL,
  `adresse` varchar(250) NOT NULL,
  `message` text NOT NULL,
  `idlot` int NOT NULL,
  `idprojet` int NOT NULL,
  `date` datetime NOT NULL,
  `statut` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`idresa`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `session_id` varchar(127) NOT NULL,
  `last_activity` int unsigned NOT NULL,
  `data` text NOT NULL,
  PRIMARY KEY (`session_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pseudo` varchar(100) NOT NULL,
  `name` varchar(250) NOT NULL,
  `password` varchar(250) NOT NULL,
  `email` varchar(250) NOT NULL,
  `question` varchar(250) NOT NULL,
  `reponse` varchar(250) NOT NULL,
  `statut` int NOT NULL DEFAULT '1',
  `pwdshow` varchar(150) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ventes`
--

DROP TABLE IF EXISTS `ventes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ventes` (
  `idvente` int NOT NULL AUTO_INCREMENT,
  `iduser` int NOT NULL,
  `idlot` int NOT NULL,
  `date` datetime NOT NULL,
  `statut` int NOT NULL DEFAULT '1',
  `date_resa` datetime NOT NULL,
  PRIMARY KEY (`idvente`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `villes`
--

DROP TABLE IF EXISTS `villes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `villes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `dept` int NOT NULL,
  `ville` varchar(250) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
