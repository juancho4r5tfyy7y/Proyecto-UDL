DROP DATABASE IF EXISTS unidad_didactica;
CREATE DATABASE IF NOT EXISTS unidad_didactica DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE unidad_didactica;
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellidos VARCHAR(100),
    usuario VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    clave VARCHAR(255) NOT NULL,
    rol ENUM('superadmin', 'admin', 'estudiante') NOT NULL,
    tipo ENUM('CC', 'TI', 'CE', 'PP', 'NIT', 'PPT', 'Pasaporte') NOT NULL,
    documento VARCHAR(30) NOT NULL,
    contacto VARCHAR(50),
    nacimiento DATE,
    ultimo_acceso TIMESTAMP NULL,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS estudiantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo_documento ENUM('CC', 'TI', 'CE', 'PP', 'NIT', 'PPT', 'Pasaporte') NOT NULL,
    documento VARCHAR(30) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    apellido1 VARCHAR(100) NOT NULL,
    apellido2 VARCHAR(100),
    fecha_nacimiento DATE,
    grado ENUM('6','7','8','9','10','11'),
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS docentes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nombre_completo VARCHAR(100) NOT NULL,
    cargo_actual VARCHAR(100),
    titulos VARCHAR(255),
    area_desempeno VARCHAR(100),
    foto_url VARCHAR(255),
    fecha_cumpleanos DATE,
    biografia VARCHAR(255),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS docente_grados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    grado ENUM('6','7','8','9','10','11') NOT NULL,
    UNIQUE KEY (docente_id, grado),
    FOREIGN KEY (docente_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- eliminar o comentar CREATE TABLE grupos (si existe)

-- Nueva tabla 'grado' para almacenar entradas de estudiantes por grado
CREATE TABLE IF NOT EXISTS grado (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grado ENUM('6','7','8','9','10','11') NOT NULL,
    nombre_completo VARCHAR(200) NOT NULL,
    documento VARCHAR(50) NOT NULL,
    tipo_documento ENUM('CC', 'TI', 'CE', 'PP', 'NIT', 'PPT', 'Pasaporte') NOT NULL,
    usuario_id INT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE guias_trabajo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    titulo VARCHAR(100),
    descripcion TEXT,
    archivo_url VARCHAR(255),
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES docentes(id) ON DELETE CASCADE
);

CREATE TABLE material_didactico (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    tipo ENUM('video', 'imagen', 'pdf', 'otro') DEFAULT 'otro',
    titulo VARCHAR(100),
    descripcion VARCHAR(255),
    enlace_url VARCHAR(255),
    archivo_adjunto_url VARCHAR(255),
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES docentes(id) ON DELETE CASCADE
);

CREATE TABLE indicadores_periodo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    periodo VARCHAR(50) NOT NULL,
    indicador VARCHAR(255) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (docente_id) REFERENCES docentes(id) ON DELETE CASCADE
);

CREATE TABLE contenidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    indicador_id INT NOT NULL,
    tipo_contenido ENUM('derecho_basico', 'estandar') NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    FOREIGN KEY (indicador_id) REFERENCES indicadores_periodo(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS grupos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    docente_id INT NOT NULL,
    activo BOOLEAN DEFAULT 1,
    FOREIGN KEY (docente_id) REFERENCES docentes(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS grupo_docente (
    id INT AUTO_INCREMENT PRIMARY KEY,
    docente_id INT NOT NULL,
    grupo_id INT NOT NULL,
    FOREIGN KEY (docente_id) REFERENCES docentes(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    UNIQUE KEY (docente_id, grupo_id)
);

CREATE TABLE IF NOT EXISTS inscripciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    estudiante_id INT NOT NULL,
    grupo_id INT NOT NULL,
    fecha_inscripcion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (estudiante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    UNIQUE KEY (estudiante_id, grupo_id)
);

CREATE TABLE IF NOT EXISTS grupo_estudiante (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grupo_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    fecha_union TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE,
    UNIQUE KEY (grupo_id, estudiante_id)
);

CREATE TABLE IF NOT EXISTS unidades_tematicas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT
);

CREATE TABLE IF NOT EXISTS actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,
    grupo_id INT NOT NULL,
    unidad_tematica_id INT,
    fecha_limite DATETIME NOT NULL,
    tipo_actividad VARCHAR(50) NOT NULL,
    puntos_maximos DECIMAL(10, 2) NOT NULL,
    instrucciones TEXT,
    recursos_necesarios TEXT,
    criterios_evaluacion TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activa BOOLEAN DEFAULT 1,
    FOREIGN KEY (grupo_id) REFERENCES grupos(id) ON DELETE CASCADE,
    FOREIGN KEY (unidad_tematica_id) REFERENCES unidades_tematicas(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    estudiante_id INT NOT NULL,
    fecha_entrega TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    archivo_url VARCHAR(255),
    calificacion DECIMAL(5, 2),
    comentario_docente TEXT,
    FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    FOREIGN KEY (estudiante_id) REFERENCES estudiantes(id) ON DELETE CASCADE
);

-- Insertar usuarios desde el archivo usuarios.sql
INSERT INTO `usuarios` (`id`, `nombre`, `apellidos`, `usuario`, `email`, `clave`, `rol`, `tipo`, `documento`, `contacto`, `nacimiento`, `fecha_registro`) VALUES
(1, 'Yeferson ', 'Giraldo Cano', 'estu1', 'yefersongiraldocano@gmail.com', 'pass123', 'estudiante', 'TI', '2220333383', '3008228694', '2008-12-29', '2025-09-03 01:56:12'),
(2, 'Gedeón ', 'Martínez Atehortúa', 'estu2', 'gmartinezatehortua@gmail.com', 'pass123', 'estudiante', 'TI', '2240875208', '3221115399', '2007-10-13', '2025-09-03 02:06:36'),
(3, 'Jorge ', 'Hernandez Castaño', 'estu3', 'jorgenal008@gmail.com', 'pass123', 'estudiante', 'TI', '2034920745', '3212286914', '2008-11-30', '2025-09-03 02:14:04'),
(4, 'Juan Fernando', 'Velez Ayala', 'estu4', 'juanfernandovelez487@gmail.com', 'pass123', 'estudiante', 'CC', '2238358923', '3128127539', '2006-12-22', '2025-09-03 02:22:31'),
(5, 'Williams Manuel', 'Montiel Hurtado', 'estu5', 'williamsm050507@gmail.com', 'pass123', 'estudiante', 'PPT', '4667573', '3004929772', '2007-05-05', '2025-09-03 07:30:09'),
(6, 'Juan Jose', 'Olarte Florez', 'estu6', 'Juanjo75358282@gmail.com', 'pass123', 'estudiante', 'TI', '2096805074', '3122952728', '2009-05-07', '2025-09-03 07:34:18'),
(7, 'Alejandro', 'Correa Arias', 'estu7', 'acorreaarias6.2@gmail.com', 'pass123', 'estudiante', 'CC', '2311396570', '3011972970', '2007-06-25', '2025-09-03 07:40:06'),
(8, 'Cristian Camilo', 'Galeano Velasquez', 'estu8', 'camilogta719@gmail.com', 'pass123', 'estudiante', 'TI', '2223635063', '3214086568', '2008-06-25', '2025-09-04 00:32:14'),
(9, 'Santiago', 'Giraldo Cano', 'estu9', 'santiagogiraldocano02@gmail.com', 'pass123', 'estudiante', 'TI', '2218348060', '3002627373', '2011-02-13', '2025-09-04 00:35:57'),
(10, 'Letty', 'Contreras Ospitia', 'doce1', 'lcontrerarso@colsantander.edu.co', 'pass123', 'admin', 'CC', '65231795', '3284124120176', '1985-01-05', '2025-09-03 20:31:44'),
(11, 'Ender José ', 'Flores Ramos', 'estu10', 'ender.florese@colsantander.edu.co', 'pass123', 'estudiante', 'PPT', '2863048', '3052124886', '2007-06-21', '2025-09-08 18:28:58'),
(12, 'Jader Alexis ', 'Jaramillo Arango', 'estu11', 'jader.jaramilloa@colsantander.edu.co', 'pass123', 'estudiante', 'CC', '1037264604', '3204438327', '2007-04-18', '2025-09-08 18:49:16'),
(13, 'Jeronimo ', 'Londoño Congote', 'estu12', 'jeronimolondonocongote@gmail.com', 'pass123', 'estudiante', 'TI', '1223528170', '3148502419', '2009-03-23', '2025-09-08 19:31:21'),
(14, 'Jeison', 'Sossa Sierra', 'estu13', 'jeisonsossa88@gmail.com', 'pass123', 'estudiante', 'TI', '2222148127', '3215661010', '2008-02-05', '2025-09-08 19:35:31'),
(15, 'Leyder', 'Montoya Gonzales', 'estu14', 'leydermontoyag@gmail.com', 'pass123', 'estudiante', 'TI', '2233186373', '3127055625', '2008-10-08', '2025-09-08 19:42:42'),
(16, 'Juan Sebastian', 'Miranda Bohorquez', 'estu15', 'juansebastianmirandabohorquez@gmail.com', 'pass123', 'estudiante', 'TI', '1038115439', '3235029207', '2009-09-29', '2025-09-08 20:17:45'),
(17, 'Mateo', 'Isaza Muñoz', 'estu16', 'mateozm200566@gmail.com', 'pass123', 'estudiante', 'TI', '1228346554', '3233424412', '2009-09-29', '2025-09-08 20:22:13'),
(18, 'Mariana', 'Borja Cañaveral', 'estu18', 'mariana.borjac@colsantander.edu.co', 'pass123', 'estudiante', 'TI', '101159339', '3023239425', '2008-10-18', '2025-09-08 20:27:23'),
(19, 'Maria Isabel', 'Zapata Restrepo', 'doc2', 'mzapatar@colsantander.edu.co', 'pass123', 'admin', 'CC', '2213537279', '3015373790', '1994-11-26', '2025-09-08 20:31:51'),
(20, 'Gabriela', 'Garrido Galeano', 'estu19', 'gabrielagarridog@colsantander.edu.co', 'pass123', 'estudiante', 'TI', '1011401901', '3135217515', '2010-03-24', '2025-09-08 20:34:41'),
(21, 'Super', 'Admin', 'super1', 'superadmin@gmail.com', 'pass123', 'superadmin', 'TI', '12211401901', '3135217566', '2010-08-22', '1');
-- Insertar estudiantes correspondientes a los usuarios
INSERT INTO `estudiantes` (`usuario_id`, `tipo_documento`, `documento`, `nombre`, `apellido1`, `apellido2`, `fecha_nacimiento`, `grado`) VALUES
(1, 'TI', '2220333383', 'Yeferson', 'Giraldo', 'Cano', '2008-12-29', '11'),
(1, 'TI', '2220333383', 'Yeferson', 'Giraldo', 'Cano', '2008-12-29', '11'),
(2, 'TI', '2240875208', 'Gedeón', 'Martínez', 'Atehortúa', '2007-10-13', '11'),
(3, 'TI', '2234920745', 'Jorge', 'Hernandez', 'Castaño', '2008-11-30', '11'),
(4, 'CC', '2238358923', 'Juan Fernando', 'Velez', 'Ayala', '2006-12-22', '11'),
(5, 'PPT', '2863048', 'Williams Manuel', 'Montiel', 'Hurtado', '2007-05-05', '11'),
(6, 'TI', '2096805074', 'Juan Jose', 'Olarte', 'Florez', '2009-05-07', '11'),
(7, 'CC', '2311396570', 'Alejandro', 'Correa', 'Arias', '2007-06-25', '11'),
(8, 'TI', '2223635063', 'Cristian Camilo', 'Galeano', 'Velasquez', '2008-06-25', '11'),
(9, 'TI', '2218348060', 'Santiago', 'Giraldo', 'Cano', '2011-02-13', '7'),
(11, 'PPT', '2863048', 'Ender José ', 'Florez', 'Ramos', '2007-06-21', '11'),
(12, 'CC', '2237264604', 'Jader Alexis ', 'Jaramillo', 'Arango', '2007-04-18', '11'),
(13, 'TI', '2223528170', 'Jeronimo ', 'Londoño', 'Congote', '2009-03-23', '11'),
(14, 'TI', '2222148107', 'Jeison', 'Sossa', 'Sierra', '2008-02-05', '10'),
(15, 'TI', '2233186373', 'Leyder', 'Montoya', 'Gonzales', '2008-10-08', '10'),
(16, 'TI', '2238115439', 'Juan Sebastian', 'Miranda', 'Bohorquez', '2009-09-29', '10'),
(17, 'TI', '2218346554', 'Mateo', 'Isaza', 'Muñoz', '2009-09-29', '10'),
(18,'TI', '221159339', 'Mariana','Borja','Cañaveral','2008-10-18','10'),
(20,'TI','2211401901','Gabriela','Garrido','Galeano','2010-03-24','10'),

-- Insertar en la tabla grado
INSERT INTO `grado` (`grado`, `nombre_completo`, `documento`, `tipo_documento`, `usuario_id`) VALUES
('11', 'Yeferson Giraldo Cano', '2220333383', 'TI', 1 ),
('11', 'Gedeón Martínez Atehortúa', '2240875208', 'TI', 2 ),
('11', 'Jorge Hernandez Castaño', '2234920745', 'TI', 3 ),
('11', 'Juan Fernando Velez Ayala', '2238358923', 'CC', 4 ),
('11', 'Williams Manuel Montiel Hurtado', '2863048', 'PPT', 5 ),
('11', 'Juan Jose Olarte Florez', '2096805074', 'TI', 6 ),
('11', 'Alejandro Correa Arias', '2311396570', 'CC', 7 ),
('11', 'Cristian Camilo Galeano Velasquez', '2223635063', 'TI', 8 ),
('7', 'Santiago Giraldo Cano', '2218348060', 'TI', 9 ),
('11', 'Ender José  Florez Ramos', '2863048', 'PPT', 11 ),
('11', 'Jader Alexis  Jaramillo Arango', '2237264604', 'CC', 12 ),
('11', 'Jeronimo  Londoño Congote', '2223528170', 'TI', 13 ),
('10', 'Jeison Sossa Sierra', '2222148107', 'TI', 14 ),
('10', 'Leyder Montoya Gonzales', '2233186373', 'TI', 15 ),
('10', 'Juan Sebastian Miranda Bohorquez', '2238115439', 'TI', 16 ),
('10', 'Mateo Isaza Muñoz', '2218346554', 'TI', 17 ),
('10', 'Mariana Borja Cañaveral', '221159339', 'TI', 18 ),
('10', 'Gbriela Garrido Galeano', '2211401901', 'TI', 20 );

-- Insertar asignaciones de grados a docentes
INSERT IGNORE INTO docente_grados (docente_id, grado) VALUES (10, '10');
INSERT IGNORE INTO docente_grados (docente_id, grado) VALUES (10, '11');
INSERT IGNORE INTO docente_grados (docente_id, grado) VALUES (19, '6');
INSERT IGNORE INTO docente_grados (docente_id, grado) VALUES (19, '7');

-- Insertar docentes
INSERT INTO `docentes` (`id`, `usuario_id`, `nombre_completo`) VALUES
(1, 10, 'Letty Contreras Ospitia'),
(2, 19, 'Maria Isabel Zapata Restrepo');

-- Insertar grupo de muestra
INSERT INTO `grupos` (`id`, `nombre`, `descripcion`, `docente_id`, `activo`) VALUES
(1, 'Grado 11 - A', 'Grupo para el curso de Lenguaje de grado 11', 1, 1);

-- Insertar inscripcion de muestra
INSERT INTO `inscripciones` (`estudiante_id`, `grupo_id`) VALUES
(1, 1);
