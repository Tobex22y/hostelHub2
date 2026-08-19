const btn = document.getElementById("adminLoginBtn");
const status = document.getElementById("adminStatus");

btn.addEventListener("click", async () => {
  const email = document.getElementById("adminEmail").value.trim();
  const password = document.getElementById("adminPassword").value;

  if (!email || !password) {
    status.textContent = "Please fill all fields";
    status.className = "status error";
    return;
  }

  try {
    const res = await fetch(
      `${CONFIG.API_BASE_URL}/admin/login.php`,
      {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        credentials: "include",
        body: JSON.stringify({ email, password }),
      },
    );

    const data = await res.json();

    if (data.success) {
      status.textContent = "Login successful...";
      status.className = "status success";

      setTimeout(() => {
        window.location.href = "admin.html";
      }, 1000);
    } else {
      status.textContent = data.message;
      status.className = "status error";
    }
  } catch (err) {
    console.error(err);
    status.textContent = "Server error";
    status.className = "status error";
  }
});
