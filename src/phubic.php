<?php
/*
     Copyright 2013 Stéphane Depierrepont (aka Toorop) toorop@toorop.fr

    Licensed under the Apache License, Version 2.0 (the "License"); you may not
    use this file except in compliance with the License. You may obtain a copy of
    the License at

    http://www.apache.org/licenses/LICENSE-2.0

    Unless required by applicable law or agreed to in writing, software
    distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
    WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
    License for the specific language governing permissions and limitations under
    the License.

 */


/*
 * Todo :
 *  method curlPost
 *
 *
 *
 */

class Phubic
{

    private $hubicLogin;
    private $hubicPasswd;
    private $tempDir; // dir with r&w access

    private $userAgent;

    private $hubicSettings = false;

    public function __construct($config = array())
    {
        if (!is_array($config)) throw new \Exception('Contructor parameter $config must be a array. ' . gettype($config) . ' given');
        if (!array_key_exists('login', $config) || !array_key_exists('login', $config) || !array_key_exists('tempDir', $config)) throw new \Exception('Parameter $config must have at least "login", "passwd" and "tempDir" keys set');

        // Hubic login
        $this->hubicLogin = trim($config['login']); // Don't try to know why i "trim", you loose your time.
        // Hubic passwd
        $this->hubicPasswd = trim($config['passwd']);
        // Temp dir with rw access (cookies)
        $this->tempDir = trim($config['tempDir']);
        if (!file_exists($this->tempDir))
            throw new \Exception('tempDir parameter ' . $this->tempDir . ' is not an existing directory');
        if (!touch($this->tempDir . $this->getPathSeparator() . 'test'))
            throw new \Exception('tempDir ' . $this->tempDir . ' is not writable');
        unlink($this->tempDir . $this->getPathSeparator() . 'test');

        // user agent
        # $this->userAgent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_2) AppleWebKit/537.11 (KHTML, like Gecko) Chrome/23.0.1271.101 Safari/537.11';
        $this->userAgent = 'Phubic (dev version)  more info : https://github.com/Toorop/Phubic';


        unset($config);

        /* Login */
        $this->login();

    }

    /**
     * Login
     *
     * @return bool
     * @throws Exception
     */
    private function login()
    {

        $this->logout();

        /* Init Curl */
        $ch = curl_init("https://app.hubic.me/v2/actions/nasLogin.php");

        /* Cookies */
        $cookiesFile = $this->getCookiesPathFile();
        // file exist ?
        if (!file_exists($cookiesFile)) {
            touch($cookiesFile);
        }
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiesFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiesFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /* Post data */
        $post = array('sign-in-email' => $this->hubicLogin, 'sign-in-password' => $this->hubicPasswd, 'sign-in-action' => 'true');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        /* Header */
        $headers = array('User-Agent: ' . $this->userAgent, 'Origin: https://app.hubic.me');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        /* Verbosity */
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        /* Go go go !!! */
        $resp = curl_exec($ch);

        $error = curl_error($ch);
        if ($error)
            throw new \Exception($error);

        // HTTP_CODE must be 302 (redirect to location: /v2/)
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 302)
            throw new \Exception('Bad HTTP code returned by Hubic server on login. Returned : ' . $httpCode . ' Expected : 302');
        curl_close($ch);

        /* Cookie HUBIC_ACTION_RETURN ? */
        $cookies = $this->getCookies();
        if (array_key_exists('HUBIC_ACTION_RETURN', $cookies)) {
            $r = json_decode($cookies['HUBIC_ACTION_RETURN']);
            if (isset($r->answer->login->message))
                throw new \Exception($r->answer->login->message);
            throw new \Exception('Login failed');
        }
    }

    /**
     * Logout
     */
    public function logout()
    {
        /* Init Curl */
        $ch = curl_init("https://app.hubic.me/v2/actions/ajax/logoff.php");
        /* Cookies */
        $cookiesFile = $this->getCookiesPathFile();
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiesFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiesFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /* Post data */
        $post = array('action' => 'unload');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        /* Header */
        $headers = array('User-Agent: ' . $this->userAgent, 'Origin: https://app.hubic.me');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        /* Verbosity (debug) */
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        /* Go go go !!! */
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) throw new \Exception('Logout Fail');
        @unlink($cookiesFile);
    }

    /**
     * Get Settings
     *
     * @return object settings
     * @throws Exception
     */
    public function getSettings()
    {

        if ($this->hubicSettings)
            return $this->hubicSettings;

        /* Init Curl */
        $ch = curl_init("https://app.hubic.me/v2/actions/ajax/getSettings.php");
        /* Cookies */
        $cookiesFile = $this->getCookiesPathFile();
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiesFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiesFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /* Header */
        $headers = array('User-Agent: ' . $this->userAgent, 'Origin: https://app.hubic.me');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        /* Verbosity (debug) */
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        /* Go go go !!! */
        $r = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200) {
            throw new \Exception('Bad HTTP code returned by Hubic server on getSetting. Returned : ' . $httpCode . ' Expected : 200');
        }
        curl_close($ch);
        $t = json_decode($r);
        if ($t === false)
            throw new \Exception('Unexpected response returned by Hubic server on getSetting. Returned : ' . (string)$r);
        if (!isset($t->answer) || !isset($t->answer->status))
            throw new \Exception('Unexpected response returned by Hubic server on getSetting. Returned : ' . (string)$r);
        if ($t->answer->status !== 200)
            throw new \Exception('Bad response code returned by Hubic server on getSetting. Returned : ' . $t->answer->status . ' Expected : 200');
        if (isset($t->answer->settings->hubic)) {
            $this->hubicSettings = $t->answer->settings->hubic;
            return $this->hubicSettings;
        }
        throw new \Exception('No settings returned by Hubic server. Response(JSON) : ' . (string)$r);
    }

    /**
     * List folder
     *
     * @param string $folder
     * @param string $container
     * @return mixed
     * @throws Exception
     */
    public function listFolder($folder = '/', $container = 'default')
    {
        /* Curl Init */
        $ch = curl_init("https://app.hubic.me/v2/actions/ajax/hubic-browser.php");
        /* Cookies */
        $cookiesFile = $this->getCookiesPathFile();
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiesFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiesFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /* Post data */
        $post = array('action' => 'get', 'folder' => $folder, 'container' => urlencode($container), 'init' => 'true');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        /* Header */
        $headers = array('User-Agent: ' . $this->userAgent, 'Origin: https://app.hubic.me');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        /* Verbosity */
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        /* Go go go !!! */
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200) {
            throw new \Exception('Bad HTTP code recieved : ' . $httpCode);
        }
        $r = json_decode($resp, true, 512);
        // get fullCacheMode. If not fullCacheMode we are at root
        $fullCacheMode = $r['answer']['hubic']['fullCacheMode'];

        $root = $r['answer']['hubic']['list'][$container]['items'];
        if ($fullCacheMode) {
            if ($folder !== '/') {
                $parts = explode('/', $folder);
                foreach ($parts as $p) {
                    if ($p and strlen($p) > 0) {
                        if (isset($root[$p]['items'])) {
                            $root = $root[$p]['items'];
                        } else {
                            throw new \Exception('No such folder ' . $folder, 404);
                        }
                    }
                }
            }
        }
        foreach (array_keys($root) as $k) {
            if (isset($root[$k]['items'])) unset($root[$k]['items']);
            //clean prop
            $root[$k]['creation'] = $root[$k]['props']['creation'];
            $root[$k]['modified'] = $root[$k]['props']['modified'];
            $root[$k]['type'] = $root[$k]['props']['type'];
            $root[$k]['isFile'] = $root[$k]['props']['isFile'];
            $root[$k]['container'] = $root[$k]['props']['container'];
            $root[$k]['size'] = $root[$k]['props']['size'];
            $root[$k]['publication'] = $root[$k]['props']['publication'];
            unset($root[$k]['props']);
        }
        return $root;
    }

    /**
     * Upload
     *
     * @param string $src
     * @param string $dest
     * @param string $container
     * @return bool
     * @throws Exception
     *
     * @todo : create folder(s) if they doesn't exist
     *
     *
     */
    public function upload($src = '', $dest = '', $container = 'default')
    {

        if ($src === '' || $dest === '') throw new \Exception("Upload need src and dest parameters");
        $src = trim($src); // clean & cast
        $dest = trim($dest); // itoo
        if (!file_exists($src)) throw new \Exception('File ' . $src . ' not found');

        /* Info on file to upload */
        // mimetype
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $src);
        finfo_close($finfo);
        // size
        $size = filesize($src);

        /* Transfert */
        $url = "http://app.hubic.me/v2/actions/ajax/hubic-browser.php";
        $fp = fopen($src, "r");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        // Cookies
        $cookiesFile = $this->getCookiesPathFile();
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiesFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiesFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        // Headers
        $headers = array(
            'User-Agent: ' . urlencode($this->userAgent),
            'Origin: https://app.hubic.me',
            'X-File-Size :' . $size,
            'X-File-Dest :' . urlencode($dest),
            'X-Action: upload',
            'X-File-Container: ' . urlencode($container),
            'X-File-Name: ' . urlencode(basename($src)),
            'X-File-Type: ' . $mimeType,
            'Content-Type: ' . $mimeType
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        // PUT & File
        curl_setopt($ch, CURLOPT_PUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_INFILE, $fp);
        curl_setopt($ch, CURLOPT_INFILESIZE, $size);
        // Go
        $resp = curl_exec($ch);
        #$error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($httpCode !== 200)
            throw new \Exception('Error uploading ' . $src . ' - Hubic HTTP response code : ' . $httpCode, ' - Json response : ' . $resp);
            throw new \Exception('Error uploading ' . $src . ' - Hubic HTTP response code : ' . $httpCode. ' - Json response : ' . $resp);

        $r = json_decode($resp);
        #unset($resp);

        if (is_null($r->answer))
            throw new \Exception('Error uploading ' . $src . ' - ' . $r->error->message);


        if (isset($r->answer->error) && $r->answer->error !== null) {
            throw new \Exception('Error uploading ' . $src . ' - Hubic HTTP response code : ' . $httpCode, ' - Json response : ' . $resp);
        }

        // is filesize OK ? (=> simili integrity check)
        if ($r->answer->upload->size !== $size)
            throw new \Exception('Integrity check failed, uploaded filesize (' . $r->answer->upload->size . ') does not match with original size (' . $size . ')');

        return true;
    }

    /**
     * Create folder recursively
     *
     * @param string $folder
     * @param string $container
     * @return bool
     * @throws Exception
     */
    public function createFolder($folder = '', $container = 'default')
    {
        if ($folder == '')
            throw new \Exception('Method createFolder needs parameter $folder');
        $folder = trim($folder);

        // clean ending / if present
        if (substr($folder, strlen($folder) - 1, 1) == '/')
            $folder = substr($folder, 0, strlen($folder) - 1);
        ;
        if ($folder === '') return true;
        // exists ?
        if ($this->hubicFolderExists($folder)) return true;

        $p = explode("/", $folder);
        // @todo : limit sub folder to avoid long exec
        // if parent folder doesn't exists create it (recursively)
        $parent = implode("/", array_slice($p, 0, count($p) - 1));
        if ($this->hubicFolderExists($parent) === false) {
            $this->createFolder($parent, $container);
        }
        // name
        $postName = $p[count($p) - 1];
        // folder
        $postFolder = substr($folder, 0, strlen($folder) - strlen($postName) - 1);

        /* Init Curl */
        $ch = curl_init("https://app.hubic.me/v2/actions/ajax/hubic-browser.php");
        /* Cookies */
        $cookiesFile = $this->getCookiesPathFile();
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiesFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiesFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /* Header */
        $headers = array('User-Agent: ' . $this->userAgent, 'Origin: https://app.hubic.me');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        /* Post data */
        $post = array('action' => 'create', 'folder' => $postFolder, 'container' => urlencode($container), 'name' => urlencode($postName));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

        /* Verbosity (debug) */
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        /* Go go go !!! */
        $r = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            throw new \Exception('Bad HTTP code returned by Hubic server on createFolder. Returned : ' . $httpCode . ' Expected : 200');
        }

        $t = json_decode($r);
        if ($t === false) // not a json response as expected
            throw new \Exception('Unexpected response returned by Hubic server on createFolder. Returned : ' . (string)$r);
        if (!isset($t->answer) || !isset($t->answer->status))
            throw new \Exception('Unexpected response returned by Hubic server on createFolder. Returned : ' . (string)$r);
        if ($t->answer->status !== 201)
            throw new \Exception('Bad response code returned by Hubic server on createFolder. Returned : ' . $t->answer->status . ' Expected : 201');

        return true;
    }


    /**
     * Check if a given folder exists
     *
     * @param string $folder
     * @return bool
     * @throws Exception
     * @throws Exception
     */
    public function hubicFolderExists($folder = '')
    {
        if ($folder === '')
            throw new \Exception('Method hubicFolderExists needs parameter $folder');
        try {
            $r = $this->listFolder($folder);
        } catch (\Exception $e) {
            if ($e->getCode() === 404)
                return false;
            throw $e;
        }
        return true;
    }


    /**
     * Download file from Hubic
     *
     * @param string $file
     * @param string $saveToFolder
     * @param string $container
     * @param bool $stream
     * @return bool true
     * @throws Exception
     */
    public function downloadFile($file = '', $saveToFolder = '', $container = 'default', $stream = false)
    {
        if ($file === '')
            throw new \Exception('Method downloadFile needs parameter $file');

        if ($saveToFolder === '')
            throw new \Exception('Method downloadFile needs parameter $saveAs');
        if (!is_dir($saveToFolder))
            throw new \Exception('Folder ' . $saveToFolder . ' does not exists');
        if (!is_writable($saveToFolder))
            throw new \Exception('Folder ' . $saveToFolder . ' is not writable');
        // if last char is (back)slash remove it
        $t = substr($saveToFolder, -1);
        if (!ctype_alnum($t))
            $saveToFolder = substr($saveToFolder, 0, strlen($saveToFolder) - 1);

        // Get Hubic folder and name from $file
        $p = explode('/', $file);
        if ($p[count($p) - 1] === '')
            array_pop($p);
        $name = $p[count($p) - 1];
        array_pop($p);
        $folder = implode('/', $p);

        // Get size of file
        $r = $this->listFolder($folder, $container);
        if (!array_key_exists($name, $r))
            throw new \Exception('File ' . $file . ' not found on your Hubic.');
        $size = $r[$name]['size'];
        // type
        $type = $r[$name]['type'];
        // key (rand)
        $key = (int)round(mt_rand() * time());
        // sessionHash is needed
        $s = $this->getSettings();

        // https://app.hubic.me/v2/actions/ajax/hubic-browser.php?action=download&folder=/toorop/Tests/Folder2/Tests&container=default&name=mod_fcgid.c&key=126146434820&isFile=true&size=41777&type=&secret=113d0235ef265d1b239a4a3be3846ea3f3a47dcd1af67f14068b36e80225dafe
        $url = 'https://app.hubic.me/v2/actions/ajax/hubic-browser.php';

        // GET parameters as array for better readability
        $get = array(
            'action' => 'download',
            'folder' => urlencode($folder),
            'container' => urlencode($container),
            'name' => urlencode($name),
            'key' => $key,
            'isFile' => 'true',
            'size' => (string)$size,
            'type' => urlencode($type),
            'secret' => $s->sessionHash
        );
        $par = '';
        foreach ($get as $k => $v) $par .= '&' . $k . '=' . $v;
        $par = substr($par, 1);
        $url .= '?' . $par;

        /* Init Curl */
        $ch = curl_init($url);
        /* Cookies */
        $cookiesFile = $this->getCookiesPathFile();
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiesFile);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiesFile);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /* Header */
        $headers = array('User-Agent: ' . $this->userAgent, 'Origin: https://app.hubic.me');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        /* save to */
        $saveTo=$saveToFolder.'/'.$name;
        $fp = fopen($saveTo, 'w');
        curl_setopt($ch, CURLOPT_FILE, $fp);

        /* Verbosity (debug) */
        curl_setopt($ch, CURLOPT_VERBOSE, 1);
        /* Go go go !!! */
        $r = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if($httpCode!==200)
            throw new Exception("Download Failed - Bad HTTP response from Hubic server - Expected 200 Recieved ".$httpCode);
        if($r!==true)
            throw new \Exception("Download failed - Hubic response is false");
        return true;
    }

    /**
    /**
     * Return file name and directory path
     * @param string $file
     * @return array
     * @throws Exception
     */
    private function getFolderAndNameFromFile($file)
    {
        if (empty($file))
            throw new \Exception('Method getFolderAndNameFromFile needs parameter $file');
        $file=(string)$file;
        $p = explode('/', $file);
        if ($p[count($p) - 1] === '')
            array_pop($p);
        $name = $p[count($p) - 1];
        array_pop($p);
        $folder = implode('/', $p);
        return array($folder,$name);
    }


    /**
     * Parse cookies file
     * @return array
     */
    private function getCookies()
    {
        $cookies = array();
        // read cookie file
        $t = file($this->getCookiesPathFile());
        // @todo : gestion des erreurs
        unset($t[0], $t[1], $t[2], $t[3]);
        foreach ($t as $c) {
            $ac = explode("\t", $c);
            $cookies[$ac[5]] = $ac[6];
        }
        if (array_key_exists('HUBIC_ACTION_RETURN', $cookies)) {
            $cookies['HUBIC_ACTION_RETURN'] = urldecode($cookies['HUBIC_ACTION_RETURN']);
        }
        return $cookies;
    }


    /*
    * Get path to cookies files
    * @return string
    */
    private function getCookiesPathFile()
    {
        return $this->tempDir . $this->getPathSeparator() . 'cookies.txt';
    }

    /**
     * Return path separator
     * @return string
     */
    private function getPathSeparator()
    {
        // NOTE : according to PHP doc Windows accept / as path separator
        // @todo :  clean the code that use this method
        return "/";

        #if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') return '\\';
        #return '/';
    }


}
