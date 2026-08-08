<?php
session_start();

// Verificar que exista el pedido en la sesión
if (!isset($_SESSION['pedido'])) {
    $_SESSION['pedido'] = [];
}

// Verificar que se recibió un producto
if (isset($_POST['id_producto'])) {

    $idProducto = intval($_POST['id_producto']);

    // Verificar si el producto ya está en el pedido
    if (isset($_SESSION['pedido'][$idProducto])) {

        $_SESSION['pedido'][$idProducto]['cantidad']++;

    } else {

        $_SESSION['pedido'][$idProducto] = [
            "cantidad" => 1
        ];

    }
}

// Regresar al menú
header("Location: menu.php");
exit();