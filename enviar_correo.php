<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

function enviarCorreo($correo, $token){

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
         

        // TU CORREO GMAIL
        $mail->Username = 'maurogi16@gmail.com';
        // CONTRASEÑA DE APLICACIÓN
        $mail->Password = 'mpzc vimx xfzq vvaq';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

       $mail->setFrom('maurogi16@gmail.com', 'Sabora');
        $mail->addAddress($correo);

        $mail->isHTML(true);

        $mail->Subject = 'Verificacion de cuenta';

        $enlace = "http://localhost/sabora/verificar.php?token=$token";

        $mail->Body = "
        <h2>Bienvenido a Sabora</h2>

        <p>Haz clic en el enlace para verificar tu cuenta:</p>

        <a href='$enlace'>$enlace</a>
        ";

        $mail->send();

        return true;

    } catch (Exception $e) {

        echo $mail->ErrorInfo;
    }
}

?>