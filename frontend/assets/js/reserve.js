// =============================================
// reserve.js  –  Bed Selection + Payment Flow
// =============================================
let currentAllocationId = null;
let countdownTimer      = null;

const API = (typeof CONFIG !== 'undefined' ? CONFIG.API_BASE_URL : null)
  || (window.CONFIG ? window.CONFIG.API_BASE_URL : null)
  || (window.location.origin + '/HostelHub-main/backend/api');

let roomData       = null;   // { id, number, room_type, block }
let selectedBed    = null;   // { id, number }
let studentEmail   = null;

// ── HALL PRICE MAP (Unified Truth Matrix) ─────────────────────
const HALL_PRICES = {
  'A':  50000,
  'B':  50000,
  'C1': 50000,
  'C2': 65000,
  'D':  55000,
  'E':  60000,
};

function getHallPrice(hallId) {
  const key = String(hallId || '').trim().toUpperCase();
  return HALL_PRICES[key] || 50000;
}

// Helper to reliably extract block identifiers (e.g. "C2", "A")
function extractHallBlock(roomObj) {
  if (!roomObj) return '';
  let blockRaw = roomObj.hall || roomObj.block || '';
  if (!blockRaw && roomObj.number) {
    // If room number starts with C2 (e.g. C2-04), extract the block code properly
    const match = String(roomObj.number).trim().match(/^([A-Za-z][0-9]?)/);
    if (match) blockRaw = match[1];
  }
  return String(blockRaw).trim().toUpperCase();
}

// ── Bootstrap ────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
  const raw = localStorage.getItem('reserveRoom');
  if (!raw) {
    showError('No room selected. Please go back and choose a room.');
    return;
  }

  roomData = JSON.parse(raw);

  // Extract block code and compute the correct pricing matrix value
  const actualHall = extractHallBlock(roomData);
  const currentPrice = getHallPrice(actualHall);

  // Show room pill with the correct dynamic session price
  const pill = document.getElementById('room-pill');
  const pillText = document.getElementById('pill-text');
  if (pill && pillText) {
    pillText.textContent = `Room ${roomData.number || roomData.id}  ·  ${roomData.room_type || 'Standard'} (₦${currentPrice.toLocaleString()})`;
    pill.style.display = 'inline-flex';
  }

  fetchBeds(roomData.id);
  fetchStudentEmail();
});

// ── Fetch beds for the selected room ─────────

async function fetchBeds(roomId) {
  showBedsLoading(true);

  try {
    const res  = await fetch(`${API}/student/room_beds.php?room_id=${roomId}`, { credentials: 'include' });
    const data = await res.json();

    showBedsLoading(false);

    if (data.room) {
      roomData = data.room;
    }

    const bedsArray = Array.isArray(data) ? data : (data.beds || []);

    if (bedsArray.length === 0) {
      document.getElementById('beds-grid').style.display = 'block';
      document.getElementById('beds-grid').innerHTML = '<p class="empty">No beds found for this room.</p>';
      return;
    }

    renderBeds(bedsArray);

  } catch (err) {
    showBedsLoading(false);
    showError('Could not load beds. Please go back and try again.');
    console.error(err);
  }
}

// ── Render bed cards ──────────────────────────

function renderBeds(beds) {
  const grid = document.getElementById('beds-grid');
  grid.innerHTML = '';
  grid.style.display = 'grid';

  beds.forEach((bed) => {
    const isOccupied = String(bed.is_occupied) === '1' || bed.status === 'occupied';

    const card = document.createElement('div');
    card.className = `bed-card ${isOccupied ? 'occupied' : 'available'}`;
    card.dataset.bedId     = bed.id;
    card.dataset.bedNumber = bed.bed_number;

    card.innerHTML = `
      ${isOccupied ? '<i class="fa-solid fa-ban" style="color:#dc2626;font-size:22px;"></i>' : '<i class="fa-solid fa-bed" style="color:#16a34a;font-size:22px;"></i>'}
      <div class="bed-number">Bed ${bed.bed_number}</div>
      <div class="bed-status">${isOccupied ? 'Occupied' : 'Available'}</div>
    `;

    if (!isOccupied) {
      card.addEventListener('click', () => selectBed(card, bed));
    }

    grid.appendChild(card);
  });
}

// ── Select a bed ─────────────────────────────

async function selectBed(card, bed) {
  if (card.classList.contains('locking')) return;

  document.querySelectorAll('.bed-card.selected').forEach(c => {
    c.classList.remove('selected');
    c.querySelector('.bed-status').textContent = 'Available';
  });

  card.classList.add('locking');
  card.querySelector('.bed-status').textContent = '⏳ Locking...';
  clearError();

  try {
    const res  = await fetch(`${API}/student/reserve.php`, {
      method:      'POST',
      credentials: 'include',
      headers:     { 'Content-Type': 'application/json' },
      body: JSON.stringify({ room_id: roomData.id, bed_id: bed.id })
    });
    const data = await res.json();

    card.classList.remove('locking');

    if (!data.success) {
      card.querySelector('.bed-status').textContent = 'Available';
      showError(data.message || 'Could not lock bed. Try another.');
      fetchBeds(roomData.id);
      return;
    }

    currentAllocationId = data.allocation_id;
    selectedBed = { id: bed.id, number: bed.bed_number };

    card.classList.add('selected');
    card.querySelector('.bed-status').textContent = '🔒 Locked for you';

    // Compute price accurately using global matrix lookup
    const actualHall = extractHallBlock(roomData);
    const dynamicRoomPrice = Number(roomData.price) || getHallPrice(actualHall);

    document.getElementById('hall-value').textContent      = actualHall || 'N/A';
    document.getElementById('room-value').textContent      = roomData.room_number || roomData.number;
    document.getElementById('bed-value').textContent       = bed.bed_number;
    document.getElementById('room-type-value').textContent = roomData.room_type || 'Standard';
    
    if (document.getElementById('hostelCharges')) {
      document.getElementById('hostelCharges').textContent = `₦${dynamicRoomPrice.toLocaleString()}`;
    }
    if (document.getElementById('totalAmount')) {
      document.getElementById('totalAmount').textContent = `₦${dynamicRoomPrice.toLocaleString()}`;
    }

    const panel = document.getElementById('confirm-panel');
    panel.style.display = 'block';
    setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    startCountdown(15 * 60);

  } catch (err) {
    card.classList.remove('locking');
    card.querySelector('.bed-status').textContent = 'Available';
    showError('Network error. Please try again.');
    console.error(err);
  }
}

// ── Deselect / change bed ─────────────────────

function cancelSelection() {
  selectedBed = null;

  document.querySelectorAll('.bed-card.selected').forEach(c => {
    c.classList.remove('selected');
    c.querySelector('.bed-status').textContent = 'Available';
  });

  document.getElementById('confirm-panel').style.display = 'none';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Fetch student email (needed for Paystack) ─

async function fetchStudentEmail() {
  try {
    const res  = await fetch(`${API}/auth/dashboard.php`, { credentials: 'include' });
    const data = await res.json();
    if (data.success && data.student) {
      studentEmail = data.student.email;
    }
  } catch (err) {
    console.error('Could not fetch student email:', err);
  }
}

// ── Proceed to Payment ────────────────────────

function proceedToPayment() {
  clearError();

  if (!selectedBed || !currentAllocationId) {
    showError('Please select a bed first.');
    return;
  }

  if (!studentEmail) {
    showError('Session expired. Please log in again.');
    return;
  }

  // Determine dynamic structural configuration price values
  const hallId = extractHallBlock(roomData);
  const price  = Number(roomData.price) || getHallPrice(hallId);
  const ref    = 'HH-' + currentAllocationId + '-' + Date.now();

  const chargesEl = document.getElementById('hostelCharges');
  const totalEl   = document.getElementById('totalAmount');
  const payBtn    = document.getElementById('pay-btn');
  if (chargesEl) chargesEl.textContent = '₦' + price.toLocaleString();
  if (totalEl)   totalEl.textContent   = '₦' + price.toLocaleString();
  if (payBtn)    payBtn.innerHTML       = `<i class="fa-solid fa-lock"></i> Pay ₦${price.toLocaleString()}`;

  const handler = PaystackPop.setup({
    key:      'pk_test_ee4c2475b4ddbee902eb8a12801a4adc91b04340',
    email:    studentEmail,
    amount:   price * 100,   // Exact Kobo translation calculation
    currency: 'NGN',
    ref:      ref,

    onClose: function () {
      clearInterval(countdownTimer);
      alert('Payment window closed. Your reservation is still held for now. You can complete payment from your dashboard within the time limit.');
      window.location.href = 'dashboard.html';
    },

    callback: function (response) {
      clearInterval(countdownTimer);
      verifyAndComplete(response.reference, currentAllocationId);
    }
  });

  handler.openIframe();
}

async function releaseBed(allocationId) {
  try {
    await fetch(`${API}/student/release_bed.php`, {
      method:      'POST',
      credentials: 'include',
      headers:     { 'Content-Type': 'application/json' },
      body: JSON.stringify({ allocation_id: allocationId })
    });
  } catch (e) {
    console.warn('Could not release bed:', e);
  }
}

async function verifyAndComplete(reference, allocationId) {
  setPayLoading(true);
  try {
    const res = await fetch(`${API}/student/verify_payment.php`, {
      method:      'POST',
      credentials: 'include',
      headers:     { 'Content-Type': 'application/json' },
      body:        JSON.stringify({ reference, allocation_id: allocationId })
    });

    const data = await res.json();

    if (data.success) {
      clearInterval(countdownTimer);
      localStorage.removeItem('reserveRoom');
      localStorage.removeItem('selectedHall');
      setPayLoading(false);
      alert('✅ Payment successful! Your bed space is secured.');
      window.open(`${API}/student/receipt.php?reference=${encodeURIComponent(reference)}`, '_blank');
      window.location.href = 'dashboard.html';
    } else {
      await releaseBed(allocationId);
      setPayLoading(false);
      showError('❌ Payment failed: ' + (data.message || 'Verification unsuccessful. Your bed has been released.'));
      cancelSelection();
      fetchBeds(roomData.id);
    }
  } catch (err) {
    await releaseBed(allocationId);
    setPayLoading(false);
    showError('Network error during verification. Your bed has been released. Please try again.');
    cancelSelection();
    fetchBeds(roomData.id);
    console.error(err);
  }
}

// ── Navigation ────────────────────────────────

function goBack() {
  window.location.href = 'room.html';
}

// ── UI helpers ────────────────────────────────

function showBedsLoading(on) {
  document.getElementById('beds-loading').style.display = on ? 'block' : 'none';
  document.getElementById('beds-grid').style.display    = on ? 'none'  : 'grid';
}

function setPayLoading(on) {
  document.getElementById('pay-loading').style.display   = on ? 'block' : 'none';
  document.getElementById('pay-btn').style.display       = on ? 'none'  : 'block';
}

// ── Error Helpers ────────────────────────────

function showError(msg) {
  const errorBox = document.getElementById('error-box');
  if (errorBox) {
    errorBox.innerHTML = `<div class="error" style="color:#dc2626; background:#fee2e2; padding:10px; border-radius:4px; margin-bottom:15px; font-weight:bold;">${msg}</div>`;
  }
}

function clearError() {
  const errorBox = document.getElementById('error-box');
  if (errorBox) errorBox.innerHTML = '';
}

function startCountdown(seconds) {
  clearInterval(countdownTimer);
  const statusEl = document.getElementById('status-value');

  countdownTimer = setInterval(async () => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    if (statusEl) statusEl.textContent = `${m}:${s.toString().padStart(2,'0')} remaining`;

    if (seconds <= 0) {
      clearInterval(countdownTimer);
      if (currentAllocationId) await releaseBed(currentAllocationId);
      showError('⏰ Time expired. Your bed lock has been released. Please select a bed again.');
      cancelSelection();
      currentAllocationId = null;
      fetchBeds(roomData.id);
    }
    seconds--;
  }, 1000);
}