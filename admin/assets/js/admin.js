document.addEventListener("DOMContentLoaded", function () {
    initializeWelcomeMessage();
    initializePasswordToggle();
    loadLoans(); // Fetch loans on page load
    setInterval(loadLoans, 180000); // Auto-refresh every 3 minutes
});

// Show Welcome Message Once Per Session
function initializeWelcomeMessage() {
    const messageBox = document.getElementById("successMsg");

    if (window.location.pathname.includes("login.php")) {
        sessionStorage.removeItem("welcomeMessageShown"); // Clear session on login page
    }

    if (messageBox) {
        if (!sessionStorage.getItem("welcomeMessageShown")) {
            sessionStorage.setItem("welcomeMessageShown", "true");

            messageBox.classList.add("show");
            messageBox.textContent = "Welcome to the admin panel!";

            setTimeout(() => {
                messageBox.classList.remove("show");
            }, 3000);
        }
    }
}

// Password Visibility Toggle
function initializePasswordToggle() {
    const passwordInput = document.querySelector("#password");

    if (passwordInput) {
        const toggleIcon = document.createElement("span");
        toggleIcon.textContent = "Show";
        toggleIcon.style.position = "absolute";
        toggleIcon.style.right = "10px";
        toggleIcon.style.top = "50%";
        toggleIcon.style.transform = "translateY(-50%)";
        toggleIcon.style.cursor = "pointer";
        toggleIcon.style.fontSize = "0.8rem";
        toggleIcon.style.zIndex = 2;

        const wrapper = document.createElement("div");
        wrapper.style.position = "relative";
        wrapper.style.display = "inline-block";
        wrapper.appendChild(passwordInput.cloneNode(true));
        wrapper.appendChild(toggleIcon);
        passwordInput.replaceWith(wrapper);

        const newPasswordInput = wrapper.querySelector("input");
        toggleIcon.addEventListener("mousedown", function () {
            newPasswordInput.type = "text";
            toggleIcon.textContent = "Hide";
        });

        toggleIcon.addEventListener("mouseup", function () {
            newPasswordInput.type = "password";
            toggleIcon.textContent = "Show";
        });

        toggleIcon.addEventListener("mouseleave", function () {
            newPasswordInput.type = "password";
            toggleIcon.textContent = "Show";
        });
    }
}

// Fetch Loans and Update the Table
function loadLoans() {
    const loanTable = document.getElementById("loanTableBody");
    if (!loanTable) return;

    fetch(`/server/api/get_loans.php?nocache=${new Date().getTime()}`, { cache: "no-store" })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loanTable.innerHTML = data.html.trim();
                attachStatusListeners();
                attachPrintListeners();
            } else {
                loanTable.innerHTML = `<tr><td colspan="7">${data.message}</td></tr>`;
            }
        })
        .catch(error => console.error("Error fetching loan applications:", error));
}

//  Attach Event Listeners for Loan Status Dropdowns
function attachStatusListeners() {
    document.querySelectorAll(".loan-status").forEach(select => {
        select.addEventListener("change", function () {
            const loanId = this.getAttribute("data-loan-id");
            const newStatus = this.value;

            fetch("/server/api/update_loan_status.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `loan_id=${loanId}&status=${newStatus}`,
            })
            .then(response => response.json())
            .then(data => {
                displayMessage(data.message, data.success);
                if (data.success) {
                    loadLoans(); // Reload table after status update
                }
            })
            .catch(error => console.error("Request failed:", error));
        });
    });
}

//  Attach Event Listeners for Print Buttons
function attachPrintListeners() {
    document.querySelectorAll(".print-loan").forEach(button => {
        button.addEventListener("click", function () {
            const loanId = this.getAttribute("data-loan-id");
            if (!loanId) {
                console.error("Loan ID not found");
                return;
            }

            console.log("Opening PDF for Loan ID:", loanId);
            window.open(`/server/api/generate_loan_pdf.php?loan_id=${loanId}`, "_blank");
        });
    });
}

function displayMessage(message, isSuccess) {
    const messageBox = document.getElementById("successMsg");

    if (messageBox) {
        messageBox.textContent = message;
        messageBox.classList.add("show");

        if (isSuccess) {
            messageBox.classList.add("success");
            messageBox.classList.remove("error");
        } else {
            messageBox.classList.add("error");
            messageBox.classList.remove("success");
        }

        setTimeout(() => {
            messageBox.classList.remove("show");
        }, 3000);
    }
}
