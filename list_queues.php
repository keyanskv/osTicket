<?php
require_once('main.inc.php');
$res = db_query("SELECT id, name FROM ost_final_lat_queue");
while($row = db_fetch_array($res)) {
    echo "ID: " . $row['id'] . " - Name: " . $row['name'] . "\n";
}
?>
