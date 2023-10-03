<?php
    session_start();
    session_destroy();
    session_start();

    $_SESSION['uid'] = -1;

    header("Location: https://romaetplus.amdreier.com");
?>