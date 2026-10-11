<?php



session_start();



require_once "conexion.php";



/*

|--------------------------------------------------------------------------

| SEGURIDAD: SOLO CAJERO

|--------------------------------------------------------------------------

*/



if (!isset($_SESSION['correo'])) {

    header("Location: Cajero.html");

    exit();

}



if (strtolower(trim($_SESSION['rol'] ?? '')) !== 'cajero') {

    header("Location: Cliente.html");

    exit();

}

// Identificar al cajero autenticado; el cliente de la venta se valida por separado.
$idCajero = filter_var(
    $_SESSION['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($idCajero === false || $idCajero === null) {
    http_response_code(401);
    exit('No se encontró el ID del cajero en la sesión. Vuelve a iniciar sesión.');
}



/*

|--------------------------------------------------------------------------

| FUNCIONES

|--------------------------------------------------------------------------

*/



function e($valor): string

{

    return htmlspecialchars(

        (string)$valor,

        ENT_QUOTES,

        'UTF-8'

    );

}



function dinero($valor): string

{

    return '$' . number_format(

        (float)$valor,

        0,

        ',',

        '.'

    );

}



/*

|--------------------------------------------------------------------------

| VARIABLES

|--------------------------------------------------------------------------

*/



$mensaje = '';

$tipoMensaje = '';



/*

|--------------------------------------------------------------------------

| REGISTRAR VENTA

|--------------------------------------------------------------------------

*/



if ($_SERVER['REQUEST_METHOD'] === 'POST') {



    $idUsuario = (int)($_POST['id_usuario'] ?? 0);

    $metodoPago = trim($_POST['metodo_pago'] ?? '');

    $carritoJSON = $_POST['carrito'] ?? '';



    /*

    |--------------------------------------------------------------------------

    | VALIDAR CLIENTE

    |--------------------------------------------------------------------------

    */



    if ($idUsuario <= 0) {



        $mensaje = 'Debe seleccionar un cliente.';

        $tipoMensaje = 'error';



    /*

    |--------------------------------------------------------------------------

    | VALIDAR MÉTODO DE PAGO

    |--------------------------------------------------------------------------

    */



    } elseif (

        !in_array(

            $metodoPago,

            ['Efectivo', 'Transferencia', 'Tarjeta'],

            true

        )

    ) {



        $mensaje = 'Seleccione un método de pago válido.';

        $tipoMensaje = 'error';



    /*

    |--------------------------------------------------------------------------

    | DECODIFICAR CARRITO

    |--------------------------------------------------------------------------

    */



    } else {



        $carrito = json_decode(

            $carritoJSON,

            true

        );



        if (

            !is_array($carrito) ||

            count($carrito) === 0

        ) {



            $mensaje = 'La venta no contiene productos.';

            $tipoMensaje = 'error';



        } else {



            /*

            |--------------------------------------------------------------------------

            | TRANSACCIÓN

            |--------------------------------------------------------------------------

            */



            mysqli_begin_transaction($conexion);



            try {



                /*

                |--------------------------------------------------------------------------

                | 1. VALIDAR QUE EL CLIENTE EXISTA

                |--------------------------------------------------------------------------

                */



                $sqlCliente = "

                    SELECT id

                    FROM usuarios

                    WHERE id = ?

                      AND LOWER(TRIM(rol)) = 'cliente'

                    LIMIT 1

                ";



                $stmtCliente = mysqli_prepare(

                    $conexion,

                    $sqlCliente

                );



                if (!$stmtCliente) {

                    throw new Exception(

                        'No se pudo preparar la validación del cliente.'

                    );

                }



                mysqli_stmt_bind_param(

                    $stmtCliente,

                    'i',

                    $idUsuario

                );



                if (!mysqli_stmt_execute($stmtCliente)) {

                    throw new Exception(

                        'No se pudo validar el cliente.'

                    );

                }



                $resultadoCliente =

                    mysqli_stmt_get_result($stmtCliente);



                if (

                    !$resultadoCliente ||

                    mysqli_num_rows($resultadoCliente) !== 1

                ) {



                    mysqli_stmt_close($stmtCliente);



                    throw new Exception(

                        'El cliente seleccionado no existe o no es válido.'

                    );

                }



                mysqli_stmt_close($stmtCliente);



                /*

                |--------------------------------------------------------------------------

                | 2. PREPARAR CONSULTA DE PRODUCTOS

                |--------------------------------------------------------------------------

                |

                | FOR UPDATE bloquea las filas durante la transacción.

                | Esto evita que dos ventas simultáneas consuman el mismo stock.

                |

                */



                $sqlProducto = "

                    SELECT

                        idProducto,

                        nombre,

                        precio,

                        stock,

                        estado

                    FROM producto

                    WHERE idProducto = ?

                    FOR UPDATE

                ";



                $stmtProducto = mysqli_prepare(

                    $conexion,

                    $sqlProducto

                );



                if (!$stmtProducto) {

                    throw new Exception(

                        'No se pudo preparar la consulta de productos.'

                    );

                }



                /*

                |--------------------------------------------------------------------------

                | 3. PREPARAR INSERCIÓN DE DETALLE

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

                    mysqli_stmt_close($stmtProducto);



                    throw new Exception(

                        'No se pudo preparar el detalle de la venta.'

                    );

                }



                /*

                |--------------------------------------------------------------------------

                | VARIABLES PARA EL TOTAL

                |--------------------------------------------------------------------------

                */



                $subtotalVenta = 0;



                $productosValidados = [];



                /*

                |--------------------------------------------------------------------------

                | 4. VALIDAR TODOS LOS PRODUCTOS

                |--------------------------------------------------------------------------

                */



                foreach ($carrito as $item) {



                    $idProducto = (int)(

                        $item['idProducto'] ?? 0

                    );



                    $cantidad = (int)(

                        $item['cantidad'] ?? 0

                    );



                    if (

                        $idProducto <= 0 ||

                        $cantidad <= 0

                    ) {



                        throw new Exception(

                            'Uno de los productos tiene datos inválidos.'

                        );

                    }



                    /*

                    |--------------------------------------------------------------------------

                    | BUSCAR PRODUCTO REAL EN LA BD

                    |--------------------------------------------------------------------------

                    */



                    mysqli_stmt_bind_param(

                        $stmtProducto,

                        'i',

                        $idProducto

                    );



                    if (

                        !mysqli_stmt_execute(

                            $stmtProducto

                        )

                    ) {



                        throw new Exception(

                            'No se pudo consultar uno de los productos.'

                        );

                    }



                    $resultadoProducto =

                        mysqli_stmt_get_result(

                            $stmtProducto

                        );



                    $producto =

                        mysqli_fetch_assoc(

                            $resultadoProducto

                        );



                    if (!$producto) {



                        throw new Exception(

                            'El producto con ID ' .

                            $idProducto .

                            ' no existe.'

                        );

                    }



                    /*

                    |--------------------------------------------------------------------------

                    | VERIFICAR ESTADO

                    |--------------------------------------------------------------------------

                    */



                    if (

                        strtolower(

                            trim($producto['estado'])

                        ) !== 'activo'

                    ) {



                        throw new Exception(

                            'El producto "' .

                            $producto['nombre'] .

                            '" no está activo.'

                        );

                    }



                    /*

                    |--------------------------------------------------------------------------

                    | VERIFICAR STOCK

                    |--------------------------------------------------------------------------

                    */



                    $stockDisponible =

                        (int)$producto['stock'];



                    if (

                        $cantidad >

                        $stockDisponible

                    ) {



                        throw new Exception(

                            'Stock insuficiente para "' .

                            $producto['nombre'] .

                            '". Disponible: ' .

                            $stockDisponible .

                            '.'

                        );

                    }



                    /*

                    |--------------------------------------------------------------------------

                    | OBTENER PRECIO REAL DE LA BD

                    |--------------------------------------------------------------------------

                    |

                    | NO utilizamos el precio enviado por JavaScript.

                    |

                    */



                    $precioUnitario =

                        (float)$producto['precio'];



                    $subtotalProducto =

                        $cantidad *

                        $precioUnitario;



                    $subtotalVenta +=

                        $subtotalProducto;



                    $productosValidados[] = [

                        'idProducto' =>

                            $idProducto,



                        'nombre' =>

                            $producto['nombre'],



                        'cantidad' =>

                            $cantidad,



                        'precio' =>

                            $precioUnitario,



                        'subtotal' =>

                            $subtotalProducto,



                        'stock' =>

                            $stockDisponible

                    ];

                }



                mysqli_stmt_close($stmtProducto);



                /*

                |--------------------------------------------------------------------------

                | 5. VALORES DE FACTURA

                |--------------------------------------------------------------------------

                */



                $impuestos = 0;

                $descuento = 0;



                $totalVenta =

                    $subtotalVenta +

                    $impuestos -

                    $descuento;



                /*

                |--------------------------------------------------------------------------

                | 6. GENERAR TOKEN

                |--------------------------------------------------------------------------

                */



                $tokenFactura =

                    bin2hex(

                        random_bytes(32)

                    );



                /*

                |--------------------------------------------------------------------------

                | 7. CREAR FACTURA

                |--------------------------------------------------------------------------

                |

                | Primero se utiliza 0 en numero_factura.

                | Después usamos id_factura como consecutivo,

                | igual que el sistema actual.

                |

                */



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

                        0,

                        ?,

                        ?,

                        ?,

                        ?,

                        ?,

                        ?,

                        'pagada',

                        ?

                    )

                ";



                $stmtFactura = mysqli_prepare(

                    $conexion,

                    $sqlFactura

                );



                if (!$stmtFactura) {



                    mysqli_stmt_close($stmtDetalle);



                    throw new Exception(

                        'No se pudo preparar la factura.'

                    );

                }



                mysqli_stmt_bind_param(

                    $stmtFactura,

                    'iddddss',

                    $idUsuario,

                    $subtotalVenta,

                    $impuestos,

                    $descuento,

                    $totalVenta,

                    $metodoPago,

                    $tokenFactura

                );



                if (

                    !mysqli_stmt_execute(

                        $stmtFactura

                    )

                ) {



                    throw new Exception(

                        'No se pudo registrar la factura. MySQL: ' .

                        mysqli_stmt_error($stmtFactura)

                    );

                }



                /*

                |--------------------------------------------------------------------------

                | 8. OBTENER ID DE FACTURA

                |--------------------------------------------------------------------------

                */



                $idFactura =

                    mysqli_insert_id($conexion);



                if ($idFactura <= 0) {



                    throw new Exception(

                        'No se pudo obtener el ID de la factura.'

                    );

                }



                /*

                |--------------------------------------------------------------------------

                | 9. ASIGNAR NUMERO DE FACTURA

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

                        'No se pudo preparar el número de factura.'

                    );

                }



                mysqli_stmt_bind_param(

                    $stmtNumero,

                    'ii',

                    $idFactura,

                    $idFactura

                );



                if (

                    !mysqli_stmt_execute(

                        $stmtNumero

                    )

                ) {



                    throw new Exception(

                        'No se pudo asignar el número de factura.'

                    );

                }



                /*

                |--------------------------------------------------------------------------

                | 10. INSERTAR DETALLES Y DESCONTAR STOCK

                |--------------------------------------------------------------------------

                */



                // Descuento de stock protegido por la transacción y bloqueo de filas.
                $sqlStock = "
                    UPDATE producto
                    SET stock = stock - ?
                    WHERE idProducto = ?
                      AND stock >= ?
                      AND estado = 'Activo'
                ";

                $stmtStock = mysqli_prepare($conexion, $sqlStock);
                if (!$stmtStock) {
                    throw new Exception('No se pudo preparar la actualización del stock.');
                }

                // Cada salida de una venta también debe quedar en el historial.
                $sqlMovimiento = "
                    INSERT INTO movimiento_inventario
                    (idProducto, tipoMovimiento, cantidad, stockAnterior, stockNuevo,
                     motivo, observacion, idUsuario, moduloOrigen)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $stmtMovimiento = mysqli_prepare($conexion, $sqlMovimiento);
                if (!$stmtMovimiento) {
                    throw new Exception('No se pudo preparar el registro del historial de inventario.');
                }

                // Mantiene el stock secuencial correcto si el carrito repite un producto.
                $stockActualPorProducto = [];
                $tipoMovimiento = 'SALIDA';
                $motivoMovimiento = 'Venta';
                $moduloOrigen = 'Cajero';

                foreach ($productosValidados as $producto) {
                    $idProducto = (int)$producto['idProducto'];
                    $cantidad = (int)$producto['cantidad'];
                    $precioUnitario = (float)$producto['precio'];
                    $subtotalProducto = (float)$producto['subtotal'];

                    // Registrar detalle de factura.
                    mysqli_stmt_bind_param(
                        $stmtDetalle,
                        'iiidd',
                        $idFactura,
                        $idProducto,
                        $cantidad,
                        $precioUnitario,
                        $subtotalProducto
                    );
                    if (!mysqli_stmt_execute($stmtDetalle)) {
                        throw new Exception(
                            'No se pudo registrar el producto "' . $producto['nombre'] .
                            '". MySQL: ' . mysqli_stmt_error($stmtDetalle)
                        );
                    }

                    // Determinar el stock anterior de esta línea de venta.
                    if (!array_key_exists($idProducto, $stockActualPorProducto)) {
                        $stockActualPorProducto[$idProducto] = (int)$producto['stock'];
                    }
                    $stockAnteriorMovimiento = $stockActualPorProducto[$idProducto];

                    // Descontar solo si hay unidades suficientes en la base de datos.
                    mysqli_stmt_bind_param(
                        $stmtStock,
                        'iii',
                        $cantidad,
                        $idProducto,
                        $cantidad
                    );
                    if (!mysqli_stmt_execute($stmtStock)) {
                        throw new Exception(
                            'No se pudo actualizar el stock de "' . $producto['nombre'] . '".'
                        );
                    }
                    if (mysqli_stmt_affected_rows($stmtStock) !== 1) {
                        throw new Exception(
                            'Stock insuficiente o producto inactivo: "' . $producto['nombre'] .
                            '". La venta fue cancelada.'
                        );
                    }

                    $stockNuevoMovimiento = $stockAnteriorMovimiento - $cantidad;
                    if ($stockNuevoMovimiento < 0) {
                        throw new Exception(
                            'El stock calculado sería negativo para "' . $producto['nombre'] . '".'
                        );
                    }

                    $observacionMovimiento = 'Factura #' . $idFactura;

                    // Registrar la salida vinculada al cajero autenticado, no al cliente.
                    mysqli_stmt_bind_param(
                        $stmtMovimiento,
                        'isiiissis',
                        $idProducto,
                        $tipoMovimiento,
                        $cantidad,
                        $stockAnteriorMovimiento,
                        $stockNuevoMovimiento,
                        $motivoMovimiento,
                        $observacionMovimiento,
                        $idCajero,
                        $moduloOrigen
                    );
                    if (!mysqli_stmt_execute($stmtMovimiento)) {
                        throw new Exception(
                            'No se pudo registrar el movimiento de inventario para "' .
                            $producto['nombre'] . '". MySQL: ' . mysqli_stmt_error($stmtMovimiento)
                        );
                    }

                    $stockActualPorProducto[$idProducto] = $stockNuevoMovimiento;
                }

                /*

                |--------------------------------------------------------------------------

                | 11. CONFIRMAR TRANSACCIÓN

                |--------------------------------------------------------------------------

                */



                mysqli_commit($conexion);



                /*

                |--------------------------------------------------------------------------

                | CERRAR STATEMENTS

                |--------------------------------------------------------------------------

                */



                mysqli_stmt_close($stmtFactura);

                mysqli_stmt_close($stmtNumero);

                mysqli_stmt_close($stmtDetalle);

                mysqli_stmt_close($stmtStock);
                mysqli_stmt_close($stmtMovimiento);



                /*

                |--------------------------------------------------------------------------

                | 12. REDIRECCIONAR A FACTURACIÓN

                |--------------------------------------------------------------------------

                */



                header(

                    "Location: facturacion.php?id_factura=" .

                    $idFactura .

                    "&venta=ok"

                );



                exit();



            } catch (Throwable $e) {



                /*

                |--------------------------------------------------------------------------

                | CANCELAR TODO

                |--------------------------------------------------------------------------

                */



                mysqli_rollback($conexion);



                /*

                |--------------------------------------------------------------------------

                | CERRAR STATEMENTS SI EXISTEN

                |--------------------------------------------------------------------------

                */



                if (isset($stmtFactura) &&

                    $stmtFactura) {

                    mysqli_stmt_close(

                        $stmtFactura

                    );

                }



                if (isset($stmtNumero) &&

                    $stmtNumero) {

                    mysqli_stmt_close(

                        $stmtNumero

                    );

                }



                if (isset($stmtDetalle) &&

                    $stmtDetalle) {

                    mysqli_stmt_close(

                        $stmtDetalle

                    );

                }



                if (isset($stmtStock) &&

                    $stmtStock) {

                    mysqli_stmt_close(

                        $stmtStock

                    );

                }

                if (isset($stmtMovimiento) && $stmtMovimiento) {
                    mysqli_stmt_close($stmtMovimiento);
                }



                $mensaje =

                    $e->getMessage();



                $tipoMensaje =

                    'error';

            }

        }

    }

}



/*

|--------------------------------------------------------------------------

| CONSULTAR CLIENTES

|--------------------------------------------------------------------------

*/



$clientes = [];



$sqlClientes = "

    SELECT

        id,

        nombre,

        apellido,

        identificacion,

        correo

    FROM usuarios

    WHERE LOWER(TRIM(rol)) = 'cliente'

    ORDER BY nombre ASC, apellido ASC

";



$resultadoClientes =

    mysqli_query(

        $conexion,

        $sqlClientes

    );



if ($resultadoClientes) {



    while (

        $cliente =

        mysqli_fetch_assoc(

            $resultadoClientes

        )

    ) {



        $clientes[] = $cliente;

    }

}



/*

|--------------------------------------------------------------------------

| CONSULTAR PRODUCTOS

|--------------------------------------------------------------------------

*/



$productos = [];



$sqlProductos = "

    SELECT

        idProducto,

        nombre,

        descripcion,

        categoria,

        precio,

        stock,

        imagen

    FROM producto

    WHERE estado = 'Activo'

      AND stock > 0

    ORDER BY nombre ASC

";



$resultadoProductos =

    mysqli_query(

        $conexion,

        $sqlProductos

    );



if ($resultadoProductos) {



    while (

        $producto =

        mysqli_fetch_assoc(

            $resultadoProductos

        )

    ) {



        $productos[] = $producto;

    }

}



?>

<!DOCTYPE html>

<html lang="es">



<head>



<meta charset="UTF-8">



<meta

    name="viewport"

    content="width=device-width, initial-scale=1.0">



<title>Nueva Venta - Sabora</title>



<style>



*{

    margin:0;

    padding:0;

    box-sizing:border-box;

    font-family:'Segoe UI',sans-serif;

}



body{

    background:#efe7e2;

    color:#333;

}



/*========================

        HEADER

\========================*/



header{

    background:#ff6b35;

    color:white;

    padding:18px 5%;

    display:flex;

    justify-content:space-between;

    align-items:center;

    box-shadow:0 3px 10px rgba(0,0,0,.15);

}



header h1{

    font-size:30px;

}



.usuario{

    text-align:right;

    font-size:14px;

    line-height:21px;

}



/*========================

       CONTENEDOR

\========================*/



.container{

    width:92%;

    max-width:1250px;

    margin:30px auto;

}



/*========================

        TITULO

\========================*/



.titulo{

    background:white;

    border-radius:14px;

    padding:25px;

    margin-bottom:20px;

    box-shadow:0 5px 15px rgba(0,0,0,.10);

}



.titulo h2{

    color:#ff6b35;

    margin-bottom:7px;

}



.titulo p{

    color:#666;

}



/*========================

        MENSAJES

\========================*/



.mensaje{

    padding:14px 18px;

    border-radius:9px;

    margin-bottom:20px;

    font-weight:600;

}



.mensaje.error{

    background:#fdecec;

    color:#9b2020;

    border:1px solid #efb2b2;

}



/*========================

       ESTRUCTURA

\========================*/



.venta-grid{

    display:grid;

    grid-template-columns:1fr 1.3fr;

    gap:22px;

}



/*========================

        PANELES

\========================*/



.panel{

    background:white;

    border-radius:14px;

    padding:24px;

    box-shadow:0 5px 15px rgba(0,0,0,.10);

    margin-bottom:22px;

}



.panel h3{

    color:#ff6b35;

    margin-bottom:18px;

}



/*========================

         CAMPOS

\========================*/



label{

    display:block;

    font-weight:700;

    margin-bottom:7px;

}



select,

input{

    width:100%;

    padding:12px;

    border:1px solid #ccc;

    border-radius:8px;

    outline:none;

    font-size:14px;

}



select:focus,

input:focus{

    border-color:#ff6b35;

}



/*========================

      BUSCADOR

\========================*/



.busqueda{

    margin-bottom:18px;

}



.busqueda input{

    border:2px solid #eee;

}



.busqueda input:focus{

    border-color:#ff6b35;

}



/*========================

       PRODUCTOS

\========================*/



.productos{

    display:grid;

    grid-template-columns:repeat(2,1fr);

    gap:12px;

    max-height:520px;

    overflow-y:auto;

    padding-right:5px;

}



.producto{

    border:1px solid #eee;

    border-radius:10px;

    padding:14px;

    background:#fff;

    transition:.2s;

}



.producto:hover{

    transform:translateY(-2px);

    box-shadow:0 4px 12px rgba(0,0,0,.10);

}



.producto h4{

    margin-bottom:6px;

    color:#333;

}



.producto .categoria{

    color:#777;

    font-size:12px;

    margin-bottom:7px;

}



.producto .precio{

    color:#ff6b35;

    font-weight:bold;

    font-size:17px;

    margin-bottom:5px;

}



.stock{

    font-size:12px;

    color:#555;

    margin-bottom:10px;

}



.btn-agregar{

    width:100%;

    border:0;

    background:#ff6b35;

    color:white;

    padding:9px;

    border-radius:7px;

    cursor:pointer;

    font-weight:700;

}



.btn-agregar:hover{

    background:#e95d2d;

}



.btn-agregar:disabled{

    background:#aaa;

    cursor:not-allowed;

}



/*========================

         CARRITO

\========================*/



.carrito{

    min-height:150px;

}



.carrito-vacio{

    text-align:center;

    padding:35px 10px;

    color:#888;

}



.carrito-item{

    display:grid;

    grid-template-columns:1.5fr auto auto auto;

    gap:12px;

    align-items:center;

    border-bottom:1px solid #eee;

    padding:14px 0;

}



.carrito-item:last-child{

    border-bottom:0;

}



.carrito-nombre{

    font-weight:700;

}



.carrito-precio{

    color:#666;

    font-size:13px;

}



.cantidad-control{

    display:flex;

    align-items:center;

    gap:7px;

}



.btn-cantidad{

    width:30px;

    height:30px;

    border:0;

    border-radius:6px;

    background:#ff6b35;

    color:white;

    cursor:pointer;

    font-weight:bold;

}



.cantidad{

    min-width:25px;

    text-align:center;

    font-weight:bold;

}



.btn-eliminar{

    border:0;

    background:#b02a37;

    color:white;

    padding:7px 9px;

    border-radius:6px;

    cursor:pointer;

}



.item-subtotal{

    font-weight:bold;

    min-width:90px;

    text-align:right;

}



/*========================

         TOTALES

\========================*/



.totales{

    border-top:2px solid #ff6b35;

    margin-top:18px;

    padding-top:15px;

}



.linea-total{

    display:flex;

    justify-content:space-between;

    padding:7px 0;

}



.total-final{

    font-size:22px;

    font-weight:bold;

    color:#ff6b35;

    border-top:1px solid #ddd;

    margin-top:8px;

    padding-top:12px;

}



/*========================

       BOTONES

\========================*/



.botones{

    display:flex;

    justify-content:space-between;

    gap:12px;

    margin-top:20px;

}



.btn{

    border:0;

    border-radius:8px;

    padding:12px 22px;

    cursor:pointer;

    text-decoration:none;

    font-weight:700;

    text-align:center;

}



.btn-cancelar{

    background:#777;

    color:white;

}



.btn-venta{

    background:#ff6b35;

    color:white;

    flex:1;

}



.btn-venta:hover{

    background:#e95d2d;

}



.btn-venta:disabled{

    background:#aaa;

    cursor:not-allowed;

}



/*========================

       INFORMACIÓN

\========================*/



.info{

    background:#fff8f4;

    border:1px solid #ffd7c6;

    border-radius:9px;

    padding:14px;

    margin-top:18px;

    font-size:13px;

    color:#666;

}



.info strong{

    color:#333;

}



/*========================

       RESPONSIVE

\========================*/



@media(max-width:900px){



    .venta-grid{

        grid-template-columns:1fr;

    }



}



@media(max-width:600px){



    header{

        flex-direction:column;

        gap:10px;

        text-align:center;

    }



    .usuario{

        text-align:center;

    }



    .productos{

        grid-template-columns:1fr;

    }



    .carrito-item{

        grid-template-columns:1fr;

    }



    .item-subtotal{

        text-align:left;

    }



    .botones{

        flex-direction:column;

    }



}



</style>



</head>



<body>



<header>



<h1>SABORA</h1>



<div class="usuario">



<strong>

<?= e($_SESSION['nombre'] ?? '') ?>

<?= e($_SESSION['apellido'] ?? '') ?>

</strong>



<br>



<?= e($_SESSION['correo'] ?? '') ?>



<br>



Rol:

<strong>Cajero</strong>



</div>



</header>



<div class="container">



<div class="titulo">



<h2>🛒 Nueva Venta</h2>



<p>

Registre una venta directamente desde el módulo de caja.

</p>



</div>



<?php if ($mensaje !== ''): ?>



<div class="mensaje <?= e($tipoMensaje) ?>">



<?= e($mensaje) ?>



</div>



<?php endif; ?>





<form

    method="POST"

    id="formVenta"

    onsubmit="return prepararVenta();"

\>



<div class="venta-grid">



<!-- =========================================

     COLUMNA IZQUIERDA

\========================================= -->



<div>



<div class="panel">



<h3>👤 Cliente</h3>



<label for="id_usuario">

Seleccionar cliente

</label>



<select

    name="id_usuario"

    id="id_usuario"

    required

\>



<option value="">

\-- Seleccione un cliente --

</option>



<?php foreach ($clientes as $cliente): ?>



<option

    value="<?= (int)$cliente['id'] ?>"

\>



<?= e(

    $cliente['nombre'] .

    ' ' .

    $cliente['apellido']

) ?>



<?php if (

    !empty($cliente['identificacion'])

): ?>



 - <?= e($cliente['identificacion']) ?>



<?php endif; ?>



</option>



<?php endforeach; ?>



</select>



</div>





<div class="panel">



<h3>🍔 Productos</h3>



<div class="busqueda">



<input

    type="text"

    id="buscarProducto"

    placeholder="Buscar producto..."

    autocomplete="off"

\>



</div>





<div

    class="productos"

    id="listaProductos"

\>



<?php if (!$productos): ?>



<p>

No hay productos activos disponibles.

</p>



<?php else: ?>



<?php foreach ($productos as $producto): ?>



<div

    class="producto"

    data-nombre="<?= e(

        strtolower($producto['nombre'])

    ) ?>"

    data-categoria="<?= e(

        strtolower($producto['categoria'])

    ) ?>"

\>



<h4>

<?= e($producto['nombre']) ?>

</h4>



<div class="categoria">

<?= e($producto['categoria']) ?>

</div>



<div class="precio">

<?= dinero($producto['precio']) ?>

</div>



<div class="stock">



Stock:

<strong>

<?= (int)$producto['stock'] ?>

</strong>



</div>



<button

    type="button"

    class="btn-agregar"

    onclick="agregarProducto(

        <?= (int)$producto['idProducto'] ?>,

        <?= htmlspecialchars(

            json_encode(

                $producto['nombre'],

                JSON_UNESCAPED_UNICODE

            ),

            ENT_QUOTES,

            'UTF-8'

        ) ?>,

        <?= (float)$producto['precio'] ?>,

        <?= (int)$producto['stock'] ?>

    )"

\>



Agregar



</button>



</div>



<?php endforeach; ?>



<?php endif; ?>



</div>



</div>



</div>





<!-- =========================================

     COLUMNA DERECHA

\========================================= -->



<div>



<div class="panel">



<h3>🧾 Productos de la venta</h3>



<div

    class="carrito"

    id="carrito"

\>



<div class="carrito-vacio">

No hay productos agregados.

</div>



</div>





<div class="totales">



<div class="linea-total">



<span>Subtotal:</span>



<strong id="subtotal">

$0

</strong>



</div>



<div class="linea-total">



<span>Impuestos:</span>



<strong>

$0

</strong>



</div>



<div class="linea-total">



<span>Descuento:</span>



<strong>

$0

</strong>



</div>



<div class="linea-total total-final">



<span>Total:</span>



<strong id="total">

$0

</strong>



</div>



</div>



</div>





<div class="panel">



<h3>💳 Método de pago</h3>



<label for="metodo_pago">

Método de pago

</label>



<select

    name="metodo_pago"

    id="metodo_pago"

    required

\>



<option value="">

\-- Seleccione --

</option>



<option value="Efectivo">

Efectivo

</option>



<option value="Transferencia">

Transferencia

</option>



<option value="Tarjeta">

Tarjeta

</option>



</select>



<div class="info">



<strong>Importante:</strong>



El precio y el stock serán verificados

nuevamente en el servidor antes de registrar

la venta.



</div>



</div>





<input

    type="hidden"

    name="carrito"

    id="carritoInput"

\>





<div class="botones">



<a

    href="Cajero.php"

    class="btn btn-cancelar"

\>

Cancelar

</a>



<button

    type="submit"

    class="btn btn-venta"

    id="btnRegistrar"

    disabled

\>

Registrar Venta

</button>



</div>



</div>



</div>



</form>



</div>





<script>



/*

|--------------------------------------------------------------------------

| CARRITO

|--------------------------------------------------------------------------

*/



let carrito = [];





/*

|--------------------------------------------------------------------------

| FORMATEAR DINERO

|--------------------------------------------------------------------------

*/



function dinero(valor)

{

    return '$' +

        Number(valor).toLocaleString(

            'es-CO',

            {

                maximumFractionDigits:0

            }

        );

}





/*

|--------------------------------------------------------------------------

| AGREGAR PRODUCTO

|--------------------------------------------------------------------------

*/



function agregarProducto(

    idProducto,

    nombre,

    precio,

    stock

){



    const existente =

        carrito.find(

            item =>

                Number(item.idProducto) ===

                Number(idProducto)

        );



    if (existente) {



        if (

            existente.cantidad >=

            existente.stock

        ) {



            alert(

                'No puede agregar más unidades de "' +

                nombre +

                '". Stock disponible: ' +

                existente.stock

            );



            return;

        }



        existente.cantidad++;



    } else {



        carrito.push({



            idProducto:

                Number(idProducto),



            nombre:

                nombre,



            precio:

                Number(precio),



            stock:

                Number(stock),



            cantidad:

                1



        });



    }



    renderCarrito();

}





/*

|--------------------------------------------------------------------------

| CAMBIAR CANTIDAD

|--------------------------------------------------------------------------

*/



function cambiarCantidad(

    idProducto,

    cambio

){



    const producto =

        carrito.find(

            item =>

                Number(item.idProducto) ===

                Number(idProducto)

        );



    if (!producto) {

        return;

    }



    const nuevaCantidad =

        producto.cantidad +

        cambio;



    if (nuevaCantidad <= 0) {



        eliminarProducto(idProducto);



        return;

    }



    if (

        nuevaCantidad >

        producto.stock

    ) {



        alert(

            'No puede superar el stock disponible.'

        );



        return;

    }



    producto.cantidad =

        nuevaCantidad;



    renderCarrito();

}





/*

|--------------------------------------------------------------------------

| ELIMINAR PRODUCTO

|--------------------------------------------------------------------------

*/



function eliminarProducto(idProducto)

{



    carrito =

        carrito.filter(

            item =>

                Number(item.idProducto) !==

                Number(idProducto)

        );



    renderCarrito();

}





/*

|--------------------------------------------------------------------------

| MOSTRAR CARRITO

|--------------------------------------------------------------------------

*/



function renderCarrito()

{



    const contenedor =

        document.getElementById(

            'carrito'

        );



    const subtotalElemento =

        document.getElementById(

            'subtotal'

        );



    const totalElemento =

        document.getElementById(

            'total'

        );



    const botonRegistrar =

        document.getElementById(

            'btnRegistrar'

        );



    if (carrito.length === 0) {



        contenedor.innerHTML = \`

            <div class="carrito-vacio">

                No hay productos agregados.

            </div>

        \`;



        subtotalElemento.textContent =

            '$0';



        totalElemento.textContent =

            '$0';



        botonRegistrar.disabled =

            true;



        return;

    }





    let subtotal = 0;



    let html = '';





    carrito.forEach(

        producto => {



            const subtotalProducto =

                producto.precio *

                producto.cantidad;



            subtotal +=

                subtotalProducto;



            html += \`



                <div class="carrito-item">



                    <div>



                        <div class="carrito-nombre">

                            ${escapeHTML(

                                producto.nombre

                            )}

                        </div>



                        <div class="carrito-precio">

                            ${dinero(

                                producto.precio

                            )} unidad

                        </div>



                    </div>





                    <div class="cantidad-control">



                        <button

                            type="button"

                            class="btn-cantidad"

                            onclick="cambiarCantidad(

                                ${producto.idProducto},

                                -1

                            )"

                        >

                            −

                        </button>



                        <span class="cantidad">

                            ${producto.cantidad}

                        </span>



                        <button

                            type="button"

                            class="btn-cantidad"

                            onclick="cambiarCantidad(

                                ${producto.idProducto},

                                1

                            )"

                        >

                            +

                        </button>



                    </div>





                    <div class="item-subtotal">



                        ${dinero(

                            subtotalProducto

                        )}



                    </div>





                    <button

                        type="button"

                        class="btn-eliminar"

                        onclick="eliminarProducto(

                            ${producto.idProducto}

                        )"

                    >

                        Eliminar

                    </button>



                </div>



            \`;

        }

    );





    contenedor.innerHTML =

        html;



    subtotalElemento.textContent =

        dinero(subtotal);



    totalElemento.textContent =

        dinero(subtotal);



    botonRegistrar.disabled =

        false;

}





/*

|--------------------------------------------------------------------------

| PREPARAR FORMULARIO

|--------------------------------------------------------------------------

*/



function prepararVenta()

{



    const cliente =

        document.getElementById(

            'id_usuario'

        ).value;



    const metodo =

        document.getElementById(

            'metodo_pago'

        ).value;



    if (!cliente) {



        alert(

            'Debe seleccionar un cliente.'

        );



        return false;

    }



    if (!metodo) {



        alert(

            'Debe seleccionar un método de pago.'

        );



        return false;

    }



    if (

        !Array.isArray(carrito) ||

        carrito.length === 0

    ) {



        alert(

            'Debe agregar al menos un producto.'

        );



        return false;

    }





    /*

    |--------------------------------------------------------------------------

    | SOLO ENVIAMOS ID Y CANTIDAD

    |--------------------------------------------------------------------------

    |

    | No necesitamos confiar en nombre, precio o stock

    | enviados desde JavaScript.

    |

    */



    const carritoSeguro =

        carrito.map(

            producto => ({



                idProducto:

                    Number(

                        producto.idProducto

                    ),



                cantidad:

                    Number(

                        producto.cantidad

                    )



            })

        );





    document.getElementById(

        'carritoInput'

    ).value =

        JSON.stringify(

            carritoSeguro

        );





    const boton =

        document.getElementById(

            'btnRegistrar'

        );



    boton.disabled =

        true;



    boton.textContent =

        'Registrando...';





    return true;

}





/*

|--------------------------------------------------------------------------

| PROTEGER HTML

|--------------------------------------------------------------------------

*/



function escapeHTML(texto)

{



    const div =

        document.createElement(

            'div'

        );



    div.textContent =

        texto;



    return div.innerHTML;

}





/*

|--------------------------------------------------------------------------

| BUSCADOR DE PRODUCTOS

|--------------------------------------------------------------------------

*/



document

    .getElementById(

        'buscarProducto'

    )

    .addEventListener(

        'input',

        function()

        {



            const texto =

                this.value

                    .toLowerCase()

                    .trim();



            document

                .querySelectorAll(

                    '.producto'

                )

                .forEach(

                    producto => {



                        const nombre =

                            producto.dataset.nombre ||

                            '';



                        const categoria =

                            producto.dataset.categoria ||

                            '';



                        const coincide =

                            nombre.includes(

                                texto

                            ) ||

                            categoria.includes(

                                texto

                            );



                        producto.style.display =

                            coincide

                                ? ''

                                : 'none';



                    }

                );



        }

    );



</script>



</body>



</html>