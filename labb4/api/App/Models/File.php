<?php

namespace App\Models;
use resource;

/**
 * Class File
 *
 * Denna modell hanterar filoperationer, såsom skapande, läsning, flyttning och borttagning av filer.
 * Klassen erbjuder även metoder för att hämta metadata om filen (t.ex. storlek, MIME-typ,
 * skapande- och uppdateringstid) samt för att generera unika filnamn. Om ingen sökväg anges
 * vid konstruktionen skapas en temporär fil som är flyttbar.
 *
 */
class File
{
    /**
     * @var string Sökvägen till filen.
     */
    private string $filePath;

    /**
     * @var mixed Filhandteraren om filen är öppen. Typen är resource
     */
    private mixed $handler = null;

    /**
     * @var bool Indikerar om filen är flyttbar (t.ex. en temporär fil).
     */
    private bool $movable = false;

    /**
     * Konstruktor för File-klassen. Om en sökväg skickas med används den, annars skapas en temporär fil.
     * Vid temporär filsökväg sätts $movable till true, vilket möjliggör senare flyttning av filen.
     *
     * @param string|null $path Valfri sökväg till en befintlig fil. Om null skapas en temporär fil.
     *
     * @return void
     */
    public function __construct(
        ?string $path = null,
    ) {
        if ($path) {
            $this->filePath = $path;
        } else {
            $tmpfile = tmpfile();
            $metaData = stream_get_meta_data($tmpfile);
            $this->filePath = $metaData['uri'];
            $this->movable = true;
        }
    }

    /**
     * Destruktor som stänger filhanteraren om den existerar.
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->handler) {
            fclose($this->handler);
        }
    }

    /**
     * Flyttar filen till en ny plats med ett angivet filnamn. Flytten sker endast om filen är markerad som flyttbar.
     *
     * @param mixed $path     Den nya mappens sökväg där filen ska flyttas.
     * @param mixed $filename Det nya filnamnet.
     *
     * @return bool Returnerar true om flytten lyckades, annars false.
     */
    public function move($path, $filename): bool
    {
        if (!$this->movable) {
            return false;
        }

        if (!rename($this->filePath, $path . DIRECTORY_SEPARATOR . $filename)) {
            return false;
        }
        $this->filePath = $path;
        return true;
    }

    /**
     * Returnerar filens öppna hanterare (om det finns).
     *
     * @return mixed Filhanterare, vanligtvis en resurs, eller null om inget hanterare finns.
     */
    public function handler(): mixed
    {
        return $this->handler;
    }

    /**
     * Genererar ett unikt namn för filen baserat på ett MD5-hash av filens innehåll och ett unikt id.
     *
     * @return string Returnerar en unik filnamnsträng.
     */
    public function getUniqueName(): string
    {
        $hash = md5_file($this->filePath);
        return $hash . '_' . uniqid();
    }

    /**
     * Öppnar filen med angivet läge (t.ex. "rb", "wb") och sparar filhandtaget internt.
     *
     * @param string $mode Öppningsläge (t.ex. "rb" för läsning i binärt läge).
     *
     * @return self Returnerar instansen för metodkedjning.
     */
    public function open(string $mode): self
    {
        $this->handler = fopen($this->filePath, $mode);
        return $this;
    }

    /**
     * Hämtar den nuvarande sökvägen till filen.
     *
     * @return string Filens sökväg.
     */
    public function path(): string
    {
        return $this->filePath;
    }

    /**
     * Tar bort filen från filsystemet.
     *
     * @return bool Returnerar true om filen raderades framgångsrikt, annars false.
     */
    public function delete(): bool
    {
        return unlink($this->filePath);
    }

    /**
     * Returnerar filtypen för filen (t.ex. "file", "dir").
     *
     * @return string Filtyp.
     */
    public function type(): string
    {
        return filetype($this->filePath);
    }

    /**
     * Hämtar filens storlek i byte.
     *
     * @return string Filens storlek som en siffra i strängformat.
     */
    public function size(): string
    {
        return filesize($this->filePath);
    }

    /**
     * Returnerar filens filtillägg.
     *
     * @return string Filens filtillägg.
     */
    public function extension(): string
    {
        return pathinfo($this->filePath, PATHINFO_EXTENSION);
    }

    /**
     * Hämtar MIME-typen för filen.
     *
     * @return string MIME-typen, t.ex. "image/jpeg" eller "application/pdf".
     */
    public function mimeType(): string
    {
        return mime_content_type($this->filePath);
    }

    /**
     * Returnerar filens namn (basnamn) utan sökväg.
     *
     * @return string Filens namn.
     */
    public function name(): string
    {
        return basename($this->filePath);
    }

    /**
     * Hämtar datum och tid då filen senast ändrades, formaterat som "Y-m-d H:i:s".
     *
     * @return string Datum och tid för senaste ändring.
     */
    public function updatedAt(): string
    {
        return date('Y-m-d H:i:s', filemtime($this->filePath));
    }

    /**
     * Hämtar datum och tid då filen skapades, formaterat som "Y-m-d H:i:s".
     *
     * @return string Datum och tid för skapande.
     */
    public function createdAt(): string
    {
        return date('Y-m-d H:i:s', filectime($this->filePath));
    }
}