-- MySQL dump 10.13  Distrib 5.1.58, for portbld-freebsd5.4 (i386)
--
-- Host: localhost    Database: tictacwhoa
-- ------------------------------------------------------
-- Server version	5.1.50-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `game`
--

DROP TABLE IF EXISTS `game`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `game` (
  `id` int(15) NOT NULL AUTO_INCREMENT,
  `player1_id` int(15) NOT NULL,
  `player1_score` varchar(250) NOT NULL,
  `player2_id` int(15) NOT NULL,
  `player2_score` varchar(250) NOT NULL DEFAULT '',
  `player_up` int(15) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `state` varchar(30) NOT NULL DEFAULT 'in progress',
  `winner` int(15) NOT NULL DEFAULT '0',
  `draw` tinyint(1) NOT NULL DEFAULT '0',
  `game` text,
  `last_move` varchar(25) NOT NULL,
  `last_moved` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `player1_id` (`player1_id`),
  KEY `player2_id` (`player2_id`),
  KEY `player_up` (`player_up`),
  KEY `winner` (`winner`),
  KEY `last_moved` (`last_moved`),
  KEY `state` (`state`)
) ENGINE=MyISAM AUTO_INCREMENT=80 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `invite`
--

DROP TABLE IF EXISTS `invite`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invite` (
  `id` int(25) NOT NULL AUTO_INCREMENT,
  `player1_id` int(25) NOT NULL,
  `player2_id` int(25) NOT NULL,
  `sent` tinyint(1) NOT NULL DEFAULT '0',
  `accepted` tinyint(1) NOT NULL DEFAULT '0',
  `declined` tinyint(1) NOT NULL DEFAULT '0',
  `seen` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime DEFAULT NULL,
  `last_modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `player1_id` (`player1_id`),
  KEY `player2_id` (`player2_id`),
  KEY `sent` (`sent`),
  KEY `accepted` (`accepted`)
) ENGINE=MyISAM AUTO_INCREMENT=59 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `move`
--

DROP TABLE IF EXISTS `move`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `move` (
  `id` int(25) NOT NULL AUTO_INCREMENT,
  `game_id` int(15) NOT NULL,
  `player` varchar(50) NOT NULL,
  `player_id` int(15) NOT NULL,
  `for_player_id` int(15) NOT NULL,
  `move` varchar(20) NOT NULL DEFAULT '',
  `mark` varchar(5) NOT NULL DEFAULT '',
  `moved` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created` datetime NOT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `game_id` (`game_id`),
  KEY `player_id` (`player_id`),
  KEY `for_player_id` (`for_player_id`),
  KEY `seen` (`seen`)
) ENGINE=MyISAM AUTO_INCREMENT=1656 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player`
--

DROP TABLE IF EXISTS `player`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `player` (
  `id` int(15) NOT NULL AUTO_INCREMENT,
  `player` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `fb_id` varchar(100) NOT NULL,
  `photo` varchar(255) NOT NULL,
  `created` datetime DEFAULT NULL,
  `last_seen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `plays` int(15) NOT NULL DEFAULT '0',
  `losses` int(15) NOT NULL DEFAULT '0',
  `ties` int(15) NOT NULL DEFAULT '0',
  `wins` int(15) NOT NULL DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `bot` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fb_id` (`fb_id`),
  KEY `last_seen` (`last_seen`),
  KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=22 DEFAULT CHARSET=utf8;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'tictacwhoa'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2017-02-10 11:33:19
