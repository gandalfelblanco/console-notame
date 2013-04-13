#!/usr/bin/php
<?php
require_once('notame.php');
chdir(dirname(__FILE__));
$stdin = fopen("php://stdin", "r");

echo "Bienvenido al nótame\n";
$notame = new Notame();
$line = 'load';

do {

    $line = str_replace(array("\n", "\r"), "", $line);

    if ($line == '') {

        if ($notame->reloadNotame() === true) {

            echo "\n\n";
        }

    } elseif ($line == 'login') {

        $prepare = $notame->prepareLogin();

        if ($prepare !== true) {

            echo $prepare . "\n";

        } else {

            echo "User: ";
            $user = fgets($stdin);
            echo "Password: ";
            $pass = fgets($stdin);
            echo $notame->login($user, $pass) . "\n";
        }

    } elseif ($line == 'logout') {

        echo $notame->logout() . "\n";

    } elseif (preg_match('/new note (.*)/i', $line, $new)) {

        $notame->newNote($new[1]);

    } elseif ($line == 'load' || preg_match('/load ([0-9]+)?$/i', $line, $page)) {

        $notame->loadNotame(($line == 'load')? 1 : $page[1]);
        echo "\n\n";

    } elseif (preg_match('/vote (positive|negative) ([0-9]+)$/i', $line, $vote)) {

        $notame->voteNote($vote[1], $vote[2]);

    } elseif (preg_match('/show note ([0-9]+)$/i', $line, $show)) {

        if ($notame->loadNote($show[1])) {

            echo "\n\n";
        }

    } elseif ($line == 'help') {

        echo "\n\033[0;32mlogin\033[0m iniciar sesión en nótame.";
        echo "\n\033[0;32mlogout\033[0m cierra sesión en nótame.";
        echo "\n\033[0;32mload [page]\033[0m carga la página del nótame indicada en [page]. Si no se indica [page], se carga la primera página.";
        echo "\n\033[0;32mshow note [id]\033[0m muestra la nota con el id indicado en [id].";
        echo "\n\033[0;32mvote [positive|negative] [id]\033[0m vota positivo o negativo la nota con el id indicado en [id].";
        echo "\n\033[0;32mnew note [text]\033[0m envía una nota con el texto indicado en [text].";
        echo "\n\033[0;32m(empty)\033[0m si pulsamos Enter con la línea vacía, carga las siguientes notas desde la última nota con el id más alto mostrada.";
        echo "\n\033[0;32mbye|quit|exit\033[0m salimos del nótame.";
        echo "\n\n\033[1;31mNota\033[0m los parámetros entre [] van sin []\n\n";

    } elseif (in_array($line, array('bye', 'quit', 'exit'))) {

        break;
    }

    echo "\033[0;34m#notame" . (($notame->getUser() !== null)? " (" . $notame->getUser() . ")" : "") . "#>\033[0m ";

} while ($line = fgets($stdin));

echo "\n\nFinalizado nótame\n\n";

fclose($stdin);
