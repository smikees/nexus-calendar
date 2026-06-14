# Nexus Calendar

Calendar app hosted at **cal.stamih.com**.

## Deployment

Pushes to `main` auto-deploy to the cPanel host via FTP using a GitHub Actions
workflow (`.github/workflows/deploy.yml`).

Required GitHub repository secrets:

| Secret | Value |
|---|---|
| `FTP_SERVER` | FTP hostname (e.g. `ftp.stamih.com` or server IP) |
| `FTP_USERNAME` | cPanel FTP account user |
| `FTP_PASSWORD` | cPanel FTP account password |

The FTP account is scoped directly to the `cal.stamih.com` document root, so the
workflow uploads to `./`.

## Local development

Static files for now. Open `index.html` in a browser.
