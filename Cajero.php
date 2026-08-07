<?php
session_start();

// Verificar si existe una sesión iniciada
if (!isset($_SESSION['correo'])) {
    header("Location: Cajero.html");
    exit();
}

// Verificar que el usuario sea cajero
if (strtolower($_SESSION['rol']) != "cajero") {
    header("Location: Cliente.html");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Panel Cajero - Sabora</title>

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

/*======================
        HEADER
=======================*/

header{
    background:#ff6b35;
    color:white;
    padding:20px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    box-shadow:0 3px 10px rgba(0,0,0,.15);
}

header h1{
    font-size:30px;
}

.usuario{
    text-align:right;
    font-size:15px;
    line-height:24px;
}

/*======================
      CONTENEDOR
=======================*/

.container{
    width:90%;
    max-width:1250px;
    margin:35px auto;
}

/*======================
 TARJETA BIENVENIDA
=======================*/

.tarjeta{
    background:white;
    border-radius:15px;
    padding:30px;
    margin-bottom:35px;
    box-shadow:0 5px 15px rgba(0,0,0,.12);
}

.tarjeta h2{
    color:#ff7a3d;
    margin-bottom:20px;
}
#ff7a3d
.tarjeta p{
    margin:10px 0;
    font-size:17px;
}

/*======================
      MENÚ
=======================*/

.menu{

    display:grid;

    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));

    gap:25px;

}

.opcion{

    background:white;

    border-radius:15px;

    padding:25px;

    text-align:center;

    box-shadow:0 5px 15px rgba(0,0,0,.10);

    transition:.3s;

}

.opcion:hover{

    transform:translateY(-6px);

}

.opcion h3{

   color:#ff6b35;
    font-weight:bold;
    margin-bottom:15px;
}

.opcion p{

    color:#555;

    margin-bottom:20px;

    font-size:15px;

    min-height:55px;

}


.opcion a{
    display: inline-block;
    width: 120px;              /* Todos los botones del mismo ancho */
    text-align: center;        /* Centra el texto */
    text-decoration: none;
    background: #ff6b35;       /* Naranja */
    color: #fff;               /* Texto blanco */
    padding: 12px 0;           /* Alto del botón */
    border-radius: 8px;        /* Esquinas redondeadas */
    font-size: 17px;
    font-weight: 500;
    transition: all .3s ease;
    box-shadow: 0 3px 8px rgba(0,0,0,.15);
}
/* boton cerrar sesion*/
.opcion a:hover{

  background:#ff6b35;
    color:white;
}
/*======================
 BOTÓN CERRAR SESIÓN
=======================*/

.logout{

    margin-top:45px;

    text-align:center;

}

.logout a{
     display:inline-block;

    background:#ff6b35; 

    color:white;

    padding:12px 35px;

    text-decoration:none;

    border-radius:10px;

    transition:.3s;

}

.logout a:hover{

    background:#ff6b35; 

}

/*======================
       FOOTER
=======================*/

footer{

    text-align:center;

    color:#666;

    margin:40px;

    font-size:15px;

}

</style>

</head>

<body>

<header>

<h1>SABORA</h1>

<div class="usuario">

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

<h2>Bienvenido al Panel del Cajero</h2>

<p>

<strong>Nombre:</strong>

<?php echo $_SESSION['nombre']; ?>

</p>

<p>

<strong>Apellido:</strong>

<?php echo $_SESSION['apellido']; ?>

</p>

<p>

<strong>Correo:</strong>

<?php echo $_SESSION['correo']; ?>

</p>

<p>

<strong>Rol:</strong>

<?php echo ucfirst($_SESSION['rol']); ?>

</p>

</div>

<!-- =============================
        INICIO DEL MENÚ
============================= -->

<div class="menu">
 <!-- =========================================
                NUEVA VENTA
========================================== -->

<div class="opcion">

    <h3>🛒 Nueva Venta</h3>

    <p>
        Registrar pedidos, calcular el total y generar la venta.
    </p>

    <a href="nueva_venta.php">Abrir</a>

</div>

<!-- =========================================
                PRODUCTOS
========================================== -->

<div class="opcion">

    <h3>🍔 Productos</h3>

    <p>
        Consultar el catálogo de productos disponibles.
    </p>

    <a href="productos.php">Consultar</a>

</div>

<!-- =========================================
                CLIENTES
========================================== -->

<div class="opcion">

    <h3>👥 Clientes</h3>

    <p>
        Buscar clientes registrados para asociarlos a la venta.
    </p>

    <a href="clientes.php">Consultar</a>

</div>

<!-- =========================================
                FACTURACIÓN
========================================== -->

<div class="opcion">

    <h3>🧾 Facturación</h3>

    <p>
        Generar, consultar e imprimir facturas.
    </p>

    <a href="facturas.php">Facturar</a>

</div>

<!-- =========================================
            APERTURA DE CAJA
========================================== -->

<div class="opcion">

    <h3>💵 Apertura de Caja</h3>

    <p>
        Registrar el dinero inicial para comenzar el turno.
    </p>

    <a href="apertura_caja.php">Abrir Caja</a>

</div>

<!-- =========================================
            CIERRE PARCIAL
========================================== -->

<div class="opcion">

    <h3>📊 Cierre Parcial</h3>

    <p>
        Consultar el dinero recaudado sin cerrar la caja.
    </p>

    <a href="cierre_parcial.php">Consultar</a>

</div>

<!-- =========================================
            CIERRE DE CAJA
========================================== -->

<div class="opcion">

    <h3>🔒 Cierre de Caja</h3>

    <p>
        Finalizar el turno y generar el cierre definitivo.
    </p>

    <a href="cierre_caja.php">Cerrar Caja</a>

</div>

<!-- =========================================
          HISTORIAL DE VENTAS
========================================== -->

<div class="opcion">

    <h3>📋 Historial de Ventas</h3>

    <p>
        Consultar las ventas realizadas durante el turno.
    </p>

    <a href="historial_ventas.php">Ver Historial</a>

</div>

<!-- =========================================
                MI PERFIL
========================================== -->

<div class="opcion">

    <h3>👤 Mi Perfil</h3>

    <p>
        Actualizar información personal y contraseña.
    </p>

    <a href="perfil_cajero.php">Actualizar</a>

</div>

<!-- =========================================
                 SOPORTE
========================================== -->

<div class="opcion">

    <h3>📞 Soporte</h3>

    <p>
        Contactar al administrador del sistema en caso de inconvenientes.
    </p>

    <a href="soporte.php">Abrir</a>

</div>

</div>

<!-- =========================================
          BOTÓN CERRAR SESIÓN
========================================== -->

<div class="logout">

    <a href="logout.php">

        Cerrar Sesión

    </a>

</div>

</div>

<!-- =========================================
                FOOTER
========================================== -->

<footer>

    <strong>Sistema de Gestión Restaurante SABORA</strong>

    <br><br>

    Panel exclusivo para el personal de Caja.

    <br>

    © <?php echo date("Y"); ?> - Todos los derechos reservados.

</footer>

</body>

</html>   