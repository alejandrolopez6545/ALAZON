<?php
// session.php - Manejo centralizado de sesiones
session_start();

$usuario = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : '';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '';
$rol = isset($_SESSION['rol']) ? $_SESSION['rol'] : '';
$email = isset($_SESSION['email']) ? $_SESSION['email'] : '';
$pais = isset($_SESSION['pais']) ? $_SESSION['pais'] : 'ES';
$logged_in = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);

// NUEVO: Variables compatibles con todos tus archivos
$user_role = $rol; // ← Para compatibilidad con admin archivos
$es_admin = ($rol === 'admin'); // ← Para verificación simple

// Información de moneda
$moneda_codigo = 'EUR'; // Valor por defecto
$moneda_simbolo = '€'; // Valor por defecto
$cantidad_carrito = 0;

if ($logged_in) {
    // Forzar actualización de moneda cada vez o verificar si necesita actualizarse
    require_once('conexion.php');
    require_once('config.php');
    $db = new Database();
    $con = $db->getCon();
    
    // Obtener el país actual del usuario desde la BD
    $sql = "SELECT pais, rol FROM users WHERE id = '$user_id'";
    $result = mysqli_query($con, $sql);
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $pais_actual_bd = $row['pais'];
        $rol_actual_bd = $row['rol'];
        
        // Si el país en la sesión no coincide con el de la BD, actualizar
        if (!isset($_SESSION['pais']) || $_SESSION['pais'] !== $pais_actual_bd) {
            $_SESSION['pais'] = $pais_actual_bd;
            $pais = $pais_actual_bd;
        }
        
        // Si el rol en la sesión no coincide con el de la BD, actualizar
        if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== $rol_actual_bd) {
            $_SESSION['rol'] = $rol_actual_bd;
            $rol = $rol_actual_bd;
            $user_role = $rol_actual_bd; // ← Actualizar también user_role
        }
    }
    
    // Recalcular si es admin
    $es_admin = ($rol === 'admin');
    
    // Obtener moneda actualizada
    $moneda_info = obtenerMonedaUsuario($user_id, $con);
    $_SESSION['moneda_codigo'] = $moneda_info['codigo'];
    $_SESSION['moneda_simbolo'] = $moneda_info['simbolo'];
    
    $moneda_codigo = $moneda_info['codigo'];
    $moneda_simbolo = $moneda_info['simbolo'];
    
    // Obtener cantidad de items en carrito para el badge
    $sql_carrito_count = "SELECT SUM(oi.cantidad) as total_items
                          FROM orders o
                          JOIN order_items oi ON o.id = oi.order_id
                          WHERE o.user_id = '$user_id' AND o.estado = 'carrito'";
    $result_carrito_count = mysqli_query($con, $sql_carrito_count);
    if ($result_carrito_count && $row = mysqli_fetch_assoc($result_carrito_count)) {
        $cantidad_carrito = $row['total_items'] ?? 0;
        $_SESSION['cantidad_carrito'] = $cantidad_carrito;
    }
} else {
    // Para usuarios no logueados
    $moneda_codigo = 'EUR';
    $moneda_simbolo = '€';
    $cantidad_carrito = 0;
    $es_admin = false;
    $user_role = '';
}
?>