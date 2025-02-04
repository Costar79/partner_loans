document.addEventListener("DOMContentLoaded", async () => {
    console_Log("✅ apply.js is executing!");

    const amountInput = document.getElementById("amount");
    const termSelect = document.getElementById("term");
    const loanSection = document.getElementById("loanSection");
    const pendingApplication = document.getElementById("pendingApplication");
    const followingPaydayContainer = document.getElementById("followingPaydayContainer");
    const logoutButton = document.getElementById("logoutButton");

    if (!loanSection || !pendingApplication || !logoutButton) {
        console_Error("❌ Critical Error: Required elements not found in apply.html");
        return;
    }

async function verifyUserState(token) {
    console_Log("🚀 Checking loan status with loan.php:" + token);

    try {
        const response = await fetch("/server/api/loan.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ user_token: token })
        });

        const data = await response.json();


        if (data.error) {
            console_Error("❌ Authentication error:" + data.error);
            localStorage.removeItem("user_token");
            window.location.href = "/login.html";
            return;
        }

        // ✅ Always fetch and display loan history
        fetchLoanHistory(token);    

        // ✅ Ensure required UI elements exist before modifying them
        if (!loanSection || !pendingApplication || !followingPaydayContainer) {
            console_Error("❌ UI Error: Required elements missing in apply.html");
            return;
        }

        // ✅ If the user has a pending loan, show "Application Pending" and hide the form
        if (data.hasPendingLoan) {
            console.warn("⚠️ User has a pending loan. Showing 'Application Pending' message.");
            loanSection.style.display = "none";
            pendingApplication.style.display = "block";
        } else {
            loanSection.style.display = "block";
            pendingApplication.style.display = "none";
        }
        

        // ✅ Show "Following Payday" checkbox if eligible
        followingPaydayContainer.style.display = data.followingPaydayEligible ? "block" : "none";

    } catch (error) {
        console_Error("❌ Error verifying loan status:" + error);
    }
}

// ✅ Fetch Loan History from Backend
async function fetchLoanHistory(token) {
    console_Log("🚀 Fetching loan history...");
    
    try {
        const response = await fetch("/server/api/loan_history.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ user_token: token })
        });

        const data = await response.json();

        if (data.error || !data.loans || data.loans.length === 0) {
            console.warn("⚠️ No loan history available.");
            return;
        }

        const tableBody = document.querySelector("#loanHistoryTable tbody");
        tableBody.innerHTML = ""; // Clear previous data

        data.loans.forEach(loan => {
            const row = document.createElement("tr");
            row.innerHTML = `
                <td>${new Date(loan.created_at).toLocaleDateString()}</td>
                <td>R${loan.amount}</td>
                <td>${loan.term} months</td>
                <td>${loan.status}</td>
                <td>${loan.settled}</td>
            `;
            tableBody.appendChild(row);
        });

        document.getElementById("loanHistoryContainer").style.display = "block"; // ✅ Show history table

    } catch (error) {
        console_Error("❌ Error fetching loan history:" + error);
    }
}

    verifyUserState(localStorage.getItem("user_token"));

    // ✅ Ensure Loan Amount Updates Loan Term Dropdown (Only If Loan Form Is Visible)
    if (amountInput) {
        amountInput.addEventListener("input", () => {
            let amount = parseFloat(amountInput.value.replace(/[^\d]/g, ""));
            console_Log("🚀 Loan amount entered:" + amount);

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

            console_Log("🚀 Available Terms:" + termOptions);

            termSelect.innerHTML = termOptions.length
                ? termOptions.map(term => `<option value="${term}">${term} Month${term > 1 ? "s" : ""}</option>`).join("")
                : '<option value="">Amount Not Eligible</option>';
        });
    }

    // ✅ **Handle Logout**
    logoutButton.addEventListener("click", async () => {
        localStorage.removeItem("user_token");

        try {
            await fetch("/server/api/logout.php", { method: "POST", credentials: "include" });
        } catch (error) {
            console_Error("❌ Logout failed:" + error);
        }

        window.location.href = "/login.html";
    });
});
