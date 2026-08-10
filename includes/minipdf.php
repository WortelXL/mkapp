<?php
/**
 * Minimale, dependency-vrije PDF-generator voor eenvoudige tabelrapporten.
 * Geen Composer/externe library nodig -- bouwt de PDF-bytes rechtstreeks op
 * (standaardlettertype Helvetica, geen afbeeldingen). Voldoende voor een
 * exportrapport met titel + tabel, niet bedoeld als volwaardige PDF-lib.
 */
class MiniPdf
{
    private array $pages = [];
    private string $buffer = '';
    private float $pageW = 841.89;  // A4 liggend (landscape), in punten
    private float $pageH = 595.28;
    private float $margin = 36;
    private float $y = 0;
    private int $fontSize = 9;
    private float $lineHeight = 13;

    public function __construct()
    {
        $this->nieuwePagina();
    }

    public function nieuwePagina(): void
    {
        if ($this->buffer !== '') {
            $this->pages[] = $this->buffer;
        }
        $this->buffer = '';
        $this->y = $this->pageH - $this->margin;
    }

    public function huidigeY(): float
    {
        return $this->y;
    }

    public function ruimteNodig(float $hoogte): void
    {
        if ($this->y - $hoogte < $this->margin) {
            $this->nieuwePagina();
        }
    }

    private function escape(string $tekst): string
    {
        // Standaard PDF-lettertypes ondersteunen alleen WinAnsi (Latin-1);
        // getranslitereerd zodat bv. "ë" niet crasht maar "e" wordt.
        $tekst = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $tekst);
        if ($tekst === false) {
            $tekst = '';
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $tekst);
    }

    /** Tekst op een specifieke x-positie op de huidige regel (y verandert niet) */
    public function tekstOp(float $x, string $tekst, bool $vet = false, ?int $maxBreedtePx = null): void
    {
        if ($maxBreedtePx !== null) {
            $maxTekens = (int) ($maxBreedtePx / ($this->fontSize * 0.55));
            if (mb_strlen($tekst) > $maxTekens) {
                $tekst = mb_substr($tekst, 0, max(0, $maxTekens - 1)) . '…';
            }
        }
        $font = $vet ? '/F2' : '/F1';
        $this->buffer .= sprintf(
            "BT %s %d Tf %.2f %.2f Td (%s) Tj ET\n",
            $font, $this->fontSize, $x, $this->y, $this->escape($tekst)
        );
    }

    /**
     * Print een langere tekst met woordterugloop binnen de opgegeven breedte
     * (in punten), met behoud van expliciete regeleinden in de brontekst.
     * Gebruikt voor omschrijving, logboekregels en protocolinhoud.
     */
    public function paragraaf(float $x, string $tekst, float $breedteInPunten, bool $vet = false): void
    {
        $tekst = trim($tekst);
        if ($tekst === '') {
            return;
        }
        $tekenBreedte = $this->fontSize * 0.5;
        $maxTekensPerRegel = max(10, (int) ($breedteInPunten / $tekenBreedte));

        foreach (explode("\n", $tekst) as $brontekstRegel) {
            $brontekstRegel = trim($brontekstRegel);
            if ($brontekstRegel === '') {
                $this->nieuweRegel();
                continue;
            }
            $huidigeRegel = '';
            foreach (explode(' ', $brontekstRegel) as $woord) {
                $kandidaat = $huidigeRegel === '' ? $woord : $huidigeRegel . ' ' . $woord;
                if (mb_strlen($kandidaat) > $maxTekensPerRegel && $huidigeRegel !== '') {
                    $this->ruimteNodig($this->lineHeight);
                    $this->tekstOp($x, $huidigeRegel, $vet);
                    $this->nieuweRegel();
                    $huidigeRegel = $woord;
                } else {
                    $huidigeRegel = $kandidaat;
                }
            }
            if ($huidigeRegel !== '') {
                $this->ruimteNodig($this->lineHeight);
                $this->tekstOp($x, $huidigeRegel, $vet);
                $this->nieuweRegel();
            }
        }
    }

    /** Springt naar de volgende regel (met automatische pagina-overloop) */
    public function nieuweRegel(): void
    {
        $this->y -= $this->lineHeight;
        if ($this->y < $this->margin) {
            $this->nieuwePagina();
        }
    }

    public function setFontSize(int $size): void
    {
        $this->fontSize = $size;
        $this->lineHeight = $size * 1.5;
    }

    public function lijn(): void
    {
        $this->buffer .= sprintf(
            "0.5 w\n0.6 0.6 0.6 RG\n%.2f %.2f m %.2f %.2f l S\n",
            $this->margin, $this->y, $this->pageW - $this->margin, $this->y
        );
    }

    public function paginaBreedte(): float
    {
        return $this->pageW - (2 * $this->margin);
    }

    public function marge(): float
    {
        return $this->margin;
    }

    public function versturen(string $bestandsnaam): void
    {
        $this->pages[] = $this->buffer;
        $aantalPaginas = count($this->pages);

        $paginaStart = 3;
        $contentStart = $paginaStart + $aantalPaginas;
        $fontObjId = $contentStart + $aantalPaginas;
        $fontVetObjId = $fontObjId + 1;
        $totaalObjs = $fontVetObjId;

        $objecten = [];

        $kids = [];
        for ($i = 0; $i < $aantalPaginas; $i++) {
            $kids[] = ($paginaStart + $i) . ' 0 R';
        }
        $objecten[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objecten[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . "] /Count $aantalPaginas >>";

        for ($i = 0; $i < $aantalPaginas; $i++) {
            $paginaObjId = $paginaStart + $i;
            $contentObjId = $contentStart + $i;
            $objecten[$paginaObjId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$this->pageW} {$this->pageH}] "
                . "/Resources << /Font << /F1 $fontObjId 0 R /F2 $fontVetObjId 0 R >> >> /Contents $contentObjId 0 R >>";
            $stream = $this->pages[$i];
            $objecten[$contentObjId] = '<< /Length ' . strlen($stream) . " >>\nstream\n$stream\nendstream";
        }

        $objecten[$fontObjId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objecten[$fontVetObjId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        ksort($objecten);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objecten as $objNum => $body) {
            $offsets[$objNum] = strlen($pdf);
            $pdf .= "$objNum 0 obj\n$body\nendobj\n";
        }
        $xrefStart = strlen($pdf);
        $count = $totaalObjs + 1;
        $pdf .= "xref\n0 $count\n0000000000 65535 f \n";
        for ($i = 1; $i <= $totaalObjs; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size $count /Root 1 0 R >>\nstartxref\n$xrefStart\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $bestandsnaam . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }
}
