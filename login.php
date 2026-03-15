<?php
session_start();
require_once('conexion.php'); 
$db = new Database();
$con = $db->getCon(); // Usamos el método getCon de la clase

$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_POST['email']) && !empty($_POST['password'])) {
        $email = $db->sanitize($_POST['email']); // Limpiamos el email
        $password = $_POST['password'];

        $sql = "SELECT * FROM users WHERE email = '$email'";
        $resultado = mysqli_query($con, $sql);

        if ($resultado && mysqli_num_rows($resultado) > 0) {
            $usuario = mysqli_fetch_assoc($resultado);
            
            // Verificamos el hash que creo en el registro
            if (password_verify($password, $usuario['password'])) {
                // GUARDAR TODAS LAS VARIABLES DE SESIÓN NECESARIAS
                $_SESSION['user_id'] = $usuario['id'];
                $_SESSION['nombre'] = $usuario['nombre'];
                $_SESSION['rol'] = $usuario['rol'];
                $_SESSION['email'] = $usuario['email']; // ← FALTABA
                $_SESSION['pais'] = $usuario['pais'] ?? 'ES'; // ← FALTABA (con valor por defecto)

                // Para debug: verificar que el rol sea admin
                if ($usuario['rol'] === 'admin') {
                    error_log("Usuario admin logueado: " . $usuario['email'] . ", Rol: " . $usuario['rol']);
                }

                // Redirigir según el rol
                if ($usuario['rol'] === 'admin') {
                    header("Location: admin_panel.php"); // ← Redirigir a panel admin
                } else {
                    header("Location: index.php"); // ← Redirigir a página normal
                }
                exit();
            } else {
                $error_msg = "Contraseña incorrecta.";
            }
        } else {
            $error_msg = "Este correo no está registrado.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Login - ALAZÓN</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="css/stylesLogin.css" rel="stylesheet" /> </head>
<body>
    <div class="container mt-4">
        <a href="index.php" class="btn btn-outline-dark">
            <i class="bi-arrow-left me-1"></i> Volver a la tienda
        </a>
    </div>

    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-4">
                    <div class="card card-login p-4 shadow-sm">
                        <div class="text-center mb-4">
                            <i class="bi-person-circle display-1"></i>
                            <h2 class="fw-bolder mt-2">Iniciar Sesión</h2>
                            <p class="text-muted">Accede a tu cuenta de ALAZÓN</p>
                        </div>

                        <?php if($error_msg != ""): ?>
                            <div class="alert alert-danger"><?php echo $error_msg; ?></div>
                        <?php endif; ?>

                        <form method="POST" action="login.php">
                            <div class="mb-3">
                                <label for="email" class="form-label">Correo electrónico</label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="nombre@ejemplo.com" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Recordarme</label>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-dark btn-lg">Entrar</button>
                            </div>
                        </form>

                        <div class="text-center mt-4">
                            <p class="small">¿No tienes cuenta? <a href="registro.php" class="text-dark fw-bold">Regístrate aquí</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="py-2 bg-dark mt-auto" style="position: fixed; bottom: 0; width: 100%;">
        <div class="container">
            <p class="m-0 text-center text-white">ALAZÓN &copy; 2026</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>