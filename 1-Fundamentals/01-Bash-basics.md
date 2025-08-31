# Bash Basics

## 1. Variables

- `VAR=value` → define variable
- `echo $VAR` → print variable value

## 2. Conditionals

```bash
if [ "$VAR" -eq 1 ]; then
  echo "One"
fi
```

## 3. Loops

For Loop

```bash
for i in {1..5}; do
  echo $i
done
```

While loop

```bash
while [ $n -le 5 ]; do
  echo $n
  ((n++))
done
```

## 4. Functions

```bash
myfunc() {
  echo "Hello $1"
}
myfunc "World"
```

## 5. Script Execution

- `bash script.sh` → run script
- `chmod +x script.sh` → make script executable
- `./script.sh` → run executable script

## 6. Others

- `echo "text"` → print text
- `read var` → take user input
- `$(command)` → command substitution
- `command` → (also command substitution)
- `&&` → run next command only if previous succeeds
- `||` → run next command only if previous fails
- `;` → run multiple commands sequentially
