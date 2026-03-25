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
-- Table structure for table `bookshop_books`
--

DROP TABLE IF EXISTS `bookshop_books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookshop_books` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sku` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(180) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category_id` bigint unsigned DEFAULT NULL,
  `category_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `genre_id` bigint unsigned DEFAULT NULL,
  `genre_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `collection_id` bigint unsigned DEFAULT NULL,
  `collection_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `author_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `publisher_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `isbn` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barcode` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `edition_label` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `volume_number` smallint unsigned DEFAULT NULL,
  `volume_label` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `publication_year` smallint unsigned DEFAULT NULL,
  `page_count` int unsigned DEFAULT NULL,
  `language` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `cover_image_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_image_mime_type` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cover_image_size_bytes` bigint unsigned DEFAULT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `sale_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `stock_quantity` int NOT NULL DEFAULT '0',
  `stock_minimum` int unsigned NOT NULL DEFAULT '0',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `location_label` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `isbn` (`isbn`),
  KEY `idx_bookshop_books_title` (`title`),
  KEY `idx_bookshop_books_author` (`author_name`),
  KEY `idx_bookshop_books_category` (`category_name`),
  KEY `idx_bookshop_books_status` (`status`),
  KEY `idx_bookshop_books_stock` (`stock_quantity`),
  KEY `idx_bookshop_books_category_id` (`category_id`),
  KEY `idx_bookshop_books_genre_id` (`genre_id`),
  KEY `idx_bookshop_books_genre` (`genre_name`),
  KEY `idx_bookshop_books_barcode` (`barcode`),
  KEY `idx_bookshop_books_collection_id` (`collection_id`),
  KEY `idx_bookshop_books_collection` (`collection_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookshop_books`
--

LOCK TABLES `bookshop_books` WRITE;
/*!40000 ALTER TABLE `bookshop_books` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookshop_books` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookshop_categories`
--

DROP TABLE IF EXISTS `bookshop_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookshop_categories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_bookshop_categories_name` (`name`),
  KEY `idx_bookshop_categories_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookshop_categories`
--

LOCK TABLES `bookshop_categories` WRITE;
/*!40000 ALTER TABLE `bookshop_categories` DISABLE KEYS */;
INSERT INTO `bookshop_categories` VALUES (1,'estudo-intermediario','Estudo (Intermediário)','Categoria de livros utilizada em cursos para a formação espiritual intermediária do cediano.',1,'2026-03-21 22:20:01','2026-03-22 14:59:42'),(2,'estudo-avancado','Estudo (Avançado)','Categoria de livros utilizada em cursos para a formação espiritual avancada do cediano.',1,'2026-03-22 11:30:39','2026-03-24 17:05:43'),(3,'estudo-jedi','Estudo (Jedi)','Categoria de livros utilizada em cursos para a formação espiritual nível jedi do cediano.',1,'2026-03-22 11:32:01','2026-03-24 17:05:54'),(4,'estudo-basico','Espiritualismo  e Filosofia Espiritual','Categoria de livros utilizada em cursos para a formação espiritual básica do cediano.',1,'2026-03-22 11:38:57','2026-03-24 17:05:59'),(5,'estudo-mediunico','Estudos Mediúnicos','Mediunidade é a capacidade que algumas pessoas têm de: \r\n👉 perceber ou se comunicar com espíritos;\r\n👉 Estudos mediúnicos são o estudo e desenvolvimento da mediunidade',1,'2026-03-22 15:18:14','2026-03-24 17:06:04'),(6,'espiritismo-experimental','Espiritismo Experimental','É o estudo baseado na observação direta dos fenômenos espirituais, especialmente os fenômenos mediúnicos.',1,'2026-03-22 17:29:53','2026-03-24 17:06:09'),(7,'evangelho-no-lar','Evangelho no Lar','Titulos de apoio para rotina de estudo e prece em familia.',1,'2026-03-22 20:18:32','2026-03-24 17:06:15'),(8,'formacao-de-trabalhadores','Formação de Trabalhadores','Materiais de apoio para equipes e servico voluntario.',1,'2026-03-22 20:18:32','2026-03-24 17:06:21'),(9,'infancia-e-juventude','Infância e Juventude','Livros voltados para publico infantil e juvenil.',1,'2026-03-22 20:18:33','2026-03-24 17:06:27'),(10,'familia-e-relacionamentos','Família e Relacionamentos','Leituras sobre convivencia, educacao e cuidado no lar.',1,'2026-03-22 20:18:33','2026-03-24 17:06:32'),(11,'mediunidade-pratica','Mediunidade Prática','Obras para estudo serio da mediunidade e da disciplina mediunica.',1,'2026-03-22 20:18:33','2026-03-24 17:06:38'),(12,'pesquisa-e-referencia','Pesquisa e Referência','Material de consulta, apoio a pesquisa e referencia doutrinaria.',1,'2026-03-22 20:18:33','2026-03-24 17:06:43'),(13,'acolhimento-e-servico','Acolhimento e Serviço','Livros para acolhimento fraterno, servico e consolacao.',1,'2026-03-22 20:18:33','2026-03-24 17:06:50'),(14,'evangelizacao-infantil','Evangelização infantil',NULL,1,'2026-03-23 12:36:24','2026-03-24 17:06:55'),(15,'estudos-policiais','Estudo polociais',NULL,1,'2026-03-23 18:53:32','2026-03-24 17:07:00');
/*!40000 ALTER TABLE `bookshop_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookshop_collections`
--

DROP TABLE IF EXISTS `bookshop_collections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookshop_collections` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_bookshop_collections_name` (`name`),
  KEY `idx_bookshop_collections_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookshop_collections`
--

LOCK TABLES `bookshop_collections` WRITE;
/*!40000 ALTER TABLE `bookshop_collections` DISABLE KEYS */;
INSERT INTO `bookshop_collections` VALUES (1,'estudos-da-codificacao','Estudos da Codificação','Coleção dedicada ao estudo progressivo dos princípios fundamentais da Doutrina Espírita.',1,'2026-03-22 20:56:06','2026-03-22 20:56:06'),(2,'saude','Saúde','Saúde física e mental.',1,'2026-03-22 20:56:06','2026-03-24 12:22:35'),(3,'evangelho-no-lar-e-no-coracao','Evangelho no Lar e no Coração','Volumes voltados à vivência do Evangelho no lar, na família e na convivência diária.',1,'2026-03-22 20:56:06','2026-03-22 20:56:06'),(4,'cadernos-de-reforma-intima','Cadernos de Reforma Íntima','Coleção de apoio à autoeducação moral, vigilância e crescimento espiritual.',1,'2026-03-22 20:56:06','2026-03-22 20:56:06'),(5,'obras-basicas','Obras Básicas','Série com leituras sobre imortalidade, reencarnação e continuidade da vida.',1,'2026-03-22 20:56:06','2026-03-23 22:43:20'),(6,'caminhos-da-caridade','Caminhos da Caridade','Coleção voltada ao serviço, acolhimento fraterno e prática do bem.',1,'2026-03-22 20:56:06','2026-03-22 20:56:06'),(7,'trilhas-do-trabalhador-espirita','Trilhas do Trabalhador Espírita','Materiais em sequência para formação e sustentação do serviço voluntário.',1,'2026-03-22 20:56:06','2026-03-22 20:56:06'),(8,'consolacao-e-esperanca','Consolação e Esperança','Coleção de leitura edificante, consolo espiritual e fortalecimento da fé raciocinada.',1,'2026-03-22 20:56:06','2026-03-22 20:56:06'),(9,'vida-no-mundo','Vida no Mundo Espiritual','Relatos de André Luiz',1,'2026-03-23 18:47:21','2026-03-23 18:48:22'),(10,'motiva-o','Motivação e Espiritualidade',NULL,1,'2026-03-24 11:06:32','2026-03-24 11:06:32'),(11,'alma-dos-animais','Alma dos Animais',NULL,1,'2026-03-24 11:38:24','2026-03-24 11:38:24'),(12,'magnetismo','Magnetismo',NULL,1,'2026-03-24 11:41:43','2026-03-24 11:41:43');
/*!40000 ALTER TABLE `bookshop_collections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookshop_genres`
--

DROP TABLE IF EXISTS `bookshop_genres`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookshop_genres` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `idx_bookshop_genres_name` (`name`),
  KEY `idx_bookshop_genres_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookshop_genres`
--

LOCK TABLES `bookshop_genres` WRITE;
/*!40000 ALTER TABLE `bookshop_genres` DISABLE KEYS */;
INSERT INTO `bookshop_genres` VALUES (1,'filosofia-espiritual','Filosofia espiritual',NULL,1,'2026-03-22 15:05:08','2026-03-22 15:23:54'),(2,'psicologia','Psicologia',NULL,1,'2026-03-22 15:05:28','2026-03-22 15:05:28'),(3,'romance','Romance',NULL,1,'2026-03-22 15:05:56','2026-03-22 15:05:56'),(4,'ficcao','Ficção',NULL,1,'2026-03-22 15:06:21','2026-03-22 15:06:21'),(5,'psicografia','Psicografia',NULL,1,'2026-03-22 15:06:48','2026-03-22 15:06:48'),(6,'economia','Economia',NULL,1,'2026-03-22 15:07:08','2026-03-22 15:07:08'),(7,'politica','Política',NULL,1,'2026-03-22 15:07:24','2026-03-22 15:07:24'),(8,'drama','Drama',NULL,1,'2026-03-22 15:07:36','2026-03-22 15:07:36'),(9,'poesia','Poesia',NULL,1,'2026-03-22 15:07:48','2026-03-22 15:07:48'),(10,'literatura-espiritualista','Literatura espiritualista',NULL,1,'2026-03-22 15:16:58','2026-03-22 16:25:27'),(11,'doutrinario','Doutrinário','Obras de estudo e fundamentacao doutrinaria.',1,'2026-03-22 20:18:33','2026-03-22 20:18:33'),(12,'biografia','Biografia','Relatos de vida, memoria e trajetorias inspiradoras.',1,'2026-03-22 20:18:33','2026-03-22 20:18:33'),(13,'infantojuvenil','Infantojuvenil','Livros de linguagem acessivel para criancas e jovens.',1,'2026-03-22 20:18:33','2026-03-22 20:18:33'),(14,'filosofico-cientifico-religioso','Filosófico, Cientifico e Religioso','Textos breves, observacoes do cotidiano e reflexoes.',1,'2026-03-22 20:18:33','2026-03-24 17:03:46'),(15,'novela','Novela','Narrativas curtas para leitura individual ou em grupo.',1,'2026-03-22 20:18:33','2026-03-23 18:52:07'),(16,'mensagens','Mensagens','Coletaneas de paginas inspirativas e consoladoras.',1,'2026-03-22 20:18:33','2026-03-22 20:18:33'),(17,'referencia','Referência','Guias, consultas e material de referencia para estudos.',1,'2026-03-22 20:18:33','2026-03-22 20:18:33'),(18,'estudo-tematico','Estudo Temático','Obras organizadas por tema, assunto ou modulo de estudo.',1,'2026-03-22 20:18:33','2026-03-22 20:18:33'),(19,'cronica','Crônica',NULL,1,'2026-03-24 17:04:09','2026-03-24 17:07:39');
/*!40000 ALTER TABLE `bookshop_genres` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookshop_sale_items`
--

DROP TABLE IF EXISTS `bookshop_sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookshop_sale_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` bigint unsigned NOT NULL,
  `book_id` bigint unsigned NOT NULL,
  `stock_lot_id` bigint unsigned DEFAULT NULL,
  `stock_lot_code_snapshot` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sku_snapshot` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_cost_snapshot` decimal(10,2) DEFAULT NULL,
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `line_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bookshop_sale_items_sale` (`sale_id`),
  KEY `idx_bookshop_sale_items_book` (`book_id`),
  KEY `idx_bookshop_sale_items_lot` (`stock_lot_id`),
  CONSTRAINT `fk_bookshop_sale_items_book` FOREIGN KEY (`book_id`) REFERENCES `bookshop_books` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bookshop_sale_items_sale` FOREIGN KEY (`sale_id`) REFERENCES `bookshop_sales` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookshop_sale_items`
--

LOCK TABLES `bookshop_sale_items` WRITE;
/*!40000 ALTER TABLE `bookshop_sale_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookshop_sale_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookshop_sales`
--

DROP TABLE IF EXISTS `bookshop_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookshop_sales` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sale_code` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sold_at` datetime NOT NULL,
  `customer_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_email` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_cpf` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_count` int unsigned NOT NULL DEFAULT '0',
  `subtotal_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `received_amount` decimal(10,2) DEFAULT NULL,
  `change_amount` decimal(10,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'completed',
  `created_by_member_id` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `cancelled_by_member_id` bigint unsigned DEFAULT NULL,
  `cancelled_by_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_code` (`sale_code`),
  KEY `idx_bookshop_sales_sold_at` (`sold_at`),
  KEY `idx_bookshop_sales_status` (`status`),
  KEY `idx_bookshop_sales_payment` (`payment_method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookshop_sales`
--

LOCK TABLES `bookshop_sales` WRITE;
/*!40000 ALTER TABLE `bookshop_sales` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookshop_sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookshop_stock_lots`
--

DROP TABLE IF EXISTS `bookshop_stock_lots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookshop_stock_lots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `book_id` bigint unsigned NOT NULL,
  `source_movement_id` bigint unsigned DEFAULT NULL,
  `quantity_received` int unsigned NOT NULL DEFAULT '1',
  `quantity_available` int unsigned NOT NULL DEFAULT '1',
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `unit_sale_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `occurred_at` datetime NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bookshop_stock_lots_book` (`book_id`),
  KEY `idx_bookshop_stock_lots_movement` (`source_movement_id`),
  KEY `idx_bookshop_stock_lots_available` (`quantity_available`),
  KEY `idx_bookshop_stock_lots_occurred_at` (`occurred_at`),
  CONSTRAINT `fk_bookshop_stock_lots_book` FOREIGN KEY (`book_id`) REFERENCES `bookshop_books` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookshop_stock_lots`
--

LOCK TABLES `bookshop_stock_lots` WRITE;
/*!40000 ALTER TABLE `bookshop_stock_lots` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookshop_stock_lots` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookshop_stock_movements`
--

DROP TABLE IF EXISTS `bookshop_stock_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bookshop_stock_movements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `book_id` bigint unsigned NOT NULL,
  `stock_lot_id` bigint unsigned DEFAULT NULL,
  `stock_lot_code_snapshot` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sku_snapshot` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `author_snapshot` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `movement_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int unsigned NOT NULL DEFAULT '1',
  `stock_delta` int NOT NULL DEFAULT '0',
  `stock_before` int NOT NULL DEFAULT '0',
  `stock_after` int NOT NULL DEFAULT '0',
  `unit_cost` decimal(10,2) DEFAULT NULL,
  `unit_sale_price` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `total_sale_value` decimal(10,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `occurred_at` datetime NOT NULL,
  `created_by_member_id` bigint unsigned DEFAULT NULL,
  `created_by_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bookshop_stock_movements_book` (`book_id`),
  KEY `idx_bookshop_stock_movements_type` (`movement_type`),
  KEY `idx_bookshop_stock_movements_occurred_at` (`occurred_at`),
  KEY `idx_bookshop_stock_movements_created_by` (`created_by_name`),
  KEY `idx_bookshop_stock_movements_lot` (`stock_lot_id`),
  CONSTRAINT `fk_bookshop_stock_movements_book` FOREIGN KEY (`book_id`) REFERENCES `bookshop_books` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookshop_stock_movements`
--

LOCK TABLES `bookshop_stock_movements` WRITE;
/*!40000 ALTER TABLE `bookshop_stock_movements` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookshop_stock_movements` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-25 13:53:06
