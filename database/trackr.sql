CREATE TABLE `activity_logs`
(
    `id`        int(11)                                 NOT NULL AUTO_INCREMENT,
    `path_id`   int(11) DEFAULT NULL,
    `book_id`   int(11) DEFAULT NULL,
    `activity`  varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `timestamp` int(11)                                 NOT NULL,
    `user_id`   int(11)                                 NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `author`
(
    `id`     int(11)                                 NOT NULL AUTO_INCREMENT,
    `author` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `NAME_UNIQUE` (`author`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `book_authors`
(
    `id`        int(11) NOT NULL AUTO_INCREMENT,
    `author_id` int(11) NOT NULL,
    `book_id`   int(11) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_author_id_book_id` (`author_id`, `book_id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `bookmarks`
(
    `id`              int(11)                                  NOT NULL AUTO_INCREMENT,
    `uid`             varchar(45) COLLATE utf8mb4_unicode_ci   NOT NULL,
    `bookmark`        varchar(2000) COLLATE utf8mb4_unicode_ci NOT NULL,
    `site_name`       varchar(255) COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `title`           varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `description`     mediumtext COLLATE utf8mb4_unicode_ci,
    `note`            varchar(1000) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `site_type`       varchar(255) COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `thumbnail`       varchar(500) COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `status`          int(11)                                  DEFAULT '0',
    `orderNumber`     int(11)                                  DEFAULT NULL,
    `created`         int(11)                                  NOT NULL,
    `started`         int(11)                                  DEFAULT NULL,
    `done`            int(11)                                  DEFAULT NULL,
    `is_deleted`      int(11)                                  DEFAULT '0',
    `deleted_at`      int(11)                                  DEFAULT NULL,
    `is_title_edited` int(11)                                  DEFAULT '0',
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `books`
(
    `id`              int(11)                                 NOT NULL AUTO_INCREMENT,
    `uid`             varchar(45) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `title`           varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `subtitle`        varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `publisher`       varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `pdf`             int(11)                                 DEFAULT '0',
    `epub`            int(11)                                 DEFAULT '0',
    `notes`           longtext COLLATE utf8mb4_unicode_ci,
    `category`        int(11)                                 DEFAULT '6665',
    `added_date`      int(11)                                 DEFAULT NULL,
    `own`             int(11)                                 DEFAULT '0',
    `page_count`      int(11)                                 DEFAULT '0',
    `status`          int(11)                                 DEFAULT '0',
    `published_date`  varchar(20) COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `description`     mediumtext COLLATE utf8mb4_unicode_ci,
    `isbn`            varchar(13) COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `thumbnail`       varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `thumbnail_small` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `info_link`       varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `books_finished`
(
    `id`          int(11) NOT NULL AUTO_INCREMENT,
    `book_id`     int(11) NOT NULL,
    `path_id`     int(11) NOT NULL,
    `start_date`  varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `finish_date` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `rate`        int(11)                                 DEFAULT NULL,
    `user_id`     int(11) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `books_googleapi_responses`
(
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `book_id`    int(11) DEFAULT NULL,
    `response`   longtext COLLATE utf8mb4_unicode_ci,
    `created_at` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `book_trackings`
(
    `id`          int(11)                                NOT NULL AUTO_INCREMENT,
    `book_id`     int(11)                                NOT NULL,
    `path_id`     varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
    `record_date` int(11)                                NOT NULL,
    `amount`      int(11)                                NOT NULL,
    `user_id`     int(11)                                NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `categories`
(
    `id`            int(11)                                 NOT NULL AUTO_INCREMENT,
    `name`          varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `defaultStatus` int(11)                                 NOT NULL DEFAULT '0',
    `created`       int(11)                                 NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `NAME_UNIQUE` (`name`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `date_trackings`
(
    `id`      int(11)                                 NOT NULL AUTO_INCREMENT,
    `name`    varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `start`   varchar(15) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `created` int(11)                                 NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `highlights`
(
    `id`         int(11)                                  NOT NULL AUTO_INCREMENT,
    `highlight`  longtext COLLATE utf8mb4_unicode_ci      NOT NULL,
    `author`     varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
    `source`     varchar(1000) COLLATE utf8mb4_unicode_ci NOT NULL,
    `page`       int(11)                                 DEFAULT NULL,
    `location`   varchar(45) COLLATE utf8mb4_unicode_ci  DEFAULT NULL,
    `link`       int(11)                                 DEFAULT NULL,
    `file_name`  varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `type`       int(11)                                 DEFAULT '0',
    `is_secret`  int(11)                                 DEFAULT '1',
    `created`    int(11)                                  NOT NULL,
    `updated`    int(11)                                 DEFAULT NULL,
    `is_deleted` int(11)                                 DEFAULT '0',
    `deleted_at` int(11)                                 DEFAULT NULL,
    `user_id`    int(11)                                  NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `highlight_versions`
(
    `id`            int(11)                             NOT NULL AUTO_INCREMENT,
    `highlight_id`  int(11)                             NOT NULL,
    `old_highlight` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `created_at`    int(11)                             NOT NULL,
    `user_id`       int(11)                             NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `path_books`
(
    `id`      int(11) NOT NULL AUTO_INCREMENT,
    `path_id` int(11) NOT NULL,
    `book_id` int(11) NOT NULL,
    `status`  int(11) NOT NULL,
    `created` int(11) NOT NULL,
    `updated` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_path_id_book_id` (`path_id`, `book_id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `paths`
(
    `id`      int(11)                                NOT NULL AUTO_INCREMENT,
    `uid`     varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
    `name`    varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
    `start`   int(11)                                NOT NULL,
    `finish`  int(11)                                NOT NULL,
    `status`  int(11) DEFAULT '0',
    `user_id` int(11)                                NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `publishers`
(
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `name`       varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `created_at` int(11)                                 DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `sub_highlights`
(
    `id`               int(11) NOT NULL AUTO_INCREMENT,
    `highlight_id`     int(11) NOT NULL,
    `sub_highlight_id` int(11) NOT NULL,
    `created`          int(11) NOT NULL,
    `updated`          int(11) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_bin;

CREATE TABLE `tag_relationships`
(
    `id`         int(11) NOT NULL AUTO_INCREMENT,
    `source_id`  int(11) NOT NULL,
    `tag_id`     int(11) NOT NULL,
    `type`       int(11) DEFAULT NULL,
    `created`    int(11) NOT NULL,
    `is_deleted` int(11) DEFAULT '0',
    `deleted_at` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `tags`
(
    `id`      int(11)                                 NOT NULL AUTO_INCREMENT,
    `tag`     varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created` int(11)                                 NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;

CREATE TABLE `users`
(
    `id`       int(11)                                 NOT NULL AUTO_INCREMENT,
    `username` varchar(45) COLLATE utf8mb4_unicode_ci  NOT NULL,
    `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `created`  int(11)                                 NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username_UNIQUE` (`username`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 666
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;