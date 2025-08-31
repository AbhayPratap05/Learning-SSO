# SSH Basics

## 1. Generating SSH Keys

```bash
ssh-keygen -t ed25519 -C "your_email@example.com"
```

Generates a key pair:

- Private key: `~/.ssh/id_ed25519`
- Public key: `~/.ssh/id_ed25519.pub`

## 2. Copying Keys to Remote Server

```bash
ssh-copy-id -p 2222 user@localhost
```

- Installs public key on the server.
- Now user can log in without typing the server password.

## 3. Logging in with SSH

```bash
ssh -p 2222 user@localhost
```

- Connect to server on port 2222.

## 4. SSH Config File

- `vim ~/.ssh/config`

```
Host myserver
    HostName localhost
    User user
    Port 2222
    IdentityFile ~/.ssh/id_ed25519
```

- To login just run:
  `ssh myserver`

## 5. Disabling Password Login

- edit `/etc/ssh/sshd_config` on server

```
PasswordAuthentication no
```

- restart SSH:
