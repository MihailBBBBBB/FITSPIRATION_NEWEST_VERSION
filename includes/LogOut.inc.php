<?php

if (session_status() === PHP_SESSION_NONE) {
	session_start();
}
session_unset();
session_destroy();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: /HTML/LogIn.php', true, 303);
exit();