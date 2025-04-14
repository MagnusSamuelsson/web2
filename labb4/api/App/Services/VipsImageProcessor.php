<?php

namespace App\Services;

use App\Models\File;
use Jcupitt\Vips\Image;
use App\Services\ImageProcessorInterface;

/**
 * Class VipsImageProcessor
 *
 * Implementerar ImageProcessorInterface med hjälp av libvips (via PHP-bindningen Jcupitt\Vips\Image) för att
 * hantera och bearbeta bilddata. Vid konstruktion läses binär data från "php://input" in, vilket därefter används
 * för att skapa och manipulera bilden. Klassen erbjuder metoder för att validera bilddata, skapa och skala bilden,
 * erhålla dimensioner och skalfaktorer, kontrollera om bilden är kvadratisk, konvertera bilden till WebP-format samt
 * att frigöra resurser.
 *
 */
class VipsImageProcessor implements ImageProcessorInterface
{
    /**
     * @var string Innehåller den binära bilddatan från "php://input".
     */
    private string $binaryData;

    /**
     * @var Image Instans av Vips-bild.
     */
    private Image $image;

    /**
     * @var int Maximalt tillåtet värde för bildens bredd.
     */
    private int $maxWidth = 1500;

    /**
     * @var int Maximalt tillåtet värde för bildens höjd.
     */
    private int $maxHeight = 1500;

    /**
     * @var int Ursprungsbildens bredd före eventuell skalning.
     */
    private int $originalWidth;

    /**
     * @var int Ursprungsbildens höjd före eventuell skalning.
     */
    private int $originalHeight;

    /**
     * @var int Aktuell bildbredd efter eventuell skalning.
     */
    private int $width;

    /**
     * @var int Aktuell bildhöjd efter eventuell skalning.
     */
    private int $height;

    /**
     * Konstruktor som läser in den binära bilden från "php://input" när ett objekt av VipsImageProcessor skapas.
     *
     * @return void
     */
    public function __construct()
    {
        $this->binaryData = file_get_contents('php://input');
    }

    /**
     * Kontrollerar om den inlästa binära datan representerar en giltig bild genom att försöka skapa en bildinstans med Vips.
     * Om en exception kastas antas datan inte vara en giltig bild.
     *
     * @return bool Returnerar true om datan är en giltig bild, annars false.
     */
    public function isImage(): bool
    {
        try {
            Image::newFromBuffer($this->binaryData);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Skapar en Vips-bild från den inlästa binära datan. Metoden utför följande steg:
     * - Skapar bilden från bufferten och applicerar automatisk rotation (autorot) baserat på bildens metadata.
     * - Sparar originalbildens dimensioner.
     * - Beräknar en skalningsfaktor om bildens bredd eller höjd överstiger de maximalt tillåtna värdena,
     *   och skalar bilden i enlighet därmed.
     * - Uppdaterar de aktuella dimensionerna efter eventuell skalning.
     *
     * @return self Returnerar instansen för att möjliggöra metodkedjning.
     */
    public function createImageFromString(): self
    {
        $this->image = Image::newFromBuffer($this->binaryData);

        $this->image->autorot();

        $this->originalWidth = $this->image->width;
        $this->originalHeight = $this->image->height;

        $scaleX = ($this->originalWidth > $this->maxWidth) ? $this->maxWidth / $this->originalWidth : 1;
        $scaleY = ($this->originalHeight > $this->maxHeight) ? $this->maxHeight / $this->originalHeight : 1;
        $scale = min($scaleX, $scaleY);

        if ($scale < 1) {
            $this->image = $this->image->resize($scale);
        }

        $this->width = $this->image->width;
        $this->height = $this->image->height;

        return $this;
    }

    /**
     * Sätter det maximala tillåtna värdet för bildens bredd före skalning.
     *
     * @param int $maxWidth Maximala bredden i pixlar.
     *
     * @return self Returnerar instansen för metodkedjning.
     */
    public function setMaxWidth(int $maxWidth): self
    {
        $this->maxWidth = $maxWidth;
        return $this;
    }

    /**
     * Sätter det maximala tillåtna värdet för bildens höjd före skalning.
     *
     * @param int $maxHeight Maximala höjden i pixlar.
     *
     * @return self Returnerar instansen för metodkedjning.
     */
    public function setMaxHeight(int $maxHeight): self
    {
        $this->maxHeight = $maxHeight;
        return $this;
    }

    /**
     * Beräknar skalfaktorn i X-led genom att dividera den aktuella bildens bredd med originalbildens bredd.
     *
     * @return float Skalfaktorn för bredden.
     */
    public function getScaleX(): float
    {
        return $this->width / $this->originalWidth;
    }


    /**
     * Beräknar skalfaktorn i Y-led genom att dividera den aktuella bildens höjd med originalbildens höjd.
     *
     * @return float Skalfaktorn för höjden.
     */
    public function getScaleY(): float
    {
        return $this->height / $this->originalHeight;
    }

    /**
     * Returnerar originalbildens bredd före eventuell skalning.
     *
     * @return int Ursprungsbildens bredd i pixlar.
     */
    public function getOriginalWidth(): int
    {
        return $this->originalWidth;
    }

    /**
     * Returnerar originalbildens höjd före eventuell skalning.
     *
     * @return int Ursprungshöjden i pixlar.
     */
    public function getOriginalHeight(): int
    {
        return $this->originalHeight;
    }

    /**
     * Hämtar den aktuella bredden på bilden efter eventuell skalning har tillämpats.
     *
     * @return int Aktuell bildbredd i pixlar.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Hämtar den aktuella höjden på bilden efter eventuell skalning.
     *
     * @return int Aktuell bildhöjd i pixlar.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Kontrollerar om bilden är kvadratisk genom att jämföra bredden och höjden.
     *
     * @return bool Returnerar true om bredden och höjden är lika, annars false.
     */
    public function isSquare(): bool
    {
        return $this->image->width === $this->image->height;
    }

    /**
     * Konverterar den aktuella bilden till WebP-format med en kvalitet på 80 och sparar den i en File-instans.
     * Vid eventuella fel under skrivningen returneras false.
     *
     * @return File|false Returnerar ett File-objekt med bilden i WebP-format vid framgång, annars false.
     */
    public function convertToWebP(): File|false
    {
        $imgFile = new File();
        try {
            $this->image->writeToFile($imgFile->path(), ['Q' => 80]); // Q = quality
        } catch (\Exception $e) {
            return false;
        }
        return $imgFile;
    }

    /**
     * Frigör resursen som används av Vips-bilden. Eftersom libvips använder garbage collection
     * sker ingen manuell rensning, utan referensen till bilden tas bort för att möjliggöra minnesfrigöring.
     *
     * @return bool Returnerar true då operationen alltid lyckas.
     */
    public function destroyImage(): bool
    {
        // libvips använder garbage collection, ingen manuell clear() behövs
        unset($this->image);
        return true;
    }

    /**
     * Avslutar objektet genom att anropa destroyImage och därmed frigöra bildresurser vid objektets livscykels slut.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->destroyImage();
    }
}
