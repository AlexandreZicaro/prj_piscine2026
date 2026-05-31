<?php
session_start();
session_destroy();
header("Location: connexion_et_incription.html");
exit();
?>
