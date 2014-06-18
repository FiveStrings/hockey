<?php
if (!isset($_POST['correctionText'])) die('No text');
require_once('../GMailer.php');
$ret = GMailer::send('chris.barranco@gmail.com', 'RB Hockey <cbwhatboxmanager@gmail.com>', 'Red Beards Stats Suggestion', $_POST['correctionText']);
if ($ret != 1) die('There was an error, tell Chris at the next game or e-mail him at <a href="mailto:chris.barranco@gmail.com">chris.barranco@gmail.com</a><br><br><br><a href="index.php">Back to Stats Site</a>');

print 'Your suggestion has been sent!<br><br><br><a href="index.php">Back to Stats Site</a>';
?>
