<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>chronyc</title>
<meta http-equiv='Refresh' content='5'/>
</head>
  <body>
    <?php
    $date = shell_exec('date');
    echo "<p>$date</p>";
    ?>
    <b>chronyc sources</b>
    <?php
    $sources = shell_exec('chronyc sources -v');
    echo "<pre>$sources</pre>";
    ?>
    <b>chronyc sourcestats</b>
    <?php
    $stats = shell_exec('chronyc sourcestats -v');
    echo "<pre>$stats</pre>";
    ?>
    <b>chronyc tracking</b>
    <?php
    $tracking = shell_exec('chronyc tracking');
    echo "<pre>$tracking</pre>";
    ?>
  </body>
</html>
