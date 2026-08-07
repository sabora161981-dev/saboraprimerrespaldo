<?php
session_start();
include("conexion.php");

// Consultar productos
$sql = "SELECT * FROM producto";
$resultado = mysqli_query($conexion, $sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Productos | Sabora</title>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<link rel="stylesheet" href="productos.css">

</head>

<body>

<div class="contenedor">

<h1>🍔 Gestión de Productos</h1>

<div class="barra">

<a href="Administrador.php" class="volver">
← Volver
</a>

<a href="nuevo_producto.php" class="nuevo">
+ Nuevo Producto
</a>

</div>

<table>

<thead>

<tr>

<th>ID</th>
<th>Imagen</th>
<th>Nombre</th>
<th>Categoría</th>
<th>Precio</th>
<th>Stock</th>
<th>Estado</th>
<th>Acciones</th>

</tr>

</thead>

<tbody>

<?php while($fila=mysqli_fetch_assoc($resultado)){ ?>

<tr>

<td><?= $fila['idProducto']; ?></td>
<!-- Imagen del producto -->
<td>

<?php
if (!empty($fila['imagen'])) {
?>

    <img src="<?= htmlspecialchars($fila['imagen']); ?>"
    width="70" 
    height="70"
    style="object-fit:contain;
        border-radius:8px;
        background:white;
        padding:4px;"
        
     alt="<?= htmlspecialchars($fila['nombre']); ?>">
<?php }else{ 
    ?>

    <img src="img/productos/sin_foto.png" 
    width="70" 
    height="70"
    style="border-radius:8px; object-fit:cover;">

<?php

}

?>

</td>

<td><?= $fila['nombre']; ?></td>

<td><?= $fila['categoria']; ?></td>

<td>$<?= number_format($fila['precio'],0,',','.'); ?></td>

<td><?= $fila['stock']; ?></td>

<td><?= $fila['estado']; ?></td>



<td class="acciones">

    <a href="editar_producto.php?id=<?= $fila['idProducto']; ?>" class="editar">
        <i class="bi bi-pencil-square"></i>
    </a>

    <a href="eliminar_producto.php?id=<?= $fila['idProducto']; ?>" class="eliminar">
        <i class="bi bi-trash"></i>
    </a>

</td>
</tr>

<?php } ?>

</tbody>

</table>

</div>

</body>

</html>