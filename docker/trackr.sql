-- MySQL dump 10.13  Distrib 5.7.29, for Linux (x86_64)
--
-- Host: localhost    Database: trackr1
-- ------------------------------------------------------
-- Server version	5.7.29

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
-- Table structure for table `author`
--

DROP TABLE IF EXISTS `author`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `author` (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `author` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                          PRIMARY KEY (`id`),
                          UNIQUE KEY `NAME_UNIQUE` (`author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `author`
--

LOCK TABLES `author` WRITE;
/*!40000 ALTER TABLE `author` DISABLE KEYS */;
INSERT INTO `author` VALUES (303,'J. R. R. Tolkien');
/*!40000 ALTER TABLE `author` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `book_authors`
--

DROP TABLE IF EXISTS `book_authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `book_authors` (
                                `id` int(11) NOT NULL AUTO_INCREMENT,
                                `author_id` int(11) NOT NULL,
                                `book_id` int(11) NOT NULL,
                                PRIMARY KEY (`id`),
                                UNIQUE KEY `idx_author_id_book_id` (`author_id`,`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `book_authors`
--

LOCK TABLES `book_authors` WRITE;
/*!40000 ALTER TABLE `book_authors` DISABLE KEYS */;
INSERT INTO `book_authors` VALUES (470,303,443),(471,303,444),(472,303,445),(473,303,446),(474,303,447),(475,303,448),(476,303,449),(477,303,450),(478,303,451),(479,303,452),(480,303,453),(481,303,454),(482,303,455),(483,303,456),(484,303,457),(485,303,458),(486,303,459),(487,303,460),(488,303,461),(489,303,462),(1314,303,1263),(1355,303,1298),(1366,303,1308),(1367,303,1309);
/*!40000 ALTER TABLE `book_authors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `book_trackings`
--

DROP TABLE IF EXISTS `book_trackings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `book_trackings` (
                                  `id` int(11) NOT NULL AUTO_INCREMENT,
                                  `book_id` int(11) NOT NULL,
                                  `path_id` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
                                  `record_date` int(11) NOT NULL,
                                  `amount` int(11) NOT NULL,
                                  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `book_trackings`
--

LOCK TABLES `book_trackings` WRITE;
/*!40000 ALTER TABLE `book_trackings` DISABLE KEYS */;
/*!40000 ALTER TABLE `book_trackings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookmarks`
--

DROP TABLE IF EXISTS `bookmarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookmarks` (
                             `id` int(11) NOT NULL AUTO_INCREMENT,
                             `uid` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
                             `bookmark` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL,
                             `title` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                             `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                             `categoryId` int(11) DEFAULT NULL,
                             `status` int(11) DEFAULT '0',
                             `created` int(11) NOT NULL,
                             `started` int(11) DEFAULT NULL,
                             `done` int(11) DEFAULT NULL,
                             PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookmarks`
--

LOCK TABLES `bookmarks` WRITE;
/*!40000 ALTER TABLE `bookmarks` DISABLE KEYS */;
/*!40000 ALTER TABLE `bookmarks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `books`
--

DROP TABLE IF EXISTS `books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `books` (
                         `id` int(11) NOT NULL AUTO_INCREMENT,
                         `uid` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
                         `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                         `publisher` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                         `pdf` int(11) DEFAULT '0',
                         `epub` int(11) DEFAULT '0',
                         `notes` longtext COLLATE utf8mb4_unicode_ci,
                         `category` int(11) DEFAULT '6665',
                         `added_date` int(11) DEFAULT NULL,
                         `own` int(11) DEFAULT '0',
                         `page_count` int(11) DEFAULT '0',
                         `status` int(11) DEFAULT '0',
                         PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `books`
--

LOCK TABLES `books` WRITE;
/*!40000 ALTER TABLE `books` DISABLE KEYS */;
INSERT INTO `books` VALUES (443,'8e744c35-3802-11eb-aadd-0242ac110002','Bitmemiş Öyküler',NULL,0,0,NULL,6665,1551212662,0,756,0),(444,'8e74befc-3802-11eb-aadd-0242ac110002','Guc Yuzuklerine Dair',NULL,0,0,NULL,6665,1551212662,0,160,0),(445,'8e753b7a-3802-11eb-aadd-0242ac110002','Hobbit',NULL,0,0,NULL,6665,1551212662,0,0,0),(446,'8e75a715-3802-11eb-aadd-0242ac110002','Hobbit',NULL,0,0,NULL,6665,1551212662,0,0,0),(447,'8e761481-3802-11eb-aadd-0242ac110002','Hobbit : Aciklamali Notlariyla',NULL,0,0,NULL,6665,1551212662,0,398,0),(448,'8e768229-3802-11eb-aadd-0242ac110002','Hurin\'in Çocukları',NULL,0,0,NULL,6665,1551212662,0,355,0),(449,'8e76e765-3802-11eb-aadd-0242ac110002','Kayıp Öyküler Kitabı 1',NULL,0,0,NULL,6665,1551212662,0,405,0),(450,'8e7749fc-3802-11eb-aadd-0242ac110002','Kayıp Öyküler Kitabı 2',NULL,0,0,NULL,6665,1551212662,0,480,0),(451,'8e77baa7-3802-11eb-aadd-0242ac110002','Kullervo’nun Hikayesi',NULL,0,0,NULL,6665,1551212662,0,0,0),(452,'8e781775-3802-11eb-aadd-0242ac110002','Masallar',NULL,0,0,NULL,6665,1551212662,0,159,0),(453,'8e788268-3802-11eb-aadd-0242ac110002','Noel Baba’dan Mektuplar',NULL,0,0,NULL,6665,1551212662,0,0,0),(454,'8e78f6c7-3802-11eb-aadd-0242ac110002','Peri Masalları Üzerine',NULL,0,0,NULL,6665,1551212662,0,0,0),(455,'8e7973a3-3802-11eb-aadd-0242ac110002','Roverandom',NULL,0,0,NULL,6665,1551212662,0,144,0),(456,'8e79e0e6-3802-11eb-aadd-0242ac110002','Sigurd ile Gudrun Efsanesi',NULL,0,0,NULL,6665,1551212662,0,0,0),(457,'8e7a3b94-3802-11eb-aadd-0242ac110002','Silmarillion',NULL,0,0,NULL,6665,1551212662,0,731,0),(458,'8e7a847c-3802-11eb-aadd-0242ac110002','Tehlikeli Diyardan Öyküler',NULL,0,0,NULL,6665,1551212662,0,0,0),(459,'8e7accff-3802-11eb-aadd-0242ac110002','Tom Bombadil’in Maceralari',NULL,0,0,NULL,6665,1551212662,0,0,0),(460,'8e7b1947-3802-11eb-aadd-0242ac110002','Yüzüklerin Efendisi - 1 - Yüzük Kardeşliği',NULL,0,0,NULL,6665,1551212662,0,0,0),(461,'8e7b60e6-3802-11eb-aadd-0242ac110002','Yüzüklerin Efendisi - 2 - İki Kule',NULL,0,0,NULL,6665,1551212662,0,0,0),(462,'8e7bae5c-3802-11eb-aadd-0242ac110002','Yüzüklerin Efendisi - 3 - Kralın Dönüşü',NULL,0,0,NULL,6665,1551212662,0,0,0),(1263,'8e7bfb07-3802-11eb-aadd-0242ac110002','Beren ile Luthien',NULL,0,0,NULL,6665,1564952400,0,269,0),(1298,'8e7c42e2-3802-11eb-aadd-0242ac110002','Gondolin\'in Düşüşü',NULL,0,0,NULL,6665,1581973200,0,390,0),(1308,'8e7c9768-3802-11eb-aadd-0242ac110002','Büyük Wootton Demircisi',NULL,0,0,NULL,6665,1584651600,0,209,0),(1309,'8e7ce7d3-3802-11eb-aadd-0242ac110002','Ham\'li Çiftçi Giles',NULL,0,0,NULL,6665,1584651600,0,197,0);
/*!40000 ALTER TABLE `books` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `books_finished`
--

DROP TABLE IF EXISTS `books_finished`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `books_finished` (
                                  `id` int(11) NOT NULL AUTO_INCREMENT,
                                  `book_id` int(11) NOT NULL,
                                  `path_id` int(11) NOT NULL,
                                  `start_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                                  `finish_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                                  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `books_finished`
--

LOCK TABLES `books_finished` WRITE;
/*!40000 ALTER TABLE `books_finished` DISABLE KEYS */;
/*!40000 ALTER TABLE `books_finished` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categories`
--

DROP TABLE IF EXISTS `categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categories` (
                              `id` int(11) NOT NULL AUTO_INCREMENT,
                              `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                              `defaultStatus` int(11) NOT NULL DEFAULT '0',
                              `created` int(11) NOT NULL,
                              PRIMARY KEY (`id`),
                              UNIQUE KEY `NAME_UNIQUE` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categories`
--

LOCK TABLES `categories` WRITE;
/*!40000 ALTER TABLE `categories` DISABLE KEYS */;
INSERT INTO `categories` VALUES (6665,'default',1,1607779386);
/*!40000 ALTER TABLE `categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `date_trackings`
--

DROP TABLE IF EXISTS `date_trackings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `date_trackings` (
                                  `id` int(11) NOT NULL AUTO_INCREMENT,
                                  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                                  `start` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
                                  `created` int(11) NOT NULL,
                                  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `date_trackings`
--

LOCK TABLES `date_trackings` WRITE;
/*!40000 ALTER TABLE `date_trackings` DISABLE KEYS */;

/*!40000 ALTER TABLE `date_trackings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `highlight_tags`
--

DROP TABLE IF EXISTS `highlight_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `highlight_tags` (
                                  `id` int(11) NOT NULL AUTO_INCREMENT,
                                  `highlight_id` int(11) NOT NULL,
                                  `tag_id` int(11) NOT NULL,
                                  `created` int(11) NOT NULL,
                                  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `highlight_tags`
--

LOCK TABLES `highlight_tags` WRITE;
/*!40000 ALTER TABLE `highlight_tags` DISABLE KEYS */;

/*!40000 ALTER TABLE `highlight_tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `highlights`
--

DROP TABLE IF EXISTS `highlights`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `highlights` (
                              `id` int(11) NOT NULL AUTO_INCREMENT,
                              `highlight` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
                              `author` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
                              `source` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
                              `page` int(11) DEFAULT NULL,
                              `location` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                              `link` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                              `type` int(11) DEFAULT '0',
                              `created` int(11) NOT NULL,
                              `updated` int(11) DEFAULT NULL,
                              PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `highlights`
--

LOCK TABLES `highlights` WRITE;
/*!40000 ALTER TABLE `highlights` DISABLE KEYS */;
/*!40000 ALTER TABLE `highlights` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `path_books`
--

DROP TABLE IF EXISTS `path_books`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `path_books` (
                              `id` int(11) NOT NULL AUTO_INCREMENT,
                              `path_id` int(11) NOT NULL,
                              `book_id` int(11) NOT NULL,
                              `status` int(11) NOT NULL,
                              `created` int(11) NOT NULL,
                              `updated` int(11) DEFAULT NULL,
                              PRIMARY KEY (`id`),
                              UNIQUE KEY `idx_path_id_book_id` (`path_id`,`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `path_books`
--

LOCK TABLES `path_books` WRITE;
/*!40000 ALTER TABLE `path_books` DISABLE KEYS */;
/*!40000 ALTER TABLE `path_books` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `paths`
--

DROP TABLE IF EXISTS `paths`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `paths` (
                         `id` int(11) NOT NULL AUTO_INCREMENT,
                         `uid` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
                         `name` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
                         `start` int(11) NOT NULL,
                         `finish` int(11) NOT NULL,
                         `status` int(11) DEFAULT '0',
                         PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `paths`
--

LOCK TABLES `paths` WRITE;
/*!40000 ALTER TABLE `paths` DISABLE KEYS */;
/*!40000 ALTER TABLE `paths` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `publishers`
--

DROP TABLE IF EXISTS `publishers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `publishers` (
                              `id` int(11) NOT NULL AUTO_INCREMENT,
                              `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                              PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `publishers`
--

LOCK TABLES `publishers` WRITE;
/*!40000 ALTER TABLE `publishers` DISABLE KEYS */;
/*!40000 ALTER TABLE `publishers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tags`
--

DROP TABLE IF EXISTS `tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tags` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `tag` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                        `created` int(11) NOT NULL,
                        PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tags`
--

LOCK TABLES `tags` WRITE;
/*!40000 ALTER TABLE `tags` DISABLE KEYS */;
/*!40000 ALTER TABLE `tags` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `todos`
--

DROP TABLE IF EXISTS `todos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `todos` (
                         `id` int(11) NOT NULL AUTO_INCREMENT,
                         `todo` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
                         `description` longtext COLLATE utf8mb4_unicode_ci,
                         `created` int(11) NOT NULL,
                         `started` int(11) DEFAULT NULL,
                         `done` int(11) DEFAULT NULL,
                         `status` int(11) NOT NULL DEFAULT '0',
                         PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `todos`
--

LOCK TABLES `todos` WRITE;
/*!40000 ALTER TABLE `todos` DISABLE KEYS */;
/*!40000 ALTER TABLE `todos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
                         `id` int(11) NOT NULL AUTO_INCREMENT,
                         `username` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
                         `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                         `created` int(11) NOT NULL,
                         PRIMARY KEY (`id`),
                         UNIQUE KEY `username_UNIQUE` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `videos`
--

DROP TABLE IF EXISTS `videos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `videos` (
                          `id` int(11) NOT NULL AUTO_INCREMENT,
                          `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
                          `category_id` int(11) NOT NULL,
                          `status` int(11) NOT NULL DEFAULT '0',
                          `length` float DEFAULT NULL,
                          `link` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                          `created` int(11) NOT NULL,
                          `started` int(11) DEFAULT NULL,
                          `done` int(11) DEFAULT NULL,
                          PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `videos`
--

LOCK TABLES `videos` WRITE;
/*!40000 ALTER TABLE `videos` DISABLE KEYS */;
/*!40000 ALTER TABLE `videos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `work_trackings`
--

DROP TABLE IF EXISTS `work_trackings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `work_trackings` (
                                  `id` int(11) NOT NULL AUTO_INCREMENT,
                                  `work` int(11) NOT NULL DEFAULT '0',
                                  `description` longtext COLLATE utf8mb4_unicode_ci,
                                  `date` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                                  `status` int(11) NOT NULL DEFAULT '0',
                                  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `work_trackings`
--

LOCK TABLES `work_trackings` WRITE;
/*!40000 ALTER TABLE `work_trackings` DISABLE KEYS */;
/*!40000 ALTER TABLE `work_trackings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `writings`
--

DROP TABLE IF EXISTS `writings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `writings` (
                            `id` int(11) NOT NULL AUTO_INCREMENT,
                            `date` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                            `text` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
                            `created` int(11) NOT NULL,
                            `updated` int(11) DEFAULT NULL,
                            PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `writings`
--

LOCK TABLES `writings` WRITE;
/*!40000 ALTER TABLE `writings` DISABLE KEYS */;
/*!40000 ALTER TABLE `writings` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2020-12-12 13:17:22
