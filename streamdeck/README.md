# Stream Deck koppelen aan de Meldkamer-API

Deze map bevat de voorbereiding om via een Stream Deck-knop rechtstreeks
een melding aan te maken in het meldkamersysteem.

## 1. Token koppelen aan een gebruiker

Elk API-token hoort bij een gebruikersaccount (voor herleidbaarheid: je
ziet altijd wie/welke knop een melding heeft aangemaakt). Voor een
Stream Deck gebruik je meestal een apart, "technisch" account (bv. met
gebruikersnaam `SDA`) in plaats van een echt persoonlijk account.

Je kunt een token op twee manieren koppelen:

- **Via het beheerpaneel** (aanbevolen): Beheer &rarr; Gebruikers &rarr;
  knop "Token genereren" bij het gewenste account. Het token verschijnt
  dan meteen in de tabel.
- **Via SQL**, als je een specifiek, al gegenereerd token wilt hergebruiken:
  ```sql
  UPDATE gebruikers
  SET api_token = '<jouw-token-hier>'
  WHERE gebruikersnaam = '<gebruikersnaam>';
  ```
  **Let op:** zet een bestand met een echt token nooit in git/GitHub. Zie
  `.gitignore` in de hoofdmap — bestanden met een echt token horen daar
  expliciet in te staan (net als `koppel_token_sda.sql`, dat hier lokaal
  wel aanwezig kan zijn maar niet wordt meegecommit).

## 2. Stream Deck-knop instellen

Gebruik de ingebouwde "Website"-actie (of "System: Website") op een knop:

- **URL**: `https://jouw-domein/api/melding.php`
- **Methode**: `POST`
- **Headers**: `Authorization: Bearer <jouw-token-hier>`
- **Body (form-encoded)**, bijvoorbeeld:
  ```
  classificatie=reanimatie
  locatie=Podium 1
  prioriteit=kritiek
  ```

Voor elk scenario dat je met één druk op de knop wilt inschieten, maak je
een aparte knop met een net iets andere combinatie van `classificatie`,
`locatie` en `prioriteit`. Zie de hoofd-`README.md` voor het volledige
overzicht van ondersteunde velden.

## 3. Testen

```bash
curl -X POST https://jouw-domein/api/melding.php \
  -H "Authorization: Bearer <jouw-token-hier>" \
  -d "classificatie=reanimatie" \
  -d "locatie=Podium 1" \
  -d "prioriteit=kritiek"
```

Bij succes: een JSON-antwoord met `"success": true` en een `meld_id`. Ga
naar het dashboard om te checken dat de melding (en het geluidje, als dat
aan staat) verschijnt.
