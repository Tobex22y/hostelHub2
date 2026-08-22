<?php
// Root entry point — redirects to the actual homepage.
// Keeps all existing relative paths (frontend/assets, backend/api, etc.) unchanged.
header('Location: /frontend/pages/home.html');
exit;
