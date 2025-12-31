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

  $api_key = $_SERVER['API_KEY'];

  $nid = -1;
  $nation = "";
  $leader = "";
  $has_nation = false;
  $is_leader = false;

  $has_join_req = false;
  $join_req_nid = -1;

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
      SELECT *
      FROM Users u JOIN Nations n ON u.u_nation_id = n.nid
      WHERE uid=?
    ");
    $check_nation_stmt->bind_param("i", $uid);
    $check_nation_stmt->execute();
    $this_check_nation_res = $check_nation_stmt->get_result();
    $this_check_nation_row = $this_check_nation_res->fetch_assoc();

    if ($this_check_nation_res->num_rows > 0) {
      $has_nation = true;
      $nid = $this_check_nation_row['nid'];
      $nation = $this_check_nation_row['n_name'];
      $leader_id = $this_check_nation_row['n_leader_id'];
      $is_leader = ($leader_id == $uid);
    }

    $check_join_req_stmt = $conn->prepare("
      SELECT *
      FROM Users u JOIN Nation_Join_Reqs jr ON u.uid = jr.uid
      WHERE u.uid=?
    ");
    $check_join_req_stmt->bind_param("i", $uid);
    $check_join_req_stmt->execute();
    $check_join_req_res = $check_join_req_stmt->get_result();
    $check_join_req_row = $check_join_req_res->fetch_assoc();

    if ($check_join_req_res->num_rows > 0) {
      $has_join_req = true;
      $req_nid = $check_join_req_row['nid'];
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && $_POST['csrf_tok'] == $_SESSION['csrf_tok']) {
      if ($_POST['form_type'] == "leader_action") {
        $member_uid = $_POST['member_uid'] ?? $uid;

        $check_nation_stmt->bind_param("i", $member_uid);
        $check_nation_stmt->execute();
        $check_nation_res = $check_nation_stmt->get_result();
        $check_nation_row = $check_nation_res->fetch_assoc();

        $check_join_req_stmt->bind_param("i", $member_uid);
        $check_join_req_stmt->execute();
        $check_join_req_res = $check_join_req_stmt->get_result();
        $check_join_req_row = $check_join_req_res->fetch_assoc();

        if ($is_leader && ($check_nation_row['nid'] == $nid || $check_join_req_row['nid'] == $nid) && $member_uid != $uid) {
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

            $query_mc_username = urlencode($_POST['member_mc_username']);

            // Send to MC api
            if ($query_mc_username != "") {
              $api_key = $_SERVER['API_KEY'];
              $url = "https://romaetplus.amdreier.com/api/Nations/leave/$query_mc_username";
              $data = ['api_key' => "$api_key"];
              $options = [
                  'http' => [
                      'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                      'method' => 'POST',
                      'content' => http_build_query($data),
                      'ignore_errors' => true,
                      'timeout' => 0.1
                  ],
              ];
              $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
              if ($mc_send_link_res === false) {
                  /* Handle error */
              }
            }

            // Disc API
            $query_disc_username = urlencode($_POST['disc_username']);
            $query_disc_uid = urlencode($_POST['disc_uid']);
            if ($query_disc_username != "") {
              $nation_name = urlencode($_POST['nation_name']);
              $url = "http://localhost:6000/Nations/leave/$nation_name/$query_disc_username/$query_disc_uid";
              $data = ['api_key' => "$api_key"];
              $options = [
                  'http' => [
                      'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                      'method' => 'POST',
                      'content' => http_build_query($data),
                      'ignore_errors' => true,
                      'timeout' => 0.1
                  ],
              ];
              $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
              if ($mc_send_link_res === false) {
                  /* Handle error */
              }
            }
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

          if ($_POST['action'] == "Reject") {
            $delete_join_req_stmt = $conn->prepare("
              DELETE FROM Nation_Join_Reqs
              WHERE uid = ?
            ");
            $delete_join_req_stmt->bind_param("i", $member_uid);
            $delete_join_req_stmt->execute();
            $delete_join_req_stmt->close();

            $val_change = true;
          }

          if ($_POST['action'] == "Accept") {
            $delete_join_req_stmt = $conn->prepare("
              DELETE FROM Nation_Join_Reqs
              WHERE uid = ?
            ");
            $delete_join_req_stmt->bind_param("i", $member_uid);
            $delete_join_req_stmt->execute();
            $delete_join_req_stmt->close();

            $nation_join_stmt = $conn->prepare("
              UPDATE Users
              SET u_nation_id = ?
              WHERE uid=?
            ");
            $nation_join_stmt->bind_param("ii", $nid, $member_uid);
            $nation_join_stmt->execute();
            $nation_join_stmt->close();

            $val_change = true;

            $query_mc_username = urlencode($_POST['member_mc_username']);

            // Send to MC api
            if ($query_mc_username != "") {
              $nation_name = urlencode($_POST['nation_name']);
              $api_key = $_SERVER['API_KEY'];
              $url = "https://romaetplus.amdreier.com/api/Nations/join/$nation_name/$query_mc_username";
              $data = ['api_key' => "$api_key"];
              $options = [
                  'http' => [
                      'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                      'method' => 'POST',
                      'content' => http_build_query($data),
                      'ignore_errors' => true,
                      'timeout' => 0.1
                  ],
              ];
              $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
              if ($mc_send_link_res === false) {
                  /* Handle error */
              }
            }

            // Disc API
            $query_disc_username = urlencode($_POST['disc_username']);
            $query_disc_uid = urlencode($_POST['disc_uid']);
            if ($query_disc_username != "") {
              $nation_name = urlencode($_POST['nation_name']);
              $url = "http://localhost:6000/Nations/join/$nation_name/$query_disc_username/$query_disc_uid";
              $data = ['api_key' => "$api_key"];
              $options = [
                  'http' => [
                      'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                      'method' => 'POST',
                      'content' => http_build_query($data),
                      'ignore_errors' => true,
                      'timeout' => 0.1
                  ],
              ];
              $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
              if ($mc_send_link_res === false) {
                  /* Handle error */
              }
            }
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

          $query_mc_username = urlencode($_POST['mc_username']);
          // Send to MC api
          if ($query_mc_username != "") {
            $api_key = $_SERVER['API_KEY'];
            $url = "https://romaetplus.amdreier.com/api/Nations/leave/$query_mc_username";
            $data = ['api_key' => "$api_key"];
            $options = [
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($data),
                    'ignore_errors' => true,
                    'timeout' => 0.1
                ],
            ];
            $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
            if ($mc_send_link_res === false) {
                /* Handle error */
            }
          }

          // Disc API
          $query_disc_username = urlencode($_POST['disc_username']);
          $query_disc_uid = urlencode($_POST['disc_uid']);
          if ($query_disc_username != "") {
            $nation_name = urlencode($_POST['nation_name']);
            $url = "http://localhost:6000/Nations/leave/$nation_name/$query_disc_username/$query_disc_uid";
            $data = ['api_key' => "$api_key"];
            $options = [
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($data),
                    'ignore_errors' => true,
                    'timeout' => 0.1
                ],
            ];
            $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
            if ($mc_send_link_res === false) {
                /* Handle error */
            }
          }
        }
      }

      if ($_POST['form_type'] == "nation_action") {
        $nation_nid = $_POST['nation_nid'];

        if ($_POST['action'] == "Join") {
          $check_open_stmt = $conn->prepare("
            SELECT invite_only
            FROM Nations
            WHERE nid = ?
          ");
          $check_open_stmt->bind_param("i", $nation_nid);
          $check_open_stmt->execute();
          $check_open_res = $check_open_stmt->get_result();
          $check_open_stmt->close();

          if ($check_open_res->num_rows > 0 && !$check_open_res->fetch_assoc()['invite_only']) {
            $delete_join_req_stmt = $conn->prepare("
              DELETE FROM Nation_Join_Reqs
              WHERE uid = ?
            ");
            $delete_join_req_stmt->bind_param("i", $uid);
            $delete_join_req_stmt->execute();
            $delete_join_req_stmt->close();

            $has_join_req = false;
            $join_req_nid = -1;

            $nation_join_stmt = $conn->prepare("
              UPDATE Users
              SET u_nation_id = ?
              WHERE uid = ?
            ");
            $nation_join_stmt->bind_param("ii", $nation_nid, $uid);
            $nation_join_stmt->execute();
            $nation_join_stmt->close();
            
            $val_change = true;

            $query_mc_username = urlencode($_POST['mc_username']);
            
            // Send to MC api
            if ($query_mc_username != "") {
              $nation_name = urlencode($_POST['nation_name']);
              $api_key = $_SERVER['API_KEY'];
              $url = "https://romaetplus.amdreier.com/api/Nations/join/$nation_name/$query_mc_username";
              $data = ['api_key' => "$api_key"];
              $options = [
                  'http' => [
                      'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                      'method' => 'POST',
                      'content' => http_build_query($data),
                      'ignore_errors' => true,
                      'timeout' => 0.1
                  ],
              ];
              $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
              if ($mc_send_link_res === false) {
                  /* Handle error */
              }
            }

            // Disc API
            $query_disc_username = urlencode($_POST['disc_username']);
            $query_disc_uid = urlencode($_POST['disc_uid']);
            if ($query_disc_username != "") {
              $nation_name = urlencode($_POST['nation_name']);
              $url = "http://localhost:6000/Nations/join/$nation_name/$query_disc_username/$query_disc_uid";
              $data = ['api_key' => "$api_key"];
              $options = [
                  'http' => [
                      'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                      'method' => 'POST',
                      'content' => http_build_query($data),
                      'ignore_errors' => true,
                      'timeout' => 0.1
                  ],
              ];
              $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
              if ($mc_send_link_res === false) {
                  /* Handle error */
              }
            }
          }
        }

        if ($_POST['action'] == "Send Join Request") {
          $req_nid = $_POST['nation_nid'];
          $send_join_req_stmt = $conn->prepare("
            INSERT INTO Nation_Join_Reqs (nid, uid)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE
              nid = VALUES(nid)
          ");

          $send_join_req_stmt->bind_param("ii", $req_nid, $uid);
          $send_join_req_stmt->execute();
          $send_join_req_stmt->close();

          $has_join_req = true;
          $join_req_nid = $req_nid;
        }

        if ($_POST['action'] == "Cancel Join Request") {
          $req_nid = $_POST['nation_nid'];
          $cancel_join_req_stmt = $conn->prepare("
            DELETE FROM Nation_Join_Reqs
            WHERE nid = ? AND uid = ?
          ");

          $cancel_join_req_stmt->bind_param("ii", $req_nid, $uid);
          $cancel_join_req_stmt->execute();
          $cancel_join_req_stmt->close();

          $has_join_req = false;
          $join_req_nid = -1;
        }
      }

      if ($_POST['form_type'] == "create_nation") {
        $nation_name = $_POST['name'];

        $nation_check_fail = false;
        $length_check_fail = false;

        if (strlen($nation_name) > 16) {
          $length_check_fail = true;
        } else {
          $nation_check_stmt = $conn->prepare("
            SELECT n_name
            FROM Nations
            WHERE n_name = ?
          ");
          $nation_check_stmt->bind_param("s", $nation_name);
          $nation_check_stmt->execute();
          $nation_check_res = $nation_check_stmt->get_result();
          $nation_check_stmt->close();
        }
        if ($length_check_fail || $nation_check_res->num_rows > 0) {
          $nation_check_fail = true;
        } else {
          $delete_join_req_stmt = $conn->prepare("
            DELETE FROM Nation_Join_Reqs
            WHERE uid = ?
          ");

          $delete_join_req_stmt->bind_param("i", $uid);
          $delete_join_req_stmt->execute();
          $delete_join_req_stmt->close();

          $has_join_req = false;
          $join_req_nid = -1;

          $color_plus_hex = explode(';', $_POST['color']);
          $hex = $color_plus_hex[0] ?? '000000';
          $color = $color_plus_hex[1] ?? 'white';
          $invite_only = $_POST['invite_only'] == "1" ? 1 : 0;

          $new_nid = $conn->query("SELECT MAX(nid) AS 'max_nid' FROM Nations")->fetch_assoc()['max_nid'] + 1;

          $nation_create_stmt = $conn->prepare("
            INSERT INTO Nations
              (nid, n_name, n_leader_id, color, color_hex, invite_only)
            VALUES
              (?, ?, ?, ?, ?, ?)
          ");
          $nation_create_stmt->bind_param("isissi", $new_nid, $nation_name, $uid, $color, $hex, $invite_only);
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

          // MC API
          $nation_name = urlencode($nation_name);

          $url = "https://romaetplus.amdreier.com/api/Nations/create/$nation_name/$color";
          $data = ['api_key' => "$api_key"];
          $options = [
              'http' => [
                  'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                  'method' => 'POST',
                  'content' => http_build_query($data),
                  'ignore_errors' => true,
                  'timeout' => 0.1
              ],
          ];
          $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
          if ($mc_send_link_res === false) {
              /* Handle error */
          }

          $query_mc_username = urlencode($_POST['mc_username']);
          // Send to MC api
          if ($query_mc_username != "") {

            $url = "https://romaetplus.amdreier.com/api/Nations/join/$nation_name/$query_mc_username";
            $data = ['api_key' => "$api_key"];
            $options = [
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($data),
                    'ignore_errors' => true,
                    'timeout' => 0.1
                ],
            ];
            $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
            if ($mc_send_link_res === false) {
                /* Handle error */
            }
          }

          // Disc API
          $url = "http://localhost:6000/Nations/create/$nation_name/$hex";
          $data = ['api_key' => "$api_key"];
          $options = [
              'http' => [
                  'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                  'method' => 'POST',
                  'content' => http_build_query($data),
                  'ignore_errors' => true,
                  'timeout' => 0.1
              ],
          ];
          $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
          if ($mc_send_link_res === false) {
              /* Handle error */
          }

          $query_disc_username = urlencode($_POST['disc_username']);
          $query_disc_uid = urlencode($_POST['disc_uid']);
          if ($query_disc_username != "") {
            $url = "http://localhost:6000/Nations/join/$nation_name/$query_disc_username/$query_disc_uid";
            $data = ['api_key' => "$api_key"];
            $options = [
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method' => 'POST',
                    'content' => http_build_query($data),
                    'ignore_errors' => true,
                    'timeout' => 0.1
                ],
            ];
            $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
            if ($mc_send_link_res === false) {
                /* Handle error */
            }
          }
        }
      }

      if ($_POST['form_type'] == "modify_nation" && $is_leader) {
        if ($_POST['action'] == "Delete") {
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

          // MC API
          $nation_name = urlencode($_POST['nation_name']);

          $url = "https://romaetplus.amdreier.com/api/Nations/delete/$nation_name";
          $data = ['api_key' => "$api_key"];
          $options = [
              'http' => [
                  'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                  'method' => 'POST',
                  'content' => http_build_query($data),
                  'ignore_errors' => true,
                  'timeout' => 0.1
              ],
          ];
          $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
          if ($mc_send_link_res === false) {
              /* Handle error */
          }

          // Disc API
          $url = "http://localhost:6000/Nations/delete/$nation_name";
          $data = ['api_key' => "$api_key"];
          $options = [
              'http' => [
                  'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                  'method' => 'POST',
                  'content' => http_build_query($data),
                  'ignore_errors' => true,
                  'timeout' => 0.1
              ],
          ];
          $mc_send_link_res = file_get_contents($url, false, stream_context_create($options));
          if ($mc_send_link_res === false) {
              /* Handle error */
          }
        }

        if ($_POST['action'] == "Make Open Invite") { 
          $open_nation_stmt = $conn->prepare("
            UPDATE Nations
            SET invite_only = 0
            WHERE nid = ?
          ");
          $open_nation_stmt->bind_param("i", $nid);
          $open_nation_stmt->execute();
          $open_nation_stmt->close();
        }

        if ($_POST['action'] == "Make Invite Only") { 
          $open_nation_stmt = $conn->prepare("
            UPDATE Nations
            SET invite_only = 1
            WHERE nid = ?
          ");
          $open_nation_stmt->bind_param("i", $nid);
          $open_nation_stmt->execute();
          $open_nation_stmt->close();
        }
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
      $this_check_nation_row = $check_nation_res->fetch_assoc();

      if ($check_nation_res->num_rows > 0) {
        $has_nation = true;
        $nid = $this_check_nation_row['nid'];
        $nation = $this_check_nation_row['n_name'];
        $leader_id = $this_check_nation_row['n_leader_id'];
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
    $leader->mc_verified = !empty($row['mc_username']);
    $leader->disc_verified = !empty($row['disc_username']);
    $leader->disp_name = $leader->username." ["
                        .($leader->mc_verified ? "MC:".$leader->mc_username : "MC unverified").", "
                        .($leader->disc_verified ? "@".$leader->disc_username : "Discord unverified")."]";
    $leader->nation = $nation;

    $nation->leader = $leader;
    $nation->name = htmlspecialchars($row['n_name']);
    $nation->nid = htmlspecialchars($row['nid']);
    $nation->color = htmlspecialchars($row['color']);
    $nation->color_hex = htmlspecialchars($row['color_hex']);
    $nation->invite_only = $row['invite_only'];
    $nation->html_display = "<span style='color: #$nation->color_hex'>".$nation->name.($nation->invite_only ? " 🔒" : " 🔓")."</span>";
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
    $player->mc_verified = !empty($row['mc_username']);
    $player->disc_verified = !empty($row['disc_username']);
    $player->disp_name = $player->username." ["
                        .($player->mc_verified ? "MC:".$player->mc_username : "MC unverified").", "
                        .($player->disc_verified ? "@".$player->disc_username : "Discord unverified")."]";
    $player->nation = $nations[$row['nid']];

    $player->nation->members[] = $player;
  }

  if ($uid != -1) {
    $check_user_stmt = $conn->prepare("
        SELECT username, disc_username, disc_uid, mc_username, u_nation_id
        FROM Users
        WHERE uid=?
    ");
    $check_user_stmt->bind_param("i", $uid);
    $check_user_stmt->execute();
    $check_user_res = $check_user_stmt->get_result();
    $check_user_row = $check_user_res->fetch_assoc();
    $check_user_stmt->close();
    $check_join_req_stmt->close();

    if ($check_user_res->num_rows > 0) {
      $this_player = new Player();
      $this_player->uid = $uid;
      $this_player->username = htmlspecialchars($check_user_row['username']);
      $this_player->disc_username = htmlspecialchars($check_user_row['disc_username']);
      $this_player->disc_uid = htmlspecialchars($check_user_row['disc_uid']);
      $this_player->mc_username = htmlspecialchars($check_user_row['mc_username']);
      $this_player->mc_verified = !empty($check_user_row['mc_username']);
      $this_player->disc_verified = !empty($check_user_row['disc_username']);
      $this_player->disp_name = $this_player->username." ["
                          .($this_player->mc_verified ? "MC:".$this_player->mc_username : "MC unverified").", "
                          .($this_player->disc_verified ? "@".$this_player->disc_username : "Discord unverified")."]";

      $this_player->nation = $nid == -1 ? null : $nations[$nid];
      $_SESSION['color'] = $this_player->nation->color_hex ?? "000000";
    }

    if ($has_nation && $is_leader) {
      $check_requests_stmt = $conn->prepare("
          SELECT u.uid, u.username, u.disc_username, u.disc_uid, u.mc_username, jr.nid
          FROM Users u JOIN Nation_Join_Reqs jr ON u.uid = jr.uid
          WHERE jr.nid=?
      ");
      $check_requests_stmt->bind_param("i", $nid);
      $check_requests_stmt->execute();
      $check_requests_res = $check_requests_stmt->get_result();
      $check_requests_stmt->close();

      $requests = [];

      while ($row = $check_requests_res->fetch_assoc()) {
        $player = new Player();
        $player->uid = htmlspecialchars($row['uid']);
        $player->username = htmlspecialchars($row['username']);
        $player->disc_username = htmlspecialchars($row['disc_username']);
        $player->disc_uid = htmlspecialchars($row['disc_uid']);
        $player->mc_username = htmlspecialchars($row['mc_username']);
        $player->mc_verified = !empty($row['mc_username']);
        $player->disc_verified = !empty($row['disc_username']);
        $player->disp_name = $player->username." ["
                            .($player->mc_verified ? "MC:".$player->mc_username : "MC unverified").", "
                            .($player->disc_verified ? "@".$player->disc_username : "Discord unverified")."]";
    
        $requests[] = $player;
      }


    }
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
  <p>Welcome to the REP Nation Manager, <b style='color: #<?= $_SESSION['color'] ?? "000000" ?>'><?= htmlspecialchars($username)?></b>! <?= $uid == -1 ? "(Note that signing in as Guest is view-only and turns off certain website features.)" : "" ?></p>
  <p>Read more about the Nation Manager in the <a href="https://romaetplus.amdreier.com/guide#Nation_Manager">Guide</a></p>
  <hr>
  <?php foreach ($nations as $nation):?>
    <?php if ($nation->nid == $nid): ?>
      <h2>
        <i><?= "*$nation->html_display" ?></i>
        <?php if ($is_leader) :?>
          <form method='post' class='nations-action-form'>
            <input type='hidden' id='form_type' name='form_type' value='modify_nation'>
            <input type='hidden' id='mc_username' name='mc_username' value='<?= $this_player->mc_verified ? $this_player->mc_username : ""?>'>
            <input type='hidden' id='disc_username' name='disc_username' value='<?= $this_player->disc_verified ? $this_player->disc_username : ""?>'>
            <input type='hidden' id='disc_uid' name='disc_uid' value='<?= $this_player->disc_verified ? $this_player->disc_uid : ""?>'>
            <input type='hidden' id='nation_name' name='nation_name' value='<?= $nation->name ?>'>
            <input type='hidden' id='csrf_tok' name='csrf_tok' value='<?= $_SESSION['csrf_tok'] ?>'>
            <input type='submit' id='Delete' name='action' value='Delete'>
            <?php if ($nation->invite_only): ?>
              <input type='submit' id='Delete' name='action' value='Make Open Invite'>
            <?php else: ?>
              <input type='submit' id='Delete' name='action' value='Make Invite Only'>
            <?php endif; ?>
          </form>
        <?php elseif ($uid != -1): ?>
          <form method='post' class='nations-action-form'>
            <input type='hidden' id='form_type' name='form_type' value='member_action'>
            <input type='hidden' id='mc_username' name='mc_username' value='<?= $this_player->mc_verified ? $this_player->mc_username : ""?>'>
            <input type='hidden' id='disc_username' name='disc_username' value='<?= $this_player->disc_verified ? $this_player->disc_username : ""?>'>
            <input type='hidden' id='disc_uid' name='disc_uid' value='<?= $this_player->disc_verified ? $this_player->disc_uid : ""?>'>
            <input type='hidden' id='nation_name' name='nation_name' value='<?= $nation->name ?>'>
            <input type='hidden' id='csrf_tok' name='csrf_tok' value='<?= $_SESSION['csrf_tok'] ?>'>
            <input type='submit' id='Leave' name='action' value='Leave'>
          </form>
        <?php endif;?>
      </h2>
      <?php foreach ($nation->members as $member):?>
        <div>
        <?php if ($member->uid == $nation->leader->uid): ?>
          <p style='display: inline-block; color: #<?= $nation->color_hex ?>;'><b> <?= $member->uid == $uid ? "<i>*$member->disp_name</i>" : $member->disp_name?> (Leader)</b></p>
        <?php else: ?>
          <p style='display: inline-block; color: #<?= $nation->color_hex ?>;'> <?= $member->uid == $uid ? "<i>*$member->disp_name.$member->mc_disp_name</i>" : $member->disp_name?> </p>
            <?php if ($is_leader): ?> 
              <form method='post' class='nations-action-form'>
                <input type='hidden' id='form_type' name='form_type' value='leader_action'>
                <input type='hidden' id='member_uid' name='member_uid' value='<?= $member->uid ?>'>
                <input type='hidden' id='member_mc_username' name='member_mc_username' value='<?= $member->mc_verified ? $member->mc_username : ""?>'>
                <input type='hidden' id='disc_username' name='disc_username' value='<?= $member->disc_verified ? $member->disc_username : ""?>'>
                <input type='hidden' id='disc_uid' name='disc_uid' value='<?= $member->disc_verified ? $member->disc_uid : ""?>'>
                <input type='hidden' id='nation_name' name='nation_name' value='<?= $nation->name ?>'>
                <input type='hidden' id='csrf_tok' name='csrf_tok' value='<?= $_SESSION['csrf_tok'] ?>'>
                <input type='submit' id='kick' name='action' value='Kick'>
                <input type='submit' id='make_leader' name='action' value='Make Leader'>
              </form>
            <?php endif; ?>
        <?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if ($is_leader): ?>
        <h3>Join Requests:</h3>
        <?php foreach ($requests as $request): ?>
          <div>
            <p style="display: inline-block;"><?= $request->disp_name ?></p>
            <form method='post' class='nations-action-form'>
              <input type='hidden' id='form_type' name='form_type' value='leader_action'>
              <input type='hidden' id='member_uid' name='member_uid' value='<?= $request->uid ?>'>
              <input type='hidden' id='member_mc_username' name='member_mc_username' value='<?= $request->mc_verified ? $request->mc_username : ""?>'>
              <input type='hidden' id='disc_username' name='disc_username' value='<?= $request->disc_verified ? $request->disc_username : ""?>'>
              <input type='hidden' id='disc_uid' name='disc_uid' value='<?= $request->disc_verified ? $request->disc_uid : ""?>'>
              <input type='hidden' id='nation_name' name='nation_name' value='<?= $nation->name ?>'>
              <input type='hidden' id='csrf_tok' name='csrf_tok' value='<?= $_SESSION['csrf_tok'] ?>'>
              <input type='submit' id='kick' name='action' value='Accept'>
              <input type='submit' id='make_leader' name='action' value='Reject'>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif ?>
    <?php else: ?>
      <h2>
        <?= $nation->html_display ?>
        <?php if ($nid == -1 && $uid != -1): ?>
          <form method='post' class='nations-action-form'>
            <input type='hidden' id='form_type' name='form_type' value='nation_action'>
            <input type='hidden' id='mc_username' name='mc_username' value='<?= $this_player->mc_verified ? $this_player->mc_username : ""?>'>
            <input type='hidden' id='disc_username' name='disc_username' value='<?= $this_player->disc_verified ? $this_player->disc_username : ""?>'>
            <input type='hidden' id='disc_uid' name='disc_uid' value='<?= $this_player->disc_verified ? $this_player->disc_uid : ""?>'>
            <input type='hidden' id='csrf_tok' name='csrf_tok' value='<?= $_SESSION['csrf_tok'] ?>'>
            <input type='hidden' id='nation_nid' name='nation_nid' value='<?= $nation->nid ?>'>
            <input type='hidden' id='nation_name' name='nation_name' value='<?= $nation->name ?>'>
            <?php if ($nation->invite_only): ?>
              <?php if ($has_join_req): ?>
                <input type='submit' id='Join' name='action' value='Cancel Join Request'>
              <?php else: ?>
                <input type='submit' id='Join' name='action' value='Send Join Request'>
              <?php endif; ?>
            <?php else: ?>
              <input type='submit' id='Join' name='action' value='Join'>
            <?php endif; ?>
          </form>
        <?php endif; ?>
      </h2>
      <?php foreach ($nation->members as $member):?>
          <p style='color: #<?= $nation->color_hex ?>;'><?= $member->uid == $nation->leader->uid
            ? "<b>$member->disp_name (Leader)</b>"
            : "$member->disp_name"
          ?></p>
        <?php endforeach; ?>
    <?php endif;?>
  <?php endforeach; ?>
  <br><br>
  <?php if (!$has_nation && $uid != -1): ?>
    <div style='border: 2px solid black; padding: 2px; background-color:white; display: inline-block;'>
      <h3>Create new nation:</h3>
      <hr>
      <?php if ($length_check_fail): ?>
        <b>Name can be at most 16 characters.</b>
      <?php elseif ($nation_check_fail) : ?>
        <b>Please choose a different name.</b>
      <?php endif;?>
      <form method='post'>
        <div>
          <label for="New Nation Name">New Nation Name:</label>
          <input type="text" id="name" name="name" required>
        </div>
        <br>
        <div>
          <div>
            <div style="display: flex;">
              <label id="Color" for="Color" syle="display: inline-block;">Display Color:</label>
              <div id="color_disp" style='border: 2px solid black; display: inline-block; margin: 2px; width: 15px; height: 15px; background-color: black;'></div>
            </div>
            <select name="color" id="color" style="display: inline;">
              <option value="000000;black">Black</option>
              <option value="FFFFFF;white">White</option>
              <option value="0000AA;dark_blue">Dark Blue</option>
              <option value="00AA00;dark_green">Dark Green</option>
              <option value="00AAAA;dark_aqua">Dark Aqua</option>
              <option value="AA0000;dark_red">Dark Red</option>
              <option value="AA00AA;dark_purple">Dark Purple</option>
              <option value="FFAA00;gold">Gold</option>
              <option value="AAAAAA;gray">Gray</option>
              <option value="555555;dark_gray">Dark Gray</option>
              <option value="5555FF;blue">Blue</option>
              <option value="55FF55;green">Green</option>
              <option value="55FFFF;aqua">Aqua</option>
              <option value="FF5555;red">Red</option>
              <option value="FF55FF;light_purple">Light Purple</option>
              <option value="FFFF55;yellow">Yellow</option>
            </select>
          </div>
        </div>
        <br>
        <div style="display: flex;">
          <label id="invite_only" for="invite_only" syle="display: inline-block;">Invite Only:</label>
          <input type="checkbox" style="display: inline-block;" id="invite_only" name="invite_only" value="1">
        </div>
        <input type='hidden' id='form_type' name='form_type' value='create_nation'>
        <input type='hidden' id='mc_username' name='mc_username' value='<?= $this_player->mc_verified ? $this_player->mc_username : ""?>'>
        <input type='hidden' id='disc_username' name='disc_username' value='<?= $this_player->disc_verified ? $this_player->disc_username : ""?>'>
        <input type='hidden' id='disc_uid' name='disc_uid' value='<?= $this_player->disc_verified ? $this_player->disc_uid : ""?>'>
        <input type='hidden' id='csrf_tok' name='csrf_tok' value='<?= $_SESSION['csrf_tok'] ?>'>
        <br>
        <input type='submit' id='create' name='action' value='Create'>
      </form>
    </div>
  <?php endif; ?>
</body>
<script>
    const selector = document.getElementById("color");
    const target   = document.getElementById("color_disp");

    selector.addEventListener("change", () => {
      const choice = selector.value;
      target.style.backgroundColor = `#${selector.value.split(';')[0]}` || "transparent";
    });
  </script>
</html>