<?php
    
    /* SETUP SQL */
    $sql_servername = "localhost";
    $sql_username = "root";
    $sql_dbname = "REP_Users";

    $url = "http://10.0.0.80:3000/creds";

    $sql_password = file_get_contents($url);

    if ($result === false) {
        die("Cannot connect to DB");
    }


    // Create connection
    $conn = new mysqli($sql_servername, $sql_username, $sql_password, $sql_dbname);

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // $sql = "SELECT * FROM Users WHERE username = '$username' LIMIT 1";

    $stmt = $conn->prepare("SELECT * FROM Users WHERE username = ? LIMIT 1");
    $stmt->bind_param("s", $username);

    $username = $_POST['username'];
    $password = $_POST['password'];
    $pswd_hash = 'N/A';
    $uid = -1;

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // GET USER DATA HERE
        $pswd_hash = $row['pswd_hash'];
        $uid = $row['uid'];
    } else {
        echo("0 results");
    }
    $stmt->close();
    $conn->close();

    $is_valid = "false";
    // validate user on node server
    

    if ($is_valid == "true") {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        $url = "http://10.0.0.80:3000/allow";
        $data = ['addr' => "$ip", 'user' => "$username"];
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

        header("Location: http://romaetplus.amdreier.com");
        exit();
    } else {
        // header("Location: http://romaetplus.amdreier.com/login.html");
        // exit();
    }
?>