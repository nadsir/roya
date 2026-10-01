-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: roya
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `article_brand`
--

DROP TABLE IF EXISTS `article_brand`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `article_brand` (
  `article_id` bigint(20) unsigned NOT NULL,
  `vehicle_brand_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`vehicle_brand_id`),
  KEY `article_brand_vehicle_brand_id_foreign` (`vehicle_brand_id`),
  CONSTRAINT `article_brand_article_id_foreign` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `article_brand_vehicle_brand_id_foreign` FOREIGN KEY (`vehicle_brand_id`) REFERENCES `vehicle_brands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article_brand`
--

LOCK TABLES `article_brand` WRITE;
/*!40000 ALTER TABLE `article_brand` DISABLE KEYS */;
/*!40000 ALTER TABLE `article_brand` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `article_category`
--

DROP TABLE IF EXISTS `article_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `article_category` (
  `article_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`category_id`),
  KEY `article_category_category_id_foreign` (`category_id`),
  CONSTRAINT `article_category_article_id_foreign` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `article_category_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article_category`
--

LOCK TABLES `article_category` WRITE;
/*!40000 ALTER TABLE `article_category` DISABLE KEYS */;
/*!40000 ALTER TABLE `article_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `article_product`
--

DROP TABLE IF EXISTS `article_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `article_product` (
  `article_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`product_id`),
  KEY `article_product_product_id_foreign` (`product_id`),
  CONSTRAINT `article_product_article_id_foreign` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `article_product_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article_product`
--

LOCK TABLES `article_product` WRITE;
/*!40000 ALTER TABLE `article_product` DISABLE KEYS */;
/*!40000 ALTER TABLE `article_product` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `article_vehicle`
--

DROP TABLE IF EXISTS `article_vehicle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `article_vehicle` (
  `article_id` bigint(20) unsigned NOT NULL,
  `vehicle_engine_id` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`article_id`,`vehicle_engine_id`),
  KEY `article_vehicle_vehicle_engine_id_foreign` (`vehicle_engine_id`),
  CONSTRAINT `article_vehicle_article_id_foreign` FOREIGN KEY (`article_id`) REFERENCES `articles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `article_vehicle_vehicle_engine_id_foreign` FOREIGN KEY (`vehicle_engine_id`) REFERENCES `vehicle_engines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `article_vehicle`
--

LOCK TABLES `article_vehicle` WRITE;
/*!40000 ALTER TABLE `article_vehicle` DISABLE KEYS */;
/*!40000 ALTER TABLE `article_vehicle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `articles`
--

DROP TABLE IF EXISTS `articles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `articles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `author_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext NOT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `published_at` timestamp NULL DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `canonical_url` varchar(255) DEFAULT NULL,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `articles_slug_unique` (`slug`),
  KEY `articles_status_published_at_index` (`status`,`published_at`),
  KEY `articles_author_id_index` (`author_id`),
  CONSTRAINT `articles_author_id_foreign` FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `articles`
--

LOCK TABLES `articles` WRITE;
/*!40000 ALTER TABLE `articles` DISABLE KEYS */;
/*!40000 ALTER TABLE `articles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attribute_values`
--

DROP TABLE IF EXISTS `attribute_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attribute_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `label` varchar(255) NOT NULL,
  `value` varchar(255) NOT NULL,
  `hex_color` varchar(7) DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attribute_values_attribute_id_value_unique` (`attribute_id`,`value`),
  KEY `attribute_values_attribute_id_sort_order_index` (`attribute_id`,`sort_order`),
  CONSTRAINT `attribute_values_attribute_id_foreign` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attribute_values`
--

LOCK TABLES `attribute_values` WRITE;
/*!40000 ALTER TABLE `attribute_values` DISABLE KEYS */;
INSERT INTO `attribute_values` VALUES (1,1,'مشکی','black','#000000',0,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(2,1,'سفید','white','#FFFFFF',1,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(3,1,'قرمز','red','#EF4444',2,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(4,1,'صورتی','pink','#EC4899',3,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(5,1,'کرم','cream','#F5E6D3',4,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(6,1,'قهوه‌ای','brown','#92400E',5,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(7,1,'بژ','beige','#D6C2A1',6,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(8,1,'آبی','blue','#3B82F6',7,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(9,1,'سبز','green','#22C55E',8,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(10,1,'طلایی','gold','#D4AF37',9,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(11,1,'نقره‌ای','silver','#C0C0C0',10,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(12,2,'XS','xs',NULL,0,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(13,2,'S','s',NULL,1,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(14,2,'M','m',NULL,2,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(15,2,'L','l',NULL,3,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(16,2,'XL','xl',NULL,4,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(17,2,'XXL','xxl',NULL,5,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(18,3,'نخ','cotton',NULL,0,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(19,3,'کتان','linen',NULL,1,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(20,3,'لینن','linen-fabric',NULL,2,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(21,3,'پلی‌استر','polyester',NULL,3,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(22,3,'چرم','leather',NULL,4,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(23,3,'جیر','suede',NULL,5,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(24,3,'ساتن','satin',NULL,6,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(25,3,'ابریشم','silk',NULL,7,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(26,4,'دستی','handbag',NULL,0,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(27,4,'دوشی','shoulder-bag',NULL,1,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(28,4,'کراس‌بادی','crossbody',NULL,2,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(29,4,'کلاچ','clutch',NULL,3,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(30,4,'کوله‌پشتی','backpack',NULL,4,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(31,4,'کیف کمری','waist-bag',NULL,5,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(32,5,'کفش روزمره','casual',NULL,0,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(33,5,'کفش مجلسی','formal',NULL,1,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(34,5,'صندل','sandal',NULL,2,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(35,5,'بوت','boots',NULL,3,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(36,5,'نیم‌بوت','ankle-boots',NULL,4,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(37,5,'کتانی','sneakers',NULL,5,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(38,5,'لوفر','loafers',NULL,6,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(39,6,'فلزی','metal',NULL,0,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(40,6,'استیل','steel',NULL,1,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(41,6,'استات','acetate',NULL,2,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(42,6,'پلاستیک','plastic',NULL,3,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(43,6,'تیتانیوم','titanium',NULL,4,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(44,7,'گرد','round',NULL,0,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(45,7,'مربعی','square',NULL,1,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(46,7,'مستطیلی','rectangle',NULL,2,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(47,7,'بیضی','oval',NULL,3,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(48,7,'خلبانی','aviator',NULL,4,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(49,7,'گربه‌ای','cat-eye',NULL,5,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(50,7,'پروانه‌ای','butterfly',NULL,6,'2026-09-08 05:03:08','2026-09-08 05:03:08'),(51,8,'36','36',NULL,0,'2026-09-08 05:16:15','2026-09-08 05:16:15'),(52,8,'37','37',NULL,1,'2026-09-08 05:16:15','2026-09-08 05:16:15'),(53,8,'38','38',NULL,2,'2026-09-08 05:16:15','2026-09-08 05:16:15'),(54,8,'39','39',NULL,3,'2026-09-08 05:16:15','2026-09-08 05:16:15'),(55,8,'40','40',NULL,4,'2026-09-08 05:16:15','2026-09-08 05:16:15'),(56,8,'41','41',NULL,5,'2026-09-08 05:16:15','2026-09-08 05:16:15'),(57,8,'42','42',NULL,6,'2026-09-08 05:16:15','2026-09-08 05:16:15'),(58,3,'کرپ','crepe',NULL,8,'2026-09-08 05:17:23','2026-09-08 05:17:23');
/*!40000 ALTER TABLE `attribute_values` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `attributes`
--

DROP TABLE IF EXISTS `attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `attributes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `type` enum('select','multiselect','color','number','boolean','text') NOT NULL DEFAULT 'select',
  `is_filterable` tinyint(1) NOT NULL DEFAULT 0,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `attributes_slug_unique` (`slug`),
  KEY `attributes_is_filterable_sort_order_index` (`is_filterable`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `attributes`
--

LOCK TABLES `attributes` WRITE;
/*!40000 ALTER TABLE `attributes` DISABLE KEYS */;
INSERT INTO `attributes` VALUES (1,'رنگ','color','color',1,0,1,'2026-09-08 05:01:26','2026-09-08 05:01:26'),(2,'سایز لباس','size','select',1,0,2,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(3,'جنس','material','select',1,0,3,'2026-09-08 05:01:26','2026-09-08 05:01:26'),(4,'نوع کیف','bag-type','select',1,0,4,'2026-09-08 05:01:26','2026-09-08 05:01:26'),(5,'نوع کفش','shoe-type','select',1,0,5,'2026-09-08 05:01:26','2026-09-08 05:01:26'),(6,'جنس فریم','frame-material','select',1,0,7,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(7,'شکل فریم','frame-shape','select',1,0,8,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(8,'سایز کفش','shoe-size','select',1,0,6,'2026-09-08 05:15:00','2026-09-08 05:15:00');
/*!40000 ALTER TABLE `attributes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('laravel_cache_5c785c036466adea360111aa28563bfd556b5fba','i:1;',1790670050),('laravel_cache_5c785c036466adea360111aa28563bfd556b5fba:timer','i:1790670050;',1790670050);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_slug_unique` (`slug`),
  KEY `categories_parent_id_is_active_index` (`parent_id`,`is_active`),
  CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (1,NULL,'لباس','clothing',NULL,NULL,1,0,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(2,1,'مانتو','mantos',NULL,NULL,1,0,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(3,1,'شومیز','shirts',NULL,NULL,1,1,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(4,1,'لباس مجلسی','evening-dresses',NULL,NULL,1,2,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(5,1,'لباس روزمره','casual-dresses',NULL,NULL,1,3,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(6,NULL,'کیف','bags',NULL,'https://www.bagshik.ir/storage/product/2025/01/Brr3j1K0HLSgshWPz68JuEkoUkszrUCZDscjKKkX_thumb1.jpg',1,1,'2026-09-08 05:00:12','2026-09-27 07:20:15'),(7,6,'کیف دستی','handbags',NULL,NULL,1,0,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(8,6,'کیف دوشی','shoulder-bags',NULL,NULL,1,1,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(9,6,'کیف مجلسی','evening-bags',NULL,NULL,1,2,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(10,NULL,'کفش','shoes',NULL,NULL,1,2,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(11,10,'کفش زنانه','women-shoes',NULL,NULL,1,0,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(12,10,'صندل','sandals',NULL,NULL,1,1,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(13,10,'بوت','boots',NULL,NULL,1,2,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(14,NULL,'عینک','glasses',NULL,NULL,1,3,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(15,14,'عینک طبی','optical-glasses',NULL,NULL,1,0,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(16,14,'عینک آفتابی','sunglasses',NULL,NULL,1,1,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(17,NULL,'اکسسوری','accessories',NULL,NULL,1,4,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(18,17,'زیورآلات','jewelry',NULL,NULL,1,0,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(19,17,'ساعت','watches',NULL,NULL,1,1,'2026-09-08 05:00:12','2026-09-08 05:00:12'),(20,17,'سایر','other-accessories',NULL,NULL,1,2,'2026-09-08 05:00:12','2026-09-08 05:00:12');
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `category_attributes`
--

DROP TABLE IF EXISTS `category_attributes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `category_attributes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint(20) unsigned NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_filterable` tinyint(1) NOT NULL DEFAULT 0,
  `is_variant_axis` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_attributes_category_id_attribute_id_unique` (`category_id`,`attribute_id`),
  KEY `category_attributes_attribute_id_foreign` (`attribute_id`),
  KEY `category_attributes_category_id_sort_order_index` (`category_id`,`sort_order`),
  CONSTRAINT `category_attributes_attribute_id_foreign` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `category_attributes_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `category_attributes`
--

LOCK TABLES `category_attributes` WRITE;
/*!40000 ALTER TABLE `category_attributes` DISABLE KEYS */;
INSERT INTO `category_attributes` VALUES (1,1,3,1,0,1,0,0,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(2,1,2,1,0,1,0,1,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(3,1,1,1,0,1,0,2,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(4,6,4,1,0,1,0,0,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(5,6,3,1,0,1,0,1,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(6,6,1,1,0,1,0,2,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(7,10,5,1,0,1,0,0,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(8,10,3,1,0,1,0,2,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(9,10,2,1,0,1,0,2,'2026-09-08 05:01:26','2026-09-08 05:01:26'),(10,10,1,1,0,1,0,3,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(11,14,6,1,0,1,0,0,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(12,14,7,1,0,1,0,1,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(13,14,1,1,0,1,0,2,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(14,17,3,1,0,1,0,0,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(15,17,1,1,0,1,0,1,'2026-09-08 05:01:26','2026-09-08 05:15:00'),(16,10,8,1,0,1,0,1,'2026-09-08 05:15:00','2026-09-08 05:15:00'),(17,2,3,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(18,2,2,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(19,2,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(20,3,3,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(21,3,2,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(22,3,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(23,4,3,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(24,4,2,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(25,4,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(26,5,3,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(27,5,2,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(28,5,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(29,7,4,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(30,7,3,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(31,7,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(32,8,4,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(33,8,3,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(34,8,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(35,9,4,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(36,9,3,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(37,9,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(38,11,5,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(39,11,8,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(40,11,3,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(41,11,2,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(42,11,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(43,12,5,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(44,12,8,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(45,12,3,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(46,12,2,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(47,12,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(48,13,5,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(49,13,8,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(50,13,3,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(51,13,2,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(52,13,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(53,15,6,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(54,15,7,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(55,15,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(56,16,6,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(57,16,7,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(58,16,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(59,18,3,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(60,18,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(61,19,3,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(62,19,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(63,20,3,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31'),(64,20,1,1,0,1,0,0,'2026-09-10 04:25:31','2026-09-10 04:25:31');
/*!40000 ALTER TABLE `category_attributes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comments`
--

DROP TABLE IF EXISTS `comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `commentable_type` varchar(255) NOT NULL,
  `commentable_id` bigint(20) unsigned NOT NULL,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `rating` tinyint(3) unsigned DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comments_user_id_foreign` (`user_id`),
  KEY `comments_commentable_type_commentable_id_index` (`commentable_type`,`commentable_id`),
  KEY `comments_status_index` (`status`),
  KEY `comments_parent_id_index` (`parent_id`),
  CONSTRAINT `comments_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `comments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comments`
--

LOCK TABLES `comments` WRITE;
/*!40000 ALTER TABLE `comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_09_08_080354_create_categories_table',2),(5,'2026_09_08_080539_create_attributes_table',3),(6,'2026_09_08_080620_create_category_attributes_table',4),(7,'2026_09_08_080708_create_attribute_values_table',5),(8,'2026_09_08_080756_create_products_table',6),(9,'2026_09_08_080859_create_product_categories_table',7),(10,'2026_09_08_080938_create_product_attribute_values_table',8),(11,'2026_09_08_081408_create_product_variants_table',9),(12,'2026_09_08_081443_create_variant_attribute_values_table',10),(13,'2026_09_08_081526_create_product_images_table',11),(14,'2026_09_08_082647_create_personal_access_tokens_table',12),(15,'2026_09_09_071420_add_stock_to_products_table',12),(16,'2026_09_10_000000_add_admin_fields_to_users_table',13),(17,'2026_09_12_115705_add_filter_and_variant_flags_to_category_attributes_table',13),(18,'2026_09_12_121008_backfill_category_attribute_settings_from_attributes',13),(19,'2026_09_12_130000_create_product_custom_attribute_values_table',13),(20,'2026_09_13_000001_create_vehicle_brands_table',13),(21,'2026_09_13_000002_create_vehicle_models_table',13),(22,'2026_09_13_000003_create_vehicle_generations_table',13),(23,'2026_09_13_000004_create_vehicle_trims_table',13),(24,'2026_09_13_000005_create_vehicle_engines_table',13),(25,'2026_09_13_000006_create_product_vehicle_compat_table',13),(26,'2026_09_15_000000_create_wishlist_items_table',13),(27,'2026_09_15_100000_create_orders_table',13),(28,'2026_09_15_100001_create_order_items_table',13),(29,'2026_09_15_120000_add_mobile_to_users_table',13),(30,'2026_09_15_120001_create_phone_verifications_table',13),(31,'2026_09_16_000000_add_payment_and_cancellation_to_orders_table',13),(32,'2026_09_16_100000_create_payment_attempts_table',13),(33,'2026_09_18_000000_add_is_enabled_to_category_attributes_table',13),(34,'2026_09_20_061254_create_articles_table',13),(35,'2026_09_20_061310_create_article_category_table',13),(36,'2026_09_20_061310_create_article_product_table',13),(37,'2026_09_20_061311_create_article_brand_table',13),(38,'2026_09_20_061311_create_article_vehicle_table',13),(39,'2026_09_22_000000_create_comments_table',13);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `product_variant_id` bigint(20) unsigned DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `quantity` int(10) unsigned NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `subtotal` decimal(15,2) NOT NULL,
  `attributes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attributes`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_foreign` (`order_id`),
  KEY `order_items_product_id_foreign` (`product_id`),
  KEY `order_items_product_variant_id_foreign` (`product_variant_id`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_items_product_variant_id_foreign` FOREIGN KEY (`product_variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_items`
--

LOCK TABLES `order_items` WRITE;
/*!40000 ALTER TABLE `order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `subtotal` decimal(15,2) NOT NULL,
  `discount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `shipping_cost` decimal(15,2) NOT NULL DEFAULT 0.00,
  `total` decimal(15,2) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_phone` varchar(32) NOT NULL,
  `customer_email` varchar(255) NOT NULL,
  `shipping_address` text NOT NULL,
  `shipping_postal_code` varchar(20) NOT NULL,
  `shipping_city` varchar(100) NOT NULL,
  `shipping_province` varchar(100) NOT NULL,
  `notes` text DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `payment_method` varchar(32) DEFAULT NULL,
  `payment_ref` varchar(255) DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancelled_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `orders_user_id_foreign` (`user_id`),
  KEY `orders_status_index` (`status`),
  CONSTRAINT `orders_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_attempts`
--

DROP TABLE IF EXISTS `payment_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `gateway` varchar(64) NOT NULL,
  `authority` varchar(255) DEFAULT NULL,
  `amount` bigint(20) unsigned NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `reference` varchar(255) DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_attempts_order_id_index` (`order_id`),
  KEY `payment_attempts_authority_index` (`authority`),
  CONSTRAINT `payment_attempts_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_attempts`
--

LOCK TABLES `payment_attempts` WRITE;
/*!40000 ALTER TABLE `payment_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (1,'App\\Models\\User',1,'admin-dashboard','156220893cef252272477a5f654e2d4c2f7cd649dc679be8a69063d784114f7e','[\"admin\"]','2026-09-27 07:20:16',NULL,'2026-09-27 05:55:48','2026-09-27 07:20:16'),(2,'App\\Models\\User',2,'customer-dashboard','f19544c13c343fa4d4097d7fdb9123f3786b0944d2eaccf26829f1c4fc26a9fc','[\"customer\"]','2026-09-29 04:46:32',NULL,'2026-09-29 04:46:31','2026-09-29 04:46:32'),(3,'App\\Models\\User',3,'customer-dashboard','ad61ee7d320bf5dcb98ab26cecb9829e1a23833de01ed0275a8921ccce8b32e7','[\"customer\"]','2026-09-29 04:48:51',NULL,'2026-09-29 04:48:42','2026-09-29 04:48:51'),(4,'App\\Models\\User',4,'customer-dashboard','5639c4a45ae456b08fa668270a3fdf8785d673cb25de91931f2350bd16f176d4','[\"customer\"]','2026-09-29 04:50:05',NULL,'2026-09-29 04:49:50','2026-09-29 04:50:05');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `phone_verifications`
--

DROP TABLE IF EXISTS `phone_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `phone_verifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mobile` varchar(11) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `phone_verifications_mobile_index` (`mobile`),
  KEY `phone_verifications_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `phone_verifications`
--

LOCK TABLES `phone_verifications` WRITE;
/*!40000 ALTER TABLE `phone_verifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `phone_verifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_attribute_values`
--

DROP TABLE IF EXISTS `product_attribute_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_attribute_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `attribute_value_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pav_product_attribute_value_unique` (`product_id`,`attribute_id`,`attribute_value_id`),
  KEY `product_attribute_values_attribute_id_foreign` (`attribute_id`),
  KEY `product_attribute_values_product_id_attribute_id_index` (`product_id`,`attribute_id`),
  KEY `product_attribute_values_attribute_value_id_index` (`attribute_value_id`),
  CONSTRAINT `product_attribute_values_attribute_id_foreign` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_attribute_values_attribute_value_id_foreign` FOREIGN KEY (`attribute_value_id`) REFERENCES `attribute_values` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_attribute_values_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_attribute_values`
--

LOCK TABLES `product_attribute_values` WRITE;
/*!40000 ALTER TABLE `product_attribute_values` DISABLE KEYS */;
INSERT INTO `product_attribute_values` VALUES (3,1,2,15,'2026-09-08 05:20:07','2026-09-10 04:45:23'),(5,2,1,2,'2026-09-08 05:20:07','2026-09-08 05:22:30'),(6,2,2,13,'2026-09-08 05:20:07','2026-09-08 05:22:30'),(7,2,2,14,'2026-09-08 05:20:07','2026-09-08 05:22:30'),(8,2,2,15,'2026-09-08 05:20:07','2026-09-08 05:22:30'),(9,2,3,24,'2026-09-08 05:20:07','2026-09-08 05:22:30'),(10,3,1,1,'2026-09-08 05:20:07','2026-09-08 05:22:30'),(11,3,3,22,'2026-09-08 05:20:07','2026-09-08 05:22:30'),(12,3,4,26,'2026-09-08 05:20:07','2026-09-08 05:22:30'),(13,4,1,1,'2026-09-08 05:22:30','2026-09-08 05:22:30'),(14,4,8,53,'2026-09-08 05:22:30','2026-09-08 05:22:30'),(15,4,8,54,'2026-09-08 05:22:30','2026-09-08 05:22:30'),(16,4,3,22,'2026-09-08 05:22:30','2026-09-08 05:22:30'),(17,4,5,33,'2026-09-08 05:22:30','2026-09-08 05:22:30'),(18,5,1,1,'2026-09-08 05:22:30','2026-09-10 06:03:29'),(19,5,6,41,'2026-09-08 05:22:30','2026-09-10 06:03:29'),(20,5,7,44,'2026-09-08 05:22:30','2026-09-10 06:03:29'),(21,1,1,3,'2026-09-10 04:31:01','2026-09-10 04:45:23'),(22,1,3,25,'2026-09-10 04:36:04','2026-09-10 04:45:23'),(23,1,1,4,'2026-09-10 04:38:06','2026-09-10 04:45:23'),(24,1,1,2,'2026-09-10 04:42:54','2026-09-10 04:45:23'),(25,6,1,1,'2026-09-10 06:23:24','2026-09-10 06:23:24'),(26,6,3,19,'2026-09-10 06:23:24','2026-09-10 06:23:24');
/*!40000 ALTER TABLE `product_attribute_values` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_categories`
--

DROP TABLE IF EXISTS `product_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_categories_product_id_category_id_unique` (`product_id`,`category_id`),
  KEY `product_categories_category_id_index` (`category_id`),
  CONSTRAINT `product_categories_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_categories_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_categories`
--

LOCK TABLES `product_categories` WRITE;
/*!40000 ALTER TABLE `product_categories` DISABLE KEYS */;
INSERT INTO `product_categories` VALUES (1,1,2,'2026-09-08 05:11:33','2026-09-08 05:11:33'),(2,2,3,'2026-09-08 05:20:07','2026-09-08 05:20:07'),(3,3,7,'2026-09-08 05:20:07','2026-09-08 05:20:07'),(4,4,11,'2026-09-08 05:20:07','2026-09-08 05:20:07'),(5,5,16,'2026-09-08 05:22:30','2026-09-08 05:22:30'),(6,6,17,'2026-09-10 06:23:24','2026-09-10 06:23:24'),(7,6,18,'2026-09-10 06:23:24','2026-09-10 06:23:24');
/*!40000 ALTER TABLE `product_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_custom_attribute_values`
--

DROP TABLE IF EXISTS `product_custom_attribute_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_custom_attribute_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `attribute_id` bigint(20) unsigned NOT NULL,
  `value_type` enum('number','boolean','text') NOT NULL,
  `value_number` decimal(15,4) DEFAULT NULL,
  `value_boolean` tinyint(1) DEFAULT NULL,
  `value_text` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_custom_attribute_values_product_id_attribute_id_unique` (`product_id`,`attribute_id`),
  KEY `product_custom_attribute_values_attribute_id_value_number_index` (`attribute_id`,`value_number`),
  KEY `product_custom_attribute_values_attribute_id_value_boolean_index` (`attribute_id`,`value_boolean`),
  CONSTRAINT `product_custom_attribute_values_attribute_id_foreign` FOREIGN KEY (`attribute_id`) REFERENCES `attributes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_custom_attribute_values_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_custom_attribute_values`
--

LOCK TABLES `product_custom_attribute_values` WRITE;
/*!40000 ALTER TABLE `product_custom_attribute_values` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_custom_attribute_values` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_images`
--

DROP TABLE IF EXISTS `product_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `variant_id` bigint(20) unsigned DEFAULT NULL,
  `path` varchar(255) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_images_product_id_sort_order_index` (`product_id`,`sort_order`),
  KEY `product_images_variant_id_sort_order_index` (`variant_id`,`sort_order`),
  KEY `product_images_product_id_is_primary_index` (`product_id`,`is_primary`),
  CONSTRAINT `product_images_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_images_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_images`
--

LOCK TABLES `product_images` WRITE;
/*!40000 ALTER TABLE `product_images` DISABLE KEYS */;
INSERT INTO `product_images` VALUES (1,1,NULL,'products/manto/classic-black-manto-1.jpg','مانتو کلاسیک مشکی',1,1,'2026-09-08 05:28:07','2026-09-08 05:28:07'),(2,1,NULL,'products/manto/classic-black-manto-2.jpg','نمای پشت مانتو کلاسیک مشکی',0,2,'2026-09-08 05:28:07','2026-09-08 05:28:07'),(3,2,NULL,'products/shirt/white-satin-shirt-1.jpg','شومیز ساتن سفید',1,1,'2026-09-08 05:28:07','2026-09-08 05:28:07'),(4,2,NULL,'products/shirt/white-satin-shirt-2.jpg','نمای شومیز ساتن سفید',0,2,'2026-09-08 05:28:07','2026-09-08 05:28:07'),(5,3,NULL,'products/bag/classic-leather-handbag-1.jpg','کیف دستی چرم کلاسیک',1,1,'2026-09-08 05:28:07','2026-09-08 05:28:07'),(6,3,NULL,'products/bag/classic-leather-handbag-2.jpg','نمای کیف دستی چرم کلاسیک',0,2,'2026-09-08 05:28:07','2026-09-08 05:28:07'),(7,4,NULL,'products/shoe/classic-womens-shoes-1.jpg','کفش زنانه کلاسیک',1,1,'2026-09-08 05:28:07','2026-09-08 05:28:07'),(8,4,NULL,'products/shoe/classic-womens-shoes-2.jpg','نمای کفش زنانه کلاسیک',0,2,'2026-09-08 05:28:07','2026-09-08 05:28:07'),(11,1,NULL,'products/w7gyEggTcI1AXp75wV0yLETS8ZSkDm2reSh5Znve.webp','مانتو کلاسیک مشکی',0,3,'2026-09-10 05:29:34','2026-09-10 05:29:34'),(12,5,NULL,'products/NZSdYUXFZznzhQMgz2OzvALdPpg9Qt7xGnzKvOxa.webp','عینک آفتابی کلاسیک مشکی',1,1,'2026-09-10 06:02:52','2026-09-10 06:03:27'),(13,6,NULL,'products/dfZelpsguQ34CcwukhhZALGpeaXuzbxVaFNKNjRC.webp','کلاه',1,1,'2026-09-10 06:23:25','2026-09-10 06:23:25');
/*!40000 ALTER TABLE `product_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_variants`
--

DROP TABLE IF EXISTS `product_variants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_variants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `price` decimal(15,2) DEFAULT NULL,
  `compare_at_price` decimal(15,2) DEFAULT NULL,
  `stock` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `combination_key` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pv_product_combination_unique` (`product_id`,`combination_key`),
  UNIQUE KEY `product_variants_sku_unique` (`sku`),
  KEY `product_variants_product_id_is_active_index` (`product_id`,`is_active`),
  CONSTRAINT `product_variants_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_variants`
--

LOCK TABLES `product_variants` WRITE;
/*!40000 ALTER TABLE `product_variants` DISABLE KEYS */;
INSERT INTO `product_variants` VALUES (3,2,'ROYA-SHIRT-001-13',NULL,NULL,4,1,'13','2026-09-08 05:25:36','2026-09-08 05:25:36'),(4,2,'ROYA-SHIRT-001-14',NULL,NULL,6,1,'14','2026-09-08 05:25:36','2026-09-08 05:25:36'),(5,2,'ROYA-SHIRT-001-15',NULL,NULL,3,1,'15','2026-09-08 05:25:36','2026-09-08 05:25:36'),(6,4,'ROYA-SHOE-001-53',NULL,NULL,4,1,'53','2026-09-08 05:25:36','2026-09-08 05:25:36'),(7,4,'ROYA-SHOE-001-54',NULL,NULL,2,1,'54','2026-09-08 05:25:36','2026-09-08 05:25:36'),(26,1,'ROYA-MANTO-001-14',NULL,NULL,5,1,'14','2026-09-10 04:45:23','2026-09-10 04:45:23'),(27,1,'ROYA-MANTO-001-15',NULL,NULL,3,1,'15','2026-09-10 04:45:23','2026-09-10 04:45:23');
/*!40000 ALTER TABLE `product_variants` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `product_vehicle_compat`
--

DROP TABLE IF EXISTS `product_vehicle_compat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `product_vehicle_compat` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` bigint(20) unsigned NOT NULL,
  `vehicle_engine_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pvc_product_engine_unique` (`product_id`,`vehicle_engine_id`),
  KEY `product_vehicle_compat_vehicle_engine_id_index` (`vehicle_engine_id`),
  CONSTRAINT `product_vehicle_compat_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `product_vehicle_compat_vehicle_engine_id_foreign` FOREIGN KEY (`vehicle_engine_id`) REFERENCES `vehicle_engines` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `product_vehicle_compat`
--

LOCK TABLES `product_vehicle_compat` WRITE;
/*!40000 ALTER TABLE `product_vehicle_compat` DISABLE KEYS */;
/*!40000 ALTER TABLE `product_vehicle_compat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `short_description` text DEFAULT NULL,
  `description` longtext DEFAULT NULL,
  `price` decimal(15,2) NOT NULL,
  `compare_at_price` decimal(15,2) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `stock` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_slug_unique` (`slug`),
  UNIQUE KEY `products_sku_unique` (`sku`),
  KEY `products_is_active_published_at_index` (`is_active`,`published_at`),
  KEY `products_is_featured_sort_order_index` (`is_featured`,`sort_order`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'مانتو کلاسیک مشکی','classic-black-manto','ROYA-MANTO-001','مانتوی کلاسیک زنانه با طراحی مینیمال','مانتوی زنانه مناسب استفاده روزمره و رسمی.',313000.00,3290000.00,1,1,'2026-09-08 05:22:30',1,'2026-09-08 05:11:33','2026-09-10 04:45:23',0),(2,'شومیز ساتن سفید','white-satin-shirt','ROYA-SHIRT-001','شومیز ساتن زنانه با طراحی ظریف','شومیز ساتن مناسب استایل روزمره و مهمانی.',1490000.00,1690000.00,1,1,'2026-09-08 05:22:30',2,'2026-09-08 05:20:07','2026-09-08 05:22:30',0),(3,'کیف دستی چرم کلاسیک','classic-leather-handbag','ROYA-BAG-001','کیف دستی زنانه از چرم مصنوعی باکیفیت','کیف دستی مناسب استفاده روزمره و استایل رسمی.',2190000.00,2490000.00,1,1,'2026-09-08 05:22:30',3,'2026-09-08 05:20:07','2026-09-08 05:22:30',0),(4,'کفش زنانه کلاسیک','classic-womens-shoes','ROYA-SHOE-001','کفش زنانه کلاسیک مناسب استفاده روزمره','کفش زنانه با طراحی ساده و شیک.',2390000.00,2690000.00,1,0,'2026-09-08 05:22:30',4,'2026-09-08 05:20:07','2026-09-08 05:22:30',0),(5,'عینک آفتابی کلاسیک مشکی','classic-black-sunglasses','ROYA-GLASS-001','عینک آفتابی زنانه با فریم کلاسیک','عینک آفتابی زنانه مناسب استفاده روزمره.',1890000.00,2190000.00,1,1,'2026-09-08 05:22:30',5,'2026-09-08 05:22:30','2026-09-08 05:22:30',0),(6,'کلاه','کلاه','کلاه',NULL,NULL,1000.00,1000.00,1,0,NULL,0,'2026-09-10 06:23:24','2026-09-10 06:23:24',1);
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('c7GDSi4D2UnHzspIWnkuBLblVZkmCQowN57Y2tvw',NULL,'127.0.0.1','Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1','YTozOntzOjY6Il90b2tlbiI7czo0MDoickI2VlgzSmJKVFpVcGtUa3NCTnZXaDZ2a0NNWk5JSmk5Y0VzcDloViI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6Mjg6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9jL2JhZ3MiO3M6NToicm91dGUiO047fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=',1790839718);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` varchar(255) NOT NULL DEFAULT 'customer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `mobile` varchar(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_mobile_unique` (`mobile`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'nader','nader.davarmanesh62@gmail.com','admin',1,NULL,'$2y$12$M3.Kapw2l/jns3V3VDORpuT49IiSQFIBcnioR04p7qePMnOF5TBb2',NULL,NULL,NULL,NULL),(2,'کاربر آزمایشی گالری','qa.gallery.1790669785210@example.com','customer',1,NULL,'$2y$12$XuYfsXIAgi7tQ2Ar88y6kergUyr656WXldoVCSE0QAUX9Efq59lfe',NULL,'2026-09-29 04:46:31','2026-09-29 04:46:31','09123456789'),(3,'کاربر آزمایشی گالری','qa.gallery.1790669917194@example.com','customer',1,NULL,'$2y$12$xAEZoW94XUjNIEMLSie4z.s5ISZYqXJHY0/rwCIR32yAtifkAuXHS',NULL,'2026-09-29 04:48:42','2026-09-29 04:48:42','09162122429'),(4,'کاربر آزمایشی گالری','qa.checkout.1790669985904@example.com','customer',1,NULL,'$2y$12$4z.IKaLoB.o5EkM.OMZ8kuXBFLg7etkS3gfGdiZhiS4bA5LMNHvKG',NULL,'2026-09-29 04:49:50','2026-09-29 04:49:50','09168464721');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `variant_attribute_values`
--

DROP TABLE IF EXISTS `variant_attribute_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `variant_attribute_values` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `variant_id` bigint(20) unsigned NOT NULL,
  `attribute_value_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vav_variant_attribute_value_unique` (`variant_id`,`attribute_value_id`),
  KEY `variant_attribute_values_attribute_value_id_index` (`attribute_value_id`),
  CONSTRAINT `variant_attribute_values_attribute_value_id_foreign` FOREIGN KEY (`attribute_value_id`) REFERENCES `attribute_values` (`id`) ON DELETE CASCADE,
  CONSTRAINT `variant_attribute_values_variant_id_foreign` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `variant_attribute_values`
--

LOCK TABLES `variant_attribute_values` WRITE;
/*!40000 ALTER TABLE `variant_attribute_values` DISABLE KEYS */;
INSERT INTO `variant_attribute_values` VALUES (3,3,13,'2026-09-08 05:25:36','2026-09-08 05:25:36'),(4,4,14,'2026-09-08 05:25:36','2026-09-08 05:25:36'),(5,5,15,'2026-09-08 05:25:36','2026-09-08 05:25:36'),(6,6,53,'2026-09-08 05:25:36','2026-09-08 05:25:36'),(7,7,54,'2026-09-08 05:25:36','2026-09-08 05:25:36'),(26,26,14,'2026-09-10 04:45:23','2026-09-10 04:45:23'),(27,27,15,'2026-09-10 04:45:23','2026-09-10 04:45:23');
/*!40000 ALTER TABLE `variant_attribute_values` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_brands`
--

DROP TABLE IF EXISTS `vehicle_brands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_brands` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicle_brands_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_brands`
--

LOCK TABLES `vehicle_brands` WRITE;
/*!40000 ALTER TABLE `vehicle_brands` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle_brands` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_engines`
--

DROP TABLE IF EXISTS `vehicle_engines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_engines` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_trim_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `displacement` decimal(4,1) DEFAULT NULL,
  `fuel_type` varchar(255) DEFAULT NULL,
  `horsepower` smallint(5) unsigned DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicle_engines_slug_unique` (`slug`),
  KEY `vehicle_engines_vehicle_trim_id_is_active_index` (`vehicle_trim_id`,`is_active`),
  CONSTRAINT `vehicle_engines_vehicle_trim_id_foreign` FOREIGN KEY (`vehicle_trim_id`) REFERENCES `vehicle_trims` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_engines`
--

LOCK TABLES `vehicle_engines` WRITE;
/*!40000 ALTER TABLE `vehicle_engines` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle_engines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_generations`
--

DROP TABLE IF EXISTS `vehicle_generations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_generations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_model_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `year_start` smallint(6) DEFAULT NULL,
  `year_end` smallint(6) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicle_generations_slug_unique` (`slug`),
  KEY `vehicle_generations_vehicle_model_id_is_active_index` (`vehicle_model_id`,`is_active`),
  CONSTRAINT `vehicle_generations_vehicle_model_id_foreign` FOREIGN KEY (`vehicle_model_id`) REFERENCES `vehicle_models` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_generations`
--

LOCK TABLES `vehicle_generations` WRITE;
/*!40000 ALTER TABLE `vehicle_generations` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle_generations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_models`
--

DROP TABLE IF EXISTS `vehicle_models`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_models` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_brand_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicle_models_slug_unique` (`slug`),
  KEY `vehicle_models_vehicle_brand_id_is_active_index` (`vehicle_brand_id`,`is_active`),
  CONSTRAINT `vehicle_models_vehicle_brand_id_foreign` FOREIGN KEY (`vehicle_brand_id`) REFERENCES `vehicle_brands` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_models`
--

LOCK TABLES `vehicle_models` WRITE;
/*!40000 ALTER TABLE `vehicle_models` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle_models` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicle_trims`
--

DROP TABLE IF EXISTS `vehicle_trims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicle_trims` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `vehicle_generation_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicle_trims_slug_unique` (`slug`),
  KEY `vehicle_trims_vehicle_generation_id_is_active_index` (`vehicle_generation_id`,`is_active`),
  CONSTRAINT `vehicle_trims_vehicle_generation_id_foreign` FOREIGN KEY (`vehicle_generation_id`) REFERENCES `vehicle_generations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicle_trims`
--

LOCK TABLES `vehicle_trims` WRITE;
/*!40000 ALTER TABLE `vehicle_trims` DISABLE KEYS */;
/*!40000 ALTER TABLE `vehicle_trims` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wishlist_items`
--

DROP TABLE IF EXISTS `wishlist_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wishlist_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `wishlist_items_user_id_product_id_unique` (`user_id`,`product_id`),
  KEY `wishlist_items_product_id_index` (`product_id`),
  CONSTRAINT `wishlist_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `wishlist_items_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wishlist_items`
--

LOCK TABLES `wishlist_items` WRITE;
/*!40000 ALTER TABLE `wishlist_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `wishlist_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'roya'
--

--
-- Dumping routines for database 'roya'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-01 12:03:52
