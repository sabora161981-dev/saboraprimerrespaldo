<?php

header("Content-Type: application/json; charset=UTF-8");

include("../conexion.php");

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo == "GET") {

    $sql = "SELECT * FROM producto";

    $resultado = mysqli_query($conexion, $sql);

    if (!$resultado) {
        echo json_encode(["error" => mysqli_error($conexion)]);
        exit();
    }

    $datos = array();

    while($fila = mysqli_fetch_assoc($resultado)){
        $datos[] = $fila;
    }

    echo json_encode($datos, JSON_PRETTY_PRINT);

}

elseif ($metodo == "POST") {

    $datos = json_decode(file_get_contents("php://input"), true);

    $nombre             = $datos["nombre"];
    $descripcion        = $datos["descripcion"];
    $categoria          = $datos["categoria"];
    $precio             = $datos["precio"];
    $stock              = $datos["stock"];
    $fechaCompra        = $datos["fechaCompra"];
    $fechaVencimiento   = $datos["fechaVencimiento"];
    $almacenamiento     = $datos["almacenamiento"];
    $estado             = $datos["estado"];

    $sql = "INSERT INTO producto
    (nombre,descripcion,categoria,precio,stock,fechaCompra,fechaVencimiento,almacenamiento,estado)
    VALUES
    ('$nombre',
    '$descripcion',
    '$categoria',
    '$precio',
    '$stock',
    '$fechaCompra',
    '$fechaVencimiento',
    '$almacenamiento',
    '$estado')";

    if(mysqli_query($conexion,$sql)){

        echo json_encode([
            "mensaje"=>"Producto registrado correctamente."
        ]);

    }else{

        echo json_encode([
            "error"=>mysqli_error($conexion)
        ]);

    }

}

elseif ($metodo == "PUT") {

    $datos = json_decode(file_get_contents("php://input"), true);

    $idProducto = $datos["idProducto"];
    $nombre = $datos["nombre"];
    $descripcion = $datos["descripcion"];
    $categoria = $datos["categoria"];
    $precio = $datos["precio"];
    $stock = $datos["stock"];
    $fechaCompra = $datos["fechaCompra"];
    $fechaVencimiento = $datos["fechaVencimiento"];
    $almacenamiento = $datos["almacenamiento"];
    $estado = $datos["estado"];

    $sql = "UPDATE producto SET
            nombre='$nombre',
            descripcion='$descripcion',
            categoria='$categoria',
            precio='$precio',
            stock='$stock',
            fechaCompra='$fechaCompra',
            fechaVencimiento='$fechaVencimiento',
            almacenamiento='$almacenamiento',
            estado='$estado'
            WHERE idProducto='$idProducto'";

    if(mysqli_query($conexion,$sql)){

        echo json_encode([
            "mensaje"=>"Producto actualizado correctamente."
        ]);

    }else{

        echo json_encode([
            "error"=>mysqli_error($conexion)
        ]);

    }

}

elseif ($metodo == "DELETE") {

    $datos = json_decode(file_get_contents("php://input"), true);

    $idProducto = $datos["idProducto"];

    $sql = "DELETE FROM producto WHERE idProducto='$idProducto'";

    if(mysqli_query($conexion,$sql)){

        echo json_encode([
            "mensaje"=>"Producto eliminado correctamente."
        ]);
    }

}

    else{

        echo json_encode([
            "error"=>mysqli_error($conexion)
          ]);

}

?>