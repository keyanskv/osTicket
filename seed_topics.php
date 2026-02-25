<?php
require('main.inc.php');
if(!defined('INCLUDE_DIR')) die('Fatal Error');

$topics = array(
    'AMC Calls', 'Biometric', 'Call Log', 'Desktop', 'Fiber', 
    'Firewall', 'Leave', 'Material delivery', 'Material taken', 
    'Networking', 'Remote Support', 'Server and Other', 
    'Site Survey', 'Surveillance', 'Wireless'
);

foreach ($topics as $topic_name) {
    if (!Topic::objects()->filter(array('topic' => $topic_name))->one()) {
        $topic = new Topic(array(
            'topic' => $topic_name,
            'isactive' => 1,
            'ispublic' => 1,
        ));
        if ($topic && $topic->save()) {
            echo "Created topic: $topic_name\n";
        } else {
            echo "Failed to create topic: $topic_name\n";
        }
    } else {
        echo "Topic already exists: $topic_name\n";
    }
}
?>
