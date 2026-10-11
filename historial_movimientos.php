
<?php
session_start();
require_once "conexion.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Proteger el historial: solo administrador.
$rol = strtolower(trim($_SESSION["rol"] ?? ""));

if ($rol !== "administrador") {
    http_response_code(403);
    exit("No tienes permisos para consultar este historial.");
}

// Función para mostrar datos de forma segura.
function h($valor) {
    return htmlspecialchars(
        (string) ($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Recibir filtros.
$idProducto = filter_input(INPUT_GET, "producto", FILTER_VALIDATE_INT);
$idUsuario = filter_input(INPUT_GET, "usuario", FILTER_VALIDATE_INT);

$tipo = trim($_GET["tipo"] ?? "");
$desde = trim($_GET["desde"] ?? "");
$hasta = trim($_GET["hasta"] ?? "");

$tiposValidos = ["ENTRADA", "SALIDA", "AJUSTE"];

if (!in_array($tipo, $tiposValidos, true)) {
    $tipo = "";
}

// Validar fechas con formato YYYY-MM-DD.
function fechaValida($fecha) {
    if ($fecha === "") {
        return true;
    }

    $objeto = DateTime::createFromFormat("!Y-m-d", $fecha);

    return $objeto !== false
        && $objeto->format("Y-m-d") === $fecha;
}

if (!fechaValida($desde) || !fechaValida($hasta)) {
    exit("Formato de fecha inválido.");
}

if ($desde !== "" && $hasta !== "" && $desde > $hasta) {
    exit("La fecha inicial no puede ser posterior a la fecha final.");
}

if ($idProducto === false || $idProducto < 1) {
    $idProducto = null;
}

if ($idUsuario === false || $idUsuario < 1) {
    $idUsuario = null;
}

try {
    // Productos para el filtro.
    $resultadoProductos = mysqli_query(
        $conexion,
        "SELECT idProducto, nombre
         FROM producto
         ORDER BY nombre ASC"
    );

    $productos = mysqli_fetch_all(
        $resultadoProductos,
        MYSQLI_ASSOC
    );

    // Usuarios para el filtro.
    $resultadoUsuarios = mysqli_query(
        $conexion,
        "SELECT id, nombre, apellido
         FROM usuarios
         ORDER BY nombre ASC, apellido ASC"
    );

    $usuarios = mysqli_fetch_all(
        $resultadoUsuarios,
        MYSQLI_ASSOC
    );

    // Construir filtros SQL parametrizados.
    $condiciones = [];
    $parametros = [];
    $tiposParametros = "";

    if ($idProducto !== null) {
        $condiciones[] = "m.idProducto = ?";
        $tiposParametros .= "i";
        $parametros[] = $idProducto;
    }

    if ($tipo !== "") {
        $condiciones[] = "m.tipoMovimiento = ?";
        $tiposParametros .= "s";
        $parametros[] = $tipo;
    }

    if ($idUsuario !== null) {
        $condiciones[] = "m.idUsuario = ?";
        $tiposParametros .= "i";
        $parametros[] = $idUsuario;
    }

    if ($desde !== "") {
        $condiciones[] = "m.fechaMovimiento >= ?";
        $tiposParametros .= "s";
        $parametros[] = $desde . " 00:00:00";
    }

    if ($hasta !== "") {
        $condiciones[] = "m.fechaMovimiento < DATE_ADD(?, INTERVAL 1 DAY)";
        $tiposParametros .= "s";
        $parametros[] = $hasta . " 00:00:00";
    }

    $sql = "SELECT
                m.idMovimiento,
                m.idProducto,
                p.nombre AS producto,
                m.tipoMovimiento,
                m.cantidad,
                m.stockAnterior,
                m.stockNuevo,
                m.motivo,
                m.observacion,
                m.fechaMovimiento,
                m.idUsuario,
                u.nombre AS nombreUsuario,
                u.apellido AS apellidoUsuario,
                m.moduloOrigen
            FROM movimiento_inventario AS m
            LEFT JOIN producto AS p
                ON p.idProducto = m.idProducto
            LEFT JOIN usuarios AS u
                ON u.id = m.idUsuario";

    if (!empty($condiciones)) {
        $sql .= " WHERE " . implode(" AND ", $condiciones);
    }

    $sql .= " ORDER BY m.fechaMovimiento DESC, m.idMovimiento DESC";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!empty($parametros)) {
        $argumentos = [$stmt, $tiposParametros];

        foreach ($parametros as $indice => &$parametro) {
            $argumentos[] = &$parametro;
        }

        call_user_func_array(
            "mysqli_stmt_bind_param",
            $argumentos
        );

        unset($parametro);
    }

    mysqli_stmt_execute($stmt);

    $resultado = mysqli_stmt_get_result($stmt);
    $movimientos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

    mysqli_stmt_close($stmt);

} catch (Throwable $e) {
    error_log("Error en historial de movimientos: " . $e->getMessage());
    http_response_code(500);
    exit("No se pudo consultar el historial. Revisa el registro de errores de PHP.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Historial de movimientos | Sabora</title>

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
            width: min(1500px, 95%);
            margin: 30px auto;
        }

        .encabezado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 30px;
        }

        .subtitulo {
            color: #666;
            margin: 0;
        }

        .boton {
            display: inline-block;
            border: 1px solid #ff6b35;
            border-radius: 8px;
            padding: 11px 16px;
            background: #ff6b35;
            color: white;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }

        .boton:hover {
            background: #e85a2a;
        }

        .boton-secundario {
            background: white;
            color: #ff6b35;
        }

        .tarjeta {
            background: white;
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 22px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, .05);
        }

        .tarjeta h2 {
            margin-top: 0;
            font-size: 21px;
        }

        .filtros {
            display: grid;
            grid-template-columns: repeat(5, minmax(140px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .campo label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 7px;
        }

        .campo select,
        .campo input {
            width: 100%;
            min-width: 0;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 7px;
            background: white;
            font-size: 14px;
        }

        .acciones {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 18px;
        }

        .resumen {
            margin-bottom: 15px;
            color: #555;
        }

        .tabla-contenedor {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 1250px;
            border-collapse: collapse;
            font-size: 13px;
        }

        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #fff1e8;
            color: #333;
            white-space: nowrap;
        }

        tbody tr:hover {
            background: #fffaf6;
        }

        .etiqueta {
            display: inline-block;
            border-radius: 20px;
            padding: 5px 9px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .entrada {
            background: #dcfce7;
            color: #166534;
        }

        .salida {
            background: #fee2e2;
            color: #991b1b;
        }

        .ajuste {
            background: #fef3c7;
            color: #92400e;
        }

        .sin-datos {
            text-align: center;
            padding: 30px;
            color: #777;
        }

        .nota {
            color: #777;
            font-size: 12px;
            margin-top: 15px;
        }

        @media (max-width: 1000px) {
            .filtros {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 550px) {
            .contenedor {
                width: 94%;
                margin: 18px auto;
            }

            h1 {
                font-size: 24px;
            }

            .filtros {
                grid-template-columns: 1fr;
            }

            .tarjeta {
                padding: 15px;
            }
        }

        @media print {
            body {
                background: white;
            }

            .filtros-tarjeta,
            .boton,
            .acciones,
            .nota {
                display: none !important;
            }

            .contenedor {
                width: 100%;
                margin: 0;
            }

            .tarjeta {
                box-shadow: none;
                padding: 0;
            }

            .tabla-contenedor {
                overflow: visible;
            }

            table {
                min-width: 0;
                font-size: 9px;
            }

            th, td {
                padding: 5px;
            }
        }
    </style>
</head>

<body>
<div class="contenedor">

    <header class="encabezado">
        <div>
            <h1>Historial de movimientos</h1>
            <p class="subtitulo">
                Sabora · Consulta y trazabilidad del inventario
            </p>
        </div>

        <a href="Inventario.php" class="boton">Volver a Inventario</a>
    </header>

    <section class="tarjeta filtros-tarjeta">
        <h2>Filtrar movimientos</h2>

        <form method="GET" action="historial_movimientos.php">
            <div class="filtros">

                <div class="campo">
                    <label for="producto">Producto</label>
                    <select name="producto" id="producto">
                        <option value="">Todos los productos</option>

                        <?php foreach ($productos as $p): ?>
                            <option
                                value="<?= (int) $p["idProducto"] ?>"
                                <?= $idProducto === (int) $p["idProducto"] ? "selected" : "" ?>
                            >
                                <?= h($p["nombre"]) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="tipo">Tipo de movimiento</label>
                    <select name="tipo" id="tipo">
                        <option value="">Todos los tipos</option>

                        <?php foreach ($tiposValidos as $opcion): ?>
                            <option
                                value="<?= h($opcion) ?>"
                                <?= $tipo === $opcion ? "selected" : "" ?>
                            >
                                <?= h($opcion) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="usuario">Usuario responsable</label>
                    <select name="usuario" id="usuario">
                        <option value="">Todos los usuarios</option>

                        <?php foreach ($usuarios as $u): ?>
                            <option
                                value="<?= (int) $u["id"] ?>"
                                <?= $idUsuario === (int) $u["id"] ? "selected" : "" ?>
                            >
                                <?= h(trim($u["nombre"] . " " . $u["apellido"])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label for="desde">Desde</label>
                    <input
                        type="date"
                        name="desde"
                        id="desde"
                        value="<?= h($desde) ?>"
                    >
                </div>

                <div class="campo">
                    <label for="hasta">Hasta</label>
                    <input
                        type="date"
                        name="hasta"
                        id="hasta"
                        value="<?= h($hasta) ?>"
                    >
                </div>

            </div>

            <div class="acciones">
                <button type="submit" class="boton">
                    Aplicar filtros
                </button>

                <a
                    href="historial_movimientos.php"
                    class="boton boton-secundario"
                >
                    Limpiar filtros
                </a>

                <button
                    type="button"
                    class="boton boton-secundario"
                    onclick="window.print()"
                >
                    Imprimir historial
                </button>
            </div>
        </form>
    </section>

    <section class="tarjeta">
        <h2>Movimientos registrados</h2>

        <p class="resumen">
            Registros encontrados:
            <strong><?= count($movimientos) ?></strong>
        </p>

        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha y hora</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Stock anterior</th>
                        <th>Stock nuevo</th>
                        <th>Usuario</th>
                        <th>Módulo</th>
                        <th>Motivo</th>
                        <th>Observación</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (empty($movimientos)): ?>
                    <tr>
                        <td colspan="11" class="sin-datos">
                            No se encontraron movimientos con los filtros seleccionados.
                        </td>
                    </tr>
                <?php else: ?>

                    <?php foreach ($movimientos as $m): ?>
                        <?php
                        $claseTipo = strtolower($m["tipoMovimiento"]);

                        $nombreUsuario = trim(
                            ($m["nombreUsuario"] ?? "") . " " .
                            ($m["apellidoUsuario"] ?? "")
                        );

                        if ($nombreUsuario === "") {
                            $nombreUsuario = "No identificado";
                        }
                        ?>

                        <tr>
                            <td><?= (int) $m["idMovimiento"] ?></td>

                            <td><?= h($m["fechaMovimiento"]) ?></td>

                            <td>
                                <?= h($m["producto"] ?? "Producto no disponible") ?>
                                <br>
                                <small>ID: <?= (int) $m["idProducto"] ?></small>
                            </td>

                            <td>
                                <span class="etiqueta <?= h($claseTipo) ?>">
                                    <?= h($m["tipoMovimiento"]) ?>
                                </span>
                            </td>

                            <td><?= (int) $m["cantidad"] ?></td>

                            <td><?= (int) $m["stockAnterior"] ?></td>

                            <td><?= (int) $m["stockNuevo"] ?></td>

                            <td>
                                <?= h($nombreUsuario) ?>

                                <?php if ($m["idUsuario"] !== null): ?>
                                    <br>
                                    <small>ID: <?= (int) $m["idUsuario"] ?></small>
                                <?php endif; ?>
                            </td>

                            <td><?= h($m["moduloOrigen"] ?? "No registrado") ?></td>

                            <td><?= h($m["motivo"]) ?></td>

                            <td><?= h($m["observacion"] ?? "") ?></td>
                        </tr>
                    <?php endforeach; ?>

                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="nota">
            Los registros son de consulta. Los movimientos históricos no se
            modifican desde esta pantalla. "No identificado" significa que
            no se encontró un usuario asociado al movimiento.
        </p>
    </section>

</div>
</body>
</html>
