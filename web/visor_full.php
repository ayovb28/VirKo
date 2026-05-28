<?php
session_start();
error_reporting(E_ERROR);
if (isset($_GET['token']) && $_GET['token'] === 'virko2526') {
    $_SESSION['usuari'] = 'qt_app';
    $_SESSION['rol'] = isset($_GET['mode']) && $_GET['mode'] === 'lectura' ? 'R' : 'RW';
    $_SESSION['nom_complet'] = 'App Qt';
}
if (!isset($_SESSION['usuari'])) { header("Location: login.php"); exit; }

$conn = new mysqli("db", "root", "root", "virko");
if ($conn->connect_error) die("Error connexió");

$mac = isset($_GET['mac']) ? $conn->real_escape_string($_GET['mac']) : '';
if (!$mac) die("Cal especificar una MAC");

$full_info = $conn->query("SELECT nom FROM fulls WHERE mac='$mac' LIMIT 1")->fetch_assoc();
$nom_aula  = $full_info ? $full_info['nom'] : $mac;

$dades = $conn->query("SELECT * FROM dades WHERE mac='$mac' ORDER BY timestamp DESC LIMIT 500");
$total = $dades->num_rows;

$nom_fitxer = "virko_" . preg_replace('/[^a-zA-Z0-9]/', '_', $mac) . ".csv";
$ruta       = __DIR__ . "/fulls/" . $nom_fitxer;

if (!is_dir(__DIR__ . "/fulls")) {
    mkdir(__DIR__ . "/fulls", 0777, true);
}

$fp = fopen($ruta, 'w');
fputcsv($fp, ['Timestamp', 'Temperatura (°C)', 'CO2 (ppm)', 'Humitat (%)', 'Pressio (hPa)', 'LDR', 'IAQ', 'Virko ID'], ',', '"', '\\');
while ($f = $dades->fetch_assoc()) {
    fputcsv($fp, [$f['timestamp'], $f['temp'], $f['co2'], $f['humitat'], $f['pressio'], $f['ldr'], $f['iaq'], $f['mac']], ',', '"', '\\');
}
fclose($fp);

$host = $_SERVER['SERVER_ADDR'];
$url_fitxer = "http://web/fulls/" . $nom_fitxer;

$rol         = $_SESSION['rol'];
$nom_complet = $_SESSION['nom_complet'];

$mode_url = isset($_GET['mode']) ? $_GET['mode'] : '';
$mode_onlyoffice = ($mode_url === 'lectura' || $rol === 'R') ? 'view' : 'edit';
?>
<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VirKO</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; overflow: hidden; }
    body { font-family: 'Segoe UI', sans-serif; background: #f0f4f8; display: flex; flex-direction: column; }
    header { background: linear-gradient(to right, #ffffff 0%, #ffffff 10%, #1a3a6b 50%, #0a1628 80%); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; height: 70px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); flex-shrink: 0; }
    header a.logo img { height: 48px; }
    .nav { display: flex; gap: 8px; align-items: center; }
    .nav a { color: rgba(255,255,255,0.75); text-decoration: none; font-size: 14px; padding: 7px 14px; border-radius: 8px; transition: background .2s; }
    .nav a:hover { background: rgba(255,255,255,0.15); color: white; }
    .nav a.tornar { background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.2); }
    .usuari-info { color: rgba(255,255,255,0.6); font-size: 13px; margin-right: 8px; }
    .usuari-info strong { color: white; }
    .info-bar { background: white; padding: 14px 40px; display: flex; align-items: center; gap: 16px; border-bottom: 2px solid #e8e8e8; flex-shrink: 0; }
    .info-bar h2 { font-size: 16px; color: #1a3a6b; font-weight: 600; }
    .info-bar .mac { font-family: monospace; color: #0066cc; font-size: 13px; background: #f0f7ff; padding: 3px 10px; border-radius: 20px; border: 1px solid #cce0ff; }
    .info-bar .mode-tag { padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; }
    .mode-edicio { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
    .mode-lectura { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
    .info-bar .dreta { margin-left: auto; display: flex; align-items: center; gap: 12px; }
    .info-bar .registres { font-size: 13px; color: #888; }
    .btn-descarregar { padding: 7px 16px; background: #f0f7ff; color: #0066cc; border: 1.5px solid #cce0ff; border-radius: 8px; font-size: 13px; text-decoration: none; }
    #onlyoffice-editor { flex: 1; width: 100%; }
  </style>
</head>
<body>
<header>
  <a href="index.php" class="logo"><img src="logo.png" alt="VirKO"></a>
  <div style="display:flex;align-items:center;gap:16px">
    <span class="usuari-info">Benvingut, <strong><?= htmlspecialchars($nom_complet) ?></strong></span>
    <nav class="nav">
      <?php if ($rol === 'ADMIN'): ?><a href="admin.php">Usuaris</a><?php endif; ?>
      <a href="gestio.php">Inici</a>
      <a href="dades.php">Dades</a>
      <a href="gestio.php" class="tornar">← Tornar</a>
    </nav>
  </div>
</header>
<div class="info-bar">
  <div><h2><?= htmlspecialchars($nom_aula) ?></h2></div>
  <span class="mac"><?= htmlspecialchars($mac) ?></span>
  <span class="mode-tag <?= ($mode_onlyoffice === 'view') ? 'mode-lectura' : 'mode-edicio' ?>">
    <?= ($mode_onlyoffice === 'view') ? 'Nomes lectura' : 'Edicio' ?>
  </span>
  <div class="dreta">
    <span class="registres"><?= $total ?> registres exportats</span>
    <a href="descarregar_full.php?mac=<?= urlencode($mac) ?>" class="btn-descarregar">Descarregar Excel</a>
  </div>
</div>
<div id="onlyoffice-editor"></div>
<script src="http://localhost:8081/web-apps/apps/api/documents/api.js"></script>
<script>
var docEditor = new DocsAPI.DocEditor("onlyoffice-editor", {
    document: {
        fileType: "csv",
        key: "<?= $mac . '_' . time() ?>",
        title: "Dades.csv",
        url: "<?= $url_fitxer ?>",
        options: { "delimiter": ",", "encoding": 65001 }
    },
    documentType: "cell",
    editorConfig: {
        mode: "<?= $mode_onlyoffice ?>",
        lang: "ca",
        customization: { autosave: false, forcesave: false, chat: false, help: false }
    },
    width: "100%",
    height: "100%"
});
</script>
</body>
</html>
