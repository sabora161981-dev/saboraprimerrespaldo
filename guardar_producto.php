<?php
session_start();
include("conexion.php");

//=========================
// RECIBIR DATOS
//=========================

$nombre             = $_POST['nombre'];
$descripcion        = $_POST['descripcion'];
$categoria          = $_POST['categoria'];
$precio             = $_POST['precio'];
$stock              = $_POST['stock'];
$fechaCompra        = $_POST['fechaCompra'];
$fechaVencimiento   = $_POST['fechaVencimiento'];
$almacenamiento     = $_POST['almacenamiento'];
$estado             = $_POST['estado'];

//=========================
// SUBIR IMAGEN
//=========================

$imagen = "";

if(isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0){

    $nombreImagen = time() . "_" . basename($_FILES['imagen']['name']);

    $rutaDestino = "img/productos/" . $nombreImagen;

    if(move_uploaded_file($_FILES['imagen']['tmp_name'], $rutaDestino)){
        $imagen = $rutaDestino;
    }

}

//=========================
// GUARDAR EN LA BD
//=========================

$sql = "INSERT INTO producto
(nombre,descripcion,categoria,precio,stock,fechaCompra,fechaVencimiento,almacenamiento,estado,imagen)

VALUES

('$nombre',
'$descripcion',
'$categoria',
'$precio',
'$stock',
'$fechaCompra',
'$fechaVencimiento',
'$almacenamiento',
'$estado',
'$imagen')";

if(mysqli_query($conexion,$sql)){

    header("Location: productos.php");
    exit();

}else{

    echo "Error: " . mysqli_error($conexion);

}
?>