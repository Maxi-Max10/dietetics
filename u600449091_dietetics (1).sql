-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 11-03-2026 a las 14:37:53
-- Versión del servidor: 11.8.3-MariaDB-log
-- Versión de PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u600449091_dietetics`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `catalog_products`
--

CREATE TABLE `catalog_products` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `unit` varchar(24) DEFAULT NULL,
  `price_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `currency` char(3) NOT NULL DEFAULT 'ARS',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `catalog_products`
--

INSERT INTO `catalog_products` (`id`, `created_by`, `name`, `description`, `image_path`, `unit`, `price_cents`, `currency`, `updated_at`, `created_at`) VALUES
(18, 1, 'Avena Instantánea (a granel)', NULL, '6b3c45b05c7396912f4785a7cf06b8db.png', 'kg', 500000, 'ARS', '2026-03-02 22:35:11', '2026-01-22 21:19:19'),
(19, 1, 'Avena Tradicional/ Clasica (a granel)', NULL, '043c1114c95ca1e68d2f6e71657f7856.png', 'kg', 500000, 'ARS', '2026-03-02 22:32:48', '2026-01-22 21:19:34'),
(20, 1, 'Salvado de Avena (a granel)', NULL, '447765be07b8c042013d27de119a917e.jpg', 'kg', 500000, 'ARS', '2026-03-02 22:39:24', '2026-01-22 21:20:06'),
(21, 1, 'Harina de Avena (a granel)', NULL, 'f511d1d19bd0c8b70fab97ff4873b586.png', 'kg', 500000, 'ARS', '2026-03-02 14:54:55', '2026-01-22 21:24:48'),
(22, 1, 'Coco Rallado (a granel)', NULL, 'a29ac9d688cbdcbc8a328c1b61223d8b.jpg', 'kg', 2000000, 'ARS', '2026-03-02 14:27:22', '2026-01-22 21:25:19'),
(23, 1, 'coco en Escamas (a granel)', NULL, '0c48cea531c3695f454d53f749ebe561.png', 'kg', 4000000, 'ARS', '2026-03-02 14:23:58', '2026-01-22 21:25:38'),
(24, 1, 'Harina de Coco (a granel)', NULL, '3d0fc667cdf05029d8dc05986eb3faf0.png', 'kg', 1800000, 'ARS', '2026-03-02 14:29:12', '2026-01-22 21:25:59'),
(26, 1, 'Arroz Yamani (a granel)', NULL, 'e94f02e0a7422b1f4ed62e157cf1a49f.png', 'kg', 500000, 'ARS', '2026-03-02 13:44:11', '2026-01-22 21:27:00'),
(27, 1, 'canela en rama', NULL, NULL, 'kg', 18000000, 'ARS', '2026-01-22 21:27:47', '2026-01-22 21:27:47'),
(28, 1, 'canela molida', NULL, NULL, 'kg', 2500000, 'ARS', '2026-01-22 21:28:06', '2026-01-22 21:28:06'),
(29, 1, 'alga nori x6', NULL, NULL, 'un', 1400000, 'ARS', '2026-01-23 22:41:36', '2026-01-23 22:41:36'),
(30, 1, 'Kefir', NULL, NULL, 'un', 680000, 'ARS', '2026-01-26 14:38:35', '2026-01-26 14:38:35'),
(31, 1, 'Vinagre de sidra x500ml', NULL, NULL, 'un', 950000, 'ARS', '2026-01-26 14:40:11', '2026-01-26 14:40:11'),
(32, 1, 'Vinagre de sidra x250ml', NULL, NULL, 'un', 700000, 'ARS', '2026-01-26 14:40:37', '2026-01-26 14:40:37'),
(33, 1, 'aceite de oliva x1L', NULL, NULL, 'un', 2000000, 'ARS', '2026-01-26 14:45:48', '2026-01-26 14:45:48'),
(34, 1, '\"1917\" Aceite de oliva virgen extra x500ml', NULL, '6e64baa3b9093273022a765082f02694.png', 'un', 1400000, 'ARS', '2026-02-27 23:48:53', '2026-01-26 14:46:23'),
(36, 1, 'Harina de almendra 70%', NULL, '2c07fe4749103ba74262db09baf20c5e.jpg', 'kg', 1500000, 'ARS', '2026-03-02 14:40:35', '2026-01-26 14:54:46'),
(37, 1, 'quinoa pop', NULL, NULL, 'kg', 1500000, 'ARS', '2026-01-26 14:55:44', '2026-01-26 14:55:44'),
(38, 1, 'semilla de chia', NULL, NULL, 'kg', 900000, 'ARS', '2026-01-29 00:32:44', '2026-01-26 22:19:08'),
(39, 1, 'harina integral', NULL, NULL, 'kg', 400000, 'ARS', '2026-01-28 21:29:58', '2026-01-28 21:29:58'),
(40, 1, 'cacao amargo', NULL, NULL, 'kg', 2500000, 'ARS', '2026-01-28 21:30:31', '2026-01-28 21:30:31'),
(41, 1, 'discos de arroz', NULL, NULL, 'un', 180000, 'ARS', '2026-01-28 21:31:05', '2026-01-28 21:31:05'),
(42, 1, 'semilla de lino', NULL, NULL, 'kg', 800000, 'ARS', '2026-01-28 21:31:39', '2026-01-28 21:31:39'),
(43, 1, 'semilla de girasol', NULL, NULL, 'kg', 600000, 'ARS', '2026-01-28 21:33:43', '2026-01-28 21:33:43'),
(44, 1, 'semilla de zapallo', NULL, NULL, 'kg', 2500000, 'ARS', '2026-01-28 21:34:12', '2026-01-28 21:34:12'),
(45, 1, 'pasta de mani \"De uco\"', NULL, NULL, 'un', 500000, 'ARS', '2026-01-28 21:34:43', '2026-01-28 21:34:43'),
(46, 1, '\"Entrenuts\" Aceite de coco neutro 200cc', NULL, '6a4c50796e8e60345fd5afe7b070a0a1.jpg', 'un', 700000, 'ARS', '2026-02-26 01:42:01', '2026-01-28 21:35:14'),
(47, 1, 'aceite de coco neutro 300cc', NULL, NULL, 'un', 1000000, 'ARS', '2026-01-28 21:35:41', '2026-01-28 21:35:41'),
(48, 1, '\"Entrenuts\" Aceite de coco virgen 200cc', NULL, '594e875bc4fd740dbca56ce0a12b3cd0.jpg', 'un', 1200000, 'ARS', '2026-02-26 01:34:46', '2026-01-28 21:36:45'),
(49, 1, '\"Entrenuts\" Aceite de coco virgen 360cc', NULL, '037009398d88ef0e03db49ababba63c3.jpg', 'un', 1800000, 'ARS', '2026-02-26 01:41:02', '2026-01-28 21:37:19'),
(50, 1, 'gotas andino', NULL, NULL, 'un', 700000, 'ARS', '2026-01-29 23:34:52', '2026-01-28 21:38:35'),
(51, 1, 'Harina de Arroz (a granel)', NULL, '618f989e45e3a041031694f49296982a.png', 'kg', 500000, 'ARS', '2026-03-02 14:34:31', '2026-01-28 21:41:24'),
(52, 1, '\"Dicomere\" Harina de Almendras s/TACC 200gr', NULL, '269d4a7775e965cc0231eda3d2ecff1a.png', 'un', 1000000, 'ARS', '2026-02-25 16:38:11', '2026-01-29 21:14:32'),
(53, 1, 'Polenta \"dicomere\"', NULL, NULL, 'un', 300000, 'ARS', '2026-01-29 21:16:01', '2026-01-29 21:16:01'),
(54, 1, '\"Dicomere\" Harina de Sorgo blanco integral s/TACC- 450gr', NULL, 'a6e9abfd04c1394ad39546d2107ba514.png', 'un', 550000, 'ARS', '2026-03-02 15:58:16', '2026-01-29 21:16:55'),
(57, 1, '\"Dicomere\" Harina de Quinoa s/TACC- 200gr', NULL, 'd225a15eb64d3f1aa444460221aa3b92.png', 'un', 800000, 'ARS', '2026-03-02 15:01:59', '2026-01-29 21:18:09'),
(58, 1, '\"Dicoreme\" Harina de coco s/TACC 200gr', NULL, '9e872f5fe122d47124032b7ffb0d750c.jpg', 'un', 650000, 'ARS', '2026-02-26 02:32:33', '2026-01-29 21:18:44'),
(59, 1, '\"Dicoreme\" Leche de coco en polvo clasica (vegana) s/TACC 150gr', NULL, 'c3b75cf3026007db0024a5817bdb6439.jpg', 'un', 1200000, 'ARS', '2026-02-26 02:35:23', '2026-01-29 21:19:21'),
(60, 1, 'Alfajor dulce de leche s/a \"dicomere\"', NULL, NULL, 'un', 250000, 'ARS', '2026-01-29 21:20:15', '2026-01-29 21:20:15'),
(61, 1, 'Galleta de arroz bañada dulce \"dicomere\"', NULL, NULL, 'un', 450000, 'ARS', '2026-01-29 21:20:58', '2026-01-29 21:20:58'),
(64, 1, 'Bicarbonato de sodio \"dicomere\"', NULL, NULL, 'un', 350000, 'ARS', '2026-01-29 21:23:06', '2026-01-29 21:23:06'),
(65, 1, 'Cacao amargo \"dicomere\"', NULL, NULL, 'un', 800000, 'ARS', '2026-01-29 21:23:38', '2026-01-29 21:23:38'),
(66, 1, '\"Dicomere\" Texturizado de Arveja Amarilla- 350gr', NULL, '3d9ad687b4be8c16bb69e10d7581dd14.png', 'un', 450000, 'ARS', '2026-02-28 22:42:09', '2026-01-29 21:24:04'),
(67, 1, 'Texturizado de soja \"dicomere\"', NULL, NULL, 'un', 400000, 'ARS', '2026-01-29 21:24:25', '2026-01-29 21:24:25'),
(68, 1, '\"Dicomere\" Golden Syrup/ Jarabe de Caña  s/TACC - 300gr', 'vegana', 'e57429a50e394cb31f56b101507cf82b.png', 'un', 600000, 'ARS', '2026-02-28 23:32:52', '2026-01-29 21:25:13'),
(69, 1, 'Premezcla c/psyllium \"decomere\"', NULL, NULL, 'un', 400000, 'ARS', '2026-01-29 21:25:54', '2026-01-29 21:25:54'),
(70, 1, '\"Dicomere\" Avena gruesa', NULL, '9e172e0070df4db70decf01b4c1dd3bf.webp', 'un', 500000, 'ARS', '2026-02-26 01:59:32', '2026-01-29 21:26:16'),
(71, 1, '\"Dicomere\" Harina de avena s/TACC- 350gr', NULL, 'bbfdec3cd35c5e8337f52f7f8fb5de8c.png', 'un', 500000, 'ARS', '2026-03-02 22:38:17', '2026-01-29 21:26:38'),
(73, 1, 'Pre mezcla protéica \"dicomere\"', NULL, NULL, 'un', 600000, 'ARS', '2026-01-29 21:27:44', '2026-01-29 21:27:44'),
(74, 1, 'Polvo de hornear \"dicomere\"', NULL, NULL, 'un', 400000, 'ARS', '2026-01-29 21:28:30', '2026-01-29 21:28:30'),
(75, 1, '\"Dicomere\" Garbanzo texturizado 350gr', NULL, '42e2f72721f9c7b139ce5de57eeb06d1.webp', 'un', 450000, 'ARS', '2026-02-26 02:08:05', '2026-01-29 21:29:00'),
(76, 1, 'Stevia \"jual\" x200cc', NULL, NULL, 'un', 800000, 'ARS', '2026-01-29 23:40:33', '2026-01-29 21:30:19'),
(77, 1, 'Stevia \"jual\" x500cc', NULL, NULL, 'un', 1500000, 'ARS', '2026-01-29 23:41:21', '2026-01-29 21:30:47'),
(78, 1, 'Stevia en polvo \"jual\" x90gr', NULL, NULL, 'un', 900000, 'ARS', '2026-01-29 23:41:44', '2026-01-29 21:31:38'),
(79, 1, 'Jugo de aloe vera \"jual\" x250ml', NULL, NULL, 'un', 950000, 'ARS', '2026-01-29 23:40:45', '2026-01-29 21:32:19'),
(80, 1, 'Mermelada s/a \"natural gourmet\" x400gr', NULL, NULL, 'un', 600000, 'ARS', '2026-01-29 21:33:58', '2026-01-29 21:33:58'),
(81, 1, 'Miel x250gr', NULL, NULL, 'un', 600000, 'ARS', '2026-01-29 21:34:33', '2026-01-29 21:34:33'),
(82, 1, 'Miel x500gr', NULL, NULL, 'un', 900000, 'ARS', '2026-01-29 21:34:48', '2026-01-29 21:34:48'),
(83, 1, 'Miel x1kg', NULL, NULL, 'un', 1200000, 'ARS', '2026-01-29 21:35:00', '2026-01-29 21:35:00'),
(84, 1, 'Bombón frutal surtido', NULL, NULL, 'un', 120000, 'ARS', '2026-01-29 21:35:38', '2026-01-29 21:35:38'),
(85, 1, 'Gotas \"El naturalista\" cardo mariano', NULL, NULL, 'un', 900000, 'ARS', '2026-01-29 23:38:18', '2026-01-29 21:37:34'),
(86, 1, 'Gotas \"El naturalista\" valeriana', NULL, NULL, 'un', 800000, 'ARS', '2026-01-29 23:35:17', '2026-01-29 21:37:57'),
(87, 1, '\"El naturalista\" Gotas Melisa- 60c.c', 'Tintura Madre', '5373eac10689b50ef45ab439a04cc5b2.jpg', 'un', 700000, 'ARS', '2026-03-05 00:20:24', '2026-01-29 21:38:10'),
(88, 1, '\"El Naturalista\" Gotas carqueja- 60c.c', 'Tintura Madre', 'fb1a6e99d1205fb0d6bb1beda60adb7b.jpg', 'un', 700000, 'ARS', '2026-03-05 00:05:06', '2026-01-29 21:38:32'),
(89, 1, '\"El Naturalista\" Gotas Valeriana- 60c.c', 'Tintura Madre', '5c8a5a4f898984c8be9b72e1cc81cf1c.jpg', 'un', 800000, 'ARS', '2026-03-05 00:24:44', '2026-01-29 21:38:52'),
(90, 1, '\"El naturalista\" Gotas Rompe Piedra- 60c.c', 'Tintura Madre', 'b4be2918a868f955008040384c359a1d.jpg', 'un', 700000, 'ARS', '2026-03-05 00:22:07', '2026-01-29 21:41:21'),
(91, 1, '\"El Naturalista\" Gotas Ambay- 60c.c', 'Tintura Madre', '82dbf944042287e1354a05f5c1093193.jpg', 'un', 700000, 'ARS', '2026-03-04 21:41:16', '2026-01-29 21:41:42'),
(92, 1, '\"El Naturalista\" Gotas Ajo- 60c.c', 'Tintura Madre', '69e2324fbac10b9f365798351e950962.jpg', 'un', 700000, 'ARS', '2026-03-04 21:38:35', '2026-01-29 21:42:03'),
(93, 1, '\"El Naturalista\" Gotas Harpagofito- 60c.c', 'Tintura Madre', '752c59d4b35752bdf1d2819e3695cb32.jpg', 'un', 800000, 'ARS', '2026-03-05 00:18:26', '2026-01-29 21:42:33'),
(94, 1, '\"El Naturalista\" Gotas Uña de Gato- 60c.c', 'Tintura Madre', 'a821a78f0b8cb3da0c1d62c6984bdbb9.jpg', 'un', 700000, 'ARS', '2026-03-05 00:17:30', '2026-01-29 21:42:48'),
(96, 1, '\"El Naturalista\" Gotas Manzanilla- 60c.c', 'Tintura Madre', '6c4d0ebbde4883c79a640a941087136f.jpg', 'un', 700000, 'ARS', '2026-03-05 00:09:18', '2026-01-29 21:43:22'),
(97, 1, '\"El Naturalista\" Gotas Castaño de indias-60c.c', 'Tintura Madre', '30883ae94bc9c6cd3ddf300123a14373.jpg', 'un', 700000, 'ARS', '2026-03-05 00:01:49', '2026-01-29 21:43:52'),
(98, 1, '\"El naturalista\" Gotas Ginko Biloba-60c.c', 'Tintura Madre', 'a276db3774481786f497a120da3061f6.jpg', 'un', 700000, 'ARS', '2026-03-05 00:03:21', '2026-01-29 21:44:50'),
(99, 1, '\"El Naturalista\" Gotas Mil Hombres- 60c.c', 'Tintura Madre', 'd41f789cf0ac66aca09ef47894b56dac.jpg', 'un', 700000, 'ARS', '2026-03-05 00:13:17', '2026-01-29 21:45:14'),
(100, 1, '\"El Naturalista\" Gotas Arándano-60c.c', 'Tintura Madre', '40b372edeccd90297ab8e6779582d513.jpg', 'un', 700000, 'ARS', '2026-03-04 21:36:50', '2026-01-29 21:45:41'),
(101, 1, '\"El Naturalista\" Gotas Nencia- 60c.c', 'Tintura Madre', 'da7eeefbac32e56e0f65765aeb8a9f10.jpg', 'un', 700000, 'ARS', '2026-03-05 00:11:30', '2026-01-29 21:46:04'),
(102, 1, 'Caldo el naturalista', NULL, NULL, 'un', 450000, 'ARS', '2026-02-02 22:16:50', '2026-02-02 22:16:50'),
(103, 1, 'Yerba Organica', NULL, NULL, 'kg', 1000000, 'ARS', '2026-02-02 22:17:08', '2026-02-02 22:17:08'),
(104, 1, 'Creatina Mole', NULL, NULL, 'un', 4000000, 'ARS', '2026-02-02 22:17:28', '2026-02-02 22:17:28'),
(107, 1, 'Salsa de soja', NULL, NULL, 'un', 600000, 'ARS', '2026-02-02 22:19:26', '2026-02-02 22:19:26'),
(108, 1, 'Te de burro', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-02 22:19:54', '2026-02-02 22:19:54'),
(109, 1, 'Melisa', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-02 22:20:06', '2026-02-02 22:20:06'),
(110, 1, 'Ajenjo', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-02 23:16:27', '2026-02-02 23:16:27'),
(111, 1, 'Amargón', NULL, NULL, 'kg', 1600000, 'ARS', '2026-02-02 23:23:26', '2026-02-02 23:23:26'),
(112, 1, 'Alfalfa', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-02 23:23:43', '2026-02-02 23:23:43'),
(113, 1, 'Ambay', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-02 23:24:00', '2026-02-02 23:24:00'),
(124, 1, 'Anis Estrellado', NULL, NULL, 'kg', 10000000, 'ARS', '2026-02-02 23:41:10', '2026-02-02 23:41:10'),
(125, 1, 'Clavo de olor', NULL, NULL, 'kg', 8000000, 'ARS', '2026-02-02 23:41:29', '2026-02-02 23:41:29'),
(126, 1, 'Boldo', NULL, NULL, 'kg', 3000000, 'ARS', '2026-02-02 23:42:15', '2026-02-02 23:42:15'),
(127, 1, 'Carqueja', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-02 23:42:45', '2026-02-02 23:42:45'),
(128, 1, 'Cola de caballo', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-02 23:42:57', '2026-02-02 23:42:57'),
(129, 1, 'Cuasia amarga', NULL, NULL, 'kg', 1500000, 'ARS', '2026-02-02 23:43:19', '2026-02-02 23:43:19'),
(130, 1, 'Centella', NULL, NULL, 'kg', 1800000, 'ARS', '2026-02-02 23:43:46', '2026-02-02 23:43:46'),
(133, 1, 'cedron', NULL, NULL, 'kg', 4000000, 'ARS', '2026-02-02 23:44:52', '2026-02-02 23:44:52'),
(134, 1, 'doradilla', NULL, NULL, 'kg', 1800000, 'ARS', '2026-02-02 23:46:04', '2026-02-02 23:46:04'),
(135, 1, 'Estregon', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-02 23:46:34', '2026-02-02 23:46:34'),
(136, 1, 'Enebro', NULL, NULL, 'kg', 2500000, 'ARS', '2026-02-02 23:46:55', '2026-02-02 23:46:55'),
(137, 1, 'Fucus', NULL, NULL, 'kg', 9000000, 'ARS', '2026-02-02 23:47:09', '2026-02-02 23:47:09'),
(138, 1, 'Hisopo', NULL, NULL, 'kg', 1500000, 'ARS', '2026-02-02 23:47:28', '2026-02-02 23:47:28'),
(139, 1, 'GInkgo biloba', NULL, NULL, 'kg', 2800000, 'ARS', '2026-02-02 23:47:47', '2026-02-02 23:47:47'),
(140, 1, 'Uva ursi', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-02 23:48:21', '2026-02-02 23:48:21'),
(141, 1, 'Ortiga', NULL, NULL, 'kg', 1800000, 'ARS', '2026-02-02 23:48:44', '2026-02-02 23:48:44'),
(142, 1, 'Hibiscus', NULL, NULL, 'kg', 6000000, 'ARS', '2026-02-02 23:48:56', '2026-02-02 23:48:56'),
(143, 1, 'Lapacho', NULL, NULL, 'kg', 1500000, 'ARS', '2026-02-02 23:49:12', '2026-02-02 23:49:12'),
(144, 1, 'Menta', NULL, NULL, 'kg', 1800000, 'ARS', '2026-02-02 23:49:37', '2026-02-02 23:49:37'),
(145, 1, 'Muerdago', NULL, NULL, NULL, 1200000, 'ARS', '2026-02-02 23:49:54', '2026-02-02 23:49:54'),
(147, 1, 'Marrubio', NULL, NULL, 'kg', 1600000, 'ARS', '2026-02-02 23:50:36', '2026-02-02 23:50:36'),
(148, 1, 'Marcela', NULL, NULL, 'kg', 1200000, 'ARS', '2026-02-02 23:50:54', '2026-02-02 23:50:54'),
(149, 1, 'Malva', NULL, NULL, 'kg', 1800000, 'ARS', '2026-02-02 23:51:07', '2026-02-02 23:51:07'),
(150, 1, 'Matico', NULL, NULL, 'kg', 1300000, 'ARS', '2026-02-02 23:51:16', '2026-02-02 23:51:16'),
(151, 1, 'Muña', NULL, NULL, 'kg', 1500000, 'ARS', '2026-02-02 23:51:26', '2026-02-02 23:51:26'),
(152, 1, 'Moringa', NULL, NULL, 'kg', 2500000, 'ARS', '2026-02-02 23:51:39', '2026-02-02 23:51:39'),
(153, 1, 'Peperina', NULL, NULL, 'kg', 1800000, 'ARS', '2026-02-02 23:51:49', '2026-02-02 23:51:49'),
(154, 1, 'Stevia hoja', NULL, NULL, 'kg', 5000000, 'ARS', '2026-02-02 23:52:00', '2026-02-02 23:52:00'),
(155, 1, 'Pajaro bobo', NULL, NULL, 'kg', 1800000, 'ARS', '2026-02-02 23:52:34', '2026-02-02 23:52:34'),
(156, 1, 'Llanten', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-02 23:53:05', '2026-02-02 23:53:05'),
(157, 1, 'Sen hojas', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-02 23:53:18', '2026-02-02 23:53:18'),
(158, 1, 'Pezuña de vaca', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-02 23:54:04', '2026-02-02 23:54:04'),
(159, 1, 'Rompe piedra', NULL, NULL, 'kg', 1500000, 'ARS', '2026-02-02 23:54:21', '2026-02-02 23:54:21'),
(160, 1, 'Salvia blanca', NULL, NULL, 'kg', 1800000, 'ARS', '2026-02-02 23:54:35', '2026-02-02 23:54:35'),
(161, 1, 'Pulmonaria', NULL, NULL, 'kg', 1500000, 'ARS', '2026-02-02 23:54:47', '2026-02-02 23:54:47'),
(162, 1, 'Paico', NULL, NULL, 'kg', 1500000, 'ARS', '2026-02-02 23:54:56', '2026-02-02 23:54:56'),
(163, 1, 'Yerba meona/ Arenaria', NULL, NULL, 'kg', 1800000, 'ARS', '2026-02-02 23:55:18', '2026-02-02 23:55:18'),
(164, 1, 'Lavanda', NULL, NULL, 'kg', 8000000, 'ARS', '2026-02-02 23:55:34', '2026-02-02 23:55:34'),
(165, 1, 'Cremor tartaro', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-02 23:56:39', '2026-02-02 23:56:39'),
(166, 1, 'Bicarbonato', NULL, NULL, 'kg', 1000000, 'ARS', '2026-02-02 23:57:16', '2026-02-02 23:57:16'),
(167, 1, 'Polvo de hornear', NULL, NULL, 'kg', 1200000, 'ARS', '2026-02-02 23:58:11', '2026-02-02 23:58:11'),
(168, 1, 'Azúcar impalpable', NULL, NULL, 'kg', 600000, 'ARS', '2026-02-03 21:53:26', '2026-02-02 23:58:47'),
(170, 1, 'Arroz  de Sushi (a granel)', NULL, '2cb9250dc532a762e2723207c150e0c7.jpg', 'kg', 500000, 'ARS', '2026-03-02 13:32:53', '2026-02-03 00:10:56'),
(171, 1, 'Nuez moscada', NULL, NULL, 'kg', 2800000, 'ARS', '2026-02-03 00:11:57', '2026-02-03 00:11:57'),
(172, 1, 'Te negro', NULL, NULL, 'kg', 1000000, 'ARS', '2026-02-03 21:50:56', '2026-02-03 00:12:22'),
(176, 1, 'Azucar de mascabo', NULL, NULL, 'kg', 800000, 'ARS', '2026-02-03 00:15:40', '2026-02-03 00:15:40'),
(177, 1, 'Arroz Carnaroli (a granel)', NULL, 'a503ca915dd0c4417c0c441ce46cc61a.png', 'kg', 800000, 'ARS', '2026-03-02 13:36:27', '2026-02-03 21:48:06'),
(178, 1, 'Nuez moscada moida', NULL, NULL, 'kg', 2800000, 'ARS', '2026-02-03 21:48:39', '2026-02-03 21:48:39'),
(179, 1, 'Te Rojo', NULL, NULL, 'kg', 1000000, 'ARS', '2026-02-03 21:49:10', '2026-02-03 21:49:10'),
(185, 1, 'bicarbonato de sodio', NULL, NULL, 'kg', 1000000, 'ARS', '2026-02-03 21:51:52', '2026-02-03 21:51:52'),
(188, 1, 'mani con sal', NULL, NULL, 'kg', 950000, 'ARS', '2026-02-03 21:54:32', '2026-02-03 21:54:32'),
(189, 1, 'mani sin sal', NULL, NULL, 'kg', 950000, 'ARS', '2026-02-03 21:54:56', '2026-02-03 21:54:41'),
(190, 1, 'mani con cascara', NULL, NULL, 'kg', 650000, 'ARS', '2026-02-03 21:55:16', '2026-02-03 21:55:16'),
(191, 1, 'chips de banana', NULL, NULL, 'kg', 2200000, 'ARS', '2026-02-03 21:55:31', '2026-02-03 21:55:31'),
(192, 1, 'mix tropical', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-03 21:55:48', '2026-02-03 21:55:48'),
(193, 1, 'mix seco', NULL, NULL, 'kg', 3000000, 'ARS', '2026-02-03 21:56:00', '2026-02-03 21:56:00'),
(194, 1, 'mix premium', NULL, NULL, 'kg', 2500000, 'ARS', '2026-02-03 21:56:21', '2026-02-03 21:56:21'),
(195, 1, 'mix Fran', NULL, NULL, 'kg', 2500000, 'ARS', '2026-02-03 21:56:36', '2026-02-03 21:56:36'),
(196, 1, 'mix cervecero', NULL, NULL, 'kg', 2800000, 'ARS', '2026-02-03 21:57:28', '2026-02-03 21:57:28'),
(197, 1, 'mani japones', NULL, NULL, 'kg', 800000, 'ARS', '2026-02-03 21:58:07', '2026-02-03 21:58:07'),
(198, 1, 'castañas de caju', NULL, NULL, 'kg', 3200000, 'ARS', '2026-02-03 21:58:30', '2026-02-03 21:58:30'),
(199, 1, 'arandanos', NULL, NULL, 'kg', 2200000, 'ARS', '2026-02-03 21:58:40', '2026-02-03 21:58:40'),
(200, 1, 'nueces', NULL, NULL, 'kg', 2800000, 'ARS', '2026-02-03 21:58:51', '2026-02-03 21:58:51'),
(201, 1, 'almendras', NULL, NULL, 'kg', 2800000, 'ARS', '2026-02-03 21:59:02', '2026-02-03 21:59:02'),
(202, 1, 'Avellanas (a granel)', NULL, 'ce1149fefd096f3f2fa975172c86a129.jpg', 'kg', 4000000, 'ARS', '2026-03-02 16:47:10', '2026-02-03 21:59:11'),
(203, 1, 'pistachos', NULL, NULL, 'kg', 5000000, 'ARS', '2026-02-03 21:59:24', '2026-02-03 21:59:24'),
(204, 1, 'tomates secos', NULL, NULL, 'kg', 2700000, 'ARS', '2026-02-03 21:59:38', '2026-02-03 21:59:38'),
(205, 1, 'hongos de pino', NULL, NULL, 'kg', 4000000, 'ARS', '2026-02-03 21:59:50', '2026-02-03 21:59:50'),
(206, 1, 'bayas de goyi', NULL, NULL, 'kg', 3000000, 'ARS', '2026-02-03 22:00:03', '2026-02-03 22:00:03'),
(207, 1, 'ciruelas secas', NULL, NULL, 'kg', 1400000, 'ARS', '2026-02-03 22:00:18', '2026-02-03 22:00:18'),
(208, 1, 'pasas morochas', NULL, NULL, 'kg', 1000000, 'ARS', '2026-02-03 22:00:30', '2026-02-03 22:00:30'),
(209, 1, 'pasas rosadas', NULL, NULL, 'kg', 1400000, 'ARS', '2026-02-03 22:00:59', '2026-02-03 22:00:59'),
(210, 1, 'pasa rubias', NULL, NULL, 'kg', 1400000, 'ARS', '2026-02-03 22:01:09', '2026-02-03 22:01:09'),
(211, 1, 'datiles', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-03 22:01:20', '2026-02-03 22:01:20'),
(212, 1, 'harina de arroz integral', NULL, NULL, 'kg', 600000, 'ARS', '2026-02-03 22:01:58', '2026-02-03 22:01:58'),
(213, 1, 'harina de integral', NULL, NULL, 'kg', 400000, 'ARS', '2026-02-03 22:02:18', '2026-02-03 22:02:18'),
(214, 1, '\"Dicomere\" Harina de garbanzo organica s/TACC 450gr', NULL, 'b95306f0ce63305df8679d1b62404f95.jpg', 'un', 600000, 'ARS', '2026-02-26 03:17:45', '2026-02-03 22:02:28'),
(215, 1, 'Harina de Maní (a granel)', NULL, '94b3dc65ef92b437a87a3aa4eb09cfb7.png', 'kg', 600000, 'ARS', '2026-03-02 14:32:37', '2026-02-03 22:02:56'),
(216, 1, 'Harina de Centeno (a granel)', NULL, 'ace0b0c01d26744f7c2833114002c55f.png', 'kg', 500000, 'ARS', '2026-03-02 14:59:13', '2026-02-03 22:03:15'),
(217, 1, 'semolin de trigo', NULL, NULL, 'kg', 550000, 'ARS', '2026-02-03 22:03:33', '2026-02-03 22:03:33'),
(218, 1, 'azucar rubia organica', NULL, NULL, 'kg', 700000, 'ARS', '2026-02-03 22:04:05', '2026-02-03 22:04:05'),
(219, 1, 'azucar negra', NULL, NULL, 'kg', 600000, 'ARS', '2026-02-03 22:04:17', '2026-02-03 22:04:17'),
(220, 1, 'semilla de sesamo negro', NULL, NULL, 'kg', 1200000, 'ARS', '2026-02-03 22:04:45', '2026-02-03 22:04:45'),
(221, 1, 'semilla de sesamo blanco', NULL, NULL, 'kg', 1500000, 'ARS', '2026-02-03 22:04:59', '2026-02-03 22:04:59'),
(222, 1, 'semilla de sesamo integral', NULL, NULL, 'kg', 1200000, 'ARS', '2026-02-03 22:05:12', '2026-02-03 22:05:12'),
(223, 1, 'semilla de hinojo', NULL, NULL, 'kg', 3000000, 'ARS', '2026-02-03 22:05:28', '2026-02-03 22:05:28'),
(224, 1, 'semilla de amaranto', NULL, NULL, 'kg', 600000, 'ARS', '2026-02-03 22:05:47', '2026-02-03 22:05:47'),
(225, 1, 'mix semillas clasicas', NULL, NULL, 'kg', 1100000, 'ARS', '2026-02-03 22:06:06', '2026-02-03 22:06:06'),
(226, 1, 'semilla de anis', NULL, NULL, 'kg', 2000000, 'ARS', '2026-02-03 22:06:23', '2026-02-03 22:06:23'),
(227, 1, 'semilla de amapola', NULL, NULL, 'kg', 2500000, 'ARS', '2026-02-03 22:07:06', '2026-02-03 22:07:06'),
(228, 1, 'Jarabe de datil', NULL, NULL, 'kg', 1800000, 'ARS', '2026-02-25 21:25:43', '2026-02-03 22:07:49'),
(229, 1, 'Gluten puro', NULL, NULL, 'kg', 1000000, 'ARS', '2026-02-03 22:45:03', '2026-02-03 22:45:03'),
(230, 1, 'Yogurt', NULL, NULL, 'l', 550000, 'ARS', '2026-02-03 22:46:08', '2026-02-03 22:46:08'),
(231, 1, 'Harina para arepas \"Pan\"', NULL, NULL, 'un', 780000, 'ARS', '2026-02-03 22:52:02', '2026-02-03 22:52:02'),
(234, 1, 'Almohaditas Frutilla (a granel)', NULL, '346f2d6b4057fbd16b917e2ce8b6ff3b.png', 'kg', 1500000, 'ARS', '2026-03-02 13:50:39', '2026-02-03 22:53:26'),
(235, 1, 'aritos de Miel (a granel)', NULL, '2febf4f7b6f9b5cd12d868fb5c402399.jpg', 'kg', 800000, 'ARS', '2026-03-02 16:54:06', '2026-02-03 22:53:46'),
(236, 1, 'bananitas', NULL, NULL, 'kg', 800000, 'ARS', '2026-02-03 22:54:10', '2026-02-03 22:54:10'),
(237, 1, 'cereal con azucar', NULL, NULL, 'kg', 800000, 'ARS', '2026-02-03 22:54:21', '2026-02-03 22:54:21'),
(238, 1, 'cereal sin azucar', NULL, NULL, 'kg', 600000, 'ARS', '2026-02-03 22:54:33', '2026-02-03 22:54:33'),
(239, 1, 'cereal fibra', NULL, NULL, 'kg', 700000, 'ARS', '2026-02-03 22:54:43', '2026-02-03 22:54:43'),
(240, 1, 'Bolitas Chocolate (a granel)', NULL, 'a03cc75f4aeb7f89d94667beae55470c.webp', 'kg', 800000, 'ARS', '2026-02-28 00:06:44', '2026-02-03 22:54:54'),
(241, 1, 'copas de chocolate', NULL, NULL, 'kg', 800000, 'ARS', '2026-02-03 22:55:07', '2026-02-03 22:55:07'),
(242, 1, 'naturitos', NULL, NULL, 'kg', 1400000, 'ARS', '2026-02-03 22:55:17', '2026-02-03 22:55:17'),
(243, 1, 'granola seca', NULL, NULL, 'kg', 1800000, 'ARS', '2026-02-03 22:55:44', '2026-02-03 22:55:44'),
(244, 1, 'granolas', NULL, NULL, 'kg', 1500000, 'ARS', '2026-02-03 22:55:58', '2026-02-03 22:55:58'),
(245, 1, 'Iniciador de yogurt', NULL, NULL, 'un', 600000, 'ARS', '2026-02-03 22:58:11', '2026-02-03 22:58:11'),
(246, 1, 'mate calabaza, base bolitas', NULL, NULL, 'un', 5000000, 'ARS', '2026-02-03 22:59:42', '2026-02-03 22:59:42'),
(247, 1, 'mate calabaza, sin base, cuero', NULL, NULL, 'un', 4000000, 'ARS', '2026-02-03 23:00:18', '2026-02-03 23:00:18'),
(248, 1, 'mate algarrobo, sin base, cuero', NULL, NULL, 'un', 4500000, 'ARS', '2026-02-03 23:01:24', '2026-02-03 23:01:24'),
(249, 1, 'mate de algarrobo panza de loro', NULL, NULL, 'un', 1700000, 'ARS', '2026-02-03 23:02:17', '2026-02-03 23:02:17'),
(250, 1, 'mate de algarrobo redondo, virola de acero', NULL, NULL, 'un', 3000000, 'ARS', '2026-02-03 23:02:40', '2026-02-03 23:02:40'),
(251, 1, 'mate de algarrobo con pie y virola de alpaca', NULL, NULL, 'un', 5000000, 'ARS', '2026-02-03 23:03:06', '2026-02-03 23:03:06'),
(252, 1, 'mates matienzo y bombilla', NULL, NULL, 'un', 5000000, 'ARS', '2026-02-03 23:03:26', '2026-02-03 23:03:26'),
(253, 1, 'Bombilla acero', NULL, NULL, 'un', 1000000, 'ARS', '2026-02-03 23:03:52', '2026-02-03 23:03:52'),
(254, 1, 'Mate ranchero', NULL, NULL, NULL, 2500000, 'ARS', '2026-02-03 23:55:56', '2026-02-03 23:55:56'),
(255, 1, 'premezcla chacabuco', NULL, NULL, 'un', 500000, 'ARS', '2026-03-05 23:02:48', '2026-02-05 13:05:30'),
(256, 1, 'Barrita \"Laddubar\"', NULL, NULL, 'un', 180000, 'ARS', '2026-02-05 13:08:25', '2026-02-05 13:08:25'),
(257, 1, 'Barrita \"Zannas\"', NULL, NULL, 'un', 180000, 'ARS', '2026-02-05 13:08:57', '2026-02-05 13:08:57'),
(258, 1, 'Barrita \"Lyv\" Proteica', NULL, NULL, 'un', 200000, 'ARS', '2026-02-05 13:09:18', '2026-02-05 13:09:18'),
(259, 1, 'Barrita \"Lyv\" Cereal', NULL, NULL, 'un', 180000, 'ARS', '2026-02-05 13:09:41', '2026-02-05 13:09:41'),
(260, 1, 'Barrita \"NotCo\"', NULL, NULL, 'un', 250000, 'ARS', '2026-02-05 13:11:47', '2026-02-05 13:11:47'),
(261, 1, 'Barrita \"Crudda\"', NULL, NULL, 'un', 280000, 'ARS', '2026-02-05 13:12:11', '2026-02-05 13:12:11'),
(262, 1, 'Barrita \"Integra\" Proteica', NULL, NULL, 'un', 240000, 'ARS', '2026-02-05 13:12:35', '2026-02-05 13:12:35'),
(263, 1, 'Barrita \"Mueca\" Proteica', NULL, NULL, 'un', 240000, 'ARS', '2026-02-05 13:13:00', '2026-02-05 13:13:00'),
(264, 1, 'Barrita \"Mueca\" Cereal', NULL, NULL, 'un', 200000, 'ARS', '2026-02-05 13:13:17', '2026-02-05 13:13:17'),
(265, 1, 'Barrita \"Vitalgy\"', NULL, NULL, 'un', 180000, 'ARS', '2026-02-05 13:13:53', '2026-02-05 13:13:53'),
(266, 1, 'Barrita \"integra\" Cereal', NULL, NULL, 'un', 220000, 'ARS', '2026-02-05 13:14:16', '2026-02-05 13:14:16'),
(267, 1, 'Barrita \"wake up\"', NULL, NULL, 'un', 180000, 'ARS', '2026-02-05 13:14:35', '2026-02-05 13:14:35'),
(268, 1, 'Barrita \"Fungi bar\"', NULL, NULL, 'un', 240000, 'ARS', '2026-02-05 13:15:08', '2026-02-05 13:15:08'),
(269, 1, 'Barrita \"Brava\" Cereal', NULL, NULL, 'un', 220000, 'ARS', '2026-02-05 13:15:35', '2026-02-05 13:15:35'),
(270, 1, 'Barrita \"Crowie\" Arroz inflado', NULL, NULL, 'un', 80000, 'ARS', '2026-02-05 13:19:22', '2026-02-05 13:19:22'),
(271, 1, 'Barrita \"Crowie\" Cereal', NULL, NULL, 'un', 180000, 'ARS', '2026-02-05 13:19:50', '2026-02-05 13:19:50'),
(272, 1, 'Barrita \"Lulu lemon\"', NULL, NULL, 'un', 180000, 'ARS', '2026-02-05 13:20:18', '2026-02-05 13:20:18'),
(273, 1, 'Pasta de sesamo \"Alkanater\"', NULL, NULL, 'un', 1800000, 'ARS', '2026-02-05 13:20:57', '2026-02-05 13:20:57'),
(274, 1, 'Citrato de magnesio \"VitaminWay\"', NULL, NULL, 'un', 1600000, 'ARS', '2026-02-05 13:21:56', '2026-02-05 13:21:56'),
(275, 1, 'Zinc \"El naturalista\"', NULL, NULL, 'un', 800000, 'ARS', '2026-02-05 13:47:25', '2026-02-05 13:47:25'),
(276, 1, 'Curcuma \"Incaico\"', NULL, NULL, 'un', 2000000, 'ARS', '2026-02-05 13:49:17', '2026-02-05 13:49:17'),
(277, 1, '\"El Naturalista\" B12 VITAMINAS', NULL, 'abe43cc1d6e0c368b98604a19b96e8ce.png', 'un', 900000, 'ARS', '2026-02-26 01:48:17', '2026-02-05 13:49:42'),
(278, 1, 'Magnesio + Vitamina D \"Incaico\"', NULL, NULL, 'un', 800000, 'ARS', '2026-02-05 13:50:29', '2026-02-05 13:50:29'),
(279, 1, 'Arandano Ury \"VitaminWay\"', NULL, NULL, 'un', 1800000, 'ARS', '2026-02-05 13:51:09', '2026-02-05 13:51:09'),
(280, 1, 'Vinagre de sidra de manzana \"VitaminWay\"', NULL, NULL, 'un', 900000, 'ARS', '2026-02-05 13:51:46', '2026-02-05 13:51:46'),
(281, 1, 'Salubiotics Probioticos', NULL, NULL, 'un', 1800000, 'ARS', '2026-02-05 13:52:14', '2026-02-05 13:52:14'),
(282, 1, 'Vitamina C \"VitaminWay\"', NULL, NULL, 'un', 800000, 'ARS', '2026-02-05 13:52:31', '2026-02-05 13:52:31'),
(283, 1, 'Melena de leon \"VitaminWay\"', NULL, NULL, 'un', 1800000, 'ARS', '2026-02-05 13:52:54', '2026-02-05 13:52:54'),
(284, 1, 'Colageno dia y noche  \"Salutaris\"', NULL, NULL, 'un', 2800000, 'ARS', '2026-02-05 13:53:27', '2026-02-05 13:53:27'),
(285, 1, 'Selenio quelato \"VitaminWay\"', NULL, NULL, 'un', 1400000, 'ARS', '2026-02-05 13:54:02', '2026-02-05 13:54:02'),
(286, 1, 'Rebozador \"granix\"', NULL, NULL, 'un', 300000, 'ARS', '2026-02-05 13:55:34', '2026-02-05 13:55:34'),
(287, 1, '\"SaintGottard\" Te de Frutos del bosque- 20 saquitos', NULL, '4ef998602f208f2461d9e51a2648e4c7.jpg', 'un', 580000, 'ARS', '2026-02-28 21:38:21', '2026-02-05 13:55:51'),
(288, 1, 'Té en hebras importado', NULL, NULL, 'un', 700000, 'ARS', '2026-02-05 13:56:10', '2026-02-05 13:56:10'),
(289, 1, 'Té en hebras nacional sobre', NULL, NULL, 'un', 500000, 'ARS', '2026-02-05 13:56:36', '2026-02-05 13:56:36'),
(290, 1, 'Té en hebras nacional frasco', NULL, NULL, 'un', 600000, 'ARS', '2026-02-05 13:56:48', '2026-02-05 13:56:48'),
(291, 1, 'Hierbas materas sobre', NULL, NULL, 'un', 600000, 'ARS', '2026-02-05 13:57:24', '2026-02-05 13:57:24'),
(292, 1, 'Hierbas materas frasco', NULL, NULL, 'un', 650000, 'ARS', '2026-02-05 13:57:39', '2026-02-05 13:57:39'),
(293, 1, 'Melena de leon Gotas', NULL, NULL, 'un', 1600000, 'ARS', '2026-02-05 13:58:01', '2026-02-05 13:58:01'),
(294, 1, 'Extracto de vainilla 30cc', NULL, NULL, 'un', 1100000, 'ARS', '2026-02-05 13:59:02', '2026-02-05 13:59:02'),
(295, 1, 'Serum facial Baku', NULL, NULL, 'un', 6000000, 'ARS', '2026-02-05 13:59:47', '2026-02-05 13:59:47'),
(296, 1, 'Serumn facial Guava', NULL, NULL, 'un', 6000000, 'ARS', '2026-02-05 14:00:04', '2026-02-05 14:00:04'),
(297, 1, 'Serum Facial Aloe', NULL, NULL, 'un', 6000000, 'ARS', '2026-02-05 14:00:24', '2026-02-05 14:00:24'),
(298, 1, 'Crema facial Liviana Kale', NULL, NULL, 'un', 4500000, 'ARS', '2026-02-05 14:00:55', '2026-02-05 14:00:55'),
(299, 1, 'Serum Facial Nectar', NULL, NULL, 'un', 5000000, 'ARS', '2026-02-05 14:01:14', '2026-02-05 14:01:14'),
(300, 1, 'Bruma Té Verde', NULL, NULL, 'un', 4400000, 'ARS', '2026-02-05 14:01:41', '2026-02-05 14:01:41'),
(301, 1, 'Crema Corporal Goji', NULL, NULL, 'un', 3500000, 'ARS', '2026-02-05 14:03:12', '2026-02-05 14:03:12'),
(302, 1, 'Pastas \"Ancestral\"', NULL, NULL, 'un', 2150000, 'ARS', '2026-02-05 16:53:29', '2026-02-05 16:53:29'),
(303, 1, 'Pasta Pistachos \"Ancestral\"', NULL, NULL, 'un', 4000000, 'ARS', '2026-02-05 16:53:59', '2026-02-05 16:53:59'),
(304, 1, 'Legumbres \"Granix\"', NULL, NULL, 'un', 300000, 'ARS', '2026-02-05 16:54:27', '2026-02-05 16:54:27'),
(305, 1, 'Fideos \"Legume\"', NULL, NULL, 'un', 500000, 'ARS', '2026-02-05 16:55:10', '2026-02-05 16:55:10'),
(306, 1, '\"Jugo de Frutas\"- Durazno y Naranja con semillas de chias activada 250ml', 'Jugo de frutas + semillas de chía activadas', '013e89961fa91aa8c49c24df316e1ea3.jpg', 'un', 400000, 'ARS', '2026-02-26 03:29:14', '2026-02-21 23:17:53'),
(307, 1, 'Jugo de frutas- \"Frutilla y Naranja\" 250gr', 'Jugo de frutas + semillas de chía activadas', 'f5b82a77892156fa5429f0c8c22b5fe2.jpg', 'un', 400000, 'ARS', '2026-02-21 23:58:47', '2026-02-21 23:58:47'),
(308, 1, '\"Cerro Azul\" Alfajor dulce leche ZERO- c/STEVIA', NULL, '7ae29f6843b76bed4de39fe133208976.jpg', 'un', 300000, 'ARS', '2026-02-25 15:25:39', '2026-02-25 15:25:01'),
(309, 1, '\"Cerro Azul\" Bombones Frutales Surtidos', NULL, 'a7d0d0be962486e5cfa59fb0b702095a.png', 'un', 100000, 'ARS', '2026-02-25 15:34:22', '2026-02-25 15:34:22'),
(310, 1, '\"Cerro Azul\" Bocaditos chocolate SIN AZUCAR- c/STEVIA', NULL, 'b9aeea348f16a79f6d2ba7b82f42a524.png', 'un', 130000, 'ARS', '2026-02-25 15:41:52', '2026-02-25 15:41:52'),
(311, 1, '\"Dicomere\" Premezcla Universal c/PSYLIUM s/TACC x450gr', NULL, '85191fa3f4951f6e060993d894aded61.png', 'un', 400000, 'ARS', '2026-02-25 15:52:00', '2026-02-25 15:52:00'),
(312, 1, '\"Intipan\" Tostada c/semilla vegana s/TACC 150gr', NULL, 'b47eeceba6bab840d32870fce054ed75.png', 'un', 600000, 'ARS', '2026-02-25 15:59:54', '2026-02-25 15:59:54'),
(313, 1, '\"Intipan\" Tostada clasica vegana s/TACC x150gr', NULL, '2f8368c154a0c059d96628a0dd754403.png', 'un', 600000, 'ARS', '2026-02-25 16:06:50', '2026-02-25 16:06:50'),
(314, 1, '\"Limbus\" Cookies Integrales con PASAS, AVENA Y MANI (vegana)', NULL, '0dd1928cf31a94e2ee7182ee0c62b048.webp', 'un', 340000, 'ARS', '2026-02-25 16:10:58', '2026-02-25 16:10:17'),
(315, 1, '\"Limbus\" Cookies Integral con avena y frutos secos (veganas)', NULL, 'f1fc7db5f0aa5463a5f480245891cb3a.webp', 'un', 340000, 'ARS', '2026-02-26 02:36:21', '2026-02-25 16:13:47'),
(316, 1, '\"Limbus\" Cookies Integrales con algarroba, cacao y coco (veganas)', NULL, 'e537513a1836abe9377f02d5ac466a9b.webp', 'un', 340000, 'ARS', '2026-02-25 16:17:00', '2026-02-25 16:17:00'),
(317, 1, '\"Limbus\" Cookies Integrales con proteina de arveja, avena y algarroba (veeganas)', NULL, 'b2cb5cac6e74e4046d223c75cff2990d.webp', 'un', 380000, 'ARS', '2026-02-25 16:20:08', '2026-02-25 16:20:08'),
(318, 1, '\"Limbus\" Cookies Integrales con datiles cranberries y avena (veganas)', NULL, '935ff77bc5b6d66d1e79d6a050185414.webp', 'un', 340000, 'ARS', '2026-02-25 16:22:47', '2026-02-25 16:22:47'),
(319, 1, '\"Natural Seed\" Multisemillas s/TACC 250gr', NULL, 'f2e5d2014dcebd9239f15e042df520d3.jpg', 'un', 600000, 'ARS', '2026-02-25 16:30:03', '2026-02-25 16:30:03'),
(322, 1, '\"Entrenuts\" Aceite de coco nuetro 500cc', NULL, '32b324529f3cd8464d39775221ba1833.jpg', 'un', 1400000, 'ARS', '2026-02-26 01:51:36', '2026-02-26 01:51:36'),
(323, 1, '\"Dicomere\" Avena instantanea s/TACC', NULL, 'e0294dba922df726dbfdc347fb729bfc.webp', 'un', 500000, 'ARS', '2026-02-26 02:01:55', '2026-02-26 02:01:55'),
(324, 1, '\"Benot-Diabet\" Barra de chocolate amargo 70% - 100gr s/azucar agregada', 'sin tacc', '6372972ef2bacbe8b33caaab19c270af.jpg', 'un', 800000, 'ARS', '2026-02-26 02:42:22', '2026-02-26 02:42:22'),
(325, 1, '\"Laddubar\" Barrita Arandanos s/TACC 30gr (vegana)', NULL, 'cf70cecf70f0b48f99a5b9f44130ea90.jpg', 'un', 180000, 'ARS', '2026-02-26 02:49:51', '2026-02-26 02:49:51'),
(326, 1, '\"Laddubar\" Barrita Brownie s/ TACC 30gr (vegana)', NULL, '79026c7474171033e36faeee672a703e.webp', 'un', 180000, 'ARS', '2026-02-26 02:54:32', '2026-02-26 02:54:32'),
(327, 1, '\"Laddubar\" Barrita choco y naranja s/TACC (vegana)', NULL, 'b302dbfc155702612faefcb4552de6be.webp', 'un', 180000, 'ARS', '2026-02-26 02:56:21', '2026-02-26 02:56:21'),
(328, 1, '\"Laddubar\" Barrita frutos rojos s/TACC (vegana)', NULL, 'c47eeaba33aec6801d39ef5b1eb826dc.webp', 'un', 180000, 'ARS', '2026-02-26 02:59:12', '2026-02-26 02:59:12'),
(329, 1, '\"Dicomere\" Bicarbonato de sodio s/TACC 200gr', NULL, '1816b36afd969556deb150fd5a6bdf63.jpg', 'un', 320000, 'ARS', '2026-02-27 13:48:41', '2026-02-26 03:02:21'),
(330, 1, '\"Dicomere\" Cacao amargo puro en polvo s/TACC 200gr', '100% cacao brasilero', '6456fb66bbc85be340edec42723b4e26.jpg', 'kg', 800000, 'ARS', '2026-02-27 13:50:00', '2026-02-26 03:05:43'),
(331, 1, '\"Dicomere\" Harina de trigo sarraceno integral organica s/TACC 450gr', NULL, '38def6aa5966bfab02d3e91095e744d0.jpg', 'un', 500000, 'ARS', '2026-02-27 13:50:32', '2026-02-26 03:08:20'),
(336, 1, '\"Dicomere\" Cafe de algarroba s/TACC 200gr', NULL, '25a23034fe44cf59473ed91d09909252.jpg', 'un', 300000, 'ARS', '2026-02-26 03:10:39', '2026-02-26 03:10:39'),
(337, 1, '\"Dicomere\" Fecula de maiz s/TACC 450gr', NULL, 'd6e507b6a4baf45cd90dd096198f15c6.jpg', 'kg', 300000, 'ARS', '2026-02-26 03:12:53', '2026-02-26 03:12:53'),
(338, 1, '\"Dicomere\" Fecula de mandioca s/TACC 450gr', NULL, '307efadbc3a17a9a7baf684866d08905.jpg', 'un', 350000, 'ARS', '2026-02-27 13:58:39', '2026-02-26 03:15:05'),
(339, 1, '\"Dicoreme\" Polvo para hornear s/TACC 250gr', NULL, '26bcab87f9c01c5465adeb9360c1810b.jpg', 'un', 300000, 'ARS', '2026-02-26 03:20:22', '2026-02-26 03:20:22'),
(340, 1, '\"Dicomere\" Premezcla pan s/TACC c/PSYILLUM HUSK 450gr', NULL, '56d4d24f0cfb4f07c51e62ed291467b1.jpg', 'un', 400000, 'ARS', '2026-02-27 14:05:07', '2026-02-26 03:22:48'),
(341, 1, '\"Dicomere\" Premezcla pizza s/TACC c/PSYILLUM HUSK  450gr', NULL, '99ca2ac4d6f19025c17db079cfb876ab.jpg', 'un', 400000, 'ARS', '2026-02-27 14:05:21', '2026-02-26 03:24:54'),
(342, 1, '\"Dicomere\" Premezcla universal s/TACC c/PSYILLUM HUSK 450gr', NULL, '173686d24c71d56713b836baec9631e1.jpg', 'un', 400000, 'ARS', '2026-02-27 14:06:51', '2026-02-26 03:27:08'),
(343, 1, '\"DIcomere\" Rebozador de arroz s/TACC 450gr', NULL, '31b15934c5ba450eade7d27fb87c760b.jpg', 'un', 300000, 'ARS', '2026-02-26 03:33:35', '2026-02-26 03:33:35'),
(344, 1, '\"Salusin\" Sal diet baja en sodio carnes rojas', NULL, '6decb7ad715dbf59e254f8c12b46abd7.jpg', 'un', 300000, 'ARS', '2026-02-26 03:37:01', '2026-02-26 03:37:01'),
(345, 1, '\"SaintGottard\" Té Próstata- 20 saquitos', 'Blend de hierbas y flores con uva ursi/equiseto/ortiga', '3925800f1247fd8a7a7bca75a78f3ce3.png', 'un', 580000, 'ARS', '2026-02-28 22:03:58', '2026-02-27 16:23:44'),
(346, 1, '\"Femag\" Fécula de mandioca s/TACC', NULL, '6a51658732300b3195d141e58aece9fc.png', 'un', 600000, 'ARS', '2026-02-27 16:49:48', '2026-02-27 16:49:48'),
(347, 1, '\"SaintGottard\" Te chía - 20 saquitos', 'Rosa mosqueta, Canela y Especias', '4c45bc2fbda7306fc43b0d9969cd2282.jpg', 'un', 580000, 'ARS', '2026-02-28 21:43:43', '2026-02-28 21:43:43'),
(348, 1, '\"SaintGottard\" Te Manzanilla, canela y sabor miel- 20 saquitos', NULL, 'f067754eba1e1ce68e8aad4a8c1d50da.jpg', 'un', 580000, 'ARS', '2026-02-28 21:45:19', '2026-02-28 21:45:19'),
(349, 1, '\"SaintGottard\" Te verde (sabor cereza y frambuesa)- 20 saquitos', NULL, '2a1bb5018f2afc7192caa3e4baedc90a.jpg', 'un', 580000, 'ARS', '2026-02-28 21:47:31', '2026-02-28 21:47:31'),
(350, 1, '\"SaintGottard\" Te Anti Colesterol- 20 saquitos', 'Blend de hierbas y especias con jengibre/ alfalfa/ alcahofa', '2816e884e36b4cebaaebc3c6ecc46239.jpg', 'un', 580000, 'ARS', '2026-02-28 22:01:05', '2026-02-28 22:01:05'),
(351, 1, '\"SaintGottard\" Te Dulces sueños- 20 saquito', 'Blend de hierbas y flores con tilo/manzanilla/cedrón', 'c42143b45f3a5c69a2c2fe20f0b0c34d.jpg', 'un', 580000, 'ARS', '2026-02-28 22:14:57', '2026-02-28 22:14:57'),
(353, 1, '\"Ancestral\" Pasta de Pistachos  s/TACC (vegana)- 200gr', NULL, '4ad8f4449e2c8446d8c54497d41bcd7e.jpg', 'un', 3500000, 'ARS', '2026-02-28 22:28:43', '2026-02-28 22:28:43'),
(354, 1, '\"Ancestral\" Gianfuia postre 70% avellanas (vegana)- 200gr', NULL, 'ad540c5624268d0059970cb51ccb89c7.jpg', 'un', 2100000, 'ARS', '2026-02-28 22:32:18', '2026-02-28 22:32:18'),
(355, 1, '\"Ancestral\" Pasta untable Almendras (vegana)-200gr', NULL, '9b592c70d6b5734f23115155c203a6eb.jpg', 'un', 2100000, 'ARS', '2026-02-28 22:33:28', '2026-02-28 22:33:28'),
(356, 1, '\"Ancestral\" Pasta untable Castaña de Cajú (vegana)- 200gr', NULL, '559a35bb052d83bac22b52b6c71eb919.jpg', 'un', 2100000, 'ARS', '2026-02-28 22:34:45', '2026-02-28 22:34:45'),
(357, 1, '\"Lyna\" Date Syrup- Jarabe de dátil s/TACC -340gr', 'vegana', 'f194de5b03ff40a330b678a578d32429.png', 'un', 1800000, 'ARS', '2026-02-28 23:00:57', '2026-02-28 23:00:57'),
(358, 1, '\"Macrozen Marina\" Sal marina fina s/TACC- 500gr', NULL, '500029d8159e10a95caea4a13ce77446.png', 'un', 400000, 'ARS', '2026-02-28 23:37:37', '2026-02-28 23:37:37'),
(359, 1, '\"KonyEritritol\"+ Stevia s/TACC - 325gr', NULL, 'b6412626629238255562ca760dc32d3d.png', 'un', 2500000, 'ARS', '2026-02-28 23:42:21', '2026-02-28 23:42:21'),
(360, 1, '\"Akaguapy\" Yerba Mate orgánica molienda tradicional s/TACC- 500gr', NULL, '5976f787ee4617e8a95cbc9221785317.png', 'un', 500000, 'ARS', '2026-02-28 23:45:37', '2026-02-28 23:45:37'),
(361, 1, 'Almohaditas Chocolate negro y blanco', NULL, '5319ae23b6abcfd7e40eee91c9756c2b.jpg', 'kg', 1500000, 'ARS', '2026-03-02 13:51:49', '2026-03-02 13:51:49'),
(362, 1, 'Almohaditas Limon (a granel)', NULL, '98a3110d99b46c2f93be259999d2777e.png', 'kg', 1500000, 'ARS', '2026-03-02 13:55:04', '2026-03-02 13:55:04'),
(363, 1, 'Hierbas para infusión Sen de Hojas (a granel)', NULL, '915c0f50c685dfc549d14b85165b5c90.jpg', 'kg', 2000000, 'ARS', '2026-03-02 14:07:45', '2026-03-02 14:07:45'),
(364, 1, 'Arroz Integral (agranel)', NULL, 'afbb1059a4e9d7ea95243f5f040f8020.jpg', 'kg', 450000, 'ARS', '2026-03-02 14:19:25', '2026-03-02 14:19:25'),
(365, 1, 'Harina de Almendra Pura (a granel)', NULL, 'd815a24c6adf437ba01a593d2dfdb4a0.jpg', 'kg', 2200000, 'ARS', '2026-03-02 14:43:58', '2026-03-02 14:43:58'),
(366, 1, '\"Kimwa\" Pan de molde s/TACC- 450gr', NULL, 'e8652413b729d37ab922ab1852c1cc95.png', 'un', 800000, 'ARS', '2026-03-04 16:12:57', '2026-03-02 16:36:35'),
(367, 1, '\"Kinwa\" Pan MultiSemilla de molde s/TACC -450gr', NULL, '5fca961fa37756d8c155ba55d0d21fb1.png', 'un', 850000, 'ARS', '2026-03-04 16:13:13', '2026-03-02 16:40:59'),
(368, 1, 'Budín de chocolate s/TACC', NULL, '8e204cc2d97911bcdffa717ef52c0e97.png', 'un', 700000, 'ARS', '2026-03-02 16:45:18', '2026-03-02 16:45:18'),
(369, 1, '\"Reina\" Ketosisima premezcla de chocolate keto s/TACC- 200gr', NULL, '831dce09a3cd28f5b5802ad33111ac72.png', 'un', 800000, 'ARS', '2026-03-02 22:48:33', '2026-03-02 22:48:33'),
(370, 1, '\"Kinwa\" Pan s/TACC- 185gr', NULL, '638b34e36156635ada403e6dc70b7806.png', 'un', 80000, 'ARS', '2026-03-04 16:12:34', '2026-03-04 16:12:34'),
(371, 1, '\"Kinwa\" 2 Pizzetas s/TACC', NULL, 'a79af48f817a03bc5c2c31d3a3097f4e.png', 'un', 550000, 'ARS', '2026-03-04 16:16:32', '2026-03-04 16:16:32'),
(372, 1, '\"Kinwa\" 1 Prepizza s/TACC', NULL, '4ca6bf541e924d4566325b84858b7f31.png', 'un', 500000, 'ARS', '2026-03-04 16:21:42', '2026-03-04 16:21:42'),
(373, 1, '\"Verde Vida\" Kéfir de agua 910ml', NULL, '1127a657b22fa67c69a6caa917636f71.png', 'un', 680000, 'ARS', '2026-03-04 16:33:37', '2026-03-04 16:33:37'),
(374, 1, '\"SanaMundi\" Rebozador s/TACC- 500gr', 'Compuesto con garbanzo-sésamo-lino', 'fa5e48c9577c02defaebbc4cd4b75558.png', 'un', 550000, 'ARS', '2026-03-04 16:42:23', '2026-03-04 16:42:23'),
(375, 1, '\"El Naturalista\" Gotas Avena Sativa- 60c.c', 'Tintura Madre', '4c1585d15cc5884758417fa6cca7a662.jpg', 'un', 700000, 'ARS', '2026-03-05 00:27:00', '2026-03-05 00:27:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `customer_orders`
--

CREATE TABLE `customer_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `customer_name` varchar(190) NOT NULL,
  `customer_phone` varchar(40) DEFAULT NULL,
  `customer_email` varchar(190) DEFAULT NULL,
  `customer_dni` varchar(32) DEFAULT NULL,
  `customer_address` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'ARS',
  `total_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` enum('new','confirmed','cancelled','fulfilled') NOT NULL DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `customer_orders`
--

INSERT INTO `customer_orders` (`id`, `created_by`, `customer_name`, `customer_phone`, `customer_email`, `customer_dni`, `customer_address`, `notes`, `currency`, `total_cents`, `status`, `created_at`) VALUES
(17, 1, 'Maxi', '2464984846', 'maximilianoalderete017@gmail.com', NULL, 'LAMADRID 2601', NULL, 'ARS', 100000, 'new', '2026-02-19 20:37:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `customer_order_items`
--

CREATE TABLE `customer_order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `line_total_cents` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `customer_order_items`
--

INSERT INTO `customer_order_items` (`id`, `order_id`, `product_id`, `description`, `quantity`, `unit_price_cents`, `line_total_cents`) VALUES
(26, 17, 170, 'Arroz  de sushi', 0.20, 500000, 100000);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `finance_entries`
--

CREATE TABLE `finance_entries` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `entry_type` enum('income','expense') NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `currency` char(3) NOT NULL DEFAULT 'ARS',
  `entry_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `invoices`
--

CREATE TABLE `invoices` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `customer_name` varchar(190) NOT NULL,
  `customer_phone` varchar(40) DEFAULT NULL,
  `customer_email` varchar(190) NOT NULL,
  `customer_dni` varchar(32) DEFAULT NULL,
  `customer_address` varchar(255) DEFAULT NULL,
  `detail` text DEFAULT NULL,
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `total_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `invoice_id` int(10) UNSIGNED NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(8) DEFAULT NULL,
  `unit_price_cents` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `line_total_cents` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `stock_items`
--

CREATE TABLE `stock_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `name` varchar(190) NOT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `unit` varchar(24) DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `stock_items`
--

INSERT INTO `stock_items` (`id`, `created_by`, `name`, `sku`, `unit`, `quantity`, `updated_at`, `created_at`) VALUES
(7, 1, 'Jugo', '7790036000565', 'ml', 200.00, '2026-02-10 19:20:46', '2026-02-10 19:20:46');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `created_at`) VALUES
(1, 'lasbeltra@polopositivoar.com', '$2a$12$kEWhhlRdcyvD8emAOEmXRe8t4x4zqbHEZU1cZ2uZ/GWlRl1azETGy', '2025-12-15 14:32:44');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `catalog_products`
--
ALTER TABLE `catalog_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_catalog_products_user_name` (`created_by`,`name`),
  ADD KEY `idx_catalog_products_created_by` (`created_by`);

--
-- Indices de la tabla `customer_orders`
--
ALTER TABLE `customer_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_orders_created_by` (`created_by`),
  ADD KEY `idx_customer_orders_created_by_status_created_at` (`created_by`,`status`,`created_at`);

--
-- Indices de la tabla `customer_order_items`
--
ALTER TABLE `customer_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_customer_order_items_order_id` (`order_id`),
  ADD KEY `idx_customer_order_items_product_id` (`product_id`);

--
-- Indices de la tabla `finance_entries`
--
ALTER TABLE `finance_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_finance_entries_created_by_date` (`created_by`,`entry_date`),
  ADD KEY `idx_finance_entries_type` (`entry_type`);

--
-- Indices de la tabla `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoices_created_by` (`created_by`),
  ADD KEY `idx_invoices_customer_dni` (`customer_dni`),
  ADD KEY `idx_invoices_created_by_created_at` (`created_by`,`created_at`);

--
-- Indices de la tabla `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_invoice_items_invoice_id` (`invoice_id`);

--
-- Indices de la tabla `stock_items`
--
ALTER TABLE `stock_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_stock_items_user_sku` (`created_by`,`sku`),
  ADD KEY `idx_stock_items_created_by` (`created_by`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `catalog_products`
--
ALTER TABLE `catalog_products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=376;

--
-- AUTO_INCREMENT de la tabla `customer_orders`
--
ALTER TABLE `customer_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `customer_order_items`
--
ALTER TABLE `customer_order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT de la tabla `finance_entries`
--
ALTER TABLE `finance_entries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=184;

--
-- AUTO_INCREMENT de la tabla `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=222;

--
-- AUTO_INCREMENT de la tabla `stock_items`
--
ALTER TABLE `stock_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `catalog_products`
--
ALTER TABLE `catalog_products`
  ADD CONSTRAINT `fk_catalog_products_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `customer_orders`
--
ALTER TABLE `customer_orders`
  ADD CONSTRAINT `fk_customer_orders_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `customer_order_items`
--
ALTER TABLE `customer_order_items`
  ADD CONSTRAINT `fk_customer_order_items_orders` FOREIGN KEY (`order_id`) REFERENCES `customer_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `finance_entries`
--
ALTER TABLE `finance_entries`
  ADD CONSTRAINT `fk_finance_entries_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoices_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `fk_invoice_items_invoices` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `stock_items`
--
ALTER TABLE `stock_items`
  ADD CONSTRAINT `fk_stock_items_users` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
