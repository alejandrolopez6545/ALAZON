<?php
require_once('conexion.php');
$db = new Database();
$con = $db->getCon();

$mensaje = "";
$tipo_mensaje = ""; // success o error

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Recoger y limpiar datos
    $nombre = $db->sanitize($_POST['nombre']);
    $email = $db->sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirmPassword'];

    // Validar que las contraseñas coincidan
    if ($password !== $confirmPassword) {
        $mensaje = "Error: Las contraseñas no coinciden.";
        $tipo_mensaje = "error";
    }
    // Validar longitud mínima
    else if (strlen($password) < 8) {
        $mensaje = "Error: La contraseña debe tener al menos 8 caracteres.";
        $tipo_mensaje = "error";
    }
    // Verificar si el email ya existe
    else {
        $checkEmail = "SELECT email FROM users WHERE email = '$email'";
        $resCheck = mysqli_query($con, $checkEmail);

        if (mysqli_num_rows($resCheck) > 0) {
            $mensaje = "Error: Este correo ya está registrado.";
            $tipo_mensaje = "error";
        } else {
            // HASHEAR la contraseña
            $password_segura = password_hash($password, PASSWORD_DEFAULT);

            // Insertar en la base de datos
            $sql = "INSERT INTO users (nombre, email, password, rol) VALUES ('$nombre', '$email', '$password_segura', 'cliente')";

            if (mysqli_query($con, $sql)) {
                $mensaje = "¡Registro exitoso! Ahora puedes iniciar sesión.";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "Error al registrar: " . mysqli_error($con);
                $tipo_mensaje = "error";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Registro - ALAZÓN</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="css/stylesRegister.css" rel="stylesheet" />
    <style>
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .alert-error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <a href="index.php" class="btn btn-outline-dark">
            <i class="bi-arrow-left me-1"></i> Volver a la tienda
        </a>
    </div>

    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="card card-register p-4">
                        <div class="text-center mb-4">
                            <i class="bi-person-plus-fill display-1"></i>
                            <h2 class="fw-bolder mt-2">Crea tu cuenta</h2>
                            <p class="text-muted">Únete a la comunidad de ALAZÓN</p>
                        </div>

                        <?php if($mensaje != ""): ?>
                            <div class="alert alert-<?php echo $tipo_mensaje == 'success' ? 'success' : 'danger'; ?>">
                                <?php echo $mensaje; ?>
                                <?php if($tipo_mensaje == 'success'): ?>
                                    <div class="mt-3">
                                        <a href="login.php" class="btn btn-success">Iniciar Sesión</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if($mensaje == "" || $tipo_mensaje == "error"): ?>
                            <form action="registro.php" method="POST">
                                <div class="mb-3">
                                    <label for="fullName" class="form-label">Nombre completo</label>
                                    <input type="text" class="form-control" id="fullName" name="nombre" placeholder="Tu nombre y apellidos" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Correo electrónico</label>
                                    <input type="email" class="form-control" id="email" name="email" placeholder="nombre@ejemplo.com" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label">Contraseña</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text">Mínimo 8 caracteres.</div>
                                </div>
                                <div class="mb-3">
                                    <label for="confirmPassword" class="form-label">Confirmar Contraseña</label>
                                    <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" required>
                                </div>
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-dark btn-lg">Registrarse</button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <p class="small">¿Ya tienes cuenta? <a href="login.php" class="text-dark fw-bold">Inicia sesión aquí</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark py-2">
        <div class="container">
            <p class="m-0 text-center text-white small">ALAZÓN &copy; 2026</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>