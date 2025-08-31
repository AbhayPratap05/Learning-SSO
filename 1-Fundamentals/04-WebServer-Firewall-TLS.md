# 03 - Web Server, Firewall, and TLS Basics

## 1. Web Server (Apache)

### Installing Apache

- Apache web server serves websites and act as a reverse proxy for apps like Django, PHP, or Keycloak.

```bash
sudo apt update
sudo apt install apache2 -y
```

### Check Apache Status

`service apache2 status` -> For Docker Container
`systemctl status apache2` -> Linux

### A Simple Page

`echo "<h1>Hello from Apache</h1>" | sudo tee /var/www/html/index.html`

### Virtual Hosts

- Run multiple websites on the same server.

```
<VirtualHost *:80>
    ServerName mysite.local
    DocumentRoot /var/www/mysite
</VirtualHost>
```

### Reverse Proxy

- Apps running on different ports (Keycloak on 8080, Django on 8000). Apache listens on 80/443 and forwards traffic to them.

```
<VirtualHost *:80>
    ServerName keycloak.local
    ProxyPass / http://localhost:8080/
    ProxyPassReverse / http://localhost:8080/
</VirtualHost>
```

## 2. Firewall

- A firewall controls what network traffic can enter/exit the server.

### Enable UFW (Uncomplicated Firewall)

`sudo ufw enable`

- Note: UFW not properly supported in docker containers

### Allow Ports

`sudo ufw allow 22/tcp` # Required for SSH access
`sudo ufw allow 80/tcp` # Allows HTTP websites
`sudo ufw allow 443/tcp` # Allows HTTPS websites

### Check Firewall Status

`sudo ufw status` -> Shows which ports are open

## 3. TLS / HTTPS

- HTTP: unencrypted, data can be intercepted.
- HTTPS (HTTP + TLS): encrypted, prevents intrusion.
- TLS: protocol that powers HTTPS.

### Install Certbot

`sudo apt install certbot python3-certbot-apache -y` -> Automates SSL certificate from Let’s Encrypt (free).

### Generate SSL Certificate

`sudo certbot --apache -d abhaypratap.com -d www.abhaypratap.com` -> Installs and configures HTTPS

### Test Renewal

`sudo certbot renew --dry-run` -> Ensures SSL certificate auto-renews every 90 days.
