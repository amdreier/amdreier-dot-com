<?php
  require_once "Nation.php";
  require_once "Player.php";

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
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" type="text/css" href="styles.css">
  <link rel="stylesheet" type="text/css" href="shared/styles/sharedStyles.css">
  <title>Roma Et Plus Guide</title>
  <link rel=“icon” href='https://romaetplus.amdreier.com/media/rep_icon.ico' type="image/x-icon">
  <link rel="shortcut icon" href='https://romaetplus.amdreier.com/media/rep_icon.ico' type="image/x-icon">
  
</head>
<body>
  <?php include "navbar.php";?>
  <h1 id="Guide">Guide</h1>
  <p>Welcome to the REP Website Guide, <b style='color: #<?= $_SESSION['color'] ?? "000000" ?>'><?= htmlspecialchars($username)?></b>! <?= $uid == -1 ? "(Note that signing in as Guest is view-only and turns off certain website features.)" : "" ?></p>
  <p>This should help you understand how to use this website along with the Discord to manage your account on the server.</p>
  <hr>
  <h2 id="Verifying">Verifying</h2>
  <p>
    The first thing you'll see when you log in is the option to verify/link your Discord and Minecraft accounts to the server.
    This is necessary to be able to control the Nation you're in on the Discord and Minecraft servers.
  </p>
  <p>
    It's most important to verify your Discord account so that you can send yourself a password reset link if you ever forget your password.
    This link can't be sent unless you've done this!
  </p>
  <h3 id="Discord">Discord</h3>
  <p>
    To verify/link your Discord username, first type in your Discord username, exactly as it appears in your profile, then click "Generate new Discord verification token."
    This will return a random token, ending in a '='. Copy the whole token, including the '='.
  </p>
  <p>Next, go to the Discord server and type in /verify, followed by the username you used to log into this website, followed by the token you copied earlier.</p>
  <p>Your Discord account should now be verified! The Whitelist-bot will tell you if there were any issues verifying, and what might have gone wrong.</p>
  <h3 id="Minecraft">Minecraft</h3>
  <p>To verify/link your Minecraft username, first log onto the Minecraft server with the Minecraft account you want to link.</p>
  <p>Then, on this website, type in your Minecraft username, exactly as it appears in-game, and click "Send verify link".</p>
  <p>
    Finally, click the link sent to your chat in-game.
    This should bring you to a window telling you it worked, which will close automatically.
    Your Minecraft account should now be verified!
  </p>
  <h2 id="Nation_Manager">Nation Manager</h2>
  <p>
    The Nation Manager lets you join, create, and leave a nation.
    Each nation always has one leader who can remove people from their nation, as well as delete the nation, but this leader cannot leave the nation.
    The nation's leader can also transfer their leadership to any member.
  </p>
  <p>
    When you're not in a nation, you can join a nation, or create a new nation, where you'll automatically start as the leader.
  </p>
  <p>
    The name and color you give your nation will display on the website, Discord server, and in-game for all members.
    You chan choose to make your nation Invite Only or Open Invite.
    If a nation is Open Invite, anyone can join that nation. When a nation is Invite Only, member will need to send a join request, which will need to be approved by the nation's leader.
    The nation's leader can change if a nation is Open Invite or Invite Only at any time.
  </p>
  <p>
    The nation's leader is bolded and also has a (Leader) tag after their name.
    Your name and nation will appear with a '*' at the left, and will be italicized.
  </p>
  <p>
    The changes made on the Nation Manager will be reflected in-game for all players who have verified their Minecraft account, as well as on the Discord server for all players who have verified their Discord account.
  </p>
  <p>
    Coming soon: add sync option, changing country names, more leadership options, states within nations
  </p>
  <h2 id="Discord_Commands">Discord Commands</h2>
  <p>The Discord server has some useful commands to interact with the server as well:</p>
  <p>/verify we already mentioned, and it's used to verify/link your Discord account with this website.</p>
  <p>/reset-password will reply with a password reset link for the username entered for this website, but only if you've already used /verify to link your Discord account to that username.</p>
  <p>/whitelist will allow you to whitelist a player given their Minecraft username, optionally adding their Discord username to give them the 'Whitelisted' role.</p>
  <p>/help will give instructions on using the /whitelist command.</p>
  <p>/status will return the Minecraft server status, just like this site's homepage.</p>
</body>
<h2 id="Coming_Later">Coming Later:</h2>
<p>Nicer website design.</p>
</html>