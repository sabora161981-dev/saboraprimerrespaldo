<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Sabora</title>

<style>
body {
    background: #efe7e2;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    margin: 0;
    font-family: 'Segoe UI', sans-serif;
}

.card {
    background: #ffffff;
    padding: 35px;
    border-radius: 20px;
    text-align: center;
    width: 360px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
}

.logo {
    position: relative;
    width: 160px;
    height: 160px;
    margin: auto;
}
.logo:hover{

    transform: scale(1.05);

    transition: 0.4s;

    filter: drop-shadow(
        0 10px 20px rgba(255,107,44,0.4)
    );

    cursor: pointer;
}
.circle-center {
    width: 70px;
    height: 70px;
    background: #ff6b2c;
    border-radius: 50%;
    position: absolute;
    top: 45px;
    left: 45px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 28px;
    color: black;
}

/* TEXTOS */
.title {
    margin-top: 10px;
    font-size: 18px;
    font-weight: 600;
}

.subtitle {
    font-size: 13px;
    color: #777;
    margin-bottom: 20px;
}

.label {
    text-align: left;
    font-size: 12px;
    margin-bottom: 10px;
    color: #333;
}

/* BOTONES DE ROLES */
.role-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}




.role {
    padding: 10px;
    border-radius: 12px;
    border: 1px solid #ddd;
    background: #f9f9f9;
    cursor: pointer;
    font-size: 13px;
    transition: 0.2s;
}

.role:hover {
    border-color: #ff6b2c;
    transform: scale(1.05);
    color: #ff6b2c;
}

.role.full {
    grid-column: span 2;
}

/* LINK */
.link {
    margin-top: 15px;
    font-size: 12px;
    color: #ff6b2c;
    cursor: pointer;
     
}
</style>
</head>

<body>

<div class="card">

    <!-- LOGO -->
    <div class="logo">

        <svg width="160" height="160" viewBox="0 0 180 180">

            <defs>
                <path id="topArc"
                    d="M 90,90 m -70,0 a 70,70 0 1,1 140,0" />

                <path id="bottomArc"
                    d="M 90,90 m -70,0 a 70,70 0 1,0 140,0" />
            </defs>

            <!-- TEXTO SUPERIOR -->
            <text fill="#ff6b2c" font-size="11" letter-spacing="2">
                <textPath href="#topArc" startOffset="50%" text-anchor="middle">
                    NO ES UNA EXPERIENCIA
                </textPath>
            </text>

            <!-- TEXTO INFERIOR -->
            <text fill="#ff6b2c" font-size="11" letter-spacing="2">
                <textPath href="#bottomArc" startOffset="50%" text-anchor="middle">
                    ES SABORA
                </textPath>
            </text>

        </svg>

        <div class="circle-center">🍴</div>

    </div>

    <!-- TEXTOS -->
    <div class="title">Sabora</div>
    <div class="subtitle">Inicia sesión en tu cuenta</div>

    <div class="label">Tipo de Usuario *</div>

    <!-- ROLES -->
    <div class="role-container">

    <div class="role"
    onclick="window.location.href='Cliente.html'">
        Cliente
    </div>

    <div class="role"
     onclick="window.location.href='Administrador.html'">
        Administrador
    </div>

    <div class="role"
     onclick="window.location.href='Cajero.html'">
        Cajero
    </div>

    <div class="role"
     onclick="window.location.href='Contador.html'">
        Contador
    </div>

    <div class="role full"
     onclick="window.location.href='Proveedor.html'">
        Proveedor
    </div>

</div>  
    <!-- LINK -->
    <div class="link">
        ¿No tienes una cuenta? 
          <a href="registro.html">Regístrate aquí</a>

</div>

</body>
</html>