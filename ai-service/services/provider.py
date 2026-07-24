"""
AI Provider Abstraction Layer.

Hỗ trợ: OpenAI (ChatGPT), Google Gemini, OpenRouter, Anthropic Claude, Ollama (local).
Sử dụng httpx async cho non-blocking I/O.
"""

from __future__ import annotations

import logging

import httpx
from fastapi import HTTPException

from config import settings
from models.schemas import EMPTY_TOKENS, TokenUsage

logger = logging.getLogger(__name__)


# =============================================================================
# Provider interface
# =============================================================================


async def call_provider(
    provider: str,
    api_key: str,
    messages: list[dict[str, str]],
    model: str | None = None,
    temperature: float | None = None,
) -> tuple[str | None, TokenUsage]:
    """
    Gọi AI provider và trả về (reply_text, token_usage).

    Args:
        provider: "chatgpt" | "gemini" | "openrouter" | "claude" | "ollama"
        api_key: API key (bỏ qua với ollama)
        messages: [{"role": "system"|"user"|"assistant", "content": "..."}]
        model: Tên model (None → default)
        temperature: Nhiệt độ

    Returns:
        (reply_text, {"prompt": int, "completion": int, "total": int})
    """
    provider = provider.strip().lower()
    temp = temperature if temperature is not None else settings.DEFAULT_TEMPERATURE

    try:
        if provider == "chatgpt":
            return await _call_openai(api_key, messages, model, temp)
        if provider == "gemini":
            return await _call_gemini(api_key, messages, model, temp)
        if provider == "openrouter":
            return await _call_openrouter(api_key, messages, model, temp)
        if provider == "claude":
            return await _call_claude(api_key, messages, model, temp)
        if provider == "ollama":
            return await _call_ollama(messages, model, temp)
        raise HTTPException(
            status_code=400,
            detail=f"Provider không hỗ trợ: {provider}",
        )
    except HTTPException:
        raise
    except httpx.HTTPStatusError as exc:
        detail = exc.response.text[:500] if exc.response else str(exc)
        logger.error("Provider %s HTTP error: %s", provider, detail)
        raise HTTPException(status_code=502, detail=f"{provider} HTTP error: {detail}") from exc
    except Exception as exc:
        logger.error("Provider %s request failed: %s", provider, exc)
        raise HTTPException(status_code=502, detail=f"{provider} request failed: {exc}") from exc


# =============================================================================
# OpenAI (ChatGPT)
# =============================================================================


async def _call_openai(
    api_key: str,
    messages: list[dict[str, str]],
    model: str | None,
    temperature: float,
) -> tuple[str | None, TokenUsage]:
    """Gọi OpenAI Chat Completions API."""
    body = {
        "model": model or settings.DEFAULT_OPENAI_MODEL,
        "messages": messages,
        "temperature": temperature,
    }

    async with httpx.AsyncClient() as client:
        response = await client.post(
            settings.OPENAI_API_URL,
            json=body,
            headers={
                "Content-Type": "application/json",
                "Authorization": f"Bearer {api_key}",
            },
            timeout=settings.OPENAI_TIMEOUT,
        )
        response.raise_for_status()
        data = response.json()

    reply = data["choices"][0]["message"]["content"].strip()
    usage = data.get("usage", {})
    tokens: TokenUsage = {
        "prompt": usage.get("prompt_tokens", 0),
        "completion": usage.get("completion_tokens", 0),
        "total": usage.get("total_tokens", 0),
    }
    return reply, tokens


# =============================================================================
# Google Gemini
# =============================================================================


async def _call_gemini(
    api_key: str,
    messages: list[dict[str, str]],
    model: str | None,
    temperature: float,
) -> tuple[str | None, TokenUsage]:
    """Gọi Google Gemini API."""
    model_name = model or settings.DEFAULT_GEMINI_MODEL
    url = f"{settings.GEMINI_API_URL}/{model_name}:generateContent?key={api_key}"

    system_text = ""
    user_parts = []
    for msg in messages:
        if msg["role"] == "system":
            system_text += msg["content"] + "\n"
        else:
            user_parts.append({"text": msg["content"]})

    body: dict = {
        "contents": [{"role": "user", "parts": user_parts}],
        "generationConfig": {"temperature": temperature},
    }
    if system_text.strip():
        body["system_instruction"] = {"parts": [{"text": system_text.strip()}]}

    async with httpx.AsyncClient() as client:
        response = await client.post(
            url,
            json=body,
            headers={"Content-Type": "application/json"},
            timeout=settings.GEMINI_TIMEOUT,
        )
        response.raise_for_status()
        data = response.json()

    candidates = data.get("candidates") or []
    if not candidates:
        return None, {**EMPTY_TOKENS}

    parts = candidates[0].get("content", {}).get("parts", [])
    text = "\n".join(
        part.get("text", "").strip() for part in parts if part.get("text")
    )

    usage_meta = data.get("usageMetadata", {})
    prompt_tokens = usage_meta.get("promptTokenCount", 0)
    completion_tokens = usage_meta.get("candidatesTokenCount", 0)
    tokens: TokenUsage = {
        "prompt": prompt_tokens,
        "completion": completion_tokens,
        "total": prompt_tokens + completion_tokens,
    }
    return text.strip() or None, tokens


# =============================================================================
# OpenRouter
# =============================================================================


async def _call_openrouter(
    api_key: str,
    messages: list[dict[str, str]],
    model: str | None,
    temperature: float,
) -> tuple[str | None, TokenUsage]:
    """Gọi OpenRouter API (tương thích OpenAI format)."""
    body = {
        "model": model or settings.DEFAULT_OPENROUTER_MODEL,
        "messages": messages,
        "temperature": temperature,
    }

    async with httpx.AsyncClient() as client:
        response = await client.post(
            settings.OPENROUTER_API_URL,
            json=body,
            headers={
                "Content-Type": "application/json",
                "Authorization": f"Bearer {api_key}",
                "HTTP-Referer": "http://localhost",
                "X-Title": "Sylva LMS",
            },
            timeout=settings.OPENROUTER_TIMEOUT,
        )
        response.raise_for_status()
        data = response.json()

    reply = data["choices"][0]["message"]["content"].strip()
    usage = data.get("usage", {})
    tokens: TokenUsage = {
        "prompt": usage.get("prompt_tokens", 0),
        "completion": usage.get("completion_tokens", 0),
        "total": usage.get("total_tokens", 0),
    }
    return reply, tokens


# =============================================================================
# Anthropic Claude
# =============================================================================


async def _call_claude(
    api_key: str,
    messages: list[dict[str, str]],
    model: str | None,
    temperature: float,
) -> tuple[str | None, TokenUsage]:
    """Gọi Anthropic Claude Messages API."""
    system_text = ""
    claude_messages: list[dict[str, str]] = []
    for msg in messages:
        if msg["role"] == "system":
            system_text += msg["content"] + "\n"
        else:
            claude_messages.append({"role": msg["role"], "content": msg["content"]})

    body: dict = {
        "model": model or settings.DEFAULT_CLAUDE_MODEL,
        "max_tokens": 2048,
        "temperature": temperature,
        "messages": claude_messages,
    }
    if system_text.strip():
        body["system"] = system_text.strip()

    async with httpx.AsyncClient() as client:
        response = await client.post(
            settings.CLAUDE_API_URL,
            json=body,
            headers={
                "Content-Type": "application/json",
                "x-api-key": api_key,
                "anthropic-version": settings.CLAUDE_API_VERSION,
            },
            timeout=settings.CLAUDE_TIMEOUT,
        )
        response.raise_for_status()
        data = response.json()

    content_blocks = data.get("content") or []
    reply = "\n".join(
        block.get("text", "").strip()
        for block in content_blocks
        if block.get("type") == "text"
    ).strip()

    usage = data.get("usage", {})
    prompt_tokens = usage.get("input_tokens", 0)
    completion_tokens = usage.get("output_tokens", 0)
    tokens: TokenUsage = {
        "prompt": prompt_tokens,
        "completion": completion_tokens,
        "total": prompt_tokens + completion_tokens,
    }
    return reply or None, tokens


# =============================================================================
# Ollama (local — OpenAI-compatible /v1)
# =============================================================================


async def _call_ollama(
    messages: list[dict[str, str]],
    model: str | None,
    temperature: float,
) -> tuple[str | None, TokenUsage]:
    """Gọi Ollama local qua OpenAI-compatible API."""
    base = settings.OLLAMA_BASE_URL.rstrip("/")
    url = f"{base}/v1/chat/completions"
    body = {
        "model": model or settings.DEFAULT_OLLAMA_MODEL,
        "messages": messages,
        "temperature": temperature,
        "stream": False,
    }

    async with httpx.AsyncClient() as client:
        response = await client.post(
            url,
            json=body,
            headers={"Content-Type": "application/json"},
            timeout=settings.OLLAMA_TIMEOUT,
        )
        response.raise_for_status()
        data = response.json()

    choices = data.get("choices") or []
    if not choices:
        return None, {**EMPTY_TOKENS}

    reply = (choices[0].get("message") or {}).get("content") or ""
    reply = reply.strip()
    usage = data.get("usage") or {}
    prompt_tokens = int(usage.get("prompt_tokens") or 0)
    completion_tokens = int(usage.get("completion_tokens") or 0)
    tokens: TokenUsage = {
        "prompt": prompt_tokens,
        "completion": completion_tokens,
        "total": int(usage.get("total_tokens") or (prompt_tokens + completion_tokens)),
    }
    return reply or None, tokens
