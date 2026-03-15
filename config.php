<?php
// config.php - Configuraciones globales del sistema

// Configuración de moneda por defecto
define('MONEDA_DEFECTO', 'EUR');
define('SIMBOLO_MONEDA_DEFECTO', '€');

// Tipos de cambio fijos
$TASAS_CAMBIO = [
    'EUR' => 1.0800,
    'USD' => 1.0000,
    'GBP' => 0.7900,
    'MXN' => 0.0580,
    'ARS' => 0.0011,
    'CLP' => 0.0010,
    'COP' => 0.00025,
    'PEN' => 0.2700,
    'BRL' => 0.2000
];

// Función para obtener moneda del usuario CON MANEJO DE ERRORES
function obtenerMonedaUsuario($user_id, $conexion) {
    // Primero verificar si la columna existe
    $check_column = "SHOW COLUMNS FROM paises LIKE 'moneda_codigo'";
    $result_check = mysqli_query($conexion, $check_column);
    
    if (!$result_check || mysqli_num_rows($result_check) == 0) {
        // La columna no existe, retornar valores por defecto
        return [
            'codigo' => MONEDA_DEFECTO,
            'simbolo' => SIMBOLO_MONEDA_DEFECTO
        ];
    }
    
    // Si la columna existe, hacer la consulta normal
    $sql = "SELECT p.moneda_codigo, m.simbolo 
            FROM users u 
            JOIN paises p ON u.pais = p.codigo 
            LEFT JOIN monedas m ON p.moneda_codigo = m.codigo 
            WHERE u.id = '$user_id'";
    
    $result = mysqli_query($conexion, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        if ($row['moneda_codigo'] && $row['simbolo']) {
            return [
                'codigo' => $row['moneda_codigo'],
                'simbolo' => $row['simbolo']
            ];
        }
    }
    
    // Fallback si hay algún error
    return [
        'codigo' => MONEDA_DEFECTO,
        'simbolo' => SIMBOLO_MONEDA_DEFECTO
    ];
}
// Función para formatear precio
function formatearPrecio($precio, $moneda_codigo, $simbolo = '') {
    $formatos = [
        'EUR' => ['decimales' => 2, 'separador_decimal' => ',', 'separador_miles' => '.'],
        'USD' => ['decimales' => 2, 'separador_decimal' => '.', 'separador_miles' => ','],
        'GBP' => ['decimales' => 2, 'separador_decimal' => '.', 'separador_miles' => ','],
        'MXN' => ['decimales' => 2, 'separador_decimal' => '.', 'separador_miles' => ','],
        'ARS' => ['decimales' => 2, 'separador_decimal' => ',', 'separador_miles' => '.'],
        'CLP' => ['decimales' => 0, 'separador_decimal' => '', 'separador_miles' => '.'],
        'COP' => ['decimales' => 0, 'separador_decimal' => '', 'separador_miles' => '.'],
        'PEN' => ['decimales' => 2, 'separador_decimal' => '.', 'separador_miles' => ','],
    ];
    
    $formato = $formatos[$moneda_codigo] ?? ['decimales' => 2, 'separador_decimal' => '.', 'separador_miles' => ','];
    
    $precio_formateado = number_format(
        $precio,
        $formato['decimales'],
        $formato['separador_decimal'],
        $formato['separador_miles']
    );
    
    // Posicionar el símbolo según la moneda
    $simbolos_izquierda = ['EUR', 'USD', 'GBP', 'MXN', 'ARS', 'CLP', 'COP', 'PEN'];
    
    if (in_array($moneda_codigo, $simbolos_izquierda)) {
        return $simbolo . $precio_formateado;
    } else {
        return $precio_formateado . ' ' . $simbolo;
    }
}

// Función para refrescar moneda en sesión
function refrescarMonedaUsuario($user_id, $conexion) {
    // Obtener moneda actualizada
    $moneda_info = obtenerMonedaUsuario($user_id, $conexion);
    
    // Actualizar sesión
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['moneda_codigo'] = $moneda_info['codigo'];
    $_SESSION['moneda_simbolo'] = $moneda_info['simbolo'];
    
    return $moneda_info;
}
?>