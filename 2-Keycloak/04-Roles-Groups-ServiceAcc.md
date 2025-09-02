# Roles, Groups, Scopes & Service Accounts

## 1. Roles & Groups

Keycloak attach roles (permissions) to users or groups, and they show up inside the Access Token.

### Steps:

- Realm Roles -> Create Role -> Name: admin.
- Create another: editor.
- Go to Users -> testuser -> Role Mappings -> Assign admin.

The role will be visible in Token as:

```
"realm_access": {
  "roles": ["admin"]
}
```

## 2. Client Scopes

Used to add additional information in tokens (e.g, subscription=premium)

### Steps:

- Client Scopes -> Create -> Name: custom-claims.
- Mappers -> Add Mapper → “User Attribute”.
  - Name: subscription
  - User Attribute: subscription
  - Claim JSON Type: String
  - Token Claim Name: subscription
  - Add to Access Token
- Users -> testuser -> Attributes -> Add Attribute: subscription=premium.
- Clients -> django-app -> Client Scopes -> Add custom-claims

The role will be visible in Token as:

```
"department": "IT"
```

# 3. Service Accounts

Using this, apps can talk to each other without user login. (e.g, Django backend calling a PHP API)

### Steps:

- Clients -> Create -> Name: service-client
  - Client Type: OpenID Connect.
  - Client Authentication (On)
  - Direct Access Grants: (Off)
  - Service Accounts (On)
- Credentials tab -> copy the client-secret.

### Test

```bash
curl -X POST \
  http://localhost:8080/realms/myrealm/protocol/openid-connect/token \
  -d "grant_type=client_credentials" \
  -d "client_id=service-client" \
  -d "client_secret=YOUR_SECRET"
```

Response will include an access token, but no id_token (since no user is involved).
That token can be used to call APIs.
