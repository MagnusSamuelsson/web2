<?php

namespace App\Services;

use App\Models\File;
use Imagick;
use App\Services\ImageProcessorInterface;

/**
 * Class ImagickImageProcessor
 *
 * Denna klass implementerar ImageProcessorInterface med hjälp av Imagick-biblioteket för att hantera
 * och bearbeta bilddata. Vid instansiering läses binär data in från "php://input". Klassen erbjuder metoder
 * för att verifiera bilddata, skapa en Imagick-bild från strängdata, justera bildens storlek, normalisera EXIF-orientering,
 * konvertera bilden till WebP-format samt att frigöra använda resurser.
 *
 */
class ImagickImageProcessor implements ImageProcessorInterface
{
    /**
     * @var string Innehåller binär data för bilden, läst från "php://input".
     */
    private string $binaryData;

    /**
     * @var Imagick Instans av Imagick som representerar bilden.
     */
    private Imagick $image;

     /**
     * @var int Maximalt tillåtet värde för bildens bredd.
     */
    private int $maxWidth = 1500;

    /**
     * @var int Maximalt tillåtet värde för bildens höjd.
     */
    private int $maxHeight = 1500;

    /**
     * @var int Bildens ursprungliga bredd före skalning.
     */
    private int $originalWidth;

    /**
     * @var int Bildens ursprungliga höjd före skalning.
     */
    private int $originalHeight;

    /**
     * @var int Aktuell bredd efter eventuell skalning.
     */
    private int $width;

    /**
     * @var int Aktuell höjd efter eventuell skalning.
     */
    private int $height;

    /**
     * Konstrukterar ett nytt ImagickImageProcessor-objekt genom att läsa in binär data från "php://input".
     *
     * @return void
     */
    public function __construct(
    ) {
        $this->binaryData = file_get_contents("php://input");
    }

    /**
     * Kontrollerar om den inlästa binära datan representerar en giltig bild.
     *
     * @return bool Returnerar true om datan är en bild, annars false.
     */
    public function isImage(): bool
    {
        return getimagesizefromstring($this->binaryData) !== false;
    }

    /**
     * Skapar en Imagick-instans från den inlästa binära datan. Metoden:
     * - Läs in bildblob.
     * - Sätter originalbredd och -höjd.
     * - Skalar bilden om den överstiger angivna maxvärden.
     * - Normaliserar bildens EXIF-orientering om nödvändigt.
     * - Uppdaterar de aktuella dimensionerna efter eventuell skalning.
     * - Konverterar bildformatet till WebP med en komprimeringskvalitet på 80.
     *
     * @return self Returnerar instansen av ImagickImageProcessor för att stödja metodkedjning.
     */
    public function createImageFromString(
    ): self {
        $this->image = new Imagick();
        $this->image->readImageBlob($this->binaryData);
        $this->originalWidth = $this->image->getImageWidth();
        $this->originalHeight = $this->image->getImageHeight();
        if ($this->image->getImageWidth() > $this->maxWidth) {
            $this->image->scaleImage($this->maxWidth, 0);
        }

        if ($this->image->getImageHeight() > $this->maxHeight) {
            $this->image->scaleImage(0, $this->maxHeight);
        }

        $orientation = $this->image->getImageOrientation();
        if ($orientation !== Imagick::ORIENTATION_TOPLEFT) {
            $this->normalizeExif($orientation);
        }

        $this->width = $this->image->getImageWidth();
        $this->height = $this->image->getImageHeight();

        $this->image->setImageFormat('webp');
        $this->image->setImageCompressionQuality(80);

        return $this;
    }

    /**
     * Justerar bildens orientering baserat på EXIF-data genom att rotera och/eller spegla bilden.
     * Efter hanteringen sätts orienteringen till ORIENTATION_TOPLEFT och EXIF-data tas bort.
     *
     * @param int $orientation Den ursprungliga orienteringen från EXIF-data.
     *
     * @return void
     */
    private function normalizeExif(int $orientation): void
    {
        switch ($orientation) {
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $this->image->rotateImage("#000", 180);
                break;
            case Imagick::ORIENTATION_RIGHTTOP:
                $this->image->rotateImage("#000", 90);
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $this->image->rotateImage("#000", 270);
                break;
            case Imagick::ORIENTATION_BOTTOMLEFT:
                $this->image->rotateImage("#000", 180);
                $this->image->flopImage();
                break;
            case Imagick::ORIENTATION_LEFTTOP:
                $this->image->rotateImage("#000", 90);
                $this->image->flopImage();
                break;
            case Imagick::ORIENTATION_RIGHTBOTTOM:
                $this->image->rotateImage("#000", 270);
                $this->image->flopImage();
                break;
            case Imagick::ORIENTATION_TOPRIGHT:
                $this->image->flopImage();
                break;
            default:
                break;
        }
        $this->image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
        $this->image->stripImage();
    }

    /**
     * Sätter det maximala breddvärdet för bildens skalning.
     *
     * @param int $maxWidth Det maximala antalet pixlar för bildens bredd.
     *
     * @return self Returnerar instansen för att möjliggöra metodkedjning.
     */
    public function setMaxWidth(int $maxWidth): self
    {
        $this->maxWidth = $maxWidth;
        return $this;
    }

    /**
     * Sätter det maximala höjdvärdet för bildens skalning.
     *
     * @param int $maxHeight Det maximala antalet pixlar för bildens höjd.
     *
     * @return self Returnerar instansen för att möjliggöra metodkedjning.
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
     * Hämtar bildens ursprungliga bredd innan eventuell skalning.
     *
     * @return int Ursprungsbildens bredd i pixlar.
     */
    public function getOriginalWidth(): int
    {
        return $this->originalWidth;
    }

    /**
     * Hämtar bildens ursprungliga höjd innan eventuell skalning.
     *
     * @return int Ursprungshöjden i pixlar.
     */
    public function getOriginalHeight(): int
    {
        return $this->originalHeight;
    }

    /**
     * Hämtar den aktuella bredden på bilden efter eventuell skalning.
     *
     * @return int Bildens aktuella bredd i pixlar.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Hämtar den aktuella höjden på bilden efter eventuell skalning.
     *
     * @return int Bildens aktuella höjd i pixlar.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Kontrollerar om bilden är kvadratisk, det vill säga om bredden och höjden är lika.
     *
     * @return bool Returnerar true om bilden är kvadratisk, annars false.
     */
    public function isSquare(): bool
    {
        $width = $this->image->getImageWidth();
        $height = $this->image->getImageHeight();
        return $width === $height;
    }


    /**
     * Konverterar bilden till WebP-format och sparar den som ett File-objekt. Vid fel returneras false.
     *
     * @return File|false Returnerar ett File-objekt med den konverterade bilden, eller false vid fel.
     */
    public function convertToWebP(): File|false
    {
        $imgFile = new File();
        try {
            $this->image->setImageFormat('webp');
            $this->image->writeImage($imgFile->path());
        } catch (\Exception $e) {
            return false;
        }
        return $imgFile;
    }

    /**
     * Rensar och frigör resurser som används av Imagick-instansen om den existerar.
     *
     * @return bool Returnerar true om resurserna frigjordes korrekt, annars true om ingen bild existerar.
     */
    public function destroyImage(): bool
    {
        return isset($this->image) ? $this->image->clear() : true;
    }

    /**
     * Avslutar objektet genom att anropa destroyImage och därigenom frigöra eventuella resurser.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->destroyImage();
    }
}