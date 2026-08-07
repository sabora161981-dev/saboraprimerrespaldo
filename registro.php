<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registro - Sabora</title>

<style>
body {
    background: #f5f5f5;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    min-height: 100vh;
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
}

/* TARJETA */
.card {
    background: white;
    padding: 30px;
    border-radius: 20px;
    text-align: center;
    width: 350px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
}

/* LOGO */
.logo {
    position: relative;
    width: 180px;
    height: 180px;
    margin: auto;
}
.logo:hover{

    transform: scale(1.05);

    transition: 0.4s;

    filter: drop-shadow(
        0 10px 20px rgba(255,107, 44, 0.4)
    );

    cursor: pointer;
}


.circle-center {
    width: 90px;
    height: 90px;
    background: #ff6b2c;
    border-radius: 50%;
    position: absolute;
    top: 45px;
    left: 45px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 30px;
}

/* FORM */
.form-group {
    text-align: left;
    margin-top: 12px;
}

label {
    font-size: 12px;
    font-weight: 600;
}

input {
    width: 100%;
    padding: 10px;
    border-radius: 10px;
    border: 1px solid #ccc;
    margin-top: 5px;
    font-size: 13px;
}

/* INPUT DESTACADO */
input.active {
    background: #e8f0fe;
    border: 1px solid #9bb7e0;
}

.info {
    font-size: 11px;
    color: #777;
}

.error {
    font-size: 11px;
    color: red;
}

/* BOTONES */
.btn {
    width: 100%;
    padding: 12px;
    border-radius: 12px;
    border: none;
    font-size: 14px;
    cursor: pointer;
    margin-top: 12px;

}
.btn:hover{
    transform: translateY(-2px);
    transition: 0.3s;
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}


.btn-primary {
    background: #ff6b2c;
    color: white;
}

.btn-secondary {
    background: white;
    color: #ff6b2c;
    border: 2px solid #ff6b2c;
}

h3 {
    margin-top: 10px;
    font-size: 15px;
    color: #444;
}
</style>
</head>

<body>

<div class="card">

    <!-- LOGO ORIGINAL -->
    <div class="logo">

        <svg width="180" height="180" viewBox="0 0 180 180">
            <defs>
                <path id="topArc"
                    d="M 90,90 m -70,0 a 70,70 0 1,1 140,0" />

                <path id="bottomArc"
                    d="M 90,90 m -70,0 a 70,70 0 1,0 140,0" />
            </defs>

            <text fill="#ff6b2c" font-size="11" letter-spacing="2">
                <textPath href="#topArc" startOffset="50%" text-anchor="middle">
                    NO ES UNA EXPERIENCIA
                </textPath>
            </text>

            <text fill="#ff6b2c" font-size="11" letter-spacing="3">
                <textPath href="#bottomArc" startOffset="50%" text-anchor="middle">
                    ES SABORA
                </textPath>
            </text>
        </svg>

        <div class="circle-center">🍴</div>
    </div>

    <h3>Registro de Usuario</h3>

    <!-- FORMULARIO -->
    <form action="guardar.php" method="POST">

        <div class="form-group">
            <label>Nombre *</label>

            <input 
            class="active"
            type="text"
            id="nombre"
            name="nombre"
            placeholder="Ej: Mauricio"
            maxlength="50"
            onkeypress="return validarNombre(event)"
            required>
        </div>

        <div class="form-group">
            <label>Apellido *</label>

           <input 
           type="text"
           id="apellido"
           name="apellido"
           placeholder="Ej: Giraldo"
           maxlength="50"
           onkeypress="return validarNombre(event)"
        required>
        </div>

        <div class="form-group">
            <label>Número de Identificación *</label>

            <input 
           class="active"
        type="text"
        id="identificacion"
        name="identificacion"
        placeholder="Ej: 123456789"
        maxlength="12"
        onkeypress="return validarIdentificacion(event)"
        required>
        </div>

        <div class="form-group">
            <label>Correo Electrónico *</label>

            <input 
            type="email"
            name="correo"
            placeholder="correo@email.com"
            required>
        </div>

        <div class="form-group">
            <label>Dirección de Residencia *</label>

            <input 
            class="active" 
            name="direccion"
            type="text" placeholder="Dirección completa"
            required>
        </div>

        <div class="form-group">
            <label>Clave de Registro *</label>

            <input 
            type="password"
            name="clavedeRegistro" 
            placeholder="12345678hj"
           pattern="^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{8,}$"
            required>
            
            <div class="info">
                Debe contener minimo 8 caracteres, letras y números
            </div>
            
        </div>

        <!-- BOTONES -->
        <button type="submit" class="btn btn-primary">
            Registrarse
        </button>

        <button 
        type="button" 
        class="btn btn-secondary" 
        onclick="window.location.href='login.php'">
        Volver al login
    </button>

    </form>

</div>
<script>

// Validar Nombre y Apellido
function validarNombre(event) {

    const tecla = event.key;

    if (
        tecla === "Backspace" ||
        tecla === "Delete" ||
        tecla === "Tab" ||
        tecla === "ArrowLeft" ||
        tecla === "ArrowRight"
    ) {
        return true;
    }

    const patron = /^[A-Za-zÁÉÍÓÚáéíóúÑñ ]$/;

    if (!patron.test(tecla)) {
        alert("Solo se permiten letras.");
        event.preventDefault();
        return false;
    }

    return true;
}


// Validar Identificación
function validarIdentificacion(event) {

    const tecla = event.key;

    if (
        tecla === "Backspace" ||
        tecla === "Delete" ||
        tecla === "Tab" ||
        tecla === "ArrowLeft" ||
        tecla === "ArrowRight"
    ) {
        return true;
    }

    const patron = /^[0-9]$/;

    if (!patron.test(tecla)) {
        alert("Solo se permiten números.");
        event.preventDefault();
        return false;
    }

    return true;
}

</script>

</body>
</html>
</body>
</html>