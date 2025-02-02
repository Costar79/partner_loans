document.addEventListener("DOMContentLoaded", async () => {
    console.log("✅ Apply page loaded.");

    const loginSection = document.getElementById("loginSection");
    const loanSection = document.getElementById("loanSection");
    const loginForm = document.getElementById("loginForm");
    const loanForm = document.getElementById("loanForm");
    const amountInput = document.getElementById("amount");
    const termSelect = document.getElementById("term");
    const followingPaydayContainer = document.getElementById("followingPaydayContainer");
    const logoutButton = document.getElementById("logoutButton");

    // ✅ **Check for Existing Token**
    const userToken = localStorage.getItem("user_token");

    if (userToken) {
        console.log("✅ Token found! Checking user state...");
        verifyUserState(userToken);
    } else {
        loginSection.style.display = "block";
        loanSection.style.display = "none";
    }

    // ✅ **Handle Login Process**
    loginForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        const idNumber = document.getElementById("idNumber").value.trim();
        const phoneNumber = document.getElementById("phoneNumber").value.trim();

        try {
            const requestData = { id_number: idNumber, phone_number: phoneNumber };
            console.log("🚀 Sending data to user.php:", requestData);
            
            const response = await fetch("/server/api/user.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify(requestData)
            });

            const data = await response.json();

            if (data.error) {
                alert("❌ " + data.error);
                return;
            }

            localStorage.setItem("user_token", data.user_token);
            localStorage.setItem("user_id", idNumber);
            localStorage.setItem("phone_number", phoneNumber);

            verifyUserState(data.user_token);
        } catch (error) {
            console.error("❌ Login error:", error);
        }
    });

    // ✅ **Ensure Loan Amount Updates Loan Term Dropdown**
    amountInput.addEventListener("input", () => {
        let amount = parseFloat(amountInput.value.replace(/[^\d]/g, ""));
        console.log("🚀 Loan amount entered:", amount); // ✅ Debugging Log

        if (isNaN(amount)) {
            termSelect.innerHTML = '<option value="">Select Loan Amount First</option>';
            return;
        }

        let termOptions = [];
        if (amount >= 500 && amount <= 749) termOptions = [1];
        else if (amount >= 750 && amount <= 1000) termOptions = [1, 2];
        else if (amount >= 1001 && amount <= 1500) termOptions = [1, 2, 3];
        else if (amount >= 1501 && amount <= 2000) termOptions = [1, 2, 3, 4];
        else if (amount >= 2001 && amount <= 4000) termOptions = [1, 2, 3, 4, 5];
        else if (amount >= 4001 && amount <= 8000) termOptions = [1, 2, 3, 4, 5, 6];

        console.log("🚀 Available Terms:", termOptions); // ✅ Debugging Log

        termSelect.innerHTML = termOptions.length
            ? termOptions.map(term => `<option value="${term}">${term} Month${term > 1 ? "s" : ""}</option>`).join("")
            : '<option value="">Amount Not Eligible</option>';
    });

    async function verifyUserState(token) {
        console.log("🚀 Sending token to loan.php:", token || "No token found"); // ✅ Debugging Log

        try {
            const response = await fetch("/server/api/loan.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ user_token: token }) // ✅ No amount or term needed for this check
            });

            const data = await response.json();

            console.log("✅ Received response from loan.php:", data);
            console.log("🔍 Debug: followingPaydayEligible =", data.followingPaydayEligible);

            if (data.error) {
                console.error("❌ Authentication error:", data.error);
                localStorage.removeItem("user_token");
                loginSection.style.display = "block";
                loanSection.style.display = "none";
                return;
            }

            if (data.state === "Inactive") {
                console.log("❌ User is inactive. Blocking access.");
                alert("Your account is inactive. You cannot access this page.");
                window.location.href = "/";
                return;
            }

            if (data.state === "Suspended") {
                console.log("⚠️ User is suspended. Disabling loan application.");
                loanForm.innerHTML = "<p>Your account is suspended. You cannot apply for new loans.</p>";
            }

            // ✅ Show "Following Payday" checkbox if eligible
            if (data.hasOwnProperty("followingPaydayEligible") && data.followingPaydayEligible) {
                followingPaydayContainer.style.display = "block";
            } else {
                followingPaydayContainer.style.display = "none";
                document.getElementById("followingPayday").checked = false; // ✅ Reset checkbox
            }

            loginSection.style.display = "none";
            loanSection.style.display = "block";

        } catch (error) {
            console.error("❌ Error verifying user state:", error);
        }
    }

    // ✅ **Handle Loan Submission**
    loanForm.addEventListener("submit", async (e) => {
        e.preventDefault();

        const amount = parseFloat(amountInput.value.replace(/[^\d]/g, ""));
        const term = termSelect.value;
        const followingPayday = document.getElementById("followingPayday").checked;

        if (!amount || !term) {
            alert("Please enter a valid loan amount and select a term.");
            return;
        }

        const submitButton = loanForm.querySelector("button[type='submit']");
        submitButton.disabled = true; // ✅ Prevent multiple submissions

        try {
            const userToken = localStorage.getItem("user_token");
            if (!userToken) {
                console.error("❌ Loan request blocked: No valid token.");
                alert("Session expired. Please log in again.");
                window.location.href = "/";
                return;
            }

            const response = await fetch("/server/api/loan.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ user_token: userToken, amount, term, followingPayday })
            });

            const data = await response.json();
            if (data.error) {
                alert("❌ " + data.error);
            } else {
                alert("✅ Loan application submitted successfully!");
                window.location.reload();
            }
        } catch (error) {
            console.error("❌ Loan submission error:", error);
        } finally {
            submitButton.disabled = false; // ✅ Re-enable button after request completes
        }
    });

    // ✅ **Handle Logout**
    logoutButton.addEventListener("click", async () => {
        console.log("🚪 Logging out...");
        localStorage.removeItem("user_token");
        document.cookie = "user_token=; path=/; expires=Thu, 01 Jan 1970 00:00:00 UTC; Secure; SameSite=None";

        try {
            await fetch("/server/api/logout.php", { method: "POST", credentials: "include" });
        } catch (error) {
            console.error("❌ Logout failed:", error);
        }

        window.location.href = "/";
    });
});
