<?php

include("conexion.php");

if(isset($_GET['token'])){

    $token = $_GET['token'];

    $sql = "UPDATE usuarios
            SET verificado = 1
            WHERE token = '$token'";

    $resultado = mysqli_query($conexion, $sql);

    if($resultado){

        echo "Cuenta verificada correctamente";

    }else{

        echo "Error al verificar";
    }
}

?>