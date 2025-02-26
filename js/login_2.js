document.addEventListener("DOMContentLoaded", () => {
    console.log("login.js is executing!");

    const loginForm = document.getElementById("loginForm");
    const phoneNumberInput = document.getElementById("phoneNumber");

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
            formattedNumber = `(${input}`;
        }

        event.target.value = formattedNumber;
    });

    loginForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        console.log("Form submitted! Sending data...");

        const idNumber = document.getElementById("idNumber").value.trim();
        const phoneNumber = phoneNumberInput.value.replace(/\D/g, "");
        const partnerCode = localStorage.getItem("partner_code") || null;

        console.log("Using partner code:", partnerCode);

        const requestData = { id_number: idNumber, phone_number: phoneNumber, partner_code: partnerCode };

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

            console.log("Login successful! Storing new user token...");

            localStorage.setItem("user_token", data.user_token);
            console.log("Stored user_token:", data.user_token);

            if (data.id_number) {
                localStorage.setItem("id_number", data.id_number);
                console.log("Stored id_number:", data.id_number);
            } else {
                console.warn("Warning: id_number missing from API response.");
            }

            if (data.phone_number) {
                localStorage.setItem("phone_number", data.phone_number);
                console.log("Stored phone_number:", data.phone_number);
            } else {
                console.warn("Warning: phone_number missing from API response.");
            }

            window.location.href = "/apply.html";
        } catch (error) {
            console.error("Login error:", error);
            updateNotification("Server error. Please try again later.", "error");
        }
    });
});
