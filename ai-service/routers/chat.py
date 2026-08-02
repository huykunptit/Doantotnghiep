"""
Chat Router — endpoint chatbot tư vấn (+ RAG giáo trình cho sinh viên).
"""

from __future__ import annotations

from fastapi import APIRouter, HTTPException

from models.schemas import ChatRequest, AIResponse
from services import chat_service
from utils.providers import require_api_key, require_provider

router = APIRouter(tags=["Chat"])


@router.post("/chat", response_model=AIResponse)
async def chat(payload: ChatRequest) -> AIResponse:
    """
    Gửi tin nhắn cho chatbot AI.

    Hỗ trợ:
    - providers: chatgpt, gemini, openrouter, claude, ollama
    - Context: khóa học, danh mục, khóa đang xem
    - History: lịch sử hội thoại
    - RAG: truy xuất giáo trình PTIT (khi đã ingest) cho role student
    """
    provider = require_provider(payload.provider)
    require_api_key(provider, payload.api_key)

    reply, tokens, rag_used, sources = await chat_service.chat(payload, role=payload.role)
    if not reply:
        raise HTTPException(
            status_code=502,
            detail="Provider AI không trả về nội dung.",
        )

    return AIResponse(
        reply=reply,
        tokens_used=tokens,
        rag_used=rag_used,
        sources=sources,
    )
