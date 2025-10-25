-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 25-10-2025 a las 03:21:27
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
-- Base de datos: `pl`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `afiliacion`
--

CREATE TABLE `afiliacion` (
  `id_afiliacion` int(11) NOT NULL,
  `nombre_afiliacion` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `bibliotecario`
--

CREATE TABLE `bibliotecario` (
  `id_bibliotecario` int(11) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `cedula` varchar(12) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `es_administrador` tinyint(1) DEFAULT 0,
  `intentos_fallidos` int(11) NOT NULL DEFAULT 0,
  `ultimo_intento` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `carrera`
--

CREATE TABLE `carrera` (
  `id_carrera` int(11) NOT NULL,
  `nombre_carrera` varchar(100) NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1: Activa, 0: Desactivada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categoria`
--

CREATE TABLE `categoria` (
  `id_categoria` int(11) NOT NULL,
  `nombre_categoria` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `computadora`
--

CREATE TABLE `computadora` (
  `id_computadora` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1: Disponible, 0: Reservada, 2: Mantenimiento, 3: Desactivada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `facultad`
--

CREATE TABLE `facultad` (
  `id_facultad` int(11) NOT NULL,
  `nombre_facultad` varchar(100) NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1: Activa, 0: Desactivada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `facultadcarrera`
--

CREATE TABLE `facultadcarrera` (
  `id_facultad` int(11) NOT NULL,
  `id_carrera` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `libro`
--

CREATE TABLE `libro` (
  `id_libro` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `autor` varchar(150) NOT NULL,
  `codigo_unico` varchar(20) NOT NULL,
  `id_categoria` int(11) DEFAULT NULL,
  `disponible` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1: Disponible, 0: Reservado, 2: Desactivado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservacomputadora`
--

CREATE TABLE `reservacomputadora` (
  `id_reserva_pc` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `id_computadora` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `id_turno` int(11) NOT NULL,
  `id_tipo_uso` int(11) NOT NULL,
  `hora_entrada` time NOT NULL,
  `hora_salida` time DEFAULT NULL,
  `origen` varchar(20) NOT NULL DEFAULT 'cliente' COMMENT 'Origen de la reserva: cliente o admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reservalibro`
--

CREATE TABLE `reservalibro` (
  `id_reserva_libro` int(11) NOT NULL,
  `id_libro` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `id_tipo_reserva` int(11) NOT NULL,
  `id_turno` int(11) NOT NULL,
  `hora_entrega` time DEFAULT NULL,
  `fecha_hora_devolucion` datetime DEFAULT NULL,
  `origen` varchar(20) NOT NULL DEFAULT 'cliente' COMMENT 'Origen de la reserva: cliente o admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tiporeserva`
--

CREATE TABLE `tiporeserva` (
  `id_tipo_reserva` int(11) NOT NULL,
  `nombre_tipo_reserva` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipouso`
--

CREATE TABLE `tipouso` (
  `id_tipo_uso` int(11) NOT NULL,
  `nombre_tipo_uso` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipousuario`
--

CREATE TABLE `tipousuario` (
  `id_tipo_usuario` int(11) NOT NULL,
  `nombre_tipo_usuario` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turno`
--

CREATE TABLE `turno` (
  `id_turno` int(11) NOT NULL,
  `nombre_turno` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `cedula` varchar(12) NOT NULL,
  `id_afiliacion` int(11) NOT NULL,
  `id_tipo_usuario` int(11) DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_externo_detalle`
--

CREATE TABLE `usuario_externo_detalle` (
  `id_usuario` int(11) NOT NULL,
  `universidad_externa` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_interno_detalle`
--

CREATE TABLE `usuario_interno_detalle` (
  `id_usuario` int(11) NOT NULL,
  `id_facultad` int(11) NOT NULL,
  `id_carrera` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_particular_detalle`
--

CREATE TABLE `usuario_particular_detalle` (
  `id_usuario` int(11) NOT NULL,
  `celular` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `afiliacion`
--
ALTER TABLE `afiliacion`
  ADD PRIMARY KEY (`id_afiliacion`);

--
-- Indices de la tabla `bibliotecario`
--
ALTER TABLE `bibliotecario`
  ADD PRIMARY KEY (`id_bibliotecario`),
  ADD UNIQUE KEY `cedula` (`cedula`);

--
-- Indices de la tabla `carrera`
--
ALTER TABLE `carrera`
  ADD PRIMARY KEY (`id_carrera`);

--
-- Indices de la tabla `categoria`
--
ALTER TABLE `categoria`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Indices de la tabla `computadora`
--
ALTER TABLE `computadora`
  ADD PRIMARY KEY (`id_computadora`),
  ADD UNIQUE KEY `numero` (`numero`);

--
-- Indices de la tabla `facultad`
--
ALTER TABLE `facultad`
  ADD PRIMARY KEY (`id_facultad`);

--
-- Indices de la tabla `facultadcarrera`
--
ALTER TABLE `facultadcarrera`
  ADD PRIMARY KEY (`id_facultad`,`id_carrera`),
  ADD KEY `id_carrera` (`id_carrera`);

--
-- Indices de la tabla `libro`
--
ALTER TABLE `libro`
  ADD PRIMARY KEY (`id_libro`),
  ADD UNIQUE KEY `codigo_unico` (`codigo_unico`),
  ADD KEY `id_categoria` (`id_categoria`),
  ADD KEY `idx_libro_titulo` (`titulo`),
  ADD KEY `idx_libro_autor` (`autor`),
  ADD KEY `idx_libro_codigo_unico` (`codigo_unico`),
  ADD KEY `idx_libro_categoria` (`id_categoria`),
  ADD KEY `idx_libro_disponible` (`disponible`),
  ADD KEY `idx_libro_busqueda` (`titulo`,`autor`,`codigo_unico`);

--
-- Indices de la tabla `reservacomputadora`
--
ALTER TABLE `reservacomputadora`
  ADD PRIMARY KEY (`id_reserva_pc`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_computadora` (`id_computadora`),
  ADD KEY `id_turno` (`id_turno`),
  ADD KEY `id_tipo_uso` (`id_tipo_uso`);

--
-- Indices de la tabla `reservalibro`
--
ALTER TABLE `reservalibro`
  ADD PRIMARY KEY (`id_reserva_libro`),
  ADD KEY `id_libro` (`id_libro`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_tipo_reserva` (`id_tipo_reserva`),
  ADD KEY `reservalibro_ibfk_5` (`id_turno`);

--
-- Indices de la tabla `tiporeserva`
--
ALTER TABLE `tiporeserva`
  ADD PRIMARY KEY (`id_tipo_reserva`);

--
-- Indices de la tabla `tipouso`
--
ALTER TABLE `tipouso`
  ADD PRIMARY KEY (`id_tipo_uso`);

--
-- Indices de la tabla `tipousuario`
--
ALTER TABLE `tipousuario`
  ADD PRIMARY KEY (`id_tipo_usuario`);

--
-- Indices de la tabla `turno`
--
ALTER TABLE `turno`
  ADD PRIMARY KEY (`id_turno`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `cedula` (`cedula`),
  ADD KEY `id_tipo_usuario` (`id_tipo_usuario`),
  ADD KEY `usuario_ibfk_4` (`id_afiliacion`);

--
-- Indices de la tabla `usuario_externo_detalle`
--
ALTER TABLE `usuario_externo_detalle`
  ADD PRIMARY KEY (`id_usuario`);

--
-- Indices de la tabla `usuario_interno_detalle`
--
ALTER TABLE `usuario_interno_detalle`
  ADD PRIMARY KEY (`id_usuario`),
  ADD KEY `fk_detalle_interno_facultad` (`id_facultad`),
  ADD KEY `fk_detalle_interno_carrera` (`id_carrera`);

--
-- Indices de la tabla `usuario_particular_detalle`
--
ALTER TABLE `usuario_particular_detalle`
  ADD PRIMARY KEY (`id_usuario`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `afiliacion`
--
ALTER TABLE `afiliacion`
  MODIFY `id_afiliacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `bibliotecario`
--
ALTER TABLE `bibliotecario`
  MODIFY `id_bibliotecario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `carrera`
--
ALTER TABLE `carrera`
  MODIFY `id_carrera` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categoria`
--
ALTER TABLE `categoria`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `computadora`
--
ALTER TABLE `computadora`
  MODIFY `id_computadora` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `facultad`
--
ALTER TABLE `facultad`
  MODIFY `id_facultad` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `libro`
--
ALTER TABLE `libro`
  MODIFY `id_libro` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reservacomputadora`
--
ALTER TABLE `reservacomputadora`
  MODIFY `id_reserva_pc` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reservalibro`
--
ALTER TABLE `reservalibro`
  MODIFY `id_reserva_libro` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tiporeserva`
--
ALTER TABLE `tiporeserva`
  MODIFY `id_tipo_reserva` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipouso`
--
ALTER TABLE `tipouso`
  MODIFY `id_tipo_uso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tipousuario`
--
ALTER TABLE `tipousuario`
  MODIFY `id_tipo_usuario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `turno`
--
ALTER TABLE `turno`
  MODIFY `id_turno` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `facultadcarrera`
--
ALTER TABLE `facultadcarrera`
  ADD CONSTRAINT `facultadcarrera_ibfk_1` FOREIGN KEY (`id_facultad`) REFERENCES `facultad` (`id_facultad`),
  ADD CONSTRAINT `facultadcarrera_ibfk_2` FOREIGN KEY (`id_carrera`) REFERENCES `carrera` (`id_carrera`);

--
-- Filtros para la tabla `libro`
--
ALTER TABLE `libro`
  ADD CONSTRAINT `libro_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id_categoria`);

--
-- Filtros para la tabla `reservacomputadora`
--
ALTER TABLE `reservacomputadora`
  ADD CONSTRAINT `reservacomputadora_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `reservacomputadora_ibfk_2` FOREIGN KEY (`id_computadora`) REFERENCES `computadora` (`id_computadora`),
  ADD CONSTRAINT `reservacomputadora_ibfk_3` FOREIGN KEY (`id_turno`) REFERENCES `turno` (`id_turno`),
  ADD CONSTRAINT `reservacomputadora_ibfk_4` FOREIGN KEY (`id_tipo_uso`) REFERENCES `tipouso` (`id_tipo_uso`);

--
-- Filtros para la tabla `reservalibro`
--
ALTER TABLE `reservalibro`
  ADD CONSTRAINT `reservalibro_ibfk_1` FOREIGN KEY (`id_libro`) REFERENCES `libro` (`id_libro`),
  ADD CONSTRAINT `reservalibro_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `reservalibro_ibfk_4` FOREIGN KEY (`id_tipo_reserva`) REFERENCES `tiporeserva` (`id_tipo_reserva`),
  ADD CONSTRAINT `reservalibro_ibfk_5` FOREIGN KEY (`id_turno`) REFERENCES `turno` (`id_turno`);

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`id_tipo_usuario`) REFERENCES `tipousuario` (`id_tipo_usuario`),
  ADD CONSTRAINT `usuario_ibfk_4` FOREIGN KEY (`id_afiliacion`) REFERENCES `afiliacion` (`id_afiliacion`);

--
-- Filtros para la tabla `usuario_externo_detalle`
--
ALTER TABLE `usuario_externo_detalle`
  ADD CONSTRAINT `fk_detalle_externo_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuario_interno_detalle`
--
ALTER TABLE `usuario_interno_detalle`
  ADD CONSTRAINT `fk_detalle_interno_carrera` FOREIGN KEY (`id_carrera`) REFERENCES `carrera` (`id_carrera`),
  ADD CONSTRAINT `fk_detalle_interno_facultad` FOREIGN KEY (`id_facultad`) REFERENCES `facultad` (`id_facultad`),
  ADD CONSTRAINT `fk_detalle_interno_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuario_particular_detalle`
--
ALTER TABLE `usuario_particular_detalle`
  ADD CONSTRAINT `fk_detalle_particular_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
