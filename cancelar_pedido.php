<?php
session_start();

// Vaciar el pedido
$_SESSION['pedido'] = [];

// Regresar al menú
header("Location: menu.php");
exit();