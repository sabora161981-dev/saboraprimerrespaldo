<?php
require_once "conexion.php";

$token = trim($_GET['token'] ?? '');

$factura = null;
$detalles = [];
$error = null;

if ($token === '') {
    $error = 'No se recibió el token de la factura.';
} elseif (!preg_match('/^[a-fA-F0-9]{64}$/', $token)) {
    $error = 'El token de la factura no tiene un formato válido.';
}

if ($error === null) {
    $sql = "SELECT f.id_factura, f.numero_factura, f.fecha, f.subtotal,
                   f.impuestos, f.descuento, f.total, f.metodo_pago,
                   f.estado, f.token_factura
            FROM factura f
            WHERE f.token_factura = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);

    if (!$stmt) {
        $error = 'No fue posible preparar la validación de la factura.';
    } else {
        mysqli_stmt_bind_param($stmt, 's', $token);

        if (!mysqli_stmt_execute($stmt)) {
            $error = 'No fue posible consultar la factura.';
        } else {
            $resultado = mysqli_stmt_get_result($stmt);
            if ($resultado && mysqli_num_rows($resultado) === 1) {
                $factura = mysqli_fetch_assoc($resultado);
            } else {
                $error = 'La factura no existe o el token no es válido.';
            }
        }
        mysqli_stmt_close($stmt);
    }
}

if ($factura) {
    $sqlDetalle = "SELECT df.id_detalle, df.cantidad, df.precio_unitario,
                          df.subtotal, p.nombre
                   FROM detalle_factura df
                   LEFT JOIN producto p ON p.idProducto = df.idProducto
                   WHERE df.id_factura = ?
                   ORDER BY df.id_detalle ASC";

    $stmtDetalle = mysqli_prepare($conexion, $sqlDetalle);

    if ($stmtDetalle) {
        mysqli_stmt_bind_param($stmtDetalle, 'i', $factura['id_factura']);
        if (mysqli_stmt_execute($stmtDetalle)) {
            $resultadoDetalle = mysqli_stmt_get_result($stmtDetalle);
            if ($resultadoDetalle) {
                while ($fila = mysqli_fetch_assoc($resultadoDetalle)) {
                    $detalles[] = $fila;
                }
            }
        }
        mysqli_stmt_close($stmtDetalle);
    }
}

function escapar($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function dinero($valor): string {
    return '$' . number_format((float)$valor, 0, ',', '.');
}

$tokenVisible = $factura 
    ? substr($factura['token_factura'], 0, 8) . '...' . substr($factura['token_factura'], -8) 
    : '';

$estadoFactura = $factura
    ? strtolower(trim($factura['estado']))
    : '';

if ($estadoFactura === 'pagada') {
    $tituloEstado = 'Factura válida';
    $mensajeEstado = 'La factura fue encontrada y validada correctamente en el sistema Sabora.';
    $claseEstado = 'valida';
    $iconoEstado = '✓';
} elseif ($estadoFactura === 'generada') {
    $tituloEstado = 'Factura pendiente';
    $mensajeEstado = 'La factura existe en el sistema, pero aún se encuentra pendiente de pago.';
    $claseEstado = 'pendiente';
    $iconoEstado = '!';
} elseif ($estadoFactura === 'anulada') {
    $tituloEstado = 'Factura anulada';
    $mensajeEstado = 'La factura existe en el sistema, pero fue anulada y no es válida como comprobante vigente.';
    $claseEstado = 'anulada';
    $iconoEstado = '✕';
} else {
    $tituloEstado = 'Estado no identificado';
    $mensajeEstado = 'La factura fue encontrada, pero su estado no está reconocido por el sistema.';
    $claseEstado = 'desconocida';
    $iconoEstado = '?';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Validación de Factura | Sabora</title>
<style>
*{box-sizing:border-box;margin:0;padding:0;font-family:'Segoe UI',Arial,sans-serif}
body{min-height:100vh;background:#f5ede6;color:#333;padding:30px 15px}
.contenedor{width:100%;max-width:850px;margin:auto}
.encabezado{background:#ff6b35;color:#fff;border-radius:15px 15px 0 0;padding:25px;text-align:center}
.encabezado h1{font-size:28px;margin-bottom:6px}
.tarjeta{background:#fff;border-radius:0 0 15px 15px;padding:30px;box-shadow:0 5px 18px rgba(0,0,0,.12)}
.valida{text-align:center;padding:20px;margin-bottom:25px;border:2px solid #2e9d59;border-radius:12px;background:#f1fbf4}
.valida.pendiente{
    border-color:#d99a00;
    background:#fff9e6;
}

.valida.anulada{
    border-color:#c0392b;
    background:#fff4f3;
}

.valida.desconocida{
    border-color:#777;
    background:#f5f5f5;
}

.valida.pendiente h2{
    color:#9a6a00;
}

.valida.anulada h2{
    color:#a93226;
}

.valida.desconocida h2{
    color:#555;
}

.valida .icono{font-size:42px;margin-bottom:8px}.valida h2{color:#247a44;margin-bottom:5px}
.error{text-align:center;padding:25px;border:2px solid #c0392b;border-radius:12px;background:#fff4f3;color:#8f2d23}
.error h2{margin-bottom:10px}
.datos{display:grid;grid-template-columns:repeat(2,1fr);gap:15px;margin-bottom:30px}
.dato{border:1px solid #ddd;border-radius:10px;padding:14px}.dato .etiqueta{display:block;color:#777;font-size:13px;margin-bottom:5px}
.seccion h3{color:#ff6b35;margin-bottom:12px}
table{width:100%;border-collapse:collapse;margin-bottom:25px}th{background:#fff0e9;text-align:left;padding:12px 10px;font-size:14px}td{padding:12px 10px;border-bottom:1px solid #eee;font-size:14px}.derecha{text-align:right}
.totales{max-width:360px;margin-left:auto;border:1px solid #ff6b35;border-radius:10px;padding:15px}.total-linea{display:flex;justify-content:space-between;padding:5px 0}.total-final{border-top:1px solid #333;margin-top:8px;padding-top:10px;font-size:19px;font-weight:bold;color:#ff6b35}
.seguridad{margin-top:25px;padding:15px;border-radius:10px;background:#f7f7f7;color:#666;font-size:13px}.pie{text-align:center;margin-top:20px;color:#777;font-size:13px}
@media(max-width:600px){body{padding:15px 10px}.tarjeta{padding:20px 15px}.datos{grid-template-columns:1fr}th,td{padding:9px 6px;font-size:13px}.encabezado h1{font-size:23px}}
</style>
</head>
<body>
<div class="contenedor">
<header class="encabezado"><h1>Sabora</h1><p>Verificación de factura</p></header>
<main class="tarjeta">
<?php if ($factura): ?>
<section class="valida <?= escapar($claseEstado) ?>">
    <div class="icono"><?= escapar($iconoEstado) ?></div>
    <h2><?= escapar($tituloEstado) ?></h2>
    <p><?= escapar($mensajeEstado) ?></p>
</section><section class="datos">
<div class="dato"><span class="etiqueta">N.º de factura</span><strong><?= escapar($factura['numero_factura']) ?></strong></div>
<div class="dato"><span class="etiqueta">Fecha</span><strong><?= escapar($factura['fecha']) ?></strong></div>
<div class="dato"><span class="etiqueta">Estado</span><strong><?= escapar($factura['estado']) ?></strong></div>
<div class="dato"><span class="etiqueta">Método de pago</span><strong><?= escapar($factura['metodo_pago']) ?></strong></div>
</section>
<section class="seccion"><h3>Detalle de productos</h3>
<?php if ($detalles): ?><table><thead><tr><th>Producto</th><th class="derecha">Cantidad</th><th class="derecha">Precio</th><th class="derecha">Subtotal</th></tr></thead><tbody>
<?php foreach ($detalles as $detalle): ?><tr><td><?= escapar($detalle['nombre'] ?? 'Producto') ?></td><td class="derecha"><?= (int)$detalle['cantidad'] ?></td><td class="derecha"><?= dinero($detalle['precio_unitario']) ?></td><td class="derecha"><?= dinero($detalle['subtotal']) ?></td></tr><?php endforeach; ?>
</tbody></table><?php else: ?><p>No se encontraron detalles para esta factura.</p><?php endif; ?></section>
<section class="totales">
<div class="total-linea"><span>Subtotal:</span><span><?= dinero($factura['subtotal']) ?></span></div>
<div class="total-linea"><span>Impuestos:</span><span><?= dinero($factura['impuestos']) ?></span></div>
<div class="total-linea"><span>Descuento:</span><span><?= dinero($factura['descuento']) ?></span></div>
<div class="total-linea total-final"><span>Total:</span><span><?= dinero($factura['total']) ?></span></div>
</section>
<div class="seguridad"><strong>Token de verificación:</strong> <?= escapar($tokenVisible) ?><br><br>El token identifica de forma única la factura validada.</div>
<?php else: ?>
<section class="error"><h2>Factura no válida</h2><p><?= escapar($error ?? 'No fue posible validar la factura.') ?></p></section>
<?php endif; ?>
</main>
<footer class="pie">
    Sistema de Gestión Restaurante <strong>SABORA</strong><br>
    Verificación de factura electrónica.<br>
    &copy; 2026 - Todos los derechos reservados.
</footer>
</div>
</body>
</html>