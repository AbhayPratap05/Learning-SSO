# Keycloak concepts

## 1. What is SSO?

- Single Sign-On (SSO) means: one login = access to multiple apps (like google acc. gives access to yt, drive, gmail,etc)

## 2. What is Keycloak?

- Keycloak is an open-source Identity & Access Management (IAM) tool.
- Handles:
  - Authentication -> login, password.
  - Authorization -> who can access what (roles, groups).
  - Identity Brokering -> login with Google, GitHub, etc.
  - SSO -> multiple apps, one login.

## 3. Keycloak Concepts

### a. Realms

- Acts as a security boundary.
- Each realm has its own users, clients, roles, identity providers.

### b. Clients

- **Client Type**

  - OpenID Connect (OIDC): modern, widely used.
  - SAML: used in enterprise.

- **Client ID**

  - Apps use this when talking to Keycloak.

- **Capability Config**

  - **Client authentication**: App has a secret to prove itself. (important for backend apps)
  - **Authorization**: Keycloak to manages fine grained permissions (optional)
  - **Standard flow**: Browser login (OIDC Authorization Code Flow).
  - **Implicit flow**: Legacy, insecure.
  - **Direct access grants**: Allow password grant (curl login)
  - **Service accounts**: If app needs to talk to Keycloak without users (machine-to-machine).

- **Client Tabs**

  - **Settings**: Core options (redirect URIs, protocol, authentication).
  - **Roles**: Define app-specific roles (e.g., editor, viewer). Users/groups can be mapped to them.
  - **Client** Scopes: Control what data goes into tokens (e.g., email, profile, etc.).
  - **Sessions**: Shows active user sessions for this client. Useful for debugging.
  - **Advanced**: Token lifespans, fine-tuned security configs.
