<?php
// Används för att styra huvudmenyn.
const PAGE = 'sida3';

//Ta emot post-data från formuläret, om det inte finns någon sätts det till en tom sträng.
$string = $_POST['string'] ?? "";
$search = $_POST['search'] ?? "";

//Ta bort onödiga mellanslag i meningen och trimma strängarna.
$string = preg_replace('/\s+/', ' ', $string);
$string = trim($string);
$search = trim($search);

//Dela upp strängen i en array.
$stringArray = explode(' ', $string);

//Sanitera input.
$search = htmlspecialchars($search);
$stringArray = array_map('htmlspecialchars', $stringArray);

if (empty($string) || empty($search)) {
    $output = false;
    $stringArray = [];
    $placeString = "";
    $search = "";
    $counter = 0;
} else {
    $output = true;
    $placeString = "";
    $counter = 0;
    foreach ($stringArray as $key => $word) {
        if ($word == $search) {
            $placeString .= ($key + 1) . " ";
            $counter++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sv">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Uppgift 1</title>
</head>

<body>
    <header>
        <h1>Uppgift 1</h1>
        <?php
        include 'components/menu.php';
        ?>
    </header>
    <main>
        <div id="sida3">
            <h1>Sida 3</h1>
            <p>I första formulärrutan skriver du in en mening, i rutan under skriver du in ett sökord. Programmet kommer
                sedan att söka efter ordet i meningen som du skrev</p>
            <form method="post">
                <label for="string">Mening:</label>
                <input type="text" id="string" name="string"><br>
                <label for="search">Sökord</label>
                <input type="text" id="search" name="search"><br>
                <input type="submit" value="Skicka">
            </form>
            <!-- Om det finns något att skriva ut, så skrivs det ut här nedanför -->
            <?php if ($output): ?>
                <p><?= print_r($stringArray, true); ?></p>
                <p>Order <?= $search; ?> finns på plats <?= $placeString; ?></p>
                <p>Ordet <?= $search; ?> hittades <?= $counter; ?> gånger</p>
            <?php endif ?>
        </div>
    </main>
    <?php
    include 'components/footer.php';
    ?>
</body>

</html>