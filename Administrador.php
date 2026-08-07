<?php
session_start();

// Verificar si existe una sesión iniciada
if (!isset($_SESSION['correo'])) {
    header("Location: Administrador.html");
    exit();
}

// Verificar que el usuario sea administrador
if (strtolower($_SESSION['rol']) != "administrador") {
    header("Location: Cliente.html");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel Administrador - Sabora</title>

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
    background:#ff6b35;
    color:white;
    padding:20px;
    display:flex;
    justify-content:space-between;
    align-items:center;
}

header h1{
    font-size:30px;
}

.usuario{
    text-align:right;
    font-size:15px;
}

.container{
    width:90%;
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
    margin:10px 0;
    font-size:17px;
}

.menu{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(240px,1fr));
    gap:20px;
}

.opcion{
    background:white;
    border-radius:15px;
    padding:25px;
    text-align:center;
    box-shadow:0 5px 15px rgba(0,0,0,.1);
    transition:.3s;
}

.opcion:hover{
    transform:translateY(-5px);
}

.opcion h3{
    color:#ff7a3d;
    margin-bottom:15px;
}

.opcion p{
    color:#555;
    font-size:15px;
    margin-bottom:20px;
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
    text-align:center;
    margin-top:40px;
}

.logout a{
    background:#ff6b35;
    color:white;
    text-decoration:none;
    padding:12px 35px;
    border-radius:10px;
}

.logout a:hover{
    background:#ff6b35;

footer{
    text-align:center;
    margin:40px;
    color:#666;
}

</style>

</head>

<body>

<header>

    <h1>SABORA</h1>

    <div class="usuario">

        <strong>
            <?php echo $_SESSION['nombre']." ".$_SESSION['apellido']; ?>
        </strong><br>

        <?php echo $_SESSION['correo']; ?><br>

        Rol:
        <strong><?php echo ucfirst($_SESSION['rol']); ?></strong>

    </div>

</header>

<div class="container">

<div class="tarjeta">

<h2>Bienvenido al Panel del Administrador</h2>

<p><strong>Nombre:</strong> <?php echo $_SESSION['nombre']; ?></p>

<p><strong>Apellido:</strong> <?php echo $_SESSION['apellido']; ?></p>

<p><strong>Correo:</strong> <?php echo $_SESSION['correo']; ?></p>

<p><strong>Rol:</strong> <?php echo ucfirst($_SESSION['rol']); ?></p>

</div>

<div class="menu">

<div class="opcion">

<h3>👥 Usuarios</h3>

<p>Administrar usuarios registrados.</p>

<a href="usuarios.php">Gestionar</a>

</div>

<div class="opcion">

<h3>🍔 Productos</h3>

<p>Registrar, editar y eliminar productos.</p>

<a href="productos.php">Administrar</a>

</div>

<div class="opcion">

<h3>📦 Inventario</h3>

<p>Control de existencias e inventario.</p>

<a href="inventario.php">Ingresar</a>

</div>

<div class="opcion">

<h3>🛒 Pedidos</h3>

<p>Administrar pedidos realizados.</p>

<a href="pedidos.php">Consultar</a>

</div>

<div class="opcion">

<h3>💵 Ventas</h3>

<p>Consultar historial de ventas.</p>

<a href="ventas.php">Ver Ventas</a>

</div>

<div class="opcion">

<h3>📊 Reportes</h3>

<p>Generar reportes administrativos.</p>

<a href="reportes.php">Generar</a>

</div>

<div class="opcion">

<h3>⚙ Configuración</h3>

<p>Configurar parámetros del sistema.</p>

<a href="configuracion.php">Abrir</a>

</div>

<div class="opcion">

<h3>👤 Mi Perfil</h3>

<p>Actualizar información personal.</p>

<a href="perfil_admin.php">Actualizar</a>

</div>

</div>

<div class="logout">

<a href="logout.php">

Cerrar Sesión

</a>

</div>

</div>

<footer style="text-align:center; color:#666; margin:40px; font-size:15px;">


Sistema de Gestión Restaurante <strong>Sabora</strong><br>

Panel exclusivo para Administradores.<br>
    &copy; 2026 - Todos los derechos reservados.

</footer>

</body>
</html>