<!DOCTYPE html>
<html>
<head>
    <title> testing SCORM Player</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>

<h2>{{ $scorm->title }}</h2>

<script>
let scormData = {};
let scoUuid = "{{ $firstSco->uuid }}";
let isInitialized = false;

const trackingBaseUrl = "{{ url('scorm/track') }}";
alert(trackingBaseUrl);
console.log("Tracking Base URL:", trackingBaseUrl);
console.log("SCO UUID:", scoUuid);

/*
|--------------------------------------------------------------------------
| Load existing tracking data
|--------------------------------------------------------------------------
*/
async function loadTrackingData() {
    try {
        let response = await fetch(`${trackingBaseUrl}/${scoUuid}`);

        let data = await response.json();

        console.log("Existing tracking data:", data);

        if (data && data.details) {
            scormData = data.details;
        }

    } catch (e) {
        console.error("Tracking load failed:", e);
    }
}

loadTrackingData();


/*
|--------------------------------------------------------------------------
| Convert SCORM runtime data to DB payload
|--------------------------------------------------------------------------
*/
function buildTrackingPayload() {
    return {
        progression:
            scormData["cmi.progress_measure"] || 100,

        score_raw:
            scormData["cmi.core.score.raw"] ||
            scormData["cmi.score.raw"] ||
            null,

        score_min:
            scormData["cmi.core.score.min"] ||
            scormData["cmi.score.min"] ||
            null,

        score_max:
            scormData["cmi.core.score.max"] ||
            scormData["cmi.score.max"] ||
            null,

        lesson_status:
            scormData["cmi.core.lesson_status"] ||
            scormData["cmi.lesson_status"] ||
            "incomplete",

        completion_status:
            scormData["cmi.completion_status"] ||
            scormData["cmi.core.lesson_status"] ||
            "incomplete",

        success_status:
            scormData["cmi.success_status"] || null,

        lesson_location:
            scormData["cmi.core.lesson_location"] ||
            scormData["cmi.location"] ||
            null,

        suspend_data:
            scormData["cmi.suspend_data"] || null,

        session_time:
            scormData["cmi.core.session_time"] ||
            scormData["cmi.session_time"] ||
            null,

        total_time_string:
            scormData["cmi.core.total_time"] ||
            scormData["cmi.total_time"] ||
            null,

        details: scormData
    };
}


/*
|--------------------------------------------------------------------------
| Save progress to Laravel
|--------------------------------------------------------------------------
*/
async function commitToServer() {
    try {
        let payload = buildTrackingPayload();

        console.log("Sending payload:", payload);

        let response = await fetch(`${trackingBaseUrl}/${scoUuid}`, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]'
                ).getAttribute("content")
            },
            body: JSON.stringify(payload)
        });

        let responseText = await response.text();

        console.log("Raw response:", responseText);

        try {
            let result = JSON.parse(responseText);
            console.log("Progress saved successfully:", result);
        } catch (jsonError) {
            console.error("Invalid JSON response:", responseText);
        }

    } catch (e) {
        console.error("Commit failed:", e);
    }
}


/*
|--------------------------------------------------------------------------
| SCORM 1.2 API
|--------------------------------------------------------------------------
*/
const API = {

    LMSInitialize() {
        console.log("SCORM 1.2 LMSInitialize");
        isInitialized = true;
        return "true";
    },

    LMSFinish() {
        console.log("SCORM 1.2 LMSFinish");
        commitToServer();
        return "true";
    },

    LMSGetValue(key) {
        console.log("LMSGetValue:", key);
        return scormData[key] || "";
    },

    LMSSetValue(key, value) {
        console.log("LMSSetValue:", key, value);

        scormData[key] = value;
        return "true";
    },

    LMSCommit() {
        console.log("LMSCommit");
        commitToServer();
        return "true";
    },

    LMSGetLastError() {
        return "0";
    },

    LMSGetErrorString() {
        return "";
    },

    LMSGetDiagnostic() {
        return "";
    }
};


/*
|--------------------------------------------------------------------------
| SCORM 2004 API
|--------------------------------------------------------------------------
*/
const API_1484_11 = {

    Initialize() {
        console.log("SCORM 2004 Initialize");
        isInitialized = true;
        return "true";
    },

    Terminate() {
        console.log("SCORM 2004 Terminate");
        commitToServer();
        return "true";
    },

    GetValue(key) {
        console.log("GetValue:", key);
        return scormData[key] || "";
    },

    SetValue(key, value) {
        console.log("SetValue:", key, value);

        scormData[key] = value;
        return "true";
    },

    Commit() {
        console.log("Commit");
        commitToServer();
        return "true";
    },

    GetLastError() {
        return "0";
    },

    GetErrorString() {
        return "";
    },

    GetDiagnostic() {
        return "";
    }
};


/*
|--------------------------------------------------------------------------
| Make API accessible globally
|--------------------------------------------------------------------------
*/
window.API = API;
window.API_1484_11 = API_1484_11;

window.parent.API = API;
window.parent.API_1484_11 = API_1484_11;

window.top.API = API;
window.top.API_1484_11 = API_1484_11;


/*
|--------------------------------------------------------------------------
| Save progress when browser closes
|--------------------------------------------------------------------------
*/
window.addEventListener("beforeunload", function () {
    if (isInitialized) {
        commitToServer();
    }
});
</script>


<iframe
    src="{{ asset('storage/'.$scorm->uuid.'/'.$scorm->entry_url) }}"
    width="100%"
    height="800px"
    frameborder="0">
</iframe>

</body>
</html>