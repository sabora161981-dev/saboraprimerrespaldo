<?php
session_start();

require_once "conexion.php";

/*
|--------------------------------------------------------------------------
| INICIALIZAR PEDIDO
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['pedido'])) {
    $_SESSION['pedido'] = [];
}


/*
|--------------------------------------------------------------------------
| PROCESAR PEDIDO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';

    $idProducto = isset($_POST['id_producto'])
        ? (int)$_POST['id_producto']
        : 0;


    /*
    |--------------------------------------------------------------------------
    | AGREGAR PRODUCTO
    |--------------------------------------------------------------------------
    */

    if ($accion === 'agregar' && $idProducto > 0) {

        $sqlProducto = "
            SELECT
                idProducto,
                nombre,
                descripcion,
                precio,
                imagen,
                categoria,
                stock
            FROM producto
            WHERE idProducto = ?
        ";

        $stmtProducto = mysqli_prepare(
            $conexion,
            $sqlProducto
        );

        mysqli_stmt_bind_param(
            $stmtProducto,
            "i",
            $idProducto
        );

        mysqli_stmt_execute($stmtProducto);

        $resultadoProducto =
            mysqli_stmt_get_result($stmtProducto);


        if ($filaProducto = mysqli_fetch_assoc($resultadoProducto)) {

            $productoEncontrado = false;


            foreach ($_SESSION['pedido'] as $indice => $producto) {

                if (
                    (int)$producto['idProducto']
                    === $idProducto
                ) {

                    /*
                    | No permitir superar el stock disponible
                    */

                    $cantidadActual =
                        (int)$producto['cantidad'];

                    $stockDisponible =
                        (int)$filaProducto['stock'];


                    if ($cantidadActual < $stockDisponible) {

                        $_SESSION['pedido'][$indice]['cantidad']++;

                    }

                    $productoEncontrado = true;

                    break;
                }
            }


            /*
            |--------------------------------------------------------------------------
            | PRODUCTO NUEVO
            |--------------------------------------------------------------------------
            */

            if (!$productoEncontrado) {

                /*
                | Solo agregar si existe disponibilidad
                */

                if ((int)$filaProducto['stock'] > 0) {

                    $_SESSION['pedido'][] = [

                        'idProducto' =>
                            $filaProducto['idProducto'],

                        'nombre' =>
                            $filaProducto['nombre'],

                        'descripcion' =>
                            $filaProducto['descripcion'],

                        'precio' =>
                            $filaProducto['precio'],

                        'imagen' =>
                            $filaProducto['imagen'],

                        'categoria' =>
                            $filaProducto['categoria'],

                        'cantidad' => 1
                    ];
                }
            }
        }

        mysqli_stmt_close($stmtProducto);
    }


    /*
    |--------------------------------------------------------------------------
    | AUMENTAR / DISMINUIR CANTIDAD
    |--------------------------------------------------------------------------
    */

    if (
        $accion === 'cambiar_cantidad'
        && $idProducto > 0
    ) {

        $delta = isset($_POST['delta'])
            ? (int)$_POST['delta']
            : 0;


        foreach ($_SESSION['pedido'] as $indice => $producto) {

            if (
                (int)$producto['idProducto']
                === $idProducto
            ) {

                $nuevaCantidad =
                    (int)$producto['cantidad'] + $delta;


                /*
                | Si llega a 0 o menos,
                | eliminamos el producto.
                */

                if ($nuevaCantidad <= 0) {

                    unset(
                        $_SESSION['pedido'][$indice]
                    );

                    $_SESSION['pedido'] =
                        array_values(
                            $_SESSION['pedido']
                        );

                } else {

                    /*
                    | Consultar stock antes de aumentar
                    */

                    if ($delta > 0) {

                        $sqlStock = "
                            SELECT stock
                            FROM producto
                            WHERE idProducto = ?
                        ";

                        $stmtStock =
                            mysqli_prepare(
                                $conexion,
                                $sqlStock
                            );

                        mysqli_stmt_bind_param(
                            $stmtStock,
                            "i",
                            $idProducto
                        );

                        mysqli_stmt_execute(
                            $stmtStock
                        );

                        $resultadoStock =
                            mysqli_stmt_get_result(
                                $stmtStock
                            );

                        $filaStock =
                            mysqli_fetch_assoc(
                                $resultadoStock
                            );

                        $stockDisponible =
                            $filaStock
                                ? (int)$filaStock['stock']
                                : 0;

                        mysqli_stmt_close(
                            $stmtStock
                        );


                        /*
                        | No superar stock
                        */

                        if (
                            $nuevaCantidad
                            <= $stockDisponible
                        ) {

                            $_SESSION['pedido'][$indice]
                                ['cantidad'] =
                                $nuevaCantidad;
                        }

                    } else {

                        /*
                        | Disminuir cantidad
                        */

                        $_SESSION['pedido'][$indice]
                            ['cantidad'] =
                            $nuevaCantidad;
                    }
                }

                break;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | RESPUESTA AJAX
    |--------------------------------------------------------------------------
    */

    if (
        isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower(
            $_SERVER['HTTP_X_REQUESTED_WITH']
        ) === 'xmlhttprequest'
    ) {

        $cantidadProductosRespuesta = 0;

        $totalPedidoRespuesta = 0;

        $itemsPedidoRespuesta = [];


        foreach (
            $_SESSION['pedido']
            as $productoPedido
        ) {

            $cantidadProducto =
                (int)$productoPedido['cantidad'];

            $precioProducto =
                (float)$productoPedido['precio'];


            $cantidadProductosRespuesta +=
                $cantidadProducto;


            $totalPedidoRespuesta +=
                $precioProducto *
                $cantidadProducto;


            $itemsPedidoRespuesta[] = [

                'idProducto' =>
                    (int)$productoPedido['idProducto'],

                'nombre' =>
                    $productoPedido['nombre'],

                'precio' =>
                    $precioProducto,

                'cantidad' =>
                    $cantidadProducto,

                'imagen' =>
                    $productoPedido['imagen']
            ];
        }


        header(
            'Content-Type: application/json; charset=utf-8'
        );


        echo json_encode([

            'ok' => true,

            'cantidadProductos' =>
                $cantidadProductosRespuesta,

            'totalPedido' =>
                $totalPedidoRespuesta,

            'items' =>
                $itemsPedidoRespuesta
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | SOLICITUD NORMAL
    |--------------------------------------------------------------------------
    */

    header("Location: menu.php");

    exit;
}


/*
|--------------------------------------------------------------------------
| CANTIDAD TOTAL DE PRODUCTOS
|--------------------------------------------------------------------------
|
| Ejemplo:
|
| Hamburguesa x 3
| Pizza        x 2
|
| contador = 5
|
*/

$cantidadProductos = 0;

foreach ($_SESSION['pedido'] as $producto) {

    $cantidadProductos +=
        (int)$producto['cantidad'];
}


/*
|--------------------------------------------------------------------------
| CONSULTAR PRODUCTOS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        idProducto,
        nombre,
        descripcion,
        precio,
        imagen,
        categoria,
        stock
    FROM producto
    ORDER BY idProducto DESC
";

$resultado = mysqli_query(
    $conexion,
    $sql
);

?>
<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Sabora - Menú</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f5ede6;

            color: #333;
        }


        /*
        |--------------------------------------------------------------------------
        | ENCABEZADO
        |--------------------------------------------------------------------------
        */

        header {

            background: white;

            padding: 20px;

            box-shadow:
                0 2px 8px
                rgba(0,0,0,0.10);

            position: sticky;

            top: 0;

            z-index: 100;
        }


        .header-content {

            max-width: 1200px;

            margin: auto;

            display: flex;

            align-items: center;

            justify-content: space-between;
        }


        .logo {

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .logo-icon {

            width: 50px;

            height: 50px;

            border-radius: 50%;

            background: #ff6b35;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 25px;
        }


        .logo h1 {

            margin: 0;

            color: #ff6b35;

            font-size: 28px;
        }


        .logo p {

            margin: 3px 0 0;

            color: #777;

            font-size: 14px;
        }


        /*
        |--------------------------------------------------------------------------
        | CARRITO
        |--------------------------------------------------------------------------
        */

        .pedido-resumen {

            display: flex;

            align-items: center;

            gap: 10px;
        }


        .carrito {

            position: relative;

            width: 48px;

            height: 48px;

            border-radius: 50%;

            background: #ff6b35;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            text-decoration: none;

            font-size: 23px;
        }


        #contadorPedido {

            position: absolute;

            top: -5px;

            right: -5px;

            width: 24px;

            height: 24px;

            border-radius: 50%;

            background: #333;

            color: white;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 12px;

            font-weight: bold;
        }


        /*
        |--------------------------------------------------------------------------
        | CATEGORÍAS
        |--------------------------------------------------------------------------
        */

        .categorias {

            background: white;

            padding: 15px;

            border-bottom:
                1px solid #ddd;
        }


        .categorias-contenedor {

            max-width: 1200px;

            margin: auto;

            display: flex;

            gap: 10px;

            overflow-x: auto;
        }


        .categoria {

            border: none;

            background: #eee;

            padding: 10px 18px;

            border-radius: 10px;

            cursor: pointer;

            white-space: nowrap;
        }


        .categoria:hover {

            background: #ffddd0;
        }

        .categoria.activo {
        background: #ff6b35;S
        color: white;
         font-weight: bold;
    }

        .categoria.activo:hover {
        background: #e85a2a;
    }
        /*
        |--------------------------------------------------------------------------
        | CONTENIDO
        |--------------------------------------------------------------------------
        */

        main {

            max-width: 1200px;

            margin: auto;

            padding: 30px 20px 80px;
        }


        .pedido-actual {

            background: white;

            border-radius: 15px;

            padding: 18px;

            margin-bottom: 25px;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.08);
        }


        .pedido-actual h3 {

            margin-top: 0;

            color: #ff6b35;
        }


        /*
        |--------------------------------------------------------------------------
        | PRODUCTOS
        |--------------------------------------------------------------------------
        */

        .productos {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 25px;
        }


        .producto {

            background: white;

            border-radius: 18px;

            overflow: hidden;

            box-shadow:
                0 5px 15px
                rgba(0,0,0,0.10);
        }


        .producto img {

            width: 100%;
            height: 150px;
            object-fit: contain;
            display: block;
            background: #fff;
            padding: 10px;

            border: 2px solid #f0e0d5;

            border-radius: 12px;
        }


        .sin-imagen {
             width: 100%;
             height: 100%;

            display: flex;
            align-items: center;
            justify-content: center;

            color: #777;
        }


        .producto-info {

            padding: 20px;
        }


        .producto-info h3 {

            margin:
                0 0 10px;

            font-size: 19px;
        }


        .descripcion {

            color: #666;

            font-size: 14px;

            line-height: 1.5;

            min-height: 45px;
        }


        .precio {

            color: #ff6b35;

            font-size: 20px;

            font-weight: bold;

            margin: 10px 0;
        }


        .disponible {

            color: #555;

            font-size: 14px;

            margin-bottom: 15px;
        }


        /*
        |--------------------------------------------------------------------------
        | FORMULARIO AGREGAR
        |--------------------------------------------------------------------------
        */

        .form-pedido {

            margin-top: 15px;
        }


        .btn-agregar {

            width: 100%;

            border: none;

            padding: 12px;

            border-radius: 10px;

            background: #ff6b35;

            color: white;

            font-size: 15px;

            cursor: pointer;
        }


        .btn-agregar:hover {

            background: #e85a2a;
        }


        /*
        |--------------------------------------------------------------------------
        | BOTÓN VOLVER
        |--------------------------------------------------------------------------
        */

        .volver {

            display: block;

            margin:
                30px auto 0;

            border: none;

            padding: 12px 25px;

            border-radius: 10px;

            background: #ff6b35;

            color: white;

            cursor: pointer;
        }


        .volver:hover {

            background: #e85a2a;
        }

        /*
        |--------------------------------------------------------------------------
        | CARRITO FLOTANTE
        |--------------------------------------------------------------------------
        */

        .carrito-flotante {
            position: fixed;
            top: 0;
            right: 0;
            width: min(400px, 92vw);
            height: 100vh;
            background: white;
            box-shadow: -5px 0 20px rgba(0,0,0,0.20);
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transform: translateX(105%);
            transition: transform 0.30s ease;
        }

        .carrito-flotante.abierto {
            transform: translateX(0);
        }

        .carrito-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.35);
            z-index: 999;
            display: none;
        }

        .carrito-overlay.abierto {
            display: block;
        }

        .carrito-flotante-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            background: #ff6b35;
            color: white;
        }

        .carrito-flotante-header h2 {
            margin: 0;
            font-size: 21px;
        }

        .cerrar-carrito {
            border: none;
            background: transparent;
            color: white;
            font-size: 30px;
            line-height: 1;
            cursor: pointer;
        }

        .carrito-contenido {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .carrito-vacio {
            text-align: center;
            color: #777;
            padding: 40px 15px;
        }

        .carrito-item {
            display: flex;
            gap: 12px;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }

        .carrito-item-img {
            width: 65px;
            height: 65px;
            border-radius: 10px;
            border: 1px solid #f0e0d5;
            background: #fff;
            object-fit: contain;
            flex-shrink: 0;
        }

        .carrito-item-info {
            flex: 1;
            min-width: 0;
        }

        .carrito-item-nombre {
            margin: 0 0 5px;
            font-weight: bold;
            color: #333;
        }

        .carrito-item-cantidad {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 6px;
}

.btn-cantidad {
    width: 30px;
    height: 30px;
    border: 1px solid #f0e0d5;
    border-radius: 50%;
    background: white;
    color: #ff6b35;
    font-size: 20px;
    font-weight: bold;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}

.btn-cantidad:hover {
    background: #ff6b35;
    color: white;
}

.btn-cantidad:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}


.cantidad-valor {
    min-width: 25px;
    text-align: center;
    font-size: 15px;
    font-weight: bold;
    color: #333;
}

        .carrito-item-precio {
            margin-top: 4px;
            color: #ff6b35;
            font-weight: bold;
        }

        .carrito-flotante-footer {
            padding: 18px;
            border-top: 1px solid #ddd;
            background: #fff;
        }

        .carrito-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-size: 19px;
            font-weight: bold;
        }

        .carrito-total-valor {
            color: #ff6b35;
        }

        .btn-ver-pedido {
            display: block;
            width: 100%;
            padding: 13px;
            border-radius: 10px;
            background: #ff6b35;
            color: white;
            text-align: center;
            text-decoration: none;
            font-weight: bold;
        }

        .btn-ver-pedido:hover {
            background: #e85a2a;
        }




        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 900px) {

            .productos {

                grid-template-columns:
                    repeat(2, 1fr);
            }
        }


        @media (max-width: 600px) {

            .header-content {

                flex-direction: column;

                gap: 15px;
            }


            .productos {

                grid-template-columns: 1fr;
            }


            main {

                padding:
                    20px 15px 70px;
            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     ENCABEZADO
===================================================== -->

<header>

    <div class="header-content">

        <div class="logo">

            <div class="logo-icon">
                🍴
            </div>

            <div>

                <h1>
                    Sabora
                </h1>

                <p>
                    Menú del Restaurante
                </p>

            </div>

        </div>


        <!-- =================================================
             CARRITO
        ================================================== -->


    </div>

</header>



<!-- =====================================================
     CATEGORÍAS
===================================================== -->

<div class="categorias">

    <div class="categorias-contenedor">

        <button
            type="button"
            class="categoria activo"
            data-categoria="todos">
            Todos
        </button>

        <button
            type="button"
            class="categoria"
            data-categoria="comida">
            Comidas
        </button>

        <button
            type="button"
            class="categoria"
            data-categoria="bebida">
            Bebidas
        </button>

        <button
            type="button"
            class="categoria"
            data-categoria="helado">
            Helados
        </button>

        <button
            type="button"
            class="categoria"
            data-categoria="adicion">
            Adiciones
        </button>

    </div>

</div>


<!-- =====================================================
     CONTENIDO
===================================================== -->

<!-- =====================================================
         CARRITO FLOTANTE
====================================================== -->

    <div
        id="carritoOverlay"
        class="carrito-overlay"
        onclick="cerrarCarrito()">
    </div>

    <aside
        id="carritoFlotante"
        class="carrito-flotante"
        aria-hidden="true">

        <div class="carrito-flotante-header">

            <h2>🛒 Mi pedido</h2>

            <button
                type="button"
                class="cerrar-carrito"
                onclick="cerrarCarrito()"
                aria-label="Cerrar carrito">
                &times;
            </button>

        </div>

        <div
            id="carritoContenido"
            class="carrito-contenido">

            <div class="carrito-vacio">
                No hay productos en el pedido.
            </div>

        </div>

        <div class="carrito-flotante-footer">

            <div class="carrito-total">

                <span>Total</span>

                <span
                    id="carritoTotal"
                    class="carrito-total-valor">
                    $0
                </span>

            </div>

            <a
                href="Pedidos.php"
                class="btn-ver-pedido">
                Ver pedido
            </a>

        </div>

    </aside>


<main>


    <!-- =================================================
         PEDIDO ACTUAL
    ================================================== -->

    <div class="pedido-actual">

        <h3>
            🛒 Pedido Actual
        </h3>

        <p>

            Productos seleccionados:

            <strong>

                <?php
                echo $cantidadProductos;
                ?>

            </strong>

        </p>

    </div>



    <!-- =================================================
         PRODUCTOS
    ================================================== -->

    <div class="productos">


        <?php

        if ($resultado && mysqli_num_rows($resultado) > 0) {

            while ($fila = mysqli_fetch_assoc($resultado)) {

        ?>


    <div
         class="producto"
        data-categoria="<?= strtolower(trim($fila['categoria'])); ?>">

                <!-- IMAGEN -->

                <?php if (!empty($fila['imagen'])): ?>

                <div class="producto-img-container">
                 
                    <img
                        src="<?= htmlspecialchars($fila['imagen']); ?>"
                        alt="<?= htmlspecialchars($fila['nombre']); ?>">
                    </div> 
                <?php  else: ?>

        <div class="producto-img-container">

              <div class="sin-imagen">
                 Sin foto
              </div>

            </div>

         <?php endif; ?>

                <div class="producto-info">


                    <!-- NOMBRE -->

                    <h3>

                        <?= htmlspecialchars(
                            $fila['nombre']
                        ); ?>

                    </h3>


                    <!-- DESCRIPCIÓN -->

                    <?php if (!empty($fila['descripcion'])) { ?>

                        <p class="descripcion">

                            <?= htmlspecialchars(
                                $fila['descripcion']
                            ); ?>

                        </p>

                    <?php } ?>


                    <!-- CATEGORÍA -->

                    <p>

                        <strong>
                            Categoría:
                        </strong>

                        <?= htmlspecialchars(
                            $fila['categoria']
                        ); ?>

                    </p>


                    <!-- PRECIO -->

                    <div class="precio">

                        $

                        <?= number_format(
                            $fila['precio'],
                            0,
                            ',',
                            '.'
                        ); ?>

                    </div>


                    <!-- DISPONIBILIDAD -->

                    <div class="disponible">

                        <strong>
                            Disponibles:
                        </strong>

                        <?= (int)$fila['stock']; ?>

                    </div>



                    <!-- =================================================
                         AGREGAR AL PEDIDO
                    ================================================== -->

                    <form
                        method="POST"
                        action="menu.php"
                        class="form-pedido js-form-pedido">


                        <input
                            type="hidden"
                            name="accion"
                            value="agregar">

                        <input
                            type="hidden"
                            name="id_producto"
                            value="<?php
                                echo $fila['idProducto'];
                            ?>">


                        <button
                            type="submit"
                            class="btn-agregar">

                            Agregar al pedido

                        </button>


                    </form>


                </div>

            </div>


        <?php

            }

        } else {

        ?>

            <p>
                No hay productos disponibles.
            </p>

        <?php

        }

        ?>

    </div>



    <!-- =================================================
         VOLVER
    ================================================== -->

    <button
        class="volver"
        type="button"
        onclick="window.location.href='Cliente.php';">

        Volver al Panel

    </button>


</main>



    <script>
        /*
         * ================================================================
         * CARRITO FLOTANTE
         * ================================================================
         *
         * El carrito SOLO se abre después de pulsar
         * "Agregar al pedido".
         *
         * La tarjeta, imagen, nombre y descripción NO lo abren.
         */

        const carritoFlotante =
            document.getElementById('carritoFlotante');

        const carritoOverlay =
            document.getElementById('carritoOverlay');

        const carritoContenido =
            document.getElementById('carritoContenido');

        const carritoTotal =
            document.getElementById('carritoTotal');

        const contadorPedido =
            document.getElementById('contadorPedido');


        function formatearPrecio(valor) {

            return new Intl.NumberFormat('es-CO', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            }).format(valor);

        }


        function abrirCarrito() {

            carritoFlotante.classList.add('abierto');

            carritoOverlay.classList.add('abierto');

            carritoFlotante.setAttribute(
                'aria-hidden',
                'false'
            );

        }


        function cerrarCarrito() {

            carritoFlotante.classList.remove('abierto');

            carritoOverlay.classList.remove('abierto');

            carritoFlotante.setAttribute(
                'aria-hidden',
                'true'
            );

        }


        function escaparHtml(valor) {

            return String(valor ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');

        }


        function actualizarCarrito(datos) {

            if (!datos || !datos.ok) {
                return;
            }

            if (contadorPedido) {
                contadorPedido.textContent =
                    datos.cantidadProductos;
            }

            if (!datos.items || datos.items.length === 0) {

                carritoContenido.innerHTML = `
                    <div class="carrito-vacio">
                        No hay productos en el pedido.
                    </div>
                `;

            } else {

                carritoContenido.innerHTML =
                    datos.items.map(function(item) {

                        const subtotal =
                            Number(item.precio) *
                            Number(item.cantidad);

                        let imagen;

                        if (item.imagen) {

                            imagen = `
                                <img
                                    class="carrito-item-img"
                                    src="${escaparHtml(item.imagen)}"
                                    alt="${escaparHtml(item.nombre)}">
                            `;

                        } else {

                            imagen = `
                                <div
                                    class="carrito-item-img">
                                </div>
                            `;

                        }

                        return `
                            <div class="carrito-item">

                                ${imagen}

                                <div class="carrito-item-info">

                                    <p class="carrito-item-nombre">
                                        ${escaparHtml(item.nombre)}
                                    </p>

                                    <div class="carrito-item-precio">
                                        Precio: $${formatearPrecio(item.precio)}
                                    </div>

                                    <div class="carrito-item-cantidad">

                                        <button
                                            type="button"
                                            class="btn-cantidad"
                                            onclick="cambiarCantidad(${item.idProducto}, -1)"
                                            aria-label="Disminuir cantidad">
                                            −
                                        </button>

                                        <span class="cantidad-valor">
                                            ${item.cantidad}
                                        </span>

                                        <button
                                            type="button"
                                            class="btn-cantidad"
                                            onclick="cambiarCantidad(${item.idProducto}, 1)"
                                            aria-label="Aumentar cantidad">
                                            +
                                        </button>

                                    </div>

                                    <div class="carrito-item-precio">
                                        $${formatearPrecio(subtotal)}
                                    </div>

                                </div>

                            </div>
                        `;

                    }).join('');

            }

            carritoTotal.textContent =
                '$' + formatearPrecio(datos.totalPedido);

        }


        async function cambiarCantidad(idProducto, delta) {

            try {

                const datosFormulario =
                    new FormData();

                datosFormulario.append(
                    'accion',
                    'cambiar_cantidad'
                );

                datosFormulario.append(
                    'id_producto',
                    idProducto
                );

                datosFormulario.append(
                    'delta',
                    delta
                );

                const respuesta =
                    await fetch(
                        'menu.php',
                        {
                            method: 'POST',
                            body: datosFormulario,
                            headers: {
                                'X-Requested-With':
                                    'XMLHttpRequest'
                            }
                        }
                    );

                if (!respuesta.ok) {
                    throw new Error(
                        'No se pudo modificar la cantidad.'
                    );
                }

                const datos =
                    await respuesta.json();

                if (!datos.ok) {
                    throw new Error(
                        'No se pudo actualizar el pedido.'
                    );
                }

                actualizarCarrito(datos);

            } catch (error) {

                console.error(error);

                alert(
                    'No fue posible modificar la cantidad.'
                );
            }
        }


        /*
         * Solo interceptamos los formularios que tienen
         * la clase .js-form-pedido.
         */
        document.querySelectorAll('.js-form-pedido')
            .forEach(function(formulario) {

                formulario.addEventListener(
                    'submit',
                    async function(evento) {

                        evento.preventDefault();

                        const boton =
                            formulario.querySelector(
                                '.btn-agregar'
                            );

                        const textoOriginal =
                            boton.textContent;

                        boton.disabled = true;

                        boton.textContent =
                            'Agregando...';


                        try {

                            const datosFormulario =
                                new FormData(formulario);

                            const respuesta =
                                await fetch(
                                    formulario.action,
                                    {
                                        method: 'POST',
                                        body: datosFormulario,
                                        headers: {
                                            'X-Requested-With':
                                                'XMLHttpRequest'
                                        }
                                    }
                                );


                            if (!respuesta.ok) {
                                throw new Error(
                                    'Error al agregar el producto.'
                                );
                            }


                            const datos =
                                await respuesta.json();


                            if (!datos.ok) {
                                throw new Error(
                                    'No se pudo actualizar el pedido.'
                                );
                            }


                            /*
                             * Primero actualizamos el contenido.
                             * Después abrimos el carrito.
                             */
                            actualizarCarrito(datos);

                            abrirCarrito();


                        } catch (error) {

                            console.error(error);

                            alert(
                                'No fue posible agregar el producto al pedido.'
                            );

                        } finally {

                            boton.disabled = false;

                            boton.textContent =
                                textoOriginal;

                        }

                    }
                );

            });


        /*
         * También se puede cerrar presionando ESC.
         */
        document.addEventListener(
            'keydown',
            function(evento) {

                if (
                    evento.key === 'Escape' &&
                    carritoFlotante.classList.contains('abierto')
                ) {

                    cerrarCarrito();

                }

            }
        );
    /*
 * ================================================================
 * FILTRO DE CATEGORÍAS
 * ================================================================
 */

const botonesCategoria =
    document.querySelectorAll('.categoria');

const productos =
    document.querySelectorAll('.producto');


botonesCategoria.forEach(function(boton) {

    boton.addEventListener('click', function() {

        const categoriaSeleccionada =
            this.dataset.categoria;


        /*
         * Mostrar u ocultar productos
         */
        productos.forEach(function(producto) {

            const categoriaProducto =
                producto.dataset.categoria;

            if (
                categoriaSeleccionada === 'todos' ||
                categoriaProducto === categoriaSeleccionada
            ) {

                producto.style.display = '';

            } else {

                producto.style.display = 'none';

            }

        });


        /*
         * Cambiar botón activo
         */
        botonesCategoria.forEach(function(btn) {

            btn.classList.remove('activo');

        });

        this.classList.add('activo');

    });

});
    </script>

</body>

</html>