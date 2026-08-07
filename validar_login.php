<?php
session_start();

include("conexion.php");

$correo = trim($_POST['correo']);
$clave  = trim($_POST['clave']);
$tipo_login = strtolower(trim($_POST['tipo_login']));

$sql = "SELECT * FROM usuarios
        WHERE correo='$correo'
        AND clave='$clave'";

$resultado = mysqli_query($conexion, $sql);

if(mysqli_num_rows($resultado) > 0){

    $usuario = mysqli_fetch_assoc($resultado);

    $_SESSION['id'] = $usuario['id'];
    $_SESSION['nombre'] = $usuario['nombre'];
    $_SESSION['apellido'] = $usuario['apellido'];
    $_SESSION['correo'] = $usuario['correo'];

    $rol = strtolower(trim($usuario['rol']));
    $_SESSION['rol'] = $rol;

if ($tipo_login != $rol) {

    echo "<script>
            alert('Estas credenciales no pertenecen al tipo de usuario seleccionado.');
            window.history.back();
          </script>";
    exit();
}
    switch($rol){

        case "administrador":
            header("Location: Administrador.php");
            exit();

        case "cliente":
            header("Location: Cliente.php");
            exit();

        case "cajero":
            header("Location: Cajero.php");
            exit();

        case "contador":
            header("Location: Contador.php");
            exit();

        case "proveedor":
            header("Location: Proveedor.php");
            exit();

        default:
            echo "<script>
                    alert('Rol de usuario no válido.');
                    window.location='Cliente.html';
                  </script>";
            exit();
    }

}else{

    echo "<script>
            alert('Correo o contraseña incorrectos.');
            window.location='Cliente.html';
          </script>";
    exit();

}
?>