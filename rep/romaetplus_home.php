<?php
  session_start();

  // redirect if not logged in
  if (!isset($_SESSION['uid'])) {
    header("Location: https://romaetplus.amdreier.com/login");
  }
  $uid = $_SESSION['uid'];
  $username = "Guest";
  if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
  }

  if (!isset($_SESSION['csrf_tok'])) {
    $_SESSION['csrf_tok'] = base64_encode(random_bytes(32));
  }

  // non-guest info & form handling
  if ($_SESSION['uid'] != -1 ) {
    /* SETUP SQL */
    $sql_servername = $_SERVER['DB_SERVER'];
    $sql_username = $_SERVER['DB_USER'];
    $sql_dbname = $_SERVER['DB_NAME'];
    $sql_password = $_SERVER['DB_PASS'];

    // Create connection
    $conn = new mysqli($sql_servername, $sql_username, $sql_password, $sql_dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['csrf_tok'] == $_SESSION['csrf_tok']) {
      if ($_POST['form_type'] == 'disc_token') {
        // SQL to insert generated token
        $insert_token_stmt = $conn->prepare("
          INSERT INTO Discord_Tokens (uid, token, disc_username, expires)
          VALUES (?, ?, ?, NOW() + INTERVAL 1 DAY)
          ON DUPLICATE KEY UPDATE
            token = VALUES(token),
            disc_username = VALUES(disc_username),
            expires = VALUES(expires)
        ");
        
        // get entered user data
        $discord_username = $_POST['discord_username'];

        // generate token
        $token = base64_encode(random_bytes(32));

        $insert_token_stmt->bind_param("iss", $uid, $token, $discord_username);
        $insert_token_stmt->execute();
        $insert_token_stmt->close();
      }

      if ($_POST['form_type'] == 'mc_link') {
        $mc_username = $_POST['mc_username'];
        $api_key = $_SERVER['API_KEY'];
        $url = "https://romaetplus.amdreier.com/api/sendMCLink";
        $data = ['uid' => "$uid", 'login_username' => "$username", 'mc_username' => "$mc_username", 'api_key' => "$api_key"];
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'ignore_errors' => true,
            ],
        ];
        $context = stream_context_create($options);
        $mc_send_link_res = file_get_contents($url, false, $context);
        if ($mc_send_link_res === false) {
            /* Handle error */
        }
      }
    }

    $check_disc_stmt = $conn->prepare("
        SELECT disc_username
        FROM Users
        WHERE uid=?
      ");
    $check_disc_stmt->bind_param("i", $uid);
    $check_disc_stmt->execute();
    $check_disc_row = $check_disc_stmt->get_result()->fetch_assoc();
    $check_disc_stmt->close();
    
    $disc_verified = !empty($check_disc_row['disc_username']);


    $check_mc_stmt = $conn->prepare("
        SELECT mc_username
        FROM Users
        WHERE uid=?
      ");
    $check_mc_stmt->bind_param("i", $uid);
    $check_mc_stmt->execute();
    $check_mc_row = $check_mc_stmt->get_result()->fetch_assoc();
    $check_mc_stmt->close();
    
    $mc_verified = !empty($check_mc_row['mc_username']);

    // DB done, close
    $conn->close();
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
  <link rel=“icon” href='https://romaetplus.amdreier.com/media/rep_icon.ico' type="image/x-icon">
  <link rel="shortcut icon" href='https://romaetplus.amdreier.com/media/rep_icon.ico' type="image/x-icon">
  <script>
    function getServerStatus() {
      fetch("https://romaetplus.amdreier.com/api/status")
      .then(response => response.text())
      .then(data => {
        document.getElementById("status").textContent = `Server Status: ${data}`;
      })
      .catch(error => {
        document.getElementById("status").textContent = "Error loading status.";
        console.error(error);
      });
    }

    document.addEventListener("DOMContentLoaded", () => {    
      getServerStatus();
    });
  </script>
</head>
<body>
  <?php include "navbar.php";?>

  <h1>This is the REP Home Page</h1>

  <p>Welcome <b><?= htmlspecialchars($username); ?></b>! <?= $uid == -1 ? "(Note that signing in as Guest is view-only and turns off certain website features.)" : "" ?></p>
  
  <?php if(isset($_SESSION['ip'])):?>
    <p>You can now log onto the server from this network.</p>
  <?php endif;?>

  <img class='rotate-on-hover' src='shared/media/server-icon.png'>

  <p id=status>Loading...</p>
  <button onclick="getServerStatus()">Refresh</button>

  <hr>

  <?php if ($_SESSION['uid'] != -1): ?>
    <?php if ($disc_verified):?>
      <p>
        Your Discord account is currently verified.
        If you ever want to change the Discord account linked with your login, just generate a new token below and use it in the /verify command 
        with the Whitelist-Bot on the Discord server.
      </p>
    <?php else:?>
      <b>
        Your Discord account is currently not verified! If you forget your password you might not be able to reset it!
        Generate a verification token below and use it in the /verify command with the Whitelist-Bot on the Discord server to verify your Discord account!
      </b>
    <?php endif;?>

    <form method="post">
      <div>
        <label for="Discord Username">Discord Username:</label>
        <input type="text" id="discord_username" name="discord_username" value="<?php if (isset($discord_username)) echo(htmlspecialchars($discord_username)); ?>" required>
      </div>
      <input type="hidden" id="form_type" name="form_type" value="disc_token">
      <input type="hidden" id="csrf_tok" name="csrf_tok" value="<?= htmlspecialchars($_SESSION['csrf_tok']); ?>">
      <br>
      <input type="submit" value="Generate new Discord verification token">
    </form>

    <?php if(isset($token)): ?>
      <p>Use this token to very your account on Discord (good for 24 hours): <?= htmlspecialchars($token);?></p>
    <?php endif; ?>

    <br>

    <?php if ($mc_verified): ?>
      <p>
        Your Minecraft account is currently verified.
        If you ever want to change the Minecraft account linked with your login, just generate a new link to be sent to you in-game below.
        You must be on the server to receive the link.
      </p>
    <?php else: ?>
      <b>
        Your Minecraft account is currently not verified!
        To link your account, type your exact Minecraft username below, go onto the server, then click 'Send verify link' below.
        Then just click the link that's sent to you in chat.
        You must be on the server to receive the link.
      </b>
    <?php endif;?>

    <form id="mc_link_form" method="post">
      <div>
        <label for="Minecraft Username">Minecraft Username:</label>
        <input type="text" id="mc_username" name="mc_username" value="<?php if (isset($mc_username)) echo(htmlspecialchars($mc_username));?>" required>
      </div>
      <input type="hidden" id="form_type" name="form_type" value="mc_link">
      <input type="hidden" id="csrf_tok" name="csrf_tok" value="<?= htmlspecialchars($_SESSION['csrf_tok']); ?>">
      <br>
      <input type="submit" value="Send verify link">
    </form>

    <?php if(isset($mc_send_link_res)): ?>
      <p id="mc_link_res"><?= htmlspecialchars($mc_send_link_res); ?></p>
    <?php endif;?>

  <?php else: ?>
    Signing in as <b>Guest</b> will not let you join the server or manage a Nation. To do that please: 
    <a href="https://romaetplus.amdreier.com/login">Login</a> or 
    <a href="https://romaetplus.amdreier.com/signup">Create an Account</a>
  <?php endif; ?>
</body>
</html>