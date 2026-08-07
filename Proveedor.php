<?php
session_start();
include("conexion.php");

//========================================
// VALIDAR SESIÓN
//========================================
if (!isset($_SESSION['correo'])) {
    header("Location: Proveedor.html");
    exit();
}

if (strtolower($_SESSION['rol']) != "proveedor") {
    header("Location: Cliente.html");
    exit();
}

//========================================
// DATOS DEL USUARIO
//========================================
$nombre   = $_SESSION['nombre'];
$apellido = $_SESSION['apellido'];

$iniciales =
strtoupper(substr($nombre,0,1)).
strtoupper(substr($apellido,0,1));

//========================================
// ESTADÍSTICAS
//========================================

// Total productos
$sql="SELECT COUNT(*) total FROM producto";
$res=mysqli_query($conexion,$sql);
$total=mysqli_fetch_assoc($res)['total'];

// Bajo stock
$sql="SELECT COUNT(*) total
FROM producto
WHERE stock BETWEEN 1 AND 10";

$res=mysqli_query($conexion,$sql);
$bajo=mysqli_fetch_assoc($res)['total'];

// Agotados
$sql="SELECT COUNT(*) total
FROM producto
WHERE stock=0";

$res=mysqli_query($conexion,$sql);
$agotados=mysqli_fetch_assoc($res)['total'];

// Productos
$sql="SELECT *
FROM producto
ORDER BY nombre ASC";

$productos=mysqli_query($conexion,$sql);

?>

<!DOCTYPE html>

<html lang="es">

<head>

<meta charset="UTF-8">

<meta
name="viewport"
content="width=device-width, initial-scale=1.0">

<title>

Proveedor | Sabora

</title>

<link rel="stylesheet"
href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>

/*==================================================
RESET
==================================================*/

*{

margin:0;
padding:0;
box-sizing:border-box;
font-family:'Segoe UI',sans-serif;

}

body{

background:#efe6df;
display:flex;
min-height:100vh;
color:#333;

}

/*==================================================
SIDEBAR
==================================================*/

.sidebar{

width:260px;
background:#ff6b35;
position:fixed;
top:0;
left:0;
bottom:0;
padding:25px 20px;
color:white;
box-shadow:4px 0 15px rgba(0,0,0,.08);

}

.logo{

text-align:center;
margin-bottom:40px;

}

.logo h2{

font-size:30px;
letter-spacing:2px;

}

.logo p{

font-size:13px;
opacity:.9;

}

.sidebar ul{

list-style:none;

}

.sidebar li{

margin-bottom:12px;

}

.sidebar li a{

display:flex;
align-items:center;
gap:12px;

padding:14px 16px;

border-radius:12px;

color:white;

text-decoration:none;

transition:.3s;

font-size:15px;

}

.sidebar li a:hover{

background:#e85a2a;

}

.sidebar li.activo a{

background:#d95827;

}

.sidebar i{

width:22px;
text-align:center;

}

/*==================================================
CONTENIDO
==================================================*/

.contenido{

margin-left:260px;

width:calc(100% - 260px);

padding:30px;

}

/*==================================================
HEADER
==================================================*/

header{

display:flex;

justify-content:space-between;

align-items:center;

margin-bottom:35px;

}

header h1{

font-size:30px;

color:#333;

margin-bottom:5px;

}

header p{

color:#777;

}

.usuario{

display:flex;

align-items:center;

gap:15px;

}

.avatar{

width:60px;

height:60px;

border-radius:50%;

background:#ff6b35;

display:flex;

justify-content:center;

align-items:center;

color:white;

font-size:22px;

font-weight:bold;

box-shadow:0 5px 15px rgba(0,0,0,.15);

}

/*==================================================
TARJETAS
==================================================*/

.tarjetas{

display:grid;

grid-template-columns:repeat(auto-fit,minmax(220px,1fr));

gap:20px;

margin-bottom:30px;

}

.card{

background:white;

padding:25px;

border-radius:15px;

box-shadow:0 8px 18px rgba(0,0,0,.08);

transition:.3s;

}

.card:hover{

transform:translateY(-6px);

}

.card i{

font-size:35px;

color:#ff6b35;

margin-bottom:15px;

}

.card h3{

font-size:17px;

margin-bottom:8px;

color:#666;

}

.card h2{

font-size:34px;

color:#333;

}

/*==================================================
BUSCADOR
==================================================*/

.buscador{

margin-bottom:25px;

}

.buscador input{

width:100%;

padding:15px;

border-radius:10px;

border:1px solid #ddd;

font-size:15px;

transition:.3s;

}

.buscador input:focus{

outline:none;

border-color:#ff6b35;

box-shadow:0 0 8px rgba(255,107,53,.25);

}
</style>

</head>

<body>

<!--====================================
SIDEBAR
=====================================-->

<div class="sidebar">

<div class="logo">

<h2>SABORA</h2>

<p>Panel del Proveedor</p>

</div>

<ul>

<li class="activo">

<a href="#">

<i class="fa-solid fa-chart-line"></i>

<span>Dashboard</span>

</a>

</li>

<li>

<a href="#productos">

<i class="fa-solid fa-box"></i>

<span>Productos</span>

</a>

</li>

<li>

<a href="#perfil">

<i class="fa-solid fa-user"></i>

<span>Mi Perfil</span>

</a>

</li>

<li>

<a href="cerrar.php">

<i class="fa-solid fa-right-from-bracket"></i>

<span>Cerrar sesión</span>

</a>

</li>

</ul>

</div>

<!--====================================
INICIO CONTENIDO
=====================================-->

<div class="contenido">
<!--====================================
ENCABEZADO
=====================================-->

<header>

    <div>

        <h1>Panel del Proveedor</h1>

        <p>
            Bienvenido
            <strong><?php echo $nombre; ?></strong>,
            aquí puedes consultar el inventario disponible.
        </p>

    </div>

    <div class="usuario">

        <div style="text-align:right;">

            <strong>
                <?php echo $nombre." ".$apellido; ?>
            </strong>

            <br>

            <small style="color:#777;">
                Proveedor
            </small>

        </div>

        <div class="avatar">

            <?php echo $iniciales; ?>

        </div>

    </div>

</header>


<!--====================================
TARJETAS
=====================================-->

<section class="tarjetas">

    <div class="card">

        <i class="fa-solid fa-box"></i>

        <h3>Total Productos</h3>

        <h2>

            <?php echo $total; ?>

        </h2>

    </div>


    <div class="card">

        <i class="fa-solid fa-triangle-exclamation"></i>

        <h3>Bajo Stock</h3>

        <h2>

            <?php echo $bajo; ?>

        </h2>

    </div>


    <div class="card">

        <i class="fa-solid fa-circle-xmark"></i>

        <h3>Agotados</h3>

        <h2>

            <?php echo $agotados; ?>

        </h2>

    </div>

</section>


<!--====================================
BUSCADOR
=====================================-->

<div class="buscador">

<input
type="text"
id="buscar"
placeholder="Buscar producto por nombre o categoría...">

</div>


<!--====================================
TABLA PRODUCTOS
=====================================-->

<div class="tabla">

<table id="tablaProductos">

<thead>

<tr>

<th>ID</th>

<th>Producto</th>

<th>Categoría</th>

<th>Precio</th>

<th>Stock</th>

<th>Estado</th>

</tr>

</thead>

<tbody>

<?php

while($fila=mysqli_fetch_assoc($productos)){

    $estado="Disponible";
    $clase="verde";

    if($fila['stock']==0){

        $estado="Agotado";
        $clase="rojo";

    }

    elseif($fila['stock']<=10){

        $estado="Bajo Stock";
        $clase="amarillo";

    }

?>

<tr>

<td>

<?php echo $fila['idProducto']; ?>

</td>

<td>

<?php echo $fila['nombre']; ?>

</td>

<td>

<?php echo $fila['categoria']; ?>

</td>

<td>

$

<?php echo number_format($fila['precio'],0,",","."); ?>

</td>

<td>

<?php echo $fila['stock']; ?>

</td>

<td>

<span class="<?php echo $clase; ?>">

<?php echo $estado; ?>

</span>

</td>

</tr>

<?php

}

?>

</tbody>

</table>

</div>  
<style>

/*=========================================
TABLA
=========================================*/

.tabla{

background:#fff;

border-radius:15px;

overflow:hidden;

box-shadow:0 8px 18px rgba(0,0,0,.08);

}

table{

width:100%;

border-collapse:collapse;

}

thead{

background:#ff6b35;

color:white;

}

th{

padding:16px;

text-align:center;

font-size:15px;

}

td{

padding:14px;

text-align:center;

border-bottom:1px solid #eee;

}

tbody tr{

transition:.3s;

}

tbody tr:hover{

background:#fff7f2;

}

/*=========================================
ESTADOS
=========================================*/

.verde{

background:#d1fae5;

color:#166534;

padding:7px 15px;

border-radius:20px;

font-weight:bold;

display:inline-block;

min-width:110px;

}

.amarillo{

background:#fef3c7;

color:#92400e;

padding:7px 15px;

border-radius:20px;

font-weight:bold;

display:inline-block;

min-width:110px;

}

.rojo{

background:#fee2e2;

color:#991b1b;

padding:7px 15px;

border-radius:20px;

font-weight:bold;

display:inline-block;

min-width:110px;

}

/*=========================================
SCROLL TABLA
=========================================*/

.tabla{

overflow-x:auto;

}

/*=========================================
RESPONSIVE
=========================================*/

@media(max-width:992px){

.sidebar{

width:80px;

}

.logo h2,
.logo p{

display:none;

}

.sidebar li a{

justify-content:center;

}

.sidebar li span{

display:none;

}

.contenido{

margin-left:80px;

width:calc(100% - 80px);

}

header{

flex-direction:column;

align-items:flex-start;

gap:20px;

}

.usuario{

width:100%;

justify-content:space-between;

}

}

@media(max-width:768px){

.tarjetas{

grid-template-columns:1fr;

}

header h1{

font-size:25px;

}

table{

font-size:13px;

}

th,
td{

padding:10px;

}

.avatar{

width:50px;

height:50px;

font-size:18px;

}

}

@media(max-width:576px){

body{

font-size:14px;

}

.sidebar{

position:relative;

width:100%;

height:auto;

}

.contenido{

margin-left:0;

width:100%;

padding:20px;

}

header{

margin-top:20px;

}

}

</style>

<script>

/*=========================================
BUSCADOR
=========================================*/

const buscar=document.getElementById("buscar");

buscar.addEventListener("keyup",function(){

let texto=this.value.toLowerCase();

let filas=document.querySelectorAll("#tablaProductos tbody tr");

filas.forEach(function(fila){

let contenido=fila.textContent.toLowerCase();

if(contenido.indexOf(texto)>-1){

fila.style.display="";

}else{

fila.style.display="none";

}

});

});

/*=========================================
EFECTO TARJETAS
=========================================*/

const tarjetas=document.querySelectorAll(".card");

tarjetas.forEach(function(card){

card.addEventListener("mouseenter",function(){

this.style.transform="translateY(-8px)";
this.style.transition=".3s";

});

card.addEventListener("mouseleave",function(){

this.style.transform="translateY(0px)";

});

});

</script>

</div>

</body>

</html>