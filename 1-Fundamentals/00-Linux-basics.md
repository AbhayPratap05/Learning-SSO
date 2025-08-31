# Linux Basics

## 1. Navigating the Filesystem

- `pwd` → print current directory
- `ls` → list files in a directory
- `cd <dir>` → change directory
- `touch file.txt` → create an empty file
- `mkdir mydir` → create a new directory
- `rm file.txt` → remove a file
- `rm -r mydir` → remove a directory recursively

## 2. File Operations

- `cat file.txt` → display file content
- `nano file.txt` / `vim file.txt` → edit file
- `cp a.txt b.txt` → copy file
- `mv a.txt b.txt` → rename/move file

## 3. Permissions

- `ls -l` → check permissions
- `chmod 644 file.txt` → change permissions (rw-r--r--)
- `chown user:group file.txt` → change ownership

## 4. Processes

- `ps aux` → list running processes
- `top` → view CPU/memory usage
- `kill <pid>` → kill a process

## 5. Package Management (Ubuntu/Debian)

- `sudo apt update` → refresh package index
- `sudo apt upgrade` → update packages
- `sudo apt install <pkg>` → install package
- `sudo apt remove <pkg>` → uninstall package
