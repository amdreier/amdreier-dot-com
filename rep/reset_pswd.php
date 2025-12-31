<?php
    session_start();

    if ($_SESSION['uid'] == -1) {
        session_destroy();
        session_start();
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
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

        // Create prepared statements to check for user data
        $get_user_stmt = $conn->prepare("SELECT * FROM Users WHERE username = ? LIMIT 1");
        $get_user_stmt->bind_param("s", $username);

        // get entered user data
        $username = $_POST['username'];
        $password_1 = $_POST['password1'];
        $password_2 = $_POST['password2'];
        $token = $_POST['token'];

        echo $token;

        if ($password_1 != $password_2) {
            echo("Passwords must match");
        } else {
            $password = $password_1;

            $pswd_hash = 'N/A';
            $uid = -1;

            // get and check results from DB
            $get_user_stmt->execute();
            $get_user_result = $get_user_stmt->get_result();
            // done wtih prepared statement 1
            $get_user_stmt->close();

            $passed_checks = false;
            $reason_expired = false;

            if ($get_user_result->num_rows > 0) {
                // username exists, get uid to check token and expiration
                $uid = $get_user_result->fetch_assoc()['uid'];
    
                $check_token_stmt = $conn->prepare("SELECT token, expires FROM Reset_Tokens WHERE uid=?");
                $check_token_stmt->bind_param("i", $uid);
                $check_token_stmt->execute();
                $check_token_result = $check_token_stmt->get_result();
                $check_token_stmt->close();

                $row = $check_token_result->fetch_assoc();

                // valid if token exists and matches, and expiration is later than now
                if ($check_token_result->num_rows > 0 && ($row['token'] == $token)) {
                    $expires = new DateTime($row['expires']);
                    $now = new DateTime('now');

                    if ($now < $expires) {
                        $passed_checks = true;
                    } else {
                        $reason_expired = true;
                        echo ("This token has expired, please request a new one.");
                    }
                }
            }

            if ($passed_checks) {
                $int_key = $_SERVER['INT_KEY'];
                // Get hash from node
                $url = "http://localhost:4000/pswd_hash";
                $data = ['password' => "$password", 'int_key' => "$int_key"];
                $options = [
                    'http' => [
                        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method' => 'POST',
                        'content' => http_build_query($data),
                    ],
                ];
                $context = stream_context_create($options);
                $pswd_hash = file_get_contents($url, false, $context); // TODO: Handle error

                // Delete token for this user
                $delete_token_stmt = $conn->prepare("DELETE FROM Reset_Tokens WHERE uid=?");
                $delete_token_stmt->bind_param("i", $uid);
                $delete_token_stmt->execute();
                $delete_token_stmt->close();

                // Update pswd_hash for this user
                $update_pswd_stmt = $conn->prepare("UPDATE Users SET pswd_hash=? WHERE uid=?");
                $update_pswd_stmt->bind_param("si", $pswd_hash, $uid);
                $update_pswd_stmt->execute();
                $update_pswd_stmt->close();

                header("Location: https://romaetplus.amdreier.com/login");
            } else if (!$reason_expired) {
                echo ("Token error.");
            }

            // DB done, close
            $conn->close();
        }
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
    <title>REP Signup</title>
    <link rel=“icon” href='https://romaetplus.amdreier.com/media/rep_icon.ico' type="image/x-icon">
    <link rel="shortcut icon" href='https://romaetplus.amdreier.com/media/rep_icon.ico' type="image/x-icon">
</head>
<body>
    <?php include "navbar.php";?>
    <h1>Password Reset</h1>
    <p>This page will reset the password of <?= htmlspecialchars(isset($_GET['username']) ? $_GET['username'] : "") ?> on the this login website.</p>
    <p>
        For your security, please use a different password than you use elsewhere.
        Even though this website uses the best-practices for user login and account storage (read more at the <a href="https://amdreier.com/projects/romaetplus-amdreier-com">project page</a>),
        this is still just a personal project.
    </p>
    <hr>
    <form method="post">
        <div class="form-row">
            <label for="password">Password:</label>
            <input type="password" id="password1" name="password1"><br>
        </div>
        <div class="form-row">
            <label for="password">Retype Password:</label>
            <input type="password" id="password2" name="password2"><br>
        </div>
        <input type="hidden" id="username" name="username" value="<?= htmlspecialchars(isset($_GET['username']) ? $_GET['username'] : "") ?>">
        <input type="hidden" id="token" name="token" value="<?= htmlspecialchars(isset($_GET['token']) ? $_GET['token'] : "") ?>">
        <input type="submit" value="Reset Password">
    </form>
</body>
</html>