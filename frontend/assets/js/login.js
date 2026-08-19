document.addEventListener("DOMContentLoaded", () => {
  const loginForm = document.getElementById("loginForm");

  if (loginForm) {
    loginForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      await loginStudent();
    });
  }
});

async function loginStudent() {
  const matric_number = document.getElementById("loginMatric").value;
  const password = document.getElementById("loginPassword").value;

  try {
    const res = await fetch(`${CONFIG.API_BASE_URL}/auth/login.php`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      credentials: "include",
      body: JSON.stringify({
        matric_number,
        password,
      }),
    });

    const data = await res.json();

    if (data.success) {
      alert("Login successful");

      // OPTIONAL (not required for session system)
      // localStorage.setItem("user", JSON.stringify(data.user));

      window.location.href = "dashboard.html";
    } else {
      alert(data.message);
      console.log(data);
    }
  } catch (err) {
    console.error(err);
    alert("Login failed");
  }
}
