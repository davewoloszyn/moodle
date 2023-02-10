<?php

require_once('config.php');

$courseid = 5;
$course = get_course($courseid);
$comm = new \core_communication\communication_manager($course);

echo $comm->get_room_link();
