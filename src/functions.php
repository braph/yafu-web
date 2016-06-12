<?php 

/********************** MUST BE INCLUDED **********************/

list($mainFile) = get_included_files();
if($mainFile == __FILE__) {
    header("Location: index.php?error=403");
    die("403 Forbidden");
}

/********************** ONE-TIME JOBS **********************/

function getUserAction() {
    if((isset($_FILES['upload']['name']) && isset($_FILES['upload']['tmp_name'])) ||
        (isset($_POST['content']) && isset($_POST['filename']))) {
        return 'Upload';
    } elseif(isset($_GET['uploadid'])) {
        return 'getUploadStatus';
    } elseif(isset($_GET['get'])) {
        return 'getFile';
    } elseif(isset($_GET['thumbnail'])) {
        return 'getThumbnail';
    } elseif(isset($_GET['delete'])) {
        return 'deleteFile';
    } elseif(isset($_GET['info'])) {
        return 'getFileInfo';
    } elseif(isset($_GET['show'])) {
        return 'showTextFile';
    } elseif(isset($_GET['mail'])) {
        return 'sendInfoMail';
    } elseif(isset($_POST['store_email']) && isset($_POST['delcode'])) {
        return 'storeEMailAddress';
    } elseif(isset($_GET['search']) || (isset($_GET['action']) && $_GET['action'] == 'listfiles')) {
        return 'listFiles';
    } elseif(isset($_GET['action'])) {
        switch($_GET['action']) {
            case 'paste':
                return 'showPasteForm';
                break;
            case 'search':
                return 'showSearchForm';
                break;
            case 'info':
                return 'showInfo';
                break;
            case 'chpasswd':
                return 'changePassword';
                break;
            case 'fixpng':
                return 'fixPNG';
                break;
            default:
                break;
        }
    } elseif(isset($_GET['error'])) {
        if($_GET['error'] == '404' || $_GET['error'] == '403') {
            return 'Error'.$_GET['error'];
        }
    }
}

function updateAdditionalFiles() {
    global $FilesToUpdate;

    foreach($FilesToUpdate as $currentData) {
        $newContent = preg_replace($currentData['pattern'], $currentData['replacement'],
            file_get_contents($currentData['path']));

        if(md5($newContent)!=md5_file($currentData['path'])) {
            file_put_contents($currentData['path'], $newContent);
        }
    }
}

/********************** TEMPLATE SYSTEM **********************/

function getTemplateMessagesForFile($file) {
    return array(
        'FILENAME' => wordwrap(htmlspecialchars($file[COLUMN::FILENAME]), 40, "<br />\n", true),
        'URL_FILENAME' => urlencode($file[COLUMN::FILENAME]),
        'FILESIZE' => getHumanReadableSize($file[COLUMN::SIZE]),
        'MIMETYPE' => $file[COLUMN::MIMETYPE],
        'ICON_MIMETYPE' => getMimeTypeIcon($file[COLUMN::MIMETYPE]),
        'CRC32' => $file[COLUMN::CRC32],
        'HAS_PASSWORD' => !empty($file[COLUMN::PASSWORD])
    );
}

function generateXHTMLCode($content, $templatefile) {
    $output = file_get_contents(TEMPLATES.$templatefile);

    foreach($content as $name => $value) {
        ${$name} = $value;
        if(is_scalar($value)) {
            if(substr($value, 0, 5) === "file:") {
                unset($content[$name]);
                $value = generateXHTMLCode($content, substr($value, 5));
            }
            $output = str_replace('{'.$name.'}', $value, $output);
        }
    }

    if(strpos($output, '<?php') !== false) {
        ob_start();
        $output = "?>".$output;
        eval($output);
        $output = ob_get_clean();
        if(ob_get_length() > 0) {
            ob_flush();
        }
    }

    return $output;
}

function printXHTML() {
    global $MESSAGES;

    $MESSAGES = array_merge($MESSAGES, array(
        'STYLESHEET' => LOCAL_PATH."/stylesheet.css",
        'NAVIGATION_IMG'=> LOCAL_PATH.'/'.IMAGES."/navigation.png"
    ));

    echo(generateXHTMLCode($MESSAGES, 'Index.html'));
}

function printMinimalXHTML() {
    global $MESSAGES;

    $MESSAGES = array_merge($MESSAGES, array(
        'STYLESHEET' => LOCAL_PATH."/minimal-stylesheet.css"
    ));

    echo(generateXHTMLCode($MESSAGES, 'Minimal.html'));
}

function printErrorXHTML($errorTitle, $errorMessage, $useJavaScriptBacklink = true) {
    global $MESSAGES;

    $MESSAGES = array_merge($MESSAGES, array(
        'CONTENT' => 'file:Error.html',
        'ERROR_TITLE' => htmlspecialchars($errorTitle),
        'ERROR_MESSAGE' => $errorMessage,
    ));
    addFooterLink("Zurück zur vorherigen Seite", LOCAL_PATH.'/', "history.back(); return false");
    printXHTML();
}

function addFooterLink($description, $link, $onclick = '') {
    global $MESSAGES;

    if(!isset($MESSAGES['FOOTER']) || strlen($MESSAGES['FOOTER']) == 0) {
        $MESSAGES['FOOTER'] = '<a href="'.$link.'"'.
            (!empty($onclick)?' onclick="'.$onclick.'"':'').
            '>'.$description.'</a>';
    } else {
        $MESSAGES['FOOTER'] .= ' | <a href="'.$link.'" '.
            (!empty($onclick)?' onclick="'.$onclick.'"':'').
            '>'.$description.'</a>';
    }
}

/********************** "HUMAN READABLE" STUFF **********************/

function getHumanReadableLanguage($language) {
    if($language == 'text') {
        return "Klartext";
    } elseif(file_exists(GESHI_SCRIPT)) {
        include_once(GESHI_SCRIPT);
        $GeshiObject =& new GeSHi(null, $language, GESHI_LANGUAGES);
        return $GeshiObject->get_language_name();
    } elseif($language == 'php') {
        return "PHP und ähnliche Sprachen";
    } else {
        return strtoupper($language);
    }
}

function getHumanReadableSize($size, $nbsp = true) {
    $unit = array('Bytes', 'KB', 'MB', 'GB');

    for($i=0; $i<=count($unit); $i++) {
        $step = pow(1000, $i);
        if($size < $step*1000) {
            if(($size%$step) == 0) {
                return $size/$step.($nbsp?'&nbsp;':' ').$unit[$i];
            } else {
                return number_format($size/$step, 2, ',', '').($nbsp?'&nbsp;':' ').$unit[$i];
            }
        }
    }
}

function getHumanReadableTimePeriod($timestamp) {
    if($timestamp == 0) {
        return "Nie";
    } 

    $period = time() - abs($timestamp);

    if($period > 0) {
        $prefix = "vor";
    } else {
        $prefix = "in";
        $period *= -1;
    }

    if($period == 0) {
        return "jetzt";
    } elseif($period == 1) {
        return $prefix." einer Sekunde";
    } elseif($period < 60) {
        return $prefix." ".round($period)." Sekunden";
    } elseif(round($period/60) == 1) {
        return $prefix." einer Minute";
    } elseif($period < 60*60) {
        return $prefix." ".round($period/60)." Minuten";
    } elseif(round($period/(60*60)) == 1) {
        return $prefix." einer Stunde";
    } elseif($period < 60*60*24) {
        return $prefix." ".round($period/(60*60))." Stunden";
    } elseif(round($period/(60*60*24)) == 1) {
        return $prefix." einem Tag";
    } else {
        return $prefix." ".round($period/(60*60*24))." Tagen";
    }

}

function getMimeTypeIcon($mimetype) {
    list($type, $subtype) = split('/', $mimetype, 2);
    switch($type) {
        case 'application':
            switch($subtype) {
                case 'msword':
                case 'pdf':
                case 'postscript':
                case 'rtf':
                    return MIMETYPE_ICONS."document.png"; 
                    break;
                case '':
                    return MIMETYPE_ICONS."drawing.png"; 
                    break;
                case 'msexcel':
                    return MIMETYPE_ICONS."spreadsheet.png"; 
                    break;
                case 'gzip':
                case 'x-compress':
                case 'x-cpio':
                case 'x-gtar':
                case 'x-tar':
                case 'x-rar':
                case 'zip':
                    return MIMETYPE_ICONS."package.png"; 
                    break;
                case 'mspowerpoint':
                    return MIMETYPE_ICONS."presentation.png"; 
                    break;
                case 'xhtml+xml':
                case 'xml':
                case 'javascript':
                case 'x-shockwave-flash':
                    return MIMETYPE_ICONS."web.png"; 
                    break;
                default:
                    return MIMETYPE_ICONS."application.png"; 
                    break;
            }
        case 'audio':
            return MIMETYPE_ICONS."audio.png"; 
            break;
        case 'image':
            return MIMETYPE_ICONS."image.png"; 
            break;
        case 'text':
            switch($subtype) {
                case 'css':
                case 'html':
                case 'javascript':
                case 'xml':
                    return MIMETYPE_ICONS."web.png"; 
                    break;
                case 'richtext':
                case 'rtf':
                    return MIMETYPE_ICONS."document.png"; 
                    break;
                case 'comma-separated-values':
                    return MIMETYPE_ICONS."spreadsheet.png"; 
                    break;
                default:
                    return MIMETYPE_ICONS."text.png"; 
                    break;
            }
            break;
        case 'video':
            return MIMETYPE_ICONS."video.png";
            break;
    }
}


/********************** CHECK SOMETHING **********************/

function checkUploadedFile($file) {
    $errorcode = UPLOAD_ERR_OK;
    if($file['error'] != UPLOAD_ERR_OK) {
        switch($file['error']) {
        case UPLOAD_ERR_INI_SIZE: 
            $errorcode = "Die hochgeladene Datei übersteigt als das Serverlimit.";
            break;
        case UPLOAD_ERR_PARTIAL:
            $errorcode = "Die hochgeladene Datei wurde nicht komplett übertragen.";
            break;
        case UPLOAD_ERR_NO_FILE:
            $errorcode = "Es wurde keine Datei angegeben.";
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $errorcode = "Das temporäres Verzeichnis auf dem Server fehlt.";
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $errorcode = "Die Datei konnte nicht in das temporäres Verzeichnis ".
                            "auf dem Server geschrieben werden.";
            break;
        case UPLOAD_ERR_EXTENSION:
            $errorcode = "Die Datei durch eine Erweiterung blockiert.";
        }
    } elseif($file['size'] > MAX_FILESIZE) {
        $errorcode = "Die hochgeladene Datei ist grösser als die maximale Dateigrösse ".
                        "von ".(MAX_FILESIZE/(1000*1000))." Megabytes.";
    } elseif($file['size'] == 0) {
        $errorcode = "Die hochgeladene Datei ist leer.";
    }
    return $errorcode;
}

function getFileRequest($fileRequest) {
    if(strpos($fileRequest, ':') === false) {
        return "Ungültig formulierte Anfrage.";
    }

    list($crc32, $filename) = explode(':', $fileRequest, 2);

    if(($currentFile = getFileFromIndexArray(COLUMN::CRC32, $crc32)) === false) {
        return "Die angeforderte Datei konnte nicht gefunden werden.";
    } 

    if((($hashKeyOffset = strpos($currentFile[COLUMN::FILENAME], '#')) !== false) &&
        (strpos($filename, '#') === false)) {

        $filename .= substr($currentFile[COLUMN::FILENAME], $hashKeyOffset);
    }

    if(strlen($filename) != strlen($currentFile[COLUMN::FILENAME])) {
        return "Der gespeicherte und der angeforderte Dateiname haben nicht die gleiche Länge.";
    }

    for($i = 0; $i < strlen($filename); $i++) {
        if(ctype_alnum(substr($filename, $i, 1))) {
            if(substr($filename, $i, 1) != substr($currentFile[COLUMN::FILENAME], $i, 1)) {
                return "Der gespeicherte und der angeforderte Dateiname stimmen nicht überein.";
            }
        }
    }

    return $currentFile;
}

function checkPassword(&$currentFile) {
    global $MESSAGES;

    if(empty($currentFile[COLUMN::PASSWORD])) {
        return true;
    }

    $encryptedPassword = null;

    if(isset($_SERVER['PHP_AUTH_PW'])) {
        $encryptedPassword = sha1(utf8_encode($_SERVER['PHP_AUTH_PW']));
    } elseif(isset($_POST['sha1_password'])) {
        $encryptedPassword = $_POST['sha1_password'];
    } elseif(isset($_POST['password'])) {
        $encryptedPassword = sha1($_POST['password']);
    } elseif(isset($_COOKIE['PasswordFor'.$currentFile[COLUMN::CRC32]])) {
        $encryptedPassword = $_COOKIE['PasswordFor'.$currentFile[COLUMN::CRC32]];
    }
                
    if($encryptedPassword == $currentFile[COLUMN::PASSWORD]) {
        setcookie('PasswordFor'.$currentFile[COLUMN::CRC32], $encryptedPassword,
            0, LOCAL_PATH, null, null, true);
        return true;
    } elseif(is_null($encryptedPassword)) {
        return 'PasswordForm';
    } else {
        return 'PasswordError';
    }
}

function isTextFile($filename) {
    if(($size = filesize($filename)) >= 256000) {
        return false;
    }

    $fileHandler = fopen($filename, 'r');
    $countControlChars = $countEndOfLines = 0;

    while(($char = fgetc($fileHandler)) !== false) {
        if($char == "\n") {
            $countEndOfLines++;
        } elseif($char == "\t" || $char == "\r") {
            continue;
        } elseif(ord($char) < 0x20) {
            $countControlChars++;
            if($countControlChars > 8) {
                return false;
            }
        }
    }

    fclose($fileHandler);

    return ($countEndOfLines/$size > 0.005);
}

/********************** CREATE FILE PROPERTIES **********************/

function generateDeletionCode() {

    do {
        $pool = array_merge(range('0', '9'), range('a', 'z'), range('A', 'Z'));

        shuffle($pool);
        $code = '';

        for($i=1; $i<=12; $i++) {
            $code .= $pool[mt_rand(0,count($pool)-1)];
        }

    } while(getFileFromIndexArray(COLUMN::DELCODE, $code) !== false);

    return $code;
}

function createThumbNail($filename) {
    if(!extension_loaded('gd')) {
        return false;
    }

    $thumbnailpath = THUMBNAILS.basename($filename);

    list($width, $height, $type) = getImageSize($filename);

    if($width <= THUMBNAIL_WIDTH && $height <= THUMBNAIL_HEIGHT) {
        return false;
    } elseif($width > $height) {
        $thumbWidth = THUMBNAIL_WIDTH;
        $thumbHeight = intval($height*$thumbWidth/$width);
    } else {
        $thumbHeight = THUMBNAIL_HEIGHT;
        $thumbWidth = intval($thumbHeight*$width/$height);
    }

    $thumbnail = imageCreateTrueColor($thumbWidth, $thumbHeight);

    switch($type) {
        case 1: /* GIF */
            $image = ImageCreateFromGIF($filename);
            imageCopyResampled($thumbnail, $image, 0, 0, 0, 0, 
                        $thumbWidth, $thumbHeight, $width, $height);
            imageGIF($thumbnail, $thumbnailpath);
            break;
        case 2: /* JPEG */
            $image = ImageCreateFromJPEG($filename);
            imageCopyResampled($thumbnail, $image, 0, 0, 0, 0, 
                        $thumbWidth, $thumbHeight, $width, $height);
            imageJPEG($thumbnail, $thumbnailpath, 90);
            break;
        case 3: /* PNG */
            $image = ImageCreateFromPNG($filename);
            imageCopyResampled($thumbnail, $image, 0, 0, 0, 0, 
                        $thumbWidth, $thumbHeight, $width, $height);
            imagePNG($thumbnail, $thumbnailpath);
            break;
        default:
            return false;
    }

    return true;
}


function getMimeType($filename, $fallback = 'application/octet-stream') {
    $mimetype = $fallback;

    if(extension_loaded('FileInfo') && $finfo = @finfo_open(FILEINFO_MIME, MAGIC_FILE)) {
        $mimetype = finfo_file($finfo, realpath($filename));
        finfo_close($finfo);
    } elseif(is_callable('exec') && exec('file -v')) {
        $mimetype = exec('file -bi '.escapeshellarg($filename));
    }

    return strtok($mimetype, ',');
}


function getExpirationTimeStamp($expires) {
    switch($expires) {
        case '1w':
            return time() + 7*24*60*60;
            break;
        case '3d':
            return time() + 3*24*60*60;
            break;
        case '1d':
            return time() + 24*60*60;
            break;
        case '6h':
            return time() + 6*60*60;
            break;
        case '1h':
            return time() + 60*60;
            break;
        case '30m':
            return time() + 30*60;
            break;
        case 'max':
        default:
            if(MAX_LIFESPAN == 0) {
                return 0;
            } else {
                return ((MAX_LIFESPAN>0)?1:-1)*(time() + abs(MAX_LIFESPAN));
            }
            break;
    }
}

/********************** SYNTAX HIGHLIGHTING **********************/

function getSupportedHightlightings() {
    if(file_exists(GESHI_SCRIPT)) {
        $listedLanguages = scandir(GESHI_LANGUAGES);
        foreach($listedLanguages as &$currentLanguage) { 
            if(strtolower(substr($currentLanguage, -4)) === '.php') {
                $currentLanguage = substr($currentLanguage, 0, -4);
            } else {
                $currentLanguage = null;
            }
        }
        return array_filter($listedLanguages);
    } else {
        return array('text', 'php');
    }
}

function getPreferedHightlighting($filename) {
    $extension = strtolower(substr(strchr($filename, '.'), 1));

    if(file_exists(GESHI_SCRIPT)) {
        include_once(GESHI_SCRIPT);
        $GeshiObject =& new GeSHi(null, '', GESHI_LANGUAGES);
        $language = $GeshiObject->get_language_name_from_extension($extension);
        return (!empty($language)) ? $language : 'text';
    } else {
        switch($extension) {
            case 'php':
            case 'php5':
            case 'phps':
            case 'phtml':
                return 'php';
                break;
            case 'htm':
            case 'html':
                return 'html';
                break;
            default:
                return 'text';
        }
    }
}

function getHighlightedSource($source, $language = 'plaintext') {
    if(file_exists(GESHI_SCRIPT)) {
        include_once(GESHI_SCRIPT);
        $GeshiObject =& new GeSHi($source, $language, GESHI_LANGUAGES);
        $GeshiObject->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS, 5);
        $GeshiObject->set_header_type(GESHI_HEADER_DIV);
        $GeshiObject->set_overall_class('SourceCode');
        return $GeshiObject->parse_code();
    } elseif($language == 'php') {
        return '<div class="SourceCode">'.highlight_string($source, true).'</div>';
    } elseif($language == 'text') {
        return '<pre class="SourceCode">'.htmlspecialchars($source).'</pre>';
    }
}


/********************** FILE REMOVAL STUFF **********************/

function removeOldFiles() {
    $indexArray = getIndexArray();

    foreach($indexArray as &$row) {
        if($row[COLUMN::EXPIRES] != 0 && abs($row[COLUMN::EXPIRES]) < time()) {
            removeFile($row);
        }
    }
}

function removeFile($fileRow) {
    unlink(FILES.$fileRow[COLUMN::MD5]);
    if(file_exists(THUMBNAILS.$fileRow[COLUMN::MD5])) {
        unlink(THUMBNAILS.$fileRow[COLUMN::MD5]);
    }
    removeFileFromIndexArray(COLUMN::MD5, $fileRow[COLUMN::MD5]);
}

/********************** INDEX ARRAY ACTIONS **********************/

if(INDEX_CACHE != 'none') {
    function syncIndexArray() {
        clearstatcache();
        $fileTimestamp = filemtime(FILEINDEX);
        $memTimestamp = (INDEX_CACHE == 'tmpfs') ? getTemporaryFileTimestamp() :
            intval(shmop_read(getSharedMemoryId(), 1, SHM_TIMESTAMP));

        if($memTimestamp < $fileTimestamp) {
            if(INDEX_CACHE == 'tmpfs') {
                setTemporaryFileFromArray(getIndexFileAsArray(), $fileTimestamp);
            } elseif(INDEX_CACHE == 'shm') {
                setSharedMemoryFromArray(getIndexFileAsArray(), $fileTimestamp);
            }
            return $fileTimestamp;
        } elseif($memTimestamp > $fileTimestamp) {
            setIndexFileFromArray(
                (INDEX_CACHE == 'tmpfs') ? getTemporaryFileAsArray() : getSharedMemoryAsArray()
            );
            return $memTimestamp;
        } else {
            return $fileTimestamp;
        }
    }
}

function getIndexArray() {
    static $indexArray = null;
    static $indexArrayTimestamp = 0;
    
    if(INDEX_CACHE != 'none') {
        $oldTimestamp = $indexArrayTimestamp;
        $indexArrayTimestamp = syncIndexArray();
       
        if($indexArrayTimestamp > $oldTimestamp) {
            $indexArray = (INDEX_CACHE == 'tmpfs') ?
                    getTemporaryFileAsArray() 
                : 
                    getSharedMemoryAsArray();
        }
    } else {
        if($indexArrayTimestamp < filemtime(FILEINDEX)) {
            $indexArrayTimestamp = filemtime(FILEINDEX);
            $indexArray = getIndexFileAsArray();
        }
    }

    return $indexArray;
}

function setIndexArray(&$indexArray) {
    setIndexFileFromArray($indexArray);
    if(INDEX_CACHE != 'none') {
        syncIndexArray();
    }
}

function getFileFromIndexArray($propertyType, $propertyValue) {
    foreach(getIndexArray() as $currentFile) {
        if($currentFile[$propertyType] == $propertyValue) {
            return $currentFile;
        }
    }
    return false;
}

function addFileToIndexArray($newFile) {
    $indexArray = getIndexArray();
    if(count($newFile) == COLUMN::COUNT()) {
        $indexArray[] = $newFile;
        setIndexArray($indexArray);
        return true;
    }
    return false;
}

function removeFileFromIndexArray($propertyType, $propertyValue) {
    $indexArray = getIndexArray();
    foreach($indexArray as $currentRow => $currentFile) {
        if($currentFile[$propertyType] == $propertyValue) {
            unset($indexArray[$currentRow]);
            setIndexArray($indexArray);
            return true;
            break;
        }
    }
    return false;
}

function replaceFileInIndexArray($propertyType, $propertyValue, $newFileProperties) {
    $indexArray = getIndexArray();
    foreach($indexArray as $currentRow => $currentFile) {
        if($currentFile[$propertyType] == $propertyValue) {
            if(count($newFileProperties) == COLUMN::COUNT()) {
                $indexArray[$currentRow] = $newFileProperties;
                setIndexArray($indexArray);
                return true;
            }
            break;
        }
    }
    return false;
}


/********************** SHARED MEMORY ACTIONS **********************/

if(INDEX_CACHE == 'shm') {
    function getSharedMemoryId($minimalSize = SHM_MINIMAL_SIZE) {
        static $shmKey = null;

        if(is_null($shmKey) && !(@$shmKey = shmop_open(SHM_KEY, 'w', 0, 0))) {
            $shmKey = shmop_open(SHM_KEY, 'c', 0644, $minimalSize);
        }
        if(shmop_read($shmKey, 0, 1) == chr(0)) {
            shmop_write($shmKey, strval(LOCK_UN), 0);
        }

        return $shmKey;
    }

    function resizeSharedMemory($newSize) {
        $sharedMemoryId = getSharedMemoryId();

        lockSharedMemory(LOCK_EX);

        if($newSize > shmop_size($sharedMemoryId)) {
            $oldSharedMemoryContent = shmop_read($sharedMemoryId, 
                0, shmop_size($sharedMemoryId));

            shmop_delete($sharedMemoryId);
            shmop_close($sharedMemoryId);

            shmop_open(SHM_KEY, 'c', 0644, $newSize);

            shmop_write($sharedMemoryId, $oldSharedMemoryContent, 0);
        }

        lockSharedMemory(LOCK_UN);
    }

    function lockSharedMemory($lockState) {
        static $sharedLockCounter = 0;

        switch($lockState) {

            case LOCK_EX:
                while(intval(shmop_read(getSharedMemoryId(), 0, 1)) != LOCK_UN) {
                    usleep(50000);
                }
                shmop_write(getSharedMemoryId(), strval(LOCK_EX), 0);
                break;

            case LOCK_SH:
                $sharedLockCounter++;

                if(intval(shmop_read(getSharedMemoryId(), 0, 1)) == LOCK_SH) { break; }
                while(intval(shmop_read(getSharedMemoryId(), 0, 1)) != LOCK_UN) {
                    usleep(50000);
                }
                shmop_write(getSharedMemoryId(), strval(LOCK_SH), 0);
                break;

            case LOCK_UN:
            default:
                if(intval(shmop_read(getSharedMemoryId(), 0, 1)) == LOCK_SH) {
                    $sharedLockCounter--;
                    if($sharedLockCounter > 0) { break; }
                }

                shmop_write(getSharedMemoryId(), strval(LOCK_UN), 0);
        }
    }


    function getSharedMemoryAsArray() {
        $sharedMemoryId = getSharedMemoryId();
        lockSharedMemory(LOCK_SH);

        $indexArray = unserialize(shmop_read($sharedMemoryId, 
            SHM_TIMESTAMP+1, shmop_size($sharedMemoryId)-(SHM_TIMESTAMP+1)));

        lockSharedMemory(LOCK_UN);

        return $indexArray;
    }

    function setSharedMemoryFromArray($indexArray, $indexArrayTimestamp) {

        $serializedData = serialize($indexArray);

        resizeSharedMemory(strlen($serializedData));
        $sharedMemoryId = getSharedMemoryId();
        lockSharedMemory(LOCK_EX);

        shmop_write($sharedMemoryId, sprintf('%\'0'.SHM_TIMESTAMP.'d', $indexArrayTimestamp), 1);

        shmop_write($sharedMemoryId, $serializedData, SHM_TIMESTAMP+1);
        shmop_write(
            $sharedMemoryId, 
            str_repeat(chr(0), shmop_size($sharedMemoryId)-(SHM_TIMESTAMP+1)-strlen($serializedData)),
            1+SHM_TIMESTAMP+strlen($serializedData)
        );

        lockSharedMemory(LOCK_UN);
    }
}

/********************** TEMPORARY FILE ACTIONS **********************/

if(INDEX_CACHE == 'tmpfs') {
    function getTemporaryFileAsArray() {
        return (file_exists(TMPFS_FILE)) ? 
            unserialize(file_get_contents(TMPFS_FILE, false, null, TMPFS_TIMESTAMP)) : array();
    }

    function setTemporaryFileFromArray($indexArray, $indexArrayTimestamp) {
        if(!file_exists(TMPFS_PATH.'/.htaccess')) { 
            file_put_contents(TMPFS_PATH.'/.htaccess', "Deny from all");
        }
        file_put_contents(
            TMPFS_FILE, 
            sprintf('%\'0'.TMPFS_TIMESTAMP.'d', $indexArrayTimestamp).serialize($indexArray), 
            LOCK_EX
        );
    }

    function getTemporaryFileTimestamp() {
        return (file_exists(TMPFS_FILE)) ? 
            file_get_contents(TMPFS_FILE, false, null, 0, TMPFS_TIMESTAMP) : 0;
    }
}

/********************** INDEX FILE ACTIONS **********************/

function getIndexFileAsArray($indexFileName = FILEINDEX) {

    $indexArray = array();

    $indexFile = fopen($indexFileName, 'r') or die("Cannot open ".$indexFileName);
    flock($indexFile, LOCK_SH) or die("Cannot lock ".$indexFileName);

    while(($row = fgetcsv($indexFile)) !== false) {
        if(count($row) == COLUMN::COUNT()) {
            $indexArray[] = $row;
        }
    }

    flock($indexFile, LOCK_UN);
    fclose($indexFile);

    return $indexArray;
}

function setIndexFileFromArray(&$indexArray) {

    $tmpIndexFileName = tempnam(function_exists('sys_get_temp_dir') ? 
        sys_get_temp_dir() : dirname(realpath(FILEINDEX)), "yafu");

    $tmpIndexFile = fopen($tmpIndexFileName, 'w') or die("Cannot open ".$tmpIndexFileName);

    foreach($indexArray as &$row) {
        if(count($row) == COLUMN::COUNT()) {
            fputcsv($tmpIndexFile, $row);
        }
    }
    fflush($tmpIndexFile);
    fclose($tmpIndexFile);

    if(count(array_diff($indexArray, getIndexFileAsArray($tmpIndexFileName))) == 0) {

        $indexFile = fopen(FILEINDEX, 'r') or die("Cannot open ".FILEINDEX);
        flock($indexFile, LOCK_EX) or die("Cannot lock ".FILEINDEX);
        copy($tmpIndexFileName, FILEINDEX);
        flock($indexFile, LOCK_UN);
        unlink($tmpIndexFileName);

    } else {
        unlink($tmpIndexFileName);
        setIndexFileFromArray($indexArray);
    }
}

?>
