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
        console_Error("Critical Error: Required elements not found in apply.html");
        return;
    }

    // Fetch user data to determine "Following Payday" eligibility
async function fetchUserData() {
    console_Log("fetchUserData() is being called");
    const idNumber = localStorage.getItem("id_number");

    if (!idNumber) {
        console_Error("id_number is missing from localStorage.");
        return;
    }

    console_Log("Sending API request with id_number:", idNumber);

    try {
        const response = await fetch("/server/api/user.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ id_number: idNumber })
        });

        const responseText = await response.text();
        console_Log("Raw API Response:", responseText);

        const data = JSON.parse(responseText);
        if (data.error) {
            console_Error("API Error:", data.error);
            return;
        }

        console_Log("User Data:", data);

        const { delayed_payback, max_delayed_payback } = data;

        console_Log("delayed_payback:", delayed_payback, "max_delayed_payback:", max_delayed_payback);

        // Ensure values are treated as numbers
        const delayedPaybackNum = Number(delayed_payback);
        const maxDelayedPaybackNum = Number(max_delayed_payback);

        console_Log("Converted Values -> delayedPaybackNum:", delayedPaybackNum, "maxDelayedPaybackNum:", maxDelayedPaybackNum);

        // Check if the condition works correctly
        if (delayedPaybackNum < maxDelayedPaybackNum) {
            console_Log("Showing Following Payday Checkbox");
            followingPaydayContainer.style.display = "block";
        } else {
            console_Log("Hiding Following Payday Checkbox");
            followingPaydayContainer.style.display = "none";
        }
    } catch (error) {
        console_Error("Fetch failed:", error);
    }
}



    if (loanForm) {
        loanForm.addEventListener("submit", async (event) => {
            event.preventDefault(); 

            const userToken = localStorage.getItem("user_token");
            if (!userToken) {
                console_Error("No user token found. Redirecting to login.");
                window.location.href = "/login.html";
                return;
            }

            const amount = document.getElementById("amount").value;
            const term = document.getElementById("term").value;
            const followingPayday = document.getElementById("followingPayday").checked;

            if (!amount || !term) {
                console_Error("Loan amount or term is missing.");
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
                if (data.error) {
                    console_Error("Loan application failed: " + data.error);
                    return;
                }

                console_Log("Loan application successful: " + JSON.stringify(data));
                alert("Loan Application Submitted Successfully!");

                verifyUserState(userToken);
            } catch (error) {
                console_Error("Error submitting loan application: " + error);
            }
        });
    }

    async function verifyUserState(token) {
        console_Log("Checking loan status with loan.php: " + token);

        try {
            const response = await fetch("/server/api/loan.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ user_token: token })
            });

            const data = await response.json();

            if (data.error) {
                console_Error("Authentication error:" + data.error);
                localStorage.removeItem("user_token");
                window.location.href = "/login.html";
                return;
            }

            fetchLoanHistory(token);    

            if (!loanSection || !pendingApplication || !followingPaydayContainer) {
                console_Error("UI Error: Required elements missing in apply.html");
                return;
            }

            if (data.hasPendingLoan) {
                loanSection.style.display = "none";
                pendingApplication.style.display = "block";
            } else {
                loanSection.style.display = "block";
                pendingApplication.style.display = "none";
            }

            followingPaydayContainer.style.display = data.followingPaydayEligible ? "block" : "none";

        } catch (error) {
            console_Error("Error verifying loan status:" + error);
        }
    }

    async function fetchLoanHistory(token) {
        console_Log("Fetching loan history...");
        
        try {
            const response = await fetch("/server/api/loan_history.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ user_token: token })
            });

            const data = await response.json();

            if (data.error || !data.loans || data.loans.length === 0) {
                return;
            }

            const tableBody = document.querySelector("#loanHistoryTable tbody");
            tableBody.innerHTML = "";

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

            document.getElementById("loanHistoryContainer").style.display = "block";

        } catch (error) {
            console_Error("Error fetching loan history:" + error);
        }
    }

    verifyUserState(localStorage.getItem("user_token"));
    fetchUserData(); 

    if (amountInput) {
        amountInput.addEventListener("input", () => {
            let amount = parseFloat(amountInput.value.replace(/[^\d]/g, ""));
            console_Log("Loan amount entered:" + amount);

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

    logoutButton.addEventListener("click", async () => {
        localStorage.removeItem("user_token");

        try {
            await fetch("/server/api/logout.php", { method: "POST", credentials: "include" });
        } catch (error) {
            console_Error("Logout failed:" + error);
        }

        window.location.href = "/login.html";
    });
});
