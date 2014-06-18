<?php
$db = new mysqli('localhost', 'root', 'elias626', 'hockey2', 3306);
$q = $db->query("SELECT player.*, teamname FROM player LEFT JOIN team USING (teamID) ORDER BY teamID, number");
if (!$q) printf("Error: %s\n", $db->error);
print "<pre>";
while ($row = $q->fetch_assoc()) {
	print "#$row[number] $row[name] $row[lastName] ($row[teamname]) -> ID $row[playerID]\n";

}
?>
