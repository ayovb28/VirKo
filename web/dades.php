<?php
// Forcem la zona horària a Europe/Madrid (UTC+1/UTC+2 estiu)
// El contenidor Docker PHP corre en UTC però els timestamps
// de la BD venen de Node-RED que usa l'hora de Windows (UTC+2)
date_default_timezone_set("Europe/Madrid");

// Iniciem la sessió per verificar autenticació
session_start();

// Si no està autenticat el redirigim al login
if (!isset($_SESSION['usuari'])) { header("Location: login.php"); exit; }

// Obtenim les dades de la sessió
$rol         = $_SESSION['rol'];
$nom_complet = $_SESSION['nom_complet'];

// Connectem a la base de dades MySQL
$conn = new mysqli("db", "root", "root", "virko");
if ($conn->connect_error) die("Error connexió");

// Gestionem el tancament de sessió
if (isset($_GET['logout'])) { session_destroy(); header("Location: login.php"); exit; }

// Gestionem l'eliminació d'una VirKo de la taula 'fulls'
$missatge_eliminacio = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accio']) && $_POST['accio'] === 'eliminar_virko') {
    if (in_array($rol, ['ADMIN', 'RW'])) {
        $mac_eliminar = $conn->real_escape_string($_POST['mac_eliminar'] ?? '');
        if ($mac_eliminar) {
            $conn->query("DELETE FROM fulls WHERE mac='$mac_eliminar' OR nom='$mac_eliminar'");
            if ($conn->affected_rows > 0) {
                $missatge_eliminacio = "ok";
            } else {
                $missatge_eliminacio = "no_trobat";
            }
        }
    }
    $redirect_mac = ($missatge_eliminacio === 'ok') ? '' : urlencode($_POST['mac_eliminar'] ?? '');
    $msg_param    = ($missatge_eliminacio === 'ok') ? '&eliminat=1' : '&eliminat=0';
    header("Location: dades.php?limit=" . intval($_POST['limit_actual'] ?? 50) . ($redirect_mac ? "&mac=$redirect_mac" : '') . $msg_param);
    exit;
}

// Filtres de la pàgina
$mac_filter = isset($_GET['mac'])   ? $conn->real_escape_string($_GET['mac']) : '';
$limit      = isset($_GET['limit']) ? intval($_GET['limit']) : 50;

// Obtenim les dades filtrades per MAC si cal
if ($mac_filter) {
    $dades = $conn->query("SELECT * FROM dades WHERE mac='$mac_filter' ORDER BY timestamp DESC LIMIT $limit");
} else {
    $dades = $conn->query("SELECT * FROM dades ORDER BY timestamp DESC LIMIT $limit");
}

// Obtenim totes les MACs disponibles per al filtre
$macs = $conn->query("SELECT DISTINCT d.mac, f.nom FROM dades d LEFT JOIN fulls f ON d.mac = f.mac ORDER BY d.mac");

// Calculem els promedis
$temps_arr_php = []; $co2s = []; $hums = []; $iaqs = [];
$dades->data_seek(0);
while ($f = $dades->fetch_assoc()) {
    if ($f['temp'])    $temps_arr_php[] = $f['temp'];
    if ($f['co2'])     $co2s[]  = $f['co2'];
    if ($f['humitat']) $hums[]  = $f['humitat'];
    if ($f['iaq'])     $iaqs[]  = $f['iaq'];
}
$dades->data_seek(0);

$avg = fn($arr) => count($arr) ? round(array_sum($arr)/count($arr), 1) : '—';

// Obtenim l'últim registre
$ultim = $conn->query("SELECT * FROM dades " . ($mac_filter ? "WHERE mac='$mac_filter'" : "") . " ORDER BY timestamp DESC LIMIT 1")->fetch_assoc();

// -------------------------------------------------------
// Comprovem connexió usant PHP time() per evitar
// problemes de zona horària entre MySQL i PHP.
// Node-RED insereix el timestamp amb l'hora local del
// servidor, igual que PHP interpreta strtotime().
// -------------------------------------------------------
$connectada        = false;
$temps_desde_ultim = null;
if ($ultim && isset($ultim['timestamp'])) {
    $ts_virko = strtotime($ultim['timestamp']);
    $ts_ara   = time();
    $segons   = $ts_ara - $ts_virko;
    if ($segons < 0) $segons = abs($segons); // clock skew: agafem valor absolut
    $temps_desde_ultim = intval($segons / 60);
    $connectada = ($segons < 15); // menys de 2 minuts
}

// -------------------------------------------------------
// Mateixa lògica que la funció vRGB() de l'Arduino:
//   #define LLINDAR_VERD    800
//   #define LLINDAR_VERMELL 1000
//
//   LED_R  →  CO2 >= 1000  →  Aire dolent
//   LED_B  →  800 <= CO2 < 1000  →  Aire acceptable
//   LED_G  →  CO2 < 800  →  Aire excel·lent
// -------------------------------------------------------
$co2_actual = intval($ultim['co2'] ?? 0);

if ($co2_actual >= 1000) {
    $qualitat_label = 'Aire dolent';
    $qualitat_emoji = '🔴';
    $qualitat_led   = 'vermell';
    $qualitat_bg    = '#fff0f0';
    $qualitat_color = '#cc0000';
    $qualitat_border= '#ffcccc';
} elseif ($co2_actual >= 800) {
    $qualitat_label = 'Aire acceptable';
    $qualitat_emoji = '🔵';
    $qualitat_led   = 'blau';
    $qualitat_bg    = '#e8f4fd';
    $qualitat_color = '#0066cc';
    $qualitat_border= '#b3d9f7';
} else {
    $qualitat_label = 'Aire excel·lent';
    $qualitat_emoji = '🟢';
    $qualitat_led   = 'verd';
    $qualitat_bg    = '#e8f5e9';
    $qualitat_color = '#2e7d32';
    $qualitat_border= '#c8e6c9';
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VirKO — Dades en temps real</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Segoe UI', Tahoma, sans-serif; background: #eef2f7; min-height: 100vh; }

    /* ── CAPÇALERA ── */
    header {
      background: linear-gradient(to right, #ffffff 0%, #ffffff 15%, #1a3a6b 48%, #0a1628 100%);
      padding: 0 40px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      height: 68px;
      box-shadow: 0 2px 16px rgba(0,0,0,0.14);
      position: sticky;
      top: 0;
      z-index: 100;
    }
    header a.logo { display: flex; align-items: center; }
    header a.logo img { height: 68px; }

    .nav { display: flex; gap: 4px; align-items: center; }
    .nav a {
      color: rgba(255,255,255,0.75);
      text-decoration: none;
      font-size: 13.5px;
      padding: 7px 15px;
      border-radius: 8px;
      transition: background .2s, color .2s;
      font-weight: 500;
    }
    .nav a:hover { background: rgba(255,255,255,0.18); color: white; }
    .nav a.actiu {
      background: rgba(255,255,255,0.2);
      color: white;
      border: 1px solid rgba(255,255,255,0.25);
    }
    .nav .logout { color: #ff9090; border: 1px solid rgba(255,100,100,0.3); margin-left: 4px; }
    .nav .logout:hover { background: rgba(255,80,80,0.18); color: #ffbbbb; border-color: rgba(255,100,100,0.5); }
    .usuari-info { color: rgba(255,255,255,0.55); font-size: 13px; margin-right: 10px; }
    .usuari-info strong { color: rgba(255,255,255,0.9); }

    /* ── MAIN ── */
    main { max-width: 1140px; margin: 28px auto; padding: 0 22px; }

    /* ── BANNER ESTAT ── */
    .estat-virko {
      display: flex;
      align-items: center;
      gap: 12px;
      background: white;
      padding: 14px 22px;
      border-radius: 14px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.06);
      margin-bottom: 22px;
      font-size: 14px;
      border-left: 4px solid <?= $connectada ? '#4caf50' : '#f44336' ?>;
    }
    .estat-dot {
      width: 13px; height: 13px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .estat-dot.connectada  { background: #4caf50; animation: pulse 2s infinite; }
    .estat-dot.desconnectada { background: #f44336; }
    .estat-text-ok { color: #2e7d32; font-weight: 700; }
    .estat-text-ko { color: #cc0000; font-weight: 700; }
    .estat-temps   { color: #999; font-size: 13px; }

    /* Badge de qualitat a l'estat */
    .qualitat-badge {
      margin-left: auto;
      padding: 5px 14px;
      border-radius: 20px;
      font-size: 12.5px;
      font-weight: 600;
      background: <?= $qualitat_bg ?>;
      color: <?= $qualitat_color ?>;
      border: 1px solid <?= $qualitat_border ?>;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    /* LED indicador visual */
    .led {
      width: 14px; height: 14px;
      border-radius: 50%;
      flex-shrink: 0;
      box-shadow: 0 0 6px 2px currentColor;
    }
    .led.vermell { background: #f44336; color: #f44336; animation: ledpulse 1.5s infinite; }
    .led.blau    { background: #2196f3; color: #2196f3; animation: ledpulse 2s infinite; }
    .led.verd    { background: #4caf50; color: #4caf50; animation: ledpulse 2.5s infinite; }

    @keyframes pulse    { 0%,100%{opacity:1}50%{opacity:.35} }
    @keyframes ledpulse { 0%,100%{opacity:1;transform:scale(1)}50%{opacity:.6;transform:scale(0.9)} }

    /* ── TARGETES SENSORS ── */
    .sensors-actuals {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 16px;
      margin-bottom: 22px;
    }
    .sensor-card {
      background: white;
      border-radius: 16px;
      padding: 22px 20px 18px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.06);
      border-top: 4px solid #eee;
      transition: transform .2s, box-shadow .2s;
      position: relative;
      overflow: hidden;
    }
    .sensor-card::after {
      content: '';
      position: absolute;
      bottom: 0; right: 0;
      width: 60px; height: 60px;
      border-radius: 50%;
      opacity: 0.05;
    }
    .sensor-card:hover { transform: translateY(-3px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
    .sensor-card.temp  { border-top-color: #ff6b35; }
    .sensor-card.temp::after  { background: #ff6b35; }
    .sensor-card.co2   { border-top-color: #0066cc; }
    .sensor-card.co2::after   { background: #0066cc; }
    .sensor-card.hum   { border-top-color: #00897b; }
    .sensor-card.hum::after   { background: #00897b; }
    .sensor-card.iaq   { border-top-color: #7b1fa2; }
    .sensor-card.iaq::after   { background: #7b1fa2; }

    .sensor-icon  { font-size: 26px; margin-bottom: 10px; }
    .sensor-label { font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.7px; margin-bottom: 6px; font-weight: 600; }
    .sensor-valor { font-size: 34px; font-weight: 800; line-height: 1; letter-spacing: -1px; }
    .sensor-unit  { font-size: 13px; font-weight: 500; color: #bbb; margin-left: 3px; }
    .sensor-mitja { font-size: 12px; color: #bbb; margin-top: 8px; }
    .temp .sensor-valor { color: #ff6b35; }
    .co2  .sensor-valor { color: #0066cc; }
    .hum  .sensor-valor { color: #00897b; }
    .iaq  .sensor-valor { color: #7b1fa2; }

    /* Barra de progrés a les targetes */
    .sensor-bar-wrap { margin-top: 10px; background: #f0f0f0; border-radius: 4px; height: 4px; }
    .sensor-bar { height: 4px; border-radius: 4px; transition: width .5s; }
    .temp .sensor-bar { background: #ff6b35; }
    .co2  .sensor-bar { background: #0066cc; }
    .hum  .sensor-bar { background: #00897b; }
    .iaq  .sensor-bar { background: #7b1fa2; }

    /* ── FILTRES ── */
    .filtres {
      background: white;
      padding: 16px 22px;
      border-radius: 14px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.06);
      margin-bottom: 22px;
      display: flex;
      gap: 18px;
      align-items: flex-end;
      flex-wrap: wrap;
    }
    .filtre-grup label { display: block; font-size: 11px; color: #999; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.6px; font-weight: 600; }
    select {
      padding: 9px 14px;
      border: 1.5px solid #e4e8ee;
      border-radius: 10px;
      font-size: 13.5px;
      color: #333;
      cursor: pointer;
      transition: border .2s, box-shadow .2s;
      background: white;
      font-family: 'Segoe UI', sans-serif;
    }
    select:focus { outline: none; border-color: #0066cc; box-shadow: 0 0 0 3px rgba(0,102,204,0.1); }

    .auto-update {
      margin-left: auto;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12.5px;
      color: #999;
      background: #f8faff;
      padding: 7px 14px;
      border-radius: 20px;
      border: 1px solid #e8eef8;
    }
    .dot { width: 8px; height: 8px; border-radius: 50%; background: #4caf50; animation: pulse 2s infinite; }

    /* ── GRÀFIQUES ── */
    .grafiques-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 22px;
    }
    .grafica-card {
      background: white;
      border-radius: 16px;
      padding: 22px 24px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.06);
    }
    .grafica-cap {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 18px;
    }
    .grafica-cap h3 { font-size: 14.5px; color: #1a3a6b; font-weight: 700; }

    /* ── SECCIÓ EXTRA: Pressió i LDR ── */
    .mini-cards {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
      margin-bottom: 22px;
    }
    .mini-card {
      background: white;
      border-radius: 14px;
      padding: 18px 20px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.06);
      display: flex;
      align-items: center;
      gap: 16px;
    }
    .mini-card-icon {
      font-size: 28px;
      width: 52px; height: 52px;
      background: #f4f6fa;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .mini-card-info label { font-size: 11px; color: #999; text-transform: uppercase; letter-spacing: 0.6px; font-weight: 600; }
    .mini-card-info .val { font-size: 24px; font-weight: 700; color: #1a3a6b; margin-top: 3px; }
    .mini-card-info .unit { font-size: 12px; color: #aaa; margin-left: 2px; }

    /* ── TAULA ── */
    .taula-wrap {
      background: white;
      border-radius: 16px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.06);
      overflow: hidden;
    }
    .taula-cap {
      padding: 18px 24px;
      border-bottom: 1px solid #f0f2f8;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .taula-cap h2 { font-size: 15px; color: #1a3a6b; font-weight: 700; }
    .taula-cap span { font-size: 12.5px; color: #aaa; background: #f4f6fa; padding: 4px 12px; border-radius: 20px; }
    table { width: 100%; border-collapse: collapse; }
    th {
      background: #f8faff;
      padding: 11px 16px;
      text-align: left;
      font-size: 11px;
      color: #888;
      font-weight: 700;
      border-bottom: 1px solid #eef0f8;
      text-transform: uppercase;
      letter-spacing: 0.6px;
    }
    td { padding: 12px 16px; font-size: 13px; color: #444; border-bottom: 1px solid #f8f8fb; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: #fafcff; }
    .val-temp { color: #ff6b35; font-weight: 700; }
    .val-co2  { color: #0066cc; font-weight: 700; }
    .val-hum  { color: #00897b; font-weight: 700; }
    .val-iaq  { color: #7b1fa2; font-weight: 700; }
    .val-mac  { font-family: 'Courier New', monospace; color: #999; font-size: 11.5px; background: #f4f6fa; padding: 2px 7px; border-radius: 6px; }
    .buit { text-align: center; padding: 60px; color: #ccc; font-size: 14px; }

    /* Badge de qualitat inline a la taula (per files) */
    .badge-bo  { background:#e8f5e9; color:#2e7d32; }
    .badge-mig { background:#e3f2fd; color:#0066cc; }
    .badge-ko  { background:#fff0f0; color:#cc0000; }
    .badge-iaq {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 10px;
      font-size: 11px;
      font-weight: 700;
    }

    /* ── BOTÓ ELIMINAR ── */
    .btn-eliminar {
      padding: 9px 16px;
      background: #fff0f0;
      color: #cc0000;
      border: 1.5px solid #ffcccc;
      border-radius: 10px;
      font-size: 13px;
      cursor: pointer;
      font-family: 'Segoe UI', sans-serif;
      transition: background .2s, border-color .2s;
    }
    .btn-eliminar:hover { background: #ffe0e0; border-color: #ff9999; }

    /* ── MISSATGES ── */
    .missatge-accio { padding: 13px 20px; border-radius: 12px; font-size: 14px; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; }
    .missatge-ok { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
    .missatge-ko { background: #fff0f0; color: #cc0000; border: 1px solid #ffcccc; }

    /* ── FOOTER ── */
    footer {
      text-align: center;
      padding: 24px;
      font-size: 12px;
      color: #bbb;
      margin-top: 8px;
    }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
      .sensors-actuals  { grid-template-columns: repeat(2, 1fr); }
      .grafiques-grid   { grid-template-columns: 1fr; }
      .mini-cards       { grid-template-columns: 1fr; }
      header            { padding: 0 16px; }
      main              { padding: 0 12px; }
    }
    @media (max-width: 540px) {
      .sensors-actuals  { grid-template-columns: 1fr 1fr; }
      .filtres          { flex-direction: column; align-items: stretch; }
      .auto-update      { margin-left: 0; }
    }
  </style>
</head>
<body>

<!-- ═══════════════ CAPÇALERA ═══════════════ -->
<header>
  <a href="index.php" class="logo">
    <img src="logo.png" alt="VirKO">
  </a>
  <div style="display:flex;align-items:center;gap:12px">
    <span class="usuari-info">Benvingut, <strong><?= htmlspecialchars($nom_complet) ?></strong></span>
    <nav class="nav">
      <?php if ($rol === 'ADMIN'): ?>
        <a href="admin.php">Usuaris</a>
      <?php endif; ?>
      <!-- FIX: "Fulls" → "Inici" en català -->
      <a href="gestio.php">Inici</a>
      <a href="dades.php" class="actiu">Dades</a>
      <a href="?logout" class="logout">Tancar sessió</a>
    </nav>
  </div>
</header>

<main>

  <!-- Missatge resultat eliminació -->
  <?php if (isset($_GET['eliminat'])): ?>
    <?php if ($_GET['eliminat'] === '1'): ?>
      <div class="missatge-accio missatge-ok">✅ VirKo eliminada correctament de la taula.</div>
    <?php else: ?>
      <div class="missatge-accio missatge-ko">⚠️ No s'ha trobat cap VirKo registrada amb aquesta MAC.</div>
    <?php endif; ?>
  <?php endif; ?>

  <!-- ═══ BANNER ESTAT DE LA VIRKO ═══ -->
  <?php if ($ultim): ?>
  <div class="estat-virko">
    <div class="estat-dot <?= $connectada ? 'connectada' : 'desconnectada' ?>"></div>
    <?php if ($connectada): ?>
      <span class="estat-text-ok">VirKo connectada</span>
      <span class="estat-temps">— Últim registre fa <?= $temps_desde_ultim === 0 ? 'menys d\'1 minut' : $temps_desde_ultim . ' minut' . ($temps_desde_ultim !== 1 ? 's' : '') ?></span>
    <?php else: ?>
      <span class="estat-text-ko">VirKo desconnectada</span>
      <span class="estat-temps">— Últim registre fa <?= $temps_desde_ultim ?> minut<?= $temps_desde_ultim !== 1 ? 's' : '' ?></span>
    <?php endif; ?>
    <?php if ($mac_filter): ?>
      <span style="font-family:monospace;color:#0066cc;font-size:12px;background:#f0f7ff;padding:3px 10px;border-radius:20px;border:1px solid #cce0ff;"><?= htmlspecialchars($mac_filter) ?></span>
    <?php endif; ?>
    <!-- LED + qualitat aire -->
    <div class="qualitat-badge">
      <span class="led <?= $qualitat_led ?>"></span>
      <?= $qualitat_label ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- ═══ TARGETES SENSORS PRINCIPALS ═══ -->
  <div class="sensors-actuals">

    <?php
    // Percentatges per les barres (rangs aproximats)
    $temp_pct = min(100, max(0, (($ultim['temp'] ?? 20) - 15) / 20 * 100));
    $co2_pct  = min(100, max(0, (($ultim['co2']  ?? 400) - 400) / 1600 * 100));
    $hum_pct  = min(100, max(0, ($ultim['humitat'] ?? 50)));
    $iaq_pct  = min(100, max(0, ($ultim['iaq'] ?? 25) / 3));
    ?>

    <div class="sensor-card temp">
      <div class="sensor-icon">🌡️</div>
      <div class="sensor-label">Temperatura actual</div>
      <div class="sensor-valor"><?= $ultim['temp'] ?? '—' ?><span class="sensor-unit">°C</span></div>
      <div class="sensor-mitja">Mitjana: <?= $avg($temps_arr_php) ?> °C</div>
      <div class="sensor-bar-wrap"><div class="sensor-bar" style="width:<?= $temp_pct ?>%"></div></div>
    </div>

    <div class="sensor-card co2">
      <div class="sensor-icon">💨</div>
      <div class="sensor-label">CO2 actual</div>
      <div class="sensor-valor"><?= $ultim['co2'] ?? '—' ?><span class="sensor-unit">ppm</span></div>
      <div class="sensor-mitja">Mitjana: <?= $avg($co2s) ?> ppm</div>
      <div class="sensor-bar-wrap"><div class="sensor-bar" style="width:<?= $co2_pct ?>%"></div></div>
    </div>

    <div class="sensor-card hum">
      <div class="sensor-icon">💧</div>
      <div class="sensor-label">Humitat actual</div>
      <div class="sensor-valor"><?= $ultim['humitat'] ?? '—' ?><span class="sensor-unit">%</span></div>
      <div class="sensor-mitja">Mitjana: <?= $avg($hums) ?> %</div>
      <div class="sensor-bar-wrap"><div class="sensor-bar" style="width:<?= $hum_pct ?>%"></div></div>
    </div>

    <div class="sensor-card iaq">
      <div class="sensor-icon">🍃</div>
      <div class="sensor-label">IAQ actual</div>
      <div class="sensor-valor"><?= $ultim['iaq'] ?? '—' ?><span class="sensor-unit">idx</span></div>
      <div class="sensor-mitja">Mitjana: <?= $avg($iaqs) ?> idx</div>
      <div class="sensor-bar-wrap"><div class="sensor-bar" style="width:<?= $iaq_pct ?>%"></div></div>
    </div>

  </div>

  <!-- ═══ MINI-TARGETES: Pressió i LDR ═══ -->
  <div class="mini-cards">
    <div class="mini-card">
      <div class="mini-card-icon">🧭</div>
      <div class="mini-card-info">
        <label>Pressió atmosfèrica</label>
        <div class="val"><?= $ultim['pressio'] ?? '—' ?><span class="unit">hPa</span></div>
      </div>
    </div>
    <div class="mini-card">
      <div class="mini-card-icon">💡</div>
      <div class="mini-card-info">
        <label>Sensor de llum (LDR)</label>
        <div class="val"><?= $ultim['ldr'] ?? '—' ?><span class="unit">lux</span></div>
      </div>
    </div>
  </div>

  <!-- ═══ FILTRES ═══ -->
  <form method="GET">
    <div class="filtres">
      <div class="filtre-grup">
        <label>Filtrar per Virko</label>
        <select name="mac" onchange="this.form.submit()">
          <option value="">Totes les Virkos</option>
          <?php while ($m = $macs->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($m['mac']) ?>" <?= $mac_filter === $m['mac'] ? 'selected' : '' ?>>
              <?= $m['nom'] ? htmlspecialchars($m['nom']) . ' (' . htmlspecialchars($m['mac']) . ')' : htmlspecialchars($m['mac']) ?>
            </option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="filtre-grup">
        <label>Nombre de registres</label>
        <select name="limit" onchange="this.form.submit()">
          <option value="25"  <?= $limit===25  ? 'selected':'' ?>>Últims 25</option>
          <option value="50"  <?= $limit===50  ? 'selected':'' ?>>Últims 50</option>
          <option value="100" <?= $limit===100 ? 'selected':'' ?>>Últims 100</option>
          <option value="500" <?= $limit===500 ? 'selected':'' ?>>Últims 500</option>
        </select>
      </div>
      <?php if ($mac_filter): ?>
        <div class="filtre-grup" style="align-self:flex-end">
          <a href="dades.php" style="padding:9px 16px;background:#f4f6fa;color:#555;border-radius:10px;text-decoration:none;font-size:13px;border:1.5px solid #e4e8ee;">✕ Netejar filtre</a>
        </div>
        <?php if (in_array($rol, ['ADMIN', 'RW'])): ?>
        <div class="filtre-grup" style="align-self:flex-end">
          <button type="button" class="btn-eliminar" onclick="confirmarEliminar()">🗑️ Eliminar VirKo</button>
        </div>
        <?php endif; ?>
      <?php endif; ?>
      <div class="auto-update">
        <span class="dot"></span>
        S'actualitza cada 30s
      </div>
    </div>
  </form>

  <?php if ($mac_filter && in_array($rol, ['ADMIN', 'RW'])): ?>
  <form id="form-eliminar" method="POST" style="display:none">
    <input type="hidden" name="accio"        value="eliminar_virko">
    <input type="hidden" name="mac_eliminar" value="<?= htmlspecialchars($mac_filter) ?>">
    <input type="hidden" name="limit_actual" value="<?= $limit ?>">
  </form>
  <?php endif; ?>

  <!-- ═══ GRÀFIQUES ═══ -->
  <div class="grafiques-grid">

    <div class="grafica-card">
      <div class="grafica-cap">
        <h3>🌡️ Temperatura (°C)</h3>
        <span id="qualitat-aire" style="padding:5px 13px;border-radius:20px;font-size:12px;font-weight:600;"></span>
      </div>
      <canvas id="graficaTemp" height="130"></canvas>
    </div>

    <div class="grafica-card">
      <div class="grafica-cap">
        <h3>💨 CO2 (ppm)</h3>
        <span id="qualitat-co2" style="padding:5px 13px;border-radius:20px;font-size:12px;font-weight:600;"></span>
      </div>
      <canvas id="graficaCO2" height="130"></canvas>
    </div>

  </div>

  <!-- ═══ TAULA REGISTRES ═══ -->
  <div class="taula-wrap">
    <div class="taula-cap">
      <h2>📋 Registres de sensors</h2>
      <span><?= $dades->num_rows ?> registres</span>
    </div>
    <table>
      <tr>
        <th>Timestamp</th>
        <th>Temperatura</th>
        <th>CO2</th>
        <th>Humitat</th>
        <th>Pressió</th>
        <th>LDR</th>
        <th>IAQ</th>
        <th>Virko</th>
      </tr>
      <?php if ($dades->num_rows === 0): ?>
        <tr><td colspan="8" class="buit">No hi ha dades registrades encara.</td></tr>
      <?php else: ?>
      <?php while ($f = $dades->fetch_assoc()):
        // Badge per IAQ a la taula
        $iaq_v = intval($f['iaq']);
        if ($iaq_v > 150)      { $b_cls = 'badge-ko';  $b_lbl = '🔴'; }
        elseif ($iaq_v > 50)   { $b_cls = 'badge-mig'; $b_lbl = '🔵'; }
        else                   { $b_cls = 'badge-bo';  $b_lbl = '🟢'; }
      ?>
      <tr>
        <td style="color:#aaa;font-size:12px;white-space:nowrap"><?= $f['timestamp'] ?></td>
        <td class="val-temp"><?= $f['temp'] ?> °C</td>
        <td class="val-co2"><?= $f['co2'] ?> ppm</td>
        <td class="val-hum"><?= $f['humitat'] ?> %</td>
        <td style="color:#666"><?= $f['pressio'] ?> hPa</td>
        <td style="color:#666"><?= $f['ldr'] ?></td>
        <td><span class="badge-iaq <?= $b_cls ?>"><?= $b_lbl ?> <?= $f['iaq'] ?></span></td>
        <td><span class="val-mac"><?= htmlspecialchars($f['mac']) ?></span></td>
      </tr>
      <?php endwhile; ?>
      <?php endif; ?>
    </table>
  </div>

</main>

<footer>
  VirKO &copy; <?= date('Y') ?> — Sistema de monitoratge de qualitat de l'aire
</footer>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// ═══════════════════════════════════════════
// Dades per a les gràfiques (últims 25 punts)
// ═══════════════════════════════════════════
var labels = <?php
    $dades->data_seek(0);
    $lbl = []; $tarr = []; $carr = [];
    $cnt = 0;
    while ($f = $dades->fetch_assoc()) {
        if ($cnt >= 25) break;
        $lbl[]  = substr($f['timestamp'], 11, 5);
        $tarr[] = $f['temp'];
        $carr[] = $f['co2'];
        $cnt++;
    }
    echo json_encode(array_reverse($lbl));
?>;

var dadesTemp = <?= json_encode(array_reverse($tarr)) ?>;
var dadesCO2  = <?= json_encode(array_reverse($carr)) ?>;

var opcionsComunes = {
    responsive: true,
    plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
    scales: {
        x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#bbb' } },
        y: { grid: { color: '#f5f5f8' }, ticks: { font: { size: 10 }, color: '#bbb' } }
    },
    elements: { point: { radius: 3, hoverRadius: 5 }, line: { tension: 0.4 } },
    interaction: { mode: 'nearest', axis: 'x', intersect: false }
};

new Chart(document.getElementById('graficaTemp'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            data: dadesTemp,
            borderColor: '#ff6b35',
            backgroundColor: 'rgba(255,107,53,0.07)',
            fill: true,
            borderWidth: 2.5,
            pointBackgroundColor: '#ff6b35'
        }]
    },
    options: opcionsComunes
});

new Chart(document.getElementById('graficaCO2'), {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            data: dadesCO2,
            borderColor: '#0066cc',
            backgroundColor: 'rgba(0,102,204,0.07)',
            fill: true,
            borderWidth: 2.5,
            pointBackgroundColor: '#0066cc'
        }]
    },
    options: opcionsComunes
});

// ═══════════════════════════════════════════
// Qualitat de l'aire als badges de les gràfiques
// Reflectim la mateixa lògica que el PHP (IAQ + CO2)
// ═══════════════════════════════════════════
// Mateixa lògica que vRGB() de l'Arduino:
// LLINDAR_VERD=800, LLINDAR_VERMELL=1000
// LED_R: CO2 >= 1000 | LED_B: 800<=CO2<1000 | LED_G: CO2<800
var co2Actual = dadesCO2[dadesCO2.length - 1];

var tagCO2  = document.getElementById('qualitat-co2');
var tagAire = document.getElementById('qualitat-aire');

function setBadge(el, text, bg, color, border) {
    el.textContent = text;
    el.style.cssText = 'background:' + bg + ';color:' + color + ';padding:5px 13px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid ' + border;
}

if (co2Actual >= 1000) {
    setBadge(tagCO2,  '🔴 Aire dolent',      '#fff0f0', '#cc0000', '#ffcccc');
    setBadge(tagAire, '🔴 Aire dolent',      '#fff0f0', '#cc0000', '#ffcccc');
} else if (co2Actual >= 800) {
    setBadge(tagCO2,  '🔵 Aire acceptable',  '#e8f4fd', '#0066cc', '#b3d9f7');
    setBadge(tagAire, '🔵 Aire acceptable',  '#e8f4fd', '#0066cc', '#b3d9f7');
} else {
    setBadge(tagCO2,  '🟢 Aire excel·lent',  '#e8f5e9', '#2e7d32', '#c8e6c9');
    setBadge(tagAire, '🟢 Aire excel·lent',  '#e8f5e9', '#2e7d32', '#c8e6c9');
}

// ═══════════════════════════════════════════
// AJAX — Actualització d'estat cada 5 segons
// ═══════════════════════════════════════════
var mac = <?= json_encode($mac_filter) ?>;
var sseUrl = 'status.php' + (mac ? '?mac=' + encodeURIComponent(mac) : '');

function actualitzarEstat() {
    fetch(sseUrl)
        .then(function(r) { return r.json(); })
        .then(function(d) {
            // ── Banner estat ──
            var dot        = document.querySelector('.estat-dot');
            var textEstat  = document.querySelector('.estat-text-ok, .estat-text-ko');
            var tempsEstat = document.querySelector('.estat-temps');
            var banner     = document.querySelector('.estat-virko');

            if (dot && textEstat && tempsEstat && banner) {
                if (d.connectada) {
                    dot.className       = 'estat-dot connectada';
                    textEstat.className = 'estat-text-ok';
                    textEstat.textContent = 'VirKo connectada';
                    tempsEstat.textContent = '— Últim registre fa ' + (d.minuts === 0 ? "menys d'1 minut" : d.minuts + (d.minuts === 1 ? ' minut' : ' minuts'));
                    banner.style.borderLeftColor = '#4caf50';
                } else {
                    dot.className       = 'estat-dot desconnectada';
                    textEstat.className = 'estat-text-ko';
                    textEstat.textContent = 'VirKo desconnectada';
                    tempsEstat.textContent = '— Últim registre fa ' + d.minuts + (d.minuts === 1 ? ' minut' : ' minuts');
                    banner.style.borderLeftColor = '#f44336';
                }
            }

            // ── Badge qualitat LED ──
            var badge = document.querySelector('.qualitat-badge');
            if (badge) {
                var ledEl = badge.querySelector('.led');
                var configs = {
                    'dolent':     { cls: 'vermell', label: 'Aire dolent',    bg: '#fff0f0', color: '#cc0000', border: '#ffcccc' },
                    'acceptable': { cls: 'blau',    label: 'Aire acceptable', bg: '#e8f4fd', color: '#0066cc', border: '#b3d9f7' },
                    'excelllent': { cls: 'verd',    label: "Aire excel·lent", bg: '#e8f5e9', color: '#2e7d32', border: '#c8e6c9' },
                };
                var cfg = configs[d.qualitat];
                if (cfg && ledEl) {
                    ledEl.className = 'led ' + cfg.cls;
                    badge.style.background  = cfg.bg;
                    badge.style.color       = cfg.color;
                    badge.style.borderColor = cfg.border;
                    // Actualitzem el text (node de text final)
                    var nodes = badge.childNodes;
                    for (var i = nodes.length - 1; i >= 0; i--) {
                        if (nodes[i].nodeType === 3) { nodes[i].textContent = ' ' + cfg.label; break; }
                    }
                }
            }
        })
        .catch(function() {}); // Silenci en cas d'error de xarxa
}

// Comprova cada 5 segons
actualitzarEstat();
setInterval(actualitzarEstat, 5000);

// Recarrega completa cada 30s per actualitzar les gràfiques i taula
setTimeout(() => location.reload(), 30000);

function confirmarEliminar() {
    var mac = <?= json_encode($mac_filter) ?>;
    if (confirm('Segur que vols eliminar la VirKo ' + mac + ' de la taula?\n\nAquesta acció no es pot desfer.')) {
        document.getElementById('form-eliminar').submit();
    }
}
</script>

</body>
</html>