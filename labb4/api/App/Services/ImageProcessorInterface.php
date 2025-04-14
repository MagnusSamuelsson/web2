<?php

namespace App\Services;

use App\Models\File;

/**
 * Interface ImageProcessorInterface
 *
 * Definierar ett kontrakt för bildbehandling som inkluderar metoder för:
 * - Att verifiera om inmatad data är en giltig bild.
 * - Att skapa en bild från strängdata.
 * - Att ställa in maximala dimensioner för bildskalning.
 * - Att hämta original- och aktuella bilddimensioner samt skalfaktorer.
 * - Att kontrollera om bilden är kvadratisk.
 * - Att konvertera bilden till WebP-format.
 * - Att frigöra bildresurser.
 *
 * Detta interface möjliggör att olika implementeringar kan användas utbytbara så länge de uppfyller dessa krav.
 */
interface ImageProcessorInterface
{
    /**
     * Kontrollerar om den inlästa datan representerar en giltig bild.
     *
     * @return bool Returnerar true om datan är en bild, annars false.
     */
    public function isImage(): bool;

    /**
     * Skapar en bild från den inlästa binära datan.
     *
     * @return self Returnerar instansen av ImageProcessorInterface för att möjliggöra metodkedjning.
     */
    public function createImageFromString(): self;
    /**
     * Sätter ett maximalt värde för bildens bredd.
     *
     * @param int $maxWidth Maximala bredden i pixlar.
     *
     * @return self Returnerar instansen för metodkedjning.
     */
    public function setMaxWidth(int $maxWidth): self;

    /**
     * Sätter ett maximalt värde för bildens höjd.
     *
     * @param int $maxHeight Maximala höjden i pixlar.
     *
     * @return self Returnerar instansen för metodkedjning.
     */
    public function setMaxHeight(int $maxHeight): self;

    /**
     * Returnerar skalfaktorn i breddled jämfört med originalbildens bredd.
     *
     * @return float Skalfaktorn för bredden.
     */
    public function getScaleX(): float;

    /**
     * Returnerar skalfaktorn i höjdled jämfört med originalbildens höjd.
     *
     * @return float Skalfaktorn för höjden.
     */
    public function getScaleY(): float;

    /**
     * Hämtar originalbildens bredd innan eventuell skalning.
     *
     * @return int Ursprungsbildens bredd i pixlar.
     */
    public function getOriginalWidth(): int;

    /**
     * Hämtar originalbildens höjd innan eventuell skalning.
     *
     * @return int Ursprungshöjden i pixlar.
     */
    public function getOriginalHeight(): int;

    /**
     * Hämtar den aktuella bredden på bilden efter eventuell skalning.
     *
     * @return int Bildens aktuella bredd i pixlar.
     */
    public function getWidth(): int;

    /**
     * Hämtar den aktuella höjden på bilden efter eventuell skalning.
     *
     * @return int Bildens aktuella höjd i pixlar.
     */
    public function getHeight(): int;

    /**
     * Kontrollerar om bilden är kvadratisk, det vill säga om bredden och höjden är identiska.
     *
     * @return bool Returnerar true om bilden är kvadratisk, annars false.
     */
    public function isSquare(): bool;

    /**
     * Konverterar bilden till WebP-format och returnerar ett File-objekt med den konverterade bilden.
     * Vid fel returneras false.
     *
     * @return File|false Returnerar ett File-objekt med bilden i WebP-format, eller false om konverteringen misslyckas.
     */
    public function convertToWebP(): File|false;

    /**
     * Frigör de resurser som används av bildprocessorn.
     *
     * @return bool Returnerar true om resurserna frigjordes korrekt.
     */
    public function destroyImage(): bool;
}
