<?php
class Notame
{
    protected $_user;
    protected $_userid;
    protected $_basekey;
    protected $_lastNote = 0;
    protected $_ch;
    protected $_loginUrl = 'https://www.meneame.net/login.php';
    protected $_logoutUrl = 'https://www.meneame.net/login.php?op=logout';
    protected $_notameUrl = 'https://www.meneame.net/notame/';
    protected $_postUrl = 'https://www.meneame.net/backend/post_edit.php';
    protected $_noteVote = 'https://www.meneame.net/backend/menealo_post';

    /**
     * Configuraciones iniciales de curl
     */
    public function __construct()
    {
        date_default_timezone_set("Europe/Madrid");
        $this->_ch = curl_init();
        curl_setopt($this->_ch, CURLOPT_USERAGENT, 'Gandalf el Blanco php script');
        curl_setopt($this->_ch, CURLOPT_COOKIEJAR, 'cookie-notame.txt');
        curl_setopt($this->_ch, CURLOPT_COOKIEFILE, 'cookie-notame.txt');
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->_ch, CURLOPT_FOLLOWLOCATION, true);

        curl_setopt($this->_ch, CURLOPT_URL, $this->_loginUrl);
        $result = $this->_exec();

        if (preg_match('/<a href=\"\/user\/(.*)\" class=\"tooltip u:(.*)\">/iU', $result, $matchLogin) === 0) {
            return "El usuario o password no son correctos";
        }

        $this->_user = $matchLogin[1];
        $this->_userid = $matchLogin[2];
    }

    public function getUser()
    {
        return $this->_user;
    }

    /**
     * Hace una petición curl y devuelve el resultado quitando saltos de línea
     */
    protected function _exec($basekey = true)
    {
        $result = curl_exec($this->_ch);
        $result = trim(str_replace(array("\n", "\r"), "", $result));

        if ($basekey === true && preg_match('/base_key=\"(.*)\"/iU', $result, $matchKey)) {
            $this->_basekey = $matchKey[1];
        }

        return $result;
    }

    public function prepareLogin()
    {
        curl_setopt($this->_ch, CURLOPT_URL, $this->_loginUrl);
        $result = $this->_exec();

        if (preg_match('/name=\"recaptcha_challenge_field\"/', $result, $captcha)) {
            return "Necesario captcha para loguearse";
        }

        return true;
    }

    /**
     * Hace el login en menéame para permitir el envío y votación de notas
     * @param string $user
     * @param string $password
     */
    public function login($user, $password)
    {
        $postData = 'processlogin=1&username=' . $user . '&password=' . $password;
        curl_setopt($this->_ch, CURLOPT_URL, $this->_loginUrl);
        curl_setopt($this->_ch, CURLOPT_POST, true);
        curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $postData);
        $result = $this->_exec();

        if (preg_match('/<a href=\"\/user\/(.*)\" class=\"tooltip u:(.*)\">/iU', $result, $matchLogin) === 0) {
            return "El usuario o password no son correctos";
        }

        $this->_user = $matchLogin[1];
        $this->_userid = $matchLogin[2];

        return "Logueado correctamente como '" . $this->_user . "'";
    }

    public function logout()
    {
        curl_setopt($this->_ch, CURLOPT_POST, false);
        curl_setopt($this->_ch, CURLOPT_URL, $this->_logoutUrl);
        $result = $this->_exec();

        if (preg_match('/<a href=\"\/user\/(.*)\" class=\"tooltip u:(.*)\">/iU', $result, $matchLogin) === 0) {
            $this->_user = null;
            $this->_userid = null;
            return "Deslogueado correctamente";
        }

        return "Error al desloguearse";
    }

    /**
     * Carga una página del nótame y muestra sus notas
     * @param int $page
     */
    public function loadNotame($page = 1)
    {
        if (intval($page) < 1) {
            $page = 1;
        }

        curl_setopt($this->_ch, CURLOPT_POST, false);
        curl_setopt($this->_ch, CURLOPT_URL, $this->_notameUrl . '?page=' . $page);
        $result = $this->_exec();

        preg_match_all('/<div id=\"pcontainer-(.*)\">(.*)<\/div> <\/li>/iU', $result, $matchNotes);
        $matchNotes[1] = array_reverse($matchNotes[1], true);
        $matchNotes[2] = array_reverse($matchNotes[2], true);

        foreach ($matchNotes[2] as $key => $note) {
            $info = $this->_getNoteInfo($note, $matchNotes[1][$key]);
            $this->showNote($info);
        }

        return true;
    }

    /**
     * Vota una nota positivo o negativo
     * @param string $text
     * @param int $id
     */
    public function voteNote($option, $id)
    {
        $options = array('positive' => '1', 'negative' => '-1');
        $params = "id=" . intval($id) . "&user=" . $this->_userid . "&value=" . $options[$option] . "&key=" . $this->_basekey . "&l=0";
        curl_setopt($this->_ch, CURLOPT_POST, false);
        curl_setopt($this->_ch, CURLOPT_URL, $this->_noteVote . '?' . $params);
        $result = json_decode($this->_exec());

        if (isset($result->error)) {
            echo "Error al votar: " . $result->error . "\n";
        } else {
            echo "Nuevo karma de la nota: " . $result->karma . "\n";
        }
    }

    /**
     * Envía una nueva nota a publicar
     * @param string $text
     */
    public function newNote($text)
    {
        curl_setopt($this->_ch, CURLOPT_POST, false);
        curl_setopt($this->_ch, CURLOPT_URL, $this->_postUrl . '?id=0&key=' . $this->_basekey);
        $result = $this->_exec();

        if (!preg_match('/name=\"key\" value=\"(.*)\"/iU', $result, $matchKey)) {
            return false;
        }

        $postData = 'post_id=0&key=' . $matchKey[1] . '&user_id=' . $this->_userid . '&post=' . $text;
        curl_setopt($this->_ch, CURLOPT_POST, true);
        curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($this->_ch, CURLOPT_URL, $this->_postUrl . '?user=' . $this->_userid);
        $result = $this->_exec();
    }

    /**
     * Actualiza el nótame con las siguientes notas desde la última nota mostrada
     */
    public function reloadNotame()
    {
        if ($this->_lastNote == 0) {
            return false;
        }

        $reload = false;

        do {
            if ($this->loadNote($this->_lastNote + 1) === false) {
                break;
            }
            $reload = true;
        } while (true);

        return $reload;
    }

    /**
     * Carga la nota con el id especificado
     * @param int $id
     */
    public function loadNote($id)
    {
        curl_setopt($this->_ch, CURLOPT_POST, false);
        curl_setopt($this->_ch, CURLOPT_URL, $this->_notameUrl . intval($id));
        $result = $this->_exec();

        preg_match('/<div id=\"pcontainer-' . intval($id) . '\">(.*)<\/div> <\/li>/iU', $result, $matchNote);

        if (!isset($matchNote[1])) {
            return false;
        }

        $note = $this->_getNoteInfo($matchNote[1], $id);
        $this->showNote($note);

        return true;
    }

    /**
     * Pasado el html e id de una nota, limpia el html para mostrarlo
     * y se añade la nota al array de notas
     * @param string $html
     * @param int $id
     * @return array
     */
    protected function _getNoteInfo($html, $id)
    {
        $note = array(
            'id' => $id,
            'user' => null,
        	'userId' => null,
            'text' => null,
            'date' => null,
            'karma' => 0,
            'votes' => 0
        );

        $html = trim($html);

        preg_match('/<span class=\"votes-counter\" id=\"vk-' . $id . '\" title=\"karma\">(.*)<\/span>/U', $html, $noteKarma);
        preg_match('/<span id=\"vc-' . $id . '\" class=\"votes-counter\">(.*)<\/span>/U', $html, $noteVotes);
        preg_match('/data-ts=\"(.*)\"/U', $html, $noteDate);
        $note['karma'] = intval($noteKarma[1]);
        $note['votes'] = intval($noteVotes[1]);
        $note['date'] = date('d-m-Y H:i:s', $noteDate[1]);

        preg_match('/<div class=\"comment-body(.*)\" id=\"pid-' . $id . '\">(.*)<\/div>/iU', $html, $noteInfo);
        $html = $noteInfo[2];

        preg_match('/<a href=\"\/user\/(.*)\" class=\"tooltip u:(.*)\">/iU', $html, $noteUser);
        $note['user'] = $noteUser[1];
        $note['userId'] = $noteUser[2];

        $html = preg_replace('/<a href=\"javascript:post_edit(.*)\" title=\"editar\"><img class=\"mini-icon-text\" src=\"(.*)\" alt=\"edit\" width=\"18\" height=\"12\"\/><\/a>/', '', $html);
        $html = preg_replace('/<a href=\"\/user\/(.*)" class=\"tooltip u:(.*)\">(.*)<\/a>/U', '', $html);
        $html = str_replace(array('<sub>', '</sub>', '<sup>', '</sup>'), '', $html);
        $html = str_replace(array('<em>', '</em>', '<b>', '</b>', '<strong>', '</strong>', '<del>', '</del>'), array("\033[0;35m", "\033[0m", "\033[1;30m", "\033[0m", "\033[1;30m", "\033[0m", "\033[40m", "\033[0m"), $html);
        $html = str_replace('&nbsp;', ' ', $html);
        $html = str_replace('<br />', "\n", $html);
        $html = preg_replace('/ <img src=\"https:\/\/mnmstatic.net\/v_(.*[0-9])\/img\/smileys\/(.*)\" alt=\"(.*)\"(.*)>/U', "\033[1;33m$3\033[0m", $html);
        $html = preg_replace('/<a class=\'tooltip p:(.*),(.*[0-9])-(.*[0-9])\' href=\'\/backend\/get_post_url\?id=(.*),(.*[0-9]);(.*[0-9])\'>(.*)<\/a>/U', "\033[0;33m$7:$5\033[0m", $html);
        $html = preg_replace('/<a class=\'tooltip p:(.*)-(.*[0-9])\' href=\'\/backend\/get_post_url\?id=(.*);(.*[0-9])\'>(.*)<\/a>/U', "\033[0;33m$5\033[0m", $html);

        $html = preg_replace('/<a class=\"fancybox\" title=\"subida por (.*)\" href=\"(.*)\">(.*)<\/a>/U', "\033[0;32mhttp://www.meneame.net$2\033[0m", $html);
        $html = preg_replace('/<a href=\"\/search.php(.*)>(.*)<\/a>/U', "\033[1;36m$2\033[0m", $html);
        $html = preg_replace('/<a(.*)href=\"(.*)\"(.*)>(.*)<\/a>/U', "\033[0;32m$2\033[0m", $html);
        //$html = htmlspecialchars_decode($html); Comentado no recuerdo por qué
        $html = html_entity_decode($html);

        $note['text'] = trim($html);

        if ($id > $this->_lastNote) {
            $this->_lastNote = $id;
        }

        return $note;
    }

    /**
     * Muestra en pantalla la nota
     * @param array $note
     */
    public function showNote($note)
    {
        $title = "\033[0;36m" . $note['id'] . " [" .$note['date'] . "]\033[0m \033[0;34m" . $note['user'] . "\033[0m";
        echo "\n\n" . $title . " ";
        echo "\033[0;32m⇑\033[0m  \033[1;30mkarma:\033[0m " . $note['karma'] . " \033[0;31m⇓\033[0m  \033[1;30mvotos:\033[0m " . $note['votes'];
        echo "\n" . $note['text'];

        return true;
    }
}
