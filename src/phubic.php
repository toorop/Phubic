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
 *      - publication  unpublish
 *      - limit up/dowload speed
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
        if (!is_array($config)) throw new Exception('Contructor parameter $config must be a array. ' . gettype($config) . ' given');
        if (!array_key_exists('login', $config) || !array_key_exists('passwd', $config)) throw new Exception('Parameter $config must have at least "login"and "passwd" keys set');

        // Hubic login
        $this->hubicLogin = trim($config['login']); // Don't try to know why i "trim", you loose your time.
        // Hubic passwd
        $this->hubicPasswd = trim($config['passwd']);
        // Temp dir with rw access (cookies)
        if (isset($config['tempDir']))
            $this->tempDir = trim($config['tempDir']);
        else
            $this->getTempDir();
        $this->tempDir = $this->removeTrailingSlash($this->tempDir);
        if (!file_exists($this->tempDir))
            throw new Exception('tempDir parameter ' . $this->tempDir . ' is not an existing directory');
        if (!touch($this->tempDir . $this->getPathSeparator() . 'test'))
            throw new Exception('tempDir ' . $this->tempDir . ' is not writable');
        unlink($this->tempDir . $this->getPathSeparator() . 'test');

        // user agent
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

        // Cookies
        $cookiesFile = $this->getCookiesPathFile();
        // file exist ?
        if (!file_exists($cookiesFile)) {
            touch($cookiesFile);
        }
        // Post data
        $post = array('sign-in-email' => $this->hubicLogin, 'sign-in-password' => $this->hubicPasswd, 'sign-in-action' => 'true');

        // Curl
        $cr = $this->curlPost('https://app.hubic.me/v2/actions/nasLogin.php', $post);
        if ($cr['error'])
            throw new Exception('Login failed : ' . $cr['$error']);
        // HTTP_CODE must be 302 (redirect to location: /v2/)
        if ($cr['httpCode'] !== 302)
            throw new Exception('Bad HTTP code returned by Hubic server on login. Returned : ' . $cr['httpCode'] . ' Expected : 302');

        /* Cookie HUBIC_ACTION_RETURN ? */
        $cookies = $this->getCookies();
        if (array_key_exists('HUBIC_ACTION_RETURN', $cookies)) {
            $r = json_decode($cookies['HUBIC_ACTION_RETURN']);
            if (isset($r->answer->login->message))
                throw new Exception($r->answer->login->message);
            throw new Exception('Login failed');
        }
    }

    /**
     * Logout
     */
    public function logout()
    {
        /* Init Curl */
        $ch = curl_init("https://app.hubic.me/v2/actions/ajax/logoff.php");
        /* Post data */
        $post = array('action' => 'unload');
        /* Go go go !!! */
        $cr = $this->curlPost('https://app.hubic.me/v2/actions/ajax/logoff.php', $post);
        if ($cr['httpCode'] !== 200) throw new Exception('Logout Fail');
        @unlink($this->getCookiesPathFile());
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
            throw new Exception('Bad HTTP code returned by Hubic server on getSetting. Returned : ' . $httpCode . ' Expected : 200');
        }
        curl_close($ch);
        $t = json_decode($r);
        if ($t === false)
            throw new Exception('Unexpected response returned by Hubic server on getSetting. Returned : ' . (string)$r);
        if (!isset($t->answer) || !isset($t->answer->status))
            throw new Exception('Unexpected response returned by Hubic server on getSetting. Returned : ' . (string)$r);
        if ($t->answer->status !== 200)
            throw new Exception('Bad response code returned by Hubic server on getSetting. Returned : ' . $t->answer->status . ' Expected : 200');
        if (isset($t->answer->settings->hubic)) {
            $this->hubicSettings = $t->answer->settings->hubic;
            return $this->hubicSettings;
        }
        throw new Exception('No settings returned by Hubic server. Response(JSON) : ' . (string)$r);
    }


    /**
     * List folder
     *
     * @param string $folder
     * @param string $container
     * @return mixed
     * @throws Exception
     */
    public function listFolder($folder, $container = 'default')
    {
        if (empty($folder))
            $folder = '/';
        $folder = (string)$folder;

        if (empty($container))
            $container = 'default';
        $container = (string)$container;

        // Post data
        $post = array('action' => 'get', 'folder' => $folder, 'container' => urlencode($container), 'init' => 'true');

        // Curl
        $cr = $this->curlPost('https://app.hubic.me/v2/actions/ajax/hubic-browser.php', $post);
        if ($cr['httpCode'] !== 200) {
            throw new Exception('Bad HTTP code Received : ' . $cr['httpCode']);
        }
        $r = json_decode($cr['response'], true, 512);
        if ($r === false)
            throw new Exception('Unexpected response returned by Hubic server on listFolder. Returned : ' . (string)$cr['response']);
        if (!isset($r['answer']) || !isset($r['answer']['hubic']))
            throw new Exception('Unexpected response returned by Hubic server on listFolder. Returned : ' . (string)$cr['response']);

        // get fullCacheMode. If not fullCacheMode we are at root
        $fullCacheMode = @$r['answer']['hubic']['fullCacheMode'];
        $root = @$r['answer']['hubic']['list'][$container]['items'];
        if ($fullCacheMode) {
            if ($folder !== '/') {
                $parts = explode('/', $folder);
                foreach ($parts as $p) {
                    if ($p and strlen($p) > 0) {
                        if (isset($root[$p]['items'])) {
                            $root = $root[$p]['items'];
                        } else {
                            throw new Exception('No such folder ' . $folder, 404);
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
     * @param string $src  file or folder to upload
     * @param string $destPath  root path for destination.
     * @param string $container
     * @return bool
     * @throws Exception
     *
     * @todo : add BP limitation
     * @todo : (pseudo) sync mode : if file exit and size===size continue
     *
     *
     */
    public function upload($src = '', $destPath = '', $container = 'default')
    {
        if ($src === '' || $destPath === '') throw new Exception("Upload need src and dest parameters");
        $src = trim($src); // clean & cast
        $src = $this->removeTrailingSlash($src);
        if (!file_exists($src)) throw new Exception('File or folder ' . $src . ' not found');

        $destPath = $this->removeTrailingSlash(trim($destPath)); // itoo
        // destPath exists ? If Not create it
        $this->createFolder($destPath, $container);

        // $src is Folder ?
        if (is_dir($src)) {
            $ls = scandir($src);
            foreach ($ls as $t) {
                if ($t == '.' || $t == '..') continue;
                if (is_dir($src . '/' . $t)) {
                    $this->upload($src . '/' . $t, $destPath . '/' . $t, $container);
                } else {
                    $this->upload($src . '/' . $t, $destPath, $container);
                }
            }
            return true;
        }
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
            'X-File-Dest :' . urlencode($destPath),
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
            throw new Exception('Error uploading ' . $src . ' - Hubic HTTP response code : ' . $httpCode . ' - Json response : ' . $resp);
        $r = json_decode($resp);

        #todo $r===false
        if ($r === false)
            throw new Exception('Error uploading ' . $src . ' - Unexpected Hubic response : JSON expected, recieved : ' . (string)$resp);
        if (is_null($r->answer))
            throw new Exception('Error uploading ' . $src . ' - ' . $r->error->message);
        if (isset($r->answer->error) && $r->answer->error !== null) {
            throw new Exception('Error uploading ' . $src . ' - Hubic HTTP response code : ' . $httpCode, ' - Json response : ' . $resp);
        }
        // is filesize OK ? (=> simili integrity check)
        if ($r->answer->upload->size !== $size)
            throw new Exception('Integrity check failed, uploaded filesize (' . $r->answer->upload->size . ') does not match with original size (' . $size . ')');
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
            throw new Exception('Method createFolder needs parameter $folder');
        $folder = trim($folder);

        // clean ending / if present
        if (substr($folder, strlen($folder) - 1, 1) == '/')
            $folder = substr($folder, 0, strlen($folder) - 1);
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
        // Post data
        $post = array('action' => 'create', 'folder' => $postFolder, 'container' => urlencode($container), 'name' => urlencode($postName));

        // Go
        $cr = $this->curlPost('https://app.hubic.me/v2/actions/ajax/hubic-browser.php', $post);
        if ($cr['httpCode'] !== 200) {
            throw new Exception('Bad HTTP code returned by Hubic server on createFolder. Returned : ' . $cr['httpCode'] . ' Expected : 200');
        }
        $t = json_decode($cr['response']);
        if ($t === false) // not a json response as expected
            throw new Exception('Unexpected response returned by Hubic server on createFolder. Returned : ' . (string)$cr['response']);
        if (!isset($t->answer) || !isset($t->answer->status))
            throw new Exception('Unexpected response returned by Hubic server on createFolder. Returned : ' . (string)$cr['response']);
        if ($t->answer->status !== 201)
            throw new Exception('Bad response code returned by Hubic server on createFolder. Returned : ' . $t->answer->status . ' Expected : 201');

        return true;
    }


    /**
     * Download file or folder from Hubic
     *
     * @param string $src file or folder to download   from hubic
     * @param string $dest local folder where you want $src to be downloaded
     * @param string $container
     * @param mixed $options
     * @return bool true
     * @throws Exception
     */
    public function download($src = '', $dest = '', $container = 'default', $options = false)
    {
        if ($src === '')
            throw new Exception('Method downloadFile needs parameter $src');
        $src = $this->removeTrailingSlash($src);
        $dest = $this->removeTrailingSlash($dest);
        if ($dest === '')
            throw new Exception('Method downloadFile needs parameter $dest');
        if (!file_exists($dest))
            if (is_dir(dirname($dest)))
                mkdir($dest);
            else
                throw new Exception('Local folder ' . dirname($dest) . ' does not exists');
        if (!is_dir($dest))
            throw new Exception($dest . ' must be an existing (writable) folder not exists');
        if (!is_writable($dest))
            throw new Exception('Local folder ' . $dest . ' is not writable');


        $i = $this->getFileInfo($src);
        // Folder
        if ($i['isFile'] === false) {
            // List folder
            $ls = $this->listFolder($src);
            foreach ($ls as $name => $data) {
                if ($data['isFile']) {
                    $this->download($src . '/' . $name, $dest, $container, $options);
                } else {
                    $this->download($src . '/' . $name, $dest . '/' . $name, $container, $options);
                }
            }
            return true;
        }
        // Get Hubic folder and name from $file
        list($folder, $name) = $this->getFolderAndNameFromPath($src);

        // Get size of file
        $r = $this->listFolder($folder, $container);
        if (!array_key_exists($name, $r))
            throw new Exception('File ' . $file . ' not found on your Hubic.');
        $size = $r[$name]['size'];
        // type
        $type = $r[$name]['type'];
        // key (rand)
        $key = (int)round(mt_rand() * time());
        // sessionHash is needed
        $s = $this->getSettings();
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
        $saveTo = $dest . '/' . $name;
        $fp = fopen($saveTo, 'w');
        if ($fp === false)
            throw new Exception('Fail to open ' . $saveTo . ' for writting');
        curl_setopt($ch, CURLOPT_FILE, $fp);

        /* Verbosity (debug) */
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        /* Go go go !!! */
        $r = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($httpCode !== 200)
            throw new Exception("Download Failed - Bad HTTP response from Hubic server - Expected 200 Received " . $httpCode);
        if ($r !== true)
            throw new Exception("Download failed - Hubic response is false");
        return true;
    }

    /**
     * Remove file
     *
     * @param string $path
     * @param string $container
     * @return bool
     * @throws Exception
     */
    public function remove($path, $container = 'default')
    {
        if (empty($path))
            throw new Exception('Method removeFile needs parameter $path');
        $path = $this->removeTrailingSlash($path);

        // get info
        $i = $this->getFileInfo($path);
        list($folder, $name) = $this->getFolderAndNameFromPath($path);

        // file type
        $r = $this->listFolder($folder, $container);
        if (!array_key_exists($name, $r))
            throw new Exception('File ' . $path . ' not found on your Hubic.');

        // Post data
        $post = array(
            'action' => 'remove',
            'folder' => $folder,
            'container' => $container,
            'name' => $name,
            'isFile' => $r[$name]['isFile'],
            'type' => $r[$name]['type']
        );

        // Go
        $cr = $this->curlPost('https://app.hubic.me/v2/actions/ajax/hubic-browser.php', $post);
        if ($cr['httpCode'] !== 200)
            throw new Exception("Remove Failed - Bad HTTP response from Hubic server - Expected 200 Received " . $cr['httpCode']);
        $j = json_decode($cr['response']);
        if ($j === false)
            throw new Exception("Remove Failed - Bad response from Hubic server - Expected JSON formated string but get " . (string)$cr['response']);
        if (!isset($j->answer) || !isset($j->answer->status))
            throw new Exception("Remove Failed - Bad JSON response from Hubic server : " . (string)$cr['response']);
        if ((int)$j->answer->status !== 204)
            throw new Exception('Bad response code returned by Hubic server on removeFile. Returned : ' . $j->answer->status . ' Expected : 204');
        return true;
    }

    /**
     * Publish a file or a folder
     *
     * @param $fileOrFolder
     * @param string $message
     * @param int $duration in days
     * @return mixed
     * @throws Exception
     */
    public function publish($fileOrFolder, $message = '', $duration = 5)
    {
        if (empty($fileOrFolder))
            throw new Exception('Method publish need $fileOrFolder');
        if (strlen($message > 255)) // truncate at 255 char . Why ? Because you make too much noise !
            $message = substr($message, 0, 255);
        $duration = (int)$duration;
        if ($duration > 30) $duration = 30;

        // get info
        $i = $this->getFileInfo($fileOrFolder);
        // Already published ?
        if (!is_null($i['publication']))
            throw new Exception($fileOrFolder . ' already published');
        list($folder, $name) = $this->getFolderAndNameFromPath($fileOrFolder);

        // Post
        $post = array(
            'action' => 'publish',
            'folder' => $folder,
            'name' => $name,
            'container' => $i['container'],
            'isFile' => $i['isFile'],
            'duration' => $duration,
            'message' => $message
        );
        // Go
        $cr = $this->curlPost('https://app.hubic.me/v2/actions/ajax/hubic-browser.php', $post);
        if ($cr['httpCode'] !== 200)
            throw new Exception("Publish Failed - Bad HTTP response from Hubic server - Expected 200 Received " . $cr['httpCode']);
        $j = json_decode($cr['response']);
        if ($j === false)
            throw new Exception("Publish Failed - Bad response from Hubic server - Expected JSON formated string but get " . (string)$cr['response']);
        if (!isset($j->answer) || !isset($j->answer->status))
            throw new Exception("Publish Failed - Bad JSON response from Hubic server : " . (string)$cr['response']);
        if ((int)$j->answer->status !== 200)
            throw new Exception('Bad response code returned by Hubic server on publish. Returned : ' . $j->answer->status . ' Expected : 200');
        return $j->answer->publicationItem;
    }


    /***
     *
     * TOOLS
     *
     ***/


    /**
     * Return file name and directory path
     *
     * @param string $path
     * @return array
     * @throws Exception
     *
     *  was getFolderAndNameFromPath
     *
     */
    private function getFolderAndNameFromPath($path)
    {
        if (empty($path))
            throw new Exception('Method getFolderAndNameFromPath needs parameter $path');
        $path = (string)$path;
        $p = explode('/', $path);
        if ($p[count($p) - 1] === '')
            array_pop($p);
        $name = $p[count($p) - 1];
        array_pop($p);
        $folder = implode('/', $p);
        return array($folder, $name);
    }

    /**
     * Check if a given folder exists
     *
     * @param string $folder
     * @return bool
     * @throws Exception
     * @throws Exception
     */
    public function hubicFolderExists($folder)
    {
        if (empty($folder))
            throw new Exception('Method hubicFolderExists needs parameter $folder');
        try {
            $this->listFolder($folder);
        } catch (Exception $e) {
            if ($e->getCode() === 404)
                return false;
            throw $e;
        }
        return true;
    }


    /**
     * return file or folder info
     *
     * @param $f (file or folder)
     * @return mixed
     * @throws Exception
     */
    private function getFileInfo($f)
    {
        if (empty($f))
            throw new Exception('Method getFileInfo needs paramerter $f');
        // remove trailing slash if present
        $f = (string)$f;
        $p = explode('/', $f);
        if ($p[count($p) - 1] === '')
            array_pop($p);
        $name = $p[count($p) - 1];
        array_pop($p);
        $parentFolder = implode('/', $p);
        try {
            $r = $this->listFolder($parentFolder);
        } catch (Exception $e) {
            if ($e->getCode() === 404)
                throw new Exception("Folder $parentFolder doesn't exists");
            throw $e;
        }
        if (!array_key_exists($name, $r))
            throw new Exception($f . ' not found on Hubic strorage');
        return $r[$name];
    }

    private function removeTrailingSlash($path)
    {
        $t = substr($path, strlen($path) - 1, 1);
        if ($t === "/" || $t === "\\")
            $path = substr($path, 0, strlen($path) - 1);
        return $path;
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

    /**
     * @return string
     * @throws Exception
     */
    private function getTempDir()
    {
        if ($this->tempDir)
            return $this->tempDir;

        if (!function_exists('sys_get_temp_dir')) {
            if (!empty($_ENV['TMP'])) {
                $this->tempDir = realpath($_ENV['TMP']);
            } elseif (!empty($_ENV['TMPDIR'])) {
                $this->tempDir = realpath($_ENV['TMPDIR']);
            } elseif (!empty($_ENV['TEMP'])) {
                $this->tempDir = realpath($_ENV['TEMP']);
            } else throw new Exception('You must specify a temp dir in config array');
        } else $this->tempDir = sys_get_temp_dir();
        return $this->tempDir;
    }


    /***
     *
     * CURL METHODS
     *
     ***/

    /**
     * Performing a POST request via Curl
     *
     * @param string $url
     * @param array $data
     * @param array $xtraHeaders
     * @param array $xtraOptions
     * @return array
     * @throws Exception
     */
    private function curlPost($url, $data = array(), $xtraHeaders = array(), $xtraOptions = array())
    {
        // url
        if (empty($url))
            throw new Exception('Parameter $url is not set');
        $url = (string)$url;
        // headers
        if (!is_array($xtraHeaders))
            throw new Exception('Parameter $xtraHeaders must be a array');
        // data (POST parameters)
        if (!is_array($data))
            throw new Exception('Parameter $data must be a array');
        // Curl extra options
        if (!is_array($xtraOptions))
            throw new Exception('Parameter $xtraOptions must be a array');

        // Curl is avalaible ?
        if (!function_exists('curl_init'))
            throw new Exception('Curl seems to be not supported by your PHP version, checks : http://php.net/manual/en/curl.installation.php');

        // init Curl
        $c = curl_init($url);
        // Verbosity (useful for debug)
        curl_setopt($c, CURLOPT_VERBOSE, 0);
        // Timeouts
        curl_setopt($c, CURLOPT_TIMEOUT, 3600); // @todo read ini_get('max_execution_time');
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 10);
        // Cookies
        $cookiesFile = $this->getCookiesPathFile();
        curl_setopt($c, CURLOPT_COOKIEFILE, $cookiesFile);
        curl_setopt($c, CURLOPT_COOKIEJAR, $cookiesFile);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        // Header
        $headers = array('User-Agent: ' . $this->userAgent, 'Origin: https://app.hubic.me');
        if ($xtraHeaders)
            $headers = array_merge($headers, $xtraHeaders);
        curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
        // Post data
        curl_setopt($c, CURLOPT_POSTFIELDS, $data);
        // xtraOptions
        foreach ($xtraOptions as $curlOption => $value) {
            try {
                curl_setopt($c, strtoupper($curlOption), $value);
            } catch (Exception $e) {
                throw new Exception('Bad Curl option (or value) - Option : ' . $curlOption . ' Value : ' . (string)$value);
            }
        }

        // Go go go !!!
        try {
            $r = curl_exec($c);
            $httpCode = curl_getinfo($c, CURLINFO_HTTP_CODE);
            $error = curl_error($c);
            $errno = curl_errno($c);
            $info = curl_getinfo($c);
            curl_close($c);
        } catch (Exception $e) {
            throw new Exception('Curl failed : ' . $e->getMessage() . ' Trace : ' . $e->getTraceAsString());
        }
        return array('response' => $r, 'httpCode' => $httpCode, 'error' => $error, 'errno' => $errno, 'info' => $info);
    }
}
