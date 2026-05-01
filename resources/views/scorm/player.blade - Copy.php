<!DOCTYPE html>
<html>
<head>
    <title>SCORM Player</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body>

<h2>{{ $scorm->title }}</h2>

<script>
let scormData = {};
let scoUuid = "{{ $firstSco->uuid }}";
let isInitialized = false;

/*
|--------------------------------------------------------------------------
| Laravel generated tracking URL
|--------------------------------------------------------------------------
*/
const trackingBaseUrl = "{{ route('scorm.track', ['scoUuid' => '__UUID__']) }}";
const trackingUrl = trackingBaseUrl.replace('__UUID__', scoUuid);
const scormVersion = "{{ $scorm->version }}";
console.log("SCORM Version:", scormVersion);

console.log("Tracking URL:", trackingUrl);


/*
|--------------------------------------------------------------------------
| Load previous tracking data
|--------------------------------------------------------------------------
*/
async function loadTrackingData() {
    try {
        let response = await fetch(trackingUrl);
        let data = await response.json();

        console.log("Existing tracking data:", data);

        if (data && data.details) {

            // details stored as JSON string
            if (typeof data.details === "string") {
                scormData = JSON.parse(data.details);
            } else {
                scormData = data.details;
            }

            console.log("Restored SCORM Data:", scormData);
        }

    } catch (e) {
        console.error("Tracking load failed:", e);
    }
}

//loadTrackingData();


/*
|--------------------------------------------------------------------------
| Build payload for DB
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
            scormData["cmi.location"] ||
            scormData["cmi.core.lesson_location"] ||
            null,

        suspend_data:
            scormData["cmi.suspend_data"] || scormData["cmi.core.suspend_data"] || null,

        session_time:
            scormData["cmi.core.session_time"] ||
            scormData["cmi.session_time"] ||
            null,

        total_time_string:
            scormData["cmi.core.total_time"] ||
            scormData["cmi.total_time"] ||
            null,

        // IMPORTANT: save actual runtime SCORM values
        details: JSON.stringify(scormData)
    };
}


/*
|--------------------------------------------------------------------------
| Save progress
|--------------------------------------------------------------------------
*/
async function commitToServer() {
    try {
        scormData["cmi.exit"] = "suspend";

        // VERY IMPORTANT
//scormData["cmi.entry"] = "resume";
//scormData["cmi.core.entry"] = "resume";

        let payload = buildTrackingPayload();

        console.log("Sending payload:", payload);

        let response = await fetch(trackingUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]'
                ).getAttribute("content")
            },
            body: JSON.stringify(payload)
        });

        let result = await response.json();

        console.log("Progress saved:", result);

    } catch (e) {
        console.error("Commit failed:", e);
    }
}

async function sendCmiValue(key, value) {
    try {

let payload = {};

if (scormVersion === "scorm_12") {
    payload = {
        //cmi: {
            "cmi.core.lesson_location":
                scormData["cmi.location"] ||
                scormData["cmi.core.lesson_location"] ||
                "",

            "cmi.core.lesson_status":
                scormData["cmi.completion_status"] ||
                scormData["cmi.core.lesson_status"] ||
                "",

            "cmi.core.score.raw":
                scormData["cmi.score.raw"] || "",

            "cmi.core.session_time":
                scormData["cmi.session_time"] || "",

            "cmi.core.exit":
                scormData["cmi.exit"] || "",
            
            "cmi.entry":
                scormData["cmi.entry"] || "resume",

            "cmi.core.entry":
                scormData["cmi.core.entry"] || "resume",

            "cmi.suspend_data":
                scormData["cmi.suspend_data"] || ""
      //  }
    };
}
else {
    payload = {
        //cmi: {
            "cmi.location":
                scormData["cmi.location"] || "",

            "cmi.completion_status":
                scormData["cmi.completion_status"] || "",

            "cmi.success_status":
                scormData["cmi.success_status"] || "",

            "cmi.score.raw":
                scormData["cmi.score.raw"] || "",

            "cmi.session_time":
                scormData["cmi.session_time"] || "",

            "cmi.exit":
                scormData["cmi.exit"] || "",

            "cmi.entry":
                scormData["cmi.entry"] || "resume",

            "cmi.core.entry":
                scormData["cmi.core.entry"] || "resume",

            "cmi.suspend_data":
                scormData["cmi.suspend_data"] || ""
        //}
    };
}
        await fetch(trackingUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector(
                    'meta[name="csrf-token"]'
                ).getAttribute("content")
            },
            body: JSON.stringify(payload)
        });

    } catch (e) {
        console.error("CMI save failed:", e);
    }
}


async function startCourse() {
    await loadTrackingData();

    console.log("Restored data before launch:", scormData);

    document.getElementById("scormFrame").src =
        "{{ asset('storage/'.$scorm->uuid.'/'.$scorm->entry_url) }}";
}

startCourse();


/*
|--------------------------------------------------------------------------
| SCORM 1.2 API
|--------------------------------------------------------------------------
*/
const API = {

    LMSInitialize() {
        console.log("SCORM 1.2 Initialize");
        isInitialized = true;
        return "true";
    },

    LMSFinish() {
        console.log("SCORM 1.2 Finish");
        commitToServer();
        return "true";
    },

    LMSGetValue(key) {
        console.log("LMSGetValue:", key);

        if (key === "cmi.entry" || key === "cmi.core.entry") {
            return "resume";
        }

        if (key === "cmi.exit" || key === "cmi.core.exit") {
            return "suspend";
        }

        let value = scormData[key] || "";

        console.log("Returning value:", key, value);

        return value;
    },

    LMSSetValue(key, value) {
        console.log("LMSSetValue:", key, value);
        scormData[key] = value;
        // ✅ send immediately in correct SCORM format
        sendCmiValue(key, value);
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

        if (key === "cmi.entry") {
            return "resume";
        }

        if (key === "cmi.exit") {
            return "suspend";
        }

        let value = scormData[key] || "";

        console.log("Returning value:", key, value);

        return value;
    },

    SetValue(key, value) {
        console.log("SetValue:", key, value);

        scormData[key] = value;
        sendCmiValue(key, value);
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
| Expose APIs globally
|--------------------------------------------------------------------------
*/
function initializeScormAPI() {

    console.log("Initializing API for version:", scormVersion);

    try {
        if (scormVersion === "scorm_12") {

            window.API = API;

            // expose safely to parent chain
            if (window.parent && window.parent !== window) {
                window.parent.API = API;
            }

            if (window.opener) {
                window.opener.API = API;
            }

            console.log("SCORM 1.2 API registered");
        }
        else {

            window.API_1484_11 = API_1484_11;

            if (window.parent && window.parent !== window) {
                window.parent.API_1484_11 = API_1484_11;
            }

            if (window.opener) {
                window.opener.API_1484_11 = API_1484_11;
            }

            console.log("SCORM 2004 API registered");
        }

    } catch (e) {
        console.error("API initialization failed:", e);
    }
}

initializeScormAPI();

/*
|--------------------------------------------------------------------------
| Save on browser close
|--------------------------------------------------------------------------
*/
window.addEventListener("beforeunload", function () {
    if (isInitialized) {
        commitToServer();
    }
});
</script>


<iframe
    width="100%"
    height="800px"
    frameborder="0" id="scormFrame">
</iframe>

</body>
</html>