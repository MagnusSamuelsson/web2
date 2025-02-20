<?php
/**
 * En klass för att bygga HTML-formulär dynamiskt.
 *
 * FormBuilder möjliggör skapande av HTML-formulär genom att lägga till
 * input-fält, etiketter och specificera formulärattribut på ett strukturerat sätt.
 * Klassen använder DOM för att hantera HTML-element.
 *
 * Exempel på användning:
 * ```php
 * $doc = new \Dom\HTMLDocument();
 * $formBuilder = new FormBuilder($doc, 'post', '/submit.php');
 * $formBuilder->addInput('text', 'username', true, 'Användarnamn')
 *             ->addInput('password', 'password', true, 'Lösenord');
 * $formElement = $formBuilder->getForm();
 * ```
 */
class FormBuilder
{
    private \Dom\HTMLElement $form;
    private \Dom\HTMLDocument $doc;

    /**
     * Skapar en ny instans av FormBuilder.
     *
     * @param \Dom\HTMLDocument $doc    HTML-dokumentet där formuläret ska skapas.
     * @param string            $method HTTP-metoden för formuläret (t.ex. "post" eller "get").
     * @param string|null       $action URL dit formuläret skickas vid inskickning (valfritt).
     */
    public function __construct(
        \Dom\HTMLDocument $doc,
        string $method = 'post',
        ?string $action = null
    ) {
        $this->doc = $doc;
        $this->form = $doc->createElement('form');
        $this->form->setAttribute('method', $method);
        if ($action) {
            $this->form->setAttribute('action', $action);
        }
    }

    /**
     * Lägger till ett input-fält i formuläret.
     *
     * @param string      $type     Typ av input-fält (t.ex. "text", "password", "email").
     * @param string      $name     Namnet på input-fältet.
     * @param bool        $required Om fältet är obligatoriskt.
     * @param string|null $label    Tillhörande etikett för fältet (valfritt).
     * @param string|null $value    Förifyllt värde i fältet (valfritt).
     * @param string|null $id       ID-attribut för fältet (valfritt).
     * @param string|null $class    CSS-klass för fältet (valfritt).
     *
     * @return self Returnerar sig själv för method chaining.
     */
    public function addInput(
        string $type,
        string $name,
        bool $required = false,
        ?string $label = null,
        ?string $value = null,
        ?string $id = null,
        ?string $class = null,
    ): self {
        $input = $this->doc->createElement('input');
        $input->setAttribute('type', $type);
        $input->setAttribute('name', $name);
        if ($value) {
            $input->setAttribute('value', $value);
        }
        if ($id) {
            $input->setAttribute('id', $id);
        }
        if ($class) {
            $input->setAttribute('class', $class);
        }
        if ($required) {
            $input->setAttribute('required', 'required');
        }
        if ($label) {
            $this->addLabel($id, $label);
        }
        $this->form->appendChild($input);
        return $this;
    }

    /**
     * Lägger till en etikett (label) för ett formulärfält.
     *
     * @param string $for  ID på fältet som etiketten ska kopplas till.
     * @param string $text Texten som etiketten ska visa.
     *
     * @return self Returnerar sig själv för method chaining.
     */
    public function addLabel(string $for, string $text): self
    {
        $label = $this->doc->createElement('label');
        $label->appendChild($this->doc->createTextNode($text));
        $label->setAttribute('for', $for);
        $this->form->appendChild($label);
        return $this;
    }

    /**
     * Hämtar det skapade formuläret som en DOM-element.
     *
     * @return \Dom\HTMLElement Formuläret som ett DOM-element.
     */
    public function getForm(): \Dom\HTMLElement
    {
        return $this->form;
    }
}