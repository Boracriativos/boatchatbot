<?php
class BoatChatAI_DB {
    public static function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'boat_chat_users';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$wpdb->prefix}boat_chat_users (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    session_id varchar(255) NOT NULL,
    name varchar(255) DEFAULT '',
    email varchar(255) DEFAULT '',
    phone varchar(20) DEFAULT '',
    listing_id mediumint(9) NOT NULL,
    owner_id mediumint(9) NOT NULL,
    chat_history longtext NOT NULL,
    state varchar(20) DEFAULT 'name',
    status enum('ai','human') DEFAULT 'ai',
    created_at datetime NOT NULL,
    PRIMARY KEY  (id),
    KEY session_id (session_id)
) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        add_option('boat_chat_ai_db_version', '1.0');
    }
}