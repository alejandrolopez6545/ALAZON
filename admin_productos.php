<?php
// admin_productos.php - CON CATEGORÍAS INTEGRADAS Y SUBIDA DE IMÁGENES
require_once('session.php');
require_once('conexion.php');

$db = new Database();
$con = $db->getCon();
$mensaje = '';
$tipo_mensaje = 'success';

// Configuración de imágenes
$upload_dir = 'uploads/productos/';

// Crear directorio si no existe
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Función para subir imagen
function subirImagen($archivo) {
    global $upload_dir;
    
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error al subir el archivo'];
    }
    
    // Validar tipo de archivo
    $tipos_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $tipo_archivo = $archivo['type'];
    
    if (!in_array($tipo_archivo, $tipos_permitidos)) {
        return ['success' => false, 'message' => 'Tipo de archivo no permitido. Solo imágenes JPG, PNG, GIF o WebP'];
    }
    
    // Validar tamaño (máximo 5MB)
    if ($archivo['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'El archivo es demasiado grande. Máximo 5MB'];
    }
    
    // Generar nombre único
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
    $nombre_unico = uniqid() . '_' . time() . '.' . $extension;
    $ruta_destino = $upload_dir . $nombre_unico;
    
    // Mover el archivo
    if (move_uploaded_file($archivo['tmp_name'], $ruta_destino)) {
        return ['success' => true, 'filename' => $nombre_unico];
    } else {
        return ['success' => false, 'message' => 'Error al guardar el archivo'];
    }
}

// Función para eliminar imagen
function eliminarImagen($nombre_archivo) {
    global $upload_dir;
    if (!empty($nombre_archivo) && file_exists($upload_dir . $nombre_archivo)) {
        return unlink($upload_dir . $nombre_archivo);
    }
    return false;
}

// Eliminar producto
if (isset($_GET['eliminar'])) {
    $id = intval($_GET['eliminar']);
    
    // Obtener nombre de la imagen antes de eliminar
    $sql_img = "SELECT imagen FROM products WHERE id = $id";
    $result_img = mysqli_query($con, $sql_img);
    $producto_img = mysqli_fetch_assoc($result_img);
    
    // Eliminar imagen física si existe
    if (!empty($producto_img['imagen'])) {
        eliminarImagen($producto_img['imagen']);
    }
    
    $sql = "DELETE FROM products WHERE id = $id";
    if (mysqli_query($con, $sql)) {
        $mensaje = 'Producto eliminado correctamente';
        $tipo_mensaje = 'success';
    } else {
        $mensaje = 'Error al eliminar producto: ' . mysqli_error($con);
        $tipo_mensaje = 'danger';
    }
}

// Actualizar producto con imagen
if (isset($_POST['actualizar'])) {
    $id = intval($_POST['id']);
    $nombre = $db->sanitize($_POST['nombre']);
    $precio = floatval($_POST['precio']);
    $stock = intval($_POST['stock']);
    $estado = $db->sanitize($_POST['estado']);
    $descripcion = $db->sanitize($_POST['descripcion'] ?? '');
    $categoria_id = intval($_POST['categoria_id'] ?? 0);
    
    $sql = "UPDATE products SET 
            nombre = '$nombre',
            precio = $precio,
            stock = $stock,
            estado = '$estado',
            descripcion = '$descripcion',
            category_id = " . ($categoria_id > 0 ? $categoria_id : "NULL") . "
            WHERE id = $id";
    
    if (mysqli_query($con, $sql)) {
        // Procesar nueva imagen si se subió
        if (isset($_FILES['nueva_imagen_' . $id]) && $_FILES['nueva_imagen_' . $id]['error'] == UPLOAD_ERR_OK) {
            $resultado_imagen = subirImagen($_FILES['nueva_imagen_' . $id]);
            
            if ($resultado_imagen['success']) {
                // Obtener imagen anterior para eliminarla
                $sql_old_img = "SELECT imagen FROM products WHERE id = $id";
                $result_old_img = mysqli_query($con, $sql_old_img);
                $old_img = mysqli_fetch_assoc($result_old_img);
                
                // Eliminar imagen anterior si existe
                if (!empty($old_img['imagen'])) {
                    eliminarImagen($old_img['imagen']);
                }
                
                // Actualizar con la nueva imagen
                $nueva_imagen = $resultado_imagen['filename'];
                $sql_img = "UPDATE products SET imagen = '$nueva_imagen' WHERE id = $id";
                mysqli_query($con, $sql_img);
            }
        } else {
            // Si no se subió nueva imagen, verificar si se seleccionó una existente
            if (isset($_POST['imagen_seleccionada_' . $id]) && !empty($_POST['imagen_seleccionada_' . $id])) {
                $nueva_imagen = $db->sanitize($_POST['imagen_seleccionada_' . $id]);
                
                // Obtener imagen anterior para eliminarla
                $sql_old_img = "SELECT imagen FROM products WHERE id = $id";
                $result_old_img = mysqli_query($con, $sql_old_img);
                $old_img = mysqli_fetch_assoc($result_old_img);
                
                // Solo actualizar si es diferente
                if ($old_img['imagen'] != $nueva_imagen) {
                    // Eliminar imagen anterior si existe
                    if (!empty($old_img['imagen'])) {
                        eliminarImagen($old_img['imagen']);
                    }
                    
                    $sql_img = "UPDATE products SET imagen = '$nueva_imagen' WHERE id = $id";
                    mysqli_query($con, $sql_img);
                }
            }
        }
        
        $mensaje = 'Producto actualizado correctamente';
        $tipo_mensaje = 'success';
    } else {
        $mensaje = 'Error al actualizar producto: ' . mysqli_error($con);
        $tipo_mensaje = 'danger';
    }
}

// Crear producto con imagen
if (isset($_POST['crear'])) {
    $nombre = $db->sanitize($_POST['nombre_nuevo']);
    $precio = floatval($_POST['precio_nuevo']);
    $stock = intval($_POST['stock_nuevo']);
    $descripcion = $db->sanitize($_POST['descripcion_nuevo'] ?? '');
    $categoria_id = intval($_POST['categoria_id_nuevo'] ?? 0);
    
    $imagen = '';
    
    // Procesar imagen si se subió
    if (isset($_FILES['imagen_nuevo']) && $_FILES['imagen_nuevo']['error'] == UPLOAD_ERR_OK) {
        $resultado_imagen = subirImagen($_FILES['imagen_nuevo']);
        if ($resultado_imagen['success']) {
            $imagen = $resultado_imagen['filename'];
        } else {
            $mensaje = $resultado_imagen['message'];
            $tipo_mensaje = 'danger';
        }
    }
    
    $sql = "INSERT INTO products (nombre, precio, stock, descripcion, estado, imagen, category_id) 
            VALUES ('$nombre', $precio, $stock, '$descripcion', 'activo', '$imagen', " . ($categoria_id > 0 ? $categoria_id : "NULL") . ")";
    
    if (mysqli_query($con, $sql)) {
        $mensaje = 'Producto creado correctamente';
        $tipo_mensaje = 'success';
    } else {
        $mensaje = 'Error al crear producto: ' . mysqli_error($con);
        $tipo_mensaje = 'danger';
    }
}

// Obtener productos con categoría
$sql = "SELECT p.*, c.nombre as categoria_nombre 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.id DESC";
$result = mysqli_query($con, $sql);

// Obtener todas las categorías para los selects
$sql_categorias = "SELECT id, nombre FROM categories WHERE activo = 1 ORDER BY nombre";
$result_categorias = mysqli_query($con, $sql_categorias);
$categorias = [];
while ($row = mysqli_fetch_assoc($result_categorias)) {
    $categorias[] = $row;
}

// Obtener lista de imágenes disponibles
$imagenes_disponibles = [];
if (is_dir('uploads/productos/')) {
    $archivos = scandir('uploads/productos/');
    foreach ($archivos as $archivo) {
        if ($archivo != '.' && $archivo != '..' && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $archivo)) {
            $imagenes_disponibles[] = $archivo;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos - Admin ALAZÓN</title>
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
        .preview-image {
            max-width: 100px;
            max-height: 100px;
            margin-top: 10px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .selector-imagen-modal {
            max-height: 400px;
            overflow-y: auto;
        }
        .opcion-imagen {
            cursor: pointer;
            margin: 5px;
            padding: 5px;
            border: 2px solid transparent;
            border-radius: 8px;
            text-align: center;
        }
        .opcion-imagen:hover {
            background-color: #f8f9fa;
        }
        .opcion-imagen.seleccionada {
            border-color: #007bff;
            background-color: #e7f1ff;
        }
        .opcion-imagen img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .categoria-badge {
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            color: #495057;
        }
        .categoria-badge i {
            margin-right: 3px;
        }
        .tabla-admin th {
            white-space: nowrap;
        }
        .tabla-admin td {
            vertical-align: middle;
        }
        .form-control-sm, .form-select-sm {
            min-width: 120px;
        }
        .upload-btn-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
        }
        .btn-file {
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 5px 10px;
        }
        .upload-btn-wrapper input[type=file] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
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
                <a href="admin_productos.php" class="active"><i class="bi-box"></i> Productos</a>
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
        <!-- Título y botón nuevo producto -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0"><i class="bi-box me-2"></i>Gestión de Productos</h1>
                <p class="text-muted mb-0">Administra el catálogo de productos</p>
            </div>
            <div>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#nuevoProductoModal">
                    <i class="bi-plus-circle me-2"></i>Nuevo Producto
                </button>
            </div>
        </div>
        
        <!-- Mensajes -->
        <?php if ($mensaje): ?>
            <div class="alert alert-<?php echo $tipo_mensaje; ?> alert-dismissible fade show" role="alert">
                <i class="bi <?php echo $tipo_mensaje == 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'; ?> me-2"></i>
                <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Tabla de productos con categoría -->
        <div class="table-responsive">
            <form method="POST" enctype="multipart/form-data">
            <table class="table table-hover table-striped align-middle tabla-admin">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Imagen</th>
                        <th>Nombre</th>
                        <th>Categoría</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>Estado</th>
                        <th>Descripción</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($producto = mysqli_fetch_assoc($result)): ?>
                    <tr>
                        <td><strong>#<?php echo $producto['id']; ?></strong></td>
                        <td>
                            <?php 
                            $imagen_url = 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg';
                            if (!empty($producto['imagen']) && file_exists('uploads/productos/' . $producto['imagen'])): 
                                $imagen_url = 'uploads/productos/' . $producto['imagen'];
                            endif;
                            ?>
                            <img src="<?php echo $imagen_url; ?>" 
                                 alt="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                 class="product-image"
                                 id="img_<?php echo $producto['id']; ?>">
                            
                            <!-- Input oculto para imagen seleccionada -->
                            <input type="hidden" name="imagen_seleccionada_<?php echo $producto['id']; ?>" 
                                   id="imagen_sel_<?php echo $producto['id']; ?>" 
                                   value="<?php echo $producto['imagen']; ?>">
                            
                            <!-- Input file para subir nueva imagen -->
                            <div class="mt-1">
                                <input type="file" name="nueva_imagen_<?php echo $producto['id']; ?>" 
                                       class="form-control form-control-sm" 
                                       accept="image/jpeg,image/png,image/gif,image/webp"
                                       onchange="previewImage(this, <?php echo $producto['id']; ?>)">
                            </div>
                        </td>
                        <td>
                            <input type="text" name="nombre" class="form-control form-control-sm" 
                                   value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>
                        </td>
                        <td>
                            <!-- SELECT DE CATEGORÍA -->
                            <select name="categoria_id" class="form-select form-select-sm">
                                <option value="0">Sin categoría</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id']; ?>" 
                                        <?php echo ($producto['category_id'] == $categoria['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($categoria['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input type="number" step="0.01" name="precio" class="form-control form-control-sm" 
                                   value="<?php echo $producto['precio']; ?>" required>
                        </td>
                        <td>
                            <input type="number" name="stock" class="form-control form-control-sm" 
                                   value="<?php echo $producto['stock']; ?>" required>
                        </td>
                        <td>
                            <select name="estado" class="form-select form-select-sm">
                                <option value="activo" <?php echo $producto['estado'] == 'activo' ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactivo" <?php echo $producto['estado'] == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </td>
                        <td>
                            <input type="text" name="descripcion" class="form-control form-control-sm" 
                                   value="<?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?>" 
                                   placeholder="Descripción">
                        </td>
                        <td>
                            <div class="d-flex gap-1">
                                <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                                
                                <!-- BOTÓN DE IMAGEN (galería) -->
                                <button type="button" class="btn btn-sm btn-info" 
                                        onclick="abrirSelector(<?php echo $producto['id']; ?>)"
                                        title="Seleccionar de la galería">
                                    <i class="bi-images"></i>
                                </button>
                                
                                <button type="submit" name="actualizar" class="btn btn-sm btn-primary" title="Guardar cambios">
                                    <i class="bi-save"></i>
                                </button>
                            </form>
                            
                            <a href="?eliminar=<?php echo $producto['id']; ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('¿Estás seguro de eliminar este producto?')" 
                               title="Eliminar producto">
                                <i class="bi-trash"></i>
                            </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            </form>
        </div>
    </div>
    
    <!-- Modal selector de imágenes (galería) -->
    <div class="modal fade" id="selectorModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi-images me-2"></i>Seleccionar imagen de la galería</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body selector-imagen-modal">
                    <div class="row">
                        <?php if (empty($imagenes_disponibles)): ?>
                            <div class="col-12">
                                <div class="alert alert-warning">
                                    No hay imágenes en la carpeta uploads/productos/
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($imagenes_disponibles as $img): ?>
                                <div class="col-3 opcion-imagen" onclick="seleccionarImagen('<?php echo $img; ?>', this)">
                                    <img src="uploads/productos/<?php echo $img; ?>" alt="<?php echo $img; ?>">
                                    <small class="d-block text-truncate"><?php echo $img; ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="confirmarSeleccion()">Seleccionar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal para nuevo producto (CON CATEGORÍA Y SUBIDA DE IMAGEN) -->
    <div class="modal fade" id="nuevoProductoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi-plus-circle me-2"></i>Nuevo Producto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre *</label>
                                <input type="text" class="form-control" name="nombre_nuevo" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Categoría</label>
                                <select class="form-select" name="categoria_id_nuevo">
                                    <option value="0">Sin categoría</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                        <option value="<?php echo $categoria['id']; ?>">
                                            <?php echo htmlspecialchars($categoria['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Precio *</label>
                                <div class="input-group">
                                    <span class="input-group-text">€</span>
                                    <input type="number" step="0.01" class="form-control" name="precio_nuevo" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Stock *</label>
                                <input type="number" class="form-control" name="stock_nuevo" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="estado_nuevo">
                                    <option value="activo">Activo</option>
                                    <option value="inactivo">Inactivo</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="descripcion_nuevo" rows="3"></textarea>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Imagen del producto</label>
                                <input type="file" class="form-control" name="imagen_nuevo" 
                                       accept="image/jpeg,image/png,image/gif,image/webp"
                                       onchange="previewNewImage(this)">
                                <div class="mt-2" id="nueva_imagen_preview"></div>
                                <small class="text-muted">Formatos permitidos: JPG, PNG, GIF, WebP (Máx. 5MB)</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="crear" class="btn btn-primary">Crear Producto</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let productoActivo = null;
        let imagenSeleccionada = null;
        
        function abrirSelector(productoId) {
            productoActivo = productoId;
            imagenSeleccionada = null;
            
            // Resetear selecciones
            document.querySelectorAll('.opcion-imagen').forEach(el => {
                el.classList.remove('seleccionada');
            });
            
            new bootstrap.Modal(document.getElementById('selectorModal')).show();
        }
        
        function seleccionarImagen(imagen, elemento) {
            // Quitar selección anterior
            document.querySelectorAll('.opcion-imagen').forEach(el => {
                el.classList.remove('seleccionada');
            });
            
            // Marcar el seleccionado
            elemento.classList.add('seleccionada');
            imagenSeleccionada = imagen;
        }
        
        function confirmarSeleccion() {
            if (productoActivo && imagenSeleccionada) {
                // Actualizar el campo oculto
                document.getElementById('imagen_sel_' + productoActivo).value = imagenSeleccionada;
                
                // Actualizar la imagen mostrada
                document.getElementById('img_' + productoActivo).src = 'uploads/productos/' + imagenSeleccionada;
                
                // Cerrar modal
                bootstrap.Modal.getInstance(document.getElementById('selectorModal')).hide();
            }
        }
        
        function previewImage(input, productoId) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('img_' + productoId).src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function previewNewImage(input) {
            var preview = document.getElementById('nueva_imagen_preview');
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    var img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'preview-image';
                    preview.appendChild(img);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>