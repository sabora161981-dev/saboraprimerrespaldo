<?php
session_start();
include("conexion.php");

//==============================
// PEDIDO ACTUAL
//==============================

if (!isset($_SESSION['pedido'])) {
    $_SESSION['pedido'] = [];
}

$cantidadProductos = count($_SESSION['pedido']);

//==============================
// VALIDAR SESION
//==============================

if (!isset($_SESSION['correo'])) {
    header("Location: Cliente.html");
    exit();
}

if (strtolower($_SESSION['rol']) != "cliente") {
    header("Location: Cliente.html");
    exit();
}
//==============================
// INICIALES DEL AVATAR
//==============================

$iniciales =
strtoupper(substr($_SESSION['nombre'],0,1)).
strtoupper(substr($_SESSION['apellido'],0,1));

//==============================
// DATOS DEL USUARIO
//==============================

$nombre = $_SESSION['nombre'];
$apellido = $_SESSION['apellido'];

//==============================
// CONSULTAR PRODUCTOS
//==============================

$sql = "SELECT *
        FROM producto
        WHERE estado='Activo'
        AND stock>0
        ORDER BY categoria,nombre";

$resultado = mysqli_query($conexion,$sql);
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Menú - Sabora</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    background:#efe6df;
}

/*==============================*/
/* HEADER */
/*==============================*/

header{

    background:#ff7a3d;
    color:white;
    padding:20px;
    display:flex;
    justify-content:space-between;
    align-items:center;

}

header h1{
    font-size:28px;
}

.usuario{

    text-align:right;
    font-size:15px;

}
/*============avatar superrior==================*/
.avatar{

    width:55px;
    height:55px;

    border-radius:0%;

    background:white;
    color:#ff7a3d;


    display:flex;
    justify-content:center;
    align-items:center;


    font-size:22px;
    font-weight:bold;
    

    margin-left:auto;
    margin-bottom:10px;

    box-shadow:0 3px 8px rgba(0,0,0,.15);

}

/*==============================*/

.contenedor{

    width:95%;
    max-width:1200px;
    margin:40px auto;

}

.titulo{

    color:#ff6b35;
    margin-bottom:30px;

}

/*==============================*/
.pedido-actual{

    background:white;
    border-radius:15px;
    padding:20px;
    margin-bottom:30px;

    box-shadow:0 4px 12px rgba(0,0,0,.15);

}

.pedido-actual h3{

    color:#ff6b35;
    margin-bottom:10px;

}

.pedido-actual p{

    font-size:18px;

}


.productos{

    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(260px,1fr));
    gap:25px;

}

/*==============================*/

.card{

    background:white;
    border-radius:15px;
    padding: 20px;

    box-shadow:0 4px 12px rgba(0,0,0,.15);

    transition:.3s;

    display:flex;
    flex-direction:column;

    min-height:480px; /* puedes ajustar entre 350 y 380 */

}

/*==============================*/
/* IMAGEN DEL PRODUCTO */
/*==============================*/

.imagen-producto{

    width:100%;
    height:180px;

    margin-bottom:15px;

    border-radius:12px;

    overflow:hidden;

}

.imagen-producto img{

    width:100%;
    height:100%;

    object-fit:contain;

}

.sin-imagen{

    width:100%;
    height:100%;

    display:flex;
    justify-content:center;
    align-items:center;

    border:2px dashed #d9d9d9;

    border-radius:12px;

    background:#f8f8f8;

    color:#999;

    font-size:18px;

    font-weight:bold;

}

.card form{
    margin-top:auto;
    width:100%;
}

.card:hover{

    transform:translateY(-5px);

}

.card h2{

    color:#ff6b35;
    font-size:20px;

    height:52px;

    display:flex;
    align-items:flex-start;

    margin-bottom:10px;
}

.descripcion{

    color:#666;
    height:50px;      /* misma altura para todos */
    overflow:hidden;
    margin-bottom:15px
}

.info{

    margin:8px 0;

}

.precio{

    font-size:22px;
    font-weight:bold;
    color:#2e7d32;

}

.stock{

    color:#555;

}

.categoria{

    color:#777;

}



button{

    width:100%;
    margin-top:20px;

    padding:12px;

    border:none;
    border-radius:8px;

    background:#ff6b35;
    color:white;

    cursor:pointer;

    font-size:15px;

}

button:hover{

    background:#e85a2a;

}

/*==============================*/

.botones{

    margin-top:40px;
    text-align:center;

}

.botones a{

    text-decoration:none;

}

.volver{


    width:220px;


}
/*==============================*/
/* FOOTER */
/*==============================*/

footer{

    text-align:center;
    color:#666;
    font-size:15px;
    margin:40px;



}

/*========logo superior con avatar desde 260 hasta linea 294======================*/
/*==================================
BOTÓN FLOTANTE PEDIDO
==================================*/

#pedidoFlotante{

    position:fixed;

    right:25px;
    bottom:25px;

    width:70px;
    height:70px;

    background:#ff6b35;

    border-radius:0;

    display:flex;
    justify-content:center;
    align-items:center;

    cursor:pointer;

    box-shadow:0 8px 20px rgba(0,0,0,.30);

    z-index:9999;

     transition:
        transform .2s ease,
        box-shadow .2s ease;

    user-select:none;

}

#pedidoFlotante:hover{

    transform:scale(1.08);

}

.icono{

    font-size:30px;
    color:white;

}

#contadorPedido{

    position:absolute;

    top:-4px;

    right:-4px;

    width:24px;

    height:24px;

    background:#d32f2f;

    color:white;

    border-radius:50%;

    display:flex;

    justify-content:center;

    align-items:center;

    font-size:12px;

    font-weight:bold;

    border:2px solid white;

}

/*==================================
BOTÓN CERRAR PEDIDO
==================================*/

#cerrarPedido{

    position:absolute;

    top:-4px;
    left:-4px;

    width:24px;
    height:24px;

    background:#d32f2f;

    color:white;

    border-radius:50%;

    display:flex;
    justify-content:center;
    align-items:center;

    font-size:14px;

    font-weight:bold;

    cursor:pointer;

    border:2px solid white;

    box-shadow:0 2px 8px rgba(0,0,0,.30);

    transition:.2s;

}

#cerrarPedido:hover{

    background:#b71c1c;

    transform:scale(1.15);

}
</style>

</head>

<body>

<?php include("includes/header_cliente.php"); ?>

<div class="contenedor">

<h2 class="titulo">Nuestro Menú</h2>

<div class="pedido-actual">

    <h3>🛒 Pedido Actual</h3>

    <p>
        Productos seleccionados:
        <strong><?php echo $cantidadProductos; ?></strong>
    </p>

</div>

<div class="productos">

<?php

while($fila=mysqli_fetch_assoc($resultado)){

?>

<div class="card">


 <!-- FOTO DEL PRODUCTO -->

<div class="imagen-producto">

<?php

if (!empty($fila['imagen'])) {

?>

    <img
        src="<?= $fila['imagen']; ?>"
        alt="<?= $fila['nombre']; ?>">

<?php

}else{

?>

    <div class="sin-imagen">
        Sin foto
    </div>

<?php

}

?>

</div>

<h2><?php echo ucfirst($fila['nombre']); ?></h2>

<p class="descripcion">

<?php echo ucfirst($fila['descripcion']); ?>

</p>

<p class="info categoria">

Categoría:
<b><?php echo ucfirst($fila['categoria']); ?></b>

</p>

<p class="precio">

$ <?php echo number_format($fila['precio'],0,",","."); ?>

</p>

<p class="stock">

Disponibles:
<b><?php echo $fila['stock']; ?></b>

</p>

<form action="agregar_pedido.php" method="POST">

    <input
        type="hidden"
        name="id_producto"
        value="<?php echo $fila['idProducto']; ?>">

    <button type="submit">
        Agregar al pedido
    </button>

</form>
</div>

<?php

}

?>

</div>

<div class="botones">

    <button
        class="volver"
        type="button"
        onclick="window.location.href='Cliente.php';">

        Volver al Panel

    </button>

</div>

<!--=====================================
BOTÓN FLOTANTE PEDIDO
======================================-->

<div id="pedidoFlotante">

    <span id="cerrarPedido">&times;</span>

    <span class="icono">🛒</span>

    <span id="contadorPedido">
        <?php echo $cantidadProductos; ?>
    </span>

</div>

<?php include("includes/footer.php"); ?>

<script>

const boton = document.getElementById("pedidoFlotante");

let mover = false;

let offsetX = 0;
let offsetY = 0;

boton.addEventListener("mousedown", function(e){

    mover = true;

    offsetX = e.clientX - boton.offsetLeft;

    offsetY = e.clientY - boton.offsetTop;

});

document.addEventListener("mousemove", function(e){

    if(!mover) return;

    boton.style.left = (e.clientX - offsetX) + "px";
    boton.style.top = (e.clientY - offsetY) + "px";

    boton.style.right = "auto";
    boton.style.bottom = "auto";

});

document.addEventListener("mouseup", function(){

    mover = false;

});

</script>

</body>
</html>