<?php
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    $url = "http://10.0.0.80:3000/allow";

    $data = ['addr' => "$ip"];

    // use key 'http' even if you send the request to https://...
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

    // var_dump($result);

    // $curl = curl_init($url);
    // curl_setopt($curl, CURLOPT_URL, $url);
    // curl_setopt($curl, CURLOPT_POST, true);
    // curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    // $data = <<< DATA
    //     {"addr": $ip}s
    // DATA;

    // curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

    // $resp = curl_exec($curl);
    // // echo $resp;
    // curl_close($curl);
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
        <div class='form-row'>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username"><br>
        </div>
        <div class='form-row'>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password"><br>
        </div>
        <input type="submit" value="Login">
    </form>
</body>
</html>