
<?php
session_start();
require_once __DIR__ . '/conexion.php';

// Validar acceso de administrador
$rol = strtolower(trim($_SESSION['rol'] ?? ''));

if ($rol !== 'administrador') {
    http_response_code(403);
    exit('Acceso denegado. Inicia sesión como administrador.');
}

// Token de seguridad
if (empty($_SESSION['token_inventario'])) {
    $_SESSION['token_inventario'] = bin2hex(random_bytes(32));
}

// Mensajes de resultado
$mensajeInventario = $_SESSION['inventario_mensaje'] ?? '';
$tipoMensaje = $_SESSION['inventario_tipo'] ?? 'error';

unset(
    $_SESSION['inventario_mensaje'],
    $_SESSION['inventario_tipo']
);

// Escapar datos para HTML
function e($valor): string {
    return htmlspecialchars(
        (string)($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

// Consultar productos
$sql = "SELECT
            idProducto,
            nombre,
            descripcion,
            categoria,
            precio,
            stock,
            fechaVencimiento,
            almacenamiento,
            estado
        FROM producto
        ORDER BY nombre ASC, descripcion ASC, idProducto ASC";

$resultado = $conexion->query($sql);

$productos = [];
$stockBajo = 0;
$sinExistencias = 0;
$vencidos = 0;
$hoy = date('Y-m-d');

while ($fila = $resultado->fetch_assoc()) {
    $productos[] = $fila;
    $stock = (int)$fila['stock'];

    if ($stock === 0) {
        $sinExistencias++;
    } elseif ($stock <= 5) {
        $stockBajo++;
    }

    if (
        strcasecmp((string)$fila['estado'], 'Activo') === 0 &&
        !empty($fila['fechaVencimiento']) &&
        $fila['fechaVencimiento'] < $hoy
    ) {
        $vencidos++;
    }
}

$totalProductos = count($productos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Inventario | Sabora</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            background: #f5ede6;
            color: #333;
            font-family: Arial, sans-serif;
        }

        .contenedor {
            max-width: 1250px;
            margin: auto;
        }

        .encabezado {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 24px;
        }

        h1 {
            margin: 0 0 8px;
        }

        .subtitulo {
            color: #666;
            margin: 0;
        }

        /* Botones del encabezado */
        .acciones-encabezado {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 10px;
        }

        .volver,
        .historial {
            display: inline-block;
            padding: 11px 16px;
            border: 1px solid #ff6b35;
            border-radius: 8px;
            background: #ff6b35;
            color: #fff;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .volver:hover,
        .historial:hover {
            background: #e85a2a;
            border-color: #e85a2a;
        }

        /* Tarjetas de resumen */
        .resumen {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .tarjeta {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px #0000000d;
        }

        .tarjeta p {
            margin: 0 0 10px;
            color: #666;
        }

        .numero {
            font-size: 28px;
            font-weight: bold;
        }

        .panel,
        .tabla-contenedor {
            background: #fff;
            padding: 20px;
            margin-bottom: 24px;
            border-radius: 12px;
            box-shadow: 0 2px 8px #0000000d;
        }

        /* Formulario de movimientos */
        .titulo-formulario {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .titulo-formulario h2 {
            margin: 0;
        }

        .boton-contraer {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            border: 1px solid #ff6b35;
            border-radius: 8px;
            background: #fff;
            color: #e85a2a;
            font-weight: bold;
            cursor: pointer;
        }

        .boton-contraer:hover {
            background: #fff3eb;
        }

        #contenidoFormulario {
            margin-top: 20px;
        }

        #contenidoFormulario[hidden] {
            display: none;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 18px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .campo-completo {
            grid-column: 1 / -1;
        }

        label {
            font-weight: bold;
            font-size: 14px;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font: inherit;
            background: #fff;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: 2px solid #ffb18f;
            border-color: #ff6b35;
        }

        .ayuda {
            color: #666;
            font-size: 13px;
            margin: 0;
        }

        .boton {
            display: inline-block;
            border: 0;
            padding: 12px 18px;
            background: #ff6b35;
            border-radius: 8px;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
        }

        .boton:hover {
            background: #e85a2a;
        }

        /* Mensajes */
        .mensaje {
            padding: 12px 14px;
            margin: 16px 0;
            border-radius: 8px;
        }

        .mensaje-exito {
            background: #dcfce7;
            color: #166534;
        }

        .mensaje-error {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Tabla de existencias */
        .tabla-contenedor {
            overflow-x: auto;
        }

        .filtros {
            margin-bottom: 18px;
        }

        .filtros input {
            max-width: 420px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th,
        td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #fff3eb;
            color: #444;
        }

        .estado {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .normal {
            background: #dcfce7;
            color: #166534;
        }

        .bajo {
            background: #fef3c7;
            color: #92400e;
        }

        .agotado,
        .vencido {
            background: #fee2e2;
            color: #991b1b;
        }

        .inactivo {
            background: #e5e7eb;
            color: #374151;
        }

        .sin-datos {
            padding: 24px;
            text-align: center;
            color: #666;
        }

        /* Adaptación a móviles */
        @media (max-width: 800px) {
            body {
                padding: 14px;
            }

            .resumen {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
            }

            .tarjeta {
                padding: 14px;
            }

            .numero {
                font-size: 24px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .campo-completo {
                grid-column: auto;
            }

            .acciones-encabezado {
                justify-content: flex-start;
                width: 100%;
            }
        }

        @media (max-width: 380px) {
            .resumen {
                grid-template-columns: 1fr;
            }

            .historial,
            .volver {
                width: 100%;
            }

            .acciones-encabezado {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>
</head>

<body>
<div class="contenedor">

    <!-- Encabezado con acceso al historial -->
    <header class="encabezado">
        <div>
            <h1>Inventario Sabora</h1>
            <p class="subtitulo">
                Consulta y control de existencias de productos
            </p>
        </div>

        <div class="acciones-encabezado">
            <a
                class="historial"
                href="historial_movimientos.php"
            >
                Historial de movimientos
            </a>

            <a
                class="volver"
               href="Administrador.php"
            >
                Volver
            </a>
        </div>
    </header>

    <!-- Resumen del inventario -->
    <section class="resumen">
        <article class="tarjeta">
            <p>Productos registrados</p>
            <div class="numero"><?= $totalProductos ?></div>
        </article>

        <article class="tarjeta">
            <p>Stock bajo (1 a 5)</p>
            <div class="numero"><?= $stockBajo ?></div>
        </article>

        <article class="tarjeta">
            <p>Sin existencias</p>
            <div class="numero"><?= $sinExistencias ?></div>
        </article>

        <article class="tarjeta">
            <p>Productos vencidos activos</p>
            <div class="numero"><?= $vencidos ?></div>
        </article>
    </section>

    <!-- Registro de movimientos -->
    <section class="panel">
        <div class="titulo-formulario">
            <h2>Registrar movimiento de inventario</h2>

            <button
                type="button"
                id="btnContraer"
                class="boton-contraer"
                aria-expanded="false"
                aria-controls="contenidoFormulario"
            >
                <span id="textoBoton">Mostrar formulario</span>
                <span id="iconoBoton" aria-hidden="true">＋</span>
            </button>
        </div>

        <?php if ($mensajeInventario !== ''): ?>
            <div
                class="mensaje <?= $tipoMensaje === 'exito' ? 'mensaje-exito' : 'mensaje-error' ?>"
                role="status"
            >
                <?= e($mensajeInventario) ?>
            </div>
        <?php endif; ?>

        <div id="contenidoFormulario" hidden>
            <form
                action="registrar_movimiento.php"
                method="POST"
                id="formMovimiento"
            >
                <input
                    type="hidden"
                    name="token_csrf"
                    value="<?= e($_SESSION['token_inventario']) ?>"
                >

                <div class="form-grid">

                    <div class="campo campo-completo">
                        <label for="idProducto">Producto</label>

                        <select name="idProducto" id="idProducto" required>
                            <option value="">Selecciona un producto</option>

                            <?php foreach ($productos as $producto): ?>
                                <?php if (strcasecmp((string)$producto['estado'], 'Activo') === 0): ?>
                                    <option value="<?= (int)$producto['idProducto'] ?>">
                                        <?= e($producto['nombre']) ?>
                                        <?= !empty($producto['descripcion']) ? ' — ' . e($producto['descripcion']) : '' ?>
                                        — <?= e($producto['categoria']) ?>
                                        — Stock: <?= (int)$producto['stock'] ?>
                                        — ID: <?= (int)$producto['idProducto'] ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="tipoMovimiento">Tipo de movimiento</label>

                        <select
                            name="tipoMovimiento"
                            id="tipoMovimiento"
                            required
                        >
                            <option value="ENTRADA">Entrada</option>
                            <option value="SALIDA">Salida</option>
                            <option value="AJUSTE">Ajuste de stock físico</option>
                        </select>
                    </div>

                    <div class="campo">
                        <label for="cantidad" id="etiquetaCantidad">
                            Cantidad de unidades recibidas
                        </label>

                        <input
                            type="number"
                            name="cantidad"
                            id="cantidad"
                            min="1"
                            step="1"
                            required
                        >

                        <p class="ayuda" id="ayudaMovimiento">
                            En entradas, indica las unidades que se agregan al stock.
                        </p>
                    </div>

                    <div class="campo campo-completo">
                        <label for="motivo">Motivo</label>

                        <input
                            type="text"
                            name="motivo"
                            id="motivo"
                            maxlength="255"
                            required
                            placeholder="Ej.: compra, merma o conteo físico"
                        >
                    </div>

                    <div class="campo campo-completo">
                        <label for="observacion">Observación (opcional)</label>

                        <textarea
                            name="observacion"
                            id="observacion"
                            maxlength="5000"
                            rows="3"
                            placeholder="Información adicional del movimiento"
                        ></textarea>
                    </div>

                    <div class="campo campo-completo">
                        <button type="submit" class="boton">
                            Registrar movimiento
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </section>

    <!-- Existencias actuales -->
    <section class="tabla-contenedor">
        <h2>Existencias actuales</h2>

        <div class="filtros">
            <input
                type="search"
                id="buscar"
                placeholder="Buscar por producto o categoría..."
                aria-label="Buscar productos"
            >
        </div>

        <table id="tablaInventario">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Producto</th>
                    <th>Descripción</th>
                    <th>Categoría</th>
                    <th>Stock</th>
                    <th>Vencimiento</th>
                    <th>Almacenamiento</th>
                    <th>Estado</th>
                </tr>
            </thead>

            <tbody>
            <?php if (empty($productos)): ?>
                <tr>
                    <td colspan="8" class="sin-datos">
                        No hay productos registrados.
                    </td>
                </tr>
            <?php else: ?>

                <?php foreach ($productos as $producto): ?>
                    <?php
                    $stock = (int)$producto['stock'];
                    $fecha = $producto['fechaVencimiento'];

                    $activo = strcasecmp(
                        (string)$producto['estado'],
                        'Activo'
                    ) === 0;

                    $estaVencido = $activo
                        && !empty($fecha)
                        && $fecha < $hoy;

                    if (!$activo) {
                        $clase = 'inactivo';
                        $textoEstado = 'Inactivo';
                    } elseif ($stock === 0) {
                        $clase = 'agotado';
                        $textoEstado = 'Agotado';
                    } elseif ($stock <= 5) {
                        $clase = 'bajo';
                        $textoEstado = 'Stock bajo';
                    } else {
                        $clase = 'normal';
                        $textoEstado = 'Disponible';
                    }
                    ?>

                    <tr>
                        <td><?= (int)$producto['idProducto'] ?></td>
                        <td><?= e($producto['nombre']) ?></td>
                        <td><?= e($producto['descripcion'] ?: '—') ?></td>
                        <td><?= e($producto['categoria']) ?></td>
                        <td><?= $stock ?></td>

                        <td>
                            <?= e($fecha ?: 'No registrada') ?>

                            <?php if ($estaVencido): ?>
                                <span class="estado vencido">Vencido</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= e($producto['almacenamiento'] ?: 'No registrado') ?>
                        </td>

                        <td>
                            <span class="estado <?= e($clase) ?>">
                                <?= e($textoEstado) ?>
                            </span>
                        </td>
                    </tr>

                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

</div>

<script>
// Buscar productos por nombre, descripción o categoría.
const campoBuscar = document.getElementById('buscar');

campoBuscar.addEventListener('input', function () {
    const busqueda = this.value.toLocaleLowerCase('es');

    document.querySelectorAll('#tablaInventario tbody tr').forEach(function (fila) {
        fila.hidden = !fila.textContent
            .toLocaleLowerCase('es')
            .includes(busqueda);
    });
});

// Cambiar instrucciones según el movimiento.
const tipoMovimiento = document.getElementById('tipoMovimiento');
const cantidad = document.getElementById('cantidad');
const etiquetaCantidad = document.getElementById('etiquetaCantidad');
const ayudaMovimiento = document.getElementById('ayudaMovimiento');

function actualizarAyudaMovimiento() {
    cantidad.value = '';

    if (tipoMovimiento.value === 'AJUSTE') {
        etiquetaCantidad.textContent = 'Stock físico final contado';
        cantidad.min = '0';
        ayudaMovimiento.textContent =
            'Indica el total de unidades que hay físicamente. Puede ser cero.';
    } else if (tipoMovimiento.value === 'ENTRADA') {
        etiquetaCantidad.textContent = 'Cantidad de unidades recibidas';
        cantidad.min = '1';
        ayudaMovimiento.textContent =
            'Estas unidades se sumarán al stock actual.';
    } else {
        etiquetaCantidad.textContent = 'Cantidad de unidades retiradas';
        cantidad.min = '1';
        ayudaMovimiento.textContent =
            'Estas unidades se restarán del stock actual.';
    }
}

tipoMovimiento.addEventListener('change', actualizarAyudaMovimiento);

// Contraer y desplegar el formulario.
const btnContraer = document.getElementById('btnContraer');
const contenidoFormulario = document.getElementById('contenidoFormulario');
const textoBoton = document.getElementById('textoBoton');
const iconoBoton = document.getElementById('iconoBoton');

btnContraer.addEventListener('click', function () {
    const mostrar = contenidoFormulario.hidden;

    contenidoFormulario.hidden = !mostrar;

    btnContraer.setAttribute('aria-expanded', String(mostrar));

    textoBoton.textContent = mostrar
        ? 'Ocultar formulario'
        : 'Mostrar formulario';

    iconoBoton.textContent = mostrar ? '−' : '＋';
});
</script>

</body>
