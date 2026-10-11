
<?php
session_start();
require_once "conexion.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Solo permitir solicitudes POST.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Método no permitido.");
}

// Validar el usuario y su rol.
$rol = strtolower(trim($_SESSION["rol"] ?? ""));
$idUsuario = (int) ($_SESSION["id"] ?? 0);

if ($rol !== "administrador") {
    http_response_code(403);
    exit("No tienes permisos para registrar productos.");
}

if ($idUsuario <= 0) {
    http_response_code(401);
    exit("Sesión inválida. Inicia sesión nuevamente.");
}

// Recibir los datos del formulario.
$nombre = trim($_POST["nombre"] ?? "");
$descripcion = trim($_POST["descripcion"] ?? "");
$categoria = trim($_POST["categoria"] ?? "");
$precio = filter_var(
    $_POST["precio"] ?? null,
    FILTER_VALIDATE_FLOAT
);
$stock = filter_var(
    $_POST["stock"] ?? null,
    FILTER_VALIDATE_INT
);
$fechaCompra = trim($_POST["fechaCompra"] ?? "");
$fechaVencimiento = trim($_POST["fechaVencimiento"] ?? "");
$almacenamiento = trim($_POST["almacenamiento"] ?? "");
$estado = trim($_POST["estado"] ?? "");

// Validar los datos.
if (
    $nombre === "" ||
    $categoria === "" ||
    $precio === false ||
    $precio <= 0 ||
    $stock === false ||
    $stock < 0 ||
    !in_array($estado, ["Activo", "Inactivo"], true)
) {
    exit("Verifica los datos del producto.");
}

// Preparar campos opcionales.
$fechaCompra = $fechaCompra !== "" ? $fechaCompra : null;
$fechaVencimiento = $fechaVencimiento !== ""
    ? $fechaVencimiento : null;
$almacenamiento = $almacenamiento !== ""
    ? $almacenamiento : null;

// Preparar la imagen.
$imagen = "";
$rutaFisica = null;

if (
    isset($_FILES["imagen"]) &&
    $_FILES["imagen"]["error"] !== UPLOAD_ERR_NO_FILE
) {
    if ($_FILES["imagen"]["error"] !== UPLOAD_ERR_OK) {
        exit("No fue posible recibir la imagen.");
    }

    if ($_FILES["imagen"]["size"] > 3 * 1024 * 1024) {
        exit("La imagen supera el límite de 3 MB.");
    }

    $info = getimagesize($_FILES["imagen"]["tmp_name"]);

    if ($info === false) {
        exit("El archivo no es una imagen válida.");
    }

    $tipos = [
        IMAGETYPE_JPEG => "jpg",
        IMAGETYPE_PNG  => "png",
        IMAGETYPE_WEBP => "webp"
    ];

    if (!isset($tipos[$info[2]])) {
        exit("Solo se permiten imágenes JPG, PNG o WEBP.");
    }

    $directorio = __DIR__ . "/img/productos/";

    if (
        !is_dir($directorio) &&
        !mkdir($directorio, 0755, true) &&
        !is_dir($directorio)
    ) {
        exit("No se pudo crear la carpeta de imágenes.");
    }

    $archivo = bin2hex(random_bytes(16)) . "." . $tipos[$info[2]];
    $rutaFisica = $directorio . $archivo;
    $imagen = "img/productos/" . $archivo;

    if (!move_uploaded_file(
        $_FILES["imagen"]["tmp_name"],
        $rutaFisica
    )) {
        exit("No se pudo guardar la imagen.");
    }
}

$transaccionIniciada = false;

try {
    mysqli_begin_transaction($conexion);
    $transaccionIniciada = true;

    // 1. Guardar el producto.
    $sql = "INSERT INTO producto
        (nombre, descripcion, categoria, precio, stock,
         fechaCompra, fechaVencimiento, almacenamiento,
         estado, imagen)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conexion, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "sssdisssss",
        $nombre,
        $descripcion,
        $categoria,
        $precio,
        $stock,
        $fechaCompra,
        $fechaVencimiento,
        $almacenamiento,
        $estado,
        $imagen
    );

    mysqli_stmt_execute($stmt);

    // Obtener el ID del nuevo producto.
    $idProducto = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    // 2. Registrar el stock inicial en el historial.
    if ($stock > 0) {

        $tipoMovimiento = "ENTRADA";
        $stockAnterior = 0;
        $stockNuevo = $stock;
        $motivo = "Stock inicial";
        $observacion = "Existencias iniciales al crear el producto.";
        $moduloOrigen = "Productos";

        $sqlMov = "INSERT INTO movimiento_inventario
            (idProducto, tipoMovimiento, cantidad,
             stockAnterior, stockNuevo, motivo,
             observacion, idUsuario, moduloOrigen)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmtMov = mysqli_prepare($conexion, $sqlMov);

        mysqli_stmt_bind_param(
            $stmtMov,
            "isiiiisis",
            $idProducto,
            $tipoMovimiento,
            $stock,
            $stockAnterior,
            $stockNuevo,
            $motivo,
            $observacion,
            $idUsuario,
            $moduloOrigen
        );

        mysqli_stmt_execute($stmtMov);
        mysqli_stmt_close($stmtMov);
    }

    // 3. Confirmar ambas operaciones.
    mysqli_commit($conexion);
    $transaccionIniciada = false;

    header("Location: productos.php?mensaje=creado");
    exit();

} catch (Throwable $e) {

    if ($transaccionIniciada) {
        mysqli_rollback($conexion);
    }

    // Eliminar la imagen si falla el registro.
    if ($rutaFisica !== null && is_file($rutaFisica)) {
        unlink($rutaFisica);
    }

    error_log(
        "Error al guardar producto: " . $e->getMessage()
    );

    http_response_code(500);

    echo "No se pudo registrar el producto. "
       . "Consulta el registro de errores de PHP.";
}
?>
