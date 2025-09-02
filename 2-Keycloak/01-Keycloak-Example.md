# Keycloak Example

## Run Keycloak in Docker (Dev Mode)

```bash
docker run -d \
  --name keycloak \
  -p 8080:8080 \
  -e KEYCLOAK_ADMIN=admin \
  -e KEYCLOAK_ADMIN_PASSWORD=password \
  quay.io/keycloak/keycloak:26.0 start-dev
```

- -d -> run in background.
- --name keycloak -> container name.
- -p 8080:8080 -> map container port 8080 to host 8080.
- -e KEYCLOAK_ADMIN=admin -> set admin username.
- -e KEYCLOAK_ADMIN_PASSWORD=admin -> set admin password.
- quay.io/keycloak/keycloak:26.0 start-dev -> Keycloak image in dev mode.

## Access Keycloak Admin Console

`http://localhost:8080`

## Create a Realm

- Left Panel -> Master -> Create Realm.
- Name realm (e.g, intern-realm) and Save.

## Create a User

- Left Panel -> Users -> Add user.
- Set Username
- Credentials -> set password

## Activate User

- `http://localhost:8080/realms/intern-realm/account`
- login with username and password
- Add the required user info.

## Create a Client

- Left Panel -> Clients -> Create client.
- Add Client ID (e.g, django-app)
- Select OpenID Connect.
- Add root url (e.g, http://localhost:8000/) --> (Base URL of the app)
- Add Redirect URL (e.g, http://localhost:8000/\*) --> (where user is redirected after login.)

## Test Login

```example
http://localhost:8080/realms/intern-realm/protocol/openid-connect/auth
```

- It will show Keycloak login page
- Login with testuser we just created.
- After success, it redirect to app http://localhost:8000/
