<?php
// admin_rebajas.php - Gestión de productos en rebaja
require_once('session.php');
require_once('conexion.php');
require_once('config.php');

$db = new Database();
$con = $db->getCon();

// Verificar si es admin
if (!$es_admin) {
    header('Location: index.php');
    exit();
}

// Procesar actualización de rebaja
if (isset($_POST['actualizar_rebaja'])) {
    $id = intval($_POST['id']);
    $precio_original = floatval($_POST['precio_original']);
    $porcentaje_rebaja = intval($_POST['porcentaje_rebaja']);
    
    // Calcular el nuevo precio rebajado
    $precio_rebajado = $precio_original - ($precio_original * $porcentaje_rebaja / 100);
    $precio_rebajado = round($precio_rebajado, 2);
    
    // Actualizar el producto con los datos de rebaja
    $sql = "UPDATE products SET 
            precio = $precio_rebajado,
            en_rebaja = 1,
            precio_original = $precio_original,
            porcentaje_rebaja = $porcentaje_rebaja
            WHERE id = $id";
    
    if (mysqli_query($con, $sql)) {
        $mensaje = 'Rebaja aplicada correctamente. Nuevo precio: €' . number_format($precio_rebajado, 2, ',', '.');
        $tipo_mensaje = 'success';
    } else {
        $mensaje = 'Error al aplicar rebaja: ' . mysqli_error($con);
        $tipo_mensaje = 'danger';
    }
}

// Procesar edición de rebaja existente
if (isset($_POST['editar_rebaja'])) {
    $id = intval($_POST['id']);
    $precio_original = floatval($_POST['precio_original']);
    $porcentaje_rebaja = intval($_POST['porcentaje_rebaja']);
    
    // Calcular el nuevo precio rebajado
    $precio_rebajado = $precio_original - ($precio_original * $porcentaje_rebaja / 100);
    $precio_rebajado = round($precio_rebajado, 2);
    
    $sql = "UPDATE products SET 
            precio = $precio_rebajado,
            precio_original = $precio_original,
            porcentaje_rebaja = $porcentaje_rebaja
            WHERE id = $id AND en_rebaja = 1";
    
    if (mysqli_query($con, $sql)) {
        $mensaje = 'Rebaja actualizada correctamente';
        $tipo_mensaje = 'success';
    } else {
        $mensaje = 'Error al actualizar rebaja: ' . mysqli_error($con);
        $tipo_mensaje = 'danger';
    }
}

// Quitar rebaja
if (isset($_GET['quitar_rebaja'])) {
    $id = intval($_GET['quitar_rebaja']);
    
    // Recuperar el precio original antes de quitar la rebaja
    $sql_producto = "SELECT precio_original FROM products WHERE id = $id AND en_rebaja = 1";
    $result_producto = mysqli_query($con, $sql_producto);
    $producto = mysqli_fetch_assoc($result_producto);
    
    if ($producto && $producto['precio_original'] > 0) {
        $precio_original = $producto['precio_original'];
        
        $sql = "UPDATE products SET 
                precio = $precio_original,
                en_rebaja = 0,
                precio_original = NULL,
                porcentaje_rebaja = NULL
                WHERE id = $id";
        
        if (mysqli_query($con, $sql)) {
            header("Location: admin_rebajas.php?mensaje=" . urlencode("Rebaja eliminada correctamente"));
            exit();
        }
    }
}

// Obtener productos en rebaja
$sql_rebajas = "SELECT * FROM products 
                WHERE en_rebaja = 1 
                ORDER BY porcentaje_rebaja DESC, id DESC";
$result_rebajas = mysqli_query($con, $sql_rebajas);

// Obtener productos que NO están en rebaja (para poder añadirlos)
$sql_disponibles = "SELECT * FROM products 
                    WHERE (en_rebaja = 0 OR en_rebaja IS NULL)
                    AND estado = 'activo'
                    ORDER BY nombre ASC";
$result_disponibles = mysqli_query($con, $sql_disponibles);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rebajas - Admin ALAZÓN</title>
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
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .product-image-placeholder {
            width: 60px;
            height: 60px;
            background: #e9ecef;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            font-size: 1.5rem;
        }
        .badge-rebaja {
            background: #dc3545;
            color: white;
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .precio-original {
            text-decoration: line-through;
            color: #6c757d;
            font-size: 0.9rem;
            margin-right: 5px;
        }
        .precio-rebajado {
            color: #dc3545;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .card-rebaja {
            border-left: 4px solid #dc3545;
            transition: all 0.3s;
        }
        .card-rebaja:hover {
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2);
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
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
                <a href="admin_panel.php"><i class="bi-speedometer2"></i> Panel</a>
                <a href="admin_usuarios.php"><i class="bi-people"></i> Usuarios</a>
                <a href="admin_productos.php"><i class="bi-box"></i> Productos</a>
                <a href="admin_pedidos.php"><i class="bi-receipt"></i> Pedidos</a>
                <a href="admin_rebajas.php"class="active"><i class="bi-tag"></i> Rebajas</a>
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
        <!-- Mensajes -->
        <?php if (isset($_GET['mensaje'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi-check-circle me-2"></i><?php echo urldecode($_GET['mensaje']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($mensaje)): ?>
        <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
            <i class="bi <?php echo $tipo_mensaje == 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'; ?> me-2"></i>
            <?php echo $mensaje; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Título -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0"><i class="bi-tag me-2"></i>Gestión de Rebajas</h1>
                <p class="text-muted mb-0">Administra los productos en oferta y descuentos especiales</p>
            </div>
        </div>
        <!-- Productos en rebaja actuales -->
        <div class="card mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi-tag-fill text-danger me-2"></i>
                    Productos en Rebaja
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Producto</th>
                                <th>Precio Actual</th>
                                <th>Precio Original</th>
                                <th>Descuento</th>
                                <th>Stock</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result_rebajas) == 0): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="bi-inbox fs-1 d-block text-muted mb-2"></i>
                                    <p class="text-muted mb-0">No hay productos en rebaja</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                            
                            <?php while ($producto = mysqli_fetch_assoc($result_rebajas)): 
                                $ahorro = $producto['precio_original'] - $producto['precio'];
                                
                                // LÓGICA DE IMAGEN
                                $imagen_url = 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg';
                                if (!empty($producto['imagen'])) {
                                    $ruta_imagen = 'uploads/productos/' . $producto['imagen'];
                                    if (file_exists($ruta_imagen)) {
                                        $imagen_url = $ruta_imagen;
                                    }
                                }
                            ?>
                            <tr class="card-rebaja">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo $imagen_url; ?>" 
                                             alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                             class="product-image me-3">
                                        <div>
                                            <strong><?php echo htmlspecialchars($producto['nombre']); ?></strong>
                                            <br>
                                            <small class="text-muted">ID: #<?php echo $producto['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="precio-rebajado">€<?php echo number_format($producto['precio'], 2, ',', '.'); ?></span>
                                </td>
                                <td>
                                    <span class="precio-original">€<?php echo number_format($producto['precio_original'], 2, ',', '.'); ?></span>
                                </td>
                                <td>
                                    <span class="badge-rebaja">
                                        <i class="bi-tag"></i> -<?php echo $producto['porcentaje_rebaja']; ?>%
                                    </span>
                                    <br>
                                    <small class="text-success">Ahorras €<?php echo number_format($ahorro, 2, ',', '.'); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $producto['stock'] > 0 ? 'success' : 'danger'; ?>">
                                        <?php echo $producto['stock']; ?> unidades
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-warning" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editarRebajaModal<?php echo $producto['id']; ?>"
                                            title="Editar rebaja">
                                        <i class="bi-pencil"></i>
                                    </button>
                                    <a href="?quitar_rebaja=<?php echo $producto['id']; ?>" 
                                       class="btn btn-sm btn-danger"
                                       onclick="return confirm('¿Quitar este producto de rebajas?')"
                                       title="Quitar rebaja">
                                        <i class="bi-x-circle"></i>
                                    </a>
                                </td>
                            </tr>
                            
                            <!-- Modal para editar rebaja -->
                            <div class="modal fade" id="editarRebajaModal<?php echo $producto['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <i class="bi-pencil me-2"></i>Editar Rebaja
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Producto</label>
                                                    <input type="text" class="form-control" 
                                                           value="<?php echo htmlspecialchars($producto['nombre']); ?>" readonly>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Precio original (antes de rebaja)</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">€</span>
                                                        <input type="number" step="0.01" class="form-control" 
                                                               name="precio_original" 
                                                               value="<?php echo $producto['precio_original']; ?>" 
                                                               min="0.01" required>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label class="form-label">Porcentaje de descuento</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" 
                                                               name="porcentaje_rebaja" 
                                                               value="<?php echo $producto['porcentaje_rebaja']; ?>" 
                                                               min="1" max="99" required 
                                                               id="porcentaje_edit_<?php echo $producto['id']; ?>"
                                                               data-precio-original="<?php echo $producto['precio_original']; ?>">
                                                        <span class="input-group-text">%</span>
                                                    </div>
                                                </div>
                                                
                                                <div class="alert alert-info" id="preview_<?php echo $producto['id']; ?>">
                                                    <i class="bi-info-circle me-2"></i>
                                                    El nuevo precio será: 
                                                    <strong>€<?php 
                                                        $nuevo_precio = $producto['precio_original'] - ($producto['precio_original'] * $producto['porcentaje_rebaja'] / 100);
                                                        echo number_format($nuevo_precio, 2, ',', '.');
                                                    ?></strong>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" name="editar_rebaja" class="btn btn-primary">
                                                    Guardar cambios
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <script>
                            // Preview en tiempo real para el modal de edición - CORREGIDO
                            document.getElementById('porcentaje_edit_<?php echo $producto['id']; ?>').addEventListener('input', function(e) {
                                // Usar el precio original específico de este producto
                                const precioOriginal = <?php echo $producto['precio_original']; ?>;
                                const porcentaje = this.value;
                                const preview = document.querySelector('#preview_<?php echo $producto['id']; ?> strong');
                                
                                if (porcentaje && porcentaje > 0) {
                                    const nuevoPrecio = precioOriginal - (precioOriginal * porcentaje / 100);
                                    preview.textContent = '€' + nuevoPrecio.toFixed(2).replace('.', ',');
                                } else {
                                    preview.textContent = '€' + precioOriginal.toFixed(2).replace('.', ',');
                                }
                            });
                            </script>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Añadir nuevos productos a rebajas -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="bi-plus-circle text-success me-2"></i>
                    Añadir Productos a Rebajas
                </h5>
            </div>
            <div class="card-body">
                <?php if (mysqli_num_rows($result_disponibles) == 0): ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi-info-circle me-2"></i>
                        No hay productos disponibles para añadir a rebajas.
                    </div>
                <?php else: ?>
                <div class="row">
                    <?php while ($producto = mysqli_fetch_assoc($result_disponibles)): 
                        
                        // LÓGICA DE IMAGEN
                        $imagen_url = 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg';
                        if (!empty($producto['imagen'])) {
                            $ruta_imagen = 'uploads/productos/' . $producto['imagen'];
                            if (file_exists($ruta_imagen)) {
                                $imagen_url = $ruta_imagen;
                            }
                        }
                    ?>
                    <div class="col-md-6 col-lg-4 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <img src="<?php echo $imagen_url; ?>" 
                                         alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; margin-right: 15px;">
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($producto['nombre']); ?></h6>
                                        <small class="text-muted">Stock: <?php echo $producto['stock']; ?></small>
                                    </div>
                                </div>
                                
                                <form method="POST">
                                    <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                                    
                                    <div class="mb-2">
                                        <label class="form-label small">Precio actual: <strong>€<?php echo number_format($producto['precio'], 2, ',', '.'); ?></strong></label>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <label class="form-label small">Precio original (antes de rebaja)</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">€</span>
                                            <input type="number" step="0.01" class="form-control precio-original-input" 
                                                   name="precio_original" 
                                                   value="<?php echo $producto['precio']; ?>" 
                                                   min="0.01" required
                                                   id="precio_original_<?php echo $producto['id']; ?>"
                                                   data-producto-id="<?php echo $producto['id']; ?>">
                                        </div>
                                        <small class="text-muted">Precio antes de la rebaja</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label small">Descuento a aplicar</label>
                                        <div class="input-group input-group-sm">
                                            <input type="number" class="form-control porcentaje-input" 
                                                   name="porcentaje_rebaja" 
                                                   placeholder="%" 
                                                   min="1" max="99" required
                                                   id="porcentaje_<?php echo $producto['id']; ?>"
                                                   data-producto-id="<?php echo $producto['id']; ?>">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <small class="text-muted" id="preview_<?php echo $producto['id']; ?>">
                                            Precio final: €<?php echo number_format($producto['precio'], 2, ',', '.'); ?>
                                        </small>
                                    </div>
                                    
                                    <button type="submit" name="actualizar_rebaja" class="btn btn-sm btn-success w-100">
                                        <i class="bi-tag me-1"></i>Añadir a rebajas
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <script>
                    // Preview para productos disponibles - CORREGIDO (cada producto usa su propio precio)
                    document.getElementById('porcentaje_<?php echo $producto['id']; ?>').addEventListener('input', function(e) {
                        const productoId = this.getAttribute('data-producto-id');
                        const precioOriginal = parseFloat(document.getElementById('precio_original_' + productoId).value);
                        const porcentaje = this.value;
                        const preview = document.getElementById('preview_' + productoId);
                        
                        if (!isNaN(precioOriginal) && porcentaje && porcentaje > 0) {
                            const nuevoPrecio = precioOriginal - (precioOriginal * porcentaje / 100);
                            preview.innerHTML = 'Precio final: <strong>€' + nuevoPrecio.toFixed(2).replace('.', ',') + '</strong>';
                        } else {
                            preview.innerHTML = 'Precio final: €<?php echo number_format($producto['precio'], 2, ',', '.'); ?>';
                        }
                    });
                    
                    // También actualizar preview cuando cambie el precio original
                    document.getElementById('precio_original_<?php echo $producto['id']; ?>').addEventListener('input', function(e) {
                        const productoId = this.getAttribute('data-producto-id');
                        const precioOriginal = parseFloat(this.value);
                        const porcentaje = document.getElementById('porcentaje_' + productoId).value;
                        const preview = document.getElementById('preview_' + productoId);
                        
                        if (!isNaN(precioOriginal) && porcentaje && porcentaje > 0) {
                            const nuevoPrecio = precioOriginal - (precioOriginal * porcentaje / 100);
                            preview.innerHTML = 'Precio final: <strong>€' + nuevoPrecio.toFixed(2).replace('.', ',') + '</strong>';
                        } else {
                            preview.innerHTML = 'Precio final: €' + precioOriginal.toFixed(2).replace('.', ',');
                        }
                    });
                    </script>
                    <?php endwhile; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Botón volver -->
        <div class="mt-4">
            <a href="admin_panel.php" class="btn btn-secondary">
                <i class="bi-arrow-left"></i> Volver al Panel
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>