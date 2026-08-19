// =====================================================
// room.js — Room browsing + inline bed booking + payment
// Replaces the old room.html/app.js "goToRooms" flow AND
// reserve.html/reserve.js entirely. Beds now expand inside
// the room card itself instead of navigating to a new page.
//
// GATING: if the student already has a paid/active/approved
// allocation, the booking UI is hidden and a roommates list
// is shown instead (see loadRoommates()).
// =====================================================

const cfg = (typeof CONFIG !== 'undefined'
  ? CONFIG
  : (typeof window !== 'undefined' ? window.CONFIG : undefined)) || { API_BASE_URL: window.location.origin + '/HostelHub-main/backend/api' };
const API = cfg.API_BASE_URL;

// ── STATE ──
let currentStudentGender = null;
let studentEmail         = null;

let openRoomId       = null;   // which room card's accordion is currently open
let activeRoomData    = null;  // { id, number, room_type, hall, price }
let selectedBed       = null;  // { id, number }
let currentAllocationId = null;
let countdownTimer    = null;

// ── HALL PRICE MAP (kept in sync with reserve.js / app.js) ──
const HALL_PRICES = {
  'A': 50000, 'B': 50000, 'C1': 50000, 'C2': 65000, 'D': 55000, 'E': 60000,
};
function getHallPrice(hallId) {
  const key = String(hallId || '').trim().toUpperCase();
  return HALL_PRICES[key] || 50000;
}

function extractHallBlock(roomNumber, hallField) {
  if (hallField && String(hallField).trim().length > 0) {
    return String(hallField).trim().toUpperCase();
  }
  if (roomNumber && roomNumber !== 'N/A') {
    const m = String(roomNumber).trim().toUpperCase().match(/^([A-Z]+[0-9]*)/);
    if (m) {
      const extracted = m[1];
      if (extracted === 'RM') return 'UNKNOWN';
      return extracted;
    }
  }
  return 'UNKNOWN';
}

// =====================================================
// INIT — check allocation status first, then branch
// =====================================================
document.addEventListener('DOMContentLoaded', () => {
  gateCheck();
});

async function gateCheck() {
  try {
    const res  = await fetch(`${API}/auth/dashboard.php`, { credentials: 'include' });
    const data = await res.json();

    if (!data.success) {
      // Not logged in — send back to login
      window.location.href = 'login.html';
      return;
    }

    const student = data.student || {};
    studentEmail  = student.email;
    currentStudentGender = (student.gender || '').toLowerCase();

    // Fill topbar (same as dashboard.html)
    const welcomeEl = document.getElementById('welcomeName');
    if (welcomeEl) welcomeEl.innerText = `Welcome, ${student.fullname || 'Student'}`;
    const avatarEl = document.getElementById('studentAvatar');
    if (avatarEl) {
      if (student.profile_image) {
        avatarEl.src = window.location.origin + '/HostelHub-main/backend/uploads/profiles/' + student.profile_image;
      } else {
        avatarEl.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(student.fullname || 'Student') + '&background=2d7a3e&color=fff';
      }
      avatarEl.onerror = function () {
        this.onerror = null;
        this.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(student.fullname || 'Student') + '&background=2d7a3e&color=fff';
      };
    }

    document.getElementById('gateLoading').style.display = 'none';

    const alloc = data.allocation;
    const isAllocated = alloc && ['paid', 'active', 'approved'].includes(String(alloc.status || '').toLowerCase());

    // ── MY PASS nav item: only show when there's an active/paid allocation ──
    const navEpassLink  = document.getElementById('nav-epass-link');
    const navEpassLabel = document.getElementById('nav-epass-label');
    if (navEpassLink)  navEpassLink.style.display  = isAllocated ? 'flex' : 'none';
    if (navEpassLabel) navEpassLabel.style.display = isAllocated ? 'block' : 'none';

    if (isAllocated) {
      showRoommatesView(alloc);
    } else {
      showBookingView();
    }

  } catch (err) {
    console.error('Gate check failed:', err);
    document.getElementById('gateLoading').style.display = 'none';
    // Fail open into booking view so a network hiccup doesn't lock students out entirely
    showBookingView();
  }
}

// =====================================================
// ALREADY ALLOCATED → ROOMMATES VIEW
// =====================================================
function showRoommatesView(alloc) {
  document.getElementById('roommatesSection').style.display = 'block';
  document.getElementById('bookingSection').style.display   = 'none';

  const hallId = extractHallBlock(alloc.room_number, alloc.hall || alloc.block);
  const hallDisplay = hallId !== 'UNKNOWN' ? `Hall ${hallId}` : '--';

  document.getElementById('allocSummarySub').textContent =
    `${hallDisplay} · Room ${alloc.room_number || '--'} · Bed ${alloc.bed_number || '--'}`;

  loadRoommates();
}

async function loadRoommates() {
  const box = document.getElementById('roommatesList');
  box.innerHTML = '<p class="loading-text"><div class="spinner"></div>Loading roommates…</p>';

  try {
    const res  = await fetch(`${API}/student/roommates.php`, { credentials: 'include' });
    const data = await res.json();

    if (!data.success) {
      box.innerHTML = `<p class="loading-text">${data.message || 'Could not load roommates.'}</p>`;
      return;
    }

    const mates = data.roommates || [];
    if (!mates.length) {
      box.innerHTML = '<p class="loading-text">No other students in your room yet.</p>';
      return;
    }

    box.innerHTML = mates.map(m => {
      const initials = (m.fullname || '?').trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
      const youTag = m.is_you ? '<span class="you-tag">You</span>' : '';
      return `
        <div class="roommate-row ${m.is_you ? 'you' : ''}">
          <div class="roommate-left">
            <div class="roommate-avatar">${initials}</div>
            <div>
              <div class="roommate-name">${m.fullname || 'Student'}${youTag}</div>
              <div class="roommate-matric">${m.matric_number || ''}</div>
            </div>
          </div>
          <div class="roommate-bed"><i class="fa-solid fa-bed"></i> Bed ${m.bed_number || '--'}</div>
        </div>
      `;
    }).join('');

  } catch (err) {
    console.error('Roommates fetch failed:', err);
    box.innerHTML = '<p class="loading-text">Network error loading roommates.</p>';
  }
}

// =====================================================
// BOOKING VIEW — rooms grid
// =====================================================
function showBookingView() {
  document.getElementById('roommatesSection').style.display = 'none';
  document.getElementById('bookingSection').style.display   = 'block';
  loadRooms();
}

async function loadRooms() {
  try {
    const selectedHall = localStorage.getItem('selectedHall');
    if (selectedHall) localStorage.removeItem('selectedHall');

    let url = `${API}/student/rooms.php`;
    const params = new URLSearchParams();
    if (selectedHall) params.set('hall', selectedHall);
    if (currentStudentGender) params.set('gender', currentStudentGender);
    if (params.toString()) url += '?' + params.toString();

    const res  = await fetch(url, { credentials: 'include' });
    const data = await res.json();

    const container = document.getElementById('roomsContainer');
    const filterBanner = document.getElementById('activeHallFilter');

    if (filterBanner) {
      if (selectedHall) {
        filterBanner.style.display = 'block';
        filterBanner.innerHTML = `Showing Hall <strong>${selectedHall}</strong> &mdash; <a href="#" onclick="clearHallFilter(event)">Clear filter</a>`;
      } else {
        filterBanner.style.display = 'none';
        filterBanner.innerHTML = '';
      }
    }

    container.innerHTML = '';

    if (!data.rooms || !data.rooms.length) {
      container.innerHTML = '<p class="loading-text">No rooms available.</p>';
      return;
    }

    data.rooms.forEach(room => renderRoomCard(container, room));

  } catch (err) {
    console.error('loadRooms failed:', err);
    const container = document.getElementById('roomsContainer');
    if (container) container.innerHTML = '<p class="loading-text">Could not load rooms. Please refresh.</p>';
  }
}

function clearHallFilter(event) {
  event.preventDefault();
  loadRooms();
}

function renderRoomCard(container, room) {
  const accurateHall = extractHallBlock(room.room_number, room.hall);
  const genderTag = room.gender === 'female' ? 'tag-female' : (room.gender === 'male' ? 'tag-male' : '');
  const price = Number(room.price) || getHallPrice(accurateHall);

  const card = document.createElement('div');
  card.className = 'room-card';
  card.id = `room-card-${room.id}`;

  card.innerHTML = `
    <div class="room-card-head">
      <div class="room-card-top">
        <h3>Room ${room.room_number}</h3>
        ${room.gender ? `<span class="badge ${genderTag}">${room.gender}</span>` : ''}
      </div>
      <div class="room-meta">
        <span>${room.room_type || 'Standard'}</span>
        <span>·</span>
        <span>Hall ${accurateHall}</span>
        <span>·</span>
        <span>₦${price.toLocaleString()}</span>
      </div>
      <div class="room-badges">
        <span class="badge available"><i class="fa-solid fa-bed"></i> ${room.available} free</span>
        <span class="badge occupied"><i class="fa-solid fa-ban"></i> ${room.occupied} taken</span>
      </div>
      <button class="btn-view-beds" id="toggle-btn-${room.id}"
        onclick="toggleBeds(${room.id}, '${(room.room_number||'').replace(/'/g,"\\'")}', '${(room.room_type||'Standard').replace(/'/g,"\\'")}', '${accurateHall}', ${price})">
        <i class="fa-solid fa-chevron-down"></i> View Beds
      </button>
    </div>
    <div class="beds-accordion" id="beds-acc-${room.id}"></div>
  `;

  container.appendChild(card);
}

// =====================================================
// TOGGLE INLINE BED ACCORDION
// =====================================================
async function toggleBeds(roomId, roomNumber, roomType, hall, price) {
  // Close any other open accordion first (and release any un-paid lock held there)
  if (openRoomId !== null && openRoomId !== roomId) {
    await closeAccordion(openRoomId);
  }

  const acc = document.getElementById(`beds-acc-${roomId}`);
  const btn = document.getElementById(`toggle-btn-${roomId}`);
  const isOpen = acc.style.display === 'block';

  if (isOpen) {
    await closeAccordion(roomId);
    return;
  }

  activeRoomData = { id: roomId, number: roomNumber, room_type: roomType, hall, price };
  openRoomId = roomId;
  acc.style.display = 'block';
  btn.classList.add('open');
  btn.innerHTML = '<i class="fa-solid fa-chevron-up"></i> Hide Beds';

  acc.innerHTML = `
    <div class="acc-loading"><div class="spinner"></div>Loading beds…</div>
  `;

  try {
    const res  = await fetch(`${API}/student/room_beds.php?room_id=${roomId}`, { credentials: 'include' });
    const data = await res.json();

    if (data.room) activeRoomData = { ...activeRoomData, ...data.room };

    const bedsArray = Array.isArray(data) ? data : (data.beds || []);
    renderBedsGrid(roomId, bedsArray);

  } catch (err) {
    console.error(err);
    acc.innerHTML = `<div class="acc-error">Could not load beds for this room. Please try again.</div>`;
  }
}

async function closeAccordion(roomId) {
  // If a bed in this room is currently locked but unpaid, release it
  if (roomId === openRoomId && currentAllocationId && selectedBed) {
    await releaseBed(currentAllocationId);
  }
  clearInterval(countdownTimer);
  currentAllocationId = null;
  selectedBed = null;

  const acc = document.getElementById(`beds-acc-${roomId}`);
  const btn = document.getElementById(`toggle-btn-${roomId}`);
  if (acc) { acc.style.display = 'none'; acc.innerHTML = ''; }
  if (btn) { btn.classList.remove('open'); btn.innerHTML = '<i class="fa-solid fa-chevron-down"></i> View Beds'; }

  if (roomId === openRoomId) {
    openRoomId = null;
    activeRoomData = null;
  }
}

function renderBedsGrid(roomId, beds) {
  const acc = document.getElementById(`beds-acc-${roomId}`);
  if (!beds.length) {
    acc.innerHTML = '<p class="beds-empty">No beds found for this room.</p>';
    return;
  }

  const grid = document.createElement('div');
  grid.className = 'mini-beds-grid';

  beds.forEach(bed => {
    const isOccupied = String(bed.is_occupied) === '1' || bed.status === 'occupied';
    const card = document.createElement('div');
    card.className = `mini-bed-card ${isOccupied ? 'occupied' : 'available'}`;
    card.dataset.bedId = bed.id;
    card.innerHTML = `
      <i class="fa-solid ${isOccupied ? 'fa-ban' : 'fa-bed'}" style="color:${isOccupied ? '#dc2626' : '#16a34a'};"></i>
      <div class="mini-bed-number">Bed ${bed.bed_number}</div>
      <div class="mini-bed-status">${isOccupied ? 'Occupied' : 'Available'}</div>
    `;
    if (!isOccupied) {
      card.addEventListener('click', () => selectBed(roomId, card, bed));
    }
    grid.appendChild(card);
  });

  acc.innerHTML = '';
  acc.appendChild(grid);
}

// =====================================================
// SELECT + LOCK A BED
// =====================================================
async function selectBed(roomId, card, bed) {
  if (card.classList.contains('locking')) return;

  const acc = document.getElementById(`beds-acc-${roomId}`);
  acc.querySelectorAll('.mini-bed-card.selected').forEach(c => {
    c.classList.remove('selected');
    c.querySelector('.mini-bed-status').textContent = 'Available';
  });

  card.classList.add('locking');
  card.querySelector('.mini-bed-status').textContent = 'Locking…';

  try {
    const res  = await fetch(`${API}/student/reserve.php`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ room_id: roomId, bed_id: bed.id })
    });
    const data = await res.json();
    card.classList.remove('locking');

    if (!data.success) {
      card.querySelector('.mini-bed-status').textContent = 'Available';
      showBookingError(data.message || 'Could not lock bed. Try another.');
      refreshBedsGrid(roomId);
      return;
    }

    currentAllocationId = data.allocation_id;
    selectedBed = { id: bed.id, number: bed.bed_number };

    renderConfirmPanel(roomId);
    startCountdown(15 * 60);

  } catch (err) {
    card.classList.remove('locking');
    card.querySelector('.mini-bed-status').textContent = 'Available';
    showBookingError('Network error. Please try again.');
    console.error(err);
  }
}

// =====================================================
// CONFIRM / PAY PANEL (swaps into the same accordion)
// =====================================================
function renderConfirmPanel(roomId) {
  const acc = document.getElementById(`beds-acc-${roomId}`);
  const hallId = extractHallBlock(activeRoomData.number, activeRoomData.hall);
  const price  = Number(activeRoomData.price) || getHallPrice(hallId);

  acc.innerHTML = `
    <div class="confirm-panel">
      <div class="detail-card">
        <h3><i class="fa-solid fa-location-dot"></i> Room & Bed</h3>
        <div class="detail-row"><span>Hall Block</span><span>${hallId}</span></div>
        <div class="detail-row"><span>Room Number</span><span>${activeRoomData.number}</span></div>
        <div class="detail-row"><span>Bed Number</span><span>${selectedBed.number}</span></div>
        <div class="detail-row"><span>Room Type</span><span>${activeRoomData.room_type || 'Standard'}</span></div>
      </div>

      <div class="countdown-row">
        <i class="fa-solid fa-clock"></i> Bed locked for you
        <span class="status-value" id="status-value-${roomId}">15:00 remaining</span>
      </div>

      <div class="price-card">
        <div class="price-row"><span>Hostel Charges</span><span>₦${price.toLocaleString()}</span></div>
        <div class="price-row"><span>Processing Fee</span><span>₦0</span></div>
        <div class="price-total"><span>Total</span><span>₦${price.toLocaleString()}</span></div>
      </div>

      <div class="pay-loading" id="pay-loading-${roomId}">
        <div class="spinner"></div>
        <p>Verifying payment…</p>
      </div>

      <div class="action-row" id="action-row-${roomId}">
        <button class="btn-pay" id="pay-btn-${roomId}" onclick="proceedToPayment(${roomId})">
          <i class="fa-solid fa-lock"></i> Pay ₦${price.toLocaleString()}
        </button>
        <button class="btn-change" onclick="changeBed(${roomId})">
          <i class="fa-solid fa-sync-alt"></i> Change Bed
        </button>
      </div>
    </div>
  `;
}

async function changeBed(roomId) {
  if (currentAllocationId) await releaseBed(currentAllocationId);
  clearInterval(countdownTimer);
  currentAllocationId = null;
  selectedBed = null;
  await refreshBedsGrid(roomId);
}

// Re-fetches and re-renders the bed grid for the currently open room
// WITHOUT toggling the accordion open/closed (used after a change/expiry).
async function refreshBedsGrid(roomId) {
  const acc = document.getElementById(`beds-acc-${roomId}`);
  if (!acc) return;
  acc.innerHTML = `<div class="acc-loading"><div class="spinner"></div>Loading beds…</div>`;
  try {
    const res  = await fetch(`${API}/student/room_beds.php?room_id=${roomId}`, { credentials: 'include' });
    const data = await res.json();
    if (data.room) activeRoomData = { ...activeRoomData, ...data.room };
    const bedsArray = Array.isArray(data) ? data : (data.beds || []);
    renderBedsGrid(roomId, bedsArray);
  } catch (err) {
    console.error(err);
    acc.innerHTML = `<div class="acc-error">Could not reload beds. Please try again.</div>`;
  }
}

// =====================================================
// PAYMENT
// =====================================================
function proceedToPayment(roomId) {
  clearBookingError();

  if (!selectedBed || !currentAllocationId) {
    showBookingError('Please select a bed first.');
    return;
  }
  if (!studentEmail) {
    showBookingError('Session expired. Please log in again.');
    return;
  }

  const hallId = extractHallBlock(activeRoomData.number, activeRoomData.hall);
  const price  = Number(activeRoomData.price) || getHallPrice(hallId);
  const ref    = 'HH-' + currentAllocationId + '-' + Date.now();

  const handler = PaystackPop.setup({
    key: 'pk_test_ee4c2475b4ddbee902eb8a12801a4adc91b04340',
    email: studentEmail,
    amount: price * 100,
    currency: 'NGN',
    ref: ref,

    onClose: function () {
      clearInterval(countdownTimer);
      alert('Payment window closed. Your reservation is still held for now. You can complete payment from your dashboard within the time limit.');
      window.location.href = 'dashboard.html';
    },

    callback: function (response) {
      clearInterval(countdownTimer);
      verifyAndComplete(roomId, response.reference, currentAllocationId);
    }
  });

  handler.openIframe();
}

async function releaseBed(allocationId) {
  try {
    await fetch(`${API}/student/release_bed.php`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ allocation_id: allocationId })
    });
  } catch (e) {
    console.warn('Could not release bed:', e);
  }
}

async function verifyAndComplete(roomId, reference, allocationId) {
  const payLoading = document.getElementById(`pay-loading-${roomId}`);
  const actionRow  = document.getElementById(`action-row-${roomId}`);
  if (payLoading) payLoading.style.display = 'block';
  if (actionRow)  actionRow.style.display  = 'none';

  try {
    const res  = await fetch(`${API}/student/verify_payment.php`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reference, allocation_id: allocationId })
    });
    const data = await res.json();

    if (data.success) {
      clearInterval(countdownTimer);
      alert('✅ Payment successful! Your bed space is secured.');
      window.open(`${API}/student/receipt.php?reference=${encodeURIComponent(reference)}`, '_blank');
      window.location.href = 'dashboard.html';
    } else {
      await releaseBed(allocationId);
      showBookingError('❌ Payment failed: ' + (data.message || 'Verification unsuccessful. Your bed has been released.'));
      currentAllocationId = null;
      selectedBed = null;
      refreshBedsGrid(roomId);
    }
  } catch (err) {
    await releaseBed(allocationId);
    showBookingError('Network error during verification. Your bed has been released. Please try again.');
    currentAllocationId = null;
    selectedBed = null;
    console.error(err);
  }
}

// =====================================================
// COUNTDOWN
// =====================================================
function startCountdown(seconds) {
  clearInterval(countdownTimer);
  const roomId = openRoomId;

  countdownTimer = setInterval(async () => {
    const statusEl = document.getElementById(`status-value-${roomId}`);
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    if (statusEl) statusEl.textContent = `${m}:${s.toString().padStart(2, '0')} remaining`;

    if (seconds <= 0) {
      clearInterval(countdownTimer);
      if (currentAllocationId) await releaseBed(currentAllocationId);
      showBookingError('⏰ Time expired. Your bed lock has been released. Please select a bed again.');
      currentAllocationId = null;
      selectedBed = null;
      if (activeRoomData) {
        refreshBedsGrid(roomId);
      }
    }
    seconds--;
  }, 1000);
}

// =====================================================
// UI HELPERS
// =====================================================
function showBookingError(msg) {
  const box = document.getElementById('error-box');
  if (box) box.innerHTML = `<div class="acc-error" style="margin-bottom:16px;">${msg}</div>`;
}
function clearBookingError() {
  const box = document.getElementById('error-box');
  if (box) box.innerHTML = '';
}

// =====================================================
// LOGOUT (same as app.js)
// =====================================================
async function logout() {
  try {
    const res = await fetch(`${API}/auth/logout.php`, { method: 'POST', credentials: 'include' });
    const data = await res.json();
    if (data.success) {
      localStorage.clear();
      window.location.href = 'login.html';
    }
  } catch (e) {
    localStorage.clear();
    window.location.href = 'login.html';
  }
}

// Release any locked bed if the student navigates away without paying
window.addEventListener('beforeunload', () => {
  if (currentAllocationId && selectedBed) {
    navigator.sendBeacon(
      `${API}/student/release_bed.php`,
      new Blob([JSON.stringify({ allocation_id: currentAllocationId })], { type: 'application/json' })
    );
  }
});