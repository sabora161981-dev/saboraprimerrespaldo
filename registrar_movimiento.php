
<?php
session_start();
require_once __DIR__ . '/conexion.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Todos los movimientos deben enviarse mediante POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

// Validar el rol.
$rol = strtolower(trim($_SESSION['rol'] ?? ''));

if ($rol !== 'administrador') {
    http_response_code(403);
    exit('No tienes permisos para registrar movimientos de inventario.');
}

// Identificar al usuario autenticado.
$idUsuario = filter_var(
    $_SESSION['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if ($idUsuario === false || $idUsuario === null) {
    http_response_code(401);
    exit('No se encontró el ID del usuario en la sesión. Revisa el inicio de sesión.');
}

// Validar token CSRF enviado por Inventario.php.
$tokenFormulario = $_POST['token_csrf'] ?? '';
$tokenSesion = $_SESSION['token_inventario'] ?? '';

if (
    !is_string($tokenFormulario) ||
    $tokenSesion === '' ||
    !hash_equals($tokenSesion, $tokenFormulario)
) {
    http_response_code(403);
    exit('Token de seguridad inválido. Recarga Inventario.php e inténtalo nuevamente.');
}

// Recibir datos.

$idProducto = filter_var(
    $_POST['idProducto'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$tipoMovimiento = strtoupper(trim($_POST['tipoMovimiento'] ?? ''));
$motivo = trim($_POST['motivo'] ?? '');
$observacion = trim($_POST['observacion'] ?? '');
$cantidadRecibida = $_POST['cantidad'] ?? null;

// AJUSTE permite establecer el stock final en cero.
// ENTRADA y SALIDA requieren una cantidad mayor que cero.
$minimoCantidad = ($tipoMovimiento === 'AJUSTE') ? 0 : 1;

$cantidad = filter_var(
    $cantidadRecibida,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => $minimoCantidad]]
);

if (
    $idProducto === false ||
    $idProducto === null ||
    $cantidad === false ||
    $cantidad === null ||
    !in_array($tipoMovimiento, ['ENTRADA', 'SALIDA', 'AJUSTE'], true) ||
    $motivo === '' ||
    mb_strlen($motivo) > 255 ||
    mb_strlen($observacion) > 5000
) {
    $_SESSION['inventario_mensaje'] =
        'Verifica el producto, la cantidad, el tipo de movimiento y el motivo.';

    $_SESSION['inventario_tipo'] = 'error';

    header('Location: Inventario.php');
    exit;
}

$moduloOrigen = 'Inventario';
$transaccionIniciada = false;

try {
    mysqli_begin_transaction($conexion);
    $transaccionIniciada = true;

    // Bloquear el producto para evitar modificaciones simultáneas
    // que produzcan inconsistencias en las existencias.
    $sqlProducto = "SELECT stock
                    FROM producto
                    WHERE idProducto = ?
                      AND estado = 'Activo'
                    FOR UPDATE";

    $stmtProducto = mysqli_prepare($conexion, $sqlProducto);
    mysqli_stmt_bind_param($stmtProducto, 'i', $idProducto);
    mysqli_stmt_execute($stmtProducto);

    $resultadoProducto = mysqli_stmt_get_result($stmtProducto);
    $producto = mysqli_fetch_assoc($resultadoProducto);

    if (!$producto) {
        throw new RuntimeException(
            'El producto no existe o no está activo.'
        );
    }

    $stockAnterior = (int) $producto['stock'];

    // Calcular las existencias según el movimiento.
    if ($tipoMovimiento === 'ENTRADA') {
        $stockNuevo = $stockAnterior + $cantidad;
        $cantidadMovimiento = $cantidad;

    } elseif ($tipoMovimiento === 'SALIDA') {
        if ($cantidad > $stockAnterior) {
            throw new RuntimeException(
                'Stock insuficiente. Existencias disponibles: ' .
                $stockAnterior . '.'
            );
        }

        $stockNuevo = $stockAnterior - $cantidad;
        $cantidadMovimiento = $cantidad;

    } else {
        // En un AJUSTE, cantidad representa el stock físico final.
        $stockNuevo = $cantidad;

        if ($stockNuevo === $stockAnterior) {
            throw new RuntimeException(
                'El stock físico coincide con el sistema. No hay cambios que registrar.'
            );
        }

        $cantidadMovimiento = abs($stockNuevo - $stockAnterior);
    }

    // Actualizar el stock del producto.
    $sqlActualizar = "UPDATE producto
                      SET stock = ?
                      WHERE idProducto = ?";

    $stmtActualizar = mysqli_prepare($conexion, $sqlActualizar);

    mysqli_stmt_bind_param(
        $stmtActualizar,
        'ii',
        $stockNuevo,
        $idProducto
    );

    mysqli_stmt_execute($stmtActualizar);

    // Registrar el historial con usuario y módulo de origen.
    $sqlMovimiento = "INSERT INTO movimiento_inventario
        (idProducto, tipoMovimiento, cantidad,
         stockAnterior, stockNuevo, motivo, observacion,
         idUsuario, moduloOrigen)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmtMovimiento = mysqli_prepare($conexion, $sqlMovimiento);

    mysqli_stmt_bind_param(
        $stmtMovimiento,
        'isiiissis',
        $idProducto,
        $tipoMovimiento,
        $cantidadMovimiento,
        $stockAnterior,
        $stockNuevo,
        $motivo,
        $observacion,
        $idUsuario,
        $moduloOrigen
    );

    mysqli_stmt_execute($stmtMovimiento);

    // Confirmar ambas operaciones juntas.
    mysqli_commit($conexion);
    $transaccionIniciada = false;

    $_SESSION['inventario_mensaje'] =
        'Movimiento registrado correctamente. Stock anterior: ' .
        $stockAnterior . '; stock nuevo: ' . $stockNuevo . '.';

    $_SESSION['inventario_tipo'] = 'exito';

    header('Location: Inventario.php');
    exit;

} catch (Throwable $e) {
    if ($transaccionIniciada) {
        mysqli_rollback($conexion);
    }

    // Guardar el detalle técnico en el registro de errores del servidor.
    error_log('Error en registrar_movimiento.php: ' . $e->getMessage());

    $_SESSION['inventario_mensaje'] =
        $e instanceof RuntimeException
            ? $e->getMessage()
            : 'No se pudo registrar el movimiento. Revisa el registro de errores.';

    $_SESSION['inventario_tipo'] = 'error';

    header('Location: Inventario.php');
    exit;
}
?>
