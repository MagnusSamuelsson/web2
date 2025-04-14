<?php

namespace App\Services;

use App\Models\File;
use App\Services\ImageProcessorInterface;

/**
 * Class GdImageProcessor
 *
 * Denna klass implementerar ImageProcessorInterface med hjälp av PHP:s GD-bibliotek för att hantera
 * och bearbeta bilddata. Vid konstruktion läses den binära bilden in från "php://input", varpå metoder finns
 * för att verifiera att datan utgör en giltig bild, skapa en GD-bild, skala om bilden enligt angivna maxdimensioner,
 * hämta bildens ursprungliga och aktuella dimensioner samt konvertera bilden till WebP-format. Resurser frigörs
 * korrekt via metoden destroyImage.
 *
 */
class GdImageProcessor implements ImageProcessorInterface
{
    /**
     * @var string Innehåller den binära bilddatan från "php://input".
     */
    private string $binaryData;

    /**
     * @var \GdImage Instans av GD-bilden.
     */
    private \GdImage $image;

    /**
     * @var int Maximalt tillåtet värde för bildens bredd.
     */
    private int $maxWidth = 1500;

    /**
     * @var int Maximalt tillåtet värde för bildens höjd.
     */
    private int $maxHeight = 1500;

    /**
     * @var int Ursprungsbildens bredd.
     */
    private int $originalWidth;

    /**
     * @var int Ursprungsbildens höjd.
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
     * Konstruktor som läser in den binära bilddatan från "php://input" vid instansiering av GdImageProcessor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->binaryData = file_get_contents("php://input");
    }

    /**
     * Kontrollerar om den inlästa binära datan utgör en giltig bild med hjälp av getimagesizefromstring.
     *
     * @return bool Returnerar true om datan är en giltig bild, annars false.
     */
    public function isImage(): bool
    {
        return getimagesizefromstring($this->binaryData) !== false;
    }

    /**
     * Skapar en GD-bild från den inlästa binära datan. Metoden:
     * - Skapar en bild med imagecreatefromstring och kastar ett undantag om datan är ogiltig.
     * - Hämtar den ursprungliga bildens bredd och höjd.
     * - Beräknar en skalningsfaktor baserat på maxvärden för bredd och höjd (standard 1500px).
     * - Om skalningsfaktorn är mindre än 1, skapas en ny bild med de skalade dimensionerna via imagecopyresampled.
     * - Uppdaterar aktuella bilddimensioner baserat på om skalning skedde.
     *
     * @return self Returnerar instansen för metodkedjning.
     * @throws \RuntimeException Om bildskapandet misslyckas.
     */
    public function createImageFromString(): self
    {
        $source = imagecreatefromstring($this->binaryData);
        if (!$source) {
            throw new \RuntimeException('Invalid image data.');
        }

        $this->originalWidth = imagesx($source);
        $this->originalHeight = imagesy($source);

        $scaleX = ($this->originalWidth > $this->maxWidth) ? $this->maxWidth / $this->originalWidth : 1;
        $scaleY = ($this->originalHeight > $this->maxHeight) ? $this->maxHeight / $this->originalHeight : 1;
        $scale = min($scaleX, $scaleY);

        $this->width = (int) round($this->originalWidth * $scale);
        $this->height = (int) round($this->originalHeight * $scale);

        if ($scale < 1) {
            $resized = imagecreatetruecolor($this->width, $this->height);
            imagecopyresampled($resized, $source, 0, 0, 0, 0, $this->width, $this->height, $this->originalWidth, $this->originalHeight);
            imagedestroy($source);
            $this->image = $resized;
        } else {
            $this->width = $this->originalWidth;
            $this->height = $this->originalHeight;
            $this->image = $source;
        }

        return $this;
    }

    /**
     * Sätter det maximala värdet för bildens bredd som används vid eventuellt skalningsläge.
     *
     * @param int $maxWidth Maximala bredden i pixlar.
     *
     * @return self Returnerar instansen för att stödja metodkedjning.
     */
    public function setMaxWidth(int $maxWidth): self
    {
        $this->maxWidth = $maxWidth;
        return $this;
    }

    /**
     * Sätter det maximala värdet för bildens höjd som används vid eventuellt skalningsläge.
     *
     * @param int $maxHeight Maximala höjden i pixlar.
     *
     * @return self Returnerar instansen för att stödja metodkedjning.
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
     * Hämtar den ursprungliga bredden på bilden innan eventuell skalning.
     *
     * @return int Den ursprungliga bildbredden i pixlar.
     */
    public function getOriginalWidth(): int
    {
        return $this->originalWidth;
    }

    /**
     * Hämtar den ursprungliga höjden på bilden innan eventuell skalning.
     *
     * @return int Den ursprungliga bildhöjden i pixlar.
     */
    public function getOriginalHeight(): int
    {
        return $this->originalHeight;
    }

    /**
     * Hämtar den aktuella bredden på den eventuellt skalade bilden.
     *
     * @return int Den aktuella bildbredden i pixlar.
     */
    public function getWidth(): int
    {
        return $this->width;
    }

    /**
     * Hämtar den aktuella höjden på den eventuellt skalade bilden.
     *
     * @return int Den aktuella bildhöjden i pixlar.
     */
    public function getHeight(): int
    {
        return $this->height;
    }

    /**
     * Kontrollerar om bilden är kvadratisk genom att jämföra bildens bredd och höjd.
     *
     * @return bool Returnerar true om bilden är kvadratisk, annars false.
     */
    public function isSquare(): bool
    {
        return $this->width === $this->height;
    }

    /**
     * Konverterar den aktuella bilden till WebP-format med en kvalitetsnivå på 80 och sparar resultatet i ett File-objekt.
     * Vid fel under konverteringen fångas undantaget och metoden returnerar false.
     *
     * @return File|false Returnerar ett File-objekt med den konverterade bilden, eller false om konverteringen misslyckas.
     */
    public function convertToWebP(): File|false
    {
        $imgFile = new File();
        try {
            // 0-100 kvalitet (0 = sämst, 100 = bäst)
            imagewebp($this->image, $imgFile->path(), 80);
        } catch (\Exception $e) {
            return false;
        }
        return $imgFile;
    }

    /**
     * Frigör resurser som används av GD-bilden genom att anropa imagedestroy, om en bild existerar.
     *
     * @return bool Returnerar true om bilden förstördes eller om ingen bild existerade.
     */
    public function destroyImage(): bool
    {
        return isset($this->image) ? imagedestroy($this->image) : true;
    }

    /**
     * Destruktorn anropar destroyImage för att säkerställa att alla bildresurser frigörs när objektet tas bort.
     *
     * @return void
     */
    public function __destruct()
    {
        $this->destroyImage();
    }
}
