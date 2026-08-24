// ==============================
// Student Dashboard Script (FINAL CLEAN VERSION)
// ==============================

// Safely resolve CONFIG (some pages set window.CONFIG in ../config.js)
const cfg = (typeof CONFIG !== 'undefined'
  ? CONFIG
  : (typeof window !== 'undefined' ? window.CONFIG : undefined)) || { API_BASE_URL: window.location.origin + '/backend/api' };
const API = cfg.API_BASE_URL;

function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  if (!sidebar) return;

  const isOpen = sidebar.classList.contains('is-open') ||
    (!sidebar.classList.contains('is-closed') && window.innerWidth > 900);
  const menuButton = document.querySelector('.btn-menu');

  sidebar.classList.toggle('is-open', !isOpen);
  sidebar.classList.toggle('is-closed', isOpen);
  document.body.classList.toggle('sidebar-closed', isOpen);

  if (menuButton) {
    menuButton.setAttribute('aria-expanded', String(!isOpen));
  }
}

// ==============================
// GLOBAL STATE
// ==============================

let currentStudentEmail = null;
let currentAllocation = null;
let currentPaymentReference = null;
let currentStudentGender = null;   // ← NEW: populated by loadDashboard

// ==============================
// INIT
// ==============================

document.addEventListener("DOMContentLoaded", () => {
  loadDashboard();
  loadRooms();
  loadAllocation();

  // 🔥 REAL-TIME AUTO REFRESH (every 5 seconds)
  setInterval(() => {
    loadAllocation();
  }, 5000);

  // If returning from rooms.html to view a specific room, open modal
  const openRoomId = localStorage.getItem('openRoomId');
  const openRoomNumber = localStorage.getItem('openRoomNumber');
  if (openRoomId) {
    // openRoom expects numbers for id; ensure proper type
    openRoom(Number(openRoomId), openRoomNumber || '');
    localStorage.removeItem('openRoomId');
    localStorage.removeItem('openRoomNumber');
  }
});

function extractHallBlock(roomNumber, hallField) {
  // 1. Check if the backend explicitly provided a non-empty hall column
  if (hallField && String(hallField).trim().length > 0) {
    return String(hallField).trim().toUpperCase();
  }
  
  // 2. If missing, look precisely at the start of the room designation string
  if (roomNumber && roomNumber !== 'N/A') {
    const matches = String(roomNumber).trim().toUpperCase().match(/^([A-Z]+[0-9]*)/);
    if (matches) {
      let extracted = matches[1];
      // If it accidentally parsed 'RM' as a hall name prefix, it's invalid
      if (extracted === 'RM') {
        return 'UNKNOWN';
      }
      return extracted;
    }
  }
  
  return 'UNKNOWN'; // No fallback default hall allowed
}
// ==============================
// DASHBOARD INFO
// ==============================

async function loadDashboard() {
  try {
    const res = await fetch(`${API}/auth/dashboard.php`, {
      credentials: "include",
    });

    const data = await res.json();

    if (!data.success) {
      // If user is not logged in, don't redirect immediately —
      // still try to load a public halls summary so the dashboard shows useful info.
      console.warn('Not logged in — loading public halls summary');
      loadPublicHalls();
      return;
    }

    const student = data.student;
    currentStudentEmail = student.email;
    currentStudentGender = (student.gender || '').toLowerCase();   // ← NEW

    // set welcome name and avatar
    const welcomeEl = document.getElementById('welcomeName');
    if (welcomeEl) welcomeEl.innerText = `Welcome, ${student.fullname}`;

    const avatarEl = document.getElementById('studentAvatar');
    if (avatarEl) {
      if (student.profile_image) {
        avatarEl.src = new URL('../uploads/profiles/' + encodeURIComponent(student.profile_image), API + '/').href;
      } else {
        avatarEl.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(student.fullname) + '&background=2d7a3e&color=fff';
      }
      avatarEl.onerror = function() {
        this.onerror = null;
        this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(student.fullname) + '&background=2d7a3e&color=fff';
      };
    }

    // Render halls summary if provided
    try {
      const hallsBox = document.getElementById('hallsContainer');
      if (hallsBox) {
        hallsBox.innerHTML = '';

        if (data.halls_summary && data.halls_summary.length) {
          data.halls_summary.forEach((hall) => {
            const div = document.createElement('div');
            div.className = 'hall-card';

            if (hall.not_available) {
              // Hall exists in the gender group but admin hasn't released any rooms yet
              div.innerHTML = `
                <div class="hall-card-info">
                  <h3>Hall ${hall.id}</h3>
                  <p style="color:#94a3b8;font-style:italic;">Not yet available</p>
                </div>
                <div class="hall-badges">
                  <span class="hbadge" style="background:#f1f5f9;color:#94a3b8;border:1px dashed #cbd5e1;">
                    <i class="fa-solid fa-clock"></i> Coming Soon
                  </span>
                </div>
              `;
            } else {
              div.innerHTML = `
                <div class="hall-card-info">
                  <h3>Hall ${hall.id}</h3>
                  <p>${hall.total_rooms} rooms &middot; ${hall.available_rooms} available</p>
                </div>
                <div class="hall-badges">
                  <span class="hbadge avail"><i class="fa-solid fa-bed"></i> ${hall.available_beds}</span>
                  <span class="hbadge occup"><i class="fa-solid fa-ban"></i> ${hall.occupied_beds}</span>
                  <button class="btn-view-rooms" onclick="goToRoomPageWithHall('${hall.id}')">View <i class="fa-solid fa-chevron-right"></i></button>
                </div>
              `;
            }

            hallsBox.appendChild(div);
          });
        } else {
          hallsBox.innerHTML = '<p>No halls data available.</p>';
        }
      }
    } catch (err) {
      console.error('Error rendering halls summary', err);
    }

    const roomStatusEl = document.getElementById("roomStatus");
    if (roomStatusEl) {
      roomStatusEl.innerText = data.allocation ? "Allocated" : "Not Assigned";
    }

    const paymentStatusEl = document.getElementById("paymentStatus");
    if (paymentStatusEl) {
      paymentStatusEl.innerText = (data.allocation?.status === "active" || data.allocation?.status === "paid") ? "Paid" : "Pending";
    }

    // ── MY PASS nav item: only show when there's an active/paid allocation ──
    const allocStatus = data.allocation && data.allocation.status
      ? String(data.allocation.status).toLowerCase()
      : null;
    const hasActiveAllocation = !!(allocStatus && ['paid', 'active', 'approved'].includes(allocStatus));

    const navEpassLink  = document.getElementById('nav-epass-link');
    const navEpassLabel = document.getElementById('nav-epass-label');
    if (navEpassLink)  navEpassLink.style.display  = hasActiveAllocation ? 'flex' : 'none';
    if (navEpassLabel) navEpassLabel.style.display = hasActiveAllocation ? 'block' : 'none';

    // ── Deep links from room.html's nav (My Pass / Maintenance) ──
    const params = new URLSearchParams(window.location.search);
    let usedDeepLink = false;
    if (params.get('openEpass') === '1' && hasActiveAllocation && typeof openEpass === 'function') {
      openEpass();
      usedDeepLink = true;
    }
    if (params.get('section') === 'maintenance' && typeof showSection === 'function') {
      showSection('maintenance');
      usedDeepLink = true;
    }
    if (usedDeepLink) {
      // Strip the query params so a refresh doesn't keep re-triggering them
      window.history.replaceState({}, '', window.location.pathname);
    }
  } catch (err) {
    console.error(err);
  }
}

// ==============================
// PUBLIC HALLS SUMMARY (no-auth fallback)
// ==============================
async function loadPublicHalls() {
  try {
    const res = await fetch(`${API}/student/rooms.php`);
    const data = await res.json();

    const hallsBox = document.getElementById('hallsContainer');
    if (!hallsBox) return;

    if (!data || !data.rooms || data.rooms.length === 0) {
      hallsBox.innerHTML = '<p>No halls data available.</p>';
      return;
    }

    // Aggregate rooms into halls
    const map = {};
    data.rooms.forEach((room) => {
      const hid = extractHallBlock(room.room_number, room.hall) || 'Unknown';
      if (!map[hid]) {
        map[hid] = { id: hid, total_rooms: 0, available_rooms: 0, full_rooms: 0, total_beds: 0, available_beds: 0, occupied_beds: 0 };
      }

      map[hid].total_rooms += 1;
      map[hid].total_beds += (room.capacity || 0);
      map[hid].available_beds += (room.available || 0);
      map[hid].occupied_beds += (room.occupied || 0);
      if ((room.available || 0) > 0) map[hid].available_rooms += 1; else map[hid].full_rooms += 1;
    });

    const halls_summary = Object.values(map).sort((a,b)=> String(a.id).localeCompare(String(b.id)));

    hallsBox.innerHTML = '';
    // compute aggregated totals for overall occupancy bar
    const totals = halls_summary.reduce((acc, h) => {
      acc.total_beds += (h.total_beds || 0);
      acc.occupied_beds += (h.occupied_beds || 0);
      acc.available_beds += (h.available_beds || 0);
      return acc;
    }, { total_beds: 0, occupied_beds: 0, available_beds: 0 });

    try {
      const bar = document.getElementById('pub-occ-bar');
      const pctLbl = document.getElementById('pub-occ-pct');
      const last = document.getElementById('last-updated');
      if (bar && pctLbl) {
        const pct = totals.total_beds > 0 ? Math.round((totals.occupied_beds / totals.total_beds) * 100) : 0;
        bar.style.width = pct + '%';
        pctLbl.textContent = pct + '%';
        if (pct >= 90) bar.style.background = '#d03050';
        else if (pct >= 65) bar.style.background = '#d49a0a';
        else bar.style.background = '#2d7a3e';
      }
      if (last) last.textContent = 'Last updated: ' + new Date().toLocaleTimeString();
    } catch (e) {
      // ignore visual update failures
    }
    halls_summary.forEach((hall) => {
      const div = document.createElement('div');
      div.className = 'hall-card';
      div.innerHTML = `
        <div class="hall-card-info">
          <h3>Hall ${hall.id}</h3>
          <p>${hall.total_rooms} rooms &middot; ${hall.available_rooms} available</p>
        </div>
        <div class="hall-badges">
          <span class="hbadge avail"><i class="fa-solid fa-bed"></i> ${hall.available_beds}</span>
          <span class="hbadge occup"><i class="fa-solid fa-ban"></i> ${hall.occupied_beds}</span>
          <button class="btn-view-rooms" onclick="goToRoomPageWithHall('${hall.id}')">View <i class="fa-solid fa-chevron-right"></i></button>
        </div>
      `;
      hallsBox.appendChild(div);
    });

  } catch (err) {
    console.error('Error loading public halls', err);
    const hallsBox = document.getElementById('hallsContainer');
    if (hallsBox) hallsBox.innerHTML = '<p>No halls data available.</p>';
  }
}

function goToRoomPageWithHall(hallId) {
  try {
    localStorage.setItem('selectedHall', hallId);
  } catch (e) {
    console.warn('Could not persist selectedHall', e);
  }

  // Derive pages base from API constant to ensure correct host/path
  try {
    const path = window.location.pathname || '';
    if (path.includes('/frontend/pages/')) {
      window.location.href = 'room.html';
      return;
    }

    const pagesBase = API.replace('/backend/api', '/frontend/pages');
    window.location.href = pagesBase + '/room.html';
  } catch (e) {
    window.location.href = window.location.origin + '/frontend/pages/room.html';
  }
}

// ==============================
// LOAD ROOMS
// ==============================

async function loadRooms() {
  try {
    const selectedHall = localStorage.getItem('selectedHall');
    const appliedHall = selectedHall || null;
    if (selectedHall) {
      localStorage.removeItem('selectedHall');
    }

    // Build URL — include gender filter so students only see their gender's rooms
    let url = `${API}/student/rooms.php`;
    const params = new URLSearchParams();
    if (appliedHall) params.set('hall', appliedHall);
    if (currentStudentGender) params.set('gender', currentStudentGender);
    if (params.toString()) url += '?' + params.toString();

    const res = await fetch(url, { credentials: "include" });
    const data = await res.json();

    const container = document.getElementById("roomsContainer");
    const filterBanner = document.getElementById("activeHallFilter");
    if (!container) return; // not on rooms page

    if (filterBanner) {
      if (appliedHall) {
        filterBanner.style.display = 'block';
        filterBanner.innerHTML = `Showing Hall <strong>${appliedHall}</strong> &mdash; <a href="#" onclick="clearHallFilter(event)">Clear filter</a>`;
      } else {
        filterBanner.style.display = 'none';
        filterBanner.innerHTML = '';
      }
    }

    container.innerHTML = "";

    if (!data.rooms || !data.rooms.length) {
      container.innerHTML = "<p>No rooms available.</p>";
      return;
    }

    data.rooms.forEach((room) => {
      const div = document.createElement('div');
      div.className = 'room-card';
      const genderTag = room.gender === 'female' ? 'tag-female' : (room.gender === 'male' ? 'tag-male' : '');
      const accurateHall = extractHallBlock(room.room_number, room.hall);
      
      div.innerHTML = `
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;">
          <h3>Room ${room.room_number}</h3>
          ${room.gender ? `<span class="badge ${genderTag}" style="text-transform:capitalize;">${room.gender}</span>` : ''}
        </div>
        <div class="room-meta">
          <span>${room.room_type}</span>
          <span>Hall ${accurateHall}</span>
        </div>
        <div style="display:flex;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
          <span class="badge available"><i class="fa-solid fa-bed"></i> ${room.available} free</span>
          <span class="badge occupied"><i class="fa-solid fa-ban"></i> ${room.occupied} taken</span>
        </div>
        <button class="btn-view-rooms" onclick="viewBeds(${room.id}, '${room.room_number}', '${room.room_type}', '${accurateHall}')">View <i class="fa-solid fa-chevron-right"></i></button>
      `;
      container.appendChild(div);
    });

  } catch (err) {
    console.error(err);
  }
}

function clearHallFilter(event) {
  event.preventDefault();
  const filterBanner = document.getElementById('activeHallFilter');
  if (filterBanner) {
    filterBanner.style.display = 'none';
    filterBanner.innerHTML = '';
  }
  loadRooms();
}

// ==============================
// CLOSE MODAL
// ==============================

function closeModal() {
  const modal = document.getElementById("roomModal");
  if (modal) modal.style.display = "none";
}

// ==============================
// VIEW BEDS / NAV TO RESERVE
// ==============================

function viewBeds(roomId, roomNumber, roomType, hall) {
  const accurateHall = extractHallBlock(roomNumber, hall);
  try {
    localStorage.setItem('reserveRoom', JSON.stringify({
      id:        roomId,
      number:    roomNumber,
      room_type: roomType || 'Standard',
      hall:      accurateHall, 
      block:     accurateHall,
    }));
  } catch (e) {
    console.warn('Could not write selected room to localStorage', e);
  }

  window.location.href = 'reserve.html';
}

window.onclick = function (e) {
  const modal = document.getElementById("roomModal");
  if (e.target === modal) closeModal();
};

// ==============================
// LOAD ALLOCATION (DYNAMIC TRUTH MATRIX REFACTOR)
// ==============================
let countdownInterval = null; 
let activeTimerAllocationId = null; 

// UNIFIED PRICE MATRIX (Direct copy from reserve.js)
const HALL_PRICES_MAP = {
  'A':  50000,
  'B':  50000,
  'C1': 50000,
  'C2': 65000,
  'D':  55000,
  'E':  60000,
};

// ==============================
// LOAD ALLOCATION (STRICT MATCHING MATRIX — ZERO FALLBACKS)
// ==============================
async function loadAllocation() {
  try {
    const res = await fetch(`${API}/student/allocation.php`, {
      credentials: "include",
    });
    const data = await res.json();

    const box = document.getElementById("allocationBox") || document.getElementById("allocation-box");
    if (!box) return; 

    if (!data.success || !data.allocation) {
      currentAllocation = null;
      if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
        activeTimerAllocationId = null;
      }
      box.innerHTML = `
        <div class="alloc-empty" style="text-align: center; padding: 30px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px;">
          <div class="alloc-icon" style="font-size: 32px; color: #94a3b8; margin-bottom: 10px;"><i class="fa-solid fa-bed"></i></div>
          <p style="color: #64748b; font-size: 14px; margin-bottom: 15px;">You haven't reserved a bed space yet.</p>
          <button class="btn-book-now" onclick="goToRooms()" style="padding: 10px 20px; background: #2d7a3e; color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Book Bed Space <i class="fa-solid fa-arrow-right"></i></button>
        </div>
      `;
      loadRooms();
      return;
    }

    currentAllocation = data.allocation;
    currentPaymentReference = data.allocation.payment_reference || data.allocation.reference;
    
    const alloc = data.allocation;
    const allocStatus = alloc.status ? alloc.status.toLowerCase() : 'pending';

    const roomNum = alloc.room_number || alloc.room_name || alloc.number || 'N/A';
    const bedNum  = alloc.bed_number || alloc.bed_name || alloc.bed_id || 'N/A';

    // ── DISPLAY: Hall + Room + Bed ──────────────────────────────────
    // Use hall column from DB first, fall back to parsing room_number
    const hallId = extractHallBlock(roomNum, alloc.hall || alloc.block || alloc.hall_id);

    // Room price: prefer DB price column, fall back to hall price map
    const roomPrice = alloc.price ? Number(alloc.price) : (
      hallId !== 'UNKNOWN' ? HALL_PRICES_MAP[hallId] || 50000 : 50000
    );
    const priceStr = hallId !== 'UNKNOWN' ? '₦' + roomPrice.toLocaleString() : 'Price Pending';

    // Display: "Hall C2 · Room C2-01"
    const combinedRoomDisplay = hallId !== 'UNKNOWN'
      ? `Hall ${hallId} &middot; Room ${roomNum}`
      : roomNum;

    const combinedBedDisplay = bedNum !== 'N/A' ? `Bed ${bedNum}` : 'N/A';

    // Update Top Header Summary Stat Cards
    const hostEl = document.getElementById("hostelBlock") || document.getElementById("hallBlock");
    if (hostEl) hostEl.innerText = hallId !== 'UNKNOWN' ? `Hall ${hallId}` : roomNum;

    const bedEl = document.getElementById("bedBlock") || document.getElementById("bedNumber");
    if (bedEl) bedEl.innerText = combinedBedDisplay;

    // Evaluate Paid / Active allocations
    if (allocStatus === 'active' || allocStatus === 'paid') {
      if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
        activeTimerAllocationId = null;
      }
      if (document.getElementById('receipt-section'))          document.getElementById('receipt-section').style.display = 'block';
      if (document.getElementById('room-selection-section'))  document.getElementById('room-selection-section').style.display = 'none';
      if (document.getElementById('allocation-card'))          document.getElementById('allocation-card').style.display = 'block';
      
      box.innerHTML = `
        <div class="allocation-card" style="padding: 20px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
          <h3 style="margin-top: 0; color: #0f172a; font-size: 16px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;"><i class="fa-solid fa-circle-check" style="color: #16a34a;"></i> Your Secured Allocation</h3>
          <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px;">
            <span style="color: #64748b;">Hostel Assignment:</span>
            <strong style="color: #0f172a;">${combinedRoomDisplay}</strong>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px;">
            <span style="color: #64748b;">Bedspace Unit:</span>
            <strong style="color: #0f172a;">${combinedBedDisplay}</strong>
          </div>
          <div style="display: flex; justify-content: space-between; padding: 10px 0; margin-bottom: 15px; font-size: 14px;">
            <span style="color: #64748b;">Verification Status:</span>
            <span style="background: #dcfce7; color: #15803d; padding: 2px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; text-transform: uppercase;">VERIFIED</span>
          </div>
          <button class="action-btn" onclick="printReceipt()" style="width: 100%; padding: 11px; background: #15803d; color: #ffffff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: background 0.2s;">
            <i class="fa-solid fa-print"></i> View & Print Receipt
          </button>
        </div>
      `;
    } else {
      if (document.getElementById('receipt-section')) document.getElementById('receipt-section').style.display = 'none';

      // Countdown Config (Exact 2 minutes)
      const creationTime = alloc.created_at ? new Date(alloc.created_at.replace(/-/g, "/")).getTime() : Date.now();
      const targetExpiration = creationTime + (2 * 60 * 1000); 

      // Guard conditional protects running active interval engines from visual reset loops
      if (activeTimerAllocationId !== alloc.id) {
        activeTimerAllocationId = alloc.id;
        if (countdownInterval) clearInterval(countdownInterval);

        box.innerHTML = `
          <div style="padding:20px;background:#fff;border:1px solid #e2e8f0;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.05);">
            <h3 style="margin:0 0 14px;color:#0f172a;font-size:15px;display:flex;align-items:center;justify-content:space-between;gap:8px;">
              <span><i class="fa-solid fa-clock" style="color:#d97706;"></i> Pending Reservation</span>
              <span id="timerWrapper" style="font-size:12px; background:#fef2f2; color:#dc2626; padding:3px 8px; border-radius:6px; font-weight:bold; font-family:monospace;">02:00</span>
            </h3>
            <div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:14px;">
              <span style="color:#64748b;">Reserved Room:</span>
              <strong style="color:#0f172a;">${combinedRoomDisplay}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:14px;">
              <span style="color:#64748b;">Reserved Bed:</span>
              <strong style="color:#0f172a;">${combinedBedDisplay}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid #f1f5f9;font-size:14px;">
              <span style="color:#64748b;">Amount Due:</span>
              <strong style="color:#15803d; font-size:15px;">${priceStr}</strong>
            </div>
            <div style="display:flex;justify-content:space-between;padding:9px 0;margin-bottom:16px;font-size:14px;">
              <span style="color:#64748b;">Status:</span>
              <span style="background:#fef3c7;color:#b45309;padding:2px 10px;border-radius:999px;font-weight:700;font-size:12px;">Awaiting Payment</span>
            </div>
            <div id="actionButtonsContainer" style="display:flex;flex-direction:column;gap:8px;">
              <button onclick="payForAllocation(${alloc.id})" style="width:100%;padding:12px;background:#16a34a;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:14px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit;">
                <i class="fa-solid fa-credit-card"></i> Complete Payment — ${priceStr}
              </button>
              <button onclick="cancelAllocation(${alloc.id})" style="width:100%;padding:11px;background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;font-family:inherit;">
                <i class="fa-solid fa-xmark"></i> Cancel Reservation
              </button>
            </div>
          </div>
        `;

        const timerDisplay = document.getElementById("timerWrapper");
        if (timerDisplay) {
          countdownInterval = setInterval(async () => {
            const now = Date.now();
            const distance = targetExpiration - now;

            if (distance <= 0) {
              clearInterval(countdownInterval);
              countdownInterval = null;
              activeTimerAllocationId = null;
              timerDisplay.textContent = "EXPIRED";
              
              const actionContainer = document.getElementById("actionButtonsContainer");
              if (actionContainer) {
                actionContainer.innerHTML = `
                  <div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:8px;text-align:center;font-weight:600;font-size:13px;">
                    <i class="fa-solid fa-hourglass-end"></i> Hold Time Expired. Bed space released automatically.
                  </div>`;
              }
              await cancelAllocation(alloc.id); 
            } else {
              const minutes = Math.floor(distance / 60000);
              const seconds = Math.floor((distance % 60000) / 1000);
              timerDisplay.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
          }, 1000);
        }
      }
    }
  } catch (err) {
    console.error("Error compiling allocation profile view:", err);
  }
}
// ==============================
// PAYMENT TRIGGER
// ==============================

// ── HALL PRICE MAP ──────────────────────────────────────

function getHallPrice(hallId) {
  const key = String(hallId || '').trim().toUpperCase();
  return HALL_PRICES_MAP[key] || 50000;
}

function payForAllocation(allocationId) {
  if (!currentAllocation) return alert("No allocation found.");

  if (currentAllocation.status === "paid" || currentAllocation.status === "active") {
    return alert("This allocation has already been paid.");
  }

  if (currentAllocation.status === "rejected") {
    return alert("This reservation was rejected. Please book a new bed.");
  }

  if (!currentStudentEmail) {
    return alert("Session expired. Please log in again.");
  }

  const hallId   = extractHallBlock(currentAllocation.room_number, currentAllocation.hall);
  const price    = getHallPrice(hallId);
  const ref      = 'HH-' + allocationId + '-' + Date.now();

  const handler = PaystackPop.setup({
    key:      'pk_test_ee4c2475b4ddbee902eb8a12801a4adc91b04340',
    email:    currentStudentEmail,
    amount:   price * 100,
    currency: 'NGN',
    ref:      ref,

    onClose: function () {
      alert('Payment window closed. Your reservation is still held. You can complete payment from your dashboard.');
    },

    callback: function (response) {
      verifyDashboardPayment(response.reference, allocationId);
    }
  });

  handler.openIframe();
}

async function verifyDashboardPayment(reference, allocationId) {
  const btn = document.querySelector(`[onclick="payForAllocation(${allocationId})"]`);
  if (btn) { btn.disabled = true; btn.textContent = 'Verifying…'; }

  try {
    const res = await fetch(`${API}/student/verify_payment.php`, {
      method:      'POST',
      credentials: 'include',
      headers:     { 'Content-Type': 'application/json' },
      body:        JSON.stringify({ reference, allocation_id: allocationId }),
    });
    const data = await res.json();

    if (data.success) {
      alert('✅ Payment confirmed! Your bed space is secured.');
      window.open(`${API}/student/receipt.php?reference=${encodeURIComponent(reference)}`, '_blank');
      loadAllocation();
    } else {
      alert('❌ ' + (data.message || 'Payment verification failed.'));
      if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-credit-card"></i> Complete Payment Now'; }
    }
  } catch (err) {
    console.error(err);
    alert('Network error during verification. Please try again.');
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-credit-card"></i> Complete Payment Now'; }
  }
}

async function cancelAllocation(allocationId) {
  if (!confirm('Are you sure you want to cancel your reservation? Your bed will be released immediately.')) return;

  try {
    const res = await fetch(`${API}/student/release_bed.php`, {
      method:      'POST',
      credentials: 'include',
      headers:     { 'Content-Type': 'application/json' },
      body:        JSON.stringify({ allocation_id: allocationId }),
    });
    const data = await res.json();
    if (data.success) {
      alert('Reservation cancelled. The bed has been released.');
      loadAllocation();
    } else {
      alert(data.message || 'Could not cancel reservation.');
    }
  } catch (err) {
    alert('Network error. Please try again.');
  }
}

// ==============================
// VERIFY PAYMENT (legacy helper, kept for compatibility)
// ==============================

async function verifyPayment(reference, allocationId) {
  await verifyDashboardPayment(reference, allocationId);
}

// ==============================
// LOGOUT
// ==============================

async function logout() {
  const res = await fetch(`${API}/auth/logout.php`, {
    method: "POST",
    credentials: "include",
  });

  const data = await res.json();

  if (data.success) {
    localStorage.clear();
    window.location.href = "login.html";
  }
}

// ==============================
// NAVIGATION
// ==============================

function goToRooms() {
  window.location.href = 'room.html';
}

// ── PRINT RECEIPT ───────────────────────────────────────
function printReceipt() {
  if (!currentAllocation) {
    alert("No active allocation found.");
    return;
  }
  const reference = currentAllocation.payment_reference || currentAllocation.reference;
  if (!reference) {
    alert("No payment reference found for this allocation.");
    return;
  }
  window.open(`${API}/student/receipt.php?reference=${encodeURIComponent(reference)}`, '_blank', 'width=800,height=900');
}
