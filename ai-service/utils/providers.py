"""
Helpers kiểm tra provider / API key.
"""

from __future__ import annotations

import os

from fastapi import HTTPException

SUPPORTED_PROVIDERS = frozenset({"chatgpt", "gemini", "openrouter", "claude", "ollama"})
LOCAL_PROVIDERS = frozenset({"ollama"})

_ENV_KEY_BY_PROVIDER = {
    "chatgpt": "OPENAI_API_KEY",
    "gemini": "GEMINI_API_KEY",
    "openrouter": "OPENROUTER_API_KEY",
    "claude": "CLAUDE_API_KEY",
}


def normalize_provider(provider: str | None, default: str = "chatgpt") -> str:
    return (provider or default).strip().lower()


def resolve_api_key(provider: str, api_key: str | None) -> str:
    """Ưu tiên key trong request, sau đó lấy từ env của ai-service."""
    key = (api_key or "").strip()
    if key and key != "local":
        return key
    env_name = _ENV_KEY_BY_PROVIDER.get(provider)
    if env_name:
        from_env = (os.getenv(env_name) or "").strip()
        if from_env:
            return from_env
    # ChatGPT/Codex cùng proxy nghimmo: dùng chung key Claude nếu chưa tách key.
    if provider == "chatgpt":
        return (os.getenv("CLAUDE_API_KEY") or "").strip()
    return ""


def require_provider(provider: str | None) -> str:
    name = normalize_provider(provider)
    if name not in SUPPORTED_PROVIDERS:
        raise HTTPException(
            status_code=400,
            detail=f"Provider không hỗ trợ: {provider}",
        )
    return name


def require_api_key(provider: str | None, api_key: str | None) -> None:
    """Ollama chạy local — không bắt API key. Provider cloud: request hoặc env."""
    name = normalize_provider(provider)
    if name in LOCAL_PROVIDERS:
        return
    if not resolve_api_key(name, api_key):
        raise HTTPException(
            status_code=400,
            detail="Thiếu API key cho provider AI. Điền OPENAI_API_KEY / GEMINI_API_KEY / CLAUDE_API_KEY trong backend/.env hoặc cấu hình /admin/ai.",
        )
