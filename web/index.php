<?php
// =====================================================
// index.php — Pàgina principal del sistema VirKO
// Redirigeix automàticament al login o a la gestió
// segons si l'usuari ja està autenticat o no
// =====================================================

// Iniciem la sessió per comprovar si l'usuari ja ha iniciat sessió
session_start();

// Si l'usuari ja està autenticat el portem a la gestió
// Si no, el portem al login
if (isset($_SESSION['usuari'])) {
    header("Location: gestio.php");
} else {
    header("Location: login.php");
}
exit;
?>