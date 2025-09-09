from django.contrib.auth.decorators import login_required
from django.contrib.auth import logout as django_logout
from django.http import JsonResponse
from django.shortcuts import render, redirect
from django.conf import settings
from urllib.parse import urlencode
from django.shortcuts import redirect
from .decorators import require_kc_role
from .utlis import get_valid_access_token

import requests, jwt

from rest_framework.decorators import api_view, permission_classes
from rest_framework.permissions import IsAuthenticated
from rest_framework.response import Response

def home(request):
    return render(request, "home.html")

@login_required
def profile(request):
    """
    Show user info + roles/claims (decoded from ID token or access token).
    """
    session = request.session
    id_token = session.get("oidc_id_token")
    access_token = session.get("oidc_access_token")

    id_claims = {}
    access_claims = {}
    if id_token:
        # Display only; skip signature verification here (already done at login)
        id_claims = jwt.decode(id_token, options={"verify_signature": False, "verify_aud": False})
    if access_token:
        access_claims = jwt.decode(access_token, options={"verify_signature": False, "verify_aud": False})

    # Roles: Keycloak puts them in access token under 'realm_access' and 'resource_access'
    realm_roles = access_claims.get("realm_access", {}).get("roles", [])
    resource_access = access_claims.get("resource_access", {}).get("account", {}).get("roles", [])

    ctx = {
        "user": request.user,
        "id_claims": id_claims,
        "access_claims": access_claims,
        "realm_roles": realm_roles,
        "resource_access": resource_access,
    }
    return render(request, "profile.html", ctx)

@login_required
@require_kc_role("admin")
def call_api(request):
    """
    Call our Django protected API with a valid token.
    """
    token = get_valid_access_token(request)
    if not token:
        return redirect("oidc_authentication_init")

    # api_url = request.build_absolute_uri("/protected-api/")
    api_url = "https://httpbin.org/bearer"
    resp = requests.get(
    api_url,
    headers={"Authorization": f"Bearer {token}"},
    timeout=10
    )
    print("Using token:", token)
    return JsonResponse({
            "status": resp.status_code,
            "data": resp.json() if resp.ok else resp.text,
        })

@login_required
@require_kc_role("manager")
def manager_dashboard(request):
    return JsonResponse({"msg": "Welcome Manager! This is the Manager's Dashboard"})

@login_required
def keycloak_logout(request):
    # Clear Django session
    django_logout(request)

    # Build Keycloak logout URL
    base_url = f"{settings.KEYCLOAK_ISSUER}/protocol/openid-connect/logout"
    params = {
        "client_id": settings.OIDC_RP_CLIENT_ID,
        "post_logout_redirect_uri": request.build_absolute_uri("/"),
    }
    logout_url = f"{base_url}?{urlencode(params)}"

    return redirect(logout_url)

# @api_view(["GET"])
# @permission_classes([IsAuthenticated])
# def protected_api(request):
#     return Response({
#         "message": "Hello, you are authenticated!",
#         "claims": {k: getattr(request.user, k) for k in dir(request.user) if not k.startswith("_") and not callable(getattr(request.user, k))}
#     })