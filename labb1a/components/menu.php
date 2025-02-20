<?php
if (!defined('PAGE')) {
    exit('Direct access is not allowed');
}
?>
<nav>
    <ul>
        <li><a href="sida1.php" class="<?= PAGE === 'sida1' ? "active" : ''; ?>">Sida 1</a></li>
        <li><a href="sida2.php" class="<?= PAGE === 'sida2' ? "active" : ''; ?>">Sida 2</a></li>
        <li><a href="sida3.php" class="<?= PAGE === 'sida3' ? "active" : ''; ?>">Sida 3</a></li>
        <li><a href="sida4.php" class="<?= PAGE === 'sida4' ? "active" : ''; ?>">Sida 4</a></li>
        <li><a href="sida5.php" class="<?= PAGE === 'sida5' ? "active" : ''; ?>">Sida 5</a></li>
        <li><a href="sida6.html" class="<?= PAGE === 'sida6' ? "active" : ''; ?>">Sida 6</a></li>
    </ul>
</nav>