-- MySQL dump 10.13  Distrib 8.0.45, for Linux (x86_64)
--
-- Host: localhost    Database: cede
-- ------------------------------------------------------
-- Server version	8.0.45-0ubuntu0.24.04.1

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
-- Current Database: `cede`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `cede` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `cede`;

--
-- Table structure for table `activity_categories`
--

DROP TABLE IF EXISTS `activity_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audience_default` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_categories`
--

LOCK TABLES `activity_categories` WRITE;
/*!40000 ALTER TABLE `activity_categories` DISABLE KEYS */;
INSERT INTO `activity_categories` VALUES (1,'estudo','Estudo','#2563eb',NULL,'Adultos',1,'2026-03-15 01:51:58','2026-03-15 01:51:58'),(2,'palestra','Palestra','#d97706',NULL,'Público geral',1,'2026-03-15 01:51:58','2026-03-15 01:51:58'),(3,'juventude','Juventude','#16a34a',NULL,'Jovens',1,'2026-03-15 01:51:58','2026-03-15 01:51:58'),(13,'campanha','Campanha','#dc2626',NULL,'Adultos',1,'2026-03-15 11:19:16','2026-03-15 12:28:19'),(14,'curso','Curso','#7c3aed',NULL,'Adultos e jovens',1,'2026-03-15 11:19:16','2026-03-15 11:19:16'),(15,'simposio','Simpósio','#0ea5e9',NULL,'Público geral',1,'2026-03-15 11:19:16','2026-03-15 11:19:16'),(16,'seminario','Seminário','#9333ea',NULL,'Público geral',1,'2026-03-15 11:19:16','2026-03-15 11:19:16'),(17,'estagio','Estágio','#f59e0b',NULL,'Trabalhadores e colaboradores',1,'2026-03-15 11:19:16','2026-03-15 11:19:16'),(18,'outros','Outros','#64748b',NULL,'Público geral',1,'2026-03-15 11:19:16','2026-03-15 11:19:16'),(40,'corrida','Corrida CEDE',NULL,NULL,'Livre',1,'2026-03-15 12:29:44','2026-03-15 12:29:44');
/*!40000 ALTER TABLE `activity_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `agenda_events`
--

DROP TABLE IF EXISTS `agenda_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agenda_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint unsigned NOT NULL,
  `slug` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `theme` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_name` varchar(180) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mode` enum('presencial','online','hibrido') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'presencial',
  `meeting_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audience` enum('Jovens','Adultos','Crianças','Público interno','Livre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Livre',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `status` enum('draft','published','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published',
  `is_featured` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_agenda_events_starts_at` (`starts_at`),
  KEY `idx_agenda_events_status` (`status`),
  KEY `idx_agenda_events_category` (`category_id`),
  CONSTRAINT `fk_agenda_events_category` FOREIGN KEY (`category_id`) REFERENCES `activity_categories` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agenda_events`
--

LOCK TABLES `agenda_events` WRITE;
/*!40000 ALTER TABLE `agenda_events` DISABLE KEYS */;
INSERT INTO `agenda_events` VALUES (1,1,'estudo-do-evangelho','Estudo do Evangelho','Reflexões sobre os ensinamentos morais de Jesus à luz do Espiritismo.','A prática do Evangelho no cotidiano familiar','CEDE - Sala de Estudos','Rua Frejó nº 44 - Nova Parnamirim, Parnamirim/RN','presencial','https://meet.exemplo.org/juventude-cede-20260328','Adultos','Chegue 15 minutos antes para acolhimento inicial.','2026-03-16 20:00:00','2026-03-16 21:30:00','published',1,'2026-03-15 02:15:52','2026-03-15 11:07:12'),(2,2,'palestra-publica-quarta-2026-03-18','Palestra Pública','Exposição doutrinária seguida de passes magnéticos para os participantes.','Esperança e renovação espiritual','CEDE - Auditório Principal','Rua Exemplo, 123 - Natal/RN','presencial',NULL,'Livre','Aberta a visitantes. Atendimento fraterno após a palestra.','2026-03-18 19:30:00','2026-03-18 21:00:00','published',1,'2026-03-15 02:15:52','2026-03-15 11:37:12'),(3,3,'juventude-espirita-sabado-2026-03-21','Juventude Espírita','Encontro de estudo, diálogo e dinâmicas para jovens.','Autoconhecimento e projeto de vida','CEDE - Espaço Jovem','Rua Exemplo, 123 - Natal/RN','hibrido','https://meet.exemplo.org/juventude-cede','Público interno','Responsáveis são bem-vindos no acolhimento inicial.','2026-03-21 16:00:00','2026-03-21 17:30:00','published',0,'2026-03-15 02:15:52','2026-03-15 11:40:54'),(4,1,'estudo-do-evangelho-segunda-2026-03-23','Estudo do Evangelho','Estudo dialogado com leitura comentada e participação do grupo.','Caridade e transformação íntima','CEDE - Sala de Estudos','Rua Exemplo, 123 - Natal/RN','presencial',NULL,'Adultos','Leve seu caderno para anotações e dúvidas.','2026-03-23 20:00:00','2026-03-23 21:30:00','published',0,'2026-03-15 03:52:47','2026-03-15 11:19:16'),(5,2,'palestra-publica-quarta-2026-03-25','Palestra Pública','Momento de estudo doutrinário e acolhimento fraterno.','Fé raciocinada e vida prática','CEDE - Auditório Principal','Rua Exemplo, 123 - Natal/RN','presencial',NULL,'Livre','Haverá atendimento fraterno após a atividade.','2026-03-25 19:30:00','2026-03-25 21:00:00','published',0,'2026-03-15 03:52:47','2026-03-15 11:37:12'),(6,3,'juventude-espirita-sabado-2026-03-28','Juventude Espírita','Vivência em grupo com estudo e dinâmica de integração.','Amizade, propósito e serviço','CEDE - Espaço Jovem','Rua Exemplo, 123 - Natal/RN','hibrido','https://meet.exemplo.org/juventude-cede-20260328','Crianças','Encontro com atividade prática colaborativa.','2026-03-28 16:00:00','2026-03-28 17:30:00','published',1,'2026-03-15 03:52:47','2026-03-15 11:40:54'),(7,1,'estudo-do-evangelho-segunda-2026-03-30','Estudo do Evangelho','Roda de leitura, reflexão e aplicação prática dos conteúdos.','Perdão e reconciliação','CEDE - Sala de Estudos','Rua Exemplo, 123 - Natal/RN','presencial',NULL,'Adultos','Recepção a partir das 19h45.','2026-03-30 20:00:00','2026-03-30 21:30:00','published',0,'2026-03-15 03:52:47','2026-03-15 11:19:16'),(8,2,'palestra-publica-quarta-2026-04-01','Palestra Pública','Palestra aberta com tema doutrinário e momento de prece.','Consolo e esperança','CEDE - Auditório Principal','Rua Exemplo, 123 - Natal/RN','presencial',NULL,'Livre','Chegue cedo para melhor acomodação.','2026-04-01 19:30:00','2026-04-01 21:00:00','published',0,'2026-03-15 03:52:47','2026-03-15 11:37:12'),(9,3,'juventude-espirita-sabado-2026-04-04','Juventude Espírita','Encontro de estudo e convivência para jovens participantes.','Autocuidado e espiritualidade','CEDE - Espaço Jovem','Rua Exemplo, 123 - Natal/RN','online','https://meet.exemplo.org/juventude-cede-20260404','Jovens','Link disponível no grupo institucional.','2026-04-04 16:00:00','2026-04-04 17:30:00','published',0,'2026-03-15 03:52:47','2026-03-15 11:37:12'),(10,1,'estudo-do-evangelho-segunda-2026-04-06','Estudo do Evangelho','Continuidade do ciclo semanal de estudo e debate fraterno.','Família e educação moral','CEDE - Sala de Estudos','Rua Exemplo, 123 - Natal/RN','presencial',NULL,'Adultos','Atividade com abertura para perguntas ao final.','2026-04-06 20:00:00','2026-04-06 21:30:00','published',0,'2026-03-15 03:52:47','2026-03-15 11:19:16'),(14,1,'curso','Curso Bíblico','Um curso para entender a bíblia nos tempos atuais','A prática do Evangelho no cotidiano familiar','CEDE - Sala de Estudos','Rua Frejó nº 44 - Nova Parnamirim, Parnamirim/RN','online',NULL,'Adultos','Um estudo estruturado da Bíblia com objetivo de compreender melhor seus ensinamentos, história, contexto e aplicação espiritual.','2026-03-15 01:41:00','2026-03-21 07:53:00','published',0,'2026-03-15 04:41:41','2026-03-15 12:08:17'),(15,1,'estudo-do-evangelho-segunda-2026-03-16','Estudo do Evangelho','Reflexões sobre os ensinamentos morais de Jesus à luz do Espiritismo.','A prática do Evangelho no cotidiano familiar','CEDE - Sala de Estudos','Rua Exemplo, 123 - Natal/RN','presencial',NULL,'Adultos','Chegue 15 minutos antes para acolhimento inicial.','2026-03-16 20:00:00','2026-03-16 21:30:00','published',1,'2026-03-15 11:19:16','2026-03-15 11:19:16');
/*!40000 ALTER TABLE `agenda_events` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_users`
--

DROP TABLE IF EXISTS `member_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `member_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `full_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role_id` bigint unsigned DEFAULT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `phone_mobile` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_landline` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_completed` tinyint(1) NOT NULL DEFAULT '0',
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `birth_date` date DEFAULT NULL,
  `birth_place` varchar(140) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profile_photo_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_users`
--

LOCK TABLES `member_users` WRITE;
/*!40000 ALTER TABLE `member_users` DISABLE KEYS */;
INSERT INTO `member_users` VALUES (1,'LÚCIO FLÁVIO LEMOS','luciolemos.j5@gmail.com','$2y$12$s0e42yHEz3kmQUto9/bt3OXCCcfM90eNtXGYK8599nmuNpcWMQXiy',4,'active','(84) 99636-0721','(84) 99636-0721',1,'2026-03-15 15:01:40','2026-03-15 14:35:52','2026-03-15 17:59:41','1968-04-22','Natal/RN','assets/img/member-photos/member_20260315161110_966795b5.png'),(5,'MARCELO ALVES DE SOUZA','legiaodainfantariadenatal@gmail.com','$2y$12$c9b4Iy0AG3anoFEP5ZH8D.DAFnKCcHoY6mER6ehR8KE1pedO0RN6K',1,'active','(84) 99636-0721','(84) 9913-0692',1,'2026-03-15 17:03:50','2026-03-15 17:03:16','2026-03-15 17:04:50','1968-04-22','Messias Targino/RN','assets/img/member-photos/member_20260315170450_5f6124ee.png');
/*!40000 ALTER TABLE `member_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_key` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_key` (`role_key`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'member','Membro','Acesso à área de membro e recursos básicos.','2026-03-15 14:35:52','2026-03-15 14:35:52'),(2,'operator','Operador','Operação de funcionalidades internas específicas.','2026-03-15 14:35:52','2026-03-15 14:35:52'),(3,'manager','Gerente','Coordenação de conteúdos e fluxos internos.','2026-03-15 14:35:52','2026-03-15 14:35:52'),(4,'admin','Administrador','Gestão completa de usuários e permissões.','2026-03-15 14:35:52','2026-03-15 14:35:52');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-15 19:35:56
