from django.urls import path
from django.contrib.auth.views import LogoutView
from mozilla_django_oidc.views import (
    OIDCAuthenticationRequestView,
    OIDCAuthenticationCallbackView,
    OIDCLogoutView,
)
from . import views

urlpatterns = [
    path("", views.home, name="home"),
    path("profile/", views.profile, name="profile"),
    path("call-api/", views.call_api, name="call_api"),
    # path("protected-api/", views.protected_api, name="protected_api"),
    path("manager/", views.manager_dashboard, name="manager_dashboard"),

    # OIDC endpoints
    path("oidc/authenticate/", OIDCAuthenticationRequestView.as_view(), name="oidc_authentication_init"),
    path("oidc/callback/", OIDCAuthenticationCallbackView.as_view(), name="oidc_authentication_callback"),
    path("logout/", views.keycloak_logout, name="logout"),
]