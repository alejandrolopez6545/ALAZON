<?php
require_once('session.php');
require_once('conexion.php');

$db = new Database();
$con = $db->getCon();

// Verificar si es admin
if (!$es_admin) {
    header('Location: index.php');
    exit();
}

// Obtener pedidos con información completa
$sql = "SELECT o.*, 
               u.nombre as cliente,
               mp.nombre as metodo_pago_nombre,
               mp.icono as metodo_pago_icono,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as total_items
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        LEFT JOIN metodos_pago mp ON o.metodo_pago_id = mp.id
        WHERE o.estado != 'carrito' 
        ORDER BY o.fecha DESC";
$result = mysqli_query($con, $sql);

// Procesar filtros si se envían
$where_conditions = ["o.estado != 'carrito'"];
$filtro_estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';

if (!empty($filtro_estado)) {
    $where_conditions[] = "o.estado = '$filtro_estado'";
}
if (!empty($filtro_fecha_desde)) {
    $where_conditions[] = "DATE(o.fecha) >= '$filtro_fecha_desde'";
}
if (!empty($filtro_fecha_hasta)) {
    $where_conditions[] = "DATE(o.fecha) <= '$filtro_fecha_hasta'";
}

$sql_filtrada = "SELECT o.*, 
                        u.nombre as cliente,
                        mp.nombre as metodo_pago_nombre,
                        mp.icono as metodo_pago_icono,
                        (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as total_items
                 FROM orders o 
                 JOIN users u ON o.user_id = u.id 
                 LEFT JOIN metodos_pago mp ON o.metodo_pago_id = mp.id
                 WHERE " . implode(' AND ', $where_conditions) . " 
                 ORDER BY o.fecha DESC";
$result = mysqli_query($con, $sql_filtrada);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - Admin ALAZÓN</title>
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
        .badge-estado {
            font-size: 0.8em;
            padding: 4px 8px;
        }
        .table th {
            background-color: #2c3e50;
            color: white;
        }
        .btn-view {
            transition: all 0.2s;
        }
        .btn-view:hover {
            transform: scale(1.05);
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
                <a href="admin_usuarios.php"><i class="bi-people"></i> Usuarios</a>
                <a href="admin_productos.php"><i class="bi-box"></i> Productos</a>
                <a href="admin_pedidos.php" class="active"><i class="bi-receipt"></i> Pedidos</a>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0"><i class="bi-receipt me-2"></i>Gestión de Pedidos</h1>
                <p class="text-muted mb-0">Visualiza y gestiona los pedidos de los clientes</p>
            </div>
        </div>
        
        <!-- Tabla de pedidos -->
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle">
                <thead>
                    <tr>
                        <th>Pedido #</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Items</th>
                        <th>Estado</th>
                        <th>Método Pago</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($result) == 0): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                <i class="bi-inbox fs-1 d-block text-muted mb-2"></i>
                                <p class="text-muted mb-0">No hay pedidos que mostrar</p>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php while ($pedido = mysqli_fetch_assoc($result)): 
                        // Determinar color según estado
                        $color_estado = 'secondary';
                        $icono_estado = 'bi-question-circle';
                        
                        switch($pedido['estado']) {
                            case 'pagado':
                                $color_estado = 'success';
                                $icono_estado = 'bi-check-circle';
                                break;
                            case 'pendiente':
                                $color_estado = 'warning';
                                $icono_estado = 'bi-clock';
                                break;
                            case 'enviado':
                                $color_estado = 'info';
                                $icono_estado = 'bi-truck';
                                break;
                            case 'entregado':
                                $color_estado = 'dark';
                                $icono_estado = 'bi-check2-all';
                                break;
                            case 'cancelado':
                                $color_estado = 'danger';
                                $icono_estado = 'bi-x-circle';
                                break;
                        }
                    ?>
                    <tr>
                        <td><strong>#<?php echo $pedido['id']; ?></strong></td>
                        <td><?php echo htmlspecialchars($pedido['cliente']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($pedido['fecha'])); ?></td>
                        <td><strong class="text-primary">€<?php echo number_format($pedido['total'], 2, ',', '.'); ?></strong></td>
                        <td>
                            <span class="badge bg-secondary"><?php echo $pedido['total_items']; ?> items</span>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $color_estado; ?> badge-estado">
                                <i class="bi <?php echo $icono_estado; ?> me-1"></i>
                                <?php echo ucfirst($pedido['estado']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($pedido['metodo_pago_nombre'])): ?>
                                <i class="bi <?php echo $pedido['metodo_pago_icono'] ?? 'bi-credit-card'; ?> me-1"></i>
                                <?php echo htmlspecialchars($pedido['metodo_pago_nombre']); ?>
                            <?php else: ?>
                                <span class="text-muted">No especificado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="admin_pedido_detalle.php?id=<?php echo $pedido['id']; ?>" 
                                   class="btn btn-sm btn-primary btn-view" 
                                   title="Ver detalles del pedido">
                                    <i class="bi-eye"></i>
                                </a>
                                <!-- Solo visible, sin opción de editar -->
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Estadísticas -->
        <div class="row mt-5">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5 class="card-title">Pedidos Totales</h5>
                        <?php
                        $sql_total = "SELECT COUNT(*) as total FROM orders WHERE estado != 'carrito'";
                        $result_total = mysqli_query($con, $sql_total);
                        $total = mysqli_fetch_assoc($result_total);
                        ?>
                        <h2><?php echo $total['total']; ?></h2>
                        <small>pedidos realizados</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5 class="card-title">Ventas Totales</h5>
                        <?php
                        $sql_ventas = "SELECT SUM(total) as ventas FROM orders WHERE estado != 'carrito'";
                        $result_ventas = mysqli_query($con, $sql_ventas);
                        $ventas = mysqli_fetch_assoc($result_ventas);
                        ?>
                        <h2>€<?php echo number_format($ventas['ventas'] ?? 0, 2, ',', '.'); ?></h2>
                        <small>ingresos totales</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <h5 class="card-title">Pendientes</h5>
                        <?php
                        $sql_pendientes = "SELECT COUNT(*) as pendientes FROM orders WHERE estado = 'pendiente'";
                        $result_pendientes = mysqli_query($con, $sql_pendientes);
                        $pendientes = mysqli_fetch_assoc($result_pendientes);
                        ?>
                        <h2><?php echo $pendientes['pendientes']; ?></h2>
                        <small>por procesar</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5 class="card-title">Ticket Promedio</h5>
                        <?php
                        $sql_promedio = "SELECT AVG(total) as promedio FROM orders WHERE estado != 'carrito'";
                        $result_promedio = mysqli_query($con, $sql_promedio);
                        $promedio = mysqli_fetch_assoc($result_promedio);
                        ?>
                        <h2>€<?php echo number_format($promedio['promedio'] ?? 0, 2, ',', '.'); ?></h2>
                        <small>por pedido</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>