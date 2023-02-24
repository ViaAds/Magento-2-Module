try {
    setDefaultCookie();

    console.log("test");

    let cookieAccept = getCookie("via_ads");
    if (cookieAccept != undefined) {
        //Check redirected from email
        checkEmailGuid();
    }

    function setDefaultCookie() {
        let cookieAccept = getCookie("via_ads");
        if (cookieAccept == undefined) {
            let email = "";
            let eg = "";

            let oldCookie = getCookie("ViaAds");
            if (oldCookie != undefined) {
                let cookieValues = oldCookie.split("//");
                email = cookieValues[1] != undefined ? cookieValues[1] : "";
                eg = cookieValues[2] != undefined ? cookieValues[2] : "";
                document.cookie = 'ViaAds=; Max-Age=0; Path=/;';
            }

            //Basic cookie
            let cookie = {
                Consent: false,
                Session: uuidv4(),
                Email: email.toLowerCase(),
                EG: eg,
                FP: "",
                FPU: ""
            }

            fingerPrint(cookie);
        } else {
            let cookie = JSON.parse(atob(cookieAccept));
            //Check time for last fingerPrint update
            const date1Timestamp = cookie.FPU;
            const date2Timestamp = new Date().getTime() / 1000;
            const difference = date2Timestamp - date1Timestamp;
            const differenceInDays = Math.abs(difference / (60 * 60 * 24));
            if (differenceInDays >= 3) {
                fingerPrint(cookie);
            }
        }
    }

    function getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
    }

    function uuidv4() {
        return ([1e7] + -1e3 + -4e3 + -8e3 + -1e11).replace(/[018]/g, c =>
            (c ^ crypto.getRandomValues(new Uint8Array(1))[0] & 15 >> c / 4).toString(16)
        );
    }

    function fingerPrint(cookie) {
        let userAgent = navigator.userAgent;
        let platForm = window.navigator.platform;
        let cookieEnabled = window.navigator.cookieEnabled;
        let timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
        let language = navigator.language;
        let fonts = "";
        /*for (const font of document.fonts) {
            fonts += font.family;
        }*/
        let doNotTrack = window.navigator.doNotTrack;
        let vendor = window.navigator.vendor;
        let hardwareConcurrency = window.navigator.hardwareConcurrency;

        //Audio
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        let audio = `${audioContext.destination.channelCount}|${audioContext.destination.channelCountMode}|${audioContext.destination.channelInterpretation}|`;
        audio += `${audioContext.destination.maxChannelCount}|${audioContext.destination.numberOfInputs}|${audioContext.destination.numberOfOutputs}|`;
        audio += `${audioContext.sampleRate}|${audioContext.state}`;

        //Canvas
        let canvas = canvasFingerPrint();

        hashFingerPrint(`${userAgent}${platForm}${cookieEnabled}${timeZone}${language}${fonts}${doNotTrack}${vendor}${hardwareConcurrency}${audio}${canvas}`).then(r => {
            cookie.FP = r;
            cookie.FPU = new Date().getTime() / 1000;
            document.cookie = "via_ads=" + btoa(JSON.stringify(cookie)) + "; max-age=34560000; path=/;";
            //Check redirected from email
            checkEmailGuid();
        });
    }

    async function hashFingerPrint(message) {
        const msgUint8 = new TextEncoder().encode(message);
        const hashBuffer = await crypto.subtle.digest('SHA-256', msgUint8);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        const hashHex = hashArray.map((b) => b.toString(16).padStart(2, '0')).join('');
        return hashHex;
    }

    function canvasFingerPrint() {
        const canvas = document.createElement("canvas");
        const ctx = canvas.getContext("2d");
        canvas.width = 200;
        canvas.height = 50;
        ctx.textBaseline = "alphabetic";
        ctx.fillStyle = "#f60";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = "#069";
        ctx.font = "16px Arial";
        ctx.fillText(navigator.userAgent, 2, 15);
        ctx.fillStyle = "rgba(102, 204, 0, 0.7)";
        ctx.fillText(navigator.userAgent, 4, 17);
        return canvas.toDataURL();
    }

    function checkEmailGuid() {
        if (window.location.search.includes("emailGuid=")) {
            let cookieAccept = getCookie("via_ads");
            let cookie = JSON.parse(atob(cookieAccept));
            cookie.EG = window.location.search.split("emailGuid=")[1];
            document.cookie = "via_ads=" + btoa(JSON.stringify(cookie)) + "; max-age=34560000; path=/;";
        }
    }
} catch (err) {
    let error = {
        Error: err,
        Url: window.location.href
    }
    const url = "https://integration.viaads.dk/error"
    let xhr = new XMLHttpRequest()
    xhr.open('POST', url, true)
    xhr.setRequestHeader('Content-type', 'application/json;')
    xhr.send(JSON.stringify(error));
}
var config = {}
    //ViaAds End Track customer visit page
