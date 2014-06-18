<?php
if (!isset($_GET['playerID'])) die("Invalid Player");
$db = new mysqli('localhost', 'root', 'elias626', 'hockey2', 3306);

$q = $db->query("SELECT player.*, concat(name, ' ', left(lastName,1), '.') as name FROM player WHERE playerID = '$_GET[playerID]'");
if (!$q) printf("Error: %s\n", $db->error);
$playerInfo = $q->fetch_assoc();

$games = array();
$q = $db->query("SELECT game.*, home.teamname homeTeam, away.teamName awayTeam, sum(if(game_goal.teamID=game.homeTeamID,1,0)) homeScore, sum(if(game_goal.teamID=game.awayTeamID,1,0)) awayScore  FROM game JOIN game_goal USING (gameID) JOIN team home ON (home.teamID = homeTeamID) JOIN team away ON (away.teamID = awayTeamID) GROUP BY gameID ORDER BY gameTime DESC");
if (!$q) printf("Error: %s\n", $db->error);
while ($row = $q->fetch_assoc()) $games[$row['gameID']] = $row;

$sql = <<<SQLEND
	SELECT
		gameID,
		player,
		sum(if(stat="goal",count,0)) goals,
		sum(if(stat="assist",count,0)) assists,
		sum(if(stat="goal" OR stat="assist", count, 0)) points,
		sum(if(stat="minutes", count, 0)) minutes,
		sum(if(stat="ga", count, 0)) ga,
		sum(if(stat="sa", count, 0)) sa
	FROM
		(SELECT scorer player, count(*) count, "goal" stat, gameID FROM game_goal where teamID = 1 AND scorer = '$_GET[playerID]' group by gameID UNION
		SELECT assist1 player, count(*) count, "assist" stat, gameID FROM game_goal where teamID = 1 AND assist1 = '$_GET[playerID]' group by gameID UNION
		SELECT assist2 player, count(*) count, "assist" stat, gameID FROM game_goal where teamID = 1 AND assist2 = '$_GET[playerID]' group by gameID UNION
		SELECT player, sum(minutes) count, "minutes" stat, gameID FROM game_penalty WHERE teamID = 1 AND player = '$_GET[playerID]' GROUP BY gameID UNION
		select awayGoalie, sum(if(game_goal.teamID=game.homeTeamID,1,0)) count, "ga" stat, game.gameID from game join game_goal on (game.gameID = game_goal.gameID) WHERE awayTeamID = 1 AND awayGoalie = '$_GET[playerID]' group by gameID UNION
		select awayGoalie, sum(homeShots1 + homeShots2 + homeShots3) count, "sa" stat, game.gameID from game WHERE awayTeamID = 1 AND awayGoalie = '$_GET[playerID]' group by gameID UNION
		select homeGoalie, sum(if(game_goal.teamID=game.awayTeamID,1,0)) count, "ga" stat, game.gameID from game join game_goal on (game.gameID = game_goal.gameID) WHERE homeTeamID = 1 AND homeGoalie = '$_GET[playerID]' group by gameID UNION
		select homeGoalie, sum(awayShots1 + awayShots2 + awayShots3) count, "sa" stat, game.gameID from game WHERE homeTeamID = 1 AND homeGoalie = '$_GET[playerID]' group by gameID
	) stats join game using (gameID)
	GROUP BY gameID
	ORDER BY gameTime DESC
SQLEND;
$q = $db->query($sql);
if (!$q) printf("Error: %s\n", $db->error);

$gameRows = $goalieRows = '';
$gameHeader = $goalieHeader = 'hidden';
while ($row = $q->fetch_assoc()) {
	$row = array_merge($row, $games[$row['gameID']]);
	$theDate = date('D Y-m-d', $row['gameTime']);
	$theTime = date('h:i:s a', $row['gameTime']);
	$theAT = $row['homeTeamID'] == 1 ? "&nbsp;" : "@";
	$theOpponent = $row['homeTeamID'] == 1 ? $row['awayTeam'] : $row['homeTeam'];
	$theWin = ($row['homeTeamID'] == 1) ? ($row['homeScore'] > $row['awayScore'] ? "W" : "L") : ($row['homeScore'] < $row['awayScore'] ? "W" : "L"); 
	$rbScore = $row['homeTeamID'] == 1 ? $row['homeScore'] : $row['awayScore'];
	$oppScore = $row['homeTeamID'] == 1 ? $row['awayScore'] : $row['homeScore'];

	if ($row['points'] || $row['minutes']) {
		$gameHeader = '';
		$gameRows .= <<<ROWEND
			<tr>
				<td><a href="game.php?gameID=$row[gameID]">$theDate</a></td>
				<td>$theTime</td>
				<td>$theAT</td>
				<td>$theOpponent</td>
				<td>$theWin</td>
				<td>$rbScore</td>
				<td>$oppScore</td>
				<td class="small">$row[goals]</td>
				<td class="small">$row[assists]</td>
				<td class="small">$row[points]</td>
				<td class="small">$row[minutes]</td>
			</tr>
ROWEND;
	}
	if ($row['ga']) {
		$goalieHeader = '';
		$sv = ($row['ga']) ? ($row['sa'] - $row['ga']) : 0;
		$svp = ($row['sa']) ? number_format($sv / $row['sa'], 3) : 0;
		$goalieRows .= <<<ROWEND
			<tr>
				<td><a href="game.php?gameID=$row[gameID]">$theDate</a></td>
				<td>$theTime</td>
				<td>$theAT</td>
				<td>$theOpponent</td>
				<td>$theWin</td>
				<td>$rbScore</td>
				<td>$oppScore</td>
				<td class="small">$row[ga]</td>
				<td class="small">$row[sa]</td>
				<td class="small">$sv</td>
				<td class="small">$svp</td>
			</tr>
ROWEND;
	}
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
	.hidden { display: none;} 
</style>
</head>
<body>
<h1><img src="logo.png" style="height: 50px; margin-right: 15px;">Red Beards Stats System - Summer 2014</h1>
<a href="index.php">Back to Home</a>
<h2>$playerInfo[name] (#$playerInfo[number])</h1>
<div class="$gameHeader">
<h2>Player Stats</h2>
<table cellspacing="0" cellpadding="0">
	<tr class="bold">
		<td>Date</td>
		<td>Time</td>
		<td>&nbsp</td>
		<td>Opponent</td>
		<td>&nbsp;</td>
		<td title="Red Beards' Score">Tm</td>
		<td title="Opponent Score">Opp</td>
		<td class="small"><a href="?sort=goals">G</a></td>
		<td class="small"><a href="?sort=assists">A</a></td>
		<td class="small"><a href="?sort=points">PTS</a></td>
		<td class="small"><a href="?sort=minutes">PIM</a></td>
	</tr>
	$gameRows
</table></div>

<div class="$goalieHeader">
<h2>Goalie Stats</h2>
<table cellspacing="0" cellpadding="0">
	<tr class="bold">
		<td>Date</td>
		<td>Time</td>
		<td>&nbsp</td>
		<td>Opponent</td>
		<td>&nbsp;</td>
		<td title="Red Beards' Score">Tm</td>
		<td title="Opponent Score">Opp</td>
		<td class="small"><a href="?sort=ga">GA</a></td>
		<td class="small"><a href="?sort=sa">SA</a></td>
		<td class="small"><a href="?sort=sv">SV</a></td>
		<td class="small"><a href="?sort=svp">SV%</a></td>
	</tr>
	$goalieRows
</table>
</div>
<br><br><form action="feedback.php" method="post">Suggest a correction: <br><textarea name="correctionText" rows="5" cols="44" placeholder="type in the correction here"></textarea><br><input type="submit"></form>
</body>
</html>
PAGEEND;
?>
