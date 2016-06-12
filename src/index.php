<?php error_reporting(E_ALL); 

/*
 * ----------------------------------------------------------------------------
 * "THE COFFEE-WARE LICENSE" (Revision 12/2007):
 * Sebastian Wicki <gandro@gmx.net> wrote this file. As long as you retain
 * this notice you can do whatever you want with this stuff. If we meet some
 * day, and you think this stuff is worth it, you can buy me a cup of coffee
 * in return. 
 * ----------------------------------------------------------------------------
 */

/* WARNING: ONLY MODIFY THE FOLLOWING CONSTANTS IF YOU KNOW EXACLTY WHAT YOU DO! */

define("LOCAL_PATH", 
    ((dirname($_SERVER['PHP_SELF'])!='/') ? dirname($_SERVER['PHP_SELF']) : ''));

define("HTTP_PATH", 
        (empty($_SERVER['HTTPS'])?"http://":"https://").
        $_SERVER['SERVER_NAME'].
        (($_SERVER['SERVER_PORT']!=80 && $_SERVER['SERVER_PORT']!=443)?':'.$_SERVER['SERVER_PORT']:'').
        LOCAL_PATH
);

require_once("config.php");
require_once("functions.php");

final class COLUMN {
    const MD5       =  0;
    const FILENAME  =  1;
    const SIZE      =  2;
    const MIMETYPE  =  3;
    const CRC32     =  4;
    const DELCODE   =  5;
    const EMAIL     =  6;
    const EXPIRES   =  7;
    const COMMENT   =  8;
    const PASSWORD  =  9;
    const CLICKS    = 10;
    const SYNTAX    = 11;

    const COUNT     = 12;

    public static function COUNT() {
        if(defined("COLUMN::COUNT")) {
            return COLUMN::COUNT;
        } else {
            return count(call_user_func(array(
                new ReflectionaClass(new COLUMN), 
                'getConstants'
            )));
        }
    }

}

if(INDEX_CACHE == 'shm') {
    define("SHM_KEY", ftok(__FILE__, 'y'));
    define("SHM_MINIMAL_SIZE", (floor((filesize(FILEINDEX)*3)/10000)+1)*10000);
    define("SHM_TIMESTAMP", strlen(strval(PHP_INT_MAX)));
} elseif(INDEX_CACHE == 'tmpfs') {
    define("TMPFS_PATH", "tmp/");
    define("TMPFS_FILE", realpath(TMPFS_PATH).'/yafu-index-'.sprintf('%x', crc32(realpath(FILEINDEX))));
    define("TMPFS_TIMESTAMP", strlen(strval(PHP_INT_MAX)));
}

/* WARNING: ONLY MODIFY THE ABOVE CONSTANTS IF YOU KNOW EXACLTY WHAT YOU DO! */

ignore_user_abort(true);

$MESSAGES = array(
    'HTTP_PATH' => HTTP_PATH, 
    'LOCAL_PATH' => LOCAL_PATH, 
    'MYSELF' => $_SERVER['PHP_SELF'],
    'CURRENT_REQUEST' => htmlspecialchars($_SERVER['REQUEST_URI']),
    'IMAGES' => IMAGES, 
    'TITLE' => "Yet Another File Upload"
);

$FilesToUpdate = array(
    array(
        'path' => 'stylesheet.css',
        'pattern' => '/(url\().*(\/.*\))/',
        'replacement' => '${1}'.LOCAL_PATH.'/'.IMAGES.'${2}'
    ),
    array(
        'path' => 'minimal-stylesheet.css',
        'pattern' => '/(url\().*(\/.*\))/',
        'replacement' => '${1}'.LOCAL_PATH.'/'.IMAGES.'${2}'
    ),
    array(
        'path' => '.htaccess',
        'pattern' => '/^(.* ).*(\/index\.php\?.*)\s*$/m',
        'replacement' => '${1}'.LOCAL_PATH.'${2}'
    ),
    array(
        'path' => '.htaccess',
        'pattern' => '/^(.* \^).*(\$ .*\/index\.php\?error=403 \[F\])\s*$/',
        'replacement' => '${1}'.FILEINDEX.'${2}'
    )
);

@updateAdditionalFiles();

switch(getUserAction()) {

    case 'Upload':

        if(isset($_FILES['upload'])) {
            if(($errorMessage = checkUploadedFile($_FILES['upload'])) !== UPLOAD_ERR_OK) {
                printErrorXHTML('Fehler beim Hochladen der Datei!', $errorMessage, false);
                break;
            }

            $currentFile = array(
                COLUMN::FILENAME => stripslashes($_FILES['upload']['name']),
                COLUMN::SIZE => $_FILES['upload']['size'],
                COLUMN::MIMETYPE => getMimeType($_FILES['upload']['tmp_name'], $_FILES['upload']['type']),
                COLUMN::MD5 => md5_file($_FILES['upload']['tmp_name']),
                COLUMN::CRC32 => sprintf('%u', crc32(file_get_contents($_FILES['upload']['tmp_name']))),
                COLUMN::SYNTAX => (isTextFile($_FILES['upload']['tmp_name']) ? 'text' : '')
            );
           
        } else {
            if(empty($_POST['content']) || empty($_POST['filename'])) {
                printErrorXHTML(
                    "Fehlender Inhalt oder Dateiname", 
                    "Es wurde beim Einfügen kein Inhalt und/oder Dateiname angegeben."
                );
                break;
            }

            $currentFile = array(
                COLUMN::FILENAME => stripslashes($_POST['filename']),
                COLUMN::SIZE => strlen($_POST['content']),
                COLUMN::MIMETYPE => 'text/plain; charset=utf-8',
                COLUMN::MD5 => md5($_POST['content']),
                COLUMN::CRC32 => sprintf('%u', crc32($_POST['content'])),
                COLUMN::SYNTAX => (isset($_POST['language']) ? $_POST['language'] : 'text'),
            );
        }

        $currentFile = $currentFile + array(
            COLUMN::COMMENT => (isset($_POST['hidden']) ? HIDDEN_MARKER : '').
                (isset($_POST['comment']) ? stripslashes($_POST['comment']) : ''),
            COLUMN::EXPIRES => getExpirationTimeStamp(isset($_POST['expires']) ? $_POST['expires'] : 'max'),
            COLUMN::EMAIL => (isset($_POST['email']) ? $_POST['email'] : ''),
            COLUMN::DELCODE => generateDeletionCode(),
            COLUMN::PASSWORD => (isset($_POST['sha1_password']) ? $_POST['sha1_password'] :
                ((isset($_POST['password']) && !empty($_POST['password'])) ? sha1($_POST['password']) : '')),
            COLUMN::CLICKS => 0,
        );

        ksort($currentFile);

        if(($foundFile = getFileFromIndexArray(COLUMN::MD5, $currentFile[COLUMN::MD5])) === false) {

            if(isset($_FILES['upload'])) {
                $creatingSuccess = move_uploaded_file($_FILES['upload']['tmp_name'], FILES.$currentFile[COLUMN::MD5]);
            } else {
                $creatingSuccess = (bool) file_put_contents(FILES.$currentFile[COLUMN::MD5], $_POST['content']);
            }

            if(!is_null(BACKUP)) {
                copy(FILEINDEX, BACKUP."backup-".time().".txt");
            }

            if(!$creatingSuccess || !addFileToIndexArray($currentFile)) {
                printXHTML(
                    "Fehler beim Speichern der Datei",
                    "Die Datei konnte nicht auf dem Server gespeichert oder indiziert werden."
                );
                break;
            }

            chmod(FILES.$currentFile[COLUMN::MD5], 0666);


            if(!empty($currentFile[COLUMN::EMAIL])) {
                setcookie("EMail", $currentFile[COLUMN::EMAIL], time() + 16*24*60*60);
            }


            if(($requestDuration = time()-$_SERVER['REQUEST_TIME']) > 3) {
                $speedLast = $currentFile[COLUMN::SIZE] / $requestDuration;
                $speedAverage = (isset($_COOKIE['UploadSpeed']) && is_numeric($_COOKIE['UploadSpeed'])) ?
                                    (($speedLast + $_COOKIE['UploadSpeed']) / 2) : $speedLast;

                setcookie("UploadSpeed", $speedAverage, time() + 16*24*60*60);
            }

            $MESSAGES = array_merge($MESSAGES, getTemplateMessagesForFile($currentFile), array(
                'CONTENT' => "file:FileUploaded.html",
                'DELCODE' => $currentFile[COLUMN::DELCODE],
                'EMAIL' => empty($currentFile[COLUMN::EMAIL]) ? '' : $currentFile[COLUMN::EMAIL],
            ));


            $MESSAGES['HAS_THUMBNAIL'] = $MESSAGES['IS_IMAGE'] = $MESSAGES['IS_TEXT'] = false;

            if(strpos(IMG_MIMETYPES, $currentFile[COLUMN::MIMETYPE]) !== false) {
                $MESSAGES['IS_IMAGE'] = true;

                if(strpos(THUMBNAIL_MIMETYPES, $currentFile[COLUMN::MIMETYPE]) !== false) {
                    if(createThumbNail(FILES.$currentFile[COLUMN::MD5])) {
                        $MESSAGES['HAS_THUMBNAIL'] = true;
                    }
                }
            } elseif(!empty($currentFile[COLUMN::SYNTAX])) {
                $MESSAGES['IS_TEXT'] = true;
            }

            if(strpos($currentFile[COLUMN::EMAIL], '@')) {
                $MESSAGES['EMAIL_TIP'] = "Sie haben die E-Mail-Adresse <i>".$currentFile[COLUMN::EMAIL].
                    "</i> hinterlegt. Sollten Sie den Löschlink für diese Datei verlieren, ".
                    "können Sie ihn per E-Mail zusenden lassen.<br />";
            } else {
                $MESSAGES['EMAIL_TIP'] = "Sie haben keine oder eine ungütige E-Mail-Adresse hinterlegt. ".
                    "Sollten Sie den Löschlink für diese Datei verlieren, können Sie ihn per E-Mail ".
                    "zusenden lassen, wenn Sie eine E-Mail-Adresse hinterlegen.<br />";
            }
            addFooterLink("Weitere Dateien hochladen", LOCAL_PATH.'/');
            addFooterLink("Alle hochgeladenen Dateien auflisten", LOCAL_PATH.'/?action=listfiles');
            printXHTML();

        } else {
            $MESSAGES = array_merge($MESSAGES, getTemplateMessagesForFile($currentFile), array(
                'MISC' => "ErrorFileExists",
            ));
            printErrorXHTML(
                "Datei bereits hochgeladen!",
                "file:Misc.html",
                false
            );
        }
        break;

    case 'getUploadStatus':

        header('Connection: close');
        header('Content-Type: text/plain');
        header("Cache-Control: no-cache, must-revalidate");
        header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

        echo("{\n");
        if(extension_loaded('uploadprogress')) {
            $uploadStatus = uploadprogress_get_info($_GET['uploadid']);

            if(!is_null($uploadStatus)) {
                foreach($uploadStatus as $statusKey => $statusValue) {
                    echo("\t\"$statusKey\" : $statusValue,\n");
                }
                echo("\t\"type\" : \"extension\"\n");
            } else {
                echo("\t\"type\" : \"none\"\n");
            }

        } elseif(isset($_COOKIE['UploadSpeed']) && is_numeric($_COOKIE['UploadSpeed'])) {
                echo("\t\"speed_average\" : ".$_COOKIE['UploadSpeed'].",\n");
                echo("\t\"type\" : \"cookie\"\n");
        } else {
            echo("\t\"has_info\" : \"none\"\n");
        }
        echo("}");
        break;

    case 'showPasteForm':

        $MESSAGES = array_merge($MESSAGES, array(
            'CONTENT' => 'file:Paste.html',
            'EMAIL' => isset($_COOKIE['EMail']) ? $_COOKIE['EMail'] : '',
            'FILENAME' => "Textschnippsel @".gmdate('B').".txt"
        ));

        foreach(getSupportedHightlightings() as $language) {
            $MESSAGES['LANGUAGES'][] = array(
                'value' => $language,
                'name' => htmlspecialchars(getHumanReadableLanguage($language))
            );
        }
        addFooterLink("Komplette Dateien hochladen", LOCAL_PATH.'/');
        printXHTML();

        break;

    case 'showSearchForm':

        $MESSAGES['CONTENT'] = 'file:Search.html';
        addFooterLink("Alle hochgeladenen Dateien auflisten", HTTP_PATH.'/?action=listfiles');
        printXHTML();

        break;

    case 'listFiles': 

        header("Last-Modified: ".gmdate("D, d M Y H:i:s", filemtime(FILEINDEX))." GMT");
        header("Cache-Control: public, max-age=3600");

        $MESSAGES = array_merge($MESSAGES, array(
            'CONTENT' => 'file:FileList.html',
            'ADMIN_MODE' => false
        ));

        $fileArray = getIndexArray();
        $totalSize = 0;

        if(!is_null(ADMIN_PASSWORD)) {
            if(isset($_GET['admin']) && $_GET['admin'] == 'true') {
                if(isset($_POST['password']) || isset($_POST['sha1_password'])) {
                    if(
                        (isset($_POST['sha1_password']) && $_POST['sha1_password'] == ADMIN_PASSWORD) ||
                        (isset($_POST['password']) && sha1($_POST['password']) == ADMIN_PASSWORD)
                    ) {
                        $MESSAGES = array_merge($MESSAGES, array(
                            'SHA1_ADMIN_PASSWORD' => 
                                isset($_POST['sha1_password']) ? $_POST['sha1_password'] : sha1($_POST['password']),
                            'ADMIN_MODE' => true
                        ));
                        if(isset($_POST['remove']) && count($_POST['remove']) > 0) {
                            foreach($_POST['remove'] as $crc32) {
                                if(($fileToRemove = getFileFromIndexArray(COLUMN::CRC32, $crc32)) !== false) {
                                    removeFile($fileToRemove);
                                    $MESSAGES['REMOVED_FILES'][] = array(
                                        'mimetype' => $fileToRemove[COLUMN::MIMETYPE],
                                        'icon' => getMimeTypeIcon($fileToRemove[COLUMN::MIMETYPE]),
                                        'filename' => htmlspecialchars($fileToRemove[COLUMN::FILENAME]),
                                        'size' => getHumanReadableSize($fileToRemove[COLUMN::SIZE])
                                    );
                                }
                            }
                            if(isset($MESSAGES['REMOVED_FILES'])) {
                                $MESSAGES = array_merge($MESSAGES, array(
                                    'CONTENT' => 'file:Misc.html',
                                    'MISC' => 'AdminFilesRemoved'
                                ));
                                addFooterLink("Zurück zur vorherigen Seite", LOCAL_PATH.'/', "history.back(); return false");
                                printXHTML();
                                break;
                            }
                        }
                    } else {
                        printErrorXHTML("Falsches Passwort", "Das eingegbene Administartions-Passwort ist falsch.");
                        break;
                    }
                } else {
                    $MESSAGES = array_merge($MESSAGES, array(
                        'CONTENT' => 'file:Misc.html',
                        'MISC' => 'AdminLogin'
                    ));
                    addFooterLink("Zurück zur vorherigen Seite", LOCAL_PATH.'/', "history.back(); return false");
                    printXHTML();
                    break;
                }
            } else {
                addFooterLink("Administrationsbereich", $_SERVER['REQUEST_URI'].'&amp;admin=true');
            }
        }

        if(isset($_GET['search']) && !empty($_GET['search']) && isset($_GET['search_for'])) {
            switch($_GET['search_for']) {
                case 'filename':
                    $searchFor = COLUMN::FILENAME;
                    break;
                case 'comment':
                    $searchFor = COLUMN::COMMENT;
                    break;
                case 'md5':
                    $searchFor = COLUMN::MD5;
                    break;
                case 'mimetype':
                    $searchFor = COLUMN::MIMETYPE;
                    break;
                case 'all':
                default:
                    $searchFor = array(
                        COLUMN::FILENAME => true, 
                        COLUMN::COMMENT => true, 
                        COLUMN::MD5 => true, 
                        COLUMN::MIMETYPE => true
                    );
                    break;
            }

            foreach($fileArray as &$currentRow) {
                if(
                    (!$MESSAGES['ADMIN_MODE'] && strpos($currentRow[COLUMN::COMMENT], HIDDEN_MARKER) === 0) ||

                    (is_array($searchFor) && (strripos(implode(chr(0), 
                        array_intersect_key($currentRow, $searchFor)), $_GET['search']) === false)) || 

                    (is_int($searchFor) && (strripos($currentRow[$searchFor], $_GET['search']) === false))
                ) {
                    $currentRow =  null;
                }
            }
            unset($currentRow);

            $fileArray = array_filter($fileArray);
            $MESSAGES['SEARCHPARAMS'] = '&amp;search='.urlencode($_GET['search']).'&amp;search_for='.urlencode($_GET['search_for']);

            addFooterLink("Neue Suche starten", HTTP_PATH.'/?action=search');
            addFooterLink("Alle Dateien auflisten", HTTP_PATH.'/?action=listfiles');
        } else {
            addFooterLink("Nach bestimmten Dateien suchen", HTTP_PATH.'/?action=search');
        }

        if(isset($_GET['sortby'])) {
            switch($_GET['sortby']) {
                case 'mimetype':
                    $sortByColumn = COLUMN::MIMETYPE;
                    break;
                case 'filename':
                    $sortByColumn = COLUMN::FILENAME;
                    break;
                case 'password':
                    $sortByColumn = COLUMN::PASSWORD;
                    break;
                case 'size':
                    $sortByColumn = COLUMN::SIZE;
                    break;
                case 'expires':
                    $sortByColumn = COLUMN::EXPIRES;
                    break;
            }
        }

        if(isset($sortByColumn)) {
            $sortedColumn = array();
            foreach($fileArray as $currentRowNr => &$currentRow) {
                if($sortByColumn == COLUMN::PASSWORD) {
                    $sortedColumn[$currentRowNr] = !empty($currentRow[COLUMN::PASSWORD]);
                } elseif($sortByColumn==COLUMN::EXPIRES) {
                    $sortedColumn[$currentRowNr] = abs($currentRow[COLUMN::EXPIRES]);
                } else {
                    $sortedColumn[$currentRowNr] = strtolower($currentRow[$sortByColumn]);
                }
            }
            unset($currentRow, $currentRowNr);

            array_multisort($sortedColumn, SORT_ASC, $fileArray);
        }
      
        foreach ($fileArray as &$currentFile) {
            if(strpos($currentFile[COLUMN::COMMENT], HIDDEN_MARKER) === 0) {
                if($MESSAGES['ADMIN_MODE']) {
                    $currentFile[COLUMN::COMMENT] = 
                        substr($currentFile[COLUMN::COMMENT], strlen(HIDDEN_MARKER))." (versteckte Datei)";
                } else {
                    continue;
                }
            }

            $MESSAGES['FILEARRAY'][] = array(
                'mimetype' => $currentFile[COLUMN::MIMETYPE],
                'icon' => getMimeTypeIcon($currentFile[COLUMN::MIMETYPE]),
                'filename' => htmlspecialchars($currentFile[COLUMN::FILENAME]),
                'comment' => htmlspecialchars($currentFile[COLUMN::COMMENT]),
                'password' => !empty($currentFile[COLUMN::PASSWORD]),
                'size' => getHumanReadableSize($currentFile[COLUMN::SIZE]),
                'real_size' =>$currentFile[COLUMN::SIZE],
                'expires' => getHumanReadableTimePeriod($currentFile[COLUMN::EXPIRES]),
                'crc32' => $currentFile[COLUMN::CRC32],
                'url_filename' => urlencode($currentFile[COLUMN::FILENAME])
            );
            $totalSize += $currentFile[COLUMN::SIZE];
        }

        if(!isset($MESSAGES['FILEARRAY']) || count($MESSAGES['FILEARRAY']) == 0) {
            printErrorXHTML(
                "Keine Dateien gefunden!",
                "Es konnten keine ".(isset($_GET['search'])?"übereinstimmenden":"hochgeladenen")." Dateien gefunden werden.");
            break;
        }

        if(!isset($sortByColumn)) {
            krsort($MESSAGES['FILEARRAY']);
        }

        $MESSAGES = array_merge($MESSAGES, array(
            'SEARCHPARAMS' => isset($MESSAGES['SEARCHPARAMS']) ? $MESSAGES['SEARCHPARAMS'] : '',
            'FILES' => count($MESSAGES['FILEARRAY']),
            'TOTALSIZE' => getHumanReadableSize($totalSize)
        ));

        printXHTML();
        break;

    case 'getFileInfo':

        if(is_array($currentFile = getFileRequest($_GET['info']))) {

                if(($checkPassword = checkPassword($currentFile)) !== true) {
                    $MESSAGES = array_merge($MESSAGES, array(
                        'CONTENT' => 'file:Misc.html',
                        'MISC' => $checkPassword
                    ));
                    addFooterLink("Zurück zur vorherigen Seite", HTTP_PATH.'/', "history.back(); return false");
                    printXHTML();
                    break;
                }

                $MESSAGES = array_merge($MESSAGES, array(
                    'CONTENT' => "file:FileInfo.html",
                    'FILENAME' => wordwrap(htmlspecialchars($currentFile[COLUMN::FILENAME]), 40, "<br />\n", true),
                    'URL_FILENAME' => urlencode($currentFile[COLUMN::FILENAME]),
                    'COMMENT' => htmlspecialchars(
                        (strpos($currentFile[COLUMN::COMMENT], HIDDEN_MARKER) === 0) ?
                            substr($currentFile[COLUMN::COMMENT], strlen(HIDDEN_MARKER)) :
                            $currentFile[COLUMN::COMMENT]),
                    'FILESIZE' => getHumanReadableSize($currentFile[COLUMN::SIZE]),
                    'MIMETYPE' => $currentFile[COLUMN::MIMETYPE],
                    'ICON_MIMETYPE' => getMimeTypeIcon($currentFile[COLUMN::MIMETYPE]),
                    'CRC32' => $currentFile[COLUMN::CRC32],
                    'MD5' => $currentFile[COLUMN::MD5],
                    'CLICKS' => $currentFile[COLUMN::CLICKS],
                    'EXPIRES' => getHumanReadableTimePeriod($currentFile[COLUMN::EXPIRES]),
                    'EXPIRY_DATE' => ($currentFile[COLUMN::EXPIRES]==0) ? "am Sankt Nimmerleins-Tag" :
                                        date('\a\m d.m.Y \u\m H:i \U\h\r', abs($currentFile[COLUMN::EXPIRES])),
                    'IS_TEXT' => !empty($currentFile[COLUMN::SYNTAX]),
                    'HAS_EMAIL' => !empty($currentFile[COLUMN::EMAIL]),
                    'HAS_PASSWORD' => !empty($currentFile[COLUMN::PASSWORD])
                ));
                addFooterLink("Alle hochgeladenen Dateien auflisten", LOCAL_PATH.'/?action=listfiles');
                if(empty($currentFile[COLUMN::EMAIL])) {
                    addFooterLink(
                        "E-Mail-Adresse hinterlegen",
                        LOCAL_PATH.'/mail/'.$currentFile[COLUMN::CRC32].'/'.urlencode($currentFile[COLUMN::FILENAME])
                    );
                }
                printXHTML();

        } else {
            printErrorXHTML("Anfrage fehlgeschlagen!", $currentFile);
        }
        break;

    case 'showTextFile':

        if(is_array($currentFile = getFileRequest($_GET['show']))) {

            if(empty($currentFile[COLUMN::SYNTAX])) {
                printErrorXHTML("Datei kann nicht angezeigt werden", "Es handelt sich nicht um eine anzeigbare Textdatei");
                break;
            } elseif(($checkPassword = checkPassword($currentFile)) !== true) {
                $MESSAGES = array_merge($MESSAGES, array(
                    'CONTENT' => 'file:Misc.html',
                    'TITLE' => "Yet Another File Upload - ".htmlspecialchars($currentFile[COLUMN::FILENAME]).
                                ' ('.getHumanReadableSize($currentFile[COLUMN::SIZE], false).')',
                    'MISC' => $checkPassword,
                    'FILENAME' => htmlspecialchars($currentFile[COLUMN::FILENAME]),
                    'FILESIZE' => getHumanReadableSize($currentFile[COLUMN::SIZE])
                ));
                printMinimalXHTML();
                break;
            }


            
            if(isset($_POST['language'])) {
                $currentLanguage = $_POST['language'];
            } else {

                if(!isset($_SERVER['HTTP_REFERER']) || 
                    strpos($_SERVER['HTTP_REFERER'], HTTP_PATH.'/show/') !== false) {

                    $currentFile[COLUMN::CLICKS]++;

                    if($currentFile[COLUMN::EXPIRES] < 0) {
                        $currentFile[COLUMN::EXPIRES] = -1*(time()+abs(MAX_LIFESPAN));
                    }

                    replaceFileInIndexArray(COLUMN::MD5, $currentFile[COLUMN::MD5], $currentFile);
                }

                if($currentFile[COLUMN::SYNTAX] == 'text') {
                    $currentLanguage = getPreferedHightlighting($currentFile[COLUMN::FILENAME]);
                } else {
                    $currentLanguage = $currentFile[COLUMN::SYNTAX];
                }
            }

            $MESSAGES = array_merge($MESSAGES, getTemplateMessagesForFile($currentFile), array(
                        'CURRENT_LANGUAGE' => $currentLanguage,
                        'TITLE' => "Yet Another File Upload - ".htmlspecialchars($currentFile[COLUMN::FILENAME]).
                                    ' ('.getHumanReadableSize($currentFile[COLUMN::SIZE], false).')',
                        'CONTENT' => getHighlightedSource(
                            file_get_contents(FILES.$currentFile[COLUMN::MD5]), $currentLanguage)
            ));

            if(!in_array($currentLanguage, getSupportedHightlightings())) {
                $MESSAGES['LANGUAGES'][] = array(
                    'value' => $currentLanguage,
                    'name' => "Unbekannt (".htmlspecialchars($currentLanguage).")"
                );
            }

            foreach(getSupportedHightlightings() as $language) {
                $MESSAGES['LANGUAGES'][] = array(
                    'value' => $language,
                    'name' => htmlspecialchars(getHumanReadableLanguage($language))
                );
            }

            printMinimalXHTML();

        } else {
            printErrorXHTML("Anfrage fehlgeschlagen!", $currentFile);
        }
        break;

    case 'getFile':

        if(is_array($currentFile = getFileRequest($_GET['get']))) {

            if(strpos(basename($_SERVER['REQUEST_URI']), '+') !== false) {
                header('Location: '.LOCAL_PATH.
                    '/'.$currentFile[COLUMN::CRC32].'/'.rawurlencode($currentFile[COLUMN::FILENAME]));
                break;

            } elseif(isset($_SERVER['HTTP_USER_AGENT']) && 
                (strpos($_SERVER['HTTP_USER_AGENT'], 'vBSEO') === 13)) {

                echo('<title>'.htmlspecialchars($currentFile[COLUMN::FILENAME]).' ('.
                    getHumanReadableSize($currentFile[COLUMN::SIZE], false).')</title>');

                break;

            } elseif(($checkPassword = checkPassword($currentFile)) !== true) {

                $MESSAGES = array_merge($MESSAGES, array(
                    'CONTENT' => 'file:Misc.html',
                    'MISC' => $checkPassword,
                    'FILENAME' => htmlspecialchars($currentFile[COLUMN::FILENAME]),
                    'FILESIZE' => getHumanReadableSize($currentFile[COLUMN::SIZE])
                ));
                header('HTTP/1.1 401 Unauthorized');
                if(!isset($_SERVER['HTTP_REFERER'])) {
                    header('WWW-Authenticate: Basic realm="Passwort fuer die Datei '.
                        addslashes(utf8_decode($currentFile[COLUMN::FILENAME])).'"');
                }
                printMinimalXHTML();
                break;
            }

            if(FORCE_DOWNLOAD) {
                header('Content-Disposition: attachment; filename="'.$currentFile[COLUMN::FILENAME].'"');
            }
            header('Content-Transfer-Encoding: binary');
            header('Accept-Ranges: bytes');
            header("Cache-Control: public, max-age=".abs(MAX_LIFESPAN));
            header("Expires: ".gmdate("D, d M Y H:i:s", ($currentFile[COLUMN::EXPIRES] != 0) ? 
                abs($currentFile[COLUMN::EXPIRES]) : PHP_INT_MAX)." GMT");
            header("Last-Modified: ".gmdate("D, d M Y H:i:s", filemtime(FILES.$currentFile[COLUMN::MD5]))." GMT");


            if(isset($_SERVER['HTTP_RANGE'])) {
                list($unit, $range) = explode('=', $_SERVER['HTTP_RANGE']);
                $startRange = strtok($range, '-/');
                if(empty($startRange)) {
                    header("HTTP/1.1 416 Requested Range not satisfiable");
                    break;
                }
                if(!ctype_digit($endRange = strtok('-/'))) {
                    $endRange = $currentFile[COLUMN::SIZE]-1;
                }
                header("HTTP/1.1 206 Partial Content");

                header("Content-Length: ".($endRange+1 - $startRange));
                header("Content-Range: bytes ".$startRange."-".$endRange."/".$currentFile[COLUMN::SIZE]);
                header('Content-Type: '.$currentFile[COLUMN::MIMETYPE]);

                $fileHandler = fopen(FILES.$currentFile[COLUMN::MD5], 'rb');
                fseek($fileHandler, $startRange);
                echo(fread($fileHandler, $endRange+1 - $startRange));
                fclose($fileHandler);
            } else {
                header('Content-Length: '.$currentFile[COLUMN::SIZE]);
                header('Content-Type: '.$currentFile[COLUMN::MIMETYPE]);
                readfile(FILES.$currentFile[COLUMN::MD5]);
            }

            @flush();

            if(!isset($_SERVER['HTTP_REFERER']) || 
                strpos($_SERVER['HTTP_REFERER'], HTTP_PATH.'/show/') === false) {

                $currentFile[COLUMN::CLICKS]++;

                if($currentFile[COLUMN::EXPIRES] < 0) {
                    $currentFile[COLUMN::EXPIRES] = -1*(time()+abs(MAX_LIFESPAN));
                }

                replaceFileInIndexArray(COLUMN::MD5, $currentFile[COLUMN::MD5], $currentFile);
            }
        } else {
            header("HTTP/1.1 404 Not Found");
            printErrorXHTML("Anfrage fehlgeschlagen!", $currentFile);
        }
        break;

    case 'getThumbnail': 

        if(is_array($currentFile = getFileRequest($_GET['thumbnail']))) {

            if(($checkPassword = checkPassword($currentFile)) !== true) {
                $MESSAGES = array_merge($MESSAGES, array(
                    'CONTENT' => 'file:Misc.html',
                    'MISC' => $checkPassword,
                    'FILENAME' => htmlspecialchars($currentFile[COLUMN::FILENAME]),
                    'FILESIZE' => getHumanReadableSize($currentFile[COLUMN::SIZE])
                ));
                header('HTTP/1.1 401 Unauthorized');
                if(!isset($_SERVER['HTTP_REFERER'])) {
                    header('WWW-Authenticate: Basic realm="Passwort für das Vorschaubild von '.
                        addslashes(utf8_decode($currentFile[COLUMN::FILENAME])).'"');
                    }
                printMinimalXHTML();
                break;
            } elseif(file_exists(THUMBNAILS.$currentFile[COLUMN::MD5])) {
                header("Cache-Control: public, max-age=".abs(MAX_LIFESPAN));
                header("Expires: ".gmdate("D, d M Y H:i:s", ($currentFile[COLUMN::EXPIRES] != 0) ? 
                    abs($currentFile[COLUMN::EXPIRES]) : PHP_INT_MAX)." GMT");
                header("Last-Modified: ".gmdate("D, d M Y H:i:s", filemtime(FILES.$currentFile[COLUMN::MD5]))." GMT");
                header('Content-Type: '.$currentFile[COLUMN::MIMETYPE]);
                header('Content-Transfer-Encoding: binary');
                header('Content-Length: '.filesize(THUMBNAILS.$currentFile[COLUMN::MD5]));
                readfile(THUMBNAILS.$currentFile[COLUMN::MD5]);
            } else {
                header("HTTP/1.0 404 Not Found");
                printErrorXHTML(
                    "Kein Vorschaubild gefunden!",
                    "Es existiert kein Vorschaubild für diese Datei. Entweder handelt es sich nicht ".
                    "um eine Bildatei oder das Bild ist zu klein für eine Vorschau."
                );
            }

        } else {
            header("HTTP/1.0 404 Not Found");
            printErrorXHTML("Anfrage fehlgeschlagen!", $currentFile);
        }
        break;

    case 'showInfo':

        $MESSAGES = array_merge($MESSAGES, array(
            'CONTENT' => 'file:About.html',
            'HAS_SOURCE' => file_exists("yafu-source.zip")
        ));

        if(file_exists("yafu-source.zip")) {
            $MESSAGES = array_merge($MESSAGES, array(
                'SOURCE_FILESIZE' => getHumanReadableSize(filesize("yafu-source.zip")),
                'SOURCE_ICON' => getMimeTypeIcon('application/zip'),
                'SOURCE_REVISION' => idate("y", filemtime("yafu-source.zip")).
                                     sprintf('%\'03d', idate("z", filemtime("yafu-source.zip"))).
                                     date('.B', filemtime("yafu-source.zip"))
            ));
        }

        addFooterLink("Zurück zur Startseite", LOCAL_PATH.'/');
        printXHTML();

        break;

    case 'storeEMailAddress':

        if(empty($_POST['delcode'])) {
            printErrorXHTML(
                "Kein Löschcode angegeben!", 
                "Sie haben keinen Löschcode angegeben. ".
                "Ohne Löschcode können Sie Ihre E-Mail-Adresse nicht hinterlegen."
            );
            break;
        } elseif(!strpos($_POST['store_email'], '@')) {
            printErrorXHTML(
                "Ungültige E-Mail-Adresse angegeben!", 
                "Die angegebene E-Mail-Adresse ist ungültig."
            );
            break;
        } elseif(($currentFile = getFileFromIndexArray(COLUMN::DELCODE, basename($_POST['delcode']))) == false) {
            printErrorXHTML(
                "Ungültiger Löschcode angegeben!", 
                "Es existiert keine Datei zum angegeben Löschcode.".
                "Ohne Löschcode können Sie Ihre E-Mail-Adresse nicht hinterlegen."
            );
            break;
        }

        setcookie("EMail", $_POST['store_email'], time() + 16*24*60*60);

        $currentFile[COLUMN::EMAIL] = $_POST['store_email'];

        replaceFileInIndexArray(COLUMN::DELCODE, $currentFile[COLUMN::DELCODE], $currentFile);

        $MESSAGES = array_merge($MESSAGES, getTemplateMessagesForFile($currentFile), array(
            'CONTENT' => "file:Misc.html",
            'MISC' => 'EMailStored',
            'EMAIL' => htmlspecialchars($currentFile[COLUMN::EMAIL]),
        ));
        addFooterLink("Zurück zur vorherigen Seite", LOCAL_PATH.'/', "history.back(); return false");
        printXHTML();

        break;

    case 'sendInfoMail':

        if(is_array($currentFile = getFileRequest($_GET['mail']))) {

            $MESSAGES = array_merge($MESSAGES, getTemplateMessagesForFile($currentFile), array(
                'CONTENT' => "file:Misc.html",
            ));

            if(empty($currentFile[COLUMN::EMAIL]) || !strpos($currentFile[COLUMN::EMAIL], '@')) {
                $MESSAGES['MISC'] = 'StoreEMail';
            } elseif(isset($_POST['confirm'])) {
                $MESSAGES['MISC'] = 'EMailSent';
                $MESSAGES['DELCODE'] = $currentFile[COLUMN::DELCODE];

                $mailHeaders = "From: Yet Another File Upload <gandro@gmx.net>\n".
                                "MIME-Version: 1.0\n".
                                "Content-Type: text/plain; charset=utf8\n".
                                "Content-Transfer-Encoding: 8bit\n".
                                "X-Mailer: PHP (Yet Another File Upload)";

                $mailSubject = mb_encode_mimeheader(utf8_decode(
                    "Ihr Lösch".(!empty($currentFile[COLUMN::PASSWORD]) ? "- und Passwortlink " : "link ").
                    "für die Datei »".$currentFile[COLUMN::FILENAME]."«"), 
                    "ISO-8859-1", 'Q', ''
                );

                $mailText = wordwrap(generateXHTMLCode($MESSAGES, 'MailText.txt'), 78);

                if(!mail($currentFile[COLUMN::EMAIL], $mailSubject, $mailText, $mailHeaders)) {
                    printErrorXHTML("Senden fehlgeschlagen!", "Beim Senden der E-Mail trat ein Fehler auf.");
                    break;
                }
            } else {
                $MESSAGES['MISC'] = 'SendEMail';
            }

            addFooterLink("Zurück zur vorherigen Seite", LOCAL_PATH.'/', "history.back(); return false");
            printXHTML();
        } else {
            printErrorXHTML("Anfrage fehlgeschlagen!", $currentFile);
        }
        
        break;

    case 'changePassword':

        $MESSAGES = array_merge($MESSAGES, array(
                'CONTENT' => "file:Misc.html",
                'MISC' => 'ChangePassword'
        ));

        if(isset($_REQUEST['code']) && !empty($_REQUEST['code'])) {

            if(($currentFile = getFileFromIndexArray(COLUMN::DELCODE, basename($_REQUEST['code']))) === false) {
                printErrorXHTML("Anfrage fehlgeschlagen!", "Es existiert keine Datei zu dem angegeben Löschcode.");
                break;
            }

            $MESSAGES = array_merge($MESSAGES, getTemplateMessagesForFile($currentFile));

            if(isset($_POST['password']) || isset($_POST['sha1_password'])) {
                $MESSAGES['MISC'] = 'PasswordChanged';
                $MESSAGES['DELCODE'] = $currentFile[COLUMN::DELCODE];

                if(isset($_POST['sha1_password'])) {
                    $currentFile[COLUMN::PASSWORD] = $_POST['sha1_password'];
                } else {

                    $currentFile[COLUMN::PASSWORD] = !empty($_POST['password']) ? sha1($_POST['password']) : '';
                }
                replaceFileInIndexArray(COLUMN::DELCODE, $_REQUEST['code'], $currentFile);
            }
        }
        addFooterLink("Zurück zur Startseite", LOCAL_PATH.'/');
        printXHTML();
        break;

    case 'deleteFile':

        if(($currentFile = getFileFromIndexArray(COLUMN::DELCODE, $_GET['delete'])) !== false) {
            $MESSAGES = array_merge($MESSAGES, getTemplateMessagesForFile($currentFile), array(
                'CONTENT' => "file:Misc.html",
                'DELCODE' => $currentFile[COLUMN::DELCODE]
            ));

            if(isset($_POST['confirm'])) {
                removeFile($currentFile);
                $MESSAGES['MISC'] = 'FileDeleted';
            } else {
                $MESSAGES['MISC'] = 'DeleteFile';
            }
            addFooterLink("Zurück zur Startseite", LOCAL_PATH.'/');
            printXHTML();
        } else {
            printErrorXHTML(
                "Falscher oder abgelaufener Löschcode!",
                "Es konnte keine zu löschende Datei für den angegebenen Löschcode gefunden werden. ".
                    "Entweder ist der Löschcode ungültig oder die Datei wurde bereits gelöscht."
            );
        }

        break;

    case 'fixPNG':

        header('Content-type: text/x-component');
        echo(generateXHTMLCode($MESSAGES, 'FixPNG.htc'));
        break;

    case 'Error403':

        header("HTTP/1.1 403 Forbidden");
        printErrorXHTML("Fehler 403: Zugriff verweigert!", "Sie haben keinen Zugriff auf die angeforderte Datei.");
        break;

    case 'Error404':

        header("HTTP/1.0 404 Not Found");
        printErrorXHTML("Fehler 404: Datei nicht gefunden!", "Die angeforderte Datei konnte nicht gefunden werden.");
        break;

    default:

        $MESSAGES = array_merge($MESSAGES, array(
            'CONTENT' => 'file:Upload.html',

            'EMAIL' => isset($_COOKIE['EMail']) ? $_COOKIE['EMail'] : '',
            'JS_MAXSIZE' => MAX_FILESIZE,
            'HTML_MAXSIZE' => MAX_FILESIZE/(1000*1000).'MB',
            'UPLOAD_ID' => uniqid()
        ));

        addFooterLink("Mehr Informationen", LOCAL_PATH."/?action=info");
        addFooterLink("Fenster schliessen", "#", "javascript:window.close()");
        printXHTML();

        break;
}
@removeOldFiles();
if(INDEX_CACHE != 'none') { @syncIndexArray(); }
?>
