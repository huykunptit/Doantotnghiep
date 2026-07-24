"""
Helpers kiểm tra provider / API key.
"""

from __future__ import annotations

from fastapi import HTTPException

SUPPORTED_PROVIDERS = frozenset({"chatgpt", "gemini", "openrouter", "claude", "ollama"})
LOCAL_PROVIDERS = frozenset({"ollama"})


def normalize_provider(provider: str | None, default: str = "chatgpt") -> str:
    return (provider or default).strip().lower()


def require_provider(provider: str | None) -> str:
    name = normalize_provider(provider)
    if name not in SUPPORTED_PROVIDERS:
        raise HTTPException(
            status_code=400,
            detail=f"Provider không hỗ trợ: {provider}",
        )
    return name


def require_api_key(provider: str | None, api_key: str | None) -> None:
    """Ollama chạy local — không bắt API key."""
    name = normalize_provider(provider)
    if name in LOCAL_PROVIDERS:
        return
    if not api_key:
        raise HTTPException(
            status_code=400,
            detail="Thiếu API key cho provider AI. Vui lòng cấu hình trong phần Quản lý AI.",
        )
