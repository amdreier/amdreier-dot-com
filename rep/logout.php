<?php 
    session_start();
    session_destroy();

    header("Location: https://romaetplus.amdreier.com/login");
?>