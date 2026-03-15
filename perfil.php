<?php
require_once('session.php');
require_once('conexion.php');

// Si no está logueado, redirigir al login
if (!$logged_in) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$con = $db->getCon();
$mensaje = "";

// FUNCIÓN PARA OBTENER LISTA DE PAÍSES (CENTRALIZADA EN ESTE ARCHIVO)
function obtenerListaPaises($conexion) {
    $paises = [];
    $sql = "SELECT codigo, nombre FROM paises ORDER BY nombre";
    $result = mysqli_query($conexion, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $paises[$row['codigo']] = $row['nombre'];
        }
    } else {
        // Lista por defecto si hay algún problema
        $paises = [
            'ES' => 'España',
            'PT' => 'Portugal', 
            'FR' => 'Francia',
            'IT' => 'Italia',
            'DE' => 'Alemania',
            'GB' => 'Reino Unido',
            'US' => 'Estados Unidos',
            'MX' => 'México',
            'AR' => 'Argentina',
            'CO' => 'Colombia',
            'CL' => 'Chile',
            'PE' => 'Perú',
            'VE' => 'Venezuela',
            'EC' => 'Ecuador'
        ];
    }
    
    return $paises;
}

// Obtener la lista de países UNA VEZ (se usa en varios lugares)
$lista_paises = obtenerListaPaises($con);

// Obtener información del usuario
$sql_usuario = "SELECT * FROM users WHERE id = '$user_id'";
$result_usuario = mysqli_query($con, $sql_usuario);

if ($result_usuario && mysqli_num_rows($result_usuario) > 0) {
    $usuario_info = mysqli_fetch_assoc($result_usuario);
    $nombre_actual = $usuario_info['nombre'];
    $email_actual = $usuario_info['email'];
    $pais_codigo_actual = isset($usuario_info['pais']) && !empty($usuario_info['pais']) ? $usuario_info['pais'] : 'ES';
    
    // Guardar en sesión si no existe
    if (!isset($_SESSION['email'])) {
        $_SESSION['email'] = $email_actual;
    }
    if (!isset($_SESSION['pais'])) {
        $_SESSION['pais'] = $pais_codigo_actual;
    }
} else {
    // Si no se encuentra, usar valores de sesión
    $nombre_actual = $usuario;
    $email_actual = $_SESSION['email'] ?? '';
    $pais_codigo_actual = $_SESSION['pais'] ?? 'ES';
}

// Obtener nombre del país actual
$nombre_pais_actual = isset($lista_paises[$pais_codigo_actual]) ? $lista_paises[$pais_codigo_actual] : 'España';

// Procesar actualización de datos
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Actualizar nombre y país
    if (isset($_POST['actualizar_datos'])) {
        $nuevo_nombre = $db->sanitize($_POST['nuevo_nombre']);
        $nuevo_pais = $db->sanitize($_POST['pais']);
        
        // Validar que el país exista en la lista
        if (!array_key_exists($nuevo_pais, $lista_paises)) {
            $mensaje = "<div class='alert alert-danger'>País no válido.</div>";
        } else {
            $sql = "UPDATE users SET nombre = '$nuevo_nombre', pais = '$nuevo_pais' WHERE id = '$user_id'";
            if (mysqli_query($con, $sql)) {
                // Actualizar variables de sesión
                $_SESSION['nombre'] = $nuevo_nombre;
                $_SESSION['pais'] = $nuevo_pais;
                
                // ACTUALIZAR LA MONEDA EN LA SESIÓN TAMBIÉN
                require_once('config.php');
                $moneda_info = obtenerMonedaUsuario($user_id, $con);
                $_SESSION['moneda_codigo'] = $moneda_info['codigo'];
                $_SESSION['moneda_simbolo'] = $moneda_info['simbolo'];
                
                // Actualizar variables locales
                $usuario = $nuevo_nombre;
                $nombre_actual = $nuevo_nombre;
                $pais_codigo_actual = $nuevo_pais;
                $nombre_pais_actual = $lista_paises[$nuevo_pais];
                
                // Actualizar también las variables de moneda para esta página
                $moneda_codigo = $moneda_info['codigo'];
                $moneda_simbolo = $moneda_info['simbolo'];
                
                $mensaje = "<div class='alert alert-success'>Datos actualizados correctamente. Moneda actualizada a: " . 
                          $moneda_info['simbolo'] . " " . $moneda_info['codigo'] . "</div>";
            } else {
                $mensaje = "<div class='alert alert-danger'>Error al actualizar datos: " . mysqli_error($con) . "</div>";
            }
        }
    }
}
    
    // Cambiar contraseña
    if (isset($_POST['cambiar_password'])) {
        $password_actual_input = $_POST['password_actual'];
        $nueva_password = $_POST['nueva_password'];
        $confirmar_password = $_POST['confirmar_password'];
        
        // Verificar contraseña actual
        $sql = "SELECT password FROM users WHERE id = '$user_id'";
        $result = mysqli_query($con, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            
            if (password_verify($password_actual_input, $row['password'])) {
                if ($nueva_password === $confirmar_password) {
                    if (strlen($nueva_password) >= 8) {
                        $hash_nuevo = password_hash($nueva_password, PASSWORD_DEFAULT);
                        $sql = "UPDATE users SET password = '$hash_nuevo' WHERE id = '$user_id'";
                        
                        if (mysqli_query($con, $sql)) {
                            $mensaje = "<div class='alert alert-success'>Contraseña actualizada correctamente.</div>";
                        } else {
                            $mensaje = "<div class='alert alert-danger'>Error al actualizar contraseña.</div>";
                        }
                    } else {
                        $mensaje = "<div class='alert alert-danger'>La nueva contraseña debe tener al menos 8 caracteres.</div>";
                    }
                } else {
                    $mensaje = "<div class='alert alert-danger'>Las nuevas contraseñas no coinciden.</div>";
                }
            } else {
                $mensaje = "<div class='alert alert-danger'>La contraseña actual es incorrecta.</div>";
            }
        } else {
            $mensaje = "<div class='alert alert-danger'>No se pudo verificar la contraseña actual.</div>";
        }
    }
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mi Perfil - ALAZÓN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        .card { 
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border: 1px solid rgba(0, 0, 0, 0.125);
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        }
        .form-control:disabled, .form-control[readonly] {
            background-color: #e9ecef;
            opacity: 1;
        }
        .select-pais option {
            padding: 8px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">ALAZÓN</a>
            <div class="d-flex">
                <div class="dropdown">
                    <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi-person-fill me-1"></i>
                        Hola, <?php echo htmlspecialchars($usuario); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="perfil.php">Mi Perfil</a></li>
                        <li><a class="dropdown-item" href="mis_pedidos.php">Mis Pedidos</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">Cerrar Sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container mt-5">
        <h1 class="mb-4">Mi Perfil</h1>
        
        <?php echo $mensaje; ?>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi-person-circle me-2"></i>Información Personal</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label"><strong>Nombre</strong></label>
                                <input type="text" class="form-control" name="nuevo_nombre" 
                                       value="<?php echo htmlspecialchars($nombre_actual); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><strong>Correo electrónico</strong></label>
                                <input type="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($email_actual); ?>" 
                                       readonly disabled>
                                <small class="text-muted">El correo no se puede modificar.</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><strong>País</strong></label>
                                <select class="form-select select-pais" name="pais" required>
                                    <?php foreach ($lista_paises as $codigo => $nombre): ?>
                                        <option value="<?php echo htmlspecialchars($codigo); ?>" 
                                            <?php echo ($codigo == $pais_codigo_actual) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($nombre); ?> (<?php echo htmlspecialchars($codigo); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><strong>Rol</strong></label>
                                <input type="text" class="form-control" 
                                       value="<?php echo htmlspecialchars($rol); ?>" 
                                       readonly disabled>
                                <small class="text-muted">El rol no se puede modificar.</small>
                            </div>
                            
                            <button type="submit" name="actualizar_datos" class="btn btn-primary">
                                <i class="bi-save me-1"></i>Guardar Cambios
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi-shield-lock me-2"></i>Seguridad</h5>
                    </div>
                    <div class="card-body">
                        <h6>Cambiar Contraseña</h6>
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Contraseña Actual</label>
                                <input type="password" class="form-control" name="password_actual" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nueva Contraseña</label>
                                <input type="password" class="form-control" name="nueva_password" required>
                                <small class="text-muted">Mínimo 8 caracteres.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirmar Nueva Contraseña</label>
                                <input type="password" class="form-control" name="confirmar_password" required>
                            </div>
                            <button type="submit" name="cambiar_password" class="btn btn-primary">
                                <i class="bi-key me-1"></i>Cambiar Contraseña
                            </button>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi-info-circle me-2"></i>Información de la Cuenta</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>ID de Usuario:</strong> <?php echo htmlspecialchars($user_id); ?></p>
                        <p><strong>País actual:</strong> <?php echo htmlspecialchars($nombre_pais_actual); ?> (<?php echo htmlspecialchars($pais_codigo_actual); ?>)</p>
                        <p><strong>Correo:</strong> <?php echo htmlspecialchars($email_actual); ?></p>
                        <p><strong>Rol:</strong> <?php echo htmlspecialchars($rol); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4 d-flex justify-content-between">
            <a href="index.php" class="btn btn-outline-primary">
                <i class="bi-house-door me-1"></i>Volver al Inicio
            </a>
            <a href="logout.php" class="btn btn-danger">
                <i class="bi-box-arrow-right me-1"></i>Cerrar Sesión
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>