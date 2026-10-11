<?php

session_start();

/*
|--------------------------------------------------------------------------
| SEGURIDAD - SOLO ADMINISTRADORES
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['correo'])) {
    header("Location: Administrador.html");
    exit();
}

if (strtolower($_SESSION['rol'] ?? '') !== 'administrador') {
    header("Location: Cliente.html");
    exit();
}

require_once "conexion.php";


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

function moneda($valor): string
{
    return '$' . number_format(
        (float)$valor,
        0,
        ',',
        '.'
    );
}

function estadoClase($estado): string
{
    switch (strtolower((string)$estado)) {

        case 'pagada':
            return 'estado pagada';

        case 'generada':
            return 'estado generada';

        case 'anulada':
            return 'estado anulada';

        default:
            return 'estado';
    }
}


/*
|--------------------------------------------------------------------------
| FILTROS
|--------------------------------------------------------------------------
*/

$usuarioFiltro = isset($_GET['usuario'])
    ? (int)$_GET['usuario']
    : 0;

$buscar = trim($_GET['buscar'] ?? '');

$estadoFiltro = trim($_GET['estado'] ?? '');

$fechaDesde = trim($_GET['fecha_desde'] ?? '');

$fechaHasta = trim($_GET['fecha_hasta'] ?? '');

$idSeleccionada = isset($_GET['id_factura'])
    ? (int)$_GET['id_factura']
    : 0;


/*
|--------------------------------------------------------------------------
| USUARIOS PARA EL SELECT
|--------------------------------------------------------------------------
*/

$sqlUsuarios = "
    SELECT
        id,
        nombre,
        apellido,
        correo
    FROM usuarios
    ORDER BY nombre ASC, apellido ASC
";

$resultadoUsuarios = mysqli_query(
    $conexion,
    $sqlUsuarios
);

if (!$resultadoUsuarios) {
    die(
        "Error al consultar usuarios: "
        . mysqli_error($conexion)
    );
}


/*
|--------------------------------------------------------------------------
| CONSTRUCCIÓN DE FILTROS
|--------------------------------------------------------------------------
*/

$where = [];
$types = '';
$params = [];


/*
| Filtrar por usuario específico
*/

if ($usuarioFiltro > 0) {

    $where[] = "f.id_usuario = ?";
    $types .= 'i';
    $params[] = $usuarioFiltro;
}


/*
| Buscar por nombre, apellido o correo
*/

if ($buscar !== '') {

    $where[] = "
        (
            u.nombre LIKE ?
            OR u.apellido LIKE ?
            OR u.correo LIKE ?
            OR CONCAT(u.nombre, ' ', u.apellido) LIKE ?
        )
    ";

    $valorBusqueda = '%' . $buscar . '%';

    $types .= 'ssss';

    $params[] = $valorBusqueda;
    $params[] = $valorBusqueda;
    $params[] = $valorBusqueda;
    $params[] = $valorBusqueda;
}


/*
| Filtrar por estado
*/

if ($estadoFiltro !== '') {

    $where[] = "f.estado = ?";

    $types .= 's';

    $params[] = $estadoFiltro;
}


/*
| Fecha inicial
*/

if ($fechaDesde !== '') {

    $where[] = "DATE(f.fecha) >= ?";

    $types .= 's';

    $params[] = $fechaDesde;
}


/*
| Fecha final
*/

if ($fechaHasta !== '') {

    $where[] = "DATE(f.fecha) <= ?";

    $types .= 's';

    $params[] = $fechaHasta;
}


/*
|--------------------------------------------------------------------------
| WHERE FINAL
|--------------------------------------------------------------------------
*/

$whereSQL = '';

if (!empty($where)) {
    $whereSQL = 'WHERE ' . implode(' AND ', $where);
}


/*
|--------------------------------------------------------------------------
| FUNCIÓN PARA VINCULAR PARÁMETROS DINÁMICOS
|--------------------------------------------------------------------------
*/

function bindParametros(
    mysqli_stmt $stmt,
    string $types,
    array &$params
): void {

    if ($types === '') {
        return;
    }

    $bind = [$types];

    foreach ($params as $key => &$value) {
        $bind[] = &$value;
    }

    call_user_func_array(
        [$stmt, 'bind_param'],
        $bind
    );
}


/*
|--------------------------------------------------------------------------
| CONSULTA DE VENTAS
|--------------------------------------------------------------------------
*/

$sqlVentas = "
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
        f.token_factura,

        u.id AS id_usuario,
        u.nombre,
        u.apellido,
        u.correo,
        u.direccion

    FROM factura f

    LEFT JOIN usuarios u
        ON u.id = f.id_usuario

    $whereSQL

    ORDER BY
        f.fecha DESC,
        f.id_factura DESC
";


$stmtVentas = mysqli_prepare(
    $conexion,
    $sqlVentas
);

if (!$stmtVentas) {
    die(
        "Error al preparar consulta de ventas: "
        . mysqli_error($conexion)
    );
}

bindParametros(
    $stmtVentas,
    $types,
    $params
);

mysqli_stmt_execute($stmtVentas);

$resultadoVentas = mysqli_stmt_get_result(
    $stmtVentas
);


/*
|--------------------------------------------------------------------------
| ESTADÍSTICAS
|--------------------------------------------------------------------------
*/

$sqlEstadisticas = "
    SELECT

        COUNT(*) AS total_pedidos,

        SUM(
            CASE
                WHEN f.estado = 'pagada'
                THEN 1
                ELSE 0
            END
        ) AS ventas_pagadas,

        SUM(
            CASE
                WHEN f.estado = 'generada'
                THEN 1
                ELSE 0
            END
        ) AS ventas_generadas,

        SUM(
            CASE
                WHEN f.estado = 'anulada'
                THEN 1
                ELSE 0
            END
        ) AS ventas_anuladas,

        COALESCE(
            SUM(
                CASE
                    WHEN f.estado = 'pagada'
                    THEN f.total
                    ELSE 0
                END
            ),
            0
        ) AS total_vendido

    FROM factura f

    LEFT JOIN usuarios u
        ON u.id = f.id_usuario

    $whereSQL
";


$stmtEstadisticas = mysqli_prepare(
    $conexion,
    $sqlEstadisticas
);

if (!$stmtEstadisticas) {
    die(
        "Error al preparar estadísticas: "
        . mysqli_error($conexion)
    );
}

bindParametros(
    $stmtEstadisticas,
    $types,
    $params
);

mysqli_stmt_execute($stmtEstadisticas);

$resultadoEstadisticas =
    mysqli_stmt_get_result(
        $stmtEstadisticas
    );

$estadisticas =
    mysqli_fetch_assoc(
        $resultadoEstadisticas
    );

$totalPedidos =
    (int)($estadisticas['total_pedidos'] ?? 0);

$ventasPagadas =
    (int)($estadisticas['ventas_pagadas'] ?? 0);

$ventasGeneradas =
    (int)($estadisticas['ventas_generadas'] ?? 0);

$ventasAnuladas =
    (int)($estadisticas['ventas_anuladas'] ?? 0);

$totalVendido =
    (float)($estadisticas['total_vendido'] ?? 0);


/*
|--------------------------------------------------------------------------
| DETALLE DE UNA FACTURA
|--------------------------------------------------------------------------
*/

$facturaSeleccionada = null;
$detallesSeleccionados = [];

if ($idSeleccionada > 0) {

    $sqlFactura = "
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
            f.token_factura,

            u.id AS id_usuario,
            u.nombre,
            u.apellido,
            u.correo,
            u.identificacion,
            u.direccion

        FROM factura f

        LEFT JOIN usuarios u
            ON u.id = f.id_usuario

        WHERE f.id_factura = ?

        LIMIT 1
    ";

    $stmtFactura = mysqli_prepare(
        $conexion,
        $sqlFactura
    );

    if ($stmtFactura) {

        mysqli_stmt_bind_param(
            $stmtFactura,
            "i",
            $idSeleccionada
        );

        mysqli_stmt_execute(
            $stmtFactura
        );

        $resultadoFactura =
            mysqli_stmt_get_result(
                $stmtFactura
            );

        $facturaSeleccionada =
            mysqli_fetch_assoc(
                $resultadoFactura
            );

        mysqli_stmt_close(
            $stmtFactura
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PRODUCTOS DE LA FACTURA
    |--------------------------------------------------------------------------
    */

    if ($facturaSeleccionada) {

        $sqlDetalle = "
            SELECT

                df.id_detalle,
                df.cantidad,
                df.precio_unitario,
                df.subtotal,

                p.nombre AS producto

            FROM detalle_factura df

            LEFT JOIN producto p
                ON p.idProducto = df.idProducto

            WHERE df.id_factura = ?

            ORDER BY df.id_detalle ASC
        ";

        $stmtDetalle = mysqli_prepare(
            $conexion,
            $sqlDetalle
        );

        if ($stmtDetalle) {

            mysqli_stmt_bind_param(
                $stmtDetalle,
                "i",
                $idSeleccionada
            );

            mysqli_stmt_execute(
                $stmtDetalle
            );

            $resultadoDetalle =
                mysqli_stmt_get_result(
                    $stmtDetalle
                );

            while (
                $fila =
                mysqli_fetch_assoc(
                    $resultadoDetalle
                )
            ) {

                $detallesSeleccionados[] =
                    $fila;
            }

            mysqli_stmt_close(
                $stmtDetalle
            );
        }
    }
}

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Pedidos y Ventas - SABORA</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI', sans-serif;
}

body{
    background:#efe7e2;
    color:#333;
}


/* HEADER */

header{
    background:#ff6b35;
    color:white;
    padding:20px 5%;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

header h1{
    font-size:28px;
}

.usuario{
    text-align:right;
    font-size:14px;
}


/* CONTENEDOR */

.container{
    width:92%;
    max-width:1400px;
    margin:30px auto;
}


/* BOTÓN VOLVER */

.volver{
    display:inline-block;
    text-decoration:none;
    color:white;
    background:#ff6b35;
    padding:10px 18px;
    border-radius:8px;
    margin-bottom:20px;
}

.volver:hover{
    background:#ff6b35;
}


/* TÍTULO */

.titulo{
    background:white;
    border-radius:15px;
    padding:25px;
    margin-bottom:20px;
    box-shadow:0 5px 15px rgba(0,0,0,.08);
}

.titulo h2{
    color:#ff6b35;
    margin-bottom:8px;
}

.titulo p{
    color:#666;
}


/* ESTADÍSTICAS */

.estadisticas{
    display:grid;
    grid-template-columns:
        repeat(auto-fit,minmax(190px,1fr));

    gap:18px;
    margin-bottom:25px;
}

.tarjeta{
    background:white;
    padding:22px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,.08);
}

.tarjeta h3{
    color:#777;
    font-size:14px;
    margin-bottom:10px;
}

.tarjeta .numero{
    color:#ff6b35;
    font-size:27px;
    font-weight:bold;
}


/* FILTROS */

.filtros{
    background:white;
    padding:25px;
    border-radius:15px;
    box-shadow:0 5px 15px rgba(0,0,0,.08);
    margin-bottom:25px;
}

.filtros h3{
    color:#ff6b35;
    margin-bottom:18px;
}

.formulario{
    display:grid;
    grid-template-columns:
        repeat(auto-fit,minmax(180px,1fr));

    gap:15px;
}

.campo{
    display:flex;
    flex-direction:column;
}

.campo label{
    font-weight:bold;
    font-size:13px;
    margin-bottom:6px;
}

.campo input,
.campo select{
    padding:11px;
    border:1px solid #ccc;
    border-radius:8px;
    outline:none;
}

.campo input:focus,
.campo select:focus{
    border-color:#ff6b35;
}

.botones{
    display:flex;
    gap:10px;
    align-items:end;
}

.btn{
    border:none;
    text-decoration:none;
    color:white;
    background:#ff6b35;
    padding:11px 18px;
    border-radius:8px;
    cursor:pointer;
}

.btn:hover{
    background:#e85c2c;
}

.btn-limpiar{
    background:#777;
}

.btn-limpiar:hover{
    background:#555;
}


/* TABLA */

.tabla-contenedor{
    background:white;
    border-radius:15px;
    padding:20px;
    box-shadow:0 5px 15px rgba(0,0,0,.08);
    overflow-x:auto;
}

.tabla-contenedor h3{
    color:#ff6b35;
    margin-bottom:15px;
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:950px;
}

th{
    background:#ff6b35;
    color:white;
    padding:13px 10px;
    text-align:left;
    font-size:14px;
}

td{
    padding:13px 10px;
    border-bottom:1px solid #eee;
    font-size:14px;
}

tr:hover td{
    background:#fff7f3;
}


/* ESTADOS */

.estado{
    display:inline-block;
    padding:5px 10px;
    border-radius:20px;
    font-size:12px;
    font-weight:bold;
    background:#ddd;
}

.estado.pagada{
    background:#d8f5df;
    color:#187536;
}

.estado.generada{
    background:#fff0c2;
    color:#8a6500;
}

.estado.anulada{
    background:#ffdada;
    color:#a40000;
}


/* BOTÓN VER */

.btn-ver{
    display:inline-block;
    text-decoration:none;
    color:white;
    background:#ff6b35;
    padding:7px 12px;
    border-radius:7px;
    font-size:13px;
}

.btn-ver:hover{
    background:#e85c2c;
}


/* DETALLE */

.detalle{
    background:white;
    border-radius:15px;
    padding:25px;
    margin-top:25px;
    box-shadow:0 5px 15px rgba(0,0,0,.08);
}

.detalle h2{
    color:#ff6b35;
    margin-bottom:20px;
}

.info-grid{
    display:grid;
    grid-template-columns:
        repeat(auto-fit,minmax(220px,1fr));

    gap:15px;
    margin-bottom:25px;
}

.info{
    background:#f8f5f3;
    padding:15px;
    border-radius:10px;
}

.info strong{
    display:block;
    color:#777;
    font-size:13px;
    margin-bottom:5px;
}

.total{
    text-align:right;
    font-size:22px;
    font-weight:bold;
    color:#ff6b35;
    margin-top:20px;
}


/* SIN RESULTADOS */

.sin-resultados{
    text-align:center;
    padding:40px;
    color:#777;
}

footer{
    text-align:center;
    padding:25px 20px;
    margin-top:30px;
    color:black;
    font-size:14px;
    line-height:1.8;
}

footer strong{
    font-weight:700;
}


</style>

</head>

<body>


<header>

    <div>
        <h1>SABORA</h1>
        
    </div>

    <div class="usuario">
    Administrador<br>
    <?= e($_SESSION['correo']) ?><br>
    Pedidos y Ventas
</div>
</header>


<div class="container">


<a
    href="Administrador.php"
    class="volver"
>
    ← Volver al panel
</a>


<div class="titulo">

    <h2>Pedidos y Ventas</h2>

    <p>
        Consulta todas las ventas realizadas,
        identifica el usuario asociado y revisa
        el detalle de cada pedido.
    </p>

</div>


<!-- ESTADÍSTICAS -->

<div class="estadisticas">

    <div class="tarjeta">

        <h3>Total de pedidos</h3>

        <div class="numero">
            <?= $totalPedidos ?>
        </div>

    </div>


    <div class="tarjeta">

        <h3>Ventas pagadas</h3>

        <div class="numero">
            <?= $ventasPagadas ?>
        </div>

    </div>


    <div class="tarjeta">

        <h3>Generadas / pendientes</h3>

        <div class="numero">
            <?= $ventasGeneradas ?>
        </div>

    </div>


    <div class="tarjeta">

        <h3>Anuladas</h3>

        <div class="numero">
            <?= $ventasAnuladas ?>
        </div>

    </div>


    <div class="tarjeta">

        <h3>Total vendido</h3>

        <div class="numero">
            <?= moneda($totalVendido) ?>
        </div>

    </div>

</div>


<!-- FILTROS -->

<div class="filtros">

    <h3>Filtrar ventas</h3>

    <form
        method="GET"
        action="pedidos_admin.php"
    >

        <div class="formulario">


            <div class="campo">

                <label>
                    Usuario
                </label>

                <select name="usuario">

                    <option value="">
                        Todos los usuarios
                    </option>

                    <?php while (
                        $usuario =
                        mysqli_fetch_assoc(
                            $resultadoUsuarios
                        )
                    ): ?>

                        <option
                            value="<?= (int)$usuario['id'] ?>"
                            <?= (
                                $usuarioFiltro ==
                                $usuario['id']
                            )
                            ? 'selected'
                            : ''
                            ?>
                        >

                            <?= e(
                                $usuario['nombre']
                                . ' '
                                . $usuario['apellido']
                            ) ?>

                            -
                            <?= e(
                                $usuario['correo']
                            ) ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <div class="campo">

                <label>
                    Buscar usuario
                </label>

                <input
                    type="text"
                    name="buscar"
                    value="<?= e($buscar) ?>"
                    placeholder="Nombre, apellido o correo"
                >

            </div>


            <div class="campo">

                <label>
                    Estado
                </label>

                <select name="estado">

                    <option
                        value=""
                        <?= $estadoFiltro === ''
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Todos
                    </option>

                    <option
                        value="pagada"
                        <?= $estadoFiltro === 'pagada'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Pagada
                    </option>

                    <option
                        value="generada"
                        <?= $estadoFiltro === 'generada'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Generada
                    </option>

                    <option
                        value="anulada"
                        <?= $estadoFiltro === 'anulada'
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Anulada
                    </option>

                </select>

            </div>


            <div class="campo">

                <label>
                    Desde
                </label>

                <input
                    type="date"
                    name="fecha_desde"
                    value="<?= e($fechaDesde) ?>"
                >

            </div>


            <div class="campo">

                <label>
                    Hasta
                </label>

                <input
                    type="date"
                    name="fecha_hasta"
                    value="<?= e($fechaHasta) ?>"
                >

            </div>


            <div class="botones">

                <button
                    type="submit"
                    class="btn"
                >
                    Buscar
                </button>

                <a
                    href="pedidos_admin.php"
                    class="btn btn-limpiar"
                >
                    Limpiar
                </a>

            </div>

        </div>

    </form>

</div>


<!-- TABLA DE VENTAS -->

<div class="tabla-contenedor">

    <h3>
        Ventas registradas
    </h3>


    <?php if (
        mysqli_num_rows($resultadoVentas) > 0
    ): ?>

        <table>

            <thead>

                <tr>

                    <th>
                        Factura
                    </th>

                    <th>
                        Usuario
                    </th>

                    <th>
                        Correo
                    </th>

                    <th>
                        Fecha
                    </th>

                    <th>
                        Total
                    </th>

                    <th>
                        Método
                    </th>

                    <th>
                        Estado
                    </th>

                    <th>
                        Acción
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php while (
                $venta =
                mysqli_fetch_assoc(
                    $resultadoVentas
                )
            ): ?>

                <tr>

                    <td>

                        #<?= e(
                            $venta['numero_factura']
                        ) ?>

                    </td>


                    <td>

                        <?= e(
                            trim(
                                $venta['nombre']
                                . ' '
                                . $venta['apellido']
                            )
                        ) ?>

                    </td>


                    <td>

                        <?= e(
                            $venta['correo']
                        ) ?>

                    </td>


                    <td>

                        <?= e(
                            $venta['fecha']
                        ) ?>

                    </td>


                    <td>

                        <strong>
                            <?= moneda(
                                $venta['total']
                            ) ?>
                        </strong>

                    </td>


                    <td>

                        <?= e(
                            $venta['metodo_pago']
                        ) ?>

                    </td>


                    <td>

                        <span class="<?= estadoClase(
                            $venta['estado']
                        ) ?>">

                            <?= e(
                                strtoupper(
                                    $venta['estado']
                                )
                            ) ?>

                        </span>

                    </td>


                    <td>

                        <a
                            href="?id_factura=<?= (int)$venta['id_factura'] ?>&usuario=<?= $usuarioFiltro ?>&buscar=<?= urlencode($buscar) ?>&estado=<?= urlencode($estadoFiltro) ?>&fecha_desde=<?= urlencode($fechaDesde) ?>&fecha_hasta=<?= urlencode($fechaHasta) ?>"
                            class="btn-ver"
                        >
                            Ver detalle
                        </a>

                    </td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    <?php else: ?>

        <div class="sin-resultados">

            No se encontraron ventas con
            los filtros seleccionados.

        </div>

    <?php endif; ?>

</div>


<!-- DETALLE DE VENTA -->

<?php if ($facturaSeleccionada): ?>

<div class="detalle">

    <h2>
        Detalle de la factura
        #<?= e(
            $facturaSeleccionada['numero_factura']
        ) ?>
    </h2>


    <div class="info-grid">


        <div class="info">

            <strong>
                Cliente
            </strong>

            <?= e(
                $facturaSeleccionada['nombre']
                . ' '
                . $facturaSeleccionada['apellido']
            ) ?>

        </div>


        <div class="info">

            <strong>
                Correo
            </strong>

            <?= e(
                $facturaSeleccionada['correo']
            ) ?>

        </div>


        <div class="info">

            <strong>
                Identificación
            </strong>

            <?= e(
                $facturaSeleccionada['identificacion']
                ?? ''
            ) ?>

        </div>


        <div class="info">

            <strong>
                Dirección
            </strong>

            <?= e(
                $facturaSeleccionada['direccion']
                ?? ''
            ) ?>

        </div>


        <div class="info">

            <strong>
                Fecha
            </strong>

            <?= e(
                $facturaSeleccionada['fecha']
            ) ?>

        </div>


        <div class="info">

            <strong>
                Método de pago
            </strong>

            <?= e(
                $facturaSeleccionada['metodo_pago']
            ) ?>

        </div>


        <div class="info">

            <strong>
                Estado
            </strong>

            <span class="<?= estadoClase(
                $facturaSeleccionada['estado']
            ) ?>">

                <?= e(
                    strtoupper(
                        $facturaSeleccionada['estado']
                    )
                ) ?>

            </span>

        </div>

    </div>


    <h3>
        Productos comprados
    </h3>


    <?php if (
        !empty($detallesSeleccionados)
    ): ?>

        <div style="overflow-x:auto;">

            <table>

                <thead>

                    <tr>

                        <th>
                            Producto
                        </th>

                        <th>
                            Cantidad
                        </th>

                        <th>
                            Precio unitario
                        </th>

                        <th>
                            Subtotal
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php foreach (
                    $detallesSeleccionados
                    as $detalle
                ): ?>

                    <tr>

                        <td>

                            <?= e(
                                $detalle['producto']
                                ?? 'Producto no disponible'
                            ) ?>

                        </td>


                        <td>

                            <?= e(
                                $detalle['cantidad']
                            ) ?>

                        </td>


                        <td>

                            <?= moneda(
                                $detalle['precio_unitario']
                            ) ?>

                        </td>


                        <td>

                            <?= moneda(
                                $detalle['subtotal']
                            ) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php else: ?>

        <p>
            No hay productos registrados
            para esta factura.
        </p>

    <?php endif; ?>


    <div class="total">

        Subtotal:
        <?= moneda(
            $facturaSeleccionada['subtotal']
        ) ?>

        <br>

        Impuestos:
        <?= moneda(
            $facturaSeleccionada['impuestos']
        ) ?>

        <br>

        Descuento:
        <?= moneda(
            $facturaSeleccionada['descuento']
        ) ?>

        <br><br>

        TOTAL:
        <?= moneda(
            $facturaSeleccionada['total']
        ) ?>

    </div>

</div>

<?php endif; ?>


</div>
<footer>

     Sistema de Gestión Restaurante <strong>SABORA</strong><br>
    Panel de Administración y Gestión del Restaurante.<br>
    &copy; 2026 - Todos los derechos reservados.


</footer>
</body>

</html>