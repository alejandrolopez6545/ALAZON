<?php
require_once('session.php');
if (!$es_admin) {
    header('Location: index.php');
    exit();
}
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h4 class="mb-0">
            <i class="bi-shield-lock me-2"></i>
            Panel Admin
        </h4>
        <small class="text-white-50">ALAZÓN Admin</small>
    </div>
    
    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="admin_panel.php">
                    <i class="bi-speedometer2"></i>
                    <span>Panel</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_usuarios.php">
                    <i class="bi-people"></i>
                    <span>Usuarios</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_productos.php">
                    <i class="bi-box"></i>
                    <span>Productos</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_pedidos.php">
                    <i class="bi-receipt"></i>
                    <span>Pedidos</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin_productos.php">
                    <i class="bi-tag"></i>
                    <span>Rebajas</span>
                </a>
            <li class="nav-item mt-4">
                <a class="nav-link" href="index.php" target="_blank">
                    <i class="bi-shop"></i>
                    <span>Ver Tienda</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="bi-box-arrow-right"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </li>
        </ul>
    </div>
</div>