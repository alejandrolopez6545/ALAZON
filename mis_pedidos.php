<?php
// mis_pedidos.php - Historial de pedidos del usuario (REDISEÑADO)
require_once('session.php');
require_once('conexion.php');
require_once('config.php');

if (!$logged_in) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$con = $db->getCon();

// Procesar valoración
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['valorar_producto'])) {
    $product_id = intval($_POST['product_id']);
    $order_id = intval($_POST['order_id']);
    $puntuacion = intval($_POST['puntuacion']);
    $comentario = $db->sanitize($_POST['comentario'] ?? '');
    
    // Verificar que el usuario realmente compró este producto
    $sql_verificar = "SELECT oi.id 
                      FROM orders o
                      JOIN order_items oi ON o.id = oi.order_id
                      WHERE o.user_id = '$user_id' 
                      AND o.id = $order_id
                      AND oi.product_id = $product_id
                      AND o.estado IN ('pagado', 'enviado', 'entregado')";
    $result_verificar = mysqli_query($con, $sql_verificar);
    
    if (mysqli_num_rows($result_verificar) > 0) {
        // Verificar si ya valoró este producto
        $sql_check_valoracion = "SELECT id FROM valoraciones 
                                  WHERE user_id = '$user_id' 
                                  AND product_id = $product_id 
                                  AND order_id = $order_id";
        $result_check = mysqli_query($con, $sql_check_valoracion);
        
        if (mysqli_num_rows($result_check) == 0) {
            // Insertar valoración
            $sql_insert = "INSERT INTO valoraciones (user_id, product_id, order_id, puntuacion, comentario, fecha) 
                          VALUES ('$user_id', $product_id, $order_id, $puntuacion, '$comentario', NOW())";
            
            if (mysqli_query($con, $sql_insert)) {
                // Actualizar valoración media del producto
                $sql_update_product = "UPDATE products 
                                      SET valoracion = (SELECT AVG(puntuacion) FROM valoraciones WHERE product_id = $product_id),
                                          num_valoraciones = (SELECT COUNT(*) FROM valoraciones WHERE product_id = $product_id)
                                      WHERE id = $product_id";
                mysqli_query($con, $sql_update_product);
                
                $mensaje = "<div class='alert alert-success'>¡Gracias por tu valoración!</div>";
            }
        } else {
            $mensaje = "<div class='alert alert-warning'>Ya has valorado este producto</div>";
        }
    }
}

// ELIMINAR PEDIDOS CON TOTAL = 0
$sql_eliminar_ceros = "DELETE FROM orders WHERE user_id = '$user_id' AND total = 0 AND estado != 'carrito'";
mysqli_query($con, $sql_eliminar_ceros);

// Obtener todos los pedidos del usuario (excluyendo carritos y total > 0)
$sql_pedidos = "SELECT o.*, 
                       mp.nombre as metodo_pago_nombre,
                       mp.icono as metodo_pago_icono,
                       (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as total_items,
                       (SELECT SUM(cantidad) FROM order_items WHERE order_id = o.id) as cantidad_total
                FROM orders o 
                LEFT JOIN metodos_pago mp ON o.metodo_pago_id = mp.id
                WHERE o.user_id = '$user_id' AND o.estado != 'carrito' AND o.total > 0
                ORDER BY o.fecha DESC";

$result_pedidos = mysqli_query($con, $sql_pedidos);

// Calcular total gastado y número de pedidos (solo con total > 0)
$sql_stats = "SELECT COUNT(*) as total_pedidos, SUM(total) as total_gastado 
              FROM orders 
              WHERE user_id = '$user_id' AND estado != 'carrito' AND total > 0";
$result_stats = mysqli_query($con, $sql_stats);
$stats = mysqli_fetch_assoc($result_stats);

$total_pedidos = $stats['total_pedidos'] ?? 0;
$total_gastado = $stats['total_gastado'] ?? 0;

// Obtener cantidad de items en carrito
$cantidad_carrito = 0;
$sql_carrito_count = "SELECT SUM(oi.cantidad) as total_items
                      FROM orders o
                      JOIN order_items oi ON o.id = oi.order_id
                      WHERE o.user_id = '$user_id' AND o.estado = 'carrito'";
$result_carrito_count = mysqli_query($con, $sql_carrito_count);
if ($result_carrito_count && $row = mysqli_fetch_assoc($result_carrito_count)) {
    $cantidad_carrito = $row['total_items'] ?? 0;
}

// Función para formatear fecha
function formatearFecha($fecha) {
    return date('d/m/Y', strtotime($fecha));
}

// Función para obtener color del estado (usando Bootstrap estándar)
function getBadgeEstado($estado) {
    switch($estado) {
        case 'pagado': return '<span class="badge bg-success">Pagado</span>';
        case 'pendiente': return '<span class="badge bg-warning text-dark">Pendiente</span>';
        case 'enviado': return '<span class="badge bg-info">Enviado</span>';
        case 'entregado': return '<span class="badge bg-dark">Entregado</span>';
        case 'cancelado': return '<span class="badge bg-danger">Cancelado</span>';
        default: return '<span class="badge bg-secondary">' . ucfirst($estado) . '</span>';
    }
}

// Función para generar estrellas
function generarEstrellas($puntuacion = 0, $readonly = true) {
    $html = '<div class="valoracion-estrellas">';
    for ($i = 1; $i <= 5; $i++) {
        if ($readonly) {
            if ($i <= $puntuacion) {
                $html .= '<i class="bi-star-fill text-warning"></i>';
            } else {
                $html .= '<i class="bi-star text-secondary"></i>';
            }
        } else {
            $html .= '<i class="bi-star star-input" data-valor="' . $i . '"></i>';
        }
    }
    $html .= '</div>';
    return $html;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Pedidos - ALAZÓN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .card {
            transition: transform 0.3s;
            border: 1px solid #dee2e6;
        }
        .card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .badge-carrito {
            font-size: 0.75em;
            padding: 0.25em 0.5em;
        }
        .order-header {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
        }
        .order-id {
            color: #0d6efd;
            font-weight: 600;
        }
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .stats-card {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .stats-icon {
            font-size: 2.5rem;
            color: #0d6efd;
            opacity: 0.5;
        }
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
        }
        .empty-orders i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        .valoracion-estrellas {
            font-size: 1.1rem;
        }
        .star-input {
            cursor: pointer;
            color: #ffc107;
            font-size: 1.3rem;
            transition: all 0.2s;
        }
        .star-input:hover {
            transform: scale(1.2);
        }
        .star-input.active {
            font-weight: 900;
        }
        .star-input.bi-star-fill {
            color: #ffc107;
        }
        .btn-outline-primary {
            border-color: #dee2e6;
        }
        .btn-outline-primary:hover {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
        .table-custom {
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }
        .table-custom thead {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
    </style>
</head>
<body>
    <!-- Navbar (IGUAL QUE EN CATÁLOGO) -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="index.php">ALAZÓN</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="SNosotros.php">Sobre Nosotros</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Tienda</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="catalogo.php?filtro=todos">TODOS LOS PRODUCTOS</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="catalogo.php?filtro=valorados">MEJOR VALORADOS</a></li>
                            <li><a class="dropdown-item" href="catalogo.php?filtro=rebajas">REBAJAS</a></li>
                        </ul>
                    </li>
                </ul>
                
                <!-- BUSCADOR (OPCIONAL) -->
                <form class="d-flex me-3" action="buscar.php" method="GET" style="min-width: 250px;">
                    <div class="input-group">
                        <input type="text" name="q" class="form-control" placeholder="Buscar productos...">
                        <button class="btn btn-outline-success" type="submit">
                            <i class="bi-search"></i>
                        </button>
                    </div>
                </form>
                
                <div class="d-flex align-items-center">
                    <?php if ($logged_in): ?>
                        <span class="navbar-text me-3 d-none d-md-block">
                            <small>Moneda: <?php echo $moneda_simbolo . ' ' . $moneda_codigo; ?></small>
                        </span>
                        
                        <div class="dropdown me-2">
                            <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi-person-fill me-1"></i>
                                Hola, <?php echo htmlspecialchars($usuario); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="perfil.php">Mi Perfil</a></li>
                                <li><a class="dropdown-item active" href="mis_pedidos.php">Mis Pedidos</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php">Cerrar Sesión</a></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <a href="carrito.php" class="btn btn-outline-dark position-relative">
                        <i class="bi-cart-fill me-1"></i>
                        Carrito
                        <?php if ($cantidad_carrito > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger badge-carrito">
                                <?php echo $cantidad_carrito; ?>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-dark text-white ms-1 rounded-pill">0</span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <!-- Cabecera -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0">
                    <i class="bi-box-seam me-2 text-primary"></i>
                    Mis Pedidos
                </h1>
                <p class="text-muted mb-0">Historial de tus compras</p>
            </div>
            <a href="catalogo.php" class="btn btn-outline-dark">
                <i class="bi-plus-circle me-2"></i>Seguir Comprando
            </a>
        </div>

        <?php echo $mensaje ?? ''; ?>

        <!-- ESTADÍSTICAS SOLO SI HAY PEDIDOS -->
        <?php if ($total_pedidos > 0): ?>
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="stats-card d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Pedidos</h6>
                        <h2 class="text-primary mb-0"><?php echo $total_pedidos; ?></h2>
                    </div>
                    <i class="bi-box-seam stats-icon"></i>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stats-card d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Gastado</h6>
                        <h2 class="text-success mb-0">
                            <?php echo $moneda_simbolo; ?> <?php echo number_format($total_gastado, 2, ',', '.'); ?>
                        </h2>
                    </div>
                    <i class="bi-cash-stack stats-icon text-success"></i>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (mysqli_num_rows($result_pedidos) == 0): ?>
            <!-- No hay pedidos -->
            <div class="empty-orders">
                <i class="bi-inbox"></i>
                <h3 class="mb-3">No tienes pedidos realizados</h3>
                <p class="text-muted mb-4">¡Explora nuestro catálogo y encuentra lo que buscas!</p>
                <a href="catalogo.php" class="btn btn-dark btn-lg">
                    <i class="bi-shop me-2"></i>Ir al Catálogo
                </a>
            </div>
        <?php else: ?>
            <!-- Lista de pedidos -->
            <div class="row">
                <?php while ($pedido = mysqli_fetch_assoc($result_pedidos)): 
                    $fecha_formateada = date('d/m/Y', strtotime($pedido['fecha']));
                    
                    // Obtener productos de este pedido
                    $sql_productos = "SELECT oi.*, p.nombre, p.imagen, p.id as product_id,
                                             (SELECT COUNT(*) FROM valoraciones v 
                                              WHERE v.product_id = p.id 
                                              AND v.order_id = oi.order_id 
                                              AND v.user_id = '$user_id') as ya_valorado,
                                             (SELECT puntuacion FROM valoraciones v 
                                              WHERE v.product_id = p.id 
                                              AND v.order_id = oi.order_id 
                                              AND v.user_id = '$user_id') as puntuacion_usuario
                                     FROM order_items oi
                                     JOIN products p ON oi.product_id = p.id
                                     WHERE oi.order_id = {$pedido['id']}
                                     ORDER BY oi.id ASC";
                    $result_productos = mysqli_query($con, $sql_productos);
                    $productos = [];
                    while ($prod = mysqli_fetch_assoc($result_productos)) {
                        // Construir URL de la imagen
                        $imagen_url = 'https://dummyimage.com/60x60/dee2e6/6c757d.jpg';
                        if (!empty($prod['imagen'])) {
                            $ruta_imagen = 'uploads/productos/' . $prod['imagen'];
                            if (file_exists($ruta_imagen)) {
                                $imagen_url = $ruta_imagen;
                            }
                        }
                        $prod['imagen_url'] = $imagen_url;
                        $productos[] = $prod;
                    }
                ?>
                    <div class="col-12 mb-4">
                        <div class="card">
                            <!-- Cabecera del pedido -->
                            <div class="order-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-1">
                                        <span class="order-id">#<?php echo $pedido['id']; ?></span>
                                        <span class="ms-2"><?php echo getBadgeEstado($pedido['estado']); ?></span>
                                    </h5>
                                    <p class="text-muted mb-0 small">
                                        <i class="bi-calendar me-1"></i><?php echo $fecha_formateada; ?>
                                        <?php if ($pedido['metodo_pago_nombre']): ?>
                                            <span class="mx-2">•</span>
                                            <i class="bi-credit-card me-1"></i><?php echo $pedido['metodo_pago_nombre']; ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <div class="mb-1">Total del pedido</div>
                                    <h4 class="text-primary mb-0">
                                        <?php echo $moneda_simbolo; ?> <?php echo number_format($pedido['total'], 2, ',', '.'); ?>
                                    </h4>
                                </div>
                            </div>

                            <!-- Cuerpo del pedido (productos con valoración) -->
                            <div class="card-body">
                                <h6 class="mb-3">Productos comprados</h6>
                                
                                <?php foreach ($productos as $producto): ?>
                                    <div class="row align-items-center mb-3 pb-3 border-bottom">
                                        <div class="col-md-5">
                                            <div class="d-flex align-items-center">
                                                <img src="<?php echo $producto['imagen_url']; ?>" 
                                                     alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                                     class="product-image me-3">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                                    <div class="text-muted small">
                                                        Cantidad: <?php echo $producto['cantidad']; ?> x 
                                                        <?php echo $moneda_simbolo; ?> <?php echo number_format($producto['precio_unitario'], 2, ',', '.'); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <?php if ($pedido['estado'] != 'cancelado'): ?>
                                                <?php if ($producto['ya_valorado'] > 0): ?>
                                                    <!-- Ya valorado -->
                                                    <div class="text-success">
                                                        <small>Tu valoración:</small>
                                                        <div class="valoracion-estrellas">
                                                            <?php echo generarEstrellas($producto['puntuacion_usuario'], true); ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Botón para valorar -->
                                                    <button class="btn btn-outline-warning btn-sm" 
                                                            onclick="abrirValoracion(<?php echo $producto['product_id']; ?>, <?php echo $pedido['id']; ?>, '<?php echo htmlspecialchars($producto['nombre']); ?>')">
                                                        <i class="bi-star me-1"></i>Valorar producto
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-danger small">
                                                    <i class="bi-exclamation-triangle me-1"></i>Pedido cancelado
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <span class="fw-bold">
                                                <?php echo $moneda_simbolo; ?> <?php echo number_format($producto['precio_unitario'] * $producto['cantidad'], 2, ',', '.'); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal de Valoración -->
    <div class="modal fade" id="valoracionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi-star-fill text-warning me-2"></i>
                            Valorar Producto
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="modal_product_id">
                        <input type="hidden" name="order_id" id="modal_order_id">
                        
                        <div class="text-center mb-4">
                            <h6 id="modal_producto_nombre" class="mb-3"></h6>
                            
                            <label class="form-label d-block">Tu puntuación</label>
                            <div class="valoracion-estrellas mb-2" id="estrellas-container">
                                <i class="bi-star star-input" data-valor="1"></i>
                                <i class="bi-star star-input" data-valor="2"></i>
                                <i class="bi-star star-input" data-valor="3"></i>
                                <i class="bi-star star-input" data-valor="4"></i>
                                <i class="bi-star star-input" data-valor="5"></i>
                            </div>
                            <input type="hidden" name="puntuacion" id="puntuacion" value="0">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Comentario (opcional)</label>
                            <textarea name="comentario" class="form-control" rows="3" 
                                      placeholder="Cuéntanos tu experiencia con este producto..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="valorar_producto" class="btn btn-warning">
                            <i class="bi-star-fill me-2"></i>Enviar valoración
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark py-4 mt-5">
        <div class="container text-center">
            <p class="text-white mb-0 small">ALAZÓN &copy; <?php echo date('Y'); ?></p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables para el modal de valoración
        let valoracionActual = 0;
        
        function abrirValoracion(productId, orderId, nombreProducto) {
            document.getElementById('modal_product_id').value = productId;
            document.getElementById('modal_order_id').value = orderId;
            document.getElementById('modal_producto_nombre').textContent = nombreProducto;
            
            // Resetear estrellas
            resetearEstrellas();
            
            new bootstrap.Modal(document.getElementById('valoracionModal')).show();
        }
        
        function resetearEstrellas() {
            valoracionActual = 0;
            document.getElementById('puntuacion').value = 0;
            const estrellas = document.querySelectorAll('.star-input');
            estrellas.forEach(star => {
                star.className = 'bi-star star-input';
            });
        }
        
        // Manejar clic en estrellas
        document.addEventListener('DOMContentLoaded', function() {
            const estrellas = document.querySelectorAll('.star-input');
            const puntuacionInput = document.getElementById('puntuacion');
            
            estrellas.forEach(star => {
                star.addEventListener('mouseover', function() {
                    const valor = parseInt(this.dataset.valor);
                    highlightStars(valor, false);
                });
                
                star.addEventListener('mouseout', function() {
                    highlightStars(valoracionActual, true);
                });
                
                star.addEventListener('click', function() {
                    valoracionActual = parseInt(this.dataset.valor);
                    puntuacionInput.value = valoracionActual;
                    highlightStars(valoracionActual, true);
                });
            });
            
            function highlightStars(valor, permanentes) {
                estrellas.forEach(star => {
                    const starValor = parseInt(star.dataset.valor);
                    if (starValor <= valor) {
                        star.className = 'bi-star-fill star-input' + (permanentes ? ' active' : '');
                    } else {
                        star.className = 'bi-star star-input';
                    }
                });
            }
            
            // Resetear al cerrar modal
            document.getElementById('valoracionModal').addEventListener('hidden.bs.modal', function() {
                resetearEstrellas();
            });
        });
    </script>
</body>
</html>