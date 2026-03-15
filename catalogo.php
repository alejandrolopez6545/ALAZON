<?php
require_once('session.php');
require_once('conexion.php');
require_once('config.php');

$db = new Database();
$con = $db->getCon();

// Obtener filtros de URL
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';
$categoria_id = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Títulos según filtro
$titulos = [
    'todos' => 'Todos los Productos',
    'valorados' => 'Mejor Valorados',
    'rebajas' => 'Productos en Rebaja'
];

$titulo_pagina = $titulos[$filtro] ?? 'Catálogo';

// Función para obtener productos con filtros (ACTUALIZADA)
function obtenerProductosFiltrados($conexion, $moneda_usuario, $filtro = 'todos', $categoria_id = 0, $busqueda = '') {
    $sql = "SELECT p.*, c.nombre as categoria_nombre,
                   COALESCE(AVG(v.puntuacion), 0) as valoracion_real,
                   COUNT(v.id) as num_valoraciones_real
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN valoraciones v ON p.id = v.product_id
            WHERE p.estado = 'activo'";
    
    // Filtrar por búsqueda
    if (!empty($busqueda)) {
        $busqueda_escaped = mysqli_real_escape_string($conexion, $busqueda);
        $sql .= " AND (p.nombre LIKE '%$busqueda_escaped%' 
                  OR p.descripcion LIKE '%$busqueda_escaped%'
                  OR c.nombre LIKE '%$busqueda_escaped%')";
    }
    
    // Filtrar por categoría si se especifica
    if ($categoria_id > 0) {
        $sql .= " AND p.category_id = $categoria_id";
    }
    
    $sql .= " GROUP BY p.id";
    
    // Aplicar filtros según parámetro
    switch($filtro) {
        case 'valorados':
            $sql .= " HAVING valoracion_real >= 4.0 
                     ORDER BY valoracion_real DESC, num_valoraciones_real DESC";
            break;
            
        case 'rebajas':
            $sql .= " HAVING p.en_rebaja = 1 
                     ORDER BY p.porcentaje_rebaja DESC";
            break;
            
        case 'todos':
        default:
            $sql .= " ORDER BY p.fecha_creacion DESC";
            break;
    }
    
    $result = mysqli_query($conexion, $sql);
    $productos = [];
    
    while ($row = mysqli_fetch_assoc($result)) {
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
            'imagen_url' => $imagen_url,
            'categoria' => $row['categoria_nombre'],
            'estado' => $row['estado'],
            'valoracion' => round($row['valoracion_real'], 1), // Usar la valoración real
            'num_valoraciones' => $row['num_valoraciones_real'] // Usar el número real
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

// Obtener todas las categorías para el desplegable
$sql_categorias = "SELECT id, nombre FROM categories WHERE activo = 1 ORDER BY nombre";
$result_categorias = mysqli_query($con, $sql_categorias);
$categorias = [];
while ($row = mysqli_fetch_assoc($result_categorias)) {
    $categorias[] = $row;
}

$productos = obtenerProductosFiltrados($con, $moneda_usuario, $filtro, $categoria_id, $busqueda);

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

// Determinar nombre de categoría activa
$categoria_nombre_activa = 'Todas las categorías';
if ($categoria_id > 0) {
    foreach ($categorias as $cat) {
        if ($cat['id'] == $categoria_id) {
            $categoria_nombre_activa = $cat['nombre'];
            break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title><?php echo $titulo_pagina; ?> - ALAZÓN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .card {
            transition: transform 0.3s;
            border: 1px solid #dee2e6;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .badge-moneda {
            font-size: 0.8em;
            margin-left: 5px;
        }
        .badge-carrito {
            font-size: 0.75em;
            padding: 0.25em 0.5em;
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
        }
        .badge-valoracion {
            background: #ffc107;
            color: #000;
        }
        /* Estilos para los filtros */
        .filtros-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .categoria-dropdown .dropdown-toggle {
            background: white;
            border: 1px solid #ced4da;
            min-width: 200px;
            text-align: left;
        }
        .categoria-dropdown .dropdown-toggle::after {
            float: right;
            margin-top: 8px;
        }
        .categoria-dropdown .dropdown-menu {
            max-height: 300px;
            overflow-y: auto;
        }
        .categoria-dropdown .dropdown-item.active {
            background-color: #0d6efd;
            color: white;
        }
        .categoria-dropdown .dropdown-item i {
            margin-right: 8px;
        }
        .filtros-rapidos {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filtro-btn {
            border-radius: 20px;
            padding: 5px 15px;
            transition: all 0.3s;
        }
        .filtro-btn.active {
            background: #0d6efd;
            color: white;
            border-color: #0d6efd;
        }
        .filtro-btn i {
            margin-right: 5px;
        }
        .search-box {
            position: relative;
        }
        .search-box i {
            position: absolute;
            left: 15px;
            top: 12px;
            color: #6c757d;
        }
        .search-box input {
            padding-left: 40px;
            border-radius: 30px;
            border: 1px solid #ced4da;
        }
        .resultados-info {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
        }
        .limpiar-filtros {
            color: #dc3545;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .limpiar-filtros:hover {
            text-decoration: underline;
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
                            <li><a class="dropdown-item" href="catalogo.php?filtro=todos">TODOS LOS PRODUCTOS</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="catalogo.php?filtro=valorados">MEJOR VALORADOS</a></li>
                            <li><a class="dropdown-item" href="catalogo.php?filtro=rebajas">REBAJAS</a></li>
                        </ul>
                    </li>
                </ul>
                
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
                                <li><a class="dropdown-item" href="mis_pedidos.php">Mis Pedidos</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <?php if ($es_admin): ?>
                                    <li><a class="dropdown-item" href="admin_panel.php">
                                        <i class="bi-shield-lock me-2"></i>Panel de Administración
                                    </a></li>
                                <?php endif; ?>
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
                </div>
            </div>
        </div>
    </nav>
    
    <section class="py-5">
        <div class="container px-4 px-lg-5 mt-5">
            <?php if (isset($mensaje_carrito)) echo $mensaje_carrito; ?>
            
            <!-- TÍTULO Y FILTROS -->
            <div class="filtros-container">
                <div class="row align-items-center">
                    <div class="col-md-4">
                        <h2 class="mb-3 mb-md-0"><?php echo $titulo_pagina; ?></h2>
                    </div>
                    <div class="col-md-8">
                        <div class="row g-2">
                            <!-- BUSCADOR -->
                            <div class="col-md-5">
                                <form method="GET" class="search-box">
                                    <?php if ($categoria_id > 0): ?>
                                        <input type="hidden" name="categoria" value="<?php echo $categoria_id; ?>">
                                    <?php endif; ?>
                                    <?php if ($filtro != 'todos'): ?>
                                        <input type="hidden" name="filtro" value="<?php echo $filtro; ?>">
                                    <?php endif; ?>
                                    <i class="bi-search"></i>
                                    <input type="text" 
                                           name="buscar" 
                                           class="form-control" 
                                           placeholder="Buscar productos..." 
                                           value="<?php echo htmlspecialchars($busqueda); ?>">
                                </form>
                            </div>
                            
                            <!-- DESPLEGABLE DE CATEGORÍAS -->
                            <div class="col-md-4">
                                <div class="dropdown categoria-dropdown">
                                    <button class="btn btn-light dropdown-toggle w-100" type="button" data-bs-toggle="dropdown">
                                        <i class="bi-tag me-2"></i><?php echo $categoria_nombre_activa; ?>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item <?php echo $categoria_id == 0 ? 'active' : ''; ?>" 
                                               href="?<?php 
                                                   $params = $_GET;
                                                   $params['categoria'] = 0;
                                                   echo http_build_query($params);
                                               ?>">
                                                <i class="bi-grid-3x3-gap"></i> Todas las categorías
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <li>
                                                <a class="dropdown-item <?php echo $categoria_id == $categoria['id'] ? 'active' : ''; ?>" 
                                                   href="?<?php 
                                                       $params = $_GET;
                                                       $params['categoria'] = $categoria['id'];
                                                       echo http_build_query($params);
                                                   ?>">
                                                    <i class="bi-tag"></i> <?php echo htmlspecialchars($categoria['nombre']); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- FILTROS RÁPIDOS -->
                            <div class="col-md-3">
                                <div class="filtros-rapidos">
                                    <a href="?filtro=valorados<?php echo $categoria_id > 0 ? '&categoria='.$categoria_id : ''; ?><?php echo !empty($busqueda) ? '&buscar='.urlencode($busqueda) : ''; ?>" 
                                       class="btn btn-outline-warning filtro-btn <?php echo $filtro == 'valorados' ? 'active' : ''; ?>">
                                        <i class="bi-star-fill"></i> Mejor valorados
                                    </a>
                                    <a href="?filtro=rebajas<?php echo $categoria_id > 0 ? '&categoria='.$categoria_id : ''; ?><?php echo !empty($busqueda) ? '&buscar='.urlencode($busqueda) : ''; ?>" 
                                       class="btn btn-outline-danger filtro-btn <?php echo $filtro == 'rebajas' ? 'active' : ''; ?>">
                                        <i class="bi-tag-fill"></i> Rebajas
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- INFORMACIÓN DE FILTROS ACTIVOS -->
                <?php if ($filtro != 'todos' || $categoria_id > 0 || !empty($busqueda)): ?>
                    <div class="resultados-info mt-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi-info-circle me-2"></i>
                                <strong><?php echo count($productos); ?> productos encontrados</strong>
                                <?php if (!empty($busqueda)): ?>
                                    <span class="badge bg-secondary ms-2">Búsqueda: "<?php echo htmlspecialchars($busqueda); ?>"</span>
                                <?php endif; ?>
                                <?php if ($categoria_id > 0): ?>
                                    <span class="badge bg-info ms-2">Categoría: <?php echo $categoria_nombre_activa; ?></span>
                                <?php endif; ?>
                                <?php if ($filtro == 'valorados'): ?>
                                    <span class="badge bg-warning text-dark ms-2">Mejor valorados</span>
                                <?php elseif ($filtro == 'rebajas'): ?>
                                    <span class="badge bg-danger ms-2">En rebaja</span>
                                <?php endif; ?>
                            </div>
                            <a href="catalogo.php" class="limpiar-filtros">
                                <i class="bi-x-circle me-1"></i>Limpiar filtros
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Productos -->
            <div class="row gx-4 gx-lg-5 row-cols-2 row-cols-md-3 row-cols-xl-4 justify-content-center">
                <?php if (empty($productos)): ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center py-5">
                            <h4>No hay productos disponibles</h4>
                            <p class="mb-3">Prueba con otros filtros o palabras clave</p>
                            <a href="catalogo.php" class="btn btn-primary">
                                <i class="bi-grid-3x3-gap me-2"></i>Ver todos los productos
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($productos as $producto): ?>
                        <div class="col mb-5">
                            <div class="card h-100">
                                <!-- Badges -->
                                <div class="position-absolute top-0 start-0 m-2">
                                    <?php if ($producto['valoracion'] >= 4.0): ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi-star-fill me-1"></i><?php echo number_format($producto['valoracion'], 1); ?>
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
                                
                                <img class="card-img-top" src="<?php echo htmlspecialchars($producto['imagen_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                                     style="height: 200px; object-fit: cover;" />
                                
                                <div class="card-body p-4 text-center">
                                    <h5 class="fw-bolder"><?php echo htmlspecialchars($producto['nombre']); ?></h5>
                                    <?php if ($producto['categoria']): ?>
                                        <div class="small text-muted mb-2"><?php echo htmlspecialchars($producto['categoria']); ?></div>
                                    <?php endif; ?>
                                    
                                    <!-- Valoración -->
                                    <?php if ($producto['valoracion'] > 0): ?>
                                        <div class="valoracion-estrellas mb-2">
                                            <?php 
                                            $estrellas_llenas = floor($producto['valoracion']);
                                            $media_estrella = $producto['valoracion'] - $estrellas_llenas >= 0.5;
                                            
                                            for ($i = 1; $i <= 5; $i++):
                                                if ($i <= $estrellas_llenas):
                                                    echo '<i class="bi-star-fill"></i>';
                                                elseif ($i == $estrellas_llenas + 1 && $media_estrella):
                                                    echo '<i class="bi-star-half"></i>';
                                                else:
                                                    echo '<i class="bi-star"></i>';
                                                endif;
                                            endfor;
                                            ?>
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
                <?php endif; ?>
            </div>
            
            <!-- Badge de moneda -->
            <?php if ($logged_in): ?>
                <div class="text-center mt-4">
                    <span class="badge bg-secondary">Mostrando precios en: <?php echo $moneda_simbolo . ' ' . $moneda_codigo; ?></span>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <footer class="bg-dark py-3 mt-auto">
        <div class="container">
            <p class="m-0 text-center text-white small">ALAZÓN &copy; 2026</p>
        </div>
    </footer>
</body>
</html>