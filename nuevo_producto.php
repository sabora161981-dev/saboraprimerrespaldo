<?php
session_start();
?>

<!DOCTYPE html>
<html lang="es">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Nuevo Producto | Sabora</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link rel="stylesheet" href="productos.css">

</head>

<body>

<div class="contenedor">

    <h1>🍔 Registrar Producto</h1>

    <div class="barra">

        <a href="productos.php" class="volver">
            ← Volver
        </a>

    </div>

    <form action="guardar_producto.php"
          method="POST"
          enctype="multipart/form-data"
          class="formulario">

        <label>Nombre</label>
        <input type="text" name="nombre" required>

        <label>Descripción</label>
        <textarea name="descripcion" rows="3"></textarea>

        <label>Categoría</label>
        <input type="text" name="categoria" required>

        <label>Precio</label>
        <input type="number" name="precio" min="0" required>

        <label>Stock</label>
        <input type="number" name="stock" min="0" required>

        <label>Fecha de compra</label>
        <input type="date" name="fechaCompra">

        <label>Fecha de vencimiento</label>
        <input type="date" name="fechaVencimiento">

        <label>Almacenamiento</label>
        <input type="text" name="almacenamiento">

        <label>Estado</label>

        <select name="estado">

            <option value="Activo">Activo</option>
            <option value="Inactivo">Inactivo</option>

        </select>

        <label>Imagen del producto</label>

        <input type="file"
               name="imagen"
               accept=".jpg,.jpeg,.png,.webp">

        <br><br>

        <button type="submit" class="nuevo">
            <i class="bi bi-floppy"></i>
            Guardar Producto
        </button>

    </form>

</div>

</body>

</html>