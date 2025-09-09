from django.contrib.auth.models import User, Group
from mozilla_django_oidc.auth import OIDCAuthenticationBackend

class KeycloakOIDCBackend(OIDCAuthenticationBackend):
    """
    - Uses preferred_username/email from Keycloak
    - Optionally mirrors Keycloak realm roles into Django groups
    """
    def create_user(self, claims):
        username = claims.get("preferred_username") or claims.get("email") or claims.get("sub")
        user = User.objects.create_user(username=username)
        return self.update_user(user, claims)

    def update_user(self, user, claims):
        user.email = claims.get("email", "") or user.email
        user.first_name = claims.get("given_name", "") or user.first_name
        user.last_name = claims.get("family_name", "") or user.last_name
        user.save()

        # Map realm roles (from access token) into Django groups
        # mozilla-django-oidc passes claims from the ID token/userinfo by default.
        # We’ll look for an injected 'realm_roles' if present, else skip.
        realm_roles = claims.get("realm_roles", [])
        if realm_roles:
            # Create missing groups and sync membership
            groups = []
            for r in realm_roles:
                g, _ = Group.objects.get_or_create(name=f"kc:{r}")
                groups.append(g)
            user.groups.set(groups)
        return user

    def get_username(self, claims):
        return claims.get("preferred_username") or claims.get("email") or claims.get("sub")

    def filter_users_by_claims(self, claims):
        """Try lookup by email first, then username."""
        email = claims.get("email")
        if email:
            qs = User.objects.filter(email__iexact=email)
            if qs.exists():
                return qs
        username = self.get_username(claims)
        if username:
            return User.objects.filter(username__iexact=username)
        return self.UserModel.objects.none()