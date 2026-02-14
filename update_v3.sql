CREATE TABLE IF NOT EXISTS `model_settings` (
    `model_name` varchar(255) NOT NULL,
    `layout_rotation` int(11) DEFAULT 0,
    `sector` varchar(255) DEFAULT NULL,
    PRIMARY KEY (`model_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `checklist_items` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `model_name` varchar(255) NOT NULL,
    `item_key` varchar(255) NOT NULL,
    `is_checked` tinyint(1) DEFAULT 0,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_item` (`model_name`, `item_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `layout_connections` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `model_name` varchar(255) NOT NULL,
    `from_op_id` varchar(255) NOT NULL,
    `to_op_id` varchar(255) NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
