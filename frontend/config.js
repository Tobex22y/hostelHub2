// frontend/config.js
window.CONFIG = {
    API_BASE_URL: new URL('../../backend/api', window.location.href).href.replace(/\/$/, '')
};
