    <p class="footer-note">Meldkamer systeem — <?= e(event_naam($pdo)) ?></p>
</div>
<script>
// Zet de scrollpositie terug na het herladen van deze pagina (na een
// automatische ververssing, of na het versturen van een formulier -- bv.
// een statuswijziging, subtaak afvinken, of een gebruiker activeren in
// het beheerscherm), zodat de pagina niet naar boven springt. Wordt
// eenmalig gebruikt en dan meteen weer gewist, zodat een normale (nieuwe)
// paginabezoek gewoon bovenaan begint.
(function () {
    const sleutel = 'meldkamer_scroll_' + location.pathname;
    const opgeslagen = sessionStorage.getItem(sleutel);
    if (opgeslagen !== null) {
        window.scrollTo(0, parseInt(opgeslagen, 10) || 0);
        sessionStorage.removeItem(sleutel);
    }
})();

// Onthoudt de scrollpositie zodra ergens op deze pagina een formulier
// wordt verstuurd (elke actieknop op elke pagina is een formulier) --
// geldt dus automatisch voor alle pagina's, inclusief het beheergedeelte,
// zonder dat elk formulier apart aangepast hoeft te worden.
document.addEventListener('submit', function () {
    sessionStorage.setItem('meldkamer_scroll_' + location.pathname, window.scrollY);
}, true);
</script>
</body>
</html>
