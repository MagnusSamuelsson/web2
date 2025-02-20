<?php
/**
 * Klass för att generera en HTML-sida dynamiskt med DOM.
 */
class HTMLPage
{
    private Dom\HTMLDocument $doc;
    private Dom\HTMLElement $html;
    private Dom\HTMLElement $head;
    private Dom\HTMLElement $body;
    private Dom\HTMLElement $header;
    private Dom\HTMLElement $main;
    private Dom\HTMLElement $footer;
    /**
     * Skapar en ny HTML-sida med grundläggande struktur.
     *
     * @param string $title Sidans titel
     */
    public function __construct(string $title)
    {
        $this->doc = Dom\HTMLDocument::createEmpty();

        $meta1 = $this->createElement(
            tag: 'meta',
            attributes: [
                'charset' => 'UTF-8'
            ]
        );
        $meta2 = $this->createElement(
            tag: 'meta',
            attributes: [
                'name' => 'viewport',
                'content' => 'width=device-width, initial-scale=1.0'
            ]
        );
        $titleElement = $this->createElement(
            tag: 'title',
            text: $title
        );

        $this->head = $this->createElement(
            tag: 'head',
            children: [
                $meta1,
                $meta2,
                $titleElement
            ]
        );

        $this->header = $this->createElement('header');
        $this->main = $this->createElement('main');
        $this->footer = $this->createElement('footer');
        $this->body = $this->createElement(
            tag: 'body',
            children: [
                $this->header,
                $this->main,
                $this->footer
            ]
        );

        $this->html = $this->createElement(
            tag: 'html',
            attributes: [
                'lang' => 'sv'
            ],
            children: [
                $this->head,
                $this->body
            ]
        );

        $this->doc->appendChild($this->html);

    }
    /**
     * Lägger till en CSS-fil till dokumentets head tagg.
     *
     * @param string $href Länken till CSS-filen
     */
    public function addCss(string $href): void
    {
        $link = $this->createElement(
            tag: 'link',
            attributes: [
                'rel' => 'stylesheet',
                'href' => $href
            ]
        );
        $this->head->appendChild($link);
    }
    /**
     * Lägger till en JavaScript-fil till dokumentets body tagg.
     *
     * @param string $src   Länken till JavaScript-filen.
     * @param bool   $defer Om `true`, läggs attributet `defer` till för att fördröja skriptexekveringen.
     */
    public function addScript(string $src, $defer = true): void
    {
        $script = $this->createElement(
            tag: 'script',
        );
        if ($defer) {
            $script->setAttribute('defer', 'defer');
        }
        $this->head->appendChild($script);
    }
    /**
     * Skapar ett nytt HTML-element.
     *
     * @param string $tag Elementets tagg
     * @param string|null $text Textinnehåll (valfritt)
     * @param string|null $id Elementets id-attribut (valfritt)
     * @param string|null $class Elementets klass-attribut (valfritt)
     * @param array $attributes Övriga attribut som nyckel/värde-par (valfritt)
     * @param array $children Barnnoder som ska läggas till i elementet (valfritt)
     * @return Dom\HTMLElement Det skapade elementet
     */
    public function createElement(
        string $tag,
        ?string $text = null,
        ?string $id = null,
        ?string $class = null,
        array $attributes = [],
        array $children = []
    ): Dom\HTMLElement {
        $element = $this->doc->createElement($tag);
        if ($id) {
            $element->setAttribute('id', $id);
        }
        if ($text) {
            $element->appendChild($this->doc->createTextNode($text));
        }
        if ($class) {
            $element->setAttribute('class', $class);
        }
        foreach ($attributes as $name => $value) {
            $element->setAttribute($name, $value);
        }
        foreach ($children as $child) {
            $element->appendChild($child);
        }
        return $element;
    }

    /**
     * Skapar en textnod.
     *
     * @param string $text Textinnehållet
     * @return Dom\Text Skapad textnod
     */

    public function text(string $text): Dom\Text
    {
        return $this->doc->createTextNode($text);
    }
    /**
     * Lägger till ett element i header taggen.
     *
     * @param Dom\HTMLElement $element Elementet att lägga till
     */
    public function addToHeader(Dom\HTMLElement $element): void
    {
        $this->header->appendChild($element);
    }

    /**
     * Lägger till ett element i main taggen.
     *
     * @param Dom\HTMLElement $element Elementet att lägga till
     */
    public function addToMain(Dom\HTMLElement $element): void
    {
        $this->main->appendChild($element);
    }

    /**
     * Lägger till ett element i footer taggen.
     *
     * @param Dom\HTMLElement $element Elementet att lägga till
     */
    public function addToFooter(Dom\HTMLElement $element): void
    {
        $this->footer->appendChild($element);
    }

    /**
     * Returnerar det underliggande dokumentet.
     *
     * @return Dom\HTMLDocument Dokumentobjektet
     */
    public function getDoc(): Dom\HTMLDocument
    {
        return $this->doc;
    }

    /**
     * Returnerar den genererade HTML-koden som en sträng.
     *
     * @return string Renderad HTML-sträng
     */
    public function render(): string
    {
        return "<!DOCTYPE html>" . $this->doc->saveHTML();
    }
}
