document.addEventListener("DOMContentLoaded", async () => {
    console_Log("login.js is executing!");

    const loginForm = document.getElementById("loginForm");
    const phoneNumberInput = document.getElementById("phoneNumber");
    const nextPayDateContainer = document.getElementById("nextPayDateContainer");
    const nextPayDateInput = document.getElementById("nextPayDate");
    const heading = document.querySelector(".top-bar h2");
    const submitButton = loginForm.querySelector("button[type='submit']");

    // Function to check if a cookie exists
    function getCookie(name) {
        const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? match[2] : null;
    }

    // Function to get the last day of the current month
    function getLastDayOfCurrentMonth() {
        const today = new Date();
        const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0); // Last day of the month

        // Format YYYY-MM-DD using local time
        const year = lastDay.getFullYear();
        const month = String(lastDay.getMonth() + 1).padStart(2, "0");
        const day = String(lastDay.getDate()).padStart(2, "0");

        return `${year}-${month}-${day}`;
    }

    // Function to get partner code from the URL path
    function getPartnerCode() {
        const urlPath = window.location.pathname;
        const match = urlPath.match(/^\/pc\/([0-9]{4})$/);
        return match ? match[1] : null;
    }

    // Capture and store partner code if present
    const partnerCode = getPartnerCode();
    if (partnerCode) {
        console_Log("Captured partner code:", partnerCode);
        localStorage.setItem("partner_code", partnerCode);
    }

    // Check for authentication token (cookie or localStorage)
    const authToken = getCookie("user_token") || localStorage.getItem("user_token");

    console_Log("Checking authentication token:" + authToken);

    if (!authToken) {
        console_Warn("No authentication found. Showing signup fields.");
        
        // Show Next Pay Date field
        nextPayDateContainer.style.display = "block";
        nextPayDateInput.value = getLastDayOfCurrentMonth();

        // Change the H2 text to "Sign up"
        if (heading) {
            heading.textContent = "Sign up";
        }
    
        if (submitButton) {
        submitButton.textContent = "Sign Up";
        }    

    } else {
        console_Log("User token found, attempting auto-login...");

        try {
            console_Log("Checking for user_token:" +  getCookie("user_token"), localStorage.getItem("user_token"));

            const response = await fetch("/server/api/auth.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ user_token: authToken }) 
            });

            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const data = await response.json();

            if (data.error) {
                console_Error("Auto-login failed:" + data.error);
            } else {
                console_Log("Auto-login successful! Redirecting to apply page...");
                window.location.href = "/apply.html";
            }
        } catch (error) {
            console.error("Auto-login error:", error);
        }
    }

    function updateNotification(message, type = "error") {
        const notificationDiv = document.getElementById("notificationMessages");

        if (!notificationDiv) {
            console.error("Notification div not found.");
            return;
        }

        notificationDiv.textContent = message;
        notificationDiv.className = `notification-messages ${type}`;
        notificationDiv.style.display = "block";

        setTimeout(() => {
            notificationDiv.textContent = "";
            notificationDiv.style.display = "none";
        }, 5000);
    }

    document.getElementById("idNumber").addEventListener("input", function () {
        this.value = this.value.replace(/\D/g, "").slice(0, 13);
    });

    phoneNumberInput.addEventListener("input", function (event) {
        let input = event.target.value.replace(/\D/g, "");
        let formattedNumber = "";

        if (input.length > 10) input = input.substring(0, 10);

        if (input.length > 6) {
            formattedNumber = `(${input.substring(0, 3)}) ${input.substring(3, 6)}-${input.substring(6)}`;
        } else if (input.length > 3) {
            formattedNumber = `(${input.substring(0, 3)}) ${input.substring(3)}`;
        } else {
            formattedNumber = input.length > 0 ? `(${input}` : "";
        }

        event.target.value = formattedNumber;
    });

    loginForm.addEventListener("submit", async (e) => {
        e.preventDefault();
    
        console_Log("Form submitted! Sending data...");
    
        const idNumber = document.getElementById("idNumber").value.trim();
        const phoneNumber = phoneNumberInput.value.replace(/\D/g, "");
        const payday = nextPayDateInput.value.trim();
    
        // Retrieve partner code from localStorage
        const storedPartnerCode = localStorage.getItem("partner_code");
        const partnerCode = storedPartnerCode && storedPartnerCode !== "null" ? storedPartnerCode : null;
    
        console_Log("Using partner code:" + partnerCode);
        console_Log("Selected payday: " + payday);
 
         if (!payday) {
            console_Error("Invalid payday selected.");
            updateNotification("Invalid payday. Please select a valid date.", "error");
            return;
        }   
    
        const requestData = { 
            id_number: idNumber, 
            phone_number: phoneNumber, 
            partner_code: partnerCode, 
            payday: payday // Added payday in request data
        };
        
        try {
            const response = await fetch("/server/api/user.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(requestData)
            });
    
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }
    
            const data = await response.json();
    
            if (data.error) {
                console.error("Authentication error:", data.error);
                updateNotification(data.error, "error");
                return;
            }
    
            console_Log("Login successful! Storing new user token...");
    
            if (data.user_token) {
                localStorage.setItem("user_token", data.user_token);
                console_Log("Stored user_token:", data.user_token);
            } else {
                console_Warn("Warning: user_token missing from API response.");
            }
    
            if (data.id_number) {
                localStorage.setItem("id_number", data.id_number);
            } else {
                console_Warn("Warning: id_number missing from API response.");
            }
    
            if (data.phone_number) {
                localStorage.setItem("phone_number", data.phone_number);
            } else {
                console_Warn("Warning: phone_number missing from API response.");
            }
    
            if (data.nextPayDateInput) {
                localStorage.setItem("payday", data.payday);
                console_Log("Stored payday:", data.payday);
            } else {
                console_Warn("Warning: payday missing from API response.");
            }
    
            window.location.href = "/apply.html";
        } catch (error) {
            console_Error("Login error:" + error);
            updateNotification("Server error. Please try again later.", "error");
        }
    });

});
