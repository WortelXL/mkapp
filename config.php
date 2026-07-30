<?php
/**
 * CONFIGURATIE
 * Pas deze waarden aan zodat ze overeenkomen met jouw (externe) database
 * en jouw evenement.
 */

// ---- Externe database ----------------------------------------------
define('DB_HOST', 'localhost');       // bv. 'db.mijnhost.nl' of een IP-adres
define('DB_PORT', '3306');
define('DB_NAME', 'meldkamer');
define('DB_USER', 'meldkamer_user');
define('DB_PASS', 'wijzig_dit_wachtwoord');
define('DB_CHARSET', 'utf8mb4');

// ---- Evenement -------------------------------------------------------
define('EVENT_NAAM', 'Evenement 2026');
// Eerste dag van het evenement (yyyy-mm-dd). Wordt gebruikt om meldingen
// te nummeren per festivaldag (dag 1, 2 of 3) en voor het meld-ID.
define('EVENT_START_DATUM', '2026-08-14');
define('EVENT_AANTAL_DAGEN', 3);

// ---- Overig ------------------------------------------------------------
date_default_timezone_set('Europe/Amsterdam');

// Sessie moet gestart zijn voordat er output is (voor login beheer)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
