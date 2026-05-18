<?php
// Iniciem la sessió per gestionar l'autenticació de l'usuari
session_start();

// Si ja està autenticat, el redirigim directament a la gestió
if (isset($_SESSION['usuari'])) {
    header("Location: gestio.php");
    exit;
}

$error = "";

// Processem el formulari quan l'usuari prem "Iniciar sessió"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Connectem a la base de dades MySQL
    $conn = new mysqli("db", "root", "root", "virko");
    
    // Escapem l'usuari per evitar injeccions SQL
    $usuari = $conn->real_escape_string(trim($_POST['usuari']));
    
    // Busquem l'usuari a la base de dades
    $res = $conn->query("SELECT * FROM usuaris WHERE usuari='$usuari'");

    if ($res->num_rows === 1) {
        $user = $res->fetch_assoc();
        // Verifiquem la contrasenya de forma segura amb password_verify
        if (password_verify($_POST['contrasenya'], $user['contrasenya'])) {
            // Guardem les dades de l'usuari a la sessió
            $_SESSION['usuari']      = $user['usuari'];
            $_SESSION['rol']         = $user['rol'];
            $_SESSION['nom_complet'] = $user['nom_complet'];
            header("Location: gestio.php");
            exit;
        }
    }
    // Si les credencials són incorrectes mostrem error
    $error = "Usuari o contrasenya incorrectes";
}
?>
<!DOCTYPE html>
<html lang="ca">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VirKO — Accés</title>
  <style>
    /* Reset bàsic i configuració general */
    * { box-sizing: border-box; margin: 0; padding: 0; }

    /* Fons degradat blau corporatiu */
    body {
      font-family: 'Segoe UI', sans-serif;
      background: linear-gradient(135deg, #0a1628 0%, #1a3a6b 50%, #0066cc 100%);
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
    }

    /* Targeta principal del login */
    .caixa {
      background: white;
      padding: 48px 44px;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      width: 420px;
    }

    /* Contenidor del logo */
    .logo-wrap {
      text-align: center;
      margin-bottom: 36px;
    }

    /* Logo de l'aplicació */
    .logo-wrap img {
      width: 280px;
      max-width: 100%;
    }

    /* Etiquetes dels camps del formulari */
    label {
      display: block;
      font-size: 13px;
      font-weight: 500;
      color: #444;
      margin-bottom: 6px;
      margin-top: 18px;
    }

    /* Camps d'entrada de text */
    input {
      width: 100%;
      padding: 12px 16px;
      border: 1.5px solid #e0e0e0;
      border-radius: 10px;
      font-size: 14px;
      transition: border .2s, box-shadow .2s;
      color: #333;
    }

    /* Efecte quan el camp està actiu */
    input:focus {
      outline: none;
      border-color: #0066cc;
      box-shadow: 0 0 0 3px rgba(0,102,204,0.1);
    }

    /* Botó d'iniciar sessió */
    button {
      width: 100%;
      padding: 13px;
      background: linear-gradient(135deg, #0066cc, #0052a3);
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 28px;
      letter-spacing: 0.3px;
      transition: transform .1s, box-shadow .2s;
    }

    /* Efecte hover del botó */
    button:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(0,102,204,0.35);
    }

    /* Missatge d'error */
    .error {
      background: #fff0f0;
      border: 1.5px solid #ffcccc;
      color: #cc0000;
      padding: 11px 16px;
      border-radius: 10px;
      font-size: 13px;
      margin-bottom: 4px;
      text-align: center;
    }

    /* Peu de pàgina */
    .peu {
      text-align: center;
      font-size: 12px;
      color: #bbb;
      margin-top: 28px;
    }

    /* Línia separadora */
    .divider {
      height: 1px;
      background: #f0f0f0;
      margin: 8px 0 4px;
    }
  </style>
</head>
<body>
<div class="caixa">

  <!-- Logo del projecte VirKO -->
  <div class="logo-wrap">
    <img src="logo.png" alt="VirKO — Mesura, Control, Fiabilitat">
  </div>

  <div class="divider"></div>

  <!-- Missatge d'error si les credencials són incorrectes -->
  <?php if ($error): ?>
    <div class="error"><?= $error ?></div>
  <?php endif; ?>

  <!-- Formulari d'autenticació -->
  <form method="POST">
    <label>Nom d'usuari</label>
    <input type="text" name="usuari" placeholder="Introdueix el teu usuari" required autofocus>

    <label>Contrasenya</label>
    <input type="password" name="contrasenya" placeholder="Introdueix la contrasenya" required>

    <button type="submit">Iniciar sessió</button>
  </form>

  <!-- Informació del centre -->
  <div class="peu">Institut l'Alzina</div>
</div>
</body>
</html>