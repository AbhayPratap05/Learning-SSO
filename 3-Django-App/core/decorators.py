from functools import wraps
from django.http import JsonResponse
from django.shortcuts import redirect
import jwt

def require_kc_role(role_name):
    """
    Restrict access to views based on Keycloak realm roles.
    """
    def decorator(view_func):
        @wraps(view_func)
        def _wrapped_view(request, *args, **kwargs):
            # Get access token from session
            token = request.session.get("oidc_access_token")
            if not token:
                return redirect("oidc_authentication_init")

            try:
                # Decode token without verifying signature (for demo)
                claims = jwt.decode(token, options={"verify_signature": False})
                roles = claims.get("realm_access", {}).get("roles", [])
            except Exception:
                return JsonResponse({"Error": "Invalid token"})

            if role_name in roles:
                return view_func(request, *args, **kwargs)
            return JsonResponse({"Missing required role:":role_name})
        return _wrapped_view
    return decorator