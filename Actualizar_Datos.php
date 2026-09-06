<?php

session_start();

// ======================================================
// VERIFICAR QUE EXISTA UNA SESIÓN
// ======================================================

if (!isset($_SESSION['correo'])) {
    header("Location: Cliente.html");
    exit();
}


// ======================================================
// VERIFICAR QUE EL USUARIO SEA CLIENTE
// ======================================================

if (!isset($_SESSION['rol']) || strtolower($_SESSION['rol']) != "cliente") {
    header("Location: Cliente.html");
    exit();
}


// ======================================================
// CONEXIÓN A LA BASE DE DATOS
// ======================================================

include("conexion.php");


// ======================================================
// CONFIGURAR CODIFICACIÓN
// ======================================================

mysqli_set_charset($conexion, "utf8mb4");


// ======================================================
// VARIABLES
// ======================================================

$mensaje = "";
$error = "";


// ======================================================
// CORREO ACTUAL DE LA SESIÓN
// ======================================================

$correo_actual = $_SESSION['correo'];


// ======================================================
// PROCESAR FORMULARIO
// ======================================================

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --------------------------------------------------
    // RECIBIR DATOS DEL FORMULARIO
    // --------------------------------------------------

    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $identificacion = trim($_POST['identificacion'] ?? '');
    $correo_nuevo = trim($_POST['correo'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');


    // --------------------------------------------------
    // VALIDAR CAMPOS OBLIGATORIOS
    // --------------------------------------------------

    if (
        $nombre == "" ||
        $apellido == "" ||
        $identificacion == "" ||
        $correo_nuevo == ""
    ) {

        $error = "Los campos Nombre, Apellido, Identificación y Correo son obligatorios.";

    } else {

        // --------------------------------------------------
        // OBTENER LA DIRECCIÓN ACTUAL
        // --------------------------------------------------
        // Esto permite conservar la dirección anterior
        // si el campo llega vacío.
        // --------------------------------------------------

        $sql_direccion = "
            SELECT direccion
            FROM usuarios
            WHERE correo = ?
        ";

        $stmt_direccion = mysqli_prepare(
            $conexion,
            $sql_direccion
        );

        mysqli_stmt_bind_param(
            $stmt_direccion,
            "s",
            $correo_actual
        );

        mysqli_stmt_execute($stmt_direccion);

        $resultado_direccion = mysqli_stmt_get_result(
            $stmt_direccion
        );

        $datos_direccion = mysqli_fetch_assoc(
            $resultado_direccion
        );

        mysqli_stmt_close($stmt_direccion);


        // --------------------------------------------------
        // CONSERVAR DIRECCIÓN ANTERIOR
        // --------------------------------------------------

        if ($direccion == "") {

            $direccion = $datos_direccion['direccion'] ?? "";

        }


        // --------------------------------------------------
        // VERIFICAR SI EL NUEVO CORREO YA EXISTE
        // --------------------------------------------------

        $sql_correo = "
            SELECT id
            FROM usuarios
            WHERE correo = ?
            AND correo != ?
        ";

        $stmt_correo = mysqli_prepare(
            $conexion,
            $sql_correo
        );

        mysqli_stmt_bind_param(
            $stmt_correo,
            "ss",
            $correo_nuevo,
            $correo_actual
        );

        mysqli_stmt_execute($stmt_correo);

        $resultado_correo = mysqli_stmt_get_result(
            $stmt_correo
        );


        if (mysqli_num_rows($resultado_correo) > 0) {

            $error = "El correo electrónico ya está registrado por otro usuario.";

        } else {

            // --------------------------------------------------
            // ACTUALIZAR TODOS LOS DATOS
            // --------------------------------------------------

            $sql_update = "
                UPDATE usuarios
                SET
                    nombre = ?,
                    apellido = ?,
                    identificacion = ?,
                    correo = ?,
                    direccion = ?
                WHERE correo = ?
            ";


            $stmt_update = mysqli_prepare(
                $conexion,
                $sql_update
            );


            // IMPORTANTE:
            // 6 variables = 6 tipos "ssssss"

            mysqli_stmt_bind_param(
                $stmt_update,
                "ssssss",
                $nombre,
                $apellido,
                $identificacion,
                $correo_nuevo,
                $direccion,
                $correo_actual
            );


            // --------------------------------------------------
            // EJECUTAR ACTUALIZACIÓN
            // --------------------------------------------------

            if (mysqli_stmt_execute($stmt_update)) {

                // ----------------------------------------------
                // ACTUALIZAR SESIÓN
                // ----------------------------------------------

                $_SESSION['nombre'] = $nombre;
                $_SESSION['apellido'] = $apellido;
                $_SESSION['identificacion'] = $identificacion;
                $_SESSION['correo'] = $correo_nuevo;
                $_SESSION['direccion'] = $direccion;


                // ----------------------------------------------
                // ACTUALIZAR CORREO ACTUAL
                // ----------------------------------------------

                $correo_actual = $correo_nuevo;


                // ----------------------------------------------
                // MENSAJE
                // ----------------------------------------------

                $mensaje = "Los datos se actualizaron correctamente.";

            } else {

                $error = "No fue posible actualizar los datos.";

            }


            mysqli_stmt_close($stmt_update);
        }


        mysqli_stmt_close($stmt_correo);
    }
}


// ======================================================
// VOLVER A CONSULTAR LOS DATOS DESDE MYSQL
// ======================================================
// Esta consulta se ejecuta después del UPDATE.
// De esta forma el formulario muestra exactamente
// lo que está almacenado actualmente en la BD.
// ======================================================

$sql_usuario = "
    SELECT
        nombre,
        apellido,
        identificacion,
        correo,
        direccion
    FROM usuarios
    WHERE correo = ?
";


$stmt_usuario = mysqli_prepare(
    $conexion,
    $sql_usuario
);


mysqli_stmt_bind_param(
    $stmt_usuario,
    "s",
    $correo_actual
);


mysqli_stmt_execute($stmt_usuario);


$resultado_usuario = mysqli_stmt_get_result(
    $stmt_usuario
);


// ======================================================
// OBTENER INFORMACIÓN DEL USUARIO
// ======================================================

$usuario = mysqli_fetch_assoc(
    $resultado_usuario
);


mysqli_stmt_close($stmt_usuario);


// ======================================================
// SI NO SE ENCUENTRA EL USUARIO
// ======================================================

if (!$usuario) {

    $error = "No se encontraron los datos del usuario.";

    $usuario = [
        'nombre' => '',
        'apellido' => '',
        'identificacion' => '',
        'correo' => '',
        'direccion' => ''
    ];
}


// ======================================================
// CALCULAR INICIALES
// ======================================================

$iniciales =
    strtoupper(substr($usuario['nombre'], 0, 1)) .
    strtoupper(substr($usuario['apellido'], 0, 1));

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<h3>Actualizar Datos</h3>


<!-- HEADER DEL CLIENTE -->

<link rel="stylesheet" href="css/header_cliente.css">


<style>

/* ======================================================
   CONFIGURACIÓN GENERAL
   ====================================================== */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', sans-serif;
}


body {
    background: #efe7e2;
}


/* ======================================================
   CONTENEDOR
   ====================================================== */

.container {
    width: 90%;
    max-width: 700px;
    margin: 40px auto;
}


/* ======================================================
   TARJETA
   ====================================================== */

.tarjeta {
    background: white;
    border-radius: 15px;
    padding: 30px;

    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.10);
}


.tarjeta h2 {
    color: #ff7a3d;
    margin-bottom: 25px;
    text-align: center;
}


.tarjeta p {
    margin-bottom: 20px;
    color: #555;
}


/* ======================================================
   CAMPOS
   ====================================================== */

.campo {
    margin-bottom: 20px;
}


.campo label {
    display: block;

    margin-bottom: 7px;

    font-weight: bold;

    color: #444;
}


.campo input {
    width: 100%;

    padding: 12px;

    border: 1px solid #ccc;

    border-radius: 8px;

    font-size: 16px;

    outline: none;
}


.campo input:focus {
    border-color: #ff7a3d;
}


/* ======================================================
   BOTONES
   ====================================================== */

.botones {
    display: flex;

    justify-content: center;

    gap: 15px;

    margin-top: 25px;
}


.btn {
    border: none;

    text-decoration: none;

    padding: 12px 25px;

    border-radius: 8px;

    font-size: 16px;

    cursor: pointer;
}


/* BOTÓN ACTUALIZAR */

.btn-actualizar {
    background: #ff7a3d;

    color: white;
}


.btn-actualizar:hover {
    background: #e5672f;
}


/* BOTÓN VOLVER */

.btn-volver {
    background: #ff7a3d;

    color: white;
}


.btn-volver:hover {
    background: #e5672f;
}


/* ======================================================
   MENSAJE DE ÉXITO
   ====================================================== */

.mensaje {
    background: #d4edda;

    color: #155724;

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 20px;

    text-align: center;
}


/* ======================================================
   MENSAJE DE ERROR
   ====================================================== */

.error {
    background: #f8d7da;

    color: #721c24;

    padding: 12px;

    border-radius: 8px;

    margin-bottom: 20px;

    text-align: center;
}


/* ======================================================
   FOOTER
   ====================================================== */

footer,
.footer-sabora {
    width: 100% !important;

    text-align: center !important;

    display: block !important;

    margin: 40px auto !important;

    color: #666;

    font-size: 15px;

    line-height: 1.6;
}

</style>

</head>


<body>


<!-- =====================================================
     HEADER
     ===================================================== -->

<?php include("includes/header_cliente.php"); ?>


<!-- =====================================================
     CONTENEDOR PRINCIPAL
     ===================================================== -->

<div class="container">

    <div class="tarjeta">


        <h2>
            Actualizar Datos
        </h2>


        <!-- =================================================
             MENSAJE DE ÉXITO
             ================================================= -->

        <?php if ($mensaje != ""): ?>

            <div class="mensaje">

                <?php
                echo htmlspecialchars($mensaje);
                ?>

            </div>

        <?php endif; ?>


        <!-- =================================================
             MENSAJE DE ERROR
             ================================================= -->

        <?php if ($error != ""): ?>

            <div class="error">

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <p>
            Modifica la información que deseas actualizar.
        </p>


        <!-- =================================================
             FORMULARIO
             ================================================= -->

        <form
            method="POST"
            action="Actualizar_Datos.php"
        >


            <!-- =================================================
                 NOMBRE
                 ================================================= -->

            <div class="campo">

                <label for="nombre">
                    Nombre
                </label>

                <input
                    type="text"
                    id="nombre"
                    name="nombre"
                    value="<?php echo htmlspecialchars($usuario['nombre'] ?? ''); ?>"
                    required
                >

            </div>


            <!-- =================================================
                 APELLIDO
                 ================================================= -->

            <div class="campo">

                <label for="apellido">
                    Apellido
                </label>

                <input
                    type="text"
                    id="apellido"
                    name="apellido"
                    value="<?php echo htmlspecialchars($usuario['apellido'] ?? ''); ?>"
                    required
                >

            </div>


            <!-- =================================================
                 IDENTIFICACIÓN
                 ================================================= -->

            <div class="campo">

                <label for="identificacion">
                    Identificación
                </label>

                <input
                    type="text"
                    id="identificacion"
                    name="identificacion"
                    value="<?php echo htmlspecialchars($usuario['identificacion'] ?? ''); ?>"
                    placeholder="Ingrese su identificación"
                    required
                >

            </div>


            <!-- =================================================
                 CORREO
                 ================================================= -->

            <div class="campo">

                <label for="correo">
                    Correo electrónico
                </label>

                <input
                    type="email"
                    id="correo"
                    name="correo"
                    value="<?php echo htmlspecialchars($usuario['correo'] ?? ''); ?>"
                    required
                >

            </div>


            <!-- =================================================
                 DIRECCIÓN
                 ================================================= -->

            <div class="campo">

                <label for="direccion">
                    Dirección
                </label>

                <input
                    type="text"
                    id="direccion"
                    name="direccion"
                    value="<?php echo htmlspecialchars($usuario['direccion'] ?? ''); ?>"
                    placeholder="Ingrese su dirección"
                >

            </div>


            <!-- =================================================
                 BOTONES
                 ================================================= -->

            <div class="botones">


                <button
                    type="submit"
                    class="btn btn-actualizar"
                >
                    Actualizar Datos
                </button>


                <a
                    href="Cliente.php"
                    class="btn btn-volver"
                >
                    Volver
                </a>


            </div>


        </form>

    </div>

</div>


<!-- =====================================================
     FOOTER
     ===================================================== -->

<?php include("includes/footer.php"); ?>


</body>

</html>