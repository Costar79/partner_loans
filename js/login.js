document.addEventListener("DOMContentLoaded", () => {
    console_Log("🚀 login.js is executing!");

    const loginForm = document.getElementById("loginForm");
    const phoneNumberInput = document.getElementById("phoneNumber");

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
                alert("❌ " + data.error);
                return;
            }

            console_Log("✅ Login successful! Redirecting to loan application...");
            localStorage.setItem("user_token", data.user_token);
            localStorage.setItem("user_id", idNumber);
            localStorage.setItem("phone_number", phoneNumber);

            window.location.href = "/apply.html";
        } catch (error) {
            console_Error("❌ Login error:" + error);
        }
    });
});
