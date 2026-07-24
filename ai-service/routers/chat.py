"""
Chat Router — endpoint chatbot tư vấn.
"""

from __future__ import annotations

from fastapi import APIRouter, HTTPException

from models.schemas import ChatRequest, AIResponse
from services import chat_service
from utils.providers import require_api_key, require_provider
    if not reply:
        raise HTTPException(
            status_code=502,
            detail="Provider AI không trả về nội dung.",
        )

    return AIResponse(reply=reply, tokens_used=tokens)
