<?php
session_start();

/*
|--------------------------------------------------------------------------
| VALIDACIÓN DE SESIÓN
|--------------------------------------------------------------------------
| Se acepta id_usuario o id por compatibilidad con la sesión actual.
|--------------------------------------------------------------------------
*/
$id_usuario = $_SESSION['id_usuario'] ?? $_SESSION['id'] ?? 0;

if (empty($id_usuario)) {
    header("Location: index.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| CONEXIÓN
|--------------------------------------------------------------------------
*/
require_once "conexion.php";

/*
|--------------------------------------------------------------------------
| CONSULTAR PEDIDOS DEL USUARIO
|--------------------------------------------------------------------------
*/
$sql = "
    SELECT
        f.id_factura,
        f.numero_factura,
        f.fecha,
        f.subtotal,
        f.impuestos,
        f.descuento,
        f.total,
        f.metodo_pago,
        f.estado,
        f.token_factura
    FROM factura f
    WHERE f.id_usuario = ?
    ORDER BY f.fecha DESC, f.id_factura DESC
";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    die("Error al preparar la consulta: " . mysqli_error($conexion));
}

mysqli_stmt_bind_param($stmt, "i", $id_usuario);
mysqli_stmt_execute($stmt);

$resultado = mysqli_stmt_get_result($stmt);

/*
|--------------------------------------------------------------------------
| DETALLE DEL PEDIDO SELECCIONADO
|--------------------------------------------------------------------------
*/
$id_factura = isset($_GET['id_factura']) ? (int)$_GET['id_factura'] : 0;
$pedidoDetalle = null;
$detalleResultado = null;

if ($id_factura > 0) {

    $sqlDetalle = "
        SELECT
            f.id_factura,
            f.numero_factura,
            f.fecha,
            f.subtotal,
            f.impuestos,
            f.descuento,
            f.total,
            f.metodo_pago,
            f.estado,
            f.token_factura
        FROM factura f
        WHERE f.id_factura = ?
        AND f.id_usuario = ?
        LIMIT 1
    ";

    $stmtDetalle = mysqli_prepare($conexion, $sqlDetalle);

    if (!$stmtDetalle) {
        die("Error al preparar el detalle: " . mysqli_error($conexion));
    }

    mysqli_stmt_bind_param($stmtDetalle, "ii", $id_factura, $id_usuario);
    mysqli_stmt_execute($stmtDetalle);

    $resultadoCabecera = mysqli_stmt_get_result($stmtDetalle);

    if ($resultadoCabecera && mysqli_num_rows($resultadoCabecera) === 1) {

        $pedidoDetalle = mysqli_fetch_assoc($resultadoCabecera);
      
    
        $sqlLineas = "
            SELECT
                df.id_detalle,
                df.id_factura,
                df.idProducto,
                df.cantidad,
                df.precio_unitario,
                df.subtotal,
                p.nombre
            FROM detalle_factura df
            LEFT JOIN producto p
                ON p.idProducto = df.idProducto
            WHERE df.id_factura = ?
            ORDER BY df.id_detalle ASC
        ";

        $stmtLineas = mysqli_prepare($conexion, $sqlLineas);

        if (!$stmtLineas) {
            die("Error al preparar los productos: " . mysqli_error($conexion));
        }

        mysqli_stmt_bind_param($stmtLineas, "i", $id_factura);
        mysqli_stmt_execute($stmtLineas);

        $detalleResultado = mysqli_stmt_get_result($stmtLineas);

        mysqli_stmt_close($stmtLineas);
    }

    mysqli_stmt_close($stmtDetalle);
}

/*
|--------------------------------------------------------------------------
| DATOS DEL CLIENTE
|--------------------------------------------------------------------------
*/
$nombre = $_SESSION['nombre'] ?? '';
$apellido = $_SESSION['apellido'] ?? '';

$nombreCompleto = trim($nombre . ' ' . $apellido);
if ($nombreCompleto === '') {
    $nombreCompleto = 'Cliente';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mis Pedidos - Sabora</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5ede6;
            color: #333;
        }

        .contenedor {
            width: min(1100px, calc(100% - 30px));
            margin: 0 auto;
        }

        .encabezado {
            background: #ffffff;
            border-radius: 0 0 18px 18px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 22px 25px;
            margin-bottom: 25px;
        }

        .encabezado h1 {
            margin: 0;
            color: #ff6b35;
            font-size: 30px;
        }

        .encabezado p {
            margin: 8px 0 0;
            color: #666;
            font-size: 15px;
        }

        .barra-superior {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 22px;
            flex-wrap: wrap;
        }

        .barra-superior h2 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }

        .boton {
            display: inline-block;
            text-decoration: none;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            background: #ff6b35;
            color: #fff;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .boton:hover {
            background: #e85a2a;
            transform: translateY(-1px);
        }

        .lista-pedidos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(290px, 1fr));
            gap: 18px;
            padding-bottom: 30px;
        }

        .pedido-card {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.09);
            padding: 20px;
        }

        .pedido-cabecera {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
        }

        .pedido-cabecera h3 {
            margin: 0;
            color: #ff6b35;
            font-size: 19px;
        }

        .estado {
            border-radius: 999px;
            padding: 6px 10px;
            background: #fff1eb;
            color: #d94d1f;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
            white-space: nowrap;
        }

        .dato {
            margin: 9px 0;
            color: #555;
            font-size: 14px;
        }

        .dato strong {
            color: #333;
        }

        .total {
            margin-top: 15px;
            padding-top: 14px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
        }

        .total span:first-child {
            font-weight: bold;
            color: #333;
        }

        .total span:last-child {
            color: #ff6b35;
            font-size: 20px;
            font-weight: bold;
        }

        .acciones {
            margin-top: 18px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .boton-secundario {
            background: #f0f0f0;
            color: #333;
        }

        .boton-secundario:hover {
            background: #e0e0e0;
        }

        .sin-pedidos {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.09);
            text-align: center;
            padding: 50px 25px;
            margin-bottom: 30px;
        }

        .sin-pedidos .icono {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .sin-pedidos h3 {
            margin: 0 0 10px;
            color: #333;
        }

        .sin-pedidos p {
            color: #777;
            margin: 0 0 22px;
        }

        .pie {
            text-align: center;
            color: #666;
            font-size: 14px;
            padding: 5px 0 30px;
        }

        @media (max-width: 600px) {
            .contenedor {
                width: min(100% - 20px, 1100px);
            }

            .encabezado {
                padding: 18px;
            }

            .encabezado h1 {
                font-size: 25px;
            }

            .barra-superior {
                align-items: stretch;
            }

            .barra-superior .boton {
                width: 100%;
                text-align: center;
            }

            .pedido-cabecera {
                flex-direction: column;
            }

            .acciones {
                flex-direction: column;
            }

            .acciones .boton {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<div class="contenedor">

    <header class="encabezado">
        <h1>📋 Mis Pedidos</h1>
        <p>Histórico de pedidos realizados en Sabora.</p>
    </header>

    <div class="barra-superior">
        <h2>Hola, <?php echo htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'); ?></h2>

        <a href="Cliente.php" class="boton">
            ← Volver al panel
        </a>
    </div>

    <?php if ($pedidoDetalle !== null): ?>

        <section class="pedido-card" style="margin-bottom: 30px;">

            <div class="pedido-cabecera">
                <h3>
                    Pedido #
                    <?php
                    echo htmlspecialchars(
                        $pedidoDetalle['numero_factura'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </h3>

                <span class="estado">
                    <?php
                    echo htmlspecialchars(
                        $pedidoDetalle['estado'],
                        ENT_QUOTES,
                        'UTF-8'
                    );
                    ?>
                </span>
            </div>

            <div class="dato">
                <strong>Fecha:</strong>
                <?php
                echo htmlspecialchars(
                    $pedidoDetalle['fecha'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </div>

            <div class="dato">
                <strong>Método de pago:</strong>
                <?php
                echo htmlspecialchars(
                    $pedidoDetalle['metodo_pago'],
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </div>

            <div style="overflow-x:auto; margin-top:20px;">

                <table style="width:100%; border-collapse:collapse; min-width:600px;">

                    <thead>
                        <tr style="background:#fff1eb;">
                            <th style="padding:12px; text-align:left;">Producto</th>
                            <th style="padding:12px; text-align:center;">Cantidad</th>
                            <th style="padding:12px; text-align:right;">Precio</th>
                            <th style="padding:12px; text-align:right;">Subtotal</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($detalleResultado && mysqli_num_rows($detalleResultado) > 0): ?>

                        <?php while ($detalle = mysqli_fetch_assoc($detalleResultado)): ?>

                            <tr style="border-bottom:1px solid #eee;">

                                <td style="padding:12px;">
                                    <?php
                                    $nombreProducto = $detalle['nombre']
                                        ?? ('Producto #' . $detalle['idProducto']);

                                    echo htmlspecialchars(
                                        $nombreProducto,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                    ?>
                                </td>

                                <td style="padding:12px; text-align:center;">
                                    <?php echo (int)$detalle['cantidad']; ?>
                                </td>

                                <td style="padding:12px; text-align:right;">
                                    $
                                    <?php
                                    echo number_format(
                                        (float)$detalle['precio_unitario'],
                                        0,
                                        ',',
                                        '.'
                                    );
                                    ?>
                                </td>

                                <td style="padding:12px; text-align:right; font-weight:bold;">
                                    $
                                    <?php
                                    echo number_format(
                                        (float)$detalle['subtotal'],
                                        0,
                                        ',',
                                        '.'
                                    );
                                    ?>
                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>
                            <td
                                colspan="4"
                                style="padding:25px; text-align:center; color:#777;"
                            >
                                No se encontraron productos para este pedido.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <div style="margin-top:20px;">

                <div class="dato">
                    <strong>Subtotal:</strong>
                    $
                    <?php
                    echo number_format(
                        (float)$pedidoDetalle['subtotal'],
                        0,
                        ',',
                        '.'
                    );
                    ?>
                </div>

                <?php if ((float)$pedidoDetalle['descuento'] > 0): ?>
                    <div class="dato">
                        <strong>Descuento:</strong>
                        $
                        <?php
                        echo number_format(
                            (float)$pedidoDetalle['descuento'],
                            0,
                            ',',
                            '.'
                        );
                        ?>
                    </div>
                <?php endif; ?>

                <?php if ((float)$pedidoDetalle['impuestos'] > 0): ?>
                    <div class="dato">
                        <strong>Impuestos:</strong>
                        $
                        <?php
                        echo number_format(
                            (float)$pedidoDetalle['impuestos'],
                            0,
                            ',',
                            '.'
                        );
                        ?>
                    </div>
                <?php endif; ?>

                <div class="total">
                    <span>Total</span>

                    <span>
                        $
                        <?php
                        echo number_format(
                            (float)$pedidoDetalle['total'],
                            0,
                            ',',
                            '.'
                        );
                        ?>
                    </span>
                </div>

            </div>

            <div class="acciones">

                <a href="Mis_Pedidos.php" class="boton">
                    ← Volver a mis pedidos
                </a>

            </div>

        </section>

    <?php elseif ($resultado && mysqli_num_rows($resultado) > 0): ?>

        <section class="lista-pedidos">

            <?php while ($pedido = mysqli_fetch_assoc($resultado)): ?>

                <article class="pedido-card">

                    <div class="pedido-cabecera">
                        <h3>
                            Pedido #
                            <?php
                            echo htmlspecialchars(
                                $pedido['numero_factura'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </h3>

                        <span class="estado">
                            <?php
                            echo htmlspecialchars(
                                $pedido['estado'],
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            ?>
                        </span>
                    </div>

                    <div class="dato">
                        <strong>Fecha:</strong>
                        <?php
                        echo htmlspecialchars(
                            $pedido['fecha'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </div>

                    <div class="dato">
                        <strong>Método de pago:</strong>
                        <?php
                        echo htmlspecialchars(
                            $pedido['metodo_pago'],
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>
                    </div>

                    <div class="total">
                        <span>Total</span>

                        <span>
                            $
                            <?php
                            echo number_format(
                                (float)$pedido['total'],
                                0,
                                ',',
                                '.'
                            );
                            ?>
                        </span>
                    </div>

                    <div class="acciones">

                        <a
                            href="Mis_Pedidos.php?id_factura=<?php echo (int)$pedido['id_factura']; ?>"
                            class="boton"
                        >
                            Ver pedido
                        </a>

                    </div>

                </article>

            <?php endwhile; ?>

        </section>

    <?php else: ?>

        <section class="sin-pedidos">

            <div class="icono">📋</div>

            <h3>Aún no tienes pedidos</h3>

            <p>
                Cuando realices una compra en Sabora,
                aparecerá aquí tu historial.
            </p>

            <a href="menu.php" class="boton">
                Ver menú
            </a>

        </section>

    <?php endif; ?>

    <footer class="pie">
        Sistema de Gestión Restaurante <strong>SABORA</strong><br>
        Panel exclusivo para Clientes.<br>
        © 2026 - Todos los derechos reservados.
    </footer>

</div>

</body>
</html>

<?php
if ($stmt) {
    mysqli_stmt_close($stmt);
}

if ($conexion) {
    mysqli_close($conexion);
}
?>