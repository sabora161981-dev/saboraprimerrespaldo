<?php
session_start();
require_once __DIR__ . '/conexion.php';

/* =========================================================
   ACCESO: únicamente administrador o cajero autenticados.
========================================================= */
$rol = strtolower(trim($_SESSION['rol'] ?? ''));
if (!isset($_SESSION['correo']) || !in_array($rol, ['administrador', 'cajero'], true)) {
    header('Location: Cajero.html');
    exit();
}
$esAdmin = ($rol === 'administrador');
$nombreUsuario = trim(($_SESSION['nombre'] ?? '') . ' ' . ($_SESSION['apellido'] ?? ''));

if (empty($_SESSION['csrf_productos'])) {
    $_SESSION['csrf_productos'] = bin2hex(random_bytes(32));
}

$mensaje = $_SESSION['mensaje_productos'] ?? '';
unset($_SESSION['mensaje_productos']);
$error = '';

/*
  La tabla de notas se crea automáticamente desde este archivo.
  Así no es necesario instalar un archivo SQL adicional.
*/
$crearTablaNotas = "CREATE TABLE IF NOT EXISTS notas_producto (
    id_nota INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    nombre_producto VARCHAR(255) NOT NULL,
    id_usuario INT NULL,
    nombre_usuario VARCHAR(255) NOT NULL,
    correo_usuario VARCHAR(255) NOT NULL,
    nota TEXT NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'Pendiente',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_atencion DATETIME NULL,
    INDEX idx_notas_producto (id_producto),
    INDEX idx_notas_estado (estado),
    INDEX idx_notas_fecha (fecha_creacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
if (!mysqli_query($conexion, $crearTablaNotas)) {
    $error = 'No fue posible preparar la tabla de notas: ' . mysqli_error($conexion);
}

/* El administrador puede marcar una observación como atendida. */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'atender_nota') {
    if (!$esAdmin) {
        http_response_code(403);
        exit('Acceso no autorizado.');
    }
    $csrf = $_POST['csrf_token'] ?? '';
    $idNota = filter_input(INPUT_POST, 'id_nota', FILTER_VALIDATE_INT);
    if (!hash_equals($_SESSION['csrf_productos'], $csrf) || !$idNota || $idNota < 1) {
        $error = 'No se pudo validar la solicitud para atender la nota.';
    } else {
        $stmtAtender = mysqli_prepare($conexion, "UPDATE notas_producto SET estado = 'Atendida', fecha_atencion = NOW() WHERE id_nota = ?");
        if ($stmtAtender) {
            mysqli_stmt_bind_param($stmtAtender, 'i', $idNota);
            if (mysqli_stmt_execute($stmtAtender)) {
                $_SESSION['mensaje_productos'] = 'La nota se marcó como atendida.';
                mysqli_stmt_close($stmtAtender);
                header('Location: productos.php#notas-cajeros');
                exit();
            }
            mysqli_stmt_close($stmtAtender);
        }
        $error = 'No fue posible actualizar el estado de la nota.';
    }
}

/* =========================================================
   GUARDAR NOTA: el cajero deja una observación del producto.
   La nota se guarda con el producto, usuario y fecha.
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar_nota') {
    if ($esAdmin === false && $rol !== 'cajero') {
        http_response_code(403);
        exit('Acceso no autorizado.');
    }

    $csrf = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_productos'], $csrf)) {
        $error = 'La sesión del formulario venció. Recarga la página e inténtalo de nuevo.';
    } else {
        $idProducto = filter_input(INPUT_POST, 'idProducto', FILTER_VALIDATE_INT);
        $nota = trim($_POST['nota'] ?? '');
        $idUsuario = filter_var($_SESSION['id'] ?? null, FILTER_VALIDATE_INT);
        $correo = trim($_SESSION['correo'] ?? '');

        if (!$idProducto || $idProducto < 1) {
            $error = 'No se identificó correctamente el producto.';
        } elseif ($nota === '' || mb_strlen($nota) < 3) {
            $error = 'Escribe una nota de al menos 3 caracteres.';
        } elseif (mb_strlen($nota) > 2000) {
            $error = 'La nota no puede superar los 2000 caracteres.';
        } else {
            // Confirmar que el producto seleccionado existe.
            $stmtProducto = mysqli_prepare($conexion, 'SELECT nombre FROM producto WHERE idProducto = ? LIMIT 1');
            if (!$stmtProducto) {
                $error = 'No fue posible validar el producto en la base de datos.';
            } else {
                mysqli_stmt_bind_param($stmtProducto, 'i', $idProducto);
                mysqli_stmt_execute($stmtProducto);
                $resultadoProducto = mysqli_stmt_get_result($stmtProducto);
                $producto = mysqli_fetch_assoc($resultadoProducto);
                mysqli_stmt_close($stmtProducto);

                if (!$producto) {
                    $error = 'El producto seleccionado ya no existe.';
                } else {
                    /*
                      Se guarda el correo y nombre como referencia histórica.
                      id_usuario se conserva cuando la sesión contiene un ID válido.
                    */
                    $stmtNota = mysqli_prepare($conexion,
                        'INSERT INTO notas_producto (id_producto, nombre_producto, id_usuario, nombre_usuario, correo_usuario, nota, estado, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, \'Pendiente\', NOW())'
                    );
                    if (!$stmtNota) {
                        $error = 'No se pudo preparar el registro de la nota. Verifica los permisos de la base de datos.';
                    } else {
                        mysqli_stmt_bind_param($stmtNota, 'isisss', $idProducto, $producto['nombre'], $idUsuario, $nombreUsuario, $correo, $nota);
                        if (mysqli_stmt_execute($stmtNota)) {
                            $_SESSION['mensaje_productos'] = 'La nota del producto se envió al administrador correctamente.';
                            mysqli_stmt_close($stmtNota);
                            header('Location: productos.php');
                            exit();
                        } else {
                            $error = 'No se pudo guardar la nota. Verifica que la tabla notas_producto esté creada.';
                        }
                        mysqli_stmt_close($stmtNota);
                    }
                }
            }
        }
    }
}

/* =========================================================
   BUSCAR, ORDENAR Y CONSULTAR PRODUCTOS
   La búsqueda filtra por ID, nombre o categoría.
   El ordenamiento usa una lista permitida para evitar
   insertar valores enviados por el usuario directamente en SQL.
========================================================= */
$busqueda = trim($_GET['buscar'] ?? '');
$orden = $_GET['orden'] ?? 'recientes';

$ordenesPermitidos = [
    'recientes' => 'idProducto DESC',
    'antiguos'  => 'idProducto ASC',
    'nombre_asc' => 'nombre ASC',
    'nombre_desc' => 'nombre DESC',
    'precio_asc' => 'precio ASC',
    'precio_desc' => 'precio DESC',
    'stock_asc' => 'stock ASC',
    'stock_desc' => 'stock DESC'
];

if (!array_key_exists($orden, $ordenesPermitidos)) {
    $orden = 'recientes';
}

$sqlProductos = 'SELECT * FROM producto';
if ($busqueda !== '') {
    $sqlProductos .= ' WHERE CAST(idProducto AS CHAR) LIKE ? OR nombre LIKE ? OR categoria LIKE ?';
}
$sqlProductos .= ' ORDER BY ' . $ordenesPermitidos[$orden];

if ($busqueda !== '') {
    $stmtProductos = mysqli_prepare($conexion, $sqlProductos);
    if ($stmtProductos) {
        $terminoBusqueda = '%' . $busqueda . '%';
        mysqli_stmt_bind_param($stmtProductos, 'sss', $terminoBusqueda, $terminoBusqueda, $terminoBusqueda);
        mysqli_stmt_execute($stmtProductos);
        $resultado = mysqli_stmt_get_result($stmtProductos);
    } else {
        $resultado = false;
        $error = 'No fue posible preparar la búsqueda de productos.';
    }
} else {
    $resultado = mysqli_query($conexion, $sqlProductos);
}

if (!$resultado && $error === '') {
    $error = 'No fue posible consultar los productos: ' . mysqli_error($conexion);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Productos | Sabora</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="productos.css">
<style>
/* Ventana modal para que el cajero escriba una nota sobre el producto elegido. */
.modal-nota-fondo{
    display:none;
    position:fixed;
    inset:0;
    width:100%;
    height:100%;
    background:rgba(0,0,0,.55);
    z-index:9999;
    align-items:center;
    justify-content:center;
    padding:18px;
    box-sizing:border-box;
    overflow:auto;
}
.modal-nota-fondo.visible{
    display:flex;
}
.modal-nota{
    background:#fff;
    width:100%;max-width:560px;
    border-radius:15px;
    box-shadow:0 15px 45px rgba(0,0,0,.25);
    overflow:hidden;
    margin:auto;
    position:relative;
}
.modal-nota-cabecera{background:#ff6b35;color:#fff;padding:18px 22px;display:flex;justify-content:space-between;align-items:center;gap:12px}
.modal-nota-cabecera h2{font-size:21px;margin:0}
.modal-nota-cerrar{background:transparent;border:0;color:#fff;font-size:27px;cursor:pointer;line-height:1}
.modal-nota-cuerpo{padding:22px}
.modal-nota-producto{background:#fff2eb;border-radius:8px;padding:12px;margin-bottom:16px;color:#63351f}
.modal-nota label{display:block;font-weight:600;margin-bottom:8px}
.modal-nota textarea{width:100%;min-height:145px;resize:vertical;border:1px solid #ccc;border-radius:8px;padding:12px;font:inherit;line-height:1.5}
.modal-nota textarea:focus{outline:2px solid #ffb08f;border-color:#ff6b35}
.modal-nota-ayuda{font-size:13px;color:#666;margin-top:7px}
.modal-nota-acciones{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:18px}
.modal-nota-acciones button{border:0;border-radius:8px;padding:11px 17px;font-size:15px;font-weight:600;cursor:pointer}
.btn-nota-cancelar{background:#777;color:#fff}
.btn-nota-guardar{background:#ff6b35;color:#fff}
.btn-nota-guardar:hover{background:#e85a2a}
.btn-nota-cancelar:hover{background:#555}
.alerta-productos{margin:15px 0;padding:13px 16px;border-radius:8px;background:#e8f7ec;color:#17652b}
.alerta-error{background:#ffe9e9;color:#9c2020}
.btn-nota-tabla{background:#ff6b35;color:#fff;border:0;border-radius:7px;padding:9px 11px;cursor:pointer;font-size:17px}
.btn-nota-tabla:hover{background:#e85a2a}
.btn-nota-tabla:focus-visible,.modal-nota button:focus-visible{outline:3px solid #222;outline-offset:2px}
.seccion-notas{margin-top:35px;background:#fff;border-radius:12px;padding:22px;box-shadow:0 4px 14px rgba(0,0,0,.08)}
.seccion-notas h2{color:#ff6b35;margin:0 0 8px}
.seccion-notas>p{color:#666;margin-bottom:15px}
.tabla-notas-contenedor{width:100%;overflow-x:auto}
.tabla-notas{min-width:900px}
.tabla-notas th{background:#ff6b35;color:#fff;padding:12px}
.tabla-notas td{padding:12px;border-bottom:1px solid #eee;vertical-align:top}
.texto-nota{min-width:220px;max-width:400px;white-space:normal;overflow-wrap:anywhere}
.btn-atender-nota{background:#ff6b35;color:#fff;border:0;border-radius:7px;padding:9px 11px;cursor:pointer}
.btn-atender-nota:hover{background:#e85a2a}
.nota-atendida{color:#217a3c;font-weight:600}
.sin-notas{padding:14px;background:#fff4ed;border-radius:8px}
/* Buscador y ordenamiento de productos */
.filtros-productos{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:18px 0;padding:15px;background:#fff;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,.06)}
.filtros-productos input[type="search"]{flex:1;min-width:220px;padding:11px 12px;border:1px solid #ccc;border-radius:7px;font:inherit}
.filtros-productos select{min-width:190px;padding:11px 12px;border:1px solid #ccc;border-radius:7px;background:#fff;font:inherit}
.filtros-productos button,.filtros-productos a{display:inline-block;padding:11px 15px;border:0;border-radius:7px;background:#ff6b35;color:#fff;text-decoration:none;cursor:pointer;font:inherit}
.filtros-productos button:hover,.filtros-productos a:hover{background:#e85a2a}
.resultado-busqueda{margin:0 0 12px;color:#666}
@media(max-width:650px){.modal-nota-cuerpo{padding:16px}.modal-nota-cabecera h2{font-size:18px}.modal-nota-acciones button{flex:1}}
</style>
</head>
<body>
<div class="contenedor">
<h1>🍔 Gestión de Productos</h1>

<div class="barra">
<a href="<?= $esAdmin ? 'Administrador.php' : 'Cajero.php' ?>" class="volver">← Volver</a>
<?php if ($esAdmin): ?>
    <a href="nuevo_producto.php" class="nuevo">+ Nuevo Producto</a>
    <a href="#notas-cajeros" class="nuevo" style="margin-left:8px">Notas de cajeros</a>
<?php endif; ?>
</div>

<?php if ($mensaje !== ''): ?><div class="alerta-productos"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alerta-productos alerta-error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

<!-- Buscador y selector para organizar el listado de productos. -->
<form class="filtros-productos" method="GET" action="productos.php">
    <input
        type="search"
        name="buscar"
        value="<?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?>"
        placeholder="Buscar por ID, nombre o categoría..."
        aria-label="Buscar productos"
        maxlength="100"
    >
    <select name="orden" aria-label="Ordenar productos">
        <option value="recientes" <?= $orden === 'recientes' ? 'selected' : '' ?>>Más recientes primero</option>
        <option value="antiguos" <?= $orden === 'antiguos' ? 'selected' : '' ?>>Más antiguos primero</option>
        <option value="nombre_asc" <?= $orden === 'nombre_asc' ? 'selected' : '' ?>>Nombre: A a Z</option>
        <option value="nombre_desc" <?= $orden === 'nombre_desc' ? 'selected' : '' ?>>Nombre: Z a A</option>
        <option value="precio_asc" <?= $orden === 'precio_asc' ? 'selected' : '' ?>>Precio: menor a mayor</option>
        <option value="precio_desc" <?= $orden === 'precio_desc' ? 'selected' : '' ?>>Precio: mayor a menor</option>
        <option value="stock_asc" <?= $orden === 'stock_asc' ? 'selected' : '' ?>>Stock: menor a mayor</option>
        <option value="stock_desc" <?= $orden === 'stock_desc' ? 'selected' : '' ?>>Stock: mayor a menor</option>
    </select>
    <button type="submit"><i class="bi bi-search"></i> Buscar / organizar</button>
    <a href="productos.php">Limpiar</a>
</form>
<p class="resultado-busqueda">
    <?php if ($busqueda !== ''): ?>
        Búsqueda: <strong><?= htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8') ?></strong>.
    <?php endif; ?>
    Orden actual: <strong><?= htmlspecialchars($ordenesPermitidos[$orden], ENT_QUOTES, 'UTF-8') ?></strong>.
    <?php if ($resultado): ?>
        Productos encontrados: <strong><?= mysqli_num_rows($resultado) ?></strong>
    <?php endif; ?>
</p>

<table>
<thead><tr><th>ID</th><th>Imagen</th><th>Nombre</th><th>Categoría</th><th>Precio</th><th>Stock</th><th>Estado</th><th>Acciones</th></tr></thead>
<tbody>
<?php if ($resultado && mysqli_num_rows($resultado) > 0): ?>
<?php while ($fila = mysqli_fetch_assoc($resultado)): ?>
<tr>
<td><?= (int)$fila['idProducto'] ?></td>
<td>
<?php if (!empty($fila['imagen'])): ?>
<img src="<?= htmlspecialchars($fila['imagen'], ENT_QUOTES, 'UTF-8') ?>" width="70" height="70" style="object-fit:contain;border-radius:8px;background:white;padding:4px" alt="<?= htmlspecialchars($fila['nombre'], ENT_QUOTES, 'UTF-8') ?>">
<?php else: ?>
<img src="img/productos/sin_foto.png" width="70" height="70" style="border-radius:8px;object-fit:cover" alt="Producto sin foto">
<?php endif; ?>
</td>
<td><?= htmlspecialchars($fila['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars($fila['categoria'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
<td>$<?= number_format((float)$fila['precio'], 0, ',', '.') ?></td>
<td><?= htmlspecialchars((string)($fila['stock'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
<td><?= htmlspecialchars($fila['estado'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
<td class="acciones">
<?php if ($esAdmin): ?>
    <a href="editar_producto.php?id=<?= (int)$fila['idProducto'] ?>" class="editar" title="Editar producto" aria-label="Editar producto"><i class="bi bi-pencil-square"></i></a>
    <a href="eliminar_producto.php?id=<?= (int)$fila['idProducto'] ?>" class="eliminar" title="Eliminar producto" aria-label="Eliminar producto"><i class="bi bi-trash"></i></a>
<?php else: ?>
    <button type="button" class="btn-nota-tabla js-abrir-nota" data-id="<?= (int)$fila['idProducto'] ?>" data-nombre="<?= htmlspecialchars($fila['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>" title="Escribir nota al administrador" aria-label="Escribir nota sobre <?= htmlspecialchars($fila['nombre'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil-square"></i></button>
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
<?php else: ?>
<tr><td colspan="8">No hay productos registrados.</td></tr>
<?php endif; ?>
</tbody>
</table>

<?php if ($esAdmin): ?>
<?php
$notasAdmin = mysqli_query($conexion, "SELECT id_nota, nombre_producto, nombre_usuario, correo_usuario, nota, estado, fecha_creacion FROM notas_producto ORDER BY fecha_creacion DESC");
?>
<section id="notas-cajeros" class="seccion-notas">
    <h2>Notas de los cajeros</h2>
    <p>Observaciones enviadas sobre productos del inventario.</p>
    <?php if ($notasAdmin && mysqli_num_rows($notasAdmin) > 0): ?>
    <div class="tabla-notas-contenedor">
      <table class="tabla-notas">
        <thead><tr><th>Fecha</th><th>Producto</th><th>Cajero</th><th>Nota</th><th>Estado</th><th>Acción</th></tr></thead>
        <tbody>
        <?php while ($notaFila = mysqli_fetch_assoc($notasAdmin)): ?>
          <tr>
            <td><?= htmlspecialchars($notaFila['fecha_creacion'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($notaFila['nombre_producto'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($notaFila['nombre_usuario'] . ' (' . $notaFila['correo_usuario'] . ')', ENT_QUOTES, 'UTF-8') ?></td>
            <td class="texto-nota"><?= nl2br(htmlspecialchars($notaFila['nota'], ENT_QUOTES, 'UTF-8')) ?></td>
            <td><?= htmlspecialchars($notaFila['estado'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>
              <?php if ($notaFila['estado'] !== 'Atendida'): ?>
              <form method="POST" action="productos.php#notas-cajeros">
                <input type="hidden" name="accion" value="atender_nota">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_productos'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id_nota" value="<?= (int)$notaFila['id_nota'] ?>">
                <button type="submit" class="btn-atender-nota">Marcar atendida</button>
              </form>
              <?php else: ?>
                <span class="nota-atendida">Atendida</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <p class="sin-notas">Todavía no hay notas enviadas por los cajeros.</p>
    <?php endif; ?>
</section>
<?php endif; ?>
</div>

<?php if (!$esAdmin): ?>
<!-- Modal: el cajero escribe una observación vinculada al producto seleccionado. -->
<div class="modal-nota-fondo" id="modalNotaFondo" role="dialog" aria-modal="true" aria-labelledby="tituloModalNota">
  <div class="modal-nota">
    <div class="modal-nota-cabecera">
      <h2 id="tituloModalNota"><i class="bi bi-chat-left-text"></i> Nota para el administrador</h2>
      <button type="button" class="modal-nota-cerrar" id="cerrarModalX" aria-label="Cerrar ventana">&times;</button>
    </div>
    <form method="POST" action="productos.php" id="formNotaProducto">
      <div class="modal-nota-cuerpo">
        <div class="modal-nota-producto">Producto seleccionado: <strong id="nombreProductoModal"></strong></div>
        <input type="hidden" name="accion" value="guardar_nota">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_productos'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="idProducto" id="idProductoModal" value="">
        <label for="notaProducto">Observación del cajero</label>
        <textarea name="nota" id="notaProducto" maxlength="2000" minlength="3" required placeholder="Escribe aquí la observación relacionada con este producto..."></textarea>
        <p class="modal-nota-ayuda">La nota se enviará al administrador. Máximo 2000 caracteres.</p>
        <div class="modal-nota-acciones">
          <button type="button" class="btn-nota-cancelar" id="cancelarModalNota">Cancelar</button>
          <button type="submit" class="btn-nota-guardar">Enviar nota</button>
        </div>
      </div>
    </form>
  </div>
</div>
<script>
/* Abre el cuadro de diálogo y carga el producto que seleccionó el cajero. */
const fondoNota = document.getElementById('modalNotaFondo');
const campoNota = document.getElementById('notaProducto');
const idProductoModal = document.getElementById('idProductoModal');
const nombreProductoModal = document.getElementById('nombreProductoModal');
function abrirModalNota(id, nombre) {
    idProductoModal.value = id;
    nombreProductoModal.textContent = nombre;
    campoNota.value = '';
    fondoNota.classList.add('visible');
    campoNota.focus();
}
function cerrarModalNota() {
    fondoNota.classList.remove('visible');
}
document.querySelectorAll('.js-abrir-nota').forEach(boton => {
    boton.addEventListener('click', () => abrirModalNota(boton.dataset.id, boton.dataset.nombre));
});
document.getElementById('cerrarModalX').addEventListener('click', cerrarModalNota);
document.getElementById('cancelarModalNota').addEventListener('click', cerrarModalNota);
fondoNota.addEventListener('click', evento => { if (evento.target === fondoNota) cerrarModalNota(); });
document.addEventListener('keydown', evento => { if (evento.key === 'Escape' && fondoNota.classList.contains('visible')) cerrarModalNota(); });
</script>
<?php endif; ?>
</body>
</html>
