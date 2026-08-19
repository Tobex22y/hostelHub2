// =============================================
// HostelHub - Main JavaScript File
// =============================================

// Auth Helper Functions
//Student Login Functions

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("signupForm");

  if (form) {
    form.addEventListener("submit", async function (e) {
      e.preventDefault();

      // your signup logic here
      const formData = new FormData();
      const password = document.getElementById("signupPassword").value;
      const confirm = document.getElementById("signupConfirm").value;

      // basic validation
      if (password !== confirm) {
        alert("Passwords do not match");
        return;
      }

      formData.append("fullname", document.getElementById("full-name").value);
      formData.append(
        "matric_number",
        document.getElementById("matric-number").value,
      );
      formData.append("email", document.getElementById("email").value);
      formData.append("phone", document.getElementById("phone").value);
      formData.append("gender", document.getElementById("signupGender").value);
      formData.append("password", password);
      formData.append("confirm_password", confirm);

      const image = document.getElementById("signupPicture").files[0];
      if (image) {
        formData.append("profile_image", image);
      }

      try {
        const res = await fetch(
          `${CONFIG.API_BASE_URL}/auth/signup.php`,
          {
            method: "POST",
            body: formData,
          },
        );

        const data = await res.json();

        if (data.success) {
          alert("Account created successfully!");
          window.location.href = "login.html";
        } else {
          alert(data.message);
        }
      } catch (err) {
        console.error(err);
        alert("Server error. Try again.");
      }
    });
  }
});
