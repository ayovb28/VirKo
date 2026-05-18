<?php
// Iniciem la sessió per verificar si l'usuari està autenticat
session_start();

// Si no està autenticat el redirigim al login
if (!isset($_SESSION['usuari'])) { header("Location: login.php"); exit; }

// Obtenim les dades de la sessió de l'usuari actual
$rol         = $_SESSION['rol'];
$nom_complet = $_SESSION['nom_complet'];

// Connectem a la base de dades MySQL
$conn = new mysqli("db", "root", "root", "virko");
if ($conn->connect_error) die("Error connexió");

// Gestionem el tancament de sessió
if (isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit; }

// Comprovem si l'usuari pot editar (admin o professor)
$pot_editar = ($rol === 'ADMIN' || $rol === 'RW');

// Creem un nou full si l'usuari té permisos
if (isset($_POST['crear']) && $pot_editar) {
    $mac = $conn->real_escape_string(trim($_POST['mac']));
    $nom = $conn->real_escape_string(trim($_POST['nom']));
    $conn->query("INSERT INTO fulls (mac, nom, rol) VALUES ('$mac', '$nom', 'RW')");
}

// Eliminem un full si l'usuari té permisos
if (isset($_POST['eliminar']) && $pot_editar) {
    $id = intval($_POST['id']);
    // Obtenim la MAC abans d'eliminar per poder esborrar també les dades
    $res_mac = $conn->query("SELECT mac FROM fulls WHERE id=$id");
    if ($res_mac && $row_mac = $res_mac->fetch_assoc()) {
        $mac_eliminar = $conn->real_escape_string($row_mac['mac']);
        $conn->query("DELETE FROM dades WHERE mac='$mac_eliminar'");
    }
    $conn->query("DELETE FROM fulls WHERE id=$id");
}

// Obtenim tots els fulls registrats ordenats per data de creació
$fulls = $conn->query("SELECT * FROM fulls ORDER BY creat DESC");
?>
<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VirKO — Gestió de fulls</title>
  <style>
    /* Reset bàsic */
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; min-height: 100vh; }

    /* Capçalera principal */
    header {
      background: linear-gradient(to right, #ffffff 0%, #ffffff 17%, #1a3a6b 50%, #0a1628 80%);
      padding: 0 40px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 70px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.2);
    }

    /* Logo a la capçalera */
    header img { height: 70px; }

    /* Navegació de la capçalera */
    .nav { display: flex; gap: 8px; align-items: center; }

    /* Estil dels enllaços de navegació */
    .nav a {
      color: rgba(255,255,255,0.75);
      text-decoration: none;
      font-size: 14px;
      padding: 7px 14px;
      border-radius: 8px;
      transition: background .2s, color .2s;
    }

    /* Estil de l'enllaç actiu */
    .nav a:hover, .nav a.actiu {
      background: rgba(255,255,255,0.15);
      color: white;
    }

    /* Botó de tancar sessió */
    .nav .logout {
      color: #ff8080;
      border: 1px solid rgba(255,100,100,0.3);
    }

    .nav .logout:hover { background: rgba(255,80,80,0.15); color: #ffaaaa; }

    /* Informació de l'usuari connectat */
    .usuari-info {
      color: rgba(255,255,255,0.6);
      font-size: 13px;
      margin-right: 8px;
    }

    .usuari-info strong { color: white; }

    /* Contingut principal */
    main { max-width: 960px; margin: 40px auto; padding: 0 20px; }

    /* Targeta del formulari */
    .formulari {
      background: white;
      padding: 28px 32px;
      border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      margin-bottom: 28px;
    }

    .formulari h2 { font-size: 16px; color: #1a3a6b; margin-bottom: 20px; font-weight: 600; }

    /* Fila del formulari */
    .fila-form { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }

    /* Camps d'entrada */
    input[type=text] {
      padding: 11px 16px;
      border: 1.5px solid #e0e0e0;
      border-radius: 10px;
      font-size: 14px;
      flex: 1;
      min-width: 180px;
      transition: border .2s;
    }

    input[type=text]:focus { outline: none; border-color: #0066cc; }

    /* Botó d'afegir */
    button.crear {
      padding: 11px 28px;
      background: linear-gradient(135deg, #0066cc, #0052a3);
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: transform .1s, box-shadow .2s;
    }

    button.crear:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0,102,204,0.3);
    }

    /* Avís per a usuaris de només lectura */
    .avis {
      background: #f0f7ff;
      border: 1.5px solid #cce0ff;
      color: #0052a3;
      padding: 14px 20px;
      border-radius: 12px;
      font-size: 14px;
      margin-bottom: 28px;
    }

    /* Targeta de la taula */
    .taula-wrap {
      background: white;
      border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      overflow: hidden;
    }

    /* Capçalera de la taula */
    .taula-cap {
      padding: 20px 32px;
      border-bottom: 1px solid #f0f0f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .taula-cap h2 { font-size: 16px; color: #1a3a6b; font-weight: 600; }
    .taula-cap span { font-size: 13px; color: #aaa; }

    /* Estil de la taula */
    table { width: 100%; border-collapse: collapse; }

    th {
      background: #f8faff;
      padding: 12px 20px;
      text-align: left;
      font-size: 12px;
      color: #888;
      font-weight: 600;
      border-bottom: 1px solid #f0f0f0;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    td { padding: 16px 20px; font-size: 14px; color: #333; border-bottom: 1px solid #f8f8f8; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafcff; }

    /* Botó d'eliminar */
    button.eliminar {
      padding: 7px 16px;
      background: white;
      color: #cc0000;
      border: 1.5px solid #ffcccc;
      border-radius: 8px;
      font-size: 13px;
      cursor: pointer;
      transition: background .2s;
    }

    button.eliminar:hover { background: #fff0f0; }

    /* Botó de veure dades */
    .btn-dades {
      padding: 7px 16px;
      background: #f0f7ff;
      color: #0066cc;
      border: 1.5px solid #cce0ff;
      border-radius: 8px;
      font-size: 13px;
      text-decoration: none;
      transition: background .2s;
      display: inline-block;
    }

    .btn-dades:hover { background: #e0f0ff; }

    /* Missatge quan no hi ha fulls */
    .buit { text-align: center; padding: 50px; color: #bbb; font-size: 14px; }
  </style>
</head>
<body>

<!-- Capçalera amb logo i navegació -->
<header>
  <img src="logo.png" alt="VirKO">
  <div style="display:flex;align-items:center;gap:16px">
    <span class="usuari-info">Benvingut, <strong><?= htmlspecialchars($nom_complet) ?></strong></span>
    <nav class="nav">
      <?php if ($rol === 'ADMIN'): ?>
        <!-- Enllaç a la gestió d'usuaris (només admin) -->
        <a href="admin.php">Usuaris</a>
      <?php endif; ?>
      <!-- Enllaç actiu: gestió de fulls -->
      <a href="gestio.php" class="actiu">Inici</a>
      <!-- Enllaç a la pàgina de dades -->
      <a href="dades.php">Dades</a>
      <!-- Botó de tancar sessió -->
      <a href="?logout" class="logout">Tancar sessió</a>
    </nav>
  </div>
</header>

<main>
  <!-- Formulari per crear nous fulls (només per a admin i professors) -->
  <?php if ($pot_editar): ?>
  <div class="formulari">
    <h2>Afegir nou full de càlcul</h2>
    <form method="POST">
      <div class="fila-form">
        <input type="text" name="mac" placeholder="Identificador Virko (ex: B358)" required>
        <input type="text" name="nom" placeholder="Nom de l'aula (ex: Aula 101)" required>
        <button type="submit" name="crear" class="crear">Afegir</button>
      </div>
    </form>
  </div>

  <?php endif; ?>

  <!-- Taula amb tots els fulls registrats -->
  <div class="taula-wrap">
    <div class="taula-cap">
      <h2>Fulls registrats</h2>
      <span><?= $fulls->num_rows ?> fulls</span>
    </div>
    <table>
      <tr>
        <th>Aula</th>
        <th>Identificador Virko</th>
        <th>Data de creació</th>
        <th>Dades</th>
        <?php if ($pot_editar): ?><th>Accions</th><?php endif; ?>
      </tr>
      <?php if ($fulls->num_rows === 0): ?>
        <tr><td colspan="5" class="buit">No hi ha cap full registrat encara.</td></tr>
      <?php else: ?>
      <?php while ($f = $fulls->fetch_assoc()): ?>
      <tr>
        <!-- Nom de l'aula -->
        <td><strong><?= htmlspecialchars($f['nom']) ?></strong></td>
        <!-- Identificador MAC de la Virko -->
        <td style="font-family:monospace;color:#0066cc;font-weight:500"><?= htmlspecialchars($f['mac']) ?></td>
        <!-- Data de creació del full -->
        <td style="color:#aaa;font-size:13px"><?= $f['creat'] ?></td>
        
        <!-- Botó per veure les dades de la Virko -->
        <td style="display:flex;gap:8px">
          <!-- Botó per veure les dades de la Virko -->
          <a href="dades.php?mac=<?= urlencode($f['mac']) ?>" class="btn-dades">Veure dades</a>
  
          <!-- Botó per obrir el visor ONLYOFFICE -->
          <a href="visor_full.php?mac=<?= urlencode($f['mac']) ?>" class="btn-dades" style="background:#fff3e0;color:#e65100;border-color:#ffe0b2;">Obrir ONLYOFFICE</a>
  
          <!-- Botó per descarregar el full Excel de la Virko -->
           <a href="descarregar_full.php?mac=<?= urlencode($f['mac']) ?>" class="btn-dades" style="background:#f0fff4;color:#2e7d32;border-color:#b2dfdb;">Descarregar Excel</a>
        </td>

        <?php if ($pot_editar): ?>
        <td>
          <!-- Botó per eliminar el full amb confirmació -->
          <form method="POST" onsubmit="return confirm('Segur que vols eliminar «<?= htmlspecialchars($f['nom']) ?>»?')">
            <input type="hidden" name="id" value="<?= $f['id'] ?>">
            <button type="submit" name="eliminar" class="eliminar">Eliminar</button>
          </form>
        </td>
        <?php endif; ?>
      </tr>
      <?php endwhile; ?>
      <?php endif; ?>
    </table>
  </div>
</main>
</body>
</html>