<?php
// admin_pedido_detalle.php - Vista detallada de un pedido
require_once('session.php');
require_once('conexion.php');

$db = new Database();
$con = $db->getCon();

// Verificar si es admin
if (!$es_admin) {
    header('Location: index.php');
    exit();
}

// Verificar que se proporcionó un ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_pedidos.php');
    exit();
}

$pedido_id = intval($_GET['id']);

// Obtener información del pedido
$sql_pedido = "SELECT o.*, 
                      u.nombre as cliente,
                      u.email as email_cliente,
                      u.pais as codigo_pais,
                      p.nombre as pais_nombre,
                      mp.nombre as metodo_pago_nombre,
                      mp.icono as metodo_pago_icono,
                      mp.descripcion as metodo_pago_descripcion
               FROM orders o 
               JOIN users u ON o.user_id = u.id 
               LEFT JOIN paises p ON u.pais = p.codigo
               LEFT JOIN metodos_pago mp ON o.metodo_pago_id = mp.id
               WHERE o.id = $pedido_id AND o.estado != 'carrito'";

$result_pedido = mysqli_query($con, $sql_pedido);

if (!$result_pedido || mysqli_num_rows($result_pedido) == 0) {
    header('Location: admin_pedidos.php?mensaje=Pedido+no+encontrado');
    exit();
}

$pedido = mysqli_fetch_assoc($result_pedido);

// Obtener items del pedido
$sql_items = "SELECT oi.*, p.nombre, p.descripcion, p.imagen
              FROM order_items oi
              JOIN products p ON oi.product_id = p.id
              WHERE oi.order_id = $pedido_id AND oi.cantidad > 0 
              ORDER BY oi.id ASC";
$result_items = mysqli_query($con, $sql_items);

// Calcular subtotal y total
$subtotal = 0;
$items = [];
while ($item = mysqli_fetch_assoc($result_items)) {
    // Construir URL de la imagen
    $imagen_url = 'https://dummyimage.com/100x100/dee2e6/6c757d.jpg';
    if (!empty($item['imagen'])) {
        $ruta_imagen = 'uploads/productos/' . $item['imagen'];
        if (file_exists($ruta_imagen)) {
            $imagen_url = $ruta_imagen;
        }
    }
    
    $item['imagen_url'] = $imagen_url;
    $items[] = $item;
    $subtotal += $item['precio_unitario'] * $item['cantidad'];
}

// Determinar color según estado
$color_estado = 'secondary';
$icono_estado = 'bi-question-circle';
$texto_estado = ucfirst($pedido['estado']);

switch($pedido['estado']) {
    case 'pagado':
        $color_estado = 'success';
        $icono_estado = 'bi-check-circle';
        $texto_estado = 'Pagado';
        break;
    case 'pendiente':
        $color_estado = 'warning';
        $icono_estado = 'bi-clock';
        $texto_estado = 'Pendiente de pago';
        break;
    case 'enviado':
        $color_estado = 'info';
        $icono_estado = 'bi-truck';
        $texto_estado = 'Enviado';
        break;
    case 'entregado':
        $color_estado = 'dark';
        $icono_estado = 'bi-check2-all';
        $texto_estado = 'Entregado';
        break;
    case 'cancelado':
        $color_estado = 'danger';
        $icono_estado = 'bi-x-circle';
        $texto_estado = 'Cancelado';
        break;
}

// Formatear fechas
$fecha_pedido = date('d/m/Y H:i', strtotime($pedido['fecha']));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #<?php echo $pedido_id; ?> - Admin ALAZÓN</title>
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
            font-size: 0.9em;
            padding: 8px 12px;
        }
        .info-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s;
            height: 100%;
        }
        .info-card:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .total-row {
            font-size: 1.2rem;
            font-weight: bold;
        }
        .print-btn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            border-radius: 50px;
            padding: 12px 24px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        @media print {
            .admin-nav, .print-btn, .btn, footer {
                display: none !important;
            }
            .container {
                width: 100%;
                max-width: 100%;
            }
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
    <div class="container py-4">
        <!-- Cabecera -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">
                    <i class="bi-receipt me-2"></i>
                    Pedido #<?php echo $pedido_id; ?>
                </h1>
                <p class="text-muted mb-0">
                    <i class="bi-calendar me-1"></i><?php echo $fecha_pedido; ?>
                </p>
            </div>
            <div>
                <span class="badge bg-<?php echo $color_estado; ?> badge-estado">
                    <i class="bi <?php echo $icono_estado; ?> me-2"></i>
                    <?php echo $texto_estado; ?>
                </span>
            </div>
        </div>

        <div class="row">
            <!-- Columna izquierda: Información del cliente y envío -->
            <div class="col-md-6 mb-4">
                <div class="card info-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi-person-circle me-2 text-primary"></i>
                            Información del Cliente
                        </h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th width="120">Nombre:</th>
                                <td><?php echo htmlspecialchars($pedido['cliente']); ?></td>
                            </tr>
                            <tr>
                                <th>Email:</th>
                                <td>
                                    <a href="mailto:<?php echo $pedido['email_cliente']; ?>">
                                        <?php echo htmlspecialchars($pedido['email_cliente']); ?>
                                    </a>
                                </td>
                            </tr>
                            <tr>
                                <th>País:</th>
                                <td>
                                    <?php if (!empty($pedido['pais_nombre'])): ?>
                                        <i class="bi-globe me-1"></i>
                                        <?php echo htmlspecialchars($pedido['pais_nombre']); ?>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($pedido['codigo_pais'] ?? 'No especificado'); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="card info-card mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi-geo-alt-fill me-2 text-primary"></i>
                            Dirección de Envío
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($pedido['direccion_envio'])): ?>
                            <p class="mb-0">
                                <i class="bi-pin-map me-2"></i>
                                <?php echo nl2br(htmlspecialchars($pedido['direccion_envio'])); ?>
                            </p>
                        <?php else: ?>
                            <p class="text-muted mb-0">
                                <i class="bi-exclamation-circle me-2"></i>
                                No se especificó dirección de envío
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Columna derecha: Método de pago y resumen -->
            <div class="col-md-6 mb-4">
                <div class="card info-card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi-credit-card me-2 text-primary"></i>
                            Método de Pago
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($pedido['metodo_pago_nombre'])): ?>
                            <div class="d-flex align-items-center">
                                <div class="bg-light rounded p-3 me-3">
                                    <i class="bi <?php echo $pedido['metodo_pago_icono'] ?? 'bi-credit-card'; ?> fs-1"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1"><?php echo htmlspecialchars($pedido['metodo_pago_nombre']); ?></h5>
                                    <?php if (!empty($pedido['metodo_pago_descripcion'])): ?>
                                        <p class="text-muted mb-0 small">
                                            <?php echo htmlspecialchars($pedido['metodo_pago_descripcion']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">
                                <i class="bi-exclamation-circle me-2"></i>
                                Método de pago no especificado
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card info-card mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="bi-pie-chart me-2 text-primary"></i>
                            Resumen
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span class="fw-bold">€<?php echo number_format($subtotal, 2, ',', '.'); ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Envío:</span>
                            <span class="fw-bold">
                                <?php 
                                $gastos_envio = $pedido['total'] - $subtotal;
                                echo $gastos_envio > 0 ? '€' . number_format($gastos_envio, 2, ',', '.') : 'Gratis';
                                ?>
                            </span>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between fw-bold fs-5">
                            <span>Total pagado:</span>
                            <span class="text-primary">€<?php echo number_format($pedido['total'], 2, ',', '.'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Productos del pedido -->
        <div class="card info-card mt-2">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi-box-seam me-2 text-primary"></i>
                    Productos (<?php echo count($items); ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center">Cantidad</th>
                                <th class="text-end">Precio Unitario</th>
                                <th class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4">
                                        <i class="bi-exclamation-circle text-warning me-2"></i>
                                        No hay productos en este pedido
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($item['imagen_url'])): ?>
                                                    <img src="<?php echo $item['imagen_url']; ?>" 
                                                         alt="<?php echo htmlspecialchars($item['nombre']); ?>"
                                                         class="product-image me-3">
                                                <?php else: ?>
                                                    <div class="product-image me-3 bg-light d-flex align-items-center justify-content-center">
                                                        <i class="bi-image text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?php echo htmlspecialchars($item['nombre']); ?></strong>
                                                    <?php if (!empty($item['descripcion'])): ?>
                                                        <p class="text-muted small mb-0">
                                                            <?php echo substr(htmlspecialchars($item['descripcion']), 0, 100); ?>
                                                            <?php if (strlen($item['descripcion']) > 100): ?>...<?php endif; ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="badge bg-secondary"><?php echo $item['cantidad']; ?></span>
                                        </td>
                                        <td class="text-end align-middle">
                                            €<?php echo number_format($item['precio_unitario'], 2, ',', '.'); ?>
                                        </td>
                                        <td class="text-end align-middle fw-bold">
                                            €<?php echo number_format($item['precio_unitario'] * $item['cantidad'], 2, ',', '.'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($items)): ?>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">Total del pedido:</td>
                                    <td class="text-end fw-bold text-primary fs-5">
                                        €<?php echo number_format($pedido['total'], 2, ',', '.'); ?>
                                    </td>
                                </tr>
                            </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        <!-- Botones de acción -->
        <div class="d-flex justify-content-between mt-4">
            <a href="admin_pedidos.php" class="btn btn-outline-secondary">
                <i class="bi-arrow-left me-2"></i>Volver a la lista
            </a>
            <div>
                <!-- Aquí podrías añadir botones para cambiar estado si lo deseas -->
            </div>
        </div>
    </div>

    <footer class="bg-light py-3 mt-5 text-center text-muted small">
        <div class="container">
            ALAZÓN Admin &copy; <?php echo date('Y'); ?> - Documento generado el <?php echo date('d/m/Y H:i'); ?>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>