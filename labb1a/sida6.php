<?php
// Används för att styra huvudmenyn.
const PAGE = 'sida6';

//Ta emot data från formuläret, antingen via GET eller POST. Är inget värde med, så används en tom sträng.
$tel = $_POST['phone'] ?? $_GET['phone'] ?? '';
$name = $_POST['name'] ?? $_GET['name'] ?? '';

//Sanera indata.
$tel = htmlspecialchars($tel);
$name = htmlspecialchars($name);
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
        <?php include 'components/menu.php'; ?>
    </header>
    <main>
        <div id='sida6'>
            <h1>Sida 6</h1>
            <p>Den här sidan tar emot ett namn och ett telefonnummer och skriver ut det</p>
            <?php
            //Skriv ut informationen om det finns någon.
            if (!empty($tel) && !empty($name)) {
                echo "<p>Hej $name! Ditt telefonnummer är $tel</p>";
            }
            ?>
        </div>
    </main>
    <?php include 'components/footer.php'; ?>
</body>

</html>