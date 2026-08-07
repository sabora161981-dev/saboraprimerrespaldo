<?php
session_start();

$iniciales =
strtoupper(substr($_SESSION['nombre'],0,1)).
strtoupper(substr($_SESSION['apellido'],0,1));

// Verificar si existe una sesión iniciada
if (!isset($_SESSION['correo'])) {
    header("Location: Contador.html");
    exit();
}

// Verificar que el usuario sea contador
if (strtolower($_SESSION['rol']) != "contador") {
    header("Location: Cliente.html");
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Panel Contador - Sabora</title>

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

    display:inline-block;
    width:140px;
    text-align:center;
    text-decoration:none;
    background:#ff6b35;
    color:#fff;
    padding:12px 0;
    border-radius:8px;
    font-size:16px;
    font-weight:500;
    transition:.3s;
    box-shadow:0 3px 8px rgba(0,0,0,.15);

}

.opcion a:hover{

    background:#e85a2a;
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

    background:#e85a2a;

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

<h2>Bienvenido al Panel del Contador</h2>

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

<!-- =========================================
        INICIO DEL MENÚ
========================================= -->

<div class="menu">
 
<!-- =========================================
            REPORTE DE VENTAS
========================================== -->

<div class="opcion">

    <h3>📈 Reporte de Ventas</h3>

    <p>
        Consulte las ventas realizadas por fecha, cajero o cliente.
    </p>

    <a href="reporte_ventas.php">Consultar</a>

</div>

<!-- =========================================
            REPORTE DE COMPRAS
========================================== -->

<div class="opcion">

    <h3>📦 Reporte de Compras</h3>

    <p>
        Visualice las compras realizadas a proveedores y el historial de adquisiciones.
    </p>

    <a href="reporte_compras.php">Consultar</a>

</div>

<!-- =========================================
            BALANCE CONTABLE
========================================== -->

<div class="opcion">

    <h3>💰 Balance Contable</h3>

    <p>
        Revise los ingresos, egresos y el balance financiero del restaurante.
    </p>

    <a href="balance_contable.php">Abrir</a>

</div>

<!-- =========================================
        MOVIMIENTOS FINANCIEROS
========================================== -->

<div class="opcion">

    <h3>📄 Movimientos Financieros</h3>

    <p>
        Consulte todos los movimientos contables registrados en el sistema.
    </p>

    <a href="movimientos_financieros.php">Consultar</a>

</div>

<!-- =========================================
            MÉTODOS DE PAGO
========================================== -->

<div class="opcion">

    <h3>💳 Métodos de Pago</h3>

    <p>
        Consulte estadísticas de ventas por efectivo, tarjeta, transferencia y otros medios.
    </p>

    <a href="metodos_pago.php">Consultar</a>

</div>

<!-- =========================================
            CIERRE DE CAJA
========================================== -->

<div class="opcion">

    <h3>🧾 Cierre de Caja</h3>

    <p>
        Consulte los cierres de caja realizados por los cajeros.
    </p>

    <a href="cierres_caja.php">Consultar</a>

</div>

<!-- =========================================
          ESTADO DE RESULTADOS
========================================== -->

<div class="opcion">

    <h3>📊 Estado de Resultados</h3>

    <p>
        Visualice las utilidades, pérdidas y resultados financieros del restaurante.
    </p>

    <a href="estado_resultados.php">Ver</a>

</div>

<!-- =========================================
                REPORTES
========================================== -->

<div class="opcion">

    <h3>📑 Reportes</h3>

    <p>
        Genere reportes financieros en formato PDF o Excel.
    </p>

    <a href="reportes.php">Generar</a>

</div>

<!-- =========================================
                MI PERFIL
========================================== -->

<div class="opcion">

    <h3>👤 Mi Perfil</h3>

    <p>
        Actualice su información personal y cambie su contraseña.
    </p>

    <a href="perfil_contador.php">Actualizar</a>

</div>

<!-- =========================================
                 SOPORTE
========================================== -->

<div class="opcion">

    <h3>📞 Soporte</h3>

    <p>
        Contacte al administrador del sistema en caso de presentar inconvenientes.
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

    Panel exclusivo para el personal de Contabilidad.

    <br>

    © <?php echo date("Y"); ?> - Todos los derechos reservados.

</footer>

</body>

</html>