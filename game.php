<?php
if (!isset($_GET['gameID'])) die('No game ID');

$db = new mysqli('localhost', 'root', 'elias626', 'hockey2', 3306);
$q = $db->query("SELECT game.*, hometeam.teamName homeTeam, awayteam.teamName awayTeam FROM game JOIN team hometeam on (hometeam.teamID = homeTeamID) JOIN team awayteam on (awayteam.teamID = awayTeamID) WHERE game.gameID = $_GET[gameID]");
if (!$q) printf("Error: %s\n", $db->error);
$gameInfo = $q->fetch_assoc();
$gameInfo['homeShotsT'] = $gameInfo['homeShots1'] + $gameInfo['homeShots2'] + $gameInfo['homeShots3'];
$gameInfo['awayShotsT'] = $gameInfo['awayShots1'] + $gameInfo['awayShots2'] + $gameInfo['awayShots3'];
$gameTime = date('Y-m-d h:i:s a', $gameInfo['gameTime']);

$playerInfo = array();
$q = $db->query("SELECT player.*, concat(name, if(isnull(lastName) OR length(lastName) < 1, '', concat(' ', left(lastName,1), '.')), '') as name FROM player WHERE teamID IN ($gameInfo[homeTeamID],$gameInfo[awayTeamID])");
if (!$q) printf("Error: %s\n", $db->error);
while ($row = $q->fetch_assoc()) $playerInfo[$row['playerID']] = $row;


$q = $db->query("SELECT * FROM game_goal WHERE gameID = $_GET[gameID] ORDER BY period ASC, goalTime ASC");
if (!$q) printf("Error: %s\n", $db->error);
$homePlayerRows = $awayPlayerRows = $homeScoringRows = $awayScoringRows = '';
$byPeriod = array(
	'homeScoringRows' => array('1'=>0, '2'=>0, '3'=>0, 'total'=>0),
	'awayScoringRows' => array('1'=>0, '2'=>0, '3'=>0, 'total'=>0),
);
while ($row = $q->fetch_assoc()) {
	$theVar = ($row['teamID'] == $gameInfo['homeTeamID']) ? 'homeScoringRows' : 'awayScoringRows';
	$goalTime = gmdate('i:s', $row['goalTime']);

	$scorer = $playerInfo[$row['scorer']];
	$assist1 = strlen($row['assist1']) ? $playerInfo[$row['assist1']] : array('number' => '-');
	$assist2 = strlen($row['assist2']) ? $playerInfo[$row['assist2']] : array('number' => '-');

	$scorerT = isset($scorer['name']) ? " title='$scorer[name]'" : '';
	$assist1T = isset($assist1['name']) ? " title='$assist1[name]'" : '';
	$assist2T = isset($assist2['name']) ? " title='$assist2[name]'" : '';

	$byPeriod[$theVar][$row['period']]++;
	$byPeriod[$theVar]['total']++;

	$$theVar .= <<<ROWEND
		<tr>
			<td class="small">$row[period]</td>
			<td class="medium">$goalTime</td>
			<td class="medium">$row[goalType]</td>
			<td class="small"$scorerT>$scorer[number]</td>
			<td class="small"$assist1T>$assist1[number]</td>
			<td class="small"$assist2T>$assist2[number]</td>
		</tr>
ROWEND;
}

$q = $db->query("SELECT * FROM game_penalty WHERE gameID = $_GET[gameID] ORDER BY period ASC, penaltyTime ASC");
if (!$q) printf("Error: %s\n", $db->error);
$homePenaltyRows = $awayPenaltyRows = '';
while ($row = $q->fetch_assoc()) {
	$theVar = ($row['teamID'] == $gameInfo['homeTeamID']) ? 'homePenaltyRows' : 'awayPenaltyRows';
	$penaltyTime = gmdate('i:s', $row['penaltyTime']);
	$pi = $playerInfo[$row['player']];
	$piT = isset($pi['name']) ? " title='$pi[name]'" : '';

	$$theVar .= <<<ROWEND
		<tr>
			<td class="small">$row[period]</td>
			<td class="medium">$penaltyTime</td>
			<td class="small"$piT>$pi[number]</td>
			<td class="large">$row[infraction]</td>
			<td class="small">$row[minutes]</td>
		</tr>
ROWEND;
}

$players = array();
$q = $db->query("SELECT * FROM game_goal WHERE gameID = $_GET[gameID]");
if (!$q) printf("Error: %s\n", $db->error);
while ($row = $q->fetch_assoc()) {
	if (strlen($row['scorer'])) {
		@$players[$row['teamID']][$row['scorer']]['goals'] += 1;
		@$players[$row['teamID']][$row['scorer']]['points'] += 1;
	}
	if (strlen($row['assist1'])) {
		@$players[$row['teamID']][$row['assist1']]['assists'] += 1;
		@$players[$row['teamID']][$row['assist1']]['points'] += 1;
	}
	if (strlen($row['assist2'])) {
		@$players[$row['teamID']][$row['assist2']]['assists'] += 1;
		@$players[$row['teamID']][$row['assist2']]['points'] += 1;
	}
}
$q = $db->query("SELECT * FROM game_penalty WHERE gameID = $_GET[gameID]");
if (!$q) printf("Error: %s\n", $db->error);
while ($row = $q->fetch_assoc()) {
	@$players[$row['teamID']][$row['player']]['pim'] += $row['minutes'];
}

foreach ($players as $teamID=>$info) {
	$theVar = ($teamID == $gameInfo['homeTeamID']) ? 'homePlayerRows' : 'awayPlayerRows';
	$numbers = array();
	foreach ($info as $playerID=>$player) {
		$info[$playerID]['playerID'] = $playerID;
		$numbers[$playerID] = $playerInfo[$playerID]['number'];
	}
	array_multisort($numbers, SORT_ASC, $info);
	foreach ($info as $player) {
		$number = $playerInfo[$player['playerID']]['number'];
		$playerName = $playerInfo[$player['playerID']]['name'];
		$goals = isset($player['goals']) ? $player['goals'] : 0;
		$assists = isset($player['assists']) ? $player['assists'] : 0;
		$points = isset($player['points']) ? $player['points'] : 0;
		$pim = isset($player['pim']) ? $player['pim'] : 0;
		$theLink = ($teamID == 1) ? "<a href='player.php?playerID=$player[playerID]'>$number</a>" : $number;
		$$theVar .= <<<ROWEND
			<tr>
				<td class="small">$theLink</td>
				<td class="center">$playerName</td>
				<td class="small">$goals</td>
				<td class="small">$assists</td>
				<td class="small">$points</td>
				<td class="small">$pim</td>
			</tr>
ROWEND;
	}
}

$homeGoalie = $playerInfo[$gameInfo['homeGoalie']];
$homeGA = $byPeriod['awayScoringRows']['total'];
$homeSA = $gameInfo['awayShotsT'];
$homeSV = $homeSA - $homeGA;
$homeSVP = number_format($homeSV / $homeSA, 3);

$awayGoalie = $playerInfo[$gameInfo['awayGoalie']];
$awayGA = $byPeriod['homeScoringRows']['total'];
$awaySA = $gameInfo['homeShotsT'];
$awaySV = $awaySA - $awayGA;
$awaySVP = number_format($awaySV / $awaySA, 3);
print <<<PAGEEND
<html>
<head>
<style type="text/css">
	table {
		border-collapse: collapse;
		width: 101%;
		margin: -1px;
	}

	table#outer {
		width: 910px;
		border: 1px solid black;
	}

	table#outer > tbody > tr > td {
		border: 3px solid black;
		padding: 0;
	}

	td.small {
		width: 37px;
		text-align: center;
	}

	td.medium {
		width: 70px;
		text-align: center;
	}

	td.large {
		width: 120px;
		text-align: center;
	}

	table td {
		vertical-align: top;
		margin: 0;
		padding: 2px;
		border: 1px solid black;
		text-align: center;
	}
	.center { text-align: center; }
	.bold { font-weight: bold; }
	.border { border: 1px solid black; }
	.tdborders > tbody > tr > td { border: 1px solid black; }
</style>
</head>
<body>
<a href="index.php">Back to Home</a>
<table id="outer" cellpadding="0" cellspacing="0">
	<tr>
		<td class="center" style="font-size: 18pt;" colspan="3">$gameInfo[awayTeam] @ $gameInfo[homeTeam] on $gameTime</td>
	</tr>
	<tr>
		<td style="width: 33%" id="homeScoring">
			<table cellpadding="0" cellspacing="0">
				<tr class="bold"><td colspan="6" class="center">$gameInfo[homeTeam] Scoring</td></tr>
				<tr class="bold">
					<td class="small">Per</td>
					<td class="medium">Time</td>
					<td class="medium">Type</td>
					<td class="small">G</td>
					<td class="small">A</td>
					<td class="small">A</td>
				</tr>
				$homeScoringRows
			</table>
		</td>
		<td style="width: 34%">
			<table cellpadding="0" cellspacing="0">
				<tr class="bold">
					<td class="center" colspan="3">Scoring By Period</td>
				</tr>
				<tr class="bold">
					<td class="medium">Period</td>
					<td class="large">$gameInfo[homeTeam]</td>
					<td class="large">$gameInfo[awayTeam]</td>
				</tr>
				<tr>
					<td class="medium">1</td>
					<td class="large">{$byPeriod['homeScoringRows'][1]}</td>
					<td class="large">{$byPeriod['awayScoringRows'][1]}</td>
				</tr>
				<tr>
					<td class="medium">2</td>
					<td class="large">{$byPeriod['homeScoringRows'][2]}</td>
					<td class="large">{$byPeriod['awayScoringRows'][2]}</td>
				</tr>
				<tr>
					<td class="medium">3</td>
					<td class="large">{$byPeriod['homeScoringRows'][3]}</td>
					<td class="large">{$byPeriod['awayScoringRows'][3]}</td>
				</tr>
				<tr class="bold">
					<td class="medium">Final</td>
					<td class="large">{$byPeriod['homeScoringRows']['total']}</td>
					<td class="large">{$byPeriod['awayScoringRows']['total']}</td>
				</tr>
			</table>
		</td>
		<td style="width: 33%">
			<table cellpadding="0" cellspacing="0">
				<tr class="bold"><td colspan="6">$gameInfo[awayTeam] Scoring</td></tr>
				<tr class="bold">
					<td class="small">Per</td>
					<td class="medium">Time</td>
					<td class="medium">Type</td>
					<td class="small">G</td>
					<td class="small">A</td>
					<td class="small">A</td>
				</tr>
				$awayScoringRows
			</table>
		</td>
	</tr>
	<tr>
		<td style="width: 33%">
			<table cellpadding="0" cellspacing="0">
				<tr class="bold"><td colspan="6">$gameInfo[homeTeam] Penalties</td></tr>
				<tr class="bold">
					<td class="small">Per</td>
					<td class="medium">Time</td>
					<td class="small">#</td>
					<td class="center">Penalty</td>
					<td class="small">Min</td>
				</tr>
				$homePenaltyRows
			</table>
		</td>
		<td style="width: 34%">
			<table cellpadding="0" cellspacing="0">
				<tr class="bold">
					<td colspan="3">Shots By Period</td>
				</tr>
				<tr class="bold">
					<td class="medium">Period</td>
					<td class="large">$gameInfo[homeTeam]</td>
					<td class="large">$gameInfo[awayTeam]</td>
				</tr>
				<tr>
					<td class="medium">1</td>
					<td class="large">$gameInfo[homeShots1]</td>
					<td class="large">$gameInfo[awayShots1]</td>
				</tr>
				<tr>
					<td class="medium">2</td>
					<td class="large">$gameInfo[homeShots2]</td>
					<td class="large">$gameInfo[awayShots2]</td>
				</tr>
				<tr>
					<td class="medium">3</td>
					<td class="large">$gameInfo[homeShots3]</td>
					<td class="large">$gameInfo[awayShots3]</td>
				</tr>
				<tr class="bold">
					<td class="medium">Final</td>
					<td class="large">$gameInfo[homeShotsT]</td>
					<td class="large">$gameInfo[awayShotsT]</td>
				</tr>
			</table>
		</td>
		<td style="width: 33%">
			<table cellpadding="0" cellspacing="0">
				<tr class="bold"><td colspan="6">$gameInfo[awayTeam] Penalties</td></tr>
				<tr class="bold">
					<td class="small">Per</td>
					<td class="medium">Time</td>
					<td class="small">#</td>
					<td class="center">Penalty</td>
					<td class="small">Min</td>
				</tr>
				$awayPenaltyRows
			</table>
		</td>
	</tr>
	<tr>
		<td colspan="3">
			<table id="outer" cellpadding="0" cellspacing="0">
				<tr>
					<td style="border: 0; width: 50%">
						<table cellpadding="0" cellspacing="0">
							<tr class="bold"><td colspan="6">$gameInfo[homeTeam] Players</td></tr>
							<tr class="bold">
								<td class="small">#</td>
								<td class="center">Name</td>
								<td class="small">G</td>
								<td class="small">A</td>
								<td class="small">PTS</td>
								<td class="small">PIM</td>
							</tr>
							$homePlayerRows
							<tr class="bold">
								<td class="center" colspan="2">Goalie</td>
								<td class="small">GA</td>
								<td class="small">SA</td>
								<td class="small">SV</td>
								<td class="small">SV%</td>
							</tr>
							<tr>
								<td>$homeGoalie[number]</td>
								<td>$homeGoalie[name]</td>
								<td>$homeGA</td>
								<td>$homeSA</td>
								<td>$homeSV</td>
								<td>$homeSVP</td>
							</tr>
						</table>
					</td>
					<td style="border: 0; border-left: 3px solid black; width: 50%">
						<table cellpadding="0" cellspacing="0" style="border-right: 0; width: 100%;">
							<tr class="bold"><td colspan="6">$gameInfo[awayTeam] Players</td></tr>
							<tr class="bold">
								<td class="small">#</td>
								<td class="center">Name</td>
								<td class="small">G</td>
								<td class="small">A</td>
								<td class="small">PTS</td>
								<td class="small">PIM</td>
							</tr>
							$awayPlayerRows
							<tr class="bold">
								<td class="center" colspan="2">Goalie</td>
								<td class="small">GA</td>
								<td class="small">SA</td>
								<td class="small">SV</td>
								<td class="small">SV%</td>
							</tr>
							<tr>
								<td>$awayGoalie[number]</td>
								<td>$awayGoalie[name]</td>
								<td>$awayGA</td>
								<td>$awaySA</td>
								<td>$awaySV</td>
								<td>$awaySVP</td>
							</tr>
						</table>
					</td>
				</tr>
			</table>
		</td>
</table>
$gameInfo[notes]
<br><br><form action="feedback.php" method="post">Suggest a correction: <br><textarea name="correctionText" rows="5" cols="44" placeholder="type in the correction here"></textarea><br><input type="submit"></form>
</body>
</html>
PAGEEND;
?>
