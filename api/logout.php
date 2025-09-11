<?php
$_SESSION = [];
session_unset();
session_destroy();
header("Location: ../autenticazione.html");
exit;
