<?php
    session_start();

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

        // Create prepared statements for getting user data
        $stmt = $conn->prepare("SELECT * FROM Users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);

        // get entered user data
        $username = $_POST['username'];
        $password = urlencode($_POST['password']);
        $pswd_hash = 'N/A';
        $uid = -1;

        // get and check results from DB
        $stmt->execute();
        $result = $stmt->get_result();
        // done wtih DB, close connections
        $stmt->close();
        $conn->close();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();

            $pswd_hash = $row['pswd_hash'];
            $uid = $row['uid'];

            // validate user on node server
            $is_valid = "false";
            $url = "http://localhost:4000/pswd_hash?pswd_hash=$pswd_hash&password=$password";
            $is_valid = file_get_contents($url);

            // if valid, sign in and add IP to Minecraft server
            if ($is_valid == "true") {
                // Get IP
                if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                    $ip = $_SERVER['HTTP_CLIENT_IP'];
                } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                } else {
                    $ip = $_SERVER['REMOTE_ADDR'];
                }

                $api_key = $_SERVER['API_KEY'];
                
                // Send IP to Minecraft Server
                $url = "http://10.0.0.80:3000/allow";
                $data = ['addr' => "$ip", 'user' => "$username", 'api_key' => "$api_key"];
                $options = [
                    'http' => [
                        'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method' => 'POST',
                        'content' => http_build_query($data),
                    ],
                ];
                $context = stream_context_create($options);
                $result = file_get_contents($url, false, $context);
                if ($result === false) {
                    /* Handle error */
                }

                // Sign in user, send to main page
                $_SESSION['uid'] = $uid;
                $_SESSION['username'] = $username;
                $_SESSION['ip'] = $ip;
                header("Location: https://romaetplus.amdreier.com");
            } else {
                // password didn't match
                echo("Invalid Username and/or Password");
            }
        } else {
            // username didn't match
            echo("Invalid Username and/or Password");
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
    <title>REP Login</title>
</head>
<body>
    <form method="post">
        <div class="form-row">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username"><br>
        </div>
        <div class="form-row">
            <label for="password">Password:</label>
            <input type="password" id="password" name="password"><br>
        </div>
        <input type="submit" value="Login">
    </form>
    <p>Don't have an account? <a href='https://romaetplus.amdreier.com/signup'>Go To Signup</a></p>
</body>
</html>