<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $scorm->title ?? 'SCORM Player' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.jsdelivr.net/npm/scorm-again@2.2.0/dist/scorm-again.js"></script>
    <style>
        html, body, iframe { width: 100%; height: 100%; margin: 0; padding: 0; border: none; overflow: hidden; }
    </style>
</head>
<body>

<iframe id="scormFrame"></iframe>

<script>
// Use optional chaining and default values to prevent Blade/JS syntax errors
const settings = {
    version: "{{ $scorm->version ?? 'scorm_12' }}",
    scoUuid: "{{ $firstSco->uuid ?? '' }}",
    trackUrl: "{{ route('scorm.track', ['scoUuid' => $firstSco->uuid ?? 'none']) }}",
    getTrackingUrl: "{{ route('scorm.getTracking', ['scoUuid' => $firstSco->uuid ?? 'none', 'scormVersion' => $scorm->version ?? 'scorm_12']) }}"
};

let existingCmi = {};

async function loadTrackingData() {
    try {
        const response = await fetch(settings.getTrackingUrl, {
            headers: { 'Accept': 'application/json' }
        });
        
        // Check if response is HTML (which causes the SyntaxError)
        const contentType = response.headers.get("content-type");
        if (contentType && !contentType.includes("application/json")) {
            console.error("Server returned non-JSON response. Check Network tab.");
            return;
        }

        const result = await response.json();
        if (result && result.details) {
            existingCmi = typeof result.details === 'string' ? JSON.parse(result.details) : result.details;
        }
        console.log("Restored CMI:", existingCmi);
    } catch (error) {
        console.error("Load Tracking Failed:", error);
    }
}

async function postTracking(payload) {
    if (settings.version === 'scorm_12') {
        if (!payload.cmi['cmi.core.exit']) {
            payload.cmi['cmi.core.exit'] = 'suspend';
        }
    } else {
        if (!payload.cmi['cmi.exit']) {
            payload.cmi['cmi.exit'] = 'suspend';
        }
    }
    try {
        // Use fetch without 'await' for SetValue to keep the UI smooth
        fetch(settings.trackUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                "Accept": "application/json"
            },
            body: JSON.stringify(payload)
        });
    } catch (error) {
        console.error("Sync failed:", error);
    }
}

let isRestoring = true;
async function initializePlayer() {
    await loadTrackingData();
    
    // Safety check for the URL
    const entryUrl = "{{ asset('storage/'.($scorm->uuid ?? '').'/'.($scorm->entry_url ?? '')) . ($sco->sco_parameters ?? '') }}";

    if (settings.version === "scorm_12") {
        window.API = new Scorm12API({ autocommit: false, logLevel: 1 });
        if ( existingCmi && ( existingCmi.suspend_data || existingCmi['core.lesson_location'] || existingCmi['core.lesson_status'] !== 'unknown' ) ) { console.log("Loading resume data"); window.API.loadFromJSON(existingCmi); } else { console.log("Skipping invalid resume data"); }

        // restore finished
        isRestoring = false;

        // This ensures the entry mode is set to 'resume' inside the API memory
        if (existingCmi['cmi.core.lesson_location'] || existingCmi['cmi.suspend_data']) {
            window.API.LMSSetValue('cmi.core.entry', 'resume');
        }

        window.API.on("LMSSetValue.cmi.*", function(element, value) {
            if (isRestoring) {
                console.log("Skipping restore event");
                return;
            }
            postTracking({
                cmi: {
                    [element]: value
                }
            });
        });

        window.API.on("LMSCommit", function() {
            postTracking({
                cmi: window.API.cmi.toJSON()
            });
        });
    } else {
        window.API_1484_11 = new Scorm2004API({ autocommit: false, logLevel: 1 });
        if (
    existingCmi &&
    (
        existingCmi.suspend_data ||
        existingCmi.location ||
        existingCmi.completion_status !== 'unknown' ||
        existingCmi.success_status !== 'unknown'
    )
) {
    console.log("Loading SCORM 2004 resume data");
    window.API_1484_11.loadFromJSON(existingCmi);
} else {
    console.log("Skipping invalid SCORM 2004 resume data");
}

        window.API_1484_11.on("SetValue.cmi.*", function(element, value) {
            postTracking({
                cmi: {
                    [element]: value
                }
            });
        });

        window.API_1484_11.on("Commit", function() {
            postTracking({
                cmi: window.API_1484_11.cmi.toJSON()
            });
        });
    }

    document.getElementById("scormFrame").src = entryUrl;
}

initializePlayer();
</script>

</body>
</html>