-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:3306
-- Tiempo de generación: 19-08-2026 a las 12:01:12
-- Versión del servidor: 10.5.29-MariaDB-log
-- Versión de PHP: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `gymtrack_tasty`
--

DELIMITER $$
--
-- Procedimientos
--
CREATE DEFINER=`gymtrack`@`localhost` PROCEDURE `clases_segun_sexo` (IN `p_ID_Usuario` INT)  READS SQL DATA SQL SECURITY INVOKER COMMENT 'Unir una clase y su sexo con un usuario' BEGIN
  SELECT c.*
  FROM Usuario u
  JOIN Clase c
    ON (c.sexo = 'Mixto' OR c.sexo = u.sexo)
  WHERE u.ID_Usuario = p_id_usuario
  ORDER BY c.nombre;
END$$

CREATE DEFINER=`gymtrack`@`localhost` PROCEDURE `guardar_historial` (IN `p_usuario` INT, IN `p_ejercicio` INT, IN `p_fecha` DATE, IN `p_series` INT, IN `p_reps` INT, IN `p_peso` DECIMAL(5,2))  READS SQL DATA SQL SECURITY INVOKER BEGIN
  INSERT INTO Historial (ID_Historial, ID_Usuario, ID_Ejercicio, fecha, series, repeticiones, peso)
  VALUES (NULL, p_usuario, p_ejercicio, p_fecha, p_series, p_reps, p_peso)
  ON DUPLICATE KEY UPDATE
    series = VALUES(series),
    repeticiones = VALUES(repeticiones),
    peso = VALUES(peso);
END$$

CREATE DEFINER=`gymtrack`@`localhost` PROCEDURE `plan_dia` (IN `p_ID_Usuario` INT, IN `p_dia` INT)  READS SQL DATA SQL SECURITY INVOKER BEGIN
  SELECT 
    e.ID_Ejercicio,
    e.nombre,
    ej.series       AS obj_series,
    ej.repeticiones AS obj_reps,
    ej.peso         AS obj_peso
  FROM Usuario_RutinaDia ur
  JOIN Rutina r        ON r.ID_Rutina   = ur.ID_Rutina
  JOIN Ejercitacion ej ON ej.ID_Rutina  = r.ID_Rutina
  JOIN Ejercicio e     ON e.ID_Ejercicio = ej.ID_Ejercicio
  WHERE ur.ID_Usuario = p_ID_Usuario
    AND ur.dia       = p_dia
  ORDER BY e.nombre;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Clase`
--

CREATE TABLE `Clase` (
  `ID_Clase` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(30) DEFAULT NULL,
  `descripcion` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Clase`
--

INSERT INTO `Clase` (`ID_Clase`, `nombre`, `descripcion`) VALUES
(1, 'Universal', 'Rutinas varias para cualquier tipo de persona'),
(2, 'Deportista', 'Entrenamiento orientado a deportes de equipo, con foco en fuerza explosiva, agilidad y cambios de dirección.'),
(3, 'Tonificación', 'Programa para mejorar la composición corporal, postura y condición general, ideal para principiantes, personas sedentarias o de oficina.'),
(4, 'Hipertrofia', 'Rutina enfocada en el aumento de masa muscular con trabajo específico por grupos musculares.'),
(5, 'Adulto mayor', 'Entrenamiento adaptado con movimientos controlados para mejorar fuerza, equilibrio y movilidad en adultos mayores.'),
(6, 'Atleta de resistencia', 'Plan centrado en mejorar la capacidad aeróbica y la resistencia para corredores, ciclistas, nadadores y montañistas.'),
(7, 'Powerlifting', 'Programa orientado a desarrollar fuerza máxima en ejercicios básicos como sentadilla, press de banca y peso muerto.'),
(8, 'Entrenamiento funcional', 'Rutinas de alta intensidad con ejercicios multiarticulares para mejorar fuerza, coordinación y capacidad funcional.'),
(9, 'Deportes de combate', 'Entrenamiento específico para deportistas de combate, con énfasis en fuerza explosiva, agilidad y resistencia.'),
(10, 'Rehabilitación', 'Ejercicios adaptados para recuperación de lesiones o condiciones especiales como embarazo y estrés.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Ejercicio`
--

CREATE TABLE `Ejercicio` (
  `ID_Ejercicio` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(250) NOT NULL,
  `gif` varchar(250) DEFAULT NULL,
  `body_part_es` varchar(50) DEFAULT NULL,
  `target_muscle_es` varchar(50) DEFAULT NULL,
  `equipment_es` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Ejercicio`
--

INSERT INTO `Ejercicio` (`ID_Ejercicio`, `nombre`, `descripcion`, `gif`, `body_part_es`, `target_muscle_es`, `equipment_es`) VALUES
(1, 'Press de banca', 'Ejercicio compuesto para desarrollar pectorales, hombros y tríceps.', '01-PRESS DE BANCA.mp4', NULL, NULL, NULL),
(2, 'Sentadilla', 'Fortalece cuádriceps, glúteos y zona lumbar. Mantén la espalda recta.', '02-Sentadilla.mp4', NULL, NULL, NULL),
(3, 'Peso muerto', 'Trabaja espalda baja, glúteos e isquiotibiales. Técnica fundamental de fuerza.', '03-PESO MUERTO.mp4', NULL, NULL, NULL),
(4, 'Dominadas', 'Ejercicio con peso corporal para desarrollar dorsales y bíceps.', '04-DOMINADA.mp4', NULL, NULL, NULL),
(5, 'Curl de bíceps', 'Aisla el bíceps braquial mediante la flexión del codo.', '05-CURL DE BICEPS.mp4', NULL, NULL, NULL),
(6, 'Press militar', 'Desarrolla la fuerza en hombros y tríceps levantando una barra sobre la cabeza.', '06-Press militar.mp4', NULL, NULL, NULL),
(7, 'Fondos en paralelas', 'Ejercicio de empuje para tríceps, hombros y pecho inferior.', '07-FONDOS EN PARALELAS.mp4', NULL, NULL, NULL),
(8, 'Zancadas', 'Fortalece piernas y glúteos. Se realiza un paso largo hacia adelante o atrás.', '08-ZANCADAS.mp4', NULL, NULL, NULL),
(9, 'Elevaciones laterales', 'Aisla los deltoides medios para mejorar la forma de los hombros.', '09-ELEVACIONES LATERALES.mp4', NULL, NULL, NULL),
(10, 'Plancha abdominal', 'Ejercicio isométrico que fortalece el core y mejora la postura.', '10-PLANCHA.mp4', NULL, NULL, NULL),
(11, 'Abdominales crunch', 'Fortalece el recto abdominal con movimientos cortos del tronco.', '11-ABDOMINALES.mp4', NULL, NULL, NULL),
(12, 'Remo con barra', 'Desarrolla dorsales, trapecios y bíceps mediante tracción horizontal.', '12-REMO CON BARRA.mp4', NULL, NULL, NULL),
(13, 'Press de pierna', 'Ejercicio guiado para trabajar cuádriceps y glúteos en máquina.', '13-PRESS DE PIERNA.mp4', NULL, NULL, NULL),
(14, 'Hip thrust', 'Eleva la cadera apoyando la espalda en banco para activar glúteos.', '14-Hip Thrust.mp4', NULL, NULL, NULL),
(15, 'Extensión de tríceps en polea', 'Ejercicio de aislamiento para tríceps usando polea alta.', '15-EXTENSION DE TRICEPS EN POLEA.mp4', NULL, NULL, NULL),
(16, 'Jalón al pecho', 'Fortalece dorsales, bíceps y romboides mediante un tirón controlado hacia el pecho.', '16-JALON AL PECHO.mp4', NULL, NULL, NULL),
(17, 'Remo en polea baja', 'Ejercicio de tracción para espalda media y bíceps.', '17-REMO EN POLEA BAJA.mp4', NULL, NULL, NULL),
(18, 'Aperturas con mancuernas', 'Trabaja el pectoral mayor mediante un movimiento de abducción en banco plano.', '18-APERTURA CON MANUERNAS.mp4', NULL, NULL, NULL),
(19, 'Peck deck', 'Aísla el pectoral mediante aducción en máquina.', '19-Peck deck.mp4', NULL, NULL, NULL),
(20, 'Jalón agarre cerrado', 'Fortalece dorsales y bíceps con un agarre estrecho que favorece la activación de la parte interna de la espalda. Reduce la tensión en los hombros respecto al jalón tras nuca.', '20-JALON AGARRE CERRADO.mp4', NULL, NULL, NULL),
(21, 'Press inclinado con mancuernas', 'Trabaja pectoral superior y deltoides anteriores.', '21-PRESS INCLINADO CON MANUERNAS.mp4', NULL, NULL, NULL),
(22, 'Sentadilla búlgara', 'Aísla los cuádriceps y glúteos con apoyo unilateral.', '22-SENTADILLA BULGARA.mp4', NULL, NULL, NULL),
(23, 'Peso muerto rumano', 'Enfocado en femorales y glúteos con mínima flexión de rodilla.', '23-PESO MUERTO RUMANO.mp4', NULL, NULL, NULL),
(24, 'Press de hombros en máquina', 'Ejercicio guiado para deltoides y tríceps.', '24-PRESS DE HOMBRO EN MAQUINA.mp4', NULL, NULL, NULL),
(26, 'Face pull', 'Fortalece deltoides posteriores y trapecios.', '26-FACE PULL.mp4', NULL, NULL, NULL),
(27, 'Curl martillo', 'Trabaja el braquial y el braquiorradial.', '27-Curl_martillo.mp4', NULL, NULL, NULL),
(28, 'Curl de bíceps en banco Scott', 'Aísla totalmente el bíceps.', '28-Curl de bíceps en banco Scott.mp4', NULL, NULL, NULL),
(29, 'Fondos en banco', 'Ejercicio de tríceps usando banco como apoyo.', '29-FONDOS EN BANCO.mp4', NULL, NULL, NULL),
(31, 'Jalón de tríceps en polea', 'Movimiento de extensión para tríceps con agarre en cuerda.', '31-Jalón de tríceps en polea.mp4', NULL, NULL, NULL),
(33, 'Curl femoral en máquina', 'Aísla los isquiotibiales.', '33-Curl femoral en máquina.mp4', NULL, NULL, NULL),
(34, 'Gemelos de pie', 'Fortalece los gastrocnemios con elevaciones.', '34-GEMELOS DE PIE.mp4', NULL, NULL, NULL),
(35, 'Gemelos sentado', 'Trabaja el sóleo específicamente.', '35-GEMELOS SENTADO.mp4', NULL, NULL, NULL),
(36, 'Abducción de glúteo en máquina', 'Fortalece glúteo medio y menor.', '36-Abducción de glúteo en máquina.mp4', NULL, NULL, NULL),
(37, 'Aducción de pierna en máquina', 'Trabaja aductores internos.', '37-Aducción de glúteo en máquina.mp4', NULL, NULL, NULL),
(38, 'Paseo lateral con banda', 'Ejercicio lateral que activa glúteo medio y menor al caminar con banda elástica manteniendo tensión constante. Mejora estabilidad de cadera y control del tren inferior.', '38-Paseo lateral con banda.mp4', NULL, NULL, NULL),
(39, 'Buenos días', 'Ejercicio para zona lumbar y femorales.', '39-buenos dias.mp4', NULL, NULL, NULL),
(40, 'Remo con mancuerna a una mano', 'Aísla dorsales y romboides.', '40-REMO CON MANCUERNA A UNA MANO.mp4', NULL, NULL, NULL),
(41, 'Press Arnold', 'Trabaja todos los deltoides en rotación ascendente.', '41-PRESS ARNOLD.mp4', NULL, NULL, NULL),
(42, 'Glute bridge', 'Ejercicio básico para glúteos y femorales.', '42-GLUTE BRIDGE.mp4', NULL, NULL, NULL),
(43, 'Ab wheel', 'Fortalece toda la zona del core.', '43-AB WHEEL.mp4', NULL, NULL, NULL),
(44, 'Mountain climbers', 'Ejercicio dinámico para abdomen y resistencia.', '44-MOUNTAIN CLIMBER.mp4', NULL, NULL, NULL),
(45, 'Russian twist', 'Fortalece oblicuos mediante rotación del tronco.', '45-RUSSIAN TWIST.mp4', NULL, NULL, NULL),
(46, 'Elevación frontal con mancuernas', 'Aísla deltoides anteriores.', '46-Elevación frontal con mancuernas.mp4', NULL, NULL, NULL),
(47, 'Extensión de tríceps con mancuerna', 'Aísla el tríceps en movimiento vertical.', '47-EXTENSION DE TRICEPS CON MANCUERNA.mp4', NULL, NULL, NULL),
(48, 'Extensión de cuádriceps en máquina', 'Aísla el cuádriceps mediante extensión.', '48-Extensión de cuádriceps en máquina.mp4', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Ejercicio_ExerciseDB`
--

CREATE TABLE `Ejercicio_ExerciseDB` (
  `exercisedb_id` varchar(20) NOT NULL,
  `ID_Ejercicio` int(10) UNSIGNED DEFAULT NULL,
  `nombre_en` varchar(150) NOT NULL,
  `body_part` varchar(50) DEFAULT NULL,
  `target_muscle` varchar(50) DEFAULT NULL,
  `equipment` varchar(50) DEFAULT NULL,
  `gif_url_original` varchar(250) DEFAULT NULL,
  `descargado_en` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Ejercitacion`
--

CREATE TABLE `Ejercitacion` (
  `ID_Ejercitacion` int(10) UNSIGNED NOT NULL,
  `ID_Rutina` int(11) UNSIGNED NOT NULL,
  `ID_Ejercicio` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Ejercitacion`
--

INSERT INTO `Ejercitacion` (`ID_Ejercitacion`, `ID_Rutina`, `ID_Ejercicio`) VALUES
(1, 1, 1),
(2, 1, 12),
(3, 1, 6),
(4, 1, 9),
(5, 1, 16),
(6, 1, 5),
(7, 1, 15),
(8, 1, 10),
(9, 2, 2),
(10, 2, 13),
(11, 2, 8),
(12, 2, 3),
(13, 2, 23),
(14, 2, 14),
(15, 2, 34),
(16, 2, 11),
(17, 3, 1),
(18, 3, 21),
(19, 3, 24),
(20, 3, 40),
(21, 3, 26),
(22, 3, 41),
(23, 3, 27),
(24, 3, 31),
(25, 4, 2),
(26, 4, 22),
(27, 4, 23),
(28, 4, 39),
(29, 4, 33),
(30, 4, 42),
(31, 4, 35),
(32, 4, 38),
(33, 5, 1),
(34, 5, 2),
(35, 5, 12),
(36, 5, 3),
(37, 5, 6),
(38, 5, 13),
(39, 5, 10),
(40, 5, 43),
(41, 5, 44),
(42, 5, 45),
(43, 6, 8),
(44, 6, 22),
(45, 6, 40),
(46, 6, 16),
(47, 6, 29),
(48, 6, 14),
(49, 6, 36),
(50, 6, 37),
(51, 6, 10),
(52, 6, 44),
(53, 7, 1),
(54, 7, 12),
(55, 7, 6),
(56, 7, 4),
(57, 7, 29),
(58, 8, 21),
(59, 8, 17),
(60, 8, 9),
(61, 8, 28),
(62, 8, 31),
(63, 9, 2),
(64, 9, 13),
(65, 9, 23),
(66, 9, 34),
(67, 9, 14),
(68, 10, 22),
(69, 10, 3),
(70, 10, 33),
(71, 10, 35),
(72, 10, 37),
(73, 11, 1),
(74, 11, 2),
(75, 11, 12),
(76, 11, 14),
(77, 11, 10),
(78, 12, 21),
(79, 12, 8),
(80, 12, 17),
(81, 12, 42),
(82, 12, 44),
(83, 13, 1),
(84, 13, 18),
(85, 13, 16),
(86, 13, 17),
(87, 13, 6),
(88, 13, 5),
(89, 13, 15),
(90, 14, 21),
(91, 14, 19),
(92, 14, 20),
(93, 14, 12),
(94, 14, 9),
(95, 14, 27),
(96, 14, 29),
(97, 15, 2),
(98, 15, 13),
(99, 15, 33),
(100, 15, 48),
(101, 15, 34),
(102, 15, 35),
(103, 16, 22),
(104, 16, 14),
(105, 16, 23),
(106, 16, 8),
(107, 16, 36),
(108, 16, 37),
(109, 17, 2),
(110, 17, 1),
(111, 17, 12),
(112, 17, 6),
(113, 17, 10),
(114, 17, 11),
(115, 18, 8),
(116, 18, 4),
(117, 18, 7),
(118, 18, 26),
(119, 18, 43),
(120, 18, 45),
(121, 19, 1),
(122, 19, 21),
(123, 19, 18),
(124, 19, 7),
(125, 19, 15),
(126, 19, 31),
(127, 20, 4),
(128, 20, 16),
(129, 20, 12),
(130, 20, 17),
(131, 20, 5),
(132, 20, 27),
(133, 21, 2),
(134, 21, 13),
(135, 21, 48),
(136, 21, 8),
(137, 21, 22),
(138, 21, 34),
(139, 22, 14),
(140, 22, 23),
(141, 22, 33),
(142, 22, 42),
(143, 22, 37),
(144, 22, 36),
(145, 23, 2),
(146, 23, 1),
(147, 23, 12),
(148, 23, 6),
(149, 23, 5),
(150, 23, 11),
(151, 24, 3),
(152, 24, 41),
(153, 24, 22),
(154, 24, 26),
(155, 24, 7),
(156, 24, 43),
(157, 25, 24),
(158, 25, 19),
(159, 25, 16),
(160, 25, 17),
(161, 25, 5),
(162, 25, 15),
(163, 26, 1),
(164, 26, 18),
(165, 26, 9),
(166, 26, 26),
(167, 26, 27),
(168, 26, 31),
(169, 27, 13),
(170, 27, 48),
(171, 27, 33),
(172, 27, 34),
(173, 27, 35),
(174, 27, 42),
(175, 28, 13),
(176, 28, 36),
(177, 28, 37),
(178, 28, 38),
(179, 28, 42),
(180, 29, 13),
(181, 29, 24),
(182, 29, 16),
(183, 29, 5),
(184, 29, 15),
(185, 29, 11),
(186, 30, 42),
(187, 30, 10),
(188, 30, 11),
(189, 30, 34),
(190, 30, 26),
(191, 30, 45),
(210, 33, 16),
(211, 33, 17),
(212, 33, 40),
(213, 33, 24),
(214, 33, 26),
(215, 33, 9),
(216, 34, 4),
(217, 34, 7),
(218, 34, 6),
(219, 34, 27),
(220, 34, 31),
(221, 34, 10),
(222, 35, 2),
(223, 35, 13),
(224, 35, 23),
(225, 35, 48),
(226, 35, 33),
(227, 35, 34),
(228, 36, 8),
(229, 36, 22),
(230, 36, 14),
(231, 36, 42),
(232, 36, 35),
(233, 36, 38),
(234, 37, 2),
(235, 37, 1),
(236, 37, 12),
(237, 37, 26),
(238, 37, 10),
(239, 37, 44),
(240, 38, 23),
(241, 38, 40),
(242, 38, 24),
(243, 38, 43),
(244, 38, 45),
(245, 38, 42),
(246, 39, 1),
(247, 39, 21),
(248, 39, 7),
(249, 39, 15),
(250, 39, 31),
(251, 39, 26),
(252, 40, 6),
(253, 40, 41),
(254, 40, 12),
(255, 40, 17),
(256, 40, 9),
(257, 40, 26),
(258, 41, 2),
(259, 41, 13),
(260, 41, 22),
(261, 41, 48),
(262, 41, 34),
(263, 41, 10),
(264, 42, 3),
(265, 42, 23),
(266, 42, 39),
(267, 42, 33),
(268, 42, 35),
(269, 42, 11),
(270, 43, 2),
(271, 43, 1),
(272, 43, 12),
(273, 43, 15),
(274, 43, 5),
(275, 43, 10),
(276, 44, 3),
(277, 44, 1),
(278, 44, 16),
(279, 44, 27),
(280, 44, 31),
(281, 44, 11),
(282, 45, 4),
(283, 45, 7),
(284, 45, 26),
(285, 45, 9),
(286, 45, 10),
(287, 45, 43),
(288, 46, 1),
(289, 46, 12),
(290, 46, 41),
(291, 46, 27),
(292, 46, 31),
(293, 46, 44),
(294, 47, 8),
(295, 47, 22),
(296, 47, 14),
(297, 47, 36),
(298, 47, 38),
(299, 47, 42),
(300, 48, 2),
(301, 48, 13),
(302, 48, 23),
(303, 48, 34),
(304, 48, 44),
(305, 48, 45),
(306, 49, 2),
(307, 49, 4),
(308, 49, 7),
(309, 49, 10),
(310, 49, 11),
(311, 49, 45),
(312, 50, 3),
(313, 50, 1),
(314, 50, 12),
(315, 50, 43),
(316, 50, 44),
(317, 50, 26),
(318, 51, 1),
(319, 51, 6),
(320, 51, 12),
(321, 51, 26),
(322, 51, 7),
(323, 51, 44),
(324, 52, 4),
(325, 52, 16),
(326, 52, 17),
(327, 52, 27),
(328, 52, 31),
(329, 52, 10),
(330, 53, 2),
(331, 53, 3),
(332, 53, 14),
(333, 53, 34),
(334, 53, 42),
(335, 53, 45),
(336, 54, 8),
(337, 54, 22),
(338, 54, 23),
(339, 54, 38),
(340, 54, 35),
(341, 54, 44),
(342, 55, 2),
(343, 55, 1),
(344, 55, 12),
(345, 55, 10),
(346, 55, 43),
(347, 55, 45),
(348, 56, 3),
(349, 56, 6),
(350, 56, 26),
(351, 56, 44),
(352, 56, 45),
(353, 56, 42),
(354, 57, 24),
(355, 57, 19),
(356, 57, 26),
(357, 57, 9),
(358, 57, 5),
(359, 57, 15),
(360, 58, 16),
(361, 58, 17),
(362, 58, 40),
(363, 58, 26),
(364, 58, 10),
(365, 58, 11),
(366, 59, 13),
(367, 59, 48),
(368, 59, 33),
(369, 59, 34),
(370, 59, 42),
(371, 59, 37),
(372, 60, 36),
(373, 60, 38),
(374, 60, 42),
(375, 60, 35),
(376, 60, 10),
(377, 60, 11),
(378, 61, 13),
(379, 61, 24),
(380, 61, 16),
(381, 61, 5),
(382, 61, 15),
(383, 61, 11),
(384, 62, 42),
(385, 62, 10),
(386, 62, 11),
(387, 62, 34),
(388, 62, 26),
(389, 62, 45),
(423, 97, 1),
(424, 97, 21),
(425, 97, 15),
(426, 97, 5),
(427, 97, 20),
(428, 97, 4),
(429, 97, 9),
(430, 98, 48),
(431, 98, 33),
(432, 98, 35),
(433, 98, 22),
(434, 98, 6),
(471, 102, 18),
(472, 102, 27),
(473, 102, 31),
(474, 102, 7),
(475, 102, 39);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Historial`
--

CREATE TABLE `Historial` (
  `ID_Historial` int(10) UNSIGNED NOT NULL,
  `ID_Usuario` int(10) UNSIGNED NOT NULL,
  `ID_Ejercicio` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `hora` time DEFAULT NULL,
  `peso` decimal(6,3) NOT NULL,
  `repeticiones` int(3) NOT NULL,
  `series` int(3) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Historial`
--

INSERT INTO `Historial` (`ID_Historial`, `ID_Usuario`, `ID_Ejercicio`, `fecha`, `hora`, `peso`, `repeticiones`, `series`) VALUES
(1, 1, 1, '2026-08-01', '19:00:22', 80.000, 8, 3),
(2, 1, 3, '2026-08-01', '15:04:11', 75.000, 10, 2),
(3, 1, 12, '2026-08-01', '11:19:49', 80.000, 12, 2),
(5, 1, 18, '2026-08-01', '18:26:32', 25.000, 8, 2),
(6, 1, 19, '2026-08-01', '09:13:13', 20.000, 8, 2),
(7, 1, 5, '2026-08-01', '11:46:30', 15.000, 12, 3),
(13, 1, 4, '2026-08-01', '10:12:53', 20.000, 10, 3),
(34, 1, 14, '2026-08-02', '08:44:55', 20.000, 10, 3),
(35, 1, 23, '2026-08-02', '20:05:57', 50.000, 10, 2),
(36, 1, 33, '2026-08-02', '11:14:59', 70.000, 12, 3),
(38, 1, 37, '2026-08-02', '16:57:06', 30.000, 10, 2),
(39, 1, 36, '2026-08-02', '16:00:33', 30.000, 12, 1),
(40, 1, 2, '2026-08-03', '08:11:26', 80.000, 12, 3),
(41, 1, 3, '2026-08-03', '13:55:32', 70.000, 10, 2),
(42, 1, 6, '2026-08-03', '10:03:24', 30.000, 8, 2),
(43, 1, 10, '2026-08-03', '15:30:25', 25.000, 7, 1),
(44, 1, 44, '2026-08-03', '12:21:54', 10.000, 20, 2),
(45, 2, 4, '2026-08-04', '08:18:15', 15.000, 10, 6),
(46, 2, 7, '2026-08-04', '11:25:34', 20.000, 8, 3),
(47, 2, 2, '2026-08-04', '11:13:05', 110.000, 10, 3),
(48, 2, 1, '2026-08-04', '14:48:45', 80.000, 8, 4),
(49, 2, 12, '2026-08-04', '19:24:31', 90.000, 5, 3),
(50, 2, 10, '2026-08-04', '17:36:20', 0.000, 1, 3),
(51, 2, 45, '2026-08-04', '08:48:06', 15.000, 50, 4),
(52, 1, 48, '2026-08-04', '12:11:32', 70.000, 12, 3),
(53, 1, 33, '2026-08-04', '13:33:23', 55.000, 10, 3),
(54, 1, 35, '2026-08-04', '10:12:18', 100.000, 16, 3),
(55, 1, 22, '2026-08-04', '17:21:20', 25.000, 8, 2),
(56, 1, 6, '2026-08-04', '07:09:49', 15.000, 12, 3),
(57, 1, 1, '2026-08-05', '18:45:03', 80.000, 6, 2),
(58, 1, 21, '2026-08-05', '09:15:49', 24.000, 10, 3),
(59, 1, 15, '2026-08-05', '11:03:56', 35.000, 12, 2),
(60, 1, 5, '2026-08-05', '20:32:13', 15.000, 14, 2),
(61, 1, 20, '2026-08-05', '20:29:20', 80.000, 12, 2),
(63, 1, 9, '2026-08-05', '19:49:59', 10.000, 12, 3),
(64, 2, 18, '2026-08-06', '16:41:57', 10.000, 13, 3),
(65, 2, 27, '2026-08-06', '16:59:50', 30.000, 112, 12),
(66, 2, 31, '2026-08-06', '13:53:19', 12.000, 45, 12),
(68, 2, 39, '2026-08-06', '11:27:04', 20.000, 10, 3),
(69, 3, 21, '2026-08-06', '08:35:23', 50.000, 12, 3),
(70, 3, 8, '2026-08-06', '15:35:46', 20.000, 10, 2),
(71, 3, 17, '2026-08-06', '17:12:40', 10.000, 20, 3),
(72, 3, 42, '2026-08-06', '18:16:04', 30.000, 12, 2),
(73, 3, 44, '2026-08-06', '18:42:20', 50.000, 12, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Rutina`
--

CREATE TABLE `Rutina` (
  `ID_Rutina` int(11) UNSIGNED NOT NULL,
  `ID_Clase` int(10) UNSIGNED DEFAULT NULL,
  `ID_Usuario` int(10) UNSIGNED DEFAULT NULL,
  `nombre` varchar(50) DEFAULT NULL,
  `descripcion` varchar(250) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `Rutina`
--

INSERT INTO `Rutina` (`ID_Rutina`, `ID_Clase`, `ID_Usuario`, `nombre`, `descripcion`) VALUES
(1, 1, NULL, 'Rutina general de tren superior', 'Sesión de 60 minutos centrada en pecho, espalda, hombros y brazos. Incluye calentamiento articular, un bloque principal de fuerza con empujes y tracciones, trabajo accesorio para core y una vuelta a la calma con estiramientos y movilidad de hombro.'),
(2, 1, NULL, 'Rutina general de tren inferior', 'Sesión de 60 minutos orientada a cuádriceps, isquios, glúteos y gemelos. Comienza con movilidad de cadera y tobillo, sigue con un bloque de fuerza bilateral y unilateral, trabajo de estabilidad de rodilla y termina con estiramientos y ejercicios suav'),
(3, 1, NULL, 'Tren superior – Intermedia', 'Rutina universal de tren superior enfocada en empujes y tirones, con bloques de trabajo pesado al inicio y series más ligeras al final para acumular volumen manteniendo una técnica controlada.'),
(4, 1, NULL, 'Tren inferior – Fuerza y estabilidad', 'Rutina universal de tren inferior orientada a mejorar fuerza en cadera y rodillas, combinando patrones de sentadilla, bisagra de cadera y trabajo unilateral para equilibrar ambas piernas.'),
(5, 1, NULL, 'Full body – Fuerza general', 'Rutina universal de cuerpo completo que alterna ejercicios de tren superior, tren inferior y core en forma de circuito, priorizando movimientos compuestos para aprovechar mejor el tiempo de entrenamiento.'),
(6, 1, NULL, 'Full body – Resistencia y control', 'Rutina universal de cuerpo completo centrada en series de moderada duración, con énfasis en control del movimiento y activación del core para mejorar resistencia muscular global.'),
(7, 2, NULL, 'Tren superior de potencia', 'Rutina enfocada en desarrollar potencia y velocidad en movimientos de empuje y tracción. Incluye bloques cortos de fuerza explosiva, trabajo técnico con cargas moderadas y series complementarias para mejorar estabilidad del hombro y control escapular'),
(8, 2, NULL, 'Tren superior de fuerza técnica', 'Sesión orientada a mejorar la fuerza máxima mediante movimientos principales de empuje y tirón. Incluye progresiones controladas, trabajo de tempo para mejorar mecánica y un bloque final de accesorios específicos para prevenir lesiones.'),
(9, 2, NULL, 'Tren inferior de potencia y aceleraci', 'Programa centrado en desarrollar potencia en piernas mediante saltos controlados, patrones de sentadilla y movimientos bilaterales y unilaterales. Finaliza con ejercicios de estabilidad de tobillo y rodilla para optimizar el rendimiento deportivo.'),
(10, 2, NULL, 'Tren inferior de fuerza funcional', 'Entrenamiento estructurado para mejorar fuerza y coordinación mediante patrones fundamentales como sentadilla, bisagra y zancada. Incluye trabajo unilateral para corregir asimetrías y un bloque final de fuerza isométrica para estabilidad.'),
(11, 2, NULL, 'Full body de acondicionamiento', 'Rutina integral enfocada en mejorar resistencia muscular, coordinación y eficiencia del movimiento mediante circuitos de cuerpo completo. Incluye alternancia de tren superior, inferior y core con descansos controlados para mantener la intensidad.'),
(12, 2, NULL, 'Full body de fuerza atlética', 'Plan orientado al desarrollo global de fuerza utilizando movimientos compuestos y progresiones atléticas. Se combinan levantamientos básicos con ejercicios funcionales para mejorar potencia, estabilidad central y control corporal.'),
(13, 3, NULL, 'Tren superior – Tonificación básica', 'Rutina de tren superior orientada a mejorar la definición muscular con cargas moderadas y altas repeticiones en pecho, espalda, hombros y brazos.'),
(14, 3, NULL, 'Tren superior – Tonificación avanzada', 'Programa de tren superior para usuarios intermedios, centrado en aumentar la resistencia muscular y la definición mediante superseries y tiempos de tensión prolongados.'),
(15, 3, NULL, 'Tren inferior – Tonificación general', 'Rutina de tren inferior enfocada en tonificar cuádriceps, isquiotibiales y glúteos con ejercicios guiados y controlados.'),
(16, 3, NULL, 'Tren inferior – Glúteos y piernas', 'Programa de tren inferior orientado a la definición de glúteos y piernas con énfasis en sentadillas, zancadas y trabajo unilateral.'),
(17, 3, NULL, 'Full body – Tonificación global', 'Rutina de cuerpo completo que combina ejercicios multiarticulares y de core para mejorar la composición corporal y la resistencia muscular.'),
(18, 3, NULL, 'Full body – Core y definición', 'Plan full body con foco en la activación del core y la tonificación general, alternando ejercicios de empuje, tracción y trabajo abdominal.'),
(19, 4, NULL, 'Tren superior – Hipertrofia de pecho y tríceps', 'Rutina orientada al aumento de masa muscular en los pectorales y tríceps mediante ejercicios de empuje.'),
(20, 4, NULL, 'Tren superior – Hipertrofia de espalda y bíceps', 'Programa enfocado en el desarrollo de dorsales, romboides y bíceps mediante ejercicios de tracción.'),
(21, 4, NULL, 'Tren inferior – Hipertrofia de cuádriceps', 'Rutina diseñada para desarrollar fuerza e hipertrofia en cuádriceps mediante ejercicios básicos y máquinas.'),
(22, 4, NULL, 'Tren inferior – Hipertrofia de glúteos y femorales', 'Programa destinado al aumento de volumen en glúteos e isquiotibiales con énfasis en movimientos de empuje de cadera.'),
(23, 4, NULL, 'Full body – Hipertrofia general', 'Rutina que combina ejercicios multiarticulares para aumentar la masa muscular global.'),
(24, 4, NULL, 'Full body – Hipertrofia avanzada', 'Programa full body con mayor volumen y variación, orientado a usuarios intermedios/avanzados.'),
(25, 5, NULL, 'Tren superior – Fuerza y movilidad básica', 'Rutina suave enfocada en mejorar movilidad de hombros y fuerza básica en pecho, espalda y brazos utilizando ejercicios guiados.'),
(26, 5, NULL, 'Tren superior – Resistencia y estabilidad', 'Programa orientado a desarrollar resistencia muscular y mejorar la estabilidad del tren superior con movimientos controlados.'),
(27, 5, NULL, 'Tren inferior – Fuerza para la vida diaria', 'Rutina enfocada en fortalecer piernas y mejorar la capacidad funcional para tareas cotidianas como subir escaleras o levantarse de una silla.'),
(28, 5, NULL, 'Tren inferior – Movilidad y glúteos', 'Entrenamiento suave de piernas y glúteos con énfasis en movilidad de cadera y control del movimiento.'),
(29, 5, NULL, 'Full body – Funcional básico', 'Rutina de cuerpo completo diseñada para mantener la movilidad general, estabilidad y fuerza ligera.'),
(30, 5, NULL, 'Full body – Estabilidad y core', 'Programa para adultos mayores centrado en mejorar equilibrio, estabilidad del core y movimientos seguros de todo el cuerpo.'),
(33, 6, NULL, 'Tren superior – Fuerza postural', 'Rutina enfocada en mejorar la fuerza de espalda, hombros y core para mantener buena postura en deportes de resistencia.'),
(34, 6, NULL, 'Tren superior – Estabilidad y resistencia', 'Programa orientado a resistencia muscular del tren superior y estabilidad escapular para soportar volúmenes altos de entrenamiento.'),
(35, 6, NULL, 'Tren inferior – Fuerza para running y ciclismo', 'Rutina diseñada para potenciar cuádriceps, femorales y gemelos, mejorando la eficiencia en carrera y pedaleo.'),
(36, 6, NULL, 'Tren inferior – Estabilidad de cadera y rodilla', 'Entrenamiento con énfasis en control unilateral, estabilidad de cadera y prevención de lesiones.'),
(37, 6, NULL, 'Full body – Fuerza funcional para resistencia', 'Rutina de cuerpo completo que combina fuerza funcional y trabajo de core para atletas de resistencia.'),
(38, 6, NULL, 'Full body – Core y estabilidad avanzada', 'Programa con enfoque en zona media y control corporal en movimientos multiarticulares.'),
(39, 7, NULL, 'Powerlifting – Press banca pesado', 'Rutina centrada en el press de banca como movimiento principal, con ejercicios accesorios para tríceps, hombros y espalda.'),
(40, 7, NULL, 'Powerlifting – Empuje y estabilidad superior', 'Programa orientado a mejorar la fuerza de empuje del tren superior y la estabilidad escapular.'),
(41, 7, NULL, 'Powerlifting – Sentadilla pesada', 'Rutina enfocada en la sentadilla como levantamiento principal, con trabajo accesorio para piernas y core.'),
(42, 7, NULL, 'Powerlifting – Peso muerto', 'Programa centrado en el peso muerto, fortaleciendo cadena posterior y estabilidad lumbar.'),
(43, 7, NULL, 'Powerlifting – Día A (sentadilla + banca)', 'Rutina full body que combina sentadilla y press de banca como levantamientos principales.'),
(44, 7, NULL, 'Powerlifting – Día B (peso muerto + banca)', 'Rutina full body enfocada en peso muerto y press de banca, con accesorios de soporte.'),
(45, 8, NULL, 'Funcional – Tren superior y core', 'Rutina funcional de tren superior con énfasis en estabilidad, empuje, tracción y activación del core.'),
(46, 8, NULL, 'Funcional – Tren superior dinámico', 'Entrenamiento dinámico de tren superior enfocado en coordinación, control y resistencia muscular.'),
(47, 8, NULL, 'Funcional – Tren inferior y estabilidad', 'Rutina funcional de piernas orientada a estabilidad, fuerza unilateral y control de cadera.'),
(48, 8, NULL, 'Funcional – Tren inferior dinámico', 'Entrenamiento funcional de tren inferior con movimientos globales y énfasis en coordinación.'),
(49, 8, NULL, 'Funcional – Full body básico', 'Rutina funcional de cuerpo completo con movimientos naturales y trabajo de core.'),
(50, 8, NULL, 'Funcional – Full body avanzado', 'Programa funcional full body de mayor exigencia con ejercicios dinámicos y multiarticulares.'),
(51, 9, NULL, 'Combate – Tren superior y potencia', 'Rutina de tren superior orientada a fuerza, potencia y resistencia para deportes de combate.'),
(52, 9, NULL, 'Combate – Tren superior y estabilidad', 'Entrenamiento enfocado en estabilidad de hombros, espalda y brazos para golpes y agarres.'),
(53, 9, NULL, 'Combate – Tren inferior y potencia', 'Rutina para desarrollar potencia y estabilidad en piernas, base fundamental en deportes de combate.'),
(54, 9, NULL, 'Combate – Tren inferior y desplazamientos', 'Programa orientado a fuerza unilateral, coordinación y desplazamientos rápidos.'),
(55, 9, NULL, 'Combate – Full body funcional', 'Rutina de cuerpo completo con énfasis en fuerza funcional, core y resistencia.'),
(56, 9, NULL, 'Combate – Full body potencia y core', 'Entrenamiento full body enfocado en potencia, estabilidad y rotación del tronco.'),
(57, 10, NULL, 'Rehabilitación – Tren superior y movilidad', 'Rutina suave de tren superior enfocada en movilidad articular y fortalecimiento progresivo.'),
(58, 10, NULL, 'Rehabilitación – Tren superior y estabilidad', 'Entrenamiento de estabilidad de hombros y espalda con ejercicios guiados y controlados.'),
(59, 10, NULL, 'Rehabilitación – Tren inferior básico', 'Rutina de fortalecimiento progresivo de piernas orientada a la recuperación funcional.'),
(60, 10, NULL, 'Rehabilitación – Cadera y rodilla', 'Programa enfocado en estabilidad de cadera y rodilla para prevenir recaídas.'),
(61, 10, NULL, 'Rehabilitación – Full body funcional', 'Rutina de cuerpo completo orientada a la recuperación general y control del movimiento.'),
(62, 10, NULL, 'Rehabilitación – Full body y core', 'Entrenamiento full body con énfasis en el core, equilibrio y estabilidad general.'),
(97, 1, 1, 'Tren superior', 'Personalizada'),
(98, 1, 1, 'Tren Inferior', 'Personalizada'),
(102, 1, 2, 'Tren superior – Resistencia y estabilidad (Copia)', 'Personalizada');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Usuario`
--

CREATE TABLE `Usuario` (
  `ID_Usuario` int(10) UNSIGNED NOT NULL,
  `ID_Clase` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(30) DEFAULT NULL,
  `apellido` varchar(30) DEFAULT NULL,
  `peso` decimal(5,1) UNSIGNED NOT NULL,
  `altura` decimal(5,3) NOT NULL,
  `DNI` varchar(20) DEFAULT NULL,
  `fecha_nacimiento` date NOT NULL,
  `fecha_alta` date DEFAULT NULL,
  `sexo` enum('M','F') NOT NULL,
  `mail` varchar(40) DEFAULT NULL,
  `telefono` varchar(30) NOT NULL,
  `clave` varchar(250) DEFAULT NULL,
  `foto` varchar(260) NOT NULL,
  `es_admin` enum('S','N') NOT NULL DEFAULT 'N'
) ;

--
-- Volcado de datos para la tabla `Usuario`
--

INSERT INTO `Usuario` (`ID_Usuario`, `ID_Clase`, `nombre`, `apellido`, `peso`, `altura`, `DNI`, `fecha_nacimiento`, `fecha_alta`, `sexo`, `mail`, `telefono`, `clave`, `foto`, `es_admin`) VALUES
(1, 4, 'Demo', 'Uno', 76.0, 1.750, '10000001', '2000-01-01', '2026-01-01', 'M', 'demo1@example.com', '1111111111', '$2y$10$fSKglXfYq0RE7sAERNoFF.ZzsE.UW4L92fW9OdxLHqM4Jk4dILtXa', 'default.jpg', 'N'),
(2, 1, 'Demo', 'Dos', 62.0, 1.650, '10000002', '2000-02-02', '2026-01-01', 'F', 'demo2@example.com', '2222222222', '$2y$10$VMx5rEAFJfQz85WweFkxH.mvru.OemVGSWoxVTMpyOd0RT/pEelnW', 'default.jpg', 'N'),
(3, 2, 'Demo', 'Admin', 80.0, 1.800, '10000003', '1990-03-03', '2026-01-01', 'M', 'admin@example.com', '3333333333', '$2y$10$DfzLZiawUp/janmxSZIL0Okng4JsgWiEkRpG6TXzK0u.tTw0O7lFm', 'default.jpg', 'S');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `Usuario_RutinaDia`
--

CREATE TABLE `Usuario_RutinaDia` (
  `ID_URD` int(10) UNSIGNED NOT NULL,
  `ID_Usuario` int(10) UNSIGNED NOT NULL,
  `ID_Rutina` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `Usuario_RutinaDia`
--

INSERT INTO `Usuario_RutinaDia` (`ID_URD`, `ID_Usuario`, `ID_Rutina`) VALUES
(9, 2, 55),
(17, 1, 97),
(18, 1, 98),
(20, 1, 5),
(21, 1, 6),
(24, 2, 29),
(25, 2, 6),
(26, 2, 102),
(27, 3, 6);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `Clase`
--
ALTER TABLE `Clase`
  ADD PRIMARY KEY (`ID_Clase`);

--
-- Indices de la tabla `Ejercicio`
--
ALTER TABLE `Ejercicio`
  ADD PRIMARY KEY (`ID_Ejercicio`);

--
-- Indices de la tabla `Ejercicio_ExerciseDB`
--
ALTER TABLE `Ejercicio_ExerciseDB`
  ADD PRIMARY KEY (`exercisedb_id`),
  ADD UNIQUE KEY `uq_eedb_ejercicio` (`ID_Ejercicio`);

--
-- Indices de la tabla `Ejercitacion`
--
ALTER TABLE `Ejercitacion`
  ADD PRIMARY KEY (`ID_Ejercitacion`),
  ADD KEY `ix_ejercitacion_rutina` (`ID_Rutina`),
  ADD KEY `ix_ejercitacion_ejercicio` (`ID_Ejercicio`);

--
-- Indices de la tabla `Historial`
--
ALTER TABLE `Historial`
  ADD PRIMARY KEY (`ID_Historial`),
  ADD UNIQUE KEY `uk_historial_user_exercise_date` (`ID_Usuario`,`ID_Ejercicio`,`fecha`),
  ADD KEY `fk_historial_ejercicio` (`ID_Ejercicio`);

--
-- Indices de la tabla `Rutina`
--
ALTER TABLE `Rutina`
  ADD PRIMARY KEY (`ID_Rutina`),
  ADD KEY `ix_rutina_id_clase` (`ID_Clase`),
  ADD KEY `fk_rutina_usuario` (`ID_Usuario`);

--
-- Indices de la tabla `Usuario`
--
ALTER TABLE `Usuario`
  ADD PRIMARY KEY (`ID_Usuario`),
  ADD UNIQUE KEY `uk_usuario_mail` (`mail`),
  ADD UNIQUE KEY `uq_usuario_dni` (`DNI`),
  ADD KEY `ix_usuario_id_clase` (`ID_Clase`);

--
-- Indices de la tabla `Usuario_RutinaDia`
--
ALTER TABLE `Usuario_RutinaDia`
  ADD PRIMARY KEY (`ID_URD`),
  ADD KEY `fk_urd_rutina` (`ID_Rutina`),
  ADD KEY `idx_urd_usuario` (`ID_Usuario`),
  ADD KEY `idx_urd_rutina` (`ID_Rutina`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `Clase`
--
ALTER TABLE `Clase`
  MODIFY `ID_Clase` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

--
-- AUTO_INCREMENT de la tabla `Ejercicio`
--
ALTER TABLE `Ejercicio`
  MODIFY `ID_Ejercicio` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT de la tabla `Ejercitacion`
--
ALTER TABLE `Ejercitacion`
  MODIFY `ID_Ejercitacion` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=476;

--
-- AUTO_INCREMENT de la tabla `Historial`
--
ALTER TABLE `Historial`
  MODIFY `ID_Historial` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=74;

--
-- AUTO_INCREMENT de la tabla `Rutina`
--
ALTER TABLE `Rutina`
  MODIFY `ID_Rutina` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;

--
-- AUTO_INCREMENT de la tabla `Usuario`
--
ALTER TABLE `Usuario`
  MODIFY `ID_Usuario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `Usuario_RutinaDia`
--
ALTER TABLE `Usuario_RutinaDia`
  MODIFY `ID_URD` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `Ejercicio_ExerciseDB`
--
ALTER TABLE `Ejercicio_ExerciseDB`
  ADD CONSTRAINT `fk_eedb_ejercicio` FOREIGN KEY (`ID_Ejercicio`) REFERENCES `Ejercicio` (`ID_Ejercicio`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `Ejercitacion`
--
ALTER TABLE `Ejercitacion`
  ADD CONSTRAINT `fk_ejercitacion_ejercicio` FOREIGN KEY (`ID_Ejercicio`) REFERENCES `Ejercicio` (`ID_Ejercicio`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ejercitacion_rutina` FOREIGN KEY (`ID_Rutina`) REFERENCES `Rutina` (`ID_Rutina`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `Historial`
--
ALTER TABLE `Historial`
  ADD CONSTRAINT `fk_historial_ejercicio` FOREIGN KEY (`ID_Ejercicio`) REFERENCES `Ejercicio` (`ID_Ejercicio`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_historial_usuario` FOREIGN KEY (`ID_Usuario`) REFERENCES `Usuario` (`ID_Usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `Rutina`
--
ALTER TABLE `Rutina`
  ADD CONSTRAINT `fk_rutina_clase` FOREIGN KEY (`ID_Clase`) REFERENCES `Clase` (`ID_Clase`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rutina_usuario` FOREIGN KEY (`ID_Usuario`) REFERENCES `Usuario` (`ID_Usuario`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `Usuario`
--
ALTER TABLE `Usuario`
  ADD CONSTRAINT `fk_usuario_clase` FOREIGN KEY (`ID_Clase`) REFERENCES `Clase` (`ID_Clase`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `Usuario_RutinaDia`
--
ALTER TABLE `Usuario_RutinaDia`
  ADD CONSTRAINT `fk_urd_rutina` FOREIGN KEY (`ID_Rutina`) REFERENCES `Rutina` (`ID_Rutina`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_urd_usuario` FOREIGN KEY (`ID_Usuario`) REFERENCES `Usuario` (`ID_Usuario`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
