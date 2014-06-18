<?php
$db = new mysqli('localhost', 'root', 'elias626', 'hockey2', 3306);
$q = $db->query("SELECT game.*, home.teamname homeTeam, away.teamName awayTeam, sum(if(game_goal.teamID=game.homeTeamID,1,0)) homeScore, sum(if(game_goal.teamID=game.awayTeamID,1,0)) awayScore  FROM game JOIN game_goal USING (gameID) JOIN team home ON (home.teamID = homeTeamID) JOIN team away ON (away.teamID = awayTeamID) GROUP BY gameID ORDER BY gameTime DESC");
if (!$q) printf("Error: %s\n", $db->error);
$gameRows = '';
while ($row = $q->fetch_assoc()) {
	$theDate = date('D Y-m-d', $row['gameTime']);
	$theTime = date('h:i:s a', $row['gameTime']);
	$theAT = $row['homeTeamID'] == 1 ? "&nbsp;" : "@";
	$theOpponent = $row['homeTeamID'] == 1 ? $row['awayTeam'] : $row['homeTeam'];
	$theWin = ($row['homeTeamID'] == 1) ? ($row['homeScore'] > $row['awayScore'] ? "W" : "L") : ($row['homeScore'] < $row['awayScore'] ? "W" : "L"); 
	$rbScore = $row['homeTeamID'] == 1 ? $row['homeScore'] : $row['awayScore'];
	$oppScore = $row['homeTeamID'] == 1 ? $row['awayScore'] : $row['homeScore'];

	$gameRows .= <<<ROWEND
		<tr>
			<td><a href="game.php?gameID=$row[gameID]">$theDate</a></td>
			<td>$theTime</td>
			<td>$theAT</td>
			<td>$theOpponent</td>
			<td>$theWin</td>
			<td>$rbScore</td>
			<td>$oppScore</td>
		</tr>
ROWEND;
}

$playerInfo = array();
$q = $db->query("SELECT player.*, concat(name, if(isnull(lastName) OR length(lastName) < 1, '', concat(' ', left(lastName,1), '.')), '') as name FROM player WHERE teamID = 1");
if (!$q) printf("Error: %s\n", $db->error);
while ($row = $q->fetch_assoc()) $playerInfo[$row['number']] = $row;

$sort = isset($_GET['sort']) ? $_GET['sort'].' DESC' : "CAST(number as unsigned) ASC";
$sql = <<<SQLEND
SELECT
	playerID,
	sum(if(stat="goal",count,0)) goals,
	sum(if(stat="assist",count,0)) assists,
	sum(if(stat="goal" OR stat="assist", count, 0)) points,
	sum(if(stat="minutes", count, 0)) minutes,
	sum(if(stat="ga", count, 0)) ga,
	sum(if(stat="sa", count, 0)) sa,
	sum(if(stat="wins", count, 0)) wins,
	sum(if(stat="losses", count, 0)) losses,
	player.number,
	concat(name, if(isnull(lastName) OR length(lastName) < 1, '', concat(' ', left(lastName,1), '.')), '') as name
FROM
	(SELECT scorer player, count(*) count, "goal" stat FROM game_goal where teamID = 1 group by scorer UNION
	SELECT assist1 player, count(*) count, "assist" stat FROM game_goal where teamID = 1 group by assist1 UNION
	SELECT assist2 player, count(*) count, "assist" stat FROM game_goal where teamID = 1 group by assist2 UNION
	SELECT player, sum(minutes) count, "minutes" stat FROM game_penalty WHERE teamID = 1 GROUP BY player UNION
	select awayGoalie, sum(if(game_goal.teamID=game.homeTeamID,1,0)) count, "ga" stat from game join game_goal on (game.gameID = game_goal.gameID) where awayTeamID = 1 group by awayGoalie UNION
	select awayGoalie, sum(homeShots1 + homeShots2 + homeShots3) count, "sa" stat from game where awayTeamID = 1 group by awayGoalie UNION
	select homeGoalie, sum(if(game_goal.teamID=game.awayTeamID,1,0)) count, "ga" stat from game join game_goal on (game.gameID = game_goal.gameID) where homeTeamID = 1 group by homeGoalie UNION
	select homeGoalie, sum(awayShots1 + awayShots2 + awayShots3) count, "sa" stat from game where homeTeamID = 1 group by homeGoalie UNION
	select awayGoalie, sum(if(gf>ga,1,0)) count, "wins" stat from (select awayGoalie, sum(if(game_goal.teamID=game.homeTeamID,1,0)) ga, sum(if(game_goal.teamID=game.awayTeamID,1,0)) gf from game join game_goal on (game.gameID = game_goal.gameID) where awayTeamID = 1 group by game.gameID) games group by awaygoalie UNION
	select awayGoalie, sum(if(gf<ga,1,0)) count, "losses" stat from (select awayGoalie, sum(if(game_goal.teamID=game.homeTeamID,1,0)) ga, sum(if(game_goal.teamID=game.awayTeamID,1,0)) gf from game join game_goal on (game.gameID = game_goal.gameID) where awayTeamID = 1 group by game.gameID) games group by awaygoalie UNION
	select homeGoalie, sum(if(gf>ga,1,0)) count, "wins" stat from (select homeGoalie, sum(if(game_goal.teamID=game.awayTeamID,1,0)) ga, sum(if(game_goal.teamID=game.homeTeamID,1,0)) gf from game join game_goal on (game.gameID = game_goal.gameID) where homeTeamID = 1 group by game.gameID) games group by homegoalie UNION
	select homeGoalie, sum(if(gf<ga,1,0)) count, "losses" stat from (select homeGoalie, sum(if(game_goal.teamID=game.awayTeamID,1,0)) ga, sum(if(game_goal.teamID=game.homeTeamID,1,0)) gf from game join game_goal on (game.gameID = game_goal.gameID) where homeTeamID = 1 group by game.gameID) games group by homegoalie
) stats
	LEFT JOIN player on (player.playerID = stats.player)
WHERE player.teamID = 1 GROUP BY player ORDER BY $sort;
SQLEND;
$q = $db->query($sql);
if (!$q) printf("Error: %s\n", $db->error);
$playerRows = '';
$goalieRows = '';
while ($row = $q->fetch_assoc()) {
	if ($row['points'] || $row['minutes']) {
		$playerRows .= <<<ROWEND
			<tr>
				<td><a href="player.php?playerID=$row[playerID]">$row[number]</a></td>
				<td>$row[name]</td>
				<td class="small">$row[goals]</td>
				<td class="small">$row[assists]</td>
				<td class="small">$row[points]</td>
				<td class="small">$row[minutes]</td>
			</tr>
ROWEND;
	}
	if ($row['ga']) {
		$sv = ($row['ga']) ? ($row['sa'] - $row['ga']) : 0;
		$svp = ($row['sa']) ? number_format($sv / $row['sa'], 3) : 0;
		$goalieRows .= <<<ROWEND
			<tr>
				<td><a href="player.php?player=$row[number]">$row[number]</a></td>
				<td>$row[name]</td>
				<td class="small">$row[wins]-$row[losses]</td>
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
</style>
</head>
<body>
<h1><img src="logo.png" style="height: 50px; margin-right: 15px;">Red Beards Stats System - Summer 2014</h1>
<h2>Games</h2>
<table cellspacing="0" cellpadding="0">
	<tr class="bold">
		<td>Date</td>
		<td>Time</td>
		<td>&nbsp</td>
		<td>Opponent</td>
		<td>&nbsp;</td>
		<td title="Red Beards' Score">Tm</td>
		<td title="Opponent Score">Opp</td>
	</tr>
	$gameRows
</table>
<hr>
<h2>Players</h2>
<table cellspacing="0" cellpadding="0">
	<tr class="bold">
		<td><a href="index.php">#</a></td>
		<td>Name</td>
		<td class="small"><a href="?sort=goals">G</a></td>
		<td class="small"><a href="?sort=assists">A</a></td>
		<td class="small"><a href="?sort=points">PTS</a></td>
		<td class="small"><a href="?sort=minutes">PIM</a></td>
	</tr>
	$playerRows
</table>
<h2>Goalies</h2>
<table cellspacing="0" cellpadding="0">
	<tr class="bold">
		<td><a href="index.php">#</a></td>
		<td>Name</td>
		<td class="small">W-L</td>
		<td class="small">GA</td>
		<td class="small">SA</td>
		<td class="small">SV</td>
		<td class="small">SV%</td>
	</tr>
	$goalieRows
</table>

<br><br><form action="feedback.php" method="post">Suggest a correction: <br><textarea name="correctionText" rows="5" cols="44" placeholder="type in the correction here"></textarea><br><input type="submit"></form>
</body>
</html>
PAGEEND;
?>
