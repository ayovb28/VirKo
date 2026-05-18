-- =====================================================
-- Fitxer d'inicialització automàtica de la BD Virko
-- S'executa automàticament quan MySQL arrenca per primera vegada
-- =====================================================

-- Creem la taula d'usuaris del sistema
CREATE TABLE IF NOT EXISTS usuaris (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  usuari      VARCHAR(50) UNIQUE,
  contrasenya VARCHAR(255),
  rol         VARCHAR(10),
  nom_complet VARCHAR(100),
  creat       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Creem la taula de fulls de càlcul vinculats a les Virkos
CREATE TABLE IF NOT EXISTS fulls (
  id    INT AUTO_INCREMENT PRIMARY KEY,
  mac   VARCHAR(50),
  nom   VARCHAR(100),
  rol   VARCHAR(5),
  creat TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Creem la taula de dades dels sensors de la Virko
CREATE TABLE IF NOT EXISTS dades (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  timestamp DATETIME,
  ldr       INT,
  co2       INT,
  temp      FLOAT,
  pressio   FLOAT,
  humitat   FLOAT,
  iaq       INT,
  mac       VARCHAR(50)
);

-- Inserim els usuaris per defecte del sistema
INSERT INTO usuaris (usuari, contrasenya, rol, nom_complet) VALUES
('admin',     '$2y$12$.ngcnNJteTa2Fx2SCQ6BPeZ8pFV45YFvah0PJCy7Aqq0Q3ZWByRTi', 'ADMIN', 'Administrador'),
('professor', '$2y$12$TavBR7EcGY3g0cbQzZum3u.D2GvdJE9pBKoNhFCYJP//7QjsJ17HG', 'RW',    'Professor'),
('alumne',    '$2y$12$MILBMX2M7dRmUgbdvOb0H.qMQ3K.9cq/gyxl.VdY.ScVB4nN1PSVi', 'R',     'Alumne');

-- Inserim l'aula d'informàtica amb la Virko real
INSERT INTO fulls (mac, nom, rol) VALUES ('B358', "Aula d'informàtica", 'RW');