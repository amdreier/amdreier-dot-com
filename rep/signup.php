<?php
    session_start();

    if ($_SESSION['uid'] == -1) {
        session_destroy();
        session_start();
    }

    if (isset($_SESSION['uid'])) {
        header("Location: https://romaetplus.amdreier.com");
        exit();
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
        $stmt = $conn->prepare("SELECT * FROM Users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);

        // get entered user data
        $username = $_POST['username'];
        $password_1 = $_POST['password1'];
        $password_2 = $_POST['password2'];
        if ($password_1 != $password_2) {
            echo("Passwords must match");
        } else {
            $password = $password_1;

            $pswd_hash = 'N/A';
            $uid = -1;

            // get and check results from DB
            $stmt->execute();
            $result = $stmt->get_result();
            // done wtih prepared statement 1
            $stmt->close();
            if ($result->num_rows > 0) {
                // username match, try again
                echo("Please enter a different Username");

                // IN FUTURE ADD CHECK FOR WHITELIST
            } else {
                // username didn't match, add user

                // get unused uid
                $result = $conn->query("SELECT MAX(uid) AS 'max_uid' FROM Users");
                if ($result->num_rows > 0) {
                    $uid = $result->fetch_assoc()['max_uid'] + 1;
                } else {
                    $conn->close();
                    die("Couldn't connect to DB");
                }
                
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
                $pswd_hash = file_get_contents($url, false, $context);
                if ($result === false) {
                    /* Handle error */
                }

                $stmt = $conn->prepare("INSERT INTO Users (uid, username, pswd_hash) VALUES ($uid, ?, ?)");
                $stmt->bind_param("ss", $username, $pswd_hash);
                $stmt->execute();
                $stmt->close();

                header("Location: https://romaetplus.amdreier.com/login");
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
    <form method="post">
        <div class="form-row">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username"><br>
        </div>
        <div class="form-row">
            <label for="password">Password:</label>
            <input type="password" id="password1" name="password1"><br>
        </div>
        <div class="form-row">
            <label for="password">Retype Password:</label>
            <input type="password" id="password2" name="password2"><br>
        </div>
        <input type="submit" value="Sign Up">
    </form>
    <p>Already have an account? <a href='https://romaetplus.amdreier.com/login'>Go To Login</a></p>
</body>
</html>