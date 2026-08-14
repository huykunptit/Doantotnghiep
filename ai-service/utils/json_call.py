"""Gọi LLM lấy JSON; sai định dạng thì gọi lại 1 lần với prompt rút gọn."""

from __future__ import annotations

import logging
from collections.abc import Callable

from fastapi import HTTPException

from services.provider import call_provider
from utils.json_extract import extract_json_object

logger = logging.getLogger(__name__)

JsonValidator = Callable[[dict], bool]


async def complete_json(
    *,
    provider: str,
    api_key: str,
    model: str | None,
    messages: list[dict[str, str]],
    short_messages: list[dict[str, str]],
    temperature: float,
    is_valid: JsonValidator,
) -> dict:
    """
    Gọi provider, parse JSON. Nếu không đúng schema thì retry đúng 1 lần
    với prompt rút gọn. Vẫn sai → HTTP 502 để tầng trên fallback bộ luật.
    """
    reply, _ = await call_provider(
        provider=provider,
        api_key=api_key,
        messages=messages,
        model=model,
        temperature=temperature,
    )
    raw = extract_json_object(reply) if reply else None
    if raw is not None and is_valid(raw):
        return raw

    logger.warning("Model JSON invalid or empty; retrying once with shortened prompt")
    retry_reply, _ = await call_provider(
        provider=provider,
        api_key=api_key,
        messages=short_messages,
        model=model,
        temperature=min(temperature, 0.2),
    )
    retry_raw = extract_json_object(retry_reply) if retry_reply else None
    if retry_raw is not None and is_valid(retry_raw):
        return retry_raw

    raise HTTPException(
        status_code=502,
        detail="Model response was not valid JSON after retry.",
    )
