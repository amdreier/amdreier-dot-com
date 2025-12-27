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

  if (!isset($_SESSION['csrf_tok'])) {
    $_SESSION['csrf_tok'] = base64_encode(random_bytes(32));
  }

  $nid = -1;
  $nation = "";
  $leader = "";
  $has_nation = false;
  $is_leader = false;

  $val_change = false;

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

  // non-guest info & form handling
  if ($_SESSION['uid'] != -1 ) {
    $check_nation_stmt = $conn->prepare("
      SELECT n.nid, n.n_name, n.n_leader_id
      FROM Users u JOIN Nations n ON u.u_nation_id = n.nid
      WHERE uid=?
    ");
    $check_nation_stmt->bind_param("i", $uid);
    $check_nation_stmt->execute();
    $check_nation_res = $check_nation_stmt->get_result();
    $check_nation_row = $check_nation_res->fetch_assoc();

    if ($check_nation_res->num_rows > 0) {
      $has_nation = true;
      $nid = $check_nation_row['nid'];
      $nation = $check_nation_row['n_name'];
      $leader_id = $check_nation_row['n_leader_id'];
      $is_leader = ($leader_id == $uid);
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['csrf_tok'] == $_SESSION['csrf_tok']) {
      if ($_POST['form_type'] == "leader_action") {
        $member_uid = $_POST['member_uid'] ?? $uid;

        $check_nation_stmt->bind_param("i", $member_uid);
        $check_nation_stmt->execute();
        $check_nation_res = $check_nation_stmt->get_result();
        $check_nation_row = $check_nation_res->fetch_assoc();

        if ($is_leader && $check_nation_row['nid'] == $nid && $member_uid != $uid) {
          if ($_POST['action'] == "Kick") {
            $nation_kick_stmt = $conn->prepare("
              UPDATE Users
              SET u_nation_id = NULL
              WHERE uid=?
            ");
            $nation_kick_stmt->bind_param("i", $member_uid);
            $nation_kick_stmt->execute();
            $nation_kick_stmt->close();

            $val_change = true;
          }

          if ($_POST['action'] == "Make Leader") {
            $change_leader_stmt = $conn->prepare("
              UPDATE Nations
              SET n_leader_id = ?
              WHERE nid = ?
            ");
            $change_leader_stmt->bind_param("ii", $member_uid, $nid);
            $change_leader_stmt->execute();
            $change_leader_stmt->close();

            $val_change = true;
          }
        }
      }

      if ($_POST['form_type'] == "member_action") {
        if ($_POST['action'] == "Leave") {
          $nation_leave_stmt = $conn->prepare("
            UPDATE Users
            SET u_nation_id = NULL
            WHERE uid = ?
          ");
          $nation_leave_stmt->bind_param("i", $uid);
          $nation_leave_stmt->execute();
          $nation_leave_stmt->close();

          $val_change = true;
        }
      }

      if ($_POST['form_type'] == "nation_action") {
        $nation_nid = $_POST['nation_nid'];

        if ($_POST['action'] == "Join") {
          $nation_join_stmt = $conn->prepare("
            UPDATE Users
            SET u_nation_id = ?
            WHERE uid = ?
          ");
          $nation_join_stmt->bind_param("ii", $nation_nid, $uid);
          $nation_join_stmt->execute();
          $nation_join_stmt->close();
          
          $val_change = true;
        }
      }

      if ($_POST['form_type'] == "create_nation") {
        $nation_name = $_POST['name'];

        $nation_check_stmt = $conn->prepare("
          SELECT n_name
          FROM Nations
          WHERE n_name = ?
        ");
        $nation_check_stmt->bind_param("s", $nation_name);
        $nation_check_stmt->execute();
        $nation_check_res = $nation_check_stmt->get_result();
        $nation_check_stmt->close();

        $nation_check_fail = false;

        if ($nation_check_res->num_rows > 0) {
          $nation_check_fail = true;
        } else {
          $new_nid = $conn->query("SELECT MAX(nid) AS 'max_nid' FROM Nations")->fetch_assoc()['max_nid'] + 1;

          $nation_create_stmt = $conn->prepare("
            INSERT INTO Nations
              (nid, n_name, n_leader_id)
            VALUES
              (?, ?, ?)
          ");
          $nation_create_stmt->bind_param("isi", $new_nid, $nation_name, $uid);
          $nation_create_stmt->execute();
          $nation_create_stmt->close();

          $join_nation_stmt = $conn->prepare("
            UPDATE Users
            SET u_nation_id = ?
            WHERE uid = ?
          ");

          $join_nation_stmt->bind_param("ii", $new_nid, $uid);
          $join_nation_stmt->execute();
          $join_nation_stmt->close();

          $val_change = true;
        }
      }

      if ($_POST['form_type'] == "delete_nation" && $is_leader) {
        $kick_all_stmt = $conn->prepare("
          WITH In_Nation AS (
            SELECT uid
            FROM Users
            WHERE u_nation_id = ?
          )  
          UPDATE Users
          SET u_nation_id = NULL
          WHERE uid IN (SELECT uid FROM In_Nation)
        ");
        $kick_all_stmt->bind_param("i", $nid);
        $kick_all_stmt->execute();
        $kick_all_stmt->close();

        $dlt_nation_stmt = $conn->prepare("
          DELETE FROM Nations
          WHERE nid = ?
        ");
        $dlt_nation_stmt->bind_param("i", $nid);
        $dlt_nation_stmt->execute();
        $dlt_nation_stmt->close();
          
        $val_change = true;
      }
    }

    if ($val_change) {
      // Recheck for changed values
      $nid = -1;
      $nation = "";
      $leader = "";
      $has_nation = false;
      $is_leader = false;

      $check_nation_stmt->bind_param("i", $uid);
      $check_nation_stmt->execute();
      $check_nation_res = $check_nation_stmt->get_result();
      $check_nation_row = $check_nation_res->fetch_assoc();

      if ($check_nation_res->num_rows > 0) {
        $has_nation = true;
        $nid = $check_nation_row['nid'];
        $nation = $check_nation_row['n_name'];
        $leader_id = $check_nation_row['n_leader_id'];
        $is_leader = ($leader_id == $uid);
      }
    }

    $check_nation_stmt->close();
  }

  // Query nation and member info
  $get_nations_stmt = $conn->prepare("
    SELECT *
    FROM Users u JOIN Nations n ON u.uid = n.n_leader_id
    ORDER BY n.nid
  ");
  $get_nations_stmt->execute();
  $get_nations_res = $get_nations_stmt->get_result();
  $get_nations_stmt->close();

  $get_members_stmt = $conn->prepare("
    SELECT *
    FROM Users u JOIN Nations n ON u.u_nation_id = n.nid
    ORDER BY n.nid
  ");
  $get_members_stmt->execute();
  $get_members_res = $get_members_stmt->get_result();
  $get_members_stmt->close();

  // Build nation objects from results
  $nations = [];

  while ($row = $get_nations_res->fetch_assoc()) {
    $nation = new Nation();

    $leader = new Player();
    $leader->uid = htmlspecialchars($row['n_leader_id']);
    $leader->username = htmlspecialchars($row['username']);
    $leader->disc_username = htmlspecialchars($row['disc_username']);
    $leader->disc_uid = htmlspecialchars($row['disc_uid']);
    $leader->mc_username = htmlspecialchars($row['mc_username']);
    $leader->nation = $nation;

    $nation->leader = $leader;
    $nation->name = htmlspecialchars($row['n_name']);
    $nation->nid = htmlspecialchars($row['nid']);
    $nation->members = [];

    $nations[$nation->nid] = $nation;
  }

  while ($row = $get_members_res->fetch_assoc()) {
    $player = new Player();
    $player->uid = htmlspecialchars($row['uid']);
    $player->username = htmlspecialchars($row['username']);
    $player->disc_username = htmlspecialchars($row['disc_username']);
    $player->disc_uid = htmlspecialchars($row['disc_uid']);
    $player->mc_username = htmlspecialchars($row['mc_username']);
    $player->nation = $nations[$row['nid']];

    $player->nation->members[] = $player;
  }

  // DB done, close
  $conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" type="text/css" href="styles.css">
  <link rel="stylesheet" type="text/css" href="shared/styles/sharedStyles.css">
  <title>Roma Et Plus Nation Manager</title>
  <link rel=“icon” href='https://romaetplus.amdreier.com/media/rep_icon.ico' type="image/x-icon">
  <link rel="shortcut icon" href='https://romaetplus.amdreier.com/media/rep_icon.ico' type="image/x-icon">
  
</head>
<body>
  <?php include "navbar.php";?>
  <h1>Nation Manager</h1>
  <p>Welcome to the REP Nation Manager, <b><?= htmlspecialchars($username)?></b>! <?= $uid == -1 ? "(Note that signing in as Guest is view-only and turns off certain website features.)" : "" ?></p>
  <hr>
  <?php foreach ($nations as $nation):?>
    <?php if ($nation->nid == $nid): ?>
      <h2>
        <i><?= "*$nation->name" ?></i>
        <?php if ($is_leader) :?>
          <form method='post' class='nations-action-form'>
            <input type='hidden' id='form_type' name='form_type' value='delete_nation'>
            <input type='hidden' id='csrf_tok' name='csrf_tok' value='<?= $_SESSION['csrf_tok'] ?>'>
            <input type='submit' id='Delete' name='action' value='Delete'>
          </form>
        <?php elseif ($uid != -1): ?>
          <form method='post' class='nations-action-form'>
            <input type='hidden' id='form_type' name='form_type' value='member_action'>
            <input type='hidden' id='csrf_tok' name='csrf_tok' value='<?= $_SESSION['csrf_tok'] ?>'>
            <input type='submit' id='Leave' name='action' value='Leave'>
          </form>
        <?php endif;?>
      </h2>
      <?php foreach ($nation->members as $member):?>
        <div>
        <?php if ($member->uid == $nation->leader->uid): ?>
          <p style='display: inline-block;'><b> <?= $member->uid == $uid ? "<i>*$member->username</i>" : $member->username?> (Leader)</b></p>
        <?php else: ?>
          <p style='display: inline-block;'> <?= $member->uid == $uid ? "<i>*$member->username</i>" : $member->username?> </p>
            <?php if ($is_leader): ?> 
              <form method='post' class='nations-action-form'>
                <input type='hidden' id='form_type' name='form_type' value='leader_action'>
                <input type='hidden' id='member_uid' name='member_uid' value='<?= $member->uid ?>'>
                <input type='hidden' id='csrf_tok' name='csrf_tok' value='<?= $_SESSION['csrf_tok'] ?>'>
                <input type='submit' id='kick' name='action' value='Kick'>
                <input type='submit' id='make_leader' name='action' value='Make Leader'>
              </form>
            <?php endif; ?>
        <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <h2>
        <?= $nation->name?>
        <?php if ($nid == -1 && $uid != -1): ?>
          <form method='post' class='nations-action-form'>
            <input type='hidden' id='form_type' name='form_type' value='nation_action'>
            <input type='hidden' id='csrf_tok' name='csrf_tok' value='<?= $_SESSION['csrf_tok'] ?>'>
            <input type='hidden' id='nation_nid' name='nation_nid' value='<?= $nation->nid ?>'>
            <input type='submit' id='Join' name='action' value='Join'>
          </form>
        <?php endif; ?>
      </h2>
      <?php foreach ($nation->members as $member):?>
          <?= $member->uid == $nation->leader->uid
            ? "<b>$member->username (Leader)</b>"
            : "<p>$member->username</p>"
          ?>
        <?php endforeach; ?>
    <?php endif;?>
  <?php endforeach; ?>
  <br><br>
  <?php if ($nid == -1 && $uid != -1): ?>
    <?php if ($nation_check_fail): ?>
      <p>Please choose a new name.</p>
    <?php endif;?>
    <form method='post'>
      <div>
        <label for="New Nation Name">New Nation Name:</label>
        <input type="text" id="name" name="name" required>
      </div>
      <input type='hidden' id='form_type' name='form_type' value='create_nation'>
      <input type='hidden' id='csrf_tok' name='csrf_tok' value='<?= $_SESSION['csrf_tok'] ?>'>
      <input type='submit' id='Join' name='action' value='Create'>
    </form>
  <?php endif; ?>
</body>
</html>