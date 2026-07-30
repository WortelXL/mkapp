<?php
require_once __DIR__ . '/../config.php';

function get_pdo(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo '<!doctype html><html lang="nl"><head><meta charset="utf-8">';
        echo '<title>Databasefout</title></head><body style="font-family:sans-serif;padding:2rem;background:#0b0e14;color:#e6e9ef;">';
        echo '<h1>Kan geen verbinding maken met de database</h1>';
        echo '<p>Controleer de instellingen in <code>config.php</code> (host, gebruikersnaam, wachtwoord, databasenaam) '
           . 'en zorg dat het externe databaseschema geimporteerd is via <code>database.sql</code>.</p>';
        echo '<p style="color:#8b93a7;">Technische foutmelding: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</body></html>';
        exit;
    }

    return $pdo;
}
