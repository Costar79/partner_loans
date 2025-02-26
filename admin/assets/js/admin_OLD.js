document.addEventListener("DOMContentLoaded", function () {
    const messageBox = document.getElementById("successMsg");

    // Ensure welcome message is only shown once per session
    if (window.location.pathname.includes("login.php")) {
        sessionStorage.removeItem("welcomeMessageShown"); // Clear session on login page
    }

    if (messageBox) {
        if (!sessionStorage.getItem("welcomeMessageShown")) {
            sessionStorage.setItem("welcomeMessageShown", "true"); // Mark as shown
            
            messageBox.classList.add("show"); // Make it visible
            messageBox.textContent = "Welcome to the admin panel!"; // Ensure content is set

            setTimeout(() => {
                messageBox.classList.remove("show"); // Fade out instead of removing
            }, 3000); // Hide after 3 seconds
        }
    }
});

// Function to Show Status Messages Without Page Shift
function displayMessage(message, isSuccess) {
    const messageBox = document.getElementById("successMsg");

    if (messageBox) {
        messageBox.textContent = message;
        messageBox.classList.add("show"); // Make it visible

        // Set message type (success or error)
        if (isSuccess) {
            messageBox.classList.add("success");
            messageBox.classList.remove("error");
        } else {
            messageBox.classList.add("error");
            messageBox.classList.remove("success");
        }

        // Hide after 3 seconds
        setTimeout(() => {
            messageBox.classList.remove("show"); // Fade out
        }, 3000);
    }
}

document.addEventListener("DOMContentLoaded", function () {
    const passwordInput = document.querySelector("#password");
    
    if (passwordInput) {
        const toggleIcon = document.createElement("span");
        toggleIcon.innerHTML = "👁";
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
            toggleIcon.innerHTML = "🙈";
        });
        
        toggleIcon.addEventListener("mouseup", function () {
            newPasswordInput.type = "password";
            toggleIcon.innerHTML = "👁";
        });
        
        toggleIcon.addEventListener("mouseleave", function () {
            newPasswordInput.type = "password";
            toggleIcon.innerHTML = "👁";
        });
    }
});

document.addEventListener("DOMContentLoaded", function () {
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

    function refreshLoanTable() {
        const loanTable = document.getElementById("loanTableBody");
        if (!loanTable) return;
    
        fetch(`/server/api/get_loans.php?nocache=${new Date().getTime()}`, { cache: "no-store" })
            .then(response => response.text())
            .then(data => {
                loanTable.innerHTML = data;
                attachPrintListeners();
                attachStatusListeners(); 
            })
            .catch(error => console.error("Error fetching loan applications:", error));
    }

    setInterval(refreshLoanTable, 180000);
    refreshLoanTable();
    attachPrintListeners();
    attachStatusListeners();
});

function attachStatusListeners() {
    document.querySelectorAll(".loan-status").forEach(select => {
        select.addEventListener("change", function () {
            const loanId = this.getAttribute("data-loan-id");
            const newStatus = this.value;

            //console.log("Updating Loan ID:", loanId); 
            //console.log("Sending Request Body:", `loan_id=${loanId}&status=${newStatus}`);

            fetch("/server/api/update_loan_status.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded",
                },
                body: `loan_id=${loanId}&status=${newStatus}`,
            })
            .then(response => response.text()) 
            .then(text => {
                //console.log("Raw Response from PHP:", text); 

                try {
                    const data = JSON.parse(text);
                    displayMessage(data.message, data.success);
                } catch (error) {
                    //console.error("Invalid JSON response:", text);
                    displayMessage("Unexpected server response", false);
                }
            })
            .catch(error => {
                console.error("Request failed:", error);
                displayMessage("An error occurred while updating the status.", false);
            });
        });
    });
}


