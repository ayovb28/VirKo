# VirKo — Servidor de Fulls de Càlcul per a la IoT

> **Mesura · Control · Fiabilitat**

Sistema servidor per a la recepció, emmagatzematge i visualització de dades en temps real de dispositius IoT **VirKo** (sensors de qualitat de l'aire). Inclou servidor web PHP, base de dades MySQL, broker MQTT, Node-RED i visor ONLYOFFICE, tot empaquetат amb Docker.


## Arquitectura del sistema

```
Placa VirKo (sensor físic)
        │
        │  MQTT (broker.emqx.io:8883 / TLS)
        ▼
   Node-RED  ──────────────────────────────┐
   (port 1880)                             │ INSERT INTO dades
        │                                  ▼
        │                           MySQL (port 3306)
        │                                  │
        │                                  │ mysqli
        │                                  ▼
        └──────────────────────────► PHP/Apache (port 80)
                                           │
                                           ▼
                                    Navegador web
                              http://IP_DEL_SERVIDOR
```

### Components Docker

| Contenidor | Imatge | Port | Funció |
|------------|--------|------|--------|
| `web` | PHP 8 + Apache | 80 | Servidor web (pàgines PHP) |
| `db` | MySQL 5.7 | 3306 | Base de dades |
| `nodered` | Node-RED | 1880 | Recepció MQTT → MySQL |
| `mosquitto` | Eclipse Mosquitto | 1883 | Broker MQTT local |
| `onlyoffice` | ONLYOFFICE Document Server | 8081 | Visor de fulls de càlcul |

---

## Requisits previs

- **Sistema operatiu:** Linux (Debian 13 / Ubuntu 22.04 o superior) o Windows amb WSL2
- **Docker Engine** i **Docker Compose** instal·lats
- **Mínim 4 GB de RAM** i **2 CPU**
- **Ports lliures:** 80, 1880, 1883, 3306, 8081
- Connexió a Internet per al primer `docker compose up` (descàrrega d'imatges)

---

## Instal·lació

### 1. Instal·lar Docker (Debian/Ubuntu)

```bash
sudo apt update && sudo apt upgrade -y

sudo apt install -y ca-certificates curl gnupg

install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/debian/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
  https://download.docker.com/linux/debian $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io docker-compose-plugin

# Afegir l'usuari al grup docker (evita usar sudo)
sudo usermod -aG docker $USER
newgrp docker

# Verificar instal·lació
docker --version
docker compose version
```

### 2. Clonar el repositori

```bash
git clone https://github.com/ayovb28/VirKo.git
cd VirKo
```

### 3. Configurar Mosquitto

Comprova que el fitxer `mosquitto/config/mosquitto.conf` conté:

```
listener 1883
allow_anonymous true
```

### 4. Arrencar el sistema

```bash
docker compose up -d --build
```

La primera vegada pot trigar **5-10 minuts** per descarregar les imatges. Un cop acabat, tots els contenidors han d'estar en marxa:

```bash
docker compose ps
```

Ha de sortir tots els serveis amb estat `Up`.

### 5. Accedir a la web

Obre el navegador i ves a:

```
http://localhost
```

> Si estàs en un VPS, substitueix `localhost` per la IP pública del servidor.

**Credencials per defecte:**

| Usuari | Contrasenya | Rol |
|--------|-------------|-----|
| `admin` | `***` | Administrador |
| `professor` | `***` | Professor (R/W) |
| `alumne` | `alumne` | Alumne (R) |

> ⚠️ **Canvia les contrasenyes** després del primer accés des de la pàgina d'administració.

---

## Desplegament en VPS (Azure / DigitalOcean / Hetzner)

Si vols que el sistema sigui accessible des de qualsevol lloc:

### 1. Obrir ports al firewall

| Port | Protocol | Servei |
|------|----------|--------|
| 80 | TCP | Web VirKo |
| 1880 | TCP | Node-RED |
| 1883 | TCP | MQTT |
| 8081 | TCP | ONLYOFFICE |
| 22 | TCP | SSH |

### 2. Arrencar amb `restart: always`

El `docker-compose.yml` ja inclou `restart: always` a tots els serveis. Això garanteix que el sistema s'arrenca automàticament si el servidor es reinicia.

### 3. Accedir

```
http://IP_PUBLICA_DEL_VPS
```

---

## Connexió de les VirKos

Cada VirKo ha de publicar les dades via MQTT al broker públic `broker.emqx.io` amb el format:

**Topic MQTT:**
```
/{MAC_DE_LA_VIRKO}/jsData
```

**Format JSON del payload:**
```json
{
    "T": 24.5,
    "RH": 44.2,
    "P": 1014.8,
    "CO2_ppm": 715,
    "LDR": 3349,
    "IAQ": 10
}
```

| Camp | Descripció | Unitat |
|------|------------|--------|
| `T` | Temperatura | °C |
| `RH` | Humitat relativa | % |
| `P` | Pressió atmosfèrica | hPa |
| `CO2_ppm` | Concentració de CO2 | ppm |
| `LDR` | Sensor de llum | lux |
| `IAQ` | Índex de qualitat de l'aire | idx |

> Node-RED escolta el topic `+/jsData` (wildcard) per rebre dades de **qualsevol VirKo** automàticament, sense necessitat de configuració addicional.

---

## 🌡️ Lògica de qualitat de l'aire

El sistema usa la mateixa lògica que el firmware de la VirKo física:

| CO2 (ppm) | LED físic | Indicador web |
|-----------|-----------|---------------|
| < 800 | 🟢 Verd | Aire excel·lent |
| 800 – 999 | 🔵 Blau | Aire acceptable |
| ≥ 1000 | 🔴 Vermell | Aire dolent |

---

## Estructura de la base de dades

### Taula `dades` — Registres dels sensors
```sql
CREATE TABLE dades (
    id        INT AUTO_INCREMENT PRIMARY KEY,
    timestamp DATETIME,
    temp      FLOAT,
    co2       INT,
    humitat   FLOAT,
    pressio   FLOAT,
    ldr       INT,
    iaq       INT,
    mac       VARCHAR(50)
);
```

### Taula `fulls` — VirKos registrades
```sql
CREATE TABLE fulls (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    mac   VARCHAR(50),
    nom   VARCHAR(100),
    rol   VARCHAR(10),
    creat DATETIME DEFAULT NOW()
);
```

### Taula `usuaris` — Usuaris del sistema
```sql
CREATE TABLE usuaris (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    usuari       VARCHAR(50) UNIQUE,
    contrasenya  VARCHAR(255),
    rol          ENUM('ADMIN','RW','R'),
    nom_complet  VARCHAR(100),
    creat        DATETIME DEFAULT NOW()
);
```

---

## Pàgines del sistema

| Pàgina | URL | Descripció | Rols |
|--------|-----|------------|------|
| Login | `/login.php` | Inici de sessió | Tots |
| Inici | `/gestio.php` | Gestió de VirKos | ADMIN, RW |
| Dades | `/dades.php` | Dashboard en temps real | Tots |
| Usuaris | `/admin.php` | Gestió d'usuaris | ADMIN |
| Visor | `/visor_full.php?mac=MAC` | ONLYOFFICE integrat | Tots |
| Excel | `/descarregar_full.php?mac=MAC` | Descàrrega Excel | Tots |

### Paràmetre `mode` per al visor (integració externa)

El visor accepta un paràmetre `mode` a la URL per controlar l'accés:

```
# Mode lectura (alumne)
http://IP/visor_full.php?mac=CC50E3A8FA8C&mode=lectura

# Mode edició (professor/admin)
http://IP/visor_full.php?mac=CC50E3A8FA8C
```

---

## Gestió del sistema

### Veure l'estat dels contenidors
```bash
docker compose ps
```

### Aturar el sistema
```bash
docker compose down
```

### Reiniciar un contenidor
```bash
docker compose restart web
```

### Veure els logs
```bash
docker compose logs -f
```

### Fer una còpia de seguretat de la BD
```bash
docker exec virko-server-db-1 mysqldump -u root -proot virko > backup_virko.sql
```

### Restaurar la còpia de seguretat
```bash
docker exec -i virko-server-db-1 mysql -u root -proot virko < backup_virko.sql
```

---

## Estructura del repositori

```
VirKo/
├── docker-compose.yml        # Configuració dels contenidors Docker
├── Dockerfile                # Imatge PHP+Apache personalitzada
├── start.sh                  # Script d'inici (cron + apache)
├── mosquitto/
│   └── config/
│       └── mosquitto.conf    # Configuració del broker MQTT
├── mysql-init/
│   └── init.sql              # Script d'inicialització de la BD
├── nodered_data/             # Flux i configuració de Node-RED
└── web/                      # Fitxers PHP del servidor web
    ├── index.php             # Redirecció inicial
    ├── login.php             # Autenticació
    ├── gestio.php            # Gestió de VirKos
    ├── admin.php             # Gestió d'usuaris
    ├── dades.php             # Dashboard en temps real
    ├── status.php            # API JSON d'estat (AJAX)
    ├── visor_full.php        # Visor ONLYOFFICE
    ├── descarregar_full.php  # Descàrrega Excel
    ├── guardar.php           # API REST per guardar dades
    ├── regenerar_csv.php     # Regeneració de fitxers CSV
    └── logo.png              # Logotip del sistema
```

---

## Serveis accessibles

| Servei | URL |
|--------|-----|
| Web VirKo | `http://IP` |
| Node-RED | `http://IP:1880` |
| ONLYOFFICE | `http://IP:8081` |
| MQTT | `IP:1883` |

---

## Llicència

Projecte desenvolupat per **Ayoub Hamdi Touhami** — Institut l'Alzina / Jesuïtes Clot  
Curs 2025-2026 — Projecte de sistemes i back-end IoT

---

> VirKo — *Mesura · Control · Fiabilitat*
