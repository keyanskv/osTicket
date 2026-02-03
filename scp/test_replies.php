<?php
require('admin.inc.php');

$sql = "SELECT 
    t.number AS ticket_number,
    t.created AS ticket_created,
    c.subject AS ticket_subject,
    CONCAT(s.firstname, ' ', s.lastname) AS agent_name,
    e.body AS reply_message,
    e.created AS reply_date
FROM ".THREAD_ENTRY_TABLE." e
LEFT JOIN ".STAFF_TABLE." s ON e.staff_id = s.staff_id
LEFT JOIN ".THREAD_TABLE." th ON e.thread_id = th.id
LEFT JOIN ".TICKET_TABLE." t ON th.object_id = t.ticket_id
LEFT JOIN ".TICKET_CDATA_TABLE." c ON t.ticket_id = c.ticket_id
WHERE e.type = 'R'
ORDER BY e.created DESC
LIMIT 5";

echo "Testing SQL: " . $sql . "\n\n";

$res = db_query($sql);
if (!$res) {
    die("Query Failed: " . db_error());
}

while ($row = db_fetch_array($res)) {
    print_r($row);
    echo "-------------------\n";
}

echo "Done.\n";
?>
