<?php

include("conexion.php");
include("enviar_correo.php");

$nombre = $_POST['nombre'];
$apellido = $_POST['apellido'];
$identificacion = $_POST['identificacion'];
$correo = $_POST['correo'];
$direccion = $_POST['direccion'];
$clave = $_POST['clavedeRegistro'];

$token = bin2hex(random_bytes(16));

$rol = "cliente";
$verificarCorreo = "SELECT * FROM usuarios WHERE correo = '$correo'";

$resultadoCorreo = mysqli_query($conexion, $verificarCorreo);

if(mysqli_num_rows($resultadoCorreo) > 0){

    echo "Este correo ya está registrado";

    exit();
}

$sql = "INSERT INTO usuarios
(nombre, apellido, identificacion, correo, direccion, clave, rol, token)

VALUES

('$nombre', '$apellido', '$identificacion', '$correo', '$direccion', '$clave', '$rol', '$token')";

$resultado = mysqli_query($conexion, $sql);

if($resultado){

    enviarCorreo($correo, $token);

    echo "Registro exitoso. Revisa tu correo para verificar tu cuenta.";

}else{

    echo "Error: " . mysqli_error($conexion);
}

?>