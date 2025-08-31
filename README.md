# 🔐 SSO Learning & Implementation Project

This repository documents my journey of learning **Linux administration, web security, and Single Sign-On (SSO) with Keycloak**, along with end-to-end mini-projects in **Django, PHP, and Drupal**.  

Ultimate Goal: **Deploy a Production-Ready SSO setup on DigitalOcean**, proving the ability to run, secure, and integrate multiple stacks with a centralized authentication system.

---

## 📌 Project Plan

### 1. Learn the Foundations

- Linux basics, SSH, and Bash workflows
- Web server setup
- Firewall configuration
- TLS (high-level concepts)

### 2. Understand SSO with Keycloak

- Keycloak basics: realms, clients, users, roles
- Identity brokering & federated identity
- Reverse proxy setup with Apache
- Enabling HTTPS (Let’s Encrypt)

### 3. Mini-Project A — Django + Keycloak

- Deploy Django with Gunicorn behind Apache
- Secure Django with Keycloak SSO

### 4. Mini-Project B — PHP + Keycloak

- Apache vhost for PHP app
- Integrate with Keycloak SSO

### 5. Mini-Project C — Drupal 11 + Keycloak

- Deploy Drupal 11
- Configure Keycloak module for SSO

### 6. Final Deployment — DigitalOcean

- Rocky Linux 10 droplet setup
- New sudo user + SSH keys
- `firewalld` rules (80/443/8080)
- Apache/PHP/MariaDB with systemd
- Keycloak behind Apache reverse proxy (no exposed 9000)
- HTTPS enabled with Let’s Encrypt
- Keycloak clients for real domains with exact redirect URIs

---

## ⚡ High-Level Deployment Checklist

1. Create Rocky Linux droplet & secure access
2. Install Apache/PHP/MariaDB
3. Install Keycloak (service mode)
4. Put Keycloak behind Apache reverse proxy + HTTPS
5. Deploy Django app with Gunicorn behind Apache ProxyPass
6. Deploy PHP app (Apache vhost)
7. Configure Drupal 11 with Keycloak
8. Register clients in Keycloak with correct redirect URIs

---

## ⚠️ Rocky Linux 10 Notes

- RL10 uses `dnf5` → old `dnf module enable` syntax is phased out
- Some PHP streams may differ from RL9
- If third-party docs fail, check vendor RL10 instructions or fallback to RL9

---

## 🎯 Why This Project Matters

- Proves ability to run and secure Linux servers
- Demonstrates end-to-end SSO integration with multiple stacks
- Provides hands-on production-like deployment experience

---

## 🚀 Progress

- ✅ Part 1: Foundations
- [ ] Part 2: Keycloak Basics
- [ ] Part 3: Django SSO
- [ ] Part 4: PHP SSO
- [ ] Part 5: Drupal 11 SSO
- [ ] Part 6: Final Deployment on DigitalOcean
