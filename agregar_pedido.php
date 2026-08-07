<?php
session_start();

// Verificar que exista el pedido en la sesión
if (!isset($_SESSION['pedido'])) {
    $_SESSION['pedido'] = [];
}

// Verificar que se recibió un producto
if (isset($_POST['id_producto'])) {

    $idProducto = intval($_POST['id_producto']);

    // Agregar el producto al pedido
    $_SESSION['pedido'][] = $idProducto;
}

// Regresar al menú
header("Location: menu.php");
exit();
?>