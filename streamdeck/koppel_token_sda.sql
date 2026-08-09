-- ============================================================
-- Koppelt het opgegeven API-token aan de tijdelijke gebruiker "SDA",
-- bedoeld voor de Stream Deck-koppeling.
--
-- Voorwaarde: het account "SDA" moet al bestaan (aangemaakt via
-- Beheer -> Gebruikers). Dit script wijzigt alleen het token, niet
-- de rol, het wachtwoord of de actief-status van dat account.
-- ============================================================

UPDATE gebruikers
SET api_token = 'd4c8ce309b52c03550188de6c420b704ac4ae8c7'
WHERE gebruikersnaam = 'SDA';

-- Ter controle: laat zien of het gelukt is (moet 1 rij tonen met het token)
SELECT gebruikersnaam, naam, rol, actief, api_token
FROM gebruikers
WHERE gebruikersnaam = 'SDA';
