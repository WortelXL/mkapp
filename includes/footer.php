<?php $versies = get_versies($pdo); ?>
    <p class="footer-note">
        Meldkamer systeem — <?= e(event_naam($pdo)) ?>
        <button type="button" class="versie-knop" onclick="document.getElementById('wijzigingen-dialog').showModal()"><?= e(huidige_versie($pdo)) ?></button>
    </p>

    <dialog id="wijzigingen-dialog" class="wijzigingen-dialog">
        <div class="wijzigingen-kop">
            <h2>Wat is er nieuw</h2>
            <button type="button" class="wijzigingen-sluiten" onclick="document.getElementById('wijzigingen-dialog').close()" aria-label="Sluiten">&times;</button>
        </div>
        <div class="wijzigingen-inhoud">
            <?php if (!$versies): ?>
                <p style="color:var(--muted);">Nog geen wijzigingenlog beschikbaar.</p>
            <?php endif; ?>
            <?php foreach ($versies as $release): ?>
                <div class="wijzigingen-release">
                    <p class="wijzigingen-release-kop"><?= e($release['versienummer']) ?> <span>&middot; <?= e($release['datum']) ?></span></p>
                    <?= render_wijzigingen_html($release['wijzigingen']) ?>
                </div>
            <?php endforeach; ?>
        </div>
    </dialog>
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

// Onthoudt de open/dicht-stand van elk schakelaartje (protocol-/
// classificatiedetails, logboek, etc.) per pagina, zodat een blokje open
// blijft staan nadat je er iets in toevoegt/wijzigt (de pagina herlaadt
// dan) -- totdat je 'm zelf weer dichtklikt.
(function () {
    document.querySelectorAll('.log-toggle-checkbox[id]').forEach(function (checkbox) {
        const sleutel = 'meldkamer_toggle_' + location.pathname + '_' + checkbox.id;
        const opgeslagen = localStorage.getItem(sleutel);
        if (opgeslagen === '1') {
            checkbox.checked = true;
        } else if (opgeslagen === '0') {
            checkbox.checked = false;
        }
        checkbox.addEventListener('change', function () {
            localStorage.setItem(sleutel, checkbox.checked ? '1' : '0');
        });
    });
})();
</script>
</body>
</html>
