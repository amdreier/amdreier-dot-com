<?php
  session_start();

  // redirect if not logged in
  if (!isset($_SESSION['uid'])) {
    header("Location: https://romaetplus.amdreier.com/login");
  }
  $username = "Guest";
  if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" type="text/css" href="styles.css">
  <link rel="stylesheet" type="text/css" href="shared/styles/sharedStyles.css">
  <title>Roma Et Plus Home Page</title>
</head>
<body>
  <h1>This is the REP Home Page</h1>
  <p>Welcome <?php echo(htmlspecialchars($username)); ?></p>
  <?php if(isset($_SESSION['ip'])) { ?>
  <p>You can now log into the server from your current IP address: <?php echo(htmlspecialchars($_SESSION['ip'])); ?></p>
  <?php } ?>
  <img class='rotate-on-hover' src='shared/media/server-icon.png'>
  <br>
  <a href="https://romaetplus.amdreier.com/logout">Logout</a>
</body>
</html>