<?php
require_once('session.php');
require_once('conexion.php');
require_once('config.php');

if (!$logged_in) {
    header('Location: login.php');
    exit;
}

$db = new Database();
$con = $db->getCon();

$mensaje = "";
$carrito_vacio = true;
$total_carrito = 0.00;
$cantidad_total = 0; // ← NUEVA VARIABLE PARA LA CANTIDAD TOTAL
$items_carrito = [];

// Obtener carrito activo del usuario
$sql_carrito = "SELECT o.id as order_id, oi.id as item_id, p.*, oi.cantidad, oi.precio_unitario, 
                (oi.cantidad * oi.precio_unitario) as subtotal_item
                FROM orders o
                JOIN order_items oi ON o.id = oi.order_id
                JOIN products p ON oi.product_id = p.id
                WHERE o.user_id = '$user_id' AND o.estado = 'carrito'
                ORDER BY oi.id DESC";

$result = mysqli_query($con, $sql_carrito);

if ($result && mysqli_num_rows($result) > 0) {
    $carrito_vacio = false;
    $order_id = null;
    
    while ($row = mysqli_fetch_assoc($result)) {
        if (!$order_id) {
            $order_id = $row['order_id'];
        }
        
        // CONSTRUIR URL DE LA IMAGEN CORRECTAMENTE
        $imagen_url = 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg'; // Imagen por defecto
        if (!empty($row['imagen'])) {
            $ruta_imagen = 'uploads/productos/' . $row['imagen'];
            if (file_exists($ruta_imagen)) {
                $imagen_url = $ruta_imagen;
            }
        }
        
        // Calcular precio en moneda del usuario
        $precio_formateado = formatearPrecio(
            $row['precio_unitario'], 
            $moneda_codigo, 
            $moneda_simbolo
        );
        
        $subtotal_formateado = formatearPrecio(
            $row['subtotal_item'], 
            $moneda_codigo, 
            $moneda_simbolo
        );
        
        $items_carrito[] = [
            'item_id' => $row['item_id'],
            'product_id' => $row['id'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'precio_unitario' => $row['precio_unitario'],
            'precio_formateado' => $precio_formateado,
            'cantidad' => $row['cantidad'],
            'stock' => $row['stock'],
            'imagen' => $row['imagen'], // Guardamos el nombre del archivo
            'imagen_url' => $imagen_url, // Guardamos la URL completa
            'subtotal' => $row['subtotal_item'],
            'subtotal_formateado' => $subtotal_formateado
        ];
        
        $total_carrito += $row['subtotal_item'];
        $cantidad_total += $row['cantidad'];
    }
}

// Procesar acciones del carrito
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['actualizar_cantidad'])) {
        $item_id = intval($_POST['item_id']);
        $nueva_cantidad = intval($_POST['cantidad']);
        
        // Validar stock disponible
        $sql_stock = "SELECT p.stock, oi.product_id 
                      FROM order_items oi 
                      JOIN products p ON oi.product_id = p.id 
                      WHERE oi.id = $item_id";
        $result_stock = mysqli_query($con, $sql_stock);
        
        if ($result_stock && $row = mysqli_fetch_assoc($result_stock)) {
            if ($nueva_cantidad <= 0) {
                // Eliminar item si cantidad es 0 o negativa
                $sql_delete = "DELETE FROM order_items WHERE id = $item_id";
                mysqli_query($con, $sql_delete);
                $mensaje = "<div class='alert alert-success'>Producto eliminado del carrito.</div>";
            } elseif ($nueva_cantidad <= $row['stock']) {
                // Actualizar cantidad
                $sql_update = "UPDATE order_items SET cantidad = $nueva_cantidad WHERE id = $item_id";
                if (mysqli_query($con, $sql_update)) {
                    $mensaje = "<div class='alert alert-success'>Cantidad actualizada.</div>";
                }
            } else {
                $mensaje = "<div class='alert alert-danger'>No hay suficiente stock disponible.</div>";
            }
        }
        
        // Recargar la página para ver cambios
        header("Location: carrito.php");
        exit;
    }
    
    if (isset($_POST['eliminar_item'])) {
        $item_id = intval($_POST['item_id']);
        $sql_delete = "DELETE FROM order_items WHERE id = $item_id";
        
        if (mysqli_query($con, $sql_delete)) {
            $mensaje = "<div class='alert alert-success'>Producto eliminado del carrito.</div>";
        }
        
        header("Location: carrito.php");
        exit;
    }
    
    if (isset($_POST['vaciar_carrito'])) {
        if ($order_id) {
            $sql_delete = "DELETE FROM order_items WHERE order_id = $order_id";
            
            if (mysqli_query($con, $sql_delete)) {
                $mensaje = "<div class='alert alert-success'>Carrito vaciado.</div>";
                $carrito_vacio = true;
                $items_carrito = [];
                $total_carrito = 0;
                $cantidad_total = 0;
            }
        }
        
        header("Location: carrito.php");
        exit;
    }
    
    if (isset($_POST['proceder_pago']) && !$carrito_vacio) {
        header('Location: checkoutPago.php');
        exit;
    }
}

$total_formateado = formatearPrecio($total_carrito, $moneda_codigo, $moneda_simbolo);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Carrito de Compras - ALAZÓN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .cart-item {
            transition: all 0.3s ease;
            border: 1px solid #dee2e6;
        }
        .cart-item:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .cart-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
        }
        .quantity-input {
            width: 80px;
            text-align: center;
        }
        .empty-cart {
            padding: 60px 0;
            text-align: center;
        }
        .empty-cart i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        .summary-card {
            position: sticky;
            top: 20px;
        }
        @media (max-width: 768px) {
            .cart-img {
                width: 80px;
                height: 80px;
            }
        }
        .badge-carrito {
            font-size: 0.75em;
            padding: 0.25em 0.5em;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="index.php">ALAZÓN</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="SNosotros.php">Sobre Nosotros</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">Tienda</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="catalogo.php">TODOS LOS PRODUCTOS</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#!">MEJOR VALORADOS</a></li>
                            <li><a class="dropdown-item" href="#!">REBAJAS</a></li>
                        </ul>
                    </li>
                </ul>
                <form class="d-flex">
                    <?php if ($logged_in): ?>
                        <!-- Mostrar moneda del usuario -->
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
                                <li><a class="dropdown-item" href="mis_pedidos.php">Mis Pedidos</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php">Cerrar Sesión</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-dark me-2">
                            <i class="bi-person-fill me-1"></i>
                            Iniciar Sesión
                        </a>
                    <?php endif; ?>
                    
                    <a href="carrito.php" class="btn btn-outline-dark position-relative">
                        <i class="bi-cart-fill me-1"></i>
                        Carrito
                        <?php if (!$carrito_vacio && $cantidad_total > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger badge-carrito">
                                <?php echo $cantidad_total; ?>
                                <span class="visually-hidden">productos en el carrito</span>
                            </span>
                        <?php else: ?>
                            <span class="badge bg-dark text-white ms-1 rounded-pill">0</span>
                        <?php endif; ?>
                    </a>
                </form>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h1 class="mb-4">Mi Carrito de Compras</h1>
        
        <?php echo $mensaje; ?>
        
        <?php if ($carrito_vacio): ?>
            <div class="empty-cart">
                <i class="bi-cart-x"></i>
                <h3 class="mb-3">Tu carrito está vacío</h3>
                <p class="text-muted mb-4">¡Agrega algunos productos para comenzar a comprar!</p>
                <a href="catalogo.php" class="btn btn-dark btn-lg">
                    <i class="bi-arrow-left me-2"></i>Ver Catálogo
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Productos (<?php echo $cantidad_total; ?> items)</h5> <!-- ← Mostrar cantidad total -->
                            <form method="POST">
                                <button type="submit" name="vaciar_carrito" class="btn btn-sm btn-outline-danger" 
                                        onclick="return confirm('¿Estás seguro de vaciar todo el carrito?')">
                                    <i class="bi-trash me-1"></i>Vaciar Carrito
                                </button>
                            </form>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($items_carrito as $item): ?>
                                <div class="cart-item p-3 border-bottom">
                                    <div class="row align-items-center">
                                        <div class="col-md-2 col-4">
                                            <img src="<?php echo htmlspecialchars($item['imagen_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['nombre']); ?>" 
                                                 class="img-fluid cart-img rounded">
                                        </div>
                                        <div class="col-md-5 col-8">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($item['nombre']); ?></h6>
                                            <?php if ($item['descripcion']): ?>
                                                <p class="text-muted small mb-2"><?php echo substr($item['descripcion'], 0, 100); ?>...</p>
                                            <?php endif; ?>
                                            <div class="text-success mb-2">
                                                <?php echo $item['precio_formateado']; ?> cada uno
                                            </div>
                                            <?php if ($item['stock'] < $item['cantidad']): ?>
                                                <div class="text-danger small">
                                                    <i class="bi-exclamation-triangle"></i> 
                                                    Stock insuficiente (disponible: <?php echo $item['stock']; ?>)
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-3 col-6">
                                            <form method="POST" class="d-flex align-items-center">
                                                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                <div class="input-group input-group-sm">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="decrementQuantity(<?php echo $item['item_id']; ?>)">-</button>
                                                    <input type="number" class="form-control quantity-input" 
                                                           name="cantidad" id="quantity-<?php echo $item['item_id']; ?>"
                                                           value="<?php echo $item['cantidad']; ?>" min="1" max="<?php echo $item['stock']; ?>">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="incrementQuantity(<?php echo $item['item_id']; ?>, <?php echo $item['stock']; ?>)">+</button>
                                                </div>
                                                <button type="submit" name="actualizar_cantidad" class="btn btn-sm btn-outline-primary ms-2">
                                                    <i class="bi-check"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <div class="col-md-2 col-6 text-end">
                                            <div class="fw-bold mb-2"><?php echo $item['subtotal_formateado']; ?></div>
                                            <div class="text-muted small">
                                                <?php echo $item['cantidad']; ?> x <?php echo $item['precio_formateado']; ?>
                                            </div>
                                            <form method="POST" class="mt-1">
                                                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                <button type="submit" name="eliminar_item" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('¿Eliminar este producto del carrito?')">
                                                    <i class="bi-trash"></i> Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-4">
                        <a href="catalogo.php" class="btn btn-outline-dark">
                            <i class="bi-arrow-left me-1"></i>Seguir Comprando
                        </a>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card summary-card">
                        <div class="card-header">
                            <h5 class="mb-0">Resumen del Pedido</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Productos (<?php echo $cantidad_total; ?>):</span> <!-- ← Mostrar cantidad total aquí también -->
                                <span><?php echo $total_formateado; ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Envío:</span>
                                <span class="text-success"><?php echo $moneda_simbolo; ?> 0,00</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Impuestos:</span>
                                <span><?php echo $moneda_simbolo; ?> 0,00</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-3 fw-bold fs-5">
                                <span>Total:</span>
                                <span><?php echo $total_formateado; ?></span>
                            </div>
                            <div class="text-center text-muted small mb-3">
                                Moneda: <?php echo $moneda_codigo; ?> (<?php echo $moneda_simbolo; ?>)
                            </div>
                            
                            <form method="POST">
                                <button type="submit" name="proceder_pago" class="btn btn-dark btn-lg w-100 mb-3">
                                    <i class="bi-lock-fill me-2"></i>Proceder al Pago
                                </button>
                            </form>
                            
                            <div class="alert alert-info small">
                                <i class="bi-info-circle me-2"></i>
                                El pago se procesará en la siguiente página. Puedes modificar tu dirección de envío y método de pago.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <footer class="bg-dark py-3 mt-auto">
        <div class="container">
            <p class="m-0 text-center text-white small">ALAZÓN &copy; 2026</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function incrementQuantity(itemId, maxStock) {
            const input = document.getElementById('quantity-' + itemId);
            let currentValue = parseInt(input.value);
            if (currentValue < maxStock) {
                input.value = currentValue + 1;
                // Auto-submit el formulario cuando cambia
                input.closest('form').querySelector('[name="actualizar_cantidad"]').click();
            }
        }
        
        function decrementQuantity(itemId) {
            const input = document.getElementById('quantity-' + itemId);
            let currentValue = parseInt(input.value);
            if (currentValue > 1) {
                input.value = currentValue - 1;
                // Auto-submit el formulario cuando cambia
                input.closest('form').querySelector('[name="actualizar_cantidad"]').click();
            }
        }
        
        // Auto-submit cuando se cambia manualmente el valor
        document.addEventListener('DOMContentLoaded', function() {
            const quantityInputs = document.querySelectorAll('.quantity-input');
            quantityInputs.forEach(input => {
                input.addEventListener('change', function() {
                    this.closest('form').submit();
                });
            });
        });
    </script>
</body>
</html>