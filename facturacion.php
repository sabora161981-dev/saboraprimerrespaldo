<?php
session_start();

/*
|--------------------------------------------------------------------------
| FACTURACIÓN - MÓDULO CAJERO
|--------------------------------------------------------------------------
| Las facturas NO se duplican.
| Este módulo consulta los mismos registros creados por Pedidos.php.
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['correo'])) {
    header("Location: Cajero.html");
    exit();
}

if (strtolower($_SESSION['rol'] ?? '') !== 'cajero') {
    header("Location: Cliente.html");
    exit();
}

require_once "conexion.php";

/*
|--------------------------------------------------------------------------
| CONFIGURACIÓN
|--------------------------------------------------------------------------
| Tiempo de vida de una factura en estado "generada".
| Después de este tiempo, si no fue gestionada, pasa a "anulada".
|--------------------------------------------------------------------------
*/
$minutosExpiracion = 30;

/*
|--------------------------------------------------------------------------
| ANULACIÓN AUTOMÁTICA POR VENCIMIENTO
|--------------------------------------------------------------------------
| Se ejecuta al entrar/recargar el módulo.
|--------------------------------------------------------------------------
*/
$sqlVencidas = "
    UPDATE factura
    SET estado = 'anulada'
    WHERE estado = 'generada'
      AND fecha < DATE_SUB(NOW(), INTERVAL ? MINUTE)
";

$stmtVencidas = mysqli_prepare($conexion, $sqlVencidas);
if ($stmtVencidas) {
    mysqli_stmt_bind_param($stmtVencidas, "i", $minutosExpiracion);
    mysqli_stmt_execute($stmtVencidas);
    mysqli_stmt_close($stmtVencidas);
}

/*
|--------------------------------------------------------------------------
| ACCIONES DEL CAJERO
|--------------------------------------------------------------------------
*/
$mensaje = '';
$tipoMensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $accion = $_POST['accion'] ?? '';
    $idFactura = (int)($_POST['id_factura'] ?? 0);

    if ($idFactura <= 0) {
        $mensaje = 'Factura no válida.';
        $tipoMensaje = 'error';
    } elseif ($accion === 'pagar') {

        $sql = "
            UPDATE factura
            SET estado = 'pagada'
            WHERE id_factura = ?
            AND estado = 'generada'
        ";

        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $idFactura);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) === 1) {
                $mensaje = 'El pago fue confirmado y la factura quedó en estado PAGADA.';
                $tipoMensaje = 'ok';
            } else {
                $mensaje = 'La factura no pudo pasar a PAGADA. Puede estar vencida o ya gestionada.';
                $tipoMensaje = 'error';
            }

            mysqli_stmt_close($stmt);
        } else {
            $mensaje = 'No fue posible actualizar la factura.';
            $tipoMensaje = 'error';
        }

    } elseif ($accion === 'anular') {

        $sql = "
            UPDATE factura
            SET estado = 'anulada'
            WHERE id_factura = ?
              AND estado = 'generada'
        ";

        $stmt = mysqli_prepare($conexion, $sql);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $idFactura);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) === 1) {
                $mensaje = 'La factura fue anulada correctamente.';
                $tipoMensaje = 'ok';
            } else {
                $mensaje = 'La factura no pudo ser anulada porque ya no está en estado GENERADA.';
                $tipoMensaje = 'error';
            }

            mysqli_stmt_close($stmt);
        } else {
            $mensaje = 'No fue posible anular la factura.';
            $tipoMensaje = 'error';
        }
    }
}

/*
|--------------------------------------------------------------------------
| CONSULTA DE FACTURAS
|--------------------------------------------------------------------------
| Se muestran todas las facturas, ordenadas de la más reciente.
|--------------------------------------------------------------------------
*/
$sqlFacturas = "
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
        u.nombre,
        u.apellido,
        u.correo
    FROM factura f
    LEFT JOIN usuarios u
        ON u.id = f.id_usuario
    ORDER BY f.fecha DESC, f.id_factura DESC
";

$resultadoFacturas = mysqli_query($conexion, $sqlFacturas);

if (!$resultadoFacturas) {
    die("Error al consultar las facturas: " . mysqli_error($conexion));
}

/*
|--------------------------------------------------------------------------
| FACTURA SELECCIONADA
|--------------------------------------------------------------------------
*/
$idSeleccionada = (int)($_GET['id_factura'] ?? 0);
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
            u.nombre,
            u.apellido,
            u.correo
        FROM factura f
        LEFT JOIN usuarios u
            ON u.id = f.id_usuario
        WHERE f.id_factura = ?
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conexion, $sqlFactura);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $idSeleccionada);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $facturaSeleccionada = mysqli_fetch_assoc($res) ?: null;
        mysqli_stmt_close($stmt);
    }

    if ($facturaSeleccionada) {

        $sqlDetalle = "
            SELECT
                df.id_detalle,
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

        $stmt = mysqli_prepare($conexion, $sqlDetalle);

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $idSeleccionada);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);

            while ($fila = mysqli_fetch_assoc($res)) {
                $detallesSeleccionados[] = $fila;
            }

            mysqli_stmt_close($stmt);
        }
    }
}

function e($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function estadoClase($estado): string {
    $estado = strtolower((string)$estado);
    if ($estado === 'pagada') return 'pagada';
    if ($estado === 'anulada') return 'anulada';
    return 'generada';
}

function estadoTexto($estado): string {
    return strtoupper((string)$estado);
}

function urlValidacion($token): string {
    if (!$token) return '#';

    $protocolo = 'http://';

    // IP del computador donde está ejecutándose XAMPP/SABORA
    // Permite que el QR pueda abrirse desde otro dispositivo
    // conectado a la misma red local.
    $host = '192.168.101.81';

    $rutaBase = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    return $protocolo
        . $host
        . $rutaBase
        . '/validar_factura.php?token='
        . rawurlencode($token);
}        

?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Facturación - Saborá</title>

<style>
 .btn-volver {
    display: inline-flex;
    background: #333333;
    color: #fff;
    padding: 10px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    transition: background .2s;
}

.btn-volver:hover {
    background: #555;
    color: #fff;
} 
.titulo-encabezado {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.titulo-encabezado h2 {
    margin: 0;
}
.contenedor-volver {
    display: flex;
    justify-content: flex-end;
    margin: -5px 0 20px;
}
.btn-volver {
    display: inline-block;
    background: #ff6b35;
    color: #fff;
    padding: 10px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    white-space: nowrap;
}

.btn-volver:hover {
    background: #ff6b35;
    color: #fff;
}

@media (max-width: 600px) {
    .titulo-encabezado {
        align-items: flex-start;
    }
}


*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',sans-serif}
body{background:#efe7e2;color:#222}
header{background:#ff6b35;color:#fff;padding:18px 5%;display:flex;justify-content:space-between;align-items:center;gap:20px}
header h1{font-size:30px}
.usuario{text-align:right;font-size:14px;line-height:21px}
.container{width:92%;max-width:1350px;margin:28px auto}
.titulo{background:#fff;border-radius:14px;padding:24px;box-shadow:0 5px 15px rgba(0,0,0,.1);margin-bottom:20px}
.titulo h2{color:#ff6b35;margin-bottom:8px}
.titulo p{color:#666}
.mensaje{padding:13px 16px;border-radius:9px;margin-bottom:18px;font-weight:600}
.mensaje.ok{background:#e8f7ec;color:#17652b;border:1px solid #a9ddb5}
.mensaje.error{background:#fdecec;color:#9b2020;border:1px solid #efb2b2}
.panel{background:#fff;border-radius:14px;box-shadow:0 5px 15px rgba(0,0,0,.1);overflow:hidden}
.tabla-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;min-width:900px}
th,td{padding:13px 12px;border-bottom:1px solid #eee;text-align:left;font-size:14px}
th{background:#fff4ee;color:#333}
.estado{display:inline-block;padding:6px 9px;border-radius:999px;font-size:12px;font-weight:800}
.estado.generada{background:#fff0cf;color:#8a5b00}
.estado.pagada{background:#e5f7e9;color:#17652b}
.estado.anulada{background:#f8e3e3;color:#982727}
.acciones{display:flex;gap:7px;flex-wrap:wrap}
.btn{border:0;border-radius:7px;padding:8px 11px;text-decoration:none;cursor:pointer;font-weight:700;font-size:12px;display:inline-block}
.btn.ver{background:#ff6b35;color:#fff}
.btn.pagar{background:#198754;color:#fff}
.btn.anular{background:#b02a37;color:#fff}
.btn:hover{filter:brightness(.95)}
.form-inline{display:inline}
.detalle{margin-top:22px;background:#fff;border-radius:14px;box-shadow:0 5px 15px rgba(0,0,0,.1);padding:25px}
.detalle h2{color:#ff6b35;margin-bottom:16px}
.factura-head{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;border-bottom:2px solid #ff6b35;padding-bottom:15px}
.factura-head h3{font-size:14px;color:#777;margin-bottom:5px}
.factura-head strong{font-size:20px}
.datos{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin:18px 0}
.bloque{border:1px solid #eee;border-radius:9px;padding:15px}
.bloque h3{color:#ff6b35;font-size:15px;margin-bottom:10px}
.bloque p{margin:5px 0;font-size:14px}
.productos{overflow-x:auto}
.productos table{min-width:600px}
.total{text-align:right;margin-top:18px;font-size:20px;font-weight:800}
.total span{color:#ff6b35}
.qr-area{display:grid;grid-template-columns:130px 1fr;gap:20px;align-items:center;border-top:1px solid #ddd;margin-top:20px;padding-top:20px}
.qr{width:120px;height:120px;border:1px solid #ddd;border-radius:8px;display:flex;align-items:center;justify-content:center}
.qr-info{font-size:13px;color:#555;word-break:break-word}
.qr-info a{color:#ff6b35}
footer{text-align:center;color:#666;margin:35px}
@media(max-width:800px){
 header{align-items:flex-start;flex-direction:column}
 .usuario{text-align:left}
 .container{width:95%}
 .factura-head,.datos{grid-template-columns:1fr}
 .qr-area{grid-template-columns:1fr}
}
@media print{
 body{background:#fff}
 header,.titulo,.mensaje,.panel,footer,.acciones-print{display:none!important}
 .detalle{box-shadow:none;margin:0;padding:0}
}
</style>
</head>

<body>

<header>
    <h1>SABORA</h1>
    <div class="usuario">
        <strong><?= e($_SESSION['nombre'] ?? '') . ' ' . e($_SESSION['apellido'] ?? '') ?></strong><br>
        <?= e($_SESSION['correo'] ?? '') ?><br>
        Rol: <strong><?= e(ucfirst($_SESSION['rol'] ?? '')) ?></strong>
    </div>
</header>

<div class="container">

    
<section class="titulo">
    <div class="titulo-encabezado">
        <h2>🧾 Facturación</h2>
    </div>

    <p>
        Facturas generadas por los clientes y disponibles para gestión del cajero.
        No se crean facturas duplicadas: esta pantalla consulta la misma factura
        almacenada en el sistema.
    </p>
</section>

<div class="contenedor-volver">
    <a href="Cajero.php"
       class="btn-volver"
       aria-label="Volver al panel del cajero"
       title="Volver al panel del cajero">
        <span class="flecha-volver">←</span>
        <span class="texto-volver">Volver al panel del cajero</span>
    </a>
</div>


    <?php if ($mensaje !== ''): ?>
        <div class="mensaje <?= e($tipoMensaje) ?>">
            <?= e($mensaje) ?>
        </div>
    <?php endif; ?>

    <section class="panel">
        <div class="tabla-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Medio de pago</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (mysqli_num_rows($resultadoFacturas) === 0): ?>
                    <tr>
                        <td colspan="7">No hay facturas registradas.</td>
                    </tr>
                <?php else: ?>
                    <?php while ($factura = mysqli_fetch_assoc($resultadoFacturas)): ?>
                        <?php
                            $nombreCliente = trim(($factura['nombre'] ?? '') . ' ' . ($factura['apellido'] ?? ''));
                            if ($nombreCliente === '') {
                                $nombreCliente = $factura['correo'] ?? 'Cliente';
                            }
                        ?>
                        <tr>
                            <td><strong><?= e(str_pad((string)$factura['numero_factura'], 7, '0', STR_PAD_LEFT)) ?></strong></td>
                            <td><?= e($nombreCliente) ?></td>
                            <td><?= e($factura['fecha']) ?></td>
                            <td>$<?= number_format((float)$factura['total'], 0, ',', '.') ?></td>
                            <td><?= e(strtoupper($factura['metodo_pago'])) ?></td>
                            <td>
                                <span class="estado <?= e(estadoClase($factura['estado'])) ?>">
                                    <?= e(estadoTexto($factura['estado'])) ?>
                                </span>
                            </td>
                            <td>
                                <div class="acciones">
                                    <a class="btn ver" href="facturacion.php?id_factura=<?= (int)$factura['id_factura'] ?>">
                                        Ver factura
                                    </a>

                                    <?php if (strtolower($factura['estado']) === 'generada'): ?>
                                        <form class="form-inline" method="post" onsubmit="return confirm('¿Confirmar el pago de esta factura?');">
                                            <input type="hidden" name="accion" value="pagar">
                                            <input type="hidden" name="id_factura" value="<?= (int)$factura['id_factura'] ?>">
                                            <button class="btn pagar" type="submit">Confirmar pago</button>
                                        </form>

                                        <form class="form-inline" method="post" onsubmit="return confirm('¿Anular esta factura?');">
                                            <input type="hidden" name="accion" value="anular">
                                            <input type="hidden" name="id_factura" value="<?= (int)$factura['id_factura'] ?>">
                                            <button class="btn anular" type="submit">Anular</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($facturaSeleccionada): ?>
        <?php
            $nombreSeleccionado = trim(
                ($facturaSeleccionada['nombre'] ?? '') . ' ' .
                ($facturaSeleccionada['apellido'] ?? '')
            );

            if ($nombreSeleccionado === '') {
                $nombreSeleccionado = $facturaSeleccionada['correo'] ?? 'Cliente';
            }

            $urlQR = urlValidacion($facturaSeleccionada['token_factura']);
        ?>

        <section class="detalle" id="factura">
            <div class="factura-head">
                <div>
                    <h3>FACTURA</h3>
                    <strong><?= e(str_pad((string)$facturaSeleccionada['numero_factura'], 7, '0', STR_PAD_LEFT)) ?></strong>
                </div>
                <div>
                    <h3>ESTADO</h3>
                    <span class="estado <?= e(estadoClase($facturaSeleccionada['estado'])) ?>">
                        <?= e(estadoTexto($facturaSeleccionada['estado'])) ?>
                    </span>
                </div>
                <div>
                    <h3>FECHA</h3>
                    <strong><?= e($facturaSeleccionada['fecha']) ?></strong>
                </div>
            </div>

            <div class="datos">
                <div class="bloque">
                    <h3>Cliente</h3>
                    <p><strong>Nombre:</strong> <?= e($nombreSeleccionado) ?></p>
                    <p><strong>Correo:</strong> <?= e($facturaSeleccionada['correo'] ?? '—') ?></p>
                </div>

                <div class="bloque">
                    <h3>Pago</h3>
                    <p><strong>Método:</strong> <?= e(strtoupper($facturaSeleccionada['metodo_pago'])) ?></p>
                    <p><strong>Estado:</strong> <?= e(estadoTexto($facturaSeleccionada['estado'])) ?></p>
                </div>
            </div>

            <div class="productos">
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($detallesSeleccionados as $detalle): ?>
                        <tr>
                            <td><?= e($detalle['nombre'] ?? 'Producto') ?></td>
                            <td><?= (int)$detalle['cantidad'] ?></td>
                            <td>$<?= number_format((float)$detalle['precio_unitario'], 0, ',', '.') ?></td>
                            <td>$<?= number_format((float)$detalle['subtotal'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="total">
                Total: <span>$<?= number_format((float)$facturaSeleccionada['total'], 0, ',', '.') ?></span>
            </div>

            <div class="qr-area">
                <div class="qr" id="qrFactura"></div>
                <div class="qr-info">
                    <p><strong>Verificación de factura</strong></p>
                    <p>El código QR utiliza el mismo token de la factura original.</p>
                    <p style="margin-top:7px;">
                        <a href="<?= e($urlQR) ?>" target="_blank" rel="noopener">
                            Abrir validación
                        </a>
                    </p>
                </div>
            </div>

            <div class="acciones acciones-print" style="margin-top:20px">
                <button class="btn ver" type="button" onclick="window.print()">🖨 Imprimir factura</button>

                <?php if (strtolower($facturaSeleccionada['estado']) === 'generada'): ?>
                    <form class="form-inline" method="post" onsubmit="return confirm('¿Confirmar el pago de esta factura?');">
                        <input type="hidden" name="accion" value="pagar">
                        <input type="hidden" name="id_factura" value="<?= (int)$facturaSeleccionada['id_factura'] ?>">
                        <button class="btn pagar" type="submit">Confirmar pago</button>
                    </form>

                    <form class="form-inline" method="post" onsubmit="return confirm('¿Anular esta factura?');">
                        <input type="hidden" name="accion" value="anular">
                        <input type="hidden" name="id_factura" value="<?= (int)$facturaSeleccionada['id_factura'] ?>">
                        <button class="btn anular" type="submit">Anular</button>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

</div>

<footer>
    <strong>Sistema de Gestión Restaurante SABORA</strong><br>
    Módulo de Facturación - Cajero
    <br>
    &copy; <?= date('Y') ?> - Todos los derechos reservados.
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
(function () {
    const contenedor = document.getElementById('qrFactura');
    if (!contenedor || typeof QRCode === 'undefined') return;

    const url = <?= json_encode($facturaSeleccionada ? urlValidacion($facturaSeleccionada['token_factura']) : '') ?>;

    if (!url) return;

    new QRCode(contenedor, {
        text: url,
        width: 110,
        height: 110,
        correctLevel: QRCode.CorrectLevel.M
    });
})();
</script>

</body>
</html>
