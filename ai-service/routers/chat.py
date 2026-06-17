"""
Chat Router — endpoint chatbot tư vấn.
"""

from __future__ import annotations

from fastapi import APIRouter, HTTPException

from models.schemas import ChatRequest, AIResponse
from services import chat_service

router = APIRouter(tags=["Chat"])


@router.post("/chat", response_model=AIResponse)
async def chat(payload: ChatRequest) -> AIResponse:
    """
    Gửi tin nhắn cho chatbot AI.

    Hỗ trợ:
    - 3 providers: chatgpt, gemini, openrouter
    - Context: khóa học, danh mục, khóa đang xem
    - History: lịch sử hội thoại (gửi kèm từ client)
    """
    if not payload.api_key:
        raise HTTPException(
            status_code=400,
            detail="Thiếu API key cho provider AI. Vui lòng cấu hình API key trong phần Cài đặt.",
        )

    provider = (payload.provider or "chatgpt").strip().lower()
    if provider not in {"chatgpt", "gemini", "openrouter"}:
        raise HTTPException(
            status_code=400,
            detail=f"Provider không hỗ trợ: {provider}",
        )

    # Xác định role dựa trên thông tin có thể có trong request
    role = None  # Laravel sẽ truyền role trong tương lai

    reply, tokens = await chat_service.chat(payload, role=role)
    if not reply:
        raise HTTPException(
            status_code=502,
            detail="Provider AI không trả về nội dung.",
        )

    return AIResponse(reply=reply, tokens_used=tokens)
