function $(element) {
    return document.getElementById(element);
}

function toggleSettings() {
    var step = 5;

    if($("Settings").style.display == 'none') {
        $("Settings").style.height = '0px';
        $("Settings").style.display = '';

        $("MoreSettings").innerHTML = 
            $("MoreSettings").innerHTML.replace(/Mehr/, 'Weniger');

        if($("UploadForm")) {
            $("UploadForm").style.paddingTop = ($("UploadForm")['maxPadding']).toString()+'px';
        } else if($("PasteField")) {
            $("PasteField").style.height = ($("PasteField")['maxHeight']).toString()+'px';
        }
    } else {
        step *= -1;
        $("Settings").style.height = ($("Settings")['maxHeight']).toString()+'px';

        $("MoreSettings").innerHTML = 
            $("MoreSettings").innerHTML.replace(/Weniger/, 'Mehr');

        if($("UploadForm")) {
            $("UploadForm").style.paddingTop = ($("UploadForm")['minPadding']).toString()+'px';
        } else if($("PasteField")) {
            $("PasteField").style.height = 
                ($("PasteField")['maxHeight']-$("Settings")['maxHeight']).toString()+'px';
        }
    }

    SettingsInterval = setInterval(function () {
        if(
            (step > 0 && parseInt($("Settings").style.height) < $("Settings")['maxHeight']) ||
            (step < 0 && parseInt($("Settings").style.height) > 0)
        ) {
            $("Settings").style.height = (parseInt($("Settings").style.height) + step).toString() + 'px';
        } else {
            if(step < 0) {
                $("Settings").style.display = 'none';
            }
            clearInterval(SettingsInterval);
        }
    }, 25);

    if($("UploadForm")) {
        UploadFormInterval = setInterval(function () {
            if(
                (step > 0 && parseInt($("UploadForm").style.paddingTop) > $("UploadForm")['minPadding']) ||
                (step < 0 && parseInt($("UploadForm").style.paddingTop) < $("UploadForm")['maxPadding'])
            ) {
                $("UploadForm").style.paddingTop = (parseInt($("UploadForm").style.paddingTop) - step).toString() + 'px';
            } else {
                clearInterval(UploadFormInterval);
            }
        }, 25);
    } else if($("PasteField")) {
        PasteFieldInterval = setInterval(function () {
            if(
                (step > 0 && parseInt($("PasteField").style.height) > $("PasteField")['maxHeight']-$("Settings")['maxHeight']) ||
                (step < 0 && parseInt($("PasteField").style.height) < $("PasteField")['maxHeight'])
            ) {
                $("PasteField").style.height = (parseInt($("PasteField").style.height) - step).toString() + 'px';
            } else {
                clearInterval(PasteFieldInterval);
            }
        }, 25);
    }

}

function toggleEMailField() {
    var newNode = null;
    var oldNode = $("EMailField");

    if(oldNode.nodeName == 'INPUT') {

        newNode = document.createElement('b');
        newNode.appendChild(document.createTextNode(
            (oldNode.value == '') ? "<hier klicken>" : oldNode.value));
        newNode.style.cursor = 'pointer';
        newNode.id = "EMailField";

        newNode.onclick = toggleEMailField;

        var newInputNode = document.createElement('input');
        newInputNode.type = 'hidden';
        newInputNode.name = 'email';
        newInputNode.id = 'HiddenEMailField';
        newInputNode.value = oldNode.value;

        newNode.appendChild(newInputNode);
        
        oldNode.parentNode.replaceChild(newNode, oldNode);

        newInputNode.form.onsubmit = showUploadProgress;

    } else {

        newNode = document.createElement('input');
        newNode.type = 'text';
        newNode.name = 'email';
        newNode.id = "EMailField";
        newNode.value = $("HiddenEMailField").value;
        newNode.className = 'TextField';
        newNode.size = '14';

        newNode.onblur = toggleEMailField;

        oldNode.parentNode.replaceChild(newNode, oldNode);
        newNode.focus();

        newNode.form.onsubmit = function () { 
            if($("EMailField").nodeName == 'INPUT') { 
                $("EMailField").onblur = null;
                toggleEMailField();
                return false;
            } else { 
                return true;
            } 
        }
    }   
}

function togglePasswordField() {
    var newInputNode = document.createElement('input');
    newInputNode.type = ($("PasswordField").type=='text') ? 'password' : 'text';

    var propertiesToCopy = new Array('size', 'value', 'name', 'id', 'className');

    for(var i=0; i<propertiesToCopy.length; i++){
        if($("PasswordField")[propertiesToCopy[i]]) {
            newInputNode[propertiesToCopy[i]] = $("PasswordField")[propertiesToCopy[i]];
        }
    }
    
    $("PasswordField").parentNode.replaceChild(newInputNode, $("PasswordField"));
}

function encryptPassword() {
    if($("PasswordField").value != '') {
        var encryptedPasswordNode = document.createElement('input');

        encryptedPasswordNode.type = 'hidden';
        encryptedPasswordNode.name = 'sha1_password';
        encryptedPasswordNode.value = SHA1($("PasswordField").value);

        $("PasswordField").form.appendChild(encryptedPasswordNode, $("PasswordField"));

        $("PasswordField").value = '';
    }
}

var totalFiles = 0;
var totalSize = 0;

function sumFilesToDelete(sourceInput) {

    var currentFilesize = 
        parseInt(document.getElementsByName('FilesizeOf'+sourceInput.value)[0].value);

    if(sourceInput.checked) {
        totalFiles++;
        totalSize += currentFilesize;
    } else {
        totalFiles--;
        totalSize -= currentFilesize;
    }

    if(totalFiles > 0) {
        $("SelectedFiles").innerHTML = totalFiles+" Dateien markiert ("+
            ((totalSize>1000*1000)?
                (totalSize/(1000*1000)).toFixed(2).toString()+' MB'
            :
                (totalSize/1000).toFixed(2).toString()+' KB')
        +")";
    } else {
        totalFiles = 0;
        totalSize = 0;
        sourceInput.form.reset();
        $("SelectedFiles").innerHTML = "Keine Dateien markiert";
    }
}

function showUploadProgress() {
    this.createRequestObject = function () {
        if(typeof XMLHttpRequest != 'undefined') {
            return new XMLHttpRequest();
        }

        try {
            return new ActiveXObject("Msxml2.XMLHTTP");
        } catch(e) {
            try {
                return new ActiveXObject("Microsoft.XMLHTTP");
            } catch(e) {
                return null;
            }
        }
     
    }
    
    this.sendRequest = function() {

        if(xmlHttp.readyState != 0 && xmlHttp.readyState != 4) {
            if((new Date()).getTime() - lastUpdated > 1000) {
                xmlHttp.abort();
            } else {
                this.timer = setTimeout(function() { self.sendRequest(); }, 100);
                return false;
            }
        }
        xmlHttp.open('GET', myself+'?uploadid='+uploadid, true);
        xmlHttp.onreadystatechange = function() { self.handleRequest(); };
        xmlHttp.send(null);
    }
    
    this.handleRequest = function() {
        if(xmlHttp.readyState == 4) {
            try {
                if(xmlHttp.status == 200) {
                    UploadInfo = eval('('+xmlHttp.responseText+')');
                    self.printUploadInfo();

                    if(UploadInfo['type'] != 'cookie') {
                        this.timer = setTimeout(function() { self.sendRequest(); }, 500);
                    }

                    lastUpdated = (new Date()).getTime();
                } else {
                    alert("Fehler beim Abholen der Fortschrittsdaten!\n"+xmlHttp.statusText);
                }
            } catch(e) {
                this.timer = setTimeout(function() { self.sendRequest(); }, 100);
            }
        }
    }

    this.printUploadInfo = function () {
        if(!UploadInfo || UploadInfo['type'] == 'none') {
            return false;
        } else if(UploadInfo['type'] == 'cookie') {

            if(typeof UploadInfo['bytes_uploaded'] == 'undefined') {
                UploadInfo['bytes_uploaded'] = 0;
            }

            this.interval = setInterval(function() {
                UploadInfo['bytes_uploaded'] += + UploadInfo['speed_average'];
                $('UploadInfoText').innerHTML = "Bisher hochgeladene Daten: ~"+
                            Math.floor(UploadInfo['bytes_uploaded']/1000)+" KBytes";
            }, 1000);

        } else if(UploadInfo['type'] == 'extension') {
            if(UploadInfo["bytes_total"] > maxsize && typeof aborted == 'undefined') {
                aborted = !confirm("Die Datei, die Sie hochladen möchten, ist zu gross!"+
                            "\nWollen Sie fortfahren?\n\n"+
                            "Klicken Sie auf 'Abbrechen' um den Vorgang abzubrechen.");

                if(aborted) {
                    location.replace(myself);
                }
            }

            if(UploadInfo["bytes_total"] > 1000*1000) {
                var uploaded = (UploadInfo["bytes_uploaded"]/(1000*1000)).toFixed(2)+" MB";
                var total = (UploadInfo["bytes_total"]/(1000*1000)).toFixed(2)+" MB";
            } else {
                var uploaded = Math.round(UploadInfo["bytes_uploaded"]/1000)+" KB";
                var total = Math.round(UploadInfo["bytes_total"]/1000)+" KB";
            }

            est_min = Math.floor(UploadInfo["est_sec"]/60);
            est_sec = UploadInfo["est_sec"] - est_min * 60;

            if(est_sec.toString().length < 2) { 
                est_sec = '0'+est_sec.toString(); 
            }

            speed = (UploadInfo["speed_last"]/1000).toFixed(1);

            $('UploadInfoText').innerHTML = uploaded+" von "+total+" komplett <br />"+
                speed+" KB/s ("+est_min+":"+est_sec+" verbleibend)";

            if(isNaN(parseInt($('ProgressBar').style.backgroundPosition))) {
                $('ProgressBar').style.backgroundPosition = '-'+$('ProgressBar').width+'px 0px'
            }

            var wantedProgressBarPosition = (Math.round($('ProgressBar').width * 
                (UploadInfo["bytes_uploaded"]/UploadInfo["bytes_total"])) - $('ProgressBar').width);

            this.interval = setInterval(function () {
                var currentProgressBarPosition = parseInt($('ProgressBar').style.backgroundPosition);
                if(currentProgressBarPosition >= wantedProgressBarPosition) {
                    clearInterval(this.interval);
                } else {
                    currentProgressBarPosition++;
                    $('ProgressBar').style.backgroundPosition = currentProgressBarPosition.toString()+'px 0px';
                }
            }, 25);
        }
    }
        
    var self = this;
    var lastUpdated = 0;

    var UploadInfo = null;
    var xmlHttp = this.createRequestObject();

    encryptPassword();

    $('UploadButton').style.display = 'none';

    if($('Settings').style.display != 'none') {
        toggleSettings();
    }

    $('UploadInfo').style.display = 'block';

    this.sendRequest();
}

/* Secure Hash Algorithm (SHA1) *
 * http://www.webtoolkit.info/  */

function SHA1(msg) {

    function rotate_left(n,s) {
        var t4 = ( n<<s ) | (n>>>(32-s));
        return t4;
    };

    function lsb_hex(val) {
        var str="";
        var i;
        var vh;
        var vl;

        for( i=0; i<=6; i+=2 ) {
            vh = (val>>>(i*4+4))&0x0f;
            vl = (val>>>(i*4))&0x0f;
            str += vh.toString(16) + vl.toString(16);
        }
        return str;
    };

    function cvt_hex(val) {
        var str="";
        var i;
        var v;

        for( i=7; i>=0; i-- ) {
            v = (val>>>(i*4))&0x0f;
            str += v.toString(16);
        }
        return str;
    };

    function Utf8Encode(string) {
        string = string.replace(/\r\n/g,"\n");
        var utftext = "";

        for (var n = 0; n < string.length; n++) {

            var c = string.charCodeAt(n);

            if (c < 128) {
                utftext += String.fromCharCode(c);
            }
            else if((c > 127) && (c < 2048)) {
                utftext += String.fromCharCode((c >> 6) | 192);
                utftext += String.fromCharCode((c & 63) | 128);
            }
            else {
                utftext += String.fromCharCode((c >> 12) | 224);
                utftext += String.fromCharCode(((c >> 6) & 63) | 128);
                utftext += String.fromCharCode((c & 63) | 128);
            }

        }

        return utftext;
    };

    var blockstart;
    var i, j;
    var W = new Array(80);
    var H0 = 0x67452301;
    var H1 = 0xEFCDAB89;
    var H2 = 0x98BADCFE;
    var H3 = 0x10325476;
    var H4 = 0xC3D2E1F0;
    var A, B, C, D, E;
    var temp;

    msg = Utf8Encode(msg);

    var msg_len = msg.length;

    var word_array = new Array();
    for( i=0; i<msg_len-3; i+=4 ) {
        j = msg.charCodeAt(i)<<24 | msg.charCodeAt(i+1)<<16 |
        msg.charCodeAt(i+2)<<8 | msg.charCodeAt(i+3);
        word_array.push( j );
    }

    switch( msg_len % 4 ) {
        case 0:
            i = 0x080000000;
        break;
        case 1:
            i = msg.charCodeAt(msg_len-1)<<24 | 0x0800000;
        break;

        case 2:
            i = msg.charCodeAt(msg_len-2)<<24 | msg.charCodeAt(msg_len-1)<<16 | 0x08000;
        break;

        case 3:
            i = msg.charCodeAt(msg_len-3)<<24 | msg.charCodeAt(msg_len-2)<<16 | msg.charCodeAt(msg_len-1)<<8    | 0x80;
        break;
    }

    word_array.push( i );

    while( (word_array.length % 16) != 14 ) word_array.push( 0 );

    word_array.push( msg_len>>>29 );
    word_array.push( (msg_len<<3)&0x0ffffffff );

    for ( blockstart=0; blockstart<word_array.length; blockstart+=16 ) {

        for( i=0; i<16; i++ ) W[i] = word_array[blockstart+i];
        for( i=16; i<=79; i++ ) W[i] = rotate_left(W[i-3] ^ W[i-8] ^ W[i-14] ^ W[i-16], 1);

        A = H0;
        B = H1;
        C = H2;
        D = H3;
        E = H4;

        for( i= 0; i<=19; i++ ) {
            temp = (rotate_left(A,5) + ((B&C) | (~B&D)) + E + W[i] + 0x5A827999) & 0x0ffffffff;
            E = D;
            D = C;
            C = rotate_left(B,30);
            B = A;
            A = temp;
        }

        for( i=20; i<=39; i++ ) {
            temp = (rotate_left(A,5) + (B ^ C ^ D) + E + W[i] + 0x6ED9EBA1) & 0x0ffffffff;
            E = D;
            D = C;
            C = rotate_left(B,30);
            B = A;
            A = temp;
        }

        for( i=40; i<=59; i++ ) {
            temp = (rotate_left(A,5) + ((B&C) | (B&D) | (C&D)) + E + W[i] + 0x8F1BBCDC) & 0x0ffffffff;
            E = D;
            D = C;
            C = rotate_left(B,30);
            B = A;
            A = temp;
        }

        for( i=60; i<=79; i++ ) {
            temp = (rotate_left(A,5) + (B ^ C ^ D) + E + W[i] + 0xCA62C1D6) & 0x0ffffffff;
            E = D;
            D = C;
            C = rotate_left(B,30);
            B = A;
            A = temp;
        }

        H0 = (H0 + A) & 0x0ffffffff;
        H1 = (H1 + B) & 0x0ffffffff;
        H2 = (H2 + C) & 0x0ffffffff;
        H3 = (H3 + D) & 0x0ffffffff;
        H4 = (H4 + E) & 0x0ffffffff;

    }

    var temp = cvt_hex(H0) + cvt_hex(H1) + cvt_hex(H2) + cvt_hex(H3) + cvt_hex(H4);

    return temp.toLowerCase();
}
