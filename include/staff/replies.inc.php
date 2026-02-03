<?php
$sql = "SELECT 
            t.number AS ticket_number,
            t.ticket_id,
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
        LIMIT 50";

$res = db_query($sql);
?>
<h2><?php echo __('Agent Replies'); ?></h2>
<table class="list" border="0" cellspacing="1" cellpadding="0" width="940">
    <thead>
        <tr>
            <th width="100"><?php echo __('Reply Date'); ?></th>
            <th width="80"><?php echo __('Ticket #'); ?></th>
            <th width="200"><?php echo __('Subject'); ?></th>
            <th width="120"><?php echo __('Agent'); ?></th>
            <th width="440"><?php echo __('Reply'); ?></th>
        </tr>
    </thead>
    <tbody>
    <?php
    if($res && db_num_rows($res)):
        while($row = db_fetch_array($res)) {
            $reply_date = Format::db_datetime($row['reply_date']);
            $ticket_number = $row['ticket_number'];
            $ticket_id = $row['ticket_id'];
            $subject = Format::htmlchars($row['ticket_subject']);
            $agent = Format::htmlchars($row['agent_name']);
            $body = Format::display($row['reply_message']);
            
            // Link to ticket
            $ticket_link = sprintf('<a href="tickets.php?id=%d">%s</a>', $ticket_id, $ticket_number);
            ?>
            <tr>
                <td><?php echo $reply_date; ?></td>
                <td><?php echo $ticket_link; ?></td>
                <td><?php echo $subject; ?> <br> <small class="faded"><?php echo Format::db_date($row['ticket_created']); ?></small></td>
                <td><?php echo $agent; ?></td>
                <td><div style="max-height: 100px; overflow: hidden;"><?php echo $body; ?></div></td>
            </tr>
            <?php
        }
    else:
        echo '<tr><td colspan="5">'.__('No replies found.').'</td></tr>';
    endif;
    ?>
    </tbody>
</table>
