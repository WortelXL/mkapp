<?php
/**
 * CONFIGURATIE
 * Pas deze waarden aan zodat ze overeenkomen met jouw (externe) database
 * en jouw evenement.
 *
 * Draai je de applicatie via Docker? Dan hoef je dit bestand meestal niet
 * aan te passen: alle waarden hieronder kunnen ook via omgevingsvariabelen
 * (environment variables) worden aangeleverd, bijvoorbeeld vanuit
 * docker-compose.yml. Een omgevingsvariabele overschrijft altijd de
 * standaardwaarde hieronder.
 */

function env_of(string $naam, $standaard)
{
    $waarde = getenv($naam);
    return $waarde !== false && $waarde !== '' ? $waarde : $standaard;
}

// ---- Externe database ----------------------------------------------
define('DB_HOST', env_of('DB_HOST', 'localhost'));       // bv. 'db.mijnhost.nl' of een IP-adres
define('DB_PORT', env_of('DB_PORT', '3306'));
define('DB_NAME', env_of('DB_NAME', 'meldkamer'));
define('DB_USER', env_of('DB_USER', 'meldkamer_user'));
define('DB_PASS', env_of('DB_PASS', 'wijzig_dit_wachtwoord'));
define('DB_CHARSET', env_of('DB_CHARSET', 'utf8mb4'));

// ---- Evenement -------------------------------------------------------
define('EVENT_NAAM', env_of('EVENT_NAAM', 'Evenement 2026'));
// Eerste dag van het evenement (yyyy-mm-dd). Wordt gebruikt om meldingen
// te nummeren per festivaldag (dag 1, 2 of 3) en voor het meld-ID.
define('EVENT_START_DATUM', env_of('EVENT_START_DATUM', '2026-08-14'));
define('EVENT_AANTAL_DAGEN', (int) env_of('EVENT_AANTAL_DAGEN', 3));

// ---- Versie -------------------------------------------------------------
define('APP_VERSION', 'V1.3.8');

// ---- Overig ------------------------------------------------------------
date_default_timezone_set(env_of('APP_TIMEZONE', 'Europe/Amsterdam'));

// Sessie moet gestart zijn voordat er output is (voor login beheer)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
