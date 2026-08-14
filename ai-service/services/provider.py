"""
AI Provider Abstraction Layer.

Hỗ trợ: OpenAI (ChatGPT), Google Gemini, OpenRouter, Anthropic Claude, Ollama (local).
Sử dụng httpx async cho non-blocking I/O.
"""

from __future__ import annotations

import json
import logging
from typing import AsyncGenerator

import httpx
from fastapi import HTTPException

from config import settings
from models.schemas import EMPTY_TOKENS, TokenUsage
from utils.providers import resolve_api_key

logger = logging.getLogger(__name__)

_MAINTENANCE_MARKERS = (
    "đang bảo trì",
    "under maintenance",
    "tách trà",
    "cup of tea",
    "stay calm and enjoy",
)


def _is_maintenance_reply(reply: str | None) -> bool:
    text = (reply or "").lower()
    return bool(text) and any(marker in text for marker in _MAINTENANCE_MARKERS)


def _reject_placeholder_reply(provider: str, reply: str | None) -> str | None:
    if _is_maintenance_reply(reply):
        raise HTTPException(
            status_code=503,
            detail=f"{provider} trả về thông báo bảo trì/placeholder, thử provider khác.",
        )
    return reply


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
    api_key = resolve_api_key(provider, api_key)

    try:
        if provider == "chatgpt":
            reply, tokens = await _call_openai(api_key, messages, model, temp)
            return _reject_placeholder_reply(provider, reply), tokens
        if provider == "gemini":
            reply, tokens = await _call_gemini(api_key, messages, model, temp)
            return _reject_placeholder_reply(provider, reply), tokens
        if provider == "openrouter":
            reply, tokens = await _call_openrouter(api_key, messages, model, temp)
            return _reject_placeholder_reply(provider, reply), tokens
        if provider == "claude":
            reply, tokens = await _call_claude(api_key, messages, model, temp)
            return _reject_placeholder_reply(provider, reply), tokens
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
                "X-Title": "Eript LMS",
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
    """Gọi Claude Messages API (Anthropic official hoặc proxy như nghimmo)."""
    system_text = ""
    claude_messages: list[dict[str, str]] = []
    for msg in messages:
        if msg["role"] == "system":
            system_text += msg["content"] + "\n"
        else:
            claude_messages.append({"role": msg["role"], "content": msg["content"]})

    # Một số gateway yêu cầu ít nhất 1 user message
    if not claude_messages:
        claude_messages = [{"role": "user", "content": "Hello"}]

    body: dict = {
        "model": model or settings.DEFAULT_CLAUDE_MODEL,
        "max_tokens": 2048,
        "temperature": temperature,
        "messages": claude_messages,
        "stream": False,
    }
    if system_text.strip():
        body["system"] = system_text.strip()

    headers = {
        "Content-Type": "application/json",
        "anthropic-version": settings.CLAUDE_API_VERSION,
    }
    auth_mode = (settings.CLAUDE_AUTH_MODE or "bearer").strip().lower()
    if auth_mode == "x-api-key":
        headers["x-api-key"] = api_key
    else:
        # Gateway bên thứ 3 (nghimmo, …) thường dùng Bearer
        headers["Authorization"] = f"Bearer {api_key}"

    async with httpx.AsyncClient() as client:
        response = await client.post(
            settings.CLAUDE_API_URL,
            json=body,
            headers=headers,
            timeout=settings.CLAUDE_TIMEOUT,
        )
        response.raise_for_status()
        data = response.json()

    # Anthropic format
    content_blocks = data.get("content") or []
    if content_blocks:
        reply = "\n".join(
            block.get("text", "").strip()
            for block in content_blocks
            if block.get("type") == "text"
        ).strip()
        usage = data.get("usage", {})
        prompt_tokens = usage.get("input_tokens", 0)
        completion_tokens = usage.get("output_tokens", 0)
    else:
        # Một số proxy trả OpenAI-compatible envelope
        choices = data.get("choices") or []
        reply = ""
        if choices:
            reply = ((choices[0].get("message") or {}).get("content") or "").strip()
        usage = data.get("usage") or {}
        prompt_tokens = usage.get("prompt_tokens", 0)
        completion_tokens = usage.get("completion_tokens", 0)

    tokens: TokenUsage = {
        "prompt": int(prompt_tokens or 0),
        "completion": int(completion_tokens or 0),
        "total": int(prompt_tokens or 0) + int(completion_tokens or 0),
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


# =============================================================================
# Streaming — dùng cho /chat/stream (SSE tới client theo thời gian thực)
# =============================================================================


async def stream_provider(
    provider: str,
    api_key: str,
    messages: list[dict[str, str]],
    model: str | None = None,
    temperature: float | None = None,
) -> AsyncGenerator[dict, None]:
    """
    Gọi AI provider ở chế độ streaming.

    Yields:
        {"delta": "..."} cho từng đoạn văn bản mới sinh ra, theo đúng thứ tự.
        {"usage": TokenUsage} duy nhất 1 lần, ở cuối (có thể toàn 0 nếu provider
        không trả usage khi stream).
    """
    provider = provider.strip().lower()
    temp = temperature if temperature is not None else settings.DEFAULT_TEMPERATURE
    api_key = resolve_api_key(provider, api_key)

    try:
        if provider == "chatgpt":
            gen = _stream_openai(api_key, messages, model, temp)
        elif provider == "gemini":
            gen = _stream_gemini(api_key, messages, model, temp)
        elif provider == "openrouter":
            gen = _stream_openrouter(api_key, messages, model, temp)
        elif provider == "claude":
            gen = _stream_claude(api_key, messages, model, temp)
        elif provider == "ollama":
            gen = _stream_ollama(messages, model, temp)
        else:
            raise HTTPException(status_code=400, detail=f"Provider không hỗ trợ: {provider}")

        accumulated = ""
        async for item in gen:
            delta = item.get("delta") if isinstance(item, dict) else None
            if isinstance(delta, str) and delta:
                accumulated += delta
                if _is_maintenance_reply(delta) or _is_maintenance_reply(accumulated):
                    raise HTTPException(
                        status_code=503,
                        detail=f"{provider} trả về thông báo bảo trì/placeholder, thử provider khác.",
                    )
            yield item
    except HTTPException:
        raise
    except httpx.HTTPStatusError as exc:
        detail = exc.response.text[:500] if exc.response else str(exc)
        logger.error("Provider %s stream HTTP error: %s", provider, detail)
        raise HTTPException(status_code=502, detail=f"{provider} HTTP error: {detail}") from exc
    except Exception as exc:
        logger.error("Provider %s stream failed: %s", provider, exc)
        raise HTTPException(status_code=502, detail=f"{provider} stream failed: {exc}") from exc


async def _stream_openai_compatible(
    provider: str,
    url: str,
    headers: dict[str, str],
    body: dict,
    timeout: float,
) -> AsyncGenerator[dict, None]:
    """SSE parser dùng chung cho các API tương thích OpenAI (chatgpt/openrouter/ollama)."""
    prompt_tokens = 0
    completion_tokens = 0
    async with httpx.AsyncClient() as client:
        async with client.stream("POST", url, json=body, headers=headers, timeout=timeout) as response:
            if response.status_code >= 400:
                error_body = (await response.aread()).decode("utf-8", "ignore")
                raise HTTPException(
                    status_code=502,
                    detail=f"{provider} HTTP error: HTTP {response.status_code}: {error_body[:500]}",
                )
            async for line in response.aiter_lines():
                line = line.strip()
                if not line or not line.startswith("data:"):
                    continue
                data = line[len("data:"):].strip()
                if data == "[DONE]" or not data:
                    continue
                try:
                    chunk = json.loads(data)
                except (json.JSONDecodeError, ValueError):
                    continue

                usage = chunk.get("usage")
                if usage:
                    prompt_tokens = usage.get("prompt_tokens", prompt_tokens)
                    completion_tokens = usage.get("completion_tokens", completion_tokens)

                choices = chunk.get("choices") or []
                if choices:
                    delta = (choices[0].get("delta") or {}).get("content")
                    if delta:
                        yield {"delta": delta}

    yield {"usage": {
        "prompt": prompt_tokens,
        "completion": completion_tokens,
        "total": prompt_tokens + completion_tokens,
    }}


async def _stream_openai(
    api_key: str,
    messages: list[dict[str, str]],
    model: str | None,
    temperature: float,
) -> AsyncGenerator[dict, None]:
    body = {
        "model": model or settings.DEFAULT_OPENAI_MODEL,
        "messages": messages,
        "temperature": temperature,
        "stream": True,
        "stream_options": {"include_usage": True},
    }
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {api_key}",
    }
    async for item in _stream_openai_compatible("chatgpt", settings.OPENAI_API_URL, headers, body, settings.OPENAI_TIMEOUT):
        yield item


async def _stream_openrouter(
    api_key: str,
    messages: list[dict[str, str]],
    model: str | None,
    temperature: float,
) -> AsyncGenerator[dict, None]:
    body = {
        "model": model or settings.DEFAULT_OPENROUTER_MODEL,
        "messages": messages,
        "temperature": temperature,
        "stream": True,
    }
    headers = {
        "Content-Type": "application/json",
        "Authorization": f"Bearer {api_key}",
        "HTTP-Referer": "http://localhost",
        "X-Title": "Eript LMS",
    }
    async for item in _stream_openai_compatible("openrouter", settings.OPENROUTER_API_URL, headers, body, settings.OPENROUTER_TIMEOUT):
        yield item


async def _stream_ollama(
    messages: list[dict[str, str]],
    model: str | None,
    temperature: float,
) -> AsyncGenerator[dict, None]:
    base = settings.OLLAMA_BASE_URL.rstrip("/")
    url = f"{base}/v1/chat/completions"
    body = {
        "model": model or settings.DEFAULT_OLLAMA_MODEL,
        "messages": messages,
        "temperature": temperature,
        "stream": True,
    }
    headers = {"Content-Type": "application/json"}
    async for item in _stream_openai_compatible("ollama", url, headers, body, settings.OLLAMA_TIMEOUT):
        yield item


async def _stream_gemini(
    api_key: str,
    messages: list[dict[str, str]],
    model: str | None,
    temperature: float,
) -> AsyncGenerator[dict, None]:
    model_name = model or settings.DEFAULT_GEMINI_MODEL
    url = f"{settings.GEMINI_API_URL}/{model_name}:streamGenerateContent?alt=sse&key={api_key}"

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

    prompt_tokens = 0
    completion_tokens = 0
    async with httpx.AsyncClient() as client:
        async with client.stream(
            "POST", url, json=body, headers={"Content-Type": "application/json"}, timeout=settings.GEMINI_TIMEOUT,
        ) as response:
            if response.status_code >= 400:
                error_body = (await response.aread()).decode("utf-8", "ignore")
                raise HTTPException(status_code=502, detail=f"gemini HTTP error: HTTP {response.status_code}: {error_body[:500]}")
            async for line in response.aiter_lines():
                line = line.strip()
                if not line or not line.startswith("data:"):
                    continue
                data = line[len("data:"):].strip()
                if not data:
                    continue
                try:
                    chunk = json.loads(data)
                except (json.JSONDecodeError, ValueError):
                    continue

                candidates = chunk.get("candidates") or []
                if candidates:
                    parts = candidates[0].get("content", {}).get("parts", [])
                    text = "".join(p.get("text", "") for p in parts if p.get("text"))
                    if text:
                        yield {"delta": text}

                usage_meta = chunk.get("usageMetadata")
                if usage_meta:
                    prompt_tokens = usage_meta.get("promptTokenCount", prompt_tokens)
                    completion_tokens = usage_meta.get("candidatesTokenCount", completion_tokens)

    yield {"usage": {
        "prompt": prompt_tokens,
        "completion": completion_tokens,
        "total": prompt_tokens + completion_tokens,
    }}


async def _stream_claude(
    api_key: str,
    messages: list[dict[str, str]],
    model: str | None,
    temperature: float,
) -> AsyncGenerator[dict, None]:
    system_text = ""
    claude_messages: list[dict[str, str]] = []
    for msg in messages:
        if msg["role"] == "system":
            system_text += msg["content"] + "\n"
        else:
            claude_messages.append({"role": msg["role"], "content": msg["content"]})
    if not claude_messages:
        claude_messages = [{"role": "user", "content": "Hello"}]

    body: dict = {
        "model": model or settings.DEFAULT_CLAUDE_MODEL,
        "max_tokens": 2048,
        "temperature": temperature,
        "messages": claude_messages,
        "stream": True,
    }
    if system_text.strip():
        body["system"] = system_text.strip()

    headers = {
        "Content-Type": "application/json",
        "anthropic-version": settings.CLAUDE_API_VERSION,
    }
    auth_mode = (settings.CLAUDE_AUTH_MODE or "bearer").strip().lower()
    if auth_mode == "x-api-key":
        headers["x-api-key"] = api_key
    else:
        headers["Authorization"] = f"Bearer {api_key}"

    prompt_tokens = 0
    completion_tokens = 0
    async with httpx.AsyncClient() as client:
        async with client.stream(
            "POST", settings.CLAUDE_API_URL, json=body, headers=headers, timeout=settings.CLAUDE_TIMEOUT,
        ) as response:
            if response.status_code >= 400:
                error_body = (await response.aread()).decode("utf-8", "ignore")
                raise HTTPException(status_code=502, detail=f"claude HTTP error: HTTP {response.status_code}: {error_body[:500]}")
            async for line in response.aiter_lines():
                line = line.strip()
                if not line or not line.startswith("data:"):
                    continue
                data = line[len("data:"):].strip()
                if not data:
                    continue
                try:
                    event = json.loads(data)
                except (json.JSONDecodeError, ValueError):
                    continue

                event_type = event.get("type")
                if event_type == "content_block_delta":
                    delta = (event.get("delta") or {}).get("text")
                    if delta:
                        yield {"delta": delta}
                elif event_type == "message_start":
                    usage = (event.get("message") or {}).get("usage") or {}
                    prompt_tokens = usage.get("input_tokens", prompt_tokens)
                elif event_type == "message_delta":
                    usage = event.get("usage") or {}
                    completion_tokens = usage.get("output_tokens", completion_tokens)
                elif event_type == "error":
                    err = event.get("error") or {}
                    raise HTTPException(status_code=502, detail=f"claude stream error: {err}")
                elif event_type is None and event.get("choices"):
                    # Một số gateway proxy (vd. nghimmo) trả envelope kiểu OpenAI thay vì Anthropic gốc.
                    choices = event.get("choices") or []
                    delta = (choices[0].get("delta") or {}).get("content") if choices else None
                    if delta:
                        yield {"delta": delta}
                    usage = event.get("usage")
                    if usage:
                        prompt_tokens = usage.get("prompt_tokens", prompt_tokens)
                        completion_tokens = usage.get("completion_tokens", completion_tokens)

    yield {"usage": {
        "prompt": prompt_tokens,
        "completion": completion_tokens,
        "total": prompt_tokens + completion_tokens,
    }}
