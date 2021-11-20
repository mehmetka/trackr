CREATE TABLE `author` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `author` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `NAME_UNIQUE` (`author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `author` VALUES 
(303,'J. R. R. Tolkien');

CREATE TABLE `book_authors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `author_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_author_id_book_id` (`author_id`,`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `book_authors` VALUES 
(470,303,443),
(471,303,444),
(472,303,445),
(473,303,446),
(474,303,447),
(475,303,448),
(476,303,449),
(477,303,450),
(478,303,451),
(479,303,452),
(480,303,453),
(481,303,454),
(482,303,455),
(483,303,456),
(484,303,457),
(485,303,458),
(486,303,459),
(487,303,460),
(488,303,461),
(489,303,462),
(1314,303,1263),
(1355,303,1298),
(1366,303,1308),
(1367,303,1309);

CREATE TABLE `bookmarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bookmark` varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoryId` int(11) DEFAULT NULL,
  `status` int(11) DEFAULT '0',
  `orderNumber` int(11) DEFAULT NULL,
  `created` int(11) NOT NULL,
  `started` int(11) DEFAULT NULL,
  `done` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

INSERT INTO `books` VALUES 
(443,'8e744c35-3802-11eb-aadd-0242ac110002','Bitmemiş Öyküler',NULL,0,0,NULL,6665,1551212662,0,756,0),
(444,'8e74befc-3802-11eb-aadd-0242ac110002','Guc Yuzuklerine Dair',NULL,0,0,NULL,6665,1551212662,0,160,0),
(445,'8e753b7a-3802-11eb-aadd-0242ac110002','Hobbit',NULL,0,0,NULL,6665,1551212662,0,0,0),
(446,'8e75a715-3802-11eb-aadd-0242ac110002','Hobbit',NULL,0,0,NULL,6665,1551212662,0,0,0),
(447,'8e761481-3802-11eb-aadd-0242ac110002','Hobbit : Aciklamali Notlariyla',NULL,0,0,NULL,6665,1551212662,0,398,0),
(448,'8e768229-3802-11eb-aadd-0242ac110002','Hurin\'in Çocukları',NULL,0,0,NULL,6665,1551212662,0,355,0),
(449,'8e76e765-3802-11eb-aadd-0242ac110002','Kayıp Öyküler Kitabı 1',NULL,0,0,NULL,6665,1551212662,0,405,0),
(450,'8e7749fc-3802-11eb-aadd-0242ac110002','Kayıp Öyküler Kitabı 2',NULL,0,0,NULL,6665,1551212662,0,480,0),
(451,'8e77baa7-3802-11eb-aadd-0242ac110002','Kullervo’nun Hikayesi',NULL,0,0,NULL,6665,1551212662,0,0,0),
(452,'8e781775-3802-11eb-aadd-0242ac110002','Masallar',NULL,0,0,NULL,6665,1551212662,0,159,0),
(453,'8e788268-3802-11eb-aadd-0242ac110002','Noel Baba’dan Mektuplar',NULL,0,0,NULL,6665,1551212662,0,0,0),
(454,'8e78f6c7-3802-11eb-aadd-0242ac110002','Peri Masalları Üzerine',NULL,0,0,NULL,6665,1551212662,0,0,0),
(455,'8e7973a3-3802-11eb-aadd-0242ac110002','Roverandom',NULL,0,0,NULL,6665,1551212662,0,144,0),
(456,'8e79e0e6-3802-11eb-aadd-0242ac110002','Sigurd ile Gudrun Efsanesi',NULL,0,0,NULL,6665,1551212662,0,0,0),
(457,'8e7a3b94-3802-11eb-aadd-0242ac110002','Silmarillion',NULL,0,0,NULL,6665,1551212662,0,731,0),
(458,'8e7a847c-3802-11eb-aadd-0242ac110002','Tehlikeli Diyardan Öyküler',NULL,0,0,NULL,6665,1551212662,0,0,0),
(459,'8e7accff-3802-11eb-aadd-0242ac110002','Tom Bombadil’in Maceralari',NULL,0,0,NULL,6665,1551212662,0,0,0),
(460,'8e7b1947-3802-11eb-aadd-0242ac110002','Yüzüklerin Efendisi - 1 - Yüzük Kardeşliği',NULL,0,0,NULL,6665,1551212662,0,0,0),
(461,'8e7b60e6-3802-11eb-aadd-0242ac110002','Yüzüklerin Efendisi - 2 - İki Kule',NULL,0,0,NULL,6665,1551212662,0,0,0),
(462,'8e7bae5c-3802-11eb-aadd-0242ac110002','Yüzüklerin Efendisi - 3 - Kralın Dönüşü',NULL,0,0,NULL,6665,1551212662,0,0,0),
(1263,'8e7bfb07-3802-11eb-aadd-0242ac110002','Beren ile Luthien',NULL,0,0,NULL,6665,1564952400,0,269,0),
(1298,'8e7c42e2-3802-11eb-aadd-0242ac110002','Gondolin\'in Düşüşü',NULL,0,0,NULL,6665,1581973200,0,390,0),
(1308,'8e7c9768-3802-11eb-aadd-0242ac110002','Büyük Wootton Demircisi',NULL,0,0,NULL,6665,1584651600,0,209,0),
(1309,'8e7ce7d3-3802-11eb-aadd-0242ac110002','Ham\'li Çiftçi Giles',NULL,0,0,NULL,6665,1584651600,0,197,0);

CREATE TABLE `books_finished` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `path_id` int(11) NOT NULL,
  `start_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `finish_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `book_trackings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `book_id` int(11) NOT NULL,
  `path_id` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_date` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `defaultStatus` int(11) NOT NULL DEFAULT '0',
  `created` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `NAME_UNIQUE` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` VALUES 
(6665,'default',1,1607779386);

CREATE TABLE `date_trackings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `highlights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `highlight` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `html` longtext COLLATE utf8mb4_unicode_ci,
  `author` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
  `page` int(11) DEFAULT NULL,
  `location` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` int(11) DEFAULT NULL,
  `type` int(11) DEFAULT '0',
  `is_secret` int(11) DEFAULT '1',
  `created` int(11) NOT NULL,
  `updated` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `highlight_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `highlight_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  `created` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `paths` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uid` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start` int(11) NOT NULL,
  `finish` int(11) NOT NULL,
  `status` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publishers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sub_highlights` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `highlight_id` int(11) NOT NULL,
  `sub_highlight_id` int(11) NOT NULL,
  `created` int(11) NOT NULL,
  `updated` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tag` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `todos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `todo` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `orderNumber` int(11) DEFAULT NULL,
  `created` int(11) NOT NULL,
  `started` int(11) DEFAULT NULL,
  `done` int(11) DEFAULT NULL,
  `canceled` int(11) DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username_UNIQUE` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE `work_trackings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `work` int(11) NOT NULL DEFAULT '0',
  `description` longtext COLLATE utf8mb4_unicode_ci,
  `date` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `writings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `date` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `text` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `created` int(11) NOT NULL,
  `updated` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
