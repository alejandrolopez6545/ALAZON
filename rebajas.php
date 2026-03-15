<?php
require_once('session.php');
require_once('conexion.php');
require_once('config.php');

$db = new Database();
$con = $db->getCon();

// Obtener información de moneda del usuario
if ($logged_in) {
    $moneda_usuario = [
        'codigo' => $moneda_codigo,
        'simbolo' => $moneda_simbolo
    ];
} else {
    $moneda_usuario = [
        'codigo' => 'EUR',
        'simbolo' => '€'
    ];
}

// Función para obtener productos en rebaja
function obtenerProductosRebajas($conexion, $moneda_usuario) {
    // Primero verificamos si existen las columnas de rebaja
    $check_columns = "SHOW COLUMNS FROM products LIKE 'en_rebaja'";
    $result_check = mysqli_query($conexion, $check_columns);
    
    if ($result_check && mysqli_num_rows($result_check) > 0) {
        // Si existe la columna, usar filtro por rebaja
        $sql = "SELECT p.*, c.nombre as categoria_nombre 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.estado = 'activo' 
                AND p.en_rebaja = 1
                ORDER BY p.porcentaje_rebaja DESC";
    } else {
        // Si no existe, obtener productos aleatorios
        $sql = "SELECT p.*, c.nombre as categoria_nombre 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                WHERE p.estado = 'activo' 
                ORDER BY RAND() LIMIT 8";
    }
    
    $result = mysqli_query($conexion, $sql);
    $productos = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $precio_mostrar = $row['precio'];
        $precio_original = isset($row['precio_original']) && $row['precio_original'] > $row['precio'] 
            ? $row['precio_original'] 
            : $row['precio'];
        
        $porcentaje_rebaja = isset($row['porcentaje_rebaja']) ? $row['porcentaje_rebaja'] : 0;
        if ($porcentaje_rebaja == 0 && $precio_original > $precio_mostrar) {
            $porcentaje_rebaja = round((($precio_original - $precio_mostrar) / $precio_original) * 100);
        }
        
        $precio_formateado = formatearPrecio(
            $precio_mostrar, 
            $moneda_usuario['codigo'], 
            $moneda_usuario['simbolo']
        );
        
        $precio_original_formateado = formatearPrecio(
            $precio_original, 
            $moneda_usuario['codigo'], 
            $moneda_usuario['simbolo']
        );
        
        $productos[] = [
            'id' => $row['id'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'precio' => $precio_mostrar,
            'precio_formateado' => $precio_formateado,
            'precio_original' => $precio_original,
            'precio_original_formateado' => $precio_original_formateado,
            'porcentaje_rebaja' => $porcentaje_rebaja,
            'stock' => $row['stock'],
            'imagen_url' => $row['imagen_url'] ?: 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg',
            'categoria' => $row['categoria_nombre'],
            'estado' => $row['estado'],
            'en_rebaja' => $row['en_rebaja'] ?? 0
        ];
    }
    
    return $productos;
}

// Procesar añadir al carrito
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart']) && $logged_in) {
    $product_id = intval($_POST['product_id']);
    $cantidad = intval($_POST['cantidad'] ?? 1);
    
    // Verificar stock
    $sql_stock = "SELECT stock, precio FROM products WHERE id = $product_id AND estado = 'activo'";
    $result_stock = mysqli_query($con, $sql_stock);
    
    if ($result_stock && $row = mysqli_fetch_assoc($result_stock)) {
        if ($cantidad <= 0) {
            $mensaje_carrito = "<div class='alert alert-danger'>Cantidad inválida.</div>";
        } elseif ($cantidad > $row['stock']) {
            $mensaje_carrito = "<div class='alert alert-danger'>No hay suficiente stock disponible.</div>";
        } else {
            // Buscar carrito activo del usuario
            $sql_carrito = "SELECT id FROM orders WHERE user_id = '$user_id' AND estado = 'carrito'";
            $result_carrito = mysqli_query($con, $sql_carrito);
            
            if ($result_carrito && mysqli_num_rows($result_carrito) > 0) {
                $carrito = mysqli_fetch_assoc($result_carrito);
                $order_id = $carrito['id'];
            } else {
                $sql_new_order = "INSERT INTO orders (user_id, estado) VALUES ('$user_id', 'carrito')";
                if (mysqli_query($con, $sql_new_order)) {
                    $order_id = mysqli_insert_id($con);
                } else {
                    $mensaje_carrito = "<div class='alert alert-danger'>Error al crear carrito.</div>";
                }
            }
            
            if (isset($order_id)) {
                $sql_check = "SELECT id, cantidad FROM order_items WHERE order_id = $order_id AND product_id = $product_id";
                $result_check = mysqli_query($con, $sql_check);
                
                if ($result_check && mysqli_num_rows($result_check) > 0) {
                    $item = mysqli_fetch_assoc($result_check);
                    $nueva_cantidad = $item['cantidad'] + $cantidad;
                    
                    if ($nueva_cantidad <= $row['stock']) {
                        $sql_update = "UPDATE order_items SET cantidad = $nueva_cantidad WHERE id = {$item['id']}";
                        if (mysqli_query($con, $sql_update)) {
                            $mensaje_carrito = "<div class='alert alert-success'>Cantidad actualizada en el carrito.</div>";
                        }
                    } else {
                        $mensaje_carrito = "<div class='alert alert-danger'>No puedes agregar más de lo disponible en stock.</div>";
                    }
                } else {
                    $precio_unitario = $row['precio'];
                    $sql_insert = "INSERT INTO order_items (order_id, product_id, cantidad, precio_unitario) 
                                   VALUES ($order_id, $product_id, $cantidad, '$precio_unitario')";
                    
                    if (mysqli_query($con, $sql_insert)) {
                        $mensaje_carrito = "<div class='alert alert-success'>Producto añadido al carrito.</div>";
                    } else {
                        $mensaje_carrito = "<div class='alert alert-danger'>Error al agregar al carrito: " . mysqli_error($con) . "</div>";
                    }
                }
            }
        }
    } else {
        $mensaje_carrito = "<div class='alert alert-danger'>Producto no disponible.</div>";
    }
}

$productos = obtenerProductosRebajas($con, $moneda_usuario);

// Obtener cantidad de items en carrito
$cantidad_carrito = 0;
if ($logged_in) {
    $sql_carrito_count = "SELECT SUM(oi.cantidad) as total_items
                          FROM orders o
                          JOIN order_items oi ON o.id = oi.order_id
                          WHERE o.user_id = '$user_id' AND o.estado = 'carrito'";
    $result_carrito_count = mysqli_query($con, $sql_carrito_count);
    if ($result_carrito_count && $row = mysqli_fetch_assoc($result_carrito_count)) {
        $cantidad_carrito = $row['total_items'] ?? 0;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Rebajas - ALAZÓN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .card {
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .badge-moneda {
            font-size: 0.8em;
            margin-left: 5px;
        }
        .badge-carrito {
            font-size: 0.75em;
            padding: 0.25em 0.5em;
        }
        .header-rebajas {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .badge-rebaja {
            background: #dc3545;
            color: white;
            font-size: 0.9rem;
            padding: 5px 10px;
        }
        .precio-original {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .ahorro {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- NAVBAR -->
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
                        <a class="nav-link dropdown-toggle active" id="navbarDropdown" href="#" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">Tienda</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="catalogo.php">TODOS LOS PRODUCTOS</a></li>
                            <li><a class="dropdown-item" href="mejor_valorados.php">MEJOR VALORADOS</a></li>
                            <li><a class="dropdown-item active" href="rebajas.php">REBAJAS</a></li>
                        </ul>
                    </li>
                </ul>
                <form class="d-flex">
                    <?php if ($logged_in): ?>
                        <span class="navbar-text me-3 d-none d-md-block">
                            <small>Moneda: <?php echo $moneda_simbolo . ' ' . $moneda_codigo; ?></small>
                        </span>
                        
                        <div class="dropdown">
                            <button class="btn btn-outline-dark dropdown-toggle me-2" type="button" data-bs-toggle="dropdown">
                                <i class="bi-person-fill me-1"></i>
                                Hola, <?php echo htmlspecialchars($usuario); ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="perfil.php">Mi Perfil</a></li>
                                <li><a class="dropdown-item" href="mis_pedidos.php">Mis pedidos</a></li>
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
                        <?php if ($cantidad_carrito > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger badge-carrito">
                                <?php echo $cantidad_carrito; ?>
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
    
    <section class="py-5">
        <div class="container px-4 px-lg-5 mt-5">
            <?php if (isset($mensaje_carrito)) echo $mensaje_carrito; ?>
            
            <div class="row mb-4">
                <div class="col-md-8">
                    <h2 class="mb-3">
                        <i class="bi-lightning-charge-fill me-2"></i>Ofertas Especiales
                        <?php if ($logged_in): ?>
                            <span class="badge bg-secondary badge-moneda"><?php echo $moneda_simbolo . ' ' . $moneda_codigo; ?></span>
                        <?php endif; ?>
                    </h2>
                    <p class="text-muted">No dejes pasar estas oportunidades únicas</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="btn-group">
                        <a href="catalogo.php" class="btn btn-outline-primary">
                            <i class="bi-grid me-1"></i>Ver Todos
                        </a>
                        <a href="mejor_valorados.php" class="btn btn-outline-warning ms-2">
                            <i class="bi-star me-1"></i>Mejor Valorados
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if (empty($productos)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">
                        <i class="bi-info-circle me-2"></i>
                        No hay productos en rebaja disponibles en este momento.
                        <a href="catalogo.php" class="alert-link">Ver todos los productos</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-3 row-cols-xl-4 justify-content-center">
                    <?php foreach ($productos as $producto): ?>
                        <div class="col mb-5">
                            <div class="card h-100 shadow-sm">
                                <!-- Badge de rebaja -->
                                <?php if ($producto['porcentaje_rebaja'] > 0): ?>
                                    <div class="position-absolute top-0 end-0 m-2">
                                        <span class="badge bg-danger">
                                            -<?php echo $producto['porcentaje_rebaja']; ?>%
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <img class="card-img-top" src="<?php echo htmlspecialchars($producto['imagen_url']); ?>" alt="<?php echo htmlspecialchars($producto['nombre']); ?>" style="height: 200px; object-fit: cover;" />
                                <div class="card-body p-4 text-center">
                                    <h5 class="fw-bolder"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                                    <?php if ($producto['categoria']): ?>
                                        <div class="small text-muted mb-2"><?php echo htmlspecialchars($producto['categoria']); ?></div>
                                    <?php endif; ?>
                                    
                                    <!-- Precios -->
                                    <div class="mb-2">
                                        <?php if ($producto['precio_original'] > $producto['precio']): ?>
                                            <div class="precio-original"><?php echo $producto['precio_original_formateado']; ?></div>
                                        <?php endif; ?>
                                        <div class="fw-bold fs-5 text-primary"><?php echo $producto['precio_formateado']; ?></div>
                                        <?php if ($producto['porcentaje_rebaja'] > 0): ?>
                                            <div class="ahorro small">
                                                Ahorras <?php echo $producto['porcentaje_rebaja']; ?>%
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($producto['stock'] <= 0): ?>
                                        <div class="text-danger small">Agotado</div>
                                    <?php elseif ($producto['stock'] < 10): ?>
                                        <div class="text-warning small">Solo <?php echo $producto['stock']; ?> disponibles</div>
                                    <?php else: ?>
                                        <div class="text-success small">Disponible</div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer p-4 pt-0 border-top-0 bg-transparent text-center">
                                    <?php if ($producto['stock'] > 0 && $logged_in): ?>
                                        <form method="POST" action="">
                                            <input type="hidden" name="product_id" value="<?php echo $producto['id']; ?>">
                                            <input type="hidden" name="cantidad" value="1">
                                            <button type="submit" name="add_to_cart" class="btn btn-danger w-100">
                                                <i class="bi-cart-plus me-1"></i>Añadir al carrito
                                            </button>
                                        </form>
                                    <?php elseif ($producto['stock'] > 0 && !$logged_in): ?>
                                        <a href="login.php" class="btn btn-danger w-100">
                                            <i class="bi-cart-plus me-1"></i>Inicia sesión para comprar
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-outline-secondary w-100" disabled>
                                            <i class="bi-cart-x me-1"></i>Agotado
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- INFO ADICIONAL -->
            <div class="row mt-5">
                <div class="col-12">
                    <div class="alert alert-light border">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5><i class="bi-clock-history me-2"></i>¡Oferta por tiempo limitado!</h5>
                                <p class="mb-0 text-muted">Estas rebajas son por tiempo limitado. No dejes pasar la oportunidad de adquirir productos de calidad a precios increíbles.</p>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="catalogo.php" class="btn btn-primary">
                                    <i class="bi-arrow-left me-1"></i>Volver al Catálogo
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <footer class="bg-dark py-3 mt-auto">
        <div class="container">
            <p class="m-0 text-center text-white small">ALAZÓN &copy; 2026 | Ofertas y Rebajas</p>
        </div>
    </footer>
</body>
</html>
