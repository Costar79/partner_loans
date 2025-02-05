document.addEventListener("DOMContentLoaded", async () => {
    console_Log("apply.js is executing!");

    const amountInput = document.getElementById("amount");
    const termSelect = document.getElementById("term");
    const loanSection = document.getElementById("loanSection");
    const pendingApplication = document.getElementById("pendingApplication");
    const followingPaydayContainer = document.getElementById("followingPaydayContainer");
    const logoutButton = document.getElementById("logoutButton");
    const loanForm = document.getElementById("loanForm");

    if (!loanSection || !pendingApplication || !logoutButton) {
        console.error("Critical Error: Required elements not found in apply.html");
        return;
    }

    window.fetchUserData = async function fetchUserData() {

    console_Log("fetchUserData() is being called");

    let idNumber = localStorage.getItem("id_number");
    let phoneNumber = localStorage.getItem("phone_number");
    let userToken = localStorage.getItem("user_token");

    console_Log("Stored id_number:", idNumber);
    console_Log("Stored phone_number:", phoneNumber);
    console_Log("Stored user_token before API call:", userToken);

        console_Log("Sending API request with:", JSON.stringify({ 
            id_number: idNumber.toString().trim(), 
            phone_number: phoneNumber.toString().trim(),
            user_token: userToken 
        }));

    try {
        const response = await fetch("/server/api/user.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "Accept": "application/json"
            },
            body: JSON.stringify({
                id_number: idNumber.toString().trim(),
                phone_number: phoneNumber.toString().trim(),
                user_token: userToken
            })
        });

        const data = await response.json();
        console_Log("Parsed User Data:", data);

        if (data.error) {
            console_Error("API Error:", data.error);
            return;
        }

        console_Log("** User Payback:", data.delayed_payback);
        console_Log("** Partner Payback:", data.max_delayed_payback);
        if (data.delayed_payback < data.max_delayed_payback) {
            console_Log("User is eligible for 'Following Payday'. Showing checkbox.");
            document.getElementById("followingPaydayContainer").style.display = "block";
        } else {
            console_Log("User is NOT eligible for 'Following Payday'. Hiding checkbox.");
            document.getElementById("followingPaydayContainer").style.display = "none";
        }

    } catch (error) {
        console_Error("Error fetching user data:", error);
    }
};

    async function fetchLoanHistory(token) {
        console_Log("Fetching loan history...");

        let userToken = localStorage.getItem("user_token");
        console_Log("Using updated user_token for Loan History API:", userToken);

        if (!userToken) {
            console_Error("Missing user token! Cannot fetch loan history.");
            return;
        }

        try {
            const response = await fetch("/server/api/loan_history.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ user_token: userToken })
            });

            const responseText = await response.text();
            console_Log("Raw Loan History API Response:", responseText);

            const data = JSON.parse(responseText);
            console_Log("Parsed Loan History Data:", data);

            if (data.error) {
                console_Error("Loan History API Error:", data.error);
                return;
            }

            if (!data.loans || data.loans.length === 0) {
            document.getElementById("loanHistoryContainer").style.display = "none";                
                console_Log("No loan history available.");
                return;
            } else {
            document.getElementById("loanHistoryContainer").style.display = "block";
            console_Log("Loan history displayed successfully.");                
            }

            console_Log("Loan history retrieved successfully. Updating table...");

            const tableBody = document.querySelector("#loanHistoryTable tbody");

            if (!tableBody) {
                console_Error("Loan History Table Not Found in HTML.");
                return;
            }

            tableBody.innerHTML = "";

            data.loans.forEach(loan => {
                const row = document.createElement("tr");
                row.innerHTML = `
                    <td>${new Date(loan.created_at).toLocaleDateString()}</td>
                    <td>R${parseFloat(loan.amount).toFixed(2)}</td>
                    <td>${loan.term} months</td>
                    <td>${loan.status}</td>
                    <td>${loan.settled}</td>
                `;
                tableBody.appendChild(row);
            });



        } catch (error) {
            console_Error("Error fetching loan history:", error);
        }
    }

    async function verifyUserState(token) {
        console_Log("Checking loan status with loan.php:", token);

        try {
            const response = await fetch("/server/api/loan.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ user_token: token })
            });

            const data = await response.json();
            console_Log("Loan state response:", data);

            if (data.error) {
                console_Error("Authentication error:", data.error);
                localStorage.removeItem("user_token");
                window.location.href = "/login.html";
                return;
            }

            fetchLoanHistory(token); 

            const loanSection = document.getElementById("loanSection");
            const pendingApplication = document.getElementById("pendingApplication");
            const followingPaydayContainer = document.getElementById("followingPaydayContainer");

            if (!loanSection || !pendingApplication || !followingPaydayContainer) {
                console_Error("UI Error: Required elements missing in apply.html");
                return;
            }

            if (data.hasPendingLoan) {
                console_Log("User has a pending loan. Showing 'Application Pending' message.");
                loanSection.style.display = "none";
                pendingApplication.style.display = "block";
            } else {
                console_Log("No pending loan. Showing loan application form.");
                loanSection.style.display = "block";
                pendingApplication.style.display = "none";
            }

            console_Log("Fetching latest user data from server...");
            await fetchUserData();

            if (data.delayed_payback < data.max_delayed_payback) {
                console_Log("User is eligible for 'Following Payday'. Showing checkbox.");
                followingPaydayContainer.style.display = "block";
            } else {
                console_Log("User is NOT eligible for 'Following Payday'. Hiding checkbox.");
                followingPaydayContainer.style.display = "none";
            }

        } catch (error) {
            console_Error("Error verifying loan status:", error);
        }
    }

    /**
     * Show Notification Message
     */
    function showNotification(message, type) {
        const notificationDiv = document.getElementById("notificationMessages");
        if (!notificationDiv) return;
    
        notificationDiv.textContent = message;
        notificationDiv.className = `notification-messages ${type}`;
        notificationDiv.style.display = "block";
    
    // Sets a timer for notification to disappear
    /*
        setTimeout(() => {
            notificationDiv.style.display = "none";
        }, 10000);
    */    
    }

    const userToken = localStorage.getItem("user_token");
    if (!userToken) {
        console_Error("No user token found. Redirecting to login.");
        window.location.href = "/login.html";
        return;
    }

    console_Log("Calling verifyUserState() on page load...");
    await verifyUserState(userToken);
    fetchUserData();
    window.fetchLoanHistory = fetchLoanHistory;

    if (amountInput) {
        amountInput.addEventListener("input", () => {
            let amount = parseFloat(amountInput.value.replace(/[^\d]/g, ""));
            console.log("Loan amount entered:" + amount);

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

            termSelect.innerHTML = termOptions.length
                ? termOptions.map(term => `<option value="${term}">${term} Month${term > 1 ? "s" : ""}</option>`).join("")
                : '<option value="">Amount Not Eligible</option>';
        });
    }

if (loanForm) {
    loanForm.addEventListener("submit", async (event) => {
        event.preventDefault(); 

        const userToken = localStorage.getItem("user_token");
        if (!userToken) {
            console_Error("No user token found. Redirecting to login.");
            showNotification("Session expired. Redirecting to login...", "error");
            window.location.href = "/login.html";
            return;
        }

        const amount = document.getElementById("amount").value;
        const term = document.getElementById("term").value;
        const followingPayday = document.getElementById("followingPayday").checked;

        if (!amount || !term) {
            console_Error("Loan amount or term is missing.");
            showNotification("Loan amount or term is missing.", "error");
            return;
        }

        try {
            const response = await fetch("/server/api/loan.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    user_token: userToken,
                    amount: amount,
                    term: term,
                    followingPayday: followingPayday
                })
            });

            const data = await response.json();
            console_Log("Loan API Response:", data);

            // ✅ Display the exact message from the API response
            if (data.error) {
                console_Error("Loan application failed: " + data.message);
                showNotification(data.message || "An error occurred while processing your loan.", "error");
                return;
            }

            // ✅ If successful, show success message from API
            showNotification(data.message || "Loan Application Submitted Successfully!", "success");

            // Refresh loan state
            verifyUserState(userToken);
        } catch (error) {
            console_Error("Error submitting loan application:", error);
            showNotification("An unexpected error occurred. Please try again.", "error");
        }
    });
}



    if (logoutButton) {
        logoutButton.addEventListener("click", async () => {
            console_Log("Logging out user...");
            localStorage.removeItem("user_token");

            try {
                await fetch("/server/api/logout.php", { method: "POST", credentials: "include" });
            } catch (error) {
                console_Error("Logout failed:", error);
            }

            window.location.href = "/login.html";
        });
    } else {
        console_Error("Logout button not found in DOM.");
    }
});
