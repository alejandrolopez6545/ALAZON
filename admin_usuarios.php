<?php
require_once('session.php');
require_once('conexion.php');

$db = new Database();
$con = $db->getCon();

// Obtener lista de países desde la base de datos (DEBE IR ANTES DE TODO)
$sql_paises = "SELECT codigo, nombre FROM paises ORDER BY nombre";
$result_paises = mysqli_query($con, $sql_paises);
$paises = [];
while ($pais = mysqli_fetch_assoc($result_paises)) {
    $paises[] = $pais;
}

// Procesar eliminación de usuario
if (isset($_GET['eliminar']) && is_numeric($_GET['eliminar'])) {
    $id_eliminar = $_GET['eliminar'];
    
    // Verificar que no sea el usuario actual
    if ($id_eliminar != $_SESSION['user_id']) {
        $sql_eliminar = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($con, $sql_eliminar);
        mysqli_stmt_bind_param($stmt, "i", $id_eliminar);
        mysqli_stmt_execute($stmt);
        
        // Redirigir para evitar reenvío del formulario
        header("Location: admin_usuarios.php?mensaje=Usuario+eliminado+correctamente");
        exit();
    }
}

// Procesar nuevo usuario (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] == 'crear') {
        $nombre = mysqli_real_escape_string($con, $_POST['nombre']);
        $email = mysqli_real_escape_string($con, $_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $rol = mysqli_real_escape_string($con, $_POST['rol']);
        // CONVERTIR A MAYÚSCULAS
        $pais = strtoupper(mysqli_real_escape_string($con, $_POST['pais']));
        
        $sql_insert = "INSERT INTO users (nombre, email, password, rol, pais, fecha_registro) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = mysqli_prepare($con, $sql_insert);
        mysqli_stmt_bind_param($stmt, "sssss", $nombre, $email, $password, $rol, $pais);
        mysqli_stmt_execute($stmt);
        
        header("Location: admin_usuarios.php?mensaje=Usuario+creado+correctamente");
        exit();
    }
    
    // Procesar edición de usuario
    if ($_POST['accion'] == 'editar' && isset($_POST['id'])) {
        $id = $_POST['id'];
        $nombre = mysqli_real_escape_string($con, $_POST['nombre']);
        $email = mysqli_real_escape_string($con, $_POST['email']);
        $rol = mysqli_real_escape_string($con, $_POST['rol']);
        // CONVERTIR A MAYÚSCULAS
        $pais = strtoupper(mysqli_real_escape_string($con, $_POST['pais']));
        
        // Si se proporcionó nueva contraseña, actualizarla
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $sql_update = "UPDATE users SET nombre = ?, email = ?, password = ?, rol = ?, pais = ? WHERE id = ?";
            $stmt = mysqli_prepare($con, $sql_update);
            mysqli_stmt_bind_param($stmt, "sssssi", $nombre, $email, $password, $rol, $pais, $id);
        } else {
            $sql_update = "UPDATE users SET nombre = ?, email = ?, rol = ?, pais = ? WHERE id = ?";
            $stmt = mysqli_prepare($con, $sql_update);
            mysqli_stmt_bind_param($stmt, "ssssi", $nombre, $email, $rol, $pais, $id);
        }
        
        mysqli_stmt_execute($stmt);
        
        header("Location: admin_usuarios.php?mensaje=Usuario+actualizado+correctamente");
        exit();
    }
}

// MODIFICAR CONSULTA DE USUARIOS PARA HACER JOIN CON PAÍSES Y SUMAR GASTOS
$sql = "SELECT u.*, 
               p.nombre as nombre_pais,
               COALESCE((
                   SELECT SUM(o.total) 
                   FROM orders o 
                   WHERE o.user_id = u.id 
                   AND o.estado != 'carrito'
                   AND o.estado IN ('pagado', 'enviado', 'entregado')
               ), 0) as total_gastado,
               (
                   SELECT COUNT(*) 
                   FROM orders o 
                   WHERE o.user_id = u.id 
                   AND o.estado != 'carrito'
               ) as total_pedidos
        FROM users u 
        LEFT JOIN paises p ON u.pais = p.codigo 
        ORDER BY u.id DESC";
$result = mysqli_query($con, $sql);

// Si hay un ID para editar, obtener los datos
$usuario_editar = null;
if (isset($_GET['editar']) && is_numeric($_GET['editar'])) {
    $id_editar = $_GET['editar'];
    $sql_editar = "SELECT * FROM users WHERE id = ?";
    $stmt = mysqli_prepare($con, $sql_editar);
    mysqli_stmt_bind_param($stmt, "i", $id_editar);
    mysqli_stmt_execute($stmt);
    $result_editar = mysqli_stmt_get_result($stmt);
    $usuario_editar = mysqli_fetch_assoc($result_editar);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Admin ALAZÓN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .admin-nav {
            background: #2c3e50;
            color: white;
            padding: 15px;
            margin-bottom: 30px;
        }
        .admin-nav a {
            color: white;
            text-decoration: none;
            margin: 0 10px;
            padding: 5px 10px;
            border-radius: 4px;
        }
        .admin-nav a:hover {
            background: rgba(255,255,255,0.1);
        }
        .admin-nav a.active {
            background: rgba(255,255,255,0.2);
        }
        .badge-rol {
            font-size: 0.8em;
            padding: 4px 8px;
        }
        .btn-action {
            width: 30px;
            height: 30px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 5px;
        }
        .password-toggle {
            cursor: pointer;
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
        }
        .form-group {
            position: relative;
        }
        .mensaje-alerta {
            animation: fadeOut 5s forwards;
        }
        @keyframes fadeOut {
            0% { opacity: 1; }
            80% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }
    </style>
</head>
<body>
    <!-- Navbar superior -->
    <nav class="admin-nav">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <i class="bi-shield-lock me-2"></i>
                <strong>ALAZÓN Admin</strong>
                <a href="admin_panel.php"><i class="bi-speedometer2"></i> Panel</a>
                <a href="admin_usuarios.php" class="active"><i class="bi-people"></i> Usuarios</a>
                <a href="admin_productos.php"><i class="bi-box"></i> Productos</a>
                <a href="admin_pedidos.php"><i class="bi-receipt"></i> Pedidos</a>
                <a href="admin_rebajas.php"><i class="bi-tag"></i> Rebajas</a>
            </div>
            <div>
                <span class="me-3">Hola, <?php echo $_SESSION['nombre']; ?></span>
                <a href="index.php" class="btn btn-sm btn-outline-light">
                    <i class="bi-shop"></i> Tienda
                </a>
                <a href="logout.php" class="btn btn-sm btn-danger ms-2">
                    <i class="bi-box-arrow-right"></i> Salir
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <!-- Mensajes de éxito/error -->
        <?php if (isset($_GET['mensaje'])): ?>
        <div class="alert alert-success alert-dismissible fade show mensaje-alerta" role="alert">
            <i class="bi-check-circle"></i> <?php echo urldecode($_GET['mensaje']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- acciones -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0"><i class="bi-people me-2"></i>Gestión de Usuarios</h1>
                <p class="text-muted mb-0">Administra los usuarios registrados en la plataforma</p>
            </div>
            <div>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#nuevoUsuarioModal">
                    <i class="bi-person-plus me-2"></i>Nuevo Usuario
                </button>
            </div>
        </div>
        
        <!-- Tabla de usuarios -->
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Rol</th>
                        <th>País</th>
                        <th>Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    // Modificar consulta si hay búsqueda
                    if (isset($_GET['busqueda']) && !empty($_GET['busqueda'])) {
                        $busqueda = mysqli_real_escape_string($con, $_GET['busqueda']);
                        $sql = "SELECT u.*, p.nombre as nombre_pais 
                                FROM users u 
                                LEFT JOIN paises p ON u.pais = p.codigo 
                                WHERE u.nombre LIKE '%$busqueda%' 
                                OR u.email LIKE '%$busqueda%' 
                                OR p.nombre LIKE '%$busqueda%'
                                ORDER BY u.id DESC";
                        $result = mysqli_query($con, $sql);
                    }
                    
                    while ($usuario = mysqli_fetch_assoc($result)): 
                        $color_rol = ($usuario['rol'] == 'admin') ? 'danger' : 'primary';
                    ?>
                    <tr>
                        <td><strong>#<?php echo $usuario['id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($usuario['nombre']); ?></td>
                        <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $color_rol; ?> badge-rol">
                                <?php echo ucfirst($usuario['rol']); ?>
                            </span>
                        </td>
                        <td>
                            <?php 
                            // Mostrar el nombre del país desde el JOIN
                            if (isset($usuario['nombre_pais']) && !empty($usuario['nombre_pais'])) {
                                echo htmlspecialchars($usuario['nombre_pais']);
                            } else {
                                echo 'No especificado';
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if (isset($usuario['fecha_registro'])) {
                                echo date('d/m/Y', strtotime($usuario['fecha_registro']));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </td>
                        <td>
                            <a href="#" class="btn btn-sm btn-primary btn-action" 
                            data-bs-toggle="modal" data-bs-target="#detallesUsuarioModal<?php echo $usuario['id']; ?>" 
                            title="Ver detalles">
                                <i class="bi-eye"></i>
                            </a>
                            <a href="?editar=<?php echo $usuario['id']; ?>" class="btn btn-sm btn-warning btn-action" 
                            title="Editar usuario">
                                <i class="bi-pencil"></i>
                            </a>
                            <?php if ($usuario['id'] != $_SESSION['user_id']): ?>
                            <a href="?eliminar=<?php echo $usuario['id']; ?>" 
                            class="btn btn-sm btn-danger btn-action" 
                            title="Eliminar usuario"
                            onclick="return confirm('¿Estás seguro de eliminar a <?php echo htmlspecialchars($usuario['nombre']); ?>?')">
                                <i class="bi-trash"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- Modal para detalles del usuario -->
                    <div class="modal fade" id="detallesUsuarioModal<?php echo $usuario['id']; ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="bi-person me-2"></i>Detalles de Usuario
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-4 text-center mb-3">
                                            <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                                                 style="width: 80px; height: 80px; font-size: 2rem;">
                                                <?php echo strtoupper(substr($usuario['nombre'], 0, 1)); ?>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <p><strong>ID:</strong> #<?php echo $usuario['id']; ?></p>
                                            <p><strong>Nombre:</strong> <?php echo htmlspecialchars($usuario['nombre']); ?></p>
                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($usuario['email']); ?></p>
                                            <p><strong>Rol:</strong> <span class="badge bg-<?php echo $color_rol; ?>"><?php echo ucfirst($usuario['rol']); ?></span></p>
                                            <p><strong>País:</strong> 
                                                <?php 
                                                if (isset($usuario['nombre_pais']) && !empty($usuario['nombre_pais'])) {
                                                    echo htmlspecialchars($usuario['nombre_pais']);
                                                } else {
                                                    echo 'No especificado';
                                                }
                                                ?>
                                            </p>
                                            <p><strong>Registro:</strong> <?php echo date('d/m/Y H:i', strtotime($usuario['fecha_registro'])); ?></p>
                                        </div>
                                    </div>
                                    <hr>
                                    <h6>Estadísticas:</h6>
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="p-2 border rounded">
                                                <small class="text-muted">Pedidos</small>
                                                <h5 class="mb-0"></h5><?php echo $usuario['total_pedidos'] ?? 0; ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 border rounded">
                                                <small class="text-muted">Total Gastado</small>
                                                <h5 class="mb-0"></h5><?php echo number_format($usuario['total_gastado'] ?? 0, 2, ',', '.');?>€
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                    <a href="?editar=<?php echo $usuario['id']; ?>" class="btn btn-warning">
                                        <i class="bi-pencil"></i> Editar
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Estadísticas -->
        <div class="row mt-5">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Usuarios Totales</h5>
                        <?php
                        $sql_total = "SELECT COUNT(*) as total FROM users";
                        $result_total = mysqli_query($con, $sql_total);
                        $total = mysqli_fetch_assoc($result_total);
                        ?>
                        <h2><?php echo $total['total']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Clientes</h5>
                        <?php
                        $sql_clientes = "SELECT COUNT(*) as clientes FROM users WHERE rol = 'cliente'";
                        $result_clientes = mysqli_query($con, $sql_clientes);
                        $clientes = mysqli_fetch_assoc($result_clientes);
                        ?>
                        <h2><?php echo $clientes['clientes']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <h5 class="card-title">Administradores</h5>
                        <?php
                        $sql_admins = "SELECT COUNT(*) as admins FROM users WHERE rol = 'admin'";
                        $result_admins = mysqli_query($con, $sql_admins);
                        $admins = mysqli_fetch_assoc($result_admins);
                        ?>
                        <h2><?php echo $admins['admins']; ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Último Mes</h5>
                        <?php
                        $sql_mes = "SELECT COUNT(*) as mes FROM users WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                        $result_mes = mysqli_query($con, $sql_mes);
                        $mes = mysqli_fetch_assoc($result_mes);
                        ?>
                        <h2><?php echo $mes['mes']; ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Modal para nuevo usuario -->
        <div class="modal fade" id="nuevoUsuarioModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <form method="POST" action="">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi-person-plus me-2"></i>Nuevo Usuario</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="accion" value="crear">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="nombre" class="form-label">Nombre completo *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="password" class="form-label">Contraseña *</label>
                                    <div class="form-group">
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <span class="password-toggle" onclick="togglePassword('password')">
                                            <i class="bi-eye"></i>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirmar contraseña *</label>
                                    <div class="form-group">
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                        <span class="password-toggle" onclick="togglePassword('confirm_password')">
                                            <i class="bi-eye"></i>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="rol" class="form-label">Rol *</label>
                                    <select class="form-select" id="rol" name="rol" required>
                                        <option value="cliente" selected>Cliente</option>
                                        <option value="admin">Administrador</option>
                                    </select>
                                </div>
                                
                                <!-- Select de país desde la BD -->
                                <div class="col-md-6">
                                    <label for="pais" class="form-label">País</label>
                                    <select class="form-select" id="pais" name="pais">
                                        <option value="">Seleccionar país</option>
                                        <?php foreach ($paises as $pais): ?>
                                            <option value="<?php echo $pais['codigo']; ?>">
                                                <?php echo htmlspecialchars($pais['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="alert alert-info mt-3">
                                <i class="bi-info-circle"></i> Todos los campos marcados con * son obligatorios.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary">Crear Usuario</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Modal para editar usuario (se muestra si hay ID en GET) -->
        <?php if ($usuario_editar): ?>
        <div class="modal fade show" id="editarUsuarioModal" tabindex="-1" aria-hidden="false" style="display: block; background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <form method="POST" action="">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi-pencil me-2"></i>Editar Usuario</h5>
                            <a href="admin_usuarios.php" class="btn-close" aria-label="Close"></a>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="accion" value="editar">
                            <input type="hidden" name="id" value="<?php echo $usuario_editar['id']; ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="edit_nombre" class="form-label">Nombre completo *</label>
                                    <input type="text" class="form-control" id="edit_nombre" name="nombre" 
                                           value="<?php echo htmlspecialchars($usuario_editar['nombre']); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="edit_email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="edit_email" name="email" 
                                           value="<?php echo htmlspecialchars($usuario_editar['email']); ?>" required>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="edit_password" class="form-label">Nueva contraseña (dejar vacío para no cambiar)</label>
                                    <div class="form-group">
                                        <input type="password" class="form-control" id="edit_password" name="password">
                                        <span class="password-toggle" onclick="togglePassword('edit_password')">
                                            <i class="bi-eye"></i>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="edit_rol" class="form-label">Rol *</label>
                                    <select class="form-select" id="edit_rol" name="rol" required>
                                        <option value="cliente" <?php echo ($usuario_editar['rol'] == 'cliente') ? 'selected' : ''; ?>>Cliente</option>
                                        <option value="admin" <?php echo ($usuario_editar['rol'] == 'admin') ? 'selected' : ''; ?>>Administrador</option>
                                    </select>
                                </div>
                                
                                <!-- Select de país desde la BD para edición -->
                                <div class="col-md-6">
                                    <label for="edit_pais" class="form-label">País</label>
                                    <select class="form-select" id="edit_pais" name="pais">
                                        <option value="">Seleccionar país</option>
                                        <?php foreach ($paises as $pais): ?>
                                            <option value="<?php echo $pais['codigo']; ?>" 
                                                <?php echo (isset($usuario_editar['pais']) && $usuario_editar['pais'] == $pais['codigo']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($pais['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Fecha de registro</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo date('d/m/Y H:i', strtotime($usuario_editar['fecha_registro'])); ?>" 
                                           readonly>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning mt-3">
                                <i class="bi-exclamation-triangle"></i> Si cambias el rol del usuario actual, se cerrará su sesión.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="admin_usuarios.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-warning">Guardar Cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <a href="admin_panel.php" class="btn btn-secondary">
                <i class="bi-arrow-left"></i> Volver al Panel
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para mostrar/ocultar contraseña
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
        }
        
        // Validación de contraseñas en el formulario de nuevo usuario
        document.addEventListener('DOMContentLoaded', function() {
            const formNuevo = document.querySelector('#nuevoUsuarioModal form');
            if (formNuevo) {
                formNuevo.addEventListener('submit', function(e) {
                    const password = document.getElementById('password').value;
                    const confirmPassword = document.getElementById('confirm_password').value;
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        alert('Las contraseñas no coinciden. Por favor, verifica.');
                        document.getElementById('password').focus();
                    }
                });
            }
            
            // Si hay modal de edición, autoenfocar el primer campo
            const modalEditar = document.getElementById('editarUsuarioModal');
            if (modalEditar) {
                document.getElementById('edit_nombre').focus();
            }
            
            // Cerrar modal de edición al hacer clic fuera
            if (modalEditar) {
                modalEditar.addEventListener('click', function(e) {
                    if (e.target === this) {
                        window.location.href = 'admin_usuarios.php';
                    }
                });
            }
        });
    </script>
</body>
</html>