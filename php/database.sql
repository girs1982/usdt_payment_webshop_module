-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: usdtpay
-- ------------------------------------------------------
-- Server version	8.0.46

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
-- Table structure for table `addresses`
--

DROP TABLE IF EXISTS `addresses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `addresses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `address` varchar(64) NOT NULL,
  `privkey` varchar(64) NOT NULL,
  `assigned` tinyint NOT NULL DEFAULT '0',
  `swept` datetime DEFAULT NULL,
  `sweep_tx` varchar(128) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `addr_index` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `address` (`address`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `addresses`
--

LOCK TABLES `addresses` WRITE;
/*!40000 ALTER TABLE `addresses` DISABLE KEYS */;
INSERT INTO `addresses` VALUES (1,'TSWMgHED7y96QsNdDrxDYWqGu6xfTbXazC','eeb49d6c0c43e7ca66ea4cd5bdaab021883ad438432fdf7d4aa99c3397e8a3e4',1,NULL,NULL,'2026-10-07 13:42:12',0),(2,'TNCnXFGyabmiyFkzcrBRZXgmNq4cDw1p2V','54b44edc53a85f5c00b319fb92d7197529c658bf9b910bd6140fb2a0df54bb09',0,NULL,NULL,'2026-10-07 13:42:12',1),(3,'TQvaHmyXreoYsi3TMgqv3jz1a4TGMEonws','e1e483b036ddb1cc6e20f68eff59dea8f1d98357f47d7b65e1ca0b8fe6d73214',0,NULL,NULL,'2026-10-07 13:42:12',2),(4,'TGFqwVhAeCmUtUy37yAJKeaLH3hpEU65Nj','eb3a6deee10178a7f15ac86618ab7456bdcb8e2f16e7ef62929ceb62ddad8926',0,NULL,NULL,'2026-10-07 13:42:12',3),(5,'TW8yp7Gwbvx7w8Bq44SG72XzGWrQXkwqaY','9ad5a1268a84973cff746bfb91f856d9442ea96a67a59683be1ad6beb3bee93d',0,NULL,NULL,'2026-10-07 13:42:12',4);
/*!40000 ALTER TABLE `addresses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `admin_users`
--

DROP TABLE IF EXISTS `admin_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (1,'admin','$2y$10$Lxbepuuz9CzBPwmsPGcifefPLmXQ9Wx7kWwzK45shi8r.OqcehH.G','admin@example.com','2025-04-23 13:48:36','2025-04-23 13:48:36');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usdt_address` varchar(100) NOT NULL,
  `tron_api_key` varchar(100) NOT NULL,
  `network` varchar(20) NOT NULL,
  `checkout_timeout` int DEFAULT '1800',
  `check_interval` int DEFAULT '30',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `api_token` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'TXFT5NNmTBFWpUA577avVDK2eSR9FpvzBz','ae743c14-02ca-49df-a693-e96e0e707e37','TRC20',3600,60,'2026-10-04 10:36:12','2026-10-07 14:07:03','0723a019eb49d9dc8866325217d559a452aaddad2ecb819bf5b09d474d1cdaf0');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `transactions`
--

DROP TABLE IF EXISTS `transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `order_id` varchar(50) NOT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `real_amount` decimal(20,6) NOT NULL,
  `payment_amount` decimal(20,6) NOT NULL,
  `status` enum('pending','completed','failed','cancelled') DEFAULT 'pending',
  `address` varchar(100) DEFAULT NULL,
  `network` varchar(20) DEFAULT NULL,
  `tx_hash` text,
  `failure_reason` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  `shop` varchar(64) DEFAULT NULL,
  `webhook_url` text,
  `return_url` text,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=74 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `transactions`
--

LOCK TABLES `transactions` WRITE;
/*!40000 ALTER TABLE `transactions` DISABLE KEYS */;
INSERT INTO `transactions` VALUES (65,'82689e05-c096-44d6-9a4a-ac6d472f7dc9','huy','sait-complete@yandex.ru',3.000000,3.578740,'completed','TB6EqkjgbqxeWrgNUvRQQJK26dmkYwikBg','TRC20','ad980fdd5b9b36753243e3c3f46070a2d05bdc1d9ed33d6f6c36385f9936c4fa',NULL,'2026-10-05 12:28:23','2026-10-05 12:55:17','2026-10-05 12:55:17',NULL,NULL,NULL,NULL),(72,'28c9a602-e5cc-4e49-b94b-6089100932c1','Test','',9.990000,10.538500,'pending','TSWMgHED7y96QsNdDrxDYWqGu6xfTbXazC','TRC20',NULL,NULL,'2026-10-07 14:07:40','2026-10-07 14:07:40',NULL,'test','','','2026-10-07 15:07:40'),(73,'e672a30f-9d86-48dc-9e24-e14c066d2a41','','',1.500000,2.103600,'cancelled','TNCnXFGyabmiyFkzcrBRZXgmNq4cDw1p2V','TRC20',NULL,'api cancel','2026-10-07 14:18:55','2026-10-07 14:19:57',NULL,'test','','','2026-10-07 15:18:55');
/*!40000 ALTER TABLE `transactions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'usdtpay'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-07 18:59:29
