<?php
require_once('session.php');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <title>Sobre Nosotros - ALAZÓN</title>
    <link rel="icon" type="image/x-icon" href="assets/favicon.ico" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <style>
        /* Mantenemos el estilo que ya tienes en index.php */
        .navbar {
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        /* Header negro como en index.php */
        .bg-black {
            background-color: #000 !important;
        }
        .sobre-nosotros-img {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            max-width: 100%;
            height: auto;
            max-height: 400px; /* Limito la altura máxima */
            width: 100%;
            object-fit: cover; /* Para que se vea bien aunque recorte un poco */
        }
        .icon-feature {
            font-size: 2.5rem;
            color: #000;
        }
    </style>
</head>

<body>
    <!-- Navigation (igual que en index.php) -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container px-4 px-lg-5">
            <a class="navbar-brand" href="index.php">ALAZÓN</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarSupportedContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link active" aria-current="page" href="SNosotros.php">Sobre Nosotros</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" id="navbarDropdown" href="#" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">Tienda</a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="catalogo.php?filtro=todos">TODOS LOS PRODUCTOS</a></li>
                            <li>
                                <hr class="dropdown-divider" />
                            </li>
                            <li><a class="dropdown-item" href="catalogo.php?filtro=valorados">MEJOR VALORADOS</a></li>
                            <li><a class="dropdown-item" href="catalogo.php?filtro=rebajas">REBAJAS</a></li>
                        </ul>
                    </li>
                </ul>
                <form class="d-flex">
                    <?php if ($logged_in): ?>
                        <div class="dropdown">
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
                                        <i class="bi-shield-lock me-2"></i>Panel de Administración</a></li>
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
                        <a href="registro.php" class="btn btn-dark me-2">
                            <i class="bi-person-plus me-1"></i>
                            Registrarse
                        </a>
                    <?php endif; ?>
                    
                    <a href="carrito.php" class="btn btn-outline-dark">
                        <i class="bi-cart-fill me-1"></i>
                        Carrito
                    </a>
                </form>
            </div>
        </div>
    </nav>

    <!-- Header NEGRO como en index.php -->
    <header class="bg-black py-5">
        <div class="container px-4 px-lg-5 my-5">
            <div class="text-center text-white">
                <h1 class="display-4 fw-bolder">Sobre Nosotros</h1>
                <p class="lead fw-normal text-white-50 mb-0">Conoce un poco más de ALAZÓN</p>
            </div>
        </div>
    </header>

    <!-- Contenido principal -->
    <section class="py-5">
        <div class="container px-4 px-lg-5">
            <!-- Primera fila: imagen y texto -->
            <div class="row align-items-center mb-5">
                <div class="col-lg-6">
                    <img src="https://images.pexels.com/photos/4482900/pexels-photo-4482900.jpeg?auto=compress&cs=tinysrgb&w=600" 
                         alt="Sobre nosotros" 
                         class="sobre-nosotros-img img-fluid">
                </div>
                <div class="col-lg-6">
                    <h2 class="fw-bolder mb-3">¿Quiénes somos?</h2>
                    <p class="lead">ALAZÓN nació en 2020 con una idea clara: ofrecer productos de calidad a precios justos.</p>
                    <p>Lo que empezó como un pequeño proyecto, hoy es una tienda online en crecimiento que busca la mejor experiencia para sus clientes. Nos esforzamos cada día para mejorar y ofrecerte lo que buscas.</p>
                    <p class="mb-0"><i class="bi-check-circle-fill text-success me-2"></i>Más de 500 productos disponibles</p>
                    <p><i class="bi-check-circle-fill text-success me-2"></i>Envíos a toda España,Europa y el Mundo!</p>
                </div>
            </div>

            <!-- Segunda fila: características sencillas -->
            <div class="row text-center mt-5 pt-4">
                <h3 class="fw-bolder mb-4">Por qué elegirnos</h3>
                
                <div class="col-md-4 mb-4">
                    <div class="p-4">
                        <i class="bi-truck icon-feature mb-3 d-block"></i>
                        <h5>Envíos rápidos</h5>
                        <p class="text-muted">Entregas en 24-48 horas en la mayoría de destinos.</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="p-4">
                        <i class="bi-shield-check icon-feature mb-3 d-block"></i>
                        <h5>Compra segura</h5>
                        <p class="text-muted">Tus datos y pagos están siempre protegidos.</p>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4">
                    <div class="p-4">
                        <i class="bi-headset icon-feature mb-3 d-block"></i>
                        <h5>Atención al cliente</h5>
                        <p class="text-muted">Estamos aquí para ayudarte cuando lo necesites.</p>
                    </div>
                </div>
            </div>
            
            <!-- Llamada a la acción sencilla -->
            <div class="text-center bg-light p-5 rounded-3 mt-4">
                <h4 class="fw-bolder mb-3">¿Preparado para empezar a comprar?</h4>
                <p class="mb-4">Descubre todo lo que tenemos para ti</p>
                <a href="catalogo.php?filtro=todos" class="btn btn-dark btn-lg">
                    <i class="bi-shop me-2"></i>Ver catálogo
                </a>
            </div>
        </div>
    </section>

    <!-- Footer negro también para que pegue -->
    <footer class="py-4 bg-black">
        <div class="container">
            <p class="m-0 text-center text-white">ALAZÓN &copy; <?php echo date('Y'); ?> - Todos los derechos reservados</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>