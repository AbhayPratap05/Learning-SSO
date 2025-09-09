# from rest_framework.authentication import BaseAuthentication
# from rest_framework.exceptions import AuthenticationFailed
# from django.conf import settings
# from jwt import PyJWKClient
# import jwt


# class KeycloakJWTAuthentication(BaseAuthentication):
#     """
#     Custom DRF authentication backend that verifies JWTs from Keycloak
#     using the realm's JWKS endpoint.
#     """

#     def authenticate(self, request):
#         auth_header = request.headers.get("Authorization")
#         if not auth_header or not auth_header.startswith("Bearer "):
#             return None  # no token → DRF continues to next authentication backend

#         token = auth_header.split(" ", 1)[1]

#         try:
#             jwks_url = settings.KEYCLOAK_ISSUER.rstrip("/") + "/protocol/openid-connect/certs"
#             jwks_client = PyJWKClient(jwks_url)
#             signing_key = jwks_client.get_signing_key_from_jwt(token)

#             decoded = jwt.decode(
#             token,
#             signing_key.key,
#             algorithms=["RS256"],
#             audience=[settings.OIDC_RP_CLIENT_ID, "account"],  # allow multiple audiences
#         )

#         except Exception as e:
#             raise AuthenticationFailed(f"Invalid token: {str(e)}")

#         # You could map Keycloak user info → Django User model here
#         # For now, we just return a simple user-like object
#         user = type("User", (), decoded)()
#         return (user, None)