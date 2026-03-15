<?php
// index.php - ACTUALIZADO CON VALORACIONES REALES Y REBAJAS
require_once('session.php');
require_once('conexion.php');
require_once('config.php');

$db = new Database();
$con = $db->getCon();

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

// Función para formatear precios (si no está en config.php)
if (!function_exists('formatearPrecio')) {
    function formatearPrecio($precio, $moneda_codigo, $moneda_simbolo) {
        return $moneda_simbolo . ' ' . number_format($precio, 2, ',', '.');
    }
}

// Función para generar estrellas
function generarEstrellas($puntuacion) {
    $html = '<div class="valoracion-estrellas">';
    $puntuacion_redondeada = round($puntuacion * 2) / 2; // Redondear a 0.5
    
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($puntuacion_redondeada)) {
            $html .= '<i class="bi-star-fill text-warning"></i>';
        } elseif ($i == ceil($puntuacion_redondeada) && $puntuacion_redondeada - floor($puntuacion_redondeada) >= 0.5) {
            $html .= '<i class="bi-star-half text-warning"></i>';
        } else {
            $html .= '<i class="bi-star text-secondary"></i>';
        }
    }
    $html .= '</div>';
    return $html;
}

// Obtener productos destacados (los más vendidos o mejor valorados)
$sql_productos = "SELECT p.*, c.nombre as categoria_nombre,
                         COALESCE(AVG(v.puntuacion), 0) as valoracion_real,
                         COUNT(v.id) as num_valoraciones_real
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  LEFT JOIN valoraciones v ON p.id = v.product_id
                  WHERE p.estado = 'activo' 
                  GROUP BY p.id
                  ORDER BY 
                    CASE 
                        WHEN p.en_rebaja = 1 THEN 1  -- Primero los que están en rebaja
                        ELSE 2
                    END,
                    valoracion_real DESC,  -- Luego los mejor valorados
                    p.fecha_creacion DESC  -- Luego los más nuevos
                  LIMIT 12"; // Mostrar 12 productos

$result_productos = mysqli_query($con, $sql_productos);
$productos_destacados = [];

while ($row = mysqli_fetch_assoc($result_productos)) {
    // Calcular precio original si está en rebaja
    $precio_mostrar = $row['precio'];
    $precio_original = isset($row['precio_original']) && $row['precio_original'] > $row['precio'] 
        ? $row['precio_original'] 
        : $row['precio'];
    
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
    
    $porcentaje_rebaja = isset($row['porcentaje_rebaja']) ? $row['porcentaje_rebaja'] : 0;
    if ($porcentaje_rebaja == 0 && $precio_original > $precio_mostrar) {
        $porcentaje_rebaja = round((($precio_original - $precio_mostrar) / $precio_original) * 100);
    }
    
    // Construir URL de la imagen
    $imagen_url = 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg';
    if (!empty($row['imagen'])) {
        $ruta_imagen = 'uploads/productos/' . $row['imagen'];
        if (file_exists($ruta_imagen)) {
            $imagen_url = $ruta_imagen;
        }
    }
    
    $productos_destacados[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'precio' => $precio_mostrar,
        'precio_formateado' => $precio_formateado,
        'precio_original' => $precio_original,
        'precio_original_formateado' => $precio_original_formateado,
        'porcentaje_rebaja' => $porcentaje_rebaja,
        'stock' => $row['stock'],
        'imagen_url' => $imagen_url,
        'categoria' => $row['categoria_nombre'] ?? 'Sin categoría',
        'estado' => $row['estado'],
        'valoracion' => round($row['valoracion_real'], 1),
        'num_valoraciones' => $row['num_valoraciones_real'],
        'en_rebaja' => $row['en_rebaja'] ?? 0
    ];
}

// Obtener productos en rebaja destacados (para una sección especial)
$sql_rebajas = "SELECT p.*, c.nombre as categoria_nombre,
                       COALESCE(AVG(v.puntuacion), 0) as valoracion_real,
                       COUNT(v.id) as num_valoraciones_real
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.id 
                LEFT JOIN valoraciones v ON p.id = v.product_id
                WHERE p.estado = 'activo' AND p.en_rebaja = 1
                GROUP BY p.id
                ORDER BY p.porcentaje_rebaja DESC
                LIMIT 4";
$result_rebajas = mysqli_query($con, $sql_rebajas);
$productos_rebajas = [];

while ($row = mysqli_fetch_assoc($result_rebajas)) {
    $precio_mostrar = $row['precio'];
    $precio_original = isset($row['precio_original']) && $row['precio_original'] > $row['precio'] 
        ? $row['precio_original'] 
        : $row['precio'];
    
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
    
    $porcentaje_rebaja = isset($row['porcentaje_rebaja']) ? $row['porcentaje_rebaja'] : 0;
    if ($porcentaje_rebaja == 0 && $precio_original > $precio_mostrar) {
        $porcentaje_rebaja = round((($precio_original - $precio_mostrar) / $precio_original) * 100);
    }
    
    $imagen_url = 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg';
    if (!empty($row['imagen'])) {
        $ruta_imagen = 'uploads/productos/' . $row['imagen'];
        if (file_exists($ruta_imagen)) {
            $imagen_url = $ruta_imagen;
        }
    }
    
    $productos_rebajas[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'precio_formateado' => $precio_formateado,
        'precio_original_formateado' => $precio_original_formateado,
        'porcentaje_rebaja' => $porcentaje_rebaja,
        'stock' => $row['stock'],
        'imagen_url' => $imagen_url,
        'categoria' => $row['categoria_nombre'] ?? 'Sin categoría',
        'valoracion' => round($row['valoracion_real'], 1),
        'num_valoraciones' => $row['num_valoraciones_real']
    ];
}

// Obtener mejor valorados
$sql_valorados = "SELECT p.*, c.nombre as categoria_nombre,
                         COALESCE(AVG(v.puntuacion), 0) as valoracion_real,
                         COUNT(v.id) as num_valoraciones_real
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  LEFT JOIN valoraciones v ON p.id = v.product_id
                  WHERE p.estado = 'activo'
                  GROUP BY p.id
                  HAVING valoracion_real >= 4.0
                  ORDER BY valoracion_real DESC, num_valoraciones_real DESC
                  LIMIT 4";
$result_valorados = mysqli_query($con, $sql_valorados);
$productos_valorados = [];

while ($row = mysqli_fetch_assoc($result_valorados)) {
    $imagen_url = 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg';
    if (!empty($row['imagen'])) {
        $ruta_imagen = 'uploads/productos/' . $row['imagen'];
        if (file_exists($ruta_imagen)) {
            $imagen_url = $ruta_imagen;
        }
    }
    
    $productos_valorados[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'precio_formateado' => formatearPrecio($row['precio'], $moneda_usuario['codigo'], $moneda_usuario['simbolo']),
        'stock' => $row['stock'],
        'imagen_url' => $imagen_url,
        'categoria' => $row['categoria_nombre'] ?? 'Sin categoría',
        'valoracion' => round($row['valoracion_real'], 1),
        'num_valoraciones' => $row['num_valoraciones_real']
    ];
}

$hay_productos = !empty($productos_destacados);
$hay_rebajas = !empty($productos_rebajas);
$hay_valorados = !empty($productos_valorados);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Inicio - ALAZÓN</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        .badge-carrito {
            font-size: 0.75em;
            padding: 0.25em 0.5em;
        }
        .product-card {
            transition: transform 0.3s, box-shadow 0.3s;
            border: 1px solid #dee2e6;
            height: 100%;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .badge-admin {
            background-color: #dc3545;
            color: white;
            font-size: 0.7em;
            margin-left: 5px;
        }
        .product-image {
            height: 200px;
            object-fit: cover;
        }
        .stock-badge {
            font-size: 0.8rem;
        }
        .valoracion-estrellas {
            color: #ffc107;
            font-size: 0.9rem;
        }
        .precio-original {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.9rem;
        }
        .badge-rebaja {
            background: #dc3545;
            color: white;
            font-size: 0.9rem;
            padding: 5px 10px;
        }
        .seccion-titulo {
            position: relative;
            margin-bottom: 30px;
            padding-bottom: 15px;
        }
        .seccion-titulo:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 3px;
            background: linear-gradient(90deg, #0d6efd, #20c997);
        }
        .ver-mas-link {
            color: #0d6efd;
            text-decoration: none;
            font-weight: 500;
        }
        .ver-mas-link:hover {
            text-decoration: underline;
        }
        .video-header {
            position: relative;
            background-color: #000;
            height: 500px;
            overflow: hidden;
            color: white;
        }
        .video-header video {
            position: absolute;
            top: 50%;
            left: 50%;
            min-width: 100%;
            min-height: 100%;
            width: auto;
            height: auto;
            transform: translateX(-50%) translateY(-50%);
            z-index: 1;
            opacity: 0.6;
            object-fit: cover;
        }
        .video-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2;
        }
        .video-content {
            position: relative;
            z-index: 3;
            height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .video-content h1 {
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        .video-content p {
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
            font-size: 1.5rem;
            opacity: 0.95;
        }
        @media (max-width: 768px) {
            .video-content h1 {
                font-size: 2.5rem;
            }
            .video-content p {
                font-size: 1.2rem;
            }
            .video-header {
                height: 400px;
            }
            .video-content {
                height: 400px;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation-->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="index.php">ALAZÓN</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="SNosotros.php">Sobre Nosotros</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">Tienda</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="catalogo.php?filtro=todos">TODOS LOS PRODUCTOS</a></li>
                            <li><hr class="dropdown-divider" /></li>
                            <li><a class="dropdown-item" href="catalogo.php?filtro=valorados">MEJOR VALORADOS</a></li>
                            <li><a class="dropdown-item" href="catalogo.php?filtro=rebajas">REBAJAS</a></li>
                        </ul>
                    </li>
                </ul>
                <form class="d-flex">
                    <?php if ($logged_in): ?>
                        <span class="navbar-text me-3 d-none d-md-block">
                            <small>Moneda: <?php echo $moneda_simbolo . ' ' . $moneda_codigo; ?></small>
                        </span>
                        
                        <div class="dropdown me-2">
                            <button class="btn btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi-person-fill me-1"></i>
                                Hola, <?php echo htmlspecialchars($usuario); ?>
                                <?php if ($es_admin): ?>
                                    <span class="badge badge-admin">Admin</span>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="perfil.php">Mi Perfil</a></li>
                                <li><a class="dropdown-item" href="mis_pedidos.php">Mis Pedidos</a></li>
                                <?php if ($es_admin): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="admin_panel.php">
                                        <i class="bi-shield-lock me-2"></i>Panel de Administración
                                    </a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="logout.php">
                                    <i class="bi-box-arrow-right me-2"></i>Cerrar Sesión
                                </a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-dark me-2">
                            <i class="bi-person-fill me-1"></i>
                            Iniciar Sesión
                        </a>
                        <a href="registro.php" class="btn btn-dark me-2">
                            <i class="bi-person-plus me-1"></i>
                            Registrarse
                        </a>
                    <?php endif; ?>
                    
                    <a href="carrito.php" class="btn btn-outline-dark position-relative">
                        <i class="bi-cart-fill me-1"></i>
                        Carrito
                        <?php if ($logged_in && $cantidad_carrito > 0): ?>
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
    
    <!-- Header con video de fondo-->
    <header class="video-header">
        <video autoplay muted loop playsinline>
            <source src="uploads/videos/video_Index.mp4" type="video/mp4">
        </video>
        <div class="video-overlay"></div>
        <div class="container px-4 px-lg-5 video-content">
            <div>
                <h1 class="display-4 fw-bolder">Bienvenido a ALAZÓN</h1>
                <p class="lead fw-normal mb-4">Tu tienda online de confianza</p>
                <div class="mt-4">
                    <a href="catalogo.php?filtro=todos" class="btn btn-light btn-lg me-2">
                        <i class="bi-shop me-1"></i>Ver Catálogo
                    </a>
                    <?php if (!$logged_in): ?>
                        <a href="registro.php" class="btn btn-outline-light btn-lg">
                            <i class="bi-person-plus me-1"></i>Regístrate Gratis
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Sección principal: Productos Destacados -->
    <section class="py-5 <?php echo ($hay_rebajas || $hay_valorados) ? 'bg-light' : ''; ?>">
        <div class="container px-4 px-lg-5 mt-5">
            <h2 class="text-center seccion-titulo mb-5">Productos Destacados</h2>
            
            <?php if (!$hay_productos): ?>
                <div class="alert alert-info text-center">
                    <i class="bi-info-circle me-2"></i>
                    No hay productos disponibles en este momento. 
                    <?php if ($es_admin): ?>
                        <a href="admin_productos.php" class="alert-link">Añade productos desde el panel de administración</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-3 row-cols-xl-4 justify-content-center">
                    
                    <?php foreach ($productos_destacados as $producto): ?>
                        <div class="col mb-5">
                            <div class="card h-100 product-card">
                                <!-- Badges -->
                                <div class="position-absolute top-0 start-0 m-2">
                                    <?php if ($producto['valoracion'] >= 4.0): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi-star-fill me-1"></i><?php echo $producto['valoracion']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($producto['porcentaje_rebaja'] > 0): ?>
                                    <div class="position-absolute top-0 end-0 m-2">
                                        <span class="badge bg-danger">
                                            -<?php echo $producto['porcentaje_rebaja']; ?>%
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($producto['stock'] <= 0): ?>
                                    <div class="position-absolute bottom-0 end-0 m-2">
                                        <span class="badge bg-danger">Agotado</span>
                                    </div>
                                <?php elseif ($producto['stock'] < 10): ?>
                                    <div class="position-absolute bottom-0 end-0 m-2">
                                        <span class="badge bg-warning text-dark">Últimas <?php echo $producto['stock']; ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <img class="card-img-top product-image" 
                                     src="<?php echo htmlspecialchars($producto['imagen_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($producto['nombre']); ?>" />
                                
                                <div class="card-body p-4 text-center">
                                    <h5 class="fw-bolder"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                                    <?php if (!empty($producto['categoria'])): ?>
                                        <div class="small text-muted mb-2"><?php echo htmlspecialchars($producto['categoria']); ?></div>
                                    <?php endif; ?>
                                    
                                    <!-- Valoración -->
                                    <?php if ($producto['valoracion'] > 0): ?>
                                        <div class="valoracion-estrellas mb-2">
                                            <?php echo generarEstrellas($producto['valoracion']); ?>
                                            <small class="text-muted ms-1">(<?php echo $producto['num_valoraciones']; ?>)</small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Precios -->
                                    <div class="mb-2">
                                        <?php if ($producto['precio_original'] > $producto['precio']): ?>
                                            <div class="precio-original"><?php echo $producto['precio_original_formateado']; ?></div>
                                        <?php endif; ?>
                                        <div class="fw-bold fs-5 text-primary"><?php echo $producto['precio_formateado']; ?></div>
                                    </div>
                                    
                                    <!-- Stock -->
                                    <?php if ($producto['stock'] > 0): ?>
                                        <div class="text-success small stock-badge">
                                            <i class="bi-check-circle-fill me-1"></i>Disponible
                                        </div>
                                    <?php else: ?>
                                        <div class="text-danger small stock-badge">
                                            <i class="bi-x-circle-fill me-1"></i>Agotado
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer p-4 pt-0 border-top-0 bg-transparent text-center">
                                    <?php if ($producto['stock'] > 0 && $logged_in): ?>
                                        <form method="POST" action="catalogo.php" class="d-inline">
                                            <input type="hidden" name="product_id" value="<?php echo $producto['id']; ?>">
                                            <input type="hidden" name="cantidad" value="1">
                                            <button type="submit" name="add_to_cart" class="btn btn-outline-dark w-100">
                                                <i class="bi-cart-plus me-1"></i>Añadir al carrito
                                            </button>
                                        </form>
                                    <?php elseif ($producto['stock'] > 0 && !$logged_in): ?>
                                        <a href="login.php" class="btn btn-outline-dark w-100">
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
            
            <div class="text-center mt-5">
                <a href="catalogo.php?filtro=todos" class="btn btn-dark btn-lg">
                    <i class="bi-arrow-right me-2"></i>Ver Todos los Productos
                </a>
            </div>
        </div>
    </section>
    
    <!-- Footer-->
    <footer class="py-4 bg-black">
        <div class="container">
            <p class="m-0 text-center text-white">ALAZÓN &copy; <?php echo date('Y'); ?> - Todos los derechos reservados</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>