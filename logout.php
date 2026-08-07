<?php
session_start();

// Elimina todas las variables de sesión
session_unset();

// Destruye la sesión
session_destroy();

// Regresa al inicio de sesión
header("Location: Cliente.html");

exit();
?>