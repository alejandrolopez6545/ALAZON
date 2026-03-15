<?php
// admin_panel.php - Dashboard simple
require_once('session.php');
require_once('conexion.php');

$db = new Database();
$con = $db->getCon();

// Obtener estadísticas básicas
$sql = "SELECT 
    (SELECT COUNT(*) FROM users) as total_usuarios,
    (SELECT COUNT(*) FROM products) as total_productos,
    (SELECT COUNT(*) FROM orders WHERE estado != 'carrito') as total_pedidos,
    (SELECT SUM(total) FROM orders WHERE estado != 'carrito') as ventas_totales";

$result = mysqli_query($con, $sql);
$stats = mysqli_fetch_assoc($result);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel - Admin ALAZÓN</title>
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
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
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
                <a href="admin_panel.php" class="active"><i class="bi-speedometer2"></i> Panel</a>
                <a href="admin_usuarios.php"><i class="bi-people"></i> Usuarios</a>
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
        <h1 class="mb-4">
            <i class="bi-speedometer2 me-2"></i>
            Panel de Administración
        </h1>
        
        <!-- Estadísticas -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_usuarios']; ?></div>
                    <div class="stat-label">Usuarios</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_productos']; ?></div>
                    <div class="stat-label">Productos</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_pedidos']; ?></div>
                    <div class="stat-label">Pedidos</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-number">€<?php echo number_format($stats['ventas_totales'] ?? 0, 2); ?></div>
                    <div class="stat-label">Ventas Totales</div>
                </div>
            </div>
        </div>
        
        <!-- Mensaje de bienvenida -->
        <div class="card mt-4">
            <div class="card-body">
                <h5 class="card-title">¡Bienvenido al Panel de Administración!</h5>
                <p class="card-text">
                    El menú superior sirve para moverte sobre todos los paneles disponibles
                </p>
                <div class="alert alert-info">
                    <i class="bi-info-circle me-2"></i>
                    Sesión iniciada como: <strong><?php echo $_SESSION['nombre']; ?></strong>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>