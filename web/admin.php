<?php
// Iniciem la sessió per verificar autenticació
session_start();

// Només l'administrador pot accedir a aquesta pàgina
if (!isset($_SESSION['usuari']) || $_SESSION['rol'] !== 'ADMIN') {
    header("Location: login.php"); exit;
}

// Connectem a la base de dades MySQL
$conn = new mysqli("db", "root", "root", "virko");
if ($conn->connect_error) die("Error connexió");

$missatge = "";
$tipus    = "";

// Gestionem el tancament de sessió
if (isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit; }

// Creem un nou usuari si s'ha enviat el formulari
if (isset($_POST['crear_usuari'])) {
    $usuari      = $conn->real_escape_string(trim($_POST['usuari']));
    $nom_complet = $conn->real_escape_string(trim($_POST['nom_complet']));
    // Validem que el rol sigui un dels permesos
    $rol         = in_array($_POST['rol'], ['ADMIN','RW','R']) ? $_POST['rol'] : 'R';
    // Encriptem la contrasenya de forma segura
    $contra      = password_hash($_POST['contrasenya'], PASSWORD_DEFAULT);
    $res = $conn->query("INSERT INTO usuaris (usuari, contrasenya, rol, nom_complet) VALUES ('$usuari', '$contra', '$rol', '$nom_complet')");
    $missatge = $res ? "Usuari creat correctament." : "Error: l'usuari ja existeix.";
    $tipus    = $res ? "ok" : "err";
}

// Eliminem un usuari si s'ha sol·licitat
if (isset($_POST['eliminar_usuari'])) {
    $id = intval($_POST['id']);
    // No permetem eliminar el compte actual
    $conn->query("DELETE FROM usuaris WHERE id=$id AND usuari != '{$_SESSION['usuari']}'");
    $missatge = "Usuari eliminat correctament.";
    $tipus    = "ok";
}

// Obtenim tots els usuaris ordenats per rol i nom
$usuaris = $conn->query("SELECT id, usuari, nom_complet, rol, creat FROM usuaris ORDER BY FIELD(rol,'ADMIN','RW','R'), usuari");

$rol         = $_SESSION['rol'];
$nom_complet = $_SESSION['nom_complet'];
?>
<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VirKO — Gestió d'usuaris</title>
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
      box-shadow: 0 2px 12px rgba(0,0,0,0.1);
    }

    header img { height: 70px; }

    /* Navegació */
    .nav { display: flex; gap: 8px; align-items: center; }
    .nav a { color: rgba(255,255,255,0.75); text-decoration: none; font-size: 14px; padding: 7px 14px; border-radius: 8px; transition: background .2s; }
    .nav a:hover { background: rgba(255,255,255,0.15); color: white; }
    .nav a.actiu { background: rgba(255,255,255,0.15); color: white; }
    .nav .logout { color: #ff8080; border: 1px solid rgba(255,100,100,0.3); }
    .nav .logout:hover { background: rgba(255,80,80,0.15); color: #ffaaaa; }
    .usuari-info { color: rgba(255,255,255,0.6); font-size: 13px; margin-right: 8px; }
    .usuari-info strong { color: white; }

    /* Contingut principal */
    main { max-width: 960px; margin: 32px auto; padding: 0 20px; }

    /* Missatges d'èxit i error */
    .ok  { background: #f0fff4; border: 1.5px solid #b2dfdb; color: #1b5e20; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; }
    .err { background: #fff0f0; border: 1.5px solid #ffcccc; color: #cc0000; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 14px; }

    /* Targeta del formulari */
    .formulari {
      background: white;
      padding: 28px 32px;
      border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      margin-bottom: 28px;
    }

    .formulari h2 { font-size: 16px; color: #1a3a6b; margin-bottom: 20px; font-weight: 600; }

    /* Grid de dos columnes per al formulari */
    .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    label { display: block; font-size: 12px; font-weight: 600; color: #666; margin-bottom: 6px; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.3px; }

    /* Camps d'entrada */
    input, select {
      width: 100%;
      padding: 11px 16px;
      border: 1.5px solid #e0e0e0;
      border-radius: 10px;
      font-size: 14px;
      transition: border .2s;
      color: #333;
    }

    input:focus, select:focus { outline: none; border-color: #0066cc; }

    /* Botó de crear usuari */
    button.crear {
      margin-top: 20px;
      padding: 11px 32px;
      background: linear-gradient(135deg, #0066cc, #0052a3);
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      transition: transform .1s, box-shadow .2s;
    }

    button.crear:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,102,204,0.3); }

    /* Targeta de la taula */
    .taula-wrap {
      background: white;
      border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0,0,0,0.06);
      overflow: hidden;
    }

    .taula-cap {
      padding: 20px 32px;
      border-bottom: 1px solid #f0f0f0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .taula-cap h2 { font-size: 16px; color: #1a3a6b; font-weight: 600; }
    .taula-cap span { font-size: 13px; color: #aaa; }

    table { width: 100%; border-collapse: collapse; }

    th {
      background: #f8faff;
      padding: 12px 20px;
      text-align: left;
      font-size: 11px;
      color: #888;
      font-weight: 600;
      border-bottom: 1px solid #f0f0f0;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    td { padding: 15px 20px; font-size: 14px; color: #333; border-bottom: 1px solid #f8f8f8; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafcff; }

    /* Etiquetes de rol amb colors */
    .tag-admin { background: #ede9fe; color: #4c1d95; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
    .tag-rw    { background: #fff3e0; color: #e65100; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
    .tag-r     { background: #e8f5e9; color: #2e7d32; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }

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

    /* Text del compte actual */
    .compte-actual { color: #bbb; font-size: 13px; font-style: italic; }
  </style>
</head>
<body>

<!-- Capçalera amb logo i navegació -->
<header>
  <img src="logo.png" alt="VirKO">
  <div style="display:flex;align-items:center;gap:16px">
    <span class="usuari-info">Benvingut, <strong><?= htmlspecialchars($nom_complet) ?></strong></span>
    <nav class="nav">
      <!-- Enllaç actiu: gestió d'usuaris -->
      <a href="admin.php" class="actiu">Usuaris</a>
      <a href="gestio.php">Inici</a>
      <a href="dades.php">Dades</a>
      <a href="?logout" class="logout">Tancar sessió</a>
    </nav>
  </div>
</header>

<main>

  <!-- Missatge de resultat de l'operació -->
  <?php if ($missatge): ?>
    <div class="<?= $tipus ?>"><?= $missatge ?></div>
  <?php endif; ?>

  <!-- Formulari per crear nous usuaris -->
  <div class="formulari">
    <h2>Crear nou usuari</h2>
    <form method="POST">
      <div class="grid">
        <div>
          <label>Nom complet</label>
          <input type="text" name="nom_complet" placeholder="Ex: Maria García" required>
        </div>
        <div>
          <label>Nom d'usuari</label>
          <input type="text" name="usuari" placeholder="Ex: mgarcia" required>
        </div>
        <div>
          <label>Contrasenya</label>
          <input type="password" name="contrasenya" placeholder="Mínim 6 caràcters" required minlength="6">
        </div>
        <div>
          <label>Rol</label>
          <select name="rol">
            <!-- Opció per a alumnes: només lectura -->
            <option value="R">Alumne — només lectura</option>
            <!-- Opció per a professors: lectura i escriptura -->
            <option value="RW">Professor — lectura i escriptura</option>
            <!-- Opció per a administradors: accés total -->
            <option value="ADMIN">Administrador — accés total</option>
          </select>
        </div>
      </div>
      <button type="submit" name="crear_usuari" class="crear">Crear usuari</button>
    </form>
  </div>

  <!-- Taula amb tots els usuaris del sistema -->
  <div class="taula-wrap">
    <div class="taula-cap">
      <h2>Usuaris del sistema</h2>
      <span><?= $usuaris->num_rows ?> usuaris</span>
    </div>
    <table>
      <tr>
        <th>Nom complet</th>
        <th>Usuari</th>
        <th>Rol</th>
        <th>Data d'alta</th>
        <th>Accions</th>
      </tr>
      <?php while ($u = $usuaris->fetch_assoc()): ?>
      <tr>
        <td><strong><?= htmlspecialchars($u['nom_complet']) ?></strong></td>
        <td style="font-family:monospace;color:#555"><?= htmlspecialchars($u['usuari']) ?></td>
        <td>
          <?php if ($u['rol'] === 'ADMIN'): ?>
            <!-- Etiqueta d'administrador -->
            <span class="tag-admin">Administrador</span>
          <?php elseif ($u['rol'] === 'RW'): ?>
            <!-- Etiqueta de professor -->
            <span class="tag-rw">Professor</span>
          <?php else: ?>
            <!-- Etiqueta d'alumne -->
            <span class="tag-r">Alumne</span>
          <?php endif; ?>
        </td>
        <td style="color:#aaa;font-size:13px"><?= $u['creat'] ?></td>
        <td>
          <?php if ($u['usuari'] !== $_SESSION['usuari']): ?>
          <!-- Botó per eliminar l'usuari amb confirmació -->
          <form method="POST" onsubmit="return confirm('Segur que vols eliminar <?= htmlspecialchars($u['nom_complet']) ?>?')">
            <input type="hidden" name="id" value="<?= $u['id'] ?>">
            <button type="submit" name="eliminar_usuari" class="eliminar">Eliminar</button>
          </form>
          <?php else: ?>
          <!-- No es pot eliminar el compte actual -->
          <span class="compte-actual">Compte actual</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endwhile; ?>
    </table>
  </div>

</main>
</body>
</html>