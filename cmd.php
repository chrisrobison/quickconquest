<?php

$mysqli = new mysqli("localhost", "mail", "activate", "quickconquest");
$result = $mysqli->query("SELECT * FROM player");

$players = array();
while ($row = $result->fetch_assoc()) {
	$players[] = $row;
}

print_r($players);

?>
