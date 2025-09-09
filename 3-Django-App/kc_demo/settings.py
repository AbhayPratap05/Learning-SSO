import os
from pathlib import Path
from dotenv import load_dotenv

BASE_DIR = Path(__file__).resolve().parent.parent
load_dotenv(BASE_DIR / ".env")

SECRET_KEY = os.getenv("SECRET_KEY", "dev-please-change")
DEBUG = os.getenv("DEBUG", "True") == "True"
ALLOWED_HOSTS = os.getenv("ALLOWED_HOSTS", "127.0.0.1,localhost").split(",")

INSTALLED_APPS = [
    "django.contrib.admin",
    "django.contrib.auth",
    "django.contrib.contenttypes",
    "django.contrib.sessions",
    "django.contrib.messages",
    "django.contrib.staticfiles",
    "rest_framework",
    # OIDC
    "mozilla_django_oidc",
    # app
    "core",
]

# REST_FRAMEWORK = {
#     "DEFAULT_AUTHENTICATION_CLASSES": [
#         "core.auth_backends.KeycloakJWTAuthentication",
#     ],
#     "DEFAULT_PERMISSION_CLASSES": [
#         "rest_framework.permissions.IsAuthenticated",
#     ],
# }

MIDDLEWARE = [
    "django.middleware.security.SecurityMiddleware",
    "django.contrib.sessions.middleware.SessionMiddleware",
    "django.middleware.common.CommonMiddleware",
    "django.middleware.csrf.CsrfViewMiddleware",
    "django.contrib.auth.middleware.AuthenticationMiddleware",
    "django.contrib.messages.middleware.MessageMiddleware",
    "django.middleware.clickjacking.XFrameOptionsMiddleware",
]

ROOT_URLCONF = "kc_demo.urls"
TEMPLATES = [
    {
        "BACKEND": "django.template.backends.django.DjangoTemplates",
        "DIRS": [BASE_DIR / "core" / "templates"],
        "APP_DIRS": True,
        "OPTIONS": {
            "context_processors": [
                "django.template.context_processors.debug",
                "django.template.context_processors.request",
                "django.contrib.auth.context_processors.auth",
                "django.contrib.messages.context_processors.messages",
            ],
        },
    },
]
WSGI_APPLICATION = "kc_demo.wsgi.application"

DATABASES = {"default": {"ENGINE": "django.db.backends.sqlite3", "NAME": BASE_DIR / "db.sqlite3"}}

STATIC_URL = "static/"

# -------- OIDC settings (mozilla-django-oidc) --------
KEYCLOAK_ISSUER = os.getenv("KEYCLOAK_ISSUER", "").rstrip("/")
OIDC_RP_CLIENT_ID = os.getenv("OIDC_CLIENT_ID")
OIDC_RP_CLIENT_SECRET = os.getenv("OIDC_CLIENT_SECRET")

# OIDC ENDPOINTS
OIDC_OP_AUTHORIZATION_ENDPOINT=f"{KEYCLOAK_ISSUER}/protocol/openid-connect/auth"
OIDC_OP_TOKEN_ENDPOINT=f"{KEYCLOAK_ISSUER}/protocol/openid-connect/token"
OIDC_OP_USER_ENDPOINT=f"{KEYCLOAK_ISSUER}/protocol/openid-connect/userinfo"
OIDC_OP_JWKS_ENDPOINT = f"{KEYCLOAK_ISSUER}/protocol/openid-connect/certs"

# Keycloak’s signing algo
OIDC_RP_SIGN_ALGO = "RS256"

# Common scopes;
OIDC_RP_SCOPES = "openid profile email"

# Store tokens
OIDC_STORE_ID_TOKEN = True
OIDC_STORE_ACCESS_TOKEN = True
OIDC_STORE_REFRESH_TOKEN = True

# Custom backend to map Keycloak claims to Django users
AUTHENTICATION_BACKENDS = [
    "django.contrib.auth.backends.ModelBackend",
    "core.auth.KeycloakOIDCBackend",
]

LOGIN_URL = "oidc_authentication_init"
LOGIN_REDIRECT_URL = "/"
LOGOUT_REDIRECT_URL = "/"

SESSION_COOKIE_SAMESITE = "Lax"
CSRF_TRUSTED_ORIGINS = ["http://127.0.0.1:8000", "http://localhost:8000"]