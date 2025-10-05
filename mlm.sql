SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `chats` (
  `chat_id` int UNSIGNED NOT NULL,
  `chat_name` varchar(100) NOT NULL,
  `avatar_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'components/media/images/chat.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `chat_bans` (
  `chat_ban_id` int UNSIGNED NOT NULL,
  `chat_id` int UNSIGNED NOT NULL,
  `banned_user_id` int UNSIGNED NOT NULL,
  `banner_user_id` int UNSIGNED NOT NULL,
  `ban_reason` varchar(255) DEFAULT NULL,
  `ban_start_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ban_end_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `messages` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `chat_id` int UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `post_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reply_to` int DEFAULT NULL,
  `reply_snapshot` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `message_media` (
  `id` int UNSIGNED NOT NULL,
  `message_id` int UNSIGNED NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `is_deleted` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `users` (
  `user_id` int UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `avatar_url` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'components/media/images/user.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `user_bans` (
  `ban_id` int UNSIGNED NOT NULL,
  `banned_user_id` int UNSIGNED NOT NULL,
  `banner_user_id` int UNSIGNED DEFAULT NULL,
  `ban_reason` varchar(255) DEFAULT NULL,
  `ban_start_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ban_end_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE `user_chats` (
  `user_id` int UNSIGNED NOT NULL,
  `chat_id` int UNSIGNED NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


ALTER TABLE `chats`
  ADD PRIMARY KEY (`chat_id`),
  ADD UNIQUE KEY `chat_name` (`chat_name`);

ALTER TABLE `chat_bans`
  ADD PRIMARY KEY (`chat_ban_id`),
  ADD UNIQUE KEY `idx_active_chat_ban` (`chat_id`,`banned_user_id`,`is_active`),
  ADD KEY `fk_cb_banned_user` (`banned_user_id`),
  ADD KEY `fk_cb_banner_user` (`banner_user_id`);

ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `chat_id` (`chat_id`),
  ADD KEY `reply_to` (`reply_to`);

ALTER TABLE `message_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_message_id` (`message_id`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`);

ALTER TABLE `user_bans`
  ADD PRIMARY KEY (`ban_id`),
  ADD UNIQUE KEY `idx_active_ban` (`banned_user_id`,`is_active`),
  ADD KEY `fk_banned_user` (`banned_user_id`),
  ADD KEY `fk_banner_user` (`banner_user_id`);

ALTER TABLE `user_chats`
  ADD PRIMARY KEY (`user_id`,`chat_id`),
  ADD KEY `chat_id` (`chat_id`);


ALTER TABLE `chats`
  MODIFY `chat_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `chat_bans`
  MODIFY `chat_ban_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `messages`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `message_media`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `users`
  MODIFY `user_id` int UNSIGNED NOT NULL AUTO_INCREMENT;

ALTER TABLE `user_bans`
  MODIFY `ban_id` int UNSIGNED NOT NULL AUTO_INCREMENT;


ALTER TABLE `chat_bans`
  ADD CONSTRAINT `fk_cb_banned_user` FOREIGN KEY (`banned_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cb_banner_user` FOREIGN KEY (`banner_user_id`) REFERENCES `users` (`user_id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_cb_chat` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`chat_id`) ON DELETE CASCADE;

ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`chat_id`);

ALTER TABLE `message_media`
  ADD CONSTRAINT `message_media_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_bans`
  ADD CONSTRAINT `fk_banned_user` FOREIGN KEY (`banned_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_banner_user` FOREIGN KEY (`banner_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

ALTER TABLE `user_chats`
  ADD CONSTRAINT `user_chats_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_chats_ibfk_2` FOREIGN KEY (`chat_id`) REFERENCES `chats` (`chat_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
