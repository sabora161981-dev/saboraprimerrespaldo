<?php
session_start();

include("conexion.php");

/*
|--------------------------------------------------------------------------
| VERIFICAR SESIÓN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['correo'])) {
    header("Location: Cliente.html");
    exit();
}

if (
    !isset($_SESSION['rol']) ||
    strtolower(trim($_SESSION['rol'])) !== "cliente"
) {
    header("Location: Cliente.html");
    exit();
}


/*
|--------------------------------------------------------------------------
| DATOS DEL CLIENTE
|--------------------------------------------------------------------------
*/

$nombre   = $_SESSION['nombre'] ?? '';
$apellido = $_SESSION['apellido'] ?? '';
$correo   = $_SESSION['correo'] ?? '';
$identificacion = '';
$direccionCliente = '';

/*
|--------------------------------------------------------------------------
| OBTENER DATOS COMPLETOS DEL CLIENTE
|--------------------------------------------------------------------------
*/

if ($correo !== '') {

    $sqlCliente = "
        SELECT identificacion, direccion
        FROM usuarios
        WHERE correo = ?
        LIMIT 1
    ";

    $stmtCliente = mysqli_prepare(
        $conexion,
        $sqlCliente
    );

    if ($stmtCliente) {

        mysqli_stmt_bind_param(
            $stmtCliente,
            "s",
            $correo
        );

        mysqli_stmt_execute(
            $stmtCliente
        );

        $resultadoCliente =
            mysqli_stmt_get_result(
                $stmtCliente
            );

        if (
            $resultadoCliente &&
            mysqli_num_rows($resultadoCliente) > 0
        ) {

            $cliente =
                mysqli_fetch_assoc(
                    $resultadoCliente
                );

            $identificacion =
                $cliente['identificacion'] ?? '';

            $direccionCliente =
                $cliente['direccion'] ?? '';
        }

        mysqli_stmt_close(
            $stmtCliente
        );
    }
}
$iniciales =
    strtoupper(substr($nombre, 0, 1)) .
    strtoupper(substr($apellido, 0, 1));


/*
|--------------------------------------------------------------------------
| INICIALIZAR PEDIDO
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['pedido']) || !is_array($_SESSION['pedido'])) {
    $_SESSION['pedido'] = [];
}


/*
|--------------------------------------------------------------------------
| CAMBIAR CANTIDAD DESDE PEDIDOS.PHP
|--------------------------------------------------------------------------
|
| accion = cambiar_cantidad
| delta  = 1  -> sumar
| delta  = -1 -> restar
|
| Si la cantidad llega a cero, el producto se elimina.
|
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['accion']) &&
    $_POST['accion'] === 'cambiar_cantidad'
) {

    $idProducto = isset($_POST['id_producto'])
        ? (int)$_POST['id_producto']
        : 0;

    $delta = isset($_POST['delta'])
        ? (int)$_POST['delta']
        : 0;

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    if (
        $idProducto <= 0 ||
        !in_array($delta, [-1, 1], true)
    ) {

        echo json_encode([
            'ok' => false,
            'mensaje' => 'Datos inválidos.'
        ]);

        exit();
    }

    $productoEncontrado = false;

    foreach ($_SESSION['pedido'] as $indice => $producto) {

        if (
            (int)$producto['idProducto'] ===
            $idProducto
        ) {

            $cantidadActual =
                (int)($producto['cantidad'] ?? 1);

            $nuevaCantidad =
                $cantidadActual + $delta;


            if ($nuevaCantidad <= 0) {

                unset(
                    $_SESSION['pedido'][$indice]
                );

            } else {

                $_SESSION['pedido'][$indice]['cantidad'] =
                    $nuevaCantidad;
            }

            $productoEncontrado = true;

            break;
        }
    }

    $_SESSION['pedido'] =
        array_values($_SESSION['pedido']);


    $cantidadTotal = 0;
    $totalActual = 0;

    foreach ($_SESSION['pedido'] as $producto) {

        $cantidad =
            (int)($producto['cantidad'] ?? 0);

        $precio =
            (float)($producto['precio'] ?? 0);

        $cantidadTotal += $cantidad;

        $totalActual +=
            $cantidad * $precio;
    }

    echo json_encode([
        'ok' => $productoEncontrado,
        'cantidadProductos' => $cantidadTotal,
        'totalPedido' => $totalActual
    ]);

    exit();
}


/*
|--------------------------------------------------------------------------
| CALCULAR PEDIDO
|--------------------------------------------------------------------------
*/

$cantidadProductos = 0;
$totalPedido = 0;

foreach ($_SESSION['pedido'] as $producto) {

    $cantidad =
        (int)($producto['cantidad'] ?? 0);

    $precio =
        (float)($producto['precio'] ?? 0);

    $cantidadProductos += $cantidad;

    $totalPedido +=
        $cantidad * $precio;
}

/*
|--------------------------------------------------------------------------
| GENERAR FACTURA DE VENTA
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['accion']) &&
    $_POST['accion'] === 'generar_factura'
) {

    header('Content-Type: application/json; charset=utf-8');

    if (empty($_SESSION['pedido'])) {

        echo json_encode([
            'ok' => false,
            'mensaje' => 'No hay productos en el pedido.'
        ]);

        exit();
    }

    $metodoPago = trim($_POST['metodo_pago'] ?? '');

    if ($metodoPago === '') {

        echo json_encode([
            'ok' => false,
            'mensaje' => 'No se seleccionó el método de pago.'
        ]);

        exit();
    }

    $idUsuario = (int)($_SESSION['id'] ?? 0);

    if ($idUsuario <= 0) {

        echo json_encode([
            'ok' => false,
            'mensaje' => 'No se encontró el usuario de la sesión.'
        ]);

        exit();
    }

    $subtotal = (float)$totalPedido;
    $impuestos = 0;
    $descuento = 0;
    $total = (float)$totalPedido;

    mysqli_begin_transaction($conexion);

    try {

        /*
        |--------------------------------------------------------------------------
        | 1. CREAR FACTURA
        |--------------------------------------------------------------------------
        |
        | numero_factura queda temporalmente NULL.
        | MySQL genera primero id_factura mediante AUTO_INCREMENT.
        |
        */
        $tokenFactura = bin2hex(random_bytes(32));
        $sqlFactura = "
            INSERT INTO factura (
                numero_factura,
                id_usuario,
                subtotal,
                impuestos,
                descuento,
                total,
                metodo_pago,
                estado,
                token_factura

            )
            VALUES (
                NULL,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'generada',
                 ?
            )
        ";

        $stmtFactura = mysqli_prepare(
            $conexion,
            $sqlFactura
        );

        if (!$stmtFactura) {
            throw new Exception(
                'No se pudo preparar la factura.'
            );
        }

        mysqli_stmt_bind_param(
            $stmtFactura,
            "iddddss",
            $idUsuario,
            $subtotal,
            $impuestos,
            $descuento,
            $total,
            $metodoPago,
            $tokenFactura
        );

        if (!mysqli_stmt_execute($stmtFactura)) {

            throw new Exception(
                'No se pudo guardar la factura.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 2. OBTENER ID_FACTURA GENERADO POR MYSQL
        |--------------------------------------------------------------------------
        */

        $idFactura = mysqli_insert_id($conexion);

        if ($idFactura <= 0) {

            throw new Exception(
                'No se pudo obtener el consecutivo de la factura.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 3. GUARDAR DETALLE DE LA FACTURA
        |--------------------------------------------------------------------------
        */

        $sqlDetalle = "
            INSERT INTO detalle_factura (
                id_factura,
                idProducto,
                cantidad,
                precio_unitario,
                subtotal
            )
            VALUES (?, ?, ?, ?, ?)
        ";

        $stmtDetalle = mysqli_prepare(
            $conexion,
            $sqlDetalle
        );

        if (!$stmtDetalle) {
            throw new Exception(
                'No se pudo preparar el detalle de la factura.'
            );
        }

        foreach ($_SESSION['pedido'] as $producto) {

            $idProducto = (int)(
                $producto['idProducto'] ?? 0
            );

            $cantidad = (int)(
                $producto['cantidad'] ?? 0
            );

            $precioUnitario = (float)(
                $producto['precio'] ?? 0
            );

            $subtotalProducto =
                $cantidad * $precioUnitario;

            if (
                $idProducto <= 0 ||
                $cantidad <= 0 ||
                $precioUnitario < 0
            ) {
                throw new Exception(
                    'Uno de los productos del pedido tiene datos inválidos.'
                );
            }

            mysqli_stmt_bind_param(
                $stmtDetalle,
                "iiidd",
                $idFactura,
                $idProducto,
                $cantidad,
                $precioUnitario,
                $subtotalProducto
            );

            if (!mysqli_stmt_execute($stmtDetalle)) {
                throw new Exception(
                    'No se pudo guardar un producto de la factura.'
                );
            }
        }

        mysqli_stmt_close($stmtDetalle);

        /*
        |--------------------------------------------------------------------------
        | 4. USAR ID_FACTURA COMO NUMERO_FACTURA
        |--------------------------------------------------------------------------
        */

        $sqlNumero = "
            UPDATE factura
            SET numero_factura = ?
            WHERE id_factura = ?
        ";

        $stmtNumero = mysqli_prepare(
            $conexion,
            $sqlNumero
        );

        if (!$stmtNumero) {
            throw new Exception(
                'No se pudo preparar el consecutivo.'
            );
        }

        mysqli_stmt_bind_param(
            $stmtNumero,
            "ii",
            $idFactura,
            $idFactura
        );

        if (!mysqli_stmt_execute($stmtNumero)) {

            throw new Exception(
                'No se pudo asignar el consecutivo.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | 5. CONFIRMAR TRANSACCIÓN
        |--------------------------------------------------------------------------
        */

        mysqli_commit($conexion);

        mysqli_stmt_close($stmtFactura);
        mysqli_stmt_close($stmtNumero);

        /*
        |--------------------------------------------------------------------------
        | 6. RESPUESTA
        |--------------------------------------------------------------------------
        */

        echo json_encode([
            'ok' => true,
            'idFactura' => $idFactura,
            'numeroFactura' => str_pad(
                $idFactura,
                7,
                '0',
                STR_PAD_LEFT
            ),
            'tokenFactura' => $tokenFactura,
            'mensaje' => 'Factura generada correctamente.'
        ]);

        exit();

    } catch (Throwable $e) {

        mysqli_rollback($conexion);

        echo json_encode([
            'ok' => false,
            'mensaje' => $e->getMessage()
        ]);

        exit();
    }
}

/*
|--------------------------------------------------------------------------
| DATOS PARA LA FACTURA
|--------------------------------------------------------------------------
|
| Se deja preparado el espacio para los datos legales.
| Los campos que todavía no tenemos permanecen vacíos.
|
*/

$numeroFactura = '';

$razonSocial = '';
$nit = '';
$direccionRestaurante = '';
$telefonoRestaurante = '';
$correoRestaurante = '';
$responsabilidadTributaria = '';


$cufe = '';
$observaciones = '';

?>
<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0">

<title>Pedidos - Sabora</title>


<style>

/*
|--------------------------------------------------------------------------
| GENERAL
|--------------------------------------------------------------------------
*/

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Arial, sans-serif;
}

body {
    background: #efe7e2;
    color: #333;
}


/*
|--------------------------------------------------------------------------
| ENCABEZADO
|--------------------------------------------------------------------------
*/

header {
    background: #ff7a3d;
    color: white;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

header h1 {
    font-size: 28px;
}

.usuario {
    text-align: right;
    font-size: 15px;
}

.avatar {
    width: 55px;
    height: 55px;
    border-radius: 50%;
    background: white;
    color: #ff7a3d;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 22px;
    font-weight: bold;
    margin-left: auto;
    margin-bottom: 10px;
    box-shadow: 0 3px 8px rgba(0,0,0,.15);
}


/*
|--------------------------------------------------------------------------
| CONTENEDOR
|--------------------------------------------------------------------------
*/

.container {
    width: 95%;
    max-width: 1200px;
    margin: 40px auto;
}


/*
|--------------------------------------------------------------------------
| TARJETAS
|--------------------------------------------------------------------------
*/

.tarjeta {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 5px 15px rgba(0,0,0,.10);
    margin-bottom: 30px;
}

.tarjeta h2 {
    color: #ff7a3d;
    margin-bottom: 20px;
}

.tarjeta p {
    margin: 12px 0;
    font-size: 17px;
}


/*
|--------------------------------------------------------------------------
| PEDIDO
|--------------------------------------------------------------------------
*/

.pedido-lista {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.pedido-item {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 15px;
    border: 1px solid #eee;
    border-radius: 12px;
    background: #fff;
}

.pedido-imagen,
.pedido-imagen-vacia {
    width: 90px;
    height: 90px;
    border: 1px solid #f0e0d5;
    border-radius: 12px;
    flex-shrink: 0;
}

.pedido-imagen {
    object-fit: contain;
    background: white;
}

.pedido-imagen-vacia {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #777;
    font-size: 13px;
}

.pedido-info {
    flex: 1;
    min-width: 0;
}

.pedido-nombre {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 7px;
}

.pedido-precio {
    color: #666;
    margin-bottom: 8px;
}


/*
|--------------------------------------------------------------------------
| BOTONES + Y -
|--------------------------------------------------------------------------
*/

.pedido-cantidad {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-cantidad {
    width: 34px;
    height: 34px;
    border: 1px solid #f0e0d5;
    border-radius: 50%;
    background: white;
    color: #ff7a3d;
    font-size: 21px;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-cantidad:hover {
    background: #ff7a3d;
    color: white;
}

.btn-cantidad:disabled {
    opacity: .5;
    cursor: not-allowed;
}

.cantidad-valor {
    min-width: 35px;
    text-align: center;
    font-weight: bold;
    font-size: 17px;
}

.pedido-subtotal {
    min-width: 140px;
    text-align: right;
    color: #ff7a3d;
    font-weight: bold;
    font-size: 18px;
}


/*
|--------------------------------------------------------------------------
| TOTAL
|--------------------------------------------------------------------------
*/

.resumen-total {
    border-top: 1px solid #ddd;
    margin-top: 20px;
    padding-top: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 22px;
    font-weight: bold;
}

.resumen-total span:last-child {
    color: #ff7a3d;
}


/*
|--------------------------------------------------------------------------
| PEDIDO VACÍO
|--------------------------------------------------------------------------
*/

.pedido-vacio {
    text-align: center;
    padding: 35px 15px;
    color: #666;
}

.btn-menu {
    display: inline-block;
    margin-top: 15px;
    background: #ff7a3d;
    color: white;
    text-decoration: none;
    padding: 12px 25px;
    border-radius: 10px;
}

.btn-menu:hover {
    background: #e5672f;
}


/*
|--------------------------------------------------------------------------
| MÉTODOS DE PAGO
|--------------------------------------------------------------------------
*/

.metodos-pago {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 15px;
}

.metodo-pago {
    position: relative;
}

.metodo-pago input {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.metodo-pago label {
    display: block;
    padding: 18px;
    border: 2px solid #eee;
    border-radius: 12px;
    cursor: pointer;
    font-size: 17px;
    background: white;
}

.metodo-pago label:hover {
    border-color: #ff7a3d;
}

.metodo-pago input:checked + label {
    border-color: #ff7a3d;
    background: #fff4ee;
    color: #ff7a3d;
    font-weight: bold;
}


/*
|--------------------------------------------------------------------------
| ACCIONES
|--------------------------------------------------------------------------
*/

.acciones {
    margin-top: 25px;
    display: flex;
    justify-content: center;
    gap: 20px;
    flex-wrap: wrap;
}

.btn {
    border: none;
    text-decoration: none;
    padding: 13px 30px;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
    display: inline-block;
}

.btn-volver {
    background: #ff7a3d;
    color: white;
}

.btn-volver:hover {
    background: #666;
}

.btn-confirmar {
    background: #ff7a3d;
    color: white;
}

.btn-confirmar:hover {
    background: #e5672f;
}


/*
|--------------------------------------------------------------------------
| FACTURA - VENTANA
|--------------------------------------------------------------------------
*/

.factura-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.55);
    z-index: 2000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 18px;
}

.factura-overlay.abierto {
    display: flex;
}

.factura {
    width: min(1100px, 96vw);
    max-height: 94vh;
    overflow-y: auto;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 12px 40px rgba(0,0,0,.30);
    padding: 24px;
    color: #222;
}

.factura.pantalla-completa {
    width: 100vw;
    height: 100vh;
    max-width: none;
    max-height: none;
    border-radius: 0;
    padding: 32px 45px;
    overflow-y: auto;
}

.factura-overlay.factura-maximizada {
    padding: 0;
}

/* CABECERA */
.factura-encabezado {
    display: grid;
    grid-template-columns: 1fr 1.15fr 1fr;
    align-items: center;
    gap: 20px;
    border-bottom: 2px solid #ff7a3d;
    padding-bottom: 14px;
    margin-bottom: 14px;
}

/* ============================================================
   LOGO OFICIAL SABORA
   ============================================================ */

.factura-logo {
    display: flex;
    align-items: center;
    justify-content: flex-start;
    gap: 12px;
}

/* Círculo del logo */
.factura-logo img {
    width: 88px;
    height: 88px;
    object-fit: contain;
    display: block;
    flex-shrink: 0;
}

/* Contenedor del texto */
.factura-logo-texto {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
}

/* SABORA */
.factura-logo-nombre {
    color: #ff7a3d !important;
    font-size: 42px;
    font-weight: 700;
    line-height: 0.9;
    margin: 0;
    padding: 0;
}

/* FAST FOOD */
.factura-logo-subtitulo {
    color: #ff7a3d !important;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 6px;
    line-height: 1;
    margin-top: 9px;
    padding: 0;
}
.factura-logo-fallback {
    color: #ff7a3d;
    font-size: 30px;
    font-weight: 800;
}

.factura-titulo {
    text-align: center;
}

.factura-titulo h2 {
    margin: 0 0 8px;
    color: #111;
    font-size: 25px;
}

.numero-factura {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    min-width: 175px;
    padding: 7px 15px;
    border: 1.5px solid #ff7a3d;
    border-radius: 10px;
    color: #ff7a3d;
}

.numero-factura span {
    font-size: 12px;
    font-weight: bold;
}

.numero-factura strong {
    font-size: 22px;
}

.factura-fecha {
    display: grid;
    gap: 9px;
    justify-content: end;
}

.factura-fecha p {
    margin: 0;
    font-size: 13px;
    line-height: 1.3;
}

.factura-fecha .dato {
    color: #ff7a3d;
    font-weight: bold;
}

/* DATOS DEL RESTAURANTE / CLIENTE */
.factura-datos-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    border-bottom: 1px dashed #aaa;
    padding: 0 0 14px;
    margin-bottom: 12px;
}

.factura-bloque {
    min-width: 0;
}

.factura-bloque + .factura-bloque {
    border-left: 1px dotted #bbb;
    padding-left: 24px;
}

.factura-bloque h3,
.factura-detalle-titulo,
.factura-pago h3 {
    margin: 0 0 9px;
    color: #ff7a3d;
    font-size: 15px;
    font-weight: 800;
}

.factura-bloque p {
    display: grid;
    grid-template-columns: 155px 1fr;
    gap: 8px;
    margin: 5px 0;
    font-size: 12.5px;
    line-height: 1.3;
}

.campo-vacio {
    color: #555;
}

/* PRODUCTOS + TOTALES */
.factura-detalle {
    padding-top: 2px;
}

.factura-productos-resumen {
    display: grid;
    grid-template-columns: minmax(0, 1.65fr) minmax(270px, .8fr);
    gap: 16px;
    align-items: start;
}

.factura-tabla-contenedor {
    border: 1px solid #ff7a3d;
    border-radius: 8px;
    overflow: hidden;
}

.factura-tabla {
    width: 100%;
    border-collapse: collapse;
    margin: 0;
}

.factura-tabla th,
.factura-tabla td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
    font-size: 12px;
}

.factura-tabla th {
    background: #fff4ee;
    color: #222;
}

.factura-tabla tbody tr:last-child td {
    border-bottom: 0;
}

.factura-tabla th:first-child,
.factura-tabla td:first-child {
    width: 7%;
    text-align: center;
}

.factura-tabla th:nth-child(2),
.factura-tabla td:nth-child(2) {
    width: 40%;
    text-align: left;
}

.factura-tabla th:nth-child(3),
.factura-tabla td:nth-child(3) {
    width: 16%;
    text-align: center;
}

.factura-tabla th:nth-child(4),
.factura-tabla td:nth-child(4),
.factura-tabla th:nth-child(5),
.factura-tabla td:nth-child(5) {
    width: 18.5%;
    text-align: right;
}

/* TOTALES */
.factura-totales {
    width: 100%;
    max-width: none;
    margin: 0;
    border: 1px solid #ff7a3d;
    border-radius: 8px;
    padding: 12px 14px;
}

.factura-total-linea {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 6px 0;
    font-size: 13px;
}

.factura-total-final {
    border-top: 2px solid #222;
    margin-top: 7px;
    padding-top: 9px;
    font-size: 19px;
    font-weight: bold;
}

.factura-total-final span:last-child {
    color: #ff7a3d;
}

/* FORMA DE PAGO */
.factura-pago {
    width: min(430px, 100%);
    margin-top: 12px;
    padding: 11px 14px;
    border: 1px solid #aaa;
    border-radius: 8px;
    background: #fff;
}

.factura-pago p {
    margin: 4px 0;
    font-size: 13px;
}

.factura-pago strong {
    color: #222;
}

/* PIE */
.factura-pie {
    display: grid;
    grid-template-columns: 105px minmax(0, 1.25fr) minmax(250px, .9fr);
    gap: 16px;
    align-items: center;
    border-top: 2px solid #ff7a3d;
    margin-top: 16px;
    padding-top: 13px;
}

.factura-qr {
    width: 92px;
    height: 92px;
    border: 1px solid #aaa;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #777;
    font-size: 11px;

    margin-left: 19px;
}

.factura-legal {
    margin-left: 20px;
    padding: 0;
    border: 0;
    color: #555;
    font-size: 10.5px;
    line-height: 1.35;
}

.factura-legal p {
    margin: 4px 0;
}

.factura-agradecimiento {
    border-left: 1px dotted #aaa;
    padding-left: 16px;
    color: #555;
    font-size: 10.5px;
    line-height: 1.4;
}

.factura-agradecimiento h3 {
    margin: 0 0 5px;
    color: #ff7a3d;
    font-size: 15px;
}

/* BOTONES */
.factura-acciones {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 14px;
    padding-top: 12px;
    border-top: 1px solid #ddd;
    flex-wrap: wrap;
}

.btn-factura {
    border: none;
    padding: 10px 22px;
    border-radius: 9px;
    cursor: pointer;
    font-weight: bold;
    font-size: 14px;
}

.btn-pantalla-completa,
.btn-imprimir {
    background: #ff7a3d;
    color: white;
}

.btn-pantalla-completa:hover,
.btn-imprimir:hover {
    background: #e5672f;
}

.btn-cerrar-factura {
    background: #777;
    color: white;
}
/*
|--------------------------------------------------------------------------
| FOOTER SABORA
|--------------------------------------------------------------------------
*/

footer {
    width: 100%;
    text-align: center;
    color: #666;
    margin: 30px auto 0;
    padding: 20px 15px;
    font-size: 15px;
    line-height: 1.6;
}

footer strong {
    color: #ff7a3d;
    font-weight: 700;
}
/* RESPONSIVE */
@media (max-width: 800px) {

    .factura {
        width: 98vw;
        padding: 16px;
    }

    .factura-encabezado {
        grid-template-columns: 1fr;
        text-align: center;
    }

    .factura-logo {
        justify-content: center;
    }

    .factura-logo img {
        width: 145px;
        max-height: 75px;
    }

    .factura-fecha {
        grid-template-columns: 1fr 1fr;
        justify-content: center;
        text-align: left;
    }

    .factura-datos-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .factura-bloque + .factura-bloque {
        border-left: 0;
        border-top: 1px dotted #bbb;
        padding-left: 0;
        padding-top: 12px;
    }

    .factura-productos-resumen {
        grid-template-columns: 1fr;
    }

    .factura-pie {
        grid-template-columns: 90px 1fr;
    }

    .factura-agradecimiento {
        grid-column: 1 / -1;
        border-left: 0;
        border-top: 1px dotted #aaa;
        padding-left: 0;
        padding-top: 10px;
    }

    .factura-acciones {
        flex-direction: column;
    }

    .btn-factura {
        width: 100%;
    }
}

/* IMPRESIÓN: UNA HOJA CARTA */
@media print {

    @page {
        size: Letter portrait;
        margin: 7mm;
    }

    body * {
        visibility: hidden !important;
    }

    #facturaOverlay,
    #facturaOverlay * {
        visibility: visible !important;
    }

    #facturaOverlay {
        position: absolute !important;
        inset: 0 !important;
        display: block !important;
        background: white !important;
        padding: 0 !important;
    }

    .factura {
        width: 100% !important;
        max-width: none !important;
        max-height: none !important;
        overflow: visible !important;
        box-shadow: none !important;
        border-radius: 0 !important;
        padding: 4mm !important;
    }

    .factura-encabezado {
        padding-bottom: 8px;
        margin-bottom: 8px;
    }

    .factura-logo img {
        width: 125px;
        max-height: 62px;
    }

    .factura-titulo h2 {
        font-size: 19px;
        margin-bottom: 4px;
    }

    .numero-factura {
        min-width: 130px;
        padding: 4px 10px;
    }

    .numero-factura span {
        font-size: 9px;
    }

    .numero-factura strong {
        font-size: 16px;
    }

    .factura-fecha p,
    .factura-bloque p,
    .factura-tabla th,
    .factura-tabla td,
    .factura-total-linea,
    .factura-pago p {
        font-size: 9px;
    }

    .factura-bloque h3,
    .factura-detalle-titulo,
    .factura-pago h3 {
        font-size: 11px;
        margin-bottom: 5px;
    }

    .factura-datos-grid {
        padding-bottom: 7px;
        margin-bottom: 7px;
        gap: 12px;
    }

    .factura-bloque p {
        grid-template-columns: 110px 1fr;
        margin: 2px 0;
    }

    .factura-tabla th,
    .factura-tabla td {
        padding: 4px 5px;
    }

    .factura-totales {
        padding: 7px 9px;
    }

    .factura-total-linea {
        padding: 3px 0;
    }

    .factura-total-final {
        font-size: 13px;
        padding-top: 5px;
    }

    .factura-pago {
        margin-top: 7px;
        padding: 6px 9px;
    }

    .factura-pie {
        grid-template-columns: 70px minmax(0, 1.25fr) minmax(180px, .9fr);
        gap: 9px;
        margin-top: 8px;
        padding-top: 7px;
    }

    .factura-qr {
        width: 64px;
        height: 64px;
    }

    .factura-legal,
    .factura-agradecimiento {
        font-size: 8px;
    }

    .factura-agradecimiento h3 {
        font-size: 10px;
    }

    .factura-acciones {
        display: none !important;
    }
}

</style>

</head>


<body>


<!-- ============================================================
     ENCABEZADO
     ============================================================ -->

<header>

    <h1>SABORA</h1>

    <div class="usuario">

        <div class="avatar">

            <?= htmlspecialchars($iniciales); ?>

        </div>

        <strong>

            <?= htmlspecialchars(
                $nombre . " " . $apellido
            ); ?>

        </strong>

        <br>

        <?= htmlspecialchars($correo); ?>

        <br>

        Rol:

        <strong>

            <?= htmlspecialchars(
                ucfirst($_SESSION['rol'])
            ); ?>

        </strong>

    </div>

</header>


<div class="container">


<!-- ============================================================
     PEDIDO
     ============================================================ -->

<div class="tarjeta">

    <h2>🛒 Mi Pedido</h2>


    <?php if (empty($_SESSION['pedido'])): ?>

        <div class="pedido-vacio">

            <p>
                No hay productos en su pedido.
            </p>

            <a
                href="menu.php"
                class="btn-menu">

                Ir al menú

            </a>

        </div>

    <?php else: ?>


        <div class="pedido-lista">


            <?php foreach ($_SESSION['pedido'] as $producto): ?>

                <?php

                $cantidad =
                    (int)($producto['cantidad'] ?? 1);

                $precio =
                    (float)($producto['precio'] ?? 0);

                $subtotal =
                    $cantidad * $precio;

                $idProducto =
                    (int)($producto['idProducto'] ?? 0);

                ?>


                <div class="pedido-item">


                    <?php if (!empty($producto['imagen'])): ?>

                        <img
                            class="pedido-imagen"
                            src="<?= htmlspecialchars($producto['imagen']); ?>"
                            alt="<?= htmlspecialchars($producto['nombre']); ?>">

                    <?php else: ?>

                        <div class="pedido-imagen-vacia">

                            Sin foto

                        </div>

                    <?php endif; ?>


                    <div class="pedido-info">


                        <div class="pedido-nombre">

                            <?= htmlspecialchars(
                                $producto['nombre']
                            ); ?>

                        </div>


                        <div class="pedido-precio">

                            Precio unitario:

                            $<?= number_format(
                                $precio,
                                0,
                                ',',
                                '.'
                            ); ?>

                        </div>


                        <div class="pedido-cantidad">


                            <button
                                type="button"
                                class="btn-cantidad"
                                onclick="cambiarCantidad(
                                    <?= $idProducto; ?>,
                                    -1,
                                    this
                                )"
                                aria-label="Restar producto">

                                −

                            </button>


                            <span class="cantidad-valor">

                                <?= $cantidad; ?>

                            </span>


                            <button
                                type="button"
                                class="btn-cantidad"
                                onclick="cambiarCantidad(
                                    <?= $idProducto; ?>,
                                    1,
                                    this
                                )"
                                aria-label="Agregar producto">

                                +

                            </button>


                        </div>

                    </div>


                    <div class="pedido-subtotal">

                        $<?= number_format(
                            $subtotal,
                            0,
                            ',',
                            '.'
                        ); ?>

                    </div>


                </div>

            <?php endforeach; ?>


        </div>


        <div class="resumen-total">

            <span>
                Total
            </span>

            <span>

                $<?= number_format(
                    $totalPedido,
                    0,
                    ',',
                    '.'
                ); ?>

            </span>

        </div>


    <?php endif; ?>

</div>


<!-- ============================================================
     MÉTODO DE PAGO
     ============================================================ -->

<?php if (!empty($_SESSION['pedido'])): ?>

<div class="tarjeta">

    <h2>💳 Método de pago</h2>

    <p>
        Seleccione el método de pago que utilizará para su pedido.
    </p>


    <form
        id="formPago"
        method="POST"
        action="#">


        <div class="metodos-pago">


            <div class="metodo-pago">

                <input
                    type="radio"
                    id="efectivo"
                    name="metodo_pago"
                    value="Efectivo">

                <label for="efectivo">

                    💵 Efectivo

                </label>

            </div>


            <div class="metodo-pago">

                <input
                    type="radio"
                    id="transferencia"
                    name="metodo_pago"
                    value="Transferencia">

                <label for="transferencia">

                    🏦 Transferencia

                </label>

            </div>


        </div>


        <div class="acciones">


            <a
                href="menu.php"
                class="btn btn-volver">

                 ← Volver al menú

            </a>


            <button
                type="submit"
                class="btn btn-confirmar">

                Confirmar Pedido

            </button>


        </div>


    </form>

</div>

<?php endif; ?>


</div>


<!-- ============================================================
     FACTURA
     ============================================================ -->

<div
    id="facturaOverlay"
    class="factura-overlay"
    aria-hidden="true">

    <div
        class="factura"
        id="factura">

        <!-- CABECERA -->
        <div class="factura-encabezado">

       <div class="factura-logo">

    <img
        src="img/logo-sabora.png"
        alt="Logo Sabora">

    <div class="factura-logo-texto">

        <div class="factura-logo-nombre">
            Sabora
        </div>

       

    </div>

</div>
            <div class="factura-titulo">

                <h2>FACTURA DE VENTA</h2>

                <div class="numero-factura">

                    <span>N° FACTURA</span>

     <strong>
    <strong id="numeroFactura">
      <?= htmlspecialchars(
        $numeroFactura !== ''
            ? $numeroFactura
            : '0000000'
    ); ?>
</strong>
                    </strong>

                </div>

            </div>

            <div class="factura-fecha">

                <p>
                    <span class="dato">Fecha:</span><br>
                    <?= date('d/m/Y'); ?>
                </p>

                <p>
                    <span class="dato">Hora:</span><br>
                    <?= date('H:i:s'); ?>
                </p>

            </div>

        </div>


        <!-- DATOS RESTAURANTE / CLIENTE -->
        <div class="factura-datos-grid">

            <section class="factura-bloque">

                <h3>🏪 DATOS DEL RESTAURANTE</h3>

                <p>
                    <strong>Razón social:</strong>
                    <span class="campo-vacio">
                        <?= htmlspecialchars($razonSocial !== '' ? $razonSocial : '—'); ?>
                    </span>
                </p>

                <p>
                    <strong>NIT:</strong>
                    <span class="campo-vacio">
                        <?= htmlspecialchars($nit !== '' ? $nit : '—'); ?>
                    </span>
                </p>

                <p>
                    <strong>Dirección:</strong>
                    <span class="campo-vacio">
                        <?= htmlspecialchars($direccionRestaurante !== '' ? $direccionRestaurante : '—'); ?>
                    </span>
                </p>

                <p>
                    <strong>Teléfono:</strong>
                    <span class="campo-vacio">
                        <?= htmlspecialchars($telefonoRestaurante !== '' ? $telefonoRestaurante : '—'); ?>
                    </span>
                </p>

                <p>
                    <strong>Correo:</strong>
                    <span class="campo-vacio">
                        <?= htmlspecialchars($correoRestaurante !== '' ? $correoRestaurante : '—'); ?>
                    </span>
                </p>

                <p>
                    <strong>Responsabilidad tributaria:</strong>
                    <span class="campo-vacio">
                        <?= htmlspecialchars($responsabilidadTributaria !== '' ? $responsabilidadTributaria : '—'); ?>
                    </span>
                </p>

            </section>


            <section class="factura-bloque">

                <h3>👤 DATOS DEL CLIENTE</h3>

                <p>
                    <strong>Nombre:</strong>
                    <span>
                        <?= htmlspecialchars(
                            trim($nombre . ' ' . $apellido)
                        ); ?>
                    </span>
                </p>

                <p>
                    <p>
    <strong>Documento:</strong>
    <span class="campo-vacio">
        <?= htmlspecialchars(
            $identificacion !== ''
                ? $identificacion
                : '—'
        ); ?>
    </span>
</p>

                </p>

                <p>
                    <strong>Correo:</strong>
                    <span>
                        <?= htmlspecialchars($correo); ?>
                    </span>
                </p>

                <p>
                    <strong>Dirección:</strong>
                    <span class="campo-vacio">
                        <?= htmlspecialchars($direccionCliente !== '' ? $direccionCliente : '—'); ?>
                    </span>
                </p>

            </section>

        </div>


        <!-- PRODUCTOS Y TOTALES -->
        <section class="factura-detalle">

            <h3 class="factura-detalle-titulo">
                🛒 DETALLE DE PRODUCTOS
            </h3>

            <div class="factura-productos-resumen">

                <div class="factura-tabla-contenedor">

                    <table class="factura-tabla">

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Precio unit.</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php
                        $numeroProducto = 1;

                        foreach ($_SESSION['pedido'] as $producto):
                        ?>

                            <?php

                            $cantidadFactura =
                                (int)($producto['cantidad'] ?? 0);

                            $precioFactura =
                                (float)($producto['precio'] ?? 0);

                            $subtotalFactura =
                                $cantidadFactura * $precioFactura;

                            ?>

                            <tr>

                                <td>
                                    <?= $numeroProducto++; ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $producto['nombre']
                                    ); ?>
                                </td>

                                <td>
                                    <?= $cantidadFactura; ?>
                                </td>

                                <td>
                                    $<?= number_format(
                                        $precioFactura,
                                        0,
                                        ',',
                                        '.'
                                    ); ?>
                                </td>

                                <td>
                                    $<?= number_format(
                                        $subtotalFactura,
                                        0,
                                        ',',
                                        '.'
                                    ); ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>


                <div class="factura-totales">

                    <div class="factura-total-linea">
                        <span>Subtotal:</span>
                        <span>
                            $<?= number_format(
                                $totalPedido,
                                0,
                                ',',
                                '.'
                            ); ?>
                        </span>
                    </div>

                    <div class="factura-total-linea">
                        <span>Impuestos:</span>
                        <span>$0</span>
                    </div>

                    <div class="factura-total-linea">
                        <span>Descuentos:</span>
                        <span>$0</span>
                    </div>

                    <div class="factura-total-linea factura-total-final">
                        <span>TOTAL A PAGAR:</span>
                        <span>
                            $<?= number_format(
                                $totalPedido,
                                0,
                                ',',
                                '.'
                            ); ?>
                        </span>
                    </div>

                </div>

            </div>


            <!-- FORMA DE PAGO -->
            <div class="factura-pago">

                <h3>💵 FORMA DE PAGO</h3>

                <p>
                    <strong>Forma de pago:</strong>
                    Contado
                </p>

                <p>
                    <strong>Medio de pago:</strong>
                    EFECTIVO
                </p>

            </div>

        </section>


        <!-- PIE -->
        <div class="factura-pie">

          <div 
          class="factura-qr" 
          id="qrFactura"
          data-token="<?= htmlspecialchars(
            $tokenFactura ?? '',
            ENT_QUOTES,
            'UTF-8'
        ) ?>"
    ></div>

            <div class="factura-legal">

                <p>
                    <strong>CUFE:</strong>
                    <?= htmlspecialchars(
                        $cufe !== '' ? $cufe : '—'
                    ); ?>
                </p>

                <p>
                    <strong>Código QR:</strong>
                    Token de verificación
                </p>

                <p>
                    <strong>Observaciones:</strong>
                    <?= htmlspecialchars(
                        $observaciones !== '' ? $observaciones : '—'
                    ); ?>
                </p>

                <p>
                    <strong>Información tributaria:</strong>
                    —
                </p>

            </div>

            <div class="factura-agradecimiento">

                <h3>☺ ¡GRACIAS POR SU COMPRA!</h3>

                <p>
                    Esta factura corresponde a la representación
                    de la factura dentro del sistema Sabora.
                </p>

                <p>
                    Los datos legales y tributarios que aún no
                    están disponibles se mantienen vacíos para
                    ser configurados posteriormente.
                </p>

            </div>

        </div>


        <!-- BOTONES -->
        <div class="factura-acciones">

            <button
                type="button"
                class="btn-factura btn-pantalla-completa"
                id="btnPantallaCompleta"
                onclick="alternarPantallaCompleta()">
                ⛶ Pantalla completa
            </button>

            <button
                type="button"
                class="btn-factura btn-imprimir"
                onclick="imprimirFactura()">
                🖨 Imprimir factura
            </button>

            <button
                type="button"
                class="btn-factura btn-cerrar-factura"
                onclick="cerrarFactura()">
                Cerrar
            </button>

        </div>

    </div>

</div>

<script>

/*
|--------------------------------------------------------------------------
| PANTALLA COMPLETA DE LA FACTURA
|--------------------------------------------------------------------------
*/

function alternarPantallaCompleta() {

    const factura =
        document.getElementById('factura');

    const overlay =
        document.getElementById('facturaOverlay');

    const boton =
        document.getElementById('btnPantallaCompleta');

    if (!factura || !overlay || !boton) {
        console.error(
            'No se encontraron los elementos de pantalla completa.'
        );
        return;
    }

    const pantallaCompleta =
        factura.classList.toggle('pantalla-completa');

    overlay.classList.toggle(
        'factura-maximizada',
        pantallaCompleta
    );

    if (pantallaCompleta) {

        boton.textContent =
            '↙ Salir de pantalla completa';

    } else {

        boton.textContent =
            '⛶ Pantalla completa';
    }
}

</script>


<?php
require_once __DIR__ . '/includes/footer.php';
?>

<script>

/*
|--------------------------------------------------------------------------
| CAMBIAR CANTIDAD
|--------------------------------------------------------------------------
*/

function cambiarCantidad(
    idProducto,
    delta,
    boton
) {

    if (boton) {
        boton.disabled = true;
    }


    const datos =
        new FormData();

    datos.append(
        'accion',
        'cambiar_cantidad'
    );

    datos.append(
        'id_producto',
        idProducto
    );

    datos.append(
        'delta',
        delta
    );


    fetch(
        'Pedidos.php',
        {
            method: 'POST',
            body: datos,
            headers: {
                'X-Requested-With':
                    'XMLHttpRequest'
            }
        }
    )
    .then(function(respuesta) {

        if (!respuesta.ok) {

            throw new Error(
                'Error HTTP ' +
                respuesta.status
            );
        }

        return respuesta.json();

    })
    .then(function(datos) {

        if (!datos.ok) {

            throw new Error(
                datos.mensaje ||
                'No se pudo actualizar el pedido.'
            );
        }


        /*
         * Recargar la página actualiza:
         * cantidad,
         * subtotal,
         * total
         * y factura.
         */

        window.location.reload();

    })
    .catch(function(error) {

        console.error(error);

        alert(
            'No fue posible modificar la cantidad.'
        );


        if (boton) {
            boton.disabled = false;
        }

    });

}


/*
|--------------------------------------------------------------------------
| ABRIR FACTURA
|--------------------------------------------------------------------------
*/

function abrirFactura() {

    const overlay =
        document.getElementById(
            'facturaOverlay'
        );


    if (!overlay) {

        alert(
            'No se encontró la ventana de factura.'
        );

        return;
    }


    overlay.classList.add(
        'abierto'
    );


    overlay.setAttribute(
        'aria-hidden',
        'false'
    );


    document.body.style.overflow =
        'hidden';
}


/*
|--------------------------------------------------------------------------
| CERRAR FACTURA
|--------------------------------------------------------------------------
*/

function cerrarFactura() {

    const overlay =
        document.getElementById(
            'facturaOverlay'
        );


    if (!overlay) {
        return;
    }


    overlay.classList.remove(
        'abierto'
    );


    overlay.setAttribute(
        'aria-hidden',
        'true'
    );


    document.body.style.overflow =
        '';
}


/*
|--------------------------------------------------------------------------
| IMPRIMIR FACTURA
|--------------------------------------------------------------------------
*/

function imprimirFactura() {

    window.print();

}


/*
|--------------------------------------------------------------------------
| CONFIRMAR MÉTODO DE PAGO
|--------------------------------------------------------------------------
|
| En esta etapa:
|
| EFECTIVO       -> genera factura + detalle y abre la factura.
| TRANSFERENCIA  -> todavía no se procesa.
|
| El inventario todavía no se descuenta.
|
|--------------------------------------------------------------------------
*/

const formularioPago =
    document.getElementById(
        'formPago'
    );


if (formularioPago) {

    formularioPago.addEventListener(
        'submit',
        function(evento) {

            evento.preventDefault();


            const metodo =
                document.querySelector(
                    'input[name="metodo_pago"]:checked'
                );


            /*
             * No se seleccionó ningún método.
             */

            if (!metodo) {

                alert(
                    'Seleccione un método de pago antes de confirmar el pedido.'
                );

                return;
            }


            /*
             * OPCIÓN 1: EFECTIVO
             */

            if (metodo.value === 'Efectivo') {

    const datos = new FormData();

    datos.append(
        'accion',
        'generar_factura'
    );

    datos.append(
        'metodo_pago',
        metodo.value
    );

    fetch('Pedidos.php', {
        method: 'POST',
        body: datos,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(function(respuesta) {

        if (!respuesta.ok) {
            throw new Error(
                'Error HTTP ' + respuesta.status
            );
        }

        return respuesta.json();

    })
    .then(function(datos) {

        if (!datos.ok) {

            alert(
                datos.mensaje ||
                'No se pudo generar la factura.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | ACTUALIZAR NUMERO DE FACTURA EN PANTALLA
        |--------------------------------------------------------------------------
        */

        const numeroFactura =
            document.querySelector(
                '.numero-factura strong'
            );

        if (numeroFactura) {

            numeroFactura.textContent =
                datos.numeroFactura;
        }
          /*
        |--------------------------------------------------------------------------
        | GENERAR / ACTUALIZAR QR
        |--------------------------------------------------------------------------
        | El QR debe utilizar el token devuelto por PHP en esta misma
        | solicitud. No se utiliza el token PHP de la carga inicial,
        | porque en ese momento todavía no existe la factura.
        |--------------------------------------------------------------------------
        */

        if (!generarQR(datos.tokenFactura)) {
            throw new Error('No fue posible generar el código QR.');
        }

        /*
        |--------------------------------------------------------------------------
        | MOSTRAR FACTURA
        |--------------------------------------------------------------------------
        */

        abrirFactura();

    })
    .catch(function(error) {

        console.error('Error en la generación de la factura:', error);

        alert(
            error.message ||
            'No fue posible generar la factura.'
        );

    });

    return;
}


            /*
             * OPCIÓN 2: TRANSFERENCIA
             */

            if (
                metodo.value ===
                'Transferencia'
            ) {

                alert(
                    'La opción de transferencia se desarrollará en la siguiente etapa.'
                );

                return;
            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| CERRAR FACTURA CON ESC
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(evento) {

        if (
            evento.key === 'Escape' &&
            document
                .getElementById(
                    'facturaOverlay'
                )
                ?.classList.contains(
                    'abierto'
                )
        ) {

            cerrarFactura();

        }

    }
);


/*
|--------------------------------------------------------------------------
| CERRAR FACTURA AL HACER CLICK FUERA
|--------------------------------------------------------------------------
*/

document
    .getElementById('facturaOverlay')
    ?.addEventListener(
        'click',
        function(evento) {

            if (
                evento.target ===
                this
            ) {

                cerrarFactura();

            }

        }
    );

</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
/*
|--------------------------------------------------------------------------
| GENERAR QR DE LA FACTURA
|--------------------------------------------------------------------------
|
| Esta función se ejecuta después de que PHP devuelve la factura generada.
| Recibe directamente el token devuelto por fetch().
|
*/
function generarQR(tokenFactura) {

    const contenedorQR = document.getElementById('qrFactura');

    if (!contenedorQR) {
        console.error('No se encontró el contenedor #qrFactura.');
        return false;
    }

    if (typeof QRCode === 'undefined') {
        console.error('La librería QRCode no está disponible.');
        contenedorQR.innerHTML = 'Error: QR no disponible';
        return false;
    }

    if (!tokenFactura || typeof tokenFactura !== 'string') {
        console.error('La factura no tiene un token válido.', tokenFactura);
        contenedorQR.innerHTML = 'Sin QR';
        return false;
    }

    // Elimina cualquier QR anterior antes de generar el nuevo.
    contenedorQR.innerHTML = '';

    // Conserva el token en el HTML para futuras operaciones de la interfaz.
    contenedorQR.dataset.token = tokenFactura;

    try {

        new QRCode(contenedorQR, {
            text: tokenFactura,
            width: 110,
            height: 110,
            correctLevel: QRCode.CorrectLevel.M
        });

        return true;

    } catch (error) {

        console.error('Error generando el QR:', error);
        contenedorQR.innerHTML = 'Error al generar QR';
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| QR CUANDO LA FACTURA YA VIENE CARGADA DESDE PHP
|--------------------------------------------------------------------------
|
| Este caso sirve cuando Pedidos.php se carga con un token existente.
| En el flujo normal de confirmación, el QR se genera desde la respuesta
| AJAX mediante generarQR(datos.tokenFactura).
|
*/
document.addEventListener('DOMContentLoaded', function () {

    const contenedorQR = document.getElementById('qrFactura');

    if (!contenedorQR) {
        return;
    }

    const tokenInicial = contenedorQR.dataset.token || '';

    if (tokenInicial) {
        generarQR(tokenInicial);
    }

});
</script>

</body>

</html>