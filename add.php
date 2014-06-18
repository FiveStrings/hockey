<?php
$db = new mysqli('localhost', 'root', 'elias626', 'hockey2', 3306);

if (isset($_POST) && count($_POST)) {
	print "<pre>";
	print_r($_POST);
	print "</pre>";

	switch ($_POST['table']) {
		case 'team':
			$q = $db->query("INSERT INTO team (teamName) VALUES ('$_POST[teamName]')");
			if (!$q) printf("Error: %s\n", $db->error);
			break;
		case 'player':
			$q = $db->query("INSERT INTO player (teamID, number, name, lastName) VALUES ($_POST[teamID], '$_POST[number]', '$_POST[name]', '$_POST[lastName]')");
			if (!$q) printf("Error: %s\n", $db->error);
			break;
		case 'game':
			$rawDate = explode('-', $_POST['date']);
			$rawTime = explode('-', $_POST['time']);
			$theTime = mktime($rawTime[0], $rawTime[1], 0, $rawDate[1], $rawDate[2], $rawDate[0]);
			$q = $db->query("INSERT INTO game (gameTime, homeTeamID, awayTeamID, homeShots1, homeShots2, homeShots3, awayShots1, awayShots2, awayShots3, notes, homeGoalie, awayGoalie) VALUES ($theTime, $_POST[homeTeamID], $_POST[awayTeamID], $_POST[homeShots1], $_POST[homeShots2], $_POST[homeShots3], $_POST[awayShots1], $_POST[awayShots2], $_POST[awayShots3], '$_POST[notes]', '$_POST[homeGoalie]', '$_POST[awayGoalie]')");
			if (!$q) printf("Error: %s\n", $db->error);
			$gameID = $db->insert_id;

			foreach ($_POST['goals'] as $goal) {
				if (strlen($goal['period']) < 1) continue;
				$gMin = 15 - $goal['clockMin'];
				$gSec = 60 - $goal['clockSec'];
				$goalTime = ($gMin * 60) + ($gSec);
				$teamID = ($goal['home'] == 'yes') ? $_POST['homeTeamID'] : $_POST['awayTeamID'];
				$q = $db->query("INSERT INTO game_goal (gameID, teamID, period, goalTime, goalType, scorer, assist1, assist2) VALUES ($gameID, $teamID, $goal[period], $goalTime, '$goal[type]', '$goal[scorer]', '$goal[assist1]', '$goal[assist2]')");
				if (!$q) printf("Goals Error: %s\n", $db->error);
			}

			foreach ($_POST['pens'] as $pen) {
				if (strlen($pen['period']) < 1) continue;
				$gMin = 15 - $pen['clockMin'];
				$gSec = 60 - $pen['clockSec'];
				$penTime = ($gMin * 60) + ($gSec);
				$teamID = ($pen['home'] == 'yes') ? $_POST['homeTeamID'] : $_POST['awayTeamID'];
				$sql = "INSERT INTO game_penalty (gameID, teamID, player, period, infraction, minutes, penaltyTime) VALUES ($gameID, $teamID, '$pen[player]', '$pen[period]', '$pen[penalty]', $pen[minutes], $penTime)";
				$q = $db->query($sql);
				if (!$q) printf("Pens Error on $sql: %s\n", $db->error);
			}
			break;
	}
}

//BEGIN POST PROCESSING CODE
$teamSelect = '<option value="">Select Team</option>';
$q = $db->query("SELECT * FROM team");
if (!$q) printf("Error: %s\n", $db->error);
while ($row = $q->fetch_assoc()) {
	$teamSelect .= "<option value='$row[teamID]'>$row[teamName]</option>";
}

$gameSelect = '<option value="">Select Game</option>';
$q = $db->query("SELECT * FROM game");
if (!$q) printf("Error: %s\n", $db->error);
while ($row = $q->fetch_assoc()) {
	$theDate = date('Y-m-d h:i a', $row['gameTime']);
	$gameSelect .= "<option value='$row[gameID]'>$theDate</option>";
}

$playerSelect = '<option value="">Select Player</option>';
$q = $db->query("SELECT * FROM player LEFT JOIN team USING (teamID) ORDER BY CAST(number as unsigned), name, lastname, teamID");
if (!$q) printf("Error: %s\n", $db->error);
while ($row = $q->fetch_assoc()) {
	$thePlayer = "$row[number] $row[name] $row[lastName] ($row[teamName])";
	$playerSelect .= "<option value='$row[playerID]'>$thePlayer</option>";
}

$homeGoalRows = '';
$count = 1;
for ($ii = 1; $ii < 11; $ii++) {
	$homeGoalRows .= <<<ROWEND
		<tr>
			<td><input type="hidden" name="goals[$count][home]" value="yes"><input name="goals[$count][period]" size="3" type="text"></td>
			<td><input name="goals[$count][clockMin]" size="3" placeholder="Min" type="text"> : <input name="goals[$count][clockSec]" size="3" placeholder="Sec"></td>
			<td><input name="goals[$count][type]" size="3" type="text"></td>
			<td><select name="goals[$count][scorer]">$playerSelect</select></td>
			<td><select name="goals[$count][assist1]">$playerSelect</select></td>
			<td><select name="goals[$count][assist2]">$playerSelect</select></td>
		</tr>
ROWEND;
	$count++;
}
$awayGoalRows = '';
for ($ii = 1; $ii < 11; $ii++) {
	$awayGoalRows .= <<<ROWEND
		<tr>
			<td><input type="hidden" name="goals[$count][home]" value="no"><input name="goals[$count][period]" size="3" type="text"></td>
			<td><input name="goals[$count][clockMin]" size="3" placeholder="Min" type="text"> : <input name="goals[$count][clockSec]" size="3" placeholder="Sec"></td>
			<td><input name="goals[$count][type]" size="3" type="text"></td>
			<td><select name="goals[$count][scorer]">$playerSelect</select></td>
			<td><select name="goals[$count][assist1]">$playerSelect</select></td>
			<td><select name="goals[$count][assist2]">$playerSelect</select></td>
		</tr>
ROWEND;
	$count++;
}
$homePenRows = '';
for ($ii = 1; $ii < 11; $ii++) {
	$homePenRows .= <<<ROWEND
		<tr>
			<td><input type="hidden" name="pens[$count][home] value="yes"><input name="pens[$count][period]" size="3" type="text"></td>
			<td><input name="pens[$count][clockMin]" size="3" placeholder="Min" type="text"> : <input name="pens[$count][clockSec]" size="3" placeholder="Sec"></td>
			<td><input name="pens[$count][penalty]" type="text"></td>
			<td><select name="pens[$count][player]">$playerSelect</select></td>
			<td><input name="pens[$count][minutes]" size="3" type="text"></td>
		</tr>
ROWEND;
	$count++;
}
$awayPenRows = '';
for ($ii = 1; $ii < 11; $ii++) {
	$awayPenRows .= <<<ROWEND
		<tr>
			<td><input type="hidden" name="pens[$count][home] value="no"><input name="pens[$count][period]" size="3" type="text"></td>
			<td><input name="pens[$count][clockMin]" size="3" placeholder="Min" type="text"> : <input name="pens[$count][clockSec]" size="3" placeholder="Sec"></td>
			<td><input name="pens[$count][penalty]" type="text"></td>
			<td><select name="pens[$count][player]">$playerSelect</select></td>
			<td><input name="pens[$count][minutes]" size="3" type="text"></td>
		</tr>
ROWEND;
	$count++;
}


print <<<PAGEEND
<html>
<head>
<style type="text/css">
	table {
		border-collapse: collapse;
	}

	table td {
		vertical-align: top;
		margin: 0;
		padding: 3px;
		border: 1px solid black;
		text-align: center;
	}
	td.small {
		width: 37px;
		text-align: center;
	}

	.center { text-align: center; }
	.bold { font-weight: bold; }
	.border { border: 1px solid black; }
	.tdborders > tbody > tr > td { border: 1px solid black; }
</style>
</head>
<body>
<h2>Add Team</h2>
<form method="post" action="add.php">
<input type="hidden" name="table" value="team">
<table>
	<tr>
		<td>Name:</td>
		<td><input name="teamName" type="text"></td>
	</tr>
	<tr>
		<td colspan="2"><input type="submit"></td>
	</tr>
</table>
</form>

<h2>Add Player</h2>
<form method="post" action="add.php">
<input type="hidden" name="table" value="player">
<table>
	<tr>
		<td>Team:</td>
		<td><select name="teamID">$teamSelect</select></td>
	</tr>
	<tr>
		<td>Number:</td>
		<td><input name="number" type="text"></td>
	</tr>
	<tr>
		<td>First Name:</td>
		<td><input name="name" type="text"></td>
	</tr>
	<tr>
		<td>Last Name:</td>
		<td><input name="lastname" type="text"></td>
	</tr>
	<tr>
		<td colspan="2"><input type="submit"></td>
	</tr>
</table>
</form>

<h2>Add Game</h2>
<form method="post" action="add.php">
<input type="hidden" name="table" value="game">
<table>
	<tr>
		<td>Date (YYYY-MM-DD):</td>
		<td><input name="date" type="text"></td>
	</tr>
	<tr>
		<td>Time (HH-mm):</td>
		<td><input name="time" type="text"></td>
	</tr>
	<tr>
		<td>Home Team:</td>
		<td><select name="homeTeamID">$teamSelect</select></td>
	</tr>
	<tr>
		<td>Away Team:</td>
		<td><select name="awayTeamID">$teamSelect</select></td>
	</tr>
	<tr>
		<td>Home Shots:</td>
		<td><input placeholder="p1" name="homeShots1" size="3" type="text">
		<input placeholder="p1" name="homeShots2" size="3" type="text">
		<input placeholder="p1" name="homeShots3" size="3" type="text"></td>
	</tr>
	<tr>
		<td>Away Shots:</td>
		<td><input placeholder="p1" name="awayShots1" size="3" type="text">
		<input placeholder="p1" name="awayShots2" size="3" type="text">
		<input placeholder="p1" name="awayShots3" size="3" type="text"></td>
	</tr>
	<tr>
		<td>Notes:</td>
		<td><input name="notes" type="text"></td>
	</tr>
	<tr>
		<td>Home Goalie:</td>
		<td><select name="homeGoalie">$playerSelect</select></td>
	</tr>
	<tr>
		<td>Away Goalie:</td>
		<td><select name="awayGoalie">$playerSelect</select></td>
	</tr>
	<tr>
		<td>Home Goals:</td>
		<td>
			<table>
				<tr>
					<td>Period:</td>
					<td>Clock Time:</td>
					<td>Type:</td>
					<td>Scorer:</td>
					<td>Assist 1:</td>
					<td>Assist 2:</td>
				</tr>
				$homeGoalRows
			</table>
		</td>
	</tr>
	<tr>
		<td>Away Goals:</td>
		<td>
			<table>
				<tr>
					<td>Period:</td>
					<td>Clock Time:</td>
					<td>Type:</td>
					<td>Scorer:</td>
					<td>Assist 1:</td>
					<td>Assist 2:</td>
				</tr>
				$awayGoalRows
			</table>
		</td>
	</tr>
	<tr>
		<td>Home Penalties:</td>
		<td>
			<table>
				<tr>
					<td>Period:</td>
					<td>Clock Time:</td>
					<td>Infraction:</td>
					<td>Player:</td>
					<td>Minutes:</td>
				</tr>
				$homePenRows
			</table>
		</td>
	</tr>
	<tr>
		<td>Away Penalties:</td>
		<td>
			<table>
				<tr>
					<td>Period:</td>
					<td>Clock Time:</td>
					<td>Infraction:</td>
					<td>Player:</td>
					<td>Minutes:</td>
				</tr>
				$awayPenRows
			</table>
		</td>
	</tr>
	<tr>
		<td colspan="2"><input type="submit"></td>
	</tr>
</table>
</form>
</body>
</html>
PAGEEND;
?>
