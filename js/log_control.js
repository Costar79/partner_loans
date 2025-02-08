let consoleEnabled = false; // Default to false

fetch("/server/api/get_settings.php")
    .then(response => response.json())
    .then(settings => {
        consoleEnabled = settings.consoleLogging ?? false;

        if (!consoleEnabled) {
            console.log = function () {};
            console.warn = function () {};
            console.error = function () {};
            console.info = function () {};
        }

        console.log("Debug: Console Logging is set to", consoleEnabled);
    })
    .catch(error => {
        console.error("Error fetching settings:", error);
    });

// ✅ Custom Console Log Function
function console_Log(message) {
    if (consoleEnabled) {
        console.log(message);
    } else {
        console.warn("⚠️ console_Log() suppressed:", message);
    }
}

function console_Warn(message) {
    if (consoleEnabled) {
       console.warn(message);
    } else {
        console.warn("⚠️ console_Warn() suppressed:", message);
    }
}

function console_Error(message) {
    if (consoleEnabled) {
        console.error(message);
    } else {
        console.warn("⚠️ console_Error() suppressed:", message);
    }
}