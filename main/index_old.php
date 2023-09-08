<?php
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
?>

<!DOCTYPE html>
<html>
    <head>
        <title>amdreier's home page</title>
        <link rel="stylesheet" type="text/css" href="shared/styles/sharedStyles.css">

    </head>
    <body>
            <a href="https://romaetplus.amdreier.com">
                <img class="rotate-on-hover" style="cursor: pointer;" src="shared/media/server-icon.png">
                <h1>
                    <p>REP HOME PAGE</p>
                </h1>
            </a>
    <?php
        if (str_contains($ip, ":")) {
    ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script>
        $.getJSON("https://api.ipify.org?format=json", function(data) {

            // Setting text of element P with id=ip
            $("#ip").html(`Your IP is: ${data.ip}`);
        })
    </script>
        
    <p id="ip">Geting IPv4...</p>
    <?php
        } else {
    ?>
        <p>Your IP is: <?php echo $ip?></p>
    <?php
        }
    ?>

        <a href="node">Node</a>
        <br>
        <a href="himom">for mom</a>
        <br>
        <a href="subfolder/">subfolder</a>
    </body>
</html>