<?php
// checkoutPago.php - Página de finalización de compra
require_once('session.php');
require_once('conexion.php');
require_once('config.php');

if (!$logged_in) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$con = $db->getCon();

// Obtener el carrito del usuario para calcular el total
$sql_carrito = "SELECT o.id as order_id, oi.id as item_id, p.*, oi.cantidad, oi.precio_unitario,
                (oi.cantidad * oi.precio_unitario) as subtotal
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE o.user_id = '$user_id' AND o.estado = 'carrito'";
$result_carrito = mysqli_query($con, $sql_carrito);

$items_carrito = [];
$total_carrito = 0;
$cantidad_total = 0;

if ($result_carrito && mysqli_num_rows($result_carrito) > 0) {
    while ($row = mysqli_fetch_assoc($result_carrito)) {
        // Construir URL de la imagen
        $imagen_url = 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg';
        if (!empty($row['imagen'])) {
            $ruta_imagen = 'uploads/productos/' . $row['imagen'];
            if (file_exists($ruta_imagen)) {
                $imagen_url = $ruta_imagen;
            }
        }
        
        $items_carrito[] = [
            'id' => $row['id'],
            'nombre' => $row['nombre'],
            'cantidad' => $row['cantidad'],
            'precio_unitario' => $row['precio_unitario'],
            'precio_formateado' => formatearPrecio($row['precio_unitario'], $moneda_codigo, $moneda_simbolo),
            'subtotal' => $row['subtotal'],
            'subtotal_formateado' => formatearPrecio($row['subtotal'], $moneda_codigo, $moneda_simbolo),
            'imagen_url' => $imagen_url
        ];
        
        $total_carrito += $row['subtotal'];
        $cantidad_total += $row['cantidad'];
    }
} else {
    // Si el carrito está vacío, redirigir
    header('Location: carrito.php?mensaje=Carrito+vacio');
    exit;
}

// Calcular envío (ejemplo: gratis >50€, 5.99€ si no)
$gastos_envio = ($total_carrito >= 50) ? 0 : 5.99;
$total_final = $total_carrito + $gastos_envio;

// Formatear totales
$total_carrito_formateado = formatearPrecio($total_carrito, $moneda_codigo, $moneda_simbolo);
$gastos_envio_formateado = formatearPrecio($gastos_envio, $moneda_codigo, $moneda_simbolo);
$total_final_formateado = formatearPrecio($total_final, $moneda_codigo, $moneda_simbolo);

// Obtener métodos de pago disponibles para el usuario
function obtenerMetodosPagoDisponibles($con) {
    $sql = "SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY orden, nombre";
    $result = mysqli_query($con, $sql);
    $metodos = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $metodos[] = $row;
    }
    return $metodos;
}

$metodos_pago = obtenerMetodosPagoDisponibles($con);

// Obtener información del usuario para la dirección de envío
$sql_usuario = "SELECT u.*, p.nombre as pais_nombre, p.moneda_codigo 
                FROM users u 
                JOIN paises p ON u.pais = p.codigo 
                WHERE u.id = '$user_id'";
$result = mysqli_query($con, $sql_usuario);
$usuario_info = mysqli_fetch_assoc($result);

// Procesar el pedido
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['realizar_pedido'])) {
    $direccion_envio = $db->sanitize($_POST['direccion_envio']);
    $metodo_pago_id = intval($_POST['metodo_pago']);
    
    // Verificar que el carrito no esté vacío
    if (empty($items_carrito)) {
        $error = "El carrito está vacío";
    } else {
        // INICIAR TRANSACCIÓN
        mysqli_begin_transaction($con);
        
        try {
            $stock_suficiente = true;
            $productos_sin_stock = [];
            
            // VERIFICAR STOCK ACTUAL para cada producto
            foreach ($items_carrito as $item) {
                $sql_check = "SELECT stock FROM products WHERE id = {$item['id']} FOR UPDATE";
                $result_check = mysqli_query($con, $sql_check);
                $producto_actual = mysqli_fetch_assoc($result_check);
                
                if ($producto_actual['stock'] < $item['cantidad']) {
                    $stock_suficiente = false;
                    $productos_sin_stock[] = $item['nombre'] . " (disponible: " . $producto_actual['stock'] . ")";
                }
            }
            
            if (!$stock_suficiente) {
                throw new Exception("Stock insuficiente para: " . implode(", ", $productos_sin_stock));
            }
            
            // Crear el pedido
            $sql_pedido = "INSERT INTO orders (user_id, fecha, direccion_envio, metodo_pago_id, total, estado) 
                           VALUES ('$user_id', NOW(), '$direccion_envio', '$metodo_pago_id', '$total_final', 'pagado')";
            
            if (!mysqli_query($con, $sql_pedido)) {
                throw new Exception("Error al crear el pedido: " . mysqli_error($con));
            }
            
            $pedido_id = mysqli_insert_id($con);
            
            // Actualizar items del carrito con el nuevo order_id
            $sql_carrito_id = "SELECT id FROM orders WHERE user_id = '$user_id' AND estado = 'carrito' LIMIT 1";
            $result_carrito_id = mysqli_query($con, $sql_carrito_id);
            $carrito = mysqli_fetch_assoc($result_carrito_id);
            
            if ($carrito) {
                $sql_update_items = "UPDATE order_items SET order_id = '$pedido_id' WHERE order_id = {$carrito['id']}";
                if (!mysqli_query($con, $sql_update_items)) {
                    throw new Exception("Error al actualizar items: " . mysqli_error($con));
                }
                
                // ACTUALIZAR STOCK (restando las cantidades compradas)
                foreach ($items_carrito as $item) {
                    // Obtener stock actual
                    $sql_stock_actual = "SELECT stock FROM products WHERE id = {$item['id']}";
                    $result_stock_actual = mysqli_query($con, $sql_stock_actual);
                    $stock_actual = mysqli_fetch_assoc($result_stock_actual);
                    
                    // Calcular nuevo stock (nunca menor que 0)
                    $nuevo_stock = max(0, $stock_actual['stock'] - $item['cantidad']);
                    
                    $sql_update_stock = "UPDATE products SET stock = $nuevo_stock WHERE id = {$item['id']}";
                    mysqli_query($con, $sql_update_stock);
                }
                
                // Marcar el carrito como completado
                $sql_update_carrito = "UPDATE orders SET estado = 'completado' WHERE id = {$carrito['id']}";
                if (!mysqli_query($con, $sql_update_carrito)) {
                    throw new Exception("Error al actualizar carrito: " . mysqli_error($con));
                }
            }
            
            // CONFIRMAR TRANSACCIÓN
            mysqli_commit($con);
            
            // Redirigir a confirmación
            header("Location: confirmacion.php?id=$pedido_id");
            exit;
            
        } catch (Exception $e) {
            // REVERTIR TRANSACCIÓN en caso de error
            mysqli_rollback($con);
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - ALAZÓN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .checkout-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        .product-image-sm {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
        }
        .order-summary {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
        }
        .envio-gratis {
            color: #28a745;
            font-weight: 600;
        }
        .metodo-pago-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .metodo-pago-card:hover {
            border-color: #0d6efd;
            background: #f8f9fa;
        }
        .metodo-pago-card.selected {
            border-color: #0d6efd;
            background: #e7f1ff;
        }
    </style>
</head>
<body>
    <!-- Header simplificado -->
    <div class="checkout-header">
        <div class="container">
            <div class="d-flex align-items-center">
                <a href="carrito.php" class="text-decoration-none me-4">
                    <i class="bi-arrow-left"></i> Volver al carrito
                </a>
                <h4 class="mb-0">ALAZÓN <span class="text-muted fs-6">/ Checkout</span></h4>
            </div>
        </div>
    </div>

    <div class="container py-4">
        <!-- Mensaje de error si hay -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Columna principal: Dirección y Método de pago -->
            <div class="col-lg-8">
                <form method="POST" id="checkoutForm">
                    <!-- Dirección de envío -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi-geo-alt-fill text-primary me-2"></i>
                                Dirección de envío
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong><?php echo htmlspecialchars($usuario_info['nombre']); ?></strong></p>
                            <p class="mb-2 text-muted"><?php echo htmlspecialchars($usuario_info['email']); ?></p>
                            <p class="mb-2"><span class="badge bg-light text-dark"><?php echo htmlspecialchars($usuario_info['pais_nombre']); ?></span></p>
                            
                            <div class="mb-3">
                                <label for="direccion_envio" class="form-label">Dirección completa</label>
                                <textarea class="form-control" id="direccion_envio" name="direccion_envio" rows="2" 
                                          placeholder="Calle, número, ciudad, código postal..." required></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Métodos de pago -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="bi-credit-card-2-front-fill text-primary me-2"></i>
                                Método de pago
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($metodos_pago)): ?>
                                <div class="alert alert-warning">
                                    <i class="bi-exclamation-triangle me-2"></i>
                                    No hay métodos de pago disponibles para tu país.
                                </div>
                            <?php else: ?>
                                <?php foreach ($metodos_pago as $metodo): ?>
                                    <div class="metodo-pago-card" onclick="selectMetodo(<?php echo $metodo['id']; ?>)">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" 
                                                   name="metodo_pago" 
                                                   value="<?php echo $metodo['id']; ?>" 
                                                   id="metodo<?php echo $metodo['id']; ?>" 
                                                   required>
                                            <label class="form-check-label w-100" for="metodo<?php echo $metodo['id']; ?>">
                                                <div class="d-flex align-items-center">
                                                    <i class="bi <?php echo $metodo['icono']; ?> fs-4 me-3"></i>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($metodo['nombre']); ?></strong>
                                                        <?php if ($metodo['descripcion']): ?>
                                                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($metodo['descripcion']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Columna lateral: Resumen del pedido -->
            <div class="col-lg-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Resumen del pedido</h5>
                    </div>
                    <div class="card-body">
                        <!-- Lista de productos -->
                        <div class="mb-3">
                            <p class="mb-2 text-muted">Productos (<?php echo $cantidad_total; ?>):</p>
                            <?php foreach ($items_carrito as $item): ?>
                                <div class="d-flex align-items-center mb-2">
                                    <img src="<?php echo $item['imagen_url']; ?>" 
                                         alt="<?php echo htmlspecialchars($item['nombre']); ?>"
                                         class="product-image-sm me-2">
                                    <div class="flex-grow-1">
                                        <small class="d-block"><?php echo htmlspecialchars($item['nombre']); ?></small>
                                        <small class="text-muted">
                                            <?php echo $item['cantidad']; ?> x <?php echo $item['precio_formateado']; ?>
                                        </small>
                                    </div>
                                    <small class="fw-bold"><?php echo $item['subtotal_formateado']; ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <hr>

                        <!-- Cálculos -->
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span><?php echo $total_carrito_formateado; ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Envío:</span>
                            <span class="<?php echo $gastos_envio == 0 ? 'envio-gratis' : ''; ?>">
                                <?php if ($gastos_envio == 0): ?>
                                    <i class="bi-gift me-1"></i>GRATIS
                                <?php else: ?>
                                    <?php echo $gastos_envio_formateado; ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <?php if ($gastos_envio > 0 && $total_carrito < 50): ?>
                            <div class="alert alert-info py-2 small">
                                <i class="bi-info-circle me-1"></i>
                                Añade <?php echo formatearPrecio(50 - $total_carrito, $moneda_codigo, $moneda_simbolo); ?> 
                                más para envío GRATIS
                            </div>
                        <?php endif; ?>

                        <hr>

                        <div class="d-flex justify-content-between fw-bold fs-5 mb-3">
                            <span>Total:</span>
                            <span><?php echo $total_final_formateado; ?></span>
                        </div>

                        <div class="text-center text-muted small mb-3">
                            Moneda: <?php echo $moneda_codigo; ?> (<?php echo $moneda_simbolo; ?>)
                        </div>

                        <!-- Botón de pago -->
                        <?php if (!empty($metodos_pago)): ?>
                            <button type="submit" form="checkoutForm" name="realizar_pedido" 
                                    class="btn btn-dark btn-lg w-100 mb-2">
                                <i class="bi-lock-fill me-2"></i>Confirmar pedido
                            </button>
                            <p class="text-center small text-muted mb-0">
                                <i class="bi-shield-check me-1"></i>
                                Pago seguro
                            </p>
                        <?php else: ?>
                            <button class="btn btn-secondary btn-lg w-100" disabled>
                                No hay métodos de pago
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer simple -->
    <footer class="bg-light py-3 mt-5">
        <div class="container text-center text-muted small">
            ALAZÓN &copy; <?php echo date('Y'); ?> - Compra 100% segura
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Seleccionar método de pago
        function selectMetodo(id) {
            document.getElementById('metodo' + id).checked = true;
        }

        // Marcar visualmente el método seleccionado
        document.querySelectorAll('.metodo-pago-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.metodo-pago-card').forEach(c => 
                    c.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
    </script>
</body>
</html>