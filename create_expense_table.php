<?php
require('main.inc.php');
if(!defined('INCLUDE_DIR')) die('Fatal Error');

$sql = "CREATE TABLE IF NOT EXISTS `".TABLE_PREFIX."ticket_expense` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ticket_id` int(11) NOT NULL,
    `staff_id` int(11) NOT NULL,
    `amount` decimal(10,2) NOT NULL,
    `description` text NOT NULL,
    `created` datetime NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;";

if (db_query($sql)) {
    echo "Table ticket_expense created successfully\n";
} else {
    echo "Error creating table: " . db_error() . "\n";
}
?>
