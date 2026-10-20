<?php
/**
 * LocalMarket — deconnexion.php
 * Détruit la session et redirige vers l'accueil
 */
session_start();
session_unset();
session_destroy();

header('Location: ../pages/index.php?deconnecte=1');
exit;
