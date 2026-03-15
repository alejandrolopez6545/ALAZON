<?php
// confirmacion_simple.php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compra Exitosa - ALAZON</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f9fafb;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        
        .checkmark {
            color: #10b981;
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #1f2937;
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        p {
            color: #6b7280;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        
        .buttons {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .btn {
            padding: 14px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: all 0.2s;
            display: block;
        }
        
        .btn-primary {
            background: #2563eb;
            color: white;
        }
        
        .btn-primary:hover {
            background: #1d4ed8;
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="checkmark">✓</div>
        <h1>¡Compra realizada con éxito!</h1>
        <p>Tu pedido ha sido procesado correctamente. Puedes ver el estado de tu compra en cualquier momento.</p>
        
        <div class="buttons">
            <a href="mis_pedidos.php" class="btn btn-primary">Mis Pedidos</a>
            <a href="index.php" class="btn btn-secondary">Volver al Inicio</a>
        </div>
    </div>
</body>
</html>