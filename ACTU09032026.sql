-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 09-03-2026 a las 14:04:12
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `alazon`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categories`
--

DROP TABLE IF EXISTS `categories`;
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categories`
--

INSERT INTO `categories` (`id`, `nombre`, `descripcion`, `imagen`, `activo`, `fecha_creacion`) VALUES
(1, 'Portátiles', 'Ordenadores portátiles y PCs', NULL, 1, '2026-03-09 11:29:00'),
(2, 'Monitores', 'Monitores y pantallas', NULL, 1, '2026-03-09 11:29:00'),
(3, 'Periféricos', 'Teclados, ratones y accesorios', NULL, 1, '2026-03-09 11:29:00'),
(4, 'Componentes', 'Componentes de PC', NULL, 1, '2026-03-09 11:29:00'),
(5, 'Audio', 'Auriculares y altavoces', NULL, 1, '2026-03-09 11:29:00'),
(6, 'Almacenamiento', 'Discos duros y SSDs', NULL, 1, '2026-03-09 11:29:00'),
(7, 'Sillas Gaming', 'Sillas ergonómicas y gaming', NULL, 1, '2026-03-09 11:29:00'),
(8, 'Prueba', 'Prueba', NULL, 1, '2026-03-09 12:54:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metodos_pago`
--

DROP TABLE IF EXISTS `metodos_pago`;
CREATE TABLE IF NOT EXISTS `metodos_pago` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `icono` varchar(50) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `orden` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `metodos_pago`
--

INSERT INTO `metodos_pago` (`id`, `nombre`, `descripcion`, `icono`, `activo`, `orden`) VALUES
(1, 'Tarjeta de Crédito/Débito', 'Pago seguro con tarjeta Visa, Mastercard, etc.', 'bi-credit-card', 1, 1),
(2, 'PayPal', 'Pago rápido y seguro a través de PayPal', 'bi-paypal', 1, 2),
(3, 'Transferencia Bancaria', 'Pago mediante transferencia bancaria', 'bi-bank', 1, 3),
(4, 'MercadoPago', 'Popular en Latinoamérica', 'bi-wallet', 1, 4),
(5, 'Contra Reembolso', 'Paga al recibir el producto', 'bi-cash', 1, 5),
(6, 'Google Pay', 'Pago con Google Pay', 'bi-google', 1, 6),
(7, 'Apple Pay', 'Pago con Apple Pay', 'bi-apple', 1, 7);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `monedas`
--

DROP TABLE IF EXISTS `monedas`;
CREATE TABLE IF NOT EXISTS `monedas` (
  `codigo` char(3) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `simbolo` varchar(10) NOT NULL,
  `cambio_usd` decimal(10,4) DEFAULT 1.0000,
  PRIMARY KEY (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `monedas`
--

INSERT INTO `monedas` (`codigo`, `nombre`, `simbolo`, `cambio_usd`) VALUES
('ARS', 'Peso argentino', '$', 0.0011),
('BRL', 'Real brasileño', 'R$', 0.2000),
('CLP', 'Peso chileno', '$', 0.0010),
('COP', 'Peso colombiano', '$', 0.0003),
('EUR', 'Euro', '€', 1.0800),
('GBP', 'Libra esterlina', '£', 0.7900),
('MXN', 'Peso mexicano', '$', 0.0580),
('PEN', 'Sol peruano', 'S/', 0.2700),
('USD', 'Dólar estadounidense', '$', 1.0000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  `total` decimal(10,2) DEFAULT 0.00,
  `estado` enum('carrito','pagado','enviado','cancelado') DEFAULT 'carrito',
  `direccion_envio` text DEFAULT NULL,
  `metodo_pago_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `fk_orders_metodo_pago` (`metodo_pago_id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `orders`
--

INSERT INTO `orders` (`id`, `user_id`, `fecha`, `total`, `estado`, `direccion_envio`, `metodo_pago_id`) VALUES
(14, 2, '2026-02-15 12:58:30', 399.99, 'pagado', 'Calle prueba n16 ', 1),
(18, 2, '2026-02-16 11:35:50', 489.98, 'pagado', 'Prueba', 1),
(20, 1, '2026-02-16 11:44:34', 399.99, 'pagado', 'Prueba4', 1),
(22, 1, '2026-02-16 11:59:38', 1399.99, 'pagado', 'jvhbsduhvbsd', 1),
(24, 1, '2026-02-16 12:01:22', 100.00, 'pagado', 'ghvhg', 1),
(26, 1, '2026-02-16 12:02:00', 799.98, 'pagado', 'kmnljk', 1),
(28, 1, '2026-02-16 12:29:56', 399.99, 'pagado', 'calle popola', 1),
(31, 2, '2026-02-23 12:41:52', 0.00, 'carrito', NULL, NULL),
(34, 9, '2026-03-09 11:40:18', 5000.00, 'pagado', 'Dvadesetdevetog Novembra 11-20, Beograd', 6),
(35, 1, '2026-03-09 12:01:55', 119.99, 'pagado', 'Prueba', 1),
(37, 1, '2026-03-09 12:37:19', 350.00, 'pagado', 'Prueba', 1),
(39, 1, '2026-03-09 12:47:08', 159.99, 'pagado', 'Enseñar', 1),
(41, 1, '2026-03-09 12:55:17', 159.99, 'pagado', 'Prueba', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `order_items`
--

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `cantidad`, `precio_unitario`) VALUES
(12, 14, 3, 1, 399.99),
(14, 18, 3, 1, 399.99),
(15, 18, 2, 1, 89.99),
(16, 20, 3, 1, 399.99),
(17, 22, 1, 1, 1299.99),
(20, 26, 3, 2, 399.99),
(22, 28, 3, 1, 399.99),
(30, 35, 4, 1, 30.00),
(32, 35, 2, 1, 89.99),
(33, 37, 11, 1, 350.00),
(34, 39, 10, 1, 159.99),
(35, 41, 10, 1, 159.99);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `paises`
--

DROP TABLE IF EXISTS `paises`;
CREATE TABLE IF NOT EXISTS `paises` (
  `codigo` char(2) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `total_usuarios` int(11) DEFAULT 0,
  `moneda_codigo` char(3) DEFAULT 'EUR',
  PRIMARY KEY (`codigo`),
  KEY `fk_paises_moneda` (`moneda_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `paises`
--

INSERT INTO `paises` (`codigo`, `nombre`, `total_usuarios`, `moneda_codigo`) VALUES
('AR', 'Argentina', 0, 'ARS'),
('CL', 'Chile', 0, 'CLP'),
('CO', 'Colombia', 0, 'COP'),
('DE', 'Alemania', 0, 'EUR'),
('ES', 'España', 5, 'EUR'),
('FR', 'Francia', 0, 'EUR'),
('GB', 'Reino Unido', 0, 'GBP'),
('IT', 'Italia', 0, 'EUR'),
('MX', 'México', 0, 'MXN'),
('PE', 'Perú', 0, 'PEN'),
('PT', 'Portugal', 0, 'EUR'),
('US', 'Estados Unidos', 1, 'USD');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL,
  `stock` int(11) DEFAULT 0,
  `imagen_url` varchar(255) DEFAULT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `valoracion` decimal(3,2) DEFAULT 0.00,
  `num_valoraciones` int(11) DEFAULT 0,
  `en_rebaja` tinyint(1) DEFAULT 0,
  `precio_original` decimal(10,2) DEFAULT NULL,
  `porcentaje_rebaja` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `idx_valoracion` (`valoracion`),
  KEY `idx_en_rebaja` (`en_rebaja`)
) ;

--
-- Volcado de datos para la tabla `products`
--

INSERT INTO `products` (`id`, `category_id`, `nombre`, `descripcion`, `imagen`, `precio`, `stock`, `imagen_url`, `estado`, `fecha_creacion`, `valoracion`, `num_valoraciones`, `en_rebaja`, `precio_original`, `porcentaje_rebaja`) VALUES
(1, 1, 'Portátil Gaming', 'Portátil gaming con RTX 4060', 'portatil_gaming.jpg', 750.00, 7, 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg', 'activo', '2026-02-02 10:45:34', 4.60, 5, 1, 1499.99, 50),
(2, 3, 'Teclado Mecánico', 'Teclado mecánico RGB', 'teclado_mecanico.jpg', 89.99, 23, 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg', 'activo', '2026-02-02 10:45:34', 4.60, 5, 0, NULL, NULL),
(3, 2, 'Monitor 4K', 'Monitor 27\" 4K UHD', 'monitor.jpg', 374.99, 0, 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg', 'activo', '2026-02-02 10:45:34', 4.80, 5, 1, 499.99, 25),
(4, 3, 'Ratón Inalámbrico', 'Ratón gaming inalámbrico de la marcha logitech ', 'raton_inalambrico.jpg', 30.00, 28, 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg', 'activo', '2026-02-02 10:45:34', 4.00, 4, 1, 59.99, 50),
(6, 7, 'Silla gaming', 'La mejor silla gaming del mercado', 'silla_gaming.jpg', 299.99, 20, 'https://dummyimage.com/450x300/dee2e6/6c757d.jpg', 'activo', '2026-03-02 12:20:05', 0.00, 0, 0, NULL, NULL),
(8, 6, 'Disco Duro NVMe', '1 TB de almacenamiento', 'almacenamiento.jpg', 200.00, 50, NULL, 'activo', '2026-03-09 12:32:14', 0.00, 0, 0, NULL, NULL),
(10, 5, 'Cascos Gaming', 'Auriculares inalámbricos para gaming con RGB', 'cascos_gaming.jpg', 159.99, 148, NULL, 'activo', '2026-03-09 12:35:26', 4.50, 2, 0, NULL, NULL),
(11, 8, 'RAM DDR5 2x32GB', '', 'DDR5_vengance.jpg', 297.50, 89, NULL, 'activo', '2026-03-09 12:36:53', 5.00, 1, 1, 350.00, 15);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('cliente','admin') DEFAULT 'cliente',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT NULL,
  `pais` char(2) DEFAULT 'ES',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_pais` (`pais`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `nombre`, `email`, `password`, `rol`, `fecha_registro`, `fecha_actualizacion`, `pais`) VALUES
(1, 'alejandro lopez', 'alejandro@cliente.es', '$2y$10$H.Cw658BadZVS8MvQbEOse.A9vMIlo6d2frYWxO4qQojNWL5qlZNm', 'admin', '2026-01-26 09:54:07', '2026-02-09 11:32:53', 'ES'),
(2, 'Alejandro Peman ', 'peman@cliente.es', '$2y$10$glTauDXmEZMFs88KXXS0VOLFmUoOJyb.DCU/JPuSvHdyrFz3c3ATm', 'cliente', '2026-01-26 09:57:33', '2026-02-02 11:29:18', 'ES'),
(3, 'Paula Rodriguez', 'paula@cliente.es', '$2y$10$rzdIvyis.lGqLXaiNL2pJOE5Skev5Cef2wIehQiCGYkW5kiwuT8MW', 'cliente', '2026-01-26 12:14:51', '2026-01-26 12:15:19', 'US'),
(6, 'Paula Rodriguez', 'paula2@cliente.es', '$2y$10$iHS49lsLj9Nx.vL.U2KDp.1e0ePoyLPABcstBEfYenhqb34kCTFXe', 'cliente', '2026-01-26 12:34:59', '2026-02-15 11:59:38', 'Es'),
(8, 'pepe martinez', 'pepe@cliente.es', '$2y$10$6stuzBTDmCBdE/YwOksTLOsXFcAJ9fHAIwGfbY5.W08nYu/UIbJX6', 'cliente', '2026-02-15 11:58:01', '2026-02-16 11:56:38', 'ES'),
(9, 'bruno', 'bruno.miguel@salesianoslosboscos.com', '$2y$10$amTDMoEi8DzWc0H8gRzKEOYkmiA8.S7u.uiTgJVfRu8X8ahDc1CjG', 'cliente', '2026-03-09 11:33:59', NULL, 'ES');

--
-- Disparadores `users`
--
DROP TRIGGER IF EXISTS `after_user_delete`;
DELIMITER $$
CREATE TRIGGER `after_user_delete` AFTER DELETE ON `users` FOR EACH ROW BEGIN
    UPDATE paises 
    SET total_usuarios = total_usuarios - 1 
    WHERE codigo = OLD.pais;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `after_user_insert`;
DELIMITER $$
CREATE TRIGGER `after_user_insert` AFTER INSERT ON `users` FOR EACH ROW BEGIN
    UPDATE paises 
    SET total_usuarios = total_usuarios + 1 
    WHERE codigo = NEW.pais;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `after_user_update_pais`;
DELIMITER $$
CREATE TRIGGER `after_user_update_pais` AFTER UPDATE ON `users` FOR EACH ROW BEGIN
    IF OLD.pais != NEW.pais THEN
        -- Restar del país anterior
        UPDATE paises 
        SET total_usuarios = total_usuarios - 1 
        WHERE codigo = OLD.pais;
        
        -- Sumar al nuevo país
        UPDATE paises 
        SET total_usuarios = total_usuarios + 1 
        WHERE codigo = NEW.pais;
    END IF;
END
$$
DELIMITER ;
DROP TRIGGER IF EXISTS `before_user_update`;
DELIMITER $$
CREATE TRIGGER `before_user_update` BEFORE UPDATE ON `users` FOR EACH ROW BEGIN
    SET NEW.fecha_actualizacion = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `valoraciones`
--

DROP TABLE IF EXISTS `valoraciones`;
CREATE TABLE IF NOT EXISTS `valoraciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `puntuacion` int(11) NOT NULL CHECK (`puntuacion` >= 1 and `puntuacion` <= 5),
  `comentario` text DEFAULT NULL,
  `fecha` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_valoracion` (`user_id`,`product_id`,`order_id`),
  KEY `user_id` (`user_id`),
  KEY `product_id` (`product_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `valoraciones`
--

INSERT INTO `valoraciones` (`id`, `user_id`, `product_id`, `order_id`, `puntuacion`, `comentario`, `fecha`) VALUES
(1, 1, 1, 14, 5, 'Excelente portátil, corre todos los juegos sin problemas', '2026-02-07 11:57:34'),
(2, 2, 1, 18, 5, 'Muy contento con la compra, relación calidad-precio increíble', '2026-02-12 11:57:34'),
(3, 3, 1, 20, 4, 'Buen rendimiento, pero la batería dura poco', '2026-02-17 11:57:34'),
(4, 6, 1, 22, 5, 'RTX 4060 impresionante, muy recomendable', '2026-02-22 11:57:34'),
(5, 8, 1, 24, 4, 'Buen equipo, pero el ventilador hace algo de ruido', '2026-02-27 11:57:34'),
(6, 1, 2, 18, 5, 'El mejor teclado que he tenido, switches muy suaves', '2026-02-09 11:57:34'),
(7, 2, 2, 20, 5, 'RGB espectacular y construcción sólida', '2026-02-15 11:57:34'),
(8, 3, 2, 22, 4, 'Buen teclado, pero un poco ruidoso', '2026-02-19 11:57:34'),
(9, 6, 2, 24, 4, 'Buena relación calidad-precio', '2026-02-25 11:57:34'),
(10, 8, 2, 26, 5, 'Perfecto para gaming y programación', '2026-03-04 11:57:34'),
(11, 1, 3, 14, 5, 'Imagen increíble, colores muy vivos', '2026-02-08 11:57:34'),
(12, 2, 3, 18, 5, 'Perfecto para edición de video', '2026-02-13 11:57:34'),
(13, 3, 3, 20, 5, 'La mejor compra que he hecho', '2026-02-18 11:57:34'),
(14, 6, 3, 22, 5, '4K espectacular, HDR impresionante', '2026-02-23 11:57:34'),
(15, 8, 3, 24, 4, 'Buen monitor, pero el soporte es regular', '2026-03-02 11:57:34'),
(16, 1, 4, 26, 5, 'Muy cómodo y la batería dura muchísimo', '2026-02-16 11:57:34'),
(17, 2, 4, 28, 4, 'Buen ratón, pero los botones laterales son un poco duros', '2026-02-21 11:57:34'),
(18, 3, 4, 31, 4, 'Buena conectividad y precisión', '2026-02-28 11:57:34'),
(19, 1, 4, 35, 3, '', '2026-03-09 12:18:20'),
(20, 1, 11, 37, 5, '', '2026-03-09 12:37:23'),
(21, 1, 10, 39, 5, '', '2026-03-09 12:47:24'),
(22, 1, 10, 41, 4, '', '2026-03-09 12:55:43');

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_metodo_pago` FOREIGN KEY (`metodo_pago_id`) REFERENCES `metodos_pago` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `paises`
--
ALTER TABLE `paises`
  ADD CONSTRAINT `fk_paises_moneda` FOREIGN KEY (`moneda_codigo`) REFERENCES `monedas` (`codigo`);

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_pais` FOREIGN KEY (`pais`) REFERENCES `paises` (`codigo`) ON DELETE SET NULL;

--
-- Filtros para la tabla `valoraciones`
--
ALTER TABLE `valoraciones`
  ADD CONSTRAINT `valoraciones_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `valoraciones_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `valoraciones_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
