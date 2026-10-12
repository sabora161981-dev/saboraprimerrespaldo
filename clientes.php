```php
<?php
session_start();

require_once __DIR__ . '/conexion.php';

/* =========================================
   VALIDAR SESIÓN Y ROL DEL CAJERO
========================================= */
if (!isset($_SESSION['correo'])) {
    header('Location: Cajero.html');
    exit();
}

if (strtolower(trim($_SESSION['rol'] ?? '')) !== 'cajero') {
    header('Location: Cliente.html');
    exit();
}

/* =========================================
   FUNCIONES DE SEGURIDAD
========================================= */
function escapar($valor): string {
    return htmlspecialchars(
        (string)($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

/* =========================================
   BUSCAR CLIENTES
========================================= */
$busqueda = trim($_GET['buscar'] ?? '');
$clientes = [];
$error = '';

$sql = "
    SELECT
        id,
        nombre,
        apellido,
        identificacion,
        correo
    FROM usuarios
    WHERE LOWER(TRIM(rol)) = 'cliente'
";

if ($busqueda !== '') {
    $sql .= "
        AND (
            nombre LIKE ?
            OR apellido LIKE ?
            OR identificacion LIKE ?
            OR correo LIKE ?
        )
    ";
}

$sql .= " ORDER BY nombre ASC, apellido ASC";

$stmt = mysqli_prepare($conexion, $sql);

if (!$stmt) {
    $error = 'No fue posible preparar la consulta de clientes.';
} else {
    if ($busqueda !== '') {
        $termino = '%' . $busqueda . '%';

        mysqli_stmt_bind_param(
            $stmt,
            'ssss',
            $termino,
            $termino,
            $termino,
            $termino
        );
    }

    if (!mysqli_stmt_execute($stmt)) {
        $error = 'No fue posible consultar los clientes.';
    } else {
        $resultado = mysqli_stmt_get_result($stmt);

        while ($fila = mysqli_fetch_assoc($resultado)) {
            $clientes[] = $fila;
        }
    }

    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Clientes - Sabora</title>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Arial, sans-serif;
}

body {
    background: #efe7e2;
    color: #333;
}

header {
    background: #ff6b35;
    color: white;
    padding: 20px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    box-shadow: 0 3px 10px rgba(0,0,0,.15);
}

header h1 {
    font-size: 28px;
}

.usuario {
    text-align: right;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.contenedor {
    width: 92%;
    max-width: 1250px;
    margin: 30px auto;
}

.panel {
    background: white;
    border-radius: 14px;
    padding: 25px;
    margin-bottom: 22px;
    box-shadow: 0 4px 14px rgba(0,0,0,.08);
}

h2 {
    color: #e85a2a;
    margin-bottom: 10px;
}

.descripcion {
    color: #666;
    margin-bottom: 22px;
}

.busqueda {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.busqueda input {
    flex: 1;
    min-width: 220px;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 15px;
}

.boton {
    display: inline-block;
    border: none;
    border-radius: 8px;
    padding: 12px 18px;
    background: #ff6b35;
    color: white;
    text-decoration: none;
    cursor: pointer;
    font-size: 15px;
    text-align: center;
    transition: .2s;
}

.boton:hover {
    background: #e85a2a;
}

.boton-secundario {
   background: #ff6b35;
    color: white;
}
.boton-secundario:hover {
   background: #e85a2a;
}
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
    border-collapse: collapse;
    min-width: 650px;
}

th, td {
    padding: 14px 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
}

th {
    background: #fff0e8;
    color: #9b3e1c;
}

tbody tr:hover {
    background: #fffaf7;
}

.vacio, .error {
    padding: 18px;
    border-radius: 8px;
    margin-top: 15px;
}

.vacio {
    background: #fff4eb;
    color: #79513d;
}

.error {
    background: #ffe9e9;
    color: #a40000;
}

.acciones {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 20px;
}

footer {
    text-align: center;
    color: #666;
    padding: 25px 15px;
}

@media (max-width: 600px) {
    header {
        padding: 18px;
    }

    .usuario {
        text-align: left;
    }

    .contenedor {
        width: 94%;
        margin: 18px auto;
    }

    .panel {
        padding: 18px;
    }

    .busqueda .boton {
        width: 100%;
    }
}
</style>
</head>

<body>

<header>
    <h1>SABORA</h1>

    <div class="usuario">
        <strong>
            <?= escapar(
                ($_SESSION['nombre'] ?? '') . ' ' .
                ($_SESSION['apellido'] ?? '')
            ) ?>
        </strong>
        <br>
        <?= escapar($_SESSION['correo']) ?>
        <br>
        Rol: <strong>Cajero</strong>
    </div>
</header>

<main class="contenedor">

    <section class="panel">
        <h2>Gestión de clientes</h2>

        <p class="descripcion">
            Consulta los clientes registrados en Sabora.
            Puedes buscar por nombre, apellido, identificación
            o correo electrónico.
        </p>

        <form class="busqueda" method="GET" action="clientes.php">
            <input
                type="search"
                name="buscar"
                placeholder="Nombre, identificación o correo..."
                value="<?= escapar($busqueda) ?>"
                maxlength="100"
            >

            <button type="submit" class="boton">
                Buscar clientes
            </button>

            <a href="clientes.php"
               class="boton boton-secundario">
                Limpiar
            </a>
        </form>
    </section>

    <section class="panel">
        <h2>Clientes registrados</h2>

        <p class="resumen">
            Resultados encontrados:
            <strong><?= count($clientes) ?></strong>
        </p>

        <?php if ($error !== ''): ?>

            <div class="error">
                <?= escapar($error) ?>
                Revisa la conexión y la estructura de la tabla usuarios.
            </div>

        <?php elseif (count($clientes) > 0): ?>

            <div class="tabla-contenedor">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nombre completo</th>
                            <th>Identificación</th>
                            <th>Correo electrónico</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php foreach ($clientes as $cliente): ?>
                        <tr>
                            <td><?= escapar($cliente['id']) ?></td>

                            <td>
                                <?= escapar(
                                    $cliente['nombre'] . ' ' .
                                    $cliente['apellido']
                                ) ?>
                            </td>

                            <td>
                                <?= escapar($cliente['identificacion']) ?>
                            </td>

                            <td><?= escapar($cliente['correo']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <div class="vacio">
                <?php if ($busqueda !== ''): ?>
                    No se encontraron clientes que coincidan con
                    "<?= escapar($busqueda) ?>".
                <?php else: ?>
                    Todavía no hay clientes registrados.
                <?php endif; ?>
            </div>

        <?php endif; ?>

        <div class="acciones">
            <a href="nueva_venta.php" class="boton">
                Ir a nueva venta
            </a>

            <a href="Cajero.php" class="boton boton-secundario">
                Volver al panel Cajero
            </a>
        </div>
    </section>

</main>

<footer>
    <strong>Sistema de Gestión Restaurante SABORA</strong>
    <br>
    Módulo de consulta de clientes.
    <br>
    &copy; <?= date('Y') ?> - Todos los derechos reservados.
</footer>

</body>
</html>