document.addEventListener("DOMContentLoaded", () => {
    console_Log("login.js is executing!");

    const loginForm = document.getElementById("loginForm");
    const phoneNumberInput = document.getElementById("phoneNumber");

    function updateNotification(message, type = "error") {
        const notificationDiv = document.getElementById("notificationMessages");
    
        if (!notificationDiv) {
            console_Error("❌ Notification div not found.");
            return;
        }
    
        notificationDiv.textContent = message;
        notificationDiv.className = `notification-messages ${type}`;
        notificationDiv.style.display = "block";
    
        // Auto-hide after 5 seconds
        setTimeout(() => {
            notificationDiv.style.display = "none";
        }, 5000);
    }

    document.getElementById("idNumber").addEventListener("input", function () {
        this.value = this.value.replace(/\D/g, "").slice(0, 13); // Remove non-numeric characters and limit to 13
    });

    phoneNumberInput.addEventListener("input", function (event) {
        let input = event.target.value.replace(/\D/g, ""); // Remove non-numeric characters
        let formattedNumber = "";

        if (input.length > 10) input = input.substring(0, 10); // Limit to 10 digits

        if (input.length > 6) {
            formattedNumber = `(${input.substring(0, 3)}) ${input.substring(3, 6)} - ${input.substring(6)}`;
        } else if (input.length > 3) {
            formattedNumber = `(${input.substring(0, 3)}) ${input.substring(3)}`;
        } else {
            formattedNumber = `(${input}`;
        }

        event.target.value = formattedNumber;
    });
/*
    loginForm_OLD.addEventListener("submit", async (e) => {
        e.preventDefault();

        console_Log("Form submitted! Sending data...");

        const idNumber = document.getElementById("idNumber").value.trim();
        const phoneNumber = phoneNumberInput.value.replace(/\D/g, ""); // Remove formatting for submission
        const partnerCode = localStorage.getItem("partner_code") || null;
        
        console_Log("Using partner code:" + partnerCode);

        const requestData = { id_number: idNumber, phone_number: phoneNumber, partner_code: partnerCode };

        try {
            const response = await fetch("/server/api/user.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(requestData)
            });

            const data = await response.json();

            if (data.error) {
                console_Error("Authentication error:" + data.error);
                alert("❌ " + data.error);
                return;
            }

            console_Log("Login successful! Redirecting to loan application...");
            localStorage.setItem("user_token", data.user_token);
            localStorage.setItem("user_id", idNumber);
            localStorage.setItem("phone_number", phoneNumber);

            window.location.href = "/apply.html";
        } catch (error) {
            console_Error("❌ Login error:" + error);
        }
    });
*/

loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    console_Log("🚀 Form submitted! Sending data...");

    const idNumber = document.getElementById("idNumber").value.trim();
    const phoneNumber = phoneNumberInput.value.replace(/\D/g, ""); // Remove formatting for submission
    const partnerCode = localStorage.getItem("partner_code") || null;

    console_Log("🚀 Using partner code:" + partnerCode);

    const requestData = { id_number: idNumber, phone_number: phoneNumber, partner_code: partnerCode };

    try {
        const response = await fetch("/server/api/user.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(requestData)
        });

        const data = await response.json();

        if (data.error) {
            console_Error("Authentication error:" + data.error);
            updateNotification(data.error, "error");
            return;
        }

        console_Log("Login successful! Redirecting to loan application...");
        localStorage.setItem("user_token", data.user_token);
        localStorage.setItem("id_number", data.id_number); 
        localStorage.setItem("phone_number", data.phone_number);


        window.location.href = "/apply.html";
    } catch (error) {
        console_Error("❌ Login error:" + error);
        updateNotification("Server error. Please try again later.", "error");
    }
});


});
