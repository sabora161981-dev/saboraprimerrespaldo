<?php
session_start();

include("conexion.php");

$iniciales =
strtoupper(substr($_SESSION['nombre'],0,1)).
strtoupper(substr($_SESSION['apellido'],0,1));

// Verificar si existe una sesión iniciada
if (!isset($_SESSION['correo'])) {
    header("Location: Cliente.html");
    exit();
}

// Verificar que el usuario sea cliente
if (strtolower($_SESSION['rol']) != "cliente") {
    header("Location: Cliente.html");
    exit();
}
$sql = "SELECT * FROM producto WHERE estado='Activo'";

$resultado = mysqli_query($conexion, $sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pedidos - Sabora</title>

<style>

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
    font-family:'Segoe UI',sans-serif;
}

body{
    background:#efe7e2;
}

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

.avatar{

    width:55px;

    height:55px;

    border-radius:50%;

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

.container{
    width:95%;
    max-width:1200px;
    margin:40px auto;
}

.tarjeta{
    background:white;
    border-radius:15px;
    padding:30px;
    box-shadow:0 5px 15px rgba(0,0,0,.1);
    margin-bottom:30px;
}

.tarjeta h2{
    color:#ff7a3d;
    margin-bottom:20px;
}

.tarjeta p{
    margin:12px 0;
    font-size:17px;
}

.menu{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:20px;
}

.opcion{
    background:white;
    border-radius:15px;
    padding:25px;
    text-align:center;
    box-shadow:0 5px 10px rgba(0,0,0,.1);
    transition:.3s;
}

.opcion:hover{
    transform:translateY(-5px);
}

.opcion h3{
    color:#ff7a3d;
    margin-bottom:15px;
}

.opcion a{
    text-decoration:none;
    color:white;
    background:#ff7a3d;
    padding:10px 20px;
    border-radius:8px;
    display:inline-block;
}

.opcion a:hover{
    background:#e5672f;
}

.logout{
    margin-top:40px;
    display:flex;
    justify-content:center;
    align-items:center;
    gap:40px;      /* Espacio entre los botones */
}

.logout a{
    background:#ff7a3d;
    color:white;
    text-decoration:none;
    padding:12px 30px;
    border-radius:10px;
}

.logout a:hover{
    background:#ff7a3d;
    }

/* Footer */

footer{
   text-align:center;

    color:#666;

    margin:40px;

    font-size:15px;
;
}

</style>

</head>

<body>

<header>

   <h1>SABORA</h1>

    <div class="usuario">

        <div class="avatar">

            <?php echo $iniciales; ?>

        </div>

        <strong>

            <?php echo $_SESSION['nombre']." ".$_SESSION['apellido']; ?>

        </strong>

        <br>

        <?php echo $_SESSION['correo']; ?>

        <br>

        Rol:

        <strong>

            <?php echo ucfirst($_SESSION['rol']); ?>

        </strong>

    </div>
</header>

<div class="container">

    <div class="tarjeta">
  <h2>🛒 Realizar Pedido</h2>      
<p>
Seleccione los productos que desea agregar a su pedido.
</p>

<p>
Aquí podrá consultar el menú disponible y confirmar su compra.
</p>
    </div>

    <div class="tarjeta">

    <h2>🍔 Productos Disponibles</h2>

    <p>
        Aquí aparecerán los productos disponibles del restaurante.
    </p>

    <p>
        En la siguiente etapa se mostrarán automáticamente desde la base de datos.
    </p>

</div>

    <div class="logout">

      <a href="#">
        Confirmar Pedido
    </a>

    <a href="Cliente.php">
        Volver al Panel
    </a>

    </div>

</div>
<footer>
    Sistema de Gestión Restaurante <strong>SABORA</strong><br>
    Panel exclusivo para Clientes.<br>
    &copy; 2026 - Todos los derechos reservados.
</footer>
</body>
</html>