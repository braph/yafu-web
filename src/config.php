<?php
/* THIS FILE CANNOT BE THE MAIN FILE - PLEASE DO NOT EDIT THE FOLLOWING COMMANDS */

    list($mainFile) = get_included_files();
    if($mainFile == __FILE__) {
        header("Location: index.php?error=403");
        die("403 Forbidden");
    }

/* WARNING: ONLY MODIFY THE FOLLOWING CONSTANTS IF YOU KNOW EXACLTY WHAT YOU DO! */

    /* must exist with permission to write*/
    define("FILEINDEX", "files.csv");

    /* admin password as sha1 sum - 'null' to deactivate */
    define("ADMIN_PASSWORD", "a1fe9409c1f6e13f86c4df89183a9d40792856fa");

    define("FILES", "files/");
    define("IMAGES", "images");
    define("TEMPLATES", "html/");
    define("THUMBNAILS", FILES."thumbnails/");
    define("MIMETYPE_ICONS", LOCAL_PATH.'/'.IMAGES."/mimetype/");

    /* integer in bytes */
    define("MAX_FILESIZE", 500*1000*1000); 

    /* integer in seconds | 0 = forever | negative = seconds after inactivity */
    define("MAX_LIFESPAN", -16*24*60*60);

    /* backup directory - 'null' to disable autobackup */
    define("BACKUP", "backup/");

    /* 'null' to search for default */
    define("MAGIC_FILE", "/usr/share/misc/file/magic");

    /* file index cache: "none" | "shm" (use shmop_* functions) | "tmpfs" (use ramdisk) */
    define("INDEX_CACHE", 'none');

    define("IMG_MIMETYPES", 'image/gif|image/jpeg|image/png|image/tiff|image/bmp');
    define("THUMBNAIL_MIMETYPES", 'image/gif|image/jpeg|image/png');

    define("THUMBNAIL_WIDTH", 160);
    define("THUMBNAIL_HEIGHT", 140);

    define("FORCE_DOWNLOAD", false);

    define("GESHI_PATH", "./geshi/");
    define("GESHI_LANGUAGES", GESHI_PATH."languages/");
    define("GESHI_SCRIPT", GESHI_PATH."geshi.php");

    define("HIDDEN_MARKER", "\x01");

/* WARNING: ONLY MODIFY THE ABOVE CONSTANTS IF YOU KNOW EXACLTY WHAT YOU DO! */
?>
