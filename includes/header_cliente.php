<?php


$iniciales =
strtoupper(substr($_SESSION['nombre'],0,1)).
strtoupper(substr($_SESSION['apellido'],0,1));
?>

<header class="header-cliente">

   <div class="logo">
        <h1>SABORA</h1>
    </div>

    <!-- Información del usuario -->
    <div class="usuario">

        <!-- AQUÍ VA EL AVATAR -->
        <div class="avatar">
            <?= $iniciales ?>
        </div>

        <!-- Datos del usuario -->
        <div class="datos">

            <div class="nombre">
                <?= $_SESSION['nombre']." ".$_SESSION['apellido']; ?>
            </div>

            <div class="correo">
                <?= $_SESSION['correo']; ?>
            </div>

            <div class="rol">
                <?= $_SESSION['rol']; ?>
            </div>

        </div>

    </div>

</header>