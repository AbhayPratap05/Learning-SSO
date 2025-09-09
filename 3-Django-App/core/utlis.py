import time, requests, jwt
from django.conf import settings
from jwt import PyJWKClient

def get_valid_access_token(request):
    """
    Ensure we always return a valid access token.
    If expired, use the refresh token to get a new one.
    """
    access_token = request.session.get("oidc_access_token")
    refresh_token = request.session.get("oidc_refresh_token")

    # Check if access token exists and is still valid
    if access_token:
        try:
            claims = jwt.decode(access_token, options={"verify_signature": False})
            exp = claims.get("exp", 0)
            if exp > time.time() + 30:  # 30s buffer
                return access_token
        except Exception:
            pass  # token invalid -> refresh

    # If we reach here -> try refreshing
    if not refresh_token:
        return None

    # Refresh using Keycloak token endpoint
    token_endpoint = settings.KEYCLOAK_ISSUER.rstrip("/") + "/protocol/openid-connect/token"
    data = {
        "grant_type": "refresh_token",
        "refresh_token": refresh_token,
        "client_id": settings.OIDC_RP_CLIENT_ID,
        "client_secret": settings.OIDC_RP_CLIENT_SECRET,
    }
    r = requests.post(token_endpoint, data=data, timeout=10)
    if r.ok:
        tokens = r.json()
        request.session["oidc_access_token"] = tokens.get("access_token")
        if tokens.get("refresh_token"):
            request.session["oidc_refresh_token"] = tokens.get("refresh_token")
        return tokens.get("access_token")

    return None
