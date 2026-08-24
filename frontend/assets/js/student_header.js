(function () {
  async function loadStudentHeader() {
    const avatar = document.getElementById('studentAvatar');
    const welcome = document.getElementById('welcomeName');
    if (!avatar && !welcome) return;

    try {
      const response = await fetch(CONFIG.API_BASE_URL + '/student/profile.php', {
        credentials: 'include'
      });
      const data = await response.json();
      if (!data.success) return;

      const student = data.student || {};
      if (welcome && student.fullname) {
        welcome.textContent = 'Welcome, ' + student.fullname;
      }

      if (avatar) {
        const fallback = 'https://ui-avatars.com/api/?name=' +
          encodeURIComponent(student.fullname || 'Student') +
          '&background=2d7a3e&color=fff';
        avatar.src = student.profile_image
          ? new URL('../uploads/profiles/' + encodeURIComponent(student.profile_image), CONFIG.API_BASE_URL + '/').href
          : fallback;
        avatar.onerror = function () {
          this.onerror = null;
          this.src = fallback;
        };
      }
    } catch (error) {
      console.warn('Student header could not load:', error);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadStudentHeader);
  } else {
    loadStudentHeader();
  }
})();
