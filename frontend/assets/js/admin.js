// Automatically update form price inputs matching designated halls selection
function applyDynamicHallPrice() {
  const hall = document.getElementById("roomHall").value;
  const priceInput = document.getElementById("price");
  if (!priceInput) return;
  
  const pricingMatrix = {
    'A': 50000,
    'B': 50000,
    'C1': 50000,
    'C2': 65000,
    'D': 55000,
    'E': 60000
  };
  
  priceInput.value = pricingMatrix[hall] || "";
}

// admin.js additions/modifications

let roomStatusChartInstance = null;

async function loadStats() {
  try {
    console.log("Fetching stats from:", `${CONFIG.API_BASE_URL}/admin/stats.php`);
    const res = await fetch(`${CONFIG.API_BASE_URL}/admin/stats.php`, { credentials: "include" });
    
    // Check if the response is valid JSON
    const text = await res.text();
    let data;
    try {
        data = JSON.parse(text);
    } catch (e) {
        console.error("The server did not return valid JSON. It returned:", text);
        return;
    }

    if (!data.success) {
        console.error("Database Error:", data.message);
        return;
    }

    // Update the UI Numbers
    if(document.getElementById("totalRooms")) document.getElementById("totalRooms").innerText = data.total_rooms;
    if(document.getElementById("totalBeds")) document.getElementById("totalBeds").innerText = data.total_beds;
    if(document.getElementById("occupiedBeds")) document.getElementById("occupiedBeds").innerText = data.occupied_beds;
    if(document.getElementById("availableBeds")) document.getElementById("availableBeds").innerText = data.available_beds;

    // Build the charts
    renderOccupancyDonutChart({
      occupied: data.occupied_beds,
      allocated: data.allocated_beds,
      available: data.available_beds
    });
    renderHallDistributionChart(data.halls || []);

  } catch (err) {
    console.error("Network or Script Error:", err);
  }
}

function renderOccupancyDonutChart(stats) {
  const canvas = document.getElementById("occupancyDonutChart");
  if (!canvas || typeof Chart === "undefined") return;

  const ctx = canvas.getContext("2d");

  if (window.occupancyDonutChartInstance) {
    window.occupancyDonutChartInstance.destroy();
  }

  const occupied  = Number(stats.occupied) || 0;
  const allocated = Number(stats.allocated) || 0;
  const available = Number(stats.available) || 0;
  const total = occupied + allocated + available;

  // Update the stat chips underneath the donut
  if (document.getElementById("statOccupied"))  document.getElementById("statOccupied").innerText  = `${occupied} Beds`;
  if (document.getElementById("statAllocated")) document.getElementById("statAllocated").innerText = `${allocated} Beds`;
  if (document.getElementById("statAvailable")) document.getElementById("statAvailable").innerText = `${available} Beds`;

  window.occupancyDonutChartInstance = new Chart(ctx, {
    type: "doughnut",
    data: {
      labels: ["Occupied Spaces", "Allocated (Pending Approval)", "Available Spaces"],
      datasets: [{
        data: [occupied, allocated, available],
        backgroundColor: ["#ef4444", "#f59e0b", "#22c55e"], // Red, Orange, Green
        borderWidth: 2,
        borderColor: "#ffffff"
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      cutout: "68%",
      plugins: {
        legend: { display: false }, // custom legend rendered in HTML below the chart
        tooltip: {
          callbacks: {
            label: function(item) {
              const val = item.raw;
              const perc = total > 0 ? ((val / total) * 100).toFixed(1) : "0.0";
              return ` ${item.label}: ${val} (${perc}%)`;
            }
          }
        }
      }
    }
  });
}

function renderHallDistributionChart(halls) {
  const canvas = document.getElementById("hallDistributionChart");
  if (!canvas || typeof Chart === "undefined") return;

  const ctx = canvas.getContext("2d");

  if (window.hallDistributionChartInstance) {
    window.hallDistributionChartInstance.destroy();
  }

  const labels    = halls.map(h => `Hall ${h.hall}`);
  const allocated = halls.map(h => Number(h.allocated) || 0);
  const available = halls.map(h => Number(h.available) || 0);
  const occupied  = halls.map(h => Number(h.occupied) || 0);

  window.hallDistributionChartInstance = new Chart(ctx, {
    type: "bar",
    data: {
      labels: labels,
      datasets: [
        { label: "Allocated Beds", data: allocated, backgroundColor: "#f59e0b", borderRadius: 4, maxBarThickness: 24 },
        { label: "Available Beds", data: available, backgroundColor: "#22c55e", borderRadius: 4, maxBarThickness: 24 },
        { label: "Occupied Beds",  data: occupied,  backgroundColor: "#ef4444", borderRadius: 4, maxBarThickness: 24 }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false }, // custom legend rendered in HTML above the chart
        tooltip: {
          callbacks: {
            label: function(item) { return ` ${item.dataset.label}: ${item.raw}`; }
          }
        }
      },
      scales: {
        y: { beginAtZero: true, ticks: { precision: 0 } }
      }
    }
  });
}

// Submit a configured room object data matrix to backend
async function createRoom() {
  const room_number = document.getElementById("roomNumber").value.trim();
  const room_type = document.getElementById("roomType").value.trim();
  const room_hall = document.getElementById("roomHall").value;
  const room_gender = document.getElementById("roomGender").value;
  const capacity = document.getElementById("capacity").value;
  const price = document.getElementById("price").value.trim();

  if (!room_number || !room_type || !room_hall || !capacity || !price) {
    alert("Please fill out all properties and verify the pricing mapping.");
    return;
  }

  try {
    const res = await fetch(`${CONFIG.API_BASE_URL}/admin/rooms.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ room_number, room_type, room_hall, room_gender, capacity, price }),
      credentials: "include"
    });

    const data = await res.json();

    if (data.success) {
      alert("Room configuration and bedspaces deployed cleanly!");
      document.getElementById("roomNumber").value = "";
      document.getElementById("roomType").value = "";
      document.getElementById("capacity").value = "4";
      document.getElementById("price").value = "";
      document.getElementById("roomHall").value = "";
      
      loadStats();
    } else {
      alert("Error building node: " + data.message);
    }
  } catch (err) {
    console.error("Error creating room:", err);
    alert("An explicit server processing error occurred.");
  }
}

// Render the stack list for manually flagged verification entries
async function loadRequests() {
  try {
    const res = await fetch(`${CONFIG.API_BASE_URL}/admin/get_requests.php`, { credentials: "include" });
    const data = await res.json();

    const box = document.getElementById("requests");
    if (!box) return;
    box.innerHTML = "";

    if (!data.requests || data.requests.length === 0) {
      box.innerHTML = `
        <div class="empty-state" style="padding: 20px; text-align: center; color: #6b7280;">
          <i class="fa-solid fa-circle-check" style="font-size: 32px; color: #10b981; margin-bottom: 8px;"></i>
          <h3>All Actions Settled</h3>
          <p style="font-size: 13px;">No entries currently require manual oversight verification.</p>
        </div>
      `;
      return;
    }

    data.requests.forEach((req) => {
      const card = document.createElement("div");
      card.className = "request-card";
      card.style.background = "#f9fafb";
      card.style.border = "1px solid #e5e7eb";
      card.style.borderRadius = "8px";
      card.style.padding = "15px";
      card.style.marginBottom = "12px";

      card.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
          <h3 style="margin:0; font-size:15px; font-weight:600; color:#1f2937;">${req.fullname}</h3>
          <span class="badge pending" style="background:#fef3c7; color:#d97706; padding:2px 8px; border-radius:12px; font-size:11px; font-weight:500;">Review Hold</span>
        </div>
        <div style="font-size:13px; color:#4b5563; line-height:1.5; margin-bottom:12px;">
          <div><i class="fa-solid fa-building" style="width:16px;"></i> <strong>Location:</strong> Hall ${req.hall || ''} - Room ${req.room_number} (Bed ${req.bed_number})</div>
          <div><i class="fa-solid fa-envelope" style="width:16px;"></i> <strong>Contact:</strong> ${req.email}</div>
        </div>
        <div style="display:flex; gap:8px;">
          <button onclick="approve(${req.id})" style="flex:1; background:#10b981; color:white; border:none; padding:6px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:500;">
            <i class="fa-solid fa-check"></i> Accept
          </button>
          <button onclick="reject(${req.id})" style="flex:1; background:#ef4444; color:white; border:none; padding:6px; border-radius:6px; cursor:pointer; font-size:12px; font-weight:500;">
            <i class="fa-solid fa-xmark"></i> Decline
          </button>
        </div>
      `;
      box.appendChild(card);
    });
  } catch (err) {
    console.error("Load requests error:", err);
  }
}

// Fetch ongoing student records matrix
async function loadActiveAllocations() {
  const tbody = document.getElementById("allocationsList");
  if (!tbody) return;

  try {
    const res = await fetch(`${CONFIG.API_BASE_URL}/admin/get_allocations.php`, { credentials: "include" });
    const data = await res.json();

    if (!data.success) {
      tbody.innerHTML = `<tr><td colspan="7" style="padding:20px; text-align:center; color:#ef4444;">${data.message}</td></tr>`;
      return;
    }

    if (data.allocations.length === 0) {
      tbody.innerHTML = `<tr><td colspan="7" style="padding:30px; text-align:center; color:#6b7280;"><i class="fa-solid fa-folder-open" style="font-size:24px; display:block; margin-bottom:5px;"></i>No assignments registered across active system indexes.</td></tr>`;
      return;
    }

    tbody.innerHTML = "";
    data.allocations.forEach(alloc => {
      const tr = document.createElement("tr");
      
      tr.innerHTML = `
        <td>${alloc.fullname}</td>
        <td><strong>${alloc.matric_number || 'N/A'}</strong></td>
        <td>Hall ${alloc.hall}</td>
        <td>Room ${alloc.room_number}</td>
        <td>Bed ${alloc.bed_number}</td>
        <td><span class="status-badge ${alloc.status || 'verified'}">${alloc.status}</span></td>
        <td>
          <button class="action-btn-terminate" onclick="terminateAllocation(${alloc.id})">
            <i class="fa-solid fa-user-minus"></i> Terminate
          </button>
        </td>
      `;
      tbody.appendChild(tr);
    });
  } catch (err) {
    console.error("Error loading allocations:", err);
  }
}

// Finalize decision metrics variables pipelines hooks
async function approve(id) {
  try {
    const res = await fetch(`${CONFIG.API_BASE_URL}/admin/approve_allocation.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ allocation_id: id })
    });
    const data = await res.json();
    if (data.success) {
      loadRequests();
      loadStats();
      loadActiveAllocations();
    } else {
      alert(data.message);
    }
  } catch (err) { console.error(err); }
}

async function reject(id) {
  try {
    const res = await fetch(`${CONFIG.API_BASE_URL}/admin/reject_allocation.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ allocation_id: id })
    });
    const data = await res.json();
    if (data.success) {
      loadRequests();
      loadStats();
      loadActiveAllocations();
    } else {
      alert(data.message);
    }
  } catch (err) { console.error(err); }
}

async function terminateAllocation(allocationId) {
  if (!confirm("Are you sure you want to terminate this student's allocation? This frees up the bed space immediately so they can reapply.")) return;

  try {
    const res = await fetch(`${CONFIG.API_BASE_URL}/admin/terminate_allocation.php`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ allocation_id: allocationId }),
      credentials: "include"
    });
    const data = await res.json();

    if (data.success) {
      alert("Allocation dropped cleanly. Bed capacity refreshed.");
      loadActiveAllocations();
      loadStats();
    } else {
      alert(data.message);
    }
  } catch (err) {
    console.error(err);
  }
}

async function resetDB() {
  if (!confirm("Are you sure? This will delete ALL data structural profiles instantly!")) return;
  try {
    const res = await fetch(`${CONFIG.API_BASE_URL}/admin/reset_database.php`, { method: "POST", credentials: "include" });
    const data = await res.json();
    if (data.success) {
      alert("Database context re-instantiated successfully.");
      location.reload();
    } else {
      alert(data.message);
    }
  } catch (err) { console.error(err); }
}

// Global Core Sync Initializers
document.addEventListener("DOMContentLoaded", () => {
  loadStats();
  loadRequests();
  loadActiveAllocations();
  loadTickets();

  // Unified background routine execution sequence (Runs every 10 seconds)
  setInterval(() => {
    loadRequests();
    loadStats();
    loadActiveAllocations();
    loadTickets();
  }, 10000);
});

// ══════════════════════════════════════════
//  MAINTENANCE TICKETS — ADMIN
// ══════════════════════════════════════════

async function loadTickets(force = false) {
  const box = document.getElementById('tickets-admin-list');
  if (!box) return;

  // If the admin is actively typing a response (or has a status dropdown open),
  // skip the auto-refresh so we don't wipe out unsaved input mid-keystroke.
  // The manual Refresh button and a successful Update call this with force=true.
  const activeEl = document.activeElement;
  const isEditingTicket = activeEl && box.contains(activeEl) &&
    (activeEl.tagName === 'TEXTAREA' || activeEl.tagName === 'SELECT');
  if (!force && isEditingTicket) {
    return;
  }

  try {
    const res  = await fetch(`${CONFIG.API_BASE_URL}/admin/get_tickets.php`, { credentials: 'include' });
    const data = await res.json();

    if (!data.success || !data.tickets.length) {
      box.innerHTML = `<div style="text-align:center;padding:40px;color:#6b7280;">
        <i class="fa-solid fa-circle-check" style="font-size:32px;color:#10b981;display:block;margin-bottom:8px;"></i>
        No maintenance tickets at this time.
      </div>`;
      return;
    }

    const priorityColors = { low:'#22c55e', medium:'#f59e0b', high:'#f97316', urgent:'#ef4444' };
    const statusLabels   = { open:'Open', in_progress:'In Progress', resolved:'Resolved', closed:'Closed' };

    box.innerHTML = data.tickets.map(t => {
      const pc   = priorityColors[t.priority] || '#94a3b8';
      const date = new Date(t.created_at).toLocaleDateString('en-GB', { day:'numeric', month:'short', year:'numeric' });
      const room = t.room_number ? `Room ${t.room_number}` : '';
      const bed  = t.bed_number  ? ` · Bed ${t.bed_number}` : '';

      return `
        <div style="border:1px solid #e5e7eb;border-radius:10px;padding:16px;margin-bottom:12px;background:#fff;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
            <div style="flex:1;min-width:200px;">
              <div style="font-size:14px;font-weight:700;color:#0f172a;margin-bottom:3px;">${t.title}</div>
              <div style="font-size:12px;color:#64748b;margin-bottom:6px;">
                <strong>${t.fullname}</strong> · ${t.matric_number}
                ${room ? `· <i class="fa-solid fa-building" style="margin:0 2px;"></i>${room}${bed}` : ''}
              </div>
              <div style="font-size:12px;color:#64748b;margin-bottom:8px;">
                <i class="fa-solid fa-location-dot"></i> ${t.location} &nbsp;·&nbsp;
                <i class="fa-solid fa-tag"></i> ${t.category} &nbsp;·&nbsp;
                <i class="fa-regular fa-calendar"></i> ${date}
              </div>
              <div style="font-size:13px;color:#334155;line-height:1.5;margin-bottom:10px;">${t.description}</div>

              ${t.admin_note ? `
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:10px 12px;margin-bottom:10px;">
                  <div style="font-size:10px;font-weight:700;color:#15803d;text-transform:uppercase;letter-spacing:.05em;margin-bottom:3px;">
                    <i class="fa-solid fa-comment-dots"></i> Your Response
                  </div>
                  <div style="font-size:13px;color:#0f172a;">${t.admin_note}</div>
                </div>` : ''}
            </div>

            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0;">
              <span style="background:${pc}22;color:${pc};padding:3px 10px;border-radius:999px;font-size:11px;font-weight:700;border:1px solid ${pc}55;">
                ${t.priority.toUpperCase()}
              </span>
              <span style="background:#f1f5f9;color:#475569;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;">
                ${statusLabels[t.status] || t.status}
              </span>
            </div>
          </div>

          <!-- RESPOND FORM -->
          <div style="border-top:1px solid #f1f5f9;padding-top:12px;display:grid;grid-template-columns:1fr auto auto auto;gap:8px;align-items:start;">
            <textarea id="note-${t.id}" rows="2" placeholder="Type a response to the student (optional)…"
              style="padding:9px 12px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;resize:none;width:100%;">${t.admin_note || ''}</textarea>
            <select id="status-${t.id}"
              style="padding:9px 10px;border:1.5px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;height:fit-content;">
              <option value="open"        ${t.status==='open'        ?'selected':''}>Open</option>
              <option value="in_progress" ${t.status==='in_progress' ?'selected':''}>In Progress</option>
              <option value="resolved"    ${t.status==='resolved'    ?'selected':''}>Resolved</option>
              <option value="closed"      ${t.status==='closed'      ?'selected':''}>Closed</option>
            </select>
            <button onclick="updateTicket(${t.id})"
              style="padding:9px 16px;background:#15803d;color:white;border:none;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;height:fit-content;">
              <i class="fa-solid fa-paper-plane"></i> Update
            </button>
            <button onclick="deleteTicket(${t.id})" title="Delete ticket"
              style="padding:9px 12px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;white-space:nowrap;height:fit-content;">
              <i class="fa-solid fa-trash"></i>
            </button>
          </div>
        </div>`;
    }).join('');

  } catch (err) {
    box.innerHTML = '<p style="color:#ef4444;text-align:center;padding:20px;">Failed to load tickets.</p>';
    console.error(err);
  }
}

async function updateTicket(ticketId) {
  const note   = document.getElementById(`note-${ticketId}`)?.value?.trim() || '';
  const status = document.getElementById(`status-${ticketId}`)?.value || 'open';

  try {
    const res  = await fetch(`${CONFIG.API_BASE_URL}/admin/update_ticket.php`, {
      method:      'POST',
      credentials: 'include',
      headers:     { 'Content-Type': 'application/json' },
      body:        JSON.stringify({ ticket_id: ticketId, status, admin_note: note }),
    });
    const data = await res.json();

    if (data.success) {
      const emailResult = data.notifications?.email;
      if (emailResult && !emailResult.sent) {
        alert('Ticket updated, but the email was not sent.\n\n' + (emailResult.error || 'Check the server email configuration.'));
      }
      loadTickets(true);
    } else {
      alert('Update failed: ' + data.message);
    }
  } catch (err) {
    alert('Network error. Please try again.');
    console.error(err);
  }
}

async function deleteTicket(ticketId) {
  if (!confirm('Delete this ticket permanently? This cannot be undone.')) return;

  try {
    const res  = await fetch(`${CONFIG.API_BASE_URL}/admin/delete_ticket.php`, {
      method:      'POST',
      credentials: 'include',
      headers:     { 'Content-Type': 'application/json' },
      body:        JSON.stringify({ ticket_id: ticketId }),
    });
    const data = await res.json();

    if (data.success) {
      loadTickets(true);
    } else {
      alert('Delete failed: ' + data.message);
    }
  } catch (err) {
    alert('Network error. Please try again.');
    console.error(err);
  }
}