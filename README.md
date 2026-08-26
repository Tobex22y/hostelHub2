# hostelHub2
# HostelHub
Hostel Management System

## Render email configuration

Add these environment variables to the Render service. For Gmail, use a Google
App Password and set `SMTP_SECURE` to `tls` with port `587` (or use `ssl` with
port `465`). Do not commit credentials to Git.

```text
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_SECURE=tls
SMTP_USER=your-gmail-address
SMTP_PASS=your-16-character-app-password
SMTP_FROM=your-gmail-address
SMTP_FROM_NAME=HostelHub
# Temporary troubleshooting only; remove after checking Render logs.
SMTP_DEBUG=1
```

After adding or changing variables, redeploy the service. Check Render logs for
the PHPMailer error returned by the ticket update endpoint if delivery still fails.
