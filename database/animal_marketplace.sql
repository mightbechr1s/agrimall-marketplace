-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: animal_marketplace
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
-- Current Database: `animal_marketplace`
--

CREATE DATABASE /*!32312 IF NOT EXISTS*/ `animal_marketplace` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;

USE `animal_marketplace`;

--
-- Table structure for table `admin_audit_log`
--

DROP TABLE IF EXISTS `admin_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` bigint(20) unsigned NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` bigint(20) unsigned DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_admin` (`admin_id`),
  KEY `idx_audit_entity` (`entity_type`,`entity_id`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_audit_log`
--

LOCK TABLES `admin_audit_log` WRITE;
/*!40000 ALTER TABLE `admin_audit_log` DISABLE KEYS */;
INSERT INTO `admin_audit_log` VALUES (1,1,'farmer_approved','farmer_profile',73,NULL,'{\"farm_name\":\"Sundalong Farm\",\"decision\":\"approved\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/152.0.0.0 Safari/537.36','2026-09-05 06:49:12');
/*!40000 ALTER TABLE `admin_audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_order_holds`
--

DROP TABLE IF EXISTS `admin_order_holds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_order_holds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `reason` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `released_at` timestamp NULL DEFAULT NULL,
  `released_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_hold_order` (`order_id`),
  KEY `idx_hold_active` (`is_active`),
  KEY `fk_hold_admin` (`admin_id`),
  KEY `fk_hold_released_by` (`released_by`),
  CONSTRAINT `fk_hold_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_hold_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hold_released_by` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_order_holds`
--

LOCK TABLES `admin_order_holds` WRITE;
/*!40000 ALTER TABLE `admin_order_holds` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_order_holds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `buyer_profiles`
--

DROP TABLE IF EXISTS `buyer_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `buyer_profiles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `barangay` varchar(100) DEFAULT NULL,
  `municipality_city` varchar(100) DEFAULT NULL,
  `province` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `preferred_pickup` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_buyer_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `buyer_profiles`
--

LOCK TABLES `buyer_profiles` WRITE;
/*!40000 ALTER TABLE `buyer_profiles` DISABLE KEYS */;
INSERT INTO `buyer_profiles` VALUES (825,'Marites Mercado','09175551234',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-09-06 10:48:58','2026-09-06 10:48:58'),(826,'Karlo Dizon','09163334444',NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-09-06 10:48:58','2026-09-06 10:48:58'),(827,'Christian Reynald Canto',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-09-07 05:38:37','2026-09-07 05:38:37'),(828,'Test Buyer',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-09-09 05:08:12','2026-09-09 05:08:12');
/*!40000 ALTER TABLE `buyer_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `farmer_profiles`
--

DROP TABLE IF EXISTS `farmer_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `farmer_profiles` (
  `user_id` bigint(20) unsigned NOT NULL,
  `farm_name` varchar(150) NOT NULL,
  `farm_description` text DEFAULT NULL,
  `contact_person` varchar(100) NOT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `address` text NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `municipality_city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `region` varchar(100) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `valid_id_type` enum('national_id','drivers_license','passport','voters_id','postal_id','senior_citizen_id','pwd_id') NOT NULL,
  `valid_id_number` varchar(50) NOT NULL,
  `valid_id_image` varchar(500) DEFAULT NULL,
  `verification_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` bigint(20) unsigned DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  KEY `idx_farmer_verification` (`verification_status`),
  KEY `fk_farmer_verified_by` (`verified_by`),
  CONSTRAINT `fk_farmer_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_farmer_verified_by` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `farmer_profiles`
--

LOCK TABLES `farmer_profiles` WRITE;
/*!40000 ALTER TABLE `farmer_profiles` DISABLE KEYS */;
INSERT INTO `farmer_profiles` VALUES (73,'Sundalong Farm','Organic vegetables and free-range chicken from Laguna.','Juan Sundalong','+63 917 555 1234','sundalong.farmer@example.com','Brgy. San Isidro, Purok 3','San Isidro','Calamba','Laguna','CALABARZON',NULL,NULL,'national_id','1234-5678-9012',NULL,'approved','2026-09-05 06:49:12',1,NULL,'2026-09-05 06:48:35','2026-09-05 06:49:12'),(823,'Dela Gracia Organic Farm','Small family farm in Lucban, Quezon growing traditional leafy vegetables without synthetic pesticides.','Rosa Dela Gracia','09171234567','delagracia@animal.local','Barangay San Roque, Lucban, Quezon','San Roque','Lucban','Quezon','CALABARZON (Region IV-A)',NULL,NULL,'national_id','NAID-1001-2021',NULL,'approved','2026-09-06 10:48:58',NULL,NULL,'2026-09-06 10:48:58','2026-09-06 10:48:58'),(824,'Encarnacion Grains','Rice and vegetable farm in the Guimba, Nueva Ecija plain.','Efren Encarnacion','09189876543','encarnacion@animal.local','Purok 3, San Isidro, Guimba, Nueva Ecija','San Isidro','Guimba','Nueva Ecija','Central Luzon (Region III)',NULL,NULL,'national_id','NAID-2002-2019',NULL,'approved','2026-09-06 10:48:58',NULL,NULL,'2026-09-06 10:48:58','2026-09-06 10:48:58');
/*!40000 ALTER TABLE `farmer_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `listing_images`
--

DROP TABLE IF EXISTS `listing_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `listing_images` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `listing_id` bigint(20) unsigned NOT NULL,
  `image_path` varchar(500) NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_image_listing` (`listing_id`),
  KEY `idx_image_primary` (`listing_id`,`is_primary`),
  CONSTRAINT `fk_image_listing` FOREIGN KEY (`listing_id`) REFERENCES `produce_listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `listing_images`
--

LOCK TABLES `listing_images` WRITE;
/*!40000 ALTER TABLE `listing_images` DISABLE KEYS */;
/*!40000 ALTER TABLE `listing_images` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 1,
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email_ip` (`email`,`ip_address`),
  KEY `idx_locked_until` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `order_status_history`
--

DROP TABLE IF EXISTS `order_status_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `order_status_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint(20) unsigned NOT NULL,
  `from_status` enum('pending','delivery_fee_review','confirmed','preparing','ready_for_pickup','out_for_delivery','completed','rejected','cancelled') DEFAULT NULL,
  `to_status` enum('pending','delivery_fee_review','confirmed','preparing','ready_for_pickup','out_for_delivery','completed','rejected','cancelled') NOT NULL,
  `changed_by` bigint(20) unsigned NOT NULL,
  `changed_by_role` enum('buyer','farmer','admin') NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_status_order` (`order_id`),
  KEY `idx_status_created` (`created_at`),
  KEY `fk_status_changed_by` (`changed_by`),
  CONSTRAINT `fk_status_changed_by` FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_status_order` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=493 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `order_status_history`
--

LOCK TABLES `order_status_history` WRITE;
/*!40000 ALTER TABLE `order_status_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `order_status_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(30) NOT NULL,
  `buyer_id` bigint(20) unsigned NOT NULL,
  `farmer_id` bigint(20) unsigned NOT NULL,
  `listing_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(12,4) NOT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `delivery_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `delivery_method` enum('pickup','farmer_delivery') NOT NULL DEFAULT 'pickup',
  `delivery_address` text DEFAULT NULL,
  `preferred_pickup_date` date DEFAULT NULL,
  `preferred_pickup_time` time DEFAULT NULL,
  `buyer_note` text DEFAULT NULL,
  `farmer_note` text DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `status` enum('pending','delivery_fee_review','confirmed','preparing','ready_for_pickup','out_for_delivery','completed','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `cancelled_by_role` enum('buyer','farmer','admin') DEFAULT NULL,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `preparing_at` timestamp NULL DEFAULT NULL,
  `ready_at` timestamp NULL DEFAULT NULL,
  `out_for_delivery_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_number` (`order_number`),
  KEY `idx_order_buyer` (`buyer_id`),
  KEY `idx_order_farmer` (`farmer_id`),
  KEY `idx_order_listing` (`listing_id`),
  KEY `idx_order_status` (`status`),
  KEY `idx_order_created` (`created_at`),
  CONSTRAINT `fk_order_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `buyer_profiles` (`user_id`),
  CONSTRAINT `fk_order_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmer_profiles` (`user_id`),
  CONSTRAINT `fk_order_listing` FOREIGN KEY (`listing_id`) REFERENCES `produce_listings` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=244 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (229,'ORD-3B6CF51E',825,823,277,3.0000,45.00,0.00,'pickup',NULL,NULL,NULL,NULL,NULL,NULL,'completed',NULL,NULL,NULL,'2026-09-06 04:48:58',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-06 10:48:58','2026-09-06 10:48:58'),(230,'ORD-B4DD4DA3',826,824,280,2.0000,2450.00,0.00,'farmer_delivery','Purok 5, Camiling, Tarlac',NULL,NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-06 10:48:58','2026-09-06 10:48:58'),(231,'ORD-15822934',825,824,281,5.0000,60.00,20.00,'farmer_delivery','123 Mabini St, San Fernando, Pampanga',NULL,NULL,NULL,NULL,NULL,'confirmed',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-06 10:48:58','2026-09-06 10:48:58'),(232,'ORD-A56BD619',825,823,283,3.0000,45.00,0.00,'pickup',NULL,NULL,NULL,NULL,NULL,NULL,'completed',NULL,NULL,NULL,'2026-09-06 20:26:42',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-07 02:26:42','2026-09-07 02:26:42'),(233,'ORD-AB4D6EFD',826,824,286,2.0000,2450.00,0.00,'farmer_delivery','Purok 5, Camiling, Tarlac',NULL,NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-07 02:26:42','2026-09-07 02:26:42'),(234,'ORD-89648A73',825,824,287,5.0000,60.00,20.00,'farmer_delivery','123 Mabini St, San Fernando, Pampanga',NULL,NULL,NULL,NULL,NULL,'confirmed',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-07 02:26:42','2026-09-07 02:26:42'),(235,'ORD-E241D34D',825,823,289,3.0000,45.00,0.00,'pickup',NULL,NULL,NULL,NULL,NULL,NULL,'completed',NULL,NULL,NULL,'2026-09-08 22:56:42',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-09 04:56:42','2026-09-09 04:56:42'),(236,'ORD-E52BFF21',826,824,292,2.0000,2450.00,0.00,'farmer_delivery','Purok 5, Camiling, Tarlac',NULL,NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-09 04:56:42','2026-09-09 04:56:42'),(237,'ORD-2573C009',825,824,293,5.0000,60.00,20.00,'farmer_delivery','123 Mabini St, San Fernando, Pampanga',NULL,NULL,NULL,NULL,NULL,'confirmed',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-09 04:56:42','2026-09-09 04:56:42'),(238,'ORD-B43FE1CE',825,823,295,3.0000,45.00,0.00,'pickup',NULL,NULL,NULL,NULL,NULL,NULL,'completed',NULL,NULL,NULL,'2026-09-09 21:07:34',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-10 05:07:34','2026-09-10 05:07:34'),(239,'ORD-B135B9D7',826,824,298,2.0000,2450.00,0.00,'farmer_delivery','Purok 5, Camiling, Tarlac',NULL,NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-10 05:07:34','2026-09-10 05:07:34'),(240,'ORD-8712810F',825,824,299,5.0000,60.00,20.00,'farmer_delivery','123 Mabini St, San Fernando, Pampanga',NULL,NULL,NULL,NULL,NULL,'confirmed',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-10 05:07:34','2026-09-10 05:07:34'),(241,'ORD-A76F080B',825,823,301,3.0000,45.00,0.00,'pickup',NULL,NULL,NULL,NULL,NULL,NULL,'completed',NULL,NULL,NULL,'2026-09-10 18:11:22',NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-11 02:11:22','2026-09-11 02:11:22'),(242,'ORD-7F6EB5AC',826,824,304,2.0000,2450.00,0.00,'farmer_delivery','Purok 5, Camiling, Tarlac',NULL,NULL,NULL,NULL,NULL,'pending',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-11 02:11:22','2026-09-11 02:11:22'),(243,'ORD-4B69C6A3',825,824,305,5.0000,60.00,20.00,'farmer_delivery','123 Mabini St, San Fernando, Pampanga',NULL,NULL,NULL,NULL,NULL,'confirmed',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-09-11 02:11:22','2026-09-11 02:11:22');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pwreset_token` (`token`),
  KEY `idx_pwreset_user` (`user_id`),
  KEY `idx_pwreset_expires` (`expires_at`),
  CONSTRAINT `fk_pwreset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `produce_categories`
--

DROP TABLE IF EXISTS `produce_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `produce_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_category_slug` (`slug`),
  UNIQUE KEY `uk_category_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produce_categories`
--

LOCK TABLES `produce_categories` WRITE;
/*!40000 ALTER TABLE `produce_categories` DISABLE KEYS */;
INSERT INTO `produce_categories` VALUES (1,'Vegetables','vegetables','Fresh vegetables and root crops',1,1,'2026-09-05 05:24:16','2026-09-05 05:24:16'),(2,'Leafy Greens','leafy-greens','Leafy vegetables and herbs',2,1,'2026-09-05 05:24:16','2026-09-05 05:24:16'),(3,'Fruits','fruits','Fresh fruits',3,1,'2026-09-05 05:24:16','2026-09-05 05:24:16'),(4,'Root Crops','root-crops','Root vegetables and tubers',4,1,'2026-09-05 05:24:16','2026-09-05 05:24:16'),(5,'Herbs & Spices','herbs-spices','Culinary herbs and spices',5,1,'2026-09-05 05:24:16','2026-09-05 05:24:16'),(6,'Grains & Legumes','grains-legumes','Rice, beans, corn, and other grains',6,1,'2026-09-05 05:24:16','2026-09-05 05:24:16');
/*!40000 ALTER TABLE `produce_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `produce_listings`
--

DROP TABLE IF EXISTS `produce_listings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `produce_listings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `farmer_id` bigint(20) unsigned NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `unit_id` bigint(20) unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price_per_unit` decimal(12,2) NOT NULL,
  `available_quantity` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `minimum_order_qty` decimal(12,4) NOT NULL DEFAULT 0.0100,
  `maximum_order_qty` decimal(12,4) DEFAULT NULL,
  `harvest_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_organic` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','active','paused','sold_out','archived') NOT NULL DEFAULT 'draft',
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `published_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_listing_farmer` (`farmer_id`),
  KEY `idx_listing_category` (`category_id`),
  KEY `idx_listing_status` (`status`),
  KEY `idx_listing_published` (`published_at`),
  KEY `fk_listing_unit` (`unit_id`),
  FULLTEXT KEY `ft_listing_search` (`name`,`description`),
  CONSTRAINT `fk_listing_category` FOREIGN KEY (`category_id`) REFERENCES `produce_categories` (`id`),
  CONSTRAINT `fk_listing_farmer` FOREIGN KEY (`farmer_id`) REFERENCES `farmer_profiles` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_listing_unit` FOREIGN KEY (`unit_id`) REFERENCES `quantity_units` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=307 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `produce_listings`
--

LOCK TABLES `produce_listings` WRITE;
/*!40000 ALTER TABLE `produce_listings` DISABLE KEYS */;
INSERT INTO `produce_listings` VALUES (277,823,1,4,'Sitao (String Beans)','Fresh-picked string beans, harvested the same morning. Best for ginisa and adobo.',45.00,120.0000,1.0000,NULL,'2026-09-06','2026-09-11',1,'active',NULL,'2026-09-06 10:48:58','2026-09-06 10:48:58','2026-09-06 10:48:58'),(278,823,2,4,'Kangkong (Water Spinach)','Crisp kangkong bundles, great for sinigang and adobong kangkong.',25.00,200.0000,1.0000,NULL,'2026-09-06','2026-09-09',1,'active',NULL,'2026-09-06 10:48:58','2026-09-06 10:48:58','2026-09-06 10:48:58'),(279,823,3,1,'Calamansi','Juicy calamansi grown in the Lucban highlands.',80.00,60.0000,0.5000,NULL,'2026-09-06','2026-09-20',1,'active',NULL,'2026-09-06 10:48:58','2026-09-06 10:48:58','2026-09-06 10:48:58'),(280,824,6,5,'Sack of Rice (50 kg)','Dinorado regular milled rice, one sack per order, delivered from the mill.',2450.00,20.0000,1.0000,NULL,'2026-08-30',NULL,0,'active',NULL,'2026-09-06 10:48:58','2026-09-06 10:48:58','2026-09-06 10:48:58'),(281,824,1,1,'Eggplant','Long purple eggplant, uniform size, good for tortang talong.',60.00,150.0000,1.0000,NULL,'2026-09-06','2026-09-13',0,'active',NULL,'2026-09-06 10:48:58','2026-09-06 10:48:58','2026-09-06 10:48:58'),(282,824,4,1,'Sweet Potato (Ube Santana)','Purple-fleshed sweet potato from Nueva Ecija, ideal for roasting and halaya.',40.00,300.0000,1.0000,NULL,NULL,'2026-10-06',0,'active',NULL,'2026-09-06 10:48:58','2026-09-06 10:48:58','2026-09-06 10:48:58'),(283,823,1,4,'Sitao (String Beans)','Fresh-picked string beans, harvested the same morning. Best for ginisa and adobo.',45.00,120.0000,1.0000,NULL,'2026-09-07','2026-09-12',1,'active',NULL,'2026-09-07 02:26:42','2026-09-07 02:26:42','2026-09-07 02:26:42'),(284,823,2,4,'Kangkong (Water Spinach)','Crisp kangkong bundles, great for sinigang and adobong kangkong.',25.00,200.0000,1.0000,NULL,'2026-09-07','2026-09-10',1,'active',NULL,'2026-09-07 02:26:42','2026-09-07 02:26:42','2026-09-07 02:26:42'),(285,823,3,1,'Calamansi','Juicy calamansi grown in the Lucban highlands.',80.00,60.0000,0.5000,NULL,'2026-09-07','2026-09-21',1,'active',NULL,'2026-09-07 02:26:42','2026-09-07 02:26:42','2026-09-07 02:26:42'),(286,824,6,5,'Sack of Rice (50 kg)','Dinorado regular milled rice, one sack per order, delivered from the mill.',2450.00,20.0000,1.0000,NULL,'2026-08-31',NULL,0,'active',NULL,'2026-09-07 02:26:42','2026-09-07 02:26:42','2026-09-07 02:26:42'),(287,824,1,1,'Eggplant','Long purple eggplant, uniform size, good for tortang talong.',60.00,150.0000,1.0000,NULL,'2026-09-07','2026-09-14',0,'active',NULL,'2026-09-07 02:26:42','2026-09-07 02:26:42','2026-09-07 02:26:42'),(288,824,4,1,'Sweet Potato (Ube Santana)','Purple-fleshed sweet potato from Nueva Ecija, ideal for roasting and halaya.',40.00,300.0000,1.0000,NULL,NULL,'2026-10-07',0,'active',NULL,'2026-09-07 02:26:42','2026-09-07 02:26:42','2026-09-07 02:26:42'),(289,823,1,4,'Sitao (String Beans)','Fresh-picked string beans, harvested the same morning. Best for ginisa and adobo.',45.00,120.0000,1.0000,NULL,'2026-09-09','2026-09-14',1,'active',NULL,'2026-09-09 04:56:42','2026-09-09 04:56:42','2026-09-09 04:56:42'),(290,823,2,4,'Kangkong (Water Spinach)','Crisp kangkong bundles, great for sinigang and adobong kangkong.',25.00,200.0000,1.0000,NULL,'2026-09-09','2026-09-12',1,'active',NULL,'2026-09-09 04:56:42','2026-09-09 04:56:42','2026-09-09 04:56:42'),(291,823,3,1,'Calamansi','Juicy calamansi grown in the Lucban highlands.',80.00,60.0000,0.5000,NULL,'2026-09-09','2026-09-23',1,'active',NULL,'2026-09-09 04:56:42','2026-09-09 04:56:42','2026-09-09 04:56:42'),(292,824,6,5,'Sack of Rice (50 kg)','Dinorado regular milled rice, one sack per order, delivered from the mill.',2450.00,20.0000,1.0000,NULL,'2026-09-02',NULL,0,'active',NULL,'2026-09-09 04:56:42','2026-09-09 04:56:42','2026-09-09 04:56:42'),(293,824,1,1,'Eggplant','Long purple eggplant, uniform size, good for tortang talong.',60.00,150.0000,1.0000,NULL,'2026-09-09','2026-09-16',0,'active',NULL,'2026-09-09 04:56:42','2026-09-09 04:56:42','2026-09-09 04:56:42'),(294,824,4,1,'Sweet Potato (Ube Santana)','Purple-fleshed sweet potato from Nueva Ecija, ideal for roasting and halaya.',40.00,300.0000,1.0000,NULL,NULL,'2026-10-09',0,'active',NULL,'2026-09-09 04:56:42','2026-09-09 04:56:42','2026-09-09 04:56:42'),(295,823,1,4,'Sitao (String Beans)','Fresh-picked string beans, harvested the same morning. Best for ginisa and adobo.',45.00,120.0000,1.0000,NULL,'2026-09-10','2026-09-15',1,'active',NULL,'2026-09-10 05:07:34','2026-09-10 05:07:34','2026-09-10 05:07:34'),(296,823,2,4,'Kangkong (Water Spinach)','Crisp kangkong bundles, great for sinigang and adobong kangkong.',25.00,200.0000,1.0000,NULL,'2026-09-10','2026-09-13',1,'active',NULL,'2026-09-10 05:07:34','2026-09-10 05:07:34','2026-09-10 05:07:34'),(297,823,3,1,'Calamansi','Juicy calamansi grown in the Lucban highlands.',80.00,60.0000,0.5000,NULL,'2026-09-10','2026-09-24',1,'active',NULL,'2026-09-10 05:07:34','2026-09-10 05:07:34','2026-09-10 05:07:34'),(298,824,6,5,'Sack of Rice (50 kg)','Dinorado regular milled rice, one sack per order, delivered from the mill.',2450.00,20.0000,1.0000,NULL,'2026-09-03',NULL,0,'active',NULL,'2026-09-10 05:07:34','2026-09-10 05:07:34','2026-09-10 05:07:34'),(299,824,1,1,'Eggplant','Long purple eggplant, uniform size, good for tortang talong.',60.00,150.0000,1.0000,NULL,'2026-09-10','2026-09-17',0,'active',NULL,'2026-09-10 05:07:34','2026-09-10 05:07:34','2026-09-10 05:07:34'),(300,824,4,1,'Sweet Potato (Ube Santana)','Purple-fleshed sweet potato from Nueva Ecija, ideal for roasting and halaya.',40.00,300.0000,1.0000,NULL,NULL,'2026-10-10',0,'active',NULL,'2026-09-10 05:07:34','2026-09-10 05:07:34','2026-09-10 05:07:34'),(301,823,1,4,'Sitao (String Beans)','Fresh-picked string beans, harvested the same morning. Best for ginisa and adobo.',45.00,120.0000,1.0000,NULL,'2026-09-11','2026-09-16',1,'active',NULL,'2026-09-11 02:11:22','2026-09-11 02:11:22','2026-09-11 02:11:22'),(302,823,2,4,'Kangkong (Water Spinach)','Crisp kangkong bundles, great for sinigang and adobong kangkong.',25.00,200.0000,1.0000,NULL,'2026-09-11','2026-09-14',1,'active',NULL,'2026-09-11 02:11:22','2026-09-11 02:11:22','2026-09-11 02:11:22'),(303,823,3,1,'Calamansi','Juicy calamansi grown in the Lucban highlands.',80.00,60.0000,0.5000,NULL,'2026-09-11','2026-09-25',1,'active',NULL,'2026-09-11 02:11:22','2026-09-11 02:11:22','2026-09-11 02:11:22'),(304,824,6,5,'Sack of Rice (50 kg)','Dinorado regular milled rice, one sack per order, delivered from the mill.',2450.00,20.0000,1.0000,NULL,'2026-09-04',NULL,0,'active',NULL,'2026-09-11 02:11:22','2026-09-11 02:11:22','2026-09-11 02:11:22'),(305,824,1,1,'Eggplant','Long purple eggplant, uniform size, good for tortang talong.',60.00,150.0000,1.0000,NULL,'2026-09-11','2026-09-18',0,'active',NULL,'2026-09-11 02:11:22','2026-09-11 02:11:22','2026-09-11 02:11:22'),(306,824,4,1,'Sweet Potato (Ube Santana)','Purple-fleshed sweet potato from Nueva Ecija, ideal for roasting and halaya.',40.00,300.0000,1.0000,NULL,NULL,'2026-10-11',0,'active',NULL,'2026-09-11 02:11:22','2026-09-11 02:11:22','2026-09-11 02:11:22');
/*!40000 ALTER TABLE `produce_listings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quantity_units`
--

DROP TABLE IF EXISTS `quantity_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `quantity_units` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `symbol` varchar(10) NOT NULL,
  `type` enum('weight','count','volume') NOT NULL DEFAULT 'weight',
  `base_factor` decimal(10,4) NOT NULL DEFAULT 1.0000,
  `step` decimal(10,4) NOT NULL DEFAULT 0.0100,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_unit_symbol` (`symbol`),
  UNIQUE KEY `uk_unit_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quantity_units`
--

LOCK TABLES `quantity_units` WRITE;
/*!40000 ALTER TABLE `quantity_units` DISABLE KEYS */;
INSERT INTO `quantity_units` VALUES (1,'Kilogram','kg','weight',1.0000,0.0100,1),(2,'Gram','g','weight',0.0010,1.0000,1),(3,'Piece','pc','count',1.0000,1.0000,1),(4,'Bundle','bundle','count',1.0000,1.0000,1),(5,'Sack (50kg)','sack','weight',50.0000,0.5000,1),(6,'Crate','crate','count',1.0000,1.0000,1),(7,'Liter','L','volume',1.0000,0.0100,1),(8,'Milliliter','mL','volume',0.0010,1.0000,1);
/*!40000 ALTER TABLE `quantity_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rate_limits`
--

DROP TABLE IF EXISTS `rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rate_limits` (
  `key` varchar(128) NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `window_start` int(10) unsigned NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rate_limits`
--

LOCK TABLES `rate_limits` WRITE;
/*!40000 ALTER TABLE `rate_limits` DISABLE KEYS */;
INSERT INTO `rate_limits` VALUES ('diag_997ebd8d',1,1789605450),('login_627f15dd25254e5d76959b86045ce2f1',4,1789641200),('login_b68deaa5198869587d7650bdb3b477b4',1,1789545308),('login_d3c9793f89a8ea6c197fac202490dd0a',5,1789641167),('login_f5d1a2bd2022c958ebc8d9bc45c42c2e',1,1789690027);
/*!40000 ALTER TABLE `rate_limits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `retention_holds`
--

DROP TABLE IF EXISTS `retention_holds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `retention_holds` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `entity_type` enum('user','order','listing','verification') NOT NULL,
  `entity_id` bigint(20) unsigned NOT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `reason` text NOT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `released_at` timestamp NULL DEFAULT NULL,
  `released_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_retention_entity` (`entity_type`,`entity_id`),
  KEY `idx_retention_expires` (`expires_at`),
  KEY `fk_retention_admin` (`admin_id`),
  KEY `fk_retention_released_by` (`released_by`),
  CONSTRAINT `fk_retention_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_retention_released_by` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `retention_holds`
--

LOCK TABLES `retention_holds` WRITE;
/*!40000 ALTER TABLE `retention_holds` DISABLE KEYS */;
/*!40000 ALTER TABLE `retention_holds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text NOT NULL,
  `payload` text NOT NULL,
  `last_activity` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user_id` (`user_id`),
  KEY `idx_sessions_last_activity` (`last_activity`),
  CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stock_adjustments`
--

DROP TABLE IF EXISTS `stock_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stock_adjustments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `listing_id` bigint(20) unsigned NOT NULL,
  `adjustment_type` enum('initial','restock','sale_deduction','return','spoilage','admin_correction') NOT NULL,
  `quantity_change` decimal(12,4) NOT NULL,
  `quantity_before` decimal(12,4) NOT NULL,
  `quantity_after` decimal(12,4) NOT NULL,
  `reference_type` enum('order','manual','admin') DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_stock_listing` (`listing_id`),
  KEY `idx_stock_reference` (`reference_type`,`reference_id`),
  KEY `idx_stock_created` (`created_at`),
  KEY `fk_stock_created_by` (`created_by`),
  CONSTRAINT `fk_stock_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_stock_listing` FOREIGN KEY (`listing_id`) REFERENCES `produce_listings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=349 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stock_adjustments`
--

LOCK TABLES `stock_adjustments` WRITE;
/*!40000 ALTER TABLE `stock_adjustments` DISABLE KEYS */;
/*!40000 ALTER TABLE `stock_adjustments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_preferences`
--

DROP TABLE IF EXISTS `user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_preferences` (
  `user_id` bigint(20) unsigned NOT NULL,
  `locale` enum('en','tl') NOT NULL DEFAULT 'en',
  `theme` enum('system','light','dark') NOT NULL DEFAULT 'system',
  `notify_email` tinyint(1) NOT NULL DEFAULT 1,
  `notify_inapp` tinyint(1) NOT NULL DEFAULT 1,
  `notify_orders` tinyint(1) NOT NULL DEFAULT 1,
  `notify_messages` tinyint(1) NOT NULL DEFAULT 1,
  `notify_promotions` tinyint(1) NOT NULL DEFAULT 0,
  `reduce_motion` tinyint(1) NOT NULL DEFAULT 0,
  `larger_text` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_userprefs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_preferences`
--

LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
INSERT INTO `user_preferences` VALUES (1,'en','system',1,1,1,1,0,0,0,'2026-09-17 23:37:52');
/*!40000 ALTER TABLE `user_preferences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_suspensions`
--

DROP TABLE IF EXISTS `user_suspensions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_suspensions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `admin_id` bigint(20) unsigned NOT NULL,
  `reason` text NOT NULL,
  `suspended_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lifted_at` timestamp NULL DEFAULT NULL,
  `lifted_by` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_suspension_user` (`user_id`),
  KEY `idx_suspension_active` (`lifted_at`),
  KEY `fk_suspension_admin` (`admin_id`),
  KEY `fk_suspension_lifted_by` (`lifted_by`),
  CONSTRAINT `fk_suspension_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_suspension_lifted_by` FOREIGN KEY (`lifted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_suspension_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_suspensions`
--

LOCK TABLES `user_suspensions` WRITE;
/*!40000 ALTER TABLE `user_suspensions` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_suspensions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('farmer','buyer','admin') NOT NULL DEFAULT 'buyer',
  `status` enum('active','suspended','pending_verification') NOT NULL DEFAULT 'pending_verification',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(128) DEFAULT NULL,
  `remember_expires` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_role_status` (`role`,`status`),
  KEY `idx_users_remember_token` (`remember_token`)
) ENGINE=InnoDB AUTO_INCREMENT=838 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin@animal.local','$2y$12$qDP0nzD7LBXbzFwvWnXrVOADRwqkYpwHN/mp6VmogLQ1M1z.q.Jz2','admin','active','2026-09-05 05:24:17','2026-09-18 00:07:07','2026-09-18 00:07:07',NULL,NULL),(2,'maria.buyer@example.com','$2y$12$nfgsc0RB2sB6COfKtUILRevV11sUCG8ArDvo8uLzVcFotjm.9sCOm','buyer','active','2026-09-05 06:22:49','2026-09-05 07:05:40','2026-09-05 07:05:05',NULL,NULL),(73,'sundalong.farmer@example.com','$2y$12$0cs4VlPyO6OJaGcwZ3XXAe7gAXu8s2u34Qneuw.6Fu/a5vjF/eMNG','farmer','active','2026-09-05 06:48:35','2026-09-05 08:31:30','2026-09-05 08:31:30','1d1243902828deda9502669f4bcacb6e9b529afc5ecc0a7d9ad8dd01b42c8abe','2026-10-05 07:05:54'),(823,'delagracia@animal.local','$2y$10$7PzlSsVX1MVcjnlbV9xJd.khgA535s8mTnWBklPzmq2Th4Sqj9Kju','farmer','active','2026-09-06 10:48:58','2026-09-17 10:34:06','2026-09-17 10:34:06',NULL,NULL),(824,'encarnacion@animal.local','$2y$10$hgyHTS5NFczOayIzHfnq4.1W.r04mZoMOYD3axheTwk32mO85jBZ2','farmer','active','2026-09-06 10:48:58','2026-09-16 07:55:08','2026-09-16 07:55:08',NULL,NULL),(825,'mercado@animal.local','$2y$10$ZP2.k26k1eDY/Xg8tYZsweDjCJHWbDJ2NpcwILXEEqKxRDr2tUK8y','buyer','active','2026-09-06 10:48:58','2026-09-17 10:33:19','2026-09-17 10:33:19',NULL,NULL),(826,'dizon@animal.local','$2y$10$ig.orvROyEs/3MMXHGkqyOiMCDFyxsYZkoYcVGIKNrTZt2iDa9ueK','buyer','active','2026-09-06 10:48:58','2026-09-06 10:48:58',NULL,NULL,NULL),(827,'akdhwoiahdwa@gmail.com','$2y$10$7.DgRhVmKPDEWAuHPbZ1deSbmDJFpiX4TlBal80jUfQ6askmGwiam','buyer','active','2026-09-07 05:38:37','2026-09-07 05:38:37',NULL,NULL,NULL),(828,'testbuyer999@animal.local','$2y$10$IK9L/uq60hHEpV91iIof0uq2pKkm4WBqhNUaKAo0il70MGr8gj4mq','buyer','active','2026-09-09 05:08:12','2026-09-09 05:14:05','2026-09-09 05:14:05',NULL,NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `v_active_listings`
--

DROP TABLE IF EXISTS `v_active_listings`;
/*!50001 DROP VIEW IF EXISTS `v_active_listings`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_active_listings` AS SELECT
 1 AS `id`,
  1 AS `farmer_id`,
  1 AS `category_id`,
  1 AS `unit_id`,
  1 AS `name`,
  1 AS `description`,
  1 AS `price_per_unit`,
  1 AS `available_quantity`,
  1 AS `minimum_order_qty`,
  1 AS `maximum_order_qty`,
  1 AS `harvest_date`,
  1 AS `expiry_date`,
  1 AS `is_organic`,
  1 AS `status`,
  1 AS `rejection_reason`,
  1 AS `created_at`,
  1 AS `updated_at`,
  1 AS `published_at`,
  1 AS `category_name`,
  1 AS `category_slug`,
  1 AS `unit_symbol`,
  1 AS `unit_type`,
  1 AS `unit_step`,
  1 AS `farm_name`,
  1 AS `barangay`,
  1 AS `municipality_city`,
  1 AS `province`,
  1 AS `verification_status`,
  1 AS `primary_image` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_order_summary`
--

DROP TABLE IF EXISTS `v_order_summary`;
/*!50001 DROP VIEW IF EXISTS `v_order_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_order_summary` AS SELECT
 1 AS `id`,
  1 AS `order_number`,
  1 AS `buyer_id`,
  1 AS `farmer_id`,
  1 AS `listing_id`,
  1 AS `quantity`,
  1 AS `unit_price`,
  1 AS `delivery_fee`,
  1 AS `delivery_method`,
  1 AS `delivery_address`,
  1 AS `preferred_pickup_date`,
  1 AS `preferred_pickup_time`,
  1 AS `buyer_note`,
  1 AS `farmer_note`,
  1 AS `admin_note`,
  1 AS `status`,
  1 AS `rejection_reason`,
  1 AS `cancellation_reason`,
  1 AS `cancelled_by_role`,
  1 AS `confirmed_at`,
  1 AS `preparing_at`,
  1 AS `ready_at`,
  1 AS `out_for_delivery_at`,
  1 AS `completed_at`,
  1 AS `rejected_at`,
  1 AS `cancelled_at`,
  1 AS `created_at`,
  1 AS `updated_at`,
  1 AS `buyer_name`,
  1 AS `buyer_phone`,
  1 AS `farm_name`,
  1 AS `farmer_contact_person`,
  1 AS `farmer_phone`,
  1 AS `listing_name`,
  1 AS `unit_id`,
  1 AS `unit_symbol` */;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'animal_marketplace'
--

--
-- Current Database: `animal_marketplace`
--

USE `animal_marketplace`;

--
-- Final view structure for view `v_active_listings`
--

/*!50001 DROP VIEW IF EXISTS `v_active_listings`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY INVOKER */
/*!50001 VIEW `v_active_listings` AS select `pl`.`id` AS `id`,`pl`.`farmer_id` AS `farmer_id`,`pl`.`category_id` AS `category_id`,`pl`.`unit_id` AS `unit_id`,`pl`.`name` AS `name`,`pl`.`description` AS `description`,`pl`.`price_per_unit` AS `price_per_unit`,`pl`.`available_quantity` AS `available_quantity`,`pl`.`minimum_order_qty` AS `minimum_order_qty`,`pl`.`maximum_order_qty` AS `maximum_order_qty`,`pl`.`harvest_date` AS `harvest_date`,`pl`.`expiry_date` AS `expiry_date`,`pl`.`is_organic` AS `is_organic`,`pl`.`status` AS `status`,`pl`.`rejection_reason` AS `rejection_reason`,`pl`.`created_at` AS `created_at`,`pl`.`updated_at` AS `updated_at`,`pl`.`published_at` AS `published_at`,`pc`.`name` AS `category_name`,`pc`.`slug` AS `category_slug`,`qu`.`symbol` AS `unit_symbol`,`qu`.`type` AS `unit_type`,`qu`.`step` AS `unit_step`,`fp`.`farm_name` AS `farm_name`,`fp`.`barangay` AS `barangay`,`fp`.`municipality_city` AS `municipality_city`,`fp`.`province` AS `province`,`fp`.`verification_status` AS `verification_status`,(select `li`.`image_path` from `listing_images` `li` where `li`.`listing_id` = `pl`.`id` and `li`.`is_primary` = 1 order by `li`.`sort_order` limit 1) AS `primary_image` from (((`produce_listings` `pl` join `produce_categories` `pc` on(`pl`.`category_id` = `pc`.`id`)) join `quantity_units` `qu` on(`pl`.`unit_id` = `qu`.`id`)) join `farmer_profiles` `fp` on(`pl`.`farmer_id` = `fp`.`user_id`)) where `pl`.`status` = 'active' and `fp`.`verification_status` = 'approved' and `pc`.`is_active` = 1 and `qu`.`is_active` = 1 and `pl`.`available_quantity` > 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_order_summary`
--

/*!50001 DROP VIEW IF EXISTS `v_order_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 SQL SECURITY INVOKER */
/*!50001 VIEW `v_order_summary` AS select `o`.`id` AS `id`,`o`.`order_number` AS `order_number`,`o`.`buyer_id` AS `buyer_id`,`o`.`farmer_id` AS `farmer_id`,`o`.`listing_id` AS `listing_id`,`o`.`quantity` AS `quantity`,`o`.`unit_price` AS `unit_price`,`o`.`delivery_fee` AS `delivery_fee`,`o`.`delivery_method` AS `delivery_method`,`o`.`delivery_address` AS `delivery_address`,`o`.`preferred_pickup_date` AS `preferred_pickup_date`,`o`.`preferred_pickup_time` AS `preferred_pickup_time`,`o`.`buyer_note` AS `buyer_note`,`o`.`farmer_note` AS `farmer_note`,`o`.`admin_note` AS `admin_note`,`o`.`status` AS `status`,`o`.`rejection_reason` AS `rejection_reason`,`o`.`cancellation_reason` AS `cancellation_reason`,`o`.`cancelled_by_role` AS `cancelled_by_role`,`o`.`confirmed_at` AS `confirmed_at`,`o`.`preparing_at` AS `preparing_at`,`o`.`ready_at` AS `ready_at`,`o`.`out_for_delivery_at` AS `out_for_delivery_at`,`o`.`completed_at` AS `completed_at`,`o`.`rejected_at` AS `rejected_at`,`o`.`cancelled_at` AS `cancelled_at`,`o`.`created_at` AS `created_at`,`o`.`updated_at` AS `updated_at`,concat(`bp`.`full_name`) AS `buyer_name`,`bp`.`contact_phone` AS `buyer_phone`,`fp`.`farm_name` AS `farm_name`,`fp`.`contact_person` AS `farmer_contact_person`,`fp`.`contact_phone` AS `farmer_phone`,`pl`.`name` AS `listing_name`,`pl`.`unit_id` AS `unit_id`,`qu`.`symbol` AS `unit_symbol` from ((((`orders` `o` join `buyer_profiles` `bp` on(`o`.`buyer_id` = `bp`.`user_id`)) join `farmer_profiles` `fp` on(`o`.`farmer_id` = `fp`.`user_id`)) join `produce_listings` `pl` on(`o`.`listing_id` = `pl`.`id`)) join `quantity_units` `qu` on(`pl`.`unit_id` = `qu`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-18  8:16:09
