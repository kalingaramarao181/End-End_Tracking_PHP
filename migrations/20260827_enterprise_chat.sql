-- Enterprise chat (MySQL 8/InnoDB). Apply after enterprise RBAC migrations.
CREATE TABLE IF NOT EXISTS chat_conversations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,type ENUM('direct','group','contextual') NOT NULL,name VARCHAR(160),description VARCHAR(500),created_by INT UNSIGNED NOT NULL,direct_key VARCHAR(64),status ENUM('active','locked','archived') DEFAULT 'active',last_message_id BIGINT UNSIGNED,last_message_at DATETIME,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_direct(direct_key),KEY idx_recent(last_message_at,id),FOREIGN KEY(created_by) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS chat_contexts(conversation_id BIGINT UNSIGNED PRIMARY KEY,context_type VARCHAR(50) NOT NULL,context_id VARCHAR(80) NOT NULL,context_title VARCHAR(200) NOT NULL,context_url VARCHAR(500),UNIQUE KEY uq_context(context_type,context_id),FOREIGN KEY(conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS chat_members(conversation_id BIGINT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,role ENUM('owner','admin','member') DEFAULT 'member',status ENUM('active','left','removed') DEFAULT 'active',joined_at DATETIME DEFAULT CURRENT_TIMESTAMP,last_read_message_id BIGINT UNSIGNED,last_read_at DATETIME,muted_at DATETIME,archived_at DATETIME,pinned_at DATETIME,PRIMARY KEY(conversation_id,user_id),KEY idx_member_user(user_id,status),FOREIGN KEY(conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS chat_conversation_messages(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,conversation_id BIGINT UNSIGNED NOT NULL,sender_id INT UNSIGNED NOT NULL,client_id CHAR(36),type ENUM('text','system') DEFAULT 'text',body TEXT,reply_to_id BIGINT UNSIGNED,status ENUM('active','deleted','moderated') DEFAULT 'active',created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,edited_at DATETIME,deleted_at DATETIME,UNIQUE KEY uq_retry(sender_id,client_id),KEY idx_message_page(conversation_id,id),FULLTEXT KEY ft_body(body),FOREIGN KEY(conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,FOREIGN KEY(sender_id) REFERENCES users(id),FOREIGN KEY(reply_to_id) REFERENCES chat_conversation_messages(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS chat_mentions(message_id BIGINT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,PRIMARY KEY(message_id,user_id),FOREIGN KEY(message_id) REFERENCES chat_conversation_messages(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS chat_reactions(message_id BIGINT UNSIGNED NOT NULL,user_id INT UNSIGNED NOT NULL,reaction VARCHAR(16) NOT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(message_id,user_id,reaction),FOREIGN KEY(message_id) REFERENCES chat_conversation_messages(id) ON DELETE CASCADE,FOREIGN KEY(user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS chat_notifications(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,actor_id INT UNSIGNED,conversation_id BIGINT UNSIGNED,message_id BIGINT UNSIGNED,type VARCHAR(50) NOT NULL,title VARCHAR(180) NOT NULL,body VARCHAR(500),destination_url VARCHAR(500),dedupe_key VARCHAR(160),is_read TINYINT(1) DEFAULT 0,read_at DATETIME,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_notice(user_id,dedupe_key),KEY idx_notice(user_id,is_read,created_at),FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,FOREIGN KEY(actor_id) REFERENCES users(id) ON DELETE SET NULL,FOREIGN KEY(conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE,FOREIGN KEY(message_id) REFERENCES chat_conversation_messages(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- MySQL 5.7/MariaDB-compatible, idempotent delivery receipt columns.
SET @chat_add_delivered_message_id = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'chat_members'
              AND COLUMN_NAME = 'last_delivered_message_id'
        ),
        'SELECT 1',
        'ALTER TABLE chat_members ADD COLUMN last_delivered_message_id BIGINT UNSIGNED NULL AFTER joined_at'
    )
);
PREPARE chat_add_delivered_message_id_stmt FROM @chat_add_delivered_message_id;
EXECUTE chat_add_delivered_message_id_stmt;
DEALLOCATE PREPARE chat_add_delivered_message_id_stmt;

SET @chat_add_delivered_at = (
    SELECT IF(
        EXISTS(
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'chat_members'
              AND COLUMN_NAME = 'last_delivered_at'
        ),
        'SELECT 1',
        'ALTER TABLE chat_members ADD COLUMN last_delivered_at DATETIME NULL AFTER last_delivered_message_id'
    )
);
PREPARE chat_add_delivered_at_stmt FROM @chat_add_delivered_at;
EXECUTE chat_add_delivered_at_stmt;
DEALLOCATE PREPARE chat_add_delivered_at_stmt;
INSERT IGNORE INTO resources(resource_name,display_name,icon,route,component_key,resource_type,sort_order,status) VALUES('chat','Chat','comments','/dashboard/chat','chat','PAGE',70,'Active'),('chat_notifications','Notifications','bell','/dashboard/notifications','chat-notifications','PAGE',71,'Active'),('chat_admin','Chat Management','shield','/dashboard/admin/chat','chat-admin','PAGE',170,'Active');
INSERT INTO permissions(position_id,resource_id,can_view,can_create,can_edit,can_delete,can_manage,data_scope) SELECT 1,id,1,1,1,1,1,'ALL' FROM resources WHERE resource_name IN('chat','chat_notifications','chat_admin') ON DUPLICATE KEY UPDATE can_view=1,can_create=1,can_edit=1,can_delete=1,can_manage=1,data_scope='ALL';
